<?php
/**
 * Stripe Payment Request API
 * Handles Apple Pay and Chrome Payment Request API buttons.
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Stripe Payment Request class.
 */
class YPrint_Stripe_Payment_Request {

    /**
     * Singleton instance
     *
     * @var YPrint_Stripe_Payment_Request
     */
    private static $_instance = null;

    /**
     * Stripe settings
     * 
     * @var array
     */
    public $stripe_settings;

    /**
     * Total label for the payment request
     * 
     * @var string
     */
    public $total_label;

    /**
     * Publishable key
     * 
     * @var string
     */
    public $publishable_key;

    /**
     * Is test mode active?
     * 
     * @var bool
     */
    public $testmode;

    /**
     * Get instance of this class
     * 
     * @return YPrint_Stripe_Payment_Request
     */
    public static function get_instance() {
        if (null === self::$_instance) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->stripe_settings = YPrint_Stripe_API::get_stripe_settings();
        $this->testmode = isset($this->stripe_settings['testmode']) && 'yes' === $this->stripe_settings['testmode'];
        $this->publishable_key = $this->testmode ? 
            (isset($this->stripe_settings['test_publishable_key']) ? $this->stripe_settings['test_publishable_key'] : '') : 
            (isset($this->stripe_settings['publishable_key']) ? $this->stripe_settings['publishable_key'] : '');
            
        // Set total label
        $store_name = get_bloginfo('name');
        $this->total_label = apply_filters('yprint_stripe_payment_request_total_label', $store_name . ' (via YPrint)');

        // Don't initialize if keys are not set or plugin is not enabled
        if (empty($this->publishable_key) || !$this->is_enabled()) {
            return;
        }

        $this->init();
    }

    /**
     * Initialize hooks
     */
    public function init() {
        // Enqueue scripts
        add_action('wp_enqueue_scripts', array($this, 'scripts'));

        // Display the payment button
        add_action('woocommerce_after_add_to_cart_form', array($this, 'display_payment_request_button_html'), 1);
        add_action('woocommerce_proceed_to_checkout', array($this, 'display_payment_request_button_html'), 25);
        add_action('woocommerce_checkout_before_customer_details', array($this, 'display_payment_request_button_html'), 1);

        // AJAX handlers
        add_action('wp_ajax_yprint_stripe_get_cart_details', array($this, 'ajax_get_cart_details'));
        add_action('wp_ajax_nopriv_yprint_stripe_get_cart_details', array($this, 'ajax_get_cart_details'));
        
        add_action('wp_ajax_yprint_stripe_get_shipping_options', array($this, 'ajax_get_shipping_options'));
        add_action('wp_ajax_nopriv_yprint_stripe_get_shipping_options', array($this, 'ajax_get_shipping_options'));
        
        add_action('wp_ajax_yprint_stripe_update_shipping_method', array($this, 'ajax_update_shipping_method'));
        add_action('wp_ajax_nopriv_yprint_stripe_update_shipping_method', array($this, 'ajax_update_shipping_method'));
        
        add_action('wp_ajax_yprint_stripe_add_to_cart', array($this, 'ajax_add_to_cart'));
        add_action('wp_ajax_nopriv_yprint_stripe_add_to_cart', array($this, 'ajax_add_to_cart'));
        
        add_action('wp_ajax_yprint_stripe_process_payment', array($this, 'ajax_process_payment'));
        add_action('wp_ajax_nopriv_yprint_stripe_process_payment', array($this, 'ajax_process_payment'));

        // Order processing
        add_action('woocommerce_checkout_order_processed', array($this, 'add_order_meta'), 10, 2);
    }

    /**
     * Checks if Payment Request is enabled
     *
     * @return bool
     */
    public function is_enabled() {
        return isset($this->stripe_settings['payment_request']) && 'yes' === $this->stripe_settings['payment_request'];
    }

    /**
     * Gets the button type.
     *
     * @return string
     */
    public function get_button_type() {
        return isset($this->stripe_settings['payment_request_button_type']) ? $this->stripe_settings['payment_request_button_type'] : 'default';
    }

    /**
     * Gets the button theme.
     *
     * @return string
     */
    public function get_button_theme() {
        return isset($this->stripe_settings['payment_request_button_theme']) ? $this->stripe_settings['payment_request_button_theme'] : 'dark';
    }

    /**
     * Gets the button height.
     *
     * @return string
     */
    public function get_button_height() {
        return isset($this->stripe_settings['payment_request_button_height']) ? str_replace('px', '', $this->stripe_settings['payment_request_button_height']) : '48';
    }

    /**
     * Gets supported locations for the button
     * 
     * @return array
     */
    public function get_button_locations() {
        if (!isset($this->stripe_settings['payment_request_button_locations'])) {
            return array('product', 'cart', 'checkout');
        }
        
        if (!is_array($this->stripe_settings['payment_request_button_locations'])) {
            return array();
        }
        
        return $this->stripe_settings['payment_request_button_locations'];
    }

    /**
     * Returns true if Payment Request Button should be shown on product page
     * 
     * @return bool
     */
    public function should_show_button_on_product_page() {
        return in_array('product', $this->get_button_locations(), true);
    }

    /**
     * Returns true if Payment Request Button should be shown on cart page
     * 
     * @return bool
     */
    public function should_show_button_on_cart_page() {
        return in_array('cart', $this->get_button_locations(), true);
    }

    /**
     * Returns true if Payment Request Button should be shown on checkout page
     * 
     * @return bool
     */
    public function should_show_button_on_checkout_page() {
        return in_array('checkout', $this->get_button_locations(), true);
    }

    /**
     * Checks if current page is a product page
     * 
     * @return bool
     */
    public function is_product_page() {
        return is_product() || wc_post_content_has_shortcode('product_page');
    }

    /**
     * Checks if current page is cart page
     * 
     * @return bool
     */
    public function is_cart_page() {
        return is_cart();
    }

    /**
     * Checks if current page is checkout page
     * 
     * @return bool
     */
    public function is_checkout_page() {
        return is_checkout();
    }

    /**
     * Check if the current page is supported
     * 
     * @return bool
     */
    public function is_page_supported() {
        return $this->is_product_page() || $this->is_cart_page() || $this->is_checkout_page();
    }

    /**
     * Should show payment request button
     * 
     * @return bool
     */
    public function should_show_payment_request_button() {
        // Check if page is supported
        if (!$this->is_page_supported()) {
            return false;
        }

        // Check if button should be shown on the current page
        if ($this->is_product_page() && !$this->should_show_button_on_product_page()) {
            return false;
        }

        if ($this->is_cart_page() && !$this->should_show_button_on_cart_page()) {
            return false;
        }

        if ($this->is_checkout_page() && !$this->should_show_button_on_checkout_page()) {
            return false;
        }

        // Check if required keys are set
        if (empty($this->publishable_key)) {
            return false;
        }

        // Don't show on non-SSL pages
        if (!is_ssl() && !$this->testmode) {
            return false;
        }

        return true;
    }

    /**
     * Load scripts
     */
    public function scripts() {
        // Don't load scripts if button shouldn't be shown
        if (!$this->should_show_payment_request_button()) {
            return;
        }

        // Register Stripe JS
        wp_register_script('stripe', 'https://js.stripe.com/v3/', '', '3.0', true);
        
        // Register and enqueue our script
        wp_register_script(
            'yprint-stripe-payment-request',
            YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-payment-request.js',
            array('jquery', 'stripe'),
            YPRINT_PLUGIN_VERSION,
            true
        );

        // Localize the script with data
        wp_localize_script(
            'yprint-stripe-payment-request',
            'yprint_stripe_payment_request_params',
            $this->get_localized_params()
        );

        wp_enqueue_script('yprint-stripe-payment-request');
    }

    /**
     * Get localized script parameters
     * 
     * @return array
     */
    public function get_localized_params() {
        return array(
            'ajax_url' => WC_AJAX::get_endpoint('%%endpoint%%'),
            'stripe' => array(
                'key' => $this->publishable_key,
                'locale' => $this->get_locale()
            ),
            'nonce' => array(
                'payment' => wp_create_nonce('yprint-stripe-payment'),
                'shipping' => wp_create_nonce('yprint-stripe-shipping'),
                'update_shipping' => wp_create_nonce('yprint-stripe-update-shipping'),
                'add_to_cart' => wp_create_nonce('yprint-stripe-add-to-cart'),
                'get_cart_details' => wp_create_nonce('yprint-stripe-get-cart-details')
            ),
            'checkout' => array(
                'url' => wc_get_checkout_url(),
                'currency_code' => strtolower(get_woocommerce_currency()),
                'country_code' => substr(get_option('woocommerce_default_country'), 0, 2),
                'needs_shipping' => WC()->cart->needs_shipping() ? 'yes' : 'no',
                'needs_payer_phone' => 'required' === get_option('woocommerce_checkout_phone_field', 'required'),
                'total_label' => $this->total_label
            ),
            'button' => array(
                'type' => $this->get_button_type(),
                'theme' => $this->get_button_theme(),
                'height' => $this->get_button_height()
            ),
            'product' => $this->is_product_page() ? $this->get_product_data() : null
        );
    }

    /**
     * Get the locale to use for Stripe
     * 
     * @return string
     */
    public function get_locale() {
        $locale = get_locale();
        $locale = substr($locale, 0, 2); // Get the first two characters (language code)
        
        return $locale;
    }

    /**
     * Get product data for the current product page
     * 
     * @return array|null
     */
    public function get_product_data() {
        // Only for product pages
        if (!$this->is_product_page()) {
            return null;
        }

        global $post;
        $product = wc_get_product($post->ID);
        
        if (!$product) {
            return null;
        }

        $data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'price' => $product->get_price(),
            'currency' => get_woocommerce_currency(),
            'requestShipping' => $product->needs_shipping(),
            'total' => array(
                'label' => $this->total_label,
                'amount' => $this->get_amount_for_stripe($product->get_price()),
            ),
            'displayItems' => array(
                array(
                    'label' => $product->get_name(),
                    'amount' => $this->get_amount_for_stripe($product->get_price()),
                )
            )
        );

        return $data;
    }

    /**
     * Convert amount to cents/smallest currency unit for Stripe
     * 
     * @param float $amount
     * @param string $currency
     * @return int
     */
    public function get_amount_for_stripe($amount, $currency = null) {
        if (null === $currency) {
            $currency = get_woocommerce_currency();
        }
        
        $currency = strtolower($currency);
        
        // These currencies don't use decimals
        $no_decimal_currencies = array(
            'bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 
            'pyg', 'rwf', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'
        );
        
        if (in_array($currency, $no_decimal_currencies, true)) {
            return absint($amount);
        }
        
        return absint($amount * 100);
    }

    /**
     * Display payment request button HTML
     */
    public function display_payment_request_button_html() {
        if (!$this->should_show_payment_request_button()) {
            return;
        }

        ?>
        <div id="yprint-stripe-payment-request-wrapper" style="margin-top: 1em; clear: both; display: none;">
            <div id="yprint-stripe-payment-request-button"></div>
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

        if ($this->is_checkout_page()) {
            ?>
            <p id="yprint-stripe-payment-request-separator" style="margin-top: 1.5em; text-align: center; display: none;">&mdash; <?php esc_html_e('OR', 'yprint-plugin'); ?> &mdash;</p>
            <?php
        }
    }

    /**
     * AJAX handler for getting cart details
     */
    public function ajax_get_cart_details() {
        check_ajax_referer('yprint-stripe-get-cart-details', 'security');

        if (!defined('WOOCOMMERCE_CART')) {
            define('WOOCOMMERCE_CART', true);
        }

        WC()->cart->calculate_totals();

        wp_send_json(array(
            'success' => true,
            'data' => $this->build_display_items()
        ));
    }

    /**
     * AJAX handler for getting shipping options
     */
    public function ajax_get_shipping_options() {
        check_ajax_referer('yprint-stripe-shipping', 'security');

        $shipping_address = isset($_POST['shipping_address']) ? wc_clean(wp_unslash($_POST['shipping_address'])) : array();

        // Process shipping options
        $data = $this->get_shipping_options($shipping_address);

        wp_send_json(array(
            'success' => true,
            'data' => $data
        ));
    }

    /**
     * AJAX handler for updating shipping method
     */
    public function ajax_update_shipping_method() {
        check_ajax_referer('yprint-stripe-update-shipping', 'security');

        $shipping_option_id = isset($_POST['shipping_option_id']) ? wc_clean(wp_unslash($_POST['shipping_option_id'])) : '';

        // Update the shipping method
        $this->update_shipping_method(array($shipping_option_id));

        WC()->cart->calculate_totals();

        // Return updated totals
        wp_send_json(array(
            'success' => true,
            'data' => $this->build_display_items()
        ));
    }

    /**
     * AJAX handler for adding a product to cart
     */
    public function ajax_add_to_cart() {
        check_ajax_referer('yprint-stripe-add-to-cart', 'security');

        $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
        $quantity = isset($_POST['quantity']) ? absint($_POST['quantity']) : 1;

        // Empty the cart first to ensure we only have this product
        WC()->cart->empty_cart();

        // Add the product to cart
        $result = WC()->cart->add_to_cart($product_id, $quantity);

        if ($result) {
            WC()->cart->calculate_totals();
            
            wp_send_json(array(
                'success' => true,
                'data' => $this->build_display_items()
            ));
        } else {
            wp_send_json(array(
                'success' => false,
                'data' => array(
                    'message' => __('Failed to add product to cart', 'yprint-plugin')
                )
            ));
        }
    }

    /**
     * AJAX handler for processing payment
     */
    public function ajax_process_payment() {
        check_ajax_referer('yprint-stripe-payment', 'security');

        $payment_method_id = isset($_POST['payment_method_id']) ? wc_clean(wp_unslash($_POST['payment_method_id'])) : '';
        $payment_request_type = isset($_POST['payment_request_type']) ? wc_clean(wp_unslash($_POST['payment_request_type'])) : 'payment_request';

        // Store payment type in session
        WC()->session->set('yprint_stripe_payment_request_type', $payment_request_type);

        // Create order
        $order_id = $this->create_order();

        if (!$order_id) {
            wp_send_json(array(
                'success' => false,
                'data' => array(
                    'message' => __('Failed to create order', 'yprint-plugin')
                )
            ));
            return;
        }

        $order = wc_get_order($order_id);

        // Process payment via the gateway
        $gateway = WC()->payment_gateways()->payment_gateways()['yprint_stripe'];
        
        if (!$gateway) {
            wp_send_json(array(
                'success' => false,
                'data' => array(
                    'message' => __('Payment gateway not found', 'yprint-plugin')
                )
            ));
            return;
        }

        // Add payment method ID to order
        $order->update_meta_data('_yprint_stripe_payment_method_id', $payment_method_id);
        $order->save();

        // Process the payment
        $result = $gateway->process_payment($order_id);

        if ('success' === $result['result']) {
            wp_send_json(array(
                'success' => true,
                'data' => array(
                    'redirect' => $result['redirect']
                )
            ));
        } else {
            wp_send_json(array(
                'success' => false,
                'data' => array(
                    'message' => __('Payment processing failed', 'yprint-plugin')
                )
            ));
        }
    }

    /**
     * Create an order from the current cart
     * 
     * @return int|bool Order ID or false on failure
     */
    protected function create_order() {
        if (WC()->cart->is_empty()) {
            return false;
        }

        // Create order
        $order_id = WC()->checkout()->create_order(array(
            'payment_method' => 'yprint_stripe',
        ));

        if (is_wp_error($order_id)) {
            return false;
        }

        return $order_id;
    }

    /**
     * Add meta to the order for payment request
     * 
     * @param int $order_id
     * @param array $posted_data
     */
    public function add_order_meta($order_id, $posted_data) {
        $payment_request_type = WC()->session->get('yprint_stripe_payment_request_type', '');
        
        if (empty($payment_request_type) || 'yprint_stripe' !== $posted_data['payment_method']) {
            return;
        }

        $order = wc_get_order($order_id);

        if ('apple_pay' === $payment_request_type) {
            $order->set_payment_method_title('Apple Pay (Stripe)');
            $order->save();
        } elseif ('google_pay' === $payment_request_type) {
            $order->set_payment_method_title('Google Pay (Stripe)');
            $order->save();
        } elseif ('payment_request' === $payment_request_type) {
            $order->set_payment_method_title('Payment Request (Stripe)');
            $order->save();
        }
    }

    /**
     * Get shipping options based on the shipping address
     * 
     * @param array $shipping_address
     * @return array
     */
    protected function get_shipping_options($shipping_address) {
        $data = array();
        
        // Format the shipping address for WooCommerce
        $shipping_address = $this->format_shipping_address($shipping_address);
        
        // Calculate shipping
        $this->calculate_shipping($shipping_address);
        
        // Get packages
        $packages = WC()->shipping->get_packages();
        
        if (!empty($packages) && WC()->customer->has_calculated_shipping()) {
            foreach ($packages as $package_key => $package) {
                if (empty($package['rates'])) {
                    continue;
                }
                
                $shipping_options = array();
                
                foreach ($package['rates'] as $key => $rate) {
                    $shipping_options[] = array(
                        'id' => $rate->id,
                        'label' => $rate->label,
                        'detail' => '',
                        'amount' => $this->get_amount_for_stripe($rate->cost)
                    );
                }
                
                $data['shipping_options'] = $shipping_options;
            }
        }
        
        // Update total and display items
        $data = array_merge($data, $this->build_display_items());
        
        return $data;
    }

    /**
     * Format shipping address for WooCommerce
     * 
     * @param array $address
     * @return array
     */
    protected function format_shipping_address($address) {
        $shipping_address = array();
        
        if (isset($address['country'])) {
            $shipping_address['country'] = $address['country'];
        }
        
        if (isset($address['postalCode'])) {
            $shipping_address['postcode'] = $address['postalCode'];
        }
        
        if (isset($address['region'])) {
            $shipping_address['state'] = $address['region'];
        }
        
        if (isset($address['city'])) {
            $shipping_address['city'] = $address['city'];
        }
        
        if (isset($address['addressLine'])) {
            $shipping_address['address'] = isset($address['addressLine'][0]) ? $address['addressLine'][0] : '';
            $shipping_address['address_2'] = isset($address['addressLine'][1]) ? $address['addressLine'][1] : '';
        }
        
        return $shipping_address;
    }

    /**
     * Calculate shipping based on the address
     * 
     * @param array $address
     */
    protected function calculate_shipping($address) {
        // Set customer shipping location
        WC()->customer->set_shipping_location(
            $address['country'],
            $address['state'],
            $address['postcode'],
            $address['city']
        );
        
        // Calculate shipping
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_fees();
        WC()->cart->calculate_totals();
    }

    /**
     * Update shipping method in WC session
     * 
     * @param array $shipping_methods
     */
    protected function update_shipping_method($shipping_methods) {
        $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods', array());
        
        foreach ($shipping_methods as $i => $value) {
            $chosen_shipping_methods[$i] = wc_clean($value);
        }
        
        WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
    }

    /**
     * Build display items for payment request
     * 
     * @return array
     */
    protected function build_display_items() {
        if (WC()->cart->is_empty()) {
            return array();
        }
        
        // Calculate totals
        WC()->cart->calculate_totals();
        
        // Get display items
        $items = array();
        $subtotal = 0;
        
        // Cart items
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $line_total = $cart_item['line_subtotal'];
            $quantity_label = 1 < $cart_item['quantity'] ? ' (x' . $cart_item['quantity'] . ')' : '';
            
            $items[] = array(
                'label' => $product->get_name() . $quantity_label,
                'amount' => $this->get_amount_for_stripe($line_total)
            );
            
            $subtotal += $line_total;
        }
        
        // Tax
        if (wc_tax_enabled()) {
            $tax_total = WC()->cart->get_taxes_total();
            if ($tax_total > 0) {
                $items[] = array(
                    'label' => __('Tax', 'yprint-plugin'),
                    'amount' => $this->get_amount_for_stripe($tax_total)
                );
            }
        }
        
        // Shipping
        if (WC()->cart->needs_shipping()) {
            $shipping_total = WC()->cart->get_shipping_total();
            if ($shipping_total > 0) {
                $items[] = array(
                    'label' => __('Shipping', 'yprint-plugin'),
                    'amount' => $this->get_amount_for_stripe($shipping_total)
                );
            }
        }
        
        // Fees
        if (!empty(WC()->cart->get_fees())) {
            foreach (WC()->cart->get_fees() as $fee) {
                $items[] = array(
                    'label' => $fee->name,
                    'amount' => $this->get_amount_for_stripe($fee->amount)
                );
            }
        }
        
        // Total
        $total = WC()->cart->get_total('edit');
        
        return array(
            'displayItems' => $items,
            'total' => array(
                'label' => $this->total_label,
                'amount' => $this->get_amount_for_stripe($total)
            )
        );
    }

    /**
     * Test the Payment Request functionality
     * 
     * @return array Test results
     */
    public static function test_payment_request() {
        $instance = self::get_instance();
        
        // Check if Payment Request is enabled
        if (!$instance->is_enabled()) {
            return array(
                'success' => false,
                'message' => __('Payment Request Button is not enabled. Please enable it in the settings.', 'yprint-plugin')
            );
        }
        
        // Check if publishable key is set
        if (empty($instance->publishable_key)) {
            return array(
                'success' => false,
                'message' => __('Stripe publishable key is not set. Please configure it in the settings.', 'yprint-plugin')
            );
        }
        
        // Check if correct API version is used
        if (version_compare(YPrint_Stripe_API::STRIPE_API_VERSION, '2018-05-21', '<')) {
            return array(
                'success' => false,
                'message' => __('Stripe API version is too old. Payment Request requires at least 2018-05-21.', 'yprint-plugin')
            );
        }
        
        return array(
            'success' => true,
            'message' => __('Payment Request Button is configured correctly.', 'yprint-plugin'),
            'details' => array(
                'enabled' => $instance->is_enabled(),
                'publishable_key' => !empty($instance->publishable_key),
                'locations' => $instance->get_button_locations(),
                'button_type' => $instance->get_button_type(),
                'button_theme' => $instance->get_button_theme(),
                'button_height' => $instance->get_button_height()
            )
        );
    }
}