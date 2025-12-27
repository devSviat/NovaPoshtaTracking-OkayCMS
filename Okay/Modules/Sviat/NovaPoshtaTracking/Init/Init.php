<?php


namespace Okay\Modules\Sviat\NovaPoshtaTracking\Init;

use Okay\Admin\Controllers\OrdersAdmin;
use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Admin\Requests\BackendOrdersRequest;
use Okay\Core\Modules\AbstractInit;
use Okay\Core\Modules\EntityField;
use Okay\Core\Scheduler\Schedule;
use Okay\Entities\OrdersEntity;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Extenders\BackendExtender;
use Okay\Modules\Sviat\NovaPoshtaTracking\ExtendsEntities\OrdersEntityExtend;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\TrackingDocumentCronHelper;

class Init extends AbstractInit
{
    public function install()
    {
        $this->setBackendMainController('NovaPoshtaAdmin');

        // Додаємо поля до таблиці NPCostDeliveryDataEntity
        $fields = [
            ['payer_type', 'varchar', 255, null],
            ['payment_method', 'varchar', 255, null],
            ['cargo_type', 'varchar', 255, null],
            ['service_type', 'varchar', 255, null],
            ['cost', 'decimal', '14,2', null],
            ['control_payment', 'tinyint', 1, null],
            ['back_payer_type', 'varchar', 255, null],
            ['pickup_locker', 'tinyint', 1, null],
            ['volumetric_volume', 'varchar', 50, null],
            ['volumetric_length', 'varchar', 50, null],
            ['volumetric_width', 'varchar', 50, null],
            ['volumetric_height', 'varchar', 50, null],
            ['volumetric_weight', 'varchar', 50, null],
            ['warehouse_volume', 'varchar', 50, null],
            ['warehouse_weight', 'varchar', 50, null],
            ['additional_information', 'varchar', 255, null],
        ];

        foreach ($fields as [$name, $type, $size, $default]) {
            $field = new EntityField($name);

            switch ($type) {
                case 'varchar':
                    $field->setTypeVarchar($size, true);
                    break;
                case 'tinyint':
                    $field->setTypeTinyInt($size, true);
                    break;
                case 'decimal':
                    $field->setTypeDecimal($size, true);
                    break;
            }

            $field->setDefault($default);
            $this->migrateEntityField(NPCostDeliveryDataEntity::class, $field);
        }

        // Створюємо таблицю для трекінгу
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

        $this->registerBackendController('NovaPoshtaAdmin');
        $this->addBackendControllerPermission('NovaPoshtaAdmin', 'orders');

        $this->addBackendBlock('order_custom_block', 'tracking_document.tpl');
        $this->addBackendBlock('orders_list_name', 'nova_poshta_status.tpl');

        $this->registerQueueExtension(
            [BackendOrdersHelper::class, 'findOrder'],
            [BackendExtender::class, 'findOrder']
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
        $this->registerQueueExtension(
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
}
