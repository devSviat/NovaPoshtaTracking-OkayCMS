<?php


namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;


use Okay\Core\Settings;

class NovaPoshtaApiHelper
{
    private $settings;
    private static $cache = [];
    private static $curlHandle = null;
    private const int CACHE_TTL = 300; // 5 хвилин для кешування запитів
    private const int MAX_RETRIES = 2; // Максимальна кількість спроб при помилці
    private const int REQUEST_TIMEOUT = 20; // Таймаут запиту в секундах

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    private function getCached($key)
    {
        if (isset(self::$cache[$key])) {
            $cached = self::$cache[$key];
            if (time() - $cached['time'] < self::CACHE_TTL) {
                return $cached['data'];
            }
            unset(self::$cache[$key]);
        }
        return null;
    }

    private function setCache($key, $data)
    {
        self::$cache[$key] = [
            'data' => $data,
            'time' => time()
        ];
    }

    private function getCacheKey($request)
    {
        return md5(json_encode($request));
    }

    public function clearCache()
    {
        self::$cache = [];
    }

    public static function clearCacheStatic()
    {
        self::$cache = [];
    }

    public static function closeCurlHandle()
    {
        // curl_close() з PHP 8.0 нічого не робить, а з 8.5 ще й deprecated:
        // хендл звільняє GC, щойно зникне остання посилання на нього.
        self::$curlHandle = null;
    }

    /** Контактна особа контрагента за Ref */
    public function getContactPersonByCounterpartyRef($counterpartyRef = '', $useCache = true)
    {
        if (empty($counterpartyRef)) {
            return false;
        }
        $request = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "Counterparty",
            "calledMethod" => "getCounterpartyContactPersons",
            "methodProperties" => [
                "Ref" => $counterpartyRef
            ]
        ];

        // Перевіряємо кеш
        if ($useCache) {
            $cacheKey = $this->getCacheKey($request);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->sendApiRequest($request);

        // Зберігаємо в кеш тільки успішні запити
        if ($useCache && $result && isset($result->success) && $result->success) {
            $cacheKey = $this->getCacheKey($request);
            $this->setCache($cacheKey, $result);
        }

        return $result;
    }

    /** Список контрагентів (Sender/Recipient) */
    public function getCounterparties($filter = ['cp_property' => 'Recipient'], $useCache = true)
    {
        if (empty($filter)) {
            return false;
        }
        $request = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "Counterparty",
            "calledMethod" => "getCounterparties",
            "methodProperties" => [
                'CounterpartyProperty' => $filter['cp_property']
            ]
        ];

        if ($useCache) {
            $cacheKey = $this->getCacheKey($request);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->sendApiRequest($request);

        // Зберігаємо в кеш тільки успішні запити
        if ($useCache && $result && isset($result->success) && $result->success) {
            $cacheKey = $this->getCacheKey($request);
            $this->setCache($cacheKey, $result);
        }

        return $result;
    }

    /**
     * Отримання інформації про відділення за його референсом
     */
    /** Відділення за Ref */
    public function getWarehouseByRef($warehouseRef = '', $useCache = true)
    {
        if (empty($warehouseRef)) {
            return false;
        }
        $request = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "Address",
            "calledMethod" => "getWarehouses",
            "methodProperties" => [
                "Ref" => $warehouseRef
            ]
        ];

        // Перевіряємо кеш
        if ($useCache) {
            $cacheKey = $this->getCacheKey($request);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $result = $this->sendApiRequest($request);

        // Зберігаємо в кеш тільки успішні запити
        if ($useCache && $result && isset($result->success) && $result->success) {
            $cacheKey = $this->getCacheKey($request);
            $this->setCache($cacheKey, $result);
        }

        return $result;
    }

    /**
     * Додавання нового контрагента (отримувача) в систему Нової Пошти
     */
    public function addCounterparty($firstName, $lastName, $phone)
    {
        if (empty($firstName) || empty($lastName) || empty($phone)) {
            return false;
        }

        $request = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "Counterparty",
            "calledMethod" => "save",
            "methodProperties" => [
                'CounterpartyProperty' => 'Recipient',
                'CounterpartyType' => 'PrivatePerson',
                'FirstName' => $firstName,
                'LastName' => $lastName,
                'Phone' => $phone,
            ]
        ];

        return $this->sendApiRequest($request);
    }

    /**
     * Відправка запиту до API Нової Пошти з оптимізацією та обробкою помилок
     */
    public function sendApiRequest($request = [], $useCache = false, ?int $maxRetriesOverride = null)
    {
        if (empty($request)) {
            return false;
        }

        // save/update/delete змінюють дані в НП: після таймауту операція могла
        // пройти, тому їх не кешуємо й не повторюємо автоматично.
        $isMutation = in_array(strtolower((string)($request['calledMethod'] ?? '')), ['save', 'update', 'delete'], true);
        $maxRetries = $isMutation ? 0 : ($maxRetriesOverride === null ? self::MAX_RETRIES : max(0, min(self::MAX_RETRIES, $maxRetriesOverride)));
        $useCache = $useCache && !$isMutation;

        // Перевіряємо кеш для запитів, які можна кешувати
        if ($useCache) {
            $cacheKey = $this->getCacheKey($request);
            $cached = $this->getCached($cacheKey);
            if ($cached !== null) {
                return $cached;
            }
        }

        $requestJson = json_encode($request);
        $attempt = 0;
        $lastError = null;
        $apiErrors = [];

        // Retry логіка
        while ($attempt <= $maxRetries) {
            // Використовуємо статичний curl handle для reuse connection (оптимізація)
            if (self::$curlHandle === null) {
                self::$curlHandle = curl_init();
                curl_setopt_array(self::$curlHandle, [
                    CURLOPT_URL => 'https://api.novaposhta.ua/v2.0/json/',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => true,
                    CURLOPT_SSL_VERIFYHOST => 2,
                    CURLOPT_FRESH_CONNECT => false,
                    CURLOPT_FORBID_REUSE => false,
                ]);
            }

            // Встановлюємо дані для поточного запиту
            curl_setopt_array(self::$curlHandle, [
                CURLOPT_POSTFIELDS => $requestJson,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Content-Length: ' . strlen($requestJson)
                ],
            ]);

            $response = curl_exec(self::$curlHandle);
            $httpCode = curl_getinfo(self::$curlHandle, CURLINFO_HTTP_CODE);
            $curlError = curl_error(self::$curlHandle);

            // Перевіряємо успішність запиту
            if ($response !== false && $httpCode === 200) {
                $decoded = json_decode($response);

                // Перевіряємо, чи API повернув помилку
                if ($decoded && isset($decoded->success) && $decoded->success) {
                    // Зберігаємо в кеш успішні запити
                    if ($useCache) {
                        $cacheKey = $this->getCacheKey($request);
                        $this->setCache($cacheKey, $decoded);
                    }
                    return $decoded;
                } elseif ($decoded && (isset($decoded->errors) && !empty($decoded->errors)
                    || !empty($decoded->errorMessage) || !empty($decoded->error))) {
                    // API повернув помилку, але це не помилка мережі
                    $apiErrors = $this->getErrorMessages($decoded);
                    $lastError = $apiErrors[0] ?? 'Unknown API error';
                    break; // Не повторюємо запит при помилках API
                }
            }

            // Помилка мережі або HTTP - повторюємо
            $lastError = $curlError ?: "HTTP {$httpCode}";
            $attempt++;

            // Невелика затримка перед повторною спробою
            if ($attempt <= $maxRetries) {
                usleep(500000); // 0.5 секунди
            }
        }

        // Всі спроби невдалі - повертаємо об'єкт з помилкою для сумісності
        $errorResponse = new \stdClass();
        $errorResponse->success = false;
        $errorResponse->errors = $apiErrors ?: [$lastError];
        $errorResponse->uncertain = $isMutation && $attempt > 0;
        if ($errorResponse->uncertain) {
            $errorResponse->errors[] = 'Результат невизначений: перед повторною спробою перевірте в кабінеті Нової пошти, чи операцію вже виконано.';
        }
        $errorResponse->data = [];

        return $errorResponse;
    }

    /** @return list<string> Повний список текстових помилок API, не лише перша. */
    public function getErrorMessages($response): array
    {
        if (is_array($response)) {
            $response = (object)$response;
        }
        if (!is_object($response)) {
            return ['Невідома помилка відповіді Нової пошти.'];
        }
        $result = [];
        $errors = $response->errors ?? null;
        if (is_array($errors)) {
            foreach ($errors as $key => $error) {
                $message = is_scalar($error) ? trim((string)$error) : '';
                if ($message !== '') {
                    $result[] = is_string($key) ? $key . ': ' . $message : $message;
                }
            }
        } elseif (is_string($errors) && trim($errors) !== '') {
            $result[] = trim($errors);
        }
        foreach (['errorMessage', 'error'] as $property) {
            $message = $response->{$property} ?? null;
            if (is_scalar($message) && trim((string)$message) !== '') {
                $result[] = trim((string)$message);
            }
        }
        return array_values(array_unique($result ?: ['Нова пошта не повернула пояснення помилки.']));
    }

    public function getErrorMessage($response): string
    {
        return implode('; ', $this->getErrorMessages($response));
    }
}
