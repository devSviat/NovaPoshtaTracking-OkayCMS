<?php


namespace Okay\Modules\Sviat\NovaPoshtaTracking\Init;

use Okay\Admin\Controllers\OrdersAdmin;
use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Admin\Requests\BackendOrdersRequest;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Core\Scheduler\Schedule;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrdersEntity;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Compat\Engine;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshtaTracking\Extenders\FrontExtender;
use Okay\Modules\Sviat\NovaPoshtaTracking\ExtendsEntities\OrdersEntityExtend;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\TrackingDocumentCronHelper;
use Psr\Log\LoggerInterface;

class Init extends AbstractInit
{
    /**
     * Модуль розширює таблицю й сутності OkayCMS/NovaposhtaCost. Без нього
     * перше ж звертання до NPCostDeliveryDataEntity — фатал: в install() на
     * половині доданих колонок, в init() — на кожному запиті до сайту.
     */
    private const REQUIRES = ['OkayCMS/NovaposhtaCost'];

    public function install()
    {
        if ($this->requirementsUnmet(true)) {
            return;
        }

        $this->setBackendMainController('NovaPoshtaAdmin');

        // Поля NPCostDeliveryDataEntity (розширення таблиці з OkayCMS NovaposhtaCost)
        $payerTypeField = (new EntityField('payer_type'))->setTypeVarchar(255, true);
        $paymentMethodField = (new EntityField('payment_method'))->setTypeVarchar(255, true);
        $cargoTypeField = (new EntityField('cargo_type'))->setTypeVarchar(255, true);
        $serviceTypeField = (new EntityField('service_type'))->setTypeVarchar(255, true);
        $costField = (new EntityField('cost'))->setTypeDecimal('14,2', true);
        $controlPaymentField = (new EntityField('control_payment'))->setTypeTinyInt(1, true);
        $backPayerTypeField = (new EntityField('back_payer_type'))->setTypeVarchar(255, true);
        $pickupLockerField = (new EntityField('pickup_locker'))->setTypeTinyInt(1, true);
        $volumetricVolumeField = (new EntityField('volumetric_volume'))->setTypeVarchar(50, true);
        $volumetricLengthField = (new EntityField('volumetric_length'))->setTypeVarchar(50, true);
        $volumetricWidthField = (new EntityField('volumetric_width'))->setTypeVarchar(50, true);
        $volumetricHeightField = (new EntityField('volumetric_height'))->setTypeVarchar(50, true);
        $volumetricWeightField = (new EntityField('volumetric_weight'))->setTypeVarchar(50, true);
        $warehouseVolumeField = (new EntityField('warehouse_volume'))->setTypeVarchar(50, true);
        $warehouseWeightField = (new EntityField('warehouse_weight'))->setTypeVarchar(50, true);
        $additionalInformationField = (new EntityField('additional_information'))->setTypeVarchar(255, true);
        
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $payerTypeField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $paymentMethodField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $cargoTypeField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $serviceTypeField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $costField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $controlPaymentField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $backPayerTypeField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $pickupLockerField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $volumetricVolumeField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $volumetricLengthField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $volumetricWidthField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $volumetricHeightField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $volumetricWeightField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $warehouseVolumeField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $warehouseWeightField);
        $this->migrateEntityField(NPCostDeliveryDataEntity::class, $additionalInformationField);

        // Таблиця трекінгу накладних
        $this->migrateEntityTable(NovaPoshtaTrackingEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('order_id'))->setTypeInt(11)->setIndex(),
            (new EntityField('int_doc_number'))->setTypeVarchar(36, false),
            (new EntityField('ref_id'))->setTypeVarchar(36, false),
            (new EntityField('status_code'))->setTypeVarchar(36, false),
            (new EntityField('tracking_update_at'))->setTypeDatetime(true),
            (new EntityField('actual_delivery_at'))->setTypeDatetime(true),
            (new EntityField('tracking_response'))->setTypeLongText()->setNullable(),
            (new EntityField('created_at'))->setTypeDatetime(false),
            (new EntityField('updated_at'))->setTypeDatetime(false),
        ]);
    }

    public function init()
    {
        // Без логування: init() виконується на кожному запиті, і рядок помилки
        // тут перетворив би лог на суцільний шум. Гучно про це каже install().
        if ($this->requirementsUnmet(false)) {
            return;
        }

        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'payer_type');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'payment_method');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'cargo_type');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'service_type');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'cost');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'control_payment');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'back_payer_type');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'pickup_locker');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'volumetric_volume');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'volumetric_length');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'volumetric_width');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'volumetric_height');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'volumetric_weight');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'warehouse_volume');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'warehouse_weight');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'additional_information');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'recipient_address_note');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'door_delivery');
        $this->registerEntityField(NPCostDeliveryDataEntity::class, 'lifting_floor');

        $this->registerBackendController('NovaPoshtaAdmin');
        $this->addBackendControllerPermission('NovaPoshtaAdmin', 'orders');

        $this->addBackendBlock('order_custom_block', 'tracking_document.tpl');
        $this->addBackendBlock('orders_list_name', 'nova_poshta_status.tpl');
        $this->addBackendBlock('orders_novaposhta_mass_result', 'novaposhta_mass_result.tpl');

        $this->registerQueueExtension(
            [BackendOrdersHelper::class, 'findOrder'],
            [BackendExtender::class, 'findOrder']
        );
        $this->registerQueueExtension(
            [OrdersHelper::class, 'getOrderPurchasesList'],
            [FrontExtender::class, 'getOrderPurchasesList']
        );
        $this->registerQueueExtension(
            [BackendOrdersHelper::class, 'findOrderPurchases'],
            [BackendExtender::class, 'findOrderPurchases']
        );
        $this->registerChainExtension(
            [BackendOrdersRequest::class, 'postOrder'],
            [BackendExtender::class, 'postOrder']
        );
        $this->registerQueueExtension(
            [BackendOrdersHelper::class, 'executeCustomPost'],
            [BackendExtender::class, 'executeCustomPost']
        );
        $this->registerQueueExtension(
            [OrdersEntity::class, 'updateTotalPrice'],
            [BackendExtender::class, 'updateTotalPrice']
        );
        $this->registerQueueExtension(
            [BackendOrdersHelper::class, 'findOrders'],
            [BackendExtender::class, 'findOrders']
        );
        $this->registerChainExtension(
            [BackendOrdersHelper::class, 'buildFilter'],
            [BackendExtender::class, 'buildFilter']
        );
        $this->registerChainExtension(
            [OrdersAdmin::class, 'fetch'],
            [BackendExtender::class, 'fetch']
        );
        $this->registerEntityFilter(
            OrdersEntity::class,
            'np_status_code',
            OrdersEntityExtend::class,
            'filter__np_status_code'
        );
        $this->registerEntityFilter(
            OrdersEntity::class,
            'keyword',
            OrdersEntityExtend::class,
            'filter__keyword'
        );

        $this->registerSchedule(
            (new Schedule([TrackingDocumentCronHelper::class, 'updateAllTrackingDocuments']))
                ->name('Update Nova Poshta Tracking Docs')
                ->time('*/10 * * * *')
                ->overlap(false)
                ->timeout(1800)
        );
    }

    private function requirementsUnmet(bool $log): bool
    {
        $missing = Engine::missing(self::REQUIRES);

        if (empty($missing)) {
            return false;
        }

        if ($log) {
            ServiceLocator::getInstance()
                ->getService(LoggerInterface::class)
                ->error('Sviat/NovaPoshtaTracking: не встановлено ' . implode(', ', $missing)
                    . ' — модуль лишається неактивним');
        }

        return true;
    }

    /**
     * Оновлення до версії 1.0.3: recipient_address_note, door_delivery, lifting_floor
     */
    public function update_1_0_3()
    {
        $this->migrateEntityField(
            NPCostDeliveryDataEntity::class,
            (new EntityField('recipient_address_note'))->setTypeVarchar(50, true)
        );
        $this->migrateEntityField(
            NPCostDeliveryDataEntity::class,
            (new EntityField('door_delivery'))->setTypeTinyInt(1, true)
        );
        $this->migrateEntityField(
            NPCostDeliveryDataEntity::class,
            (new EntityField('lifting_floor'))->setTypeVarchar(10, true)
        );
    }
}
