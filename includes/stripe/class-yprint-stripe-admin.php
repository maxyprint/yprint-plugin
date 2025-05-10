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
    // Hauptmenüpunkt für YPrint Plugin
    add_menu_page(
        __('YPrint Plugin', 'yprint-plugin'),
        __('YPrint', 'yprint-plugin'),
        'manage_options',
        'yprint-plugin',
        array($this, 'display_main_page'),
        'dashicons-cart',
        58
    );
    
    // Untermenüpunkt für Stripe-Einstellungen
    add_submenu_page(
        'yprint-plugin', // Parent slug - YPrint Hauptmenü
        __('YPrint Stripe Settings', 'yprint-plugin'),
        __('Stripe', 'yprint-plugin'),
        'manage_options',
        'yprint-stripe-settings',
        array($this, 'display_settings_page')
    );
}

/**
 * Display main plugin page
 */
public function display_main_page() {
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('YPrint Plugin Dashboard', 'yprint-plugin'); ?></h1>
        <div class="card">
            <h2><?php echo esc_html__('Welcome to YPrint Plugin', 'yprint-plugin'); ?></h2>
            <p><?php echo esc_html__('This plugin provides core functionality for your YPrint e-commerce website.', 'yprint-plugin'); ?></p>
            <p><?php echo esc_html__('Use the menu on the left to access different settings sections.', 'yprint-plugin'); ?></p>
        </div>
    </div>
    <?php
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
        
        // Payment Request (Apple Pay) Settings
        add_settings_field(
            'yprint_stripe_payment_request',
            __('Payment Request', 'yprint-plugin'),
            array($this, 'payment_request_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_payment_request_button_type',
            __('Button Type', 'yprint-plugin'),
            array($this, 'payment_request_button_type_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_payment_request_button_theme',
            __('Button Theme', 'yprint-plugin'),
            array($this, 'payment_request_button_theme_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_payment_request_button_height',
            __('Button Height', 'yprint-plugin'),
            array($this, 'payment_request_button_height_callback'),
            'yprint-stripe-settings',
            'yprint_stripe_main_section'
        );
        
        add_settings_field(
            'yprint_stripe_payment_request_button_locations',
            __('Button Locations', 'yprint-plugin'),
            array($this, 'payment_request_button_locations_callback'),
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
     * Payment Request callback
     */
    public function payment_request_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $checked = isset($options['payment_request']) && 'yes' === $options['payment_request'] ? 'checked' : '';
        
        echo '<input type="checkbox" id="yprint_stripe_payment_request" name="yprint_stripe_settings[payment_request]" value="yes" ' . $checked . ' />';
        echo '<label for="yprint_stripe_payment_request">' . __('Enable Payment Request Buttons (Apple Pay and Payment Request API)', 'yprint-plugin') . '</label>';
        echo '<p class="description">' . __('If enabled, users will be able to pay using Apple Pay and Payment Request API if available in their browser.', 'yprint-plugin') . '</p>';
    }

    /**
     * Payment Request Button Type callback
     */
    public function payment_request_button_type_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $value = isset($options['payment_request_button_type']) ? $options['payment_request_button_type'] : 'default';
        
        echo '<select id="yprint_stripe_payment_request_button_type" name="yprint_stripe_settings[payment_request_button_type]">';
        echo '<option value="default" ' . selected($value, 'default', false) . '>' . __('Default', 'yprint-plugin') . '</option>';
        echo '<option value="buy" ' . selected($value, 'buy', false) . '>' . __('Buy', 'yprint-plugin') . '</option>';
        echo '<option value="donate" ' . selected($value, 'donate', false) . '>' . __('Donate', 'yprint-plugin') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Choose the button type to show.', 'yprint-plugin') . '</p>';
    }

    /**
     * Payment Request Button Theme callback
     */
    public function payment_request_button_theme_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $value = isset($options['payment_request_button_theme']) ? $options['payment_request_button_theme'] : 'dark';
        
        echo '<select id="yprint_stripe_payment_request_button_theme" name="yprint_stripe_settings[payment_request_button_theme]">';
        echo '<option value="dark" ' . selected($value, 'dark', false) . '>' . __('Dark', 'yprint-plugin') . '</option>';
        echo '<option value="light" ' . selected($value, 'light', false) . '>' . __('Light', 'yprint-plugin') . '</option>';
        echo '<option value="light-outline" ' . selected($value, 'light-outline', false) . '>' . __('Light Outline', 'yprint-plugin') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Choose the button theme to show.', 'yprint-plugin') . '</p>';
    }

    /**
     * Payment Request Button Height callback
     */
    public function payment_request_button_height_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $value = isset($options['payment_request_button_height']) ? $options['payment_request_button_height'] : '48';
        
        echo '<input type="text" id="yprint_stripe_payment_request_button_height" name="yprint_stripe_settings[payment_request_button_height]" value="' . esc_attr($value) . '" class="small-text" /> px';
        echo '<p class="description">' . __('Enter the height of the button in pixels. Minimum height is 40px.', 'yprint-plugin') . '</p>';
    }

    /**
     * Payment Request Button Locations callback
     */
    public function payment_request_button_locations_callback() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $value = isset($options['payment_request_button_locations']) ? $options['payment_request_button_locations'] : array('product', 'cart', 'checkout');
        
        if (!is_array($value)) {
            $value = array();
        }
        
        echo '<select id="yprint_stripe_payment_request_button_locations" name="yprint_stripe_settings[payment_request_button_locations][]" multiple="multiple" class="regular-text">';
        echo '<option value="product" ' . selected(in_array('product', $value), true, false) . '>' . __('Product Page', 'yprint-plugin') . '</option>';
        echo '<option value="cart" ' . selected(in_array('cart', $value), true, false) . '>' . __('Cart Page', 'yprint-plugin') . '</option>';
        echo '<option value="checkout" ' . selected(in_array('checkout', $value), true, false) . '>' . __('Checkout Page', 'yprint-plugin') . '</option>';
        echo '</select>';
        echo '<p class="description">' . __('Choose where to show the button.', 'yprint-plugin') . '</p>';
    }

    /**
     * Test button callback
     */
    public function test_button_callback() {
        echo '<button type="button" id="yprint_stripe_test_connection_button" class="button button-secondary">' . __('Test Connection', 'yprint-plugin') . '</button>';
        echo '<button type="button" id="yprint_stripe_test_apple_pay_button" class="button button-secondary" style="margin-left: 10px;">' . __('Test Apple Pay Domain', 'yprint-plugin') . '</button>';
        echo '<button type="button" id="yprint_stripe_test_payment_button" class="button button-secondary" style="margin-left: 10px;">' . __('Test Payment Request Button', 'yprint-plugin') . '</button>';
        echo '<button type="button" id="yprint_stripe_test_payment_gateway_button" class="button button-secondary" style="margin-left: 10px;">' . __('Test Payment Gateway', 'yprint-plugin') . '</button>';
        echo '<button type="button" id="yprint_stripe_test_webhook_button" class="button button-secondary" style="margin-left: 10px;">' . __('Test Webhook', 'yprint-plugin') . '</button>';
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
            
            <!-- Apple Pay Button Test Preview -->
            <div id="yprint_stripe_apple_pay_preview" style="display: none; margin-top: 20px;">
                <h2><?php echo esc_html__('Apple Pay Button Preview', 'yprint-plugin'); ?></h2>
                <p><?php echo esc_html__('This preview shows how the button would look on your website. The actual button may vary depending on the device and browser.', 'yprint-plugin'); ?></p>
                
                <div style="max-width: 400px; margin: 20px 0; padding: 20px; border: 1px solid #ddd; background: #f9f9f9;">
                    <div id="yprint-stripe-payment-request-button-preview" style="height: 48px; background: #000; border-radius: 4px; position: relative;">
                        <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); color: white; font-weight: bold; display: flex; align-items: center;">
                            <svg height="24" width="24" viewBox="0 0 24 24" style="margin-right: 8px;">
                                <path d="M17.5 13.5H16l-.3-1h-2.5l-.3 1h-1.5L14 8h2.5l1 5.5zm-3.2-2.1h1.9l-.2-.9c0-.2-.2-.8-.2-.8s-.1.6-.2.8l-.1.9zM6 15.5h10c.8 0 1.5-.7 1.5-1.5v-8c0-.8-.7-1.5-1.5-1.5H6c-.8 0-1.5.7-1.5 1.5v8c0 .8.7 1.5 1.5 1.5z" fill="#fff"></path>
                            </svg>
                            <?php echo esc_html__('Pay', 'yprint-plugin'); ?>
                        </div>
                    </div>
                    <p style="font-size: 12px; color: #666; margin-top: 10px;"><?php echo esc_html__('Note: This is a simulated button. Actual Apple Pay buttons are rendered by the browser based on user device capabilities.', 'yprint-plugin'); ?></p>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        // Debug info - remove in production
        error_log('Current admin page hook: ' . $hook);
        
        // Weniger strenge Prüfung für den Seitenhaken
        if (strpos($hook, 'yprint-stripe-settings') === false && strpos($hook, 'yprint-plugin') === false) {
            return;
        }
        
        error_log('Enqueuing Stripe admin scripts');
        
        wp_enqueue_script(
            'yprint-stripe-admin',
            YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-admin.js',
            array('jquery'),
            YPRINT_PLUGIN_VERSION . '.' . time(), // Cache-Busting für Entwicklung
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
                'testing_apple_pay' => __('Testing Apple Pay domain verification...', 'yprint-plugin'),
                'apple_pay_success' => __('Apple Pay domain verification successful!', 'yprint-plugin'),
                'apple_pay_error' => __('Apple Pay domain verification failed: ', 'yprint-plugin'),
                'testing_payment_button' => __('Testing Payment Request Button...', 'yprint-plugin'),
                'payment_button_success' => __('Payment Request Button is configured correctly!', 'yprint-plugin'),
                'payment_button_error' => __('Payment Request Button configuration failed: ', 'yprint-plugin'),
            )
        );
        
        // Debug-Ausgabe in der Konsole
        wp_add_inline_script('yprint-stripe-admin', 'console.log("YPrint Stripe Admin script localized with:", yprint_stripe_admin);', 'before');
    }

    /**
     * AJAX handler for testing connection
     */
    public function ajax_test_connection() {
        // Debug-Nachricht für AJAX-Aufruf
        error_log('AJAX-Handler für Stripe-Test-Connection wurde aufgerufen.');
        
        check_ajax_referer('yprint_stripe_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            error_log('Stripe-Test-Connection: Berechtigungsfehler');
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'yprint-plugin')));
        }
        
        // Make sure we use fresh settings
        YPrint_Stripe_API::set_secret_key_for_mode();
        
        $test_type = isset($_POST['test_type']) ? sanitize_text_field($_POST['test_type']) : 'connection';
        error_log('Stripe-Test-Type: ' . $test_type);
        
        try {
            // Je nach Test-Typ unterschiedliche Aktionen ausführen
            switch ($test_type) {
                case 'apple_pay':
                    $response = YPrint_Stripe_API::test_apple_pay_domain_verification();
                    break;
                    
                case 'register_domain':
                    // Separate Domain-Registrierung
                    require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-apple-pay.php';
                    $apple_pay = YPrint_Stripe_Apple_Pay::get_instance();
                    $response = $apple_pay->register_domain();
                    break;
                    
                case 'payment_button':
                    // Test der Payment Request Button Integration
                    require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-payment-request.php';
                    $response = YPrint_Stripe_Payment_Request::test_payment_request();
                    break;
                    
                case 'payment_gateway':
                    // Test der Payment Gateway Integration
                    if (!class_exists('YPrint_Stripe_Payment_Gateway')) {
                        require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-payment-gateway.php';
                    }
                    $response = YPrint_Stripe_Payment_Gateway::test_gateway();
                    break;
                    
                case 'webhook':
                    // Test der Webhook-Funktionalität
                    if (!class_exists('YPrint_Stripe_Webhook_Handler')) {
                        require_once YPRINT_PLUGIN_DIR . 'includes/stripe/class-yprint-stripe-webhook-handler.php';
                    }
                    $response = YPrint_Stripe_Webhook_Handler::test_webhook();
                    break;
                    
                default:
                    // Standard: API-Verbindungstest
                    $response = YPrint_Stripe_API::test_connection();
                    break;
            }
            
            error_log('Stripe-Test-' . $test_type . ' Ergebnis: ' . wp_json_encode($response));
            
            if ($response['success']) {
                // Show preview if testing payment button
                if ($test_type === 'payment_button') {
                    $response['show_preview'] = true;
                }
                
                wp_send_json_success(array(
                    'message' => $response['message'],
                    'details' => isset($response['data']) ? $response['data'] : (isset($response['details']) ? $response['details'] : []),
                    'show_preview' => isset($response['show_preview']) ? $response['show_preview'] : false
                ));
            } else {
                wp_send_json_error(array(
                    'message' => $response['message'],
                    'details' => isset($response['details']) ? $response['details'] : [],
                ));
            }
        } catch (Exception $e) {
            error_log('Stripe-Test-Exception: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'details' => ['error' => $e->getMessage()]
            ));
        }
        
        wp_die();
    }
}