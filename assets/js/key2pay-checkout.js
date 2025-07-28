jQuery(document).ready(function($) {
    'use strict';

    // Add loading state when Key2Pay payment method is selected
    $('input[name="payment_method"]').on('change', function() {
        if ($(this).val() === 'key2pay_redirect') {
            // Add any custom behavior for Key2Pay selection
        }
    });

    // Add custom validation if needed
    $('form.checkout').on('checkout_place_order_key2pay_redirect', function() {
        // You can add custom validation here if needed
        return true;
    });
});