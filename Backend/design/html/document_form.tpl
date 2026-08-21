<div class="delivery_novaposhta_document">
    <hr>
    <div class="font_16 mb-1">
        {$btr->sviat__novaposhta_tracking__params_shipment|escape}
    </div>
    <div class="row">
        <div class="col-md-12">
            <div id="service_type" class="row">
                <div class="col-md-12">
                    <div class="heading_label">{$btr->sviat__novaposhta_tracking__delivery_type|escape}</div>
                    <div id="delivery_type" class="row">
                        {assign var="is_warehouse_empty" value=empty($dataNPCostDeliveryDataEntity->warehouse_id)}
                        {if !$is_warehouse_empty}
                        <div class="col-md-6">
                            <div class="okay_type_radio_wrap">
                                <input id="delivery_type_warehouse" class="hidden_check"
                                    name="delivery_type_radiobutton" type="radio" value="warehouse"
                                    {if (!isset($dataNPCostDeliveryDataEntity->pickup_locker) OR ($dataNPCostDeliveryDataEntity->pickup_locker != 1)) AND (!isset($pickup_locker) OR ($pickup_locker != 1)) AND (empty($dataNPCostDeliveryDataEntity->city_name) OR empty($dataNPCostDeliveryDataEntity->street))}checked=""
                                    {/if} />
                                <label for="delivery_type_warehouse" class="okay_type_radio">
                                    <span>{$btr->sviat__novaposhta_tracking__warehouse|escape}</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="okay_type_radio_wrap">
                                <input id="delivery_type_locker" class="hidden_check" name="delivery_type_radiobutton"
                                    type="radio" value="locker"
                                    {if (isset($dataNPCostDeliveryDataEntity->pickup_locker) AND ($dataNPCostDeliveryDataEntity->pickup_locker == 1)) OR (isset($pickup_locker) AND ($pickup_locker == 1))}checked=""
                                    {/if} />
                                <label for="delivery_type_locker" class="okay_type_radio">
                                    <span>{$btr->sviat__novaposhta_tracking__locker|escape}
                                        <i class="fn_tooltips"
                                            title="{$btr->sviat__novaposhta_tracking__locker_tooltip|escape}">
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </span>
                                </label>
                            </div>
                        </div>
                        {else}
                        <div class="col-md-12">
                            <div class="okay_type_radio_wrap">
                                <input id="delivery_type_address" class="hidden_check" name="delivery_type_radiobutton"
                                    type="radio" value="address"
                                    {if (!isset($dataNPCostDeliveryDataEntity->pickup_locker) OR ($dataNPCostDeliveryDataEntity->pickup_locker != 1)) AND (!isset($pickup_locker) OR ($pickup_locker != 1)) AND (!empty($dataNPCostDeliveryDataEntity->city_name) AND !empty($dataNPCostDeliveryDataEntity->street))}checked=""
                                    {/if} />
                                <label for="delivery_type_address" class="okay_type_radio">
                                    <span>{$btr->sviat__novaposhta_tracking__address_delivery|escape}</span>
                                </label>
                            </div>
                        </div>
                        {/if}
                    </div>
                </div>
            </div>

            {* Тип отримувача: юр. особа (компанія) / фіз. особа — тільки якщо є дані з InvoicePayment *}
            {if $show_recipient_type_choice}
            <div class="row mb-h" id="recipient_type_block">
                <div class="col-md-12">
                    <div class="heading_label">{$btr->sviat__novaposhta_tracking__recipient_type|escape}</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="okay_type_radio_wrap">
                                <input id="recipient_type_organization" class="hidden_check" name="recipient_type_radiobutton" type="radio" value="Organization"
                                    {if $default_recipient_type == 'Organization'}checked=""{/if} />
                                <label for="recipient_type_organization" class="okay_type_radio">
                                    <span>{$btr->sviat__novaposhta_tracking__recipient_type_organization|escape}</span>
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="okay_type_radio_wrap">
                                <input id="recipient_type_private" class="hidden_check" name="recipient_type_radiobutton" type="radio" value="PrivatePerson"
                                    {if $default_recipient_type == 'PrivatePerson'}checked=""{/if} />
                                <label for="recipient_type_private" class="okay_type_radio">
                                    <span>{$btr->sviat__novaposhta_tracking__recipient_type_private|escape}</span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            {/if}

            {* Доставка до дверей (тільки для адресної доставки) *}
            <div id="door_delivery_block" class="row mb-h"
                style="display: {if !empty($dataNPCostDeliveryDataEntity->city_name) AND !empty($dataNPCostDeliveryDataEntity->street)}block{else}none{/if};">
                <div class="col-md-12">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="heading_label">
                                {$btr->sviat__novaposhta_tracking__door_delivery|escape}
                                <i class="fn_tooltips" title="{$btr->sviat__novaposhta_tracking__door_delivery_tooltip|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            {assign var="door_delivery_value" value=0}
                            {if isset($dataNPCostDeliveryDataEntity->door_delivery) AND ($dataNPCostDeliveryDataEntity->door_delivery == 1)}
                                {assign var="door_delivery_value" value=1}
                            {/if}
                            <input type="hidden" name="door_delivery" id="door_delivery_hidden" value="{$door_delivery_value}" />
                            <label class="switch switch-default">
                                <input class="switch-input" type="checkbox" id="door_delivery_checkbox" value="1"
                                    {if $door_delivery_value == 1}checked=""{/if} />
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </label>
                        </div>
                        <div class="col-md-6" id="floors_lifting_block" style="display: {if $door_delivery_value == 1}block{else}none{/if};">
                            <div class="form-group">
                                <label class="heading_label">{$btr->sviat__novaposhta_tracking__floor|escape}</label>
                                <input class="form-control" type="number" name="lifting_floor" id="lifting_floor"
                                    value="{if isset($dataNPCostDeliveryDataEntity->lifting_floor) && $dataNPCostDeliveryDataEntity->lifting_floor}{$dataNPCostDeliveryDataEntity->lifting_floor|escape}{/if}"
                                    placeholder="{$btr->sviat__novaposhta_tracking__floor_placeholder|escape}" min="1" max="99" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Габарити для відділення та адресної доставки *}
            <div id="warehouse_params" class="row"
                style="display: {if isset($dataNPCostDeliveryDataEntity->pickup_locker) AND ($dataNPCostDeliveryDataEntity->pickup_locker == 1)}none{else}block{/if};">
                <div class="col-md-12">
                    <div class="heading_label mb-h">{$btr->sviat__novaposhta_tracking__cargo_params|escape}</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-h">
                                <label class="heading_label">{$btr->sviat__novaposhta_tracking__weight_kg|escape}</label>
                                <input class="form-control" type="number" name="warehouse_weight"
                                    value="{if isset($dataNPCostDeliveryDataEntity->warehouse_weight) && $dataNPCostDeliveryDataEntity->warehouse_weight}{$dataNPCostDeliveryDataEntity->warehouse_weight}{else}{$settings->novapost_warehouse_weight|default:'0.5'}{/if}"
                                    placeholder="{$settings->novapost_warehouse_weight|default:'0.5'}" min="0.1"
                                    step="0.1" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-h">
                                <label class="heading_label">{$btr->sviat__novaposhta_tracking__volume_m3|escape}</label>
                                <input class="form-control" type="number" name="warehouse_volume" id="warehouse_volume"
                                    value="{if isset($dataNPCostDeliveryDataEntity->warehouse_volume) && $dataNPCostDeliveryDataEntity->warehouse_volume}{$dataNPCostDeliveryDataEntity->warehouse_volume}{else}{$settings->novapost_warehouse_volume|default:'0.0004'}{/if}"
                                    placeholder="{$settings->novapost_warehouse_volume|default:'0.0004'}" min="0.0004"
                                    max="0.12" step="0.0001" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Габарити для поштомату *}
            <div id="volumetric_params" class="row"
                style="display: {if isset($dataNPCostDeliveryDataEntity->pickup_locker) AND ($dataNPCostDeliveryDataEntity->pickup_locker == 1)}block{else}none{/if};">
                <div class="col-md-12">
                    <div class="heading_label mb-h">{$btr->sviat__novaposhta_tracking__cargo_params_locker|escape}</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-h">
                                <label class="heading_label">Вага (кг):</label>
                                <input class="form-control" type="number" name="volumetric_weight"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_weight) && $dataNPCostDeliveryDataEntity->volumetric_weight}{$dataNPCostDeliveryDataEntity->volumetric_weight}{else}{$settings->novapost_volumetric_weight}{/if}"
                                    placeholder="{$settings->novapost_weight|default:'0.1'}" min="0" max="20"
                                    step="0.1" />
                                <small class="text-muted">{$btr->sviat__novaposhta_tracking__max_20_kg|escape}</small>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-h">
                                <label class="heading_label">Об'єм (м³):</label>
                                <input class="form-control" type="number" name="volumetric_volume"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_volume) && $dataNPCostDeliveryDataEntity->volumetric_volume}{$dataNPCostDeliveryDataEntity->volumetric_volume}{else}{$settings->novapost_volumetric_volume}{/if}"
                                    placeholder="{$settings->novapost_volumetric_volume|default:'0.001'}" min="0.0004"
                                    step="0.0001" />
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
<label class="heading_label">{$btr->sviat__novaposhta_tracking__length|escape}</label>
                                    <input class="form-control" type="number" name="volumetric_length"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_length) && $dataNPCostDeliveryDataEntity->volumetric_length}{$dataNPCostDeliveryDataEntity->volumetric_length}{else}{$settings->novapost_volumetric_length}{/if}"
                                    placeholder="{$settings->novapost_length|default:'15'}" min="0" max="60" />
                                <small class="text-muted">{$btr->sviat__novaposhta_tracking__max_60_cm|escape}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
<label class="heading_label">{$btr->sviat__novaposhta_tracking__width|escape}</label>
                                    <input class="form-control" type="number" name="volumetric_width"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_width) && $dataNPCostDeliveryDataEntity->volumetric_width}{$dataNPCostDeliveryDataEntity->volumetric_width}{else}{$settings->novapost_volumetric_width}{/if}"
                                    placeholder="{$settings->novapost_width|default:'10'}" min="0" max="40" />
                                <small class="text-muted">{$btr->sviat__novaposhta_tracking__max_40_cm|escape}</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
<label class="heading_label">{$btr->sviat__novaposhta_tracking__height|escape}</label>
                                    <input class="form-control" type="number" name="volumetric_height"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_height) && $dataNPCostDeliveryDataEntity->volumetric_height}{$dataNPCostDeliveryDataEntity->volumetric_height}{else}{$settings->novapost_volumetric_height}{/if}"
                                    placeholder="{$settings->novapost_height|default:'20'}" min="0" max="30" />
                                <small class="text-muted">{$btr->sviat__novaposhta_tracking__max_30_cm|escape}</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-h">
        <div class="col-md-12">
            <div id="service_type" class="row">
                <div class="col-md-6">
                    <div class="heading_label">{$btr->sviat__novaposhta_tracking__announced_value|escape}
                        <i class="fn_tooltips" title="{$btr->sviat__novaposhta_tracking__announced_value_tooltip|escape}">
                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                        </i>
                    </div>
                    <input name="cost" class="form-control mb-h fn_cost" type="number"
                        value="{if isset($dataNPCostDeliveryDataEntity->cost) || !empty($dataNPCostDeliveryDataEntity->cost)}{$dataNPCostDeliveryDataEntity->cost}{else}{$order->total_price|escape}{/if}"
                        min="0" step="0.01" />
                </div>
                <div class="col-md-6">
                    <div class="heading_label">{$btr->sviat__novaposhta_tracking__control_payment|escape}
                        <i class="fn_tooltips"
                            title="{$btr->sviat__novaposhta_tracking__control_payment_tooltip|escape}">
                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                        </i>
                    </div>
                    {assign var="is_order_paid" value=($order->paid == 1 || $order->paid)}
                    {if $is_order_paid}
                        {assign var="control_payment_value" value=0}
                    {elseif isset($dataNPCostDeliveryDataEntity->control_payment) AND ($dataNPCostDeliveryDataEntity->control_payment == 1)}
                        {assign var="control_payment_value" value=1}
                    {elseif !isset($dataNPCostDeliveryDataEntity->control_payment) AND $settings->novapost_payment_control == '1'}
                        {assign var="control_payment_value" value=1}
                    {else}
                        {assign var="control_payment_value" value=0}
                    {/if}
                    <input type="hidden" name="control_payment" id="control_payment_hidden" value="{$control_payment_value}" />
                    <label class="switch switch-default{if $is_order_paid} switch-disabled{/if}">
                        <input class="switch-input" type="checkbox" id="control_payment_checkbox" value="1"
                            {if $control_payment_value == 1}checked=""{/if}
                            {if $is_order_paid}disabled="disabled"{/if} />
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="form-group">
        <div class="heading_label">
            <span>
                {$btr->sviat__novaposhta_tracking__additional_info|escape}
                <i class="fn_tooltips" title="{$btr->sviat__novaposhta_tracking__additional_info_tooltip|escape}">
                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                </i>
            </span>
        </div>
        <input class="form-control" type="text" name="additional-information"
            value="{if $dataNPCostDeliveryDataEntity->additional_information}{$dataNPCostDeliveryDataEntity->additional_information}{else}{$order->additional_information}{/if}"
            placeholder="{$btr->sviat__novaposhta_tracking__additional_info_placeholder|escape}" />
    </div>

    {* Коментар до адреси (тільки для адресної доставки) *}
    <div id="recipient_address_note_block" class="form-group" style="display: {if !empty($dataNPCostDeliveryDataEntity->city_name) AND !empty($dataNPCostDeliveryDataEntity->street)}block{else}none{/if};">
        <div class="heading_label">
            <span>
                {$btr->sviat__novaposhta_tracking__address_comment|escape}
                <i class="fn_tooltips" title="{$btr->sviat__novaposhta_tracking__address_comment_tooltip|escape}">
                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                </i>
            </span>
        </div>
        <input class="form-control" type="text" name="recipient-address-note" id="recipient_address_note"
            value="{if isset($dataNPCostDeliveryDataEntity->recipient_address_note) && $dataNPCostDeliveryDataEntity->recipient_address_note}{$dataNPCostDeliveryDataEntity->recipient_address_note|escape}{/if}"
            placeholder="{$btr->sviat__novaposhta_tracking__address_comment_placeholder|escape}" maxlength="50" />
    </div>

    <div class="row mb-2">
        <div class="col-md-12">
            <div class="heading_label">{$btr->sviat__novaposhta_tracking__cargo_type|escape}</div>
            <div id="cargo_type" class="row">
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="cargo_type_radio_1" class="hidden_check" name="cargo_type_radiobutton" type="radio"
                            value="Cargo"
                            {if isset($dataNPCostDeliveryDataEntity->cargo_type) AND ($dataNPCostDeliveryDataEntity->cargo_type == 'Cargo')}checked=""
                            {elseif $settings->novapost_cargo_type == 'Cargo'}checked="" 
                            {/if} />
                        <label for="cargo_type_radio_1" class="okay_type_radio">
                            <span>{$btr->sviat__novaposhta_tracking__cargo|escape}</span>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="cargo_type_radio_2" class="hidden_check" name="cargo_type_radiobutton" type="radio"
                            value="Documents"
                            {if isset($dataNPCostDeliveryDataEntity->cargo_type) AND ($dataNPCostDeliveryDataEntity->cargo_type == 'Documents')}checked=""
                            {elseif ($settings->novapost_cargo_type == 'Documents')}checked="" 
                            {/if} />
                        <label for="cargo_type_radio_2" class="okay_type_radio">
                            <span>{$btr->sviat__novaposhta_tracking__documents|escape}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="heading_label">{$btr->sviat__novaposhta_tracking__payer|escape}</div>
            <div id="payer_type" class="row">
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="payer_type_radio_1" class="hidden_check" name="payer_type_radiobutton" type="radio"
                            value="Sender"
                            {if isset($dataNPCostDeliveryDataEntity->payer_type) AND ($dataNPCostDeliveryDataEntity->payer_type == 'Sender')}checked=""
                            {elseif $settings->novapost_payer_type == 'Sender'}checked="" 
                            {/if} />
                        <label for="payer_type_radio_1" class="okay_type_radio">
                            <span>{$btr->sviat__novaposhta_tracking__sender|escape}</span>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="payer_type_radio_2" class="hidden_check" name="payer_type_radiobutton" type="radio"
                            value="Recipient"
                            {if isset($dataNPCostDeliveryDataEntity->payer_type) AND ($dataNPCostDeliveryDataEntity->payer_type == 'Recipient')}checked=""
                            {elseif ($settings->novapost_payer_type == 'Recipient')}checked="" 
                            {/if} />
                        <label for="payer_type_radio_2" class="okay_type_radio">
                            <span>{$btr->sviat__novaposhta_tracking__recipient|escape}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="heading_label">{$btr->sviat__novaposhta_tracking__back_payer|escape}</div>
            <div id="back_payer_type" class="row">
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="back_payer_type_radio_1" class="hidden_check" name="back_payer_type_radiobutton"
                            type="radio" value="Sender"
                            {if isset($dataNPCostDeliveryDataEntity->back_payer_type) AND ($dataNPCostDeliveryDataEntity->back_payer_type == 'Sender')}checked=""
                            {elseif ($settings->novapost_back_payer_type == 'Sender')}checked="" 
                            {/if} />
                        <label for="back_payer_type_radio_1" class="okay_type_radio">
                            <span>{$btr->sviat__novaposhta_tracking__sender|escape}</span>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="back_payer_type_radio_2" class="hidden_check" name="back_payer_type_radiobutton"
                            type="radio" value="Recipient"
                            {if isset($dataNPCostDeliveryDataEntity->back_payer_type) AND ($dataNPCostDeliveryDataEntity->back_payer_type == 'Recipient')}checked=""
                            {elseif ($settings->novapost_back_payer_type == 'Recipient')}checked="" 
                            {/if} />
                        <label for="back_payer_type_radio_2" class="okay_type_radio">
                            <span>{$btr->sviat__novaposhta_tracking__recipient|escape}</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="heading_label">{$btr->sviat__novaposhta_tracking__payment_form|escape}</div>
            <div id="payment_method" class="row">
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="payment_method_radio_1" class="hidden_check" name="payment_method_radiobutton"
                            type="radio" value="Cash" {if !empty($dataNPCostDeliveryDataEntity->payment_method)}
                                {if $dataNPCostDeliveryDataEntity->payment_method == 'Cash'}checked="" {/if}
                            {elseif $settings->novapost_payment_method == 'Cash'}checked="" 
                            {/if} />
                        <label for="payment_method_radio_1" class="okay_type_radio">
                            <span>{$btr->sviat__novaposhta_tracking__payment_cash|escape}
                                <i class="fn_tooltips"
                                    title="{$btr->sviat__novaposhta_tracking__payment_cash_tooltip|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </span>
                        </label>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="payment_method_radio_2" class="hidden_check" name="payment_method_radiobutton"
                            type="radio" value="NonCash" {if !empty($dataNPCostDeliveryDataEntity->payment_method)}
                                {if $dataNPCostDeliveryDataEntity->payment_method == 'NonCash'}checked="" {/if}
                            {elseif $settings->novapost_payment_method == 'NonCash'}checked="" 
                            {/if} />
                        <label for="payment_method_radio_2" class="okay_type_radio">
                            <span>{$btr->sviat__novaposhta_tracking__payment_noncash|escape}
                                <i class="fn_tooltips"
                                    title="{$btr->sviat__novaposhta_tracking__payment_noncash_tooltip|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {if $delivery->app_id == 'novaposhta_cost'}
        <input class="fn_manual_address" name="city_id" type="hidden"
            value="{if $np_order->city_id}{$np_order->city_id|escape}{else}{$user->np_city_ref}{/if}" />
        <input name="delivery_warehouse_id" type="hidden"
            value="{if $np_order->np_warehouse_id}{$np_order->np_warehouse_id|escape}{else}{$user->np_warehouse_ref}{/if}" />

        <div class="mb-1">
            <div class="heading_label">{$btr->sviat__novaposhta_tracking__city|escape}</div>
            <div class="edit_order_detail">
                <select name="city" style="width: 265px;padding: 2px;" data-placeholder="{$btr->sviat__novaposhta_tracking__select_city|escape}" tabindex="1"
                    class="city_novaposhta">
                    {if $ttn_novapost_cities}
                        {$ttn_novapost_cities}
                    {else}
                        {$user_novapost_cities}
                    {/if}
                </select>
                <select name="warehouse" style="width: 265px;padding: 2px;margin: 10px 0;" tabindex="1"
                    class="warehouses_novaposhta">
                    {if $ttn_novapost_warehouses}
                        {$ttn_novapost_warehouses}
                    {else}
                        {$user_novapost_warehouses}
                    {/if}
                </select>
            </div>
        </div>
    {/if}

    {if !empty($order->id)}
        <script>
            window.npDocumentFormT = {
                order_paid_control: "{$btr->sviat__novaposhta_tracking__order_paid_control_disabled|escape:'javascript'}",
                attention: "{$btr->sviat__novaposhta_tracking__attention|escape:'javascript'}",
                volumetric_limit: "{$btr->sviat__novaposhta_tracking__volumetric_weight_limit|escape:'javascript'}",
                invoice_created: "{$btr->sviat__novaposhta_tracking__invoice_created_success|escape:'javascript'}",
                success: "{$btr->sviat__novaposhta_tracking__success|escape:'javascript'}",
                unknown_error: "{$btr->sviat__novaposhta_tracking__unknown_error|escape:'javascript'}",
                error: "{$btr->sviat__novaposhta_tracking__error|escape:'javascript'}",
                create_error: "{$btr->sviat__novaposhta_tracking__create_invoice_error|escape:'javascript'}",
                attach_done: "{$btr->sviat__novaposhta_tracking__attach_success|escape:'javascript'}",
                attach_exists: "{$btr->sviat__novaposhta_tracking__attach_already|escape:'javascript'}",
                attach_not_found: "{$btr->sviat__novaposhta_tracking__attach_not_found|escape:'javascript'}",
                attach_elsewhere: "{$btr->sviat__novaposhta_tracking__attach_elsewhere|escape:'javascript'}",
                attach_invalid: "{$btr->sviat__novaposhta_tracking__attach_invalid|escape:'javascript'}"
            };
        </script>
        <div class="fn_delivery_novaposhta" style="display: block;">
            <div class="fn_error hidden boxed boxed_warning"></div>
            {* Виписану вручну накладну треба не створювати, а привʼязати за
               номером. Поле під кнопкою, бо потрібне рідше за створення. *}
            {assign var="np_has_document" value=!empty($novaposhta_delivery_data->int_doc_number)}
            <div class="np-actions">
                <button id="fn_generate_document" class="btn btn-info{if $np_has_document} disabled{/if}" {if $np_has_document}disabled{/if}>
                    <span class="btn-text">{$btr->sviat__novaposhta_tracking__btn_create_invoice|escape}</span>
                    <span class="btn-loader hidden">
                        <span class="spinner"></span>
                        <span class="loader-text">{$btr->sviat__novaposhta_tracking__creating|escape}</span>
                    </span>
                </button>
                <button id="fn_attach_toggle" type="button" class="btn btn_border_blue{if $np_has_document} disabled{/if}" {if $np_has_document}disabled{/if}>
                    {$btr->sviat__novaposhta_tracking__btn_attach_invoice|escape}
                </button>
            </div>

            <div id="fn_attach_form" class="np-attach mt-1 hidden">
                <div class="np-attach__label text-muted font_12 mb-h">
                    {$btr->sviat__novaposhta_tracking__attach_hint|escape}
                </div>
                <div class="np-attach__row">
                    <input type="text"
                           id="fn_attach_document_number"
                           class="form-control np-attach__input"
                           inputmode="numeric"
                           autocomplete="off"
                           maxlength="20"
                           placeholder="{$btr->sviat__novaposhta_tracking__attach_placeholder|escape}">
                    <button id="fn_attach_document" type="button" class="btn btn-info np-attach__button">
                        <span class="btn-text">{$btr->sviat__novaposhta_tracking__attach_confirm|escape}</span>
                        <span class="btn-loader hidden">
                            <span class="spinner"></span>
                            <span class="loader-text">{$btr->sviat__novaposhta_tracking__attaching|escape}</span>
                        </span>
                    </button>
                </div>
            </div>
        </div>

        {literal}
            <script>
                // Перевірка чи замовлення сплачене
                const isOrderPaid = {/literal}{if $order->paid == 1 || $order->paid}true{else}false{/if}{literal};

                // Синхронізація checkbox з hidden input
                $('#control_payment_checkbox').on('change', function() {
                    $('#control_payment_hidden').val($(this).is(':checked') ? '1' : '0');
                });

                // Обробка спроби натиснути на disabled checkbox
                $('#control_payment_checkbox').on('click', function(e) {
                    if (isOrderPaid && $(this).prop('disabled')) {
                        e.preventDefault();
                        e.stopPropagation();
                        toastr.warning(window.npDocumentFormT.order_paid_control, window.npDocumentFormT.attention);
                        return false;
                    }
                });

                $(document).ready(function() {
                    $('#control_payment_hidden').val($('#control_payment_checkbox').is(':checked') ? '1' : '0');
                    
                    // Якщо замовлення сплачене, переконаємось що checkbox вимкнений та додаємо клас
                    if (isOrderPaid) {
                        $('#control_payment_checkbox').prop('checked', false).prop('disabled', true);
                        $('#control_payment_hidden').val('0');
                        $('#control_payment_checkbox').closest('label').addClass('switch-disabled');
                    }
                });

                // Перемикання полів габаритів
                $('input[name="delivery_type_radiobutton"]').on('change', function() {
                    const deliveryType = $(this).val();
                    const isLocker = deliveryType === 'locker';
                    const isAddress = deliveryType === 'address';
                    $('#volumetric_params').toggle(isLocker);
                    $('#warehouse_params').toggle(!isLocker);
                    $('#recipient_address_note_block').toggle(isAddress);
                    $('#door_delivery_block').toggle(isAddress);
                });

                // Синхронізація checkbox доставки до дверей з hidden input
                $('#door_delivery_checkbox').on('change', function() {
                    const isChecked = $(this).is(':checked');
                    $('#door_delivery_hidden').val(isChecked ? '1' : '0');
                    $('#floors_lifting_block').toggle(isChecked);
                });

                // Валідація об'єму (макс. 30 кг об'ємної ваги)
                $('#warehouse_volume').on('input', function() {
                    const volume = parseFloat($(this).val());
                    if (!isNaN(volume)) {
                        const volumetricWeight = volume * 250;
                        if (volumetricWeight > 30) {
                            $(this).val('0.12');
                            alert(window.npDocumentFormT.volumetric_limit.replace('%s', volumetricWeight.toFixed(2)));
                        }
                    }
                });

                // Отримання значення радіокнопки
                function getRadioValue(name) {
                    const radio = document.querySelector('input[name="' + name + '"]:checked');
                    return radio ? radio.value : null;
                }

                // Приховування/показ анімації завантаження
                function toggleLoading($button, $buttonText, $buttonLoader, show) {
                    if (show) {
                        $buttonText.addClass('hidden');
                        $buttonLoader.removeClass('hidden');
                        $button.prop('disabled', true).addClass('btn-loading');
                    } else {
                        $buttonLoader.addClass('hidden');
                        $buttonText.removeClass('hidden');
                        $button.prop('disabled', false).removeClass('btn-loading');
                    }
                }

                // Створення накладної
                $('#fn_generate_document').on('click', function(e) {
                    e.preventDefault();
                    
                    const $button = $(this);
                    if ($button.hasClass('disabled') || $button.prop('disabled')) {
                        return;
                    }
                    
                    const $buttonText = $button.find('.btn-text');
                    const $buttonLoader = $button.find('.btn-loader');
                    toggleLoading($button, $buttonText, $buttonLoader, true);
                    
                    // Якщо замовлення сплачене, контроль оплати має бути вимкнений
                    let control_payment = '0';
                    if (!isOrderPaid) {
                        control_payment = $('#control_payment_checkbox').is(':checked') ? '1' : '0';
                    }
                    $('#control_payment_hidden').val(control_payment);

                    const deliveryType = getRadioValue('delivery_type_radiobutton');
                    const pickup_locker = deliveryType === 'locker' ? 1 : 0;
                    const delivery_type_address = deliveryType === 'address' ? 1 : 0;
                    let warehouse_volume = $('input[name="warehouse_volume"]').val();
                    let warehouse_weight = $('input[name="warehouse_weight"]').val();

                    if (!warehouse_volume || warehouse_volume === '0') warehouse_volume = '';
                    if (!warehouse_weight || warehouse_weight === '0') warehouse_weight = '';

                    $.ajax({
                        type: 'POST',
                        url: "{/literal}{url_generator route="Sviat_NovaPoshtaTracking_generateDocument" absolute=1}{literal}",
                        data: {
                            order_id: '{/literal}{$order->id}{literal}',
                            payer_type_value: getRadioValue('payer_type_radiobutton'),
                            cargo_type_value: getRadioValue('cargo_type_radiobutton'),
                            back_payer_type_value: getRadioValue('back_payer_type_radiobutton'),
                            payment_method_value: getRadioValue('payment_method_radiobutton'),
                            service_type_value: getRadioValue('service_type_radiobutton'),
                            additional_information_value: $('input[name="additional-information"]').val(),
                            recipient_address_note_value: $('input[name="recipient-address-note"]').val(),
                            door_delivery: $('#door_delivery_hidden').val(),
                            lifting_floor: $('input[name="lifting_floor"]').val(),
                            control_payment: control_payment,
                            control_payment_value: $('input[name="cost"]').val(),
                            pickup_locker: pickup_locker,
                            delivery_type_address: delivery_type_address,
                            volumetric_volume: $('input[name="volumetric_volume"]').val(),
                            volumetric_length: $('input[name="volumetric_length"]').val(),
                            volumetric_width: $('input[name="volumetric_width"]').val(),
                            volumetric_height: $('input[name="volumetric_height"]').val(),
                            volumetric_weight: $('input[name="volumetric_weight"]').val(),
                            warehouse_volume: warehouse_volume,
                            warehouse_weight: warehouse_weight,
                            recipient_type_value: ($('input[name="recipient_type_radiobutton"]:checked').length ? $('input[name="recipient_type_radiobutton"]:checked').val() : 'PrivatePerson')
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.ref_id) {
                                if (data.hasOwnProperty('tracking_document')) {
                                    showTrackingDocument(data.tracking_document);
                                }
                                $('.fn_document_input')
                                    .text(data.int_doc_number)
                                    .attr('href', 'https://new.novaposhta.ua/edit/' + data.ref_id)
                                    .closest('.document_wrap')
                                    .removeClass('hidden');
                                $('.fn_error').addClass('hidden');
                                
                                lockDocumentButtons();
                                toggleLoading($button, $buttonText, $buttonLoader, false);
                                
                                toastr.success(window.npDocumentFormT.invoice_created.replace('%s', data.int_doc_number || ''), window.npDocumentFormT.success);
                                setTimeout(() => location.reload(), 1500);
                            } else if (data.error) {
                                toggleLoading($button, $buttonText, $buttonLoader, false);
                                var message = attachErrorText(data.error);
                                $('.fn_error').text(message).removeClass('hidden');
                                toastr.error(message, window.npDocumentFormT.error);
                            } else {
                                toggleLoading($button, $buttonText, $buttonLoader, false);
                                toastr.error(window.npDocumentFormT.unknown_error, window.npDocumentFormT.error);
                            }
                        },
                        error: function(xhr, status, errorThrown) {
                            toggleLoading($button, $buttonText, $buttonLoader, false);
                            toastr.error(window.npDocumentFormT.create_error.replace('%s', errorThrown), window.npDocumentFormT.error);
                        }
                    });
                });

                $('#fn_attach_toggle').on('click', function () {
                    var $form = $('#fn_attach_form');
                    $form.toggleClass('hidden');
                    if (!$form.hasClass('hidden')) {
                        $('#fn_attach_document_number').trigger('focus');
                    }
                });

                $('#fn_attach_document').on('click', function () {
                    var $button = $(this);
                    var $buttonText = $button.find('.btn-text');
                    var $buttonLoader = $button.find('.btn-loader');
                    var $input = $('#fn_attach_document_number');
                    var number = String($input.val() || '').replace(/\D+/g, '');

                    // Той самий рубіж, що й на сервері: рівно чотирнадцять цифр.
                    if (number.length !== 14) {
                        toastr.error(window.npDocumentFormT.attach_invalid, window.npDocumentFormT.error);
                        $input.trigger('focus');
                        return;
                    }

                    toggleLoading($button, $buttonText, $buttonLoader, true);

                    $.ajax({
                        type: 'POST',
                        url: "{/literal}{url_generator route="Sviat_NovaPoshtaTracking_attachDocument" absolute=1}{literal}",
                        dataType: 'json',
                        data: {
                            order_id: '{/literal}{$order->id}{literal}',
                            int_doc_number: number,
                            session_id: '{/literal}{$smarty.session.id}{literal}'
                        },
                        success: function (data) {
                            toggleLoading($button, $buttonText, $buttonLoader, false);

                            if (data && data.success) {
                                $input.val('');
                                toastr.success(
                                    window.npDocumentFormT.attach_done.replace('%s', data.int_doc_number || ''),
                                    window.npDocumentFormT.success
                                );
                                lockDocumentButtons();
                                showTrackingDocument(data.tracking_document);
                                return;
                            }

                            toastr.error(attachErrorText(data && data.error), window.npDocumentFormT.error);
                        },
                        error: function (xhr, status, errorThrown) {
                            toggleLoading($button, $buttonText, $buttonLoader, false);
                            var payload = xhr && xhr.responseJSON ? xhr.responseJSON : null;
                            toastr.error(
                                payload && payload.error
                                    ? attachErrorText(payload.error)
                                    : window.npDocumentFormT.create_error.replace('%s', errorThrown),
                                window.npDocumentFormT.error
                            );
                        }
                    });
                });

                /**
                 * Показує щойно отриманий блок накладної.
                 *
                 * Поки ТТН немає, ядро не виводить ні блок, ні його обгортку,
                 * тож html() у порожню вибірку мовчки нічого не робить — саме
                 * тому накладна не зʼявлялась до перезавантаження сторінки.
                 */
                function showTrackingDocument(markup) {
                    var $block = $('.tracking_document');

                    if ($block.length && markup) {
                        $block.html(markup);
                        return;
                    }

                    // Вставляти нікуди: сторінка відрендерить блок сама.
                    window.location.reload();
                }

                // Накладна в замовленні одна, тож після неї обидві дії закриті.
                function lockDocumentButtons() {
                    $('#fn_generate_document, #fn_attach_toggle')
                        .addClass('disabled')
                        .prop('disabled', true);
                    $('#fn_attach_form').addClass('hidden');
                }

                // Сервер віддає машинні коди, щоб не залежати від мови адмінки.
                function attachErrorText(code) {
                    var text = String(code || '');

                    if (text.indexOf('already_attached:') === 0) {
                        return window.npDocumentFormT.attach_exists.replace('%s', text.slice('already_attached:'.length));
                    }
                    if (text.indexOf('attached_elsewhere:') === 0) {
                        return window.npDocumentFormT.attach_elsewhere.replace('%s', text.slice('attached_elsewhere:'.length));
                    }
                    if (text === 'not_found_in_np') {
                        return window.npDocumentFormT.attach_not_found;
                    }
                    if (text === 'invalid_number') {
                        return window.npDocumentFormT.attach_invalid;
                    }

                    return text || window.npDocumentFormT.unknown_error;
                }
            </script>
        {/literal}
    {/if}
</div>