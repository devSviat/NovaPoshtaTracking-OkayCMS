<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\ExtendsEntities;

use Okay\Core\Modules\AbstractModuleEntityFilter;
use Okay\Entities\OrdersEntity as OriginalOrdersEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;

class OrdersEntityExtend extends AbstractModuleEntityFilter
{
    /**
     * Фільтрує замовлення за статусом Nova Poshta
     */
    public function filter__np_status_code($statusCode, $filter)
    {
        $tableAlias = OriginalOrdersEntity::getTableAlias();
        $trackingTable = NovaPoshtaTrackingEntity::getTable();

        $statusCode = trim((string)$statusCode);

        if (empty($statusCode)) {
            return;
        }

        $this->select->join('INNER', "{$trackingTable} AS npt", "{$tableAlias}.id = npt.order_id");
        $this->select->where('npt.status_code = :np_status_code')
            ->bindValue('np_status_code', $statusCode);
        $this->select->groupBy(['id']);
    }

    /**
     * Розширює пошук за keyword, додаючи пошук за номером накладної Nova Poshta
     * Замінює вбудований фільтр, тому включає всі умови пошуку
     */
    public function filter__keyword($keywords, $filter)
    {
        $tableAlias = OriginalOrdersEntity::getTableAlias();
        $trackingTable = NovaPoshtaTrackingEntity::getTable();

        $keywords = explode(' ', trim($keywords));

        // Додаємо LEFT JOIN з таблицею tracking для пошуку за номером накладної
        $this->select->join('LEFT', "{$trackingTable} AS npt_keyword", "{$tableAlias}.id = npt_keyword.order_id");

        foreach ($keywords as $keyNum => $keyword) {
            $this->select->where("(
                {$tableAlias}.id LIKE :keyword_id_{$keyNum}
                OR {$tableAlias}.name LIKE :keyword_name_{$keyNum}
                OR {$tableAlias}.last_name LIKE :keyword_last_name_{$keyNum}
                OR REPLACE({$tableAlias}.phone, '-', '') LIKE :keyword_phone_{$keyNum}
                OR {$tableAlias}.email LIKE :keyword_email_{$keyNum}
                OR {$tableAlias}.id IN (SELECT order_id FROM __purchases WHERE product_name LIKE :keyword_product_name_{$keyNum} OR variant_name LIKE :keyword_product_name_{$keyNum})
                OR npt_keyword.int_doc_number LIKE :keyword_int_doc_number_{$keyNum}
            )");

            $this->select->bindValues([
                "keyword_id_{$keyNum}" => '%' . $keyword . '%',
                "keyword_name_{$keyNum}" => '%' . $keyword . '%',
                "keyword_last_name_{$keyNum}" => '%' . $keyword . '%',
                "keyword_phone_{$keyNum}" => '%' . $keyword . '%',
                "keyword_email_{$keyNum}" => '%' . $keyword . '%',
                "keyword_product_name_{$keyNum}" => '%' . $keyword . '%',
                "keyword_int_doc_number_{$keyNum}" => '%' . $keyword . '%',
            ]);
        }
    }
}
