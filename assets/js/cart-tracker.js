(function ($) {
    'use strict';

    if (typeof wanotifyCartConfig === 'undefined') {
        return;
    }

    var debounceTimer = null;
    var lastCapturedPhone = '';

    function sendCartBeacon() {
        var phone = $('#billing_phone').val() || '';
        var email = $('#billing_email').val() || '';
        var firstName = $('#billing_first_name').val() || '';
        var lastName = $('#billing_last_name').val() || '';

        phone = phone.replace(/[^\d+]/g, '');

        if (!phone || phone.length < 7) {
            return;
        }

        if (phone === lastCapturedPhone) {
            return;
        }

        lastCapturedPhone = phone;

        $.ajax({
            url: wanotifyCartConfig.ajax_url,
            type: 'POST',
            data: {
                action: 'wanotify_track_cart',
                nonce: wanotifyCartConfig.nonce,
                phone: phone,
                email: email,
                first_name: firstName,
                last_name: lastName
            },
            dataType: 'json'
        });
    }

    $(document).on('blur', '#billing_phone', function () {
        sendCartBeacon();
    });

    $(document).on('input change', '#billing_phone', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            sendCartBeacon();
        }, 1200);
    });

    // Also capture when updated_checkout event fires
    $(document.body).on('updated_checkout', function () {
        var phone = $('#billing_phone').val();
        if (phone && phone.length >= 7 && phone !== lastCapturedPhone) {
            sendCartBeacon();
        }
    });

})(jQuery);
