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


    }

/**
 * AJAX handler for getting real cart data
 */
public function ajax_get_cart_data() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');
    
    if (!class_exists('WooCommerce') || WC()->cart->is_empty()) {
        wp_send_json_success(array(
            'items' => array(),
            'totals' => array(
                'subtotal' => 0,
                'shipping' => 0,
                'discount' => 0,
                'vat' => 0,
                'total' => 0
            )
        ));
        return;
    }
    
    // Warenkorb-Items sammeln
    $cart_items = array();
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $_product = $cart_item['data'];
        
        if (!$_product || !$_product->exists()) {
            continue;
        }
        
        $item_data = array(
            'id' => $cart_item['product_id'],
            'name' => $_product->get_name(),
            'price' => (float) $_product->get_price(),
            'quantity' => $cart_item['quantity'],
            'image' => wp_get_attachment_image_url($_product->get_image_id(), 'thumbnail') ?: 'https://placehold.co/100x100/CCCCCC/FFFFFF?text=Produkt',
            'cart_item_key' => $cart_item_key,
            'is_design_product' => false,
            'design_details' => array()
        );
        
        // Design-spezifische Daten hinzufügen, falls vorhanden
        if (isset($cart_item['print_design'])) {
            $design = $cart_item['print_design'];
            
            $item_data['name'] = $design['name'] ?? $item_data['name'];
            $item_data['design_id'] = $design['design_id'] ?? null;
            $item_data['is_design_product'] = true;
            
            // Design-Vorschaubild verwenden
            if (!empty($design['preview_url'])) {
                $item_data['image'] = $design['preview_url'];
            }
            
            // Berechneten Design-Preis verwenden
            if (!empty($design['calculated_price'])) {
                $item_data['price'] = (float) $design['calculated_price'];
            }
            
            // Design-Details für Anzeige
            if (!empty($design['variation_name'])) {
                $item_data['design_details'][] = 'Farbe: ' . $design['variation_name'];
            }
            
            if (!empty($design['size_name'])) {
                $item_data['design_details'][] = 'Größe: ' . $design['size_name'];
            }
            
            if (!empty($design['design_width_cm']) && !empty($design['design_height_cm'])) {
                $item_data['design_details'][] = sprintf(
                    'Maße: %s×%s cm',
                    number_format($design['design_width_cm'], 1),
                    number_format($design['design_height_cm'], 1)
                );
            }
        }
        
        $cart_items[] = $item_data;
    }
    
    // Warenkorb-Summen berechnen
    WC()->cart->calculate_totals();
    
    $cart_totals = array(
        'subtotal' => (float) WC()->cart->get_subtotal(),
        'shipping' => (float) WC()->cart->get_shipping_total(),
        'discount' => (float) WC()->cart->get_discount_total(),
        'vat' => (float) WC()->cart->get_total_tax(),
        'total' => (float) WC()->cart->get_total('edit')
    );
    
    wp_send_json_success(array(
        'items' => $cart_items,
        'totals' => $cart_totals
    ));
}

/**
 * AJAX handler for refreshing cart totals
 */
public function ajax_refresh_cart_totals() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');
    
    if (!class_exists('WooCommerce') || WC()->cart->is_empty()) {
        wp_send_json_error('Warenkorb ist leer');
        return;
    }
    
    $voucher_code = isset($_POST['voucher_code']) ? strtoupper(sanitize_text_field($_POST['voucher_code'])) : '';
    
    // Gutschein anwenden falls vorhanden
    if ($voucher_code) {
        // Hier würdest du deine Gutschein-Logik integrieren
        // Zum Beispiel WooCommerce Coupon System verwenden:
        if (!WC()->cart->has_discount($voucher_code)) {
            $coupon = new WC_Coupon($voucher_code);
            if ($coupon->is_valid()) {
                WC()->cart->apply_coupon($voucher_code);
            }
        }
    }
    
    // Warenkorb neu berechnen
    WC()->cart->calculate_totals();
    
    $cart_totals = array(
        'subtotal' => (float) WC()->cart->get_subtotal(),
        'shipping' => (float) WC()->cart->get_shipping_total(),
        'discount' => (float) WC()->cart->get_discount_total(),
        'vat' => (float) WC()->cart->get_total_tax(),
        'total' => (float) WC()->cart->get_total('edit')
    );
    
    wp_send_json_success(array(
        'totals' => $cart_totals
    ));
}

/**
 * AJAX handler for final checkout processing
 */
public function ajax_process_final_checkout() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');
    
    if (!class_exists('WooCommerce') || WC()->cart->is_empty()) {
        wp_send_json_error('Warenkorb ist leer');
        return;
    }
    
    try {
        // Daten aus der Anfrage extrahieren
        $shipping_data = json_decode(stripslashes($_POST['shipping_data']), true);
        $billing_data = json_decode(stripslashes($_POST['billing_data']), true);
        $payment_data = json_decode(stripslashes($_POST['payment_data']), true);
        $billing_same_as_shipping = filter_var($_POST['billing_same_as_shipping'], FILTER_VALIDATE_BOOLEAN);
        $payment_method = sanitize_text_field($_POST['payment_method']);
        
        // Kundendaten setzen
        if ($shipping_data) {
            WC()->customer->set_shipping_first_name($shipping_data['first_name'] ?? '');
            WC()->customer->set_shipping_last_name($shipping_data['last_name'] ?? '');
            WC()->customer->set_shipping_address_1($shipping_data['street'] ?? '');
            WC()->customer->set_shipping_address_2($shipping_data['housenumber'] ?? '');
            WC()->customer->set_shipping_city($shipping_data['city'] ?? '');
            WC()->customer->set_shipping_postcode($shipping_data['zip'] ?? '');
            WC()->customer->set_shipping_country($shipping_data['country'] ?? 'DE');
        }
        
        if ($billing_same_as_shipping && $shipping_data) {
            WC()->customer->set_billing_first_name($shipping_data['first_name'] ?? '');
            WC()->customer->set_billing_last_name($shipping_data['last_name'] ?? '');
            WC()->customer->set_billing_address_1($shipping_data['street'] ?? '');
            WC()->customer->set_billing_address_2($shipping_data['housenumber'] ?? '');
            WC()->customer->set_billing_city($shipping_data['city'] ?? '');
            WC()->customer->set_billing_postcode($shipping_data['zip'] ?? '');
            WC()->customer->set_billing_country($shipping_data['country'] ?? 'DE');
        } elseif ($billing_data) {
            WC()->customer->set_billing_first_name($billing_data['first_name'] ?? '');
            WC()->customer->set_billing_last_name($billing_data['last_name'] ?? '');
            WC()->customer->set_billing_address_1($billing_data['street'] ?? '');
            WC()->customer->set_billing_address_2($billing_data['housenumber'] ?? '');
            WC()->customer->set_billing_city($billing_data['city'] ?? '');
            WC()->customer->set_billing_postcode($billing_data['zip'] ?? '');
            WC()->customer->set_billing_country($billing_data['country'] ?? 'DE');
        }
        
    // E-Mail setzen
    if (!empty($shipping_data['email'])) {
        WC()->customer->set_billing_email($shipping_data['email']);
    }
    
    // Zahlungsmethode setzen
    WC()->session->set('chosen_payment_method', $payment_method);
    
    // Bestellung erstellen
    $checkout = WC()->checkout();
    $order_id = $checkout->create_order(array());
    
    if (is_wp_error($order_id)) {
        throw new Exception($order_id->get_error_message());
    }
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        throw new Exception('Bestellung konnte nicht erstellt werden');
    }
    
    // Zahlungsverarbeitung je nach Methode
    if (strpos($payment_method, 'stripe') !== false) {
        // Stripe-Zahlung verarbeiten
        $payment_result = $this->process_stripe_payment($order, $payment_method, $payment_data);
        
        if (!$payment_result['success']) {
            throw new Exception($payment_result['message']);
        }
    } else {
        // Andere Zahlungsmethoden
        $order->set_payment_method($payment_method);
        $order->update_status('pending', 'Bestellung aufgegeben - Zahlung ausstehend');
    }
    
    // Bestellung speichern
    $order->save();
    
    // Warenkorb leeren
    WC()->cart->empty_cart();
    
    wp_send_json_success(array(
        'order_id' => $order_id,
        'order_number' => $order->get_order_number(),
        'order_total' => $order->get_total(),
        'redirect_url' => $order->get_checkout_order_received_url(),
        'message' => 'Bestellung erfolgreich aufgegeben'
    ));
    
} catch (Exception $e) {
    error_log('YPrint Checkout Error: ' . $e->getMessage());
    wp_send_json_error($e->getMessage());
}
}

/**
* Process Stripe payment for the order
*/
private function process_stripe_payment($order, $payment_method, $payment_data) {
try {
    // Prüfe ob Stripe Gateway verfügbar ist
    $available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
    
    if (!isset($available_gateways['yprint_stripe'])) {
        return array(
            'success' => false,
            'message' => 'Stripe-Zahlungsgateway nicht verfügbar'
        );
    }
    
    $stripe_gateway = $available_gateways['yprint_stripe'];
    
    // Setze Zahlungsmethode für die Bestellung
    $order->set_payment_method('yprint_stripe');
    $order->set_payment_method_title('Stripe');
    
    // Verarbeite die Zahlung über das Gateway
    $result = $stripe_gateway->process_payment($order->get_id());
    
    if (isset($result['result']) && $result['result'] == 'success') {
        return array(
            'success' => true,
            'redirect_url' => $result['redirect'] ?? ''
        );
    } else {
        return array(
            'success' => false,
            'message' => 'Stripe-Zahlung fehlgeschlagen'
        );
    }
    
} catch (Exception $e) {
    return array(
        'success' => false,
        'message' => $e->getMessage()
    );
}
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
    
    // Ensure WooCommerce is active and cart is not empty
    if (!class_exists('WooCommerce') || WC()->cart->is_empty()) {
        return '<div class="yprint-checkout-error"><p>' . __('Ihr Warenkorb ist leer. <a href="' . wc_get_page_permalink('shop') . '">Weiter einkaufen</a>', 'yprint-plugin') . '</p></div>';
    }
    
    // Ensure CSS is loaded for this page
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
                    array('jquery', 'stripe-js', 'yprint-checkout-js'),
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
                
                // Express Checkout spezifische Daten
                wp_localize_script(
                    'yprint-express-checkout-js',
                    'yprint_express_payment_params',
                    array(
                        'ajax_url' => admin_url('admin-ajax.php'),
                        'nonce' => wp_create_nonce('yprint_express_checkout_nonce'),
                        'stripe' => array(
                            'publishable_key' => $publishable_key,
                            'test_mode' => $this->is_testmode($stripe_settings)
                        ),
                        'checkout' => array(
                            'country' => substr(get_option('woocommerce_default_country'), 0, 2),
                            'currency' => strtolower(get_woocommerce_currency()),
                            'total_label' => get_bloginfo('name'),
                        ),
                        'cart' => array(
                            'total' => WC()->cart ? (int)(WC()->cart->get_total('edit') * 100) : 0, // In Cent
                            'needs_shipping' => WC()->cart ? WC()->cart->needs_shipping() : false
                        ),
                        'settings' => array(
                            'button_type' => isset($stripe_settings['payment_request_button_type']) ? $stripe_settings['payment_request_button_type'] : 'default',
                            'button_theme' => isset($stripe_settings['payment_request_button_theme']) ? $stripe_settings['payment_request_button_theme'] : 'dark',
                            'button_height' => isset($stripe_settings['payment_request_button_height']) ? $stripe_settings['payment_request_button_height'] : '48'
                        ),
                        'i18n' => array(
                            'processing' => __('Processing payment...', 'yprint-plugin'),
                            'error' => __('Payment failed. Please try again.', 'yprint-plugin'),
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
        if (class_exists('YPrint_Stripe_API')) {
            $settings = YPrint_Stripe_API::get_stripe_settings();
            
            // Prüfe ob grundlegende Stripe-Einstellungen vorhanden sind
            if (empty($settings)) {
                return false;
            }
            
            // Prüfe ob API-Schlüssel gesetzt sind
            $testmode = isset($settings['testmode']) && 'yes' === $settings['testmode'];
            if ($testmode) {
                $has_keys = !empty($settings['test_publishable_key']) && !empty($settings['test_secret_key']);
            } else {
                $has_keys = !empty($settings['publishable_key']) && !empty($settings['secret_key']);
            }
            
            // Stripe ist aktiviert wenn API-Schlüssel vorhanden sind ODER explizit aktiviert
            return $has_keys || (isset($settings['enabled']) && 'yes' === $settings['enabled']);
        }
        return false;
    }

    /**
     * Make is_stripe_enabled public for debugging
     *
     * @return bool
     */
    public function is_stripe_enabled_public() {
        return $this->is_stripe_enabled();
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
     * Render express payment buttons (Apple Pay, Google Pay)
     *
     * @return string HTML for express payment buttons
     */
    public function render_express_payment_buttons() {
        // Prüfe Stripe-Aktivierung
        if (!$this->is_stripe_enabled()) {
            return '';
        }
        
        // Hole Stripe-Einstellungen
        if (!class_exists('YPrint_Stripe_API')) {
            return '';
        }
        
        $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
        
        // Prüfe ob Express Payments aktiviert sind (Standard: ja, wenn nicht explizit deaktiviert)
        $express_enabled = !isset($stripe_settings['express_payments']) || 'yes' === $stripe_settings['express_payments'];
        if (!$express_enabled) {
            return '';
        }
        
        // Erkenne Testmode basierend auf API-Schlüsseln
        $testmode = $this->is_testmode($stripe_settings);
        
        // Prüfe SSL-Anforderung (außer im Test-Modus)
        if (!$testmode && !is_ssl()) {
            return '';
        }
        
        // Generiere HTML für Express Payment Buttons
        ob_start();
        ?>
        <div class="yprint-express-payment-container" id="yprint-express-payment-container">
            <div class="express-payment-header">
                <span class="express-payment-label"><?php _e('Express Checkout', 'yprint-plugin'); ?></span>
            </div>
            <div class="express-payment-buttons">
                <div id="yprint-payment-request-button" class="yprint-express-button"></div>
            </div>
            <div class="express-payment-separator">
                <span><?php _e('ODER', 'yprint-plugin'); ?></span>
            </div>
        </div>
        <?php
        return ob_get_clean();
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
 * AJAX handler for checking email availability
 */
public function ajax_check_email_availability() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');
    
    $email = isset($_POST['email']) ? sanitize_email($_POST['email']) : '';
    
    if (empty($email) || !is_email($email)) {
        wp_send_json_error(array(
            'message' => __('Ungültige E-Mail-Adresse.', 'yprint-plugin')
        ));
        return;
    }
    
    // Prüfe ob E-Mail bereits existiert
    if (email_exists($email)) {
        wp_send_json_error(array(
            'message' => __('Diese E-Mail-Adresse ist bereits registriert.', 'yprint-plugin')
        ));
        return;
    }
    
    // E-Mail ist verfügbar
    wp_send_json_success(array(
        'message' => __('E-Mail-Adresse ist verfügbar.', 'yprint-plugin')
    ));
}

/**
 * AJAX handler for validating voucher codes
 */
public function ajax_validate_voucher() {
    check_ajax_referer('yprint_checkout_nonce', 'nonce');
    
    $voucher_code = isset($_POST['voucher_code']) ? strtoupper(sanitize_text_field($_POST['voucher_code'])) : '';
    
    if (empty($voucher_code)) {
        wp_send_json_error(array(
            'message' => __('Bitte geben Sie einen Gutscheincode ein.', 'yprint-plugin')
        ));
        return;
    }
    
    // Einfache Gutschein-Validierung (kann erweitert werden)
    $valid_vouchers = array(
        'YPRINT10' => array(
            'discount' => 10,
            'type' => 'percentage',
            'message' => '10% Rabatt angewendet'
        ),
        'WELCOME5' => array(
            'discount' => 5,
            'type' => 'fixed',
            'message' => '5€ Rabatt angewendet'
        ),
        'NEWCUSTOMER' => array(
            'discount' => 15,
            'type' => 'percentage',
            'message' => '15% Neukunden-Rabatt angewendet'
        )
    );
    
    if (isset($valid_vouchers[$voucher_code])) {
        $voucher = $valid_vouchers[$voucher_code];
        
        // Gutschein in Session speichern für Preisberechnung
        if (!session_id()) {
            session_start();
        }
        $_SESSION['applied_voucher'] = $voucher_code;
        $_SESSION['voucher_data'] = $voucher;
        
        wp_send_json_success(array(
            'message' => $voucher['message'],
            'discount' => $voucher['discount'],
            'type' => $voucher['type']
        ));
    } else {
        wp_send_json_error(array(
            'message' => __('Ungültiger Gutscheincode.', 'yprint-plugin')
        ));
    }
}

/**
     * Prepare real WooCommerce cart data for checkout templates
     */
    private function prepare_cart_data_for_templates() {
        global $cart_items_data, $cart_totals_data;
        
        if (!WC()->cart || WC()->cart->is_empty()) {
            $cart_items_data = array();
            $cart_totals_data = array(
                'subtotal' => 0,
                'shipping' => 0,
                'discount' => 0,
                'vat' => 0,
                'total' => 0
            );
            return;
        }
        
        // Prepare cart items with design data
        $cart_items_data = array();
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = $cart_item['data'];
            
            if (!$_product || !$_product->exists()) {
                continue;
            }
            
            $item_data = array(
                'id' => $cart_item['product_id'],
                'name' => $_product->get_name(),
                'price' => (float) $_product->get_price(),
                'quantity' => $cart_item['quantity'],
                'image' => wp_get_attachment_image_url($_product->get_image_id(), 'thumbnail'),
                'cart_item_key' => $cart_item_key
            );
            
            // Add design-specific data if available
            if (isset($cart_item['print_design'])) {
                $design = $cart_item['print_design'];
                
                // Override with design-specific data
                $item_data['name'] = $design['name'] ?? $item_data['name'];
                $item_data['design_id'] = $design['design_id'] ?? null;
                $item_data['variation_name'] = $design['variation_name'] ?? '';
                $item_data['size_name'] = $design['size_name'] ?? '';
                
                // Use design preview image if available
                if (!empty($design['preview_url'])) {
                    $item_data['image'] = $design['preview_url'];
                }
                
                // Use calculated design price if available
                if (!empty($design['calculated_price'])) {
                    $item_data['price'] = (float) $design['calculated_price'];
                }
                
                // Add design metadata for display
                $item_data['is_design_product'] = true;
                $item_data['design_details'] = array();
                
                if (!empty($design['variation_name'])) {
                    $item_data['design_details'][] = __('Farbe: ', 'yprint-plugin') . $design['variation_name'];
                }
                
                if (!empty($design['size_name'])) {
                    $item_data['design_details'][] = __('Größe: ', 'yprint-plugin') . $design['size_name'];
                }
            }
            
            $cart_items_data[] = $item_data;
        }
        
        // Calculate totals
        WC()->cart->calculate_totals();
        
        $cart_totals_data = array(
            'subtotal' => (float) WC()->cart->get_subtotal(),
            'shipping' => (float) WC()->cart->get_shipping_total(),
            'discount' => (float) WC()->cart->get_discount_total(),
            'vat' => (float) WC()->cart->get_total_tax(),
            'total' => (float) WC()->cart->get_total('edit')
        );
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