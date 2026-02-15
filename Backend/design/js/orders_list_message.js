/**
 * Сторінка списку замовлень: показувати повідомлення "Виконано успішно" зеленим
 */
(function($) {
    'use strict';

    if (window.location.href.indexOf('controller=OrdersAdmin') === -1) {
        return;
    }

    const $errorAlert = $('.alert--error');
    if ($errorAlert.length > 0) {
        const messageText = $errorAlert.find('.alert__title').text().trim();
        if (messageText.indexOf('Виконано успішно') === 0) {
            $errorAlert.removeClass('alert--error').addClass('alert--success');
            $errorAlert.find('.alert--icon').removeClass('alert--error').addClass('alert--success');
        }
    }
})(jQuery);
