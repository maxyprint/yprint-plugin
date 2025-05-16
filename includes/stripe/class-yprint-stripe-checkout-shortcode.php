<?php
/**
 * YPrint Stripe Checkout Shortcode Implementation
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Stripe Checkout Shortcode Class
 */
class YPrint_Stripe_Checkout_Shortcode {

    /**
     * Singleton instance
     *
     * @var YPrint_Stripe_Checkout_Shortcode
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     *
     * @return YPrint_Stripe_Checkout_Shortcode
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Register shortcode
        add_shortcode('yprint_checkout', array($this, 'render_checkout'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add AJAX handlers for logged-in and logged-out users
        add_action('wp_ajax_yprint_save_address', array($this, 'ajax_save_address'));
        add_action('wp_ajax_nopriv_yprint_save_address', array($this, 'ajax_save_address'));

        add_action('wp_ajax_yprint_set_payment_method', array($this, 'ajax_set_payment_method'));
        add_action('wp_ajax_nopriv_yprint_set_payment_method', array($this, 'ajax_set_payment_method'));

        add_action('wp_ajax_yprint_process_checkout', array($this, 'ajax_process_checkout'));
        add_action('wp_ajax_nopriv_yprint_process_checkout', array($this, 'ajax_process_checkout'));

        add_action('wp_ajax_yprint_update_checkout', array($this, 'ajax_update_checkout'));
        add_action('wp_ajax_nopriv_yprint_update_checkout', array($this, 'ajax_update_checkout'));

        // Add AJAX handler for fetching states based on country
        add_action('wp_ajax_yprint_get_states', array($this, 'ajax_get_states'));
        add_action('wp_ajax_nopriv_yprint_get_states', array($this, 'ajax_get_states'));
        
        // Add custom checkout endpoint
        add_action('init', array($this, 'add_checkout_endpoints'));
        
        // Capture order details
        add_action('woocommerce_checkout_update_order_meta', array($this, 'capture_order_details'));
    }
    
    /**
     * Register checkout endpoint for custom URLs
     */
    public function add_checkout_endpoints() {
        add_rewrite_endpoint('checkout-step', EP_PAGES);
        
        // Flush rewrite rules only once
        if (get_option('yprint_checkout_endpoints_flushed') !== 'yes') {
            flush_rewrite_rules();
            update_option('yprint_checkout_endpoints_flushed', 'yes');
        }
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on pages with our shortcode
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
            $stripe_key = $this->get_stripe_key();
            
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
                        'required_field' => __('Dieses Feld ist erforderlich.', 'yprint-plugin'),
                        'invalid_email' => __('Bitte gib eine gültige E-Mail-Adresse ein.', 'yprint-plugin'),
                        'processing' => __('Wird verarbeitet...', 'yprint-plugin'),
                        'checkout_error' => __('Error processing checkout. Please try again.', 'yprint-plugin'),
                        'place_order' => __('Place Order', 'yprint-plugin'),
                        'no_shipping_methods' => __('No shipping methods available', 'yprint-plugin'),
                        'select_state_text' => __('Select a state / county...', 'woocommerce'),
                        'generic_error' => __('An error occurred. Please try again.', 'yprint-plugin'),
                        'terms_required' => __('Bitte akzeptiere unsere AGB und Datenschutzerklärung.', 'yprint-plugin'),
                    )
                )
            );
            
            wp_localize_script(
                'yprint-stripe-checkout-js',
                'yprintStripe',
                array(
                    'public_key' => $stripe_key,
                    'processing' => __('Zahlungsvorgang läuft...', 'yprint-plugin'),
                    'card_error' => __('Kreditkartenfehler: ', 'yprint-plugin'),
                    'generic_error' => __('Ein Fehler ist aufgetreten. Bitte versuche es erneut.', 'yprint-plugin'),
                )
            );
        }
    }
    
    /**
     * Get the appropriate Stripe API key
     *
     * @return string The Stripe public key
     */
    private function get_stripe_key() {
        $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
        $test_mode = isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'];
        
        if ($test_mode) {
            return isset($stripe_settings['test_publishable_key']) ? $stripe_settings['test_publishable_key'] : '';
        } else {
            return isset($stripe_settings['publishable_key']) ? $stripe_settings['publishable_key'] : '';
        }
    }

    /**
     * Render checkout shortcode output
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render_checkout($atts) {
        // Parse shortcode attributes
        $atts = shortcode_atts(
            array(
                'test_mode' => 'no',
            ),
            $atts,
            'yprint_checkout'
        );
        
        // Start output buffering
        ob_start();
        
        // Include the main template
        include YPRINT_PLUGIN_DIR . 'templates/checkout-multistep.php';
        
        // Return the buffered content
        return ob_get_clean();
    }

    /**
     * AJAX handler for saving address
     */
    public function ajax_save_address() {
        check_ajax_referer('yprint-checkout-nonce', 'security');
        
        // Get address data from request
        $address_data = isset($_POST['address']) ? wp_unslash($_POST['address']) : array();
        
        if (empty($address_data)) {
            wp_send_json_error(array('message' => __('Keine Adressdaten erhalten.', 'yprint-plugin')));
            return;
        }
        
        $user_id = get_current_user_id();
        $address_id = isset($address_data['id']) ? sanitize_text_field($address_data['id']) : '';
        $address_type = isset($address_data['type']) ? sanitize_text_field($address_data['type']) : 'shipping';
        
        // Handle different address types
        if ($address_type === 'new') {
            // Add new address
            $new_address = array(
                'id' => 'addr_' . uniqid(),
                'name' => isset($address_data['address_name']) ? sanitize_text_field($address_data['address_name']) : __('Neue Adresse', 'yprint-plugin'),
                'first_name' => isset($address_data['shipping_first_name']) ? sanitize_text_field($address_data['shipping_first_name']) : '',
                'last_name' => isset($address_data['shipping_last_name']) ? sanitize_text_field($address_data['shipping_last_name']) : '',
                'company' => isset($address_data['shipping_company']) ? sanitize_text_field($address_data['shipping_company']) : '',
                'address_1' => isset($address_data['shipping_address_1']) ? sanitize_text_field($address_data['shipping_address_1']) : '',
                'address_2' => isset($address_data['shipping_address_2']) ? sanitize_text_field($address_data['shipping_address_2']) : '',
                'postcode' => isset($address_data['shipping_postcode']) ? sanitize_text_field($address_data['shipping_postcode']) : '',
                'city' => isset($address_data['shipping_city']) ? sanitize_text_field($address_data['shipping_city']) : '',
                'country' => isset($address_data['shipping_country']) ? sanitize_text_field($address_data['shipping_country']) : 'DE',
            );
            
            if (is_user_logged_in()) {
                // Get existing additional addresses for logged in users
                $additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
                if (!is_array($additional_addresses)) {
                    $additional_addresses = array();
                }
                
                // Add new address and save
                $additional_addresses[$new_address['id']] = $new_address;
                update_user_meta($user_id, 'additional_shipping_addresses', $additional_addresses);
            }
            
            // Set as selected address for this order
            WC()->customer->set_shipping_address_1($new_address['address_1']);
            WC()->customer->set_shipping_address_2($new_address['address_2']);
            WC()->customer->set_shipping_city($new_address['city']);
            WC()->customer->set_shipping_postcode($new_address['postcode']);
            WC()->customer->set_shipping_country($new_address['country']);
            WC()->customer->set_shipping_first_name($new_address['first_name']);
            WC()->customer->set_shipping_last_name($new_address['last_name']);
            WC()->customer->set_shipping_company($new_address['company']);
            
            // Also set as default shipping address if requested
            $save_address = isset($address_data['save_address']) ? (bool)$address_data['save_address'] : false;
            if ($save_address && is_user_logged_in()) {
                $customer = new WC_Customer($user_id);
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
                'message' => __('Neue Adresse hinzugefügt und ausgewählt.', 'yprint-plugin'),
                'address_id' => $new_address['id'],
            );
            
        } elseif ($address_type === 'shipping') {
            // Use standard shipping address
            // This is already the default, so no need to change anything
            
            $response = array(
                'message' => __('Standard-Lieferadresse ausgewählt.', 'yprint-plugin'),
                'address_id' => 'shipping',
            );
            
        } else {
            // Use an additional address from saved addresses
            if (is_user_logged_in()) {
                // Get existing additional addresses
                $additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
                if (!is_array($additional_addresses)) {
                    $additional_addresses = array();
                }
                
                // Find the selected address
                if (isset($additional_addresses[$address_id])) {
                    $selected_address = $additional_addresses[$address_id];
                    
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
                        'message' => __('Lieferadresse ausgewählt.', 'yprint-plugin'),
                        'address_id' => $address_id,
                    );
                } else {
                    wp_send_json_error(array('message' => __('Adresse nicht gefunden.', 'yprint-plugin')));
                    return;
                }
            } else {
                wp_send_json_error(array('message' => __('Benutzer ist nicht angemeldet.', 'yprint-plugin')));
                return;
            }
        }
        
        // Set billing address same as shipping if not different
        $ship_to_different_address = isset($address_data['ship_to_different_address']) ? (bool)$address_data['ship_to_different_address'] : false;
        
        if (!$ship_to_different_address) {
            WC()->customer->set_billing_address_1(WC()->customer->get_shipping_address_1());
            WC()->customer->set_billing_address_2(WC()->customer->get_shipping_address_2());
            WC()->customer->set_billing_city(WC()->customer->get_shipping_city());
            WC()->customer->set_billing_postcode(WC()->customer->get_shipping_postcode());
            WC()->customer->set_billing_country(WC()->customer->get_shipping_country());
            WC()->customer->set_billing_first_name(WC()->customer->get_shipping_first_name());
            WC()->customer->set_billing_last_name(WC()->customer->get_shipping_last_name());
            WC()->customer->set_billing_company(WC()->customer->get_shipping_company());
        }
        
        // Set as default address if requested
        if (is_user_logged_in()) {
            $set_default = isset($address_data['set_default']) ? (bool)$address_data['set_default'] : false;
            if ($set_default) {
                update_user_meta($user_id, 'default_shipping_address', $address_id);
                $response['is_default'] = true;
            }
        }
        
        // Calculate shipping for the selected address
        WC()->customer->save();
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
        
        // Add updated totals to response
        $response['cart_total'] = WC()->cart->get_total();
        $response['cart_subtotal'] = WC()->cart->get_cart_subtotal();
        $response['shipping_total'] = WC()->cart->get_cart_shipping_total();
        
        // Get available shipping methods after address update
        $shipping_methods = $this->get_available_shipping_methods();
        $response['shipping_methods'] = $shipping_methods;
        
        wp_send_json_success($response);
    }
    
    /**
     * AJAX handler for updating checkout
     */
    public function ajax_update_checkout() {
        check_ajax_referer('yprint-checkout-nonce', 'security');
        
        try {
            // Get checkout data from POST
            $checkout_data = isset($_POST['checkout_data']) ? wp_unslash($_POST['checkout_data']) : '';
            $parsed_data = array();
            
            if (!empty($checkout_data)) {
                parse_str($checkout_data, $parsed_data);
            }
            
            // Update customer data based on checkout form
            $this->update_customer_data($parsed_data);
            
            // Calculate totals and shipping
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
            
            // Prepare response data
            $response = array(
                'totals' => array(
                    'subtotal' => WC()->cart->get_cart_subtotal(),
                    'shipping' => WC()->cart->get_cart_shipping_total(),
                    'tax' => wc_price(WC()->cart->get_total_tax()),
                    'total' => WC()->cart->get_total('edit'),
                    'total_formatted' => WC()->cart->get_total(),
                ),
                'shipping_methods' => $this->get_available_shipping_methods(),
            );
            
            wp_send_json_success($response);
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
    }
    
    /**
     * AJAX handler for setting payment method
     */
    public function ajax_set_payment_method() {
        check_ajax_referer('yprint-checkout-nonce', 'security');
        
        // Get payment data from request
        $payment_data = isset($_POST['payment']) ? wp_unslash($_POST['payment']) : array();
        
        if (empty($payment_data)) {
            wp_send_json_error(array('message' => __('Keine Zahlungsdaten erhalten.', 'yprint-plugin')));
            return;
        }
        
        $payment_method = isset($payment_data['method']) ? sanitize_text_field($payment_data['method']) : '';
        
        if (empty($payment_method)) {
            wp_send_json_error(array('message' => __('Keine Zahlungsmethode ausgewählt.', 'yprint-plugin')));
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
        if ($payment_method === 'yprint_stripe') {
            $stripe_token = isset($payment_data['token']) ? sanitize_text_field($payment_data['token']) : '';
            
            if (!empty($stripe_token)) {
                WC()->session->set('stripe_payment_token', $stripe_token);
            }
            
            // Store option to save payment method
            $save_payment = isset($payment_data['save']) ? (bool)$payment_data['save'] : false;
            WC()->session->set('save_stripe_payment', $save_payment);
        }
        
        wp_send_json_success(array(
            'message' => __('Zahlungsmethode aktualisiert.', 'yprint-plugin'),
            'payment_method' => $payment_method
        ));
    }
    
    /**
     * AJAX handler for processing checkout
     */
    public function ajax_process_checkout() {
        check_ajax_referer('yprint-checkout-nonce', 'security');
        
        // Check terms acceptance
        $terms_accepted = isset($_POST['terms_accepted']) ? (bool)$_POST['terms_accepted'] : false;
        
        if (!$terms_accepted) {
            wp_send_json_error(array('message' => __('Bitte akzeptiere unsere AGB und Datenschutzerklärung.', 'yprint-plugin')));
            return;
        }
        
        // Check if we have shipping and payment methods set
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
        $chosen_payment_method = WC()->session->get('chosen_payment_method');
        
        if (WC()->cart->needs_shipping() && empty($chosen_shipping_methods)) {
            wp_send_json_error(array('message' => __('Bitte wähle eine Versandmethode.', 'yprint-plugin')));
            return;
        }
        
        if (empty($chosen_payment_method)) {
            wp_send_json_error(array('message' => __('Bitte wähle eine Zahlungsmethode.', 'yprint-plugin')));
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
            $order->update_status('pending', __('Bestellung über YPrint Checkout aufgegeben.', 'yprint-plugin'));
            
            // Get payment gateway and process payment
            $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
            
            if (!isset($available_gateways[$chosen_payment_method])) {
                throw new Exception(__('Zahlungsmethode nicht verfügbar.', 'yprint-plugin'));
            }
            
            $result = $available_gateways[$chosen_payment_method]->process_payment($order_id);
            
            if (!empty($result['result']) && $result['result'] === 'success') {
                // Payment successful
                wp_send_json_success(array(
                    'message' => __('Bestellung erfolgreich aufgegeben.', 'yprint-plugin'),
                    'redirect' => $result['redirect'],
                    'order_id' => $order_id
                ));
            } else {
                // Payment failed
                throw new Exception(__('Zahlungsverarbeitung fehlgeschlagen.', 'yprint-plugin'));
            }
            
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'error_code' => $e->getCode()
            ));
        }
    }
    
    /**
     * AJAX handler for fetching states based on country
     */
    public function ajax_get_states() {
        // Delegate to the standard WooCommerce AJAX handler for states
        if (function_exists('WC_AJAX::get_states')) {
            WC_AJAX::get_states();
        } else {
            wp_send_json_error(array(
                'message' => __('WooCommerce AJAX handler for states not available.', 'yprint-plugin'),
            ));
        }
    }
    
    /**
     * Capture additional order details during checkout
     *
     * @param int $order_id The order ID
     */
    public function capture_order_details($order_id) {
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
    
    /**
     * Update customer data based on checkout form input
     * 
     * @param array $data Posted checkout data
     */
    private function update_customer_data($data) {
        if (empty($data)) {
            return;
        }
        
        $customer = WC()->customer;
        
        // Update shipping address
        if (isset($data['shipping_country'])) {
            $customer->set_shipping_country($data['shipping_country']);
        }
        
        if (isset($data['shipping_state'])) {
            $customer->set_shipping_state($data['shipping_state']);
        }
        
        if (isset($data['shipping_postcode'])) {
            $customer->set_shipping_postcode($data['shipping_postcode']);
        }
        
        if (isset($data['shipping_city'])) {
            $customer->set_shipping_city($data['shipping_city']);
        }
        
        if (isset($data['shipping_address_1'])) {
            $customer->set_shipping_address_1($data['shipping_address_1']);
        }
        
        if (isset($data['shipping_address_2'])) {
            $customer->set_shipping_address_2($data['shipping_address_2']);
        }
        
        if (isset($data['shipping_first_name'])) {
            $customer->set_shipping_first_name($data['shipping_first_name']);
        }
        
        if (isset($data['shipping_last_name'])) {
            $customer->set_shipping_last_name($data['shipping_last_name']);
        }
        
        if (isset($data['shipping_company'])) {
            $customer->set_shipping_company($data['shipping_company']);
        }
        
        // Update billing address
        $ship_to_different_address = isset($data['ship_to_different_address']) && $data['ship_to_different_address'] == 1;
        
        if ($ship_to_different_address) {
            if (isset($data['billing_country'])) {
                $customer->set_billing_country($data['billing_country']);
            }
            
            if (isset($data['billing_state'])) {
                $customer->set_billing_state($data['billing_state']);
            }
            
            if (isset($data['billing_postcode'])) {
                $customer->set_billing_postcode($data['billing_postcode']);
            }
            
            if (isset($data['billing_city'])) {
                $customer->set_billing_city($data['billing_city']);
            }
            
            if (isset($data['billing_address_1'])) {
                $customer->set_billing_address_1($data['billing_address_1']);
            }
            
            if (isset($data['billing_address_2'])) {
                $customer->set_billing_address_2($data['billing_address_2']);
            }
            
            if (isset($data['billing_first_name'])) {
                $customer->set_billing_first_name($data['billing_first_name']);
            }
            
            if (isset($data['billing_last_name'])) {
                $customer->set_billing_last_name($data['billing_last_name']);
            }
            
            if (isset($data['billing_company'])) {
                $customer->set_billing_company($data['billing_company']);
            }
        } else {
            // Copy shipping address to billing address
            $customer->set_billing_country($customer->get_shipping_country());
            $customer->set_billing_state($customer->get_shipping_state());
            $customer->set_billing_postcode($customer->get_shipping_postcode());
            $customer->set_billing_city($customer->get_shipping_city());
            $customer->set_billing_address_1($customer->get_shipping_address_1());
            $customer->set_billing_address_2($customer->get_shipping_address());
            $customer->set_billing_address_2($customer->get_shipping_address_2());
            $customer->set_billing_first_name($customer->get_shipping_first_name());
            $customer->set_billing_last_name($customer->get_shipping_last_name());
            $customer->set_billing_company($customer->get_shipping_company());
        }
        
        // Update email and phone (always needed)
        if (isset($data['billing_email'])) {
            $customer->set_billing_email($data['billing_email']);
        }
        
        if (isset($data['billing_phone'])) {
            $customer->set_billing_phone($data['billing_phone']);
        }
        
        // Update shipping method
        if (isset($data['shipping_method'])) {
            $chosen_shipping_methods = array(sanitize_text_field($data['shipping_method']));
            WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
        }
        
        // Save the customer data
        $customer->save();
    }
    
    /**
     * Get available shipping methods in a structured array
     *
     * @return array Array of shipping methods
     */
    private function get_available_shipping_methods() {
        $shipping_methods = array();
        
        // Get calculated packages
        $packages = WC()->shipping->get_packages();
        
        if (!empty($packages)) {
            foreach ($packages as $package_key => $package) {
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $rate_id => $rate) {
                        if ($rate instanceof WC_Shipping_Rate) {
                            $shipping_methods[] = array(
                                'id' => $rate->id,
                                'label' => $rate->label,
                                'cost' => (float) $rate->cost,
                                'cost_formatted' => wc_price($rate->cost),
                                'method_id' => $rate->method_id,
                            );
                        }
                    }
                }
            }
        }
        
        return $shipping_methods;
    }
}

// Initialize the class
YPrint_Stripe_Checkout_Shortcode::get_instance();