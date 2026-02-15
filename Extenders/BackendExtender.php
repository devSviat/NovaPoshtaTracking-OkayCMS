<?php


namespace Okay\Modules\Sviat\NovaPoshtaTracking\Extenders;

use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Languages;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Core\Response;
use Okay\Core\Settings;
use Okay\Entities\CategoriesEntity;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Helpers\DiscountsHelper;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPWarehousesEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaApiHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaStatusHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\TrackingDocumentFormatter;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;

class BackendExtender implements ExtensionInterface
{
    use TrackingDocumentFormatter;

    private $settings;
    private $request;
    private $entityFactory;
    private $design;
    private $novaPoshtaApiHelper;
    private $discountsHelper;
    private $response;
    private $backendOrdersHelper;
    private $languages;
    private $statusHelper;
    private $documentService;
    private $backendTranslations;

    public function __construct(
        Settings $settings,
        EntityFactory   $entityFactory,
        Request $request,
        Design $design,
        NovaPoshtaApiHelper $novaPoshtaApiHelper,
        DiscountsHelper $discountsHelper,
        Response $response,
        BackendOrdersHelper $backendOrdersHelper,
        Languages $languages,
        NovaPoshtaStatusHelper $statusHelper,
        NovaPoshtaDocumentService $documentService,
        BackendTranslations $backendTranslations
    ) {
        $this->settings = $settings;
        $this->design = $design;
        $this->entityFactory = $entityFactory;
        $this->request = $request;
        $this->novaPoshtaApiHelper = $novaPoshtaApiHelper;
        $this->discountsHelper = $discountsHelper;
        $this->response = $response;
        $this->backendOrdersHelper = $backendOrdersHelper;
        $this->languages = $languages;
        $this->statusHelper = $statusHelper;
        $this->documentService = $documentService;
        $this->backendTranslations = $backendTranslations;
    }

    /** Статус накладної та дані для блоку замовлення в адмінці */
    public function findOrderPurchases($purchases)
    {
        $categoriesEntity = null;
        if (!empty($purchases) && !empty($purchases[0]->product->main_category_id)) {
            $categoriesEntity = $this->entityFactory->get(CategoriesEntity::class);
            $categoriesEntity = $categoriesEntity->findOne(['id' => $purchases[0]->product->main_category_id]);
        }

        $orderId = $this->request->get('id');

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $dataOrdersEntity = $ordersEntity->findOne(['id' => $orderId]);

        $costDeliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
        $deliveryData = $costDeliveryDataEntity->findOne(['order_id' => $orderId]);

        $trackingEntity = $this->entityFactory->get(\Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity::class);
        $trackingData = $trackingEntity->findOne(['order_id' => $orderId]);

        if ($trackingData && !empty($trackingData->int_doc_number)) {
            $phoneFormatted = $this->formatPhone($dataOrdersEntity->phone ?? '');
            $result = $this->documentService->getTrackingDocument(
                $orderId,
                $trackingData->int_doc_number,
                $phoneFormatted ?: $dataOrdersEntity->phone ?? ''
            );

            if ($result) {
                $this->enrichTrackingDocumentForDisplay($result, $deliveryData, $dataOrdersEntity, $orderId);
                $this->design->assign('tracking_data', $trackingData);
                $this->design->assign('order', $dataOrdersEntity);

                if (!empty($dataOrdersEntity->delivery_id)) {
                    $deliveriesEntity = $this->entityFactory->get(DeliveriesEntity::class);
                    $delivery = $deliveriesEntity->get($dataOrdersEntity->delivery_id);
                    if ($delivery) {
                        $delivery->settings = unserialize($delivery->settings);
                        $this->design->assign('delivery', $delivery);
                    }
                }

                $this->design->assign('tracking_document', $result, true);
            }
        }

        if ($categoriesEntity && $categoriesEntity->name) {
            $this->design->assign('main_category_name', $categoriesEntity->name);
        }

        if ($deliveryData) {
            $this->autoDetectPickupLocker($deliveryData);
        }

        $controlPayment = $deliveryData ? $deliveryData->control_payment : null;
        $pickupLocker = $deliveryData ? ($deliveryData->pickup_locker ?? null) : null;

        $this->design->assign('control_payment', $controlPayment);
        $this->design->assign('pickup_locker', $pickupLocker);

        $this->design->assign('dataNPCostDeliveryDataEntity', $deliveryData);

        if ($deliveryData) {
            $novaposhtaDeliveryData = clone $deliveryData;
            if ($trackingData) {
                $novaposhtaDeliveryData->ref_id = $trackingData->ref_id;
                $novaposhtaDeliveryData->int_doc_number = $trackingData->int_doc_number;
            } else {
                $novaposhtaDeliveryData->ref_id = null;
                $novaposhtaDeliveryData->int_doc_number = null;
            }
        } else {
            $novaposhtaDeliveryData = new \stdClass();
            $novaposhtaDeliveryData->ref_id = null;
            $novaposhtaDeliveryData->int_doc_number = null;
        }
        $this->design->assign('novaposhta_delivery_data', $novaposhtaDeliveryData);

        if ($dataOrdersEntity && !empty($dataOrdersEntity->delivery_id)) {
            $deliveriesEntity = $this->entityFactory->get(DeliveriesEntity::class);
            $delivery = $deliveriesEntity->get($dataOrdersEntity->delivery_id);
            if ($delivery) {
                $delivery->settings = unserialize($delivery->settings);
                $this->design->assign('delivery', $delivery);
            }
        }

        return $purchases;
    }

    /** Збереження даних накладної при збереженні замовлення */
    public function postOrder($order)
    {
        $costDeliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);

        $costDeliveryDataEntityId = $costDeliveryDataEntity->findOne(['order_id' => $order->id])->id;

        $payerType = $this->request->post('payer_type_radiobutton');
        $cargoType = $this->request->post('cargo_type_radiobutton');
        $backPayerType = $this->request->post('back_payer_type_radiobutton');
        $paymentMethod = $this->request->post('payment_method_radiobutton');
        $additionalInformationValue = $this->request->post('additional-information');
        $recipientAddressNote = $this->request->post('recipient-address-note');
        $doorDelivery = (int)($this->request->post('door_delivery') ?: 0);
        $liftingFloor = trim((string)($this->request->post('lifting_floor') ?: ''));

        $costDeliveryDataEntity->update(
            intval($costDeliveryDataEntityId),
            [
                'payer_type' => $payerType,
                'cargo_type' => $cargoType,
                'payment_method' => $paymentMethod,
                'back_payer_type' => $backPayerType,
                'additional_information' => $additionalInformationValue,
                'recipient_address_note' => $recipientAddressNote ? mb_substr(trim($recipientAddressNote), 0, 50) : '',
                'door_delivery' => $doorDelivery,
                'lifting_floor' => $liftingFloor,
            ]
        );

        $controlPayment = $this->request->post('control_payment') ? $this->request->post('control_payment', 'int') : 0;
        if (!empty($order->paid) && $order->paid == 1) {
            $controlPayment = 0; // для сплаченого замовлення контроль оплати вимкнений
        }

        $deliveryType = $this->request->post('delivery_type_radiobutton');
        $pickupLocker = ($deliveryType === 'locker') ? 1 : 0;

        $volumetricParams = [
            'volume' => $this->request->post('volumetric_volume'),
            'length' => $this->request->post('volumetric_length'),
            'width' => $this->request->post('volumetric_width'),
            'height' => $this->request->post('volumetric_height'),
            'weight' => $this->request->post('volumetric_weight'),
        ];

        $normalizedParams = $this->normalizeVolumetricParams($volumetricParams);

        $warehouseVolume = $this->request->post('warehouse_volume');
        $warehouseWeight = $this->request->post('warehouse_weight');

        $updateData = [
            'control_payment' => $controlPayment,
            'pickup_locker' => $pickupLocker,
        ];

        foreach ($normalizedParams as $key => $value) {
            if ($value !== null && $value !== '') {
                $updateData['volumetric_' . $key] = $value;
            }
        }

        if ($warehouseVolume !== null && $warehouseVolume !== '') {
            $updateData['warehouse_volume'] = str_replace(',', '.', trim($warehouseVolume));
        }
        if ($warehouseWeight !== null && $warehouseWeight !== '') {
            $updateData['warehouse_weight'] = str_replace(',', '.', trim($warehouseWeight));
        }

        $costDeliveryDataEntity->update(intval($costDeliveryDataEntityId), $updateData);

        return $order;
    }

    /** Додаткова інформація про замовлення та дані для форми накладної */
    public function findOrder($order)
    {
        if (!$order) {
            return $order;
        }

        $purchases = $this->backendOrdersHelper->findOrderPurchases($order);
        $additionalInformationValue = $this->buildAdditionalInformation($purchases);

        if ($order) {
            $order->additional_information = $additionalInformationValue;
        }


        if (!empty($order->id)) {
            /** @var NPCostDeliveryDataEntity $deliveryDataEntity */
            $deliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
            $deliveryData = $deliveryDataEntity->getByOrderId($order->id);

            if ($deliveryData && is_object($deliveryData)) {
                $this->autoDetectPickupLocker($deliveryData);

                $redelivery = $this->settings->get('novapost_payment_control');

                if ($redelivery == '1') {
                    $deliveryData->redelivery = 0;
                }

                $trackingEntity = $this->entityFactory->get(\Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity::class);
                $trackingData = $trackingEntity->findOne(['order_id' => $order->id]);

                $novaposhtaDeliveryData = clone $deliveryData;
                if ($trackingData) {
                    $novaposhtaDeliveryData->ref_id = $trackingData->ref_id;
                    $novaposhtaDeliveryData->int_doc_number = $trackingData->int_doc_number;
                } else {
                    $novaposhtaDeliveryData->ref_id = null;
                    $novaposhtaDeliveryData->int_doc_number = null;
                }

                $this->design->assign('novaposhta_delivery_data', $novaposhtaDeliveryData);

                if (!empty($order->delivery_id)) {
                    $deliveriesEntity = $this->entityFactory->get(DeliveriesEntity::class);
                    $delivery = $deliveriesEntity->get($order->delivery_id);
                    if ($delivery) {
                        $delivery->settings = unserialize($delivery->settings);
                        $this->design->assign('delivery', $delivery);
                    }
                }

                $this->design->assign('dataNPCostDeliveryDataEntity', $deliveryData);

                $pickupLocker = isset($deliveryData->pickup_locker) ? $deliveryData->pickup_locker : null;
                $this->design->assign('pickup_locker', $pickupLocker);
            }
        }

        return $order;
    }


    /** Оновлення додаткової інформації та cost після зміни покупок/суми замовлення */
    public function executeCustomPost($order)
    {
        if (!empty($order->id)) {
            $purchases = $this->backendOrdersHelper->findOrderPurchases($order);
            $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
            $actualOrder = $ordersEntity->findOne(['id' => $order->id]);

            $additionalInformationValue = $this->buildAdditionalInformation($purchases);

            $costDeliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
            $deliveryData = $costDeliveryDataEntity->findOne(['order_id' => $order->id]);

            if ($deliveryData && is_object($deliveryData)) {
                $updateData = [];

                if (!empty($additionalInformationValue)) {
                    $updateData['additional_information'] = $additionalInformationValue;
                }

                if ($actualOrder && isset($actualOrder->total_price)) {
                    $updateData['cost'] = $actualOrder->total_price;
                } elseif (isset($order->total_price)) {
                    $updateData['cost'] = $order->total_price;
                }

                if (!empty($updateData)) {
                    $costDeliveryDataEntity->update($deliveryData->id, $updateData);
                }
            }
        }

        return $order;
    }

    /** Синхронізація cost у NPCostDeliveryData при зміні суми замовлення */
    public function updateTotalPrice($orderId)
    {
        if (!empty($orderId)) {
            $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
            $order = $ordersEntity->findOne(['id' => $orderId]);

            if ($order && isset($order->total_price)) {
                $costDeliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
                $deliveryData = $costDeliveryDataEntity->findOne(['order_id' => $orderId]);

                if ($deliveryData && is_object($deliveryData)) {
                    $costDeliveryDataEntity->update($deliveryData->id, [
                        'cost' => $order->total_price
                    ]);
                }
            }
        }

        return $orderId;
    }

    private function buildAdditionalInformation($purchases)
    {
        if (empty($purchases)) {
            return '';
        }

        $itemCounts = [];
        foreach ($purchases as $purchase) {
            $item = !empty($purchase->sku)
                ? $purchase->sku
                : (!empty($purchase->variant->sku) ? $purchase->variant->sku : '');

            if (empty($item) && !empty($purchase->product_name)) {
                $item = $purchase->product_name;
            }

            if (!empty($item)) {
                $amount = !empty($purchase->amount) ? intval($purchase->amount) : 1;
                if (isset($itemCounts[$item])) {
                    $itemCounts[$item] += $amount;
                } else {
                    $itemCounts[$item] = $amount;
                }
            }
        }

        if (empty($itemCounts)) {
            return '';
        }

        $itemList = [];
        foreach ($itemCounts as $item => $count) {
            if ($count > 1) {
                $itemList[] = $item . ' (' . $count . 'шт)';
            } else {
                $itemList[] = $item;
            }
        }

        return implode(', ', $itemList);
    }




    private function enrichTrackingDocumentForDisplay($result, $deliveryData, $dataOrdersEntity, $orderId)
    {
        if (!empty($result->Number)) {
            $result->formatNumber = $this->formatDocumentNumber($result->Number);
        }

        $trackingEntity = $this->entityFactory->get(\Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity::class);
        $trackingData = $trackingEntity->findOne(['order_id' => $orderId]);

        if (!empty($result->Ref)) {
            $result->refId = $result->Ref;
        } elseif ($trackingData && !empty($trackingData->ref_id)) {
            $result->refId = $trackingData->ref_id;
        }

        if ($trackingData) {
            $result->fromTrackingResponse = true;
            $result->trackingUpdateAt = $trackingData->tracking_update_at;
            $result->actualDeliveryAt = $trackingData->actual_delivery_at;
            $result->statusCode = $trackingData->status_code;
            $result->trackingUpdatedAt = $trackingData->updated_at;
        }

        $this->formatTrackingDates($result);
        $this->formatAmounts($result);

        $this->translateApiValues($result);
        $result->SenderCounterpartyTypeFinal = $this->getSenderCounterpartyType($result);
        $result->ScheduledDeliveryDateFinal = $this->getScheduledDeliveryDate($result);
        $this->enrichSenderWarehouseInfo($result);

        $result->SenderAddressFormatted = $this->truncateAddress($result->WarehouseSenderAddress ?? $result->SenderAddress ?? '');
        $result->RecipientAddressFormatted = $this->truncateAddress(
            !empty($result->WarehouseRecipientAddress) 
                ? $result->WarehouseRecipientAddress 
                : ($result->RecipientAddress ?? '')
        );
    }

    private function enrichSenderWarehouseInfo($result)
    {
        if (!empty($result->WarehouseSender) || !empty($result->WarehouseSenderNumber)) {
            return;
        }

        $senderWarehouseRef = $this->settings->get('novapost_sender_warehouse');
        if (empty($senderWarehouseRef)) {
            return;
        }

        $warehouseResult = $this->novaPoshtaApiHelper->getWarehouseByRef($senderWarehouseRef);
        if (!$warehouseResult || !isset($warehouseResult->data) || count($warehouseResult->data) === 0) {
            return;
        }

        $warehouse = $warehouseResult->data[0];
        if (!empty($warehouse->Number)) {
            $result->WarehouseSenderNumber = $warehouse->Number;
        }
        if (!empty($warehouse->Description)) {
            $result->WarehouseSender = $warehouse->Description;
        }
        if (!empty($warehouse->ShortAddress)) {
            $result->WarehouseSenderAddress = $warehouse->ShortAddress;
        } elseif (!empty($warehouse->Address)) {
            $result->WarehouseSenderAddress = $warehouse->Address;
        }
    }

    /** Додає фільтр за статусом Nova Poshta */
    public function buildFilter($filter)
    {
        $npStatus = $this->request->get('np_status');
        if (!empty($npStatus)) {
            $filter['np_status_code'] = (string)$npStatus;
        }

        return $filter;
    }

    /** Підвантажує tracking-дані до списку замовлень */
    public function findOrders($orders)
    {
        if (empty($orders)) {
            return $orders;
        }

        $orderIds = array_keys($orders);

        $trackingEntity = $this->entityFactory->get(\Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity::class);
        $trackingDataList = $trackingEntity->find(['order_id' => $orderIds]);

        $trackingDataMap = [];
        foreach ($trackingDataList as $trackingData) {
            $trackingDataMap[$trackingData->order_id] = $trackingData;
        }

        foreach ($orders as $order) {
            if (isset($trackingDataMap[$order->id])) {
                $trackingData = $trackingDataMap[$order->id];
                $order->novaposhta_tracking = $trackingData;

                if (!empty($trackingData->tracking_response)) {
                    $decodedResponse = json_decode($trackingData->tracking_response, true);
                    if ($decodedResponse && is_array($decodedResponse)) {
                        $order->novaposhta_tracking->tracking_response_decoded = (object)$decodedResponse;
                    }
                }

                if (!empty($trackingData->int_doc_number)) {
                    $order->novaposhta_tracking->formatNumber = $this->formatDocumentNumber($trackingData->int_doc_number);
                }

                if (!empty($trackingData->status_code)) {
                    $statusFormatted = $this->statusHelper->formatStatusForDisplay($trackingData->status_code);
                    if ($statusFormatted) {
                        $order->novaposhta_tracking->status_formatted = (object)$statusFormatted;
                    }
                }
            } else {
                $order->novaposhta_tracking = null;
            }
        }

        return $orders;
    }

    /** Статуси для фільтра + обробка масового створення накладних */
    public function fetch()
    {
        if (!empty($_SESSION['novaposhta_mass_errors'])) {
            $this->design->assign('novaposhta_mass_errors', $_SESSION['novaposhta_mass_errors']);
            unset($_SESSION['novaposhta_mass_errors']);
        }
        if (!empty($_SESSION['novaposhta_mass_success'])) {
            $this->design->assign('novaposhta_mass_success', $_SESSION['novaposhta_mass_success']);
            unset($_SESSION['novaposhta_mass_success']);
        }

        if ($this->request->method('post')) {
            $ids = $this->request->post('check');
            $action = $this->request->post('action');
            
            if (is_array($ids) && $action === 'create_novaposhta_invoice') {
                $this->massCreateNovaposhtaInvoices(null, $ids);
                Response::redirectTo($this->request->url());
            }
        }

        $npStatuses = [
            '1'   => 'Створено',
            '5'   => 'Прямує до міста',
            '6'   => 'У місті отримання',
            '7'   => 'Прибуло до відділення',
            '8'   => 'У поштоматі',
            '9'   => 'Отримано',
            '103' => 'Відмова від отримання',
            '102' => 'Відмова (створено повернення)',
            '104' => 'Змінено адресу',
            '2'   => 'Видалено',
            '3'   => 'Не знайдено',
        ];

        $npStatusFilters = [];
        foreach ($npStatuses as $code => $name) {
            $status = new \stdClass();
            $status->id = $code;
            $status->name = $name;
            $npStatusFilters[] = $status;
        }

        $this->design->assign('all_np_status', true);
        $this->design->assign('all_np_status_filters', $npStatusFilters);

        $npStatus = $this->request->get('np_status');
        if (!empty($npStatus)) {
            $this->design->assign('np_status_id', $npStatus);
        }
    }

    /** Масове створення накладних (викликається з OrdersAdmin). Результат у сесії для показу після редиректу. */
    public function massCreateNovaposhtaInvoices($output, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $results = $this->documentService->massGenerateDocuments($ids);

        $total = count($ids);
        $success = $results['success'] ?? 0;
        $errors = $results['errors'] ?? 0;

        if ($errors > 0) {
            $message = sprintf(
                $this->backendTranslations->getTranslation('sviat__novaposhta_tracking__mass_done_with_errors'),
                $total,
                $success,
                $errors
            );
            $errorDetails = [];
            foreach ($results['details'] ?? [] as $orderId => $detail) {
                if (!empty($detail['error'])) {
                    $errorDetails[$orderId] = $detail['error'];
                }
            }
            $_SESSION['novaposhta_mass_errors'] = $errorDetails;
            $_SESSION['message_error'] = $message;
        } else {
            $message = $this->backendTranslations->getTranslation('sviat__novaposhta_tracking__success_done') . ' ' . $total;
            $_SESSION['novaposhta_mass_success'] = $message;
        }
    }

    /** Визначає поштомат за типом відділення (warehouse_id) і встановлює pickup_locker */
    private function autoDetectPickupLocker($deliveryData)
    {
        if (!$deliveryData || empty($deliveryData->warehouse_id)) {
            return;
        }
        $postomatTypeRef = 'f9316480-5f2d-425d-bc2c-ac7cd29decf0';

        if (isset($deliveryData->pickup_locker) && $deliveryData->pickup_locker !== null) {
            return;
        }

        $warehousesEntity = $this->entityFactory->get(NPWarehousesEntity::class);
        $warehouse = $warehousesEntity->findOne(['ref' => $deliveryData->warehouse_id]);

        if ($warehouse && !empty($warehouse->type) && $warehouse->type === $postomatTypeRef) {
            $deliveryData->pickup_locker = 1;
        }
    }
}
