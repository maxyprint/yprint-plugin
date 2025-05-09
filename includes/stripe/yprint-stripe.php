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
        
        $response = wp_remote_post(
            self::ENDPOINT . $api,
            array(
                'method'  => $method,
                'headers' => $headers,
                'body'    => $request,
                'timeout' => 70,
            )
        );

        if (is_wp_error($response) || empty($response['body'])) {
            $error_message = is_wp_error($response) ? $response->get_error_message() : __('Empty response from Stripe', 'yprint-plugin');
            throw new Exception($error_message);
        }

        return json_decode($response['body']);
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
        
        return array(
            'success' => true,
            'message' => __('Connection to Stripe API successful!', 'yprint-plugin'),
            'data' => $response,
        );
    } catch (Exception $e) {
        return array(
            'success' => false,
            'message' => $e->getMessage(),
        );
    }
}
}