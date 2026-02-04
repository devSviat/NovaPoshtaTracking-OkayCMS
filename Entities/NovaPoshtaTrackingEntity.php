<?php


namespace Okay\Modules\Sviat\NovaPoshtaTracking\Entities;

use Okay\Core\Entity\Entity;

class NovaPoshtaTrackingEntity extends Entity
{
    protected static $fields = [
        'id',
        'order_id',
        'int_doc_number',
        'ref_id',
        'status_code',
        'tracking_update_at',
        'actual_delivery_at',
        'tracking_response',
        'created_at',
        'updated_at',
    ];

    protected static $defaultOrderFields = ['id DESC'];
    protected static $table = 'sviat__novaposhta_tracking';
    protected static $tableAlias = 'npt';
}
