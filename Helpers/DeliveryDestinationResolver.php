<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

/**
 * Єдине джерело типу призначення ТТН для форми й API.
 * Не виправляє дані NPCostDeliveryData у БД: старий warehouse_id може залишатися
 * як історичне значення, але ніколи не потрапляє в адресну ТТН.
 */
final class DeliveryDestinationResolver
{
    /**
     * @param object|null $deliveryData Рядок NPCostDeliveryDataEntity
     * @param array $deliverySettings Налаштування активного способу доставки замовлення
     * @param string|null $requestedMode Явний вибір адміністратора: address/warehouse/locker
     * @return array{mode:string,service_type:string,pickup_locker:int}
     */
    public static function resolve($deliveryData, array $deliverySettings = [], ?string $requestedMode = null): array
    {
        if (!is_object($deliveryData)) {
            throw new \InvalidArgumentException('Дані Нової пошти для замовлення відсутні. Перевірте спосіб доставки в замовленні.');
        }

        $rowService = trim((string)($deliveryData->service_type ?? ''));
        $settingsService = trim((string)($deliverySettings['service_type'] ?? ''));
        $validServices = ['WarehouseDoors', 'WarehouseWarehouse'];
        foreach ([$rowService, $settingsService] as $service) {
            if ($service !== '' && !in_array($service, $validServices, true)) {
                throw new \InvalidArgumentException('Непідтримуваний ServiceType (' . $service . '). Перевірте спосіб доставки Нової пошти.');
            }
        }
        if ($rowService !== '' && $settingsService !== '' && $rowService !== $settingsService) {
            throw new \InvalidArgumentException('Спосіб доставки замовлення і тип доставки у даних Нової пошти не збігаються. Перевірте замовлення.');
        }

        $service = $rowService !== '' ? $rowService : $settingsService;
        $hasAddress = self::filled($deliveryData->city_name ?? null) && self::filled($deliveryData->street ?? null);
        $hasWarehouse = self::filled($deliveryData->warehouse_id ?? null);
        $locker = (int)($deliveryData->pickup_locker ?? 0) === 1;
        $requestedMode = $requestedMode !== null ? trim($requestedMode) : null;

        if ($requestedMode !== null && $requestedMode !== '' && !in_array($requestedMode, ['address', 'warehouse', 'locker'], true)) {
            throw new \InvalidArgumentException('Невідомий тип доставки з форми. Створення ТТН скасовано.');
        }
        $requestedMode = $requestedMode === '' ? null : $requestedMode;

        if ($service === 'WarehouseDoors') {
            $mode = 'address';
            if ($requestedMode !== null && $requestedMode !== $mode) {
                throw new \InvalidArgumentException('Обрано доставку на відділення, але в замовленні вказана адресна доставка. Оновіть дані замовлення.');
            }
        } elseif ($service === 'WarehouseWarehouse') {
            if ($hasAddress && $hasWarehouse) {
                throw new \InvalidArgumentException('Конфлікт адреси та відділення: налаштування вказують на відділення, але в замовленні залишена вулиця. Уточніть доставку перед створенням ТТН.');
            }
            $mode = $requestedMode ?? ($locker ? 'locker' : 'warehouse');
            if ($mode === 'address') {
                throw new \InvalidArgumentException('Обрано адресну доставку, але налаштування замовлення вказують на відділення. Перевірте спосіб доставки.');
            }
        } elseif ($requestedMode !== null) {
            // Є лише дані NP Cost без авторитетного ServiceType. Адміністратор
            // обирає явно; потім виконується сувора перевірка полів адреси.
            $mode = $requestedMode;
        } elseif ($hasAddress && $hasWarehouse) {
            // Для масового створення, де немає ручного вибору, неоднозначність
            // неприпустима: старе відділення та залишена адреса — не доказ типу.
            throw new \InvalidArgumentException('Є і адреса, і відділення. Уточніть тип доставки в замовленні та створіть ТТН вручну.');
        } elseif ($hasAddress) {
            $mode = 'address';
        } elseif ($hasWarehouse) {
            $mode = $locker ? 'locker' : 'warehouse';
        } else {
            throw new \InvalidArgumentException('Немає адреси або відділення Нової пошти для створення ТТН.');
        }

        if ($mode === 'address') {
            if (!self::filled($deliveryData->city_name ?? null)) {
                throw new \InvalidArgumentException('Для адресної доставки не вказано населений пункт.');
            }
            if (!self::filled($deliveryData->street ?? null)) {
                throw new \InvalidArgumentException('Для адресної доставки не вказано вулицю.');
            }
            if (!self::filled($deliveryData->house ?? null)) {
                throw new \InvalidArgumentException('Для адресної доставки не вказано номер будинку.');
            }
        } else {
            if (!self::filled($deliveryData->city_id ?? null)) {
                throw new \InvalidArgumentException('Для доставки у відділення/поштомат не вказано Ref міста Нової пошти.');
            }
            if (!$hasWarehouse) {
                throw new \InvalidArgumentException('Для доставки у відділення/поштомат не вказано Ref відділення.');
            }
        }

        return [
            'mode' => $mode,
            'service_type' => $mode === 'address' ? 'WarehouseDoors' : 'WarehouseWarehouse',
            'pickup_locker' => $mode === 'locker' ? 1 : 0,
        ];
    }

    private static function filled($value): bool
    {
        return is_scalar($value) && trim((string)$value) !== '';
    }
}
