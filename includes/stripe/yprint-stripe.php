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
        
        // DEBUG: Erstelle JavaScript-Output für Browser-Konsole
        $debug_info = array(
            'testmode_setting' => $testmode ? 'YES' : 'NO',
            'live_key_set' => !empty($options['secret_key']) ? 'YES' : 'NO',
            'test_key_set' => !empty($options['test_secret_key']) ? 'YES' : 'NO'
        );
        
        self::set_testmode($testmode);
        
        if ($testmode) {
            $secret_key = isset($options['test_secret_key']) ? $options['test_secret_key'] : '';
            $debug_info['using_key_type'] = 'TEST';
            $debug_info['key_prefix'] = substr($secret_key, 0, 10);
        } else {
            $secret_key = isset($options['secret_key']) ? $options['secret_key'] : '';
            $debug_info['using_key_type'] = 'LIVE';
            $debug_info['key_prefix'] = substr($secret_key, 0, 10);
        }
        
        self::set_secret_key($secret_key);
        
        // JavaScript-Konsolen-Output für Frontend
        add_action('wp_footer', function() use ($debug_info) {
            echo '<script>console.log("=== STRIPE MODE DEBUG ===", ' . wp_json_encode($debug_info) . ');</script>';
        });
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
    
    // CRITICAL DEBUG: API-Key-Validierung
    $secret_key = self::get_secret_key();
    $is_test_key = strpos($secret_key, 'sk_test_') === 0;
    $is_live_key = strpos($secret_key, 'sk_live_') === 0;
    
    // JavaScript-Output für Browser-Konsole
    add_action('wp_footer', function() use ($secret_key, $is_test_key, $is_live_key, $method, $api) {
        echo '<script>console.log("🔍 STRIPE API REQUEST:", {
            "endpoint": "' . esc_js($method . ' ' . self::ENDPOINT . $api) . '",
            "key_type": "' . ($is_test_key ? 'TEST_KEY' : ($is_live_key ? 'LIVE_KEY' : 'UNKNOWN_KEY')) . '",
            "key_prefix": "' . esc_js(substr($secret_key, 0, 12)) . '...",
            "timestamp": "' . date('Y-m-d H:i:s') . '"
        });</script>';
    });
    
    error_log('Stripe API Request: ' . $method . ' ' . self::ENDPOINT . $api);
    error_log('Request data: ' . wp_json_encode($request));
    
    // CRITICAL DEBUG: Check for boolean conversion issues
    if (is_array($request)) {
        foreach ($request as $key => $value) {
            if (is_bool($value)) {
                error_log("BOOLEAN DETECTED in request: $key = " . ($value ? 'true' : 'false'));
            } elseif ($value === '1' || $value === '0') {
                error_log("STRING BOOLEAN DETECTED in request: $key = '$value'");
            }
        }
    }
    
    // Convert request data properly for Stripe
    if (is_array($request)) {
        $converted_request = array();
        foreach ($request as $key => $value) {
            if (is_bool($value)) {
                $converted_request[$key] = $value ? 'true' : 'false';
                error_log("CONVERTED BOOLEAN: $key from " . ($value ? 'PHP_TRUE' : 'PHP_FALSE') . " to '" . $converted_request[$key] . "'");
            } else {
                $converted_request[$key] = $value;
            }
        }
        $request = $converted_request;
        error_log('Request data after boolean conversion: ' . wp_json_encode($request));
    }
    
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
     * Get Stripe amount (in cents)
     *
     * @param float $amount
     * @param string $currency
     * @return int
     */
    public static function get_stripe_amount($amount, $currency = null) {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }
        
        $currency = strtolower($currency);
        
        // Zero decimal currencies
        $zero_decimal_currencies = array(
            'bif', 'djf', 'jpy', 'krw', 'pyg', 'vnd', 'xaf', 'xpf', 'kmf', 'mga', 'rwf', 'xof'
        );
        
        if (in_array($currency, $zero_decimal_currencies, true)) {
            return absint($amount);
        } else {
            return absint($amount * 100);
        }
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



// It's assumed this code exists within a class,
// and 'self::get_stripe_settings()' is a valid method within that class.
// For example, if these are methods of a class like YPrint_Stripe_Gateway.

/**
 * Test Apple Pay domain verification.
 *
 * This function initiates the Apple Pay domain verification process
 * by calling a method on the Apple Pay class instance.
 *
 * @return array API response with success/error details from the verification process.
 */
public static function test_apple_pay_domain_verification() {
    // Define the path to the Apple Pay class.
    // Using plugin_dir_path() is generally safer than YPRINT_PLUGIN_DIR directly
    // if YPRINT_PLUGIN_DIR is not guaranteed to have a trailing slash.
    $apple_pay_class_path = plugin_dir_path( __FILE__ ) . 'includes/stripe/class-yprint-stripe-apple-pay.php';

    // Ensure the Apple Pay class file exists and is loaded.
    // Use file_exists() for a cleaner check before requiring.
    if ( ! file_exists( $apple_pay_class_path ) ) {
        // Log an error or return an error message if the file is missing.
        // In a WordPress context, error_log() is good for server-side debugging.
        error_log( 'YPrint Stripe: Apple Pay class file not found at ' . $apple_pay_class_path );
        return array(
            'success' => false,
            'message' => esc_html__( 'Apple Pay class file not found.', 'your-text-domain' ),
            'details' => array( 'file_path' => $apple_pay_class_path )
        );
    }

    require_once $apple_pay_class_path;

    // Check if the class exists after requiring the file.
    if ( ! class_exists( 'YPrint_Stripe_Apple_Pay' ) ) {
        error_log( 'YPrint Stripe: Apple Pay class (YPrint_Stripe_Apple_Pay) not found after requiring file.' );
        return array(
            'success' => false,
            'message' => esc_html__( 'Apple Pay class not defined.', 'your-text-domain' ),
        );
    }

    // Get the singleton instance of the Apple Pay handler.
    $apple_pay = YPrint_Stripe_Apple_Pay::get_instance();

    // Call the domain verification method.
    // Renamed 'test_domain_verification' to 'verify_domain' for clarity
    // assuming it actually performs the verification, not just a 'test'.
    // If it truly is just a test that doesn't alter state, 'test_domain_verification' is fine.
    // I've kept your 'verify_domain' call as per your update.
    $result = $apple_pay->verify_domain();

    // Ensure the result is always an array, even if the called method doesn't return one.
    if ( ! is_array( $result ) ) {
        error_log( 'YPrint Stripe: verify_domain() did not return an array.' );
        return array(
            'success' => false,
            'message' => esc_html__( 'Unexpected response from Apple Pay verification.', 'your-text-domain' ),
            'raw_response' => $result // Include raw response for debugging
        );
    }

    return $result;
}

/**
 * Debug Stripe configuration for browser console.
 *
 * This function retrieves Stripe settings, determines the active keys (test/live),
 * and outputs debug information to the browser's console using JavaScript.
 *
 * @return array Configuration debug info.
 */
public static function debug_stripe_config() {
    // Retrieve Stripe settings. It's assumed 'self::get_stripe_settings()' exists
    // and returns an array of plugin options.
    $options = self::get_stripe_settings();

    // Determine if test mode is active.
    $testmode = isset( $options['testmode'] ) && 'yes' === $options['testmode'];

    // Get the appropriate secret and publishable keys based on test mode.
    // Using array_key_exists and null coalescing operator for robustness.
    $secret_key = $testmode
        ? ( $options['test_secret_key'] ?? '' )
        : ( $options['secret_key'] ?? '' );

    $publishable_key = $testmode
        ? ( $options['test_publishable_key'] ?? '' )
        : ( $options['publishable_key'] ?? '' );

    // Prepare debug information.
    // Use ternary operator for 'key_type' for conciseness.
    $debug_info = array(
        'testmode'               => $testmode,
        'secret_key_type'        => ( strpos( $secret_key, 'sk_test_' ) === 0 ) ? 'TEST' :
                                    ( ( strpos( $secret_key, 'sk_live_' ) === 0 ) ? 'LIVE' : 'INVALID' ),
        'publishable_key_type'   => ( strpos( $publishable_key, 'pk_test_' ) === 0 ) ? 'TEST' :
                                    ( ( strpos( $publishable_key, 'pk_live_' ) === 0 ) ? 'LIVE' : 'INVALID' ),
        // Only show prefixes for security. Using empty string for substr if key is empty.
        'secret_key_prefix'      => ! empty( $secret_key ) ? substr( $secret_key, 0, 12 ) : '',
        'publishable_key_prefix' => ! empty( $publishable_key ) ? substr( $publishable_key, 0, 12 ) : '',
    );

    // Add JavaScript output to the footer of the WordPress site.
    // This action ensures the script is loaded after the main content.
    // wp_json_encode() is safer for outputting JSON to JavaScript.
    // esc_js() is used for any string that goes directly into JavaScript.
    add_action( 'wp_footer', function() use ( $debug_info ) {
        // Only output if current user has capabilities to view debug info (e.g., 'manage_options').
        // This prevents debug info from appearing for regular visitors.
        if ( current_user_can( 'manage_options' ) || WP_DEBUG ) { // Consider a custom capability or WP_DEBUG
            // Output a script tag with the debug info.
            echo '<script type="text/javascript">';
            echo '/* <![CDATA[ */'; // CDATA for old browsers, though less critical now.
            echo 'console.log("🔍 STRIPE CONFIG DEBUG:", ' . wp_json_encode( $debug_info ) . ');';
            echo '/* ]]> */';
            echo '</script>';
        }
    });

    return $debug_info;
}}

// Your next function /** * Test Payment Request Button */ would go here.
// I've stopped before it as you only provided the start of its docblock.


// Load required classes
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-apple-pay.php';
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-payment-gateway.php';
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-webhook-handler.php';
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-payment-request.php';

// DEBUG: Hook to catch all script registrations and find yprint-checkout-header.js
add_action('wp_enqueue_scripts', function() {
    global $wp_scripts;
    if (isset($wp_scripts->registered)) {
        foreach ($wp_scripts->registered as $handle => $script) {
            if (strpos($handle, 'yprint') !== false || strpos($script->src, 'yprint') !== false) {
                error_log("YPRINT SCRIPT REGISTERED: Handle: $handle, Source: " . $script->src);
                if (strpos($script->src, 'checkout-header') !== false) {
                    error_log("CRITICAL: Found yprint-checkout-header.js registration!");
                    error_log("Handle: $handle");
                    error_log("Source: " . $script->src);
                    error_log("Full path: " . YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout-header.js');
                    error_log("File exists: " . (file_exists(YPRINT_PLUGIN_DIR . 'assets/js/yprint-checkout-header.js') ? 'YES' : 'NO'));
                    error_log("Registered from: " . wp_debug_backtrace_summary());
                }
            }
        }
    }
}, 999);

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