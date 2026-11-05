{if !empty($novaposhta_mass_success)}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success np-mass-success">
                <div class="alert__content">
                    <div class="alert__title">{$novaposhta_mass_success|escape}</div>
                </div>
            </div>
        </div>
    </div>
{/if}
{if !empty($novaposhta_mass_errors)}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--error np-mass-errors">
                <div class="alert__content">
                    <div class="alert__title">{$btr->sviat__novaposhta_tracking__mass_errors_title|escape}</div>
                    <ul class="np-mass-errors__list">
                        {foreach $novaposhta_mass_errors as $order_id => $error_text}
                            <li>
                                <strong>№ {$order_id|escape}:</strong> {$error_text|escape}
                            </li>
                        {/foreach}
                    </ul>
                </div>
            </div>
        </div>
    </div>
{/if}

{if !empty($novaposhta_mass_warnings)}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon np-mass-warnings">
                <div class="alert__content">
                    <div class="alert__title">{$btr->sviat__novaposhta_tracking__mass_warnings_title|escape}</div>
                    <ul class="np-mass-warnings__list">
                        {foreach $novaposhta_mass_warnings as $order_id => $warning_text}
                            <li><strong>№ {$order_id|escape}:</strong> {$warning_text|escape}</li>
                        {/foreach}
                    </ul>
                </div>
            </div>
        </div>
    </div>
{/if}
