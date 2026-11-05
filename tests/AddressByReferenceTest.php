<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\AddressNames;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\DocumentRequestGuard;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaApiHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Адресна ТТН з довідника НП.
 *
 * Адресу, передану текстом (InternetDocumentGeneral), НП приймає, але редактор
 * кабінету показує її поля порожніми. Тому адреса створюється через
 * AddressGeneral.save з Ref вулиці, а текст лишається запасним шляхом.
 */
class AddressByReferenceTest extends TestCase
{
    private const string CITY = 'db5c88f5-391c-11dd-90d9-001a92567626';
    private const string SETTLEMENT = 'e71abb60-4b33-11e4-ab6d-005056801329';
    private const string STREET = '8ceab873-4146-11dd-9198-001d60451983';
    private const string ADDRESS = '01a1aadb-87f7-7002-90a5-ab5c56a9a009';

    /** @var list<array> */
    private array $calls = [];

    #[DataProvider('cityProvider')]
    public function testCityFromStoredValue(string $stored, string $area, array $expected): void
    {
        $this->assertSame($expected, AddressNames::city($stored, $area));
    }

    public static function cityProvider(): array
    {
        return [
            'звичайна назва'          => ['Київ', '', ['city' => 'Київ', 'area' => '', 'region' => '']],
            'текст підказки'          => ['м. Київ, Київська обл.', '', ['city' => 'Київ', 'area' => 'Київська', 'region' => '']],
            'підказка з районом'      => ['м. Миколаїв, Стрийський р-н, Львівська обл.', '', ['city' => 'Миколаїв', 'area' => 'Львівська', 'region' => 'Стрийський']],
            'село'                    => ['с. Київець, Миколаївський р-н, Львівська обл.', '', ['city' => 'Київець', 'area' => 'Львівська', 'region' => 'Миколаївський']],
            'збережена область важить' => ['м. Львів, Львівська обл.', 'Інша', ['city' => 'Львів', 'area' => 'Інша', 'region' => '']],
        ];
    }

    #[DataProvider('streetProvider')]
    public function testStreetCore(string $street, string $core, ?string $type): void
    {
        $this->assertSame($core, AddressNames::streetCore($street));
        $this->assertSame($type, AddressNames::streetType($street));
    }

    public static function streetProvider(): array
    {
        return [
            'вул. спереду'       => ['вул. Городоцька', 'городоцька', 'вул'],
            'тип після назви'    => ['Городоцька вул.', 'городоцька', 'вул'],
            'повне слово'        => ['вулиця Шевченка', 'шевченка', 'вул'],
            'проспект'           => ['просп. Свободи', 'свободи', 'просп'],
            'без типу'           => ['Івасюка', 'івасюка', null],
            'апостроф'           => ['вул. Об’їзна', "об'їзна", 'вул'],
            'назва, що починається з «пл»' => ['Плиткова', 'плиткова', null],
        ];
    }

    public function testPicksTheOnlyMatchingStreet(): void
    {
        $candidates = [
            $this->street('a', 'Городоцька', 'вул.'),
            $this->street('b', 'Городоцька Нова', 'вул.'),
        ];

        $this->assertSame('a', AddressNames::pickStreet($candidates, 'Городоцька'));
    }

    public function testStreetTypeResolvesSameNames(): void
    {
        $candidates = [$this->street('a', 'Городоцька', 'вул.'), $this->street('b', 'Городоцька', 'пров.')];

        $this->assertSame('b', AddressNames::pickStreet($candidates, 'пров. Городоцька'));
        $this->assertNull(AddressNames::pickStreet($candidates, 'Городоцька'), 'без типу вибір неоднозначний');
    }

    public function testNoStreetWhenNothingMatches(): void
    {
        $this->assertNull(AddressNames::pickStreet([$this->street('a', 'Шевченка', 'вул.')], 'Городоцька'));
        $this->assertNull(AddressNames::pickStreet([], 'Городоцька'));
    }

    public function testAddressIsCreatedFromDirectoryAndSentByRef(): void
    {
        $service = $this->service($this->api());
        [$recipient, $notice] = $this->resolve($service, $this->delivery());

        $this->assertNull($notice);
        $this->assertSame(self::CITY, $recipient['city_ref']);
        $this->assertSame(self::ADDRESS, $recipient['address_ref']);

        $save = $this->callsTo('AddressGeneral', 'save')[0]['methodProperties'];
        $this->assertSame(['CounterpartyRef' => 'recipient-ref', 'StreetRef' => self::STREET, 'BuildingNumber' => '10', 'Flat' => '5', 'Note' => 'під’їзд 2'], $save);

        $props = $this->buildRequest($service, $recipient);
        $this->assertSame(self::CITY, $props['CityRecipient']);
        $this->assertSame(self::ADDRESS, $props['RecipientAddress']);
        $this->assertSame('5', $props['NumberOfFloorsLifting']);
        $this->assertArrayNotHasKey('RecipientCityName', $props);
        $this->assertArrayNotHasKey('RecipientAddressName', $props);
        $this->assertNull(DocumentRequestGuard::error(['calledMethod' => 'save', 'methodProperties' => $props]));
    }

    /** Ref населеного пункту з чекауту переводиться в Ref міста через DeliveryCity. */
    public function testSettlementRefIsTranslatedToCityRef(): void
    {
        $service = $this->service($this->api(cityIsSettlement: true));
        [$recipient] = $this->resolve($service, ['city_id' => self::SETTLEMENT] + $this->delivery());

        $this->assertSame(self::CITY, $recipient['city_ref']);
    }

    public function testUnknownStreetFallsBackToTextWithNotice(): void
    {
        $service = $this->service($this->api(streetFound: false));
        [$recipient, $notice] = $this->resolve($service, $this->delivery());

        $this->assertNull($recipient);
        $this->assertStringContainsString('Вулицю «вул. Городоцька»', $notice);
        $this->assertSame([], $this->callsTo('AddressGeneral', 'save'), 'адресу без вулиці не створюємо');

        $props = $this->buildRequest($service, []);
        $this->assertSame('Львів', $props['RecipientCityName']);
        $this->assertArrayNotHasKey('CityRecipient', $props);
    }

    public function testRejectedAddressFallsBackToText(): void
    {
        $service = $this->service($this->api(addressSaved: false));
        [$recipient, $notice] = $this->resolve($service, $this->delivery());

        $this->assertNull($recipient);
        $this->assertStringContainsString("Street doesn't exists", $notice);
    }

    /** Текстовий шлях: місто з тексту підказки НП не знаходить («RecipientCityName not found»). */
    public function testTextAddressGetsCleanCityName(): void
    {
        $service = $this->service($this->api());
        $props = $this->buildRequest($service, [], ['city_name' => 'м. Львів, Львівська обл.', 'area_name' => '']);

        $this->assertSame('Львів', $props['RecipientCityName']);
        $this->assertSame('Львівська', $props['RecipientArea']);
    }

    /** Масове створення: та сама вулиця не шукається в НП повторно. */
    public function testLookupsAreReusedWithinRun(): void
    {
        $service = $this->service($this->api());
        $this->resolve($service, $this->delivery());
        $this->resolve($service, ['house' => '12'] + $this->delivery());

        $this->assertCount(1, $this->callsTo('AddressGeneral', 'getStreet'));
        $this->assertCount(2, $this->callsTo('AddressGeneral', 'save'));
    }

    public function testGuardRejectsRefsMixedWithText(): void
    {
        $props = ['ServiceType' => 'WarehouseDoors', 'CityRecipient' => self::CITY, 'RecipientAddress' => self::ADDRESS,
            'Recipient' => 'r', 'ContactRecipient' => 'c', 'RecipientAddressName' => 'Городоцька'];

        $this->assertStringContainsString('ServiceType', DocumentRequestGuard::error(['calledMethod' => 'save', 'methodProperties' => $props]));
    }

    private function api(bool $cityIsSettlement = false, bool $streetFound = true, bool $addressSaved = true): NovaPoshtaApiHelper
    {
        $api = $this->createStub(NovaPoshtaApiHelper::class);
        $api->method('addCounterparty')->willReturn((object) [
            'success' => true,
            'data' => [(object) ['Ref' => 'recipient-ref', 'ContactPerson' => (object) ['data' => [(object) ['Ref' => 'contact-ref']]]]],
        ]);
        $api->method('getErrorMessage')->willReturnCallback(fn ($r) => implode('; ', $r->errors ?? []));
        $api->method('sendApiRequest')->willReturnCallback(function (array $request) use ($cityIsSettlement, $streetFound, $addressSaved) {
            $this->calls[] = $request;
            $p = $request['methodProperties'];
            return match ($request['modelName'] . '.' . $request['calledMethod']) {
                'AddressGeneral.getCities' => (object) ['success' => true, 'data' => $cityIsSettlement ? [] : [(object) ['Ref' => $p['Ref']]]],
                'Address.searchSettlements' => (object) ['success' => true, 'data' => [(object) ['Addresses' => [
                    (object) ['Ref' => self::SETTLEMENT, 'DeliveryCity' => self::CITY, 'MainDescription' => 'Львів', 'Area' => 'Львівська'],
                ]]]],
                'AddressGeneral.getStreet' => (object) ['success' => true, 'data' => $streetFound
                    ? [$this->street(self::STREET, 'Городоцька', 'вул.')] : []],
                'AddressGeneral.save' => $addressSaved
                    ? (object) ['success' => true, 'data' => [(object) ['Ref' => self::ADDRESS]]]
                    : (object) ['success' => false, 'errors' => ["Street doesn't exists"], 'data' => []],
                default => (object) ['success' => false, 'errors' => ['unexpected'], 'data' => []],
            };
        });
        return $api;
    }

    private function service(NovaPoshtaApiHelper $api): NovaPoshtaDocumentService
    {
        $service = new ReflectionClass(NovaPoshtaDocumentService::class)->newInstanceWithoutConstructor();
        new ReflectionProperty($service, 'apiHelper')->setValue($service, $api);
        new ReflectionProperty($service, 'settings')->setValue($service, $this->createStub(Settings::class));
        new ReflectionProperty($service, 'addressLookups')->setValue($service, []);
        return $service;
    }

    private function resolve(NovaPoshtaDocumentService $service, array $delivery): array
    {
        return new ReflectionMethod($service, 'resolveRecipientAddress')->invoke(
            $service,
            (object) ['id' => 77, 'name' => 'Андрій', 'last_name' => 'Шевчук'],
            (object) $delivery,
            ['recipient_address_note' => 'під’їзд 2'],
            '380000000001'
        );
    }

    private function buildRequest(NovaPoshtaDocumentService $service, array $recipientData, array $delivery = []): array
    {
        $formData = [
            'payer_type' => 'Recipient', 'payment_method' => 'Cash', 'cargo_type' => 'Cargo', 'door_delivery' => 1,
            'lifting_floor' => '5', 'pickup_locker' => 0, 'additional_information' => '', 'recipient_address_note' => '',
            'control_payment' => 0, 'amount_due' => 0.0,
        ];
        $sender = ['contact_person' => (object) ['Ref' => 'contact'], 'sender' => (object) ['Ref' => 'sender']];
        $request = new ReflectionMethod($service, 'buildDocumentRequest')->invoke(
            $service, 'WarehouseDoors', $formData, (object) ($delivery + ['city_id' => ''] + $this->delivery()),
            (object) ['id' => 77, 'name' => 'Андрій', 'last_name' => 'Шевчук'], $sender, $recipientData,
            '380000000001', '06.11.2026', 1799, [], ['volume' => '0.0004', 'weight' => '0.5'], 0.5, 0.0004
        );
        return $request['methodProperties'];
    }

    private function delivery(): array
    {
        return ['city_id' => self::CITY, 'city_name' => 'Львів', 'area_name' => 'Львівська', 'region_name' => '',
            'street' => 'вул. Городоцька', 'house' => '10', 'apartment' => '5'];
    }

    private function street(string $ref, string $name, string $type): object
    {
        return (object) ['Ref' => $ref, 'Description' => $name, 'StreetsType' => $type];
    }

    private function callsTo(string $model, string $method): array
    {
        return array_values(array_filter($this->calls, fn ($c) => $c['modelName'] === $model && $c['calledMethod'] === $method));
    }
}
