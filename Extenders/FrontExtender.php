<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Extenders;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;

class FrontExtender implements ExtensionInterface
{
    private Design $design;
    private EntityFactory $entityFactory;

    public function __construct(Design $design, EntityFactory $entityFactory)
    {
        $this->design = $design;
        $this->entityFactory = $entityFactory;
    }

    /**
     * Adds TTN data to the customer's order page.
     *
     * @param array|false $purchases
     * @param int|string $orderId
     * @return array|false
     */
    public function getOrderPurchasesList($purchases, $orderId)
    {
        if (empty($orderId) || !is_numeric($orderId)) {
            return $purchases;
        }

        $orderIdInt = (int) $orderId;
        if ($orderIdInt <= 0) {
            return $purchases;
        }

        $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
        $trackingData = $trackingEntity->order('id_desc')->findOne(['order_id' => $orderIdInt]);

        if ($trackingData && !empty($trackingData->int_doc_number)) {
            $this->design->assign('orderTracking', $trackingData);
        }

        return $purchases;
    }
}
