<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

/**
 * Звіряє відповідь InternetDocument.getDocument із даними доставки замовлення.
 *
 * getDocument повертає ServiceType і CityRecipient підписами («Відділення-Адреса»,
 * «Львів»), тому порівнюємо *Ref-поля. Адресу НП віддає одним рядком
 * («Городоцька буд. 10, кв. 5») і окремо RecipientAddressRef — створений нею запис адреси.
 */
final class RecipientAddressAudit
{
    private const string EMPTY_REF = '00000000-0000-0000-0000-000000000000';

    /**
     * @param object $document Елемент data[0] відповіді getDocument
     * @param object|null $localDelivery Рядок NPCostDeliveryDataEntity
     * @return array{status:string,warning?:string} status: verified | mismatch | unverified
     */
    public static function inspect(object $document, string $expectedServiceType, $localDelivery): array
    {
        $actualService = self::text($document->ServiceTypeRef ?? null);
        if ($actualService === '') {
            return [
                'status' => 'unverified',
                'warning' => 'Нова пошта не повернула тип доставки створеної ТТН. Перевірте ТТН у кабінеті НП перед відправленням.',
            ];
        }
        if ($actualService !== $expectedServiceType) {
            return [
                'status' => 'mismatch',
                'warning' => 'Нова пошта зберегла ТТН з іншим типом доставки (' . $actualService . ', очікувався ' . $expectedServiceType . '). Перевірте пункт доставки до відправлення.',
            ];
        }

        $expectedCityRef = self::text($localDelivery->city_id ?? null);
        $actualCityRef = self::text($document->CityRecipientRef ?? null);
        if ($expectedCityRef !== '' && $actualCityRef !== '' && strcasecmp($expectedCityRef, $actualCityRef) !== 0) {
            return [
                'status' => 'mismatch',
                'warning' => 'Населений пункт у ТТН (' . self::text($document->CityRecipient ?? null) . ') не збігається із замовленням. Перевірте адресу до відправлення.',
            ];
        }

        if ($expectedServiceType !== 'WarehouseDoors') {
            return ['status' => 'verified'];
        }

        $addressRef = self::text($document->RecipientAddressRef ?? null);
        if (!self::isRef($addressRef)) {
            return [
                'status' => 'unverified',
                'warning' => 'Нова пошта не прив’язала адресу до ТТН. Відкрийте ТТН у кабінеті НП і перевірте вулицю та будинок.',
            ];
        }

        $address = self::normalize(self::text($document->RecipientAddress ?? null));
        $street = self::normalize(self::stripStreetPrefix(self::text($localDelivery->street ?? null)));
        $house = self::normalize(self::text($localDelivery->house ?? null));
        if ($street !== '' && !str_contains($address, $street)
            || $house !== '' && !preg_match('/(^|[^\p{L}\p{N}])' . preg_quote($house, '/') . '($|[^\p{L}\p{N}])/u', $address)
        ) {
            return [
                'status' => 'unverified',
                'warning' => 'Адреса в ТТН («' . self::text($document->RecipientAddress ?? null) . '») відрізняється від замовлення. Перевірте вулицю та будинок до відправлення.',
            ];
        }

        return ['status' => 'verified'];
    }

    private static function isRef(string $value): bool
    {
        return $value !== self::EMPTY_REF
            && preg_match('/^[0-9a-f]{8}-(?:[0-9a-f]{4}-){3}[0-9a-f]{12}$/iD', $value) === 1;
    }

    private static function text($value): string
    {
        return is_scalar($value) ? trim((string)$value) : '';
    }

    private static function stripStreetPrefix(string $value): string
    {
        return preg_replace('/^(?:вул\.|вулиця)\s*/iu', '', $value) ?? $value;
    }

    private static function normalize(string $value): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        return mb_strtolower($value, 'UTF-8');
    }
}
