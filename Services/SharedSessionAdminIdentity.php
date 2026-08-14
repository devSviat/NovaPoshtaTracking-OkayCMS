<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Services;

/**
 * Рушій з однією сесією на вітрину й адмінку: логін менеджера лежить
 * у $_SESSION['admin'] — саме його очікує ManagersEntity::get().
 */
class SharedSessionAdminIdentity implements AdminIdentity
{
    public function login(): ?string
    {
        $login = $_SESSION['admin'] ?? null;

        return is_string($login) && $login !== '' ? $login : null;
    }
}
