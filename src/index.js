import { __ } from "@wordpress/i18n";
import { useState, useEffect } from "@wordpress/element";
import { select } from "@wordpress/data";

const { registerPaymentMethod } = window.wc.wcBlocksRegistry;

/**
 * @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/internal-developers/block-client-apis/checkout/checkout-api.md
 */

/**
 * ### Thai QR Setup ###
 */

const ThaiDebitFieldsContent = (props) => {
    const { eventRegistration, emitResponse, components } = props;
    const { PaymentMethodLabel } = components;
    const { onPaymentSetup } = eventRegistration;
    const { getPaymentMethodData } = window.wc.wcSettings;
    const iconUrl = window.key2pay.assetsUrl + "images/key2pay.png";
    const store = select(window.wc.wcBlocksData.CART_STORE_KEY);

    console.log(props);

    // Get payment labels
    const { title, description } = getPaymentMethodData(
        "key2pay_thai_debit"
    ) || {
        title: __("Key2Pay Thai QR (QR Payment)", "key2pay"),
        description: __(
            "Pay using Thai QR payments via Key2Pay.",
            "key2pay"
        ),
    };

    // State to store input values
    const [accountNo, setAccountNo] = useState("");
    const [accountName, setAccountName] = useState("");
    const [bankCode, setBankCode] = useState("");

    // Load values from local storage on component mount
    useEffect(() => {
        const storedAccountNo = localStorage.getItem(
            "key2pay_thai_debit_account_no"
        );
        const storedAccountName = localStorage.getItem(
            "key2pay_thai_debit_account_name"
        );
        const storedBankCode = localStorage.getItem(
            "key2pay_thai_debit_bank_code"
        );

        if (storedAccountNo) {
            setAccountNo(storedAccountNo);
        }
        if (storedAccountName) {
            setAccountName(storedAccountName);
        }
        if (storedBankCode) {
            setBankCode(storedBankCode);
        }
    }, []);

    // Save values to local storage whenever they change
    useEffect(() => {
        localStorage.setItem("key2pay_thai_debit_account_no", accountNo);
        localStorage.setItem("key2pay_thai_debit_account_name", accountName);
        localStorage.setItem("key2pay_thai_debit_bank_code", bankCode);
    }, [accountNo, accountName, bankCode]);

    useEffect(() => {
        const unsubscribe = onPaymentSetup(async () => {
            const isValid = accountNo && accountName && bankCode;
            const cartTotals = store.getCartTotals();
            const hasMinimumAmount = cartTotals.total_price >= 100;

            // Check if the total amount is sufficient for Thai QR
            if (!hasMinimumAmount) {
                return {
                    type: emitResponse.responseTypes.ERROR,
                    message: __(
                        "Thai QR is only available for orders of 100 THB or more.",
                        "key2pay"
                    ),
                };
            }

            // Check if the required fields are filled and the total amount is sufficient
            if (isValid) {
                return {
                    type: emitResponse.responseTypes.SUCCESS,
                    meta: {
                        paymentMethodData: {
                            payer_account_no: accountNo,
                            payer_account_name: accountName,
                            payer_bank_code: bankCode,
                        },
                    },
                };
            }

            return {
                type: emitResponse.responseTypes.ERROR,
                message: __(
                    "Please fill in all required fields for Thai QR.",
                    "key2pay"
                ),
            };
        });

        // Unsubscribes when this component is unmounted.
        return () => {
            unsubscribe();
        };
    }, [
        emitResponse.responseTypes.ERROR,
        emitResponse.responseTypes.SUCCESS,
        onPaymentSetup,
        accountNo,
        accountName,
        bankCode,
    ]);

    return (
        <div className='k2p-thai-debit'>
            <PaymentMethodLabel
                text={description}
                icon={<img src={iconUrl} alt={title} width={35} />}
            />
            <p>
                {__("You will be redirected to the payment page.", "key2pay") +
                    " " +
                    __("Please fill in the details below:", "key2pay")}
            </p>
            <div className='k2p-grid-3'>
                <div className='k2p-field-group'>
                    <p>
                        <label>
                            {__("Bank Code", "key2pay")}
                            <input
                                type='text'
                                placeholder={__("e.g. KBANK", "key2pay")}
                                name='payer_bank_code'
                                value={bankCode}
                                onChange={(e) => setBankCode(e.target.value)} // Update state on change
                                required
                            />
                        </label>
                    </p>
                </div>
                <div className='k2p-field-group'>
                    <p>
                        <label>
                            {__("Bank Account Number", "key2pay")}
                            <input
                                type='text'
                                placeholder={__(
                                    "Enter your debit account number",
                                    "key2pay"
                                )}
                                name='payer_account_no'
                                value={accountNo}
                                onChange={(e) => setAccountNo(e.target.value)} // Update state on change
                                required
                            />
                        </label>
                    </p>
                </div>
                <div className='k2p-field-group'>
                    <p>
                        <label>
                            {__("Bank Account Name", "key2pay")}
                            <input
                                type='text'
                                placeholder={__(
                                    "Name on your debit account",
                                    "key2pay"
                                )}
                                name='payer_account_name'
                                value={accountName}
                                onChange={(e) => setAccountName(e.target.value)} // Update state on change
                                required
                            />
                        </label>
                    </p>
                </div>
            </div>
        </div>
    );
};

registerPaymentMethod({
    name: "key2pay_thai_debit",
    paymentMethodId: "key2pay_thai_debit",
    label: __("Key2Pay Thai QR (QR Payment)", "key2pay"),
    content: <ThaiDebitFieldsContent />,
    edit: <ThaiDebitFieldsContent />,
    canMakePayment: () => true,
    ariaLabel: __("Key2Pay Thai QR (QR Payment)", "key2pay"),
    supports: {
        features: ["products", "refunds"],
    },
});

/**
 * ### Credit Card Setup ###
 */

const ThaiCreditFieldsContent = (props) => {
    const { eventRegistration, emitResponse, components } = props;
    const { PaymentMethodLabel } = components;
    const { onPaymentSetup } = eventRegistration;
    const { getPaymentMethodData } = window.wc.wcSettings;
    const iconUrl = window.key2pay.assetsUrl + "images/key2pay.png";
    const store = select(window.wc.wcBlocksData.CART_STORE_KEY);

    // Get payment labels
    const { title, description } = getPaymentMethodData("key2pay_credit") || {
        title: __("Key2Pay Credit Card", "key2pay"),
        description: __("Pay using Credit Card via Key2Pay.", "key2pay"),
    };

    return (
        <div className='k2p-credit-card'>
            <PaymentMethodLabel
                text={description}
                icon={<img src={iconUrl} alt={title} width={35} />}
            />
            <p>
                {__("You will be redirected to the payment page.", "key2pay")}
            </p>
        </div>
    );
};

registerPaymentMethod({
    name: "key2pay_credit",
    paymentMethodId: "key2pay_credit",
    label: __("Key2Pay Credit Card", "key2pay"),
    content: <ThaiCreditFieldsContent />,
    edit: <ThaiCreditFieldsContent />,
    canMakePayment: () => true,
    ariaLabel: __("Key2Pay Credit Card", "key2pay"),
    supports: {
        features: ["products", "refunds"],
    },
});
