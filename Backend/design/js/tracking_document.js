/**
 * Управління відображенням детальної інформації про накладну Нової Пошти
 */
(function($) {
    'use strict';

    // Перемикання детальної інформації
    function t(key, fallback) {
        return (window.novaPoshtaT && window.novaPoshtaT[key]) || fallback;
    }

    function toggleDetails($button) {
        const $wrapper = $button.closest('.np-document-wrapper');
        const $details = $wrapper.find('.np-document-details');
        const $textSpan = $button.find('.np-toggle-text');
        const $icon = $button.find('.fn_icon_arrow');
        
        if (!$wrapper.length || !$details.length || !$textSpan.length || !$icon.length) {
            return;
        }

        const isHidden = $details.hasClass('np-document-details--hidden') || !$details.is(':visible');

        if (isHidden) {
            $details.removeClass('np-document-details--hidden').slideDown({
                duration: 200,
                complete: function() {
                    $details.css({ 'max-height': '', 'height': '', 'display': '' });
                }
            });
            $textSpan.text(t('hide', 'Приховати'));
            $icon.addClass('rotate_180');
        } else {
            $details.addClass('np-document-details--hidden').slideUp(200);
            $textSpan.text(t('show_more', 'Показати більше'));
            $icon.removeClass('rotate_180');
        }
    }

    // Оновлення tracking даних
    function updateTracking($button) {
        const orderId = $button.data('order-id');
        
        if (!orderId) {
            toastr.error(t('order_id_not_found', 'Order ID не знайдено'), t('error', 'Помилка'));
            return;
        }
        
        const updateUrl = (typeof window.novaPoshtaRoutes !== 'undefined' && window.novaPoshtaRoutes.updateTrackingDocument) 
            ? window.novaPoshtaRoutes.updateTrackingDocument 
            : '/backend/nova-poshta/ajax/updateTrackingDocument';
        
        $.ajax({
            type: 'POST',
            url: updateUrl,
            data: { order_id: orderId },
            dataType: 'json',
            success: function(response) {
                if (response && response.error) {
                    toastr.error(response.error, t('error', 'Помилка'));
                } else if (response && response.success) {
                    toastr.success(t('invoice_updated', 'Накладну оновлено'), t('success', 'Успіх'));
                    setTimeout(() => location.reload(), 1500);
                } else {
                    toastr.error(t('unknown_error', 'Невідома помилка'), t('error', 'Помилка'));
                }
            },
            error: function(xhr, status, errorThrown) {
                toastr.error((t('update_error', 'Помилка оновлення: %s').replace('%s', errorThrown)), t('error', 'Помилка'));
            }
        });
    }

    // Об'єкт для управління видаленням накладної
    const DocumentRemover = {
        /**
         * Отримує URL для видалення документа
         */
        getRemoveUrl: function(orderId) {
            return (typeof window.novaPoshtaRoutes !== 'undefined' && window.novaPoshtaRoutes.removeDocument) 
                ? window.novaPoshtaRoutes.removeDocument + '?order_id=' + orderId
                : '/backend/nova-poshta/ajax/removeDocument?order_id=' + orderId;
        },

        /**
         * Отримує повідомлення про помилку з відповіді сервера
         */
        extractErrorMessage: function(xhr) {
            // Спробуємо отримати повідомлення з JSON відповіді
            if (xhr.responseJSON && xhr.responseJSON.error) {
                return xhr.responseJSON.error;
            }
            
            // Спробуємо розпарсити responseText
            if (xhr.responseText) {
                try {
                    const errorResponse = JSON.parse(xhr.responseText);
                    if (errorResponse.error) {
                        return errorResponse.error;
                    }
                } catch (e) {
                    // Якщо не вдалося розпарсити JSON, продовжуємо
                }
            }
            
            // Стандартні повідомлення залежно від статусу
            if (xhr.status === 0) {
                return t('connection_error', 'Помилка з\'єднання з сервером. Перевірте підключення до інтернету.');
            } else if (xhr.status >= 500) {
                return t('server_error', 'Помилка сервера (код %s). Спробуйте пізніше.').replace('%s', xhr.status);
            } else if (xhr.status === 404) {
                return t('not_found', 'Сторінка не знайдена. Можливо, маршрут не налаштований.');
            }
            
            return t('remove_error', 'Помилка видалення накладної. Спробуйте ще раз.');
        },

        /**
         * Обробляє успішну відповідь від сервера
         */
        handleSuccess: function(response, statusCode) {
            const message = statusCode === '2' 
                ? t('removed_from_db', 'Накладну видалено з бази даних (ТТН вже була видалена в Новій Пошті)') 
                : t('removed_success', 'Накладну успішно видалено');
            toastr.success(message, t('success', 'Успіх'));
            setTimeout(() => location.reload(), 1500);
        },

        /**
         * Обробляє помилку від API
         */
        handleApiError: function(response, $button) {
            const errorMessage = (response && response.error) 
                ? response.error 
                : t('remove_api_error', 'Помилка видалення накладної через API Нової Пошти');
            
            toastr.error(errorMessage, t('error_api', 'Помилка API'));
            $button.prop('disabled', false).removeClass('disabled');
        },

        /**
         * Обробляє помилку мережі або сервера
         */
        handleNetworkError: function(xhr, status, errorThrown, $button) {
            const errorMessage = this.extractErrorMessage(xhr);
            toastr.error(errorMessage, t('error_connection', 'Помилка з\'єднання'));
            $button.prop('disabled', false).removeClass('disabled');
        },

        /**
         * Видаляє документ через API
         */
        removeDocument: function(orderId, statusCode, $button) {
            if (!orderId) {
                toastr.error('Order ID не знайдено', 'Помилка');
                return;
            }

            const removeUrl = this.getRemoveUrl(orderId);
            
            $.ajax({
                url: removeUrl,
                dataType: 'json',
                timeout: 30000, // 30 секунд таймаут
                success: (response) => {
                    if (response && response.success) {
                        this.handleSuccess(response, statusCode);
                    } else {
                        this.handleApiError(response, $button);
                    }
                },
                error: (xhr, status, errorThrown) => {
                    this.handleNetworkError(xhr, status, errorThrown, $button);
                }
            });
        },

        /**
         * Ініціалізує обробник видалення документа
         */
        init: function($button) {
            const orderId = $button.data('order-id');
            const statusCode = $button.data('status-code') || '';
            
            if (!orderId) {
                toastr.error(t('order_id_not_found', 'Order ID не знайдено'), t('error', 'Помилка'));
                return;
            }

            // Видаляємо попередні обробники та додаємо новий
            $(document).off('click.removeDocument', '.fn_submit_delete')
                .on('click.removeDocument', '.fn_submit_delete', () => {
                    $('#fn_action_modal').modal('hide');
                    $button.prop('disabled', true).addClass('disabled');
                    this.removeDocument(orderId, statusCode, $button);
                });
        }
    };

    // Експортуємо для сумісності зі старим кодом (onclick в шаблоні)
    window.removeDocumentAction = function($button) {
        DocumentRemover.init($button);
    };

    // Обробники подій (працюють з динамічно доданим контентом)
    $(document)
        .on('click', '.np-document-footer__toggle', function() {
            toggleDetails($(this));
        })
        .on('click', '.np-document-wrapper .fn_update_tracking', function(e) {
            e.preventDefault();
            updateTracking($(this));
        })
        .on('click', '.np-document-wrapper .fn_remove', function(e) {
            e.preventDefault();
            DocumentRemover.init($(this));
        });
})(jQuery);
