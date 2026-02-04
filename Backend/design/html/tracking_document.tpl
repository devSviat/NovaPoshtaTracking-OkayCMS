{if $tracking_document->Number}
    <div class="tracking_document">
        <div class="np-document-wrapper mb-2">
            <!-- Заголовок -->
            <div class="np-document-header">
                {if $tracking_document->DateTimeCreatedFormatted || $tracking_document->DateCreatedFullFormatted || $tracking_document->DateTimeFormatted || $tracking_document->DateCreatedFormatted || $tracking_document->DateTime || $tracking_document->DateCreated}
                    <div class="text-muted font_12 mb-h">
                        Створена:
                        {$tracking_document->DateTimeCreatedFormatted|default:$tracking_document->DateCreatedFullFormatted|default:$tracking_document->DateTimeFormatted|default:$tracking_document->DateCreatedFormatted|default:$tracking_document->DateTime|default:$tracking_document->DateCreated}
                    </div>
                {/if}
                <div class="row">
                    <!-- Номер накладної -->
                    <div class="col-md-6">
                        <div class="font_26 text_600">
                            <a href="" class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile"
                                data-hint="Натисніть, щоб скопіювати" data-hint-copied="✔ Скопійовано"
                                data-copy-string="{$tracking_document->Number}">
                                {$tracking_document->formatNumber}
                            </a>
                            <a href="https://new.novaposhta.ua/edit/{$tracking_document->refId}" class="np-document-link"
                                target="_blank">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
                                    fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="icon icon-tabler icons-tabler-outline icon-tabler-external-link">
                                    <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                    <path d="M12 6h-6a2 2 0 0 0 -2 2v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2 -2v-6" />
                                    <path d="M11 13l9 -9" />
                                    <path d="M15 4h5v5" />
                                </svg>
                            </a>
                        </div>
                    </div>
                    <!-- Опис відправлення -->
                    <div class="col-md-3">
                        {if $tracking_document->CargoDescriptionString}
                            <div class="np-info-cell">
                                <div class="np-info-cell__label">Опис відправлення</div>
                                <div class="np-info-cell__content">{$tracking_document->CargoDescriptionString}</div>
                            </div>
                        {/if}
                    </div>
                    <!-- Оголошена цінність -->
                    <div class="col-md-3">
                        {if $tracking_document->AnnouncedPriceFormatted || $tracking_document->AnnouncedPrice}
                            <div class="np-info-cell">
                                <div class="np-info-cell__label">Оголошена цінність</div>
                                <div class="np-info-cell__content text_700">
                                    {if $tracking_document->AnnouncedPriceFormatted}
                                        {$tracking_document->AnnouncedPriceFormatted} ₴
                                    {else}
                                        {$tracking_document->AnnouncedPrice} ₴
                                    {/if}
                                </div>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>

            <!-- Основна інформація -->
            <div class="card-block">
                <div class="row">
                    <div class="col-md-6">
                        <!-- Статус доставки -->
                        {if $tracking_document->Status}
                            <div class="np-document-state">
                                {* Дата виїзду *}
                                {if $tracking_document->DateCreatedFormatted || $tracking_document->DateTimeFormatted}
                                    <div class="mb-2">
                                        <div>Дата виїзду:
                                            {$tracking_document->DateCreatedFormatted|default:$tracking_document->DateTimeFormatted}
                                        </div>
                                        {if $tracking_document->SenderAddressFormatted}
                                            <div class="font_12 mt-q">
                                                {$tracking_document->SenderAddressFormatted}
                                            </div>
                                        {/if}
                                    </div>
                                {/if}

                                <div class="np-document-status-text text-muted">
                                    {$tracking_document->Status}
                                </div>

                                {* Плановий час доставки *}
                                {if $tracking_document->ScheduledDeliveryDateFinal}
                                    <div class="mt-2">
                                        <div>Плановий час доставки: {$tracking_document->ScheduledDeliveryDateFinal}</div>
                                        {if $tracking_document->RecipientAddressFormatted}
                                            <div class="font_12 mt-q">
                                                {$tracking_document->RecipientAddressFormatted}
                                            </div>
                                        {/if}
                                    </div>
                                {/if}
                            </div>
                        {/if}
                    </div>

                    <div class="col-md-4">
                        <div class="np-document-body__info">
                            {if $tracking_document->RecipientFullName || $tracking_document->RecipientFullNameEW}
                                <div class="np-info-cell mb-h">
                                    <div class="np-info-cell__label">Отримувач</div>
                                    <div class="np-info-cell__content">
                                        {$tracking_document->RecipientFullName|default:$tracking_document->RecipientFullNameEW}
                                    </div>
                                </div>
                            {/if}
                            {if $tracking_document->PhoneRecipient}
                                <div class="np-info-cell mb-h">
                                    <div class="np-info-cell__label">Телефон отримувача</div>
                                    <div class="np-info-cell__content">{$tracking_document->PhoneRecipient}</div>
                                </div>
                            {/if}
                            {if $tracking_document->RecipientDateTime}
                                <div class="np-info-cell">
                                    <div class="np-info-cell__label">Отримав(ла)</div>
                                    <div class="np-info-cell__content">{$tracking_document->RecipientDateTime}</div>
                                </div>
                            {/if}
                            {if $tracking_document->RecipientAddress}
                                <div class="np-info-cell">
                                    <div class="np-info-cell__label">Адреса отримувача</div>
                                    <div class="np-info-cell__content">{$tracking_document->RecipientAddress}</div>
                                </div>
                            {/if}
                        </div>
                    </div>
                </div>
            </div>

            <!-- Додаткові послуги -->
            {if $tracking_document->Redelivery || $tracking_document->RedeliverySum || $tracking_document->AfterpaymentOnGoodsCost || ($tracking_document->LastCreatedOnTheBasisDocumentType == 'CargoReturn' && $tracking_document->LastCreatedOnTheBasisNumber) || ($tracking_document->LastCreatedOnTheBasisDocumentType == 'Redirecting' && $tracking_document->LastCreatedOnTheBasisNumber)}
                <div class="np-document-services">
                    <div class="font_12 text-muted text-uppercase mb-h">Додаткові послуги</div>
                    <div class="np-document-services__items-wrapper">
                        {if $tracking_document->Redelivery || $tracking_document->RedeliverySum}
                            <div class="np-document-services__item">
                                <span class="font_12 text_600">
                                    Зворотна доставка
                                    {if $tracking_document->RedeliverySum}
                                        :
                                        {if $tracking_document->RedeliverySumFormatted}{$tracking_document->RedeliverySumFormatted}{else}{$tracking_document->RedeliverySum}{/if}
                                        ₴
                                    {/if}
                                </span>
                                {if $tracking_document->RedeliveryPayer}
                                    <span class="text-muted font_12">
                                        • Платник за комісію:
                                        {$tracking_document->RedeliveryPayerDisplay|default:$tracking_document->RedeliveryPayer}
                                    </span>
                                {/if}
                                {if $tracking_document->RedeliveryNum}
                                    <span class="text-muted font_12">
                                        • Номер ЕН зворотної доставки: {$tracking_document->RedeliveryNum}
                                    </span>
                                {/if}
                            </div>
                            <span class="np-document-services__separator">|</span>
                        {/if}
                        {if $tracking_document->AfterpaymentOnGoodsCost}
                            <div class="np-document-services__item">
                                <span class="font_12 text_600">
                                    Контроль оплати:
                                    {$tracking_document->AfterpaymentOnGoodsCostFormatted|default:$tracking_document->AfterpaymentOnGoodsCost}
                                    ₴
                                </span>
                            </div>
                            <span class="np-document-services__separator">|</span>
                        {/if}
                        {if $tracking_document->LastCreatedOnTheBasisDocumentType == 'CargoReturn' && $tracking_document->LastCreatedOnTheBasisNumber}
                            <div class="np-document-services__item">
                                <span class="font_12 text_600">Повернення</span>
                                <span class="text-muted font_12">
                                    • Номер повернення: {$tracking_document->LastCreatedOnTheBasisNumber}
                                </span>
                            </div>
                            <span class="np-document-services__separator">|</span>
                        {/if}
                        {if $tracking_document->LastCreatedOnTheBasisDocumentType == 'Redirecting' && $tracking_document->LastCreatedOnTheBasisNumber}
                            <div class="np-document-services__item">
                                <span class="font_12 text_600">Перенаправлення</span>
                                <span class="text-muted font_12">
                                    • Номер перенаправлення: {$tracking_document->LastCreatedOnTheBasisNumber}
                                </span>
                            </div>
                        {/if}
                    </div>
                </div>
            {/if}

            <!-- Оплата -->
            {if $tracking_document->DocumentCost || $tracking_document->PayerType || $tracking_document->PaymentMethod}
                <div class="card-block">
                    <div class="row">
                        <!-- Вартість доставки -->
                        <div class="col-md-3">
                            {if $tracking_document->DocumentCost}
                                <div class="np-info-cell">
                                    <div class="np-info-cell__label">Вартість доставки</div>
                                    <div class="font_18 text_600 np-info-cell__content">
                                        {if $tracking_document->DocumentCostFormatted}
                                            {$tracking_document->DocumentCostFormatted} ₴
                                        {else}
                                            {$tracking_document->DocumentCost} ₴
                                        {/if}
                                    </div>
                                </div>
                            {/if}
                        </div>
                        <!-- Платник за доставку -->
                        <div class="col-md-3">
                            {if $tracking_document->PayerType}
                                <div class="np-info-cell">
                                    <div class="np-info-cell__label">Платник за доставку</div>
                                    <div class="np-info-cell__content">
                                        {$tracking_document->PayerTypeDisplay|default:$tracking_document->PayerType}</div>
                                </div>
                            {/if}
                        </div>
                        <!-- Форма оплати -->
                        <div class="col-md-3">
                            {if $tracking_document->PaymentMethod}
                                <div class="np-info-cell">
                                    <div class="np-info-cell__label">Форма оплати за доставку</div>
                                    <div class="np-info-cell__content">
                                        {$tracking_document->PaymentMethodDisplay|default:$tracking_document->PaymentMethod}</div>
                                </div>
                            {/if}
                        </div>
                        <div class="col-md-3">
                        </div>
                    </div>
                </div>
            {/if}

            <!-- Детальна інформація (прихована) -->
            <div class="card-block boxed--grey np-document-details np-document-details--hidden" style="display: none;">
                <div class="font_14 text_600 text-uppercase mb-1">Детальна інформація</div>

                <!-- Основні параметри -->
                <div class="row mb-2">
                    <div class="col-md-6">
                        <div class="row">
                            {if $tracking_document->CargoTypeDisplay || $tracking_document->CargoType}
                                <div class="col-md-3">
                                    <div class="np-info-cell">
                                        <div class="np-info-cell__label">Тип</div>
                                        <div class="np-info-cell__content">
                                            {$tracking_document->CargoTypeDisplay|default:$tracking_document->CargoType}</div>
                                    </div>
                                </div>
                            {/if}
                            {if $tracking_document->DocumentWeightFormatted || $tracking_document->DocumentWeight}
                                <div class="col-md-3">
                                    <div class="np-info-cell">
                                        <div class="np-info-cell__label">Вага</div>
                                        <div class="np-info-cell__content">
                                            {if $tracking_document->DocumentWeightFormatted}
                                                {$tracking_document->DocumentWeightFormatted}
                                            {else}
                                                {$tracking_document->DocumentWeight} кг
                                            {/if}
                                        </div>
                                    </div>
                                </div>
                            {/if}
                            {if $tracking_document->VolumeWeightFormatted || $tracking_document->VolumeWeight}
                                <div class="col-md-3">
                                    <div class="np-info-cell">
                                        <div class="np-info-cell__label">Об'ємна вага</div>
                                <div class="np-info-cell__content">
                                    {if $tracking_document->VolumeWeightFormatted}
                                    {$tracking_document->VolumeWeightFormatted}
                                    {else}
                                    {$tracking_document->VolumeWeight} кг
                                    {/if}
                                </div>
                            </div>
                        </div>
                        {/if}
                        {if $tracking_document->SeatsAmount}
                        <div class="col-md-3">
                            <div class="np-info-cell">
                                <div class="np-info-cell__label">Місць</div>
                                <div class="np-info-cell__content">{$tracking_document->SeatsAmount}</div>
                            </div>
                        </div>
                        {/if}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="np-info-cell">
                        <div class="np-info-cell__label">Внутрішній номер відправлення</div>
                        <div class="np-info-cell__content">{$tracking_document->ClientBarcode|default:'—'}</div>
                    </div>
                </div>
            </div>

            <!-- Фактична вага та спосіб доставки -->
            <div class="row mb-2">
                {if $tracking_document->FactualWeightFormatted || $tracking_document->FactualWeight || $tracking_document->ServiceType}
                <div class="col-md-6">
                    <div class="np-info-cell">
                        <div class="np-info-cell__label">Фактична вага з ЕН</div>
                        <div class="np-info-cell__content">
                            {if $tracking_document->FactualWeightFormatted}
                            {$tracking_document->FactualWeightFormatted}
                            {else}
                            {$tracking_document->FactualWeight} кг
                            {/if}
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="np-info-cell">
                        <div class="np-info-cell__label">Спосіб доставки</div>
                        <div class="np-info-cell__content">
                            {$tracking_document->ServiceTypeDisplay|default:$tracking_document->ServiceType}</div>
                    </div>
                </div>
                {/if}
            </div>

        </div>

        <!-- Кнопки -->
        <div class="np-document-footer">
            <div class="d_flex" style="justify-content: space-between; align-items: center;">
                <button type="button" class="np-document-footer__toggle">
                    <span class="np-toggle-text">Показати більше</span>
                    <i class="fn_icon_arrow fa fa-angle-down fa-lg m-t-2"></i>
                </button>
                {if $tracking_data}
                <div class="d_flex" style="align-items: center; gap: 10px;">
                    {if $tracking_data->updated_at}
                    <span class="text-muted font_12">
                        Оновлено: {$tracking_data->updated_at|date_format:"%d.%m.%Y %H:%M"}
                    </span>
                    {/if}
                    {if $order && $order->id}
                    <button type="button" title="Оновити дані трекінгу" class="fn_update_tracking btn btn_np_update"
                        data-order-id="{$order->id}">
                        {include file='svg_icon.tpl' svgId='refresh_icon'}
                    </button>
                    {if $tracking_data && ($tracking_data->status_code == '1' || $tracking_data->status_code == '2')}
                    <button type="button" id="fn_remove_document" title="Видалити накладну"
                        class="btn btn_np_remove fn_remove hint-bottom-right-t-info-s-small-mobile hint-anim"
                        data-toggle="modal" data-target="#fn_action_modal" data-order-id="{$order->id}"
                        data-status-code="{$tracking_data->status_code}" onclick="removeDocumentAction($(this));">
                        {include file='svg_icon.tpl' svgId='trash'}
                            </button>
                        {/if}
                    {/if}
                </div>
                {/if}
            </div>
        </div>
    </div>
    <script src="{$rootUrl}/Okay/Modules/Sviat/NovaPoshtaTracking/Backend/design/js/tracking_document.js"></script>
    <script>
        sclipboard();
        {literal}
        if (typeof window.novaPoshtaRoutes === 'undefined') {
            window.novaPoshtaRoutes = {
                updateTrackingDocument: '{/literal}{url_generator route="Sviat_NovaPoshtaTracking_updateTrackingDocument" absolute=1}{literal}',
                removeDocument: '{/literal}{url_generator route="Sviat_NovaPoshtaTracking_removeDocument" absolute=1}{literal}'
            };
        }
        {/literal}
    </script>
</div>
{/if}