<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Services;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\EntityFactory;
use Okay\Core\Money;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\CurrenciesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaApiHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\TrackingDocumentFormatter;

/** Генерація, оновлення та видалення експрес-накладних НП */
class NovaPoshtaDocumentService
{
    use TrackingDocumentFormatter;

    private $entityFactory;
    private $settings;
    private $request;
    private $apiHelper;
    private $backendOrdersHelper;
    private $money;

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
            // Визначаємо service_type на основі типу доставки з форми
            // delivery_type_address → WarehouseDoors
            // delivery_type_warehouse або delivery_type_locker (pickup_locker) → WarehouseWarehouse
            // За замовчуванням → WarehouseWarehouse
            $serviceType = 'WarehouseWarehouse'; // За замовчуванням
            if (!empty($formData['delivery_type_address'])) {
                $serviceType = 'WarehouseDoors';
            } elseif (!empty($formData['pickup_locker']) && $formData['pickup_locker'] == 1) {
                $serviceType = 'WarehouseWarehouse'; // Поштомат
            } elseif ($delivery && is_array($delivery->settings) && !empty($delivery->settings['service_type'])) {
                // Якщо є налаштування, використовуємо їх (для зворотної сумісності)
                $serviceType = $delivery->settings['service_type'];
            }
            $phoneFormatted = $this->formatPhone($order->phone ?? '');
            if (!$phoneFormatted) {
                return ['error' => 'User phone is invalid'];
            }

            // Розраховуємо вагу та об'єм вантажу
            $purchases = $this->backendOrdersHelper->findOrderPurchases($order);
            [$totalWeight, $totalVolume] = $this->calculateCargoWeightAndVolume($purchases);

            // Отримуємо дані відправника
            $senderData = $this->getSenderData();
            if (!$senderData) {
                return ['error' => 'Sender not found'];
            }

            // Дані юридичної особи (ЄДРПОУ, назва) з модуля InvoicePayment — для накладної на компанію
            $invoicePaymentData = $this->getInvoicePaymentDataForOrder($orderId);

            // Для адресної доставки не потрібен recipientData. Для відділення/поштомату — потрібен контрагент (фіз. особа), крім випадку організації (передаємо рядком).
            $recipientData = null;
            if (empty($formData['delivery_type_address'])) {
                $isLegalEntityToWarehouse = ($formData['recipient_type'] ?? '') === 'Organization'
                    && $invoicePaymentData
                    && !empty(trim((string)$invoicePaymentData->edrpou))
                    && !empty(trim((string)$invoicePaymentData->company_name));
                if ($isLegalEntityToWarehouse) {
                    $recipientData = [];
                } else {
                    $recipientData = $this->getRecipientData($order, $phoneFormatted);
                    if (!$recipientData) {
                        return ['error' => 'Recipient contact person not found'];
                    }
                }
            }

            // Формуємо API запит
            $apiRequest = $this->buildApiRequest(
                $serviceType,
                $formData,
                $deliveryData,
                $order,
                $senderData,
                $recipientData ?: [],
                $phoneFormatted,
                $volumetricParams,
                $warehouseParams,
                $totalWeight,
                $totalVolume,
                $invoicePaymentData
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
     * Масове створення накладних для масиву замовлень
     * 
     * @param array $orderIds Масив ID замовлень
     * @return array Результат з полями: success (кількість успішних), errors (кількість помилок), details (деталі по кожному замовленню)
     */
    public function massGenerateDocuments(array $orderIds): array
    {
        $results = [
            'success' => 0,
            'errors' => 0,
            'details' => []
        ];

        foreach ($orderIds as $orderId) {
            $orderId = (int)$orderId;
            if (!$orderId) {
                $results['errors']++;
                $results['details'][$orderId] = ['error' => 'Invalid order ID'];
                continue;
            }

            try {
                $result = $this->generateDocument($orderId);
                
                if (!empty($result['error'])) {
                    $results['errors']++;
                    $results['details'][$orderId] = ['error' => $result['error']];
                } else {
                    $results['success']++;
                    $results['details'][$orderId] = [
                        'success' => true,
                        'int_doc_number' => $result['int_doc_number'] ?? null,
                        'ref_id' => $result['ref_id'] ?? null
                    ];
                }
            } catch (\Exception $e) {
                $results['errors']++;
                $results['details'][$orderId] = ['error' => $e->getMessage()];
                error_log('NovaPoshtaDocumentService::massGenerateDocuments error for order ' . $orderId . ': ' . $e->getMessage());
            }
        }

        return $results;
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

        // Коментар до адреси (тільки для адресної доставки)
        $recipientAddressNote = $this->getRequestParam('recipient_address_note_value');
        if ($recipientAddressNote === null && $deliveryData && isset($deliveryData->recipient_address_note)) {
            $recipientAddressNote = $deliveryData->recipient_address_note;
        }
        $recipientAddressNote = $recipientAddressNote ? mb_substr(trim((string)$recipientAddressNote), 0, 50) : '';

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

        // Delivery type address
        $deliveryTypeAddressFromForm = $this->getRequestParam('delivery_type_address');
        // Визначаємо чи це адресна доставка: з форми або за наявністю адресних полів в БД
        $isAddressDelivery = false;
        if ($deliveryTypeAddressFromForm !== null) {
            $isAddressDelivery = (int)$deliveryTypeAddressFromForm === 1;
        } elseif ($deliveryData && !empty($deliveryData->city_name) && !empty($deliveryData->street)) {
            $isAddressDelivery = true;
        }

        // Door delivery (доставка до дверей)
        $doorDelivery = (int)$this->getPriorityValue(
            $this->getRequestParam('door_delivery'),
            ($deliveryData->door_delivery ?? null),
            null,
            0
        );

        // Lifting floor (номер поверху)
        $liftingFloor = trim((string)$this->getPriorityValue(
            $this->getRequestParam('lifting_floor'),
            ($deliveryData->lifting_floor ?? null),
            null,
            ''
        ));

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

        $recipientType = trim((string)$this->getRequestParam('recipient_type_value'));
        if ($recipientType !== 'Organization' && $recipientType !== 'PrivatePerson') {
            $recipientType = 'PrivatePerson';
            // Масове створення: форми немає — створюємо на компанію лише якщо тип оплати InvoicePayment і є запис у sviat__invoice_payment_data
            if ($deliveryData && !empty($deliveryData->order_id)) {
                $orderId = (int)$deliveryData->order_id;
                if ($this->orderUsesInvoicePayment($orderId)) {
                    $invoiceData = $this->getInvoicePaymentDataForOrder($orderId);
                    if ($invoiceData && !empty(trim((string)$invoiceData->edrpou)) && !empty(trim((string)$invoiceData->company_name))) {
                        $recipientType = 'Organization';
                    }
                }
            }
        }

        return [
            'payer_type' => $payerType,
            'cargo_type' => $cargoType,
            'payment_method' => $paymentMethod,
            'back_payer_type' => $backPayerType,
            'recipient_type' => $recipientType,
            'additional_information' => $additionalInformation,
            'recipient_address_note' => $recipientAddressNote,
            'control_payment' => $controlPayment,
            'control_payment_value' => $this->getRequestParam('control_payment_value'),
            'pickup_locker' => $pickupLocker,
            'pickup_locker_from_form' => $pickupLockerFromForm,
            'delivery_type_address' => $isAddressDelivery,
            'door_delivery' => $doorDelivery,
            'lifting_floor' => $liftingFloor,
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
        $orderId = (int)($this->request->get('order_id') ?? $this->request->post('order_id') ?? 0);

        $updateData = [
            'payer_type' => $formData['payer_type'],
            'cargo_type' => $formData['cargo_type'],
            'payment_method' => $formData['payment_method'],
            'back_payer_type' => $formData['back_payer_type'],
            'additional_information' => $formData['additional_information'],
            'recipient_address_note' => $formData['recipient_address_note'] ?? '',
            'door_delivery' => $formData['door_delivery'],
            'lifting_floor' => $formData['lifting_floor'],
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
        // Для адресної доставки перевіряємо адресні поля
        if (!empty($formData['delivery_type_address'])) {
            if (!$deliveryData || empty($deliveryData->city_name)) {
                return 'Empty client city name for address delivery';
            }
            if (!$deliveryData || empty($deliveryData->street)) {
                return 'Empty client street for address delivery';
            }
        } else {
            // Для доставки на відділення/поштомат перевіряємо city_id
            if (!$deliveryData || empty($deliveryData->city_id)) {
                return 'Empty client city';
            }
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
     * Отримує дані отримувача (контрагент НП для доставки на відділення/поштомат).
     * Якщо в замовленні порожні ім'я/прізвище (наприклад, замовлення на юр. особу),
     * підставляється назва компанії з InvoicePayment або заглушка, щоб API НП прийняв запит.
     */
    private function getRecipientData($order, $phoneFormatted)
    {
        $orderId = (int)($order->id ?? 0);
        $firstName = trim((string)($order->name ?? ''));
        $lastName = trim((string)($order->last_name ?? ''));

        if ($firstName === '' || $lastName === '') {
            $invoiceData = $this->getInvoicePaymentDataForOrder($orderId);
            $companyName = $invoiceData && !empty(trim((string)$invoiceData->company_name))
                ? trim((string)$invoiceData->company_name)
                : '';
            if ($lastName === '' && $companyName !== '') {
                $lastName = mb_substr($companyName, 0, 99);
            }
            if ($firstName === '') {
                $firstName = $lastName !== '' ? 'Контакт' : 'Отримувач';
            }
            if ($lastName === '') {
                $lastName = 'Отримувач';
            }
        }

        $recipientResult = $this->apiHelper->addCounterparty($firstName, $lastName, $phoneFormatted);

        if (!$recipientResult || empty($recipientResult->data[0])) {
            return null;
        }

        if (isset($recipientResult->success) && !$recipientResult->success && !empty($recipientResult->errors)) {
            return null;
        }

        $recipient = $recipientResult->data[0];
        $contactPerson = isset($recipient->ContactPerson->data[0]) ? $recipient->ContactPerson->data[0] : null;

        if (!$contactPerson && !empty($recipient->Ref)) {
            $contactsResult = $this->apiHelper->getContactPersonByCounterpartyRef($recipient->Ref, false);
            if ($contactsResult && !empty($contactsResult->data[0])) {
                $contactPerson = $contactsResult->data[0];
            }
        }

        if (!$contactPerson) {
            return null;
        }

        return [
            'recipient' => $recipient,
            'contact_person' => $contactPerson,
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
        $totalVolume,
        $invoicePaymentData = null
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
            $totalVolume,
            $invoicePaymentData ?? null
        );
    }

    /**
     * Чи замовлення використовує спосіб оплати модуля InvoicePayment (оплата для юр. осіб).
     */
    private function orderUsesInvoicePayment(int $orderId): bool
    {
        try {
            $order = $this->entityFactory->get(OrdersEntity::class)->findOne(['id' => $orderId]);
            if (!$order || empty($order->payment_method_id)) {
                return false;
            }
            $payment = $this->entityFactory->get(PaymentsEntity::class)->get($order->payment_method_id);
            return $payment && isset($payment->module) && $payment->module === 'Sviat/InvoicePayment';
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Дані юридичної особи по замовленню (модуль InvoicePayment): ЄДРПОУ та повна назва компанії.
     * Повертає null, якщо модуль не встановлений, вимкнений, таблиця відсутня або по замовленню немає даних.
     */
    private function getInvoicePaymentDataForOrder(int $orderId): ?object
    {
        $invoicePaymentEntityClass = 'Okay\Modules\Sviat\InvoicePayment\Entities\InvoicePaymentDataEntity';
        if (!class_exists($invoicePaymentEntityClass)) {
            return null;
        }
        try {
            $entity = $this->entityFactory->get($invoicePaymentEntityClass);
            $data = $entity->findOne(['order_id' => $orderId]);
            if ($data && !empty(trim((string)$data->edrpou)) && !empty(trim((string)$data->company_name))) {
                return $data;
            }
        } catch (\Throwable $e) {
            // Таблиця відсутня (модуль не встановлений) або інша помилка БД
        }
        return null;
    }

    /**
     * Формує API запит для створення експрес-накладної
     * Підтримує доставку на відділення/поштомат та адресну доставку
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
        $totalVolume,
        $invoicePaymentData = null
    ) {
        // Визначаємо тип доставки
        $isAddressDelivery = !empty($formData['delivery_type_address']);
        $isWarehouseDelivery = !$isAddressDelivery && ($serviceType === 'WarehouseWarehouse' || $serviceType === 'DoorsWarehouse');

        // Для адресної доставки отримуємо тип населеного пункту
        $settlementType = '';
        if ($isAddressDelivery && $deliveryData && !empty($deliveryData->city_id)) {
            $settlementType = $this->getSettlementTypeByCityRef(
                $deliveryData->city_id,
                $deliveryData->city_name ?? null
            );
        }

        // Отримувач — юридична особа (компанія), якщо користувач вибрав Organization і є дані з InvoicePayment
        $isLegalEntity = ($formData['recipient_type'] ?? '') === 'Organization'
            && $invoicePaymentData
            && !empty(trim((string)$invoicePaymentData->edrpou))
            && !empty(trim((string)$invoicePaymentData->company_name));

        // Базові параметри запиту (спільні для всіх типів)
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
            "DateTime" => $date,
            "RecipientsPhone" => $phoneFormatted,
            "InfoRegClientBarcodes" => (string)$order->id,
            "Cost" => $cost,
        ];

        // Параметри отримувача залежно від типу доставки
        if ($isAddressDelivery) {
            // Адресна доставка (рядком) - використовуємо InternetDocumentGeneral
            $recipientFullName = trim(($order->last_name ?? '') . ' ' . ($order->name ?? ''));

            $methodProperties["RecipientAddressNote"] = !empty($formData['recipient_address_note'])
                ? mb_substr(trim($formData['recipient_address_note']), 0, 50)
                : '';
            $methodProperties["RecipientCityName"] = $deliveryData->city_name ?? '';
            $methodProperties["RecipientArea"] = $deliveryData->area_name ?? '';
            $methodProperties["RecipientAreaRegions"] = $deliveryData->region_name ?? '';
            $methodProperties["RecipientAddressName"] = $deliveryData->street ?? '';
            $methodProperties["RecipientHouse"] = $deliveryData->house ?? '';
            $methodProperties["RecipientFlat"] = $deliveryData->apartment ?? '';

            if ($isLegalEntity) {
                // Юридична особа (компанія): дані з модуля InvoicePayment
                $methodProperties["RecipientType"] = "Organization";
                $methodProperties["EDRPOU"] = mb_substr(trim((string)$invoicePaymentData->edrpou), 0, 20);
                $methodProperties["RecipientName"] = trim((string)$invoicePaymentData->company_name);
                $methodProperties["RecipientContactName"] = $recipientFullName ?: $methodProperties["RecipientName"];
            } else {
                $methodProperties["RecipientName"] = $recipientFullName;
                $methodProperties["RecipientType"] = "PrivatePerson";
                $methodProperties["RecipientContactName"] = $recipientFullName;
            }

            // Доставка до дверей (NumberOfFloorsLifting)
            if ($formData['door_delivery'] == 1 && !empty($formData['lifting_floor'])) {
                $methodProperties["NumberOfFloorsLifting"] = (string)$formData['lifting_floor'];
            }

            if (!empty($settlementType)) {
                $methodProperties["SettlementType"] = $settlementType;
            }
        } elseif ($isWarehouseDelivery) {
            // Доставка на склад/поштомат (відділення-відділення або відділення-поштомат)
            $methodProperties["CityRecipient"] = $deliveryData->city_id ?? '';
            $methodProperties["RecipientAddress"] = $deliveryData->warehouse_id ?? '';
            if ($isLegalEntity) {
                // Організація: не передаємо ContactRecipient/Recipient (API НП приймає отримувача за EDRPOU/рядком)
                $recipientFullName = trim(($order->last_name ?? '') . ' ' . ($order->name ?? ''));
                $methodProperties["RecipientType"] = "Organization";
                $methodProperties["EDRPOU"] = mb_substr(trim((string)$invoicePaymentData->edrpou), 0, 20);
                $methodProperties["RecipientName"] = trim((string)$invoicePaymentData->company_name);
                $methodProperties["RecipientContactName"] = $recipientFullName ?: $methodProperties["RecipientName"];
            } else {
                if (!empty($recipientData['contact_person'])) {
                    $methodProperties["ContactRecipient"] = $recipientData['contact_person']->Ref;
                }
                if (!empty($recipientData['recipient'])) {
                    $methodProperties["Recipient"] = $recipientData['recipient']->Ref;
                }
            }
        }

        // Використовуємо InternetDocumentGeneral для всіх типів доставки
        $apiRequest = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "InternetDocumentGeneral",
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
     * Отримує тип населеного пункту (SettlementType) за REF міста
     * Використовує SettlementTypeDescription з getSettlements або SettlementTypeCode з searchSettlements
     * 
     * @param string $cityRef REF міста
     * @param string|null $cityName Назва міста (для fallback через searchSettlements)
     * @return string Тип населеного пункту (місто, село тощо) або порожній рядок
     */
    private function getSettlementTypeByCityRef($cityRef, $cityName = null)
    {
        if (empty($cityRef)) {
            return '';
        }

        try {
            // Спочатку пробуємо отримати через getSettlements (модель AddressGeneral)
            $request = [
                "apiKey" => $this->settings->get('newpost_key'),
                "modelName" => "AddressGeneral",
                "calledMethod" => "getSettlements",
                "methodProperties" => [
                    "Ref" => $cityRef,
                    "Limit" => "1",
                ]
            ];

            $response = $this->apiHelper->sendApiRequest($request);

            if (!empty($response->success) && !empty($response->data) && is_array($response->data) && !empty($response->data[0])) {
                $settlement = $response->data[0];
                // SettlementTypeDescription - тип населеного пункту українською (місто, село тощо)
                if (!empty($settlement->SettlementTypeDescription)) {
                    return $settlement->SettlementTypeDescription;
                }
            }

            // Якщо не вдалося отримати через getSettlements, пробуємо через searchSettlements
            if (!empty($cityName)) {
                $searchRequest = [
                    "apiKey" => $this->settings->get('newpost_key'),
                    "modelName" => "AddressGeneral",
                    "calledMethod" => "searchSettlements",
                    "methodProperties" => [
                        "CityName" => $cityName,
                        "Limit" => "1",
                        "Page" => "1",
                    ]
                ];

                $searchResponse = $this->apiHelper->sendApiRequest($searchRequest);

                if (!empty($searchResponse->success) && !empty($searchResponse->data) && is_array($searchResponse->data)) {
                    foreach ($searchResponse->data as $dataItem) {
                        if (!empty($dataItem->Addresses) && is_array($dataItem->Addresses)) {
                            foreach ($dataItem->Addresses as $address) {
                                // Перевіряємо чи це наш місто за REF
                                if (!empty($address->Ref) && $address->Ref === $cityRef) {
                                    // SettlementTypeCode - абревіатура типу (м., с. тощо)
                                    if (!empty($address->SettlementTypeCode)) {
                                        return $address->SettlementTypeCode;
                                    }
                                    break 2;
                                }
                            }
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('NovaPoshtaDocumentService::getSettlementTypeByCityRef error: ' . $e->getMessage());
        }

        return '';
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

        // Для відділення та адресної доставки
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
        // Для адресної доставки використовуємо RecipientAddress, якщо WarehouseRecipientAddress порожнє
        $trackingDocument->RecipientAddressFormatted = $this->truncateAddress(
            !empty($trackingDocument->WarehouseRecipientAddress)
                ? $trackingDocument->WarehouseRecipientAddress
                : ($trackingDocument->RecipientAddress ?? '')
        );

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
