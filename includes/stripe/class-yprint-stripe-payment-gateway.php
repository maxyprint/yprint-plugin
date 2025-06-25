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

    public function __construct() {
        $this->id                 = 'yprint_stripe';
        $this->icon               = apply_filters('yprint_stripe_icon', YPRINT_PLUGIN_URL . 'assets/images/stripe.png');
        $this->has_fields         = true;
        $this->method_title       = __('YPrint Stripe', 'yprint-plugin');
        $this->method_description = sprintf(
            __('Accept payments via Stripe - Credit Cards, Apple Pay, and more. <br/><strong>Webhook URL:</strong> <code>%s</code><br/>Add this URL to your <a href="https://dashboard.stripe.com/webhooks" target="_blank">Stripe Webhook settings</a> and enter the webhook secret below.', 'yprint-plugin'),
            home_url('wc-api/yprint_stripe')
        );
        $this->supports           = array(
            'products',
            'refunds',
            'tokenization',
            'add_payment_method',
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
        add_action('woocommerce_api_yprint_stripe_verification', array($this, 'process_payment_verification'));
        
        // Add JavaScript for handling payment form
        add_action('wp_enqueue_scripts', array($this, 'payment_scripts'));
        
        // Pay for order screen - handle Apple Pay/Payment Request
        add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
        
        // Save payment method checkbox
        add_action('woocommerce_checkout_order_processed', array($this, 'save_payment_method_checkbox'), 10, 3);
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
        'webhook_settings' => array(
            'title'       => __('Webhook Settings', 'yprint-plugin'),
            'type'        => 'title',
            'description' => __('Webhooks allow Stripe to notify your site when events happen in your Stripe account, such as successful payments or refunds.', 'yprint-plugin'),
        ),
        'webhook_url' => array(
            'title'       => __('Webhook URL', 'yprint-plugin'),
            'type'        => 'text',
            'description' => __('Add this URL to your Stripe webhook settings to receive notifications about payments.', 'yprint-plugin'),
            'default'     => home_url('wc-api/yprint_stripe'),
            'disabled'    => true,
            'css'         => 'width: 400px;',
        ),
        'webhook_secret' => array(
            'title'       => __('Webhook Secret', 'yprint-plugin'),
            'type'        => 'password',
            'description' => __('The webhook secret is used to verify that webhook calls come from Stripe. Get this from your Stripe Dashboard → Developers → Webhooks.', 'yprint-plugin'),
            'default'     => '',
            'css'         => 'width: 400px;',
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
    
    // YPRINT DEBUG: Session-Daten vor Zahlungsverarbeitung
    if (class_exists('YPrint_Address_Manager')) {
        YPrint_Address_Manager::debug_session_data('stripe_process_payment_start');
        YPrint_Address_Manager::debug_order_addresses($order, 'stripe_before_processing');
    }

    try {
        // Check if we have a payment method ID directly
        $payment_method_id = isset($_POST['yprint_stripe_payment_method_id']) 
            ? wc_clean(wp_unslash($_POST['yprint_stripe_payment_method_id'])) 
            : '';
            
        // If no payment method provided, check if we should redirect to Stripe Checkout
        if (empty($payment_method_id)) {
            // Determine payment method types based on selected gateway
            $payment_method_types = ['card']; // Default
            
            // Check if SEPA was selected
            $selected_payment_method = isset($_POST['payment_method']) ? wc_clean(wp_unslash($_POST['payment_method'])) : '';
            if ($selected_payment_method === 'yprint_stripe_sepa') {
                $payment_method_types = ['sepa_debit'];
            } elseif ($selected_payment_method === 'yprint_stripe_card') {
                $payment_method_types = ['card'];
            }
            
            // Create payment intent
            $intent_data = array(
                'amount' => YPrint_Stripe_API::get_stripe_amount($order->get_total(), $order->get_currency()),
                'currency' => strtolower($order->get_currency()),
                'payment_method_types' => $payment_method_types,
                'description' => sprintf('Order #%s from %s', $order->get_order_number(), get_bloginfo('name')),
                'metadata' => array(
                    'order_id' => $order->get_id(),
                    'site_url' => get_site_url(),
                ),
                'receipt_email' => $order->get_billing_email(),
            );
            
            // Create the PaymentIntent
            $intent = YPrint_Stripe_API::request($intent_data, 'payment_intents');
            
            if (!empty($intent->error)) {
                throw new Exception($intent->error->message);
            }
            
            // Save intent ID to order
            $order->update_meta_data('_yprint_stripe_intent_id', $intent->id);
            $order->save();
            
            return array(
                'result'   => 'success',
                'redirect' => $this->get_stripe_checkout_url($intent),
            );
        }
        
        // If we have a payment method ID (from Apple Pay/Google Pay), create and confirm intent
        $intent_data = array(
            'amount' => YPrint_Stripe_API::get_stripe_amount($order->get_total(), $order->get_currency()),
            'currency' => strtolower($order->get_currency()),
            'payment_method' => $payment_method_id,
            'confirmation_method' => 'manual',
            'confirm' => true,
            'description' => sprintf('Order #%s from %s', $order->get_order_number(), get_bloginfo('name')),
            'metadata' => array(
                'order_id' => $order->get_id(),
                'site_url' => get_site_url(),
            ),
            'receipt_email' => $order->get_billing_email(),
        );
        
        // Create and confirm the PaymentIntent
        $intent = YPrint_Stripe_API::request($intent_data, 'payment_intents');
        
        if (!empty($intent->error)) {
            throw new Exception($intent->error->message);
        }
        
        // Check if authentication is required
        if ('requires_action' === $intent->status || 'requires_source_action' === $intent->status) {
            // 3D Secure is required
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order),
                'payment_intent_client_secret' => $intent->client_secret,
            );
        }
        
        if ('succeeded' === $intent->status) {
            // Payment is complete
            $order->payment_complete($intent->id);
            
            // Store the payment method ID
            $order->update_meta_data('_yprint_stripe_payment_method_id', $payment_method_id);
            $order->update_meta_data('_yprint_stripe_intent_id', $intent->id);
            
            // Add payment type info if available
            if (isset($_POST['payment_request_type'])) {
                $payment_type = wc_clean(wp_unslash($_POST['payment_request_type']));
                $order->update_meta_data('_yprint_stripe_payment_type', $payment_type);
                
                // Set a more user-friendly payment method title
                if ('apple_pay' === $payment_type) {
                    $order->set_payment_method_title('Apple Pay');
                } elseif ('google_pay' === $payment_type) {
                    $order->set_payment_method_title('Google Pay');
                }
            }
            
            $order->add_order_note(sprintf(__('Stripe payment complete (Payment Intent ID: %s)', 'yprint-plugin'), $intent->id));
            
            // CRITICAL: Apply YPrint address corrections BEFORE final save (Standard Checkout)
            if (class_exists('YPrint_Stripe_Checkout')) {
                error_log('🔍 YPRINT DEBUG: Applying address corrections for Standard Payment Order #' . $order->get_id());
                
                // Session-Daten vor Überschreibung sichern
                $yprint_selected = WC()->session ? WC()->session->get('yprint_selected_address', array()) : array();
                $yprint_billing = WC()->session ? WC()->session->get('yprint_billing_address', array()) : array();
                $yprint_billing_different = WC()->session ? WC()->session->get('yprint_billing_address_different', false) : false;
                
                error_log('🔍 YPRINT DEBUG: Standard Payment - Session preserved data check:');
                error_log('🔍 YPRINT DEBUG: - Selected Address empty: ' . (empty($yprint_selected) ? 'TRUE' : 'FALSE'));
                error_log('🔍 YPRINT DEBUG: - Billing Different: ' . ($yprint_billing_different ? 'TRUE' : 'FALSE'));
                
                // AUTORITATIVE YPRINT-ADRESS-ANWENDUNG: Überschreibe Stripe Payment Method Adressen mit YPrint-Daten
                if (!empty($yprint_selected)) {
                    error_log('🔍 YPRINT DEBUG: AUTORITATIVE OVERRIDE - Applying YPrint selected address for Standard Payment');
                    
                    // Setze Lieferadresse aus YPrint Session (überschreibt Stripe Payment Method)
                    $order->set_shipping_first_name($yprint_selected['first_name'] ?? '');
                    $order->set_shipping_last_name($yprint_selected['last_name'] ?? '');
                    $order->set_shipping_address_1($yprint_selected['address_1'] ?? '');
                    $order->set_shipping_address_2($yprint_selected['address_2'] ?? '');
                    $order->set_shipping_city($yprint_selected['city'] ?? '');
                    $order->set_shipping_postcode($yprint_selected['postcode'] ?? '');
                    $order->set_shipping_country($yprint_selected['country'] ?? 'DE');
                    $order->set_shipping_phone($yprint_selected['phone'] ?? '');
                    
                    // Setze Rechnungsadresse: Entweder separate Billing oder gleiche wie Shipping
                    if ($yprint_billing_different && !empty($yprint_billing)) {
                        error_log('🔍 YPRINT DEBUG: AUTORITATIVE OVERRIDE - Applying separate YPrint billing address for Standard Payment');
                        $order->set_billing_first_name($yprint_billing['first_name'] ?? '');
                        $order->set_billing_last_name($yprint_billing['last_name'] ?? '');
                        $order->set_billing_address_1($yprint_billing['address_1'] ?? '');
                        $order->set_billing_address_2($yprint_billing['address_2'] ?? '');
                        $order->set_billing_city($yprint_billing['city'] ?? '');
                        $order->set_billing_postcode($yprint_billing['postcode'] ?? '');
                        $order->set_billing_country($yprint_billing['country'] ?? 'DE');
                        $order->set_billing_phone($yprint_billing['phone'] ?? '');
                        // E-Mail bleibt aus Stripe Payment Method
                    } else {
                        error_log('🔍 YPRINT DEBUG: AUTORITATIVE OVERRIDE - Using YPrint shipping as billing address for Standard Payment');
                        $order->set_billing_first_name($yprint_selected['first_name'] ?? '');
                        $order->set_billing_last_name($yprint_selected['last_name'] ?? '');
                        $order->set_billing_address_1($yprint_selected['address_1'] ?? '');
                        $order->set_billing_address_2($yprint_selected['address_2'] ?? '');
                        $order->set_billing_city($yprint_selected['city'] ?? '');
                        $order->set_billing_postcode($yprint_selected['postcode'] ?? '');
                        $order->set_billing_country($yprint_selected['country'] ?? 'DE');
                        $order->set_billing_phone($yprint_selected['phone'] ?? '');
                        // E-Mail bleibt aus Stripe Payment Method
                    }
                    
                    // Markiere dass YPrint-Adressen autoritativ angewendet wurden
                    $order->update_meta_data('_yprint_addresses_applied_authoritatively', 'standard_payment');
                    $order->update_meta_data('_yprint_addresses_timestamp', current_time('mysql'));
                    
                    error_log('🔍 YPRINT DEBUG: AUTORITATIVE OVERRIDE COMPLETED for Standard Payment');
                } else {
                    error_log('🔍 YPRINT DEBUG: No YPrint session data found - keeping Stripe Payment Method addresses');
                }
            }
            
            $order->save();
            
            // Empty cart
            WC()->cart->empty_cart();
            
// CRITICAL: Apply YPrint address corrections BEFORE final success
if (class_exists('YPrint_Address_Manager')) {
    error_log('🔍 YPRINT DEBUG: Applying address corrections for Express Payment Order #' . $order->get_id());
    
    // Session-Daten vor Überschreibung sichern
    $yprint_selected = WC()->session ? WC()->session->get('yprint_selected_address', array()) : array();
    $yprint_billing = WC()->session ? WC()->session->get('yprint_billing_address', array()) : array();
    $yprint_billing_different = WC()->session ? WC()->session->get('yprint_billing_address_different', false) : false;
    
    error_log('🔍 YPRINT DEBUG: Express Payment - Session preserved data check:');
    error_log('🔍 YPRINT DEBUG: - Selected Address empty: ' . (empty($yprint_selected) ? 'TRUE' : 'FALSE'));
    error_log('🔍 YPRINT DEBUG: - Billing Different: ' . ($yprint_billing_different ? 'TRUE' : 'FALSE'));
    
    // AUTORITATIVE YPRINT-ADRESS-ANWENDUNG: Überschreibe Express Payment Adressen mit YPrint-Daten
    if (!empty($yprint_selected)) {
        error_log('🔍 YPRINT DEBUG: AUTORITATIVE OVERRIDE - Applying YPrint selected address');
        
        // Setze Lieferadresse aus YPrint Session (überschreibt Apple Pay/Google Pay)
        $order->set_shipping_first_name($yprint_selected['first_name'] ?? '');
        $order->set_shipping_last_name($yprint_selected['last_name'] ?? '');
        $order->set_shipping_address_1($yprint_selected['address_1'] ?? '');
        $order->set_shipping_address_2($yprint_selected['address_2'] ?? '');
        $order->set_shipping_city($yprint_selected['city'] ?? '');
        $order->set_shipping_postcode($yprint_selected['postcode'] ?? '');
        $order->set_shipping_country($yprint_selected['country'] ?? 'DE');
        $order->set_shipping_phone($yprint_selected['phone'] ?? '');
        
        // Setze Rechnungsadresse: Entweder separate Billing oder gleiche wie Shipping
        if ($yprint_billing_different && !empty($yprint_billing)) {
            error_log('🔍 YPRINT DEBUG: AUTORITATIVE OVERRIDE - Applying separate YPrint billing address');
            $order->set_billing_first_name($yprint_billing['first_name'] ?? '');
            $order->set_billing_last_name($yprint_billing['last_name'] ?? '');
            $order->set_billing_address_1($yprint_billing['address_1'] ?? '');
            $order->set_billing_address_2($yprint_billing['address_2'] ?? '');
            $order->set_billing_city($yprint_billing['city'] ?? '');
            $order->set_billing_postcode($yprint_billing['postcode'] ?? '');
            $order->set_billing_country($yprint_billing['country'] ?? 'DE');
            $order->set_billing_phone($yprint_billing['phone'] ?? '');
            // E-Mail bleibt aus Express Payment
        } else {
            error_log('🔍 YPRINT DEBUG: AUTORITATIVE OVERRIDE - Using YPrint shipping as billing address');
            $order->set_billing_first_name($yprint_selected['first_name'] ?? '');
            $order->set_billing_last_name($yprint_selected['last_name'] ?? '');
            $order->set_billing_address_1($yprint_selected['address_1'] ?? '');
            $order->set_billing_address_2($yprint_selected['address_2'] ?? '');
            $order->set_billing_city($yprint_selected['city'] ?? '');
            $order->set_billing_postcode($yprint_selected['postcode'] ?? '');
            $order->set_billing_country($yprint_selected['country'] ?? 'DE');
            $order->set_billing_phone($yprint_selected['phone'] ?? '');
            // E-Mail bleibt aus Express Payment
        }
        
        // Markiere dass YPrint-Adressen autoritativ angewendet wurden
        $order->update_meta_data('_yprint_addresses_applied_authoritatively', 'express_payment');
        $order->update_meta_data('_yprint_addresses_timestamp', current_time('mysql'));
        
        error_log('🔍 YPRINT DEBUG: AUTORITATIVE OVERRIDE COMPLETED for Express Payment');
    } else {
        error_log('🔍 YPRINT DEBUG: No YPrint session data found - keeping Express Payment addresses');
    }
}
            
            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }
        
        // If we reach this point, something unexpected happened
        $order->update_status('failed', __('Payment failed or was declined', 'yprint-plugin'));
        throw new Exception(__('Payment failed. Please try again.', 'yprint-plugin'));
        
    } catch (Exception $e) {
        wc_add_notice($e->getMessage(), 'error');
        
        // Update order status to failed
        $order->update_status('failed', $e->getMessage());
        
        error_log('Stripe payment failed: ' . $e->getMessage());
        
        return array(
            'result'   => 'failure',
            'redirect' => wc_get_checkout_url(),
        );
    }
}

/**
 * Get the Stripe Checkout URL
 *
 * @param object $intent
 * @return string
 */
private function get_stripe_checkout_url($intent) {
    $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
    $testmode = isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'];
    
    $key = $testmode ? $stripe_settings['test_publishable_key'] : $stripe_settings['publishable_key'];
    
    return add_query_arg(
        array(
            'payment_intent' => $intent->id,
            'key' => $key,
            'return_url' => rawurlencode($this->get_return_url(null)),
        ),
        'https://checkout.stripe.com/pay/'
    );
}

/**
 * Load payment scripts on checkout page
 */
public function payment_scripts() {
    // Don't load scripts if gateway is disabled
    if ('no' === $this->enabled) {
        return;
    }
    
    // Don't load scripts on non-checkout pages
    if (!is_checkout() && !is_wc_endpoint_url('order-pay') && !isset($_GET['pay_for_order'])) {
        return;
    }
    
    // Get Stripe settings
    $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
    $testmode = isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'];
    $publishable_key = $testmode ? $stripe_settings['test_publishable_key'] : $stripe_settings['publishable_key'];
    
    // Check if publishable key is set
    if (empty($publishable_key)) {
        return;
    }
    
    // Register and enqueue scripts
    wp_register_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
    wp_register_script(
        'yprint-stripe-checkout',
        YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-checkout.js',
        array('jquery', 'stripe-js'),
        YPRINT_PLUGIN_VERSION,
        true
    );
    
    // Localize script
    wp_localize_script(
        'yprint-stripe-checkout',
        'yprint_stripe_checkout_params',
        array(
            'publishable_key' => $publishable_key,
            'is_checkout'     => is_checkout() && empty($_GET['pay_for_order']),
            'is_pay_for_order' => isset($_GET['pay_for_order']),
            'is_order_pay'    => is_wc_endpoint_url('order-pay'),
            'ajax_url'        => admin_url('admin-ajax.php'),
            'return_url'      => $this->get_return_url(null),
            'nonce'           => wp_create_nonce('yprint-stripe-checkout'),
            'i18n'            => array(
                'card_error'        => __('There was an error processing your card. Please try again or use a different card.', 'yprint-plugin'),
                'card_declined'     => __('Your card was declined.', 'yprint-plugin'),
                'card_try_again'    => __('Please try again with a different card.', 'yprint-plugin'),
                'generic_error'     => __('Something went wrong. Please try again or use an alternative payment method.', 'yprint-plugin'),
            ),
        )
    );
    
    wp_enqueue_script('yprint-stripe-checkout');
    
    // Add inline styles for payment request buttons
    wp_add_inline_style('woocommerce-inline', '
        .yprint-stripe-payment-request-wrapper {
            margin-bottom: 20px;
        }
        .payment-method-separator {
            text-align: center;
            margin: 20px 0;
            position: relative;
        }
        .payment-method-separator:before {
            content: "";
            display: block;
            border-top: 1px solid #e6e6e6;
            width: 100%;
            height: 1px;
            position: absolute;
            top: 50%;
            z-index: 1;
        }
        .payment-method-separator span {
            background: #fff;
            padding: 0 10px;
            position: relative;
            z-index: 2;
            color: #666;
        }
    ');
}

/**
 * Display payment form fields on checkout
 */
public function payment_fields() {
    $description = $this->get_description();
    if (!empty($description)) {
        echo '<p>' . wp_kses_post($description) . '</p>';
    }
    
    echo '<div id="yprint-stripe-card-element"></div>';
    echo '<div id="yprint-stripe-card-errors" role="alert"></div>';
    
    if (is_user_logged_in()) {
        $this->save_payment_method_checkbox();
    }
    
    // Add nonce field
    wp_nonce_field('yprint_stripe_checkout_nonce', 'yprint_stripe_checkout_nonce');
}

/**
 * Output checkbox for saving payment method
 */
public function save_payment_method_checkbox() {
    // Only show if user is logged in
    if (!is_user_logged_in()) {
        return;
    }
    
    echo '<p class="form-row woocommerce-SavedPaymentMethods-saveNew">
        <input id="yprint_stripe_save_payment_method" name="yprint_stripe_save_payment_method" type="checkbox" value="yes" />
        <label for="yprint_stripe_save_payment_method">' . esc_html__('Save payment method for future purchases', 'yprint-plugin') . '</label>
    </p>';
}

/**
 * Output for the order received page.
 *
 * @param int $order_id
 */
public function receipt_page($order_id) {
    $order = wc_get_order($order_id);
    
    echo '<div id="yprint-stripe-payment-data" data-order-id="' . esc_attr($order_id) . '"></div>';
    
    // Check if we need to show payment request buttons
    $options = YPrint_Stripe_API::get_stripe_settings();
    if (isset($options['payment_request']) && 'yes' === $options['payment_request']) {
        echo '<div class="yprint-stripe-payment-request-wrapper" style="margin-top: 1em;">
            <div id="yprint-stripe-payment-request-button"></div>
        </div>';
        
        echo '<div class="payment-method-separator">
            <span>' . esc_html__('OR', 'yprint-plugin') . '</span>
        </div>';
    }
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
 * Process payment after customer returns from 3D Secure verification
 */
public function process_payment_verification() {
    if (!isset($_GET['payment_intent']) || !isset($_GET['wc_order_id'])) {
        return;
    }
    
    $order_id = intval($_GET['wc_order_id']);
    $intent_id = wc_clean(wp_unslash($_GET['payment_intent']));
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        return;
    }
    
    // If the order is already completed, redirect to the thank you page
    if ($order->has_status('completed') || $order->has_status('processing')) {
        wp_redirect($this->get_return_url($order));
        exit;
    }
    
    try {
        // Retrieve the PaymentIntent from Stripe
        $intent = YPrint_Stripe_API::request(array(), 'payment_intents/' . $intent_id, 'GET');
        
        if (!empty($intent->error)) {
            throw new Exception($intent->error->message);
        }
        
        if ('succeeded' === $intent->status) {
            // Payment is complete
            $order->payment_complete($intent->id);
            $order->add_order_note(sprintf(__('Stripe payment complete (Payment Intent ID: %s)', 'yprint-plugin'), $intent->id));
            $order->save();
            
            // Empty cart
            WC()->cart->empty_cart();
            
            wp_redirect($this->get_return_url($order));
            exit;
        }
        
        if ('requires_payment_method' === $intent->status) {
            // Payment failed
            $order->update_status('failed', __('Payment authentication failed or customer cancelled.', 'yprint-plugin'));
            wc_add_notice(__('Payment failed. Please try again with a different payment method.', 'yprint-plugin'), 'error');
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        
        // Handle other status cases
        $order->update_status('on-hold', sprintf(__('Payment status: %s. Manual verification required.', 'yprint-plugin'), $intent->status));
        wc_add_notice(__('Your payment is being processed. Please contact us if you encounter any issues.', 'yprint-plugin'), 'notice');
        wp_redirect($this->get_return_url($order));
        exit;
        
    } catch (Exception $e) {
        wc_add_notice($e->getMessage(), 'error');
        wp_redirect(wc_get_checkout_url());
        exit;
    }
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