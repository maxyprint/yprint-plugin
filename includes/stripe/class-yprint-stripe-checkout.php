<?php
/**
 * YPrint Stripe Checkout Implementation
 *
 * @package YPrint
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to implement the YPrint custom checkout experience with Stripe
 */
class YPrint_Stripe_Checkout {
    
    /**
     * Initialize the class
     */
    public static function init() {
        // Register shortcode for custom checkout
        add_shortcode('yprint_checkout', array(__CLASS__, 'checkout_shortcode'));
        
        // Enqueue necessary scripts and styles
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_checkout_assets'));
        
        // AJAX handlers for checkout steps
        add_action('wp_ajax_yprint_save_address', array(__CLASS__, 'ajax_save_address'));
        add_action('wp_ajax_yprint_set_payment_method', array(__CLASS__, 'ajax_set_payment_method'));
        add_action('wp_ajax_yprint_process_checkout', array(__CLASS__, 'ajax_process_checkout'));
        
        // Add custom checkout endpoint
        add_action('init', array(__CLASS__, 'add_checkout_endpoints'));
        
        // Capture order details
        add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'capture_order_details'));
    }
    
    /**
     * Register checkout endpoint for custom URLs
     */
    public static function add_checkout_endpoints() {
        add_rewrite_endpoint('checkout-step', EP_PAGES);
        
        // Flush rewrite rules only once
        if (get_option('yprint_checkout_endpoints_flushed') !== 'yes') {
            flush_rewrite_rules();
            update_option('yprint_checkout_endpoints_flushed', 'yes');
        }
    }
    
    /**
     * Enqueue scripts and styles for checkout
     */
    public static function enqueue_checkout_assets() {
        // Only load on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'yprint_checkout')) {
            // Stripe scripts
            wp_enqueue_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
            
            // CSS for checkout
            wp_enqueue_style(
                'yprint-checkout-style',
                YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css',
                array(),
                YPRINT_PLUGIN_VERSION
            );
            
            // Main checkout JS
            wp_enqueue_script(
                'yprint-checkout-js',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js',
                array('jquery'),
                YPRINT_PLUGIN_VERSION,
                true
            );
            
            // Stripe checkout JS
            wp_enqueue_script(
                'yprint-stripe-checkout-js',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-checkout.js',
                array('jquery', 'stripe-js', 'yprint-checkout-js'),
                YPRINT_PLUGIN_VERSION,
                true
            );
            
            // FontAwesome for icons
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css',
                array(),
                '5.15.4'
            );
            
            // Pass data to scripts
            $stripe_key = self::get_stripe_key();
            
            wp_localize_script(
                'yprint-checkout-js',
                'yprintCheckout',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'checkout_nonce' => wp_create_nonce('yprint-checkout-nonce'),
                    'is_logged_in' => is_user_logged_in() ? 'yes' : 'no',
                    'cart_url' => wc_get_cart_url(),
                    'checkout_url' => wc_get_checkout_url(),
                    'i18n' => array(
                        'required_field' => __('Dieses Feld ist erforderlich.', 'yprint'),
                        'invalid_email' => __('Bitte gib eine gültige E-Mail-Adresse ein.', 'yprint'),
                        'processing' => __('Wird verarbeitet...', 'yprint'),
                    )
                )
            );
            
            wp_localize_script(
                'yprint-stripe-checkout-js',
                'yprintStripe',
                array(
                    'public_key' => $stripe_key,
                    'processing' => __('Zahlungsvorgang läuft...', 'yprint'),
                    'card_error' => __('Kreditkartenfehler: ', 'yprint'),
                    'generic_error' => __('Ein Fehler ist aufgetreten. Bitte versuche es erneut.', 'yprint'),
                )
            );
        }
    }
    
    /**
     * Get the appropriate Stripe API key
     *
     * @return string The Stripe public key
     */
    public static function get_stripe_key() {
        $test_mode = get_option('woocommerce_stripe_settings')['testmode'] === 'yes';
        
        if ($test_mode) {
            return get_option('woocommerce_stripe_settings')['test_publishable_key'];
        } else {
            return get_option('woocommerce_stripe_settings')['publishable_key'];
        }
    }
    
    /**
     * Checkout shortcode implementation
     *
     * @param array $atts Shortcode attributes
     * @return string The checkout HTML
     */
    public static function checkout_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(
            array(
                'test_mode' => 'no',
            ),
            $atts,
            'yprint_checkout'
        );
        
        // Start output buffer
        ob_start();
        
        // Include the template
        include(YPRINT_PLUGIN_DIR . 'templates/checkout-multistep.php');
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * AJAX handler for saving address
     */
    public static function ajax_save_address() {
        check_ajax_referer('yprint-checkout-nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du musst angemeldet sein.', 'yprint')));
            return;
        }
        
        $user_id = get_current_user_id();
        $customer = new WC_Customer($user_id);
        
        // Get address data from request
        $address_data = isset($_POST['address']) ? wp_unslash($_POST['address']) : array();
        
        if (empty($address_data)) {
            wp_send_json_error(array('message' => __('Keine Adressdaten erhalten.', 'yprint')));
            return;
        }
        
        $address_id = isset($address_data['id']) ? sanitize_text_field($address_data['id']) : '';
        $address_type = isset($address_data['type']) ? sanitize_text_field($address_data['type']) : 'shipping';
        
        // Handle different address types
        if ($address_type === 'new') {
            // Add new address
            $new_address = array(
                'id' => 'addr_' . uniqid(),
                'name' => isset($address_data['name']) ? sanitize_text_field($address_data['name']) : __('Neue Adresse', 'yprint'),
                'first_name' => isset($address_data['first_name']) ? sanitize_text_field($address_data['first_name']) : '',
                'last_name' => isset($address_data['last_name']) ? sanitize_text_field($address_data['last_name']) : '',
                'company' => isset($address_data['company']) ? sanitize_text_field($address_data['company']) : '',
                'address_1' => isset($address_data['address_1']) ? sanitize_text_field($address_data['address_1']) : '',
                'address_2' => isset($address_data['address_2']) ? sanitize_text_field($address_data['address_2']) : '',
                'postcode' => isset($address_data['postcode']) ? sanitize_text_field($address_data['postcode']) : '',
                'city' => isset($address_data['city']) ? sanitize_text_field($address_data['city']) : '',
                'country' => isset($address_data['country']) ? sanitize_text_field($address_data['country']) : 'DE',
                'is_company' => isset($address_data['is_company']) ? (bool)$address_data['is_company'] : false,
            );
            
            // Get existing additional addresses
            $additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
            if (!is_array($additional_addresses)) {
                $additional_addresses = array();
            }
            
            // Add new address and save
            $additional_addresses[] = $new_address;
            update_user_meta($user_id, 'additional_shipping_addresses', $additional_addresses);
            
            // Set as selected address for this order
            WC()->customer->set_shipping_address_1($new_address['address_1']);
            WC()->customer->set_shipping_address_2($new_address['address_2']);
            WC()->customer->set_shipping_city($new_address['city']);
            WC()->customer->set_shipping_postcode($new_address['postcode']);
            WC()->customer->set_shipping_country($new_address['country']);
            WC()->customer->set_shipping_first_name($new_address['first_name']);
            WC()->customer->set_shipping_last_name($new_address['last_name']);
            WC()->customer->set_shipping_company($new_address['company']);
            
            // Also set as default shipping address
            $save_address = isset($address_data['save_address']) ? (bool)$address_data['save_address'] : false;
            if ($save_address) {
                $customer->set_shipping_address_1($new_address['address_1']);
                $customer->set_shipping_address_2($new_address['address_2']);
                $customer->set_shipping_city($new_address['city']);
                $customer->set_shipping_postcode($new_address['postcode']);
                $customer->set_shipping_country($new_address['country']);
                $customer->set_shipping_first_name($new_address['first_name']);
                $customer->set_shipping_last_name($new_address['last_name']);
                $customer->set_shipping_company($new_address['company']);
                $customer->save();
            }
            
            $response = array(
                'message' => __('Neue Adresse hinzugefügt und ausgewählt.', 'yprint'),
                'address_id' => $new_address['id'],
            );
            
        } elseif ($address_type === 'shipping') {
            // Use standard shipping address
            
            // Set shipping address for this order
            // No need to do anything as this is the default address
            
            $response = array(
                'message' => __('Standard-Lieferadresse ausgewählt.', 'yprint'),
                'address_id' => 'shipping',
            );
            
        } else {
            // Use an additional address from saved addresses
            
            // Get existing additional addresses
            $additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
            if (!is_array($additional_addresses)) {
                $additional_addresses = array();
            }
            
            // Find the selected address
            $selected_address = null;
            foreach ($additional_addresses as $address) {
                if ($address['id'] === $address_id) {
                    $selected_address = $address;
                    break;
                }
            }
            
            if ($selected_address) {
                // Set as selected address for this order
                WC()->customer->set_shipping_address_1($selected_address['address_1']);
                WC()->customer->set_shipping_address_2($selected_address['address_2']);
                WC()->customer->set_shipping_city($selected_address['city']);
                WC()->customer->set_shipping_postcode($selected_address['postcode']);
                WC()->customer->set_shipping_country($selected_address['country']);
                WC()->customer->set_shipping_first_name($selected_address['first_name']);
                WC()->customer->set_shipping_last_name($selected_address['last_name']);
                WC()->customer->set_shipping_company($selected_address['company']);
                
                $response = array(
                    'message' => __('Lieferadresse ausgewählt.', 'yprint'),
                    'address_id' => $address_id,
                );
            } else {
                wp_send_json_error(array('message' => __('Adresse nicht gefunden.', 'yprint')));
                return;
            }
        }
        
        // Set as default address if requested
        $set_default = isset($address_data['set_default']) ? (bool)$address_data['set_default'] : false;
        if ($set_default) {
            update_user_meta($user_id, 'default_shipping_address', $address_id);
            $response['is_default'] = true;
        }
        
        // Calculate shipping for the selected address
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
        
        // Add updated totals to response
        $response['cart_total'] = WC()->cart->get_total();
        $response['cart_subtotal'] = WC()->cart->get_cart_subtotal();
        $response['shipping_total'] = WC()->cart->get_cart_shipping_total();
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX handler for setting payment method
     */
    public static function ajax_set_payment_method() {
        check_ajax_referer('yprint-checkout-nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du musst angemeldet sein.', 'yprint')));
            return;
        }
        
        // Get payment data from request
        $payment_data = isset($_POST['payment']) ? wp_unslash($_POST['payment']) : array();
        
        if (empty($payment_data)) {
            wp_send_json_error(array('message' => __('Keine Zahlungsdaten erhalten.', 'yprint')));
            return;
        }
        
        $payment_method = isset($payment_data['method']) ? sanitize_text_field($payment_data['method']) : '';
        
        if (empty($payment_method)) {
            wp_send_json_error(array('message' => __('Keine Zahlungsmethode ausgewählt.', 'yprint')));
            return;
        }
        
        // Store the chosen payment method in session
        WC()->session->set('chosen_payment_method', $payment_method);
        
        // Handle saved payment methods
        $saved_payment_id = isset($payment_data['saved_id']) ? sanitize_text_field($payment_data['saved_id']) : '';
        
        if (!empty($saved_payment_id) && $saved_payment_id !== 'new') {
            WC()->session->set('chosen_payment_saved_id', $saved_payment_id);
        } else {
            WC()->session->__unset('chosen_payment_saved_id');
        }
        
        // Handle specific payment method data
        if ($payment_method === 'stripe') {
            $stripe_token = isset($payment_data['token']) ? sanitize_text_field($payment_data['token']) : '';
            
            if (empty($stripe_token) && empty($saved_payment_id)) {
                wp_send_json_error(array('message' => __('Keine Stripe-Zahlungsdaten erhalten.', 'yprint')));
                return;
            }
            
            if (!empty($stripe_token)) {
                WC()->session->set('stripe_payment_token', $stripe_token);
            }
            
            // Store option to save payment method
            $save_payment = isset($payment_data['save']) ? (bool)$payment_data['save'] : false;
            WC()->session->set('save_stripe_payment', $save_payment);
        }
        
        wp_send_json_success(array(
            'message' => __('Zahlungsmethode aktualisiert.', 'yprint'),
            'payment_method' => $payment_method
        ));
    }
    
    /**
     * AJAX handler for processing checkout
     */
    public static function ajax_process_checkout() {
        check_ajax_referer('yprint-checkout-nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Du musst angemeldet sein.', 'yprint')));
            return;
        }
        
        // Check terms acceptance
        $terms_accepted = isset($_POST['terms_accepted']) ? (bool)$_POST['terms_accepted'] : false;
        
        if (!$terms_accepted) {
            wp_send_json_error(array('message' => __('Bitte akzeptiere unsere AGB und Datenschutzerklärung.', 'yprint')));
            return;
        }
        
        // Check if we have shipping and payment methods set
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $chosen_payment_method = WC()->session->get('chosen_payment_method');
        
        if (empty($chosen_shipping_methods)) {
            wp_send_json_error(array('message' => __('Bitte wähle eine Versandmethode.', 'yprint')));
            return;
        }
        
        if (empty($chosen_payment_method)) {
            wp_send_json_error(array('message' => __('Bitte wähle eine Zahlungsmethode.', 'yprint')));
            return;
        }
        
        // Set order data
        $order_data = array(
            'payment_method' => $chosen_payment_method,
            'shipping_method' => $chosen_shipping_methods,
            'terms_accepted' => $terms_accepted,
        );
        
        // Store order data in session for use during order creation
        WC()->session->set('yprint_checkout_data', $order_data);
        
        try {
            // Create the order
            $checkout = WC()->checkout();
            $order_id = $checkout->create_order(array());
            
            if (is_wp_error($order_id)) {
                throw new Exception($order_id->get_error_message());
            }
            
            $order = wc_get_order($order_id);
            
            // Mark order as pending
            $order->update_status('pending', __('Bestellung über YPrint Checkout aufgegeben.', 'yprint'));
            
            // Process payment
            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
            $result = $available_gateways[$chosen_payment_method]->process_payment($order_id);
            
            if (!empty($result['result']) && $result['result'] === 'success') {
                // Payment successful
                wp_send_json_success(array(
                    'message' => __('Bestellung erfolgreich aufgegeben.', 'yprint'),
                    'redirect' => $result['redirect'],
                    'order_id' => $order_id
                ));
            } else {
                // Payment failed
                throw new Exception(__('Zahlungsverarbeitung fehlgeschlagen.', 'yprint'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ));
        }
    }
    
    /**
     * Capture additional order details during checkout
     *
     * @param int $order_id The order ID
     */
    public static function capture_order_details($order_id) {
        if (!$order_id) {
            return;
        }
        
        $order = wc_get_order($order_id);
        $checkout_data = WC()->session->get('yprint_checkout_data');
        
        if (empty($checkout_data)) {
            return;
        }
        
        // Store terms acceptance
        if (isset($checkout_data['terms_accepted']) && $checkout_data['terms_accepted']) {
            update_post_meta($order_id, '_terms_accepted', 'yes');
        }
        
        // Store which saved address was used
        $address_id = WC()->session->get('chosen_shipping_address_id');
        if (!empty($address_id)) {
            update_post_meta($order_id, '_shipping_address_id', $address_id);
        }
        
        // Store which saved payment method was used
        $saved_payment_id = WC()->session->get('chosen_payment_saved_id');
        if (!empty($saved_payment_id)) {
            update_post_meta($order_id, '_payment_method_id', $saved_payment_id);
        }
        
        // Clean up session data
        WC()->session->__unset('yprint_checkout_data');
        WC()->session->__unset('chosen_shipping_address_id');
        WC()->session->__unset('chosen_payment_saved_id');
        WC()->session->__unset('stripe_payment_token');
        WC()->session->__unset('save_stripe_payment');
    }
}

// Initialize the class
YPrint_Stripe_Checkout::init();