<?php
if (! defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

/**
 * Key2Pay Authentication Handler
 * 
 * Handles different authentication methods for Key2Pay API:
 * - Basic Auth (Merchant ID + Password)
 * - API Key Authentication
 * - Bearer Token Authentication
 * - Request Signing (HMAC)
 */
class WC_Key2Pay_Auth
{
    /**
     * Authentication types
     */
    const AUTH_TYPE_BASIC = 'basic';
    const AUTH_TYPE_API_KEY = 'api_key';
    const AUTH_TYPE_BEARER = 'bearer';
    const AUTH_TYPE_SIGNED = 'signed';

    /**
     * Get all available authentication types
     */
    public static function get_auth_types()
    {
        return array(
            self::AUTH_TYPE_BASIC => __('Basic (Merchant ID + Password)', 'woocommerce-key2pay-gateway'),
            self::AUTH_TYPE_API_KEY => __('API Key', 'woocommerce-key2pay-gateway'),
            self::AUTH_TYPE_BEARER => __('Bearer Token', 'woocommerce-key2pay-gateway'),
            self::AUTH_TYPE_SIGNED => __('HMAC Signed', 'woocommerce-key2pay-gateway'),
        );
    }

    private $auth_type;
    private $merchant_id;
    private $password;
    private $api_key;
    private $secret_key;
    private $access_token;
    private $debug;

    /**
     * Constructor
     */
    public function __construct($auth_type = self::AUTH_TYPE_BASIC)
    {
        $this->auth_type = $auth_type;
        $this->debug = false;
    }

    /**
     * Set authentication credentials
     */
    public function set_credentials($credentials = array())
    {
        $this->merchant_id = isset($credentials['merchant_id']) ? $credentials['merchant_id'] : '';
        $this->password = isset($credentials['password']) ? $credentials['password'] : '';
        $this->api_key = isset($credentials['api_key']) ? $credentials['api_key'] : '';
        $this->secret_key = isset($credentials['secret_key']) ? $credentials['secret_key'] : '';
        $this->access_token = isset($credentials['access_token']) ? $credentials['access_token'] : '';
    }

    /**
     * Set debug mode
     */
    public function set_debug($debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Get authentication headers for API requests
     */
    public function get_auth_headers()
    {
        $headers = array();

        switch ($this->auth_type) {
            case self::AUTH_TYPE_BASIC:
                // Basic authentication - credentials in request body (current method)
                // No headers needed as credentials are sent in body
                break;

            case self::AUTH_TYPE_API_KEY:
                // API Key in header
                if (!empty($this->api_key)) {
                    $headers['X-API-Key'] = $this->api_key;
                }
                break;

            case self::AUTH_TYPE_BEARER:
                // Bearer token authentication
                if (!empty($this->access_token)) {
                    $headers['Authorization'] = 'Bearer ' . $this->access_token;
                }
                break;

            case self::AUTH_TYPE_SIGNED:
                // HMAC signed requests
                if (!empty($this->api_key)) {
                    $headers['X-API-Key'] = $this->api_key;
                }
                break;
        }

        return $headers;
    }

    /**
     * Add authentication data to request body
     */
    public function add_auth_to_body($request_data = array())
    {
        switch ($this->auth_type) {
            case self::AUTH_TYPE_BASIC:
                // Add merchant ID and password to request body (current method)
                $request_data['merchantid'] = $this->merchant_id;
                $request_data['password'] = $this->password;
                break;

            case self::AUTH_TYPE_API_KEY:
                // API key might be in body for some endpoints
                if (!empty($this->api_key)) {
                    $request_data['api_key'] = $this->api_key;
                }
                break;

            case self::AUTH_TYPE_BEARER:
                // Bearer tokens are typically in headers, not body
                break;

            case self::AUTH_TYPE_SIGNED:
                // For signed requests, we might need a timestamp
                $request_data['timestamp'] = time();
                break;
        }

        return $request_data;
    }

    /**
     * Sign request data with HMAC
     */
    public function sign_request($request_data, $endpoint = '')
    {
        if (empty($this->secret_key)) {
            return $request_data;
        }

        // Create signature string
        $signature_string = $this->create_signature_string($request_data, $endpoint);
        
        // Generate HMAC signature
        $signature = hash_hmac('sha256', $signature_string, $this->secret_key);
        
        // Add signature to request data
        $request_data['signature'] = $signature;
        
        if ($this->debug) {
            error_log('Key2Pay Auth: Signature generated: ' . $signature);
        }

        return $request_data;
    }

    /**
     * Create signature string for HMAC signing
     */
    private function create_signature_string($request_data, $endpoint = '')
    {
        // Sort parameters alphabetically
        ksort($request_data);
        
        // Create query string
        $query_string = http_build_query($request_data);
        
        // Add endpoint if provided
        if (!empty($endpoint)) {
            $signature_string = $endpoint . '?' . $query_string;
        } else {
            $signature_string = $query_string;
        }

        return $signature_string;
    }

    /**
     * Verify webhook signature
     */
    public function verify_webhook_signature($payload, $signature_header, $webhook_secret = '')
    {
        if (empty($webhook_secret)) {
            $webhook_secret = $this->secret_key;
        }

        if (empty($webhook_secret) || empty($signature_header)) {
            return false;
        }

        // Generate expected signature
        $expected_signature = hash_hmac('sha256', $payload, $webhook_secret);
        
        // Compare signatures using hash_equals for timing attack prevention
        $is_valid = hash_equals($expected_signature, $signature_header);

        if ($this->debug) {
            error_log('Key2Pay Auth: Webhook signature verification: ' . ($is_valid ? 'VALID' : 'INVALID'));
            error_log('Key2Pay Auth: Expected: ' . $expected_signature . ', Received: ' . $signature_header);
        }

        return $is_valid;
    }

    /**
     * Get authentication type
     */
    public function get_auth_type()
    {
        return $this->auth_type;
    }

    /**
     * Check if authentication is properly configured
     */
    public function is_configured()
    {
        switch ($this->auth_type) {
            case self::AUTH_TYPE_BASIC:
                return !empty($this->merchant_id) && !empty($this->password);
            
            case self::AUTH_TYPE_API_KEY:
                return !empty($this->api_key);
            
            case self::AUTH_TYPE_BEARER:
                return !empty($this->access_token);
            
            case self::AUTH_TYPE_SIGNED:
                return !empty($this->api_key) && !empty($this->secret_key);
            
            default:
                return false;
        }
    }

    /**
     * Get authentication method description
     */
    public function get_auth_description()
    {
        switch ($this->auth_type) {
            case self::AUTH_TYPE_BASIC:
                return __('Basic authentication using Merchant ID and Password in request body', 'woocommerce-key2pay-gateway');
            
            case self::AUTH_TYPE_API_KEY:
                return __('API Key authentication using X-API-Key header', 'woocommerce-key2pay-gateway');
            
            case self::AUTH_TYPE_BEARER:
                return __('Bearer token authentication using Authorization header', 'woocommerce-key2pay-gateway');
            
            case self::AUTH_TYPE_SIGNED:
                return __('HMAC signed requests with API Key and Secret Key', 'woocommerce-key2pay-gateway');
            
            default:
                return __('Unknown authentication method', 'woocommerce-key2pay-gateway');
        }
    }
} 