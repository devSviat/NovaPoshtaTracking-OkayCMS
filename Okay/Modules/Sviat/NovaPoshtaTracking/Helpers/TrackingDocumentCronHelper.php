<?php


namespace Okay\Modules\Sviat\NovaPoshtaTracking\Helpers;

use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Services\NovaPoshtaDocumentService;

class TrackingDocumentCronHelper
{
    use TrackingDocumentFormatter;

    private $entityFactory;
    private $settings;
    private $novaPoshtaApiHelper;
    private $documentService;
    private const MAX_DOCUMENTS_PER_REQUEST = 100; // Максимум накладних в одному запиті до API

    /**
     * Масив фінальних статусів, які не потребують подальшого оновлення
     */
    private const FINAL_STATUSES = [
        '2',    // Видалено
        '9',    // Відправлення отримано
        '11',   // Відправлення отримано (переказ видано)
        '102',  // Відмова від отримання (створено повернення)
        '103',  // Відмова від отримання
        '106',  // Створено ЄН повернення
    ];

    public function __construct(
        EntityFactory $entityFactory,
        Settings $settings,
        NovaPoshtaApiHelper $novaPoshtaApiHelper,
        NovaPoshtaDocumentService $documentService
    ) {
        $this->entityFactory = $entityFactory;
        $this->settings = $settings;
        $this->novaPoshtaApiHelper = $novaPoshtaApiHelper;
        $this->documentService = $documentService;
    }

    /**
     * Оновлює всі tracking документи з БД
     * 
     * @return array Статистика оновлення
     */
    public function updateAllTrackingDocuments()
    {
        $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);

        // Отримуємо всі записи з tracking
        $allTrackingData = $trackingEntity->find();

        if (empty($allTrackingData)) {
            return [
                'total' => 0,
                'updated' => 0,
                'errors' => 0,
                'skipped' => 0,
            ];
        }

        // Групуємо tracking дані по order_id для отримання телефонів
        // Фільтруємо тільки ті, що мають int_doc_number та не мають фінальних статусів
        $trackingByOrderId = [];
        $skippedFinalStatuses = 0;
        foreach ($allTrackingData as $tracking) {
            // Пропускаємо накладні без номера
            if (empty($tracking->int_doc_number)) {
                continue;
            }

            // Пропускаємо накладні з фінальними статусами
            if (!empty($tracking->status_code) && in_array($tracking->status_code, self::FINAL_STATUSES, true)) {
                $skippedFinalStatuses++;
                continue;
            }

            $trackingByOrderId[$tracking->order_id] = $tracking;
        }

        // Отримуємо замовлення для отримання телефонів
        $orderIds = array_keys($trackingByOrderId);
        $orders = $ordersEntity->find(['id' => $orderIds]);

        // Створюємо масив документів для API запиту
        $documentsForApi = [];
        foreach ($orders as $order) {
            if (!isset($trackingByOrderId[$order->id])) {
                continue;
            }

            $tracking = $trackingByOrderId[$order->id];
            $phoneFormatted = $this->formatPhone($order->phone ?? '');

            if (!$phoneFormatted) {
                continue; // Пропускаємо якщо телефон невалідний
            }

            $documentsForApi[] = [
                'order_id' => $order->id,
                'tracking_id' => $tracking->id,
                'document' => [
                    'DocumentNumber' => $tracking->int_doc_number,
                    'Phone' => $phoneFormatted,
                ],
            ];
        }

        // Розбиваємо на батчі по 100 документів (максимум для API)
        $batches = array_chunk($documentsForApi, self::MAX_DOCUMENTS_PER_REQUEST);

        $updated = 0;
        $errors = 0;
        $skipped = 0;

        foreach ($batches as $batch) {
            $result = $this->updateBatch($batch);
            $updated += $result['updated'];
            $errors += $result['errors'];
            $skipped += $result['skipped'];
        }

        return [
            'total' => count($allTrackingData),
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
            'skipped_final_statuses' => $skippedFinalStatuses,
        ];
    }

    /**
     * Оновлює батч документів
     * 
     * @param array $batch Масив документів для оновлення
     * @return array Статистика
     */
    private function updateBatch(array $batch)
    {
        // Формуємо запит до API
        $documents = array_column($batch, 'document');

        $trackingRequest = [
            "apiKey" => $this->settings->get('newpost_key'),
            "modelName" => "TrackingDocument",
            "calledMethod" => "getStatusDocuments",
            "methodProperties" => [
                "Documents" => $documents,
            ],
        ];

        $trackingResult = $this->novaPoshtaApiHelper->sendApiRequest($trackingRequest);

        if (
            !$trackingResult || !isset($trackingResult->success) || !$trackingResult->success ||
            !isset($trackingResult->data) || !is_array($trackingResult->data)
        ) {
            return [
                'updated' => 0,
                'errors' => count($batch),
                'skipped' => 0,
            ];
        }

        // Створюємо мапінг результатів по DocumentNumber
        $resultsByDocumentNumber = [];
        foreach ($trackingResult->data as $trackingDocument) {
            if (!empty($trackingDocument->Number)) {
                $resultsByDocumentNumber[$trackingDocument->Number] = $trackingDocument;
            }
        }

        $updated = 0;
        $errors = 0;
        $skipped = 0;

        // Оновлюємо дані в БД
        foreach ($batch as $item) {
            $documentNumber = $item['document']['DocumentNumber'];
            $orderId = $item['order_id'];
            $trackingId = $item['tracking_id'];

            if (!isset($resultsByDocumentNumber[$documentNumber])) {
                $skipped++;
                continue;
            }

            $trackingDocument = $resultsByDocumentNumber[$documentNumber];

            try {
                $this->documentService->saveTrackingData($orderId, $trackingDocument, $documentNumber, $trackingId);
                $updated++;
            } catch (\Exception $e) {
                $errors++;
            }
        }

        return [
            'updated' => $updated,
            'errors' => $errors,
            'skipped' => $skipped,
        ];
    }


}
