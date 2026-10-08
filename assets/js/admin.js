jQuery(document).ready(function ($) {
    var $testBtn = $('#wanotify-test-btn');
    var $resultBox = $('#wanotify-test-result');

    $testBtn.on('click', function (e) {
        e.preventDefault();

        $resultBox.removeClass('is-success is-error')
            .addClass('is-loading')
            .text(wanotifyAdmin.testing)
            .show();

        $testBtn.prop('disabled', true).addClass('updating-message');

        $.ajax({
            url: wanotifyAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'wanotify_test_connection',
                nonce: wanotifyAdmin.nonce
            },
            success: function (res) {
                $testBtn.prop('disabled', false).removeClass('updating-message');
                if (res && res.success) {
                    $resultBox.removeClass('is-loading is-error')
                        .addClass('is-success')
                        .html('<strong>✅ Success:</strong> ' + res.data.message);
                } else {
                    var msg = (res && res.data && res.data.message) ? res.data.message : 'Connection failed. Please check credentials.';
                    $resultBox.removeClass('is-loading is-success')
                        .addClass('is-error')
                        .html('<strong>❌ Error:</strong> ' + msg);
                }
            },
            error: function (xhr) {
                $testBtn.prop('disabled', false).removeClass('updating-message');
                $resultBox.removeClass('is-loading is-success')
                    .addClass('is-error')
                    .html('<strong>❌ Server Error:</strong> Failed to communicate with WordPress AJAX handler.');
            }
        });
    });
});
