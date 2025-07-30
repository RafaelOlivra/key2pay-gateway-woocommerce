const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
import { __ } from "@wordpress/i18n";
import { useState } from "@wordpress/element";
import { useEffect } from "@wordpress/element";

/**
 * @see https://github.com/woocommerce/woocommerce/blob/trunk/plugins/woocommerce/client/blocks/docs/internal-developers/block-client-apis/checkout/checkout-api.md
 */

/**
 * ### Thai QR Debit Setup ###
 */

const ThaiDebitFieldsContent = (props) => {
    const { eventRegistration, emitResponse, components } = props;
    const { PaymentMethodLabel } = components;
    const { onPaymentSetup } = eventRegistration;

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
                    "Please fill in all required fields for Thai QR Debit.",
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
                text={__(
                    "Pay using Thai QR Debit payments via Key2Pay. Please enter your bank details below.",
                    "key2pay"
                )}
            />
            <div className='k2p-field-group'>
                <p>
                    <label>
                        {__("Bank Code", "key2pay")}
                        <input
                            type='text'
                            placeholder={__("e.g., 014", "key2pay")}
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
                            placeholder={__("Enter account number", "key2pay")}
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
                            placeholder={__("Name on account", "key2pay")}
                            name='payer_account_name'
                            value={accountName}
                            onChange={(e) => setAccountName(e.target.value)} // Update state on change
                            required
                        />
                    </label>
                </p>
            </div>
        </div>
    );
};

registerPaymentMethod({
    name: "key2pay_thai_debit",
    paymentMethodId: "key2pay_thai_debit",
    label: __("Key2Pay Thai QR Debit (QR Payment)", "key2pay"),
    content: <ThaiDebitFieldsContent />,
    edit: <ThaiDebitFieldsContent />,
    canMakePayment: () => true,
    ariaLabel: __("Key2Pay Thai QR Debit (QR Payment)", "key2pay"),
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

    return (
        <div className='k2p-credit-card'>
            <PaymentMethodLabel
                text={__(
                    "Pay using Credit Card via Key2Pay. You will be redirected to complete your payment securely.",
                    "key2pay"
                )}
            />
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
