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

        // Express Checkout Design Transfer Hook
        add_action('yprint_express_order_created', array($instance, 'express_checkout_design_transfer_hook'), 10, 1);
        
        // Final address protection hook temporarily disabled - removing race condition logic
// add_action('woocommerce_checkout_order_processed', array($instance, 'final_address_protection_hook'), 999, 3);
        
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
     * Integriere erweiterte Design-Daten aus der Datenbank
     */
    public function integrate_database_design_data($order_item, $design_id) {
        global $wpdb;
        
        error_log('EXPRESS DB: Integrating database design data for ID: ' . $design_id);
        
        // Hole vollständige Design-Daten aus der Datenbank
        $db_design = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id, template_id, name, design_data, created_at, product_name, product_description 
             FROM {$wpdb->prefix}octo_user_designs 
             WHERE id = %d",
            $design_id
        ), ARRAY_A);
        
        if ($db_design) {
            error_log('EXPRESS DB: Database design found, processing...');
            
            // Parse JSON design_data
            $parsed_design_data = json_decode($db_design['design_data'], true);
            
            if (json_last_error() === JSON_ERROR_NONE && $parsed_design_data) {
                // Erweiterte Datenbank-Meta-Daten
                $order_item->update_meta_data('_db_design_template_id', $db_design['template_id']);
                $order_item->update_meta_data('_db_design_user_id', $db_design['user_id']);
                $order_item->update_meta_data('_db_design_created_at', $db_design['created_at']);
                $order_item->update_meta_data('_db_design_product_name', $db_design['product_name']);
                $order_item->update_meta_data('_db_design_product_description', $db_design['product_description']);
                
                // Vollständige JSON-Daten speichern
                $order_item->update_meta_data('_db_design_raw_json', $db_design['design_data']);
                $order_item->update_meta_data('_db_design_parsed_data', wp_json_encode($parsed_design_data));
                
                // Template Info
                if (isset($parsed_design_data['templateId'])) {
                    $order_item->update_meta_data('_db_template_id', $parsed_design_data['templateId']);
                }
                if (isset($parsed_design_data['currentVariation'])) {
                    $order_item->update_meta_data('_db_current_variation', $parsed_design_data['currentVariation']);
                }
                
                // KRITISCH: Design-URL-Auswahl mit Datenbankpriorität (Express Checkout)
                $final_design_url = '';
                $url_source = 'none';
                
                if (!empty($parsed_design_data['final_design_url'])) {
                    $final_design_url = $parsed_design_data['final_design_url'];
                    $url_source = 'database_final_design_url';
                } elseif (!empty($parsed_design_data['print_ready_url'])) {
                    $final_design_url = $parsed_design_data['print_ready_url'];
                    $url_source = 'database_print_ready_url';
                } elseif (!empty($parsed_design_data['high_res_url'])) {
                    $final_design_url = $parsed_design_data['high_res_url'];
                    $url_source = 'database_high_res_url';
                } elseif (!empty($parsed_design_data['design_image_url'])) {
                    $final_design_url = $parsed_design_data['design_image_url'];
                    $url_source = 'database_design_image_url';
                } elseif (!empty($parsed_design_data['original_url'])) {
                    $final_design_url = $parsed_design_data['original_url'];
                    $url_source = 'database_original_url';
                }
                
                if (!empty($final_design_url)) {
                    $order_item->update_meta_data('_yprint_design_image_url', $final_design_url);
                    $order_item->update_meta_data('_yprint_url_source', $url_source);
                    error_log('EXPRESS DB: Final design URL selected from: ' . $url_source . ' -> ' . $final_design_url);
                } else {
                    error_log('EXPRESS DB: WARNING - No design URL found in database for design ID: ' . $design_id);
                }
                
                // Verarbeite variationImages für detaillierte View-Daten
                if (isset($parsed_design_data['variationImages'])) {
                    $this->process_variation_images($order_item, $parsed_design_data['variationImages']);
                }
                
                error_log('EXPRESS DB: Database integration completed successfully');
                return true;
            } else {
                error_log('EXPRESS DB: Failed to parse JSON design data: ' . json_last_error_msg());
            }
        } else {
            error_log('EXPRESS DB: Design not found in database for ID: ' . $design_id);
        }
        
        return false;
    }

    /**
     * Verarbeite variationImages für detaillierte View-Daten
     */
    private function process_variation_images($order_item, $variation_images) {
        $processed_views = array();
        
        foreach ($variation_images as $view_key => $images) {
            $view_parts = explode('_', $view_key);
            $variation_id = $view_parts[0] ?? '';
            $view_system_id = $view_parts[1] ?? '';
            
            $view_data = array(
                'view_key' => $view_key,
                'variation_id' => $variation_id,
                'system_id' => $view_system_id,
                'view_name' => yprint_get_view_name_by_system_id($view_system_id),
                'images' => array()
            );
            
            foreach ($images as $image_index => $image) {
                $image_data = array(
                    'id' => $image['id'] ?? '',
                    'url' => $image['url'] ?? '',
                    'filename' => basename($image['url'] ?? ''),
                    'transform' => $image['transform'] ?? array(),
                    'visible' => $image['visible'] ?? true
                );
                
                // Berechne Print-Dimensionen
                if (isset($image['transform'])) {
                    $transform = $image['transform'];
                    $original_width = $transform['width'] ?? 0;
                    $original_height = $transform['height'] ?? 0;
                    $scale_x = $transform['scaleX'] ?? 0;
                    $scale_y = $transform['scaleY'] ?? 0;
                    
                    // Pixel zu mm Konvertierung (96 DPI Standard)
                    $image_data['print_width_mm'] = round(($original_width * $scale_x) * 0.26458333, 2);
                    $image_data['print_height_mm'] = round(($original_height * $scale_y) * 0.26458333, 2);
                    $image_data['scale_percent'] = round($scale_x * 100, 2);
                    $image_data['position_left'] = $transform['left'] ?? 0;
                    $image_data['position_top'] = $transform['top'] ?? 0;
                    $image_data['angle'] = $transform['angle'] ?? 0;
                }
                
                $view_data['images'][] = $image_data;
            }
            
            $processed_views[$view_key] = $view_data;
        }
        
        // Speichere processed views
        $order_item->update_meta_data('_db_processed_views', wp_json_encode($processed_views));
        $order_item->update_meta_data('_db_view_count', count($processed_views));
        
        // Separate View Meta Fields für einfachen Zugriff
        $view_counter = 1;
        foreach ($processed_views as $view_key => $view_data) {
            $order_item->update_meta_data("_view_{$view_counter}_key", $view_key);
            $order_item->update_meta_data("_view_{$view_counter}_name", $view_data['view_name']);
            $order_item->update_meta_data("_view_{$view_counter}_system_id", $view_data['system_id']);
            $order_item->update_meta_data("_view_{$view_counter}_variation_id", $view_data['variation_id']);
            $order_item->update_meta_data("_view_{$view_counter}_image_count", count($view_data['images']));
            $order_item->update_meta_data("_view_{$view_counter}_data", wp_json_encode($view_data));
            $view_counter++;
        }
        
        error_log('EXPRESS DB: Processed and saved ' . count($processed_views) . ' views');
    }

    

    /**
     * Triggere Enhanced Debug-Analyse für Express Orders
     */
    private function trigger_enhanced_debug_analysis($order_id) {
        error_log('EXPRESS: Triggering enhanced debug analysis for order: ' . $order_id);
        
        // Simuliere Enhanced Debug-Analyse
        if (class_exists('YPrint_Enhanced_Debug')) {
            $debug_instance = YPrint_Enhanced_Debug::get_instance();
            
            // Triggere die Analyse
            if (method_exists($debug_instance, 'log_final_order')) {
                $debug_instance->log_final_order($order_id);
                error_log('EXPRESS: Enhanced debug analysis completed');
            }
        }
        
        // Log hook execution für Tracking
        if (function_exists('yprint_log_hook_execution')) {
            yprint_log_hook_execution('express_enhanced_debug', "Order ID: $order_id | Enhanced analysis triggered");
        }
    }

    /**
     * Express Checkout Design Transfer Hook
     */
    public function express_checkout_design_transfer_hook($order_id) {
        error_log('EXPRESS HOOK: Design transfer verification for order: ' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        $design_items = 0;
        $complete_transfers = 0;
        
        foreach ($order->get_items() as $item_id => $item) {
            if ($item->get_meta('print_design')) {
                $design_items++;
                
                // Prüfe ob vollständiger Transfer stattgefunden hat
                if ($item->get_meta('_express_checkout_transfer') === 'yes' && 
                    $item->get_meta('design_id') && 
                    $item->get_meta('product_images')) {
                    $complete_transfers++;
                }
            }
        }
        
        error_log("EXPRESS HOOK: Found $design_items design items, $complete_transfers complete transfers");
        
        // Speichere Transfer-Status
        $order->update_meta_data('_express_design_items', $design_items);
        $order->update_meta_data('_express_complete_transfers', $complete_transfers);
        $order->save();
        
        return $complete_transfers;
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
        // DEBUG: Log all enqueued scripts to find yprint-checkout-header.js source
        error_log('=== YPRINT CHECKOUT ASSETS DEBUG ===');
        error_log('Current page URL: ' . $_SERVER['REQUEST_URI']);
        error_log('Function called from: ' . wp_debug_backtrace_summary());
        
        // Check if this mysterious file is being enqueued here
        global $wp_scripts;
        if (isset($wp_scripts->registered)) {
            foreach ($wp_scripts->registered as $handle => $script) {
                if (strpos($script->src, 'yprint-checkout-header') !== false) {
                    error_log('FOUND: yprint-checkout-header.js registered with handle: ' . $handle);
                    error_log('Source: ' . $script->src);
                    error_log('Dependencies: ' . print_r($script->deps, true));
                }
            }
        }
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

// Checkout Header JS
wp_enqueue_script(
    'yprint-checkout-header-js',
    YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout-header.js',
    array('jquery'),
    YPRINT_PLUGIN_VERSION,
    true
);

// Localize Checkout Header Script
wp_localize_script(
    'yprint-checkout-header-js',
    'yprintCheckoutHeader',
    array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yprint_checkout_header_nonce'),
        'texts' => array(
            'show_summary' => __('Bestellung anzeigen', 'yprint-checkout'),
            'hide_summary' => __('Bestellung ausblenden', 'yprint-checkout'),
            'loading' => __('Lädt...', 'yprint-checkout')
        )
    )
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

                

                // Express Checkout spezifische Daten mit zentraler Datenverwaltung + Debug
$cart_data_manager = YPrint_Cart_Data::get_instance();
$checkout_context = $cart_data_manager->get_checkout_context('full');
$express_payment_data = $checkout_context['express_payment'];

// Hole aktuelle Adresse aus Address Manager / WooCommerce Customer
$current_address = null;
if (WC()->customer) {
    // Prüfe zuerst ob eine Adresse aus dem Address Manager Session gesetzt wurde
    $selected_address = WC()->session->get('yprint_selected_address');
    
    if ($selected_address) {
        error_log('EXPRESS DEBUG: Using selected address from session: ' . print_r($selected_address, true));
        $current_address = array(
            'country' => $selected_address['country'] ?? 'DE',
            'state' => $selected_address['state'] ?? '',
            'city' => $selected_address['city'] ?? '',
            'postal_code' => $selected_address['postcode'] ?? '',
            'line1' => $selected_address['address_1'] ?? '',
            'line2' => $selected_address['address_2'] ?? ''
        );
    } else {
        // Fallback: Nutze WooCommerce Customer Daten
        $current_address = array(
            'country' => WC()->customer->get_shipping_country() ?: WC()->customer->get_billing_country(),
            'state' => WC()->customer->get_shipping_state() ?: WC()->customer->get_billing_state(),
            'city' => WC()->customer->get_shipping_city() ?: WC()->customer->get_billing_city(),
            'postal_code' => WC()->customer->get_shipping_postcode() ?: WC()->customer->get_billing_postcode(),
            'line1' => WC()->customer->get_shipping_address_1() ?: WC()->customer->get_billing_address_1(),
            'line2' => WC()->customer->get_shipping_address_2() ?: WC()->customer->get_billing_address_2()
        );
    }
    
    // Entferne leere Werte
    $current_address = array_filter($current_address);
    
    error_log('EXPRESS DEBUG: Current address for Apple Pay: ' . print_r($current_address, true));
}

// Debug für Versandkosten-Problem
error_log('=== EXPRESS PAYMENT DEBUG ===');
error_log('WC Cart Shipping Total: ' . (WC()->cart ? WC()->cart->get_shipping_total() : 'Cart not available'));
error_log('WC Cart Total: ' . (WC()->cart ? WC()->cart->get_total('edit') : 'Cart not available'));
error_log('WC Cart Needs Shipping: ' . (WC()->cart ? (WC()->cart->needs_shipping() ? 'Yes' : 'No') : 'Cart not available'));
error_log('Express Payment Data Total: ' . print_r($express_payment_data['total'], true));
error_log('Express Payment Display Items: ' . print_r($express_payment_data['displayItems'], true));
error_log('Express Payment Request Shipping: ' . ($express_payment_data['requestShipping'] ? 'Yes' : 'No'));

// Direkte WooCommerce Werte für Vergleich
if (WC()->cart) {
    WC()->cart->calculate_totals();
    $wc_subtotal = WC()->cart->get_subtotal();
    $wc_shipping = WC()->cart->get_shipping_total();
    $wc_total = WC()->cart->get_total('edit');
    
    error_log('WC Direct Values:');
    error_log('- Subtotal: ' . $wc_subtotal);
    error_log('- Shipping: ' . $wc_shipping);
    error_log('- Total: ' . $wc_total);
    
    // Überschreibe Express Payment Daten mit aktuellen WC-Werten
    $corrected_display_items = array();
    
    // Zwischensumme
    $corrected_display_items[] = array(
        'label' => 'Zwischensumme',
        'amount' => round($wc_subtotal * 100) // in Cent
    );
    
    // Versandkosten nur hinzufügen wenn > 0
    if ($wc_shipping > 0) {
        $corrected_display_items[] = array(
            'label' => 'Versand',
            'amount' => round($wc_shipping * 100) // in Cent
        );
    }
    
    $corrected_total_amount = round($wc_total * 100);
    
    error_log('Corrected Display Items: ' . print_r($corrected_display_items, true));
    error_log('Corrected Total Amount: ' . $corrected_total_amount);
    
    // Überschreibe die fehlerhaften Daten
    $express_payment_data['displayItems'] = $corrected_display_items;
    $express_payment_data['total']['amount'] = $corrected_total_amount;
}

error_log('=== EXPRESS PAYMENT DEBUG END ===');

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
        'address' => array(
            'current' => $current_address,
            'prefill' => !empty($current_address)
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

private function add_express_checkout_ajax_handlers() {
    add_action('wp_ajax_yprint_stripe_express_checkout_data', array($this, 'ajax_express_checkout_data'));
    add_action('wp_ajax_nopriv_yprint_stripe_express_checkout_data', array($this, 'ajax_express_checkout_data'));
    
    add_action('wp_ajax_yprint_stripe_process_express_payment', array($this, 'ajax_process_express_payment'));
    add_action('wp_ajax_nopriv_yprint_stripe_process_express_payment', array($this, 'ajax_process_express_payment'));
    
    add_action('wp_ajax_yprint_stripe_update_express_shipping', array($this, 'ajax_update_express_shipping'));
    add_action('wp_ajax_nopriv_yprint_stripe_update_express_shipping', array($this, 'ajax_update_express_shipping'));
    
    add_action('wp_ajax_yprint_save_apple_pay_address', array($this, 'ajax_save_apple_pay_address'));
    add_action('wp_ajax_nopriv_yprint_save_apple_pay_address', array($this, 'ajax_save_apple_pay_address'));
    
    // NEUE HANDLER FÜR DESIGN-DATEN SICHERUNG
    add_action('wp_ajax_yprint_secure_express_design_data', array($this, 'ajax_secure_express_design_data'));
    add_action('wp_ajax_nopriv_yprint_secure_express_design_data', array($this, 'ajax_secure_express_design_data'));
}

/**
 * VERBESSERTE AJAX handler für Express Design Data Sicherung
 */
public function ajax_secure_express_design_data() {
    check_ajax_referer('yprint_express_checkout_nonce', 'nonce');
    
    error_log('=== SECURING EXPRESS DESIGN DATA (IMPROVED) ===');
    
    if (WC()->cart->is_empty()) {
        error_log('EXPRESS SECURE: Cart is empty');
        wp_send_json_error(array('message' => 'Cart is empty'));
        return;
    }
    
    // SOFORTIGES BACKUP ERSTELLEN
    $force_immediate = isset($_POST['force_immediate']) && $_POST['force_immediate'] === 'true';
    if ($force_immediate) {
        error_log('EXPRESS SECURE: Force immediate backup requested');
    }
    
    $design_backup = array();
    $design_count = 0;
    
    // Sammle alle Design-Daten aus dem Warenkorb
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['print_design']) && !empty($cart_item['print_design'])) {
            $design_backup[$cart_item_key] = array(
                'design_data' => $cart_item['print_design'],
                'product_id' => $cart_item['product_id'],
                'variation_id' => $cart_item['variation_id'] ?? 0,
                'quantity' => $cart_item['quantity'],
                'secured_at' => current_time('mysql')
            );
            $design_count++;
            
            error_log('EXPRESS SECURE: Secured design for cart key: ' . $cart_item_key);
            error_log('Design ID: ' . ($cart_item['print_design']['design_id'] ?? 'unknown'));
        }
    }
    
    if ($design_count > 0) {
        // Speichere in Session mit mehreren Backup-Schlüsseln
        WC()->session->set('yprint_express_design_backup', $design_backup);
        WC()->session->set('yprint_express_design_backup_v2', $design_backup);
        WC()->session->set('yprint_express_design_backup_v3', $design_backup);
        
        error_log('EXPRESS SECURE: Saved ' . $design_count . ' design items to session backup');
        
        wp_send_json_success(array(
            'message' => 'Design data secured',
            'design_count' => $design_count,
            'backup_keys' => array_keys($design_backup)
        ));
    } else {
        error_log('EXPRESS SECURE: No design data found in cart');
        wp_send_json_error(array('message' => 'No design data found'));
    }
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
    
    error_log('=== EXPRESS SHIPPING UPDATE DEBUG ===');
    
    // Shipping Address aus Request
    $shipping_address_json = isset($_POST['shipping_address']) ? wp_unslash($_POST['shipping_address']) : '';
    $shipping_address = null;
    
    if (!empty($shipping_address_json)) {
        $shipping_address = json_decode($shipping_address_json, true);
        error_log('Received shipping address: ' . print_r($shipping_address, true));
    }
    
    try {
        // Setze Kundenadresse für Versandkostenberechnung
        if ($shipping_address && WC()->customer) {
            WC()->customer->set_shipping_country($shipping_address['country'] ?? 'DE');
            WC()->customer->set_shipping_state($shipping_address['region'] ?? '');
            WC()->customer->set_shipping_postcode($shipping_address['postalCode'] ?? '');
            WC()->customer->set_shipping_city($shipping_address['city'] ?? '');
            WC()->customer->set_shipping_address($shipping_address['addressLine'][0] ?? '');
            
            // Billing gleich Shipping setzen
            WC()->customer->set_billing_country($shipping_address['country'] ?? 'DE');
            WC()->customer->set_billing_state($shipping_address['region'] ?? '');
            WC()->customer->set_billing_postcode($shipping_address['postalCode'] ?? '');
            WC()->customer->set_billing_city($shipping_address['city'] ?? '');
            WC()->customer->set_billing_address($shipping_address['addressLine'][0] ?? '');
            
            error_log('Customer shipping address updated');
        }
        
        // Neuberechnung der Versandkosten
        if (WC()->cart) {
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();
            
            $shipping_total = WC()->cart->get_shipping_total();
            $cart_total = WC()->cart->get_total('edit');
            
            error_log('Calculated shipping total: ' . $shipping_total);
            error_log('Calculated cart total: ' . $cart_total);
        }
        
        // Hole verfügbare Versandmethoden
        $packages = WC()->shipping->get_packages();
        $shipping_options = array();
        
        if (!empty($packages)) {
            foreach ($packages as $package_key => $package) {
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $rate_id => $rate) {
                        $shipping_options[] = array(
                            'id' => $rate_id,
                            'label' => $rate->get_label(),
                            'detail' => $rate->get_method_title(),
                            'amount' => round($rate->get_cost() * 100), // in Cent
                        );
                        
                        error_log('Available shipping rate: ' . $rate->get_label() . ' - Cost: ' . $rate->get_cost());
                    }
                }
            }
        }
        
        // Fallback: Kostenloser Versand wenn keine Methoden gefunden
        if (empty($shipping_options)) {
            $shipping_options[] = array(
                'id' => 'free',
                'label' => __('Kostenloser Versand', 'yprint-plugin'),
                'detail' => __('3-5 Werktage', 'yprint-plugin'),
                'amount' => 0,
            );
            error_log('No shipping methods found, using free shipping fallback');
        }
        
        // Erstelle neue Display Items mit korrekten Versandkosten
        $display_items = array();
        $subtotal = WC()->cart ? WC()->cart->get_subtotal() : 0;
        $shipping_cost = WC()->cart ? WC()->cart->get_shipping_total() : 0;
        $total = WC()->cart ? WC()->cart->get_total('edit') : 0;
        
        // Zwischensumme
        $display_items[] = array(
            'label' => 'Zwischensumme',
            'amount' => round($subtotal * 100)
        );
        
        // Versandkosten nur wenn > 0
        if ($shipping_cost > 0) {
            $display_items[] = array(
                'label' => 'Versand',
                'amount' => round($shipping_cost * 100)
            );
        }
        
        error_log('Final display items: ' . print_r($display_items, true));
        error_log('Final total amount: ' . round($total * 100));
        
        wp_send_json_success(array(
            'shippingOptions' => $shipping_options,
            'displayItems' => $display_items,
            'total' => array(
                'label' => get_bloginfo('name') . ' (via YPrint)',
                'amount' => round($total * 100),
            )
        ));
        
    } catch (Exception $e) {
        error_log('Express shipping update error: ' . $e->getMessage());
        
        // Fallback zu kostenlosem Versand
        wp_send_json_success(array(
            'shippingOptions' => array(
                array(
                    'id' => 'free',
                    'label' => __('Kostenloser Versand', 'yprint-plugin'),
                    'detail' => __('3-5 Werktage', 'yprint-plugin'),
                    'amount' => 0,
                )
            )
        ));
    }
    
    error_log('=== EXPRESS SHIPPING UPDATE DEBUG END ===');
}

public function ajax_save_apple_pay_address() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');
    
    $address_data_json = isset($_POST['address_data']) ? wp_unslash($_POST['address_data']) : '';
    $address_data = json_decode($address_data_json, true);
    
    if ($address_data) {
        // Apple Pay address storage temporarily disabled - avoiding session conflicts
        // The address will be handled directly by Stripe
        error_log('🔍 YPRINT DEBUG: Apple Pay address received but not stored to avoid conflicts');
        wp_send_json_success(array('message' => 'Apple Pay address noted - handled by Stripe'));
    } else {
        wp_send_json_error(array('message' => 'Invalid address data'));
    }
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

    public function ajax_set_payment_method() {
        check_ajax_referer('yprint_checkout_nonce', 'security');
    
        $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        $response = array('success' => false, 'message' => __('Fehler beim Setzen der Zahlungsart.', 'yprint-plugin'));
    
        if (!empty($payment_method)) {
            WC()->session->set('yprint_checkout_payment_method', $payment_method);
            
            // Generiere den Display-Namen für die Confirmation Page
            $display_name = $this->generate_payment_method_display($payment_method);
            WC()->session->set('yprint_checkout_payment_method_display', $display_name);
    
            // Store chosen saved payment method ID if it exists
            if (isset($_POST['chosen_payment_saved_id']) && !empty($_POST['chosen_payment_saved_id'])) {
                WC()->session->set('chosen_payment_saved_id', sanitize_text_field($_POST['chosen_payment_saved_id']));
            }
    
            $response['success'] = true;
            $response['message'] = __('Zahlungsart erfolgreich gesetzt.', 'yprint-plugin');
            $response['display_name'] = $display_name;
        }
    
        wp_send_json($response);
    }
    
    /**
     * Generiert den Display-Namen für eine Zahlungsmethode
     */
    private function generate_payment_method_display($payment_method) {
        $method = strtolower($payment_method);
        $title = __('Kreditkarte (Stripe)', 'yprint-plugin');
        $icon = 'fa-credit-card';
    
        if (str_contains($method, 'sepa')) {
            $title = __('SEPA-Lastschrift (Stripe)', 'yprint-plugin');
            $icon = 'fa-university';
        } elseif (str_contains($method, 'apple')) {
            $title = __('Apple Pay (Stripe)', 'yprint-plugin');
            $icon = 'fa-apple fab';
        } elseif (str_contains($method, 'google')) {
            $title = __('Google Pay (Stripe)', 'yprint-plugin');
            $icon = 'fa-google-pay fab';
        } elseif (str_contains($method, 'express') || str_contains($method, 'payment_request')) {
            $title = __('Express-Zahlung (Stripe)', 'yprint-plugin');
            $icon = 'fa-bolt';
        }
    
        return sprintf('<i class="%s mr-2"></i>%s', $icon, $title);
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

        // KRITISCH: AKTIVE DESIGN-DATEN SICHERUNG VOR CART-VERLUST
error_log('=== EXPRESS ORDER: SECURING DESIGN DATA BEFORE CART LOSS ===');

// 1. Sichere Design-Daten in Session BEVOR Cart geleert wird
$design_backup = array();
$design_count = 0;
foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
    if (isset($cart_item['print_design']) && !empty($cart_item['print_design'])) {
        $design_backup[$cart_item_key] = array(
            'design_data' => $cart_item['print_design'],
            'product_id' => $cart_item['product_id'],
            'variation_id' => $cart_item['variation_id'] ?? 0,
            'quantity' => $cart_item['quantity'],
            'line_subtotal' => $cart_item['line_subtotal'],
            'line_total' => $cart_item['line_total'],
            'data' => $cart_item['data'], // Komplettes Produkt-Objekt
            'secured_at' => current_time('mysql')
        );
        $design_count++;
        error_log('EXPRESS SECURE: Design secured for key: ' . $cart_item_key . ' (ID: ' . ($cart_item['print_design']['design_id'] ?? 'unknown') . ')');
    }
}

// 2. Speichere in Session mit Triple-Backup
if ($design_count > 0) {
    WC()->session->set('yprint_express_design_backup', $design_backup);
    WC()->session->set('yprint_express_design_backup_v2', $design_backup);
    WC()->session->set('yprint_express_design_backup_v3', $design_backup);
    error_log('EXPRESS SECURE: Triple-backup created with ' . $design_count . ' design items');
}

// 3. Cart-Backup erstellen (weiterhin für Standard-Items)
$cart_items_backup = WC()->cart->get_cart();
error_log('EXPRESS CHECKOUT: Cart backup created with ' . count($cart_items_backup) . ' items');

// 4. Design-Transfer mit Backup-Fallback-System
error_log('EXPRESS CHECKOUT: Starting cart items transfer with design data');
$design_transfers_success = 0;
$design_transfers_failed = 0;

foreach ($cart_items_backup as $cart_item_key => $cart_item) {
    error_log('EXPRESS CHECKOUT: Processing cart item: ' . $cart_item_key);
            error_log('NORMAL CHECKOUT: Processing cart item: ' . $cart_item_key);
            
            // Create proper WC_Order_Item_Product
            $order_item = new WC_Order_Item_Product();
            $order_item->set_product($cart_item['data']);
            $order_item->set_quantity($cart_item['quantity']);
            
            // Set variation ID if exists
            if (isset($cart_item['variation_id']) && $cart_item['variation_id'] > 0) {
                $order_item->set_variation_id($cart_item['variation_id']);
            }
            
            // Set pricing
            $order_item->set_subtotal($cart_item['line_subtotal']);
            $order_item->set_total($cart_item['line_total']);
            
            // === VOLLSTÄNDIGE DESIGN DATA TRANSFER ===
            if (isset($cart_item['print_design']) && !empty($cart_item['print_design'])) {
                $design_data = $cart_item['print_design'];
                error_log('NORMAL CHECKOUT: Found design data for cart item: ' . $cart_item_key);
                error_log('NORMAL CHECKOUT: Design data: ' . print_r($design_data, true));
                
                try {
                    // Legacy Format (für Kompatibilität)
                    $order_item->update_meta_data('print_design', $design_data);
                    $order_item->update_meta_data('_is_design_product', true);
                    $order_item->update_meta_data('_has_print_design', 'yes');
                    $order_item->update_meta_data('_cart_item_key', $cart_item_key);
                    $order_item->update_meta_data('_yprint_design_transferred', current_time('mysql'));
                    
                    // Print Provider E-Mail Format
                    $order_item->update_meta_data('design_id', $design_data['design_id'] ?? '');
                    $order_item->update_meta_data('name', $design_data['name'] ?? '');
                    $order_item->update_meta_data('template_id', $design_data['template_id'] ?? '');
                    $order_item->update_meta_data('variation_id', $design_data['variation_id'] ?? '');
                    $order_item->update_meta_data('size_id', $design_data['size_id'] ?? '');
                    $order_item->update_meta_data('preview_url', $design_data['preview_url'] ?? '');
                    
                    // Dimensionen
                    $order_item->update_meta_data('width_cm', $design_data['width_cm'] ?? $design_data['width'] ?? '25.4');
                    $order_item->update_meta_data('height_cm', $design_data['height_cm'] ?? $design_data['height'] ?? '30.2');
                    
                    // Design Image URL
                    $order_item->update_meta_data('design_image_url', $design_data['design_image_url'] ?? $design_data['original_url'] ?? '');
                    
                    // Product Images (JSON)
                    if (isset($design_data['product_images']) && !empty($design_data['product_images'])) {
                        $product_images_json = is_string($design_data['product_images']) ? 
                            $design_data['product_images'] : wp_json_encode($design_data['product_images']);
                        $order_item->update_meta_data('product_images', $product_images_json);
                    }
                    
                    // Design Images (JSON)
                    if (isset($design_data['design_images']) && !empty($design_data['design_images'])) {
                        $design_images_json = is_string($design_data['design_images']) ? 
                            $design_data['design_images'] : wp_json_encode($design_data['design_images']);
                        $order_item->update_meta_data('design_images', $design_images_json);
                    }
                    
                    // Multiple Images Check
                    $has_multiple_images = (isset($design_data['design_images']) && is_array($design_data['design_images']) && count($design_data['design_images']) > 1) ? true : false;
                    $order_item->update_meta_data('has_multiple_images', $has_multiple_images);
                    
                    // Alternative Feldnamen (Kompatibilität)
                    $order_item->update_meta_data('_design_id', $design_data['design_id'] ?? '');
                    $order_item->update_meta_data('_design_name', $design_data['name'] ?? '');
                    $order_item->update_meta_data('_design_color', $design_data['variation_name'] ?? '');
                    $order_item->update_meta_data('_design_size', $design_data['size_name'] ?? '');
                    $order_item->update_meta_data('_design_preview_url', $design_data['preview_url'] ?? '');
                    $order_item->update_meta_data('_design_width_cm', $design_data['width_cm'] ?? $design_data['width'] ?? '25.4');
                    $order_item->update_meta_data('_design_height_cm', $design_data['height_cm'] ?? $design_data['height'] ?? '30.2');
                    $order_item->update_meta_data('_design_image_url', $design_data['design_image_url'] ?? $design_data['original_url'] ?? '');
                    $order_item->update_meta_data('_design_has_multiple_images', $has_multiple_images);
                    
                    // Normal Checkout Markers
                    $order_item->update_meta_data('_normal_checkout_transfer', 'yes');
                    
                    $design_transfers_success++;
                    error_log('NORMAL CHECKOUT: Design data successfully transferred for cart item: ' . $cart_item_key);
                    
                } catch (Exception $e) {
                    error_log('NORMAL CHECKOUT: Design transfer exception: ' . $e->getMessage());
                    $design_transfers_failed++;
                }
            } else {
                error_log('NORMAL CHECKOUT: No design data found for cart item: ' . $cart_item_key);
                $design_transfers_failed++;
            }
            
            // Add item to order
            $order->add_item($order_item);
        }
        
        error_log('NORMAL CHECKOUT: Design transfers completed - Success: ' . $design_transfers_success . ', Failed: ' . $design_transfers_failed);
        
        // === DESIGN TRANSFER VERIFICATION ===
        error_log('NORMAL CHECKOUT: ========== DESIGN TRANSFER VERIFICATION ==========');
        foreach ($order->get_items() as $item_id => $item) {
            error_log('NORMAL CHECKOUT: Item ID: ' . $item_id);
            error_log('NORMAL CHECKOUT: Product ID: ' . $item->get_product_id());
            error_log('NORMAL CHECKOUT: Has print_design meta: ' . ($item->get_meta('print_design') ? 'YES' : 'NO'));
            error_log('NORMAL CHECKOUT: Has design_id meta: ' . ($item->get_meta('design_id') ? $item->get_meta('design_id') : 'NO'));
            error_log('NORMAL CHECKOUT: Has _design_id meta: ' . ($item->get_meta('_design_id') ? $item->get_meta('_design_id') : 'NO'));
            error_log('NORMAL CHECKOUT: Has _is_design_product meta: ' . ($item->get_meta('_is_design_product') ? 'YES' : 'NO'));
        }
        error_log('NORMAL CHECKOUT: ========== DESIGN TRANSFER VERIFICATION END ==========');

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
 * @param string|null $payment_intent_id Die ID des Stripe Payment Intents, falls vorhanden
 */
private function send_confirmation_email_if_needed($order, $payment_intent_id = null) {
    error_log('=== YPRINT CHECKOUT DEBUG: E-Mail-Trigger gestartet ===');

    if (!$order || !is_a($order, 'WC_Order')) {
        error_log('YPrint CHECKOUT DEBUG: FEHLER - Ungültiges Order-Objekt empfangen');
        error_log('YPrint CHECKOUT DEBUG: Order Type: ' . gettype($order));
        error_log('YPrint CHECKOUT DEBUG: Is WC_Order: ' . (is_a($order, 'WC_Order') ? 'JA' : 'NEIN'));
        return false;
    }

    error_log('YPrint CHECKOUT DEBUG: Gültiges Order-Objekt empfangen');
    error_log('YPrint CHECKOUT DEBUG: Bestellnummer: ' . $order->get_order_number());
    error_log('YPrint CHECKOUT DEBUG: Bestell-ID: ' . $order->get_id());

    // Füge Payment Intent ID zur Protokollierung hinzu, falls übergeben
    if ($payment_intent_id) {
        error_log('YPrint CHECKOUT DEBUG: Payment Intent ID (übergeben): ' . $payment_intent_id);
    } else {
        error_log('YPrint CHECKOUT DEBUG: Keine Payment Intent ID direkt übergeben');
    }
    
    // Prüfe ob E-Mail bereits gesendet wurde
    $email_already_sent = $order->get_meta('_yprint_confirmation_email_sent');
    error_log('YPrint CHECKOUT DEBUG: E-Mail bereits gesendet Meta: "' . $email_already_sent . '"');

    if ($email_already_sent === 'yes') {
        error_log('YPrint CHECKOUT DEBUG: ÜBERSPRUNGEN - Bestätigungsmail bereits gesendet für Bestellung ' . $order->get_order_number());
        return true;
    }

    // Prüfe ob Bestellung bezahlt ist
    $is_paid = $order->is_paid();
    error_log('YPrint CHECKOUT DEBUG: Ist Bestellung bezahlt: ' . ($is_paid ? 'JA' : 'NEIN'));
    error_log('YPrint CHECKOUT DEBUG: Bestellstatus: ' . $order->get_status());
    error_log('YPrint CHECKOUT DEBUG: Zahlungsmethode: ' . $order->get_payment_method());
    error_log('YPrint CHECKOUT DEBUG: Transaktions-ID: ' . ($order->get_transaction_id() ?: 'KEINE'));
    
    // Für Test-Bestellungen: Sende E-Mail auch wenn nicht als "bezahlt" markiert
    // Annahme: YPrint_Stripe_API::is_testmode() ist eine statische Methode in deiner Stripe API Klasse
    $is_test_order = false;
    if (class_exists('YPrint_Stripe_API') && method_exists('YPrint_Stripe_API', 'is_testmode')) {
        $is_test_order = YPrint_Stripe_API::is_testmode();
    }
    
    // Zusätzliche Test-Erkennung
    if (!$is_test_order) {
        $is_test_order = strpos($order->get_payment_method_title(), '(Test)') !== false;
    }
    
    error_log('YPrint CHECKOUT DEBUG: Ist Test-Bestellung: ' . ($is_test_order ? 'JA' : 'NEIN'));
    
    if (!$is_paid && !$is_test_order) {
        error_log('YPrint CHECKOUT DEBUG: ÜBERSPRUNGEN - Bestellung ' . $order->get_order_number() . ' ist noch nicht bezahlt und keine Test-Bestellung - keine E-Mail');
        return false;
    }
    
    if ($is_test_order) {
        error_log('YPrint CHECKOUT DEBUG: Test-Bestellung erkannt - E-Mail wird trotzdem gesendet');
    }

    // Prüfe E-Mail-Funktion Verfügbarkeit
    $function_exists = function_exists('yprint_send_order_confirmation_email');
    error_log('YPrint CHECKOUT DEBUG: E-Mail-Funktion verfügbar: ' . ($function_exists ? 'JA' : 'NEIN'));
    
    if (!$function_exists) {
        error_log('YPrint CHECKOUT DEBUG: FEHLER - E-Mail-Funktion nicht verfügbar');
        return false;
    }

    error_log('YPrint CHECKOUT DEBUG: Rufe yprint_send_order_confirmation_email() auf...');
    
    // E-Mail-Funktion aufrufen
    $email_result = yprint_send_order_confirmation_email($order);
    
    error_log('YPrint CHECKOUT DEBUG: E-Mail-Funktion Ergebnis: ' . ($email_result ? 'ERFOLGREICH' : 'FEHLGESCHLAGEN'));
    error_log('=== YPRINT CHECKOUT DEBUG: E-Mail-Trigger beendet ===');
    
    return $email_result;
}

/**
 * Get Stripe amount (in cents)
 *
 * @param float $amount
 * @param string $currency
 * @return int
 */
public static function get_stripe_amount($amount, $currency = null) {
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
     * FINALE AUTORITÄRE YPRINT-ADRESS-ANWENDUNG
     * Diese Funktion hat das FINALE Wort über alle Adressdaten
     */
    private function apply_final_yprint_addresses($order) {
        error_log('🔍 YPRINT FINAL: ==========================================');
        error_log('🔍 YPRINT FINAL: FINALE AUTORITATIVE ADRESS-ANWENDUNG START');
        error_log('🔍 YPRINT FINAL: Order #' . $order->get_id());
        
        // YPrint Session-Daten laden
        $selected_address = WC()->session->get('yprint_selected_address', array());
        $billing_address = WC()->session->get('yprint_billing_address', array());
        $has_different_billing = WC()->session->get('yprint_billing_address_different', false);
        
        error_log('🔍 YPRINT FINAL: Selected Address Count: ' . count($selected_address));
        error_log('🔍 YPRINT FINAL: Has Different Billing: ' . ($has_different_billing ? 'YES' : 'NO'));
        error_log('🔍 YPRINT FINAL: Billing Address Count: ' . count($billing_address));
        
        // BEDINGUNG: Nur anwenden wenn YPrint-Adressen vorhanden sind
        if (empty($selected_address)) {
            error_log('🔍 YPRINT FINAL: SKIP - Keine YPrint-Adresse in Session');
            return;
        }
        
        // SCHRITT 1: SHIPPING ADDRESS - IMMER aus selected_address
        error_log('🔍 YPRINT FINAL: ANWENDEN - Shipping Address');
        $order->set_shipping_first_name($selected_address['first_name'] ?? '');
        $order->set_shipping_last_name($selected_address['last_name'] ?? '');
        $order->set_shipping_address_1($selected_address['address_1'] ?? '');
        $order->set_shipping_address_2($selected_address['address_2'] ?? '');
        $order->set_shipping_city($selected_address['city'] ?? '');
        $order->set_shipping_postcode($selected_address['postcode'] ?? '');
        $order->set_shipping_country($selected_address['country'] ?? 'DE');
        $order->set_shipping_phone($selected_address['phone'] ?? '');
        
        error_log('🔍 YPRINT FINAL: Shipping gesetzt: ' . $selected_address['address_1'] . ', ' . $selected_address['city']);
        
        // SCHRITT 2: BILLING ADDRESS - Conditional Logic
        if ($has_different_billing && !empty($billing_address)) {
            // FALL A: Abweichende Rechnungsadresse verwenden
            error_log('🔍 YPRINT FINAL: ANWENDEN - Separate Billing Address');
            $order->set_billing_first_name($billing_address['first_name'] ?? '');
            $order->set_billing_last_name($billing_address['last_name'] ?? '');
            $order->set_billing_address_1($billing_address['address_1'] ?? '');
            $order->set_billing_address_2($billing_address['address_2'] ?? '');
            $order->set_billing_city($billing_address['city'] ?? '');
            $order->set_billing_postcode($billing_address['postcode'] ?? '');
            $order->set_billing_country($billing_address['country'] ?? 'DE');
            $order->set_billing_phone($billing_address['phone'] ?? '');
            // E-Mail wird NICHT überschrieben - bleibt aus Payment Method
            
            error_log('🔍 YPRINT FINAL: Billing gesetzt: ' . $billing_address['address_1'] . ', ' . $billing_address['city']);
        } else {
            // FALL B: Shipping-Adresse als Billing verwenden
            error_log('🔍 YPRINT FINAL: ANWENDEN - Shipping als Billing');
            $order->set_billing_first_name($selected_address['first_name'] ?? '');
            $order->set_billing_last_name($selected_address['last_name'] ?? '');
            $order->set_billing_address_1($selected_address['address_1'] ?? '');
            $order->set_billing_address_2($selected_address['address_2'] ?? '');
            $order->set_billing_city($selected_address['city'] ?? '');
            $order->set_billing_postcode($selected_address['postcode'] ?? '');
            $order->set_billing_country($selected_address['country'] ?? 'DE');
            $order->set_billing_phone($selected_address['phone'] ?? '');
            // E-Mail wird NICHT überschrieben - bleibt aus Payment Method
            
            error_log('🔍 YPRINT FINAL: Billing=Shipping gesetzt: ' . $selected_address['address_1'] . ', ' . $selected_address['city']);
        }
        
        error_log('🔍 YPRINT FINAL: FINALE AUTORITATIVE ADRESS-ANWENDUNG COMPLETE');
        error_log('🔍 YPRINT FINAL: ==========================================');
    }


/**
     * FINALER SCHUTZ-HOOK: Verhindert Überschreibung der YPrint-Adressen
     */
    public function final_address_protection_hook($order_id, $posted_data, $order) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        // Prüfe ob dieser Order YPrint-Adressen hatte
        $yprint_final_applied = $order->get_meta('_yprint_addresses_final_applied', true);
        
        if ($yprint_final_applied) {
            error_log('🔍 YPRINT PROTECTION: Finale YPrint-Adress-Kontrolle für Order #' . $order_id);
            
            // Re-apply YPrint addresses als absolute finale Maßnahme
            $this->apply_final_yprint_addresses($order);
            $order->save();
            
            error_log('🔍 YPRINT PROTECTION: YPrint-Adressen final geschützt');
        }
    }

/**
 * AJAX handler for processing payment methods (FINAL CORRECTED VERSION)
 */
public function ajax_process_payment_method() {
    error_log('=== YPRINT EXPRESS PAYMENT METHOD PROCESSING START ===');
    
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
    
    // **KRITISCHER SESSION-DATEN-BACKUP vor Payment Processing**
    // Verhindert Datenverlust durch konkurrierende Systeme
    error_log('🔍 YPRINT SESSION BACKUP: ==========================================');
    error_log('🔍 YPRINT SESSION BACKUP: Securing session data before payment processing');
    $backup_selected = WC()->session->get('yprint_selected_address');
    $backup_billing = WC()->session->get('yprint_billing_address');     
    $backup_billing_different = WC()->session->get('yprint_billing_address_different');
        
    // Session-Backup-Status protokollieren
    error_log('🔍 YPRINT SESSION BACKUP: Selected exists: ' . (!empty($backup_selected) ? 'YES' : 'NO'));
    error_log('🔍 YPRINT SESSION BACKUP: Billing exists: ' . (!empty($backup_billing) ? 'YES' : 'NO'));
    error_log('🔍 YPRINT SESSION BACKUP: Billing different: ' . ($backup_billing_different ? 'YES' : 'NO'));
        
    if (!empty($backup_selected)) {        
        error_log('🔍 YPRINT SESSION BACKUP: Selected address secured: ' . $backup_selected['address_1'] . ', ' . $backup_selected['city']);
    }    
    if (!empty($backup_billing) && $backup_billing_different) {        
        error_log('🔍 YPRINT SESSION BACKUP: Billing address secured: ' . $backup_billing['address_1'] . ', ' . $backup_billing['city']);
    }    
    error_log('🔍 YPRINT SESSION BACKUP: ==========================================');

    // Debug WooCommerce availability (this line was moved down from the original position
    // to keep the WC_VERSION check if still desired)
    error_log('WooCommerce version: ' . (defined('WC_VERSION') ? WC_VERSION : 'Not defined'));

    // Vereinfachte Express Payment Debug-Ausgabe
    error_log('EXPRESS PAYMENT: Design data check...');
            
    if (WC()->cart && !WC()->cart->is_empty()) {
        $design_items = 0;
        $cart_debug = array();
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $has_design = isset($cart_item['print_design']);
            if ($has_design) {
                $design_items++;
                $cart_debug[] = $cart_item_key . ' ✓';
                
                // Sichere nur wenn nötig
                if (!WC()->session->get('yprint_express_design_backup')) {
                    $existing_backup = WC()->session->get('yprint_express_design_backup', array());
                    $existing_backup[$cart_item_key] = $cart_item['print_design'];
                    WC()->session->set('yprint_express_design_backup', $existing_backup);
                }
            } else {
                $cart_debug[] = $cart_item_key . ' ✗';
            }
        }
        
        error_log("EXPRESS: $design_items design items found [" . implode(', ', $cart_debug) . "]");
    } else {
        error_log('EXPRESS: Cart empty or unavailable');
    }


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
    error_log('POST Data: ' . print_r($_POST, true));
    error_log('Request Method: ' . $_SERVER['REQUEST_METHOD']);
    
    // Nonce Verification
    if (!isset($_POST['nonce'])) {
        error_log('ERROR: Nonce not provided in request');
        wp_send_json_error(array('message' => 'Nonce missing'));
        return;
    }
    
    $nonce_valid = wp_verify_nonce($_POST['nonce'], 'yprint_stripe_service_nonce');
    error_log('Nonce Verification: ' . ($nonce_valid ? 'VALID' : 'INVALID'));
    
    if (!$nonce_valid) {
        error_log('ERROR: Nonce verification failed');
        wp_send_json_error(array('message' => 'Invalid nonce'));
        return;
    }
    
    // Validate and sanitize boolean parameters to prevent "Invalid boolean: 1" error
    $source = isset($_POST['source']) ? sanitize_text_field($_POST['source']) : '';
    $type = isset($_POST['type']) ? sanitize_text_field($_POST['type']) : '';
    
    // Explicitly validate boolean parameters
    if (isset($_POST['capture_payment'])) {
        $capture_value = $_POST['capture_payment'];
        if (!in_array($capture_value, array('0', '1', 'true', 'false', 0, 1, true, false), true)) {
            error_log('ERROR: Invalid boolean value for capture_payment: ' . $capture_value);
            wp_send_json_error(array('message' => 'Invalid boolean: ' . $capture_value));
            return;
        }
    }
    
    if (isset($_POST['save_payment_method'])) {
        $save_value = $_POST['save_payment_method'];
        if (!in_array($save_value, array('0', '1', 'true', 'false', 0, 1, true, false), true)) {
            error_log('ERROR: Invalid boolean value for save_payment_method: ' . $save_value);
            wp_send_json_error(array('message' => 'Invalid boolean: ' . $save_value));
            return;
        }
    }
    
    // Payment Method Data - Fix URL encoding issues
    $payment_method_json = isset($_POST['payment_method']) ? wp_unslash($_POST['payment_method']) : '';
    error_log('=== PAYMENT METHOD DATA DEBUGGING ===');
    error_log('Payment Method JSON length: ' . strlen($payment_method_json));

    // Try different decoding approaches
    if (empty($payment_method_json)) {
        error_log('ERROR: Payment method data is empty');
        wp_send_json_error(array('message' => 'Payment method data missing'));
        return;
    }

    // Method 1: Direct decode
    $payment_method = json_decode($payment_method_json, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Method 1 failed - JSON error: ' . json_last_error_msg());
        
        // Method 2: Strip slashes then decode
        $stripped_json = stripslashes($payment_method_json);
        $payment_method = json_decode($stripped_json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Method 2 failed - JSON error after stripslashes: ' . json_last_error_msg());
            wp_send_json_error(array('message' => 'Invalid payment method data - JSON decode failed: ' . json_last_error_msg()));
            return;
        } else {
            error_log('Method 2 SUCCESS - stripslashes worked');
        }
    } else {
        error_log('Method 1 SUCCESS - direct decode worked');
    }

    // Validate payment method structure
    if (!is_array($payment_method) || !isset($payment_method['id'])) {
        error_log('ERROR: Invalid payment method structure');
        wp_send_json_error(array('message' => 'Invalid payment method structure'));
        return;
    }

    error_log('Payment method validation PASSED');
    error_log('Payment Method ID: ' . $payment_method['id']);
        
    // Shipping Address Data
    $shipping_address_json = isset($_POST['shipping_address']) ? wp_unslash($_POST['shipping_address']) : '';
    $shipping_address = null;
    if (!empty($shipping_address_json)) {
        $shipping_address = json_decode($shipping_address_json, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $shipping_address = json_decode(stripslashes($shipping_address_json), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('WARNING: Shipping address JSON decode error: ' . json_last_error_msg());
            }
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
    error_log('WC Cart Available: ' . (WC()->cart ? 'Yes' : 'No'));

    if (WC()->cart) {
        error_log('Cart Items Count: ' . WC()->cart->get_cart_contents_count());
        error_log('Cart Is Empty: ' . (WC()->cart->is_empty() ? 'Yes' : 'No'));
    } else {
        error_log('ERROR: Cart could not be initialized');
        wp_send_json_error(array('message' => 'Cart initialization failed'));
        return;
    }

    // Cart Fallback
    if (WC()->cart->is_empty()) {
        error_log('Cart is empty - checking for session cart data');
        
        // Try to restore cart from session
        $session_cart = WC()->session->get('cart', null);
        if ($session_cart) {
            error_log('Found session cart data');
            WC()->cart->set_session(WC()->session);
            WC()->cart->get_cart_from_session();
        } else {
            error_log('No session cart data found');
            
            // For Express checkout, proceed without cart validation
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
    
    // Create actual WooCommerce order for Express Checkout with design data transfer
    try {
        // Get customer email from different sources
        $customer_email = '';
        if (isset($payment_method['billing_details']['email']) && !empty($payment_method['billing_details']['email'])) {
            $customer_email = $payment_method['billing_details']['email'];
        } elseif (is_user_logged_in()) {
            $current_user = wp_get_current_user();
            $customer_email = $current_user->user_email;
        } else {
            // Fallback: Check for email in form data or session
            $checkout_data = WC()->session->get('yprint_checkout_address');
            if ($checkout_data && isset($checkout_data['billing_email'])) {
                $customer_email = $checkout_data['billing_email'];
            }
        }
        
        error_log('=== YPRINT EXPRESS ORDER CREATION ===');
        error_log('Kunden-E-Mail ermittelt: ' . $customer_email);
        
        // Create actual WooCommerce order
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            error_log('ERROR creating order: ' . $order->get_error_message());
            throw new Exception('Failed to create order: ' . $order->get_error_message());
        }
        
        $order_id = $order->get_id();
        error_log('Created order with ID: ' . $order_id);
        
        // CRITICAL: Prüfe ob YPrint-Adressen bereits gesetzt wurden
        $yprint_addresses_set = $order->get_meta('_yprint_addresses_applied', true);

        if (!$yprint_addresses_set) {
            // Set customer details nur wenn YPrint-Adressen noch nicht angewendet wurden
            if (!empty($customer_email)) {
                $order->set_billing_email($customer_email);
            }

            if (isset($payment_method['billing_details']['name'])) {
                $name_parts = explode(' ', $payment_method['billing_details']['name']);
                $order->set_billing_first_name($name_parts[0]);
                if (count($name_parts) > 1) {
                    $order->set_billing_last_name(end($name_parts));
                }
            }
        } else {
            error_log('YPRINT DEBUG: Skipping Stripe address override - YPrint addresses already applied');
        }
        
        // Critical YPrint address application temporarily disabled - removing override logic
error_log('🔍 YPRINT: Order #' . $order->get_id() . ' - critical address application disabled during cleanup phase');
            
        
        
        // Set payment method
        $order->set_payment_method('yprint_stripe');
        $order->set_payment_method_title('Stripe Express');
        
        // Add cart items to order WITH design data transfer
        error_log('Adding cart items with design data transfer...');
        $design_transfers_success = 0;
        $design_transfers_failed = 0;
        
        if (!WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                error_log('Processing cart item: ' . $cart_item_key);
                
                // Add product to order
                $order_item = new WC_Order_Item_Product();
                $order_item->set_product($cart_item['data']);
                $order_item->set_quantity($cart_item['quantity']);
                $order_item->set_variation_id($cart_item['variation_id'] ?? 0);
                $order_item->set_subtotal($cart_item['line_subtotal']);
                $order_item->set_total($cart_item['line_total']);
                
                // === SICHERE DESIGN-BACKUP-ERSTELLUNG VOR CART-PROCESSING ===
                // Diese Backup-Erstellung muss VOR jeder Cart-Manipulation stehen!
                if (!isset($express_design_backup_created)) {
                    $express_design_backup_created = true;
                    error_log('🎯 EXPRESS SECURE: Creating fresh design backup from active cart');
                    
                    $active_design_backup = array();
                    $active_design_count = 0;
                    
                    foreach (WC()->cart->get_cart() as $backup_cart_key => $backup_cart_item) {
                        if (isset($backup_cart_item['print_design']) && !empty($backup_cart_item['print_design'])) {
                            $active_design_backup[$backup_cart_key] = array(
                                'design_data' => $backup_cart_item['print_design'],
                                'product_id' => $backup_cart_item['product_id'],
                                'variation_id' => $backup_cart_item['variation_id'] ?? 0,
                                'quantity' => $backup_cart_item['quantity'],
                                'line_subtotal' => $backup_cart_item['line_subtotal'],
                                'line_total' => $backup_cart_item['line_total'],
                                'secured_timestamp' => current_time('mysql')
                            );
                            $active_design_count++;
                            error_log('🎯 EXPRESS SECURE: Active backup created for: ' . $backup_cart_key . ' (Design ID: ' . ($backup_cart_item['print_design']['design_id'] ?? 'unknown') . ')');
                        }
                    }
                    
                    // Triple-Session-Backup mit aktiven Daten
                    if ($active_design_count > 0) {
                        WC()->session->set('yprint_express_design_backup_active', $active_design_backup);
                        WC()->session->set('yprint_express_design_backup_v2_active', $active_design_backup);
                        WC()->session->set('yprint_express_design_backup_v3_active', $active_design_backup);
                        error_log('🎯 EXPRESS SECURE: Active triple-backup created with ' . $active_design_count . ' design items');
                    }
                }
                
                // === INTELLIGENTE DESIGN DATA RECOVERY ===
                $design_data = null;
                $data_source = 'none';
                
                // PRIO 1: Direkter Cart-Zugriff (falls Cart noch aktiv)
                if (isset($cart_item['print_design']) && !empty($cart_item['print_design'])) {
                    $design_data = $cart_item['print_design'];
                    $data_source = 'direct_cart_access';
                    error_log('🎯 CART URL QUALITY DEBUG: Using direct cart design data for: ' . $cart_item_key);
                }
                // PRIO 2: Aktives Session-Backup (frisch erstellt)
                elseif (isset($active_design_backup[$cart_item_key]['design_data'])) {
                    $design_data = $active_design_backup[$cart_item_key]['design_data'];
                    $data_source = 'active_session_backup';
                    error_log('🎯 CART URL QUALITY DEBUG: Using active session backup for: ' . $cart_item_key);
                }
                // PRIO 3: Bestehende Session-Backups
                else {
                    $existing_backup_keys = array(
                        'yprint_express_design_backup_active',
                        'yprint_express_design_backup',
                        'yprint_express_design_backup_v2',
                        'yprint_express_design_backup_v3'
                    );
                    
                    foreach ($existing_backup_keys as $backup_key) {
                        $existing_backup = WC()->session->get($backup_key, array());
                        if (isset($existing_backup[$cart_item_key])) {
                            // Flexible Datenstruktur-Behandlung
                            if (isset($existing_backup[$cart_item_key]['design_data'])) {
                                $design_data = $existing_backup[$cart_item_key]['design_data'];
                            } elseif (is_array($existing_backup[$cart_item_key]) && isset($existing_backup[$cart_item_key]['design_id'])) {
                                $design_data = $existing_backup[$cart_item_key];
                            }
                            
                            if (!empty($design_data)) {
                                $data_source = 'existing_backup_' . $backup_key;
                                error_log('🎯 CART URL QUALITY DEBUG: Recovered from existing backup: ' . $backup_key . ' for: ' . $cart_item_key);
                                break;
                            }
                        }
                    }
                }

                // DESIGN-TRANSFER MIT QUALITÄTSKONTROLLE
                if (!empty($design_data)) {
                    error_log('Found design data for cart item: ' . $cart_item_key . ' (Source: ' . $data_source . ')');
                    error_log('🔍 EXPRESS CHECKOUT: Processing cart item: ' . $cart_item_key);
                    error_log('🔍 Found design data for cart item: ' . $cart_item_key);
                    
                    // Use central design transfer function if available
                    if (function_exists('yprint_complete_design_transfer')) {
                        // Erstelle temporären Cart-Item für Transfer-Funktion
                        $temp_cart_item = $cart_item;
                        $temp_cart_item['print_design'] = $design_data;
                        
                        error_log('🎯 EXPRESS CHECKOUT: Calling yprint_complete_design_transfer for: ' . $cart_item_key);
                        $transfer_success = yprint_complete_design_transfer($order_item, $temp_cart_item, $cart_item_key);
                        
                        if ($transfer_success) {
                            $order_item->update_meta_data('_express_checkout_transfer', 'yes');
                            $order_item->update_meta_data('_yprint_design_transferred', current_time('mysql'));
                            $order_item->update_meta_data('_design_transfer_source', $data_source);
                            $design_transfers_success++;
                            error_log('🎯 EXPRESS SUCCESS: Design data transferred using central function for: ' . $cart_item_key);
                        } else {
                            error_log('🎯 EXPRESS FAILED: Central design transfer failed for: ' . $cart_item_key);
                            $design_transfers_failed++;
                        }
                    } else {
                        error_log('🎯 EXPRESS FALLBACK: Central function not available, using manual transfer for: ' . $cart_item_key);
                        // Fallback: manual design data transfer
                        if (is_array($design_data)) {
                            foreach ($design_data as $key => $value) {
                                $order_item->update_meta_data('_yprint_' . $key, $value);
                            }
                            $order_item->update_meta_data('_yprint_design_transferred', current_time('mysql'));
                            $order_item->update_meta_data('_design_transfer_source', $data_source);
                            $design_transfers_success++;
                            error_log('🎯 EXPRESS MANUAL: Design data transferred manually for: ' . $cart_item_key);
                        } else {
                            error_log('🎯 EXPRESS ERROR: Invalid design data format for: ' . $cart_item_key);
                            $design_transfers_failed++;
                        }
                    }
                } else {
                    error_log('🎯 EXPRESS MISSING: No design data found for cart item: ' . $cart_item_key);
                    $design_transfers_failed++;
                }
                
                // === VOLLSTÄNDIGE DESIGN-TRANSFER-LOGIK ===
                if (!empty($design_data)) {
                    error_log('🎯 EXPRESS CHECKOUT: Found design data for cart item: ' . $cart_item_key . ' (Source: ' . $data_source . ')');
                    
                    // Use central design transfer function if available
                    if (function_exists('yprint_complete_design_transfer')) {
                        // Erstelle vollständigen Cart-Item für Transfer-Funktion
                        $complete_cart_item = $cart_item;
                        $complete_cart_item['print_design'] = $design_data;
                        
                        error_log('🎯 EXPRESS CHECKOUT: Calling yprint_complete_design_transfer for: ' . $cart_item_key);
                        $transfer_success = yprint_complete_design_transfer($order_item, $complete_cart_item, $cart_item_key);
                        
                        if ($transfer_success) {
                            $order_item->update_meta_data('_express_checkout_transfer', 'yes');
                            $order_item->update_meta_data('_yprint_design_transferred', current_time('mysql'));
                            $order_item->update_meta_data('_design_transfer_source', $data_source);
                            $design_transfers_success++;
                            error_log('🎯 EXPRESS SUCCESS: Design data transferred using central function for: ' . $cart_item_key);
                        } else {
                            error_log('🎯 EXPRESS FAILED: Central design transfer failed for: ' . $cart_item_key);
                            $design_transfers_failed++;
                        }
                    } else {
                        error_log('🎯 EXPRESS FALLBACK: Central function not available, using manual transfer for: ' . $cart_item_key);
                        
                        // Fallback: manual design data transfer with validation
                        if (is_array($design_data) && !empty($design_data)) {
                            // Legacy format compatibility
                            $order_item->update_meta_data('print_design', $design_data);
                            $order_item->update_meta_data('_is_design_product', true);
                            $order_item->update_meta_data('_has_print_design', 'yes');
                            
                            // Individual design data fields
                            foreach ($design_data as $key => $value) {
                                $order_item->update_meta_data('_yprint_' . $key, $value);
                            }
                            
                            // Transfer metadata
                            $order_item->update_meta_data('_yprint_design_transferred', current_time('mysql'));
                            $order_item->update_meta_data('_design_transfer_source', $data_source);
                            $order_item->update_meta_data('_express_manual_transfer', 'yes');
                            
                            $design_transfers_success++;
                            error_log('🎯 EXPRESS MANUAL: Design data transferred manually for: ' . $cart_item_key);
                        } else {
                            error_log('🎯 EXPRESS ERROR: Invalid design data format for: ' . $cart_item_key . ' - Data: ' . print_r($design_data, true));
                            $design_transfers_failed++;
                        }
                    }
                } else {
                    error_log('🎯 EXPRESS MISSING: No design data found for cart item: ' . $cart_item_key . ' from any source');
                    $design_transfers_failed++;
                }
                
                // Add item to order
                $order->add_item($order_item);
            }
        }
        
        // === FINAL DESIGN-TRANSFER-STATISTIK ===
        error_log('🎯 EXPRESS FINAL: Design transfers completed - Success: ' . $design_transfers_success . ', Failed: ' . $design_transfers_failed);
        
        // Design-Transfer-Status in Order Meta speichern
        $order->update_meta_data('_express_design_transfers_success', $design_transfers_success);
        $order->update_meta_data('_express_design_transfers_failed', $design_transfers_failed);
        $order->update_meta_data('_express_design_transfer_timestamp', current_time('mysql'));
        
        // Empty cart NACH der Design-Daten-Übertragung
        WC()->cart->empty_cart();
        error_log('EXPRESS CHECKOUT: Cart emptied after design transfer');

        // Calculate totals and save order
        $order->calculate_totals();
        $order->save();
        
        error_log('Order saved with ID: ' . $order_id);
        error_log('Design transfers - Success: ' . $design_transfers_success . ', Failed: ' . $design_transfers_failed);
        
        // CRITICAL: Create and confirm Payment Intent with corrected method
        error_log('=== CREATING PAYMENT INTENT ===');
        
        // Use the static method from YPrint_Stripe_API class instead of local closure
// The static method YPrint_Stripe_API::get_stripe_amount() will be used consistently
        
        // Convert boolean parameters correctly
        $capture_payment = isset($_POST['capture_payment']) ? wc_string_to_bool($_POST['capture_payment']) : true;
        $save_payment_method = isset($_POST['save_payment_method']) ? wc_string_to_bool($_POST['save_payment_method']) : false;
        
        $intent_data = array(
            'amount' => YPrint_Stripe_API::get_stripe_amount($order->get_total(), $order->get_currency()),
            'currency' => strtolower($order->get_currency()),
            'payment_method' => $payment_method['id'],
            'confirmation_method' => 'manual',
            'confirm' => true,  // Native boolean
            'capture_method' => $capture_payment ? 'automatic' : 'manual',
            'description' => sprintf('Order #%s from %s', $order->get_order_number(), get_bloginfo('name')),
            'metadata' => array(
                'order_id' => (string) $order->get_id(),  // Ensure string for metadata
                'site_url' => get_site_url(),
            ),
            'receipt_email' => $order->get_billing_email(),
            // CRITICAL: Add return_url as required by Stripe (without automatic_payment_methods to avoid conflict)
            'return_url' => home_url('/checkout/?step=confirmation&order_id=' . $order->get_id())
        );
        
        // Add save payment method if requested
        if ($save_payment_method && is_user_logged_in()) {
            $intent_data['setup_future_usage'] = 'off_session';
        }

        // CRITICAL DEBUG: Validate all data before sending to Stripe
error_log('=== STRIPE PAYMENT INTENT DEBUG ===');
error_log('Order ID: ' . $order->get_id());
error_log('Order Total: ' . $order->get_total());
error_log('Order Currency: ' . $order->get_currency());
error_log('Stripe Amount: ' . YPrint_Stripe_API::get_stripe_amount($order->get_total(), $order->get_currency()));
error_log('Billing Email: ' . $order->get_billing_email());
error_log('Payment Method ID: ' . $payment_method['id']);
error_log('Return URL: ' . home_url('/checkout/?step=confirmation&order_id=' . $order->get_id()));
        
        // Validate critical fields
        if (empty($order->get_billing_email())) {
            error_log('WARNING: Billing email is empty');
            $order->set_billing_email('noreply@yprint.de'); // Fallback email
            $order->save();
        }
        
        if ($order->get_total() <= 0) {
            error_log('ERROR: Order total is zero or negative: ' . $order->get_total());
            wp_send_json_error(array('message' => 'Invalid order amount'));
            return;
        }
        
        // Determine payment method types based on PaymentMethod type
        $payment_method_types = ['card']; // Default
        if (isset($payment_method['type'])) {
            switch ($payment_method['type']) {
                case 'sepa_debit':
                    $payment_method_types = ['sepa_debit'];
                    break;
                case 'card':
                    $payment_method_types = ['card'];
                    break;
                default:
                    $payment_method_types = [$payment_method['type']];
            }
        }
        
        error_log('🚀 SEPA DEBUG: PaymentIntent will be created with payment_method_types: ' . wp_json_encode($payment_method_types));
        error_log('🚀 SEPA DEBUG: PaymentMethod type detected: ' . ($payment_method['type'] ?? 'unknown'));

        // Enhanced intent data with better validation and dynamic payment_method_types
// KORREKTUR: SEPA-optimierte PaymentIntent-Konfiguration
$is_sepa_payment = isset($payment_method['type']) && $payment_method['type'] === 'sepa_debit';

$intent_data = array(
    'amount' => YPrint_Stripe_API::get_stripe_amount($order->get_total(), $order->get_currency()),
    'currency' => strtolower($order->get_currency()),
    'payment_method_types' => $payment_method_types,
    'payment_method' => $payment_method['id'],
    // KORREKTUR: SEPA muss confirm=true haben wegen mandate_data Requirement
    'confirmation_method' => 'automatic',
    'confirm' => true, // Beide Payment-Arten benötigen confirm=true für mandate_data
    'capture_method' => 'automatic',
    'description' => sprintf('YPrint Order #%s - %s', $order->get_order_number(), get_bloginfo('name')),
    'metadata' => array(
        'order_id' => (string) $order->get_id(),
        'order_number' => $order->get_order_number(),
        'site_url' => get_site_url(),
        'customer_email' => $order->get_billing_email(),
        'payment_method_type' => $payment_method['type'] ?? 'unknown',
    ),
    'receipt_email' => $order->get_billing_email(),
    'payment_method_options' => array(
        'card' => array(
            'request_three_d_secure' => 'automatic'
        )
    ) 
);

// return_url für beide Payment-Arten setzen
$intent_data['return_url'] = home_url('/checkout/?step=confirmation&order_id=' . $order->get_id());

// SEPA-spezifische Erweiterungen für Mandat
if (isset($payment_method['type']) && $payment_method['type'] === 'sepa_debit') {
    error_log('🚀 SEPA MANDATE: Adding mandate_data for SEPA payment');
    
    // SEPA-Mandat-Daten hinzufügen (EU-Recht konform)
    $intent_data['mandate_data'] = array(
        'customer_acceptance' => array(
            'type' => 'online',
            'online' => array(
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'YPrint Checkout'
            )
        )
    );
    
    // SEPA-spezifische Payment Method Options
    $intent_data['payment_method_options']['sepa_debit'] = array(
        'mandate_options' => array()
    );
    
    error_log('🚀 SEPA MANDATE: mandate_data added: ' . wp_json_encode($intent_data['mandate_data']));
}
        
        // Debug: Log complete intent data
        error_log('Complete Intent data being sent to Stripe: ' . wp_json_encode($intent_data));
        
        // Create and confirm Payment Intent
        $intent = YPrint_Stripe_API::request($intent_data, 'payment_intents');

        if (!empty($intent->error)) {
            error_log('Payment Intent creation failed: ' . $intent->error->message);
            error_log('Intent data that caused error: ' . wp_json_encode($intent_data));
            
            // Check for specific test card declined error
            $error_message = $intent->error->message;
            if (strpos($error_message, 'card was declined') !== false) {
                $error_message = 'Payment was declined. Please try with a different card or contact your bank. (This might be due to using test card 4000000000003012 which simulates declines)';
            }
            
            wp_send_json_error(array('message' => $error_message));
            return;
        }

        // Debug: Check if this is a test card that simulates decline
if (isset($payment_method['card']['last4']) && $payment_method['card']['last4'] === '3012') {
    error_log('WARNING: Using test card 4000000000003012 which always declines. Use 4242424242424242 for successful test payments.');
}

        error_log('Payment Intent created: ' . $intent->id . ' with status: ' . $intent->status);

        // KORREKTUR: SEPA "processing" Status als Erfolg behandeln
if ('succeeded' === $intent->status) {
    // Sofortiger Erfolg - bei Cards
    $order->payment_complete($intent->id);
    $order->add_order_note(sprintf(__('Stripe payment completed (Payment Intent ID: %s)', 'yprint-plugin'), $intent->id));
    $order->set_transaction_id($intent->id);
    $order->save();
    error_log('Payment completed for order: ' . $order->get_id());
    
} elseif ('processing' === $intent->status) {
    // SEPA oder andere asynchrone Zahlungen in Bearbeitung
    if ($is_sepa_payment) {
        // SEPA: "processing" ist der normale Erfolgsstatus
        $order->update_status('processing', sprintf(__('SEPA payment confirmed and processing (Payment Intent ID: %s)', 'yprint-plugin'), $intent->id));
        $order->set_transaction_id($intent->id);
        $order->add_order_note(__('SEPA payment successfully initiated. The payment is being processed and will be completed automatically.', 'yprint-plugin'));
        $order->save();
        error_log('SEPA payment successfully initiated for order: ' . $order->get_id());
        
        // WICHTIG: Als Erfolg zurückgeben für Frontend mit korrekter Redirect URL
        $confirmation_url = add_query_arg(array(
            'step' => 'confirmation',
            'order_id' => $order->get_id()
        ), get_permalink());
        
        // Erstelle order_data wie bei Express Payments für Konsistenz
        $order_data = array(
            'order_id' => $order->get_id(),
            'payment_method_id' => $payment_method['id'],
            'payment_intent_id' => $intent->id,
            'amount' => $order->get_total(),
            'currency' => get_woocommerce_currency(),
            'customer_details' => array(
                'name' => $payment_method['billing_details']['name'] ?? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $payment_method['billing_details']['email'] ?? $order->get_billing_email(),
                'phone' => $payment_method['billing_details']['phone'] ?? $order->get_billing_phone(),
            ),
            'billing_address' => $order->get_billing_address(),
            'shipping_address' => $order->get_shipping_address(),
            // KRITISCH: payment_method_details für Frontend-Konsistenz
            'payment_method_details' => array(
                'type' => $payment_method['type'] ?? 'sepa_debit',
                'sepa_debit' => array(
                    'last4' => $payment_method['sepa_debit']['last4'] ?? '',
                    'bank_code' => $payment_method['sepa_debit']['bank_code'] ?? '',
                    'country' => $payment_method['sepa_debit']['country'] ?? 'DE'
                )
            )
        );

        wp_send_json_success(array(
            'next_step' => 'confirmation',
            'order_id' => $order->get_id(),
            'payment_status' => 'processing',
            'redirect_url' => $confirmation_url,
            'message' => __('SEPA payment initiated successfully', 'yprint-plugin'),
            'order_data' => $order_data,  // HINZUGEFÜGT: Gleiche Struktur wie Express Payments
            'payment_method_id' => $payment_method['id'],
            'payment_intent_id' => $intent->id
        ));
        return;
    } else {
        // Andere Payment-Arten: processing als pending behandeln
        $order->update_status('pending-payment', sprintf(__('Payment is being processed (Payment Intent ID: %s)', 'yprint-plugin'), $intent->id));
        $order->set_transaction_id($intent->id);
        $order->add_order_note(__('Payment initiated. Waiting for final confirmation.', 'yprint-plugin'));
        $order->save();
        error_log('Payment initiated and pending for order: ' . $order->get_id());
        
        // Erstelle order_data auch für Kreditkarten für Konsistenz
        $confirmation_url = add_query_arg(array(
            'step' => 'confirmation',
            'order_id' => $order->get_id()
        ), get_permalink());
        
        $order_data = array(
            'order_id' => $order->get_id(),
            'payment_method_id' => $payment_method['id'],
            'payment_intent_id' => $intent->id,
            'amount' => $order->get_total(),
            'currency' => get_woocommerce_currency(),
            'customer_details' => array(
                'name' => $payment_method['billing_details']['name'] ?? $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'email' => $payment_method['billing_details']['email'] ?? $order->get_billing_email(),
                'phone' => $payment_method['billing_details']['phone'] ?? $order->get_billing_phone(),
            ),
            'billing_address' => $payment_method['billing_details']['address'] ?? array(),
            'shipping_address' => array(),
            // KRITISCH: payment_method_details für Frontend-Konsistenz
            'payment_method_details' => array(
                'type' => $payment_method['type'] ?? 'card',
                'card' => array(
                    'last4' => $payment_method['card']['last4'] ?? '',
                    'brand' => $payment_method['card']['brand'] ?? 'unknown',
                    'exp_month' => $payment_method['card']['exp_month'] ?? '',
                    'exp_year' => $payment_method['card']['exp_year'] ?? ''
                )
            )
        );

        wp_send_json_success(array(
            'next_step' => 'confirmation',
            'order_id' => $order->get_id(),
            'payment_status' => 'pending',
            'redirect_url' => $confirmation_url,
            'message' => __('Card payment initiated successfully', 'yprint-plugin'),
            'order_data' => $order_data,  // HINZUGEFÜGT: Gleiche Struktur wie Express Payments
            'payment_method_id' => $payment_method['id'],
            'payment_intent_id' => $intent->id
        ));
        return;
    }
    
} elseif ('requires_action' === $intent->status || 'requires_source_action' === $intent->status) {
    // 3D Secure oder weitere Aktionen erforderlich
    error_log('Payment requires additional action: ' . $intent->status);
    wp_send_json_error(array(
        'message' => 'Additional authentication required',
        'requires_action' => true,
        'payment_intent_client_secret' => $intent->client_secret
    ));
    return;
    
} else {
    // Alle anderen Status sind echte Fehler
    error_log('Payment Intent failed with status: ' . $intent->status);
    wp_send_json_error(array('message' => 'Payment Intent confirmation failed: ' . $intent->status));
    return;
}
        
        // Create order data for session storage
        $order_data = array(
            'order_id' => $order_id,
            'payment_method_id' => $payment_method['id'],
            'payment_intent_id' => $intent->id,
            'amount' => $order->get_total(),
            'currency' => get_woocommerce_currency(),
            'customer_details' => array(
                'name' => $payment_method['billing_details']['name'] ?? 'Test Kunde',
                'email' => $customer_email,
                'phone' => $payment_method['billing_details']['phone'] ?? '',
            ),
            'billing_address' => $payment_method['billing_details']['address'] ?? array(),
            'shipping_address' => $shipping_address ?? array(),
            'payment_method_details' => $payment_method, // HINZUGEFÜGT: Vollständige Payment Method Details mit Wallet-Info
            'design_transfers_success' => $design_transfers_success,
            'design_transfers_failed' => $design_transfers_failed,
            'status' => 'completed',
            'simple_order_id' => 'YP-' . $order_id
        );
        
        // Store order data in session for confirmation page
        WC()->session->set('yprint_pending_order', $order_data);
        WC()->session->set('yprint_last_order_id', $order_id);
        
        // Clear cart after successful payment
        if (WC()->cart && !WC()->cart->is_empty()) {
            error_log('Clearing cart after successful checkout: ' . $order_id);
            
            try {
                WC()->cart->empty_cart();
                error_log('Cart successfully cleared for order: ' . $order_id);
            } catch (Exception $e) {
                error_log('ERROR clearing cart: ' . $e->getMessage());
            }
        }
        
        // Store simple order ID for display
        $simple_order_id = 'YP-' . $order_id;
        WC()->session->set('yprint_last_order_id', $simple_order_id);
        $order_data['simple_order_id'] = $simple_order_id;
        
        error_log('Order creation successful for payment method: ' . $payment_method['id']);
        
        // ===== ROBUSTE ORCHESTRATOR INTEGRATION MIT CONSOLE DEBUGGING (CHECKOUT) =====
// Für AJAX Responses müssen wir die Console Logs in die JSON Response einbetten
$debug_logs = [];
$debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): Starting AddressOrchestrator integration for Order #' . $order_id;
$debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): Class exists check: ' . (class_exists('YPrint_Address_Orchestrator') ? 'TRUE' : 'FALSE');

if (class_exists('YPrint_Address_Orchestrator')) {
    $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): Getting orchestrator instance...';
    
    try {
        $orchestrator = YPrint_Address_Orchestrator::get_instance();
        $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): Instance retrieved: ' . (is_object($orchestrator) ? 'SUCCESS' : 'FAILED');
        
        // Reload order for orchestrator processing
        $final_order = wc_get_order($order_id);
        $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): Order reload: ' . ($final_order ? 'SUCCESS' : 'FAILED');
        
        if ($final_order && is_object($orchestrator)) {
            // Extract payment method data if available
            $payment_method_data = null;
            if (isset($payment_method) && is_array($payment_method)) {
                $payment_method_data = $payment_method;
                $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): Payment method data available: ' . implode(', ', array_keys($payment_method));
            } else {
                $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): No payment method data available';
            }
            
            // Method existence check
            if (method_exists($orchestrator, 'orchestrate_addresses_for_order')) {
                $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): Method orchestrate_addresses_for_order EXISTS - calling now...';
                
                // CALL ORCHESTRATOR
                $result = $orchestrator->orchestrate_addresses_for_order($final_order, $payment_method_data);
                
                $debug_logs[] = '🔍 POST-ORCHESTRATOR (CHECKOUT): Orchestrator returned: ' . ($result ? 'SUCCESS' : 'FAILED');
                
                // Save order after orchestrator processing
                $final_order->save();
                $debug_logs[] = '🔍 POST-ORCHESTRATOR (CHECKOUT): Order saved after orchestrator processing';
                
                $debug_logs[] = '🎯 AddressOrchestrator: Stripe Checkout processed for Order #' . $order_id;
            } else {
                $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): ERROR - Method orchestrate_addresses_for_order NOT FOUND';
            }
        } else {
            $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): ERROR - Order reload or orchestrator instance failed';
        }
        
    } catch (Exception $e) {
        $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): EXCEPTION during orchestrator processing: ' . $e->getMessage();
    }
} else {
    $debug_logs[] = '🔍 PRE-ORCHESTRATOR (CHECKOUT): AddressOrchestrator class NOT AVAILABLE';
}

$debug_logs[] = '🔍 POST-ORCHESTRATOR (CHECKOUT): Integration attempt completed for Order #' . $order_id;

// Für AJAX: Debug logs in die Success Response einbetten, damit sie im Frontend per Console ausgegeben werden können
        
        // Return success with payment intent ID und Redirect URL
        $confirmation_url = add_query_arg(array(
            'step' => 'confirmation',
            'order_id' => $order_id
        ), get_permalink());
        
        wp_send_json_success(array(
            'message' => 'Express order created and payment confirmed',
            'payment_method_id' => $payment_method['id'],
            'payment_intent_id' => $intent->id,
            'order_id' => $order_id,
            'order_data' => $order_data,
            'next_step' => 'confirmation',
            'redirect_url' => $confirmation_url,
            'design_transfers' => array(
                'success' => $design_transfers_success,
                'failed' => $design_transfers_failed
            ),
            'test_mode' => YPrint_Stripe_API::is_testmode(),
            'debug_logs' => $debug_logs
        ));
        
        } catch (Exception $e) {
            error_log('Payment processing error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ));
        }}





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

/**
 * Erstellt eine Test-Bestellung für E-Mail-Debugging
 *
 * @param array $order_data Die Bestelldaten
 * @return int|false Die Order ID oder false bei Fehler
 */
function yprint_create_test_order_for_email($order_data) {
    error_log('=== CREATE TEST ORDER FOR EMAIL DEBUG ===');
    
    try {
        // Erstelle neue WooCommerce Bestellung
        $order = wc_create_order();
        
        if (is_wp_error($order)) {
            error_log('FEHLER beim Erstellen der Test-Bestellung: ' . $order->get_error_message());
            return false;
        }
        
        error_log('Test-Bestellung Objekt erstellt, ID: ' . $order->get_id());
        
        // Setze Kunden-E-Mail wenn vorhanden
        if (isset($order_data['customer_details']['email']) && !empty($order_data['customer_details']['email'])) {
            $order->set_billing_email($order_data['customer_details']['email']);
            error_log('Kunden-E-Mail gesetzt: ' . $order_data['customer_details']['email']);
        } else {
            // Versuche E-Mail von verschiedenen Quellen zu ermitteln
            $customer_email = '';
            
            if (is_user_logged_in()) {
                $current_user = wp_get_current_user();
                $customer_email = $current_user->user_email;
                error_log('E-Mail von eingeloggtem Benutzer: ' . $customer_email);
            } else {
                // Fallback: Prüfe Session-Daten
                $checkout_data = WC()->session->get('yprint_checkout_address');
                if ($checkout_data && isset($checkout_data['billing_email'])) {
                    $customer_email = $checkout_data['billing_email'];
                    error_log('E-Mail aus Session-Daten: ' . $customer_email);
                }
            }
            
            if (!empty($customer_email)) {
                $order->set_billing_email($customer_email);
                error_log('Ermittelte Kunden-E-Mail gesetzt: ' . $customer_email);
            } else {
                // Nur als absoluter Fallback - sollte nicht verwendet werden
                $order->set_billing_email('test@yprint.de');
                error_log('WARNUNG: Fallback E-Mail gesetzt da keine Kunden-E-Mail gefunden: test@yprint.de');
            }
        }
        
        // Setze Kundenname wenn vorhanden
        if (isset($order_data['customer_details']['name'])) {
            $name_parts = explode(' ', $order_data['customer_details']['name']);
            $order->set_billing_first_name($name_parts[0]);
            if (count($name_parts) > 1) {
                $order->set_billing_last_name(end($name_parts));
            }
            error_log('Kundenname gesetzt: ' . $order_data['customer_details']['name']);
        } else {
            $order->set_billing_first_name('Test');
            $order->set_billing_last_name('Kunde');
            error_log('Fallback Kundenname gesetzt: Test Kunde');
        }
        
        // Setze Adressdaten wenn vorhanden
        if (isset($order_data['billing_address'])) {
            $billing_address = $order_data['billing_address'];
            $order->set_billing_address_1($billing_address['line1'] ?? 'Teststraße 1');
            $order->set_billing_city($billing_address['city'] ?? 'Berlin');
            $order->set_billing_postcode($billing_address['postal_code'] ?? '10115');
            $order->set_billing_country($billing_address['country'] ?? 'DE');
            error_log('Rechnungsadresse gesetzt');
        }
        
        // Füge Warenkorb-Artikel hinzu (falls vorhanden)
        if (!WC()->cart->is_empty()) {
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $order->add_product($cart_item['data'], $cart_item['quantity']);
                error_log('Produkt hinzugefügt: ' . $cart_item['data']->get_name());
            }
        } else {
            // Füge ein Dummy-Produkt hinzu für Test
            error_log('Warenkorb leer, füge Dummy-Produkt hinzu');
            $dummy_product = new WC_Product_Simple();
            $dummy_product->set_name('Test Produkt');
            $dummy_product->set_regular_price(10.00);
            $dummy_product->set_status('publish');
            $dummy_product->save();
            
            $order->add_product($dummy_product, 1);
            error_log('Dummy-Produkt erstellt und hinzugefügt');
        }
        
        // Setze Zahlungsmethode
$order->set_payment_method('yprint_stripe');
$title = YPrint_Stripe_API::is_testmode() ? 'Stripe (Test)' : 'Stripe';
$order->set_payment_method_title($title);
        
        // Setze Transaktions-ID wenn vorhanden
        if (isset($order_data['payment_method_id'])) {
            $order->set_transaction_id($order_data['payment_method_id']);
            error_log('Transaktions-ID gesetzt: ' . $order_data['payment_method_id']);
        }
        
        // Berechne Gesamtsumme
        $order->calculate_totals();
        
        // Speichere die Bestellung
        $order->save();
        
        error_log('Test-Bestellung erfolgreich erstellt mit ID: ' . $order->get_id());
        error_log('Test-Bestellung Gesamtsumme: ' . $order->get_total());
        error_log('Test-Bestellung Status: ' . $order->get_status());
        
        return $order->get_id();
        
    } catch (Exception $e) {
        error_log('EXCEPTION beim Erstellen der Test-Bestellung: ' . $e->getMessage());
        error_log('Exception Stack Trace: ' . $e->getTraceAsString());
        return false;
    }
}

/**
 * Zentrale Funktion für Design Data Transfer mit Print Provider E-Mail Kompatibilität
 *
 * @param WC_Order_Item_Product $order_item    The order item object.
 * @param array                 $cart_data     The cart item data. This can be either the full cart_item array or a $values array from normal checkout.
 * @param string                $cart_item_key The cart item key.
 * @return bool True if data was transferred, false otherwise.
 */
if (!function_exists('yprint_complete_design_transfer')) {
    function yprint_complete_design_transfer($order_item, $cart_data, $cart_item_key) {
        // UNIVERSAL PARAMETER HANDLING für Express und Normal Checkout
        $cart_item = $cart_data;

        // Wenn $cart_data von Normal Checkout kommt (ist $values), extrahiere cart_item
        // Ein $values-Array aus dem Normal-Checkout hat üblicherweise den 'data'-Schlüssel, aber nicht 'print_design' direkt.
        if (!isset($cart_data['print_design']) && isset($cart_data['data'])) {
            // Dies ist ein $values Array vom Normal Checkout, hole cart_item vom WC()->cart
            $current_cart = WC()->cart->get_cart();
            if (isset($current_cart[$cart_item_key])) {
                $cart_item = $current_cart[$cart_item_key];
                error_log('COMPLETE TRANSFER: Using cart item from WC()->cart for Normal Checkout');
            } else {
                error_log('COMPLETE TRANSFER: ERROR - Cannot find cart item for key: ' . $cart_item_key . ' in WC()->cart.');
                return false;
            }
        }

        if (!isset($cart_item['print_design']) || empty($cart_item['print_design'])) {
            error_log('COMPLETE TRANSFER: No print_design found in cart item for key: ' . $cart_item_key);
            return false;
        }

        $design_data = $cart_item['print_design'];
        error_log('COMPLETE TRANSFER: Processing design data for cart key: ' . $cart_item_key);

        // ---
        // ## LEGACY FORMAT (für Kompatibilität)
        // ---

        $order_item->update_meta_data('print_design', $design_data);
        $order_item->update_meta_data('_is_design_product', true);
        $order_item->update_meta_data('_has_print_design', 'yes');
        $order_item->update_meta_data('_cart_item_key', $cart_item_key);
        $order_item->update_meta_data('_yprint_design_transferred', current_time('mysql'));

        // ---
        // ## PRINT PROVIDER E-MAIL FORMAT
        // ---

        // Basis Design-Daten
        $order_item->update_meta_data('design_id', $design_data['design_id'] ?? '');
        $order_item->update_meta_data('name', $design_data['name'] ?? '');
        $order_item->update_meta_data('template_id', $design_data['template_id'] ?? '');
        $order_item->update_meta_data('variation_id', $design_data['variation_id'] ?? '');
        $order_item->update_meta_data('size_id', $design_data['size_id'] ?? '');
        $order_item->update_meta_data('preview_url', $design_data['preview_url'] ?? '');

        // Dimensionen
        $order_item->update_meta_data('width_cm', $design_data['width_cm'] ?? $design_data['width'] ?? '25.4');
        $order_item->update_meta_data('height_cm', $design_data['height_cm'] ?? $design_data['height'] ?? '30.2');

        // ---
        // ## INTELLIGENTE DESIGN IMAGE URL AUSWAHL MIT DATENBANKPRIORITÄT
        // ---

        $final_design_url = '';
        $url_source = 'none';

        // PRIORITÄT 1: Finale Design-URL aus der Datenbank
        if (!empty($design_data['design_id'])) {
            error_log('COMPLETE TRANSFER: Integrating database design data for ID: ' . $design_data['design_id']);

            global $wpdb;
            $design_id = intval($design_data['design_id']);

            // Hole vollständige Design-Daten aus der Datenbank
            // Nutze die korrekte Tabellenpräfix-Variable für WordPress
            $db_design = $wpdb->get_row($wpdb->prepare(
                "SELECT id, user_id, template_id, name, design_data, created_at, product_name, product_description
                 FROM {$wpdb->prefix}octo_user_designs
                 WHERE id = %d",
                $design_id
            ), ARRAY_A); // ARRAY_A gibt ein assoziatives Array zurück

            if ($db_design) {
                error_log('COMPLETE TRANSFER: Database design found, processing...');

                // Parse JSON design_data
                $parsed_design_data = json_decode($db_design['design_data'], true);

                if (json_last_error() === JSON_ERROR_NONE && $parsed_design_data) {
                    // Erweiterte Datenbank-Meta-Daten
                    $order_item->update_meta_data('_db_design_template_id', $db_design['template_id']);
                    $order_item->update_meta_data('_db_design_user_id', $db_design['user_id']);
                    $order_item->update_meta_data('_db_design_created_at', $db_design['created_at']);
                    $order_item->update_meta_data('_db_design_product_name', $db_design['product_name']);
                    $order_item->update_meta_data('_db_design_product_description', $db_design['product_description']);

                    // KRITISCH: Suche nach finaler Design-URL in der Datenbank
                    // Versuche verschiedene JSON-Felder für finale Design-Datei
                    if (!empty($parsed_design_data['final_design_url'])) {
                        $final_design_url = $parsed_design_data['final_design_url'];
                        $url_source = 'database_final_design_url';
                    } elseif (!empty($parsed_design_data['print_ready_url'])) {
                        $final_design_url = $parsed_design_data['print_ready_url'];
                        $url_source = 'database_print_ready_url';
                    } elseif (!empty($parsed_design_data['high_res_url'])) {
                        $final_design_url = $parsed_design_data['high_res_url'];
                        $url_source = 'database_high_res_url';
                    } elseif (!empty($parsed_design_data['design_image_url'])) {
                        $final_design_url = $parsed_design_data['design_image_url'];
                        $url_source = 'database_design_image_url';
                    } elseif (!empty($parsed_design_data['original_url'])) { // Zusätzlicher Fallback auf original_url aus der DB
                        $final_design_url = $parsed_design_data['original_url'];
                        $url_source = 'database_original_url';
                    }

                    error_log('COMPLETE TRANSFER: Database URL search result - Source: ' . $url_source . ', URL: ' . $final_design_url);

                    // Vollständige JSON-Daten speichern
                    $order_item->update_meta_data('_db_design_raw_json', $db_design['design_data']);
                    $order_item->update_meta_data('_db_design_parsed_data', wp_json_encode($parsed_design_data));

                    // Template Info
                    if (isset($parsed_design_data['templateId'])) {
                        $order_item->update_meta_data('_db_template_id', $parsed_design_data['templateId']);
                    }
                    if (isset($parsed_design_data['currentVariation'])) {
                        $order_item->update_meta_data('_db_current_variation', $parsed_design_data['currentVariation']);
                    }

                    // Verarbeite variationImages für detaillierte View-Daten
                    if (isset($parsed_design_data['variationImages'])) {
                        foreach ($parsed_design_data['variationImages'] as $variation_key => $variation_images) {
                            if (is_array($variation_images) && isset($variation_images['views'])) {
                                foreach ($variation_images['views'] as $view_name => $view_data) {
                                    if (isset($view_data['layers']) && is_array($view_data['layers'])) {
                                        foreach ($view_data['layers'] as $layer_index => $layer) {
                                            if (isset($layer['printAreas']) && is_array($layer['printAreas'])) {
                                                foreach ($layer['printAreas'] as $area_index => $print_area) {
                                                    $area_key = "variation_{$variation_key}_view_{$view_name}_layer_{$layer_index}_area_{$area_index}";
                                                    $order_item->update_meta_data("print_area_{$area_key}", wp_json_encode($print_area));
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    error_log('COMPLETE TRANSFER: Merged database design data with cart data for display.');
                    // Merge database data with cart data (database takes priority for relevant fields)
                    // Beachten Sie, dass $design_data selbst überschrieben wird, um die Datenbankdaten zu priorisieren
                    $design_data = array_merge($design_data, $parsed_design_data);
                    error_log('COMPLETE TRANSFER: Database integration completed successfully');
                } else {
                    error_log('COMPLETE TRANSFER: Failed to parse JSON design data from database for ID: ' . $design_id);
                }
            } else {
                error_log('COMPLETE TRANSFER: No database design found for ID: ' . $design_data['design_id']);
            }
        }

        // PRIORITÄT 2-4: Fallback auf Cart-Daten wenn Datenbank keine URL liefert
        if (empty($final_design_url)) {
            // Priorität 2: Explizite design_image_url aus Cart-Daten
            if (!empty($design_data['design_image_url'])) {
                $final_design_url = $design_data['design_image_url'];
                $url_source = 'cart_design_image_url';
            }
            // Priorität 3: original_url als Fallback
            elseif (!empty($design_data['original_url'])) {
                $final_design_url = $design_data['original_url'];
                $url_source = 'cart_original_url';
            }
            // Priorität 4: preview_url als letzter Ausweg (mit Warnung)
            elseif (!empty($design_data['preview_url'])) {
                $final_design_url = $design_data['preview_url'];
                $url_source = 'cart_preview_url_fallback';
                error_log('COMPLETE TRANSFER WARNING: Using preview_url as design_image_url - may not be print-ready quality!');
            }
        }

        // Design Image URL mit intelligenter Quelle setzen
        $order_item->update_meta_data('design_image_url', $final_design_url);
        $order_item->update_meta_data('_design_url_source', $url_source);

        error_log('COMPLETE TRANSFER: Final design URL selected from source "' . $url_source . '": ' . $final_design_url);

        // ---
        // ## Product Images (JSON)
        // ---

        if (isset($design_data['product_images']) && !empty($design_data['product_images'])) {
            $product_images_json = is_string($design_data['product_images']) ?
                $design_data['product_images'] :
                wp_json_encode($design_data['product_images']);
            $order_item->update_meta_data('product_images', $product_images_json);
        } else {
            // Fallback Product Images
            $product_images = array(
                array(
                    'url' => $design_data['preview_url'] ?? '',
                    'view_name' => 'Front View',
                    'view_id' => 'front',
                    'width_cm' => $design_data['width_cm'] ?? $design_data['width'] ?? '25.4',
                    'height_cm' => $design_data['height_cm'] ?? $design_data['height'] ?? '30.2'
                )
            );
            $order_item->update_meta_data('product_images', wp_json_encode($product_images));
        }

        // ---
        // ## Design Images (JSON)
        // ---

        if (isset($design_data['design_images']) && !empty($design_data['design_images'])) {
            $design_images_json = is_string($design_data['design_images']) ?
                $design_data['design_images'] :
                wp_json_encode($design_data['design_images']);
            $order_item->update_meta_data('design_images', $design_images_json);
        } else {
            // Fallback Design Images
            $design_images = array(
                array(
                    'url' => $design_data['design_image_url'] ?? $design_data['original_url'] ?? '',
                    'scaleX' => $design_data['scaleX'] ?? 1,
                    'scaleY' => $design_data['scaleY'] ?? 1,
                    'width_cm' => $design_data['width_cm'] ?? $design_data['width'] ?? '25.4',
                    'height_cm' => $design_data['height_cm'] ?? $design_data['height'] ?? '30.2',
                    'view_name' => 'Front Design'
                )
            );
            $order_item->update_meta_data('design_images', wp_json_encode($design_images));
        }

        // ---
        // ## MULTIPLE IMAGES FLAG
        // ---

        $has_multiple_images = false;
        if (isset($design_data['product_images'])) {
            $images = is_string($design_data['product_images']) ?
                json_decode($design_data['product_images'], true) :
                $design_data['product_images'];
            $has_multiple_images = is_array($images) && count($images) > 1;
        }
        // Also consider the explicit 'has_multiple_images' flag if present
        $has_multiple_images = $design_data['has_multiple_images'] ?? $has_multiple_images;
        $order_item->update_meta_data('has_multiple_images', $has_multiple_images);


        // ---
        // ## DISPLAY META-DATEN FÜR ADMIN
        // ---

        $order_item->update_meta_data('_design_id', $design_data['design_id'] ?? '');
        $order_item->update_meta_data('_design_name', $design_data['name'] ?? '');
        $order_item->update_meta_data('_design_color', $design_data['variation_name'] ?? '');
        $order_item->update_meta_data('_design_size', $design_data['size_name'] ?? '');
        $order_item->update_meta_data('_design_preview_url', $design_data['preview_url'] ?? '');
        $order_item->update_meta_data('_design_width_cm', $order_item->get_meta('width_cm'));
        $order_item->update_meta_data('_design_height_cm', $order_item->get_meta('height_cm'));
        $order_item->update_meta_data('_design_image_url', $order_item->get_meta('design_image_url'));
        $order_item->update_meta_data('_design_has_multiple_images', $has_multiple_images);
        $order_item->update_meta_data('_design_product_images', $order_item->get_meta('product_images'));
        $order_item->update_meta_data('_design_images', $order_item->get_meta('design_images'));

        // Finale Datenqualitätskontrolle
        if ($url_source === 'cart_preview_url_fallback') {
            error_log('COMPLETE TRANSFER QUALITY WARNING: Order will be created with preview URL instead of final design file!');
        } elseif ($url_source === 'none') {
            error_log('COMPLETE TRANSFER QUALITY ERROR: No design URL found at all!');
        } else {
            error_log('COMPLETE TRANSFER QUALITY OK: Using ' . $url_source . ' as design source');
        }

        // Checkout-Pfad-Logging für Debugging
        $checkout_type = isset($cart_data['data']) ? 'NORMAL' : 'EXPRESS';
        error_log("COMPLETE TRANSFER ({$checkout_type}): Function completed for cart key: " . $cart_item_key);

        return true;
    }
}

/**
 * Hook for Standard WooCommerce Checkout
 */
if (!function_exists('yprint_tracked_design_transfer')) {
    function yprint_tracked_design_transfer($order_item, $cart_item_key, $values, $order) {
        // Log hook execution
        if (function_exists('yprint_log_hook_execution')) {
            yprint_log_hook_execution('checkout_create_order_line_item', 'Standard checkout design transfer for cart key: ' . $cart_item_key);
        }
        
        // Use central transfer function
        return yprint_complete_design_transfer($order_item, $values, $cart_item_key);
    }
}

// Register the hook
add_action('woocommerce_checkout_create_order_line_item', 'yprint_tracked_design_transfer', 10, 4);

YPrint_Stripe_Checkout::init();