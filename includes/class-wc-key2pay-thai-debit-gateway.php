<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure the base class is loaded
if (! class_exists('WC_Key2Pay_Gateway_Base')) {
    require_once dirname(__FILE__) . '/abstract-wc-key2pay-gateway-base.php';
}

/**
 * WC_Key2Pay_Thai_Debit_Gateway Class.
 *
 * Handles Thai QR Debit payments via Key2Pay.
 * Customers provide bank details on checkout and are redirected for QR scan.
 *
 * @extends WC_Key2Pay_Gateway_Base
 */
class WC_Key2Pay_Thai_Debit_Gateway extends WC_Key2Pay_Gateway_Base
{
    /**
     * Payment method type for Thai QR Debit payments.
     */
    public const PAYMENT_METHOD_TYPE = 'THAI_DEBIT';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        // 1. Set specific properties for THIS gateway. These MUST be set BEFORE parent::__construct().
        $this->id                 = 'key2pay_thai_debit';
        $this->icon               = KEY2PAY_PLUGIN_URL . 'assets/images/key2pay.png';
        $this->has_fields         = true; // This gateway requires input fields on checkout.
        $this->method_title       = __('Key2Pay Thai QR Debit (QR Payment)', 'key2pay');
        $this->method_description = __('Pay using Thai QR Debit payments via Key2Pay.', 'key2pay');

        // 2. Call the parent constructor (WC_Payment_Gateway) and initialize settings.
        parent::__construct();
        $this->init_form_fields();
        $this->init_settings();

        // 3. Load plugin options into properties (these are available AFTER parent::__construct() and init_settings() have run).
        $this->title                = $this->get_option('title');
        $this->description          = $this->get_option('description');
        $this->enabled              = $this->get_option('enabled');
        $this->debug                = 'yes' === $this->get_option('debug');
        $this->merchant_id          = sanitize_text_field($this->get_option('merchant_id'));
        $this->password             = sanitize_text_field($this->get_option('password'));
        $this->auth_type            = $this->get_option('auth_type', WC_Key2Pay_Auth::AUTH_TYPE_BASIC); // Get auth_type from settings or default
        $this->api_base_url         = sanitize_text_field($this->get_option('api_base_url', self::DEFAULT_API_BASE_URL));
        $this->disable_url_fallback = 'yes' === $this->get_option('disable_url_fallback');

        // 4. Initialize authentication handler using the retrieved settings.
        $this->setup_authentication_handler();

        // Parent (WC_Key2Pay_Gateway_Base) constructor already added common hooks, no need to redeclare.
    }

    /**
     * Payment fields for Thai QR Debit.
     * This will display input fields for payer_account_no, payer_account_name, payer_bank_code.
     * Overrides base class method.
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        } else {
            echo wpautop(wp_kses_post(__('Pay using Thai QR Debit payments via Key2Pay. Please enter your bank details below.', 'key2pay')));
        }

        echo '<fieldset id="key2pay_thai_debit_form" class="wc-payment-form wc-payment-form-thai-debit">';

        // We may want a dropdown for bank codes if Key2Pay provides a list
        woocommerce_form_field('payer_bank_code', [
            'type'        => 'text', // Can be select if you have a list of banks
            'label'       => __('Bank Code', 'key2pay'),
            'placeholder' => __('e.g., 014', 'key2pay'),
            'required'    => true,
            'default'     => '',
        ], $this->get_posted_data('payer_bank_code'));

        woocommerce_form_field('payer_account_no', [
            'type'        => 'text',
            'label'       => __('Bank Account Number', 'key2pay'),
            'placeholder' => __('Enter your debit account number', 'key2pay'),
            'required'    => true,
            'default'     => '',
        ], $this->get_posted_data('payer_account_no'));

        woocommerce_form_field('payer_account_name', [
            'type'        => 'text',
            'label'       => __('Bank Account Name', 'key2pay'),
            'placeholder' => __('Name on your debit account', 'key2pay'),
            'required'    => true,
            'default'     => '',
        ], $this->get_posted_data('payer_account_name'));

        echo '</fieldset>';
    }

    /**
     * Validate fields specific to Thai QR Debit.
     * Overrides base class method.
     */
    public function validate_fields()
    {
        // Call parent validation first if any common validation is needed, e.g., for credentials
        $parent_validation = parent::validate_fields();
        if (! $parent_validation) {
            return false;
        }

        $account_no   = $this->get_posted_data('payer_account_no');
        $account_name = $this->get_posted_data('payer_account_name');
        $bank_code    = $this->get_posted_data('payer_bank_code');

        $this->log_to_file("Posted Data: " . print_r($this->get_post_data(), true));

        if (empty($account_no)) {
            wc_add_notice(__('Please provide your Bank Account Number.', 'key2pay'), 'error');
            return false;
        }
        if (empty($account_name)) {
            wc_add_notice(__('Please provide your Bank Account Name.', 'key2pay'), 'error');
            return false;
        }
        if (empty($bank_code)) {
            wc_add_notice(__('Please provide your Bank Code.', 'key2pay'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Process the payment for Thai QR Debit.
     * Implements the abstract method from the base class.
     *
     * @param int $order_id Order ID.
     * @return array An array with 'result' and 'redirect' keys.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($this->debug) {
            $this->log_to_file(sprintf('Processing Thai QR Debit payment for order #%s.', $order_id));
        }

        // Collect Thai QR Debit specific fields from POST data
        $payer_account_no   = $this->get_posted_data('payer_account_no');
        $payer_account_name = $this->get_posted_data('payer_account_name');
        $payer_bank_code    = $this->get_posted_data('payer_bank_code');

        // Prepare data for Key2Pay Thai QR Debit API request.
        $amount      = (float) $order->get_total();
        $currency    = $order->get_currency();
        $customer_ip = WC_Geolocation::get_ip_address();
        $endpoint    = $this->build_api_url('/transaction/s2s');
        $return_url  = $this->get_return_url($order);
        $server_url  = home_url('/wc-api/' . strtolower($this->id));

        $request_data = [
            'payment_method'       => ['type' => self::PAYMENT_METHOD_TYPE], // 'THAI_DEBIT'
            'trackid'              => $order->get_id() . '_' . time(),
            'bill_currencycode'    => $currency,
            'bill_amount'          => $amount,
            'bill_country'         => $order->get_billing_country() ?: '',
            'bill_customerip'      => $customer_ip,
            'bill_email'           => $order->get_billing_email(),
            'returnUrl'            => $return_url,
            'serverUrl'            => $server_url,
            'payer_account_no'     => $payer_account_no,
            'payer_account_name'   => $payer_account_name,
            'payer_bank_code'      => $payer_bank_code,
            'productdesc'          => sprintf(__('Order %s from %s', 'key2pay'), $order->get_order_number(), get_bloginfo('name')),
            'returnUrl_on_failure' => add_query_arg('k2p-status', 'failed', $order->get_checkout_payment_url(false)),
            'lang'                 => self::DEFAULT_LANGUAGE,
            'bill_phone'           => $order->get_billing_phone() ?: '',
            'bill_city'            => $order->get_billing_city() ?: '',
            'bill_state'           => $order->get_billing_state() ?: '',
            'bill_address'         => $order->get_billing_address_1() ?: '',
            'bill_zip'             => $order->get_billing_postcode(),
        ];

        // Add authentication data to request body (for Basic Auth, merchantid and password)
        $request_data = $this->auth_handler->add_auth_to_body($request_data);

        // Get authentication headers (e.g., API Key, Bearer Token - empty for Basic Auth)
        $headers = ['Content-Type' => 'application/json'];
        $headers = array_merge($headers, $this->auth_handler->get_auth_headers());

        // Log the complete request data before sending
        $this->log_to_file('Key2Pay Thai QR Debit API Request: Preparing to send payment request for order #' . $order_id);
        $this->log_to_file('Key2Pay Thai QR Debit API Request: API URL: ' . $endpoint);

        // Redact sensitive data in headers for logging
        $safe_headers = $this->redact_sensitive_data($headers);
        $this->log_to_file('Key2Pay API Request: Headers: ' . print_r($safe_headers, true));

        // Redact sensitive data in request data for logging
        $safe_request_data = $this->redact_sensitive_data($request_data);
        $this->log_to_file('Key2Pay Thai QR Debit API Request: Request Data: ' . print_r($safe_request_data, true));
        $this->log_to_file('Key2Pay Thai QR Debit API Request: Webhook URL being sent: ' . $server_url);
        $this->log_to_file('Key2Pay Thai QR Debit API Request: JSON payload length: ' . strlen(json_encode($request_data)) . ' characters');

        // Make the API call to Key2Pay Thai QR Debit endpoint.
        $response = wp_remote_post(
            $endpoint,
            [
                'method'    => 'POST',
                'headers'   => $headers,
                'body'      => json_encode($request_data),
                'timeout'   => 60,
                'sslverify' => true,
            ]
        );

        if (is_wp_error($response)) {
            $error_message = $this->extract_error_message($response, __('Invalid Key2Pay API response.', 'key2pay'));
            wc_add_notice(sprintf(__('Key2Pay Thai QR Debit payment error: %s', 'key2pay'), $error_message), 'error');
            $this->log_to_file(sprintf('Key2Pay Thai QR Debit API Request Failed for order #%s: %s', $order_id, $error_message));
            return [
                'result'   => 'failure',
                'redirect' => '',
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);
        $this->log_to_file(sprintf('Key2Pay Thai QR Debit API Response for order #%s: %s', $order_id, print_r($this->redact_sensitive_data($data), true)));

        // Process the API response.
        // @see https://key2pay.readme.io/reference/debitsolution
        if (isset($data->type) && 'valid' === $data->type) {
            if (! empty($data->redirectUrl)) {

                // Check for "Not Successful" response code
                if (! empty($data->result) && trim($data->result) == "Not Successful") {
                    wc_add_notice(sprintf(__('Key2Pay Credit Card payment failed: %s', 'key2pay'), $this->extract_error_message($data)), 'error');
                    $this->log_to_file(sprintf('Key2Pay Credit Card Not Successful for order #%s: %s', $order_id, $data->error_text ?? 'Unknown error'));
                    return [
                        'result'   => 'failure',
                        'redirect' => '',
                    ];
                }

                // Expect transactionid and token to be present in the response
                if (empty($data->transactionid) || empty($data->token)) {
                    wc_add_notice($this->get_user_friendly_error_message($data), 'error');
                    if ($this->debug) {
                        $this->log_to_file(sprintf('Key2Pay Thai QR Debit Missing Transaction ID or Token for order #%s', $order_id));
                    }
                    return [
                        'result'   => 'failure',
                        'redirect' => esc_url_raw($data->redirectUrl),
                    ];
                }

                // Payment session created successfully, redirect customer to Key2Pay.
                $order->update_status('pending', __('Awaiting Key2Pay Thai QR Debit payment confirmation.', 'key2pay'));

                // Store Key2Pay transaction details.
                if (! empty($data->transactionid)) {
                    $order->update_meta_data('_key2pay_transaction_id', $data->transactionid);
                }
                if (! empty($data->trackid)) {
                    $order->update_meta_data('_key2pay_track_id', $data->trackid);
                }
                if (! empty($data->token)) {
                    $order->update_meta_data('_key2pay_token', $data->token);
                    $this->log_to_file(sprintf('Key2Pay Token for order #%s: %s', $order_id, $data->token));
                }

                $order->save();

                /**
                 * [!] For TEST enviroment only
                 * Key2Pay will return an invalid redirect URL in test mode.
                 * If merchant_id contains 'TEST', we can redirect to the order confirmation page instead.
                 * This is a workaround for the test environment.
                 * In production, this should not be needed as Key2Pay will provide a valid redirect URL.
                 */
                if (strpos($this->merchant_id, 'TEST') !== false) {
                    $data->redirectUrl = $this->get_return_url($order);
                    $this->log_to_file(sprintf('Key2Pay Thai QR Debit Test Mode: Using fallback redirect URL for order #%s', $order_id));
                    wc_add_notice(__('You are in test mode. Redirecting to order confirmation page.', 'key2pay'), 'notice');
                    sleep(10); // Allow some time for the webhook to process and simulate the payment confirmation.
                }

                // Return success with redirect URL.
                return [
                    'result'   => 'success',
                    'redirect' => esc_url_raw($data->redirectUrl),
                ];
            } else {
                // Valid response but no redirect URL, which is unexpected for Thai QR Debit QR.
                wc_add_notice($this->get_user_friendly_error_message($data), 'error');
                $error_message = $this->extract_error_message($data, __('Payment session created, but no redirection URL received for Thai QR Debit.', 'key2pay'));
                if ($this->debug) {
                    $this->log_to_file(sprintf('Key2Pay Thai QR Debit Missing Redirect URL for order #%s: %s', $order_id, $error_message));
                }
                return [
                    'result'   => 'failure',
                    'redirect' => '',
                ];
            }
        } else {
            // Payment session creation failed or API returned an error.
            wc_add_notice($this->get_user_friendly_error_message($data), 'error');
            $error_message = $this->extract_error_message($data, __('An unknown error occurred with Key2Pay Thai QR Debit.', 'key2pay'));
            $this->log_to_file(sprintf('Key2Pay Thai QR Debit API Error for order #%s: %s', $order_id, $error_message));
            return [
                'result'   => 'failure',
                'redirect' => '',
            ];
        }
    }
}
