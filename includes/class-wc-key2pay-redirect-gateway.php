<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * WC_Key2Pay_Redirect_Gateway Class.
 *
 * A secure redirect-based WooCommerce payment gateway for Key2Pay.
 * Customers are redirected to Key2Pay's hosted payment page.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Key2Pay_Redirect_Gateway extends WC_Payment_Gateway
{
    public $id;
    public $icon;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $title;
    public $description;
    public $enabled;
    public $merchant_id;
    public $password;
    public $api_key;
    public $secret_key;
    public $access_token;
    public $auth_type;
    public $debug;
    public $log;
    public $form_fields;
    public $auth_handler;
    public $disable_url_fallback;

    /**
     * Default API Base URL for Key2Pay.
     */
    public const DEFAULT_API_BASE_URL = 'https://api.key2payment.com/';

    /**
     * Payment method type for redirect-based payments.
     * CARD = Credit card payments only
     */
    public const PAYMENT_METHOD_TYPE = 'CARD';

    /**
     * Default language for payment page.
     */
    public const DEFAULT_LANGUAGE = 'en';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        // Initialize logger
        $this->log = wc_get_logger();
        $this->custom_log_file = WP_CONTENT_DIR . '/uploads/key2pay-gateway.log';

        $this->id                 = 'key2pay_redirect'; // Unique ID for redirect gateway.
        $this->icon               = apply_filters('woocommerce_key2pay_redirect_icon', plugin_dir_url(dirname(__FILE__)) . 'assets/images/key2pay-admin.webp');
        $this->has_fields         = false; // Redirect-based payment method - no fields needed.
        $this->method_title       = __('Key2Pay Credit Card', 'key2pay');
        $this->method_description = __('Accept credit card payments via Key2Pay with maximum security. Customers are redirected to Key2Pay\'s hosted payment page.', 'key2pay');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings values.
        $this->title          = $this->get_option('title');
        $this->description    = $this->get_option('description');
        $this->enabled        = $this->get_option('enabled');
        $this->debug          = 'yes' === $this->get_option('debug');

        // Get credentials from admin settings
        $this->merchant_id    = sanitize_text_field($this->get_option('merchant_id'));
        $this->password       = sanitize_text_field($this->get_option('password'));
        $this->payment_method_type = 'CARD'; // Force credit card only
        $this->api_base_url   = sanitize_text_field($this->get_option('api_base_url', self::DEFAULT_API_BASE_URL));
        $this->disable_url_fallback = 'yes' === $this->get_option('disable_url_fallback');

        // Initialize authentication handler
        try {
            $this->auth_handler = new WC_Key2Pay_Auth('basic');
            $this->auth_handler->set_credentials(array(
                'merchant_id' => $this->merchant_id,
                'password' => $this->password,
            ));
            $this->auth_handler->set_debug($this->debug);
        } catch (Exception $e) {
            $this->log->error('Key2Pay Error: Failed to initialize auth handler: ' . $e->getMessage(), array('source' => 'key2pay-redirect'));
        }

        // Add hooks for admin settings and payment processing.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Webhook listener for payment status updates.
        add_action('woocommerce_api_wc_key2pay_redirect_gateway', array($this, 'handle_webhook_callback'));

        // Add validation for API base URL
        add_action('woocommerce_admin_field_api_base_url', array($this, 'validate_api_base_url'));
    }

    /**
     * Initialize Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'key2pay'),
                'type'    => 'checkbox',
                'label'   => __('Enable Key2Pay Credit Card', 'key2pay'),
                'default' => 'no', // Disabled by default for production safety
            ),
            'title' => array(
                'title'       => __('Title', 'key2pay'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'key2pay'),
                'default'     => __('Credit Card (Key2Pay)', 'key2pay'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'key2pay'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'key2pay'),
                'default'     => __('Pay securely with your credit card via Key2Pay. You will be redirected to complete your payment securely.', 'key2pay'),
                'desc_tip'    => true,
            ),
            'credentials_section' => array(
                'title'       => __('Key2Pay Credentials', 'key2pay'),
                'type'        => 'title',
                'description' => __('Enter your Key2Pay credentials. These are required for the gateway to work.', 'key2pay'),
            ),
            'merchant_id' => array(
                'title'       => __('Merchant ID', 'key2pay'),
                'type'        => 'text',
                'description' => __('Your Key2Pay Merchant ID.', 'key2pay'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'password' => array(
                'title'       => __('Password', 'key2pay'),
                'type'        => 'password',
                'description' => __('Your Key2Pay API Password.', 'key2pay'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'api_base_url' => array(
                'title'       => __('API Base URL', 'key2pay'),
                'type'        => 'text',
                'description' => __('Key2Pay API base URL. Use sandbox for testing, production for live payments.', 'key2pay'),
                'default'     => self::DEFAULT_API_BASE_URL,
                'desc_tip'    => true,
                'custom_attributes' => array(
                    'placeholder' => 'https://api.key2payment.com/',
                ),
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'key2pay'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'key2pay'),
                'default'     => 'no',
                'description' => __('Log Key2Pay credit card payment events.', 'key2pay'),
            ),
            'security_section' => array(
                'title'       => __('Security Settings', 'key2pay'),
                'type'        => 'title',
                'description' => __('Configure security settings for payment processing.', 'key2pay'),
            ),
            'disable_url_fallback' => array(
                'title'       => __('Disable URL Parameter Fallback', 'key2pay'),
                'type'        => 'checkbox',
                'label'       => __('Disable URL parameter processing for maximum security', 'key2pay'),
                'default'     => 'yes',
                'description' => __('When enabled, only webhooks will be used for payment status updates. URL parameters will be completely ignored. Recommended for production environments.', 'key2pay'),
            ),
        );
    }

    /**
     * Payment fields - just show description since this is redirect-based.
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        } else {
            // Default description if none is set
            echo wpautop(wp_kses_post(__('Pay securely with your credit card via Key2Pay. You will be redirected to complete your payment securely.', 'woocommerce-key2pay-gateway')));
        }
    }

    /**
     * Validate fields (no custom fields needed for redirect).
     */
    public function validate_fields()
    {
        return true;
    }

    /**
     * Process the payment for redirect-based payments.
     *
     * This method creates a payment session with Key2Pay and redirects the customer
     * to their hosted payment page. The payment status will be updated via webhook.
     *
     * @param int $order_id Order ID.
     * @return array An array with 'result' and 'redirect' keys.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($this->debug) {
            $this->log->debug(sprintf('Processing redirect payment for order #%s.', $order_id), array('source' => 'key2pay-redirect'));
        }

        // Prepare data for Key2Pay redirect API request.
        $amount           = (float) $order->get_total();
        $currency         = $order->get_currency();
        $return_url       = $this->get_return_url($order);
        $customer_ip      = WC_Geolocation::get_ip_address();
        $server_url       = home_url('/wc-api/wc_key2pay_redirect_gateway'); // Webhook endpoint for this gateway.



        // Prepare request data for Key2Pay API
        $request_data = array(
            'merchantid'        => $this->merchant_id,
            'password'          => $this->password,
            'payment_method'    => array('type' => self::PAYMENT_METHOD_TYPE),
            'trackid'           => $order->get_id() . '_' . time(),
            'bill_currencycode' => $currency,
            'bill_amount'       => $amount,
            'returnUrl'         => $return_url,
            'returnUrl_on_failure' => $order->get_checkout_payment_url(false),
            'productdesc'       => sprintf(__('Order %s from %s', 'key2pay'), $order->get_order_number(), get_bloginfo('name')),
            'bill_customerip'   => $customer_ip,
            'bill_phone'        => $order->get_billing_phone() ?: '',
            'bill_email'        => $order->get_billing_email(),
            'bill_country'      => $order->get_billing_country() ?: '',
            'bill_city'         => $order->get_billing_city() ?: '',
            'bill_state'        => $order->get_billing_state() ?: '',
            'bill_address'      => $order->get_billing_address_1() ?: '',
            'bill_zip'          => $order->get_billing_postcode(),
            'serverUrl'         => $server_url,
            'lang'              => self::DEFAULT_LANGUAGE,
        );

        // Get authentication headers
        $auth_headers = $this->auth_handler->get_auth_headers();

        // Prepare request headers
        $headers = array(
            'Content-Type' => 'application/json',
        );

        // Merge authentication headers
        $headers = array_merge($headers, $auth_headers);

        // Log the complete request data before sending
        $this->log_to_file('Key2Pay API Request: Preparing to send payment request for order #' . $order_id);
        $this->log_to_file('Key2Pay API Request: API URL: ' . $this->build_api_url('/PaymentToken/Create'));

        // Log headers without sensitive authentication data
        $safe_headers = $headers;
        if (isset($safe_headers['Authorization'])) {
            $safe_headers['Authorization'] = '[REDACTED]';
        }

        $this->log_to_file('Key2Pay API Request: Headers: ' . print_r($safe_headers, true));

        // Log request data without sensitive information
        $safe_request_data = $request_data;
        if (isset($safe_request_data['password'])) {
            $safe_request_data['password'] = '[REDACTED]';
        }
        if (isset($safe_request_data['merchantid'])) {
            $safe_request_data['merchantid'] = '[REDACTED]';
        }

        $this->log_to_file('Key2Pay API Request: Request Data: ' . print_r($safe_request_data, true));
        $this->log_to_file('Key2Pay API Request: Webhook URL being sent: ' . $server_url);
        $this->log_to_file('Key2Pay API Request: JSON payload length: ' . strlen(json_encode($request_data)) . ' characters');

        // Make the API call to Key2Pay redirect endpoint.
        $response = wp_remote_post(
            $this->build_api_url('/PaymentToken/Create'),
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
            wc_add_notice(sprintf(__('Key2Pay redirect payment error: %s', 'key2pay'), $error_message), 'error');
            if ($this->debug) {
                $this->log->error(sprintf('Key2Pay redirect API Request Failed for order #%s: %s', $order_id, $error_message), array('source' => 'key2pay-redirect'));
            }
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if ($this->debug) {
            $this->log->debug(sprintf('Key2Pay redirect API Response for order #%s: %s', $order_id, print_r($data, true)), array('source' => 'key2pay-redirect'));
        }

        // Process the API response.
        if (isset($data->type) && 'valid' === $data->type) {
            if (isset($data->redirectUrl) && ! empty($data->redirectUrl)) {
                // Payment session created successfully, redirect customer to Key2Pay.
                $order->update_status('pending', __('Awaiting Key2Pay payment confirmation.', 'key2pay'));

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
                // Valid response but no redirect URL, which is unexpected.
                $error_message = isset($data->error_text) ? $data->error_text : __('Payment session created, but no redirection URL received.', 'key2pay');
                wc_add_notice(sprintf(__('Key2Pay redirect failed: %s', 'key2pay'), $error_message), 'error');
                if ($this->debug) {
                    $this->log->error(sprintf('Key2Pay redirect Missing Redirect URL for order #%s: %s', $order_id, $error_message), array('source' => 'key2pay-redirect'));
                }
                return array(
                    'result'   => 'fail',
                    'redirect' => '',
                );
            }
        } else {
            // Payment session creation failed or API returned an error.
            $error_message = isset($data->error_text) ? $data->error_text : __('An unknown error occurred with Key2Pay redirect.', 'key2pay');
            wc_add_notice(sprintf(__('Key2Pay redirect payment failed: %s', 'key2pay'), $error_message), 'error');
            if ($this->debug) {
                $this->log->error(sprintf('Key2Pay redirect API Error for order #%s: %s', $order_id, $error_message), array('source' => 'key2pay-redirect'));
            }
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * Output for the order received page.
     *
     * Note: URL parameter processing can be disabled via admin settings for maximum security.
     * When disabled, only webhooks are used for payment status updates.
     *
     * If URL parameters are present and fallback is enabled, process them and redirect to clean URL.
     */
    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);

        // Check if URL parameter fallback is disabled
        if ($this->disable_url_fallback) {
            // URL parameter fallback is disabled - only show status messages
            if ($order->has_status('pending')) {
                echo wpautop(wp_kses_post(__('Your order is awaiting payment confirmation from Key2Pay. We will update your order status once the payment is confirmed via our secure webhook system.', 'key2pay')));
            } elseif ($order->has_status('processing') || $order->has_status('completed')) {
                echo wpautop(wp_kses_post(__('Thank you! Your credit card payment has been confirmed and your order is being processed.', 'key2pay')));
            } elseif ($order->has_status('failed')) {
                echo wpautop(wp_kses_post(__('Your credit card payment was not successful. Please try again or contact support if you believe this is an error.', 'key2pay')));
            } else {
                echo wpautop(wp_kses_post(__('Thank you for your order. We will process your credit card payment shortly.', 'key2pay')));
            }
            return;
        }

        // Check if we have URL parameters (Key2Pay redirect) - fallback is enabled
        if (isset($_GET['result']) || isset($_GET['responsecode']) || isset($_GET['trackid'])) {
            // Log the parameters for debugging
            $this->log_to_file('Key2Pay Redirect: URL parameters detected - processing as fallback');

            // Process URL parameters as fallback
            $this->process_url_parameters_fallback($order);

            // Redirect to clean URL after processing
            $clean_url = $this->get_return_url($order);
            wp_redirect($clean_url);
            exit();
        }

        // Default messages based on order status (from webhooks or fallback)
        if ($order->has_status('pending')) {
            echo wpautop(wp_kses_post(__('Your order is awaiting payment confirmation from Key2Pay. We will update your order status once the payment is confirmed via our secure webhook system.', 'key2pay')));
        } elseif ($order->has_status('processing') || $order->has_status('completed')) {
            echo wpautop(wp_kses_post(__('Thank you! Your credit card payment has been confirmed and your order is being processed.', 'key2pay')));
        } elseif ($order->has_status('failed')) {
            // Get the specific error message for failed orders
            $failed_message = $this->get_user_friendly_error_message_for_failed_order($order);
            echo wpautop(wp_kses_post($failed_message));
        } else {
            echo wpautop(wp_kses_post(__('Thank you for your order. We will process your credit card payment shortly.', 'key2pay')));
        }
    }

    /**
     * Process URL parameters as fallback when webhooks fail or haven't processed yet
     *
     * @param WC_Order $order Order object.
     */
    private function process_url_parameters_fallback($order)
    {
        $result = isset($_GET['result']) ? sanitize_text_field($_GET['result']) : '';
        $response_code = isset($_GET['responsecode']) ? sanitize_text_field($_GET['responsecode']) : '';
        $track_id = isset($_GET['trackid']) ? sanitize_text_field($_GET['trackid']) : '';
        $response_description = isset($_GET['responsedescription']) ? sanitize_text_field($_GET['responsedescription']) : '';

        // Log for debugging
        $this->log_to_file('Key2Pay Fallback: Processing result=' . $result . ', response_code=' . $response_code . ', track_id=' . $track_id . ', description=' . $response_description);

        // Only process if order is still pending (webhook hasn't processed it yet)
        if ($order->has_status('pending')) {
            $numeric_code = $this->extract_numeric_code($response_code);

            $this->log_to_file('Key2Pay Fallback: Order #' . $order->get_id() . ' is pending, processing with code: ' . $numeric_code);

            // Process the payment result using the same logic as webhooks
            $this->process_payment_result($order, $numeric_code, '', $response_description);

            $this->log_to_file('Key2Pay Fallback: Order #' . $order->get_id() . ' status updated to: ' . $order->get_status());

            // Add order note about fallback processing
            $order->add_order_note(sprintf(__('Payment status processed via URL parameter fallback. Code: %s, Description: %s', 'key2pay'), $numeric_code, $response_description));

        } else {
            $this->log_to_file('Key2Pay Fallback: Order #' . $order->get_id() . ' already processed by webhook, skipping fallback processing.');
        }
    }


    /**
     * Handle webhook callbacks from Key2Pay.
     * This processes payment status updates from Key2Pay for credit card payments.
     *
     * Security: This is the ONLY way payment status is updated.
     * URL parameters are completely ignored for maximum security.
     *
     * Webhook format per Key2Pay documentation:
     * {
     *   "type": "valid",
     *   "result": "Processing",
     *   "responsecode": "9",
     *   "trackid": "123455",
     *   "merchantid": "TEST001",
     *   "redirectUrl": "https://api.key2payment.com/transaction/Redirect?ID=...",
     *   "token": "1d85ca154e754b4596128b00a5b21d1c",
     *   "error_code_tag": null,
     *   "error_text": null,
     *   "transactionid": "1001547"
     * }
     */
    public function handle_webhook_callback()
    {
        // Always log webhook attempts for debugging
        $this->log_to_file('Key2Pay Webhook: Attempt received');

        // Log request method and headers for debugging
        $this->log_to_file('Key2Pay Webhook: Request Method: ' . $_SERVER['REQUEST_METHOD']);
        $this->log_to_file('Key2Pay Webhook: Request Headers: ' . print_r(getallheaders(), true));

        // Get the raw POST data
        $raw_data = file_get_contents('php://input');

        // Log raw data length only (for debugging without exposing sensitive data)
        $this->log_to_file('Key2Pay Webhook: Raw data length: ' . strlen($raw_data) . ' characters');

        // Parse the webhook data
        $webhook_data = json_decode($raw_data, true);

        if (!$webhook_data) {
            $this->log_to_file('Key2Pay Webhook: Failed to parse JSON data: ' . $raw_data);

            if ($this->debug) {
                $this->log->error('Key2Pay Webhook: Failed to parse webhook data', array('source' => 'key2pay-redirect'));
            }
            status_header(400);
            exit();
        }

        // Log parsed data without sensitive information
        $safe_webhook_data = $webhook_data;
        if (isset($safe_webhook_data['card'])) {
            $safe_webhook_data['card'] = '[REDACTED]';
        }
        if (isset($safe_webhook_data['cardholder'])) {
            $safe_webhook_data['cardholder'] = '[REDACTED]';
        }
        if (isset($safe_webhook_data['authcode'])) {
            $safe_webhook_data['authcode'] = '[REDACTED]';
        }

        $this->log_to_file('Key2Pay Webhook: Parsed data: ' . print_r($safe_webhook_data, true));

        if ($this->debug) {
            $this->log->debug('Key2Pay Webhook: Received credit card payment data: ' . print_r($webhook_data, true), array('source' => 'key2pay-redirect'));
        }

        // Extract order information per Key2Pay documentation
        $type = isset($webhook_data['type']) ? $webhook_data['type'] : '';
        $result = isset($webhook_data['result']) ? $webhook_data['result'] : '';
        $response_code = isset($webhook_data['responsecode']) ? $webhook_data['responsecode'] : '';
        $track_id = isset($webhook_data['trackid']) ? $webhook_data['trackid'] : '';
        $merchant_id = isset($webhook_data['merchantid']) ? $webhook_data['merchantid'] : '';
        $transaction_id = isset($webhook_data['transactionid']) ? $webhook_data['transactionid'] : '';
        $error_code_tag = isset($webhook_data['error_code_tag']) ? $webhook_data['error_code_tag'] : '';
        $error_text = isset($webhook_data['error_text']) ? $webhook_data['error_text'] : '';

        // Log extracted fields
        $this->log_to_file('Key2Pay Webhook: Extracted fields - type: ' . $type . ', result: ' . $result . ', response_code: ' . $response_code . ', track_id: ' . $track_id . ', merchant_id: ' . $merchant_id . ', transaction_id: ' . $transaction_id);

        // Always use response_code for processing, as it contains the actual gateway response code
        $code_to_process = $response_code;

        $this->log_to_file('Key2Pay Webhook: Code to process: ' . $code_to_process);

        // Find the order by track ID
        $order = null;
        if ($track_id) {
            // Extract order ID from track_id (format: order_id_timestamp)
            $parts = explode('_', $track_id);
            if (count($parts) >= 2) {
                $order_id = $parts[0];
                $order = wc_get_order($order_id);
                $this->log_to_file('Key2Pay Webhook: Extracted order_id: ' . $order_id . ' from track_id: ' . $track_id);
            }
        }

        if (!$order) {
            $this->log_to_file('Key2Pay Webhook: Order not found for track_id: ' . $track_id);

            if ($this->debug) {
                $this->log->error('Key2Pay Webhook: Order not found for track_id: ' . $track_id, array('source' => 'key2pay-redirect'));
            }
            status_header(404);
            exit();
        }

        $this->log_to_file('Key2Pay Webhook: Found order #' . $order->get_id() . ' - current status: ' . $order->get_status());

        if ($this->debug) {
            $this->log->debug('Key2Pay Webhook: Processing credit card payment for order #' . $order->get_id(), array('source' => 'key2pay-redirect'));
        }

        // Process the payment status based on Key2Pay gateway response codes
        $this->process_payment_result($order, $code_to_process, $transaction_id, $error_text);

        // Log final status
        $this->log_to_file('Key2Pay Webhook: Final order status: ' . $order->get_status());

        // Always acknowledge the webhook
        status_header(200);
        exit();
    }



    /**
     * Process payment result based on Key2Pay gateway response codes for credit card payments.
     *
     * @param WC_Order $order Order object.
     * @param string   $result Payment result code.
     * @param string   $transaction_id Transaction ID.
     * @param string   $error_text Error text if any.
     */
    private function process_payment_result($order, $result, $transaction_id, $error_text)
    {
        // Extract the numeric code from the result, handling currency prefixes (e.g., "EGP9998" -> "9998")
        $numeric_code = $this->extract_numeric_code($result);

        if ($this->debug) {
            $this->log->debug('Key2Pay Credit Card: Processing response code: ' . $numeric_code . ' for order #' . $order->get_id(), array('source' => 'key2pay-redirect'));
        }

        // Get descriptive message for this status code
        $status_message = $this->get_status_code_message($numeric_code);

        // Handle different gateway response codes for credit card payments
        if ($this->is_approved_code($numeric_code)) {
            // 0 - Approved
            $order->payment_complete($transaction_id);
            $order->add_order_note(sprintf(__('Key2Pay credit card payment approved. Transaction ID: %s, Code: %s - %s', 'key2pay'), $transaction_id, $numeric_code, $status_message));

            if ($this->debug) {
                $this->log->debug('Key2Pay Credit Card: Order #' . $order->get_id() . ' marked as paid (Code: ' . $numeric_code . ')', array('source' => 'key2pay-redirect'));
            }
        } elseif ($this->is_insufficient_funds_code($numeric_code)) {
            // 51 - INSUFFICIENT FUNDS
            $order->update_status('failed', sprintf(__('Key2Pay credit card payment failed: %s. Code: %s, Error: %s', 'key2pay'), $this->get_status_code_message($numeric_code), $numeric_code, $error_text));

            if ($this->debug) {
                $this->log->debug('Key2Pay Credit Card: Order #' . $order->get_id() . ' failed - insufficient funds (Code: ' . $numeric_code . ')', array('source' => 'key2pay-redirect'));
            }
        } elseif ($this->is_do_not_honour_code($numeric_code)) {
            // 05 - DNH (Do not honour)
            $order->update_status('failed', sprintf(__('Key2Pay credit card payment failed: %s. Code: %s, Error: %s', 'key2pay'), $this->get_status_code_message($numeric_code), $numeric_code, $error_text));

            if ($this->debug) {
                $this->log->debug('Key2Pay Credit Card: Order #' . $order->get_id() . ' failed - do not honour (Code: ' . $numeric_code . ')', array('source' => 'key2pay-redirect'));
            }
        } elseif ($this->is_restricted_card_code($numeric_code)) {
            // 62 - RESTRICTED CARDS
            $order->update_status('failed', sprintf(__('Key2Pay credit card payment failed: %s. Code: %s, Error: %s', 'key2pay'), $this->get_status_code_message($numeric_code), $numeric_code, $error_text));

            if ($this->debug) {
                $this->log->debug('Key2Pay Credit Card: Order #' . $order->get_id() . ' failed - restricted card (Code: ' . $numeric_code . ')', array('source' => 'key2pay-redirect'));
            }
        } elseif ($this->is_invalid_transaction_code($numeric_code)) {
            // 12 - INVALID TRANSACTION
            $order->update_status('failed', sprintf(__('Key2Pay credit card payment failed: %s. Code: %s, Error: %s', 'key2pay'), $this->get_status_code_message($numeric_code), $numeric_code, $error_text));

            if ($this->debug) {
                $this->log->debug('Key2Pay Credit Card: Order #' . $order->get_id() . ' failed - invalid transaction (Code: ' . $numeric_code . ')', array('source' => 'key2pay-redirect'));
            }
        } elseif ($numeric_code === '9998') {
            // 9998 - TIMEOUT
            $order->update_status('failed', sprintf(__('Key2Pay credit card payment failed: %s. Code: %s, Error: %s', 'key2pay'), $this->get_status_code_message($numeric_code), $numeric_code, $error_text));

            if ($this->debug) {
                $this->log->debug('Key2Pay Credit Card: Order #' . $order->get_id() . ' failed - timeout (Code: ' . $numeric_code . ')', array('source' => 'key2pay-redirect'));
            }
        } elseif ($result === 'CAPTURED') {
            // Legacy support for CAPTURED status
            $order->payment_complete($transaction_id);
            $order->add_order_note(sprintf(__('Key2Pay credit card payment completed successfully. Transaction ID: %s', 'key2pay'), $transaction_id));

            if ($this->debug) {
                $this->log->debug('Key2Pay Credit Card: Order #' . $order->get_id() . ' marked as paid (CAPTURED)', array('source' => 'key2pay-redirect'));
            }
        } else {
            // Any other code not in the list - treat as approved (as per Key2Pay documentation)
            $order->payment_complete($transaction_id);
            $order->add_order_note(sprintf(__('Key2Pay credit card payment approved (unknown code). Transaction ID: %s, Code: %s - %s', 'key2pay'), $transaction_id, $numeric_code, $this->get_status_code_message($numeric_code)));

            if ($this->debug) {
                $this->log->debug('Key2Pay Credit Card: Order #' . $order->get_id() . ' marked as paid (unknown code: ' . $numeric_code . ')', array('source' => 'key2pay-redirect'));
            }
        }
    }

    /**
     * Check if the code indicates an approved transaction (0).
     *
     * @param string $code The response code.
     * @return bool True if approved.
     */
    private function is_approved_code($code)
    {
        return $code === '0';
    }

    /**
     * Check if the code indicates insufficient funds (51).
     *
     * @param string $code The response code.
     * @return bool True if insufficient funds.
     */
    private function is_insufficient_funds_code($code)
    {
        return $code === '51';
    }

    /**
     * Check if the code indicates do not honour (05).
     *
     * @param string $code The response code.
     * @return bool True if do not honour.
     */
    private function is_do_not_honour_code($code)
    {
        return $code === '05';
    }

    /**
     * Check if the code indicates restricted card (62).
     *
     * @param string $code The response code.
     * @return bool True if restricted card.
     */
    private function is_restricted_card_code($code)
    {
        return $code === '62';
    }

    /**
     * Check if the code indicates invalid transaction (12).
     *
     * @param string $code The response code.
     * @return bool True if invalid transaction.
     */
    private function is_invalid_transaction_code($code)
    {
        return $code === '12';
    }



    /**
     * Get descriptive error message for a specific status code.
     *
     * @param string $code The response code.
     * @return string The descriptive error message.
     */
    private function get_status_code_message($code)
    {
        switch ($code) {
            case '0':
                return __('Payment approved successfully.', 'key2pay');
            case '51':
                return __('Payment failed: Insufficient funds in the account.', 'key2pay');
            case '05':
                return __('Payment failed: Do not honour - the transaction was declined by the bank.', 'key2pay');
            case '62':
                return __('Payment failed: Restricted card - this card cannot be used for this transaction.', 'key2pay');
            case '12':
                return __('Payment failed: Invalid transaction - the transaction details are not valid.', 'key2pay');
            case '9998':
                return __('Payment failed: Transaction timeout - the request took too long to process.', 'key2pay');
            default:
                return __('Payment processed with unknown response code.', 'key2pay');
        }
    }

    /**
     * Get user-friendly error message for display to customers.
     *
     * @param string $code The response code.
     * @return string The user-friendly error message.
     */
    private function get_user_friendly_error_message($code)
    {
        switch ($code) {
            case '0':
                return __('Your payment has been approved successfully!', 'key2pay');
            case '51':
                return __('Sorry, your payment could not be processed due to insufficient funds. Please check your account balance and try again.', 'key2pay');
            case '05':
                return __('Sorry, your payment was declined by your bank. Please contact your bank or try a different payment method.', 'key2pay');
            case '62':
                return __('Sorry, this card cannot be used for this transaction. Please try a different card or contact your bank.', 'key2pay');
            case '12':
                return __('Sorry, there was an issue with the transaction details. Please check your information and try again.', 'key2pay');
            case '9998':
                return __('Sorry, the payment request timed out. Please try again or contact support if the problem persists.', 'key2pay');
            default:
                return __('Sorry, there was an unexpected issue with your payment. Please try again or contact support.', 'key2pay');
        }
    }

    /**
     * Get user-friendly error message for a failed order by checking order notes.
     *
     * @param WC_Order $order Order object.
     * @return string The user-friendly error message.
     */
    private function get_user_friendly_error_message_for_failed_order($order)
    {
        // Get the latest order notes to find the error code
        $notes = wc_get_order_notes(array(
            'order_id' => $order->get_id(),
            'type' => 'customer',
            'limit' => 10
        ));

        // Look for Key2Pay error codes in the notes
        foreach ($notes as $note) {
            $content = $note->content;

            // Check for specific error codes in the note content
            if (strpos($content, 'Code: 51') !== false) {
                return $this->get_user_friendly_error_message('51');
            } elseif (strpos($content, 'Code: 05') !== false) {
                return $this->get_user_friendly_error_message('05');
            } elseif (strpos($content, 'Code: 62') !== false) {
                return $this->get_user_friendly_error_message('62');
            } elseif (strpos($content, 'Code: 12') !== false) {
                return $this->get_user_friendly_error_message('12');
            } elseif (strpos($content, 'Code: 9998') !== false) {
                return $this->get_user_friendly_error_message('9998');
            }
        }

        // Default message if no specific error code found
        return __('Your credit card payment was not successful. Please try again or contact support if you believe this is an error.', 'key2pay');
    }

    /**
     * Extract numeric code from response code, handling currency prefixes.
     *
     * @param string $code The response code (e.g., "EGP9998", "USD51", "9998").
     * @return string The numeric code (e.g., "9998", "51", "9998").
     */
    private function extract_numeric_code($code)
    {
        // Remove currency prefixes (3 letters) and extract numeric part
        // Examples: "EGP9998" -> "9998", "USD51" -> "51", "9998" -> "9998"
        if (preg_match('/^[A-Z]{3}(\d+)$/', $code, $matches)) {
            return $matches[1];
        }

        // If no currency prefix, return as is
        return $code;
    }

    /**
     * Process a refund.
     *
     * @param int    $order_id Order ID.
     * @param float  $amount Refund amount.
     * @param string $reason Refund reason.
     * @return bool True if refund was successful, false otherwise.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        // Redirect gateway refunds would use the same refund API as other methods
        $main_gateway = new WC_Key2Pay_Gateway();
        return $main_gateway->process_refund($order_id, $amount, $reason);
    }

    /**
     * Build a proper API URL by handling trailing slashes.
     *
     * @param string $endpoint The API endpoint (e.g., '/PaymentToken/Create').
     * @return string The complete API URL.
     */
    private function build_api_url($endpoint)
    {
        $base_url = rtrim($this->api_base_url, '/');
        $endpoint = ltrim($endpoint, '/');
        return $base_url . '/' . $endpoint;
    }

    /**
     * Validate API base URL field.
     *
     * @param string $key Field key.
     * @param array  $field Field data.
     * @return bool
     */
    public function validate_api_base_url($key, $field)
    {
        $value = $this->get_option($key);

        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_URL)) {
            WC_Admin_Settings::add_error(__('API Base URL must be a valid URL.', 'key2pay'));
            return false;
        }

        return true;
    }

    /**
     * Check if the gateway is available for use.
     *
     * @return bool
     */
    public function is_available()
    {
        $this->log_to_file('Key2Pay Debug: Credit card gateway is_available() called');

        // Check if gateway is enabled
        if ('yes' !== $this->enabled) {
            $this->log_to_file('Key2Pay Debug: Credit card gateway disabled');
            return false;
        }

        // Check if credentials are provided
        if (empty($this->merchant_id) || empty($this->password)) {
            $this->log_to_file('Key2Pay Debug: Missing credentials - merchant_id: ' . (empty($this->merchant_id) ? 'empty' : 'set') . ', password: ' . (empty($this->password) ? 'empty' : 'set'));

            if (is_admin() && current_user_can('manage_woocommerce') && (! defined('DOING_AJAX') || ! DOING_AJAX)) {
                wc_print_notice(sprintf(__('Key2Pay credit card gateway is enabled but credentials are not configured. %sClick here to configure.%s', 'key2pay'), '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id) . '">', '</a>'), 'error');
            }
            return false;
        }

        $this->log_to_file('Key2Pay Debug: Credit card gateway available - all checks passed');
        return parent::is_available();
    }

    /**
     * Log messages to a custom log file.
     *
     * @param string $message The message to log.
     */
    private function log_to_file($message)
    {
        error_log('[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL, 3, $this->custom_log_file);
    }
}
