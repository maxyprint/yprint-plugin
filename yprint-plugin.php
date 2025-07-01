<?php
/**
 * Plugin Name: YPrint Plugin
 * Plugin URI: https://yprint.de
 * Description: Custom functions for YPrint e-commerce website, including user registration, email handling, and more.
 * Version: 1.0.0
 * Author: YPrint
 * Author URI: https://yprint.de
 * Text Domain: yprint-plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Plugin constants
define('YPRINT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('YPRINT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('YPRINT_PLUGIN_VERSION', '1.0.0');

 // Include required files
require_once YPRINT_PLUGIN_DIR . 'includes/registration.php';
require_once YPRINT_PLUGIN_DIR . 'includes/email.php';
require_once YPRINT_PLUGIN_DIR . 'includes/rest-registration.php';
require_once YPRINT_PLUGIN_DIR . 'includes/login.php';
require_once YPRINT_PLUGIN_DIR . 'includes/password-recovery.php';
require_once YPRINT_PLUGIN_DIR . 'includes/user-shortcodes.php';
require_once YPRINT_PLUGIN_DIR . 'includes/ui-shortcodes.php';
require_once YPRINT_PLUGIN_DIR . 'includes/woocommerce.php';
require_once YPRINT_PLUGIN_DIR . 'includes/legal-shortcodes.php';
require_once YPRINT_PLUGIN_DIR . 'includes/product-fields.php';
require_once YPRINT_PLUGIN_DIR . 'includes/user-settings.php';
require_once YPRINT_PLUGIN_DIR . 'includes/help-shortcode.php';
require_once YPRINT_PLUGIN_DIR . 'includes/mobile-product-shortcode.php';
require_once YPRINT_PLUGIN_DIR . 'includes/checkout-header.php';

// Include Stripe files
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/yprint-stripe.php';
require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-admin.php';
// Apple Pay class will be loaded by yprint-stripe.php

// Include the checkout shortcode (nur wenn Datei existiert)
$checkout_file = YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-checkout.php';
if (file_exists($checkout_file)) {
    require_once $checkout_file;
    // Initialize Checkout Shortcode
    add_action('init', function() {
        if (class_exists('YPrint_Stripe_Checkout')) {
            YPrint_Stripe_Checkout::init();
        }
    });
} else {
    error_log('YPrint Plugin: Checkout class file not found at: ' . $checkout_file);
}

// Include Cart Data Manager (zentrale Datenverwaltung)
require_once YPRINT_PLUGIN_DIR . 'includes/class-yprint-cart-data.php';

// Include Address Manager
require_once YPRINT_PLUGIN_DIR . 'includes/class-yprint-address-manager.php';

// Include Address Handler (zentrale AJAX-Verwaltung)
require_once YPRINT_PLUGIN_DIR . 'includes/class-yprint-address-handler.php';

// Include Order Actions Shortcode
require_once YPRINT_PLUGIN_DIR . 'includes/order-actions-shortcode.php';

// Include Your Designs Shortcode
require_once YPRINT_PLUGIN_DIR . 'includes/your-designs-shortcode.php';

// Include Product Slider Shortcode
require_once YPRINT_PLUGIN_DIR . 'includes/product-slider-shortcode.php';

require_once YPRINT_PLUGIN_DIR . 'includes/navigation-menu-popup.php';
require_once YPRINT_PLUGIN_DIR . 'includes/mobile-cart-popup.php';

require_once YPRINT_PLUGIN_DIR . 'includes/yprint-order-debug-tracker.php';

// Include the design share page
require_once plugin_dir_path(__FILE__) . 'includes/design-share-page.php';

// Turnstile Integration laden
require_once plugin_dir_path(__FILE__) . 'includes/class-yprint-turnstile.php';
require_once plugin_dir_path(__FILE__) . 'includes/admin/class-yprint-turnstile-admin.php';

// Initialize Admin Classes (Stripe FIRST, dann Turnstile)
add_action('plugins_loaded', function() {
    // Stripe Admin erstellt das Hauptmenü
    YPrint_Stripe_Admin::get_instance();
    
    // Turnstile Admin hängt Untermenü an
    if (is_admin()) {
        YPrint_Turnstile_Admin::get_instance();
        add_action('admin_footer', function() {
            $is_admin = is_admin() ? 'YES' : 'NO';
            $class_exists = class_exists('YPrint_Turnstile_Admin') ? 'YES' : 'NO';
            echo '<script>';
            echo 'console.log("🔄 plugins_loaded hook fired");';
            echo 'console.log("🔄 is_admin(): ' . $is_admin . '");';
            echo 'console.log("🔄 Class exists: ' . $class_exists . '");';
            echo 'console.log("🔄 YPrint_Turnstile_Admin initialized");';
            echo '</script>';
        });
    }
});

// Flush rewrite rules on plugin activation
register_activation_hook(__FILE__, function() {
    YPrint_Design_Share_Page::add_rewrite_rules();
    flush_rewrite_rules();
});

// Flush rewrite rules on plugin deactivation
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
});

// Initialize Cart Data Manager early
add_action('init', function() {
    if (class_exists('WooCommerce')) {
        YPrint_Cart_Data::get_instance();
    }
}, 5);

// Add AJAX handlers for Cart Data
add_action('wp_ajax_yprint_cart_apply_coupon', function() {
    $cart_manager = YPrint_Cart_Data::get_instance();
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
    $result = $cart_manager->apply_coupon($coupon_code);
    wp_send_json($result);
});

add_action('wp_ajax_nopriv_yprint_cart_apply_coupon', function() {
    $cart_manager = YPrint_Cart_Data::get_instance();
    $coupon_code = sanitize_text_field($_POST['coupon_code'] ?? '');
    $result = $cart_manager->apply_coupon($coupon_code);
    wp_send_json($result);
});

// Initialize Address Manager
add_action('plugins_loaded', function() {
    YPrint_Address_Manager::get_instance();
});

// Initialize Address Handler (zentrale AJAX-Verwaltung)
add_action('plugins_loaded', function() {
    YPrint_Address_Handler::get_instance();
});

 

/**
 * Enqueue scripts and styles
 */
function yprint_enqueue_scripts() {
    // Enqueue CSS
    wp_enqueue_style('yprint-styles', YPRINT_PLUGIN_URL . 'assets/css/yprint-styles.css', array(), YPRINT_PLUGIN_VERSION);
    wp_enqueue_style('yprint-checkout', YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css', array(), YPRINT_PLUGIN_VERSION);
    
    // Explicitly enqueue jQuery first
    wp_enqueue_script('jquery');
    
    // Enqueue JS with proper dependencies
    wp_enqueue_script('yprint-scripts', YPRINT_PLUGIN_URL . 'assets/js/yprint-scripts.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
    wp_enqueue_script('yprint-address-manager', YPRINT_PLUGIN_URL . 'assets/js/yprint-address-manager.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
    wp_enqueue_script('yprint-checkout', YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
    
    // Add AJAX URL and Nonces
    wp_localize_script('yprint-scripts', 'yprint_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yprint-ajax-nonce')
    ));
    
    // Add address-specific AJAX settings
wp_localize_script('yprint-address-manager', 'yprint_address_ajax', array(
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('yprint_save_address_action'),
    'messages' => array(
        'set_as_default' => __('Als Standard setzen', 'yprint-plugin'),
        'delete_address' => __('Adresse löschen', 'yprint-plugin'),
        'confirm_delete' => __('Diese Adresse wirklich löschen?', 'yprint-plugin'),
        'address_saved' => __('Adresse erfolgreich gespeichert', 'yprint-plugin'),
        'address_deleted' => __('Adresse gelöscht', 'yprint-plugin'),
        'error_saving' => __('Fehler beim Speichern der Adresse', 'yprint-plugin'),
        'error_deleting' => __('Fehler beim Löschen der Adresse', 'yprint-plugin'),
        'standard_address' => __('Standard-Adresse', 'yprint-plugin'),
        'loading_addresses' => __('Adressen werden geladen...', 'yprint-plugin')
    )
));

// Add checkout-specific localization for payment method display
wp_localize_script('yprint-checkout', 'yprint_checkout_l10n', array(
    'payment_methods' => array(
        'apple_pay' => __('Apple Pay (Stripe)', 'yprint-plugin'),
        'google_pay' => __('Google Pay (Stripe)', 'yprint-plugin'),
        'sepa_debit' => __('SEPA-Lastschrift', 'yprint-plugin'),
        'stripe_payment' => __('Stripe-Zahlung', 'yprint-plugin'),
        'payment_pending' => __('Zahlungsart wird ermittelt...', 'yprint-plugin'),
        'card_payment' => __('Kreditkarte (Stripe)', 'yprint-plugin')
    )
));
}
add_action('wp_enqueue_scripts', 'yprint_enqueue_scripts');

/**
 * Plugin activation
 */
function yprint_plugin_activation() {
    global $wpdb;
    
    // Erstelle die Tabelle für E-Mail-Verifikationen, falls sie nicht existiert
    $table_name = $wpdb->prefix . 'email_verifications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        verification_code varchar(255) NOT NULL,
        email_verified tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY verification_code (verification_code)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Erstelle die Tabelle für Passwortwiederherstellungs-Tokens
    $recovery_table = $wpdb->prefix . 'password_reset_tokens';
    
    $sql_recovery = "CREATE TABLE IF NOT EXISTS $recovery_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        token_hash varchar(255) NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    dbDelta($sql_recovery);
    
    // Erstellen der erforderlichen Seiten, falls sie nicht existieren
    
    // Verifizierungsseite
    $verify_page = get_page_by_path('verify-email');
    if (!$verify_page) {
        wp_insert_post(array(
            'post_title' => 'E-Mail-Bestätigung',
            'post_name' => 'verify-email',
            'post_status' => 'publish',
            'post_type' => 'page',
            'post_content' => '[verify_email]',
            'comment_status' => 'closed'
        ));
    }
    
    // Account-Wiederherstellungsseite
    $recovery_page = get_page_by_path('recover-account');
    if (!$recovery_page) {
        wp_insert_post(array(
            'post_title' => 'Konto wiederherstellen',
            'post_name' => 'recover-account',
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed'
        ));
    }
    
    // Erfolgsseite für Passwortänderung
    $success_page = get_page_by_path('password-reset-success');
    if (!$success_page) {
        wp_insert_post(array(
            'post_title' => 'Passwort zurückgesetzt',
            'post_name' => 'password-reset-success',
            'post_status' => 'publish',
            'post_type' => 'page',
            'comment_status' => 'closed'
        ));
    }

    // Erstelle die Tabelle für E-Mail-Verifikationen, falls sie nicht existiert
    // Hinweis: Hier wird bewusst der direkte Tabellenname 'wp_email_verifications' verwendet
    // um mit dem Code in rest-registration.php konsistent zu sein
    $table_name = 'wp_email_verifications';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        verification_code varchar(255) NOT NULL,
        email_verified tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY verification_code (verification_code)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Stripe Apple Pay: Create .well-known directory and set up rewrite rules
    $well_known_dir = untrailingslashit(ABSPATH) . '/.well-known';
    if (!file_exists($well_known_dir)) {
        @mkdir($well_known_dir, 0755);
    }
    
    // Copy domain association file if available
    $source_file = YPRINT_PLUGIN_DIR . 'includes/stripe/apple-developer-merchantid-domain-association';
    $dest_file = $well_known_dir . '/apple-developer-merchantid-domain-association';
    
    if (file_exists($source_file)) {
        @copy($source_file, $dest_file);
    }
    
    // Flag setzen, um Rewrite Rules zu aktualisieren
    update_option('yprint_recovery_flush_rules', true);
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'yprint_plugin_activation');

/**
 * Plugin deactivation
 */
function yprint_plugin_deactivation() {
    // Cleanup if needed
    // Note: We do not delete the verification table to prevent data loss
    
    // Clear scheduled event for password recovery token cleanup
    wp_clear_scheduled_hook('yprint_cleanup_recovery_tokens');
}

/**
 * Custom AJAX Endpoint to Debug WooCommerce Cart Data
 * Add this to your theme's functions.php or a custom plugin.
 */

 add_action('wp_ajax_yprint_debug_cart', 'yprint_handle_debug_cart_ajax');
 add_action('wp_ajax_nopriv_yprint_debug_cart', 'yprint_handle_debug_cart_ajax');
 
 function yprint_handle_debug_cart_ajax() {
     if (!function_exists('WC') || !WC()->cart) {
         wp_send_json_error('WooCommerce Warenkorb nicht verfügbar.');
     }
     $cart_contents = WC()->cart->get_cart();
     wp_send_json_success($cart_contents);
 }

