<?php

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * WC_Key2Pay_InstaPay_Gateway Class.
 *
 * A custom WooCommerce payment gateway for Key2Pay InstaPay.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Key2Pay_InstaPay_Gateway extends WC_Payment_Gateway
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
    public $debug;
    public $log;
    public $form_fields;

    /**
     * API Endpoint for Key2Pay InstaPay payments.
     * As per 'create-order' doc, endpoint is 'PaymentToken/Create' with specific method.
     */
    public const API_INSTAPAY_ENDPOINT = 'https://api.key2payment.com/PaymentToken/Create';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                 = 'key2pay_instapay'; // Unique ID for Instapay gateway.
        $this->icon               = apply_filters('woocommerce_key2pay_instapay_icon', plugin_dir_url(dirname(__FILE__)) . 'assets/images/instapay-logo.png'); // Placeholder for Instapay icon.
        $this->has_fields         = false; // InstaPay is a redirect-based payment method.
        $this->method_title       = __('Key2Pay InstaPay', 'woocommerce-key2pay-gateway');
        $this->method_description = __('Accept payments via InstaPay through Key2Pay. Customers will be redirected to complete the payment.', 'woocommerce-key2pay-gateway');

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings values (common with the main Key2Pay gateway).
        $this->title          = $this->get_option('title');
        $this->description    = $this->get_option('description');
        $this->enabled        = $this->get_option('enabled');
        $this->merchant_id    = $this->get_option('merchant_id');
        $this->password       = $this->get_option('password');
        $this->debug          = 'yes' === $this->get_option('debug');

        // Add hooks for admin settings and payment processing.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));

        // Webhook listener - shared with the main gateway's logic (or can be distinct).
        // For simplicity, we assume webhooks from Key2Pay go to the same endpoint and differentiate by payload.
        // It's better if Key2Pay provides distinct webhook URLs or clear identifiers in the payload.
        add_action('woocommerce_api_' . strtolower($this->id), array($this, 'handle_webhook_callback'));

        // Debug logging.
        if ($this->debug) {
            $this->log = wc_get_logger();
        }
    }

    /**
     * Initialize Gateway Settings Form Fields.
     * Reuses common settings like Merchant ID and Password.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'woocommerce-key2pay-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Key2Pay InstaPay', 'woocommerce-key2pay-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'woocommerce-key2pay-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-key2pay-gateway'),
                'default'     => __('InstaPay', 'woocommerce-key2pay-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'woocommerce-key2pay-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-key2pay-gateway'),
                'default'     => __('Pay instantly using InstaPay via your banking app.', 'woocommerce-key2pay-gateway'),
                'desc_tip'    => true,
            ),
            'merchant_id' => array(
                'title'       => __('Merchant ID', 'woocommerce-key2pay-gateway'),
                'type'        => 'text',
                'description' => __('Your Key2Pay Merchant ID (used for both CC and InstaPay).', 'woocommerce-key2pay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'password' => array(
                'title'       => __('Password', 'woocommerce-key2pay-gateway'),
                'type'        => 'password',
                'description' => __('Your Key2Pay API Password (used for both CC and InstaPay).', 'woocommerce-key2pay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'woocommerce-key2pay-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'woocommerce-key2pay-gateway'),
                'default'     => 'no',
                'description' => __('Log Key2Pay InstaPay events.', 'woocommerce-key2pay-gateway'),
            ),
        );
    }

    /**
     * InstaPay is redirect-based, so no custom fields are typically needed on checkout.
     * If any specific customer input is required for InstaPay (e.g., mobile number),
     * you would add fields here and set $this->has_fields = true.
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }
    }

    /**
     * Validate fields (if any custom fields were added in payment_fields).
     * Since has_fields is false, this won't typically run unless overridden or explicitly called.
     */
    public function validate_fields()
    {
        // No custom fields, so no specific validation needed here.
        return true;
    }

    /**
     * Process the payment for InstaPay.
     * This will make the API call to Key2Pay's InstaPay endpoint and redirect the customer.
     *
     * @param int $order_id Order ID.
     * @return array An array of results.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($this->debug) {
            $this->log->debug(sprintf('Processing InstaPay payment for order #%s.', $order_id), array('source' => 'key2pay-instapay'));
        }

        // Prepare data for Key2Pay InstaPay API request.
        $amount           = (float) $order->get_total();
        $currency         = $order->get_currency();
        $return_url       = $this->get_return_url($order);
        $customer_ip      = WC_Geolocation::get_ip_address();
        $server_url       = WC()->api_request_url('wc_key2pay_gateway'); // Use the main gateway's webhook URL.

        $request_data = array(
            'merchantid'        => $this->merchant_id,
            'password'          => $this->password,
            'trackid'           => $order->get_id() . '_' . time(), // Unique ID for transaction.
            'payment_method'    => array('type' => 'PHQR'), // Specific type for InstaPay as per docs.
            'bill_currencycode' => $currency,
            'bill_amount'       => $amount,
            'returnUrl'         => $return_url,
            'bill_customerip'   => $customer_ip,
            'serverUrl'         => $server_url,
            'bill_country'      => $order->get_billing_country(),
            'bill_email'        => $order->get_billing_email(),
            'bill_phone'        => $order->get_billing_phone(),
            // Other optional fields from InstaPay doc: bill_city, bill_state, bill_address, bill_zip, lang.
            'bill_city'         => $order->get_billing_city(),
            'bill_state'        => $order->get_billing_state(),
            'bill_address'      => $order->get_billing_address_1(),
            'bill_zip'          => $order->get_billing_postcode(),
            'productdesc'       => sprintf(__('Order %s from %s', 'woocommerce-key2pay-gateway'), $order->get_order_number(), get_bloginfo('name')),
            'lang'              => substr(get_bloginfo('language'), 0, 2), // e.g., 'en' for en-US
        );

        // Make the API call to Key2Pay InstaPay endpoint.
        $response = wp_remote_post(
            self::API_INSTAPAY_ENDPOINT,
            array(
                'method'    => 'POST',
                'headers'   => array(
                    'Content-Type' => 'application/json',
                ),
                'body'      => json_encode($request_data),
                'timeout'   => 60,
                'sslverify' => true,
            )
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wc_add_notice(sprintf(__('InstaPay payment error: %s', 'woocommerce-key2pay-gateway'), $error_message), 'error');
            if ($this->debug) {
                $this->log->error(sprintf('Key2Pay InstaPay API Request Failed for order #%s: %s', $order_id, $error_message), array('source' => 'key2pay-instapay'));
            }
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if ($this->debug) {
            $this->log->debug(sprintf('Key2Pay InstaPay API Response for order #%s: %s', $order_id, print_r($data, true)), array('source' => 'key2pay-instapay'));
        }

        // Process the API response.
        if (isset($data->type) && 'valid' === $data->type) {
            if (isset($data->redirectUrl) && ! empty($data->redirectUrl)) {
                // InstaPay requires redirection to Key2Pay's page.
                $order->update_status('pending', __('Awaiting InstaPay payment.', 'woocommerce-key2pay-gateway'));

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
                // Valid response but no redirect URL, which is unexpected for InstaPay.
                $error_message = isset($data->error_text) ? $data->error_text : __('InstaPay payment initiated, but no redirection URL received.', 'woocommerce-key2pay-gateway');
                wc_add_notice(sprintf(__('Key2Pay InstaPay failed: %s', 'woocommerce-key2pay-gateway'), $error_message), 'error');
                if ($this->debug) {
                    $this->log->error(sprintf('Key2Pay InstaPay Missing Redirect URL for order #%s: %s', $order_id, $error_message), array('source' => 'key2pay-instapay'));
                }
                return array(
                    'result'   => 'fail',
                    'redirect' => '',
                );
            }
        } else {
            // Payment failed or API returned an error.
            $error_message = isset($data->error_text) ? $data->error_text : __('An unknown error occurred with Key2Pay InstaPay.', 'woocommerce-key2pay-gateway');
            wc_add_notice(sprintf(__('Key2Pay InstaPay payment failed: %s', 'woocommerce-key2pay-gateway'), $error_message), 'error');
            if ($this->debug) {
                $this->log->error(sprintf('Key2Pay InstaPay API Error for order #%s: %s', $order_id, $error_message), array('source' => 'key2pay-instapay'));
            }
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * Output for the order received page.
     */
    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);
        if ($order->has_status('pending')) {
            echo wpautop(wp_kses_post(__('Your order is awaiting InstaPay payment confirmation from Key2Pay. Please complete the payment using the instructions provided on the previous page or in your email. We will update your order status once the payment is confirmed.', 'woocommerce-key2pay-gateway')));
        }
    }

    /**
     * Handle webhook callbacks from Key2Pay.
     * This gateway reuses the main gateway's webhook URL and expects a similar payload.
     * The `wc_key2pay_gateway` webhook action will be triggered, and the main gateway's
     * `handle_webhook_callback` will process it.
     * It's important that Key2Pay sends the `trackid` or `transactionid` to link back to the order.
     */
    public function handle_webhook_callback()
    {
        // This is primarily handled by the main `WC_Key2Pay_Gateway`'s webhook handler,
        // as Key2Pay sends webhooks to a single `serverUrl` which is typically the primary gateway's endpoint.
        // If Key2Pay were to send webhooks to `woocommerce_api_key2pay_instapay`, then this function
        // would contain specific handling for InstaPay statuses.
        if ($this->debug) {
            $this->log->debug('InstaPay webhook callback received. Processing expected to be handled by the main Key2Pay gateway.', array('source' => 'key2pay-instapay-webhook'));
        }
        // Call the main gateway's webhook handler if it's designed to process all types.
        // Or implement specific InstaPay webhook logic here if necessary.
        // For now, assuming main gateway handles all.
        // new WC_Key2Pay_Gateway()->handle_webhook_callback(); // This would instantiate a new gateway and process, but maybe not ideal.
        // Better: ensure the main webhook handler can differentiate payment types if needed.
        status_header(200); // Always acknowledge.
        exit();
    }

    /**
     * Process a refund for InstaPay.
     * The refund API is generic (`/transaction/refund`) and uses Key2Pay's transaction ID.
     * So, this method will largely delegate to the main gateway's refund processing.
     *
     * @param int    $order_id Order ID.
     * @param float  $amount Refund amount.
     * @param string $reason Refund reason.
     * @return bool True if refund was successful, false otherwise.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        // InstaPay refunds would use the same refund API as credit cards,
        // requiring the original Key2Pay transaction ID.
        // So, we can delegate to the main gateway's refund logic.
        $main_gateway = new WC_Key2Pay_Gateway();
        return $main_gateway->process_refund($order_id, $amount, $reason);
    }

    /**
     * Check if the gateway is available for use.
     *
     * @return bool
     */
    public function is_available()
    {
        if ('yes' !== $this->enabled) {
            return false;
        }

        // Common availability checks (Merchant ID, Password).
        if (empty($this->merchant_id) || empty($this->password)) {
            if (is_admin() && current_user_can('manage_woocommerce') && (! defined('DOING_AJAX') || ! DOING_AJAX)) {
                wc_print_notice(sprintf(__('Key2Pay InstaPay is enabled but requires Merchant ID and Password to be set. %sClick here to configure.%s', 'woocommerce-key2pay-gateway'), '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id) . '">', '</a>'), 'error');
            }
            return false;
        }

        // InstaPay is primarily for PHP currency. Check if store currency matches.
        if ('PHP' !== get_woocommerce_currency()) {
            if (is_admin() && current_user_can('manage_woocommerce') && (! defined('DOING_AJAX') || ! DOING_AJAX)) {
                wc_print_notice(sprintf(__('Key2Pay InstaPay only supports PHP currency. Your store currency is %s. %sClick here to configure.%s', 'woocommerce-key2pay-gateway'), get_woocommerce_currency(), '<a href="' . admin_url('admin.php?page=wc-settings&tab=general') . '">', '</a>'), 'error');
            }
            return false;
        }

        return parent::is_available();
    }
}
