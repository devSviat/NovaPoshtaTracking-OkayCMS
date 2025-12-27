{if $all_np_status}
    <div class="col-md-3 col-lg-3 col-sm-12">
        <select name="np_status" class="selectpicker form-control" onchange="location = this.value;">
            <option value="{url controller=OrdersAdmin np_status=null status=$status_id keyword=$keyword id=null page=null label=$label_id from_date=$from_date to_date=$to_date}" {if !$np_status_id}selected{/if}>Всі статуси доставки</option>
            {foreach $all_np_status_filters as $np_status}
                <option value="{url controller=OrdersAdmin np_status=$np_status->id status=$status_id keyword=$keyword id=null page=null label=$label_id from_date=$from_date to_date=$to_date}" {if $np_status_id == $np_status->id}selected=""{/if}>{$np_status->name|escape}</option>
            {/foreach}
        </select>
    </div>
{/if}