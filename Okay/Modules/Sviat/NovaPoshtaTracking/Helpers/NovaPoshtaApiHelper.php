<?php


namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;


use Okay\Core\Settings;

class NovaPoshtaApiHelper
{
    private $settings;
    private static $cache = [];
    private static $curlHandle = null;
    private const CACHE_TTL = 300; // 5 хвилин для кешування запитів
    private const MAX_RETRIES = 2; // Максимальна кількість спроб при помилці
    private const REQUEST_TIMEOUT = 20; // Таймаут запиту в секундах

    public function __construct(Settings $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Отримання кешованого значення
     */
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

    /**
     * Збереження значення в кеш
     */
    private function setCache($key, $data)
    {
        self::$cache[$key] = [
            'data' => $data,
            'time' => time()
        ];
    }

    /**
     * Генерація ключа кешу на основі запиту
     */
    private function getCacheKey($request)
    {
        return md5(json_encode($request));
    }

    /**
     * Очищення кешу (для тестування або примусового оновлення)
     */
    public function clearCache()
    {
        self::$cache = [];
    }

    /**
     * Статичний метод для очищення кешу без створення об'єкта
     */
    public static function clearCacheStatic()
    {
        self::$cache = [];
    }

    /**
     * Закриває curl handle (викликати при завершенні роботи)
     */
    public static function closeCurlHandle()
    {
        if (self::$curlHandle !== null) {
            curl_close(self::$curlHandle);
            self::$curlHandle = null;
        }
    }

    /**
     * Отримання контактної особи контрагента за його референсом
     */
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

    /**
     * Отримання списку контрагентів за фільтром
     */
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

        // Перевіряємо кеш (контрагенти рідко змінюються)
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
    public function sendApiRequest($request = [], $useCache = false)
    {
        if (empty($request)) {
            return false;
        }

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

        // Retry логіка
        while ($attempt <= self::MAX_RETRIES) {
            // Використовуємо статичний curl handle для reuse connection (оптимізація)
            if (self::$curlHandle === null) {
                self::$curlHandle = curl_init();
                curl_setopt_array(self::$curlHandle, [
                    CURLOPT_URL => 'https://api.novaposhta.ua/v2.0/json/',
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_POST => true,
                    CURLOPT_TIMEOUT => self::REQUEST_TIMEOUT,
                    CURLOPT_CONNECTTIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => false,
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
                } elseif ($decoded && isset($decoded->errors) && !empty($decoded->errors)) {
                    // API повернув помилку, але це не помилка мережі
                    $lastError = $decoded->errors[0] ?? 'Unknown API error';
                    break; // Не повторюємо запит при помилках API
                }
            }

            // Помилка мережі або HTTP - повторюємо
            $lastError = $curlError ?: "HTTP {$httpCode}";
            $attempt++;

            // Невелика затримка перед повторною спробою
            if ($attempt <= self::MAX_RETRIES) {
                usleep(500000); // 0.5 секунди
            }
        }

        // Всі спроби невдалі - повертаємо об'єкт з помилкою для сумісності
        $errorResponse = new \stdClass();
        $errorResponse->success = false;
        $errorResponse->errors = [$lastError ?? 'Request failed after ' . self::MAX_RETRIES . ' attempts'];
        $errorResponse->data = [];

        return $errorResponse;
    }

    /**
     * Отримує повідомлення про помилку з відповіді API
     * Уніфікована логіка збору помилок з різних форматів відповідей
     * 
     * @param object|array $response Відповідь API
     * @return string Повідомлення про помилку
     */
    public function getErrorMessage($response): string
    {
        $errorMessages = [];

        if (is_array($response)) {
            $response = (object)$response;
        }

        if (!empty($response->errors) && is_array($response->errors)) {
            foreach ($response->errors as $key => $error) {
                $errorMessages[] = is_numeric($key) ? $error : ($key . ' - ' . $error);
            }
        } elseif (!empty($response->errorMessage)) {
            $errorMessages[] = $response->errorMessage;
        } elseif (!empty($response->error)) {
            $errorMessages[] = is_string($response->error) ? $response->error : json_encode($response->error);
        } elseif (!empty($response->errors) && is_string($response->errors)) {
            $errorMessages[] = $response->errors;
        } else {
            $errorMessages[] = 'Unknown API error';
        }

        return implode('; ', $errorMessages);
    }
}
