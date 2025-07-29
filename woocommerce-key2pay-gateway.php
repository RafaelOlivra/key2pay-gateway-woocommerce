<?php

/**
 * Plugin Name: WooCommerce Key2Pay Gateway [AXDS]
 * Plugin URI:  https://axons.com/
 * Description: A secure redirect-based WooCommerce payment gateway for Key2Pay.
 * Version:     1.0.2
 * Author:      Axons
 * Author URI:  https://axons.com/
 * Text Domain: key2pay
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 8.0
 * WC HPOS compatible: yes
 */

if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * The main plugin class.
 */
class WC_Key2Pay_Gateway_Plugin
{
    /**
     * Constructor.
     */
    public function __construct()
    {
        add_action('plugins_loaded', array( $this, 'init' ));
    }

    /**
     * Initialize the plugin.
     */
    public function init()
    {
        // Check if WooCommerce is active.
        if (! class_exists('WooCommerce')) {
            return;
        }

        // Include necessary classes in the correct order.
        // The authentication handler must be loaded first.
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-key2pay-auth.php';

        // The abstract base gateway must be loaded before any child gateways.
        require_once plugin_dir_path(__FILE__) . 'includes/abstract-wc-key2pay-gateway-base.php';
        // Now include the specific payment gateway implementations.
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-key2pay-redirect-gateway.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-key2pay-thai-debit-gateway.php';

        // require_once plugin_dir_path(__FILE__) . 'includes/_class-wc-key2pay-redirect-gateway.php';

        // Add the Key2Pay Gateways to WooCommerce.
        add_filter('woocommerce_payment_gateways', array( $this, 'add_key2pay_gateways' ));

        // Load plugin text domain.
        load_plugin_textdomain('key2pay', false, basename(dirname(__FILE__)) . '/languages');

        // Enqueue scripts and styles for the checkout page.
        add_action('wp_enqueue_scripts', array( $this, 'key2pay_enqueue_checkout_scripts' ));

        // Declare compatibility with WooCommerce custom order tables (HPOS).
        add_action('before_woocommerce_init', function () {
            if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
                \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
                    'custom_order_tables',
                    __FILE__,
                    true
                );
            }
        });
        
        // Add settings link on plugins page
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array( $this, 'add_plugin_action_links' ));
    }

    /**
     * Enqueue scripts and styles for the Key2Pay checkout.
     *
     * This function checks if the current page is the checkout page and not an endpoint,
     * then enqueues the necessary JavaScript and CSS files for the Key2Pay payment gateway.
     */
    public function key2pay_enqueue_checkout_scripts()
    {
        $version = '1.0.2'; // Update version
        if (is_checkout() && ! is_wc_endpoint_url()) {
            wp_enqueue_script('key2pay-checkout', plugin_dir_url(__FILE__) . 'assets/js/key2pay-checkout.js', array( 'jquery' ), $version, true);
            wp_enqueue_style('key2pay-styles', plugin_dir_url(__FILE__) . 'assets/css/key2pay.css', array(), $version);
        }
    }

    /**
     * Add the Key2Pay Gateways to the list of available gateways.
     *
     * @param array $gateways Available gateways.
     * @return array $gateways Updated gateways.
     */
    public function add_key2pay_gateways($gateways)
    {
        // Add both redirect and Thai Debit gateways
        $gateways[] = 'WC_Key2Pay_Redirect_Gateway';
        $gateways[] = 'WC_Key2Pay_Thai_Debit_Gateway';
        return $gateways;
    }

    /**
     * Add settings link on plugins page.
     *
     * @param array $links Plugin action links.
     * @return array Updated plugin action links.
     */
    public function add_plugin_action_links($links) {
        $settings_link = '<a href="' . admin_url('admin.php?page=wc-settings&tab=checkout&section=key2pay_redirect') . '">' . __('Settings', 'key2pay') . '</a>';
        array_unshift($links, $settings_link);
        return $links;
    }
}

// Instantiate the plugin.
new WC_Key2Pay_Gateway_Plugin();