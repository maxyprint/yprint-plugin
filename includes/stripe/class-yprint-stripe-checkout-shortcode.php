<?php
/**
 * YPrint Checkout Shortcode Implementation
 * 
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Checkout Shortcode Class
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
     * Initialize
     */
    public function init() {
        // Register shortcode
        add_shortcode('yprint_checkout', array($this, 'render_checkout'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // Add AJAX handlers
        add_action('wp_ajax_yprint_update_checkout', array($this, 'ajax_update_checkout'));
        add_action('wp_ajax_nopriv_yprint_update_checkout', array($this, 'ajax_update_checkout'));
        
        add_action('wp_ajax_yprint_process_checkout', array($this, 'ajax_process_checkout'));
        add_action('wp_ajax_nopriv_yprint_process_checkout', array($this, 'ajax_process_checkout'));
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'yprint_checkout')) {
            // Register and enqueue Stripe.js
            wp_register_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
            
            // Register and enqueue our script
            wp_register_script(
                'yprint-checkout',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js',
                array('jquery', 'stripe-js'),
                YPRINT_PLUGIN_VERSION,
                true
            );
            
            // Get Stripe settings
            $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
            $testmode = isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'];
            $publishable_key = $testmode ? $stripe_settings['test_publishable_key'] : $stripe_settings['publishable_key'];
            
            // Get payment request button settings
$stripe_settings = YPrint_Stripe_API::get_stripe_settings();
$payment_request_button_type = isset($stripe_settings['payment_request_button_type']) ? $stripe_settings['payment_request_button_type'] : 'default';
$payment_request_button_theme = isset($stripe_settings['payment_request_button_theme']) ? $stripe_settings['payment_request_button_theme'] : 'dark';
$payment_request_button_height = isset($stripe_settings['payment_request_button_height']) ? $stripe_settings['payment_request_button_height'] : '48';
$statement_descriptor = isset($stripe_settings['statement_descriptor']) ? $stripe_settings['statement_descriptor'] : get_bloginfo('name');

// Check if Apple Pay domain is verified
$apple_pay_domain_set = isset($stripe_settings['apple_pay_domain_set']) && 'yes' === $stripe_settings['apple_pay_domain_set'];

// Localize script
wp_localize_script(
    'yprint-checkout',
    'yprint_checkout_params',
    array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yprint-checkout'),
        'stripe' => array(
            'key' => $publishable_key,
            'locale' => get_locale(),
            'apple_pay_enabled' => $apple_pay_domain_set,
        ),
        'i18n' => array(
            'required' => __('This field is required.', 'yprint-plugin'),
            'invalid_email' => __('Please enter a valid email address.', 'yprint-plugin'),
            'processing' => __('Processing...', 'yprint-plugin'),
            'payment_processing' => __('Processing payment. Please wait...', 'yprint-plugin'),
            'checkout_error' => __('Error processing checkout. Please try again.', 'yprint-plugin'),
            'place_order' => __('Place Order', 'yprint-plugin'),
            'no_shipping_methods' => __('No shipping methods available', 'yprint-plugin'),
        ),
        'button' => array(
            'type' => $payment_request_button_type,
            'theme' => $payment_request_button_theme,
            'height' => $payment_request_button_height,
        ),
        'shipping_required' => WC()->cart->needs_shipping(),
        'currency' => get_woocommerce_currency(),
        'country_code' => substr(get_option('woocommerce_default_country'), 0, 2),
        'total_label' => apply_filters('yprint_stripe_payment_request_total_label', $statement_descriptor),
    )
);
            
            wp_enqueue_script('yprint-checkout');
            
            // Register and enqueue our styles
            wp_register_style(
                'yprint-checkout',
                YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css',
                array(),
                YPRINT_PLUGIN_VERSION
            );
            
            wp_enqueue_style('yprint-checkout');
        }
    }
    
    /**
     * Render checkout
     *
     * @return string
     */
    public function render_checkout() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return '<div class="yprint-checkout-error">' . __('WooCommerce is required for checkout.', 'yprint-plugin') . '</div>';
        }
        
        // Check if cart is empty
        if (WC()->cart->is_empty()) {
            return '<div class="yprint-checkout-error">' . __('Your cart is empty. Please add some products before checkout.', 'yprint-plugin') . '</div>';
        }
        
        // Start output buffering
        ob_start();
        
        // Get customer data
        $customer = WC()->customer;
        
        // Get countries and states
        $countries = WC()->countries->get_countries();
        $states = WC()->countries->get_states();
        
        // Get current user's saved addresses if logged in
        $user_id = get_current_user_id();
        $saved_addresses = array();
        
        if ($user_id > 0) {
            $saved_addresses = $this->get_user_addresses($user_id);
        }
        
        // Currency symbol
        $currency_symbol = get_woocommerce_currency_symbol();
        
        // Get cart totals
        WC()->cart->calculate_totals();
        $cart_total = WC()->cart->get_total();
        $cart_subtotal = WC()->cart->get_cart_subtotal();
        $cart_tax = WC()->cart->get_total_tax();
        $cart_shipping = WC()->cart->get_shipping_total();
        
        // Include template
        include(YPRINT_PLUGIN_DIR . 'templates/checkout.php');
        
        // Return the buffered output
        return ob_get_clean();
    }
    
    /**
     * Get user addresses
     *
     * @param int $user_id
     * @return array
     */
    private function get_user_addresses($user_id) {
        $addresses = array(
            'billing' => array(
                'first_name' => get_user_meta($user_id, 'billing_first_name', true),
                'last_name' => get_user_meta($user_id, 'billing_last_name', true),
                'company' => get_user_meta($user_id, 'billing_company', true),
                'address_1' => get_user_meta($user_id, 'billing_address_1', true),
                'address_2' => get_user_meta($user_id, 'billing_address_2', true),
                'city' => get_user_meta($user_id, 'billing_city', true),
                'state' => get_user_meta($user_id, 'billing_state', true),
                'postcode' => get_user_meta($user_id, 'billing_postcode', true),
                'country' => get_user_meta($user_id, 'billing_country', true),
                'email' => get_user_meta($user_id, 'billing_email', true),
                'phone' => get_user_meta($user_id, 'billing_phone', true),
            ),
            'shipping' => array(
                'first_name' => get_user_meta($user_id, 'shipping_first_name', true),
                'last_name' => get_user_meta($user_id, 'shipping_last_name', true),
                'company' => get_user_meta($user_id, 'shipping_company', true),
                'address_1' => get_user_meta($user_id, 'shipping_address_1', true),
                'address_2' => get_user_meta($user_id, 'shipping_address_2', true),
                'city' => get_user_meta($user_id, 'shipping_city', true),
                'state' => get_user_meta($user_id, 'shipping_state', true),
                'postcode' => get_user_meta($user_id, 'shipping_postcode', true),
                'country' => get_user_meta($user_id, 'shipping_country', true),
            ),
        );
        
        return $addresses;
    }
    
    /**
     * AJAX update checkout
     */
    public function ajax_update_checkout() {
        check_ajax_referer('yprint-checkout', 'nonce');
        
        try {
            // Get checkout data
            $posted_data = isset($_POST['checkout_data']) ? $_POST['checkout_data'] : array();
            
            // Parse data
            parse_str($posted_data, $checkout_data);
            
            // Update customer data
            if (isset($checkout_data['billing_country'])) {
                WC()->customer->set_billing_country($checkout_data['billing_country']);
            }
            
            if (isset($checkout_data['billing_state'])) {
                WC()->customer->set_billing_state($checkout_data['billing_state']);
            }
            
            if (isset($checkout_data['billing_postcode'])) {
                WC()->customer->set_billing_postcode($checkout_data['billing_postcode']);
            }
            
            if (isset($checkout_data['billing_city'])) {
                WC()->customer->set_billing_city($checkout_data['billing_city']);
            }
            
            if (isset($checkout_data['shipping_country'])) {
                WC()->customer->set_shipping_country($checkout_data['shipping_country']);
            }
            
            if (isset($checkout_data['shipping_state'])) {
                WC()->customer->set_shipping_state($checkout_data['shipping_state']);
            }
            
            if (isset($checkout_data['shipping_postcode'])) {
                WC()->customer->set_shipping_postcode($checkout_data['shipping_postcode']);
            }
            
            if (isset($checkout_data['shipping_city'])) {
                WC()->customer->set_shipping_city($checkout_data['shipping_city']);
            }
            
            // Update shipping method if set
            if (isset($checkout_data['shipping_method']) && !empty($checkout_data['shipping_method'])) {
                WC()->session->set('chosen_shipping_methods', array($checkout_data['shipping_method']));
            }
            
            // Calculate totals
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
            
            // Get updated totals
            $totals = array(
                'subtotal' => WC()->cart->get_cart_subtotal(),
                'shipping' => wc_price(WC()->cart->get_shipping_total()),
                'tax' => wc_price(WC()->cart->get_total_tax()),
                'total' => WC()->cart->get_total(),
                'total_formatted' => wc_price(WC()->cart->get_total()),
            );
            
            // Get available shipping methods
            $shipping_methods = $this->get_available_shipping_methods();
            
            $response = array(
                'success' => true,
                'data' => array(
                    'totals' => $totals,
                    'shipping_methods' => $shipping_methods,
                ),
            );
            
            wp_send_json_success($response);
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
    }
    
    /**
     * Get available shipping methods
     *
     * @return array
     */
    private function get_available_shipping_methods() {
        $shipping_methods = array();
        
        $packages = WC()->shipping->get_packages();
        
        if (!empty($packages)) {
            foreach ($packages as $package_key => $package) {
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $key => $rate) {
                        $shipping_methods[] = array(
                            'id' => $rate->id,
                            'label' => $rate->label,
                            'cost' => $rate->cost,
                            'cost_formatted' => wc_price($rate->cost),
                            'taxes' => $rate->taxes,
                            'method_id' => $rate->method_id,
                        );
                    }
                }
            }
        }
        
        return $shipping_methods;
    }
    
    /**
     * AJAX process checkout
     */
    public function ajax_process_checkout() {
        check_ajax_referer('yprint-checkout', 'nonce');
        
        try {
            // Get checkout data
            $posted_data = isset($_POST['checkout_data']) ? $_POST['checkout_data'] : '';
            
            // Parse data
            parse_str($posted_data, $checkout_data);
            
            // Validate required fields
            $this->validate_checkout_fields($checkout_data);
            
            // Create order
            $order_id = $this->create_order($checkout_data);
            
            if (!$order_id) {
                throw new Exception(__('Error creating order. Please try again.', 'yprint-plugin'));
            }
            
            $order = wc_get_order($order_id);
            
            // Process payment
            $payment_method = isset($checkout_data['payment_method']) ? $checkout_data['payment_method'] : 'yprint_stripe';
            $payment_method_id = isset($checkout_data['payment_method_id']) ? $checkout_data['payment_method_id'] : '';
            
            // Set payment method
            $order->set_payment_method($payment_method);
            $order->save();
            
            // Process payment
            if ($payment_method === 'yprint_stripe') {
                // Get the payment gateway
                $gateways = WC()->payment_gateways()->payment_gateways();
                $gateway = isset($gateways[$payment_method]) ? $gateways[$payment_method] : null;
                
                if (!$gateway) {
                    throw new Exception(__('Payment gateway not found.', 'yprint-plugin'));
                }
                
                // Process payment
                $result = $gateway->process_payment($order_id);
                
                if (isset($result['result']) && $result['result'] === 'success') {
                    // Empty cart
                    WC()->cart->empty_cart();
                    
                    wp_send_json_success(array(
                        'result' => 'success',
                        'redirect' => $result['redirect'],
                    ));
                } else {
                    throw new Exception(__('Payment processing failed. Please try again.', 'yprint-plugin'));
                }
            } else {
                throw new Exception(__('Invalid payment method.', 'yprint-plugin'));
            }
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
    }
    
    /**
     * Validate checkout fields
     *
     * @param array $data
     * @throws Exception
     */
    private function validate_checkout_fields($data) {
        // Required fields
        $required_fields = array(
            'billing_first_name' => __('First name', 'yprint-plugin'),
            'billing_last_name' => __('Last name', 'yprint-plugin'),
            'billing_address_1' => __('Address', 'yprint-plugin'),
            'billing_city' => __('City', 'yprint-plugin'),
            'billing_postcode' => __('Postcode / ZIP', 'yprint-plugin'),
            'billing_country' => __('Country', 'yprint-plugin'),
            'billing_email' => __('Email address', 'yprint-plugin'),
            'billing_phone' => __('Phone', 'yprint-plugin'),
        );
        
        if (WC()->cart->needs_shipping()) {
            $required_fields = array_merge($required_fields, array(
                'shipping_first_name' => __('Shipping first name', 'yprint-plugin'),
                'shipping_last_name' => __('Shipping last name', 'yprint-plugin'),
                'shipping_address_1' => __('Shipping address', 'yprint-plugin'),
                'shipping_city' => __('Shipping city', 'yprint-plugin'),
                'shipping_postcode' => __('Shipping postcode / ZIP', 'yprint-plugin'),
                'shipping_country' => __('Shipping country', 'yprint-plugin'),
            ));
        }
        
        // Check if shipping is same as billing
        if (isset($data['ship_to_different_address']) && $data['ship_to_different_address'] == 0) {
            // Remove shipping fields from required
            foreach ($required_fields as $key => $value) {
                if (strpos($key, 'shipping_') === 0) {
                    unset($required_fields[$key]);
                }
            }
        }
        
        // Validate required fields
        foreach ($required_fields as $key => $label) {
            if (!isset($data[$key]) || empty($data[$key])) {
                throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $label));
            }
        }
        
        // Validate email
        if (isset($data['billing_email']) && !is_email($data['billing_email'])) {
            throw new Exception(__('Please enter a valid email address.', 'yprint-plugin'));
        }
    }
    
    /**
     * Create order
     *
     * @param array $data
     * @return int
     * @throws Exception
     */
    private function create_order($data) {
        // Start with a clean cart
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();
        
        // Check if we need to create a new customer
        $customer_id = apply_filters('woocommerce_checkout_customer_id', get_current_user_id());
        
        // Set up order data
        $order_data = array(
            'status' => apply_filters('woocommerce_default_order_status', 'pending'),
            'customer_id' => $customer_id,
            'customer_note' => isset($data['order_comments']) ? $data['order_comments'] : '',
            'cart_hash' => WC()->cart->get_cart_hash(),
        );
        
        // Create the order
        $order = wc_create_order($order_data);
        
        if (is_wp_error($order)) {
            throw new Exception($order->get_error_message());
        }
        
        // Set billing address
        $billing_address = array(
            'first_name' => isset($data['billing_first_name']) ? $data['billing_first_name'] : '',
            'last_name' => isset($data['billing_last_name']) ? $data['billing_last_name'] : '',
            'company' => isset($data['billing_company']) ? $data['billing_company'] : '',
            'address_1' => isset($data['billing_address_1']) ? $data['billing_address_1'] : '',
            'address_2' => isset($data['billing_address_2']) ? $data['billing_address_2'] : '',
            'city' => isset($data['billing_city']) ? $data['billing_city'] : '',
            'state' => isset($data['billing_state']) ? $data['billing_state'] : '',
            'postcode' => isset($data['billing_postcode']) ? $data['billing_postcode'] : '',
            'country' => isset($data['billing_country']) ? $data['billing_country'] : '',
            'email' => isset($data['billing_email']) ? $data['billing_email'] : '',
            'phone' => isset($data['billing_phone']) ? $data['billing_phone'] : '',
        );
        
        $order->set_address($billing_address, 'billing');
        
        // Set shipping address
        if (isset($data['ship_to_different_address']) && $data['ship_to_different_address'] == 1) {
            $shipping_address = array(
                'first_name' => isset($data['shipping_first_name']) ? $data['shipping_first_name'] : '',
                'last_name' => isset($data['shipping_last_name']) ? $data['shipping_last_name'] : '',
                'company' => isset($data['shipping_company']) ? $data['shipping_company'] : '',
                'address_1' => isset($data['shipping_address_1']) ? $data['shipping_address_1'] : '',
                'address_2' => isset($data['shipping_address_2']) ? $data['shipping_address_2'] : '',
                'city' => isset($data['shipping_city']) ? $data['shipping_city'] : '',
                'state' => isset($data['shipping_state']) ? $data['shipping_state'] : '',
                'postcode' => isset($data['shipping_postcode']) ? $data['shipping_postcode'] : '',
                'country' => isset($data['shipping_country']) ? $data['shipping_country'] : '',
            );
        } else {
            $shipping_address = $billing_address;
        }
        
        $order->set_address($shipping_address, 'shipping');
        
        // Add products
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            // Get product
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $variation_id = $cart_item['variation_id'];
            $variation = $cart_item['variation'];
            
            // Add item to order
            $item_id = $order->add_product(
                $product,
                $quantity,
                array(
                    'variation' => $variation,
                    'totals' => array(
                        'subtotal' => $cart_item['line_subtotal'],
                        'subtotal_tax' => $cart_item['line_subtotal_tax'],
                        'total' => $cart_item['line_total'],
                        'tax' => $cart_item['line_tax'],
                        'tax_data' => $cart_item['line_tax_data'],
                    ),
                )
            );
            
            if (is_wp_error($item_id)) {
                throw new Exception($item_id->get_error_message());
            }
        }
        
        // Add shipping
        if (WC()->cart->needs_shipping()) {
            // Get chosen shipping method
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
            
            if (!empty($chosen_shipping_methods)) {
                $shipping_rate = $this->get_shipping_rate($chosen_shipping_methods[0]);
                
                if ($shipping_rate) {
                    $item = new WC_Order_Item_Shipping();
                    $item->set_props(array(
                        'method_title' => $shipping_rate->label,
                        'method_id' => $shipping_rate->method_id,
                        'total' => wc_format_decimal($shipping_rate->cost),
                        'taxes' => $shipping_rate->taxes,
                        'order_id' => $order->get_id(),
                    ));
                    
                    $order->add_item($item);
                }
            }
        }
        
        // Add fees
        foreach (WC()->cart->get_fees() as $fee_key => $fee) {
            $item = new WC_Order_Item_Fee();
            $item->set_props(array(
                'name' => $fee->name,
                'tax_class' => $fee->tax_class,
                'amount' => $fee->amount,
                'total' => $fee->total,
                'total_tax' => $fee->tax,
                'taxes' => array(
                    'total' => $fee->tax_data,
                ),
                'order_id' => $order->get_id(),
            ));
            
            $order->add_item($item);
        }
        
        // Add taxes
        foreach (WC()->cart->get_tax_totals() as $code => $tax) {
            $item = new WC_Order_Item_Tax();
            $item->set_props(array(
                'rate_code' => $code,
                'rate_id' => $tax->tax_rate_id,
                'label' => $tax->label,
                'compound' => $tax->is_compound,
                'tax_total' => $tax->amount,
                'order_id' => $order->get_id(),
            ));
            
            $order->add_item($item);
        }
        
        // Save order
        $order->save();
        
        // Set order totals
        $order->calculate_totals();
        
        return $order->get_id();
    }
    
    /**
     * Get shipping rate
     *
     * @param string $shipping_method_id
     * @return object|null
     */
    private function get_shipping_rate($shipping_method_id) {
        $packages = WC()->shipping->get_packages();
        
        if (!empty($packages)) {
            foreach ($packages as $package_key => $package) {
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $key => $rate) {
                        if ($rate->id === $shipping_method_id) {
                            return $rate;
                        }
                    }
                }
            }
        }
        
        return null;
    }
}

// Initialize the class
YPrint_Stripe_Checkout_Shortcode::get_instance();