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

        // Add AJAX handlers for logged-in and logged-out users
        add_action('wp_ajax_yprint_update_checkout', array($this, 'ajax_update_checkout'));
        add_action('wp_ajax_nopriv_yprint_update_checkout', array($this, 'ajax_update_checkout'));

        add_action('wp_ajax_yprint_process_checkout', array($this, 'ajax_process_checkout'));
        add_action('wp_ajax_nopriv_yprint_process_checkout', array($this, 'ajax_process_checkout'));

        // Add AJAX handler for fetching states based on country
        add_action('wp_ajax_yprint_get_states', array($this, 'ajax_get_states'));
        add_action('wp_ajax_nopriv_yprint_get_states', array($this, 'ajax_get_states'));
    }

    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only enqueue on pages with our shortcode
        global $post;
        // Check if it's a singular post/page and has the shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'yprint_checkout')) {

            // Check if WooCommerce is active, as our script depends on its localization (for states)
            if (!class_exists('WooCommerce')) {
                 error_log('YPrint Checkout: WooCommerce is not active. Cannot enqueue scripts.');
                 // Display a message to the user or handle appropriately
                 return;
            }

            // Register and enqueue Stripe.js (required for Stripe payment methods)
            // Ensure this is only loaded if Stripe is enabled and used
            $stripe_settings = YPrint_Stripe_API::get_stripe_settings(); // Assuming YPrint_Stripe_API exists
            $stripe_enabled = isset($stripe_settings['enabled']) && 'yes' === $stripe_settings['enabled'];

            if ($stripe_enabled) {
                wp_register_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
                wp_enqueue_script('stripe-js'); // Enqueue Stripe.js
            }


            // Register and enqueue our main checkout script
            wp_register_script(
                'yprint-checkout',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js', // Assuming this path
                array('jquery', 'stripe-js', 'wc-country-select'), // Dependencies: jQuery, Stripe.js, WC Country Select
                YPRINT_PLUGIN_VERSION, // Use plugin version for cache busting
                true // Load in footer
            );

            // Get Stripe settings for localization
            $testmode = isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'];
            $publishable_key = $testmode ? $stripe_settings['test_publishable_key'] : $stripe_settings['publishable_key'];

            // Get payment request button settings
            $payment_request_button_type = isset($stripe_settings['payment_request_button_type']) ? $stripe_settings['payment_request_button_type'] : 'default';
            $payment_request_button_theme = isset($stripe_settings['payment_request_button_theme']) ? $stripe_settings['payment_request_button_theme'] : 'dark';
            $payment_request_button_height = isset($stripe_settings['payment_request_button_height']) ? $stripe_settings['payment_request_button_height'] : '48';
            $statement_descriptor = isset($stripe_settings['statement_descriptor']) ? $stripe_settings['statement_descriptor'] : get_bloginfo('name');

            // Check if Apple Pay domain is verified (required for Apple Pay)
            $apple_pay_domain_set = isset($stripe_settings['apple_pay_domain_set']) && 'yes' === $stripe_settings['apple_pay_domain_set'];

            // Localize script - Pass data from PHP to our JavaScript file
            wp_localize_script(
                'yprint-checkout',
                'yprint_checkout_params',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'), // WordPress AJAX URL
                    'nonce' => wp_create_nonce('yprint-checkout'), // Security nonce
                    'stripe' => array(
                        'key' => $publishable_key, // Stripe Publishable Key
                        'locale' => str_replace('_', '-', get_locale()), // Stripe uses hyphenated locale (e.g., 'en-US')
                        'apple_pay_enabled' => $apple_pay_domain_set && $stripe_enabled, // Only enable Apple Pay if domain is set and Stripe is enabled
                    ),
                    'i18n' => array( // Internationalization strings
                        'required' => __('This field is required.', 'yprint-plugin'),
                        'invalid_email' => __('Please enter a valid email address.', 'yprint-plugin'),
                        'processing' => __('Processing...', 'yprint-plugin'),
                        'payment_processing' => __('Processing payment. Please wait...', 'yprint-plugin'),
                        'checkout_error' => __('Error processing checkout. Please try again.', 'yprint-plugin'),
                        'place_order' => __('Place Order', 'yprint-plugin'),
                        'no_shipping_methods' => __('No shipping methods available', 'yprint-plugin'),
                        'select_state_text' => __('Select a state / county...', 'woocommerce'), // Use WC's text for states
                        'error_message' => __('An error occurred. Please try again.', 'yprint-plugin'), // Generic error message
                    ),
                    'button' => array( // Payment Request Button settings
                        'type' => $payment_request_button_type,
                        'theme' => $payment_request_button_theme,
                        'height' => $payment_request_button_height,
                    ),
                    'shipping_required' => WC()->cart->needs_shipping(), // Boolean: does the cart require shipping?
                    'currency' => get_woocommerce_currency(), // Current currency code (e.g., 'USD')
                    'country_code' => substr(get_option('woocommerce_default_country'), 0, 2), // Get base country code for initial state loading
                    'total_label' => apply_filters('yprint_stripe_payment_request_total_label', $statement_descriptor), // Label for Payment Request Button
                     'is_user_logged_in' => is_user_logged_in(), // Pass login status
                     'saved_shipping_addresses' => $this->get_user_saved_addresses('shipping'), // Pass saved shipping addresses
                     'saved_billing_addresses' => $this->get_user_saved_addresses('billing'), // Pass saved billing addresses
                     'default_shipping_address_id' => get_user_meta( get_current_user_id(), 'default_shipping_address', true ), // Default shipping ID
                     'default_billing_address_id' => get_user_meta( get_current_user_id(), 'default_billing_address', true ), // Default billing ID
                )
            );

            wp_enqueue_script('yprint-checkout');

            // Register and enqueue our styles
            wp_register_style(
                'yprint-checkout',
                YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css', // Assuming this path
                array(),
                YPRINT_PLUGIN_VERSION
            );

            wp_enqueue_style('yprint-checkout');

            // Enqueue WooCommerce's country-select script if not already enqueued
            // This script provides wc_country_select_params and handles state updates
            if (!wp_script_is('wc-country-select', 'enqueued')) {
                 wp_enqueue_script('wc-country-select');
            }
        }
    }

    /**
     * Render checkout shortcode output
     *
     * @return string HTML output for the checkout page.
     */
    public function render_checkout() {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return '<div class="yprint-checkout-error">' . esc_html__('WooCommerce is required for checkout.', 'yprint-plugin') . '</div>';
        }

        // Check if cart is empty
        if (WC()->cart->is_empty()) {
            return '<div class="yprint-checkout-error">' . esc_html__('Your cart is empty. Please add some products before checkout.', 'yprint-plugin') . '</div>';
        }

        // Start output buffering to capture HTML
        ob_start();

        // Get customer data (WooCommerce handles guest vs logged-in)
        $customer = WC()->customer;

        // Get countries (states are handled by wc-country-select script via AJAX)
        $countries = WC()->countries->get_countries();
        $states = WC()->countries->get_states(); // Get all states initially (wc-country-select will filter)

        // Get current user's saved addresses if logged in
        // We pass these to JS via wp_localize_script, but also need them here for initial rendering
        $user_id = get_current_user_id();
        $saved_addresses = array();
        $default_shipping_address_id = '';
        $default_billing_address_id = '';

        if ($user_id > 0) {
            $saved_addresses['shipping'] = $this->get_user_saved_addresses('shipping');
            $saved_addresses['billing'] = $this->get_user_saved_addresses('billing');
            $default_shipping_address_id = get_user_meta( $user_id, 'default_shipping_address', true );
            $default_billing_address_id = get_user_meta( $user_id, 'default_billing_address', true );
        }

        // Currency symbol
        $currency_symbol = get_woocommerce_currency_symbol();

        // Calculate cart totals (ensure they are up-to-date)
        WC()->cart->calculate_totals();
        // Get totals - use WC functions to retrieve calculated values
        $cart_total = WC()->cart->get_total(); // Formatted total
        $cart_subtotal = WC()->cart->get_cart_subtotal(); // Formatted subtotal
        $cart_tax_total = wc_price(WC()->cart->get_total_tax()); // Formatted total tax
        $cart_shipping_total = wc_price(WC()->cart->get_shipping_total()); // Formatted shipping total


        // Include the checkout template file
        // Look for the template in the theme first, then in the plugin
        $template_name = 'checkout.php';
        $template_path = locate_template('yprint-checkout/' . $template_name); // Check theme/yprint-checkout/
        if (!$template_path) {
            $template_path = YPRINT_PLUGIN_DIR . 'templates/' . $template_name; // Check plugin/templates/
        }

        if (file_exists($template_path)) {
             include($template_path);
        } else {
             error_log('YPrint Checkout: Checkout template not found at ' . $template_path);
             echo '<div class="yprint-checkout-error">' . esc_html__('Checkout template not found.', 'yprint-plugin') . '</div>';
        }


        // Return the buffered HTML output
        return ob_get_clean();
    }

    /**
     * Get user's saved addresses from user meta.
     *
     * @param string $type 'shipping' or 'billing'.
     * @return array Array of saved addresses.
     */
    private function get_user_saved_addresses($type) {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return array(); // No saved addresses for guests
        }

        $meta_key = ($type === 'shipping') ? 'additional_shipping_addresses' : 'additional_billing_addresses';
        $saved_addresses = get_user_meta($user_id, $meta_key, true);

        if (empty($saved_addresses) || !is_array($saved_addresses)) {
            return array();
        }

        // Ensure required keys exist for each address to prevent errors in template
        foreach ($saved_addresses as $address_id => &$address) {
             $address = wp_parse_args( $address, array(
                 'title' => '',
                 'first_name' => '',
                 'last_name' => '',
                 'company' => '',
                 'address_1' => '',
                 'address_2' => '',
                 'city' => '',
                 'state' => '',
                 'postcode' => '',
                 'country' => '',
                 'email' => '', // Only relevant for billing
                 'phone' => '', // Only relevant for billing
             ) );
        }

        return $saved_addresses;
    }


    /**
     * AJAX handler to update checkout details (shipping methods, totals)
     */
    public function ajax_update_checkout() {
        error_log('YPrint Checkout AJAX: ajax_update_checkout called');

        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'yprint-checkout')) {
            error_log('YPrint Checkout AJAX: Nonce verification failed.');
            wp_send_json_error(array(
                'message' => __('Invalid nonce.', 'yprint-plugin'),
            ), 403);
            wp_die();
        }
         error_log('YPrint Checkout AJAX: Nonce verification successful.');

        try {
            // Ensure WooCommerce is loaded in AJAX context
            if (!class_exists('WooCommerce')) {
                 error_log('YPrint Checkout AJAX: WooCommerce not loaded in AJAX context.');
                 throw new Exception(__('WooCommerce is not available.', 'yprint-plugin'));
            }
             error_log('YPrint Checkout AJAX: WooCommerce is available.');


            // Get checkout data string from POST request
            $posted_data = isset($_POST['checkout_data']) ? $_POST['checkout_data'] : '';
            error_log('YPrint Checkout AJAX: Received checkout_data string: ' . $posted_data);

            // Parse the query string into an array
            $checkout_data = array();
            if (!empty($posted_data)) {
                 parse_str($posted_data, $checkout_data);
            }
            error_log('YPrint Checkout AJAX: Parsed checkout_data array: ' . print_r($checkout_data, true));

            // --- Update Customer Location and Addresses ---
            // This is crucial for calculating shipping methods and taxes correctly.
            // We need to determine which address (saved or new) is being used for shipping and billing.

            $user_id = get_current_user_id();
            $shipping_address_data = array();
            $billing_address_data = array();

            // Determine Shipping Address Data
            $shipping_selection = isset($checkout_data['yprint_shipping_address_selection']) ? sanitize_text_field($checkout_data['yprint_shipping_address_selection']) : 'new_address';

            if ($user_id > 0 && $shipping_selection !== 'new_address') {
                 // User selected a saved shipping address
                 $saved_shipping_addresses = $this->get_user_saved_addresses('shipping');
                 if (isset($saved_shipping_addresses[$shipping_selection])) {
                      $shipping_address_data = $saved_shipping_addresses[$shipping_selection];
                      error_log('YPrint Checkout AJAX: Using saved shipping address: ' . $shipping_selection);
                 } else {
                     // Fallback if saved address ID is invalid
                     $shipping_selection = 'new_address';
                     error_log('YPrint Checkout AJAX: Saved shipping address ID invalid, falling back to new address.');
                 }
            }

            if ($shipping_selection === 'new_address') {
                 // User entered a new shipping address
                 $shipping_address_data = array(
                     'first_name' => isset($checkout_data['shipping_first_name']) ? sanitize_text_field($checkout_data['shipping_first_name']) : '',
                     'last_name' => isset($checkout_data['shipping_last_name']) ? sanitize_text_field($checkout_data['shipping_last_name']) : '',
                     'company' => isset($checkout_data['shipping_company']) ? sanitize_text_field($checkout_data['shipping_company']) : '',
                     'address_1' => isset($checkout_data['shipping_address_1']) ? sanitize_text_field($checkout_data['shipping_address_1']) : '',
                     'address_2' => isset($checkout_data['shipping_address_2']) ? sanitize_text_field($checkout_data['shipping_address_2']) : '',
                     'city' => isset($checkout_data['shipping_city']) ? sanitize_text_field($checkout_data['shipping_city']) : '',
                     'state' => isset($checkout_data['shipping_state']) ? sanitize_text_field($checkout_data['shipping_state']) : '',
                     'postcode' => isset($checkout_data['shipping_postcode']) ? sanitize_text_field($checkout_data['shipping_postcode']) : '',
                     'country' => isset($checkout_data['shipping_country']) ? sanitize_text_field($checkout_data['shipping_country']) : '',
                 );
                 error_log('YPrint Checkout AJAX: Using new shipping address.');
            }

            // Determine Billing Address Data
            $ship_to_different_address = isset($checkout_data['ship_to_different_address']) && $checkout_data['ship_to_different_address'] == 1;

            if ($ship_to_different_address) {
                 // User specified a separate billing address
                 $billing_selection = isset($checkout_data['yprint_billing_address_selection']) ? sanitize_text_field($checkout_data['yprint_billing_address_selection']) : 'new_address';

                 if ($user_id > 0 && $billing_selection !== 'new_address') {
                      // User selected a saved billing address
                      $saved_billing_addresses = $this->get_user_saved_addresses('billing');
                      if (isset($saved_billing_addresses[$billing_selection])) {
                           $billing_address_data = $saved_billing_addresses[$billing_selection];
                           error_log('YPrint Checkout AJAX: Using saved billing address: ' . $billing_selection);
                      } else {
                          // Fallback if saved address ID is invalid
                          $billing_selection = 'new_address';
                          error_log('YPrint Checkout AJAX: Saved billing address ID invalid, falling back to new billing address.');
                      }
                 }

                 if ($billing_selection === 'new_address') {
                      // User entered a new billing address
                      $billing_address_data = array(
                          'first_name' => isset($checkout_data['billing_first_name']) ? sanitize_text_field($checkout_data['billing_first_name']) : '',
                          'last_name' => isset($checkout_data['billing_last_name']) ? sanitize_text_field($checkout_data['billing_last_name']) : '',
                          'company' => isset($checkout_data['billing_company']) ? sanitize_text_field($checkout_data['billing_company']) : '',
                          'address_1' => isset($checkout_data['billing_address_1']) ? sanitize_text_field($checkout_data['billing_address_1']) : '',
                          'address_2' => isset($checkout_data['billing_address_2']) ? sanitize_text_field($checkout_data['billing_address_2']) : '',
                          'city' => isset($checkout_data['billing_city']) ? sanitize_text_field($checkout_data['billing_city']) : '',
                          'state' => isset($checkout_data['billing_state']) ? sanitize_text_field($checkout_data['billing_state']) : '',
                          'postcode' => isset($checkout_data['billing_postcode']) ? sanitize_text_field($checkout_data['billing_postcode']) : '',
                          'country' => isset($checkout_data['billing_country']) ? sanitize_text_field($checkout_data['billing_country']) : '',
                          'email' => isset($checkout_data['billing_email']) ? sanitize_email($checkout_data['billing_email']) : '',
                          'phone' => isset($checkout_data['billing_phone']) ? sanitize_text_field($checkout_data['billing_phone']) : '',
                      );
                      error_log('YPrint Checkout AJAX: Using new billing address.');
                 }

            } else {
                 // Billing address is the same as shipping address
                 $billing_address_data = $shipping_address_data;
                 // Also copy email and phone if available in billing fields (might be for guests)
                 if (isset($checkout_data['billing_email'])) $billing_address_data['email'] = sanitize_email($checkout_data['billing_email']);
                 if (isset($checkout_data['billing_phone'])) $billing_address_data['phone'] = sanitize_text_field($checkout_data['billing_phone']);

                 error_log('YPrint Checkout AJAX: Billing address is same as shipping.');
            }

            // Set customer addresses and location in WooCommerce session
            // This is how WC determines shipping methods and taxes
            WC()->customer->set_shipping_first_name(isset($shipping_address_data['first_name']) ? $shipping_address_data['first_name'] : '');
            WC()->customer->set_shipping_last_name(isset($shipping_address_data['last_name']) ? $shipping_address_data['last_name'] : '');
            WC()->customer->set_shipping_company(isset($shipping_address_data['company']) ? $shipping_address_data['company'] : '');
            WC()->customer->set_shipping_address_1(isset($shipping_address_data['address_1']) ? $shipping_address_data['address_1'] : '');
            WC()->customer->set_shipping_address_2(isset($shipping_address_data['address_2']) ? $shipping_address_data['address_2'] : '');
            WC()->customer->set_shipping_city(isset($shipping_address_data['city']) ? $shipping_address_data['city'] : '');
            WC()->customer->set_shipping_state(isset($shipping_address_data['state']) ? $shipping_address_data['state'] : '');
            WC()->customer->set_shipping_postcode(isset($shipping_address_data['postcode']) ? $shipping_address_data['postcode'] : '');
            WC()->customer->set_shipping_country(isset($shipping_address_data['country']) ? $shipping_address_data['country'] : '');

            WC()->customer->set_billing_first_name(isset($billing_address_data['first_name']) ? $billing_address_data['first_name'] : '');
            WC()->customer->set_billing_last_name(isset($billing_address_data['last_name']) ? $billing_address_data['last_name'] : '');
            WC()->customer->set_billing_company(isset($billing_address_data['company']) ? $billing_address_data['company'] : '');
            WC()->customer->set_billing_address_1(isset($billing_address_data['address_1']) ? $billing_address_data['address_1'] : '');
            WC()->customer->set_billing_address_2(isset($billing_address_data['address_2']) ? $billing_address_data['address_2'] : '');
            WC()->customer->set_billing_city(isset($billing_address_data['city']) ? $billing_address_data['city'] : '');
            WC()->customer->set_billing_state(isset($billing_address_data['state']) ? $billing_address_data['state'] : '');
            WC()->customer->set_billing_postcode(isset($billing_address_data['postcode']) ? $billing_address_data['postcode'] : '');
            WC()->customer->set_billing_country(isset($billing_address_data['country']) ? $billing_address_data['country'] : '');
            WC()->customer->set_billing_email(isset($billing_address_data['email']) ? $billing_address_data['email'] : '');
            WC()->customer->set_billing_phone(isset($billing_address_data['phone']) ? $billing_address_data['phone'] : '');


            // Save customer data to session
            WC()->customer->save();
            error_log('YPrint Checkout AJAX: Customer addresses and location updated and saved.');


            // --- Update Shipping Method ---
            // Get the chosen shipping method from the posted data
            $chosen_shipping_methods = array();
            if (isset($checkout_data['shipping_method']) && !empty($checkout_data['shipping_method'])) {
                 // Sanitize and set the chosen shipping method in the session
                 // WooCommerce expects an array of chosen methods (one per package, usually just one)
                 $chosen_shipping_methods[] = sanitize_text_field($checkout_data['shipping_method']);
                 WC()->session->set('chosen_shipping_methods', $chosen_shipping_methods);
                 error_log('YPrint Checkout AJAX: Chosen shipping method set to ' . $chosen_shipping_methods[0]);
            } else {
                 // If no shipping method is posted, clear the chosen method in session
                 WC()->session->set('chosen_shipping_methods', array());
                 error_log('YPrint Checkout AJAX: No shipping method posted, clearing chosen method in session.');
            }


            // --- Recalculate Totals and Shipping ---
            // This is the core WC function to update everything based on the customer location and chosen shipping
            WC()->cart->calculate_totals();
            error_log('YPrint Checkout AJAX: Cart totals recalculated.');


            // --- Prepare Response Data ---
            // Get updated totals
            $totals = array(
                'subtotal' => WC()->cart->get_cart_subtotal(), // Already formatted
                'shipping' => wc_price(WC()->cart->get_shipping_total()), // Format shipping total
                'tax' => wc_price(WC()->cart->get_total_tax()), // Format tax total
                'total' => WC()->cart->get_total('edit'), // Get raw total for JS calculations (e.g., for Stripe)
                'total_formatted' => wc_price(WC()->cart->get_total()), // Get formatted total for display
            );
             error_log('YPrint Checkout AJAX: Calculated totals: ' . print_r($totals, true));


            // Get available shipping methods for the updated location
            $shipping_methods = $this->get_available_shipping_methods();
            error_log('YPrint Checkout AJAX: Available shipping methods: ' . print_r($shipping_methods, true));

            // Get updated cart items HTML (optional, but useful for updating the summary)
            // You would need to render the cart items section of your template here
            // For simplicity, we'll just return the totals and shipping methods for now.
            // If you need to update the cart items display, you'd call a function here that
            // renders the 'cart-items' part of checkout.php using the current WC()->cart data.


            $response = array(
                'success' => true,
                'data' => array(
                    'totals' => $totals,
                    'shipping_methods' => $shipping_methods,
                    // 'cart_items_html' => $this->render_cart_items(), // Example if you need to update item list
                ),
            );

            error_log('YPrint Checkout AJAX: Sending success response for update_checkout.');
            wp_send_json_success($response);

        } catch (Exception $e) {
            error_log('YPrint Checkout AJAX Error in ajax_update_checkout: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
         wp_die(); // Always die after sending JSON
    }

    /**
     * Get available shipping methods in a structured array.
     *
     * @return array
     */
    private function get_available_shipping_methods() {
        $shipping_methods = array();

        // Get calculated packages (requires calculate_shipping() to be called first, which calculate_totals() does)
        $packages = WC()->shipping->get_packages();

        if (!empty($packages)) {
            foreach ($packages as $package_key => $package) {
                // Check if rates are available for the package
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $key => $rate) {
                        // Ensure rate object is valid
                        if ($rate instanceof WC_Shipping_Rate) {
                            $shipping_methods[] = array(
                                'id' => $rate->id, // Rate ID (e.g., 'flat_rate:1')
                                'label' => $rate->label, // Rate label (e.g., 'Flat Rate')
                                'cost' => (float) $rate->cost, // Raw cost as float
                                'cost_formatted' => wc_price($rate->cost), // Formatted cost
                                // 'taxes' => $rate->taxes, // Optional: include tax details if needed on frontend
                                'method_id' => $rate->method_id, // Method ID (e.g., 'flat_rate')
                            );
                        } else {
                             error_log('YPrint Checkout AJAX: Invalid shipping rate object found.');
                        }
                    }
                } else {
                     error_log('YPrint Checkout AJAX: No rates found for package key: ' . $package_key);
                }
            }
        } else {
             error_log('YPrint Checkout AJAX: No shipping packages found.');
        }

        return $shipping_methods;
    }

    /**
     * AJAX handler to process the checkout and place the order.
     */
    public function ajax_process_checkout() {
        error_log('YPrint Checkout AJAX: ajax_process_checkout called');

        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'yprint-checkout')) {
            error_log('YPrint Checkout AJAX: Nonce verification failed.');
            wp_send_json_error(array(
                'message' => __('Invalid nonce.', 'yprint-plugin'),
            ), 403);
            wp_die();
        }
         error_log('YPrint Checkout AJAX: Nonce verification successful.');

        try {
             // Ensure WooCommerce is loaded in AJAX context
            if (!class_exists('WooCommerce')) {
                 error_log('YPrint Checkout AJAX: WooCommerce not loaded in AJAX context.');
                 throw new Exception(__('WooCommerce is not available.', 'yprint-plugin'));
            }
             error_log('YPrint Checkout AJAX: WooCommerce is available.');


            // Get checkout data string from POST request
            $posted_data = isset($_POST['checkout_data']) ? $_POST['checkout_data'] : '';
            error_log('YPrint Checkout AJAX: Received checkout_data string: ' . $posted_data);

            // Parse the query string into an array
            $checkout_data = array();
            if (!empty($posted_data)) {
                 parse_str($posted_data, $checkout_data);
            }
            error_log('YPrint Checkout AJAX: Parsed checkout_data array: ' . print_r($checkout_data, true));


            // Validate required fields based on the submitted data
            $this->validate_checkout_fields($checkout_data);
             error_log('YPrint Checkout AJAX: Checkout fields validated.');


            // --- Create Order ---
            $order_id = $this->create_order($checkout_data);

            if (!$order_id || is_wp_error($order_id)) {
                 $error_message = is_wp_error($order_id) ? $order_id->get_error_message() : __('Error creating order. Please try again.', 'yprint-plugin');
                 error_log('YPrint Checkout AJAX Error: Failed to create order. ' . $error_message);
                 throw new Exception($error_message);
            }

            $order = wc_get_order($order_id);
            if (!$order) {
                 error_log('YPrint Checkout AJAX Error: Created order ID ' . $order_id . ' but could not retrieve order object.');
                 throw new Exception(__('Error retrieving order after creation.', 'yprint-plugin'));
            }
             error_log('YPrint Checkout AJAX: Order created successfully with ID ' . $order_id);


            // --- Process Payment ---
            // Get the chosen payment method ID from the posted data
            $payment_method_id = isset($checkout_data['payment_method']) ? sanitize_text_field($checkout_data['payment_method']) : ''; // e.g., 'yprint_stripe'
            $stripe_payment_method_id = isset($checkout_data['payment_method_id']) ? sanitize_text_field($checkout_data['payment_method_id']) : ''; // Stripe's client-side PaymentMethod ID

            if (empty($payment_method_id)) {
                 error_log('YPrint Checkout AJAX Error: No payment method selected.');
                 throw new Exception(__('No payment method selected.', 'yprint-plugin'));
            }

            // Set payment method on the order
            $order->set_payment_method($payment_method_id);

            // Store Stripe's client-side PaymentMethod ID as order meta if available
            if (!empty($stripe_payment_method_id)) {
                 $order->update_meta_data('_yprint_stripe_payment_method_id', $stripe_payment_method_id);
            }
            // Store payment request type if available (e.g., 'apple_pay', 'google_pay')
            if (isset($checkout_data['payment_request_type'])) {
                 $order->update_meta_data('_yprint_stripe_payment_request_type', sanitize_text_field($checkout_data['payment_request_type']));
            }
             // Store the selected saved address IDs if applicable
             if (isset($checkout_data['yprint_shipping_address_selection']) && $checkout_data['yprint_shipping_address_selection'] !== 'new_address') {
                  $order->update_meta_data('_yprint_chosen_shipping_address_id', sanitize_text_field($checkout_data['yprint_shipping_address_selection']));
             }
             if (isset($checkout_data['yprint_billing_address_selection']) && $checkout_data['yprint_billing_address_selection'] !== 'new_address') {
                  $order->update_meta_data('_yprint_chosen_billing_address_id', sanitize_text_field($checkout_data['yprint_billing_address_selection']));
             }


            $order->save();
             error_log('YPrint Checkout AJAX: Payment method and meta data set on order.');


            // Get the payment gateway instance
            $gateways = WC()->payment_gateways()->payment_gateways();
            $gateway = isset($gateways[$payment_method_id]) ? $gateways[$payment_method_id] : null;

            // Check if gateway exists and is enabled and supports products
            if (!$gateway || !$gateway->is_available() || !$gateway->supports('products')) {
                error_log('YPrint Checkout AJAX Error: Payment gateway ' . $payment_method_id . ' not found, not available, or does not support products.');
                throw new Exception(__('Payment gateway not found or is not configured correctly.', 'yprint-plugin'));
            }
             error_log('YPrint Checkout AJAX: Payment gateway instance retrieved: ' . $payment_method_id);


            // Process payment using the gateway's process_payment method
            // This method handles the actual payment processing (e.g., calling Stripe API)
            // and returns a result array with 'result' and 'redirect' keys.
            $process_result = $gateway->process_payment($order_id);
             error_log('YPrint Checkout AJAX: Gateway process_payment result: ' . print_r($process_result, true));


            // Check the result of process_payment
            if (isset($process_result['result']) && $process_result['result'] === 'success') {
                // Payment was successful according to the gateway

                // Empty cart (WooCommerce's process_payment usually does this, but good to be sure)
                WC()->cart->empty_cart();
                 error_log('YPrint Checkout AJAX: Cart emptied.');

                // Send success response with redirect URL
                wp_send_json_success(array(
                    'result' => 'success',
                    'redirect' => $process_result['redirect'], // Redirect URL from gateway (e.g., order received page)
                ));

            } elseif (isset($process_result['result']) && $process_result['result'] === 'failure') {
                 // Payment failed according to the gateway
                 $error_message = isset($process_result['messages']) ? strip_tags($process_result['messages']) : __('Payment processing failed. Please try again.', 'yprint-plugin');
                  // The gateway should have already set the order status to 'failed'
                 error_log('YPrint Checkout AJAX Error: Payment processing failed. ' . $error_message);
                 throw new Exception($error_message); // Throw exception to send error JSON

            } elseif (isset($process_result['redirect'])) {
                 // Gateway requires further action (like 3D Secure authentication) and provided a redirect URL
                 wp_send_json_success($process_result); // Send the gateway's response directly, frontend JS will handle redirect

            } else {
                // Unexpected result from gateway
                error_log('YPrint Checkout AJAX Error: Unexpected result from payment gateway process_payment.');
                throw new Exception(__('An unexpected error occurred during payment processing.', 'yprint-plugin'));
            }

        } catch (Exception $e) {
            error_log('YPrint Checkout AJAX Error in ajax_process_checkout: ' . $e->getMessage());
            // If an order was created before the exception, mark it as failed
            // Check if $order object exists and is a WC_Order instance
            if (isset($order) && $order instanceof WC_Order && !$order->has_status(wc_get_is_paid_statuses())) {
                 // Avoid setting to failed if it's already a paid status (shouldn't happen with exceptions before payment success)
                 $order->update_status('failed', sprintf(__('Checkout processing failed: %s', 'yprint-plugin'), $e->getMessage()));
            }
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
        wp_die(); // Always die after sending JSON
    }

    /**
     * Validate checkout fields based on submission.
     *
     * @param array $data Array of posted checkout data.
     * @throws Exception If validation fails.
     */
    private function validate_checkout_fields($data) {
        // Define required fields for billing
        $required_billing_fields = array(
            'billing_first_name' => __('First name', 'yprint-plugin'),
            'billing_last_name' => __('Last name', 'yprint-plugin'),
            'billing_address_1' => __('Street address', 'yprint-plugin'),
            'billing_city' => __('Town / City', 'yprint-plugin'),
            'billing_postcode' => __('Postcode / ZIP', 'yprint-plugin'),
            'billing_country' => __('Country / Region', 'yprint-plugin'),
            'billing_email' => __('Email address', 'yprint-plugin'),
            'billing_phone' => __('Phone', 'yprint-plugin'),
        );

        // Define required fields for shipping if separate shipping is used
        $required_shipping_fields = array(
            'shipping_first_name' => __('Shipping first name', 'yprint-plugin'),
            'shipping_last_name' => __('Shipping last name', 'yprint-plugin'),
            'shipping_address_1' => __('Shipping street address', 'yprint-plugin'),
            'shipping_city' => __('Shipping town / city', 'yprint-plugin'),
            'shipping_postcode' => __('Shipping postcode / ZIP', 'yprint-plugin'),
            'shipping_country' => __('Shipping country / region', 'yprint-plugin'),
        );

        $needs_shipping = WC()->cart->needs_shipping();
        $ship_to_different_address = isset($data['ship_to_different_address']) && $data['ship_to_different_address'] == 1;
        $user_id = get_current_user_id();

        // --- Validate Billing Address ---
        $billing_selection = isset($data['yprint_billing_address_selection']) ? sanitize_text_field($data['yprint_billing_address_selection']) : 'new_address';

        if ($ship_to_different_address && ($billing_selection === 'new_address' || $user_id <= 0)) {
             // Validate new billing address fields if separate billing is used and it's a new address or guest
             foreach ($required_billing_fields as $key => $label) {
                 if (empty($data[$key])) {
                      // Special check for state field: only required if the selected country has states
                      if ($key === 'billing_state') {
                           $country_code = isset($data['billing_country']) ? $data['billing_country'] : '';
                           $states_for_country = WC()->countries->get_states($country_code);
                           if (!empty($states_for_country)) {
                                throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $label));
                           }
                      } else {
                           throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $label));
                      }
                 }
             }
             // Validate email format for new billing address
             if (!empty($data['billing_email']) && !is_email($data['billing_email'])) {
                  throw new Exception(__('Please enter a valid email address.', 'yprint-plugin'));
             }

        } elseif (!$ship_to_different_address) {
             // If billing is same as shipping, validate the fields used for shipping
             // which are the shipping fields if 'new_address' is selected for shipping,
             // or the saved shipping address data if a saved one is selected.
             // We'll rely on the shipping validation below, but ensure email/phone are checked if needed.
             if (empty($data['billing_email']) && !is_user_logged_in()) { // Email is required for guests even if billing=shipping
                  throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $required_billing_fields['billing_email']));
             }
             if (!empty($data['billing_email']) && !is_email($data['billing_email'])) {
                  throw new Exception(__('Please enter a valid email address.', 'yprint-plugin'));
             }
             if (empty($data['billing_phone']) && !is_user_logged_in()) { // Phone might be required for guests
                  // Check WC setting if phone is required
                  $checkout_fields = WC()->checkout()->get_checkout_fields('billing');
                  if (isset($checkout_fields['billing_phone']['required']) && $checkout_fields['billing_phone']['required']) {
                       throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $required_billing_fields['billing_phone']));
                  }
             }
        }
        // If separate billing is used and a saved billing address is selected, we assume the saved data is valid.


        // --- Validate Shipping Address ---
        // Shipping address is always required if the cart needs shipping.
        if ($needs_shipping) {
             $shipping_selection = isset($data['yprint_shipping_address_selection']) ? sanitize_text_field($data['yprint_shipping_address_selection']) : 'new_address';

             if ($shipping_selection === 'new_address' || $user_id <= 0) {
                  // Validate new shipping address fields if 'new address' is selected or it's a guest
                  foreach ($required_shipping_fields as $key => $label) {
                       if (empty($data[$key])) {
                            // Special check for state field
                           if ($key === 'shipping_state') {
                                $country_code = isset($data['shipping_country']) ? $data['shipping_country'] : '';
                                $states_for_country = WC()->countries->get_states($country_code);
                                if (!empty($states_for_country)) {
                                     throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $label));
                                }
                           } else {
                                throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $label));
                           }
                       }
                  }
             }
             // If a saved shipping address is selected, we assume the saved data is valid.
        }


        // --- Validate Shipping Method ---
        if ($needs_shipping) {
             $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
             if (empty($chosen_shipping_methods)) {
                  // This should ideally be set by ajax_update_checkout, but double-check
                  throw new Exception(__('Please select a shipping method.', 'yprint-plugin'));
             }
             // We could also validate if the chosen method is actually available for the current address,
             // but WC's process_checkout usually handles this check internally.
        }


        // --- Validate Payment Method ---
        $payment_method = isset($data['payment_method']) ? sanitize_text_field($data['payment_method']) : '';
        $payment_method_id_posted = isset($data['payment_method_id']) ? sanitize_text_field($data['payment_method_id']) : ''; // Stripe PaymentMethod ID

        if (empty($payment_method)) {
             throw new Exception(__('Please select a payment method.', 'yprint-plugin'));
        }

        // Basic validation for Stripe if it's the selected method
        if ($payment_method === 'yprint_stripe') { // Assuming 'yprint_stripe' is the gateway ID
             // If using the card element, we need the client-side payment method ID
             // If using Payment Request Button, the payment_method_id_posted should also be set
             if (empty($payment_method_id_posted)) {
                  // This check might be too strict if other payment methods are added later
                  // It's safer to rely on the payment gateway's own validation.
                  // For Stripe card element, the JS should ensure payment_method_id is populated.
             }
             // The actual card details validation is handled client-side by Stripe.js
             // and server-side by the Stripe gateway's process_payment method.
        }

        // Add any other custom validations here (e.g., terms and conditions checkbox)

        // If we reach here, validation passed
        error_log('YPrint Checkout Validation: All fields passed validation.');
        return true;
    }


    /**
     * Create a WooCommerce order from the cart and posted data.
     *
     * @param array $data Array of posted checkout data.
     * @return int|WP_Error Order ID on success, WP_Error on failure.
     */
    private function create_order($data) {
        // Ensure cart is not empty before creating order
        if (WC()->cart->is_empty()) {
             error_log('YPrint Checkout Error: Cannot create order, cart is empty.');
             return new WP_Error('yprint_checkout_empty_cart', __('Cannot create order, cart is empty.', 'yprint-plugin'));
        }

        // Recalculate totals one last time to be sure
        WC()->cart->calculate_totals();

        // Determine customer ID (guest or logged-in user)
        $customer_id = apply_filters('woocommerce_checkout_customer_id', get_current_user_id());

        // Set up basic order data
        $order_data = array(
            'status' => apply_filters('woocommerce_default_order_status', 'pending'), // Default status for new orders
            'customer_id' => $customer_id,
            'customer_note' => isset($data['order_comments']) ? sanitize_textarea_field($data['order_comments']) : '',
            'cart_hash' => WC()->cart->get_cart_hash(),
            'created_via' => 'yprint_checkout', // Custom field to identify source
            'customer_ip_address' => WC_Geolocation::get_ip_address(), // Store customer IP
            'customer_user_agent' => wc_get_user_agent(), // Store user agent
        );

        // Create the order object
        $order = wc_create_order($order_data);

        if (is_wp_error($order)) {
            error_log('YPrint Checkout Error: Failed to create order object. ' . $order->get_error_message());
            return $order; // Return WP_Error
        }
        error_log('YPrint Checkout: Order object created.');


        // --- Set Addresses ---
        $user_id = get_current_user_id();
        $shipping_address_data = array();
        $billing_address_data = array();

        // Get Shipping Address Data (from saved or new fields)
        $shipping_selection = isset($data['yprint_shipping_address_selection']) ? sanitize_text_field($data['yprint_shipping_address_selection']) : 'new_address';
        if ($user_id > 0 && $shipping_selection !== 'new_address') {
             $saved_shipping_addresses = $this->get_user_saved_addresses('shipping');
             if (isset($saved_shipping_addresses[$shipping_selection])) {
                  $shipping_address_data = $saved_shipping_addresses[$shipping_selection];
             }
        }
        if (empty($shipping_address_data) || $shipping_selection === 'new_address') {
             // Use data from posted new shipping fields
             $shipping_address_data = array(
                 'first_name' => isset($data['shipping_first_name']) ? sanitize_text_field($data['shipping_first_name']) : '',
                 'last_name' => isset($data['shipping_last_name']) ? sanitize_text_field($data['shipping_last_name']) : '',
                 'company' => isset($data['shipping_company']) ? sanitize_text_field($data['shipping_company']) : '',
                 'address_1' => isset($data['shipping_address_1']) ? sanitize_text_field($data['shipping_address_1']) : '',
                 'address_2' => isset($data['shipping_address_2']) ? sanitize_text_field($data['shipping_address_2']) : '',
                 'city' => isset($data['shipping_city']) ? sanitize_text_field($data['shipping_city']) : '',
                 'state' => isset($data['shipping_state']) ? sanitize_text_field($data['shipping_state']) : '',
                 'postcode' => isset($data['shipping_postcode']) ? sanitize_text_field($data['shipping_postcode']) : '',
                 'country' => isset($data['shipping_country']) ? sanitize_text_field($data['shipping_country']) : '',
             );
        }
        $order->set_address($shipping_address_data, 'shipping');
         error_log('YPrint Checkout: Shipping address set on order.');


        // Get Billing Address Data (from saved, new fields, or shipping if same)
        $ship_to_different_address = isset($data['ship_to_different_address']) && $data['ship_to_different_address'] == 1;

        if ($ship_to_different_address) {
             $billing_selection = isset($data['yprint_billing_address_selection']) ? sanitize_text_field($data['yprint_billing_address_selection']) : 'new_address';
             if ($user_id > 0 && $billing_selection !== 'new_address') {
                  $saved_billing_addresses = $this->get_user_saved_addresses('billing');
                  if (isset($saved_billing_addresses[$billing_selection])) {
                       $billing_address_data = $saved_billing_addresses[$billing_selection];
                  }
             }
             if (empty($billing_address_data) || $billing_selection === 'new_address') {
                  // Use data from posted new billing fields
                  $billing_address_data = array(
                      'first_name' => isset($data['billing_first_name']) ? sanitize_text_field($data['billing_first_name']) : '',
                      'last_name' => isset($data['billing_last_name']) ? sanitize_text_field($data['billing_last_name']) : '',
                      'company' => isset($data['billing_company']) ? sanitize_text_field($data['billing_company']) : '',
                      'address_1' => isset($data['billing_address_1']) ? sanitize_text_field($data['billing_address_1']) : '',
                      'address_2' => isset($data['billing_address_2']) ? sanitize_text_field($data['billing_address_2']) : '',
                      'city' => isset($data['billing_city']) ? sanitize_text_field($data['billing_city']) : '',
                      'state' => isset($data['billing_state']) ? sanitize_text_field($data['billing_state']) : '',
                      'postcode' => isset($data['billing_postcode']) ? sanitize_text_field($data['billing_postcode']) : '',
                      'country' => isset($data['billing_country']) ? sanitize_text_field($data['billing_country']) : '',
                      'email' => isset($data['billing_email']) ? sanitize_email($data['billing_email']) : '',
                      'phone' => isset($data['billing_phone']) ? sanitize_text_field($data['billing_phone']) : '',
                  );
             }
        } else {
             // Billing address is same as shipping
             $billing_address_data = $shipping_address_data;
             // Ensure email and phone are included from billing fields if available (for guests)
             if (isset($data['billing_email'])) $billing_address_data['email'] = sanitize_email($data['billing_email']);
             if (isset($data['billing_phone'])) $billing_address_data['phone'] = sanitize_text_field($data['billing_phone']);
        }
        $order->set_address($billing_address_data, 'billing');
         error_log('YPrint Checkout: Billing address set on order.');


        // --- Add Products from Cart ---
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            // Get product object
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];

            // Add item to order using WC_Order's add_product method
            // This method handles variations, subtotals, totals, and tax data automatically from the cart item
            $item_id = $order->add_product(
                $product,
                $quantity,
                array(
                    'variation' => $cart_item['variation'], // Pass variation attributes if it's a variable product
                    'subtotal' => $cart_item['line_subtotal'],
                    'subtotal_tax' => $cart_item['line_subtotal_tax'],
                    'total' => $cart_item['line_total'],
                    'tax' => $cart_item['line_tax'],
                    'tax_data' => $cart_item['line_tax_data'], // Pass tax data details
                    'item_meta_data' => $cart_item['data']->get_meta_data(), // Pass product meta data
                )
            );

            if (is_wp_error($item_id)) {
                 error_log('YPrint Checkout Error: Failed to add product to order. ' . $item_id->get_error_message());
                 // Clean up the partially created order
                 $order->delete(true); // Delete the order permanently
                 return $item_id; // Return WP_Error
            }
             error_log('YPrint Checkout: Added product ID ' . $product->get_id() . ' to order.');
        }


        // --- Add Shipping Cost as an Item ---
        if (WC()->cart->needs_shipping()) {
            // Get chosen shipping method from session (set by ajax_update_checkout)
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

            if (!empty($chosen_shipping_methods) && isset($chosen_shipping_methods[0])) {
                $shipping_method_id = $chosen_shipping_methods[0];
                // Retrieve the shipping rate object from the available rates
                $shipping_rate = $this->get_shipping_rate($shipping_method_id);

                if ($shipping_rate && $shipping_rate instanceof WC_Shipping_Rate) {
                     error_log('YPrint Checkout: Adding shipping rate ' . $shipping_rate->id . ' to order.');
                    $item = new WC_Order_Item_Shipping();
                    $item->set_props(array(
                        'method_title' => $shipping_rate->label,
                        'method_id' => $shipping_rate->method_id,
                        'total' => wc_format_decimal($shipping_rate->cost), // Use cost from the rate
                        'taxes' => $shipping_rate->taxes, // Use taxes from the rate object
                        // 'order_id' => $order->get_id(), // Set by add_item
                    ));

                    $order->add_item($item);
                     error_log('YPrint Checkout: Shipping item added.');
                } else {
                     error_log('YPrint Checkout Error: Chosen shipping rate ' . $shipping_method_id . ' not found or invalid. Order created without shipping item.');
                     // The order will be created, but without a shipping item. Recalculate totals will reflect this.
                }
            } else {
                 error_log('YPrint Checkout Warning: Cart needs shipping but no chosen shipping method found in session. Order created without shipping item.');
                 // This might happen if update_checkout wasn't called correctly or session was lost.
            }
        } else {
             error_log('YPrint Checkout: Cart does not need shipping.');
        }


        // --- Add Fees as Items ---
        foreach (WC()->cart->get_fees() as $fee_key => $fee) {
             error_log('YPrint Checkout: Adding fee ' . $fee->name . ' to order.');
            $item = new WC_Order_Item_Fee();
            $item->set_props(array(
                'name' => $fee->name,
                'tax_class' => $fee->tax_class,
                'amount' => $fee->amount, // Raw amount
                'total' => $fee->total, // Total including tax
                'total_tax' => $fee->tax, // Total tax amount for the fee
                'taxes' => array( // Format taxes correctly for item
                    'total' => $fee->tax_data,
                ),
                // 'order_id' => $order->get_id(), // Set by add_item
            ));

            $order->add_item($item);
             error_log('YPrint Checkout: Fee item added.');
        }


        // --- Add Taxes as Items ---
        // WC_Cart::get_tax_totals() returns formatted tax lines based on calculated taxes
        foreach (WC()->cart->get_tax_totals() as $code => $tax) {
             error_log('YPrint Checkout: Adding tax line ' . $code . ' to order.');
            $item = new WC_Order_Item_Tax();
            $item->set_props(array(
                'rate_code' => $code, // Tax rate code
                'rate_id' => $tax->rate_id, // Tax rate ID
                'label' => $tax->label, // Tax label
                'compound' => $tax->is_compound, // Is it a compound tax?
                'tax_total' => $tax->amount, // Total tax amount for this rate
                'shipping_tax_total' => $tax->shipping_tax_amount, // Shipping tax amount for this rate
                // 'order_id' => $order->get_id(), // Set by add_item
            ));

            $order->add_item($item);
             error_log('YPrint Checkout: Tax item added.');
        }


        // --- Calculate and Save Order Totals ---
        // This is crucial after adding all items to ensure order totals match the items
        $order->calculate_totals();
         error_log('YPrint Checkout: Order totals recalculated based on items.');


        // --- Save Order ---
        $order->save();
         error_log('YPrint Checkout: Order saved successfully with ID ' . $order->get_id());


        // Return the order ID
        return $order->get_id();
    }

    /**
     * Get a specific shipping rate object from the available calculated rates.
     *
     * @param string $shipping_method_id The rate ID (e.g., 'flat_rate:1').
     * @return WC_Shipping_Rate|null The WC_Shipping_Rate object or null if not found.
     */
    private function get_shipping_rate($shipping_method_id) {
        // Get calculated packages (requires WC()->cart->calculate_shipping() or calculate_totals() to be called first)
        $packages = WC()->shipping->get_packages();

        if (!empty($packages)) {
            foreach ($packages as $package) {
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $rate) {
                        // Compare the rate ID
                        if ($rate instanceof WC_Shipping_Rate && $rate->id === $shipping_method_id) {
                            return $rate; // Return the matching WC_Shipping_Rate object
                        }
                    }
                }
            }
        }

        error_log('YPrint Checkout Warning: Shipping rate ' . $shipping_method_id . ' not found in available rates.');
        return null; // Return null if rate is not found
    }

    /**
     * AJAX handler for fetching states based on country.
     * Used by the wc-country-select script.
     * This is essentially a wrapper for the default WC AJAX handler.
     */
    public function ajax_get_states() {
        // Delegate to the standard WooCommerce AJAX handler for states
        // This requires the WC_AJAX class to be available
        if (class_exists('WC_AJAX')) {
            WC_AJAX::get_states();
        } else {
            // Fallback error if WC_AJAX is not available
            wp_send_json_error(array(
                'message' => __('WooCommerce AJAX handler for states not available.', 'yprint-plugin'),
            ));
            wp_die();
        }
    }

    /**
     * Helper function to render the cart items HTML for AJAX updates.
     * (Optional - uncomment and use in ajax_update_checkout if needed)
     *
     * @return string HTML for cart items.
     */
    /*
    private function render_cart_items() {
        ob_start();
        // Loop through cart items and render the HTML structure from checkout.php
        // This requires replicating the relevant part of the checkout.php template here
        // or creating a separate template part for cart items.
        // Example (simplified):
        if (!WC()->cart->is_empty()) {
             foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                 $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                 if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                     $product_name = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
                     $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                     $product_subtotal = apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key);
                     ?>
                     <div class="cart-item">
                         <div class="cart-item-image">
                             <?php echo $thumbnail; ?>
                             <span class="item-quantity"><?php echo $cart_item['quantity']; ?></span>
                         </div>
                         <div class="cart-item-details">
                             <div class="cart-item-name"><?php echo $product_name; ?></div>
                             <?php
                             // Display variation data
                             if (!empty($cart_item['variation'])) {
                                 echo '<div class="cart-item-variation">';
                                 foreach ($cart_item['variation'] as $attr => $value) {
                                     $taxonomy = wc_attribute_taxonomy_name(str_replace('attribute_pa_', '', urldecode($attr)));
                                     $term = get_term_by('slug', $value, $taxonomy);
                                     $label = wc_attribute_label(str_replace('attribute_', '', urldecode($attr)));
                                     $display_value = $term ? $term->name : $value;
                                     echo $label . ': ' . $display_value . '<br>';
                                 }
                                 echo '</div>';
                             }
                             ?>
                         </div>
                         <div class="cart-item-price"><?php echo $product_subtotal; ?></div>
                     </div>
                     <?php
                 }
             }
        } else {
             // Cart is empty
             echo '<p class="empty-cart-message">' . esc_html__('Your cart is empty.', 'yprint-plugin') . '</p>';
        }

        return ob_get_clean();
    }
    */

}

// Initialize the class
YPrint_Stripe_Checkout_Shortcode::get_instance();

// Note: This file assumes the existence of:
// - WooCommerce plugin active
// - A constant YPRINT_PLUGIN_URL pointing to the plugin's URL
// - A constant YPRINT_PLUGIN_VERSION for versioning
// - A constant YPRINT_PLUGIN_DIR pointing to the plugin's directory
// - A class YPrint_Stripe_API with a static method get_stripe_settings()
// - The template file 'templates/checkout.php' within the plugin directory
// - Or 'yprint-checkout/checkout.php' within the active theme directory
?>
