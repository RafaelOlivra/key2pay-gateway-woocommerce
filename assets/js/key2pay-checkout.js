jQuery(document).ready(function ($) {
    "use strict";

    /** ### Handle Thai QR Debit fields ## */

    if ($("body").hasClass("woocommerce-checkout")) {
        function autoFillThaiDebitFields() {
            const debitAccountNameEl = $('input[name="payer_account_name"]');
            const debitAccountNoEl = $('input[name="payer_account_no"]');
            const debitAccountBankCodeEl = $('input[name="payer_bank_code"]');

            // Set the values from localStorage if they are empty initially
            setInputFromData(
                debitAccountNameEl,
                "key2pay_thai_debit_account_name"
            );
            setInputFromData(debitAccountNoEl, "key2pay_thai_debit_account_no");
            setInputFromData(
                debitAccountBankCodeEl,
                "key2pay_thai_debit_bank_code"
            );
        }

        // If we are on the block checkout page, we ignore the auto-fill
        // as they will be handled by the block itself.
        if ($('[data-block-name="woocommerce/checkout"]').length === 0) {
            autoFillThaiDebitFields();
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
