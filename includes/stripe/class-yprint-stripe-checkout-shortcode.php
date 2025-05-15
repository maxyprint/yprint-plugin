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
        // Check if it's a singular post/page and has the shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'yprint_checkout')) {

            // Check if WooCommerce is active, as our script depends on its localization (for states)
            if (!class_exists('WooCommerce')) {
                 error_log('YPrint Checkout: WooCommerce is not active. Cannot enqueue scripts.');
                 return;
            }

            // Register and enqueue Stripe.js
            wp_register_script('stripe-js', 'https://js.stripe.com/v3/', array(), null, true);
            wp_enqueue_script('stripe-js'); // Enqueue Stripe.js

            // Register and enqueue our script
            wp_register_script(
                'yprint-checkout',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js',
                array('jquery', 'stripe-js', 'wc-country-select'), // Add wc-country-select dependency for states
                YPRINT_PLUGIN_VERSION,
                true
            );

            // Get Stripe settings
            $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
            $testmode = isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'];
            $publishable_key = $testmode ? $stripe_settings['test_publishable_key'] : $stripe_settings['publishable_key'];

            // Get payment request button settings
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
                        'locale' => str_replace('_', '-', get_locale()), // Stripe uses hyphenated locale
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
                        'select_state_text' => __('Select a state / county...', 'woocommerce'), // Use WC's text for states
                    ),
                    'button' => array(
                        'type' => $payment_request_button_type,
                        'theme' => $payment_request_button_theme,
                        'height' => $payment_request_button_height,
                    ),
                    'shipping_required' => WC()->cart->needs_shipping(),
                    'currency' => get_woocommerce_currency(),
                    'country_code' => substr(get_option('woocommerce_default_country'), 0, 2), // Get base country code
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

            // Enqueue WooCommerce's country-select script if not already enqueued
            // This script provides wc_country_select_params and handles state updates
            if (!wp_script_is('wc-country-select', 'enqueued')) {
                 wp_enqueue_script('wc-country-select');
            }
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
            return '<div class="yprint-checkout-error">' . esc_html__('WooCommerce is required for checkout.', 'yprint-plugin') . '</div>';
        }

        // Check if cart is empty
        if (WC()->cart->is_empty()) {
            return '<div class="yprint-checkout-error">' . esc_html__('Your cart is empty. Please add some products before checkout.', 'yprint-plugin') . '</div>';
        }

        // Start output buffering
        ob_start();

        // Get customer data
        $customer = WC()->customer;

        // Get countries and states
        $countries = WC()->countries->get_countries();
        // States are loaded via AJAX by wc-country-select script, no need to get all here

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
        // Ensure the template exists at this path: YPRINT_PLUGIN_DIR . 'templates/checkout.php'
        $template_path = YPRINT_PLUGIN_DIR . 'templates/checkout.php';
        if (file_exists($template_path)) {
             include($template_path);
        } else {
             error_log('YPrint Checkout: Checkout template not found at ' . $template_path);
             echo '<div class="yprint-checkout-error">' . esc_html__('Checkout template not found.', 'yprint-plugin') . '</div>';
        }


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
        // Use WC_Customer methods for getting addresses if possible
        $customer = new WC_Customer($user_id);

        $addresses = array(
            'billing' => array(
                'first_name' => $customer->get_billing_first_name(),
                'last_name' => $customer->get_billing_last_name(),
                'company' => $customer->get_billing_company(),
                'address_1' => $customer->get_billing_address_1(),
                'address_2' => $customer->get_billing_address_2(),
                'city' => $customer->get_billing_city(),
                'state' => $customer->get_billing_state(),
                'postcode' => $customer->get_billing_postcode(),
                'country' => $customer->get_billing_country(),
                'email' => $customer->get_billing_email(),
                'phone' => $customer->get_billing_phone(),
            ),
            'shipping' => array(
                'first_name' => $customer->get_shipping_first_name(),
                'last_name' => $customer->get_shipping_last_name(),
                'company' => $customer->get_shipping_company(),
                'address_1' => $customer->get_shipping_address_1(),
                'address_2' => $customer->get_shipping_address_2(),
                'city' => $customer->get_shipping_city(),
                'state' => $customer->get_shipping_state(),
                'postcode' => $customer->get_shipping_postcode(),
                'country' => $customer->get_shipping_country(),
            ),
        );

        return $addresses;
    }

    /**
     * AJAX update checkout
     */
    public function ajax_update_checkout() {
        error_log('YPrint Checkout AJAX: ajax_update_checkout called'); // Log start of function

        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'yprint-checkout')) {
            error_log('YPrint Checkout AJAX: Nonce verification failed.');
            wp_send_json_error(array(
                'message' => __('Invalid nonce.', 'yprint-plugin'),
            ), 403); // Use 403 Forbidden status for nonce failure
            wp_die(); // Always die after sending JSON
        }
         error_log('YPrint Checkout AJAX: Nonce verification successful.');

        try {
            // Ensure WooCommerce is loaded in AJAX context
            if (!class_exists('WooCommerce')) {
                 error_log('YPrint Checkout AJAX: WooCommerce not loaded in AJAX context.');
                 throw new Exception(__('WooCommerce is not available.', 'yprint-plugin'));
            }
             error_log('YPrint Checkout AJAX: WooCommerce is available.');


            // Get checkout data
            $posted_data = isset($_POST['checkout_data']) ? $_POST['checkout_data'] : '';
            error_log('YPrint Checkout AJAX: Received checkout_data string: ' . $posted_data);

            // Parse data
            $checkout_data = array();
            if (!empty($posted_data)) {
                 parse_str($posted_data, $checkout_data);
            }
            error_log('YPrint Checkout AJAX: Parsed checkout_data array: ' . print_r($checkout_data, true));

            // Update customer data based on received fields
            // Set customer location first, as it affects shipping methods
            if (isset($checkout_data['shipping_country'])) {
                WC()->customer->set_shipping_country(sanitize_text_field($checkout_data['shipping_country']));
            }
            if (isset($checkout_data['shipping_state'])) {
                WC()->customer->set_shipping_state(sanitize_text_field($checkout_data['shipping_state']));
            }
            if (isset($checkout_data['shipping_postcode'])) {
                WC()->customer->set_shipping_postcode(sanitize_text_field($checkout_data['shipping_postcode']));
            }
            if (isset($checkout_data['shipping_city'])) {
                WC()->customer->set_shipping_city(sanitize_text_field($checkout_data['shipping_city']));
            }

             // Update billing address if different
             if (isset($checkout_data['ship_to_different_address']) && $checkout_data['ship_to_different_address'] == 1) {
                if (isset($checkout_data['billing_country'])) {
                    WC()->customer->set_billing_country(sanitize_text_field($checkout_data['billing_country']));
                }
                if (isset($checkout_data['billing_state'])) {
                    WC()->customer->set_billing_state(sanitize_text_field($checkout_data['billing_state']));
                }
                if (isset($checkout_data['billing_postcode'])) {
                    WC()->customer->set_billing_postcode(sanitize_text_field($checkout_data['billing_postcode']));
                }
                if (isset($checkout_data['billing_city'])) {
                    WC()->customer->set_billing_city(sanitize_text_field($checkout_data['billing_city']));
                }
             } else {
                  // If shipping is same as billing, update shipping based on billing
                  if (isset($checkout_data['billing_country'])) {
                      WC()->customer->set_shipping_country(sanitize_text_field($checkout_data['billing_country']));
                  }
                  if (isset($checkout_data['billing_state'])) {
                      WC()->customer->set_shipping_state(sanitize_text_field($checkout_data['billing_state']));
                  }
                  if (isset($checkout_data['billing_postcode'])) {
                      WC()->customer->set_shipping_postcode(sanitize_text_field($checkout_data['billing_postcode']));
                  }
                  if (isset($checkout_data['billing_city'])) {
                      WC()->customer->set_shipping_city(sanitize_text_field($checkout_data['billing_city']));
                  }
             }

            // Save customer data to session
            WC()->customer->save();
            error_log('YPrint Checkout AJAX: Customer location updated and saved.');


            // Update shipping method if set
            if (isset($checkout_data['shipping_method']) && !empty($checkout_data['shipping_method'])) {
                // Ensure the chosen method is one of the available methods before setting
                 $available_methods = WC()->shipping()->get_shipping_methods();
                 $method_id = sanitize_text_field($checkout_data['shipping_method']);
                 $package_id = 0; // Assuming a single package for simplicity, adjust if needed

                 // Find the rate within the available packages
                 $packages = WC()->shipping->get_packages();
                 $rate_found = false;
                 if (!empty($packages)) {
                     foreach ($packages as $package_key => $package) {
                         if (!empty($package['rates'])) {
                             foreach ($package['rates'] as $key => $rate) {
                                 if ($rate->id === $method_id) {
                                     WC()->session->set('chosen_shipping_methods', array($method_id));
                                     $rate_found = true;
                                     error_log('YPrint Checkout AJAX: Chosen shipping method set to ' . $method_id);
                                     break 2; // Exit both loops
                                 }
                             }
                         }
                     }
                 }

                 if (!$rate_found) {
                     error_log('YPrint Checkout AJAX: Chosen shipping method ' . $method_id . ' is not available.');
                     // Optionally unset or set a default if the chosen method is invalid
                     // WC will likely default to the first available if the chosen one is invalid during calculation
                 }

            } else {
                 // If no shipping method is posted, clear the chosen method in session
                 WC()->session->set('chosen_shipping_methods', array());
                 error_log('YPrint Checkout AJAX: No shipping method posted, clearing chosen method in session.');
            }


            // Calculate shipping and totals
            WC()->cart->calculate_shipping(); // Calculate shipping first
            WC()->cart->calculate_totals(); // Then calculate totals

            error_log('YPrint Checkout AJAX: Shipping and Totals calculated.');

            // Get updated totals
            $totals = array(
                'subtotal' => WC()->cart->get_cart_subtotal(), // Already formatted
                'shipping' => wc_price(WC()->cart->get_shipping_total()), // Format shipping total
                'tax' => wc_price(WC()->cart->get_total_tax()), // Format tax total
                'total' => WC()->cart->get_total('edit'), // Get raw total for JS calculations
                'total_formatted' => wc_price(WC()->cart->get_total()), // Get formatted total for display
            );
             error_log('YPrint Checkout AJAX: Calculated totals: ' . print_r($totals, true));


            // Get available shipping methods
            $shipping_methods = $this->get_available_shipping_methods();
            error_log('YPrint Checkout AJAX: Available shipping methods: ' . print_r($shipping_methods, true));

            $response = array(
                'success' => true,
                'data' => array(
                    'totals' => $totals,
                    'shipping_methods' => $shipping_methods,
                ),
            );

            error_log('YPrint Checkout AJAX: Sending success response.');
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
     * Get available shipping methods
     *
     * @return array
     */
    private function get_available_shipping_methods() {
        $shipping_methods = array();

        // Get calculated packages
        $packages = WC()->shipping->get_packages();

        if (!empty($packages)) {
            foreach ($packages as $package_key => $package) {
                // Check if rates are available for the package
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $key => $rate) {
                        // Ensure rate object is valid
                        if ($rate instanceof WC_Shipping_Rate) {
                            $shipping_methods[] = array(
                                'id' => $rate->id,
                                'label' => $rate->label,
                                'cost' => $rate->cost, // Send raw cost
                                'cost_formatted' => wc_price($rate->cost), // Send formatted cost
                                'taxes' => $rate->taxes,
                                'method_id' => $rate->method_id,
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
     * AJAX process checkout
     */
    public function ajax_process_checkout() {
        error_log('YPrint Checkout AJAX: ajax_process_checkout called'); // Log start of function

        // Check nonce for security
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'yprint-checkout')) {
            error_log('YPrint Checkout AJAX: Nonce verification failed.');
            wp_send_json_error(array(
                'message' => __('Invalid nonce.', 'yprint-plugin'),
            ), 403); // Use 403 Forbidden status for nonce failure
            wp_die(); // Always die after sending JSON
        }
         error_log('YPrint Checkout AJAX: Nonce verification successful.');

        try {
             // Ensure WooCommerce is loaded in AJAX context
            if (!class_exists('WooCommerce')) {
                 error_log('YPrint Checkout AJAX: WooCommerce not loaded in AJAX context.');
                 throw new Exception(__('WooCommerce is not available.', 'yprint-plugin'));
            }
             error_log('YPrint Checkout AJAX: WooCommerce is available.');


            // Get checkout data
            $posted_data = isset($_POST['checkout_data']) ? $_POST['checkout_data'] : '';
            error_log('YPrint Checkout AJAX: Received checkout_data string: ' . $posted_data);

            // Parse data
            $checkout_data = array();
            if (!empty($posted_data)) {
                 parse_str($posted_data, $checkout_data);
            }
            error_log('YPrint Checkout AJAX: Parsed checkout_data array: ' . print_r($checkout_data, true));


            // Validate required fields
            $this->validate_checkout_fields($checkout_data);
             error_log('YPrint Checkout AJAX: Checkout fields validated.');


            // Create order
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


            // Process payment
            $payment_method = isset($checkout_data['payment_method']) ? sanitize_text_field($checkout_data['payment_method']) : 'yprint_stripe';
            $payment_method_id = isset($checkout_data['payment_method_id']) ? sanitize_text_field($checkout_data['payment_method_id']) : '';

            // Set payment method on the order
            $order->set_payment_method($payment_method);
            // Store payment method ID as order meta if available (useful for Stripe)
            if (!empty($payment_method_id)) {
                 $order->update_meta_data('_yprint_stripe_payment_method_id', $payment_method_id);
            }
            // Store payment request type if available
            if (isset($checkout_data['payment_request_type'])) {
                 $order->update_meta_data('_yprint_stripe_payment_request_type', sanitize_text_field($checkout_data['payment_request_type']));
            }
            $order->save();
             error_log('YPrint Checkout AJAX: Payment method set on order.');


            // Get the payment gateway instance
            $gateways = WC()->payment_gateways()->payment_gateways();
            $gateway = isset($gateways[$payment_method]) ? $gateways[$payment_method] : null;

            if (!$gateway || !$gateway->supports('products')) { // Check if gateway exists and supports products
                error_log('YPrint Checkout AJAX Error: Payment gateway ' . $payment_method . ' not found or does not support products.');
                throw new Exception(__('Payment gateway not found or is not configured correctly.', 'yprint-plugin'));
            }
             error_log('YPrint Checkout AJAX: Payment gateway instance retrieved.');


            // Process payment using the gateway
            // Pass checkout data to the gateway's process_payment method if it accepts it
            // Standard WC gateways usually just need the order ID
            $process_result = $gateway->process_payment($order_id);
             error_log('YPrint Checkout AJAX: Gateway process_payment result: ' . print_r($process_result, true));


            // Check the result of process_payment
            if (isset($process_result['result']) && $process_result['result'] === 'success') {
                // Payment was successful according to the gateway

                // Empty cart
                WC()->cart->empty_cart();
                 error_log('YPrint Checkout AJAX: Cart emptied.');

                wp_send_json_success(array(
                    'result' => 'success',
                    'redirect' => $process_result['redirect'], // Redirect URL from gateway
                ));

            } else {
                // Payment failed or requires further action (like 3D Secure)
                // The gateway's process_payment should handle setting order status (e.g., pending, failed)
                // and potentially return a redirect for authentication.

                // If the gateway returned a redirect, send it to the frontend
                 if (isset($process_result['redirect'])) {
                      wp_send_json_success($process_result); // Send the gateway's response directly
                 } else {
                     // If no redirect and not success, assume failure
                     $error_message = isset($process_result['message']) ? $process_result['message'] : __('Payment processing failed. Please try again.', 'yprint-plugin');
                     error_log('YPrint Checkout AJAX Error: Payment processing failed. ' . $error_message);
                     throw new Exception($error_message);
                 }
            }

        } catch (Exception $e) {
            error_log('YPrint Checkout AJAX Error in ajax_process_checkout: ' . $e->getMessage());
            // If an order was created before the exception, mark it as failed
            if (isset($order) && $order instanceof WC_Order && !$order->has_status('failed')) {
                 $order->update_status('failed', sprintf(__('Checkout processing failed: %s', 'yprint-plugin'), $e->getMessage()));
            }
            wp_send_json_error(array(
                'message' => $e->getMessage(),
            ));
        }
        wp_die(); // Always die after sending JSON
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

        // Check if shipping is required and if shipping is same as billing
        $needs_shipping = WC()->cart->needs_shipping();
        $ship_to_different_address = isset($data['ship_to_different_address']) && $data['ship_to_different_address'] == 1;


        if ($needs_shipping && $ship_to_different_address) {
            $required_fields = array_merge($required_fields, array(
                'shipping_first_name' => __('Shipping first name', 'yprint-plugin'),
                'shipping_last_name' => __('Shipping last name', 'yprint-plugin'),
                'shipping_address_1' => __('Shipping address', 'yprint-plugin'),
                'shipping_city' => __('Shipping city', 'yprint-plugin'),
                'shipping_postcode' => __('Shipping postcode / ZIP', 'yprint-plugin'),
                'shipping_country' => __('Shipping country', 'yprint-plugin'),
            ));
        }
        // Note: If needs_shipping is true but ship_to_different_address is false,
        // the shipping address fields are implicitly required and taken from billing.
        // The validation below will cover the billing fields which are used for shipping.


        // Validate required fields
        foreach ($required_fields as $key => $label) {
            // Check if the field is required AND if it's empty in the submitted data
            if (empty($data[$key])) {
                 // For state, check if country has states before requiring
                 if (in_array($key, array('billing_state', 'shipping_state'))) {
                      $country_key = str_replace('_state', '_country', $key);
                      $country_code = isset($data[$country_key]) ? $data[$country_key] : '';
                      $states_for_country = WC()->countries->get_states($country_code);
                      if (!empty($states_for_country)) {
                           // State is required for this country
                           error_log('YPrint Checkout Validation Error: ' . $label . ' is required for country ' . $country_code);
                           throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $label));
                      }
                 } else {
                     // Other required fields
                     error_log('YPrint Checkout Validation Error: ' . $label . ' is required.');
                     throw new Exception(sprintf(__('%s is a required field.', 'yprint-plugin'), $label));
                 }
            }
        }

        // Validate email format if email is provided
        if (!empty($data['billing_email']) && !is_email($data['billing_email'])) {
            error_log('YPrint Checkout Validation Error: Invalid email format for ' . $data['billing_email']);
            throw new Exception(__('Please enter a valid email address.', 'yprint-plugin'));
        }

        // Add any other custom validations here
    }

    /**
     * Create order
     *
     * @param array $data
     * @return int|WP_Error Order ID or WP_Error on failure
     */
    private function create_order($data) {
        // Start with a clean cart and recalculate
        WC()->cart->calculate_shipping();
        WC()->cart->calculate_totals();

        // Ensure cart is not empty before creating order
        if (WC()->cart->is_empty()) {
             error_log('YPrint Checkout Error: Cannot create order, cart is empty.');
             return new WP_Error('yprint_checkout_empty_cart', __('Cannot create order, cart is empty.', 'yprint-plugin'));
        }

        // Check if we need to create a new customer or use existing
        $customer_id = apply_filters('woocommerce_checkout_customer_id', get_current_user_id());
        $customer = $customer_id > 0 ? new WC_Customer($customer_id) : null;

        // Set up order data
        $order_data = array(
            'status' => apply_filters('woocommerce_default_order_status', 'pending'), // Default status
            'customer_id' => $customer_id,
            'customer_note' => isset($data['order_comments']) ? sanitize_textarea_field($data['order_comments']) : '',
            'cart_hash' => WC()->cart->get_cart_hash(),
            'created_via' => 'yprint_checkout', // Custom field to identify source
        );

        // Create the order object
        $order = wc_create_order($order_data);

        if (is_wp_error($order)) {
            error_log('YPrint Checkout Error: Failed to create order object. ' . $order->get_error_message());
            return $order; // Return WP_Error
        }

        // Set billing address
        $billing_address = array(
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

        $order->set_address($billing_address, 'billing');
         error_log('YPrint Checkout: Billing address set on order.');


        // Set shipping address
        $ship_to_different_address = isset($data['ship_to_different_address']) && $data['ship_to_different_address'] == 1;

        if (WC()->cart->needs_shipping() && $ship_to_different_address) {
             error_log('YPrint Checkout: Setting separate shipping address.');
            $shipping_address = array(
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
        } else {
             error_log('YPrint Checkout: Shipping address is same as billing or not required.');
            // If shipping not required or same as billing, use billing address for shipping
            $shipping_address = $billing_address;
        }

        $order->set_address($shipping_address, 'shipping');
         error_log('YPrint Checkout: Shipping address set on order.');


        // Add products from cart
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            // Get product
            $product = $cart_item['data'];
            $quantity = $cart_item['quantity'];
            // Variation ID and attributes are handled by add_product internally

            // Add item to order
            $item_id = $order->add_product(
                $product,
                $quantity,
                array(
                    'variation' => $cart_item['variation'], // Pass variation attributes
                    'subtotal' => $cart_item['line_subtotal'],
                    'subtotal_tax' => $cart_item['line_subtotal_tax'],
                    'total' => $cart_item['line_total'],
                    'tax' => $cart_item['line_tax'],
                    'tax_data' => $cart_item['line_tax_data'], // Pass tax data
                    'item_meta_data' => $cart_item['data']->get_meta_data(), // Pass product meta data
                )
            );

            if (is_wp_error($item_id)) {
                 error_log('YPrint Checkout Error: Failed to add product to order. ' . $item_id->get_error_message());
                 // Clean up the order if item addition fails
                 $order->delete(true); // Delete the order permanently
                 return $item_id; // Return WP_Error
            }
             error_log('YPrint Checkout: Added product ID ' . $product->get_id() . ' to order.');
        }

        // Add shipping cost as an item
        if (WC()->cart->needs_shipping()) {
            // Get chosen shipping method from session
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');

            if (!empty($chosen_shipping_methods) && isset($chosen_shipping_methods[0])) {
                $shipping_method_id = $chosen_shipping_methods[0];
                $shipping_rate = $this->get_shipping_rate($shipping_method_id);

                if ($shipping_rate && $shipping_rate instanceof WC_Shipping_Rate) {
                     error_log('YPrint Checkout: Adding shipping rate ' . $shipping_rate->id . ' to order.');
                    $item = new WC_Order_Item_Shipping();
                    $item->set_props(array(
                        'method_title' => $shipping_rate->label,
                        'method_id' => $shipping_rate->method_id,
                        'total' => wc_format_decimal($shipping_rate->cost),
                        'taxes' => $shipping_rate->taxes, // Use taxes from the rate object
                        // 'order_id' => $order->get_id(), // Set by add_item
                    ));

                    $order->add_item($item);
                     error_log('YPrint Checkout: Shipping item added.');
                } else {
                     error_log('YPrint Checkout Error: Chosen shipping rate ' . $shipping_method_id . ' not found or invalid.');
                     // Decide how to handle: fail order creation or proceed without shipping cost?
                     // For now, log error and proceed, recalculate_totals should handle missing shipping
                }
            } else {
                 error_log('YPrint Checkout Warning: Cart needs shipping but no chosen shipping method found in session.');
                 // This might happen if update_checkout wasn't called correctly or session was lost.
                 // Order will be created, but without a shipping item. Recalculate totals will reflect this.
            }
        } else {
             error_log('YPrint Checkout: Cart does not need shipping.');
        }


        // Add fees as items
        foreach (WC()->cart->get_fees() as $fee_key => $fee) {
             error_log('YPrint Checkout: Adding fee ' . $fee->name . ' to order.');
            $item = new WC_Order_Item_Fee();
            $item->set_props(array(
                'name' => $fee->name,
                'tax_class' => $fee->tax_class,
                'amount' => $fee->amount,
                'total' => $fee->total,
                'total_tax' => $fee->tax,
                'taxes' => array( // Format taxes correctly
                    'total' => $fee->tax_data,
                ),
                // 'order_id' => $order->get_id(), // Set by add_item
            ));

            $order->add_item($item);
             error_log('YPrint Checkout: Fee item added.');
        }

        // Add taxes as items
        // WC_Cart::get_tax_totals() returns formatted tax lines
        foreach (WC()->cart->get_tax_totals() as $code => $tax) {
             error_log('YPrint Checkout: Adding tax line ' . $code . ' to order.');
            $item = new WC_Order_Item_Tax();
            $item->set_props(array(
                'rate_code' => $code,
                'rate_id' => $tax->rate_id, // Use rate_id from tax total object
                'label' => $tax->label,
                'compound' => $tax->is_compound,
                'tax_total' => $tax->amount, // Amount for this tax line
                'shipping_tax_total' => $tax->shipping_tax_amount, // Shipping tax amount for this line
                // 'order_id' => $order->get_id(), // Set by add_item
            ));

            $order->add_item($item);
             error_log('YPrint Checkout: Tax item added.');
        }

        // Save order items and recalculate totals based on items
        // This is important to ensure order totals match the added items
        $order->calculate_totals();
         error_log('YPrint Checkout: Order totals recalculated based on items.');


        // Save order data (addresses, items, totals)
        $order->save();
         error_log('YPrint Checkout: Order saved.');


        // Return the order ID
        return $order->get_id();
    }

    /**
     * Get shipping rate object from available rates
     *
     * @param string $shipping_method_id The rate ID (e.g., flat_rate:1)
     * @return WC_Shipping_Rate|null
     */
    private function get_shipping_rate($shipping_method_id) {
        // Get calculated packages (requires calculate_shipping() to be called first)
        $packages = WC()->shipping->get_packages();

        if (!empty($packages)) {
            foreach ($packages as $package_key => $package) {
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $key => $rate) {
                        // Compare the rate ID
                        if ($rate->id === $shipping_method_id) {
                            return $rate; // Return the WC_Shipping_Rate object
                        }
                    }
                }
            }
        }

        error_log('YPrint Checkout Warning: Shipping rate ' . $shipping_method_id . ' not found in available rates.');
        return null; // Return null if rate is not found
    }
}

// Initialize the class
YPrint_Stripe_Checkout_Shortcode::get_instance();
