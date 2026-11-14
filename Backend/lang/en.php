<?php

// Orders list — action
$lang['sviat__novaposhta_tracking__create_ttn'] = 'Create Nova Poshta waybill';

// document_form.tpl — shipment parameters
$lang['sviat__novaposhta_tracking__params_shipment'] = 'Shipment parameters';
$lang['sviat__novaposhta_tracking__delivery_type'] = 'Delivery type:';
$lang['sviat__novaposhta_tracking__recipient_type'] = 'Recipient:';
$lang['sviat__novaposhta_tracking__recipient_type_organization'] = 'Organization (company)';
$lang['sviat__novaposhta_tracking__recipient_type_private'] = 'Private person';
$lang['sviat__novaposhta_tracking__warehouse'] = 'Branch';
$lang['sviat__novaposhta_tracking__locker'] = 'Parcel locker';
$lang['sviat__novaposhta_tracking__locker_tooltip'] = 'Packaging ID for each seat of the shipment (set in the module settings)';
$lang['sviat__novaposhta_tracking__address_delivery'] = 'Address delivery';
$lang['sviat__novaposhta_tracking__door_delivery'] = 'Delivery to the door:';
$lang['sviat__novaposhta_tracking__door_delivery_tooltip'] = "If you order the service*, the courier brings your parcel to the front door**.\n\nThe service costs 200 UAH (per each 100 kg).\n*Free for shipments up to 30 kg.\n**This means the door of an apartment or office, or the front door of a private house.";
$lang['sviat__novaposhta_tracking__floor'] = 'Floor:';
$lang['sviat__novaposhta_tracking__floor_placeholder'] = 'For example: 3';
$lang['sviat__novaposhta_tracking__cargo_params'] = 'Cargo parameters:';
$lang['sviat__novaposhta_tracking__weight_kg'] = 'Weight (kg):';
$lang['sviat__novaposhta_tracking__volume_m3'] = 'Volume (m³):';
$lang['sviat__novaposhta_tracking__cargo_params_locker'] = 'Cargo parameters (for a parcel locker):';
$lang['sviat__novaposhta_tracking__max_20_kg'] = 'Max 20 kg';
$lang['sviat__novaposhta_tracking__length'] = 'Length:';
$lang['sviat__novaposhta_tracking__max_60_cm'] = 'Max 60 cm';
$lang['sviat__novaposhta_tracking__width'] = 'Width:';
$lang['sviat__novaposhta_tracking__max_40_cm'] = 'Max 40 cm';
$lang['sviat__novaposhta_tracking__height'] = 'Height:';
$lang['sviat__novaposhta_tracking__max_30_cm'] = 'Max 30 cm';
$lang['sviat__novaposhta_tracking__announced_value'] = 'Declared value:';
$lang['sviat__novaposhta_tracking__announced_value_tooltip'] = 'The amount the shipment is insured for. Usually the full order total, even if part of it was paid in advance.';
$lang['sviat__novaposhta_tracking__amount_due'] = 'Amount to collect:';
$lang['sviat__novaposhta_tracking__amount_due_tooltip'] = 'How much to collect from the recipient at pickup: the payment control or money transfer amount. The advance payment is already deducted.';
$lang['sviat__novaposhta_tracking__control_payment'] = 'Payment control';
$lang['sviat__novaposhta_tracking__control_payment_tooltip'] = 'The recipient\'s money goes straight to the sender\'s account. Similar to Money transfer. Cannot be used together with Money transfer.';
$lang['sviat__novaposhta_tracking__additional_info'] = 'Additional shipment information:';
$lang['sviat__novaposhta_tracking__additional_info_tooltip'] = 'Maximum length is 100 characters';
$lang['sviat__novaposhta_tracking__additional_info_placeholder'] = 'Smartphone';
$lang['sviat__novaposhta_tracking__address_comment'] = 'Address note:';
$lang['sviat__novaposhta_tracking__address_comment_tooltip'] = 'Extra information about the delivery address (maximum length is 50 characters)';
$lang['sviat__novaposhta_tracking__address_comment_placeholder'] = 'For example: 3rd floor, near the entrance';
$lang['sviat__novaposhta_tracking__cargo_type'] = 'Cargo type:';
$lang['sviat__novaposhta_tracking__cargo'] = 'Cargo';
$lang['sviat__novaposhta_tracking__documents'] = 'Documents';
$lang['sviat__novaposhta_tracking__payer'] = 'Delivery paid by:';
$lang['sviat__novaposhta_tracking__sender'] = 'Sender';
$lang['sviat__novaposhta_tracking__recipient'] = 'Recipient';
$lang['sviat__novaposhta_tracking__back_payer'] = 'Cash on delivery paid by:';
$lang['sviat__novaposhta_tracking__payment_form'] = 'Payment form:';
$lang['sviat__novaposhta_tracking__payment_cash'] = 'Cash';
$lang['sviat__novaposhta_tracking__payment_cash_tooltip'] = 'Cash payment: in cash or by bank card on the website or at a terminal.';
$lang['sviat__novaposhta_tracking__payment_noncash'] = 'Non-cash';
$lang['sviat__novaposhta_tracking__payment_noncash_tooltip'] = 'Non-cash payment for organizations with an NP contract. Paid to the bank account under the certificate of completed work (not by bank card).';
$lang['sviat__novaposhta_tracking__city'] = 'City';
$lang['sviat__novaposhta_tracking__select_city'] = 'Select a city';
$lang['sviat__novaposhta_tracking__btn_create_invoice'] = 'Create waybill';
$lang['sviat__novaposhta_tracking__creating'] = 'Creating...';
$lang['sviat__novaposhta_tracking__order_paid_control_disabled'] = 'The order is paid, payment control is unavailable';
$lang['sviat__novaposhta_tracking__attention'] = 'Attention';
$lang['sviat__novaposhta_tracking__volumetric_weight_limit'] = 'Volumetric weight (%s kg) exceeds the 30 kg limit. Volume is capped at 0.12 m³';
$lang['sviat__novaposhta_tracking__invoice_created_success'] = 'Waybill %s created';
$lang['sviat__novaposhta_tracking__success'] = 'Success';
$lang['sviat__novaposhta_tracking__unknown_error'] = 'Unknown error';
$lang['sviat__novaposhta_tracking__error'] = 'Error';
$lang['sviat__novaposhta_tracking__create_invoice_error'] = 'Error creating the waybill: %s';

// nova_poshta_admin.tpl
$lang['sviat__novaposhta_tracking__module_description'] = 'Module for creating Nova Poshta waybills';
$lang['sviat__novaposhta_tracking__module_important'] = 'Important: The module requires OkayCMS/NovaposhtaCost to be installed and extends its features.';
$lang['sviat__novaposhta_tracking__main_settings'] = 'Main settings';
$lang['sviat__novaposhta_tracking__api_key'] = 'Nova Poshta API key';
$lang['sviat__novaposhta_tracking__sender_phone'] = 'Sender phone';
$lang['sviat__novaposhta_tracking__sender_city'] = 'Sender city';
$lang['sviat__novaposhta_tracking__sender_warehouse'] = 'Sender branch';
$lang['sviat__novaposhta_tracking__select_warehouse'] = 'Select a branch';
$lang['sviat__novaposhta_tracking__time_today'] = 'Same-day dispatch cutoff';
$lang['sviat__novaposhta_tracking__time_today_tooltip'] = 'Orders accepted before this time are shipped the same day. After it, they are shipped on the next business day.';
$lang['sviat__novaposhta_tracking__default_shipment_settings'] = 'Default shipment settings';
$lang['sviat__novaposhta_tracking__payment'] = 'Payment';
$lang['sviat__novaposhta_tracking__parcel_payer'] = 'Parcel paid by';
$lang['sviat__novaposhta_tracking__back_payer_short'] = 'Cash on delivery paid by';
$lang['sviat__novaposhta_tracking__payment_type'] = 'Payment type';
$lang['sviat__novaposhta_tracking__payment_cash_short'] = 'Cash';
$lang['sviat__novaposhta_tracking__payment_cash_short_tooltip'] = 'In cash or by bank card on the website or at a terminal.';
$lang['sviat__novaposhta_tracking__payment_noncash_short'] = 'Non-cash';
$lang['sviat__novaposhta_tracking__payment_noncash_short_tooltip'] = 'Non-cash payment for the sender or the recipient is available only with a signed contract with Nova Poshta.';
$lang['sviat__novaposhta_tracking__enable_control_payment'] = 'Enable payment control';
$lang['sviat__novaposhta_tracking__enable_control_payment_tooltip'] = 'The service controls cash payment for the shipment received by the Recipient. The money is transferred to the Sender\'s current account (the next business day) under the contract with Nova Poshta.';
$lang['sviat__novaposhta_tracking__locker_dimensions'] = 'Parcel locker dimensions';
$lang['sviat__novaposhta_tracking__volume_place'] = 'Seat volume';
$lang['sviat__novaposhta_tracking__actual_weight'] = 'Actual weight';
$lang['sviat__novaposhta_tracking__actual_weight_locker_tooltip'] = 'Maximum: 20 kg (for a parcel locker)';
$lang['sviat__novaposhta_tracking__length_cm'] = 'Length';
$lang['sviat__novaposhta_tracking__max_35_cm'] = 'Maximum: 35 cm';
$lang['sviat__novaposhta_tracking__width_cm'] = 'Width';
$lang['sviat__novaposhta_tracking__max_37_cm'] = 'Maximum: 37 cm';
$lang['sviat__novaposhta_tracking__height_cm'] = 'Height';
$lang['sviat__novaposhta_tracking__max_61_cm'] = 'Maximum: 61 cm';
$lang['sviat__novaposhta_tracking__warehouse_dimensions'] = 'Branch dimensions';
$lang['sviat__novaposhta_tracking__volume'] = 'Volume';
$lang['sviat__novaposhta_tracking__volume_warehouse_tooltip'] = "Maximum: 0.12 m³ (volumetric weight up to 30 kg)";
$lang['sviat__novaposhta_tracking__actual_weight_warehouse_tooltip'] = 'Maximum: 30 kg (for a branch)';

// nova_poshta_status.tpl
$lang['sviat__novaposhta_tracking__hint_copy'] = 'Click to copy';
$lang['sviat__novaposhta_tracking__hint_copied'] = '✔ Copied';
$lang['sviat__novaposhta_tracking__status'] = 'Status:';

// novaposhta_mass_result.tpl
$lang['sviat__novaposhta_tracking__mass_errors_title'] = 'Order errors (Nova Poshta):';

// np_status_filters.tpl
$lang['sviat__novaposhta_tracking__all_delivery_statuses'] = 'All delivery statuses';

// tracking_document.tpl
$lang['sviat__novaposhta_tracking__created'] = 'Created:';
$lang['sviat__novaposhta_tracking__cargo_description'] = 'Shipment description';
$lang['sviat__novaposhta_tracking__departure_date'] = 'Dispatch date:';
$lang['sviat__novaposhta_tracking__scheduled_delivery'] = 'Planned delivery time:';
$lang['sviat__novaposhta_tracking__recipient_phone'] = 'Recipient phone';
$lang['sviat__novaposhta_tracking__received_at'] = 'Received by';
$lang['sviat__novaposhta_tracking__recipient_address'] = 'Recipient address';
$lang['sviat__novaposhta_tracking__extra_services'] = 'Extra services';
$lang['sviat__novaposhta_tracking__redelivery'] = 'Cash on delivery';
$lang['sviat__novaposhta_tracking__redelivery_payer'] = 'Commission paid by:';
$lang['sviat__novaposhta_tracking__redelivery_number'] = 'Cash on delivery waybill number:';
$lang['sviat__novaposhta_tracking__control_payment_label'] = 'Payment control:';
$lang['sviat__novaposhta_tracking__return_label'] = 'Return';
$lang['sviat__novaposhta_tracking__return_number'] = 'Return number:';
$lang['sviat__novaposhta_tracking__redirect_label'] = 'Redirect';
$lang['sviat__novaposhta_tracking__redirect_number'] = 'Redirect number:';
$lang['sviat__novaposhta_tracking__delivery_cost'] = 'Delivery cost';
$lang['sviat__novaposhta_tracking__payer_delivery'] = 'Delivery paid by';
$lang['sviat__novaposhta_tracking__payment_form_delivery'] = 'Delivery payment form';
$lang['sviat__novaposhta_tracking__details_title'] = 'Details';
$lang['sviat__novaposhta_tracking__type'] = 'Type';
$lang['sviat__novaposhta_tracking__weight'] = 'Weight';
$lang['sviat__novaposhta_tracking__volumetric_weight'] = 'Volumetric weight';
$lang['sviat__novaposhta_tracking__seats'] = 'Seats';
$lang['sviat__novaposhta_tracking__internal_number'] = 'Internal shipment number';
$lang['sviat__novaposhta_tracking__factual_weight'] = 'Actual weight from the waybill';
$lang['sviat__novaposhta_tracking__delivery_method'] = 'Delivery type';
$lang['sviat__novaposhta_tracking__show_more'] = 'Show more';
$lang['sviat__novaposhta_tracking__hide'] = 'Hide';
$lang['sviat__novaposhta_tracking__updated'] = 'Updated:';
$lang['sviat__novaposhta_tracking__btn_update_tracking'] = 'Update tracking data';
$lang['sviat__novaposhta_tracking__btn_remove_invoice'] = 'Delete the waybill';
$lang['sviat__novaposhta_tracking__btn_detach_invoice'] = 'Detach the waybill';

// tracking_document.js
$lang['sviat__novaposhta_tracking__order_id_not_found'] = 'Order ID not found';
$lang['sviat__novaposhta_tracking__invoice_updated'] = 'Waybill updated';
$lang['sviat__novaposhta_tracking__connection_error'] = 'Could not connect to the server. Check your internet connection.';
$lang['sviat__novaposhta_tracking__server_error'] = 'Server error (code %s). Try again later.';
$lang['sviat__novaposhta_tracking__not_found'] = 'Page not found. The route may not be configured.';
$lang['sviat__novaposhta_tracking__remove_error'] = 'Error deleting the waybill. Try again.';
$lang['sviat__novaposhta_tracking__removed_from_db'] = 'The waybill was removed from the database (it had already been deleted at Nova Poshta)';
$lang['sviat__novaposhta_tracking__removed_success'] = 'Waybill deleted';
$lang['sviat__novaposhta_tracking__detached_success'] = 'The waybill was detached from the order';
$lang['sviat__novaposhta_tracking__remove_api_error'] = 'Error deleting the waybill through the Nova Poshta API';
$lang['sviat__novaposhta_tracking__error_api'] = 'API error';
$lang['sviat__novaposhta_tracking__error_connection'] = 'Connection error';
$lang['sviat__novaposhta_tracking__update_error'] = 'Update error: %s';

// orders_list_message.js — text compared against the success message
$lang['sviat__novaposhta_tracking__success_done'] = 'Completed successfully';

// Bulk waybill creation — message with errors
$lang['sviat__novaposhta_tracking__mass_done_with_errors'] = 'Processed %s, succeeded %s, failed %s';

// document_form.tpl — attaching a waybill created by hand
$lang['sviat__novaposhta_tracking__attach_hint'] = 'Already created the waybill by hand? Enter its number.';
$lang['sviat__novaposhta_tracking__attach_placeholder'] = 'Waybill number, 14 digits';
$lang['sviat__novaposhta_tracking__btn_attach_invoice'] = 'Attach a waybill';
$lang['sviat__novaposhta_tracking__attaching'] = 'Attaching…';
$lang['sviat__novaposhta_tracking__attach_success'] = 'Waybill %s attached';
$lang['sviat__novaposhta_tracking__attach_already'] = 'The order already has waybill %s. Detach it first.';
$lang['sviat__novaposhta_tracking__attach_not_found'] = 'Nova Poshta does not know this waybill. Check the number.';
$lang['sviat__novaposhta_tracking__attach_elsewhere'] = 'This waybill is already attached to order #%s.';
$lang['sviat__novaposhta_tracking__attach_invalid'] = 'A waybill number is exactly 14 digits.';
$lang['sviat__novaposhta_tracking__attach_confirm'] = 'Attach';

$lang['sviat__novaposhta_tracking__issues_title'] = 'Waybill not created — fix the marked fields';
$lang['sviat__novaposhta_tracking__issues_toast'] = 'Could not create the waybill. The reason is shown above the create button.';
$lang['sviat__novaposhta_tracking__issues_unrecognized'] = 'Could not read the Nova Poshta response';
$lang['sviat__novaposhta_tracking__issues_no_confirmation'] = 'No confirmation that the waybill was created';
$lang['sviat__novaposhta_tracking__issues_connection'] = 'The connection to Nova Poshta was interrupted or the server returned an error. Before clicking again, check whether the waybill already exists in the NP account.';
$lang['sviat__novaposhta_tracking__issues_jump'] = 'Show what to fix';
$lang['sviat__novaposhta_tracking__issues_open_ttn'] = 'Open the waybill in the Nova Poshta account';
$lang['sviat__novaposhta_tracking__created_with_warnings'] = 'Waybill %s created, but check it before shipping';
$lang['sviat__novaposhta_tracking__delivery_mode_missing'] = 'The delivery type is not set. Check the address, branch or parcel locker in the order and save the changes.';
$lang['sviat__novaposhta_tracking__mass_warnings_title'] = 'These waybills were created, but check the delivery point in the NP account:';
