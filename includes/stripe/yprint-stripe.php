<?php
/**
 * Stripe API Class
 * 
 * Handles communication with the Stripe API
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Stripe API Class
 */
class YPrint_Stripe_API {

    /**
     * Stripe API Endpoint
     */
    const ENDPOINT = 'https://api.stripe.com/v1/';
    
    /**
     * Stripe API Version
     */
    const STRIPE_API_VERSION = '2023-10-16';

    /**
     * Secret API Key.
     *
     * @var string
     */
    private static $secret_key = '';

    /**
     * Is test mode active?
     *
     * @var bool
     */
    private static $testmode = false;

    /**
     * Set secret API Key.
     *
     * @param string $key
     */
    public static function set_secret_key($secret_key) {
        self::$secret_key = $secret_key;
    }

    /**
     * Get secret key.
     *
     * @return string
     */
    public static function get_secret_key() {
        if (empty(self::$secret_key)) {
            self::set_secret_key_for_mode();
        }
        return self::$secret_key;
    }

    /**
     * Set test mode.
     *
     * @param bool $testmode
     */
    public static function set_testmode($testmode) {
        self::$testmode = $testmode;
    }

    /**
     * Get test mode.
     *
     * @return bool
     */
    public static function is_testmode() {
        return self::$testmode;
    }

    /**
     * Set secret key based on mode.
     */
    public static function set_secret_key_for_mode() {
        $options = self::get_stripe_settings();
        $testmode = isset($options['testmode']) && 'yes' === $options['testmode'];
        self::set_testmode($testmode);
        
        if ($testmode) {
            $secret_key = isset($options['test_secret_key']) ? $options['test_secret_key'] : '';
        } else {
            $secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';
        }
        
        self::set_secret_key($secret_key);
    }

    /**
     * Get Stripe settings.
     *
     * @return array
     */
    public static function get_stripe_settings() {
        return get_option('yprint_stripe_settings', array());
    }

    /**
     * Update Stripe settings.
     *
     * @param array $settings
     */
    public static function update_stripe_settings($settings) {
        update_option('yprint_stripe_settings', $settings);
        
        // Trigger a hook after settings are updated
        do_action('yprint_stripe_settings_updated');
    }

    /**
     * Generates the user agent we use to pass to API request.
     *
     * @return array
     */
    public static function get_user_agent() {
        $app_info = array(
            'name'       => 'YPrint Stripe Integration',
            'version'    => YPRINT_PLUGIN_VERSION,
            'url'        => site_url(),
        );

        return array(
            'lang'         => 'php',
            'lang_version' => phpversion(),
            'publisher'    => 'yprint',
            'uname'        => php_uname(),
            'application'  => $app_info,
        );
    }

    /**
 * Test Payment Request Button
 *
 * @return array Response with success/error details
 */
public static function test_payment_request_button() {
    try {
        // Ensure we have API keys set
        if (empty(self::get_secret_key())) {
            return array(
                'success' => false,
                'message' => __('API key is not set. Please save your settings first.', 'yprint-plugin'),
            );
        }
        
        // Check if payment request button is enabled
        $options = self::get_stripe_settings();
        $payment_request_enabled = isset($options['payment_request']) && 'yes' === $options['payment_request'];
        
        if (!$payment_request_enabled) {
            return array(
                'success' => false,
                'message' => __('Payment Request Button is not enabled in settings.', 'yprint-plugin'),
                'details' => array(
                    'enabled' => false,
                ),
            );
        }
        
        // Check for Apple Pay domain registration
        $apple_pay_domain_set = isset($options['apple_pay_domain_set']) && 'yes' === $options['apple_pay_domain_set'];
        $apple_pay_verified_domain = isset($options['apple_pay_verified_domain']) ? $options['apple_pay_verified_domain'] : '';
        
        if (!$apple_pay_domain_set) {
            return array(
                'success' => false,
                'message' => __('Domain is not verified for Apple Pay. Please complete the domain verification first.', 'yprint-plugin'),
                'details' => array(
                    'enabled' => true,
                    'domain_verified' => false,
                    'domain' => $apple_pay_verified_domain
                ),
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Payment Request Button is enabled and domain is verified for Apple Pay.', 'yprint-plugin'),
            'details' => array(
                'enabled' => true,
                'domain_verified' => true,
                'domain' => $apple_pay_verified_domain
            ),
        );
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => $e->getMessage(),
        );
    }
}

    /**
     * Generates the headers to pass to API request.
     *
     * @return array
     */
    public static function get_headers() {
        $user_agent = self::get_user_agent();
        $app_info   = $user_agent['application'];

        $headers = array(
            'Authorization'  => 'Basic ' . base64_encode(self::get_secret_key() . ':'),
            'Stripe-Version' => self::STRIPE_API_VERSION,
            'User-Agent'     => $app_info['name'] . '/' . $app_info['version'] . ' (' . $app_info['url'] . ')',
            'X-Stripe-Client-User-Agent' => json_encode($user_agent),
        );

        return $headers;
    }

    /**
 * Send the request to Stripe's API
 *
 * @param array  $request
 * @param string $api
 * @param string $method
 * @return object
 * @throws Exception
 */
public static function request($request, $api = '', $method = 'POST') {
    $headers = self::get_headers();
    
    error_log('Stripe API Request: ' . $method . ' ' . self::ENDPOINT . $api);
    error_log('Request data: ' . wp_json_encode($request));
    
    // Erhöhter Timeout für API-Anfragen
    $response = wp_remote_request(
        self::ENDPOINT . $api,
        array(
            'method'  => $method,
            'headers' => $headers,
            'body'    => $request,
            'timeout' => 120, // Erhöht von 70 auf 120 Sekunden
            'sslverify' => true,
            'httpversion' => '1.1',
        )
    );

    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        $error_code = $response->get_error_code();
        error_log('Stripe API WP Error: Code: ' . $error_code . ', Message: ' . $error_message);
        throw new Exception($error_message);
    }
    
    if (empty($response['body'])) {
        $error_message = __('Empty response from Stripe', 'yprint-plugin');
        error_log('Stripe API Error: ' . $error_message);
        throw new Exception($error_message);
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    error_log('Stripe API Response Code: ' . $response_code);
    
    // Kürze den Response-Body für das Logging
    $log_body = strlen($body) > 1000 ? substr($body, 0, 1000) . '... [truncated]' : $body;
    error_log('Stripe API Response Body: ' . $log_body);
    
    $json_response = json_decode($body);
    
    // Überprüfen auf API-Fehler in der Antwort
    if (isset($json_response->error)) {
        $error_message = $json_response->error->message ?? __('Unknown Stripe API error', 'yprint-plugin');
        $error_type = $json_response->error->type ?? 'unknown';
        $error_code = $json_response->error->code ?? 'unknown';
        
        error_log("Stripe API Error: Type: {$error_type}, Code: {$error_code}, Message: {$error_message}");
    }

    return $json_response;
}

    /**
 * Test the API connection
 *
 * @return array API response with success/error details
 */
public static function test_connection() {
    try {
        // Sicherstellen, dass der Secret Key gesetzt ist
        if (empty(self::get_secret_key())) {
            return array(
                'success' => false,
                'message' => __('API key is not set. Please save your settings first.', 'yprint-plugin'),
            );
        }
        
        // Simple API call to check connection
        $response = self::request(array(), 'account', 'GET');
        
        // Check if payment request button is enabled
        $options = self::get_stripe_settings();
        $payment_request_enabled = isset($options['payment_request']) && 'yes' === $options['payment_request'];
        
        return array(
            'success' => true,
            'message' => __('Connection to Stripe API successful!', 'yprint-plugin'),
            'data' => array(
                'account' => $response,
                'payment_request_enabled' => $payment_request_enabled,
                'api_version' => self::STRIPE_API_VERSION,
                'testmode' => self::is_testmode(),
            ),
        );
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => $e->getMessage(),
        );
    }
}

    /**
     * Test Apple Pay domain verification
     *
     * @return array API response with success/error details
     */
    public static function test_apple_pay_domain_verification() {
        // Ensure Apple Pay class is loaded
        require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-apple-pay.php';
        
        // Test domain verification
        $apple_pay = YPrint_Stripe_Apple_Pay::get_instance();
        return $apple_pay->test_domain_verification();
    }
}

// Load required classes
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-apple-pay.php';
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-payment-gateway.php';
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-webhook-handler.php';
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-payment-request.php';

// Initialize classes
YPrint_Stripe_Apple_Pay::get_instance();
YPrint_Stripe_Webhook_Handler::get_instance();

// Ensure default Stripe settings exist for testing
add_action('init', function() {
    $settings = YPrint_Stripe_API::get_stripe_settings();
    if (empty($settings)) {
        // Erstelle Standard-Einstellungen für Tests
        $default_settings = array(
            'enabled' => 'yes',
            'testmode' => 'yes',
            'test_publishable_key' => '', // Wird vom Admin gesetzt
            'test_secret_key' => '', // Wird vom Admin gesetzt
            'publishable_key' => '',
            'secret_key' => '',
            'express_payments' => 'yes'
        );
        
        YPrint_Stripe_API::update_stripe_settings($default_settings);
        error_log('YPrint: Default Stripe settings created');
    }

    // Debug Plugin Load Order
add_action('plugins_loaded', function() {
    error_log('YPrint Plugin: plugins_loaded hook fired');
    error_log('WooCommerce available at plugins_loaded: ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
}, 5);

add_action('init', function() {
    error_log('YPrint Plugin: init hook fired');
    error_log('WooCommerce available at init: ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
}, 5);
});



// Add WooCommerce payment gateway
add_filter('woocommerce_payment_gateways', 'yprint_add_stripe_gateway');

/**
 * Add the Stripe Gateway to WooCommerce
 *
 * @param array $gateways WooCommerce payment gateways
 * @return array Payment gateways with Stripe added
 */
function yprint_add_stripe_gateway($gateways) {
    $gateways[] = 'YPrint_Stripe_Payment_Gateway';
    return $gateways;
}