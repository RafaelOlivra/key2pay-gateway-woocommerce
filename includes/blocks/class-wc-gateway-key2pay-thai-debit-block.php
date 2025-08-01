<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class WC_Gateway_Key2Pay_Thai_Debit_Block extends AbstractPaymentMethodType
{
    protected $name = 'key2pay_thai_debit';

    public function initialize()
    {
        add_action('enqueue_block_assets', array( $this, 'enqueue_checkout_block_scripts'));
    }

    public function get_payment_method_script_handles()
    {
        return [
            'key2pay-thai-debit-block',
        ];
    }

    public function get_payment_method_data()
    {
        return [
            'title' => __('Key2Pay Thai QR', 'key2pay'),
            'description' => __('Pay using Thai QR payments via Key2Pay.', 'key2pay'),
            'supports' => ['products', 'refunds'],
        ];
    }

    public function enqueue_checkout_block_scripts()
    {
        $build = include KEY2PAY_PLUGIN_PATH . 'build/index.asset.php';
        wp_enqueue_script(
            'key2pay-thai-debit-block',
            KEY2PAY_PLUGIN_URL . 'build/index.js',
            [...$build['dependencies'], 'wc-blocks-registry'],
            $build['version'],
            true
        );
    }
}

// Register the Key2Pay Credit Gateway
add_filter('woocommerce_gateway_class_name', function ($class_name, $gateway_id) {
    if ($gateway_id === 'key2pay_thai_debit') {
        require_once KEY2PAY_PLUGIN_PATH . 'includes/class-wc-key2pay-thai-debit-gateway.php';
        return 'WC_Key2Pay_Thai_Debit_Gateway';
    }
    return $class_name;
}, 10, 2);

add_filter('woocommerce_blocks_payment_method_type', function ($gateways) {
    $gateways[] = 'key2pay_thai_debit';
    return $gateways;
});
