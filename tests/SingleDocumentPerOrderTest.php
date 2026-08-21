<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Одне замовлення — одна накладна, незалежно від того, звідки вона взялась.
 *
 * Кнопка створення гаслась по `ref_id`, а його має лише накладна, зроблена
 * через API: привʼязана вручну лишала кнопку живою, і другим кліком у Новій
 * Пошті зʼявлялась зайва накладна на ту саму посилку. Ознака наявності —
 * номер, а не `ref_id`.
 */
class SingleDocumentPerOrderTest extends TestCase
{
    private const TEMPLATE = 'Okay/Modules/Sviat/NovaPoshtaTracking/Backend/design/html/document_form.tpl';

    /** @dataProvider trackingRowProvider */
    #[DataProvider('trackingRowProvider')]
    public function testDocumentIsRecognisedByItsNumber(bool $expected, $row): void
    {
        $this->assertSame($expected, NovaPoshtaDocumentService::hasDocument($row));
    }

    public static function trackingRowProvider(): array
    {
        return [
            'створена через API' => [true, (object) ['int_doc_number' => '20451495020198', 'ref_id' => 'abc']],
            'привʼязана вручну'  => [true, (object) ['int_doc_number' => '20451495020198', 'ref_id' => null]],
            'порожній номер'     => [false, (object) ['int_doc_number' => '', 'ref_id' => null]],
            'номера немає'       => [false, (object) ['ref_id' => 'abc']],
            'рядка немає'        => [false, null],
            'findOne нічого не знайшов' => [false, false],
        ];
    }

    /**
     * Обидві кнопки замикає та сама ознака, інакше одна з них лишиться живою.
     *
     * @dataProvider buttonProvider
     */
    #[DataProvider('buttonProvider')]
    public function testBothButtonsAreLockedByTheSameFlag(string $buttonId): void
    {
        $markup = $this->markup();
        $button = substr($markup, strpos($markup, $buttonId));
        $button = substr($button, 0, strpos($button, '</button>'));

        $this->assertStringContainsString(
            '{if $np_has_document}disabled{/if}',
            $button,
            $buttonId . ': кнопка лишається активною, коли накладна вже є'
        );
    }

    public static function buttonProvider(): array
    {
        return [
            'створити'  => ['fn_generate_document'],
            'привʼязати' => ['fn_attach_toggle'],
        ];
    }

    /** Ознака мусить бути номером: `ref_id` порожній у накладної, привʼязаної вручну. */
    public function testFlagComesFromTheDocumentNumber(): void
    {
        $this->assertMatchesRegularExpression(
            '~\{assign\s+var="np_has_document"\s+value=[^}]*int_doc_number~',
            $this->markup()
        );
    }

    private function markup(): string
    {
        return file_get_contents(dirname(__DIR__, 4) . '/' . self::TEMPLATE);
    }
}
