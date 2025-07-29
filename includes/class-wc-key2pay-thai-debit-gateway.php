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
 * Handles Thai QR debit payments via Key2Pay.
 * Customers provide bank details on checkout and are redirected for QR scan.
 *
 * @extends WC_Key2Pay_Gateway_Base
 */
class WC_Key2Pay_Thai_Debit_Gateway extends WC_Key2Pay_Gateway_Base
{
    /**
     * Payment method type for Thai Debit payments.
     */
    public const PAYMENT_METHOD_TYPE = 'THAI_DEBIT';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        // 1. Set specific properties for THIS gateway. These MUST be set BEFORE parent::__construct().
        $this->id                   = 'key2pay_thai_debit';
        $this->icon                 = apply_filters('woocommerce_key2pay_thai_debit_icon', plugin_dir_url(dirname(__FILE__)) . 'assets/images/thai-debit.webp');
        $this->has_fields           = true; // This gateway requires input fields on checkout.
        $this->method_title         = __('Key2Pay Thai Debit (QR Payment)', 'key2pay');
        $this->method_description   = __('Accept Thai QR debit payments via Key2Pay. Customers provide their bank details and are redirected to scan a QR code.', 'key2pay');

        // 2. Call the parent constructor (WC_Payment_Gateway).
        // This will call init_form_fields() and init_settings() from the parent chain.
        parent::__construct();

        // Load the settings.
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
        try {
            // For Thai Debit, we explicitly use Basic Auth per the API docs for `transaction/s2s`.
            // We ensure that the auth_type property is set correctly here for the handler.
            $this->auth_handler = new WC_Key2Pay_Auth(WC_Key2Pay_Auth::AUTH_TYPE_BASIC); // Always basic for Thai Debit API
            $this->auth_handler->set_credentials(array(
                'merchant_id'  => $this->merchant_id,
                'password'     => $this->password,
                // Other auth methods are not applicable for Thai Debit /transaction/s2s
            ));
            $this->auth_handler->set_debug($this->debug);
        } catch (Exception $e) {
            $this->log->error('Key2Pay Thai Debit Gateway Error: Failed to initialize auth handler: ' . $e->getMessage(), array('source' => $this->id));
        }

        // Parent (WC_Key2Pay_Gateway_Base) constructor already added common hooks, no need to redeclare.

        //print_r($this);
    }

    /**
     * Initialize Gateway Settings Form Fields.
     * Overrides parent to add specific fields for this gateway.
     */
    public function init_form_fields()
    {
        parent::init_form_fields(); // Get common fields from the base class
        // No specific changes needed here as the base class already provides the auth fields.
    }

    /**
     * Payment fields for Thai Debit.
     * This will display input fields for payer_account_no, payer_account_name, payer_bank_code.
     * Overrides base class method.
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        } else {
            echo wpautop(wp_kses_post(__('Pay securely with Thai Debit via Key2Pay. Please enter your bank details below.', 'woocommerce-key2pay-gateway')));
        }

        echo '<fieldset id="key2pay_thai_debit_form" class="wc-payment-form wc-payment-form-thai-debit">';

        woocommerce_form_field('key2pay_thai_debit_account_no', array(
            'type'        => 'text',
            'label'       => __('Payer Account Number', 'key2pay'),
            'placeholder' => __('Enter your debit account number', 'key2pay'),
            'required'    => true,
            'default'     => '',
        ), $this->get_posted_data('key2pay_thai_debit_account_no'));

        woocommerce_form_field('key2pay_thai_debit_account_name', array(
            'type'        => 'text',
            'label'       => __('Payer Account Name', 'key2pay'),
            'placeholder' => __('Name on your debit account', 'key2pay'),
            'required'    => true,
            'default'     => '',
        ), $this->get_posted_data('key2pay_thai_debit_account_name'));

        // You might want a dropdown for bank codes if Key2Pay provides a list
        woocommerce_form_field('key2pay_thai_debit_bank_code', array(
            'type'        => 'text', // Can be select if you have a list of banks
            'label'       => __('Payer Bank Code', 'key2pay'),
            'placeholder' => __('e.g., 014', 'key2pay'),
            'required'    => true,
            'default'     => '',
        ), $this->get_posted_data('key2pay_thai_debit_bank_code'));

        echo '</fieldset>';
    }

    /**
     * Validate fields specific to Thai Debit.
     * Overrides base class method.
     */
    public function validate_fields()
    {
        // Call parent validation first if any common validation is needed, e.g., for credentials
        $parent_validation = parent::validate_fields();
        if (!$parent_validation) {
            return false;
        }

        $account_no   = $this->get_posted_data('key2pay_thai_debit_account_no');
        $account_name = $this->get_posted_data('key2pay_thai_debit_account_name');
        $bank_code    = $this->get_posted_data('key2pay_thai_debit_bank_code');

        if (empty($account_no)) {
            wc_add_notice(__('Please provide your Payer Account Number for Thai Debit.', 'key2pay'), 'error');
            return false;
        }
        if (empty($account_name)) {
            wc_add_notice(__('Please provide your Payer Account Name for Thai Debit.', 'key2pay'), 'error');
            return false;
        }
        if (empty($bank_code)) {
            wc_add_notice(__('Please provide your Payer Bank Code for Thai Debit.', 'key2pay'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Process the payment for Thai Debit.
     * Implements the abstract method from the base class.
     *
     * @param int $order_id Order ID.
     * @return array An array with 'result' and 'redirect' keys.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($this->debug) {
            $this->log_to_file(sprintf('Processing Thai Debit payment for order #%s.', $order_id));
        }

        // Collect Thai Debit specific fields from POST data
        $payer_account_no   = $this->get_posted_data('key2pay_thai_debit_account_no');
        $payer_account_name = $this->get_posted_data('key2pay_thai_debit_account_name');
        $payer_bank_code    = $this->get_posted_data('key2pay_thai_debit_bank_code');

        // Prepare data for Key2Pay Thai Debit API request.
        $amount           = (float) $order->get_total();
        $currency         = $order->get_currency();
        $return_url       = $this->get_return_url($order);
        $customer_ip      = WC_Geolocation::get_ip_address();
        $server_url       = home_url('/wc-api/' . strtolower($this->id)); // Webhook endpoint for this gateway.

        $request_data = array(
            'payment_method'      => array('type' => self::PAYMENT_METHOD_TYPE), // 'THAI_DEBIT'
            'trackid'             => $order->get_id() . '_' . time(),
            'bill_currencycode'   => $currency,
            'bill_amount'         => $amount,
            'bill_country'        => $order->get_billing_country() ?: '',
            'bill_customerip'     => $customer_ip,
            'bill_email'          => $order->get_billing_email(),
            'returnUrl'           => $return_url,
            'serverUrl'           => $server_url,
            'payer_account_no'    => $payer_account_no,
            'payer_account_name'  => $payer_account_name,
            'payer_bank_code'     => $payer_bank_code,
            'productdesc'         => sprintf(__('Order %s from %s', 'key2pay'), $order->get_order_number(), get_bloginfo('name')),
            'returnUrl_on_failure' => $order->get_checkout_payment_url(false),
            'lang'                => self::DEFAULT_LANGUAGE,
            'bill_phone'          => $order->get_billing_phone() ?: '',
            'bill_city'           => $order->get_billing_city() ?: '',
            'bill_state'          => $order->get_billing_state() ?: '',
            'bill_address'        => $order->get_billing_address_1() ?: '',
            'bill_zip'            => $order->get_billing_postcode(),
        );

        // Add authentication data to request body (for Basic Auth, merchantid and password)
        $request_data = $this->auth_handler->add_auth_to_body($request_data);

        // Sign the request if using HMAC (though Thai Debit API spec implies Basic)
        if ($this->auth_type === WC_Key2Pay_Auth::AUTH_TYPE_SIGNED) {
            $request_data = $this->auth_handler->sign_request($request_data, '/transaction/s2s');
        }

        // Get authentication headers (e.g., API Key, Bearer Token - empty for Basic Auth)
        $headers = array('Content-Type' => 'application/json');
        $headers = array_merge($headers, $this->auth_handler->get_auth_headers());

        // Log the complete request data before sending
        $this->log_to_file('Key2Pay Thai Debit API Request: Preparing to send payment request for order #' . $order_id);
        $this->log_to_file('Key2Pay Thai Debit API Request: API URL: ' . $this->build_api_url('/transaction/s2s'));

        $safe_headers = $headers;
        if (isset($safe_headers['Authorization'])) {
            $safe_headers['Authorization'] = '[REDACTED]';
        }
        $this->log_to_file('Key2Pay Thai Debit API Request: Headers: ' . print_r($safe_headers, true));

        $safe_request_data = $request_data;
        if (isset($safe_request_data['password'])) {
            $safe_request_data['password'] = '[REDACTED]';
        }
        if (isset($safe_request_data['merchantid'])) {
            $safe_request_data['merchantid'] = '[REDACTED]';
        }
        if (isset($safe_request_data['api_key'])) {
            $safe_request_data['api_key'] = '[REDACTED]';
        } // For API key in body
        if (isset($safe_request_data['secret_key'])) {
            $safe_request_data['secret_key'] = '[REDACTED]';
        } // For HMAC
        if (isset($safe_request_data['payer_account_no'])) {
            $safe_request_data['payer_account_no'] = '[REDACTED]';
        }
        if (isset($safe_request_data['payer_account_name'])) {
            $safe_request_data['payer_account_name'] = '[REDACTED]';
        }
        $this->log_to_file('Key2Pay Thai Debit API Request: Request Data: ' . print_r($safe_request_data, true));
        $this->log_to_file('Key2Pay Thai Debit API Request: Webhook URL being sent: ' . $server_url);
        $this->log_to_file('Key2Pay Thai Debit API Request: JSON payload length: ' . strlen(json_encode($request_data)) . ' characters');

        // Make the API call to Key2Pay Thai Debit endpoint.
        $response = wp_remote_post(
            $this->build_api_url('/transaction/s2s'),
            array(
                'method'    => 'POST',
                'headers'   => $headers,
                'body'      => json_encode($request_data),
                'timeout'   => 60,
                'sslverify' => true,
            )
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wc_add_notice(sprintf(__('Key2Pay Thai Debit payment error: %s', 'key2pay'), $error_message), 'error');
            if ($this->debug) {
                $this->log_to_file(sprintf('Key2Pay Thai Debit API Request Failed for order #%s: %s', $order_id, $error_message));
            }
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if ($this->debug) {
            $this->log_to_file(sprintf('Key2Pay Thai Debit API Response for order #%s: %s', $order_id, print_r($data, true)));
        }

        // Process the API response.
        if (isset($data->type) && 'valid' === $data->type) {
            if (isset($data->redirectUrl) && ! empty($data->redirectUrl)) {
                // Payment session created successfully, redirect customer to Key2Pay.
                $order->update_status('pending', __('Awaiting Key2Pay Thai Debit payment confirmation.', 'key2pay'));

                // Store Key2Pay transaction details.
                if (isset($data->transactionid)) {
                    $order->update_meta_data('_key2pay_transaction_id', $data->transactionid);
                }
                if (isset($data->trackid)) {
                    $order->update_meta_data('_key2pay_track_id', $data->trackid);
                }
                $order->save();

                return array(
                    'result'   => 'success',
                    'redirect' => $data->redirectUrl,
                );
            } else {
                // Valid response but no redirect URL, which is unexpected for Thai Debit QR.
                $error_message = isset($data->error_text) ? $data->error_text : __('Payment session created, but no redirection URL received for Thai Debit.', 'key2pay');
                wc_add_notice(sprintf(__('Key2Pay Thai Debit failed: %s', 'key2pay'), $error_message), 'error');
                if ($this->debug) {
                    $this->log_to_file(sprintf('Key2Pay Thai Debit Missing Redirect URL for order #%s: %s', $order_id, $error_message));
                }
                return array(
                    'result'   => 'fail',
                    'redirect' => '',
                );
            }
        } else {
            // Payment session creation failed or API returned an error.
            $error_message = isset($data->error_text) ? $data->error_text : __('An unknown error occurred with Key2Pay Thai Debit.', 'key2pay');
            wc_add_notice(sprintf(__('Key2Pay Thai Debit payment failed: %s', 'key2pay'), $error_message), 'error');
            if ($this->debug) {
                $this->log_to_file(sprintf('Key2Pay Thai Debit API Error for order #%s: %s', $order_id, $error_message));
            }
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }
}
