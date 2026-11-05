<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\DocumentIssueTranslator;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\RecipientAddressAudit;
use PHPUnit\Framework\TestCase;

/**
 * Що оператор бачить після відповіді НП: звірка створеної ТТН і тексти помилок.
 *
 * Документ нижче — скорочена справжня відповідь InternetDocument.getDocument
 * на адресну ТТН (тестовий ключ, 2026-11-05). ServiceType і CityRecipient
 * приходять підписами, машинні значення — у *Ref-полях.
 */
class CreatedDocumentCheckTest extends TestCase
{
    private const string CITY = 'db5c88f5-391c-11dd-90d9-001a92567626';

    public function testRealAddressDocumentIsVerified(): void
    {
        $result = RecipientAddressAudit::inspect($this->addressDocument(), 'WarehouseDoors', $this->order());

        $this->assertSame(['status' => 'verified'], $result);
    }

    /** Перша версія звірки порівнювала підпис «Відділення-Адреса» з WarehouseDoors. */
    public function testLocalisedServiceTypeAloneIsNotTakenAsMismatch(): void
    {
        $document = $this->addressDocument();
        unset($document->ServiceTypeRef);

        $this->assertSame('unverified', RecipientAddressAudit::inspect($document, 'WarehouseDoors', $this->order())['status']);
    }

    public function testDifferentServiceTypeIsMismatch(): void
    {
        $document = $this->addressDocument();
        $document->ServiceTypeRef = 'WarehouseWarehouse';

        $this->assertSame('mismatch', RecipientAddressAudit::inspect($document, 'WarehouseDoors', $this->order())['status']);
    }

    public function testDifferentCityIsMismatch(): void
    {
        $document = $this->addressDocument();
        $document->CityRecipientRef = '8d5a980d-391c-11dd-90d9-001a92567626';

        $this->assertSame('mismatch', RecipientAddressAudit::inspect($document, 'WarehouseDoors', $this->order())['status']);
    }

    public function testAddressWithoutRefNeedsReview(): void
    {
        $document = $this->addressDocument();
        $document->RecipientAddressRef = '00000000-0000-0000-0000-000000000000';

        $this->assertSame('unverified', RecipientAddressAudit::inspect($document, 'WarehouseDoors', $this->order())['status']);
    }

    public function testOtherHouseNeedsReview(): void
    {
        $order = $this->order();
        $order->house = '1';

        $this->assertSame('unverified', RecipientAddressAudit::inspect($this->addressDocument(), 'WarehouseDoors', $order)['status']);
    }

    public function testStreetPrefixInOrderIsIgnored(): void
    {
        $order = $this->order();
        $order->street = 'вул. Городоцька';

        $this->assertSame('verified', RecipientAddressAudit::inspect($this->addressDocument(), 'WarehouseDoors', $order)['status']);
    }

    /** Ці попередження НП додає майже до кожної ТТН — оператору їх не показуємо. */
    public function testRoutineWarningsAreDropped(): void
    {
        $this->assertSame([], DocumentIssueTranslator::destinationWarnings([
            'CargoType is changed to Parcel',
            'RecipientType is set to PrivatePerson',
            'VolumeWeight is changed',
            'Weight is changed',
        ]));
    }

    public function testServiceTypeWarningIsKept(): void
    {
        $issues = DocumentIssueTranslator::destinationWarnings(['ServiceType is changed to WarehouseWarehouse']);

        $this->assertCount(1, $issues);
        $this->assertSame('delivery_mode', $issues[0]['field']);
    }

    /** Без договору на контроль оплати НП відповідає саме так. */
    public function testPaymentControlUnavailableTellsWhatToDo(): void
    {
        $issue = DocumentIssueTranslator::explain('AfterpaymentOnGoodsCost is unavailable');

        $this->assertSame('control_payment', $issue['field']);
        $this->assertStringContainsString('Вимкніть «Контроль оплати»', $issue['message']);
        $this->assertStringContainsString('AfterpaymentOnGoodsCost is unavailable', $issue['message']);
    }

    public function testOriginalTextIsKeptForSupport(): void
    {
        $issue = DocumentIssueTranslator::explain('RecipientContactName contains invalid words');

        $this->assertSame('recipient_name', $issue['field']);
        $this->assertStringContainsString('«RecipientContactName contains invalid words»', $issue['message']);
    }

    /** Екранують шаблон і JS; тут екранування дало б «&lt;» на екрані. */
    public function testMessagesAreNotHtmlEscapedTwice(): void
    {
        $issue = DocumentIssueTranslator::explain('Something <odd> & new');

        $this->assertStringContainsString('<odd> & new', $issue['message']);
    }

    public function testPhonesAndKeysAreMasked(): void
    {
        $issue = DocumentIssueTranslator::explain('Unknown key 0123456789abcdef0123456789abcdef for 380671234567');

        $this->assertStringNotContainsString('380671234567', $issue['message']);
        $this->assertStringNotContainsString('0123456789abcdef0123456789abcdef', $issue['message']);
    }

    public function testUkrainianModuleMessageMapsToItsField(): void
    {
        $message = 'Для адресної доставки не вказано вулицю.';

        $this->assertSame(['field' => 'street', 'message' => 'Не заповнено або неправильно вибрано вулицю одержувача. У блоці доставки виберіть вулицю з довідника Нової пошти та збережіть замовлення.'], DocumentIssueTranslator::explain($message));
    }

    private function addressDocument(): object
    {
        return (object) [
            'Ref' => '01a1aab7-79bd-777c-9bf1-e43acba9cac6',
            'IntDocNumber' => '20451579136961',
            'ServiceType' => 'Відділення-Адреса',
            'ServiceTypeRef' => 'WarehouseDoors',
            'CityRecipient' => 'Львів',
            'CityRecipientRef' => self::CITY,
            'RecipientAddress' => 'Городоцька буд. 10, кв. 5',
            'RecipientAddressRef' => '01a1aab7-79cb-7370-8781-1368e91ec8a3',
        ];
    }

    private function order(): object
    {
        return (object) ['city_id' => self::CITY, 'city_name' => 'Львів', 'street' => 'Городоцька', 'house' => '10', 'apartment' => '5'];
    }
}
