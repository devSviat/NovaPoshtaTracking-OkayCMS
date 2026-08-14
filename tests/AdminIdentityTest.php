<?php

namespace Modules\Sviat\NovaPoshtaTracking;

use Okay\Modules\Sviat\NovaPoshtaTracking\Services\AdminIdentity;
use PHPUnit\Framework\TestCase;

/**
 * Перевіряється гілка спільної сесії — уся логіка, яка може помилитися, саме
 * в ній. Друга гілка делегує читання ядру, власної логіки не має, а підняти
 * чужу бекендову сесію в модульному тесті неможливо.
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

    /** Рушій зі спільною сесією — незалежно від того, на якому йде прогін. */
    private function sharedSession(): AdminIdentity
    {
        return new class extends AdminIdentity {
            protected function hasSeparateBackendSession(): bool
            {
                return false;
            }
        };
    }

    public function testLoginIsReadFromTheSession(): void
    {
        $_SESSION = ['admin' => 'manager'];

        self::assertSame('manager', $this->sharedSession()->login());
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

        self::assertNull($this->sharedSession()->login());
    }

    public static function notLoggedIn(): array
    {
        return [
            'сесії немає'    => [null],
            'ключа немає'    => [[]],
            'порожній рядок' => [['admin' => '']],
            'не рядок'       => [['admin' => 0]],
            'масив'          => [['admin' => ['x']]],
        ];
    }
}
