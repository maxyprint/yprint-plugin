<?php
/**
 * YPrint Stripe Checkout - Consolidated Class
 *
 * Handles the registration of the checkout shortcode, assets loading,
 * AJAX handlers, and Stripe integration for a custom checkout process.
 *
 * @package YPrint_Plugin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class YPrint_Stripe_Checkout {

    /**
     * Singleton instance
     *
     * @var YPrint_Stripe_Checkout
     */
    protected static $instance = null;

    /**
     * Get singleton instance
     *
     * @return YPrint_Stripe_Checkout
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Initialize the class
     */
    public static function init() {
        $instance = self::get_instance();

        // Register shortcode
        add_shortcode('yprint_checkout', array($instance, 'render_checkout_shortcode'));

        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($instance, 'enqueue_checkout_assets'));

        // Register AJAX handlers (entfernt: yprint_save_address - wird zentral verwaltet)
        // add_action('wp_ajax_yprint_save_address', array($instance, 'ajax_save_address')); // ENTFERNT
        // add_action('wp_ajax_nopriv_yprint_save_address', array($instance, 'ajax_save_address')); // ENTFERNT

        add_action('wp_ajax_yprint_set_payment_method', array($instance, 'ajax_set_payment_method'));
        add_action('wp_ajax_nopriv_yprint_set_payment_method', array($instance, 'ajax_set_payment_method'));

        add_action('wp_ajax_yprint_process_checkout', array($instance, 'ajax_process_checkout'));
        add_action('wp_ajax_nopriv_yprint_process_checkout', array($instance, 'ajax_process_checkout'));

        // Validation AJAX handlers
        add_action('wp_ajax_yprint_check_email_availability', array($instance, 'ajax_check_email_availability'));
        add_action('wp_ajax_nopriv_yprint_check_email_availability', array($instance, 'ajax_check_email_availability'));

        add_action('wp_ajax_yprint_validate_voucher', array($instance, 'ajax_validate_voucher'));
        add_action('wp_ajax_nopriv_yprint_validate_voucher', array($instance, 'ajax_validate_voucher'));

        // New AJAX handlers for real cart data
        add_action('wp_ajax_yprint_get_cart_data', array($instance, 'ajax_get_cart_data'));
        add_action('wp_ajax_nopriv_yprint_get_cart_data', array($instance, 'ajax_get_cart_data'));

        add_action('wp_ajax_yprint_refresh_cart_totals', array($instance, 'ajax_refresh_cart_totals'));
        add_action('wp_ajax_nopriv_yprint_refresh_cart_totals', array($instance, 'ajax_refresh_cart_totals'));

        add_action('wp_ajax_yprint_process_final_checkout', array($instance, 'ajax_process_final_checkout'));
        add_action('wp_ajax_nopriv_yprint_process_final_checkout', array($instance, 'ajax_process_final_checkout'));

        // Add Express Checkout AJAX handlers
        $instance->add_express_checkout_ajax_handlers();

        // Add Payment Method Processing AJAX handlers - Direct registration
        add_action('wp_ajax_yprint_process_payment_method', array($instance, 'ajax_process_payment_method'));
        add_action('wp_ajax_nopriv_yprint_process_payment_method', array($instance, 'ajax_process_payment_method'));

        // Add custom checkout endpoint
        add_action('init', array(__CLASS__, 'add_checkout_endpoints'));

        // Capture order details
        add_action('woocommerce_checkout_update_order_meta', array(__CLASS__, 'capture_order_details'));

        // New AJAX handlers for unified data management
        add_action('wp_ajax_yprint_get_checkout_context', array($instance, 'ajax_get_checkout_context'));
        add_action('wp_ajax_nopriv_yprint_get_checkout_context', array($instance, 'ajax_get_checkout_context'));

        add_action('wp_ajax_yprint_refresh_checkout_context', array($instance, 'ajax_refresh_checkout_context'));
        add_action('wp_ajax_nopriv_yprint_refresh_checkout_context', array($instance, 'ajax_refresh_checkout_context'));

        // Add handler for pending order data
        add_action('wp_ajax_yprint_get_pending_order', array($instance, 'ajax_get_pending_order'));
        add_action('wp_ajax_nopriv_yprint_get_pending_order', array($instance, 'ajax_get_pending_order'));
    }

    /**
 * AJAX handler to get unified checkout context
 */
public function ajax_get_checkout_context() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');
    
    $format = isset($_POST['format']) ? sanitize_text_field($_POST['format']) : 'full';
    
    $cart_data_manager = YPrint_Cart_Data::get_instance();
    $checkout_context = $cart_data_manager->get_checkout_context($format);
    
    wp_send_json_success($checkout_context);
}

/**
 * AJAX handler to refresh checkout context after changes
 */
public function ajax_refresh_checkout_context() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');
    
    // Clear cache to force fresh data
    $cart_data_manager = YPrint_Cart_Data::get_instance();
    $cart_data_manager->clear_cache();
    
    // Get fresh context
    $checkout_context = $cart_data_manager->get_checkout_context('full');
    
    wp_send_json_success(array(
        'context' => $checkout_context,
        'timestamp' => time(),
    ));
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
     * Constructor
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation
    }

/**
     * Check if all required files exist
     *
     * @return array Missing files
     */
    private function check_required_files() {
        $missing_files = array();
        
        $required_files = array(
            'CSS' => array(
                'yprint-checkout.css' => YPRINT_PLUGIN_DIR . 'assets/css/yprint-checkout.css',
                'yprint-checkout-confirmation.css' => YPRINT_PLUGIN_DIR . 'assets/css/yprint-checkout-confirmation.css',
            ),
            'JS' => array(
                'yprint-checkout.js' => YPRINT_PLUGIN_DIR . 'assets/js/yprint-checkout.js',
                'yprint-express-checkout.js' => YPRINT_PLUGIN_DIR . 'assets/js/yprint-express-checkout.js',
            ),
            'Templates' => array(
                'checkout-multistep.php' => YPRINT_PLUGIN_DIR . 'templates/checkout-multistep.php',
                'checkout-step-payment.php' => YPRINT_PLUGIN_DIR . 'templates/partials/checkout-step-payment.php',
            )
        );
        
        foreach ($required_files as $type => $files) {
            foreach ($files as $name => $path) {
                if (!file_exists($path)) {
                    $missing_files[$type][] = $name . ' (' . $path . ')';
                }
            }
        }
        
        return $missing_files;
    }

    /**
     * Enhanced debug function with file checks
     */
    private function get_debug_info() {
        // Only show debug info to admins
        if (!current_user_can('manage_options')) {
            return '';
        }

        $missing_files = $this->check_required_files();
        $stripe_enabled = $this->is_stripe_enabled();

        $debug = '<div style="background:#f8f8f8; border:1px solid #ddd; padding:15px; margin:15px 0; font-family:monospace; font-size:12px;">';
        $debug .= '<h3 style="margin-top:0;">YPrint Checkout Debug Info:</h3>';
        $debug .= '<div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">';
        
        // Left column - Basic info
        $debug .= '<div>';
        $debug .= '<h4>Basic Configuration:</h4>';
        $debug .= '<ul style="margin:0; padding-left:20px;">';
        $debug .= '<li>Plugin URL: ' . YPRINT_PLUGIN_URL . '</li>';
        $debug .= '<li>Plugin Dir: ' . YPRINT_PLUGIN_DIR . '</li>';
        $debug .= '<li>Plugin Version: ' . YPRINT_PLUGIN_VERSION . '</li>';
        $debug .= '<li>WooCommerce Active: ' . (class_exists('WooCommerce') ? 'Yes' : 'No') . '</li>';
        $debug .= '<li>Cart Empty: ' . (WC()->cart && WC()->cart->is_empty() ? 'Yes' : 'No') . '</li>';
        $debug .= '<li>Stripe Enabled: ' . ($stripe_enabled ? 'Yes' : 'No') . '</li>';
        $debug .= '</ul>';
        $debug .= '</div>';
        
        // Right column - File status
        $debug .= '<div>';
        $debug .= '<h4>File Status:</h4>';
        if (empty($missing_files)) {
            $debug .= '<p style="color:green;">✓ All required files found</p>';
        } else {
            $debug .= '<p style="color:red;">✗ Missing files:</p>';
            foreach ($missing_files as $type => $files) {
                $debug .= '<strong>' . $type . ':</strong><br>';
                foreach ($files as $file) {
                    $debug .= '<span style="color:red; font-size:10px;">- ' . $file . '</span><br>';
                }
            }
        }
        $debug .= '</div>';
        
        $debug .= '</div>';
        $debug .= '</div>';
        return $debug;
    }

    /**
     * Render checkout shortcode output
     *
     * @param array $atts Shortcode attributes
     * @return string The checkout HTML
     */
    public function render_checkout_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(array(
            'test_mode' => 'no',
            'debug' => 'no',
        ), $atts, 'yprint_checkout');

        // Set flag to force asset loading
        if (!defined('YPRINT_CHECKOUT_LOADING')) {
            define('YPRINT_CHECKOUT_LOADING', true);
        }

        // Ensure WooCommerce is active and cart is not empty
        if (!class_exists('WooCommerce') || WC()->cart->is_empty()) {
            return '<div class="yprint-checkout-error"><p>' . __('Ihr Warenkorb ist leer. <a href="' . wc_get_page_permalink('shop') . '">Weiter einkaufen</a>', 'yprint-plugin') . '</p></div>';
        }

        // Force load assets for this shortcode
        $this->enqueue_checkout_assets();

        // Prepare real cart data for templates
        $this->prepare_cart_data_for_templates();

        // Start output buffer
        ob_start();

        // Add debug info if requested and user is admin
        if ($atts['debug'] === 'yes' && current_user_can('manage_options')) {
            echo $this->get_debug_info();
        }

        // Add minimal inline styles for basic functionality in case external CSS fails
        echo '<style>
            .yprint-checkout-container {max-width: 1200px; margin: 0 auto; padding: 20px;}
            .form-input, .form-select {width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; margin-bottom: 10px;}
            .form-label {display: block; margin-bottom: 5px; font-weight: 500;}
            .checkout-step {display: none;}
            .checkout-step.active {display: block;}
        </style>';

        // Include the template
        $template_path = YPRINT_PLUGIN_DIR . 'templates/checkout-multistep.php';

        if (file_exists($template_path)) {
            include($template_path);
        } else {
            echo '<p>Checkout template not found at: ' . esc_html($template_path) . '</p>';
        }

        // Return the buffered content
        return ob_get_clean();
    }


/**
     * AJAX handler to get pending order data
     */
    public function ajax_get_pending_order() {
        check_ajax_referer('yprint_checkout_nonce', 'nonce');
        
        $pending_order = WC()->session->get('yprint_pending_order');
        
        if ($pending_order) {
            wp_send_json_success($pending_order);
        } else {
            wp_send_json_error(array('message' => 'No pending order found'));
        }
    }

    /**
     * Enqueue scripts and styles for checkout
     */
    public function enqueue_checkout_assets() {
        // Only load on pages with our shortcode OR when called directly
        global $post;
        $should_load_assets = false;
        
        // Check if we're on a page with the shortcode
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'yprint_checkout')) {
            $should_load_assets = true;
        }
        
        // Check if we're in admin or if this is called from render_checkout_shortcode
        if (is_admin() || (defined('DOING_AJAX') && DOING_AJAX) || !empty($_GET['yprint_checkout'])) {
            $should_load_assets = true;
        }
        
        // Force load if called from shortcode (add a flag)
        if (defined('YPRINT_CHECKOUT_LOADING') && YPRINT_CHECKOUT_LOADING) {
            $should_load_assets = true;
        }

        if ($should_load_assets) {
            // Checkout CSS - Priorität hoch setzen
            wp_enqueue_style(
                'yprint-checkout-style',
                YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css',
                array(),
                YPRINT_PLUGIN_VERSION,
                'all'
            );
            
            // Checkout Confirmation CSS
            wp_enqueue_style(
                'yprint-checkout-confirmation-style',
                YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout-confirmation.css',
                array('yprint-checkout-style'),
                YPRINT_PLUGIN_VERSION,
                'all'
            );

            // Add Tailwind CSS for base styling
            wp_enqueue_style(
                'tailwind-css',
                'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css',
                array(),
                '2.2.19'
            );

            // Font Awesome
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
                array(),
                '6.5.1'
            );

            // Stripe Service (muss zuerst geladen werden)
            wp_enqueue_script(
                'yprint-stripe-service',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-service.js',
                array(),
                YPRINT_PLUGIN_VERSION,
                true
            );

            // Checkout JS
            wp_enqueue_script(
                'yprint-checkout-js',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js',
                array('jquery', 'yprint-stripe-service'),
                YPRINT_PLUGIN_VERSION,
                true
            );

            // Checkout Validation JS
            wp_enqueue_script(
                'yprint-checkout-validation-js',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout-validation.js',
                array('jquery', 'yprint-checkout-js'),
                YPRINT_PLUGIN_VERSION,
                true
            );

            // Stripe JS (if needed) - Prüfe ob bereits geladen
            if ($this->is_stripe_enabled()) {
                // Prüfe ob Stripe.js bereits von anderem Plugin geladen wurde
                global $wp_scripts;
                $stripe_already_loaded = false;

                if (isset($wp_scripts->registered)) {
                    foreach ($wp_scripts->registered as $handle => $script) {
                        if (strpos($script->src, 'js.stripe.com') !== false) {
                            $stripe_already_loaded = true;
                            break;
                        }
                    }
                }

                if (!$stripe_already_loaded) {
                    wp_enqueue_script(
                        'stripe-js',
                        'https://js.stripe.com/v3/',
                        array(),
                        null,
                        true
                    );
                }

                wp_enqueue_script(
                    'yprint-stripe-checkout-js',
                    YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-checkout.js',
                    array('jquery', 'stripe-js'),
                    YPRINT_PLUGIN_VERSION,
                    true
                );

                // Express Checkout JS (für Wallet Payments)
                wp_enqueue_script(
                    'yprint-express-checkout-js',
                    YPRINT_PLUGIN_URL . 'assets/js/yprint-express-checkout.js',
                    array('jquery', 'stripe-js', 'yprint-stripe-service'),
                    YPRINT_PLUGIN_VERSION,
                    true
                );

                // Localize script with Stripe-specific data
                $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
                $testmode = isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'];
                $publishable_key = $testmode ?
                    (isset($stripe_settings['test_publishable_key']) ? $stripe_settings['test_publishable_key'] : '') :
                    (isset($stripe_settings['publishable_key']) ? $stripe_settings['publishable_key'] : '');

                wp_localize_script(
                    'yprint-stripe-checkout-js',
                    'yprint_stripe_vars',
                    array(
                        'publishable_key' => $publishable_key,
                        'is_test_mode' => $testmode ? 'yes' : 'no',
                        'processing_text' => __('Processing payment...', 'yprint-plugin'),
                        'card_error_text' => __('Card error: ', 'yprint-plugin'),
                    )
                );

                // Stripe Service Konfiguration
                wp_localize_script(
                    'yprint-stripe-service',
                    'yprint_stripe_ajax',
                    array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('yprint_stripe_service_nonce'),
                        'publishable_key' => $publishable_key,
                        'test_mode' => $testmode ? 'yes' : 'no',
                    )
                );

                

                // Express Checkout spezifische Daten mit zentraler Datenverwaltung
$cart_data_manager = YPrint_Cart_Data::get_instance();
$checkout_context = $cart_data_manager->get_checkout_context('full');
$express_payment_data = $checkout_context['express_payment'];

wp_localize_script(
    'yprint-express-checkout-js',
    'yprint_express_payment_params',
    array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yprint_express_checkout_nonce'),
        'stripe' => array(
            'publishable_key' => $publishable_key,
            'test_mode' => $this->is_testmode($stripe_settings),
            'locale' => $this->get_stripe_locale()
        ),
        'checkout' => array(
            'country' => $express_payment_data['country'],
            'currency' => $express_payment_data['currency'],
            'total_label' => $express_payment_data['total']['label'],
        ),
        'cart' => array(
            'total' => $express_payment_data['total']['amount'],
            'needs_shipping' => $express_payment_data['requestShipping'],
            'display_items' => $express_payment_data['displayItems']
        ),
        'settings' => array(
            'button_type' => isset($stripe_settings['payment_request_button_type']) ? $stripe_settings['payment_request_button_type'] : 'default',
            'button_theme' => isset($stripe_settings['payment_request_button_theme']) ? $stripe_settings['payment_request_button_theme'] : 'dark',
            'button_height' => isset($stripe_settings['payment_request_button_height']) ? $stripe_settings['payment_request_button_height'] : '48'
        ),
        'i18n' => array(
            'processing' => __('Zahlung wird verarbeitet...', 'yprint-plugin'),
            'error' => __('Zahlung fehlgeschlagen. Bitte versuchen Sie es erneut.', 'yprint-plugin'),
            'success' => __('Zahlung erfolgreich!', 'yprint-plugin'),
        )
    )
);
            }

            

            // Localize checkout script with common data
            wp_localize_script(
                'yprint-checkout-js',
                'yprint_checkout_params',
                array(
                    'ajax_url' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('yprint_checkout_nonce'),
                    'is_logged_in' => is_user_logged_in() ? 'yes' : 'no',
                    'current_step' => isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'address',
                    'validation_enabled' => true,
                    'cart_url' => wc_get_cart_url(),
                    'checkout_url' => wc_get_checkout_url(),
                    'i18n' => array(
                        'required_field' => __('Dieses Feld ist erforderlich.', 'yprint-plugin'),
                        'invalid_email' => __('Bitte geben Sie eine gültige E-Mail-Adresse ein.', 'yprint-plugin'),
                        'processing' => __('Wird verarbeitet...', 'yprint-plugin'),
                        'validation_error' => __('Bitte korrigieren Sie die markierten Felder.', 'yprint-plugin'),
                        'form_invalid' => __('Das Formular enthält Fehler. Bitte überprüfen Sie Ihre Eingaben.', 'yprint-plugin'),
                    )
                )
            );
        }
    }

    

    /**
     * Check if Stripe is enabled
     *
     * @return bool
     */
    private function is_stripe_enabled() {
        if (!class_exists('YPrint_Stripe_API')) {
            error_log('YPrint Debug: YPrint_Stripe_API class not found');
            return false;
        }

        try {
            $settings = YPrint_Stripe_API::get_stripe_settings();

            // Prüfe ob grundlegende Stripe-Einstellungen vorhanden sind
            if (empty($settings)) {
                error_log('YPrint Debug: No Stripe settings found');
                return false;
            }

            // Prüfe ob API-Schlüssel gesetzt sind
            $testmode = isset($settings['testmode']) && 'yes' === $settings['testmode'];
            if ($testmode) {
                $has_keys = !empty($settings['test_publishable_key']) && !empty($settings['test_secret_key']);
                error_log('YPrint Debug: Test mode - Keys available: ' . ($has_keys ? 'Yes' : 'No'));
            } else {
                $has_keys = !empty($settings['publishable_key']) && !empty($settings['secret_key']);
                error_log('YPrint Debug: Live mode - Keys available: ' . ($has_keys ? 'Yes' : 'No'));
            }

            // Stripe ist aktiviert wenn API-Schlüssel vorhanden sind
            $is_enabled = $has_keys;
            error_log('YPrint Debug: Stripe enabled: ' . ($is_enabled ? 'Yes' : 'No'));
            
            return $is_enabled;
        } catch (Exception $e) {
            error_log('YPrint Debug: Error checking Stripe status: ' . $e->getMessage());
            return false;
        }
    }

    

    /**
     * Make is_stripe_enabled public for debugging
     *
     * @return bool
     */
    public function is_stripe_enabled_public() {
        $enabled = $this->is_stripe_enabled();
        error_log('YPrint Debug: is_stripe_enabled_public called, returning: ' . ($enabled ? 'true' : 'false'));
        return $enabled;
    }

    /**
     * Determine if we're in test mode
     *
     * @param array $settings Stripe settings
     * @return bool
     */
    private function is_testmode($settings) {
        // Expliziter Testmode
        if (isset($settings['testmode']) && 'yes' === $settings['testmode']) {
            return true;
        }

        // Erkenne anhand der API-Schlüssel
        $live_key_set = !empty($settings['publishable_key']) && !empty($settings['secret_key']);
        $test_key_set = !empty($settings['test_publishable_key']) && !empty($settings['test_secret_key']);

        // Wenn nur Test-Schlüssel gesetzt sind, verwende Testmode
        if ($test_key_set && !$live_key_set) {
            return true;
        }

        // Standard: Live-Modus (da Live-Keys gesetzt sind)
        return false;
    }

    /**
     * Get Stripe locale
     *
     * @return string
     */
    private function get_stripe_locale() {
        $locale = get_locale();
        return substr($locale, 0, 2); // Get the first 2 characters (e.g., 'de' from 'de_DE')
    }

    /**
 * Get cart display items for Stripe Payment Request using central data
 *
 * @return array
 */
private function get_cart_display_items() {
    $cart_data_manager = YPrint_Cart_Data::get_instance();
    $express_payment_data = $cart_data_manager->get_checkout_context('express_payment');
    
    return $express_payment_data['displayItems'] ?? array();
}

    /**
     * Add AJAX handlers for Express Checkout
     */
    private function add_express_checkout_ajax_handlers() {
        add_action('wp_ajax_yprint_stripe_express_checkout_data', array($this, 'ajax_express_checkout_data'));
        add_action('wp_ajax_nopriv_yprint_stripe_express_checkout_data', array($this, 'ajax_express_checkout_data'));
        
        add_action('wp_ajax_yprint_stripe_process_express_payment', array($this, 'ajax_process_express_payment'));
        add_action('wp_ajax_nopriv_yprint_stripe_process_express_payment', array($this, 'ajax_process_express_payment'));
        
        add_action('wp_ajax_yprint_stripe_update_express_shipping', array($this, 'ajax_update_express_shipping'));
        add_action('wp_ajax_nopriv_yprint_stripe_update_express_shipping', array($this, 'ajax_update_express_shipping'));
    }

    /**
 * AJAX handler for Express Checkout data using central data manager
 */
public function ajax_express_checkout_data() {
    check_ajax_referer('yprint_express_checkout_nonce', 'nonce');
    
    $cart_data_manager = YPrint_Cart_Data::get_instance();
    $express_payment_data = $cart_data_manager->get_checkout_context('express_payment');
    
    if (empty($express_payment_data)) {
        wp_send_json_error(array('message' => __('Warenkorb ist leer', 'yprint-plugin')));
        return;
    }

    wp_send_json_success($express_payment_data);
}

    /**
     * AJAX handler for Express Payment processing
     */
    public function ajax_process_express_payment() {
        check_ajax_referer('yprint_express_checkout_nonce', 'nonce');
        
        // Placeholder für Express Payment Verarbeitung
        // Hier würde die tatsächliche Stripe Payment Intent Verarbeitung stattfinden
        
        wp_send_json_success(array(
            'message' => __('Express Payment erfolgreich verarbeitet', 'yprint-plugin'),
            'redirect' => wc_get_checkout_url()
        ));
    }

    /**
     * AJAX handler for Express Shipping updates
     */
    public function ajax_update_express_shipping() {
        check_ajax_referer('yprint_express_checkout_nonce', 'nonce');
        
        // Placeholder für Versandkostenberechnung bei Adressänderung
        
        wp_send_json_success(array(
            'shippingOptions' => array(
                array(
                    'id' => 'standard',
                    'label' => __('Standard Versand', 'yprint-plugin'),
                    'detail' => __('3-5 Werktage', 'yprint-plugin'),
                    'amount' => 499, // 4,99 EUR in Cent
                )
            )
        ));
    }

    /**
     * Render express payment buttons (Apple Pay, Google Pay)
     *
     * @return string HTML for express payment buttons
     */
    public function render_express_payment_buttons() {
        // Prüfe Stripe-Aktivierung
        if (!$this->is_stripe_enabled()) {
            return ''; // Oder eine Meldung, dass Stripe nicht aktiviert ist
        }

        $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
        
        // Prüfe ob Express Payments aktiviert sind
        if (!isset($stripe_settings['payment_request']) || 'yes' !== $stripe_settings['payment_request']) {
            return '';
        }

        // Prüfe SSL für Live-Modus
        $testmode = isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'];
        if (!$testmode && !is_ssl()) {
            return '';
        }

        ob_start();
        ?>
        <div class="express-payment-section" style="margin: 20px 0;">
            <div class="express-payment-title" style="text-align: center; margin-bottom: 15px;">
                <span style="font-size: 14px; color: #666; background: #f8f8f8; padding: 8px 15px; border-radius: 20px;">
                    <i class="fas fa-bolt mr-2"></i><?php esc_html_e('Express-Zahlung', 'yprint-checkout'); ?>
                </span>
            </div>
            
            <div id="yprint-express-payment-container" style="display: none;">
                <div id="yprint-payment-request-button" style="margin-bottom: 15px;">
                    <!-- Stripe Elements wird hier eingefügt -->
                </div>
                
                <div class="express-payment-separator" style="text-align: center; margin: 20px 0; position: relative;">
                    <span style="background: white; padding: 0 15px; color: #999; font-size: 14px; position: relative; z-index: 2;">
                        <?php esc_html_e('oder', 'yprint-checkout'); ?>
                    </span>
                    <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #e5e5e5; z-index: 1;"></div>
                </div>
            </div>
            
            <div class="express-payment-loading" style="text-align: center; padding: 20px; display: block;">
                <i class="fas fa-spinner fa-spin text-blue-500"></i>
                <span style="margin-left: 8px; color: #666; font-size: 14px;">
                    <?php esc_html_e('Express-Zahlungsmethoden werden geladen...', 'yprint-checkout'); ?>
                </span>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
 * Prepare cart data for templates using central data manager
 */
private function prepare_cart_data_for_templates() {
    $cart_data_manager = YPrint_Cart_Data::get_instance();
    $checkout_context = $cart_data_manager->get_checkout_context('full');

    // Store in session for consistency with existing code
    WC()->session->set('yprint_checkout_cart_data', $checkout_context['cart_items']);
    WC()->session->set('yprint_checkout_cart_totals', $checkout_context['cart_totals']);
    
    // Also store complete context for advanced usage
    WC()->session->set('yprint_checkout_context', $checkout_context);
}

    /**
     * AJAX handler to save address information
     */
    // METHODE ENTFERNT - wird zentral von YPrint_Address_Handler verwaltet
    // ajax_save_address() wurde nach YPrint_Address_Handler::handle_checkout_context() migriert

    /**
     * AJAX handler to set payment method
     */
    public function ajax_set_payment_method() {
        check_ajax_referer('yprint_checkout_nonce', 'security');

        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        $response = array('success' => false, 'message' => __('Fehler beim Setzen der Zahlungsart.', 'yprint-plugin'));

        if (!empty($payment_method)) {
            WC()->session->set('yprint_checkout_payment_method', $payment_method);

            // Store chosen saved payment method ID if it exists
            if (isset($_POST['chosen_payment_saved_id']) && !empty($_POST['chosen_payment_saved_id'])) {
                WC()->session->set('chosen_payment_saved_id', sanitize_text_field($_POST['chosen_payment_saved_id']));
            }

            $response['success'] = true;
            $response['message'] = __('Zahlungsart erfolgreich gesetzt.', 'yprint-plugin');
        }

        wp_send_json($response);
    }

    /**
     * AJAX handler to process the checkout and create the order
     */
    public function ajax_process_checkout() {
        check_ajax_referer('yprint_checkout_nonce', 'security');

        $response = array('success' => false, 'message' => __('Fehler beim Verarbeiten des Checkouts.', 'yprint-plugin'));

        try {
            // Retrieve data from session
            $address = WC()->session->get('yprint_checkout_address');
            $payment_method = WC()->session->get('yprint_checkout_payment_method');

            // Validate data (add more validation as needed)
            if (empty($address) || empty($payment_method)) {
                throw new Exception(__('Daten für die Bestellung fehlen.', 'yprint-plugin'));
            }

            // Create the order
            $order_id = $this->create_order($address, $payment_method);

            if (is_wp_error($order_id)) {
                throw new Exception($order_id->get_error_message());
            }

            // Clear cart
            WC()->cart->empty_cart();

            $response['success'] = true;
            $response['message'] = __('Bestellung erfolgreich erstellt.', 'yprint-plugin');
            $response['order_id'] = $order_id;

        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        wp_send_json($response);
    }

    /**
     * AJAX handler to process the final checkout and payment via Stripe
     */
    public function ajax_process_final_checkout() {
        check_ajax_referer('yprint_checkout_nonce', 'security');

        $response = array('success' => false, 'message' => __('Fehler beim Verarbeiten der Zahlung.', 'yprint-plugin'));

        try {
            // Retrieve data from session
            $address = WC()->session->get('yprint_checkout_address');
            $payment_method = WC()->session->get('yprint_checkout_payment_method');
            $terms_accepted = isset($_POST['terms_accepted']) ? wc_string_to_bool($_POST['terms_accepted']) : false;

            // Validate data (add more validation as needed)
            if (empty($address) || empty($payment_method)) {
                throw new Exception(__('Daten für die Bestellung fehlen.', 'yprint-plugin'));
            }

            if (!$terms_accepted) {
                throw new Exception(__('Bitte akzeptieren Sie die AGB.', 'yprint-plugin'));
            }

            // Create the order
            $order_id = $this->create_order($address, $payment_method);

            if (is_wp_error($order_id)) {
                throw new Exception($order_id->get_error_message());
            }

            // Store terms acceptance and other details for later
            WC()->session->set('yprint_checkout_data', array(
                'terms_accepted' => $terms_accepted
            ));

            // Handle Stripe payment
            if ($payment_method === 'stripe') {
                $payment_intent_id = isset($_POST['payment_intent_id']) ? sanitize_text_field($_POST['payment_intent_id']) : '';

                if (empty($payment_intent_id)) {
                    throw new Exception(__('Zahlungsdaten fehlen.', 'yprint-plugin'));
                }

                $order = wc_get_order($order_id);
                if (!$order) {
                    throw new Exception(__('Bestellung nicht gefunden.', 'yprint-plugin'));
                }

                $stripe_result = YPrint_Stripe_API::process_payment($order, $payment_intent_id);

                if (is_wp_error($stripe_result)) {
                    throw new Exception($stripe_result->get_error_message());
                }

                // Payment successful
                $order->payment_complete($stripe_result->id);
                $order->add_order_note(__('Zahlung erfolgreich via Stripe.', 'yprint-plugin'));
                WC()->session->set('stripe_payment_intent_id', $stripe_result->id); // Für Express Checkout
            } else {
                // For other payment methods, just complete the order
                $order->payment_complete();
                $order->add_order_note(__('Zahlung ausstehend.', 'yprint-plugin'));
            }

            // Clear cart
            WC()->cart->empty_cart();

            $response['success'] = true;
            $response['message'] = __('Bestellung erfolgreich abgeschlossen.', 'yprint-plugin');
            $response['order_id'] = $order_id;
        } catch (Exception $e) {
            $response['message'] = $e->getMessage();
        }

        wp_send_json($response);
    }

    /**
     * AJAX handler to check email availability
     */
    public function ajax_check_email_availability() {
        check_ajax_referer('yprint_checkout_nonce', 'security');

        $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
        $response = array('available' => true);

        if (!empty($email) && email_exists($email)) {
            $response['available'] = false;
        }

        wp_send_json($response);
    }

    /**
 * AJAX handler to validate a voucher code - delegated to Cart Data Manager
 */
public function ajax_validate_voucher() {
    check_ajax_referer('yprint_checkout_nonce', 'security');

    $voucher_code = isset($_POST['voucher_code']) ? sanitize_text_field($_POST['voucher_code']) : '';
    
    $cart_manager = YPrint_Cart_Data::get_instance();
    $result = $cart_manager->apply_coupon($voucher_code);
    
    wp_send_json($result);
}

    /**
 * AJAX handler to get real cart data using central data manager with performance optimization
 */
public function ajax_get_cart_data() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');

    $minimal = isset($_POST['minimal']) && $_POST['minimal'] === '1';
    
    $cart_data_manager = YPrint_Cart_Data::get_instance();
    
    if ($minimal) {
        // Minimale Daten für bessere Performance auf nicht-kritischen Seiten
        $cart_count = WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
        $cart_total = WC()->cart ? WC()->cart->get_total('edit') : 0;
        
        wp_send_json_success(array(
            'items' => array(), // Leeres Array für minimale Last
            'totals' => array(
                'total' => (float) $cart_total,
                'count' => $cart_count
            ),
            'minimal' => true
        ));
    } else {
        // Vollständiger Kontext nur wenn wirklich benötigt
        $checkout_context = $cart_data_manager->get_checkout_context('full');

        wp_send_json_success(array(
            'items' => $checkout_context['cart_items'],
            'totals' => $checkout_context['cart_totals'],
            'context' => $checkout_context,
            'minimal' => false
        ));
    }
}

    /**
     * AJAX handler to refresh cart totals
     */
    public function ajax_refresh_cart_totals() {
        check_ajax_referer('yprint_checkout_nonce', 'nonce');

        WC()->cart->calculate_totals();
        $cart_totals = array(
            'subtotal' => (float) WC()->cart->get_subtotal(),
            'shipping' => (float) WC()->cart->get_shipping_total(),
            'discount' => (float) WC()->cart->get_discount_total(),
            'vat' => (float) WC()->cart->get_total_tax(),
            'total' => (float) WC()->cart->get_total('edit'),
        );

        wp_send_json_success(array('totals' => $cart_totals));
    }

    /**
     * Create the order programmatically
     *
     * @param array $address Address data
     * @param string $payment_method Payment method
     * @return int|\WP_Error Order ID or WP_Error object
     */
    private function create_order($address, $payment_method) {
        // Start transaction if available
        if (function_exists('wc_transaction_begin')) {
            wc_transaction_begin();
        }

        $order = wc_create_order();

        if (is_wp_error($order)) {
            if (function_exists('wc_transaction_rollback')) {
                wc_transaction_rollback();
            }
            return $order;
        }

        // Set address information
        $order->set_address($address, 'billing');
        if (isset($address['ship_to_different_address']) && $address['ship_to_different_address'] === '1') {
            $order->set_address($address, 'shipping');
        } else {
            $order->set_address($address, 'billing');
        }

        // Add cart items
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $order->add_item($cart_item['data'], $cart_item['quantity']);
            add_item($cart_item['data'], $cart_item['quantity']);
        }

        // Set payment method
        $order->set_payment_method($payment_method);

        // Set customer details
        if (isset($address['billing_email'])) {
            $order->set_customer_id(apply_filters('woocommerce_checkout_customer_id', get_current_user_id()));
            $order->set_billing_email($address['billing_email']);
        }
        if (isset($address['billing_phone'])) {
            $order->set_billing_phone($address['billing_phone']);
        }

        // Set shipping method (you might need to adjust this based on your shipping setup)
        $available_shipping_methods = $order->get_available_shipping_methods();
        if (!empty($available_shipping_methods)) {
            $first_method = reset($available_shipping_methods);
            $order->set_shipping_method($first_method->id); // Or logic to choose a method
        }

        // Calculate totals
        $order->calculate_totals();

        // Return the order ID
        return $order->get_id();
    }

/**
     * Sendet Bestätigungsmail nach erfolgreichem Checkout
     *
     * @param WC_Order $order Das Bestellobjekt
     */
    private function send_confirmation_email_if_needed($order) {
        error_log('=== YPRINT CHECKOUT DEBUG: E-Mail-Trigger gestartet ===');
        
        if (!$order || !is_a($order, 'WC_Order')) {
            error_log('YPrint CHECKOUT DEBUG: FEHLER - Ungültiges Order-Objekt empfangen');
            error_log('YPrint CHECKOUT DEBUG: Order Type: ' . gettype($order));
            error_log('YPrint CHECKOUT DEBUG: Is WC_Order: ' . (is_a($order, 'WC_Order') ? 'JA' : 'NEIN'));
            return;
        }

        error_log('YPrint CHECKOUT DEBUG: Gültiges Order-Objekt empfangen');
        error_log('YPrint CHECKOUT DEBUG: Bestellnummer: ' . $order->get_order_number());
        error_log('YPrint CHECKOUT DEBUG: Bestell-ID: ' . $order->get_id());

        // Prüfe ob E-Mail bereits gesendet wurde
        $email_sent = $order->get_meta('_yprint_confirmation_email_sent');
        error_log('YPrint CHECKOUT DEBUG: E-Mail bereits gesendet Meta: "' . $email_sent . '"');
        
        if ($email_sent === 'yes') {
            error_log('YPrint CHECKOUT DEBUG: ÜBERSPRUNGEN - Bestätigungsmail bereits gesendet für Bestellung ' . $order->get_order_number());
            return;
        }

        // Prüfe ob Bestellung bezahlt ist
        $is_paid = $order->is_paid();
        error_log('YPrint CHECKOUT DEBUG: Ist Bestellung bezahlt: ' . ($is_paid ? 'JA' : 'NEIN'));
        error_log('YPrint CHECKOUT DEBUG: Bestellstatus: ' . $order->get_status());
        error_log('YPrint CHECKOUT DEBUG: Zahlungsmethode: ' . $order->get_payment_method());
        error_log('YPrint CHECKOUT DEBUG: Transaktions-ID: ' . ($order->get_transaction_id() ?: 'KEINE'));
        
        if (!$is_paid) {
            error_log('YPrint CHECKOUT DEBUG: ÜBERSPRUNGEN - Bestellung ' . $order->get_order_number() . ' ist noch nicht bezahlt - keine E-Mail');
            return;
        }

        // Prüfe E-Mail-Funktion Verfügbarkeit
        $function_exists = function_exists('yprint_send_order_confirmation_email');
        error_log('YPrint CHECKOUT DEBUG: E-Mail-Funktion verfügbar: ' . ($function_exists ? 'JA' : 'NEIN'));
        
        if (!$function_exists) {
            error_log('YPrint CHECKOUT DEBUG: FEHLER - E-Mail-Funktion nicht verfügbar');
            error_log('YPrint CHECKOUT DEBUG: Definierte Funktionen: ' . print_r(get_defined_functions()['user'], true));
            return;
        }

        error_log('YPrint CHECKOUT DEBUG: Rufe yprint_send_order_confirmation_email() auf...');
        
        // E-Mail-Funktion aufrufen
        $email_result = yprint_send_order_confirmation_email($order);
        
        error_log('YPrint CHECKOUT DEBUG: E-Mail-Funktion Ergebnis: ' . ($email_result ? 'ERFOLGREICH' : 'FEHLGESCHLAGEN'));
        error_log('=== YPRINT CHECKOUT DEBUG: E-Mail-Trigger beendet ===');
    }

    /**
     * AJAX handler for processing payment methods (DEBUG VERSION)
     */

 public function ajax_process_payment_method() {
    // Force WooCommerce initialization if not already done
    if (!did_action('woocommerce_loaded')) {
        // Include WooCommerce if not loaded
        if (!class_exists('WooCommerce') && file_exists(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php')) {
            include_once(WP_PLUGIN_DIR . '/woocommerce/woocommerce.php');
            
            // Initialize WooCommerce manually
            if (class_exists('WooCommerce')) {
                WooCommerce::instance();
            }
        }
    }
    
    // Ensure WC() function is available after initialization
    if (!function_exists('WC')) {
        error_log('ERROR: WC() function not available after initialization attempt');
        wp_send_json_error(array('message' => 'WooCommerce not available'));
        return;
    }
    
    // Initialize WooCommerce core components if needed
    if (!WC()->session) {
        WC()->init();
    }

    // Debug WooCommerce availability
error_log('=== WOOCOMMERCE AVAILABILITY CHECK ===');
error_log('WooCommerce class exists: ' . (class_exists('WooCommerce') ? 'YES' : 'NO'));
error_log('WC function exists: ' . (function_exists('WC') ? 'YES' : 'NO'));
error_log('WooCommerce version: ' . (defined('WC_VERSION') ? WC_VERSION : 'Not defined'));
error_log('WooCommerce active: ' . (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) ? 'YES' : 'NO'));
error_log('WooCommerce init action fired: ' . (did_action('woocommerce_init') ? 'YES' : 'NO'));

// Try to access WC()
try {
    $wc_instance = WC();
    error_log('WC() instance available: YES');
    error_log('WC() instance type: ' . get_class($wc_instance));
} catch (Exception $e) {
    error_log('WC() instance error: ' . $e->getMessage());
    wp_send_json_error(array('message' => 'WooCommerce instance not accessible: ' . $e->getMessage()));
    return;
}
    
    // Load WooCommerce frontend
    WC()->frontend_includes();
    
    // Initialize session
    if (is_null(WC()->session)) {
        $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
        WC()->session = new $session_class();
        WC()->session->init();
    }
    
    // Initialize cart
    if (is_null(WC()->cart)) {
        WC()->cart = new WC_Cart();
        WC()->cart->get_cart();
    }
    
    // Initialize customer
    if (is_null(WC()->customer)) {
        WC()->customer = new WC_Customer(get_current_user_id(), true);
    }
    
    error_log('=== RAW REQUEST DEBUGGING ===');
        error_log('=== RAW REQUEST DEBUGGING ===');
        error_log('Raw POST data: ' . file_get_contents('php://input'));
        error_log('Content Length: ' . ($_SERVER['CONTENT_LENGTH'] ?? 'Not set'));
        error_log('Max Post Size: ' . ini_get('post_max_size'));
        error_log('Max Input Vars: ' . ini_get('max_input_vars'));
        error_log('POST array size: ' . count($_POST));
        
        error_log('=== YPRINT PAYMENT METHOD PROCESSING START ===');
    error_log('=== YPRINT PAYMENT METHOD PROCESSING START ===');
    error_log('POST Data: ' . print_r($_POST, true));
    error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
    error_log('Content Type: ' . ($_SERVER['CONTENT_TYPE'] ?? 'Not set'));
    error_log('User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set'));
    
    // Nonce Verification
    if (!isset($_POST['nonce'])) {
        error_log('ERROR: Nonce not provided in request');
        wp_send_json_error(array('message' => 'Nonce missing'));
        return;
    }
    
    $nonce_valid = wp_verify_nonce($_POST['nonce'], 'yprint_stripe_service_nonce');
    error_log('Nonce Verification: ' . ($nonce_valid ? 'VALID' : 'INVALID'));
    error_log('Provided Nonce: ' . $_POST['nonce']);
    error_log('Expected Nonce Action: yprint_stripe_service_nonce');
    
    if (!$nonce_valid) {
        error_log('ERROR: Nonce verification failed');
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }
    
    // Payment Method Data - Fix URL encoding issues
$payment_method_json = isset($_POST['payment_method']) ? wp_unslash($_POST['payment_method']) : '';
error_log('=== PAYMENT METHOD DATA DEBUGGING ===');
error_log('Raw payment_method from POST: ' . var_export($_POST['payment_method'] ?? 'NOT_SET', true));
error_log('After wp_unslash: ' . var_export($payment_method_json, true));
error_log('Payment Method JSON length: ' . strlen($payment_method_json));
error_log('Payment Method JSON first 200 chars: ' . substr($payment_method_json, 0, 200));

// Try different decoding approaches
if (empty($payment_method_json)) {
    error_log('ERROR: Payment method data is empty');
    error_log('Available POST keys: ' . print_r(array_keys($_POST), true));
    wp_send_json_error(array('message' => 'Payment method data missing'));
    return;
}

// Method 1: Direct decode
$payment_method = json_decode($payment_method_json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log('Method 1 failed - JSON error: ' . json_last_error_msg());
    
    // Method 2: URL decode first
    $decoded_json = urldecode($payment_method_json);
    error_log('URL decoded JSON: ' . substr($decoded_json, 0, 200));
    $payment_method = json_decode($decoded_json, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Method 2 failed - JSON error after URL decode: ' . json_last_error_msg());
        
        // Method 3: Strip slashes then decode
        $stripped_json = stripslashes($payment_method_json);
        error_log('Stripped slashes JSON: ' . substr($stripped_json, 0, 200));
        $payment_method = json_decode($stripped_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Method 3 failed - JSON error after stripslashes: ' . json_last_error_msg());
            error_log('Original data sample: ' . substr($payment_method_json, 0, 500));
            wp_send_json_error(array('message' => 'Invalid payment method data - JSON decode failed: ' . json_last_error_msg()));
            return;
        } else {
            error_log('Method 3 SUCCESS - stripslashes worked');
        }
    } else {
        error_log('Method 2 SUCCESS - URL decode worked');
    }
} else {
    error_log('Method 1 SUCCESS - direct decode worked');
}

error_log('Final decoded Payment Method: ' . print_r($payment_method, true));
error_log('=== PAYMENT METHOD DATA DEBUGGING ===');
error_log('Payment Method JSON received: ' . var_export($payment_method_json, true));
error_log('Payment Method JSON length: ' . strlen($payment_method_json));
error_log('Payment Method JSON is string: ' . (is_string($payment_method_json) ? 'YES' : 'NO'));
error_log('Payment Method JSON first 200 chars: ' . substr($payment_method_json, 0, 200));

if (empty($payment_method_json)) {
    error_log('ERROR: Payment method data is empty');
    error_log('Available POST keys: ' . print_r(array_keys($_POST), true));
    wp_send_json_error(array('message' => 'Payment method data missing'));
    return;
}

// Check if it's already an array (sometimes WordPress auto-decodes)
if (is_array($payment_method_json)) {
    error_log('Payment method data is already an array (WordPress auto-decoded)');
    $payment_method = $payment_method_json;
} else {
    // Try to decode JSON
    $payment_method = json_decode($payment_method_json, true);
    error_log('JSON decode attempted');
    error_log('JSON last error: ' . json_last_error_msg());
    error_log('JSON decode result type: ' . gettype($payment_method));
}

error_log('Decoded Payment Method: ' . print_r($payment_method, true));

// Enhanced validation
if (json_last_error() !== JSON_ERROR_NONE && !is_array($payment_method)) {
    error_log('ERROR: JSON decode error: ' . json_last_error_msg());
    error_log('Original data type: ' . gettype($payment_method_json));
    error_log('Original data sample: ' . substr($payment_method_json, 0, 500));
    wp_send_json_error(array('message' => 'Invalid payment method data - JSON decode failed: ' . json_last_error_msg()));
    return;
}

// Validate payment method structure
if (!is_array($payment_method)) {
    error_log('ERROR: Payment method is not an array after processing');
    error_log('Payment method type: ' . gettype($payment_method));
    error_log('Payment method value: ' . var_export($payment_method, true));
    wp_send_json_error(array('message' => 'Invalid payment method data - not an array'));
    return;
}

if (!isset($payment_method['id'])) {
    error_log('ERROR: Payment method ID missing');
    error_log('Available payment method keys: ' . print_r(array_keys($payment_method), true));
    wp_send_json_error(array('message' => 'Invalid payment method data - ID missing'));
    return;
}

error_log('Payment method validation PASSED');
error_log('Payment Method ID: ' . $payment_method['id']);
error_log('Payment Method Type: ' . ($payment_method['type'] ?? 'TYPE_MISSING'));
    
    // Shipping Address Data - Apply same fix
$shipping_address_json = isset($_POST['shipping_address']) ? wp_unslash($_POST['shipping_address']) : '';
error_log('Shipping Address JSON: ' . $shipping_address_json);

$shipping_address = null;
if (!empty($shipping_address_json)) {
    $shipping_address = json_decode($shipping_address_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // Try alternative decoding methods
        $shipping_address = json_decode(stripslashes($shipping_address_json), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WARNING: Shipping address JSON decode error: ' . json_last_error_msg());
        }
    }
    error_log('Decoded Shipping Address: ' . print_r($shipping_address, true));
}
    error_log('Shipping Address JSON: ' . $shipping_address_json);
    
    $shipping_address = null;
    if (!empty($shipping_address_json)) {
        $shipping_address = json_decode($shipping_address_json, true);
        error_log('Decoded Shipping Address: ' . print_r($shipping_address, true));
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('WARNING: Shipping address JSON decode error: ' . json_last_error_msg());
        }
    }
    
    // Define WooCommerce cart constant
if (!defined('WOOCOMMERCE_CART')) {
    define('WOOCOMMERCE_CART', true);
}

// Ensure all WooCommerce components are initialized
try {
    // Initialize session if needed
    if (is_null(WC()->session)) {
        $session_class = apply_filters('woocommerce_session_handler', 'WC_Session_Handler');
        if (class_exists($session_class)) {
            WC()->session = new $session_class();
            WC()->session->init();
        }
    }
    
    // Initialize customer if needed
    if (is_null(WC()->customer)) {
        WC()->customer = new WC_Customer(get_current_user_id(), true);
    }
    
    // Initialize cart if needed
    if (is_null(WC()->cart)) {
        WC()->cart = new WC_Cart();
        // Load cart from session
        WC()->cart->get_cart();
    }
    
    error_log('WooCommerce components initialized successfully');
} catch (Exception $e) {
    error_log('ERROR initializing WooCommerce components: ' . $e->getMessage());
    wp_send_json_error(array('message' => 'Failed to initialize WooCommerce: ' . $e->getMessage()));
    return;
}

// User and Cart Verification
error_log('Current User ID: ' . get_current_user_id());
error_log('Is User Logged In: ' . (is_user_logged_in() ? 'Yes' : 'No'));
error_log('WC Session Available: ' . (WC()->session ? 'Yes' : 'No'));
error_log('WC Cart Available: ' . (WC()->cart ? 'Yes' : 'No'));

if (WC()->cart) {
    error_log('Cart Items Count: ' . WC()->cart->get_cart_contents_count());
    error_log('Cart Total: ' . WC()->cart->get_total('edit'));
    error_log('Cart Is Empty: ' . (WC()->cart->is_empty() ? 'Yes' : 'No'));
} else {
    error_log('ERROR: Cart could not be initialized');
    wp_send_json_error(array('message' => 'Cart initialization failed'));
    return;
}
    

// Cart Fallback - create minimal order from session data
if (WC()->cart->is_empty()) {
    error_log('Cart is empty - checking for session cart data');
    
    // Try to restore cart from session
    $session_cart = WC()->session->get('cart', null);
    if ($session_cart) {
        error_log('Found session cart data: ' . print_r($session_cart, true));
        WC()->cart->set_session(WC()->session);
        WC()->cart->get_cart_from_session();
    } else {
        error_log('No session cart data found');
        
        // For Apple Pay, we can proceed without cart validation
        // as the payment details contain all necessary information
        if (isset($_POST['source']) && $_POST['source'] === 'express_checkout') {
            error_log('Express checkout detected - proceeding without cart validation');
        } else {
            wp_send_json_error(array('message' => 'Cart is empty and cannot be restored'));
            return;
        }
    }
}

    // Stripe API Check
    if (!class_exists('YPrint_Stripe_API')) {
        error_log('ERROR: YPrint_Stripe_API class not found');
        wp_send_json_error(array('message' => 'Stripe API not available'));
        return;
    }
    
    $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
    error_log('Stripe Settings Available: ' . (empty($stripe_settings) ? 'No' : 'Yes'));
    error_log('Test Mode: ' . (isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'] ? 'Yes' : 'No'));
    
    // Here you would continue with actual payment processing
    // For now, let's just return success to test the flow
    error_log('=== SIMULATED PAYMENT PROCESSING ===');
    error_log('Payment Method ID: ' . ($payment_method['id'] ?? 'Not found'));
    error_log('Payment Method Type: ' . ($payment_method['type'] ?? 'Not found'));
    
    // Simulate payment processing (since we're in test mode)
    try {
        // Create a mock order for testing
        $order_data = array(
            'payment_method_id' => $payment_method['id'],
            'amount' => WC()->cart->get_total('edit'),
            'currency' => get_woocommerce_currency(),
            'customer_details' => array(
                'name' => $payment_method['billing_details']['name'] ?? '',
                'email' => $payment_method['billing_details']['email'] ?? '',
                'phone' => $payment_method['billing_details']['phone'] ?? '',
            ),
            'billing_address' => $payment_method['billing_details']['address'] ?? array(),
            'shipping_address' => $shipping_address ?? array(),
        );
        
        // Store order data in session for confirmation page
        WC()->session->set('yprint_pending_order', $order_data);
        
        error_log('Payment simulation successful for payment method: ' . $payment_method['id']);
        
        // Prüfe ob eine echte Bestellung erstellt wurde und sende E-Mail
        error_log('=== YPRINT PAYMENT DEBUG: Prüfe pending order für E-Mail-Versendung ===');
        
        $pending_order = WC()->session->get('yprint_pending_order');
        error_log('YPrint PAYMENT DEBUG: Pending Order gefunden: ' . ($pending_order ? 'JA' : 'NEIN'));
        
        if ($pending_order) {
            error_log('YPrint PAYMENT DEBUG: Pending Order Daten: ' . print_r($pending_order, true));
            
            if (isset($pending_order['order_id'])) {
                error_log('YPrint PAYMENT DEBUG: Order ID in pending order: ' . $pending_order['order_id']);
                
                $order = wc_get_order($pending_order['order_id']);
                error_log('YPrint PAYMENT DEBUG: WC_Order geladen: ' . ($order ? 'JA' : 'NEIN'));
                
                if ($order) {
                    error_log('YPrint PAYMENT DEBUG: Order ist bezahlt: ' . ($order->is_paid() ? 'JA' : 'NEIN'));
                    error_log('YPrint PAYMENT DEBUG: Order Status: ' . $order->get_status());
                    
                    if ($order->is_paid()) {
                        error_log('YPrint PAYMENT DEBUG: Trigger E-Mail für bezahlte Bestellung...');
                        $this->send_confirmation_email_if_needed($order);
                    } else {
                        error_log('YPrint PAYMENT DEBUG: Order nicht bezahlt - keine E-Mail');
                    }
                } else {
                    error_log('YPrint PAYMENT DEBUG: FEHLER - Konnte Order nicht laden für ID: ' . $pending_order['order_id']);
                }
            } else {
                error_log('YPrint PAYMENT DEBUG: FEHLER - Keine order_id in pending_order gefunden');
            }
        } else {
            error_log('YPrint PAYMENT DEBUG: Kein pending_order in Session gefunden');
        }
        
        error_log('=== YPRINT PAYMENT DEBUG: Pending order Prüfung beendet ===');

        // Return success with step change instead of redirect
        wp_send_json_success(array(
            'message' => 'Payment processed successfully (Test Mode)',
            'payment_method_id' => $payment_method['id'],
            'order_data' => $order_data,
            'next_step' => 'confirmation',
            'test_mode' => true
        ));
        
    } catch (Exception $e) {
        error_log('Payment processing error: ' . $e->getMessage());
        wp_send_json_error(array(
            'message' => 'Payment processing failed: ' . $e->getMessage()
        ));
    }
}



    /**
     * Handler für Bestellstatus-Änderungen
     *
     * @param int $order_id
     * @param string $old_status
     * @param string $new_status
     * @param WC_Order $order
     */
    public function handle_order_status_change($order_id, $old_status, $new_status, $order) {
        error_log('=== YPRINT STATUS CHANGE DEBUG: Bestellstatus-Änderung erkannt ===');
        error_log('YPrint STATUS DEBUG: Bestell-ID: ' . $order_id);
        error_log('YPrint STATUS DEBUG: Alter Status: ' . $old_status);
        error_log('YPrint STATUS DEBUG: Neuer Status: ' . $new_status);
        error_log('YPrint STATUS DEBUG: Ist bezahlt: ' . ($order->is_paid() ? 'JA' : 'NEIN'));
        
        // Sende Bestätigungsmail bei Statuswechsel zu "processing" oder "completed"
        $trigger_statuses = array('processing', 'completed');
        $should_trigger = in_array($new_status, $trigger_statuses) && $order->is_paid();
        
        error_log('YPrint STATUS DEBUG: Status löst E-Mail aus: ' . ($should_trigger ? 'JA' : 'NEIN'));
        error_log('YPrint STATUS DEBUG: Trigger-Status-Liste: ' . implode(', ', $trigger_statuses));
        
        if ($should_trigger) {
            error_log('YPrint STATUS DEBUG: Trigger E-Mail-Versendung für Status-Änderung...');
            $this->send_confirmation_email_if_needed($order);
        } else {
            error_log('YPrint STATUS DEBUG: E-Mail-Versendung NICHT ausgelöst für Status-Änderung');
        }
        
        error_log('=== YPRINT STATUS CHANGE DEBUG: Handler beendet ===');
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
    }
}





YPrint_Stripe_Checkout::init();