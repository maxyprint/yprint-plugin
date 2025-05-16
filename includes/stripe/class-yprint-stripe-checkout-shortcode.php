<?php
/**
 * YPrint Stripe Checkout Shortcode Class
 * 
 * Handles the registration of the checkout shortcode, assets loading
 * and AJAX handlers for the checkout process.
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

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
     * Initialize the class
     */
    public static function init() {
        $instance = self::get_instance();
        
        // Register shortcode
        add_shortcode('yprint_checkout', array($instance, 'render_checkout_shortcode'));
        
        // Enqueue scripts and styles
        add_action('wp_enqueue_scripts', array($instance, 'enqueue_checkout_assets'));
        
        // Register AJAX handlers
        add_action('wp_ajax_yprint_save_address', array($instance, 'ajax_save_address'));
        add_action('wp_ajax_nopriv_yprint_save_address', array($instance, 'ajax_save_address'));
        
        add_action('wp_ajax_yprint_set_payment_method', array($instance, 'ajax_set_payment_method'));
        add_action('wp_ajax_nopriv_yprint_set_payment_method', array($instance, 'ajax_set_payment_method'));
        
        add_action('wp_ajax_yprint_process_checkout', array($instance, 'ajax_process_checkout'));
        add_action('wp_ajax_nopriv_yprint_process_checkout', array($instance, 'ajax_process_checkout'));
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation
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
    
    // Ensure CSS is loaded for this page
    $this->enqueue_checkout_assets();
    
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
     * Enqueue scripts and styles for checkout
     */
    public function enqueue_checkout_assets() {
        // Only load on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'yprint_checkout')) {
            
            // Checkout CSS
wp_enqueue_style(
    'yprint-checkout-style',
    YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css',
    array(),
    YPRINT_PLUGIN_VERSION . '.' . time() // Force no cache during development
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
            
            // Checkout JS
            wp_enqueue_script(
                'yprint-checkout-js',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js',
                array('jquery'),
                YPRINT_PLUGIN_VERSION,
                true
            );
            
            // Stripe JS (if needed)
            if ($this->is_stripe_enabled()) {
                wp_enqueue_script(
                    'stripe-js',
                    'https://js.stripe.com/v3/',
                    array(),
                    null,
                    true
                );
                
                wp_enqueue_script(
                    'yprint-stripe-checkout-js',
                    YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-checkout.js',
                    array('jquery', 'stripe-js'),
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
                    'i18n' => array(
                        'required_field' => __('This field is required.', 'yprint-plugin'),
                        'invalid_email' => __('Please enter a valid email address.', 'yprint-plugin'),
                        'processing' => __('Processing...', 'yprint-plugin'),
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
        if (class_exists('YPrint_Stripe_API')) {
            $settings = YPrint_Stripe_API::get_stripe_settings();
            return !empty($settings) && isset($settings['enabled']) && 'yes' === $settings['enabled'];
        }
        return false;
    }

    /**
     * AJAX handler for saving address
     */
    public function ajax_save_address() {
        check_ajax_referer('yprint_checkout_nonce', 'nonce');
        
        // This is just a placeholder - implement the actual logic according to your needs
        wp_send_json_success(array(
            'message' => __('Address saved successfully', 'yprint-plugin')
        ));
    }

    /**
     * AJAX handler for setting payment method
     */
    public function ajax_set_payment_method() {
        check_ajax_referer('yprint_checkout_nonce', 'nonce');
        
        // This is just a placeholder - implement the actual logic according to your needs
        wp_send_json_success(array(
            'message' => __('Payment method set successfully', 'yprint-plugin')
        ));
    }

    /**
     * AJAX handler for processing checkout
     */
    public function ajax_process_checkout() {
        check_ajax_referer('yprint_checkout_nonce', 'nonce');
        
        // This is just a placeholder - implement the actual logic according to your needs
        wp_send_json_success(array(
            'message' => __('Checkout processed successfully', 'yprint-plugin'),
            'redirect_url' => home_url('/thank-you/'),
        ));
    }

    /**
 * Debug function to check if assets are loading correctly
 * Add this to the rendered output when debug is enabled
 */
private function get_debug_info() {
    // Only show debug info to admins
    if (!current_user_can('manage_options')) {
        return '';
    }
    
    $debug = '<div style="background:#f8f8f8; border:1px solid #ddd; padding:10px; margin:10px 0; font-family:monospace;">';
    $debug .= '<h3>Checkout Debug Info:</h3>';
    $debug .= '<ul>';
    $debug .= '<li>Plugin URL: ' . YPRINT_PLUGIN_URL . '</li>';
    $debug .= '<li>CSS Path: ' . YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css' . '</li>';
    $debug .= '<li>CSS File Exists: ' . (file_exists(YPRINT_PLUGIN_DIR . 'assets/css/yprint-checkout.css') ? 'Yes' : 'No') . '</li>';
    $debug .= '<li>Plugin Version: ' . YPRINT_PLUGIN_VERSION . '</li>';
    $debug .= '<li>WP Debug: ' . (defined('WP_DEBUG') && WP_DEBUG ? 'Enabled' : 'Disabled') . '</li>';
    $debug .= '</ul>';
    $debug .= '</div>';
    
    return $debug;
}
}