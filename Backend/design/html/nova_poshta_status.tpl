{if $order->novaposhta_tracking && !empty($order->novaposhta_tracking->int_doc_number)}
    {assign var="tracking" value=$order->novaposhta_tracking}
    {assign var="trackingResponse" value=$tracking->tracking_response_decoded}

    <div class="font_12 text_500 mb-q np-status-container">
        {if !empty($tracking->int_doc_number)}
            <div class="np-logo-icon">
                <svg xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24" fill="none" width="16" height="16"
                    xmlns="http://www.w3.org/2000/svg">
                    <path fill="var(--special-fill, #f23c32)" clip-rule="evenodd"
                        d="M1.70703 11.293C1.31641 11.6835 1.31641 12.3167 1.70703 12.7072L5.99988 17.0001V14.0001V10.0001V7.00009L1.70703 11.293ZM6.99988 6.00009H9.99988V10.0001H13.9999V6.00009H16.9999L12.707 1.70718C12.3164 1.31668 11.6833 1.31668 11.2927 1.70718L6.99988 6.00009ZM17.9999 7.00009V10.0001V14.0001V17.0001L22.2927 12.7072C22.6833 12.3167 22.6833 11.6835 22.2927 11.293L17.9999 7.00009ZM16.9999 18.0001H13.9999V14.0001H9.99988V18.0001H6.99988L11.2927 22.293C11.6833 22.6835 12.3164 22.6835 12.707 22.293L16.9999 18.0001Z" />
                </svg>
            </div>
            <a href="" class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile np-status-doc-number"
                data-hint="Натисніть, щоб скопіювати" data-hint-copied="✔ Скопійовано"
                data-copy-string="{$tracking->int_doc_number}">
                {$tracking->formatNumber}
            </a>
        {/if}

        {if !empty($tracking->status_formatted) && !empty($tracking->status_formatted->badge_class)}
            <span class="np-status-badge hint-bottom-middle-t-info-s-small-mobile {$tracking->status_formatted->badge_class}"
                {if !empty($trackingResponse) && !empty($trackingResponse->Status)}data-hint="{$trackingResponse->Status|escape}"
                {/if}>
                {$tracking->status_formatted->text|default:''}
            </span>
        {elseif !empty($tracking->status_code)}
            {* Fallback для випадку, якщо status_formatted не встановлено *}
            <span class="np-status-badge hint-bottom-middle-t-info-s-small-mobile np-status-badge--failed"
                {if !empty($trackingResponse) && !empty($trackingResponse->Status)}data-hint="{$trackingResponse->Status|escape}"
                {/if}>
                Статус: {$tracking->status_code}
            </span>
        {/if}

    </div>
    {literal}
        <script>
            sclipboard();
        </script>
    {/literal}
{/if}