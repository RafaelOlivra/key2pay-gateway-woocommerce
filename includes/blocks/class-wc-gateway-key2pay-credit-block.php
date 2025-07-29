<?php


use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Gateway_Key2Pay_Credit_Block extends AbstractPaymentMethodType
{
    protected $name = 'key2pay_credit';

    public function initialize()
    {
        add_action('enqueue_block_assets', array( $this, 'enqueue_checkout_block_scripts'));
    }

    public function get_payments_method_script_handles()
    {
        return [];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => __('Key2Pay Credit Card', 'key2pay'),
            'description' => __('Pay with credit card via Key2Pay with maximum security.', 'key2pay'),
            'supports' => ['products', 'refunds'],
        ];
    }

    public function enqueue_checkout_block_scripts()
    {
        return; // No specific scripts for the credit card block at this time
    }
}

// Register the Key2Pay Credit Gateway
add_filter('woocommerce_gateway_class_name', function ($class_name, $gateway_id) {
    if ($gateway_id === 'key2pay_credit') {
        require_once KEY2PAY_PLUGIN_PATH . 'includes/class-wc-key2pay-credit-gateway.php';
        return 'WC_Key2Pay_Credit_Gateway';
    }
    return $class_name;
}, 10, 2);

add_filter('woocommerce_blocks_payment_method_type', function ($gateways) {
    $gateways[] = 'key2pay_credit';
    return $gateways;
});
