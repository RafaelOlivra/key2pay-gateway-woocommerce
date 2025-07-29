const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
import { __ } from "@wordpress/i18n";
import { useState } from "@wordpress/element";

/**
 * ### Thai Debit Setup ###
 */

const ThaiDebitFieldsContent = (props) => {
    const { onPaymentSetup, components } = props;
    const { PaymentMethodLabel } = components;

    const [accountNo, setAccountNo] = useState("");
    const [accountName, setAccountName] = useState("");
    const [bankCode, setBankCode] = useState("");

    const isValid = accountNo && accountName && bankCode;

    console.log(props);

    if (onPaymentSetup) {
        onPaymentSetup(
            isValid
                ? {
                      type: "complete",
                      data: {
                          payer_account_no: accountNo,
                          payer_account_name: accountName,
                          payer_bank_code: bankCode,
                      },
                  }
                : { type: "incomplete" }
        );
    }

    return (
        <div className='k2p-thai-debit'>
            <PaymentMethodLabel
                text={__(
                    "Pay securely with Thai Debit via Key2Pay. Please enter your bank details below.",
                    "key2pay"
                )}
            />
            <div className='k2p-field-group'>
                <p>
                    <label>
                        {__("Bank Code", "key2pay")}
                        <input
                            type='text'
                            value={bankCode}
                            onChange={(e) => setBankCode(e.target.value)}
                            placeholder={__("e.g., 014", "key2pay")}
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
                            value={accountNo}
                            onChange={(e) => setAccountNo(e.target.value)}
                            placeholder={__("Enter account number", "key2pay")}
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
                            value={accountName}
                            onChange={(e) => setAccountName(e.target.value)}
                            placeholder={__("Name on account", "key2pay")}
                            required
                        />
                    </label>
                </p>
            </div>

            {/* Send the values to PHP via POST */}
            <input type='hidden' name='payer_account_no' value={accountNo} />
            <input
                type='hidden'
                name='payer_account_name'
                value={accountName}
            />
            <input type='hidden' name='payer_bank_code' value={bankCode} />
        </div>
    );
};

registerPaymentMethod({
    name: "key2pay_thai_debit",
    paymentMethodId: "key2pay_thai_debit",
    label: __("Key2Pay Thai Debit (QR Payment)", "key2pay"),
    content: <ThaiDebitFieldsContent />,
    edit: <ThaiDebitFieldsContent />,
    canMakePayment: () => true,
    ariaLabel: __("Key2Pay Thai Debit (QR Payment)", "key2pay"),
    supports: {
        features: ["products", "refunds"],
    },
});

/**
 * ### Credit Card Setup ###
 */

const ThaiCreditFieldsContent = (props) => {
    const { onPaymentSetup, components } = props;
    const { PaymentMethodLabel } = components;

    console.log(PaymentMethodLabel);
    console.log(props);

    return (
        <div className='k2p-credit-card'>
            <PaymentMethodLabel
                text={__(
                    "Pay securely with your credit card via Key2Pay. You will be redirected to complete your payment securely.",
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
