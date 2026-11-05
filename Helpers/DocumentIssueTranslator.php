<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

/** Пояснення помилок НП та локальної валідації без залежності від CMS. */
final class DocumentIssueTranslator
{
    /** @return array{field:string,message:string} */
    public static function explain(string $raw): array
    {
        $raw = trim($raw);
        if (preg_match('/^already_attached:(.+)$/i', $raw, $match)) {
            return ['field' => 'general', 'message' => 'До замовлення вже прив’язано ТТН № ' . self::safeRaw($match[1]) . '. Перевірте наявну накладну замість створення повторної.'];
        }

        // Більш конкретні поля стоять вище за загальні (RecipientAddressName перед RecipientAddress).
        $rules = [
            ['~RecipientHouse|\b(client )?house\b|номер(а|у)? будинк~iu', 'house', 'Номер будинку одержувача відсутній або некоректний. У блоці доставки замовлення вкажіть номер будинку та збережіть замовлення.'],
            ['~RecipientAddressName|\b(client )?street\b|вулиц~iu', 'street', 'Не заповнено або неправильно вибрано вулицю одержувача. У блоці доставки виберіть вулицю з довідника Нової пошти та збережіть замовлення.'],
            ['~RecipientCityName|RecipientAreaRegions|RecipientArea\b|CityRecipient|\bclient city\b|\bcity name\b|населен(ий|ого) пункт|\bsettlement\b~iu', 'city', 'Не визначено населений пункт одержувача. Виберіть місто або село у блоці доставки замовлення та збережіть зміни.'],
            ['~RecipientFlat|\bapartment\b|\bflat\b|квартир~iu', 'apartment', 'Некоректний номер квартири або офісу. Виправте його в адресі замовлення та збережіть зміни.'],
            ['~SenderAddress|sender warehouse|відділенн(я|і) відправник~iu', 'sender_warehouse', 'Не визначено відділення відправника. Відкрийте налаштування NovaPoshtaTracking і перевірте відділення відправлення.'],
            ['~RecipientAddress\b|\bclient warehouse\b|Ref відділення|відсутн(є|ій).*відділенн~iu', 'warehouse', 'Не вибрано або неправильно визначено відділення/поштомат одержувача. У блоці доставки замовлення виберіть пункт Нової пошти та збережіть зміни.'],
            ['~No services found|No Services allowed|Delivery unavailable|delivery is not available~iu', 'delivery_mode', 'Нова пошта не дозволяє доставку за вказаним маршрутом або до цього пункту. Перевірте тип доставки, населений пункт, відділення та габарити посилки.'],
            ['~\bServiceType\b|delivery.type|тип доставки|конфлікт адреси та відділення|спосіб доставки замовлення|адреса.*відділення~iu', 'delivery_mode', 'Тип доставки суперечить даним замовлення. Перевірте, чи вибрано «Адреса», «Відділення» або «Поштомат», виправте спосіб доставки в замовленні та збережіть його.'],
            ['~AfterpaymentOnGoodsCost is unavailable~i', 'control_payment', 'Контроль оплати недоступний за договором з Новою поштою. Вимкніть «Контроль оплати» і створіть ТТН ще раз.'],
            ['~AfterpaymentOnGoodsCost|MoneyTransfer|\bafterpayment\b|контроль оплати|\bredelivery\b~iu', 'control_payment', 'Помилка контролю оплати або післяплати. Перевірте перемикач «Контроль оплати» та суму післяплати; за потреби вимкніть контроль оплати.'],
            ['~\bRecipientsPhone\b|\bRecipientPhone\b|\buser phone\b|\bInvalid phone\b|телефон(а|у)? одержувач~iu', 'phone', 'Не вказано або неправильно записано телефон одержувача. Виправте номер у замовленні (український номер із кодом країни) та збережіть.'],
            ['~\bSendersPhone\b|\bSenderPhone\b~iu', 'sender', 'Неправильний телефон відправника. Перевірте телефон у налаштуваннях NovaPoshtaTracking.'],
            ['~\bRecipientName\b|\bRecipientContactName\b|\bEmpty user name\b|\bname\b.*(recipient|client)|ПІБ одержувач~iu', 'recipient_name', 'Не вказано або неправильно заповнено ім’я та прізвище одержувача. Перевірте контактні дані замовлення.'],
            ['~\bContactRecipient\b|Recipient contact person|\bRecipient\b.*not found~iu', 'recipient_name', 'Нова пошта не знайшла контакт одержувача. Перевірте ім’я, прізвище й телефон у замовленні, потім повторіть створення.'],
            ['~\bCargoType\b|тип вантажу~iu', 'cargo_type', 'Не обрано або неправильно встановлено тип вантажу. У параметрах ТТН виберіть «Вантаж» або «Документи».'],
            ['~\bPayerType\b|\bPayer type\b|платник(а|ом)? за доставк~iu', 'payer_type', 'Не вибрано платника доставки. У параметрах ТТН зазначте відправника або одержувача.'],
            ['~\bPaymentMethod\b|\bPayment method\b|форма оплати за доставк~iu', 'payment_method', 'Не вказано форму оплати доставки. Виберіть готівкову або безготівкову оплату.'],
            ['~\bVolumeWeight\b|\bWeight\b|\bweight\b|\bvolumetric.weight\b|ваг(а|у|и)~iu', 'weight', 'Некоректна вага або об’ємна вага посилки. Перевірте вагу й габарити у параметрах ТТН.'],
            ['~\bVolumeGeneral\b|\bvolume\b|об[’\']єм~iu', 'volume', 'Некоректний об’єм посилки. Перевірте об’єм і габарити у параметрах ТТН.'],
            ['~\bSeatsAmount\b|кількість місць~iu', 'weight', 'Некоректна кількість місць. Перевірте параметри відправлення.'],
            ['~\bCost\b|оголошен(а|ої) цінніст|declared value~iu', 'cost', 'Невірна оголошена цінність або сума відправлення. Перевірте поле «Оголошена цінність».'],
            ['~\bDateTime\b|\bshipping date\b|\bdate.*(past|invalid)\b|дат(а|у) відправ~iu', 'delivery_date', 'Некоректна дата відправлення. Перевірте дату у налаштуваннях та можливість створення ТТН на цю дату.'],
            ['~\bSender\b|\bContactSender\b|\bSender not found\b~iu', 'sender', 'Нова пошта не знайшла дані відправника. Перевірте ключ API, відділення та контакт відправника в налаштуваннях модуля.'],
            ['~\bapi.key\b|\bAPI key\b|invalid.*key|access denied|unauthorized|forbidden|token expired~iu', 'api_key', 'Нова пошта відхилила авторизацію. Перевірте чинність API-ключа в налаштуваннях модуля та права доступу в кабінеті НП.'],
            ['~429|Too many requests|rate limit|to many requests~iu', 'api', 'Перевищено ліміт запитів Нової пошти. Не натискайте кнопку багато разів; повторіть операцію після зменшення навантаження.'],
            ['~\btimeout\b|timed out|cURL 28|connection|HTTP [45]\d\d|Internal Server Error|failed to build|request failed|Unknown API error~iu', 'api', 'Запит до Нової пошти не завершився належним чином. Перед повторним створенням перевірте в кабінеті НП, чи ТТН уже не з’явилася.'],
            ['~\bOrder ID\b|\bOrder not found\b|замовлення не знайден~iu', 'general', 'Не знайдено замовлення. Оновіть сторінку та перевірте, що воно не видалене.'],
        ];
        foreach ($rules as [$pattern, $field, $message]) {
            if (preg_match($pattern, $raw)) {
                return ['field' => $field, 'message' => $message . self::originalText($raw)];
            }
        }

        if ($raw === '') {
            return ['field' => 'general', 'message' => 'Нова пошта не надала опису помилки. Перевірте заповнення замовлення та повторіть дію лише після перевірки, чи ТТН уже не створена.'];
        }
        // Зрозумілі українські помилки, сформовані самим модулем, не губимо.
        if (preg_match('/[а-яіїєґ]/iu', $raw)) {
            return ['field' => 'general', 'message' => self::safeRaw($raw)];
        }
        return ['field' => 'general', 'message' => 'Нова пошта повідомила: «' . self::safeRaw($raw) . '». Перевірте параметри ТТН; якщо повторюється, передайте цей текст адміністратору сайту.'];
    }

    /** @return array{field:string,message:string} */
    public static function explainWarning(string $raw): array
    {
        $warningMap = [
            ['~\bVolumeWeight\b.*chang~i', 'weight', 'Нова пошта автоматично змінила об’ємну вагу посилки. Звірте її з габаритами перед відправленням.'],
            ['~\bVolumeGeneral\b.*chang~i', 'volume', 'Нова пошта автоматично змінила об’єм посилки. Перевірте габарити відправлення.'],
            ['~\bWeight\b.*chang~i', 'weight', 'Нова пошта автоматично змінила вагу посилки. Перевірте параметри відправлення.'],
            ['~\bCargoType\b.*chang~i', 'cargo_type', 'Нова пошта змінила тип вантажу. Перевірте, чи правильний тип указано у створеній ТТН.'],
            ['~No services found|No Services allowed|Delivery unavailable|delivery is not available~iu', 'delivery_mode', 'Нова пошта не дозволяє доставку за вказаним маршрутом або до цього пункту. Перевірте тип доставки, населений пункт, відділення та габарити посилки.'],
            ['~\bServiceType\b.*chang~i', 'delivery_mode', 'Нова пошта змінила тип доставки! Перевірте тип і пункт призначення в кабінеті НП перед відправленням.'],
        ];
        foreach ($warningMap as [$pattern, $field, $message]) {
            if (preg_match($pattern, $raw)) {
                return ['field' => $field, 'message' => $message];
            }
        }
        return self::explain($raw);
    }

    /**
     * Лише попередження про тип чи пункт доставки: решту (Cargo → Parcel,
     * RecipientType, перерахунок ваги) НП додає майже до кожної ТТН.
     *
     * @param list<string> $messages
     * @return list<array{field:string,message:string}>
     */
    public static function destinationWarnings(array $messages): array
    {
        $issues = [];
        foreach ($messages as $message) {
            $issue = self::explainWarning((string)$message);
            if ($issue['field'] === 'delivery_mode' && !in_array($issue, $issues, true)) {
                $issues[] = $issue;
            }
        }
        return $issues;
    }

    /** @param list<string> $messages @return list<array{field:string,message:string}> */
    public static function explainMany(array $messages): array
    {
        $issues = [];
        foreach ($messages as $message) {
            $issue = self::explain((string)$message);
            if (!in_array($issue, $issues, true)) {
                $issues[] = $issue;
            }
        }
        return $issues;
    }

    /** Англомовний оригінал (від НП чи валідації модуля) — щоб оператор міг передати його в підтримку. */
    private static function originalText(string $raw): string
    {
        if ($raw === '' || preg_match('/[а-яіїєґ]/iu', $raw)) {
            return '';
        }
        return ' (оригінал: «' . self::safeRaw($raw) . '»)';
    }

    /** Текст ще не екранований: його екранують шаблон (|escape) і JS (.text()). */
    private static function safeRaw(string $raw): string
    {
        $raw = preg_replace('/\b(?:\+?380)?\d{9,12}\b/u', '[номер приховано]', $raw);
        $raw = preg_replace('/\b[a-f0-9]{32,}\b/iu', '[значення приховано]', $raw);
        return mb_substr($raw, 0, 320, 'UTF-8');
    }
}
