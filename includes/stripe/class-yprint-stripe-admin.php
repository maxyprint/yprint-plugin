<?php
/**
 * Stripe Admin Class
 * 
 * Handles the admin interface for Stripe settings
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Stripe Admin Class
 */
class YPrint_Stripe_Admin {

    /**
     * Instance of this class.
     *
     * @var YPrint_Stripe_Admin
     */
    protected static $instance = null;

    /**
     * Get the singleton instance of this class
     *
     * @return YPrint_Stripe_Admin
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
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add AJAX handlers for the test button
        add_action('wp_ajax_yprint_stripe_test_connection', array($this, 'ajax_test_connection'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
    }

/**
 * Add admin menu
 */
public function add_admin_menu() {
    // Hauptmenüpunkt hinzufügen
    add_menu_page(
        __('YPrint', 'yprint-plugin'),              // Seitentitel
        __('YPrint', 'yprint-plugin'),              // Menütitel
        'manage_options',                           // Erforderliche Berechtigung
        'yprint-settings',                          // Menü-Slug
        null,                                       // Callback-Funktion (null, da wir Untermenüs verwenden)
        'dashicons-cart',                           // Icon (Warenkorb-Icon)
        30                                          // Position im Menü
    );
    
    // Untermenüpunkt für Stripe-Einstellungen hinzufügen
    add_submenu_page(
        'yprint-settings',                          // Übergeordneter Slug
        __('Stripe Settings', 'yprint-plugin'),     // Seitentitel
        __('Stripe', 'yprint-plugin'),              // Menütitel
        'manage_options',                           // Erforderliche Berechtigung
        'yprint-stripe-settings',                   // Menü-Slug
        array($this, 'display_settings_page')       // Callback-Funktion
    );
}

    /**
     * Register settings
     */
    public function register_settings() {
        // Register setting
        register_setting('yprint_stripe_settings_group', 'yprint_stripe_settings');
        
        // Add settings section
        add_settings_section(
            'yprint_stripe_main_section',
            __('Stripe API Settings', 'yprint-plugin'),
            array($this, 'main_section_callback'),
            'yprint-stripe-settings'
        );
        
        // Add settings fields
        add_settings_field(
            'yprint_stripe_testmode',
            __('Test Mode', 'yprint-plugin'),
            array($this, 'testmode_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_test_secret_key',
            __('Test Secret Key', 'yprint-plugin'),
            array($this, 'test_secret_key_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_test_publishable_key',
            __('Test Publishable Key', 'yprint-plugin'),
            array($this, 'test_publishable_key_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_secret_key',
            __('Live Secret Key', 'yprint-plugin'),
            array($this, 'secret_key_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_publishable_key',
            __('Live Publishable Key', 'yprint-plugin'),
            array($this, 'publishable_key_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_test_button',
            __('Test Connection', 'yprint-plugin'),
            array($this, 'test_button_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
    }

    /**
     * Main section callback
     */
    public function main_section_callback() {
        echo '<p>' . __('Configure your Stripe API settings below:', 'yprint-plugin') . '</p>';
    }

    /**
     * Test mode callback
     */
    public function testmode_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $checked = isset($options['testmode']) && 'yes' === $options['testmode'] ? 'checked' : '';
        
        echo '<input type="checkbox" id="yprint_stripe_testmode" name="yprint_stripe_settings[testmode]" value="yes" ' . $checked . ' />';
        echo '<label for="yprint_stripe_testmode">' . __('Enable Test Mode', 'yprint-plugin') . '</label>';
        echo '<p class="description">' . __('Place the payment gateway in test mode using test API keys.', 'yprint-plugin') . '</p>';
    }

    /**
     * Test secret key callback
     */
    public function test_secret_key_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $value = isset($options['test_secret_key']) ? $options['test_secret_key'] : '';
        
        echo '<input type="text" id="yprint_stripe_test_secret_key" name="yprint_stripe_settings[test_secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Get your API keys from your Stripe account.', 'yprint-plugin') . '</p>';
    }

    /**
     * Test publishable key callback
     */
    public function test_publishable_key_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $value = isset($options['test_publishable_key']) ? $options['test_publishable_key'] : '';
        
        echo '<input type="text" id="yprint_stripe_test_publishable_key" name="yprint_stripe_settings[test_publishable_key]" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    /**
     * Live secret key callback
     */
    public function secret_key_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $value = isset($options['secret_key']) ? $options['secret_key'] : '';
        
        echo '<input type="text" id="yprint_stripe_secret_key" name="yprint_stripe_settings[secret_key]" value="' . esc_attr($value) . '" class="regular-text" />';
        echo '<p class="description">' . __('Get your API keys from your Stripe account.', 'yprint-plugin') . '</p>';
    }

    /**
     * Live publishable key callback
     */
    public function publishable_key_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $value = isset($options['publishable_key']) ? $options['publishable_key'] : '';
        
        echo '<input type="text" id="yprint_stripe_publishable_key" name="yprint_stripe_settings[publishable_key]" value="' . esc_attr($value) . '" class="regular-text" />';
    }

    /**
     * Test button callback
     */
    public function test_button_callback() {
        echo '<button type="button" id="yprint_stripe_test_connection_button" class="button button-secondary">' . __('Test Connection', 'yprint-plugin') . '</button>';
        echo '<span id="yprint_stripe_test_connection_result" style="margin-left: 10px;"></span>';
    }

    /**
     * Display settings page
     */
    public function display_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('YPrint Stripe Settings', 'yprint-plugin'); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields('yprint_stripe_settings_group');
                do_settings_sections('yprint-stripe-settings');
                submit_button();
                ?>
            </form>
            <div id="yprint_stripe_test_details" style="display: none; margin-top: 20px;">
                <h2><?php echo esc_html__('Connection Test Details', 'yprint-plugin'); ?></h2>
                <div id="yprint_stripe_test_details_content" style="background: #f8f8f8; padding: 15px; border: 1px solid #ddd;"></div>
            </div>
        </div>
        <?php
    }

/**
 * Enqueue admin scripts
 */
public function enqueue_admin_scripts($hook) {
    // Only enqueue on our settings page
    if ('yprint-settings_page_yprint-stripe-settings' !== $hook) {
        return;
    }
    
    // Rest of the function remains the same
    wp_enqueue_script(
        'yprint-stripe-admin',
        YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-admin.js',
        array('jquery'),
        YPRINT_PLUGIN_VERSION,
        true
    );
        
        wp_localize_script(
            'yprint-stripe-admin',
            'yprint_stripe_admin',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('yprint_stripe_admin_nonce'),
                'testing_connection' => __('Testing connection...', 'yprint-plugin'),
                'connection_success' => __('Connection successful!', 'yprint-plugin'),
                'connection_error' => __('Connection failed: ', 'yprint-plugin'),
            )
        );
    }

    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        check_ajax_referer('yprint_stripe_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'yprint-plugin')));
        }
        
        // Make sure we use fresh settings
        YPrint_Stripe_API::set_secret_key_for_mode();
        
        $response = YPrint_Stripe_API::test_connection();
        
        if ($response['success']) {
            wp_send_json_success(array(
                'message' => $response['message'],
                'details' => $response['data'],
            ));
        } else {
            wp_send_json_error(array(
                'message' => $response['message'],
            ));
        }
    }
}