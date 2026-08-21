<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Накладну, виписану вручну на сайті Нової Пошти, менеджер привʼязує до
 * замовлення номером. Номер він переносить очима або копіює разом із
 * пробілами й дефісами, тож нормалізація — не косметика.
 *
 * Друга частина — розпізнати, що такого номера в Новій Пошті немає. Це не
 * очевидно: на вигаданий номер API відповідає `success: true` і віддає
 * документ зі `StatusCode` 3 («Номер не знайдено»). Тобто перевірка за
 * `success` пропустила б помилку друку, і в базі осіла б накладна, якої не
 * існує — а вона потрапляє і в експорт замовлень, і до покупця.
 */
class ManualDocumentAttachTest extends TestCase
{
    /** @dataProvider acceptedNumberProvider */
    #[DataProvider('acceptedNumberProvider')]
    public function testNumberIsNormalizedToFourteenDigits(string $typed, string $expected): void
    {
        $this->assertSame($expected, NovaPoshtaDocumentService::normalizeDocumentNumber($typed));
    }

    public static function acceptedNumberProvider(): array
    {
        return [
            'як є'            => ['20451495020198', '20451495020198'],
            'з пробілами'     => [' 20451495020198 ', '20451495020198'],
            'розбитий групами' => ['2045 1495 0201 98', '20451495020198'],
            'через дефіси'    => ['20451495-020198', '20451495020198'],
        ];
    }

    /** @dataProvider rejectedNumberProvider */
    #[DataProvider('rejectedNumberProvider')]
    public function testRubbishIsRejected(string $typed): void
    {
        $this->assertNull(NovaPoshtaDocumentService::normalizeDocumentNumber($typed));
    }

    public static function rejectedNumberProvider(): array
    {
        return [
            'порожній'       => [''],
            'самі пробіли'   => ['   '],
            'закоротко'      => ['2045149502019'],
            'задовго'        => ['204514950201987'],
            'з літерами'     => ['2045149502019X'],
            'номер телефону' => ['380975977200'],
        ];
    }

    /**
     * `StatusCode` 3 — це відповідь «такого номера немає», а не помилка
     * запиту. Саме на ній стоїть відмова привʼязати.
     *
     * @dataProvider documentProvider
     */
    #[DataProvider('documentProvider')]
    public function testUnknownDocumentIsRecognised(bool $expected, $statusCode): void
    {
        $document = (object) ['Number' => '20451495020198', 'StatusCode' => $statusCode];

        $this->assertSame($expected, NovaPoshtaDocumentService::isUnknownDocument($document));
    }

    public static function documentProvider(): array
    {
        return [
            'номер не знайдено'      => [true, '3'],
            'той самий код числом'   => [true, 3],
            'відправлення отримано'  => [false, '9'],
            'у дорозі'               => [false, '5'],
            'код відсутній'          => [true, null],
        ];
    }

    /** Порожня відповідь — теж «не знайдено», а не привід зберегти запис. */
    public function testMissingDocumentIsUnknown(): void
    {
        $this->assertTrue(NovaPoshtaDocumentService::isUnknownDocument(null));
    }
}
