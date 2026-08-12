<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Compat;

use Okay\Core\Security\SessionNames;

/**
 * Рушій, де сесії вітрини й адмінки — різні куки. Сесія вітрини бекендового
 * логіна не бачить узагалі, тож читає його ядро, окремо від поточної сесії.
 *
 * Клас завантажується лише там, де SessionNames існує: вибір робить
 * Init/services.php, а PHP підвантажує клас лише коли він справді потрібен.
 */
class SeparateSessionAdminIdentity implements AdminIdentity
{
    public function login(): ?string
    {
        return SessionNames::adminLogin();
    }
}
