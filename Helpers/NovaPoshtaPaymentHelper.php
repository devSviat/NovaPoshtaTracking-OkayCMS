<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

use Okay\Core\Modules\Extender\ExtenderFacade;

class NovaPoshtaPaymentHelper
{
    /**
     * Скільки взяти з отримувача контролем оплати чи грошовим переказом.
     *
     * Без розширень це вся сума замовлення. Модуль, що знає про вже внесені
     * кошти (аванс), зменшує її chain-розширенням; оголошена цінність при цьому
     * лишається повною.
     *
     * @param object $order
     * @param float $orderTotal сума замовлення у валюті Нової Пошти
     */
    public function amountDueOnPickup(object $order, float $orderTotal): float
    {
        return (float) ExtenderFacade::execute(__METHOD__, max(0.0, $orderTotal), func_get_args());
    }
}
