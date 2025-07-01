<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * WC_Key2Pay_Gateway Class.
 *
 * A custom WooCommerce payment gateway for Key2Pay.
 * Deal's with Server-to-Server (S2S) transactions for credit card payments by default.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Key2Pay_Gateway extends WC_Payment_Gateway
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
    public $webhook_secret;
    public $debug;
    public $log;
    public $form_fields;

    /**
     * API Endpoint for Key2Pay S2S transactions.
     */
    public const API_S2S_ENDPOINT   = 'https://api.key2payment.com/transaction/s2s';

    /**
     * API Endpoint for Key2Pay Refund transactions.
     */
    public const API_REFUND_ENDPOINT = 'https://api.key2payment.com/transaction/refund';

    /**
     * Constructor for the gateway.
     */
    public function __construct()
    {
        $this->id                 = 'key2pay'; // Unique ID for your gateway.
        $this->icon               = apply_filters('woocommerce_key2pay_icon', plugin_dir_url(dirname(__FILE__)) . 'assets/images/key2pay-logo.png'); // URL of the gateway icon.
        $this->has_fields         = true; // True because we collect credit card fields directly.
        $this->method_title       = __('Key2Pay (Credit Card)', 'woocommerce-key2pay-gateway');
        $this->method_description = __('Accept credit card payments via Key2Pay\'s Server-to-Server API.', 'woocommerce-key2pay-gateway'); // Will be displayed on the plugins page.

        // Load the settings.
        $this->init_form_fields();
        $this->init_settings();

        // Get settings values.
        $this->title          = $this->get_option('title');
        $this->description    = $this->get_option('description');
        $this->enabled        = $this->get_option('enabled');
        $this->merchant_id    = $this->get_option('merchant_id');
        $this->password       = $this->get_option('password');
        $this->webhook_secret = $this->get_option('webhook_secret'); // For webhook authentication.
        $this->debug          = 'yes' === $this->get_option('debug');

        // Add hooks for admin settings and payment processing.
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ));
        add_action('woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ));

        // Webhook listener for Key2Pay status updates.
        add_action('woocommerce_api_' . strtolower($this->id), array( $this, 'handle_webhook_callback' ));

        // Debug logging.
        if ($this->debug) {
            $this->log = wc_get_logger();
        }
    }

    /**
     * Initialize Gateway Settings Form Fields.
     */
    public function init_form_fields()
    {
        $this->form_fields = array(
            'enabled' => array(
                'title'   => __('Enable/Disable', 'woocommerce-key2pay-gateway'),
                'type'    => 'checkbox',
                'label'   => __('Enable Key2Pay Gateway', 'woocommerce-key2pay-gateway'),
                'default' => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'woocommerce-key2pay-gateway'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'woocommerce-key2pay-gateway'),
                'default'     => __('Credit Card (Key2Pay)', 'woocommerce-key2pay-gateway'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'woocommerce-key2pay-gateway'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'woocommerce-key2pay-gateway'),
                'default'     => __('Pay securely using your Credit Card via Key2Pay.', 'woocommerce-key2pay-gateway'),
                'desc_tip'    => true,
            ),
            'api_details' => array(
                'title'       => __('API Credentials', 'woocommerce-key2pay-gateway'),
                'type'        => 'title',
                'description' => __('Enter your Key2Pay API credentials below.', 'woocommerce-key2pay-gateway') . '<br><br><strong><span style="color: red;">WARNING:</span> This S2S integration directly handles credit card data on your server, increasing your PCI DSS compliance burden (likely SAQ A-EP or D). Ensure your hosting and server environment meet all PCI DSS requirements. Consider using a tokenization solution if Key2Pay provides one, to reduce your PCI scope.</strong>',
            ),
            'merchant_id' => array(
                'title'       => __('Merchant ID', 'woocommerce-key2pay-gateway'),
                'type'        => 'text',
                'description' => __('Your Key2Pay Merchant ID.', 'woocommerce-key2pay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'password' => array(
                'title'       => __('Password', 'woocommerce-key2pay-gateway'),
                'type'        => 'password',
                'description' => __('Your Key2Pay API Password. Keep this secure!', 'woocommerce-key2pay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'webhook_secret' => array(
                'title'       => __('Webhook Secret', 'woocommerce-key2pay-gateway'),
                'type'        => 'password',
                'description' => __('If Key2Pay provides a webhook secret for signature verification, enter it here. This is crucial for security and authenticating webhook requests.', 'woocommerce-key2pay-gateway'),
                'default'     => '',
                'desc_tip'    => true,
            ),
            'debug' => array(
                'title'       => __('Debug Log', 'woocommerce-key2pay-gateway'),
                'type'        => 'checkbox',
                'label'       => __('Enable logging', 'woocommerce-key2pay-gateway'),
                'default'     => 'no',
                'description' => __('Log Key2Pay events, such as API requests, inside <code>WooCommerce &gt; Status &gt; Logs</code>', 'woocommerce-key2pay-gateway'),
            ),
        );
    }

    /**
     * Output custom credit card fields on the checkout page.
     */
    public function payment_fields()
    {
        if ($this->description) {
            echo wpautop(wp_kses_post($this->description));
        }

        // Render credit card fields.
        // IMPORTANT: For PCI DSS compliance with direct card collection, these fields should be served over HTTPS.
        // Also, consider client-side validation for better user experience.
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id); ?>-cc-form" class="wc-credit-card-form wc-payment-form" style="background:transparent;">
            <?php do_action('woocommerce_credit_card_form_start', $this->id); ?>

            <p class="form-row form-row-wide">
                <label for="<?php echo esc_attr($this->id); ?>-card-number"><?php esc_html_e('Card Number', 'woocommerce-key2pay-gateway'); ?> <span class="required">*</span></label>
                <input id="<?php echo esc_attr($this->id); ?>-card-number" class="input-text wc-credit-card-form__input wc-credit-card-form__input--card-number" type="text" inputmode="numeric" autocomplete="cc-number" autocapitalize="off" spellcheck="off" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" name="<?php echo esc_attr($this->id); ?>_card_number" />
            </p>

            <p class="form-row form-row-first">
                <label for="<?php echo esc_attr($this->id); ?>-card-expiry"><?php esc_html_e('Expiry Date', 'woocommerce-key2pay-gateway'); ?> <span class="required">*</span></label>
                <input id="<?php echo esc_attr($this->id); ?>-card-expiry" class="input-text wc-credit-card-form__input wc-credit-card-form__input--card-expiry" type="text" inputmode="numeric" autocomplete="cc-exp" autocapitalize="off" spellcheck="off" placeholder="<?php esc_attr_e('MM / YY', 'woocommerce-key2pay-gateway'); ?>" name="<?php echo esc_attr($this->id); ?>_card_expiry" />
            </p>

            <p class="form-row form-row-last">
                <label for="<?php echo esc_attr($this->id); ?>-card-cvc"><?php esc_html_e('Card Code (CVC)', 'woocommerce-key2pay-gateway'); ?> <span class="required">*</span></label>
                <input id="<?php echo esc_attr($this->id); ?>-card-cvc" class="input-text wc-credit-card-form__input wc-credit-card-form__input--card-cvc" type="text" inputmode="numeric" autocomplete="off" autocapitalize="off" spellcheck="off" placeholder="<?php esc_attr_e('CVC', 'woocommerce-key2pay-gateway'); ?>" name="<?php echo esc_attr($this->id); ?>_card_cvc" />
            </p>
            <div class="clear"></div>
            <?php do_action('woocommerce_credit_card_form_end', $this->id); ?>
        </fieldset>
        <?php
    }

    /**
     * Validate fields on the checkout form.
     * This is crucial for direct card collection.
     *
     * @return bool True if valid, false otherwise.
     */
    public function validate_fields()
    {
        $card_number = isset($_POST[ $this->id . '_card_number' ]) ? sanitize_text_field($_POST[ $this->id . '_card_number' ]) : '';
        $card_expiry = isset($_POST[ $this->id . '_card_expiry' ]) ? sanitize_text_field($_POST[ $this->id . '_card_expiry' ]) : '';
        $card_cvc    = isset($_POST[ $this->id . '_card_cvc' ]) ? sanitize_text_field($_POST[ $this->id . '_card_cvc' ]) : '';

        // Basic validation for presence. More advanced validation (e.g., Luhn algorithm for card number, expiry date check)
        // should ideally be done client-side with JavaScript for better UX, but server-side is essential as a fallback.
        if (empty($card_number)) {
            wc_add_notice(__('Credit card number is required.', 'woocommerce-key2pay-gateway'), 'error');
            return false;
        }
        if (empty($card_expiry)) {
            wc_add_notice(__('Credit card expiry date is required.', 'woocommerce-key2pay-gateway'), 'error');
            return false;
        }
        if (empty($card_cvc)) {
            wc_add_notice(__('Credit card CVC is required.', 'woocommerce-key2pay-gateway'), 'error');
            return false;
        }

        // Parse expiry date: MM / YY
        $expiry_parts = array_map('trim', explode('/', $card_expiry));
        if (count($expiry_parts) !== 2 || ! ctype_digit($expiry_parts[0]) || ! ctype_digit($expiry_parts[1])) {
            wc_add_notice(__('Invalid expiry date format. Please use MM / YY.', 'woocommerce-key2pay-gateway'), 'error');
            return false;
        }
        $exp_month = str_pad($expiry_parts[0], 2, '0', STR_PAD_LEFT);
        $exp_year  = '20' . $expiry_parts[1]; // Assumes 2-digit year like 25 for 2025

        // Basic expiry date check (server-side, more robust check would be better)
        $current_year  = (int) date('Y');
        $current_month = (int) date('m');

        if ((int) $exp_year < $current_year || ((int) $exp_year === $current_year && (int) $exp_month < $current_month)) {
            wc_add_notice(__('Credit card has expired.', 'woocommerce-key2pay-gateway'), 'error');
            return false;
        }

        return true;
    }

    /**
     * Process the payment.
     * This is where you make the API call to Key2Pay S2S endpoint.
     *
     * @param int $order_id Order ID.
     * @return array An array of results.
     */
    public function process_payment($order_id)
    {
        $order = wc_get_order($order_id);

        if ($this->debug) {
            $this->log->debug(sprintf('Processing payment for order #%s using Key2Pay S2S.', $order_id), array( 'source' => 'key2pay-s2s' ));
        }

        // Get card details from $_POST
        $card_number = sanitize_text_field($_POST[ $this->id . '_card_number' ]);
        $card_expiry = sanitize_text_field($_POST[ $this->id . '_card_expiry' ]);
        $card_cvc    = sanitize_text_field($_POST[ $this->id . '_card_cvc' ]);

        // Parse expiry date: MM / YY
        $expiry_parts = array_map('trim', explode('/', $card_expiry));
        $exp_month    = str_pad($expiry_parts[0], 2, '0', STR_PAD_LEFT);
        $exp_year     = '20' . $expiry_parts[1]; // Ensure 4-digit year for API if required

        // Prepare data for Key2Pay API request.
        $amount           = (float) $order->get_total(); // Ensure float for amount
        $currency         = $order->get_currency();
        $description      = sprintf(__('Order %s from %s', 'woocommerce-key2pay-gateway'), $order->get_order_number(), get_bloginfo('name'));
        $return_url       = $this->get_return_url($order);
        $return_url_fail  = $order->get_checkout_payment_url(false); // Redirect back to checkout if failed.
        $server_url       = WC()->api_request_url('wc_key2pay_gateway'); // Your webhook endpoint.
        $customer_ip      = WC_Geolocation::get_ip_address(); // Get customer IP.

        // Basic browser info collection (more advanced requires JS).
        // This is a minimal set. For full accuracy, these should be collected via JS on the client.
        $browser_info = array(
            'color_depth'   => '24', // Default, needs JS for actual
            'device_type'   => wp_is_mobile() ? 'mobile' : 'desktop',
            'java_enabled'  => false, // Cannot reliably detect server-side
            'language'      => get_bloginfo('language'),
            'screen_height' => 0, // Needs JS for actual
            'screen_width'  => 0, // Needs JS for actual
            'tz_info'       => date_default_timezone_get(),
            'user_agent'    => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : 'Unknown',
        );

        $request_data = array(
            'merchantid'           => $this->merchant_id,
            'password'             => $this->password,
            'payment_method'       => array( 'type' => 'BANKCARD' ),
            'trackid'              => $order->get_id() . '_' . time(), // Unique ID for transaction.
            'bill_currencycode'    => $currency,
            'bill_cardholder'      => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
            'bill_amount'          => $amount,
            'returnUrl'            => $return_url,
            'returnUrl_on_failure' => $return_url_fail,
            'productdesc'          => $description,
            'bill_customerip'      => $customer_ip,
            'bill_phone'           => $order->get_billing_phone(),
            'bill_cc'              => str_replace(' ', '', $card_number), // Remove spaces.
            'bill_expmonth'        => $exp_month,
            'bill_expyear'         => $exp_year,
            'serverUrl'            => $server_url,
            'bill_email'           => $order->get_billing_email(),
            'bill_country'         => $order->get_billing_country(),
            'bill_city'            => $order->get_billing_city(),
            'bill_state'           => $order->get_billing_state(),
            'bill_address'         => $order->get_billing_address_1(),
            'bill_zip'             => $order->get_billing_postcode(),
            'browser_info'         => $browser_info,
        );

        // Make the API call to Key2Payment.
        $response = wp_remote_post(
            self::API_S2S_ENDPOINT, // Using the defined S2S endpoint.
            array(
                'method'    => 'POST',
                'headers'   => array(
                    'Content-Type' => 'application/json',
                ),
                'body'      => json_encode($request_data),
                'timeout'   => 60, // Increased timeout for external API.
                'sslverify' => true,
            )
        );

        // Clear sensitive card data from memory immediately after use.
        unset($_POST[ $this->id . '_card_number' ], $_POST[ $this->id . '_card_expiry' ], $_POST[ $this->id . '_card_cvc' ]);
        $card_number = $card_expiry = $card_cvc = null;


        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            wc_add_notice(sprintf(__('Payment error: %s', 'woocommerce-key2pay-gateway'), $error_message), 'error');
            if ($this->debug) {
                $this->log->error(sprintf('Key2Pay S2S API Request Failed for order #%s: %s', $order_id, $error_message), array( 'source' => 'key2pay-s2s' ));
            }
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if ($this->debug) {
            $this->log->debug(sprintf('Key2Pay S2S API Response for order #%s: %s', $order_id, print_r($data, true)), array( 'source' => 'key2pay-s2s' ));
        }

        // Process the API response.
        if (isset($data->type) && 'valid' === $data->type) {
            // Check for a redirect URL, which indicates 3D Secure or other required action.
            if (isset($data->redirectUrl) && ! empty($data->redirectUrl)) {
                // Payment initiated successfully, but redirection needed.
                $order->update_status('pending', __('Awaiting Key2Pay 3D Secure authentication.', 'woocommerce-key2pay-gateway'));

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
            } elseif (isset($data->result) && 'Processing' === $data->result) {
                // If no redirect and result is 'Processing', it means the payment is being handled asynchronously.
                $order->update_status('on-hold', __('Payment initiated with Key2Pay, awaiting confirmation.', 'woocommerce-key2pay-gateway'));

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
                    'redirect' => $this->get_return_url($order), // Redirect to thank you page.
                );
            } else {
                // Unexpected valid response without redirect or processing status.
                $error_message = isset($data->error_text) ? $data->error_text : __('An unexpected response was received from Key2Pay.', 'woocommerce-key2pay-gateway');
                wc_add_notice(sprintf(__('Key2Pay payment failed: %s', 'woocommerce-key2pay-gateway'), $error_message), 'error');
                if ($this->debug) {
                    $this->log->error(sprintf('Key2Pay S2S Unexpected valid response for order #%s: %s', $order_id, $error_message), array( 'source' => 'key2pay-s2s' ));
                }
                return array(
                    'result'   => 'fail',
                    'redirect' => '',
                );
            }

        } else {
            // Payment failed or API returned an error.
            $error_message = isset($data->error_text) ? $data->error_text : __('An unknown error occurred with Key2Pay.', 'woocommerce-key2pay-gateway');
            wc_add_notice(sprintf(__('Key2Pay payment failed: %s', 'woocommerce-key2pay-gateway'), $error_message), 'error');
            if ($this->debug) {
                $this->log->error(sprintf('Key2Pay S2S API Error for order #%s: %s', $order_id, $error_message), array( 'source' => 'key2pay-s2s' ));
            }
            return array(
                'result'   => 'fail',
                'redirect' => '',
            );
        }
    }

    /**
     * Output for the order received page.
     * This will show after a successful payment redirect.
     * You can add custom messages here or just use the default WooCommerce message.
     */
    public function thankyou_page($order_id)
    {
        $order = wc_get_order($order_id);
        // Check if the order is still pending. If it was processed successfully by webhook, it might be 'processing' or 'completed'.
        if ($order->has_status('pending')) {
            echo wpautop(wp_kses_post(__('Your order is awaiting payment confirmation from Key2Pay. We will update your order status once the payment is confirmed.', 'woocommerce-key2pay-gateway')));
        }
    }

    /**
     * Handle webhook callbacks from Key2Pay.
     * This is crucial for updating order statuses asynchronously.
     * The `serverUrl` in the S2S request points to this endpoint.
     */
    public function handle_webhook_callback()
    {
        if ($this->debug) {
            $this->log->debug('Webhook callback initiated for Key2Pay.', array( 'source' => 'key2pay-webhook' ));
        }

        // --- IMPORTANT: WEBHOOK AUTHENTICATION ---
        // It is CRITICAL to verify the authenticity of the incoming webhook request.
        // Key2Pay SHOULD provide a mechanism for this, such as:
        // 1. A shared secret: Key2Pay generates a signature using the request body and a secret key,
        //    and sends it in a header (e.g., 'X-Key2Pay-Signature'). You then recalculate the signature
        //    using your shared secret and the raw request body, and compare it.
        // 2. IP Whitelisting: Key2Pay sends webhooks from a specific set of IP addresses. You verify the sender's IP.
        // 3. API Key/Auth in webhook body: Less secure, but some older systems might do this.

        // Placeholder for webhook verification logic. Replace with actual Key2Pay method.
        if (! $this->is_valid_webhook_request()) {
            if ($this->debug) {
                $this->log->error('Invalid Key2Pay webhook request (authentication failed).', array( 'source' => 'key2pay-webhook-security' ));
            }
            // Respond with 403 Forbidden to unauthorized requests.
            wp_die('Forbidden', '', array( 'response' => 403 ));
        }

        $raw_body = file_get_contents('php://input');
        $data     = json_decode($raw_body);

        if (! $data) {
            if ($this->debug) {
                $this->log->error('Invalid JSON received from Key2Pay webhook.', array( 'source' => 'key2pay-webhook' ));
            }
            wp_die('Invalid JSON', '', array( 'response' => 400 ));
        }

        // Based on the S2S response, likely webhook will also contain transactionid, trackid, and result/status.
        $transaction_id = isset($data->transactionid) ? sanitize_text_field($data->transactionid) : null;
        $track_id       = isset($data->trackid) ? sanitize_text_field($data->trackid) : null;
        $status         = isset($data->result) ? strtolower(sanitize_text_field($data->result)) : null; // Use 'result' field for status.
        $amount         = isset($data->bill_amount) ? floatval($data->bill_amount) : null; // Or 'amount' field if present.

        // Retrieve order based on track_id or transaction_id. track_id is safer if it contains the order ID.
        // Assuming trackid is `order_id_timestamp` as generated in process_payment.
        $order_id_parts = explode('_', $track_id);
        $order_id = absint($order_id_parts[0]);

        if (! $order_id || ! $transaction_id || ! $status) {
            if ($this->debug) {
                $this->log->error(sprintf('Missing critical data in Key2Pay webhook: order_id=%s, transactionid=%s, status=%s', $order_id, $transaction_id, $status), array( 'source' => 'key2pay-webhook' ));
            }
            wp_die('Missing data', '', array( 'response' => 400 ));
        }

        $order = wc_get_order($order_id);

        if (! $order) {
            if ($this->debug) {
                $this->log->warning(sprintf('Order #%s not found for Key2Pay webhook with track ID %s.', $order_id, $track_id), array( 'source' => 'key2pay-webhook' ));
            }
            wp_die('Order not found', '', array( 'response' => 404 ));
        }

        // Verify that the transaction ID matches the one stored with the order.
        $stored_transaction_id = $order->get_meta('_key2pay_transaction_id');
        if (! empty($stored_transaction_id) && $stored_transaction_id !== $transaction_id) {
            if ($this->debug) {
                $this->log->warning(sprintf('Webhook transaction ID mismatch for order #%s. Expected: %s, Received: %s', $order_id, $stored_transaction_id, $transaction_id), array( 'source' => 'key2pay-webhook' ));
            }
            wp_die('Transaction ID mismatch', '', array( 'response' => 403 ));
        }

        if ($this->debug) {
            $this->log->debug(sprintf('Processing Key2Pay webhook for Order #%s (Track ID: %s) with status: %s', $order_id, $track_id, $status), array( 'source' => 'key2pay-webhook' ));
        }

        switch ($status) {
            case 'success': // Or 'completed', 'paid'
                // Add an additional check for amount if it's provided in webhook.
                if ($order->get_total() > 0 && $amount && $order->get_total() != $amount) {
                    $order->update_status('on-hold', sprintf(__('Key2Pay payment received, but amount mismatch. Expected: %s, Received: %s. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $order->get_total(), $amount, $transaction_id));
                    $order->add_order_note(sprintf(__('Key2Pay payment amount mismatch. Expected: %s, Received: %s. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $order->get_total(), $amount, $transaction_id));
                    if ($this->debug) {
                        $this->log->warning(sprintf('Amount mismatch for order #%s. Expected: %s, Received: %s. Transaction ID: %s', $order_id, $order->get_total(), $amount, $transaction_id), array( 'source' => 'key2pay-webhook' ));
                    }
                    wp_die('Amount mismatch', '', array( 'response' => 200 )); // Acknowledge to Key2Pay.
                }

                if ($order->has_status(array( 'pending', 'on-hold' ))) {
                    $order->payment_complete($transaction_id);
                    $order->add_order_note(sprintf(__('Key2Pay payment completed successfully. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $transaction_id));
                    if ($this->debug) {
                        $this->log->info(sprintf('Order #%s payment completed via Key2Pay webhook. Transaction ID: %s', $order_id, $transaction_id), array( 'source' => 'key2pay-webhook' ));
                    }
                }
                break;
            case 'failed': // Or 'error', 'denied', 'declined'
            case 'cancel':
                if (! $order->has_status(array( 'failed', 'cancelled' ))) {
                    $order->update_status('failed', sprintf(__('Key2Pay payment failed. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $transaction_id));
                    $order->add_order_note(sprintf(__('Key2Pay payment failed. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $transaction_id));
                    if ($this->debug) {
                        $this->log->warning(sprintf('Order #%s payment failed via Key2Pay webhook. Transaction ID: %s', $order_id, $transaction_id), array( 'source' => 'key2pay-webhook' ));
                    }
                }
                break;
            case 'refunded': // If Key2Pay sends webhook for refunds.
                if (! $order->has_status('refunded')) {
                    $order->update_status('refunded', sprintf(__('Key2Pay payment refunded. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $transaction_id));
                    $order->add_order_note(sprintf(__('Key2Pay payment refunded. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $transaction_id));
                    if ($this->debug) {
                        $this->log->info(sprintf('Order #%s payment refunded via Key2Pay webhook. Transaction ID: %s', $order_id, $transaction_id), array( 'source' => 'key2pay-webhook' ));
                    }
                }
                break;
            case 'processing': // Some gateways might send 'processing' before 'success'.
                if ($order->has_status('pending')) {
                    $order->update_status('on-hold', sprintf(__('Key2Pay payment is processing. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $transaction_id));
                    $order->add_order_note(sprintf(__('Key2Pay payment is processing. Transaction ID: %s', 'woocommerce-key2pay-gateway'), $transaction_id));
                    if ($this->debug) {
                        $this->log->info(sprintf('Order #%s payment is processing via Key2Pay webhook. Transaction ID: %s', $order_id, $transaction_id), array( 'source' => 'key2pay-webhook' ));
                    }
                }
                break;
            default:
                // Handle other statuses or ignore.
                if ($this->debug) {
                    $this->log->info(sprintf('Received unhandled Key2Pay webhook status "%s" for order #%s. Transaction ID: %s', $status, $order_id, $transaction_id), array( 'source' => 'key2pay-webhook' ));
                }
                break;
        }

        // Always respond with 200 OK to acknowledge receipt of the webhook.
        status_header(200);
        exit();
    }

    /**
     * Internal helper to validate webhook requests.
     * YOU MUST IMPLEMENT THIS BASED ON KEY2PAY'S WEBHOOK SECURITY GUIDELINES.
     * This is a placeholder example for signature verification.
     *
     * @return bool True if the webhook request is valid, false otherwise.
     */
    private function is_valid_webhook_request()
    {
        // Get the raw request body.
        $raw_request_body = file_get_contents('php://input');

        // Get the signature from Key2Pay's webhook header (e.g., 'X-Key2Pay-Signature').
        // The header name will vary depending on Key2Pay's implementation.
        // Replace 'X-KEY2PAY-SIGNATURE' with the actual header name Key2Pay uses.
        $signature_header = isset($_SERVER['HTTP_X_KEY2PAY_SIGNATURE']) ? $_SERVER['HTTP_X_KEY2PAY_SIGNATURE'] : '';

        // Get the webhook secret from your plugin settings.
        $webhook_secret = $this->get_option('webhook_secret');

        // If no secret is configured, and a secret is expected by Key2Pay, it's invalid.
        if (empty($webhook_secret) && ! empty($signature_header)) {
            if ($this->debug) {
                $this->log->error('Webhook secret is not configured in plugin settings, but a signature header was received.', array( 'source' => 'key2pay-webhook-security' ));
            }
            return false;
        }

        // --- REPLACE THE FOLLOWING LOGIC WITH KEY2PAY'S SPECIFIC SIGNATURE VERIFICATION METHOD. ---
        // Key2Pay's documentation does not specify webhook authentication.
        // Common methods include HMAC-SHA256.
        // Example (conceptual, adjust for Key2Pay's specific algorithm and payload format):
        /*
        if ( ! empty( $webhook_secret ) && ! empty( $signature_header ) ) {
            $expected_signature = hash_hmac( 'sha256', $raw_request_body, $webhook_secret ); // Or other algorithm
            if ( hash_equals( $expected_signature, $signature_header ) ) { // Use hash_equals for timing attack prevention.
                return true;
            } else {
                if ( $this->debug ) {
                    $this->log->warning( 'Webhook signature mismatch.', array( 'source' => 'key2pay-webhook-security' ) );
                }
                return false;
            }
        }
        */

        // If Key2Pay uses IP whitelisting instead:
        /*
        $allowed_ips = array( 'KEY2PAY_IP_1', 'KEY2PAY_IP_2' ); // Get these from Key2Pay documentation
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        if ( in_array( $remote_ip, $allowed_ips ) ) {
            return true;
        } else {
            if ( $this->debug ) {
                $this->log->warning( sprintf( 'Webhook received from unauthorized IP: %s', $remote_ip ), array( 'source' => 'key2pay-webhook-security' ) );
            }
            return false;
        }
        */

        // FOR NOW, AS A TEMPORARY MEASURE (NOT SECURE FOR PRODUCTION):
        // This always returns true, which means webhooks are NOT authenticated.
        // DO NOT USE IN PRODUCTION WITHOUT PROPER AUTHENTICATION.
        if ($this->debug) {
            $this->log->warning('Webhook authentication is not fully implemented. This is a security risk for production.', array( 'source' => 'key2pay-webhook-security' ));
        }
        return true; // TEMPORARY: REMOVE OR REPLACE FOR PRODUCTION
    }


    /**
     * Process a refund.
     * This method is called by WooCommerce when a refund is initiated.
     * Implemented based on Key2Pay Refund API documentation.
     *
     * @param int    $order_id Order ID.
     * @param float  $amount Refund amount.
     * @param string $reason Refund reason.
     * @return bool True if refund was successful, false otherwise.
     */
    public function process_refund($order_id, $amount = null, $reason = '')
    {
        $order = wc_get_order($order_id);
        if (! $order) {
            return false;
        }

        // Key2Pay's refund API uses 'tranid' which refers to their transaction ID.
        $key2pay_transaction_id = $order->get_meta('_key2pay_transaction_id');

        if (! $key2pay_transaction_id) {
            $order->add_order_note(__('Key2Pay refund failed: No Key2Pay transaction ID found for this order.', 'woocommerce-key2pay-gateway'));
            if ($this->debug) {
                $this->log->error(sprintf('Refund failed for order #%s: No Key2Pay transaction ID found.', $order_id), array( 'source' => 'key2pay-refund' ));
            }
            return false;
        }

        if ($this->debug) {
            $this->log->debug(sprintf('Initiating Key2Pay refund for order #%s (Transaction ID: %s, Amount: %s).', $order_id, $key2pay_transaction_id, $amount), array( 'source' => 'key2pay-refund' ));
        }

        // Prepare data for Key2Pay Refund API request.
        $request_data = array(
            'merchantid' => $this->merchant_id,
            'password'   => $this->password,
            'tranid'     => $key2pay_transaction_id,
            'amount'     => (float) $amount, // Ensure float type for amount.
            // No 'reason' parameter mentioned in Key2Pay's refund sample.
        );

        // Make the API call to Key2Pay for refund.
        $response = wp_remote_post(
            self::API_REFUND_ENDPOINT,
            array(
                'method'    => 'POST',
                'headers'   => array(
                    'Content-Type' => 'application/json',
                ),
                'body'      => json_encode($request_data),
                'timeout'   => 45,
                'sslverify' => true,
            )
        );

        if (is_wp_error($response)) {
            $error_message = $response->get_error_message();
            $order->add_order_note(sprintf(__('Key2Pay refund API request failed: %s', 'woocommerce-key2pay-gateway'), $error_message));
            if ($this->debug) {
                $this->log->error(sprintf('Key2Pay Refund API Request Failed for order #%s: %s', $order_id, $error_message), array( 'source' => 'key2pay-refund' ));
            }
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body);

        if ($this->debug) {
            $this->log->debug(sprintf('Key2Pay Refund API Response for order #%s: %s', $order_id, print_r($data, true)), array( 'source' => 'key2pay-refund' ));
        }

        // Process the API refund response.
        // Assuming 'type' = 'valid' and 'result' = 'Successful' indicates a successful refund.
        if (isset($data->type) && 'valid' === $data->type && isset($data->result) && 'Successful' === $data->result) {
            $order->add_order_note(sprintf(__('Refund processed successfully via Key2Pay for amount %s. Key2Pay Transaction ID: %s', 'woocommerce-key2pay-gateway'), wc_price($amount), $key2pay_transaction_id));
            if ($this->debug) {
                $this->log->info(sprintf('Refund successful for order #%s, amount %s. Key2Pay Transaction ID: %s', $order_id, $amount, $key2pay_transaction_id), array( 'source' => 'key2pay-refund' ));
            }
            return true;
        } else {
            $error_message = isset($data->error_text) ? $data->error_text : __('An unknown error occurred during Key2Pay refund.', 'woocommerce-key2pay-gateway');
            $order->add_order_note(sprintf(__('Key2Pay refund failed: %s', 'woocommerce-key2pay-gateway'), $error_message));
            if ($this->debug) {
                $this->log->error(sprintf('Key2Pay Refund API Error for order #%s: %s', $order_id, $error_message), array( 'source' => 'key2pay-refund' ));
            }
            return false;
        }
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

        if (empty($this->merchant_id) || empty($this->password)) {
            // Only show admin notice if on settings page.
            if (is_admin() && current_user_can('manage_woocommerce') && (! defined('DOING_AJAX') || ! DOING_AJAX)) {
                wc_print_notice(sprintf(__('Key2Pay gateway is enabled but requires Merchant ID and Password to be set. %sClick here to configure.%s', 'woocommerce-key2pay-gateway'), '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=' . $this->id) . '">', '</a>'), 'error');
            }
            return false;
        }

        // You might add currency support checks here if Key2Pay has limitations.
        // E.g., if ( 'EUR' !== get_woocommerce_currency() ) { return false; }

        return parent::is_available();
    }
}
