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
    // Create required pages
    // Add any initial settings
}
register_activation_hook(__FILE__, 'yprint_plugin_activation');

/**
 * Plugin deactivation
 */
function yprint_plugin_deactivation() {
    // Cleanup if needed
}
register_deactivation_hook(__FILE__, 'yprint_plugin_deactivation');