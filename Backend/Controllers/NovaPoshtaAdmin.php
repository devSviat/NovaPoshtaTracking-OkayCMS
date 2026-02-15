<?php

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Modules\OkayCMS\NovaposhtaCost\Entities\NPCostDeliveryDataEntity;
use Okay\Modules\Sviat\NovaPoshtaTracking\Helpers\NovaPoshtaApiHelper;

class NovaPoshtaAdmin extends IndexAdmin
{
    /** Налаштування модуля (API, відділення, габарити за замовч.) */
    public function fetch()
    {
        if ($this->request->method('POST')) {
            $oldApiKey = $this->settings->get('newpost_key');
            
            $this->settings->set('newpost_key', $this->request->post('newpost_key'));
            $this->settings->set('newpost_city', $this->request->post('newpost_city'));
            $this->settings->set('novapost_sender_warehouse', $this->request->post('sender_warehouse'));
            $this->settings->set('novapost_sender_phone', $this->request->post('sender_phone'));
            $this->settings->set('novapost_time_today_date', $this->request->post('time_today_date'));
            $this->settings->set('novapost_payer_type', $this->request->post('payer_type'));
            $this->settings->set('novapost_cargo_type', $this->request->post('cargo_type'));
            $this->settings->set('novapost_back_payer_type', $this->request->post('back_payer_type'));
            $this->settings->set('novapost_payment_method', $this->request->post('payment_method'));
            $this->settings->set('novapost_payment_control', $this->request->post('payment_control'));
            $this->settings->set('novapost_volumetric_volume', $this->normalizeNumericValue($this->request->post('novapost_volumetric_volume')));
            $this->settings->set('novapost_volumetric_length', $this->normalizeNumericValue($this->request->post('novapost_volumetric_length')));
            $this->settings->set('novapost_volumetric_width', $this->normalizeNumericValue($this->request->post('novapost_volumetric_width')));
            $this->settings->set('novapost_volumetric_height', $this->normalizeNumericValue($this->request->post('novapost_volumetric_height')));
            $this->settings->set('novapost_volumetric_weight', $this->normalizeNumericValue($this->request->post('novapost_volumetric_weight')));
            $this->settings->set('novapost_warehouse_volume', $this->normalizeNumericValue($this->request->post('novapost_warehouse_volume')));
            $this->settings->set('novapost_warehouse_weight', $this->normalizeNumericValue($this->request->post('novapost_warehouse_weight')));

            $newApiKey = $this->settings->get('newpost_key');
            if ($oldApiKey !== $newApiKey) {
                NovaPoshtaApiHelper::clearCacheStatic(); // кеш API при зміні ключа
            }

            $paymentControl = $this->settings->get('novapost_payment_control');
            if ($paymentControl === null) {
                // вимкнено контроль оплати — очищаємо control_payment у всіх замовлень
                $deliveryDataEntity = $this->entityFactory->get(NPCostDeliveryDataEntity::class);
                $deliveryDataList = $deliveryDataEntity->mappedBy('id')->find(['redelivery' => '1']);
                $ids = array_keys($deliveryDataList);
                $deliveryDataEntity->update($ids, ['control_payment' => null]);
            }

            $this->design->assign('message_success', 'saved');
        }
        $this->response->setContent($this->design->fetch('nova_poshta_admin.tpl'));
    }

    private function normalizeNumericValue($value)
    {
        if ($value === null || $value === '') {
            return $value;
        }
        return str_replace(',', '.', $value);
    }
}
