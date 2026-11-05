<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

/** Захист від змішаних параметрів призначення ТТН перед викликом save. */
final class DocumentRequestGuard
{
    public static function error(array $request): ?string
    {
        $props = $request['methodProperties'] ?? [];
        if (!is_array($props) || ($request['calledMethod'] ?? '') !== 'save') {
            return 'Некоректний запит на створення ТТН.';
        }
        $service = (string)($props['ServiceType'] ?? '');
        if ($service === 'WarehouseDoors') {
            foreach (['RecipientCityName', 'RecipientAddressName', 'RecipientHouse'] as $key) {
                if (trim((string)($props[$key] ?? '')) === '') {
                    return $key . ' is required for address delivery';
                }
            }
            if (!empty($props['RecipientAddress']) || !empty($props['CityRecipient'])) {
                return 'ServiceType: адресна доставка містить відділення/місто зі складської доставки. ТТН не відправлено.';
            }
        } elseif ($service === 'WarehouseWarehouse') {
            foreach (['CityRecipient', 'RecipientAddress'] as $key) {
                if (trim((string)($props[$key] ?? '')) === '') {
                    return $key . ' is required for warehouse delivery';
                }
            }
            if (!empty($props['RecipientAddressName']) || !empty($props['RecipientHouse'])) {
                return 'ServiceType: складська доставка містить вулицю або будинок від адресної доставки. ТТН не відправлено.';
            }
        } else {
            return 'ServiceType: unsupported delivery type';
        }
        return null;
    }
}
