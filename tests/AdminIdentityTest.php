<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Modules\Sviat\NovaPoshtaTracking\Services\AdminIdentity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\SharedSessionAdminIdentity;
use PHPUnit\Framework\TestCase;

/**
 * Логін менеджера для рушія з однією сесією.
 *
 * Другий адаптер (окрема бекендова сесія) тут не перевіряється свідомо: у
 * ньому немає власної логіки — лише делегування ядру, яке саме читає чужу
 * сесію. Перевіряти нічого, а підняти таку сесію в модульному тесті
 * неможливо. Вся логіка, яка може помилитися, зібрана в цьому адаптері.
 */
class AdminIdentityTest extends TestCase
{
    private $sessionBackup;

    protected function setUp(): void
    {
        $this->sessionBackup = $_SESSION ?? null;
    }

    protected function tearDown(): void
    {
        if ($this->sessionBackup === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->sessionBackup;
        }
    }

    public function testAdapterFulfilsThePort(): void
    {
        self::assertInstanceOf(AdminIdentity::class, new SharedSessionAdminIdentity());
    }

    public function testLoginIsReadFromTheSession(): void
    {
        $_SESSION = ['admin' => 'manager'];

        self::assertSame('manager', (new SharedSessionAdminIdentity())->login());
    }

    /**
     * null, а не порожній рядок: ManagersEntity::get('') знайшов би першого-
     * ліпшого менеджера, і перевірка прав пропустила б анонімний запит.
     *
     * @dataProvider notLoggedIn
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('notLoggedIn')]
    public function testAbsentOrUnusableLoginIsNull($session): void
    {
        if ($session === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $session;
        }

        self::assertNull((new SharedSessionAdminIdentity())->login());
    }

    public static function notLoggedIn(): array
    {
        return [
            'сесії немає'        => [null],
            'ключа немає'        => [[]],
            'порожній рядок'     => [['admin' => '']],
            'не рядок'           => [['admin' => 0]],
            'масив'              => [['admin' => ['x']]],
        ];
    }
}
