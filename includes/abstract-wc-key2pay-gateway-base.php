<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Abstract Base Class for Key2Pay WooCommerce Payment Gateways.
 *
 * Provides common functionality, properties, and a shared structure
 * for all Key2Pay payment methods (e.g., Redirect, Thai QR).
 *
 * @extends WC_Payment_Gateway
 */
abstract class WC_Key2Pay_Gateway_Base extends WC_Payment_Gateway
{
    // These properties are declared here for clarity and potential type hinting,
    // but their values are typically set in the concrete child's constructor
    // BEFORE parent::__construct() is called.
    public $id;
    public $icon;
    public $has_fields;
    public $method_title;
    public $method_description;
    public $title;        // Loaded from settings
    public $description;  // Loaded from settings
    public $enabled;      // Loaded from settings
    public $api_base_url; // Loaded from settings
    public $merchant_id;  // Loaded from settings
    public $password;     // Loaded from settings
    public $auth_type;    // Loaded from settings
    public $debug;        // Loaded from settings
    public $log;
    // public $form_fields; // Managed by WC_Settings_API - DO NOT redeclare
    public WC_Key2Pay_Auth $auth_handler;
    public $disable_url_fallback; // Loaded from settings
    public $custom_log_file;

    /**
     * Default API Base URL for Key2Pay.
     */
    public const DEFAULT_API_BASE_URL = 'https://api.key2payment.com/';

    /**
     * Default language for payment page.
     */
    public const DEFAULT_LANGUAGE = 'en';

    /**
     * Abstract constant for payment method type.
     * Child classes MUST define this (e.g., 'CARD', 'THAI_DEBIT').
     */
    public const PAYMENT_METHOD_TYPE = '';

    /**
     * STATUS CODES
     */
    public const CODE_INVALID_CREDENTIALS = '1000';

    public const CODE_TIMEOUT = '9998';

    public const CODE_APPROVED = '0';
    public const CODE_CAPTURED = 'CAPTURED';

    public const CODE_INSUFFICIENT_FUNDS  = '51';
    public const CODE_DO_NOT_HONOUR       = '05';
    public const CODE_RESTRICTED_CARD     = '62';
    public const CODE_INVALID_TRANSACTION = '12';

    public const CODE_DEBIT_PENDING = '9';
    public const CODE_DEBIT_FAILED  = '6';

    /**
     * Constructor for the gateway base.
     *
     * IMPORTANT: This constructor does NOT call parent::__construct().
     * The `WC_Payment_Gateway` constructor is called by the concrete child class
     * when it is instantiated by WooCommerce. This ensures the correct
     * order of `init_form_fields()` and `init_settings()`.
     */
    public function __construct()
    {
        // Initialize logger BEFORE anything else that might log.
        // This is safe to do here as it doesn't depend on WC_Payment_Gateway init.
        $this->log             = wc_get_logger();
        $this->custom_log_file = WP_CONTENT_DIR . '/uploads/key2pay-gateway.log'; // Shared log file

        // Common hooks that use $this->id. $this->id MUST be set by the child
        // BEFORE its call to parent::__construct() for these hooks to be specific.
        // They are safe to define here once for all children.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
        add_action('woocommerce_thankyou_' . $this->id, [$this, 'thankyou_page']);
        add_action('woocommerce_api_' . strtolower($this->id), [$this, 'handle_webhook_callback']);
        add_action('woocommerce_admin_field_api_base_url', [$this, 'validate_api_base_url']);

        // All other properties like $this->title, $this->description, $this->enabled,
        // and credentials will be loaded in the concrete child's constructor
        // AFTER it calls parent::__construct() and init_settings() has run.
    }

    /**
     * Setup the authentication handler.
     * This initializes the authentication handler based on the auth type.
     * Child classes should call this after setting $this->auth_type and credentials.
     */
    public function setup_authentication_handler(): WC_Key2Pay_Auth
    {
        try {
                                                                                         // Initialize the authentication handler based on the auth type.
            $this->auth_handler = new WC_Key2Pay_Auth(WC_Key2Pay_Auth::AUTH_TYPE_BASIC); // Only Basic Auth is supported in this base class.
            $this->auth_handler->set_credentials([
                'merchant_id' => $this->merchant_id,
                'password'    => $this->password,
            ]);
            $this->auth_handler->set_debug($this->debug);
        } catch (Exception $e) {
            $this->debug_log('Key2Pay Error: Failed to initialize auth handler: ' . $e->getMessage());
        }

        return $this->auth_handler;
    }

    /**
     * Initialize Common Gateway Settings Form Fields.
     * This defines the base fields that all Key2Pay gateways share.
     * Child classes should override this and call parent::init_form_fields()
     * to inherit these fields and then add their specific fields.
     */
    public function init_form_fields()
    {
        $auth_types = WC_Key2Pay_Auth::get_auth_types();

        $this->form_fields = [
            'enabled'              => [
                'title'   => __('Enable/Disable', 'key2pay'),
                'type'    => 'checkbox',
                'label'   => __('Enable Key2Pay Payment Method', 'key2pay'),
                'default' => 'no',
            ],
            'title'                => [
                'title'       => __('Title', 'key2pay'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'key2pay'),
                'default'     => $this->method_title, // Uses the child's method_title
                'desc_tip'    => true,
            ],
            'description'          => [
                'title'       => __('Description', 'key2pay'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'key2pay'),
                'default'     => $this->method_description, // Uses the child's method_description
                'desc_tip'    => true,
            ],
            'auth_section'         => [
                'title'       => __('Key2Pay API Authentication', 'key2pay'),
                'type'        => 'title',
                'description' => __('Configure how to authenticate with the Key2Pay API. Consult your Key2Pay account manager for the correct method.', 'key2pay'),
            ],
            'auth_type'            => [
                'title'       => __('Authentication Method', 'key2pay'),
                'type'        => 'select',
                'description' => __('Select the authentication method for Key2Pay API requests.', 'key2pay'),
                'options'     => $auth_types,
                'default'     => WC_Key2Pay_Auth::AUTH_TYPE_BASIC,
                'desc_tip'    => true,
            ],
            'merchant_id'          => [
                'title'       => __('Merchant ID', 'key2pay'),
                'type'        => 'text',
                'description' => __('Your Key2Pay Merchant ID. Used for Basic Auth, and might be required for HMAC Signed.', 'key2pay'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'password'             => [
                'title'       => __('Password', 'key2pay'),
                'type'        => 'password',
                'description' => __('Your Key2Pay API Password. Used for Basic Auth.', 'key2pay'),
                'default'     => '',
                'desc_tip'    => true,
            ],
            'api_base_url'         => [
                'title'             => __('API Base URL', 'key2pay'),
                'type'              => 'text',
                'description'       => __('Key2Pay API base URL. Use sandbox for testing, production for live payments. Ensure it ends with a slash if required by Key2Pay (e.g., https://api.key2payment.com/).', 'key2pay'),
                'default'           => self::DEFAULT_API_BASE_URL,
                'desc_tip'          => true,
                'custom_attributes' => [
                    'placeholder' => 'https://api.key2payment.com/',
                ],
            ],
            'debug'                => [
                'title'       => __('Debug Log', 'key2pay'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'key2pay'),
                'default'     => 'no',
                'description' => sprintf(__('Log Key2Pay payment events to <code>%s</code>.', 'key2pay'), esc_html(WP_CONTENT_DIR . '/uploads/key2pay-gateway.log')), // Direct path here for description
            ],
            'security_section'     => [
                'title'       => __('Security Settings', 'key2pay'),
                'type'        => 'title',
                'description' => __('Configure security settings for payment processing.', 'key2pay'),
            ],
            'disable_url_fallback' => [
                'title'       => __('Disable URL Parameter Fallback', 'key2pay'),
                'type'        => 'checkbox',
                'label'       => __('Disable URL parameter processing for maximum security', 'key2pay'),
                'default'     => 'yes',
                'description' => __('When enabled, only webhooks will be used for payment status updates. URL parameters will be completely ignored. Recommended for production environments.', 'key2pay'),
            ],
        ];
    }

    /**
     * Payment fields - just show description since this is redirect-based by default.
     * Child classes will override this to add specific input fields.
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        } else {
            echo wpautop(wp_kses_post(__('This payment method requires no extra fields on checkout.', 'key2pay')));
        }
    }

    /**
     * Validate fields (no custom fields needed for redirect by default).
     * Child classes should override this to add specific validation.
     *
     * @return bool
     */
    public function validate_fields()
    {
        return true;
    }

    /**
     * Prepare common request data for Key2Pay payment request.
     * This method is used by all Key2Pay gateways to prepare the order data
     * that will be sent to Key2Pay for processing.
     *
     * @param WC_Order $order The WooCommerce order object.
     * @param array   $data_to_merge Additional data to merge into the request.
     * @return array An array of order data to be sent to Key2Pay.
     */
    public function prepare_request_data($order, $data_to_merge = [])
    {

        // Ensure the order is a valid WC_Order object
        if (! $order instanceof WC_Order) {
            throw new Exception('Invalid order object provided to prepare_request_data.');
        }

        // Prepare common order data for Key2Pay payment request.
        $amount      = (float) $order->get_total();
        $currency    = $order->get_currency();
        $customer_ip = WC_Geolocation::get_ip_address();
        $return_url  = $this->get_return_url($order);
        $server_url  = home_url('/wc-api/' . strtolower($this->id));

        $request_data = [
            'payment_method'       => ['type' => self::PAYMENT_METHOD_TYPE], // Should be set by child classes
            'trackid'              => $order->get_id() . '_' . time(),
            'bill_currencycode'    => $currency,
            'bill_amount'          => $amount,
            'bill_country'         => $order->get_billing_country() ?: '',
            'bill_customerip'      => $customer_ip,
            'bill_email'           => $order->get_billing_email(),
            'returnUrl'            => $return_url,
            'serverUrl'            => $server_url,
            'productdesc'          => sprintf(__('Order #%s from %s', 'key2pay'), $order->get_order_number(), get_bloginfo('name')),
            'returnUrl_on_failure' => add_query_arg('k2p-status', 'failed', $order->get_checkout_payment_url(false)),
            'lang'                 => self::DEFAULT_LANGUAGE,
            'bill_phone'           => $order->get_billing_phone() ?: '',
            'bill_city'            => $order->get_billing_city() ?: '',
            'bill_state'           => $order->get_billing_state() ?: '',
            'bill_address'         => $order->get_billing_address_1() ?: '',
            'bill_zip'             => $order->get_billing_postcode(),
        ];

        // Merge any additional data provided by child classes
        if (! empty($data_to_merge) && is_array($data_to_merge)) {
            $request_data = array_merge($request_data, $data_to_merge);
        }

        // Add authentication data to the request.
        $request_data = $this->auth_handler->add_auth_to_body($request_data);

        return $request_data;
    }

    /**
     * Process the payment. Abstract as implementation differs per gateway type.
     *
     * @param int $order_id Order ID.
     * @return array An array with 'result' and 'redirect' keys.
     */
    public function process_payment($order_id)
    {
        // This method should be implemented by child classes.
        // It will handle the actual payment processing logic.
        // For now, we throw an exception to enforce implementation.
        throw new Exception('process_payment() must be implemented by child classes.');
    }

    /**
     * Process a refund.
     * This method is abstract as it might involve different APIs or parameters
     * depending on the payment method type, although often it's a common refund API.
     * For now, we'll delegate it to a potential 'main' gateway or make it concrete if common.
     * @see https://key2pay.readme.io/reference/refund
     *
     * @param int    $order_id Order ID.
     * @param float  $amount Refund amount.
     * @param string $reason Refund reason.
     * @return bool True if refund was successful, false otherwise.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        // Retrieve transaction ID from order meta
        $order          = wc_get_order($order_id);
        $transaction_id = $order->get_meta('_key2pay_transaction_id');
        $amount         = (float) $order->get_total();
        $currency       = $order->get_currency();
        $email          = $order->get_billing_email();
        $endpoint       = $this->build_api_url('/transaction/refund');

        if (empty($transaction_id)) {
            $this->debug_log('Key2Pay refund: No transaction ID found for order #' . $order_id);
            return false;
        }

        // Prepare refund data
        $refund_data = [
            'transactionid'     => $transaction_id,
            'tranid'            => $transaction_id,
            'trackid'           => $email, // Using email as track ID for refund
            'bill_amount'       => $amount,
            'bill_currencycode' => $currency,
            'reason'            => $reason,
        ];
        $refund_data = $this->auth_handler->add_auth_to_body($refund_data);

        $headers = ['Content-Type' => 'application/json'];
        $headers = array_merge($headers, $this->auth_handler->get_auth_headers());

        $this->debug_log('Key2Pay refund Request: Preparing to send refund for order #' . $order_id);
        $this->debug_log('Key2Pay refund Request: API URL: ' . $endpoint);

        $response = wp_remote_post(
            $endpoint,
            [
                'method'    => 'POST',
                'headers'   => $headers,
                'body'      => json_encode($refund_data),
                'timeout'   => 60,
                'sslverify' => true,
            ]
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $this->debug_log(sprintf('Key2Pay refund API Request Failed for order #%s: %s', $order_id, $error_message));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        $this->debug_log(sprintf('Key2Pay refund API Response for order #%s: %s', $order_id, print_r($data, true)));

        // Key2Pay refund specific success/failure logic
        // Note: Documentation is not provided yet, so this is based on common patterns.
        // @see https://key2pay.readme.io/reference/refund
        if (isset($data->type) && 'valid' === $data->type && isset($data->result) && ($data->result === 'CAPTURED' || $data->result === 'Success' || $data->responsecode == self::CODE_APPROVED)) {
            $order->add_order_note(sprintf(__('Key2Pay refund successful. Amount: %s. Reason: %s. Transaction ID: %s', 'key2pay'), wc_price($amount), $reason, $transaction_id));
            $this->debug_log('Key2Pay refund: Order #' . $order_id . ' refund successful.');
            return true;
        } else {
            $error_message = isset($data->error_text) ? $data->error_text : __('An unknown error occurred during Key2Pay refund.', 'key2pay');
            $order->add_order_note(sprintf(__('Key2Pay refund failed. Amount: %s. Reason: %s. Error: %s', 'key2pay'), wc_price($amount), $reason, $error_message));
            $this->debug_log(sprintf('Key2Pay refund Failed for order #%s: %s', $order_id, $error_message));
            return false;
        }
    }

    /**
     * Handle webhook callbacks from Key2Pay.
     * This processes payment status updates from Key2Pay for payments.
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
        $this->debug_log('Key2Pay Webhook: Attempt received for ' . $this->id);

        // Log request method and headers for debugging
        $this->debug_log('Key2Pay Webhook: Request Method: ' . $_SERVER['REQUEST_METHOD']);
        $this->debug_log('Key2Pay Webhook: Request Headers: ' . print_r(getallheaders(), true));

        // Get the raw POST data
        $raw_data = file_get_contents('php://input');

        // Log raw data length only (for debugging without exposing sensitive data)
        $this->debug_log('Key2Pay Webhook: Raw data length: ' . strlen($raw_data) . ' characters');

        // Parse the webhook data
        $webhook_data = json_decode($raw_data, true);

        if (! $webhook_data) {
            $this->debug_log('Key2Pay Webhook: Failed to parse JSON data: ' . $raw_data);
            wp_send_json_error(['message' => __('Invalid webhook data received.', 'key2pay')]);
            exit();
        }

        // Log parsed data without sensitive information
        $safe_webhook_data = $this->redact_sensitive_data($webhook_data);
        $this->debug_log('Key2Pay Webhook: Parsed data: ' . print_r($safe_webhook_data, true));

        // Extract order information per Key2Pay documentation
        // @see https://key2pay.readme.io/reference/webhooks-copy
        $type           = isset($webhook_data['type']) ? $webhook_data['type'] : '';
        $result         = isset($webhook_data['result']) ? $webhook_data['result'] : '';
        $response_code  = isset($webhook_data['responsecode']) ? $webhook_data['responsecode'] : '';
        $track_id       = isset($webhook_data['trackid']) ? $webhook_data['trackid'] : '';
        $transaction_id = isset($webhook_data['transactionid']) ? $webhook_data['transactionid'] : '';
        $token          = isset($webhook_data['token']) ? $webhook_data['token'] : '';
        $token          = ! empty($token) ? $token : (isset($webhook_data['udf4']) ? $webhook_data['udf4'] : ''); // Fallback to udf4 if token is not set
        $error_text     = isset($webhook_data['error_text']) ? $webhook_data['error_text'] : '';

        $this->debug_log('Key2Pay Webhook: Extracted fields - type: ' . $type . ', result: ' . $result . ', response_code: ' . $response_code . ', track_id: ' . $track_id . ', transaction_id: ' . $transaction_id);

        // Always use response_code for processing, as it contains the actual gateway response code
        $code_to_process = $response_code;
        $this->debug_log('Key2Pay Webhook: Code to process: ' . $code_to_process);

        // Find the order by track ID
        $order = null;
        if ($track_id) {
            // Extract order ID from track_id (format: order_id_timestamp)
            $parts = explode('_', $track_id);
            if (count($parts) >= 2) {
                $order_id = $parts[0];
                $order    = wc_get_order($order_id);
                $this->debug_log('Key2Pay Webhook: Extracted order_id: ' . $order_id . ' from track_id: ' . $track_id);
            }
        }

        // Order not found or track_id is empty
        if (! $order) {
            $this->debug_log('Key2Pay Webhook: Order not found for track_id: ' . $track_id);
            wp_send_json_error(['message' => __('Order not found for the provided track_id.', 'key2pay')]);
            exit();
        }

        $this->debug_log('Key2Pay Webhook: Found order #' . $order->get_id() . ' - current status: ' . $order->get_status());

        // [!] Make sure the token matches the order's token
        // This is a security measure to ensure the webhook is for the correct order.
        if ($token && $order->get_meta('_key2pay_token') !== $token) {
            $this->debug_log('Key2Pay Webhook: Token mismatch for order #' . $order->get_id() . '. Expected: ' . $order->get_meta('_key2pay_token') . ', Received: ' . $token);
            wp_send_json_error(['message' => __('Token mismatch for the provided order id. Please try again.', 'key2pay')]);
            exit();
        }

        // Process the payment status based on Key2Pay gateway response codes
        $this->process_payment_result($order, $code_to_process, $transaction_id, $error_text);

        // Log final status
        $this->debug_log('Key2Pay Webhook: Final order status: ' . $order->get_status());

        // Always acknowledge the webhook
        wp_send_json_success([
            'message' => __('Webhook processed successfully.', 'key2pay'),
        ]);
        exit();
    }

    /**
     * Fallback handler to process payment status from URL parameters
     * when the webhook has failed or hasn’t run yet.
     *
     * This method parses those parameters and processes the payment result,
     * but only if the order is still in a 'pending' state.
     *
     * @param WC_Order $order The WooCommerce order object.
     */
    protected function process_url_parameters_fallback($order)
    {
        $result               = ! empty($_GET['result']) ? sanitize_text_field($_GET['result']) : '';
        $response_code        = ! empty($_GET['responsecode']) ? sanitize_text_field($_GET['responsecode']) : '';
        $track_id             = ! empty($_GET['trackid']) ? sanitize_text_field($_GET['trackid']) : '';
        $response_description = ! empty($_GET['responsedescription']) ? sanitize_text_field($_GET['responsedescription']) : '';

        $this->debug_log('Key2Pay Fallback: Processing result=' . $result . ', response_code=' . $response_code . ', track_id=' . $track_id . ', description=' . $response_description);

        // Only continue if the order is still pending (i.e., webhook hasn’t updated it yet)
        if ($order->has_status('pending')) {
            $status_code = $this->extract_status_code($response_code);

            $this->debug_log('Key2Pay Fallback: Order #' . $order->get_id() . ' is pending, processing with code: ' . $status_code);

            // Simulate the webhook behavior using the parsed values
            $this->process_payment_result($order, $status_code, '', $response_description);

            $this->debug_log('Key2Pay Fallback: Order #' . $order->get_id() . ' status updated to: ' . $order->get_status());

            // Add a note to the order to indicate fallback processing was used
            $order->add_order_note(sprintf(
                __('Payment status processed via URL parameter fallback. [Code: %s], Description: %s', 'key2pay'),
                $status_code,
                $response_description
            ));
        } else {
            $this->debug_log('Key2Pay Fallback: Order #' . $order->get_id() . ' already processed by webhook, skipping fallback processing.');
        }
    }

    /**
     * Process payment result based on Key2Pay gateway response codes.
     * This method is common and can remain in the base class.
     *
     * @param WC_Order $order Order object.
     * @param string   $result Payment result code.
     * @param string   $transaction_id Transaction ID.
     * @param string   $error_text Error text if any.
     */
    protected function process_payment_result($order, $result, $transaction_id, $error_text)
    {
        $status_code    = $this->extract_status_code($result);
        $status_message = $this->get_status_code_message($status_code);

        $this->debug_log('Key2Pay Gateway: Processing response code: ' . $status_code . ' for order #' . $order->get_id());

        switch ($status_code) {
            case self::CODE_APPROVED:
                $order->payment_complete($transaction_id);
                $order->add_order_note(sprintf(__('Key2Pay payment approved. Transaction ID: %s, [Code: %s] - %s', 'key2pay'), $transaction_id, $status_code, $status_message));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' marked as paid (Code: ' . $status_code . ')');
                break;
            case self::CODE_INSUFFICIENT_FUNDS:
                $order->update_status('failed', sprintf(__('Key2Pay payment failed: %s. [Code: %s], Error: %s', 'key2pay'), $this->get_status_code_message($status_code), $status_code, $error_text));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' failed - insufficient funds (Code: ' . $status_code . ')');
                break;
            case self::CODE_DO_NOT_HONOUR:
                $order->update_status('failed', sprintf(__('Key2Pay payment failed: %s. [Code: %s], Error: %s', 'key2pay'), $this->get_status_code_message($status_code), $status_code, $error_text));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' failed - do not honour (Code: ' . $status_code . ')');
                break;
            case self::CODE_RESTRICTED_CARD:
                $order->update_status('failed', sprintf(__('Key2Pay payment failed: %s. [Code: %s], Error: %s', 'key2pay'), $this->get_status_code_message($status_code), $status_code, $error_text));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' failed - restricted card (Code: ' . $status_code . ')');
                break;
            case self::CODE_INVALID_TRANSACTION:
                $order->update_status('failed', sprintf(__('Key2Pay payment failed: %s. [Code: %s], Error: %s', 'key2pay'), $this->get_status_code_message($status_code), $status_code, $error_text));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' failed - invalid transaction (Code: ' . $status_code . ')');
                break;
            case self::CODE_TIMEOUT:
                $order->update_status('failed', sprintf(__('Key2Pay payment failed: %s. [Code: %s], Error: %s', 'key2pay'), $this->get_status_code_message($status_code), $status_code, $error_text));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' failed - timeout (Code: ' . $status_code . ')');
                break;
            case self::CODE_DEBIT_PENDING:
                // Thai QR initial processing, treat as pending
                $order->update_status('pending', sprintf(__('Key2Pay payment is processing. Transaction ID: %s, [Code: %s] - %s', 'key2pay'), $transaction_id, $status_code, $status_message));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' marked as pending (Processing)', ['source' => $this->id]);
                break;
            case self::CODE_DEBIT_FAILED:
                // Thai QR failed, treat as failed
                $order->update_status('failed', sprintf(__('Key2Pay payment failed: %s. [Code: %s], Error: %s', 'key2pay'), $this->get_status_code_message($status_code), $status_code, $error_text));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' failed', ['source' => $this->id]);
                break;
            case self::CODE_CAPTURED:
                $order->payment_complete($transaction_id);
                $order->add_order_note(sprintf(__('Key2Pay payment completed successfully. Transaction ID: %s, [Code: %s] - %s', 'key2pay'), $transaction_id, $status_code, $status_message));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' marked as paid (CAPTURED)', ['source' => $this->id]);
                break;
            default:
                // Any other code not in the list, treat as pending or failed based on documentation.
                // Current interpretation treats others as approved, which might need fine-tuning with Key2Pay docs.
                // For now, keeping consistent with previous logic.
                $order->payment_complete($transaction_id);
                $order->add_order_note(sprintf(__('Key2Pay payment processed with unknown response code. Transaction ID: %s, [Code: %s] - %s', 'key2pay'), $transaction_id, $status_code, $status_message));
                $this->debug_log('Key2Pay Payment: Order #' . $order->get_id() . ' marked as paid (unknown code: ' . $status_code . ')', ['source' => $this->id]);
                break;
        }
    }

    /**
     * Output for the order received page.
     * Handles URL fallback logic and messages based on order status.
     * This method is common and can remain in the base class.
     *
     * @param int $order_id Order ID.
     */
    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);

        if (! $order) {
            return;
        }

        /**
         * Check if the fallback using URL parameters is disabled.
         *
         * When a payment fails or the webhook hasn't been processed yet,
         * Key2Pay may redirect the user to the "Thank You" page with
         * status information in the URL (e.g. ?result=Not%20Successful&responsecode=EGP9998&trackid=12345).
         *
         * This fallback method helps show a status message even if the webhook is delayed.
         *
         * If the "Disable Fallback" option is enabled in the admin settings,
         * we ignore these URL parameters and only show the status based on the webhook.
         */
        if ($this->disable_url_fallback) {
            // URL parameter fallback is disabled, only show status messages
            if ($order->has_status('pending')) {
                echo wpautop(wp_kses_post(__('Your order is awaiting payment confirmation from Key2Pay. We will update your order status once the payment is confirmed via our secure webhook system.', 'key2pay')));
            } elseif ($order->has_status('processing') || $order->has_status('completed')) {
                echo wpautop(wp_kses_post(__('Thank you! Your payment has been confirmed and your order is being processed.', 'key2pay')));
            } elseif ($order->has_status('failed')) {
                echo wpautop(wp_kses_post($this->get_user_friendly_error_message_for_failed_order($order)));
            } else {
                echo wpautop(wp_kses_post(__('Thank you for your order. We will process your payment shortly.', 'key2pay')));
            }
            return;
        }

        // Check if we have URL parameters from the Key2Pay Credit Card
        // Since fallback is enabled, we process them and redirect to clean URL.
        if (isset($_GET['result']) || isset($_GET['responsecode']) || isset($_GET['trackid'])) {
            $this->debug_log('Key2Pay Gateway: URL parameters detected - Processing as fallback for order #' . $order_id);
            $this->process_url_parameters_fallback($order);

            // Redirect to clean URL after processing to remove parameters
            $clean_url = $this->get_return_url($order);
            wp_redirect($clean_url);
            exit();
        }

        // Display messages based on order status (from webhooks or fallback)
        if ($order->has_status('pending')) {
            echo wpautop(wp_kses_post(__('Your order is awaiting payment confirmation from Key2Pay. We will update your order status once the payment is confirmed via our secure webhook system.', 'key2pay')));
        } elseif ($order->has_status('processing') || $order->has_status('completed')) {
            echo wpautop(wp_kses_post(__('Thank you! Your payment has been confirmed and your order is being processed.', 'key2pay')));
        } elseif ($order->has_status('failed')) {
            echo wpautop(wp_kses_post($this->get_user_friendly_error_message_for_failed_order($order)));
        } else {
            echo wpautop(wp_kses_post(__('Thank you for your order. We will process your payment shortly.', 'key2pay')));
        }
    }

    /**
     * Get descriptive error message for a specific status code.
     *
     * @param string $code The response code.
     * @return string The descriptive error message.
     */
    protected function get_status_code_message($code)
    {
        $code = $this->extract_status_code($code);

        switch ($code) {
            case self::CODE_INVALID_CREDENTIALS:
                return __('Payment failed: Invalid credentials. Please check your merchant ID and password.', 'key2pay');
            case self::CODE_APPROVED:
                return __('Payment approved successfully.', 'key2pay');
            case self::CODE_INSUFFICIENT_FUNDS:
                return __('Payment failed: Insufficient funds in the account.', 'key2pay');
            case self::CODE_DO_NOT_HONOUR:
                return __('Payment failed: Do not honour. The transaction was declined by the bank.', 'key2pay');
            case self::CODE_RESTRICTED_CARD:
                return __('Payment failed: Restricted card. This card cannot be used for this transaction.', 'key2pay');
            case self::CODE_INVALID_TRANSACTION:
                return __('Payment failed: Invalid transaction. The transaction details are not valid.', 'key2pay');
            case self::CODE_TIMEOUT:
                return __('Payment failed: Transaction timeout. The request took too long to process.', 'key2pay');
            case self::CODE_DEBIT_PENDING:
                return __('Payment processing: Awaiting confirmation.', 'key2pay');
            case self::CODE_DEBIT_FAILED:
                return __('Payment failed: The transaction was not completed.', 'key2pay');
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
    protected function get_user_friendly_error_message($code)
    {
        $code = $this->extract_status_code($code);

        switch ($code) {
            case self::CODE_APPROVED:
                return __('Your payment has been approved successfully!', 'key2pay');
            case self::CODE_INSUFFICIENT_FUNDS:
                return __('Sorry, your payment could not be processed due to insufficient funds. Please check your account balance and try again.', 'key2pay');
            case self::CODE_DO_NOT_HONOUR:
                return __('Sorry, your payment was declined by your bank. Please contact your bank or try a different payment method.', 'key2pay');
            case self::CODE_RESTRICTED_CARD:
                return __('Sorry, this card cannot be used for this transaction. Please try a different card or contact your bank.', 'key2pay');
            case self::CODE_INVALID_TRANSACTION:
                return __('Sorry, there was an issue with the transaction details. Please check your information and try again.', 'key2pay');
            case self::CODE_TIMEOUT:
                return __('Sorry, the payment request timed out. Please try again or contact support if the problem persists.', 'key2pay');
            case self::CODE_DEBIT_PENDING:
                return __('Sorry, your payment is still processing. Please wait for confirmation.', 'key2pay');
            case self::CODE_DEBIT_FAILED:
                return __('Sorry, your payment could not be processed. Please try again or contact support.', 'key2pay');
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
    protected function get_user_friendly_error_message_for_failed_order($order)
    {
        $order_notes = wc_get_order_notes([
            'order_id' => $order->get_id(),
            'type'     => 'customer',
            'limit'    => 10,
        ]);

        $error_codes = [
            self::CODE_INVALID_CREDENTIALS,
            self::CODE_APPROVED,
            self::CODE_INSUFFICIENT_FUNDS,
            self::CODE_RESTRICTED_CARD,
            self::CODE_INVALID_TRANSACTION,
            self::CODE_TIMEOUT,
            self::CODE_DEBIT_PENDING,
            self::CODE_DEBIT_FAILED,
        ];

        foreach ($order_notes as $note) {
            $content = $note->content;

            // Check if the note contains any of the known error codes
            foreach ($error_codes as $code) {
                if (strpos($content, '[Code: ' . $code) !== false) {
                    return $this->get_user_friendly_error_message($code);
                }
            }
        }

        return __('Your payment was not successful. Please try again or contact support if you believe this is an error.', 'key2pay');
    }

    /**
     * Extract status code from response code, handling currency prefixes.
     *
     * @param string|array $code The response code (e.g., "EGP9998", "USD51", "9998").
     * @return string The status code (e.g., "9998", "51", "9998").
     */
    protected function extract_status_code($code)
    {

        $known_keys = ['responsecode', 'error_code_tag', 'error_text'];

        // If it's a class instance, attempt to convert to an array
        if (is_object($code)) {
            if (method_exists($code, 'to_array')) {
                $code = $code->to_array();
            } elseif (method_exists($code, 'get_data')) {
                $code = $code->get_data();
            } else {
                // Check for known keys in the object
                foreach ($known_keys as $key) {
                    if (isset($code->$key)) {
                        $code = $code->$key;
                        break;
                    }
                }
            }
        }

        // If it's an array, attempt to extract the code from known keys
        if (is_array($code)) {
            foreach ($known_keys as $key) {
                if (isset($code[$key])) {
                    $code = $code[$key];
                    break;
                }
            }
        }

        // Remove currency prefixes (3 letters) and extract numeric part
        if (preg_match('/^[A-Z]{3}(\d+)$/', $code, $matches)) {
            return $matches[1];
        }
        return $code;
    }

    /**
     * Extract error message from response data.
     * This method handles both objects and arrays, extracting known keys.
     *
     * @param mixed $data The response data (object or array).
     * @param string $default_message Default message if no error found.
     * @return string The extracted error message or code.
     */
    protected function extract_error_message($data, $default_message = '')
    {

        $known_keys      = ['extendedresponse', 'responsedescription', 'error_code_tag', 'error_text', 'result'];
        $error           = '';
        $default_message = $default_message ?: __('An unknown error occurred.', 'key2pay');

        // If it's a class instance, attempt to convert to an array
        if (is_object($data)) {
            if (method_exists($data, 'to_array')) {
                $data = $data->to_array();
            } elseif (method_exists($data, 'get_data')) {
                $data = $data->get_data();
            } else {
                // Check for known keys in the object
                foreach ($known_keys as $key) {
                    if (isset($data->$key)) {
                        $error = $data->$key;
                        break;
                    }
                }
            }
        }

        // If it's an array, attempt to extract the code from known keys
        if (is_array($data)) {
            foreach ($known_keys as $key) {
                if (isset($data[$key])) {
                    $error = $data[$key];
                    break;
                }
            }
        }

        // Check for wp_error object
        if (is_wp_error($data)) {
            $error = $data->get_error_message();
        }

        return $error ? $error : $default_message;
    }

    /**
     * Helper to get posted data for form fields.
     *
     * @param string $field_name The name of the POST field.
     * @return string Sanitized value.
     */
    protected function get_posted_data($field_name)
    {

        // Use the get_post_data method to retrieve posted data
        $post_data = $this->get_post_data();
        if (isset($post_data[$field_name])) {
            return wc_clean(wp_unslash($post_data[$field_name]));
        }

        // Fallback to $_POST if not found in post_data
        return isset($_POST[$field_name]) ? wc_clean(wp_unslash($_POST[$field_name])) : '';
    }

    /**
     * Build a proper API URL by handling trailing slashes.
     *
     * @param string $endpoint The API endpoint (e.g., '/PaymentToken/Create').
     * @return string The complete API URL.
     */
    protected function build_api_url($endpoint)
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

        if (! empty($value) && ! filter_var($value, FILTER_VALIDATE_URL)) {
            WC_Admin_Settings::add_error(__('API Base URL must be a valid URL.', 'key2pay'));
            return false;
        }

        return true;
    }

    /**
     * Check if the gateway is available for use.
     * This method is common and can remain in the base class.
     *
     * @return bool
     */
    public function is_available()
    {
        // Check if gateway is enabled
        if ('yes' !== $this->enabled) {
            $this->debug_log('Key2Pay Debug: Payment gateway ' . $this->id . ' disabled');
            return false;
        }

        // Validate credentials based on selected authentication type
        if (! $this->auth_handler->is_configured()) {
            $this->debug_log('Key2Pay Debug: Missing credentials for ' . $this->id . ' with auth type ' . $this->auth_type);

            if (is_admin() && current_user_can('manage_woocommerce') && (! defined('DOING_AJAX') || ! DOING_AJAX)) {
                add_action('admin_notices', function () {
                    echo '<div class="notice notice-error"><p>' . sprintf(__('Key2Pay payment gateway "%1$s" is enabled but credentials for "%2$s" are not configured. %3$sClick here to configure.%4$s', 'key2pay'), $this->method_title, $this->auth_handler->get_auth_description(), '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id) . '">', '</a>') . '</p></div>';
                });
            }
            return false;
        }

        return parent::is_available();
    }

    /**
     * Check if the current page is the "Pay for Order" page.
     * This method is common and can remain in the base class.
     *
     * @return bool True if on the "Pay for Order" page, false otherwise.
     */
    public function is_pay_for_order_page()
    {
        if (! is_wc_endpoint_url('order-pay')) {
            return false;
        }

        $order_id  = isset($_GET['order']) ? absint($_GET['order']) : get_query_var('order-pay');
        $order_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';

        if (! $order_id || ! $order_key) {
            return false;
        }

        $order = wc_get_order($order_id);

        if (! $order || $order->get_order_key() !== $order_key) {
            return false;
        }

        if ($order->get_payment_method() !== $this->id) {
            return false;
        }

        return true;
    }

    /**
     * Redact sensitive data from an array for logging.
     *
     * @param array $array The array to redact.
     * @return array The redacted array.
     */
    protected function redact_sensitive_data($array)
    {
        // Currently, this method only redacts specific keys.
        if (! is_array($array)) {
            return $array; // If not an array, return as is
        }

        $forbidden_keys = [
            'Authorization',
            'password',
            'merchantid',
            'api_key',
            'secret_key',
            'card',
            'cardholder',
            'authcode',
            'token',
        ];

        foreach ($forbidden_keys as $key) {
            if (isset($array[$key])) {
                $array[$key] = '[REDACTED]';
            }
        }

        return $array;
    }

    /**
     * Log messages for debugging purposes.
     *
     * @param string|array $message The message to log.
     * @param bool   $redact_sensitive  Whether to redact sensitive information.
     */
    protected function debug_log($message, $redact_sensitive = true)
    {
        // If debug is not enabled, do not log
        if (! $this->debug) {
            return;
        }

        if ($redact_sensitive) {
            $message = $this->redact_sensitive_data($message);
        }

        if (is_array($message)) {
            $message = print_r($message, true);
        }

        $message = '[' . date('Y-m-d H:i:s') . '][Key2Pay][' . $this->id . '] ' . $message . PHP_EOL;

        // Woocommerce logging
        $this->log->debug($message);

        // Custom log file
        error_log($message, 3, $this->custom_log_file);
    }
}
