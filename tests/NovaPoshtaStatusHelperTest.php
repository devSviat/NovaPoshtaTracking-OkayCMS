<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaStatusHelper;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Статус ТТН менеджер бачить у списку замовлень і в картці. Незнайомий код не
 * має ламати сторінку: у списку з сотні замовлень достатньо одного нового коду
 * від Нової Пошти, щоб зіпсувати весь екран.
 */
class NovaPoshtaStatusHelperTest extends TestCase
{
    /** @var NovaPoshtaStatusHelper */
    private $helper;

    protected function setUp(): void
    {
        $this->helper = new NovaPoshtaStatusHelper();
    }

    /** @dataProvider knownStatusProvider */
    #[DataProvider('knownStatusProvider')]
    public function testKnownCodesGetTextAndBadge(string $code, string $text, string $badge): void
    {
        $this->assertSame($text, $this->helper->getStatusText($code));
        $this->assertSame($badge, $this->helper->getBadgeClass($code));
    }

    public static function knownStatusProvider(): array
    {
        return [
            'створено'    => ['1', 'Створено', 'np-status-badge--created'],
            'у дорозі'    => ['5', 'Прямує до міста', 'np-status-badge--in-transit'],
            'прибуло'     => ['7', 'Прибуло до відділення', 'np-status-badge--arrived'],
            'у поштоматі' => ['8', 'У поштоматі', 'np-status-badge--arrived'],
            'отримано'    => ['9', 'Отримано', 'np-status-badge--received'],
            'відмова'     => ['103', 'Відмова від отримання', 'np-status-badge--refused'],
            'повернення'  => ['106', 'Створено ЄН повернення', 'np-status-badge--return'],
        ];
    }

    /** Новий код від НП не має лишати менеджера без підказки. */
    public function testUnknownCodeStillRendersSomething(): void
    {
        $this->assertSame('Статус: 777', $this->helper->getStatusText('777'));
        $this->assertSame('np-status-badge--failed', $this->helper->getBadgeClass('777'));
    }

    public function testDisplayBundlesCodeTextAndBadge(): void
    {
        $this->assertSame(
            [
                'code' => '7',
                'text' => 'Прибуло до відділення',
                'badge_class' => 'np-status-badge--arrived',
            ],
            $this->helper->formatStatusForDisplay('7')
        );
    }

    /** Замовлення без ТТН статусу не має — і це не помилка. */
    /** @dataProvider emptyStatusProvider */
    #[DataProvider('emptyStatusProvider')]
    public function testMissingStatusGivesNull($code): void
    {
        $this->assertNull($this->helper->formatStatusForDisplay($code));
    }

    public static function emptyStatusProvider(): array
    {
        return [
            'null'           => [null],
            'порожній рядок' => [''],
            'нуль'           => ['0'],
        ];
    }

    /** Коди 4 і 41 — той самий стан, тож і виглядати мають однаково. */
    public function testCityOfDepartureCodesAreEquivalent(): void
    {
        $this->assertSame($this->helper->getStatusText('4'), $this->helper->getStatusText('41'));
        $this->assertSame($this->helper->getBadgeClass('4'), $this->helper->getBadgeClass('41'));
    }
}
