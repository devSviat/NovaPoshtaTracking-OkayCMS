<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Compat;

/**
 * Логін менеджера, залогіненого в адмінці, або null.
 *
 * Порт: єдине місце, де модуль залежить від того, як рушій зберігає бекендову
 * сесію. Реалізацію обирає Init/services.php — один раз і за можливостями,
 * бо за номером версії рушії не розрізнити: обидва звуть себе 4.5.2.
 */
interface AdminIdentity
{
    public function login(): ?string;
}
