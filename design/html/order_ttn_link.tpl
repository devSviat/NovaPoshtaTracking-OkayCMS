{if $orderTracking && !empty($orderTracking->int_doc_number)}
    <tr>
        <td>
            <span data-language="sviat_novaposhta_tracking_ttn_number">{$lang->sviat__novaposhta_tracking__ttn_number|escape}</span>
        </td>
        <td>
            <a href="https://novaposhta.ua/tracking/{$orderTracking->int_doc_number|escape:'url'}" target="_blank" rel="noopener">
                {$orderTracking->int_doc_number|escape}
            </a>
        </td>
    </tr>
{/if}
