<?php
/**
 * Stripe Payment Gateway Class
 * 
 * Handles Stripe integration with WooCommerce
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Stripe Payment Gateway Class
 */
class YPrint_Stripe_Payment_Gateway extends WC_Payment_Gateway {

    /**
     * Constructor for the gateway.
     */
    public function __construct() {
        $this->id                 = 'yprint_stripe';
        $this->icon               = apply_filters('yprint_stripe_icon', YPRINT_PLUGIN_URL . 'assets/images/stripe.png');
        $this->has_fields         = true;
        $this->method_title       = __('YPrint Stripe', 'yprint-plugin');
        $this->method_description = __('Accept payments via Stripe - Credit Cards, Apple Pay, and more.', 'yprint-plugin');
        $this->supports           = array(
            'products',
            'refunds',
        );

        // Load the settings
        $this->init_form_fields();
        $this->init_settings();

        // Define user set variables
        $this->title        = $this->get_option('title');
        $this->description  = $this->get_option('description');
        $this->enabled      = $this->get_option('enabled');
        $this->testmode     = 'yes' === $this->get_option('testmode');

        // Actions
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
        add_action('woocommerce_api_yprint_stripe', array(YPrint_Stripe_Webhook_Handler::get_instance(), 'handle_webhook'));
    }

    /**
     * Initialize Gateway Settings Form Fields
     */
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'       => __('Enable/Disable', 'yprint-plugin'),
                'label'       => __('Enable YPrint Stripe', 'yprint-plugin'),
                'type'        => 'checkbox',
                'description' => '',
                'default'     => 'no',
            ),
            'title' => array(
                'title'       => __('Title', 'yprint-plugin'),
                'type'        => 'text',
                'description' => __('This controls the title which the user sees during checkout.', 'yprint-plugin'),
                'default'     => __('Credit Card (Stripe)', 'yprint-plugin'),
                'desc_tip'    => true,
            ),
            'description' => array(
                'title'       => __('Description', 'yprint-plugin'),
                'type'        => 'textarea',
                'description' => __('This controls the description which the user sees during checkout.', 'yprint-plugin'),
                'default'     => __('Pay with your credit card via Stripe.', 'yprint-plugin'),
                'desc_tip'    => true,
            ),
            'testmode' => array(
                'title'       => __('Test mode', 'yprint-plugin'),
                'label'       => __('Enable Test Mode', 'yprint-plugin'),
                'type'        => 'checkbox',
                'description' => __('Place the payment gateway in test mode using test API keys.', 'yprint-plugin'),
                'default'     => 'yes',
                'desc_tip'    => true,
            ),
            'webhook_secret' => array(
                'title'       => __('Webhook Secret', 'yprint-plugin'),
                'type'        => 'password',
                'description' => __('The webhook secret is used to verify that webhook calls come from Stripe.', 'yprint-plugin') . ' ' . sprintf(__('Your webhook URL is: %s', 'yprint-plugin'), '<code>' . home_url('wc-api/yprint_stripe') . '</code>'),
                'default'     => '',
            ),
        );
    }

    /**
     * Process the payment and return the result.
     *
     * @param int $order_id
     * @return array
     */
    public function process_payment($order_id) {
        $order = wc_get_order($order_id);

        error_log('Processing payment for order: ' . $order_id);

        // Here you would implement the actual payment processing logic
        // For now, just mark as completed for testing purposes
        $order->payment_complete();
        
        // Remove cart
        WC()->cart->empty_cart();

        // Return thank you page redirect
        return array(
            'result'   => 'success',
            'redirect' => $this->get_return_url($order),
        );
    }

    /**
     * Process a refund if supported.
     *
     * @param  int    $order_id
     * @param  float  $amount
     * @param  string $reason
     * @return bool|WP_Error
     */
    public function process_refund($order_id, $amount = null, $reason = '') {
        // Here you would implement the actual refund processing logic
        return true;
    }

    /**
     * Check if SSL is enabled and warn the user if it's not.
     */
    public function admin_options() {
        if ($this->enabled === 'yes' && !is_ssl()) {
            echo '<div class="notice notice-warning"><p>' .
                __('YPrint Stripe requires SSL for security reasons. Your checkout may not be secure!', 'yprint-plugin') .
                '</p></div>';
        }

        parent::admin_options();
    }
    
    /**
     * Test the payment gateway integration.
     *
     * @return array Test results
     */
    public static function test_gateway() {
        try {
            // Check if WooCommerce is active
            if (!class_exists('WooCommerce')) {
                return array(
                    'success' => false,
                    'message' => __('WooCommerce is not active. Please activate WooCommerce to use the Stripe payment gateway.', 'yprint-plugin'),
                );
            }
            
            // Check if gateway is registered
            $payment_gateways = WC()->payment_gateways()->payment_gateways();
            if (!isset($payment_gateways['yprint_stripe'])) {
                return array(
                    'success' => false,
                    'message' => __('YPrint Stripe Gateway is not registered properly in WooCommerce.', 'yprint-plugin'),
                );
            }
            
            // Get the gateway settings
            $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
            
            // Check for API keys
            if (empty($stripe_settings)) {
                return array(
                    'success' => false,
                    'message' => __('Stripe API settings are not configured. Please configure them first.', 'yprint-plugin'),
                );
            }
            
            // Check webhook URL accessibility
            $webhook_url = home_url('wc-api/yprint_stripe');
            $response = wp_remote_get($webhook_url, array(
                'timeout' => 15,
                'sslverify' => false,
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Could not access webhook URL: %s', 'yprint-plugin'), $response->get_error_message()),
                    'details' => array(
                        'webhook_url' => $webhook_url,
                        'error' => $response->get_error_message(),
                    ),
                );
            }
            
            return array(
                'success' => true,
                'message' => __('YPrint Stripe Gateway is properly configured and registered with WooCommerce.', 'yprint-plugin'),
                'details' => array(
                    'webhook_url' => $webhook_url,
                    'is_enabled' => $payment_gateways['yprint_stripe']->enabled === 'yes',
                    'testmode' => $payment_gateways['yprint_stripe']->testmode,
                ),
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }
}