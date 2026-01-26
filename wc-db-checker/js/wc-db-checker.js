jQuery(document).ready(function ($) {
    $('#check-products').on('click', function (e) {
        e.preventDefault();
        var $result = $('#products-result');
        $result.html('Checking...');

        $.ajax({
            url: wc_product_checker.ajax_url,
            type: 'POST',
            data: {
                action: 'check_products',
                nonce: wc_product_checker.nonce
            },
            success: function (response) {
                if (response.success) {
                    $result.html(response.data);
                } else {
                    $result.html('Error: ' + response.data);
                }
            },
            error: function () {
                $result.html('AJAX error occurred.');
            }
        });
    });

    $('#repair-products').on('click', function (e) {
        e.preventDefault();
        var $result = $('#repair-result');
        $result.html('Repairing... This may take a few minutes.');

        $.ajax({
            url: wc_product_checker.ajax_url,
            type: 'POST',
            data: {
                action: 'repair_products',
                nonce: wc_product_checker.nonce
            },
            success: function (response) {
                if (response.success) {
                    $result.html(response.data);
                } else {
                    $result.html('Error: ' + response.data);
                }
            },
            error: function () {
                $result.html('AJAX error occurred.');
            }
        });
    });

    $('#enable-auto-repair').on('click', function (e) {
        e.preventDefault();
        if ($(this).prop('disabled')) return;
        var $result = $('#auto-repair-result');
        $result.html('Enabling...');

        $.ajax({
            url: wc_product_checker.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_auto_repair',
                enable: true,
                nonce: wc_product_checker.nonce
            },
            success: function (response) {
                if (response.success) {
                    $result.html(response.data);
                    $('#enable-auto-repair').prop('disabled', true).removeClass('button-primary').addClass('button-disabled');
                    $('#disable-auto-repair').prop('disabled', false).addClass('button-primary').removeClass('button-disabled');
                } else {
                    $result.html('Error: ' + response.data);
                }
            },
            error: function () {
                $result.html('AJAX error occurred.');
            }
        });
    });

    $('#disable-auto-repair').on('click', function (e) {
        e.preventDefault();
        if ($(this).prop('disabled')) return;
        var $result = $('#auto-repair-result');
        $result.html('Disabling...');

        $.ajax({
            url: wc_product_checker.ajax_url,
            type: 'POST',
            data: {
                action: 'toggle_auto_repair',
                enable: false,
                nonce: wc_product_checker.nonce
            },
            success: function (response) {
                if (response.success) {
                    $result.html(response.data);
                    $('#disable-auto-repair').prop('disabled', true).removeClass('button-primary').addClass('button-disabled');
                    $('#enable-auto-repair').prop('disabled', false).addClass('button-primary').removeClass('button-disabled');
                } else {
                    $result.html('Error: ' + response.data);
                }
            },
            error: function () {
                $result.html('AJAX error occurred.');
            }
        });
    });
});
