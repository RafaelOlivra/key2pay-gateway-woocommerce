jQuery(document).ready(function ($) {
    "use strict";

    const __ = wp.i18n.__;

    /** ### Handle Thai QR Debit fields ## */

    if ($("body").hasClass("woocommerce-checkout")) {
        // Automatically fill Thai Debit fields if they are empty
        function autoFillThaiDebitFields() {
            const PayerAccountNameEl = $('input[name="payer_account_name"]');
            const PayerAccountNoEl = $('input[name="payer_account_no"]');
            const PayerAccountBankCodeEl = $('input[name="payer_bank_code"]');

            // Set the values from localStorage if they are empty initially
            setInputFromData(
                PayerAccountNameEl,
                "key2pay_thai_debit_account_name"
            );
            setInputFromData(PayerAccountNoEl, "key2pay_thai_debit_account_no");
            setInputFromData(
                PayerAccountBankCodeEl,
                "key2pay_thai_debit_bank_code"
            );
        }

        // If we are on the block checkout page, we ignore the auto-fill
        // as they will be handled by the block itself.
        if ($('[data-block-name="woocommerce/checkout"]').length === 0) {
            autoFillThaiDebitFields();
        }

        // Thai QR Debit requires the order amount to be 100 or more
        const orderTotal = getOrderTotal();
        console.log(`Key2Pay Thai QR Debit: Order total is ${orderTotal}`);
        if (orderTotal && parseFloat(orderTotal) < 10000) {
            // Disable the Thai QR Debit payment method if the order total is less than 100
            $(".wc_payment_method.payment_method_key2pay_thai_debit")
                .addClass("disabled")
                .find("input[type='radio']")
                .prop("disabled", true);

            $("label[for='payment_method_key2pay_thai_debit']").append(
                `<span class="key2pay-error">${__(
                    "Thai QR Debit is only available for orders of 100 or more.",
                    "key2pay"
                )}</span>`
            );
        }
    }

    /** ### Helpers ## */

    /**
     * Get data from localStorage by key.
     *
     * @param {string} key - The key to retrieve data from localStorage.
     * @returns {string|null} The data stored in localStorage for the given key, or null if not found.
     */
    function getData(key) {
        return localStorage.getItem(key);
    }

    /**
     * Set data in localStorage by key.
     *
     * @param {string} key - The key to store data under.
     * @param {string} value - The value to store in localStorage.
     */
    function setData(key, value) {
        localStorage.setItem(key, value);
    }

    /**
     * Get the order total from the WooCommerce checkout page.
     * [!] This is not a reliable way to get the order total, as it may not be present in all themes.
     * It is better to use the WooCommerce API to get the order total.
     * But should be ok for simple use cases.
     * @returns {string|null} The order total as a string, or null if not found.
     */
    function getOrderTotal() {
        const orderTotalEl = $(
            ".woocommerce-Price-amount bdi, .woocommerce-Price-amount .amount"
        ).eq(0);
        if (orderTotalEl.length) {
            return parseFloat(orderTotalEl.text().replace(/[^0-9.-]+/g, ""));
        }
        return null;
    }

    /**
     * Set the value of an input element from localStorage if it is empty.
     *
     * @param {jQuery} el - The jQuery object representing the input element.
     * @param {string} key - The key to retrieve data from localStorage.
     */
    function setInputFromData(el, key) {
        // Listen for changes to the input element
        // Update localStorage when the input value changes
        el.on("change", function () {
            const value = el.val();
            if (value) {
                setData(key, value);
            }
        });

        // Exit if the element does not exist or already has a value
        if (!el.length || el.val()) {
            return;
        }

        const data = getData(key);
        if (data) {
            el.val(data);
        }
    }
});
