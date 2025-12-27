{$meta_title = $btr->settings_np scope=global}

<div class="row">
    <div class="col-lg-7 col-md-7">
        <div class="heading_page">{$btr->settings_np|escape}</div>
    </div>
    <div class="col-lg-5 col-md-5 float-xs-right"></div>
</div>

{* Повідомлення про успіх *}
{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success == 'saved'}
                            {$btr->general_settings_saved|escape}
                        {/if}
                    </div>
                </div>
                {if $smarty.get.return}
                    <a class="alert__button" href="{$smarty.get.return}">
                        {include file='svg_icon.tpl' svgId='return'}
                        <span>{$btr->general_back|escape}</span>
                    </a>
                {/if}
            </div>
        </div>
    </div>
{/if}

<form method="post" enctype="multipart/form-data">
    <input type=hidden name="session_id" value="{$smarty.session.id}">

    {* Інформація про модуль *}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="alert alert--icon">
                <div class="alert__content">
                    <div class="alert__title">{$btr->alert_description|escape}</div>
                    <p>Модуль для генерації експрес-накладних Нової Пошти</p>
                    <p><b>Важливо:</b> Модуль працює за умови встановленого OkayCMS/NovaposhtaCost і доповнює його
                        можливості.</p>
                </div>
            </div>
        </div>
    </div>

    {* Основні налаштування *}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed mb-2">
                <div class="heading_box mb-2">Основні налаштування</div>
                <div class="row">
                    <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                            <div class="heading_label heading_label--required">
                                <span>Ключ API "Нова Пошта"</span>
                            </div>
                            <input name="newpost_key" class="form-control" type="text"
                                value="{$settings->newpost_key|escape}" required />
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                            <div class="heading_label heading_label--required">
                                <span>Телефон відправника</span>
                            </div>
                            <input name="sender_phone" class="form-control" type="text"
                                value="{$settings->novapost_sender_phone|escape}" required />
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                            <div class="heading_label heading_label--required">
                                <span>Місто відправника</span>
                            </div>
                            <input type="text" class="fn_newpost_city_name form-control" name="newpost_city_name"
                                required value="{$settings->newpost_city|newpost_city}">
                            <input type="hidden" name="newpost_city" value="{$settings->newpost_city|escape}" required>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                            <div class="heading_label heading_label--required">
                                <span>Відділення відправника</span>
                            </div>
                            <select data-placeholder="Виберіть відділення"
                                class="fn_warehouse selectpicker form-control" data-live-search="true"
                                required></select>
                            <input type="hidden" value="{$settings->novapost_sender_warehouse|escape}"
                                name="sender_warehouse" required>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                            <div class="heading_label heading_label--required">
                                <span>Тип вантажу</span>
                            </div>
                            <div class="d_flex" style="gap: 20px; align-items: center;">
                                <div class="okay_type_radio_wrap mb-0">
                                    <input id="cargo_type_1" class="hidden_check" name="cargo_type" value="Cargo"
                                        type="radio" required
                                        {if !$settings->novapost_cargo_type || $settings->novapost_cargo_type == 'Cargo'}checked=""
                                        {/if} />
                                    <label for="cargo_type_1" class="okay_type_radio">
                                        <span>Вантаж</span>
                                    </label>
                                </div>
                                <div class="okay_type_radio_wrap mb-0">
                                    <input id="cargo_type_2" class="hidden_check" name="cargo_type" value="Documents"
                                        type="radio" required
                                        {if $settings->novapost_cargo_type == 'Documents'}checked="" {/if} />
                                    <label for="cargo_type_2" class="okay_type_radio">
                                        <span>Документи</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-4">
                        <div class="form-group">
                            <div class="heading_label heading_label--required">
                                <span>Час відправки сьогодні</span>
                                <i class="fn_tooltips"
                                    title='Час, до якого прийняті замовлення відправляються в той же день. Після цього часу - на наступний робочий день.'>
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            <select name="time_today_date" class="selectpicker form-control fn_time_today_date"
                                required></select>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* Налаштування за замовчуванням *}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed mb-2">
                <div class="heading_box mb-2">Базові налаштування відправок</div>
                <div class="row">
                    <div class="col-lg-6 col-md-6">
                        <div class="heading_label mb-2 font_14 text_600">Оплата</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Платник посилки</span>
                                    </div>
                                    <div class="okay_type_radio_wrap mb-h">
                                        <input id="payer_type_1" class="hidden_check" name="payer_type" value="Sender"
                                            type="radio" required
                                            {if $settings->novapost_payer_type == 'Sender'}checked=""
                                            {/if} />
                                        <label for="payer_type_1" class="okay_type_radio">
                                            <span>Відправник</span>
                                        </label>
                                    </div>
                                    <div class="okay_type_radio_wrap mb-0">
                                        <input id="payer_type_2" class="hidden_check" name="payer_type"
                                            value="Recipient" type="radio" required
                                            {if !$settings->novapost_payer_type || $settings->novapost_payer_type == 'Recipient'}checked="" {/if} />
                                        <label for="payer_type_2" class="okay_type_radio">
                                            <span>Одержувач</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Платник зворотної доставки</span>
                                    </div>
                                    <div class="okay_type_radio_wrap mb-h">
                                        <input id="back_payer_type_1" class="hidden_check" name="back_payer_type"
                                            value="Sender" type="radio" required
                                            {if $settings->novapost_back_payer_type == 'Sender'}checked="" {/if} />
                                        <label for="back_payer_type_1" class="okay_type_radio">
                                            <span>Відправник</span>
                                        </label>
                                    </div>
                                    <div class="okay_type_radio_wrap mb-0">
                                        <input id="back_payer_type_2" class="hidden_check" name="back_payer_type"
                                            value="Recipient" type="radio" required
                                            {if !$settings->novapost_back_payer_type || $settings->novapost_back_payer_type == 'Recipient'}checked="" {/if} />
                                        <label for="back_payer_type_2" class="okay_type_radio">
                                            <span>Одержувач</span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Тип оплати</span>
                                    </div>
                                    <div class="okay_type_radio_wrap mb-h">
                                        <input id="payment_method_1" class="hidden_check" name="payment_method"
                                            value="Cash" type="radio" required
                                            {if !$settings->novapost_payment_method || $settings->novapost_payment_method == 'Cash'}checked=""
                                            {/if} />
                                        <label for="payment_method_1" class="okay_type_radio">
                                            <span>Готівкою</span>
                                            <i class="fn_tooltips pl-1"
                                                title='Готівкою або банківською карткою на сайті/через термінал.'>
                                                {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                            </i>
                                        </label>
                                    </div>
                                    <div class="okay_type_radio_wrap mb-0">
                                        <input id="payment_method_2" class="hidden_check" name="payment_method"
                                            value="NonCash" type="radio" required
                                            {if $settings->novapost_payment_method == 'NonCash'}checked="" {/if} />
                                        <label for="payment_method_2" class="okay_type_radio">
                                            <span>Безготівковий</span>
                                            <i class="fn_tooltips pl-1"
                                                title='Безготівковий розрахунок для відправника або отримувача доступний лише за умови підписання договору з Новою Поштою.'>
                                                {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                            </i>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="activity_of_switch activity_of_switch--left">
                                        <div class="activity_of_switch_item">
                                            <div class="okay_switch clearfix">
                                                <label class="switch_label">
                                                    Включити контроль оплати
                                                    <i class="fn_tooltips"
                                                        title='Послуга передбачає контроль оплати готівкою за отримане Отримувачем відправлення. Кошти перераховуються Відправнику на його поточний рахунок (наступного робочого дня) на підставі укладеного договору з Новою Поштою.'>
                                                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                                    </i>
                                                </label>
                                                <label class="switch switch-default">
                                                    <input class="switch-input" name="payment_control" value="1"
                                                        type="checkbox" id="example_checkbox"
                                                        {if $settings->novapost_payment_control == '1'}checked="" {/if}>
                                                    <span class="switch-label"></span>
                                                    <span class="switch-handle"></span>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6 mb-3">
                        <div class="heading_label mb-2 font_14 text_600">Габарити для поштомату</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Об'єм місця</span>
                                    </div>
                                    <div class="input-group">
                                        <input name="novapost_volumetric_volume" class="form-control" type="number"
                                            required
                                            value="{$settings->novapost_volumetric_volume|escape|default:'0.0004'}"
                                            min="0.0004" step="0.0001" />
                                        <span class="input-group-addon">см³</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Фактична вага</span>
                                        <i class="fn_tooltips" title='Максимум: 20 кг (для поштомату)'>
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </div>
                                    <div class="input-group">
                                        <input name="novapost_volumetric_weight" class="form-control" type="number"
                                            required
                                            value="{$settings->novapost_volumetric_weight|escape|default:'0.5'}" min="0"
                                            max="20" step="0.1" />
                                        <span class="input-group-addon">кг</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Довжина</span>
                                        <i class="fn_tooltips" title='Максимум: 35 см'>
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </div>
                                    <div class="input-group">
                                        <input name="novapost_volumetric_length" class="form-control" type="number"
                                            required value="{$settings->novapost_volumetric_length|escape|default:'10'}"
                                            min="0" max="35" />
                                        <span class="input-group-addon">см</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Ширина</span>
                                        <i class="fn_tooltips" title='Максимум: 37 см'>
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </div>
                                    <div class="input-group">
                                        <input name="novapost_volumetric_width" class="form-control" type="number"
                                            required value="{$settings->novapost_volumetric_width|escape|default:'10'}"
                                            min="0" max="37" />
                                        <span class="input-group-addon">см</span>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-4">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Висота</span>
                                        <i class="fn_tooltips" title='Максимум: 61 см'>
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </div>
                                    <div class="input-group">
                                        <input name="novapost_volumetric_height" class="form-control" type="number"
                                            required value="{$settings->novapost_volumetric_height|escape|default:'10'}"
                                            min="0" max="61" />
                                        <span class="input-group-addon">см</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-md-6">
                        <div class="heading_label mb-2 font_14 text_600">Габарити для відділення</div>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Об'єм</span>
                                        <i class="fn_tooltips" title="Максимум: 0.12 м³ (об'ємна вага не більше 30 кг)">
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </div>
                                    <div class="input-group">
                                        <input name="novapost_warehouse_volume" class="form-control" type="number"
                                            required
                                            value="{$settings->novapost_warehouse_volume|escape|default:'0.001'}"
                                            min="0.001" max="0.12" step="0.001" />
                                        <span class="input-group-addon">м³</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <div class="heading_label heading_label--required">
                                        <span>Фактична вага</span>
                                        <i class="fn_tooltips" title='Максимум: 30 кг (для відділення)'>
                                            {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                        </i>
                                    </div>
                                    <div class="input-group">
                                        <input name="novapost_warehouse_weight" class="form-control" type="number"
                                            required value="{$settings->novapost_warehouse_weight|escape|default:'0.5'}"
                                            min="0.1" max="30" step="0.1" />
                                        <span class="input-group-addon">кг</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {* Кнопка збереження *}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed">
                <div class="row mb-0">
                    <div class="col-lg-12 col-md-12">
                        <button type="submit" class="btn btn_small btn_blue float-md-right">
                            {include file='svg_icon.tpl' svgId='checked'}
                            <span>{$btr->general_apply|escape}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script src="{$rootUrl}/backend/design/js/autocomplete/jquery.autocomplete-min.js"></script>
<script src="{$rootUrl}/Okay/Modules/Sviat/NovaPoshtaTracking/Backend/design/js/inputmask.js"></script>
{literal}
    <script>
        $(function() {
            // Генерація часу (крок 30 хв)
            const timeSelect = $('select.fn_time_today_date');
            const selectedTime = '{/literal}{$settings->novapost_time_today_date|escape}{literal}';

            for (let hour = 0; hour < 24; hour++) {
                for (let minute = 0; minute < 60; minute += 30) {
                    const timeValue = String(hour).padStart(2, '0') + ':' + String(minute).padStart(2, '0');
                    const isSelected = selectedTime === timeValue ? ' selected' : '';
                    timeSelect.append(`<option value="${timeValue}"${isSelected}>${timeValue}</option>`);
                }
            }
            timeSelect.selectpicker();

            // Autocomplete міста
            $(".fn_newpost_city_name").devbridgeAutocomplete({
                serviceUrl: okay.router['OkayCMS_NovaposhtaCost_find_city'],
                minChars: 1,
                maxHeight: 320,
                noCache: true,
                onSelect: function(suggestion) {
                    $('input[name="sender_warehouse"]').val('');
                    $('[name="newpost_city"]').val(suggestion.data.ref);
                    showWarehouses(suggestion.data.ref);
                },
                formatResult: function(suggestion, currentValue) {
                    const reEscape = new RegExp('(\\' + ['/', '.', '*', '+', '?', '|', '(', ')', '[', ']', '{', '}', '\\'].join('|\\') + ')', 'g');
                    const pattern = '(' + currentValue.replace(reEscape, '\\$1') + ')';
                    return "<span>" + suggestion.value.replace(new RegExp(pattern, 'gi'),
                        '<strong>$1<\/strong>') + "<\/span>";
                }
            });

        {/literal}
        {if !empty($settings->get('newpost_city'))}
            showWarehouses('{$settings->get('newpost_city')|escape}');
        {/if}
        {literal}

            function showWarehouses(cityRef) {
                const selectedWarehouseRef = $('input[name="sender_warehouse"]').val();
                const warehousesSelect = $('select.fn_warehouse');

                $.ajax({
                    url: okay.router['OkayCMS_NovaposhtaCost_get_warehouses'],
                    data: {city: cityRef},
                    dataType: 'json',
                    success: function(data) {
                        if (data.success) {
                            warehousesSelect.selectpicker('destroy').html('');
                            warehousesSelect.append(`<option value="" data-warehouse_ref="">{/literal}{$btr->warehouse_not_selected|escape}{literal}</option>`);

                            for (let warehouseKey in data.warehouses) {
                                const warehouse = data.warehouses[warehouseKey];
                                const isSelected = selectedWarehouseRef && selectedWarehouseRef ==
                                    warehouse.ref ? 'selected' : '';
                                warehousesSelect.append(`<option value="${warehouse.name}" data-warehouse_ref="${warehouse.ref}" ${isSelected}>${warehouse.name}</option>`);
                            }

                            warehousesSelect.show().selectpicker();
                        } else {
                            warehousesSelect.selectpicker('destroy').html('').hide();
                        }
                    }
                });
            }

            $(document).on('changed.bs.select', 'select.fn_warehouse', function() {
                const wareRef = $(this).find('option:selected').data('warehouse_ref');
                $('input[name="sender_warehouse"]').val(wareRef || '');
            });
        });
    </script>
{/literal}