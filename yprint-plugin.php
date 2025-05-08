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
require_once YPRINT_PLUGIN_DIR . 'includes/user-settings.php'; //

/**
 * Enqueue scripts and styles
 */
function yprint_enqueue_scripts() {
    // Enqueue CSS
    wp_enqueue_style('yprint-styles', YPRINT_PLUGIN_URL . 'assets/css/yprint-styles.css', array(), YPRINT_PLUGIN_VERSION);
    
    // Enqueue JS
    wp_enqueue_script('yprint-scripts', YPRINT_PLUGIN_URL . 'assets/js/yprint-scripts.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
    
    // Add AJAX URL
    wp_localize_script('yprint-scripts', 'yprint_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yprint-ajax-nonce')
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
    
    // Flag setzen, um Rewrite Rules zu aktualisieren
    update_option('yprint_recovery_flush_rules', true);
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