<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

/**
 * Трейт для форматування та обробки tracking документів Нової Пошти
 */
trait TrackingDocumentFormatter
{
    /**
     * Форматує дати tracking документа
     */
    protected function formatTrackingDates($trackingDocument)
    {
        $dateFields = [
            'ScheduledDeliveryDate' => 'd.m.Y H:i',
            'PreferredDeliveryDate' => 'd.m.Y H:i',
            'DateTime' => ['d.m.Y', 'd.m.Y H:i:s'],
            'DateCreated' => ['d.m.Y', 'd.m.Y H:i:s'],
            'RecipientDateTime' => 'd.m.Y H:i:s',
            'DateMoving' => 'd.m.Y',
            'DateFirstDayStorage' => 'd.m.Y',
            'DateReturnCargo' => 'd.m.Y',
        ];

        foreach ($dateFields as $field => $format) {
            if (!empty($trackingDocument->$field)) {
                $dateTime = strtotime($trackingDocument->$field);
                if ($dateTime !== false) {
                    if (is_array($format)) {
                        $trackingDocument->{$field . 'Formatted'} = date($format[0], $dateTime);
                        $trackingDocument->{$field . 'FullFormatted'} = date($format[1], $dateTime);
                    } else {
                        $trackingDocument->{$field . 'Formatted'} = date($format, $dateTime);
                    }
                }
            }
        }
    }

    /**
     * Форматує суми для відображення
     */
    protected function formatAmounts($trackingDocument)
    {
        $amountFields = [
            'AnnouncedPrice',
            'AfterpaymentOnGoodsCost',
            'DocumentCost',
            'RedeliverySum',
            'AddressPickupCostWithoutDiscount',
            'AddressPickupCostWithDiscount',
        ];

        foreach ($amountFields as $field) {
            if (!empty($trackingDocument->$field)) {
                $floatValue = (float)$trackingDocument->$field;
                $decimals = ($floatValue == floor($floatValue)) ? 0 : 2;
                $trackingDocument->{$field . 'Formatted'} = number_format($floatValue, $decimals, '.', ' ');
            }
        }
    }

    /**
     * Форматує номер накладної для відображення у форматі 2-4-4-4
     * Наприклад: 20451329330540 -> 20 1234 5678 9012
     * 
     * @param string $docNumber Номер накладної
     * @return string Відформатований номер або оригінальний, якщо номер менше 14 символів
     */
    protected function formatDocumentNumber($docNumber)
    {
        if (empty($docNumber)) {
            return '';
        }

        $cleanNumber = preg_replace('/\D/', '', $docNumber);

        if (strlen($cleanNumber) >= 14) {
            return substr($cleanNumber, 0, 2) . ' ' .
                substr($cleanNumber, 2, 4) . ' ' .
                substr($cleanNumber, 6, 4) . ' ' .
                substr($cleanNumber, 10, 4);
        }

        return $docNumber;
    }

    /**
     * Нормалізує параметри габаритів (замінює кому на крапку)
     */
    protected function normalizeVolumetricParams($params)
    {
        $normalized = [];
        foreach ($params as $key => $value) {
            if ($value !== null && $value !== '') {
                $normalized[$key] = str_replace(',', '.', trim($value));
            } else {
                $normalized[$key] = null;
            }
        }
        return $normalized;
    }

    /**
     * Обрізає адресу до 70 символів
     */
    protected function truncateAddress($address, $maxLength = 70)
    {
        if (mb_strlen($address) > $maxLength) {
            return mb_substr($address, 0, $maxLength) . '...';
        }
        return $address;
    }

    /**
     * Визначає фінальне значення типу контрагента відправника
     * Перевіряє спочатку Display версії, потім оригінальні значення
     */
    protected function getSenderCounterpartyType($trackingDocument)
    {
        $fields = [
            // Display значення (вищий пріоритет)
            'SenderCounterpartyTypeDisplay',
            'CounterpartySenderTypeDisplay',
            'CounterpartyTypeDisplay',

            // Оригінальні значення
            'SenderCounterpartyType',
            'CounterpartySenderType',
            'CounterpartyType',
        ];

        foreach ($fields as $field) {
            if (!empty($trackingDocument->$field)) {
                return $trackingDocument->$field;
            }
        }

        return null;
    }

    /**
     * Визначає планову дату доставки
     * Перевіряє спочатку відформатовані дати, потім оригінальні значення
     */
    protected function getScheduledDeliveryDate($trackingDocument)
    {
        $fields = [
            // Відформатовані дати
            'ScheduledDeliveryDateFormatted',
            'PreferredDeliveryDateFormatted',

            // Оригінальні значення
            'ScheduledDeliveryDate',
            'PreferredDeliveryDate',
        ];

        foreach ($fields as $field) {
            if (!empty($trackingDocument->$field)) {
                return $trackingDocument->$field;
            }
        }

        return null;
    }

    /**
     * Переклад значень API Нової Пошти на українську мову
     * 
     * @param string $type Тип даних (PayerType, PaymentMethod, CargoType, ServiceType, CounterpartySenderType, CounterpartyType)
     * @param string $value Значення для перекладу
     * @return string Перекладене значення або оригінальне, якщо переклад не знайдено
     */
    protected function translateApiValue($type, $value)
    {
        if (empty($value)) {
            return $value;
        }

        $translations = [
            'PayerType' => [
                'Sender' => 'Відправник',
                'Recipient' => 'Отримувач',
                'ThirdPerson' => 'Третя особа',
            ],
            'PaymentMethod' => [
                'Cash' => 'Готівка',
                'NonCash' => 'Безготівка',
            ],
            'CargoType' => [
                'Cargo' => 'Вантаж',
                'Documents' => 'Документи',
                'Parcel' => 'Посилка',
                'TiresWheels' => 'Шини-диски',
                'Pallet' => 'Палета',
                'Money' => 'Грошовий переказ',
            ],
            'ServiceType' => [
                'DoorsDoors' => 'Двері-Двері',
                'DoorsWarehouse' => 'Двері-Відділення',
                'WarehouseWarehouse' => 'Відділення-Відділення',
                'WarehouseDoors' => 'Відділення-Двері',
            ],
            'CounterpartySenderType' => [
                'PrivatePerson' => 'Приватна особа',
                'Organization' => 'Організація',
            ],
            'CounterpartyType' => [
                'PrivatePerson' => 'Приватна особа',
                'Organization' => 'Організація',
            ],
        ];

        if (isset($translations[$type]) && isset($translations[$type][$value])) {
            return $translations[$type][$value];
        }

        return $value;
    }

    /**
     * Перекладає всі значення API на українську мову для tracking документа
     */
    protected function translateApiValues($trackingDocument)
    {
        $translationFields = [
            ['field' => 'PayerType', 'type' => 'PayerType'],
            ['field' => 'RedeliveryPayer', 'type' => 'PayerType'],
            ['field' => 'PaymentMethod', 'type' => 'PaymentMethod'],
            ['field' => 'CargoType', 'type' => 'CargoType'],
            ['field' => 'ServiceType', 'type' => 'ServiceType'],
            ['field' => 'SenderCounterpartyType', 'type' => 'CounterpartySenderType'],
            ['field' => 'CounterpartySenderType', 'type' => 'CounterpartySenderType'],
            ['field' => 'CounterpartyType', 'type' => 'CounterpartyType'],
        ];

        foreach ($translationFields as $item) {
            if (!empty($trackingDocument->{$item['field']})) {
                $trackingDocument->{$item['field'] . 'Display'} = $this->translateApiValue(
                    $item['type'],
                    $trackingDocument->{$item['field']}
                );
            }
        }
    }

    /**
     * Форматує телефон
     */
    protected function formatPhone($phone)
    {
        if (empty($phone)) {
            return null;
        }

        try {
            $phoneUtil = \libphonenumber\PhoneNumberUtil::getInstance();
            $phoneNumberProto = $phoneUtil->parse($phone, "UA");
            if (!$phoneUtil->isValidNumber($phoneNumberProto)) {
                return null;
            }
            return $phoneUtil->format($phoneNumberProto, \libphonenumber\PhoneNumberFormat::E164);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Конвертує дату з формату API в формат БД
     * 
     * @param string|null $dateString Дата в будь-якому форматі
     * @return string|null Дата в форматі Y-m-d H:i:s або null
     */
    protected function convertTrackingDateToDb($dateString)
    {
        if (empty($dateString)) {
            return null;
        }

        $timestamp = strtotime($dateString);
        return $timestamp !== false ? date('Y-m-d H:i:s', $timestamp) : null;
    }

}
