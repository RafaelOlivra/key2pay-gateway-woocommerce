<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

// Ensure the base class is loaded
if (! class_exists('WC_Key2Pay_Gateway_Base')) {
    require_once dirname(__FILE__) . '/abstract-wc-key2pay-gateway-base.php';
}

/**
 * WC_Key2Pay_Credit_Gateway Class.
 *
 * A secure redirect-based WooCommerce payment gateway for Key2Pay for Credit Cards.
 * Customers are redirected to Key2Pay's hosted payment page.
 *
 * @extends WC_Key2Pay_Gateway_Base
 */
class WC_Key2Pay_Credit_Gateway extends WC_Key2Pay_Gateway_Base
{
    /**
     * Payment method type for redirect-based payments.
     * CARD = Credit card payments only
     */
    public const PAYMENT_METHOD_TYPE = 'BANKCARD';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        // 1. Set specific properties for THIS gateway. These MUST be set BEFORE parent::__construct().
        $this->id                 = 'key2pay_credit';
        $this->icon               = KEY2PAY_PLUGIN_URL . 'assets/images/key2pay.png';
        $this->has_fields         = false; // No fields on checkout for this gateway
        $this->method_title       = __('Key2Pay Credit Card', 'key2pay');
        $this->method_description = __('Pay using Credit Card via Key2Pay.', 'key2pay');

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
     * Payment fields - just show description since this is redirect-based.
     * Overrides base class to provide specific text for this gateway.
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        } else {
            echo wpautop(wp_kses_post(__('Pay using Credit Card via Key2Pay. You will be redirected to complete your payment securely.', 'key2pay')));
        }
    }

    /**
     * Process the payment for redirect-based payments.
     * Implements the abstract method from the base class.
     *
     * @param int $order_id Order ID.
     * @return array An array with 'result' and 'redirect' keys.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($this->debug) {
            $this->log_to_file(sprintf('Processing redirect payment for order #%s.', $order_id));
        }

        // Prepare data for Key2Pay Credit Card API request.
        $amount      = (float) $order->get_total();
        $currency    = $order->get_currency();
        $customer_ip = WC_Geolocation::get_ip_address();
        $endpoint    = $this->build_api_url('/PaymentToken/Create');
        $return_url  = $this->get_return_url($order);
        $server_url  = home_url('/wc-api/' . strtolower($this->id)); // Webhook endpoint for this gateway.

        // Initial request data for API call
        $request_data = [
            'payment_method'       => ['type' => self::PAYMENT_METHOD_TYPE], // 'CARD'
            'trackid'              => $order->get_id() . '_' . time(),
            'bill_currencycode'    => $currency,
            'bill_amount'          => $amount,
            'returnUrl'            => $return_url,
            'returnUrl_on_failure' => add_query_arg('k2p-status', 'failed', $order->get_checkout_payment_url(false)),
            'serverUrl'            => $server_url,
            'productdesc'          => sprintf(__('Order %s from %s', 'key2pay'), $order->get_order_number(), get_bloginfo('name')),
            'bill_customerip'      => $customer_ip,
            'bill_phone'           => $order->get_billing_phone() ?: '',
            'bill_email'           => $order->get_billing_email(),
            'bill_country'         => $order->get_billing_country() ?: '',
            'bill_city'            => $order->get_billing_city() ?: '',
            'bill_state'           => $order->get_billing_state() ?: '',
            'bill_address'         => $order->get_billing_address_1() ?: '',
            'bill_zip'             => $order->get_billing_postcode(),
            'lang'                 => self::DEFAULT_LANGUAGE,
        ];

        // Add authentication data to request body if needed (e.g., Basic Auth)
        $request_data = $this->auth_handler->add_auth_to_body($request_data);

        // Get authentication headers (e.g., API Key, Bearer Token)
        $headers = ['Content-Type' => 'application/json'];
        $headers = array_merge($headers, $this->auth_handler->get_auth_headers());

        // Log the complete request data before sending
        $this->log_to_file('Key2Pay API Request: Preparing to send payment request for order #' . $order_id);
        $this->log_to_file('Key2Pay API Request: API URL: ' . $endpoint);

        // Redact sensitive data in headers for logging
        $safe_headers = $this->redact_sensitive_data($headers);
        $this->log_to_file('Key2Pay API Request: Headers: ' . print_r($safe_headers, true));

        // Redact sensitive data in request data for logging
        $safe_request_data = $this->redact_sensitive_data($request_data);
        $this->log_to_file('Key2Pay API Request: Request Data: ' . print_r($safe_request_data, true));
        $this->log_to_file('Key2Pay API Request: Webhook URL being sent: ' . $server_url);
        $this->log_to_file('Key2Pay API Request: JSON payload length: ' . strlen(json_encode($request_data)) . ' characters');

        // Make the API call to Key2Pay Credit Card endpoint.
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
            wc_add_notice(sprintf(__('Key2Pay Credit Card payment error: %s', 'key2pay'), $error_message), 'error');
            $this->log_to_file(sprintf('Key2Pay Credit Card API Request Failed for order #%s: %s', $order_id, $error_message));
            return [
                'result'   => 'failure',
                'redirect' => '',
            ];
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        $this->log_to_file(sprintf('Key2Pay Credit Card API Response for order #%s: %s', $order_id, print_r($data, true)));

        // Process the API response.
        if (isset($data->type) && 'valid' === $data->type) {
            if (! empty($data->redirectUrl)) {

                // Check for "Not Successful" response code
                if (! empty($data->result) && trim($data->result) == "Not Successful") {
                    wc_add_notice(sprintf(__('Key2Pay payment failed: %s', 'key2pay'), $this->extract_error_message($data)), 'error');
                    $this->log_to_file(sprintf('Key2Pay Credit Card Not Successful for order #%s: %s', $order_id, $data->error_text ?? 'Unknown error'));
                    return [
                        'result'   => 'failure',
                        'redirect' => '',
                    ];
                }

                // Payment session created successfully, redirect customer to Key2Pay.
                $order->update_status('pending', __('Awaiting Key2Pay payment confirmation.', 'key2pay'));

                // Store Key2Pay transaction details.
                if (! empty($data->transactionid)) {
                    $order->update_meta_data('_key2pay_transaction_id', $data->transactionid);
                }
                if (! empty($data->trackid)) {
                    $order->update_meta_data('_key2pay_track_id', $data->trackid);
                }
                if (! empty($data->token)) {
                    $order->update_meta_data('_key2pay_token', $data->token);
                }

                $order->save();

                return [
                    'result'   => 'success',
                    'redirect' => esc_url_raw($data->redirectUrl),
                ];
            } else {
                // Valid response but no redirect URL, which is unexpected.
                $error_message = $this->extract_error_message($data, __('Payment session created, but no redirection URL received.', 'key2pay'));
                wc_add_notice(sprintf(__('Key2Pay Credit Card failed: %s', 'key2pay'), $error_message), 'error');
                if ($this->debug) {
                    $this->log_to_file(sprintf('Key2Pay Credit Card Missing Redirect URL for order #%s: %s', $order_id, $error_message));
                }
                return [
                    'result'   => 'failure',
                    'redirect' => '',
                ];
            }
        } else {
            // Payment session creation failed or API returned an error.
            $error_message = $this->extract_error_message($data, __('An unknown error occurred with Key2Pay Credit Card.', 'key2pay'));
            wc_add_notice(sprintf(__('Key2Pay Credit Card payment failed: %s', 'key2pay'), $error_message), 'error');
            $this->log_to_file(sprintf('Key2Pay Credit Card API Error for order #%s: %s', $order_id, $error_message));
            return [
                'result'   => 'failure',
                'redirect' => '',
            ];
        }
    }
}
