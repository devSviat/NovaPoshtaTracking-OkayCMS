<?php

// declare(strict_types=1);

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Services;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\EntityFactory;
use Okay\Core\Money;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaApiHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\TrackingDocumentFormatter;

/**
 * Сервіс для роботи з документами Нової Пошти
 * Містить всю бізнес-логіку генерації, оновлення та видалення накладних
 */
class NovaPoshtaDocumentService
{
    use TrackingDocumentFormatter;

    private $entityFactory;
    private $settings;
    private $request;
    private $apiHelper;
    private $backendOrdersHelper;
    private $money;

    // Константи для валідації та дефолтних значень
    private const LOCKER_MAX = ['length' => 60, 'width' => 40, 'height' => 30, 'weight' => 20];
    private const LOCKER_DEFAULTS = ['volume' => '0.001', 'length' => '10', 'width' => '10', 'height' => '10', 'weight' => '0.5'];
    private const WAREHOUSE_MAX_WEIGHT = 30;
    private const WAREHOUSE_MAX_VOLUME = 0.12;
    private const WAREHOUSE_MIN_VOLUME = 0.0004;
    private const WAREHOUSE_MIN_WEIGHT = 0.1;
    private const VOLUMETRIC_WEIGHT_COEFFICIENT = 250;

    public function __construct(
        EntityFactory $entityFactory,
        Settings $settings,
        Request $request,
        NovaPoshtaApiHelper $apiHelper,
        BackendOrdersHelper $backendOrdersHelper,
        Money $money
    ) {
        $this->entityFactory = $entityFactory;
        $this->settings = $settings;
        $this->request = $request;
        $this->apiHelper = $apiHelper;
        $this->backendOrdersHelper = $backendOrdersHelper;
        $this->money = $money;
    }

    /**
     * Генерація експрес-накладної через API Нової Пошти
     * 
     * @param int $orderId ID замовлення
     * @return array Результат генерації з полями: int_doc_number, ref_id, error, tracking_document
     */
    public function generateDocument($orderId): array
    {
        try {
            $orderId = (int)$orderId;
            if (!$orderId) {
                return ['error' => 'Order ID is required'];
            }
            
            // Отримуємо та кешуємо deliveryData (щоб не робити зайві запити)
            $deliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
            $deliveryData = $deliveryDataEntity->findOne(['order_id' => $orderId]);

            // Отримуємо та нормалізуємо дані з форми
            $formData = $this->getFormData($deliveryData);

            // Валідуємо параметри габаритів
            if ($error = $this->validateVolumetricParams($formData['volumetric_params_from_form'])) {
                return ['error' => $error];
            }

            // Отримуємо фінальні значення параметрів габаритів
            $volumetricParams = $this->getVolumetricParams($formData['volumetric_params_from_form'], $deliveryData);
            $warehouseParams = $this->getWarehouseParams($formData['warehouse_params_from_form'], $deliveryData);

            // Зберігаємо дані в БД (перед створенням накладної)
            $this->saveDeliveryData($deliveryDataEntity, $deliveryData, $formData, $volumetricParams, $warehouseParams, false);

            // Оновлюємо локальний об'єкт deliveryData після збереження
            $deliveryData = $deliveryDataEntity->findOne(['order_id' => $orderId]);

            // Отримуємо замовлення
            $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
            $order = $ordersEntity->findOne(['id' => $orderId]);
            if (!$order) {
                return ['error' => 'Order not found'];
            }

            // Якщо замовлення сплачене, контроль оплати має бути вимкнений
            if (!empty($order->paid) && $order->paid == 1) {
                $formData['control_payment'] = 0;
                if ($deliveryData) {
                    $deliveryDataEntity->update($deliveryData->id, ['control_payment' => 0]);
                    $deliveryData->control_payment = 0;
                }
            }

            // Валідація даних замовлення
            $validationError = $this->validateOrderData($order, $deliveryData, $formData);
            if ($validationError) {
                return ['error' => $validationError];
            }

            // Отримуємо дані доставки та валідуємо телефон
            $delivery = $this->getDeliveryData($order);
            if (!$delivery || !is_array($delivery->settings) || empty($delivery->settings['service_type'])) {
                return ['error' => 'Delivery data not found'];
            }

            $serviceType = $delivery->settings['service_type'];
            $phoneFormatted = $this->formatPhone($order->phone ?? '');
            if (!$phoneFormatted) {
                return ['error' => 'User phone is invalid'];
            }

            // Розраховуємо вагу та об'єм вантажу
            $purchases = $this->backendOrdersHelper->findOrderPurchases($order);
            [$totalWeight, $totalVolume] = $this->calculateCargoWeightAndVolume($purchases);

            // Отримуємо дані відправника та отримувача
            $senderData = $this->getSenderData();
            if (!$senderData) {
                return ['error' => 'Sender not found'];
            }

            $recipientData = $this->getRecipientData($order, $phoneFormatted);
            if (!$recipientData) {
                return ['error' => 'Recipient contact person not found'];
            }

            // Формуємо API запит
            $apiRequest = $this->buildApiRequest(
                $serviceType,
                $formData,
                $deliveryData,
                $order,
                $senderData,
                $recipientData,
                $phoneFormatted,
                $volumetricParams,
                $warehouseParams,
                $totalWeight,
                $totalVolume
            );

            if (!$apiRequest) {
                return ['error' => 'Failed to build API request'];
            }

            // Відправляємо запит та обробляємо відповідь
            $result = $this->apiHelper->sendApiRequest($apiRequest);
            $response = $this->processApiResponse($result, $orderId);

            // Перевіряємо чи є помилка в відповіді
            if (!empty($response['error'])) {
                return $response;
            }

            // Якщо накладна створена успішно, зберігаємо дані з форми в БД та отримуємо tracking дані
            if (!empty($response['int_doc_number'])) {
                // Зберігаємо всі значення з форми в БД після успішного створення накладної
                $this->saveDeliveryData($deliveryDataEntity, $deliveryData, $formData, $volumetricParams, $warehouseParams, true);

                // Оновлюємо локальний об'єкт deliveryData після збереження
                $deliveryData = $deliveryDataEntity->findOne(['order_id' => $orderId]);

                $trackingDocument = $this->getTrackingDocument(
                    $orderId,
                    $response['int_doc_number'],
                    $phoneFormatted,
                    [$this, 'enrichTrackingDocument'],
                    $deliveryData,
                    $orderId,
                    $order,
                    $formData['additional_information']
                );

                if ($trackingDocument) {
                    $this->formatTrackingDocument($trackingDocument);
                    $response['tracking_document'] = $trackingDocument;
                }
            }

            return $response;
        } catch (\Exception $e) {
            error_log('NovaPoshtaDocumentService::generateDocument error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return ['error' => 'Internal Server Error: ' . $e->getMessage()];
        }
    }

    /**
     * Отримує значення з GET або POST запиту
     */
    private function getRequestParam($name, $default = null)
    {
        return $this->request->get($name) ?? $this->request->post($name) ?? $default;
    }

    /**
     * Отримує значення з пріоритетом: форма -> БД -> налаштування -> за замовчуванням
     */
    private function getPriorityValue($formValue, $dbValue, ?string $settingKey, $default = null)
    {
        if ($formValue !== null && $formValue !== '') {
            return $formValue;
        }
        if ($dbValue !== null && $dbValue !== '') {
            return $dbValue;
        }
        if ($settingKey !== null) {
            return $this->settings->get($settingKey) ?? $default;
        }
        return $default;
    }

    /**
     * Нормалізує числове значення (замінює кому на крапку)
     */
    private function normalizeNumber($value)
    {
        if ($value === null || $value === '' || $value === '0') {
            return null;
        }
        return str_replace(',', '.', trim((string)$value));
    }

    /**
     * Отримує та нормалізує дані з форми
     * Пріоритет: форма -> БД -> налаштування
     */
    private function getFormData($deliveryData): array
    {
        // Додаткова інформація
        $additionalInformation = $this->getRequestParam('additional_information_value');
        if ($additionalInformation === null && $deliveryData && !empty($deliveryData->additional_information)) {
            $additionalInformation = $deliveryData->additional_information;
        }
        $additionalInformation = $additionalInformation ? mb_substr(trim((string)$additionalInformation), 0, 100) : '';

        // Control payment
        $controlPayment = $this->getRequestParam('control_payment');
        if ($controlPayment === null) {
            $controlPayment = $this->getPriorityValue(null, ($deliveryData && isset($deliveryData->control_payment)) ? $deliveryData->control_payment : null, 'novapost_payment_control', null);
        }
        $controlPayment = $controlPayment !== null ? (int)$controlPayment : null;

        // Pickup locker
        $pickupLockerFromForm = $this->getRequestParam('pickup_locker');
        $pickupLocker = $this->getPriorityValue($pickupLockerFromForm, ($deliveryData && isset($deliveryData->pickup_locker)) ? $deliveryData->pickup_locker : null, null, 0);
        $pickupLocker = (int)$pickupLocker;

        // Параметри габаритів для поштомату
        $volumetricParamsFromForm = [];
        $volumetricParamNames = ['volume' => 'volumetric_volume', 'length' => 'volumetric_length', 'width' => 'volumetric_width', 'height' => 'volumetric_height', 'weight' => 'volumetric_weight'];
        foreach ($volumetricParamNames as $key => $paramName) {
            $value = $this->getRequestParam($paramName);
            $volumetricParamsFromForm[$key] = $value !== null ? $this->normalizeNumber($value) : null;
        }

        // Параметри габаритів для відділення
        $warehouseVolume = $this->getRequestParam('warehouse_volume');
        $warehouseWeight = $this->getRequestParam('warehouse_weight');
        $warehouseParamsFromForm = [
            'volume' => $warehouseVolume !== null ? $this->normalizeNumber($warehouseVolume) : null,
            'weight' => $warehouseWeight !== null ? $this->normalizeNumber($warehouseWeight) : null,
        ];

        // Отримуємо значення з форми з пріоритетом: форма -> БД -> налаштування
        $payerType = $this->getPriorityValue(
            $this->getRequestParam('payer_type_value'),
            ($deliveryData && isset($deliveryData->payer_type)) ? $deliveryData->payer_type : null,
            'novapost_payer_type'
        );

        $cargoType = $this->getPriorityValue(
            $this->getRequestParam('cargo_type_value'),
            ($deliveryData && isset($deliveryData->cargo_type)) ? $deliveryData->cargo_type : null,
            'novapost_cargo_type'
        );

        $paymentMethod = $this->getPriorityValue(
            $this->getRequestParam('payment_method_value'),
            ($deliveryData && isset($deliveryData->payment_method)) ? $deliveryData->payment_method : null,
            'novapost_payment_method'
        );

        $backPayerType = $this->getPriorityValue(
            $this->getRequestParam('back_payer_type_value'),
            ($deliveryData && isset($deliveryData->back_payer_type)) ? $deliveryData->back_payer_type : null,
            'novapost_back_payer_type'
        );

        return [
            'payer_type' => $payerType,
            'cargo_type' => $cargoType,
            'payment_method' => $paymentMethod,
            'back_payer_type' => $backPayerType,
            'additional_information' => $additionalInformation,
            'control_payment' => $controlPayment,
            'control_payment_value' => $this->getRequestParam('control_payment_value'),
            'pickup_locker' => $pickupLocker,
            'pickup_locker_from_form' => $pickupLockerFromForm,
            'volumetric_params_from_form' => $volumetricParamsFromForm,
            'warehouse_params_from_form' => $warehouseParamsFromForm,
        ];
    }

    /**
     * Валідує параметри габаритів
     */
    private function validateVolumetricParams(array $params): ?string
    {
        $limits = [
            'length' => ['max' => 60, 'name' => 'Довжина'],
            'width' => ['max' => 40, 'name' => 'Ширина'],
            'height' => ['max' => 30, 'name' => 'Висота'],
            'weight' => ['max' => 20, 'name' => 'Вага'],
        ];

        foreach ($limits as $key => $limit) {
            if (!empty($params[$key])) {
                $value = (float)str_replace(',', '.', trim((string)$params[$key]));
                if ($value > $limit['max']) {
                    return $limit['name'] . ' не може перевищувати ' . $limit['max'] . ($key === 'weight' ? ' кг' : ' см') . ' для поштомату';
                }
            }
        }

        return null;
    }

    /**
     * Отримує фінальні значення параметрів габаритів
     */
    private function getVolumetricParams(array $paramsFromForm, $deliveryData): array
    {
        return [
            'volume' => $this->getParamValue($paramsFromForm['volume'] ?? null, ($deliveryData && isset($deliveryData->volumetric_volume)) ? $deliveryData->volumetric_volume : null, 'novapost_volumetric_volume', '0.001'),
            'length' => $this->getParamValue($paramsFromForm['length'] ?? null, ($deliveryData && isset($deliveryData->volumetric_length)) ? $deliveryData->volumetric_length : null, 'novapost_volumetric_length', '10'),
            'width' => $this->getParamValue($paramsFromForm['width'] ?? null, ($deliveryData && isset($deliveryData->volumetric_width)) ? $deliveryData->volumetric_width : null, 'novapost_volumetric_width', '10'),
            'height' => $this->getParamValue($paramsFromForm['height'] ?? null, ($deliveryData && isset($deliveryData->volumetric_height)) ? $deliveryData->volumetric_height : null, 'novapost_volumetric_height', '10'),
            'weight' => $this->getParamValue($paramsFromForm['weight'] ?? null, ($deliveryData && isset($deliveryData->volumetric_weight)) ? $deliveryData->volumetric_weight : null, 'novapost_volumetric_weight', '0.5'),
        ];
    }

    /**
     * Отримує фінальні значення параметрів для відділення
     */
    private function getWarehouseParams(array $paramsFromForm, $deliveryData): array
    {
        return [
            'volume' => $this->getParamValue($paramsFromForm['volume'] ?? null, ($deliveryData && isset($deliveryData->warehouse_volume)) ? $deliveryData->warehouse_volume : null, 'novapost_warehouse_volume', '0.0004'),
            'weight' => $this->getParamValue($paramsFromForm['weight'] ?? null, ($deliveryData && isset($deliveryData->warehouse_weight)) ? $deliveryData->warehouse_weight : null, 'novapost_warehouse_weight', '0.5'),
        ];
    }

    /**
     * Отримує значення параметра з пріоритетом: форма -> БД -> налаштування -> за замовчуванням
     */
    private function getParamValue($formValue, $dbValue, $settingKey, $default = null)
    {
        $normalized = $this->normalizeNumber($formValue);
        if ($normalized !== null) {
            return $normalized;
        }
        if ($dbValue && $dbValue !== '0') {
            return str_replace(',', '.', trim((string)$dbValue));
        }
        if ($settingKey !== null) {
            $settingValue = $this->settings->get($settingKey);
            if ($settingValue && $settingValue !== '0') {
                return str_replace(',', '.', trim((string)$settingValue));
            }
        }
        return $default !== null ? $default : '';
    }

    /**
     * Зберігає дані доставки в БД
     * 
     * @param NPCostDeliveryDataEntity $deliveryDataEntity
     * @param object|null $deliveryData
     * @param array $formData
     * @param array $volumetricParams
     * @param array $warehouseParams
     * @param bool $afterSuccess Якщо true, зберігає всі значення навіть порожні
     */
    private function saveDeliveryData(
        NPCostDeliveryDataEntity $deliveryDataEntity,
        $deliveryData,
        array $formData,
        array $volumetricParams,
        array $warehouseParams,
        bool $afterSuccess = false
    ): void {
        $orderId = $this->request->get('order_id', 'int');

        $updateData = [
            'payer_type' => $formData['payer_type'],
            'cargo_type' => $formData['cargo_type'],
            'payment_method' => $formData['payment_method'],
            'back_payer_type' => $formData['back_payer_type'],
            'additional_information' => $formData['additional_information'],
        ];

        if ($afterSuccess) {
            // Після успішного створення накладної зберігаємо всі значення
            $updateData['control_payment'] = $formData['control_payment'];
            $updateData['pickup_locker'] = $formData['pickup_locker'];

            // Зберігаємо cost з форми
            $costFromForm = $this->getRequestParam('control_payment_value');
            if ($costFromForm !== null && $costFromForm !== '') {
                $updateData['cost'] = (float)str_replace(',', '.', trim((string)$costFromForm));
            }

            // Параметри габаритів для поштомату (зберігаємо значення з форми)
            $volumetricParamNames = ['volume' => 'volumetric_volume', 'length' => 'volumetric_length', 'width' => 'volumetric_width', 'height' => 'volumetric_height', 'weight' => 'volumetric_weight'];
            foreach ($volumetricParamNames as $key => $paramName) {
                $value = $this->getRequestParam($paramName);
                if ($value !== null) {
                    $value = trim((string)$value);
                    $updateData['volumetric_' . $key] = ($value !== '' && $value !== '0') ? str_replace(',', '.', $value) : null;
                } else {
                    $updateData['volumetric_' . $key] = null;
                }
            }

            // Параметри габаритів для відділення
            $warehouseVolume = $this->getRequestParam('warehouse_volume');
            $warehouseWeight = $this->getRequestParam('warehouse_weight');
            if ($warehouseVolume !== null) {
                $warehouseVolume = trim((string)$warehouseVolume);
                $updateData['warehouse_volume'] = ($warehouseVolume !== '' && $warehouseVolume !== '0') ? str_replace(',', '.', $warehouseVolume) : null;
            } else {
                $updateData['warehouse_volume'] = null;
            }

            if ($warehouseWeight !== null) {
                $warehouseWeight = trim((string)$warehouseWeight);
                $updateData['warehouse_weight'] = ($warehouseWeight !== '' && $warehouseWeight !== '0') ? str_replace(',', '.', $warehouseWeight) : null;
            } else {
                $updateData['warehouse_weight'] = null;
            }
        } else {
            // Перед створенням накладної зберігаємо тільки не порожні значення
            // Параметри габаритів для поштомату
            foreach (['volume', 'length', 'width', 'height', 'weight'] as $key) {
                if (!empty($formData['volumetric_params_from_form'][$key])) {
                    $updateData['volumetric_' . $key] = str_replace(',', '.', trim((string)$formData['volumetric_params_from_form'][$key]));
                }
            }

            // Параметри габаритів для відділення
            if (!empty($formData['warehouse_params_from_form']['volume'])) {
                $updateData['warehouse_volume'] = str_replace(',', '.', trim((string)$formData['warehouse_params_from_form']['volume']));
            }
            if (!empty($formData['warehouse_params_from_form']['weight'])) {
                $updateData['warehouse_weight'] = str_replace(',', '.', trim((string)$formData['warehouse_params_from_form']['weight']));
            }

            if ($formData['pickup_locker_from_form'] !== null) {
                $updateData['pickup_locker'] = $formData['pickup_locker'];
            }

            $controlPayment = $this->getRequestParam('control_payment');
            if ($controlPayment !== null) {
                $updateData['control_payment'] = (int)$controlPayment;
            }
        }

        if (!$deliveryData || empty($deliveryData->id)) {
            $updateData['order_id'] = $orderId;
            if ($deliveryData) {
                if (!empty($deliveryData->city_id)) {
                    $updateData['city_id'] = $deliveryData->city_id;
                }
                if (!empty($deliveryData->warehouse_id)) {
                    $updateData['warehouse_id'] = $deliveryData->warehouse_id;
                }
            }
            $deliveryDataEntity->add($updateData);
        } else {
            $deliveryDataEntity->update($deliveryData->id, $updateData);
        }
    }

    /**
     * Валідує дані замовлення
     */
    private function validateOrderData($order, $deliveryData, array $formData)
    {
        if (!$deliveryData || empty($deliveryData->city_id)) {
            return 'Empty client city';
        }
        if (empty($order->name)) {
            return 'Empty user name';
        }
        if (empty($order->phone)) {
            return 'Empty user phone';
        }
        if (empty($formData['cargo_type'])) {
            return '0 - CargoType not selected';
        }
        if (empty($formData['payer_type'])) {
            return '1 - Payer type not selected';
        }
        if (empty($formData['payment_method'])) {
            return '2 - Payment method not selected';
        }

        return null;
    }

    /**
     * Отримує дані доставки
     */
    private function getDeliveryData($order)
    {
        if (empty($order->delivery_id)) {
            return null;
        }

        $deliveryEntity = $this->entityFactory->get(DeliveriesEntity::class);
        $delivery = $deliveryEntity->get($order->delivery_id);

        if (!$delivery) {
            return null;
        }

        if (!empty($delivery->settings)) {
            try {
                $unserialized = unserialize($delivery->settings);
                $delivery->settings = is_array($unserialized) ? $unserialized : [];
            } catch (\Exception $e) {
                $delivery->settings = [];
            }
        } else {
            $delivery->settings = [];
        }
        return $delivery;
    }

    /**
     * Розраховує вагу та об'єм вантажу
     */
    private function calculateCargoWeightAndVolume(array $purchases): array
    {
        $totalWeight = 0.1;
        $totalVolume = 0.001;

        foreach ($purchases as $purchase) {
            $purchaseWeight = !empty($purchase->variant->weight) ? $purchase->variant->weight : $this->settings->get('novapost_weight');
            $purchaseVolume = !empty($purchase->variant->volume) ? $purchase->variant->volume : $this->settings->get('novapost_volume');
            $totalWeight += $purchaseWeight * $purchase->amount;
            $totalVolume += $purchaseVolume * $purchase->amount;
        }

        if (empty($totalWeight)) {
            $totalWeight = $this->settings->get('novapost_weight');
        }
        if (empty($totalVolume)) {
            $totalVolume = $this->settings->get('novapost_volume');
        }

        return [$totalWeight, $totalVolume];
    }

    /**
     * Отримує дані відправника
     */
    private function getSenderData()
    {
        $senderResult = $this->apiHelper->getCounterparties(['cp_property' => 'Sender']);
        if (empty($senderResult->data[0])) {
            return null;
        }

        $sender = $senderResult->data[0];
        $senderContactPersonResult = $this->apiHelper->getContactPersonByCounterpartyRef($sender->Ref);

        if (empty($senderContactPersonResult->data[0])) {
            return null;
        }

        return [
            'sender' => $sender,
            'contact_person' => $senderContactPersonResult->data[0],
        ];
    }

    /**
     * Отримує дані отримувача
     */
    private function getRecipientData($order, $phoneFormatted)
    {
        $recipientResult = $this->apiHelper->addCounterparty($order->name, $order->last_name, $phoneFormatted);
        if (empty($recipientResult->data[0])) {
            return null;
        }

        $recipient = $recipientResult->data[0];
        if (empty($recipient->ContactPerson->data[0])) {
            return null;
        }

        return [
            'recipient' => $recipient,
            'contact_person' => $recipient->ContactPerson->data[0],
        ];
    }

    /**
     * Формує API запит залежно від типу доставки
     */
    private function buildApiRequest(
        $serviceType,
        array $formData,
        $deliveryData,
        $order,
        array $senderData,
        array $recipientData,
        $phoneFormatted,
        array $volumetricParams,
        array $warehouseParams,
        $totalWeight,
        $totalVolume
    ) {
        $date = $this->getShipmentDate();
        $currency = $this->getCurrency();

        // Використовуємо cost з форми (control_payment_value), якщо він є, інакше з order
        $costFromForm = $this->getRequestParam('control_payment_value');
        if ($costFromForm !== null && $costFromForm !== '') {
            $cost = (float)str_replace(',', '.', trim((string)$costFromForm));
        } elseif (!empty($formData['control_payment_value'])) {
            $cost = (float)str_replace(',', '.', trim((string)$formData['control_payment_value']));
        } else {
            $cost = $this->money->convert($order->total_price, $currency->id, false);
        }

        return $this->buildDocumentRequest(
            $serviceType,
            $formData,
            $deliveryData,
            $order,
            $senderData,
            $recipientData,
            $phoneFormatted,
            $date,
            $cost,
            $volumetricParams,
            $warehouseParams,
            $totalWeight,
            $totalVolume
        );
    }

    /**
     * Формує API запит для створення експрес-накладної
     */
    private function buildDocumentRequest(
        $serviceType,
        array $formData,
        $deliveryData,
        $order,
        array $senderData,
        array $recipientData,
        $phoneFormatted,
        $date,
        $cost,
        array $volumetricParams,
        array $warehouseParams,
        $totalWeight,
        $totalVolume
    ) {
        // Визначаємо тип доставки: на склад (Warehouse) або до дверей (Doors)
        $isWarehouseDelivery = ($serviceType === 'WarehouseWarehouse' || $serviceType === 'DoorsWarehouse');

        // Базові параметри запиту (спільні для обох типів)
        $methodProperties = [
            "NewAddress" => "1",
            "CitySender" => $this->settings->get('newpost_city'),
            "SenderAddress" => $this->settings->get('novapost_sender_warehouse'),
            "ContactSender" => $senderData['contact_person']->Ref,
            "SendersPhone" => $this->settings->get('novapost_sender_phone'),
            "Sender" => $senderData['sender']->Ref,
            "PayerType" => $formData['payer_type'],
            "PaymentMethod" => $formData['payment_method'],
            "CargoType" => $formData['cargo_type'],
            "ServiceType" => $serviceType,
            "SeatsAmount" => "1",
            "Description" => 'Замовлення №' . $order->id,
            "Cost" => $cost,
            "DateTime" => $date,
            "InfoRegClientBarcodes" => (string)$order->id,
        ];

        // Параметри отримувача залежно від типу доставки
        if ($isWarehouseDelivery) {
            // Доставка на склад/поштомат
            $methodProperties["CityRecipient"] = $deliveryData ? $deliveryData->city_id : '';
            $methodProperties["RecipientAddress"] = $deliveryData ? $deliveryData->warehouse_id : '';
            $methodProperties["ContactRecipient"] = $recipientData['contact_person']->Ref;
            $methodProperties["RecipientsPhone"] = $phoneFormatted;
            $methodProperties["Recipient"] = $recipientData['recipient']->Ref;
        } else {
            // Доставка до дверей
            $methodProperties["RecipientCityName"] = $deliveryData ? ($deliveryData->city_name ?? '') : '';
            $methodProperties["RecipientArea"] = $deliveryData ? ($deliveryData->area_name ?? '') : '';
            $methodProperties["RecipientAreaRegions"] = $deliveryData ? ($deliveryData->region_name ?? '') : '';
            $methodProperties["RecipientAddressName"] = $deliveryData ? ($deliveryData->street ?? '') : '';
            $methodProperties["RecipientHouse"] = $deliveryData ? ($deliveryData->house ?? '') : '';
            $methodProperties["RecipientFlat"] = $deliveryData ? ($deliveryData->apartment ?? '') : '';
            $methodProperties["RecipientName"] = "{$order->last_name} {$order->name}";
            $methodProperties["RecipientType"] = "PrivatePerson";
            $methodProperties["RecipientsPhone"] = $phoneFormatted;
        }

        $apiRequest = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "InternetDocument",
            "calledMethod" => "save",
            "methodProperties" => $methodProperties
        ];

        $this->addAdditionalInformationToRequest($apiRequest, $formData);
        $this->addCargoParamsToRequest($apiRequest, $formData, $volumetricParams, $warehouseParams, $totalWeight, $totalVolume);
        $this->addPaymentControlToRequest($apiRequest, $formData, $order, $deliveryData);
        $this->addBackwardDeliveryToRequest($apiRequest, $formData, $deliveryData, $cost);

        return $apiRequest;
    }

    /**
     * Додає AdditionalInformation до API запиту якщо воно не порожнє
     */
    private function addAdditionalInformationToRequest(array &$apiRequest, array $formData)
    {
        if (!empty($formData['additional_information'])) {
            $apiRequest["methodProperties"]["AdditionalInformation"] = trim($formData['additional_information']);
        }
    }

    /**
     * Додає параметри вантажу до API запиту
     */
    private function addCargoParamsToRequest(
        array &$apiRequest,
        array $formData,
        array $volumetricParams,
        array $warehouseParams,
        float $totalWeight,
        float $totalVolume
    ): void {
        $pickupLocker = (int)($formData['pickup_locker'] ?? 0);
        $cargoType = $formData['cargo_type'];

        // Для поштомату
        if ($pickupLocker === 1) {
            // Нормалізуємо параметри з дефолтними значеннями
            foreach (self::LOCKER_DEFAULTS as $key => $default) {
                $volumetricParams[$key] = ($volumetricParams[$key] && $volumetricParams[$key] !== '0')
                    ? (string)$volumetricParams[$key]
                    : $default;
            }

            // Валідація розмірів
            foreach (['length', 'width', 'height', 'weight'] as $key) {
                if ((float)$volumetricParams[$key] > self::LOCKER_MAX[$key]) {
                    return; // Повертаємо null через зовнішній метод
                }
            }

            $optionsSeat = [
                "volumetricVolume" => $volumetricParams['volume'],
                "volumetricLength" => $volumetricParams['length'],
                "volumetricWidth" => $volumetricParams['width'],
                "volumetricHeight" => $volumetricParams['height'],
                "weight" => $cargoType === 'Documents' ? '1' : $volumetricParams['weight'],
            ];

            if ($cargoType === 'Cargo') {
                $optionsSeat["VolumeGeneral"] = (string)($this->settings->get('volume_general') ?: $totalVolume);
            }

            $apiRequest["methodProperties"]["OptionsSeat"] = [$optionsSeat];
            $apiRequest["methodProperties"]["SeatsAmount"] = "1";
            return;
        }

        // Для відділення
        if ($cargoType === 'Cargo') {
            $warehouseVolume = ($warehouseParams['volume'] && $warehouseParams['volume'] !== '0')
                ? (float)$warehouseParams['volume']
                : (float)$totalVolume;

            $warehouseWeight = ($warehouseParams['weight'] && $warehouseParams['weight'] !== '0')
                ? (float)$warehouseParams['weight']
                : (float)$totalWeight;

            // Перевірка об'ємної ваги
            if ($warehouseVolume * self::VOLUMETRIC_WEIGHT_COEFFICIENT > self::WAREHOUSE_MAX_WEIGHT) {
                $warehouseVolume = self::WAREHOUSE_MAX_VOLUME;
            }

            // Валідація та нормалізація
            $warehouseWeight = min(max($warehouseWeight, self::WAREHOUSE_MIN_WEIGHT), self::WAREHOUSE_MAX_WEIGHT);
            $warehouseVolume = max($warehouseVolume, self::WAREHOUSE_MIN_VOLUME);

            $apiRequest["methodProperties"]["VolumeGeneral"] = (string)$warehouseVolume;
            $apiRequest["methodProperties"]["Weight"] = (string)$warehouseWeight;
        } elseif ($cargoType === 'Documents') {
            $apiRequest["methodProperties"]["Weight"] = '1';
        }
    }

    /**
     * Додає контроль оплати до API запиту
     */
    private function addPaymentControlToRequest(array &$apiRequest, array $formData, $order, $deliveryData)
    {
        if (($formData['control_payment'] ?? 0) == 1 && ($deliveryData && empty($deliveryData->redelivery))) {
            $amount = !empty($formData['control_payment_value'])
                ? round((float)$formData['control_payment_value'])
                : round($order->undiscounted_total_price);
            $apiRequest["methodProperties"]["AfterpaymentOnGoodsCost"] = $amount;
        }
    }

    /**
     * Додає зворотну доставку до API запиту
     */
    private function addBackwardDeliveryToRequest(array &$apiRequest, array $formData, $deliveryData, $cost)
    {
        if ($deliveryData && !empty($deliveryData->redelivery) && ($formData['control_payment'] ?? 0) == 0) {
            $apiRequest['methodProperties']['BackwardDeliveryData'][] = [
                "PayerType" => $this->settings->get('novapost_back_payer_type'),
                'CargoType' => 'Money',
                'RedeliveryString' => round($cost),
            ];
        }
    }

    /**
     * Отримує дату відправлення
     */
    private function getShipmentDate()
    {
        $todayDateTimestamp = strtotime($this->settings->get('novapost_time_today_date'));
        return time() > $todayDateTimestamp ? date('d.m.Y', time() + 86400) : date('d.m.Y');
    }

    /**
     * Отримує валюту
     */
    private function getCurrency()
    {
        $currenciesEntity = $this->entityFactory->get(CurrenciesEntity::class);
        if ($this->settings->get('novapost_currency_id')) {
            return $currenciesEntity->get((int)$this->settings->get('novapost_currency_id'));
        }
        return $currenciesEntity->getMainCurrency();
    }

    /**
     * Обробляє відповідь API
     */
    private function processApiResponse($result, $orderId)
    {
        if (empty($result->success) || empty($result->data[0])) {
            $errorMessage = $this->apiHelper->getErrorMessage($result);
            
            // Якщо помилка про недоступність контролю оплати, надаємо більш зрозуміле повідомлення
            if (stripos($errorMessage, 'AfterpaymentOnGoodsCost') !== false && stripos($errorMessage, 'unavailable') !== false) {
                $errorMessage = 'Контроль оплати недоступний. Будь ласка, вимкніть контроль оплати та спробуйте ще раз.';
            }
            
            return ['error' => $errorMessage];
        }

        $documentData = $result->data[0];
        $response = [
            'int_doc_number' => $documentData->IntDocNumber,
            'ref_id' => $documentData->Ref,
        ];

        // Зберігаємо tracking дані
        $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
        $existingTracking = $trackingEntity->findOne(['order_id' => $orderId]);

        $trackingData = [
            'order_id' => $orderId,
            'int_doc_number' => $documentData->IntDocNumber,
            'ref_id' => $documentData->Ref,
            'status_code' => '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existingTracking) {
            $trackingData['created_at'] = $existingTracking->created_at;
            $trackingEntity->update($existingTracking->id, $trackingData);
        } else {
            $trackingEntity->add($trackingData);
        }

        return $response;
    }


    /**
     * Додає додаткові дані до tracking документа
     */
    private function enrichTrackingDocument($trackingDocument, $deliveryData, $orderId, $order, $additionalInformation)
    {
        if (!empty($trackingDocument->Number)) {
            $trackingDocument->formatNumber = $this->formatDocumentNumber($trackingDocument->Number);
        }

        $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
        $trackingData = $trackingEntity->findOne(['order_id' => $orderId]);

        $trackingDocument->refId = $trackingDocument->Ref ?? $trackingData->ref_id ?? null;

        if ($trackingData) {
            $trackingDocument->fromTrackingResponse = true;
            $trackingDocument->trackingUpdateAt = $trackingData->tracking_update_at;
            $trackingDocument->actualDeliveryAt = $trackingData->actual_delivery_at;
            $trackingDocument->statusCode = $trackingData->status_code;
            $trackingDocument->trackingUpdatedAt = $trackingData->updated_at;
        }

        $this->formatTrackingDates($trackingDocument);

        $needsDeliveryData = empty($trackingDocument->AdditionalInformation) || empty($trackingDocument->VolumeGeneral);
        $updatedDeliveryData = $needsDeliveryData ? $this->entityFactory->get(NPCostDeliveryDataEntity::class)->findOne(['order_id' => $orderId]) : null;

        if (empty($trackingDocument->AdditionalInformation)) {
            if ($additionalInformation) {
                $trackingDocument->AdditionalInformation = $additionalInformation;
            } elseif ($updatedDeliveryData && isset($updatedDeliveryData->additional_information)) {
                $trackingDocument->AdditionalInformation = $updatedDeliveryData->additional_information;
            } elseif ($deliveryData && isset($deliveryData->additional_information)) {
                $trackingDocument->AdditionalInformation = $deliveryData->additional_information;
            } else {
                $trackingDocument->AdditionalInformation = '';
            }
        }

        if (empty($trackingDocument->VolumeGeneral)) {
            if ($updatedDeliveryData && isset($updatedDeliveryData->warehouse_volume)) {
                $trackingDocument->VolumeGeneral = $updatedDeliveryData->warehouse_volume;
            } elseif ($deliveryData && isset($deliveryData->warehouse_volume)) {
                $trackingDocument->VolumeGeneral = $deliveryData->warehouse_volume;
            } else {
                $trackingDocument->VolumeGeneral = null;
            }
        }

        $this->addAnnouncedPrice($trackingDocument, $deliveryData, $order);
        $this->addPaymentControl($trackingDocument, $deliveryData, $order);
        $this->formatAmounts($trackingDocument);
    }

    /**
     * Додає оголошену цінність
     */
    private function addAnnouncedPrice($trackingDocument, $deliveryData, $order)
    {
        if (empty($trackingDocument->AnnouncedPrice)) {
            if ($deliveryData && isset($deliveryData->cost)) {
                $trackingDocument->AnnouncedPrice = $deliveryData->cost;
            } elseif (isset($order->undiscounted_total_price)) {
                $trackingDocument->AnnouncedPrice = $order->undiscounted_total_price;
            } elseif (isset($order->total_price)) {
                $trackingDocument->AnnouncedPrice = $order->total_price;
            } else {
                $trackingDocument->AnnouncedPrice = null;
            }
        }
    }

    /**
     * Додає контроль оплати
     */
    private function addPaymentControl($trackingDocument, $deliveryData, $order)
    {
        if (empty($trackingDocument->AfterpaymentOnGoodsCost) && $deliveryData && ($deliveryData->control_payment ?? 0) == 1) {
            if ($deliveryData && isset($deliveryData->cost)) {
                $amount = $deliveryData->cost;
            } elseif (isset($order->undiscounted_total_price)) {
                $amount = $order->undiscounted_total_price;
            } elseif (isset($order->total_price)) {
                $amount = $order->total_price;
            } else {
                $amount = 0;
            }
            $trackingDocument->AfterpaymentOnGoodsCost = round($amount);
        }
    }

    /**
     * Форматує tracking документ для відображення
     */
    private function formatTrackingDocument($trackingDocument)
    {
        // Дані відправника
        $this->addSenderInfo($trackingDocument);

        // Кількість місць
        if (empty($trackingDocument->SeatsAmount)) {
            $trackingDocument->SeatsAmount = '1';
        }

        // Адреси
        $trackingDocument->SenderAddressFormatted = $this->truncateAddress($trackingDocument->WarehouseSenderAddress ?? $trackingDocument->SenderAddress ?? '');
        $trackingDocument->RecipientAddressFormatted = $this->truncateAddress($trackingDocument->WarehouseRecipientAddress ?? $trackingDocument->RecipientAddress ?? '');

        // Переклади значень API на українську мову
        $this->translateApiValues($trackingDocument);

        // Визначаємо фінальне значення типу контрагента відправника
        $trackingDocument->SenderCounterpartyTypeFinal = $this->getSenderCounterpartyType($trackingDocument);

        // Визначаємо планову дату доставки
        $trackingDocument->ScheduledDeliveryDateFinal = $this->getScheduledDeliveryDate($trackingDocument);

        // Вага - використовуємо DocumentWeight з API
        if (!empty($trackingDocument->DocumentWeight)) {
            $documentWeight = (float)$trackingDocument->DocumentWeight;
            if ($documentWeight == floor($documentWeight)) {
                $trackingDocument->DocumentWeightFormatted = (int)$documentWeight . ' кг';
            } else {
                $trackingDocument->DocumentWeightFormatted = number_format($documentWeight, 1, '.', '') . ' кг';
            }
        } elseif (!empty($trackingDocument->Weight)) {
            $documentWeight = (float)$trackingDocument->Weight;
            $trackingDocument->DocumentWeight = $documentWeight;
            if ($documentWeight == floor($documentWeight)) {
                $trackingDocument->DocumentWeightFormatted = (int)$documentWeight . ' кг';
            } else {
                $trackingDocument->DocumentWeightFormatted = number_format($documentWeight, 1, '.', '') . ' кг';
            }
        }

        // Об'ємна вага: розраховуємо з VolumeGeneral * 250
        $calculatedVolumetricWeight = null;
        $volumeGeneral = null;

        if (!empty($trackingDocument->VolumeGeneral)) {
            $volumeGeneral = (float)$trackingDocument->VolumeGeneral;
        } elseif (!empty($trackingDocument->OptionsSeat) && is_array($trackingDocument->OptionsSeat) && !empty($trackingDocument->OptionsSeat[0])) {
            $optionsSeat = $trackingDocument->OptionsSeat[0];
            if (!empty($optionsSeat->volumetricVolume)) {
                $volumeGeneral = (float)$optionsSeat->volumetricVolume;
            }
        }

        if ($volumeGeneral !== null && $volumeGeneral > 0) {
            $calculatedVolumetricWeight = $volumeGeneral * self::VOLUMETRIC_WEIGHT_COEFFICIENT;
        }

        if ($calculatedVolumetricWeight !== null) {
            $trackingDocument->VolumeWeight = $calculatedVolumetricWeight;
            if ($calculatedVolumetricWeight == floor($calculatedVolumetricWeight)) {
                $trackingDocument->VolumeWeightFormatted = (int)$calculatedVolumetricWeight . ' кг';
            } else {
                $trackingDocument->VolumeWeightFormatted = number_format($calculatedVolumetricWeight, 1, '.', '') . ' кг';
            }
        } elseif (!empty($trackingDocument->VolumeWeight)) {
            $volumeWeight = (float)$trackingDocument->VolumeWeight;
            if ($volumeWeight == floor($volumeWeight)) {
                $trackingDocument->VolumeWeightFormatted = (int)$volumeWeight . ' кг';
            } else {
                $trackingDocument->VolumeWeightFormatted = number_format($volumeWeight, 1, '.', '') . ' кг';
            }
        }

        // Форматуємо фактичну вагу
        if (!empty($trackingDocument->FactualWeight)) {
            $factualWeight = (float)$trackingDocument->FactualWeight;
            $trackingDocument->FactualWeightFormatted = number_format($factualWeight, ($factualWeight == floor($factualWeight)) ? 0 : 2, '.', '') . ' кг';
        }
    }

    /**
     * Додає інформацію про відправника
     */
    private function addSenderInfo($trackingDocument)
    {
        $senderResult = $this->apiHelper->getCounterparties(['cp_property' => 'Sender']);
        if (empty($senderResult->data[0])) {
            return;
        }

        $sender = $senderResult->data[0];
        $senderContactPersonResult = $this->apiHelper->getContactPersonByCounterpartyRef($sender->Ref);

        if (empty($senderContactPersonResult->data[0])) {
            return;
        }

        $senderContactPerson = $senderContactPersonResult->data[0];

        // ПІБ відправника
        if ($senderContactPerson->FirstName || $senderContactPerson->LastName || $senderContactPerson->MiddleName) {
            $trackingDocument->SenderFullName = trim(
                ($senderContactPerson->FirstName ?? '') . ' ' .
                    ($senderContactPerson->MiddleName ?? '') . ' ' .
                    ($senderContactPerson->LastName ?? '')
            );
        }

        // Телефон відправника
        if (!empty($senderContactPerson->Phones)) {
            $trackingDocument->SenderPhone = is_array($senderContactPerson->Phones)
                ? $senderContactPerson->Phones[0]
                : $senderContactPerson->Phones;
        } else {
            $senderPhone = $this->settings->get('novapost_sender_phone');
            if ($senderPhone) {
                $trackingDocument->SenderPhone = $senderPhone;
            }
        }

        if ($sender->CounterpartyType) {
            $trackingDocument->SenderCounterpartyType = $sender->CounterpartyType;
        }

        // Відділення відправника
        if (
            empty($trackingDocument->WarehouseSenderAddress) && empty($trackingDocument->SenderAddress)
            && empty($trackingDocument->WarehouseSender) && empty($trackingDocument->WarehouseSenderNumber)
        ) {
            $senderWarehouseRef = $this->settings->get('novapost_sender_warehouse');
            if ($senderWarehouseRef) {
                $warehouseResult = $this->apiHelper->getWarehouseByRef($senderWarehouseRef);
                if (!empty($warehouseResult->data[0])) {
                    $warehouse = $warehouseResult->data[0];
                    if ($warehouse->Number) {
                        $trackingDocument->WarehouseSenderNumber = $warehouse->Number;
                    }
                    if ($warehouse->Description) {
                        $trackingDocument->WarehouseSender = $warehouse->Description;
                    }
                    $trackingDocument->WarehouseSenderAddress = $warehouse->ShortAddress ?? $warehouse->Address ?? null;
                }
            }
        }
        }

    /**
     * Отримує tracking документ з БД або API
     * 
     * @param int $orderId ID замовлення
     * @param string $intDocNumber Номер накладної
     * @param string $phoneFormatted Відформатований телефон
     * @param callable|null $enrichCallback Callback функція для обогачення документа (опціонально)
     * @param mixed ...$enrichArgs Аргументи для callback функції
     * @return object|null Tracking документ або null
     */
    public function getTrackingDocument(
        $orderId,
        $intDocNumber,
        $phoneFormatted,
        $enrichCallback = null,
        ...$enrichArgs
    ) {
        $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
        $trackingData = $trackingEntity->findOne(['order_id' => $orderId]);

        // Спочатку намагаємося отримати дані з БД
        if (!empty($trackingData->tracking_response)) {
            $savedTrackingData = json_decode($trackingData->tracking_response, true);
            if ($savedTrackingData && (is_array($savedTrackingData) || is_object($savedTrackingData))) {
                $trackingDocument = (object)$savedTrackingData;
                
                // Викликаємо callback для обогачення документа, якщо він переданий
                if ($enrichCallback && is_callable($enrichCallback)) {
                    $enrichCallback($trackingDocument, ...$enrichArgs);
                }
                
                return $trackingDocument;
            }
        }

        // Якщо даних немає в БД, завантажуємо з API
        $request = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "TrackingDocument",
            "calledMethod" => "getStatusDocuments",
            "methodProperties" => [
                "Documents" => [
                    [
                        "DocumentNumber" => $intDocNumber,
                        "Phone" => $phoneFormatted
                    ]
                ]
            ]
        ];

        $apiResponse = $this->apiHelper->sendApiRequest($request);

        if (!$apiResponse || !isset($apiResponse->success) || !$apiResponse->success) {
            return null;
        }

        if (!isset($apiResponse->data) || !is_array($apiResponse->data) || count($apiResponse->data) === 0) {
            return null;
        }

        if (!isset($apiResponse->data[0]) || empty($apiResponse->data[0]) || !is_object($apiResponse->data[0])) {
            return null;
        }

        $trackingDocument = $apiResponse->data[0];

        // Зберігаємо дані tracking в NovaPoshtaTrackingEntity
        $this->saveTrackingData($orderId, $trackingDocument, $intDocNumber);

        // Викликаємо callback для обогачення документа, якщо він переданий
        if ($enrichCallback && is_callable($enrichCallback)) {
            $enrichCallback($trackingDocument, ...$enrichArgs);
        }

        return $trackingDocument;
    }

    /**
     * Зберігає дані tracking в NovaPoshtaTrackingEntity
     * 
     * @param int $orderId ID замовлення
     * @param object $trackingDocument Об'єкт tracking документа з API
     * @param string $intDocNumber Номер накладної
     * @param int|null $trackingId ID існуючого tracking запису (опціонально)
     */
    public function saveTrackingData($orderId, $trackingDocument, $intDocNumber, $trackingId = null)
    {
        $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
        
        $existingTracking = null;
        if ($trackingId) {
            $existingTracking = $trackingEntity->get($trackingId);
        }
        
        if (!$existingTracking) {
            $existingTracking = $trackingEntity->findOne(['order_id' => $orderId]);
        }

        // Отримуємо ref_id з існуючого запису або з trackingDocument
        $refId = $existingTracking->ref_id ?? $trackingDocument->Ref ?? '';
        $statusCode = $trackingDocument->StatusCode ?? '';

        $trackingData = [
            'order_id' => $orderId,
            'int_doc_number' => $intDocNumber,
            'ref_id' => $refId,
            'status_code' => $statusCode,
            'tracking_update_at' => !empty($trackingDocument->TrackingUpdateDate) ? $this->convertTrackingDateToDb($trackingDocument->TrackingUpdateDate) : null,
            'actual_delivery_at' => !empty($trackingDocument->ActualDeliveryDate) ? $this->convertTrackingDateToDb($trackingDocument->ActualDeliveryDate) : null,
            'tracking_response' => json_encode($trackingDocument, JSON_UNESCAPED_UNICODE),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existingTracking) {
            $trackingData['created_at'] = $existingTracking->created_at;
            $trackingEntity->update($existingTracking->id, $trackingData);
        } else {
            $trackingData['created_at'] = date('Y-m-d H:i:s');
            $trackingEntity->add($trackingData);
        }
    }
}
