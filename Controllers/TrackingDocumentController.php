<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Core\Managers;
use Okay\Modules\Sviat\NovaPoshtaTracking\Security\AdminIdentity;
use Okay\Entities\ManagersEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaApiHelper;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\TrackingDocumentFormatter;

class TrackingDocumentController extends AbstractController
{
    use TrackingDocumentFormatter;

    /** Те саме право, що й у NovaPoshtaAdmin (див. Init). */
    private const PERMISSION = 'orders';
    /**
     * Генерація експрес-накладної через API Нової Пошти
     */
    public function generateDocument(
        AdminIdentity $adminIdentity,
        NovaPoshtaDocumentService $documentService,
        Managers $managers,
        ManagersEntity $managersEntity
    ) {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        try {
            $orderId = $this->request->get('order_id', 'int') ?: $this->request->post('order_id', 'int');
            if (!$orderId) {
                $this->jsonError('Order ID is required');
                return;
            }

            $result = $documentService->generateDocument($orderId);

            if (!empty($result['error'])) {
                $this->jsonError($result['error']);
                return;
            }

            // Якщо є tracking_document, рендеримо шаблон
            if (!empty($result['tracking_document'])) {
                $result['tracking_document'] = $this->renderTrackingDocument($result, $orderId);
            }

            $this->response->setContentType(RESPONSE_JSON);
            $this->response->sendHeaders();
            $this->response->sendStream(json_encode($result), RESPONSE_JSON);
            exit;
        } catch (\Exception $e) {
            error_log('TrackingDocumentController::generateDocument error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->jsonError('Internal Server Error: ' . $e->getMessage());
        }
    }

    /**
     * Привʼязує до замовлення накладну, виписану вручну на сайті Нової Пошти.
     *
     * Запис лягає тим самим шляхом, що й створений кнопкою, тож статуси,
     * масові дії й колонка ТТН в експорті замовлень бачать її як свою.
     */
    public function attachDocument(
        AdminIdentity $adminIdentity,
        NovaPoshtaDocumentService $documentService,
        Managers $managers,
        ManagersEntity $managersEntity
    ) {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        try {
            $orderId = $this->request->post('order_id', 'int');
            if (!$orderId) {
                $this->jsonError('Order ID is required', false);
                return;
            }

            $intDocNumber = NovaPoshtaDocumentService::normalizeDocumentNumber(
                (string) $this->request->post('int_doc_number', 'string')
            );
            if ($intDocNumber === null) {
                $this->jsonError('invalid_number', false);
                return;
            }

            // Замінювати наявну накладну не даємо: помилка друку тихо розірвала
            // б звʼязок із посилкою, яку вже везуть. Спершу відвʼязати.
            $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
            $existing = $trackingEntity->findOne(['order_id' => $orderId]);
            if (NovaPoshtaDocumentService::hasDocument($existing)) {
                $this->jsonError('already_attached:' . $existing->int_doc_number, false);
                return;
            }

            // Створена з адмінки накладна унікальна за побудовою, набрана вручну —
            // ні: та сама посилка в двох замовленнях розсинхронить їхні статуси.
            $duplicate = $trackingEntity->findOne(['int_doc_number' => $intDocNumber]);
            if (!empty($duplicate->order_id) && (int) $duplicate->order_id !== $orderId) {
                $this->jsonError('attached_elsewhere:' . (int) $duplicate->order_id, false);
                return;
            }

            $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
            $order = $ordersEntity->get($orderId);
            if (!$order) {
                $this->jsonError('Order not found', false);
                return;
            }

            // Нова Пошта віддає накладну і з чужим телефоном, і з порожнім;
            // свій передаємо лише заради повнішої відповіді.
            $phoneFormatted = (string) ($this->formatPhone($order->phone ?? '') ?? '');

            $trackingDocument = $documentService->fetchTrackingFromApi($intDocNumber, $phoneFormatted);

            if (NovaPoshtaDocumentService::isUnknownDocument($trackingDocument)) {
                $this->jsonError('not_found_in_np', false);
                return;
            }

            $documentService->saveTrackingData($orderId, $trackingDocument, $intDocNumber);

            // Сирий документ виглядає зайвим, але з нього рендериться блок,
            // який тут же стає на його місце.
            $result = ['success' => true, 'int_doc_number' => $intDocNumber, 'tracking_document' => $trackingDocument];
            $result['tracking_document'] = $this->renderTrackingDocument($result, $orderId);

            $this->response->setContentType(RESPONSE_JSON);
            $this->response->sendHeaders();
            $this->response->sendStream(json_encode($result), RESPONSE_JSON);
            exit;
        } catch (\Exception $e) {
            error_log('TrackingDocumentController::attachDocument error: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->jsonError('Internal Server Error: ' . $e->getMessage(), false);
        }
    }

    /**
     * Оновлює tracking документ з API
     */
    public function updateTrackingDocument(
        AdminIdentity $adminIdentity,
        NovaPoshtaApiHelper $novaPoshtaApiHelper,
        NovaPoshtaDocumentService $documentService,
        Managers $managers,
        ManagersEntity $managersEntity
    ) {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        try {
            $orderId = $this->request->post('order_id', 'int');
            
            if (!$orderId) {
                return $this->jsonError('Order ID is required', false);
            }
            
            $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
            $trackingData = $trackingEntity->findOne(['order_id' => $orderId]);
            
            if (empty($trackingData->int_doc_number)) {
                return $this->jsonError('Tracking data not found', false);
            }
            
            $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
            $order = $ordersEntity->get($orderId);
            if (!$order) {
                return $this->jsonError('Order not found', false);
            }
            
            $phoneFormatted = $this->formatPhone($order->phone ?? '');
            if (!$phoneFormatted) {
                return $this->jsonError('Invalid phone number', false);
            }
            
            $trackingRequest = [
                "apiKey" => $this->settings->get('newpost_key'),
                "modelName" => "TrackingDocument",
                "calledMethod" => "getStatusDocuments",
                "methodProperties" => [
                    "Documents" => [
                        [
                            "DocumentNumber" => $trackingData->int_doc_number,
                            "Phone" => $phoneFormatted
                        ]
                    ]
                ]
            ];

            $trackingResult = $novaPoshtaApiHelper->sendApiRequest($trackingRequest);

            if (!$trackingResult || !is_object($trackingResult) || empty($trackingResult->success) || empty($trackingResult->data[0])) {
                return $this->jsonError('Failed to fetch tracking data from API', false);
            }

            $documentService->saveTrackingData($orderId, $trackingResult->data[0], $trackingData->int_doc_number);
            $updatedTrackingData = $trackingEntity->findOne(['order_id' => $orderId]);
            
            $this->response->setContentType(RESPONSE_JSON);
            $this->response->sendHeaders();
            $this->response->sendStream(json_encode([
                'success' => true, 
                'message' => 'Tracking data updated',
                'updated_at' => $updatedTrackingData->updated_at ?? null
            ]), RESPONSE_JSON);
            exit;
        } catch (\Exception $e) {
            error_log('Error in updateTrackingDocument: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->jsonError('Internal Server Error: ' . $e->getMessage(), false);
        }
    }

    /**
     * Видалення експрес-накладної через API Нової Пошти та в бд
     * Захист: для статусу 1 видаляємо з БД тільки після успішного видалення через API
     * Для статусу 2 можна видаляти з БД без перевірки (накладна вже видалена в НП)
     */
    public function removeDocument(
        AdminIdentity $adminIdentity,
        NovaPoshtaApiHelper $novaPoshtaApiHelper,
        Managers $managers,
        ManagersEntity $managersEntity
    ) {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        try {
            $orderId = $this->request->get('order_id', 'int');
            $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
            $trackingData = $trackingEntity->findOne(['order_id' => $orderId]);

            if (!$trackingData) {
                return $this->jsonError('Tracking data not found', false);
            }

            $statusCode = $trackingData->status_code ?? '';
            $apiDeleteSuccess = $this->deleteDocumentViaApi($novaPoshtaApiHelper, $trackingData, $statusCode, $orderId);

            if ($apiDeleteSuccess === false) {
                // Помилка API для статусу 1 - не видаляємо з БД
                return;
            }

            // Видаляємо з БД тільки якщо API видалення успішне або статус = 2
            if ($apiDeleteSuccess) {
                $trackingEntity->delete($trackingData->id);
                
                // Очищаємо поля в NPCostDeliveryDataEntity
                $deliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
                $deliveryData = $deliveryDataEntity->findOne(['order_id' => $orderId]);
                if ($deliveryData) {
                    $deliveryDataEntity->update(
                        $deliveryData->id,
                        [
                            'service_type' => null,
                            'payer_type' => null,
                            'cargo_type' => null,
                            'payment_method' => null,
                            'back_payer_type' => null,
                        ]
                    );
                }
                
                $this->response->setContentType(RESPONSE_JSON);
                $this->response->sendHeaders();
                $this->response->sendStream(json_encode([
                    'success' => true,
                    'message' => 'Накладну успішно видалено'
                ]), RESPONSE_JSON);
                exit;
            }
        } catch (\Exception $e) {
            error_log('Error in removeDocument: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            $this->jsonError('Помилка видалення накладної: ' . $e->getMessage(), false);
        }
    }

    /**
     * Видаляє документ через API та повертає результат
     * 
     * @param NovaPoshtaApiHelper $novaPoshtaApiHelper
     * @param object $trackingData
     * @param string $statusCode
     * @param int $orderId
     * @return bool|null true - успішно, false - помилка (для статусу 1), null - пропущено
     */
    private function deleteDocumentViaApi(
        NovaPoshtaApiHelper $novaPoshtaApiHelper,
        $trackingData,
        string $statusCode,
        int $orderId
    ) {
        // Якщо статус = 2, накладна вже видалена в НП, можна видаляти з БД без перевірки API
        if ($statusCode === '2') {
            return true;
        }
        
        // Якщо немає ref_id, просто видаляємо з БД
        if (empty($trackingData->ref_id)) {
            return true;
        }
        
        // Якщо статус = 1, потрібно спочатку видалити через API і перевірити результат
        $deleteRequest = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "InternetDocument",
            "calledMethod" => "delete",
            "methodProperties" => [
                "DocumentRefs" => $trackingData->ref_id,
            ]
        ];
        
        $deleteResult = $novaPoshtaApiHelper->sendApiRequest($deleteRequest);
        
        // Перевіряємо результат API видалення
        if (!empty($deleteResult->success)) {
            return true;
        }
        
        // Якщо статус = 1 і помилка API, не видаляємо з БД і повертаємо помилку
        if ($statusCode === '1') {
            $errorMessage = $novaPoshtaApiHelper->getErrorMessage($deleteResult);
            error_log('Failed to delete document via API for order_id=' . $orderId . ': ' . $errorMessage);
            $this->jsonError($errorMessage, false);
            return false;
        }
        
        // Для інших статусів логуємо помилку, але все одно видаляємо з БД
        error_log('API delete failed for order_id=' . $orderId . ' but deleting from DB anyway (status_code=' . $statusCode . ')');
        return true;
    }

    /**
     * Рендерить tracking документ для відображення
     */
    private function renderTrackingDocument(array $result, int $orderId): string
    {
        try {
            $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
            $trackingData = $trackingEntity->findOne(['order_id' => $orderId]);
            
            $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
            $order = $ordersEntity->findOne(['id' => $orderId]);
            
            // Встановлюємо шаблони для дизайну
            $this->design->setTemplatesDir('backend/design/html/');
            $this->design->setModuleTemplatesDir('Okay/Modules/Sviat/NovaPoshtaTracking/Backend/design/html/');
            $this->design->useModuleDir();
            
            $this->design->assign('tracking_data', $trackingData);
            $this->design->assign('order', $order);
            $this->design->assign('tracking_document', $result['tracking_document']);
            
            return $this->design->fetch('tracking_document.tpl');
        } catch (\Exception $e) {
            // Якщо помилка рендерингу шаблону, просто логуємо і повертаємо порожній рядок
            error_log('Error rendering tracking_document.tpl: ' . $e->getMessage() . ' | Trace: ' . $e->getTraceAsString());
            return '';
        }
    }


    /**
     * Повертає JSON помилку
     */
    private function jsonError(string $message, bool $sendResponse = true): void
    {
        if ($sendResponse) {
            $this->response->setContentType(RESPONSE_JSON);
            $this->response->sendHeaders();
            $this->response->sendStream(json_encode(['error' => $message]), RESPONSE_JSON);
            exit;
        } else {
            $this->response->setContent(json_encode(['error' => $message]), RESPONSE_JSON);
        }
    }

    /**
     * Маршрути цих методів оголошені з to_front, тобто запит іде через вітрину
     * повз авторизацію backend/index.php. Без перевірки будь-хто міг створити
     * або видалити експрес-накладну для довільного замовлення — це реальні
     * гроші й реальні виклики до API Нової Пошти.
     *
     * Звідки береться логін менеджера, вирішує AdminIdentity: рушії
     * зберігають бекендову сесію по-різному.
     */
    private function isAllowed(
        AdminIdentity $adminIdentity,
        Managers $managers,
        ManagersEntity $managersEntity
    ): bool
    {
        $adminLogin = $adminIdentity->login();
        if (empty($adminLogin)) {
            $this->response->setStatusCode(401);
            $this->jsonError('Unauthorized');
            return false;
        }

        $manager = $managersEntity->get($adminLogin);
        if (empty($manager) || !$managers->access(self::PERMISSION, $manager)) {
            $this->response->setStatusCode(403);
            $this->jsonError('Access denied');
            return false;
        }

        return true;
    }
}
