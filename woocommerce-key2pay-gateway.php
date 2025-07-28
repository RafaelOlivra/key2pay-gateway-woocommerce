<?php

/**
 * Plugin Name: WooCommerce Key2Pay Gateway
 * Plugin URI:  https://axons.com/
 * Description: A secure redirect-based WooCommerce payment gateway for Key2Pay.
 * Version:     1.0.1
 * Author:      Axons
 * Author URI:  https://axons.com/
 * Text Domain: key2pay
 * Domain Path: /languages
 * WC requires at least: 8.0
 * WC tested up to: 8.0
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

        // Include the redirect gateway class.
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-key2pay-auth.php';
        require_once plugin_dir_path(__FILE__) . 'includes/class-wc-key2pay-redirect-gateway.php';

        // Add the Key2Pay Gateway to WooCommerce.
        add_filter('woocommerce_payment_gateways', array( $this, 'add_key2pay_gateway' ));

        // Load plugin text domain.
        load_plugin_textdomain('key2pay', false, basename(dirname(__FILE__)) . '/languages');

        // Enqueue scripts and styles for the checkout page.
        add_action('wp_enqueue_scripts', array( $this, 'key2pay_enqueue_checkout_scripts' ));
    }

    /**
     * Enqueue scripts and styles for the Key2Pay checkout.
     *
     * This function checks if the current page is the checkout page and not an endpoint,
     * then enqueues the necessary JavaScript and CSS files for the Key2Pay payment gateway.
     */
    public function key2pay_enqueue_checkout_scripts()
    {
        $version = '1.0.0';
        if (is_checkout() && ! is_wc_endpoint_url()) {
            wp_enqueue_script('key2pay-checkout', plugin_dir_url(__FILE__) . 'assets/js/key2pay-checkout.js', array( 'jquery' ), $version, true);
            wp_enqueue_style('key2pay-styles', plugin_dir_url(__FILE__) . 'assets/css/key2pay.css', array(), $version);
        }
    }

    /**
     * Add the Key2Pay Gateway to the list of available gateways.
     *
     * @param array $gateways Available gateways.
     * @return array $gateways Updated gateways.
     */
    public function add_key2pay_gateway($gateways)
    {
        $gateways[] = 'WC_Key2Pay_Redirect_Gateway';
        return $gateways;
    }
}

// Instantiate the plugin.
new WC_Key2Pay_Gateway_Plugin();
