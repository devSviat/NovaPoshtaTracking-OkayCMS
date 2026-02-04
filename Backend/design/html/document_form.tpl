<div class="delivery_novaposhta_document">
    <hr>
    <div class="font_16 mb-1">
        Параметри відправлення
    </div>
    <div class="row">
        <div class="col-md-12">
            <div id="service_type" class="row">
                <div class="col-md-12">
                    <div class="heading_label">Тип доставки:</div>
                    <div id="delivery_type" class="row">
                        <div class="col-md-6">
                            <div class="okay_type_radio_wrap">
                                <input id="delivery_type_warehouse" class="hidden_check"
                                    name="delivery_type_radiobutton" type="radio" value="warehouse"
                                    {if (!isset($dataNPCostDeliveryDataEntity->pickup_locker) OR ($dataNPCostDeliveryDataEntity->pickup_locker != 1)) AND (!isset($pickup_locker) OR ($pickup_locker != 1))}checked=""
                                    {/if} />
                                <label for="delivery_type_warehouse" class="okay_type_radio">
                                    <span>Відділення</span>
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
                                    <span>Поштомат
                                        <i class="fn_tooltips"
                                            title="Ідентифікатор упаковки для кожного місця відправлення (налаштування в модулі)">
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {* Габарити для відділення *}
            <div id="warehouse_params" class="row"
                style="display: {if isset($dataNPCostDeliveryDataEntity->pickup_locker) AND ($dataNPCostDeliveryDataEntity->pickup_locker == 1)}none{else}block{/if};">
                <div class="col-md-12">
                    <div class="heading_label mb-h">Параметри вантажу (для відділення):</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-h">
                                <label class="heading_label">Вага (кг):</label>
                                <input class="form-control" type="number" name="warehouse_weight"
                                    value="{if isset($dataNPCostDeliveryDataEntity->warehouse_weight) && $dataNPCostDeliveryDataEntity->warehouse_weight}{$dataNPCostDeliveryDataEntity->warehouse_weight}{else}{$settings->novapost_warehouse_weight|default:'0.5'}{/if}"
                                    placeholder="{$settings->novapost_warehouse_weight|default:'0.5'}" min="0.1"
                                    step="0.1" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group mb-h">
                                <label class="heading_label">Об'єм (м³):</label>
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
                    <div class="heading_label mb-h">Параметри вантажу (для поштомату):</div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group mb-h">
                                <label class="heading_label">Вага (кг):</label>
                                <input class="form-control" type="number" name="volumetric_weight"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_weight) && $dataNPCostDeliveryDataEntity->volumetric_weight}{$dataNPCostDeliveryDataEntity->volumetric_weight}{else}{$settings->novapost_volumetric_weight}{/if}"
                                    placeholder="{$settings->novapost_weight|default:'0.1'}" min="0" max="20"
                                    step="0.1" />
                                <small class="text-muted">Макс. 20 кг</small>
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
                                <label class="heading_label">Довжина:</label>
                                <input class="form-control" type="number" name="volumetric_length"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_length) && $dataNPCostDeliveryDataEntity->volumetric_length}{$dataNPCostDeliveryDataEntity->volumetric_length}{else}{$settings->novapost_volumetric_length}{/if}"
                                    placeholder="{$settings->novapost_length|default:'15'}" min="0" max="60" />
                                <small class="text-muted">Макс. 60 см</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="heading_label">Ширина:</label>
                                <input class="form-control" type="number" name="volumetric_width"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_width) && $dataNPCostDeliveryDataEntity->volumetric_width}{$dataNPCostDeliveryDataEntity->volumetric_width}{else}{$settings->novapost_volumetric_width}{/if}"
                                    placeholder="{$settings->novapost_width|default:'10'}" min="0" max="40" />
                                <small class="text-muted">Макс. 40 см</small>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label class="heading_label">Висота:</label>
                                <input class="form-control" type="number" name="volumetric_height"
                                    value="{if isset($dataNPCostDeliveryDataEntity->volumetric_height) && $dataNPCostDeliveryDataEntity->volumetric_height}{$dataNPCostDeliveryDataEntity->volumetric_height}{else}{$settings->novapost_volumetric_height}{/if}"
                                    placeholder="{$settings->novapost_height|default:'20'}" min="0" max="30" />
                                <small class="text-muted">Макс. 30 см</small>
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
                    <div class="heading_label">Оголошена цінність:
                        <i class="fn_tooltips" title="Сума зворотної доставки, або оголошена цінність відправлення.">
                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                        </i>
                    </div>
                    <input name="cost" class="form-control mb-h fn_cost" type="number"
                        value="{if isset($dataNPCostDeliveryDataEntity->cost) || !empty($dataNPCostDeliveryDataEntity->cost)}{$dataNPCostDeliveryDataEntity->cost}{else}{$order->total_price|escape}{/if}"
                        min="0" step="0.01" />
                </div>
                <div class="col-md-6">
                    <div class="heading_label">Контроль оплати
                        <i class="fn_tooltips"
                            title="Пряме зарахування коштів від отримувача на рахунок відправника. Схоже на Грошовий переказ. Не можна використовувати разом з Грошовим переказом.">
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
                Додаткова інформація про відправку:
                <i class="fn_tooltips" title="Максимальна довжина 100 символів">
                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                </i>
            </span>
        </div>
        <input class="form-control" type="text" name="additional-information"
            value="{if $dataNPCostDeliveryDataEntity->additional_information}{$dataNPCostDeliveryDataEntity->additional_information}{else}{$order->additional_information}{/if}"
            placeholder="Побутова техніка" />
    </div>

    <div class="row mb-2">
        <div class="col-md-12">
            <div class="heading_label">Тип вантажу:</div>
            <div id="cargo_type" class="row">
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="cargo_type_radio_1" class="hidden_check" name="cargo_type_radiobutton" type="radio"
                            value="Cargo"
                            {if isset($dataNPCostDeliveryDataEntity->cargo_type) AND ($dataNPCostDeliveryDataEntity->cargo_type == 'Cargo')}checked=""
                            {elseif $settings->novapost_cargo_type == 'Cargo'}checked="" 
                            {/if} />
                        <label for="cargo_type_radio_1" class="okay_type_radio">
                            <span>Вантаж</span>
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
                            <span>Документи</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="heading_label">Платник за доставку:</div>
            <div id="payer_type" class="row">
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="payer_type_radio_1" class="hidden_check" name="payer_type_radiobutton" type="radio"
                            value="Sender"
                            {if isset($dataNPCostDeliveryDataEntity->payer_type) AND ($dataNPCostDeliveryDataEntity->payer_type == 'Sender')}checked=""
                            {elseif $settings->novapost_payer_type == 'Sender'}checked="" 
                            {/if} />
                        <label for="payer_type_radio_1" class="okay_type_radio">
                            <span>Відправник</span>
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
                            <span>Одержувач</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="heading_label">Платник зворотної доставки:</div>
            <div id="back_payer_type" class="row">
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="back_payer_type_radio_1" class="hidden_check" name="back_payer_type_radiobutton"
                            type="radio" value="Sender"
                            {if isset($dataNPCostDeliveryDataEntity->back_payer_type) AND ($dataNPCostDeliveryDataEntity->back_payer_type == 'Sender')}checked=""
                            {elseif ($settings->novapost_back_payer_type == 'Sender')}checked="" 
                            {/if} />
                        <label for="back_payer_type_radio_1" class="okay_type_radio">
                            <span>Відправник</span>
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
                            <span>Одержувач</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-12">
            <div class="heading_label">Форма оплати:</div>
            <div id="payment_method" class="row">
                <div class="col-md-6">
                    <div class="okay_type_radio_wrap">
                        <input id="payment_method_radio_1" class="hidden_check" name="payment_method_radiobutton"
                            type="radio" value="Cash" {if !empty($dataNPCostDeliveryDataEntity->payment_method)}
                                {if $dataNPCostDeliveryDataEntity->payment_method == 'Cash'}checked="" {/if}
                            {elseif $settings->novapost_payment_method == 'Cash'}checked="" 
                            {/if} />
                        <label for="payment_method_radio_1" class="okay_type_radio">
                            <span>Готівкою
                                <i class="fn_tooltips"
                                    title='Готівкова оплата: готівкою або банківською карткою на сайті/через термінал.'>
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
                            <span>Безготівковий
                                <i class="fn_tooltips"
                                    title='Безготівкова оплата для організацій з договором з НП. Оплата на розрахунковий рахунок згідно акту виконаних робіт (не банківська картка).'>
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
            <div class="heading_label">Місто</div>
            <div class="edit_order_detail">
                <select name="city" style="width: 265px;padding: 2px;" data-placeholder="Виберіть місто" tabindex="1"
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
        <div class="fn_delivery_novaposhta" style="display: block;">
            <div class="fn_error hidden boxed boxed_warning"></div>
            <button id="fn_generate_document" class="btn btn-info {if $novaposhta_delivery_data->ref_id} disabled{/if}">
                <span class="btn-text">Створити накладну</span>
                <span class="btn-loader hidden">
                    <span class="spinner"></span>
                    <span class="loader-text">Створення...</span>
                </span>
            </button>
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
                        toastr.warning('Замовлення сплачене, контроль оплати недоступний', 'Увага');
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
                    const isLocker = $(this).val() === 'locker';
                    $('#volumetric_params').toggle(isLocker);
                    $('#warehouse_params').toggle(!isLocker);
                });

                // Валідація об'єму (макс. 30 кг об'ємної ваги)
                $('#warehouse_volume').on('input', function() {
                    const volume = parseFloat($(this).val());
                    if (!isNaN(volume)) {
                        const volumetricWeight = volume * 250;
                        if (volumetricWeight > 30) {
                            $(this).val('0.12');
                            alert('Об\'ємна вага (' + volumetricWeight.toFixed(2) +
                                ' кг) перевищує ліміт 30 кг. Об\'єм обмежено до 0.12 м³');
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

                    const pickup_locker = getRadioValue('delivery_type_radiobutton') === 'locker' ? 1 : 0;
                    let warehouse_volume = $('input[name="warehouse_volume"]').val();
                    let warehouse_weight = $('input[name="warehouse_weight"]').val();

                    if (!warehouse_volume || warehouse_volume === '0') warehouse_volume = '';
                    if (!warehouse_weight || warehouse_weight === '0') warehouse_weight = '';

                    $.ajax({
                        url: "{/literal}{url_generator route="Sviat_NovaPoshtaTracking_generateDocument" absolute=1}{literal}",
                        data: {
                            order_id: '{/literal}{$order->id}{literal}',
                            payer_type_value: getRadioValue('payer_type_radiobutton'),
                            cargo_type_value: getRadioValue('cargo_type_radiobutton'),
                            back_payer_type_value: getRadioValue('back_payer_type_radiobutton'),
                            payment_method_value: getRadioValue('payment_method_radiobutton'),
                            service_type_value: getRadioValue('service_type_radiobutton'),
                            additional_information_value: $('input[name="additional-information"]').val(),
                            control_payment: control_payment,
                            control_payment_value: $('input[name="cost"]').val(),
                            pickup_locker: pickup_locker,
                            volumetric_volume: $('input[name="volumetric_volume"]').val(),
                            volumetric_length: $('input[name="volumetric_length"]').val(),
                            volumetric_width: $('input[name="volumetric_width"]').val(),
                            volumetric_height: $('input[name="volumetric_height"]').val(),
                            volumetric_weight: $('input[name="volumetric_weight"]').val(),
                            warehouse_volume: warehouse_volume,
                            warehouse_weight: warehouse_weight
                        },
                        dataType: 'json',
                        success: function(data) {
                            if (data.ref_id) {
                                if (data.hasOwnProperty('tracking_document')) {
                                    $('.tracking_document').html(data.tracking_document);
                                }
                                $('.fn_document_input')
                                    .text(data.int_doc_number)
                                    .attr('href', 'https://new.novaposhta.ua/edit/' + data.ref_id)
                                    .closest('.document_wrap')
                                    .removeClass('hidden');
                                $('.fn_error').addClass('hidden');
                                
                                $('#fn_generate_document').addClass('disabled').prop('disabled', true);
                                toggleLoading($button, $buttonText, $buttonLoader, false);
                                
                                toastr.success('Накладну ' + (data.int_doc_number || '') + ' успішно створено', 'Успіх');
                                setTimeout(() => location.reload(), 1500);
                            } else if (data.error) {
                                toggleLoading($button, $buttonText, $buttonLoader, false);
                                $('.fn_error').text(data.error).removeClass('hidden');
                                toastr.error(data.error, 'Помилка');
                            } else {
                                toggleLoading($button, $buttonText, $buttonLoader, false);
                                toastr.error('Невідома помилка', 'Помилка');
                            }
                        },
                        error: function(xhr, status, errorThrown) {
                            toggleLoading($button, $buttonText, $buttonLoader, false);
                            toastr.error('Помилка створення накладної: ' + errorThrown, 'Помилка');
                        }
                    });
                });
            </script>
        {/literal}
    {/if}
</div>