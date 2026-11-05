<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Core\Settings;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Дата відправлення в ТТН: сьогодні до «часу відправки сьогодні», далі — завтра.
 *
 * У магазині, де налаштування модуля ще не зберігали, час дорівнює null, і
 * strtotime(null) на PHP 8.5 сипав deprecation на кожну ТТН.
 */
class ShipmentDateTest extends TestCase
{
    #[DataProvider('tomorrowProvider')]
    public function testTomorrowWhenCutoffMissingOrPassed(?string $cutoff): void
    {
        $this->assertSame(date('d.m.Y', time() + 86400), $this->shipmentDate($cutoff));
    }

    public static function tomorrowProvider(): array
    {
        return [
            'налаштування не збережене' => [null],
            'порожнє'                   => [''],
            'нерозпізнаний час'         => ['не час'],
            'час уже минув'             => ['00:00'],
        ];
    }

    public function testTodayBeforeCutoff(): void
    {
        $this->assertSame(date('d.m.Y'), $this->shipmentDate('23:59:59'));
    }

    private function shipmentDate(?string $cutoff): string
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn($cutoff);
        $service = new ReflectionClass(NovaPoshtaDocumentService::class)->newInstanceWithoutConstructor();
        new ReflectionProperty($service, 'settings')->setValue($service, $settings);

        $deprecations = [];
        set_error_handler(function (int $no, string $message) use (&$deprecations) {
            $deprecations[] = $message;
            return true;
        }, E_DEPRECATED);
        try {
            $date = new ReflectionMethod($service, 'getShipmentDate')->invoke($service);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        return $date;
    }
}
