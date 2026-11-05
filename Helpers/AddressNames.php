<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

/**
 * Назви міст і вулиць у тому вигляді, в якому їх зберігає NovaposhtaCost,
 * зведені до того, з чим працює довідник Нової Пошти.
 */
final class AddressNames
{
    /** Тип вулиці => варіанти написання (порівняння без регістру). */
    private const array STREET_TYPES = [
        'вул' => ['вул.', 'вул', 'вулиця'],
        'просп' => ['просп.', 'просп', 'пр-т', 'проспект'],
        'пров' => ['пров.', 'пров', 'провулок'],
        'бульв' => ['бульв.', 'бул.', 'бульвар'],
        'пл' => ['пл.', 'площа'],
        'наб' => ['наб.', 'набережна'],
        'узв' => ['узв.', 'узвіз'],
        'туп' => ['туп.', 'тупик'],
        'шосе' => ['шосе', 'ш.'],
        'алея' => ['алея'],
        'майдан' => ['майдан', 'майд.'],
        'проїзд' => ['проїзд'],
    ];

    /**
     * Місто з поля NovaposhtaCost. Якщо на чекауті не спрацював вибір підказки,
     * там лежить її текст: «м. Миколаїв, Стрийський р-н, Львівська обл.».
     *
     * @return array{city:string, area:string, region:string}
     */
    public static function city(string $cityName, string $area = '', string $region = ''): array
    {
        $parts = array_values(array_filter(array_map(trim(...), explode(',', $cityName)), static fn (string $part): bool => $part !== ''));
        $city = $parts[0] ?? '';
        $city = preg_replace('/^(?:м\.|місто|с\.|село|смт|селище(?: міського типу)?|с-ще|сщ\.?)\s*/iu', '', $city) ?? $city;

        foreach (array_slice($parts, 1) as $part) {
            if ($area === '' && preg_match('/^(.+?)\s+обл\.?$/u', $part, $m)) {
                $area = $m[1];
            } elseif ($region === '' && preg_match('/^(.+?)\s+р-н$/u', $part, $m)) {
                $region = $m[1];
            }
        }

        return ['city' => trim($city), 'area' => trim($area), 'region' => trim($region)];
    }

    /** Назва вулиці без типу, регістру й варіантів апострофа: «вул. Городоцька» → «городоцька». */
    public static function streetCore(string $street): string
    {
        $value = self::normalize($street);
        [$type, $rest] = self::splitType($value);

        return $type === null ? $value : $rest;
    }

    /**
     * Канонічний тип вулиці (`вул`, `просп`…) або null, якщо тип не вказано.
     * Приймає і назву з типом, і сам тип — так його віддає getStreet у StreetsType.
     */
    public static function streetType(string $street): ?string
    {
        $value = self::normalize($street);
        foreach (self::STREET_TYPES as $type => $spellings) {
            if (in_array($value, $spellings, true)) {
                return $type;
            }
        }

        return self::splitType($value)[0];
    }

    /**
     * Ref вулиці з відповіді AddressGeneral.getStreet, якщо збіг однозначний.
     *
     * @param list<mixed> $candidates Елементи data: Ref, Description, StreetsType
     */
    public static function pickStreet(array $candidates, string $orderStreet): ?string
    {
        $core = self::streetCore($orderStreet);
        if ($core === '') {
            return null;
        }

        $matches = array_values(array_filter(
            $candidates,
            static fn ($c) => is_object($c) && !empty($c->Ref) && self::streetCore((string)($c->Description ?? '')) === $core
        ));

        $type = self::streetType($orderStreet);
        if (count($matches) > 1 && $type !== null) {
            $matches = array_values(array_filter(
                $matches,
                static fn ($c) => self::streetType((string)($c->StreetsType ?? '')) === $type
            ));
        }

        return count($matches) === 1 ? (string)$matches[0]->Ref : null;
    }

    public static function normalize(string $value): string
    {
        $value = str_replace(['’', 'ʼ', '`', '´'], "'", $value);
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value, 'UTF-8');
    }

    /** @return array{0: ?string, 1: string} */
    private static function splitType(string $value): array
    {
        foreach (self::STREET_TYPES as $type => $spellings) {
            foreach ($spellings as $spelling) {
                $quoted = preg_quote($spelling, '/');
                $boundary = str_ends_with($spelling, '.') ? '' : '(?=\s|$)';
                if (preg_match('/^' . $quoted . $boundary . '\s*(.+)$/u', $value, $m)
                    || preg_match('/^(.+?)\s+' . $quoted . $boundary . '$/u', $value, $m)
                ) {
                    return [$type, trim($m[1])];
                }
            }
        }

        return [null, $value];
    }
}
