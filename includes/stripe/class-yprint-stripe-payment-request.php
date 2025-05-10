<?php
/**
 * Stripe Payment Request Handler
 * 
 * Handles Payment Request API integration for Apple Pay and Google Pay
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Stripe Payment Request Class
 */
class YPrint_Stripe_Payment_Request {
    
    /**
     * Total label for payment request
     *
     * @var string
     */
    public $total_label;
    
    /**
     * Singleton instance
     *
     * @var YPrint_Stripe_Payment_Request
     */
    protected static $instance = null;
    
    /**
     * Get singleton instance
     *
     * @return YPrint_Stripe_Payment_Request
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
        $options = YPrint_Stripe_API::get_stripe_settings();
        $this->total_label = isset($options['statement_descriptor']) ? $options['statement_descriptor'] : get_bloginfo('name');
        
        // Default total label
        $this->total_label = apply_filters('yprint_stripe_payment_request_total_label', $this->total_label . ' (via YPrint)');
        
        // Register scripts
        add_action('wp_enqueue_scripts', array($this, 'scripts'));
        
        // Add payment request buttons to various locations
        add_action('woocommerce_after_add_to_cart_form', array($this, 'display_payment_request_button_html'), 1);
        add_action('woocommerce_proceed_to_checkout', array($this, 'display_payment_request_button_html'), 25);
        add_action('woocommerce_checkout_before_customer_details', array($this, 'display_payment_request_button_html'), 1);
        
        // Register AJAX handlers
        add_action('wc_ajax_yprint_stripe_get_cart_details', array($this, 'ajax_get_cart_details'));
        add_action('wc_ajax_yprint_stripe_get_shipping_options', array($this, 'ajax_get_shipping_options'));
        add_action('wc_ajax_yprint_stripe_update_shipping_method', array($this, 'ajax_update_shipping_method'));
        add_action('wc_ajax_yprint_stripe_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wc_ajax_yprint_stripe_process_payment', array($this, 'ajax_process_payment'));
        
        // Add payment data to orders
        add_action('woocommerce_checkout_order_processed', array($this, 'add_order_meta'), 10, 2);
    }
    
    /**
     * Register and enqueue scripts
     */
    public function scripts() {
        if (!$this->should_show_payment_request_button()) {
            return;
        }
        
        // Enqueue Stripe.js
        wp_register_script('stripe', 'https://js.stripe.com/v3/', '', '3.0', true);
        
        // Register our script
        wp_register_script(
            'yprint-stripe-payment-request', 
            YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-payment-request.js',
            array('jquery', 'stripe'),
            YPRINT_PLUGIN_VERSION,
            true
        );
        
        // Localize the script with the necessary data
        wp_localize_script(
            'yprint-stripe-payment-request',
            'yprint_stripe_payment_request_params',
            $this->get_javascript_params()
        );
        
        // Enqueue our script
        wp_enqueue_script('yprint-stripe-payment-request');
    }
    
    /**
     * Get JavaScript parameters
     *
     * @return array JavaScript parameters
     */
    public function get_javascript_params() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $testmode = isset($options['testmode']) && 'yes' === $options['testmode'];
        
        $params = array(
            'ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'stripe' => array(
                'key' => $testmode ? $options['test_publishable_key'] : $options['publishable_key'],
                'locale' => $this->get_stripe_locale(),
            ),
            'nonce' => array(
                'payment' => wp_create_nonce('yprint-stripe-payment-request'),
                'shipping' => wp_create_nonce('yprint-stripe-payment-request-shipping'),
                'update_shipping' => wp_create_nonce('yprint-stripe-update-shipping-method'),
                'get_cart_details' => wp_create_nonce('yprint-stripe-get-cart-details'),
                'add_to_cart' => wp_create_nonce('yprint-stripe-add-to-cart'),
            ),
            'button' => array(
                'type' => 'default',
                'theme' => 'dark',
                'height' => 48,
            ),
            'is_product' => $this->is_product(),
            'checkout' => array(
                'url' => wc_get_checkout_url(),
                'currency_code' => get_woocommerce_currency(),
                'country_code' => substr(get_option('woocommerce_default_country'), 0, 2),
                'needs_shipping' => WC()->cart->needs_shipping() ? 'yes' : 'no',
                'needs_payer_phone' => 'yes',
                'total_label' => $this->total_label,
            ),
        );
        
        // Add product data if on product page
        if ($this->is_product()) {
            $product = $this->get_product();
            if ($product) {
                $params['product'] = $this->get_product_data($product);
            }
        }
        
        return apply_filters('yprint_stripe_payment_request_params', $params);
    }
    
    /**
     * Get Stripe locale
     *
     * @return string Stripe locale
     */
    public function get_stripe_locale() {
        $locale = get_locale();
        $locale = substr($locale, 0, 2); // Get the first 2 characters
        
        // Default to 'auto' which lets Stripe decide
        return apply_filters('yprint_stripe_payment_request_locale', $locale);
    }
    
    /**
     * Checks if current page is a product page
     *
     * @return boolean
     */
    public function is_product() {
        return is_product() || wc_post_content_has_shortcode('product_page');
    }
    
    /**
     * Get product from product page
     *
     * @return WC_Product|false
     */
    public function get_product() {
        global $post;
        
        if (is_product()) {
            return wc_get_product($post->ID);
        } elseif (wc_post_content_has_shortcode('product_page')) {
            // Try to get id from product_page shortcode
            preg_match('/\[product_page id="(?<id>\d+)"\]/', $post->post_content, $shortcode_match);
            
            if (!isset($shortcode_match['id'])) {
                return false;
            }
            
            return wc_get_product($shortcode_match['id']);
        }
        
        return false;
    }
    
    /**
     * Get product data for payment request
     *
     * @param WC_Product $product
     * @return array
     */
    public function get_product_data($product) {
        $data = array();
        
        // Basic product data
        $data['id'] = $product->get_id();
        $data['name'] = $product->get_name();
        
        // Price and currency
        $product_price = $product->get_price();
        $currency = get_woocommerce_currency();
        
        // Line items
        $items = array();
        $items[] = array(
            'label' => $product->get_name(),
            'amount' => $this->get_stripe_amount($product_price, $currency),
        );
        
        // Tax
        if (wc_tax_enabled()) {
            $items[] = array(
                'label' => __('Tax', 'yprint-plugin'),
                'amount' => 0,
                'pending' => true,
            );
        }
        
        // Shipping
        if (wc_shipping_enabled() && $product->needs_shipping()) {
            $items[] = array(
                'label' => __('Shipping', 'yprint-plugin'),
                'amount' => 0,
                'pending' => true,
            );
            
            $data['shippingOptions'] = array(
                'id' => 'pending',
                'label' => __('Pending', 'yprint-plugin'),
                'detail' => '',
                'amount' => 0,
            );
        }
        
        $data['displayItems'] = $items;
        $data['total'] = array(
            'label' => $this->total_label,
            'amount' => $this->get_stripe_amount($product_price, $currency),
            'pending' => true,
        );
        
        $data['requestShipping'] = wc_shipping_enabled() && $product->needs_shipping();
        
        return $data;
    }
    
    /**
     * Get Stripe amount (in cents)
     *
     * @param float $amount
     * @param string $currency
     * @return int
     */
    public function get_stripe_amount($amount, $currency = null) {
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
     * Should show payment request button
     *
     * @return boolean
     */
    public function should_show_payment_request_button() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return false;
        }
        
        $options = YPrint_Stripe_API::get_stripe_settings();
        
        // Check if button is enabled
        if (!isset($options['payment_request']) || 'yes' !== $options['payment_request']) {
            return false;
        }
        
        // Check if keys are set
        $testmode = isset($options['testmode']) && 'yes' === $options['testmode'];
        $key = $testmode ? $options['test_publishable_key'] : $options['publishable_key'];
        
        if (empty($key)) {
            return false;
        }
        
        // Check SSL if not in test mode
        if (!$testmode && !is_ssl()) {
            return false;
        }
        
        // Don't show if on cart/checkout page with unsupported products
        if ((is_cart() || is_checkout()) && !$this->has_allowed_items_in_cart()) {
            return false;
        }
        
        // Don't show if product page but product not supported
        if ($this->is_product() && !$this->is_product_supported($this->get_product())) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Checks if cart has allowed items
     *
     * @return boolean
     */
    public function has_allowed_items_in_cart() {
        // If cart is empty, return true (nothing to check)
        if (is_null(WC()->cart) || WC()->cart->is_empty()) {
            return true;
        }
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            
            if (!$this->is_product_supported($_product)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Checks if product is supported
     *
     * @param WC_Product $product
     * @return boolean
     */
    public function is_product_supported($product) {
        if (!is_object($product) || !$product->is_purchasable()) {
            return false;
        }
        
        // Check product type
        $supported_types = array('simple', 'variable', 'variation');
        if (!in_array($product->get_type(), $supported_types, true)) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Display payment request button
     */
    public function display_payment_request_button_html() {
        if (!$this->should_show_payment_request_button()) {
            return;
        }
        
        ?>
        <div id="yprint-stripe-payment-request-wrapper" style="margin-top: 1em; clear: both; display: none;">
            <div id="yprint-stripe-payment-request-button">
                <!-- A Stripe Element will be inserted here. -->
            </div>
        </div>
        <?php
        
        $this->display_payment_request_button_separator_html();
    }
    
    /**
     * Display payment request button separator
     */
    public function display_payment_request_button_separator_html() {
        if (!$this->should_show_payment_request_button()) {
            return;
        }
        
        if (!is_checkout() && !is_cart()) {
            return;
        }
        
        ?>
        <p id="yprint-stripe-payment-request-separator" style="margin-top: 1.5em; text-align: center; display: none;">&mdash; <?php esc_html_e('OR', 'yprint-plugin'); ?> &mdash;</p>
        <?php
    }
    
    /**
     * Add order meta for payment type
     *
     * @param int $order_id
     * @param array $posted_data
     */
    public function add_order_meta($order_id, $posted_data) {
        if (empty($_POST['payment_request_type']) || !isset($_POST['payment_method']) || 'yprint_stripe' !== $_POST['payment_method']) {
            return;
        }
        
        $order = wc_get_order($order_id);
        
        $payment_request_type = wc_clean(wp_unslash($_POST['payment_request_type']));
        
        if ('apple_pay' === $payment_request_type) {
            $order->set_payment_method_title('Apple Pay (YPrint Stripe)');
            $order->save();
        } elseif ('google_pay' === $payment_request_type) {
            $order->set_payment_method_title('Google Pay (YPrint Stripe)');
            $order->save();
        } elseif ('payment_request' === $payment_request_type) {
            $order->set_payment_method_title('Payment Request (YPrint Stripe)');
            $order->save();
        }
    }
    
    /**
     * AJAX: Get cart details
     */
    public function ajax_get_cart_details() {
        check_ajax_referer('yprint-stripe-get-cart-details', 'security');
        
        if (!defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
        }
        
        WC()->cart->calculate_totals();
        
        $currency = get_woocommerce_currency();
        
        // Set mandatory payment details
        $data = array(
            'shipping_required' => WC()->cart->needs_shipping(),
            'order_data' => array(
                'currency' => strtolower($currency),
                'country_code' => substr(get_option('woocommerce_default_country'), 0, 2),
            ),
        );
        
        $data['order_data'] += $this->build_display_items();
        
        wp_send_json_success($data);
    }
    
    /**
     * Build display items for payment request
     *
     * @param bool $itemized_display_items
     * @return array
     */
    protected function build_display_items($itemized_display_items = false) {
        $items = array();
        $total = 0;
        
        // Line items
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $amount = $cart_item['line_subtotal'];
            $quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';
            $product_name = $cart_item['data']->get_name();
            
            $items[] = array(
                'label' => $product_name . $quantity_label,
                'amount' => $this->get_stripe_amount($amount),
            );
            
            $total += $amount;
        }
        
        // Fees
        foreach (WC()->cart->get_fees() as $fee) {
            $items[] = array(
                'label' => $fee->name,
                'amount' => $this->get_stripe_amount($fee->amount),
            );
            $total += $fee->amount;
        }
        
        // Tax
        if (wc_tax_enabled()) {
            $items[] = array(
                'label' => __('Tax', 'yprint-plugin'),
                'amount' => $this->get_stripe_amount(WC()->cart->get_taxes_total()),
            );
            $total += WC()->cart->get_taxes_total();
        }
        
        // Shipping
        if (WC()->cart->needs_shipping()) {
            $items[] = array(
                'label' => __('Shipping', 'yprint-plugin'),
                'amount' => $this->get_stripe_amount(WC()->cart->get_shipping_total()),
            );
            $total += WC()->cart->get_shipping_total();
        }
        
        // Discount
        if (WC()->cart->has_discount()) {
            $discount = WC()->cart->get_discount_total();
            $items[] = array(
                'label' => __('Discount', 'yprint-plugin'),
                'amount' => -$this->get_stripe_amount($discount),
            );
            $total -= $discount;
        }
        
        // Order total
        $order_total = WC()->cart->get_total('edit');
        
        return array(
            'displayItems' => $items,
            'total' => array(
                'label' => $this->total_label,
                'amount' => $this->get_stripe_amount($order_total),
            ),
        );
    }
    
    /**
     * AJAX: Get shipping options
     */
    public function ajax_get_shipping_options() {
        check_ajax_referer('yprint-stripe-payment-request-shipping', 'security');
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $is_product = isset($_POST['is_product']) ? (bool) $_POST['is_product'] : false;
        
        $shipping_address = filter_input_array(
            INPUT_POST,
            array(
                'country'   => FILTER_SANITIZE_SPECIAL_CHARS,
                'state'     => FILTER_SANITIZE_SPECIAL_CHARS,
                'postcode'  => FILTER_SANITIZE_SPECIAL_CHARS,
                'city'      => FILTER_SANITIZE_SPECIAL_CHARS,
                'address'   => FILTER_SANITIZE_SPECIAL_CHARS,
                'address_2' => FILTER_SANITIZE_SPECIAL_CHARS,
            )
        );
        
        // If it's a product page request, add product to cart first
        if ($is_product && $product_id) {
            // First empty the cart to prevent wrong calculation
            WC()->cart->empty_cart();
            
            // Add the product to the cart
            WC()->cart->add_to_cart($product_id);
        }
        
        $data = $this->get_shipping_options($shipping_address);
        
        wp_send_json_success($data);
    }
    
    /**
     * Get shipping options for address
     *
     * @param array $shipping_address
     * @return array
     */
    protected function get_shipping_options($shipping_address) {
        $data = array();
        
        // Set shipping address
        WC()->customer->set_billing_address($shipping_address['address']);
        WC()->customer->set_billing_address_2($shipping_address['address_2']);
        WC()->customer->set_billing_country($shipping_address['country']);
        WC()->customer->set_billing_state($shipping_address['state']);
        WC()->customer->set_billing_postcode($shipping_address['postcode']);
        WC()->customer->set_billing_city($shipping_address['city']);
        
        WC()->customer->set_shipping_address($shipping_address['address']);
        WC()->customer->set_shipping_address_2($shipping_address['address_2']);
        WC()->customer->set_shipping_country($shipping_address['country']);
        WC()->customer->set_shipping_state($shipping_address['state']);
        WC()->customer->set_shipping_postcode($shipping_address['postcode']);
        WC()->customer->set_shipping_city($shipping_address['city']);
        
        // Calculate shipping
        WC()->customer->set_calculated_shipping(true);
        WC()->cart->calculate_totals();
        
        // Get shipping packages
        $packages = WC()->shipping->get_packages();
        
        if (!empty($packages)) {
            foreach ($packages as $package_key => $package) {
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $key => $rate) {
                        $data['shipping_options'][] = array(
                            'id'     => $rate->id,
                            'label'  => $rate->label,
                            'detail' => '',
                            'amount' => $this->get_stripe_amount($rate->cost),
                        );
                    }
                }
            }
        }
        
        // Add display items
        $data += $this->build_display_items();
        
        return $data;
    }
    
    /**
     * AJAX: Update shipping method
     */
    public function ajax_update_shipping_method() {
        check_ajax_referer('yprint-stripe-update-shipping-method', 'security');
        
        if (!defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
        }
        
        $shipping_option_id = wc_clean(wp_unslash($_POST['shipping_option_id']));
        
        // Set the shipping option
        WC()->session->set('chosen_shipping_methods', array($shipping_option_id));
        
        // Calculate totals
        WC()->cart->calculate_totals();
        
        // Build data
        $data = array();
        $data += $this->build_display_items();
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Add to cart
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('yprint-stripe-add-to-cart', 'security');
        
        if (!defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
        }
        
        WC()->shipping->reset_shipping();
        
        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = !isset($_POST['quantity']) ? 1 : absint($_POST['quantity']);
        $product = wc_get_product($product_id);
        
        if (!$product) {
            wp_send_json_error(array('message' => __('Product not found', 'yprint-plugin')));
            return;
        }
        
        // First empty the cart to prevent wrong calculation
        WC()->cart->empty_cart();
        
        // Add to cart
        WC()->cart->add_to_cart($product->get_id(), $quantity);
        
        WC()->cart->calculate_totals();
        
        // Build data
        $data = array();
        $data += $this->build_display_items();
        
        wp_send_json_success($data);
    }
    
    /**
     * AJAX: Process payment
     */
    public function ajax_process_payment() {
        check_ajax_referer('yprint-stripe-payment-request', 'security');
        
        if (!defined('WOOCOMMERCE_CHECKOUT')) {
            define('WOOCOMMERCE_CHECKOUT', true);
        }
        
        // Get payment method ID from request
        $payment_method_id = isset($_POST['payment_method_id']) ? wc_clean(wp_unslash($_POST['payment_method_id'])) : '';
        
        if (empty($payment_method_id)) {
            wp_send_json_error(array('message' => __('Payment method ID is required', 'yprint-plugin')));
            return;
        }
        
        // Set payment method
        WC()->session->set('chosen_payment_method', 'yprint_stripe');
        
        // Store payment method ID and type
        $_POST['payment_method'] = 'yprint_stripe';
        $_POST['yprint_stripe_payment_method_id'] = $payment_method_id;
        
        if (isset($_POST['payment_request_type'])) {
            $_POST['payment_request_type'] = wc_clean(wp_unslash($_POST['payment_request_type']));
        }
        
        // Process checkout
        $checkout = WC()->checkout();
        $order_id = $checkout->create_order(array());
        $order = wc_get_order($order_id);
        
        if (is_wp_error($order_id)) {
            wp_send_json_error(array('message' => $order_id->get_error_message()));
            return;
        }
        
        // Mark order as paid
        $order->payment_complete();
        
        // Clear cart
        WC()->cart->empty_cart();
        
        // Return success
        wp_send_json_success(array(
            'redirect' => $order->get_checkout_order_received_url(),
        ));
    }
}

// Initialize the class
YPrint_Stripe_Payment_Request::get_instance();