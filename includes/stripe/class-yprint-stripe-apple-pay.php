<?php
/**
 * Stripe Apple Pay Domain Verification Class
 * 
 * Handles the domain verification process for Apple Pay via Stripe
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Stripe Apple Pay Class
 */
class YPrint_Stripe_Apple_Pay {

    /**
     * Domain association file name
     */
    const DOMAIN_ASSOCIATION_FILE_NAME = 'apple-developer-merchantid-domain-association';
    
    /**
     * Domain association file directory
     */
    const DOMAIN_ASSOCIATION_FILE_DIR = '.well-known';

    /**
     * Instance of this class.
     *
     * @var YPrint_Stripe_Apple_Pay
     */
    protected static $instance = null;

    /**
     * Current domain name.
     *
     * @var string
     */
    private $domain_name;

    /**
     * Apple Pay domain set status.
     *
     * @var bool
     */
    private $apple_pay_domain_set;

    /**
     * Stores verification notice messages.
     *
     * @var string
     */
    public $apple_pay_verify_notice;

    /**
     * Get the singleton instance of this class
     *
     * @return YPrint_Stripe_Apple_Pay
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
        $this->domain_name = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : str_replace(['https://', 'http://'], '', get_site_url());
        $this->apple_pay_domain_set = 'yes' === $this->get_option('apple_pay_domain_set', 'no');
        $this->apple_pay_verify_notice = '';
        
        // Add rewrite rules for serving the domain association file
        add_action('init', [$this, 'add_domain_association_rewrite_rule']);
        add_action('admin_init', [$this, 'verify_domain_on_domain_name_change']);
        add_filter('query_vars', [$this, 'whitelist_domain_association_query_param'], 10, 1);
        add_action('parse_request', [$this, 'parse_domain_association_request'], 10, 1);

        // Register domain verification events
        add_action('yprint_stripe_settings_updated', [$this, 'verify_domain_if_configured']);
    }

    /**
     * Gets a Stripe setting.
     *
     * @param string $setting The setting key.
     * @param string $default The default value.
     * @return string The setting value.
     */
    public function get_option($setting = '', $default = '') {
        $options = YPrint_Stripe_API::get_stripe_settings();
        
        if (empty($options)) {
            return $default;
        }

        if (!empty($options[$setting])) {
            return $options[$setting];
        }

        return $default;
    }

    /**
     * Check if domain has changed and verify if needed
     */
    public function verify_domain_on_domain_name_change() {
        if ($this->domain_name !== $this->get_option('apple_pay_verified_domain')) {
            $this->verify_domain_if_configured();
        }
    }

    /**
     * Adds a rewrite rule for serving the domain association file from the proper location.
     */
    public function add_domain_association_rewrite_rule() {
        $regex    = '^\\' . self::DOMAIN_ASSOCIATION_FILE_DIR . '\/' . self::DOMAIN_ASSOCIATION_FILE_NAME . '$';
        $redirect = 'index.php?' . self::DOMAIN_ASSOCIATION_FILE_NAME . '=1';

        add_rewrite_rule($regex, $redirect, 'top');
    }

    /**
     * Add to the list of publicly allowed query variables.
     *
     * @param array $query_vars Provided public query vars.
     * @return array Updated public query vars.
     */
    public function whitelist_domain_association_query_param($query_vars) {
        $query_vars[] = self::DOMAIN_ASSOCIATION_FILE_NAME;
        return $query_vars;
    }

    /**
     * Serve domain association file when proper query param is provided.
     *
     * @param WP $wp WordPress environment object.
     */
    public function parse_domain_association_request($wp) {
        if (
            !isset($wp->query_vars[self::DOMAIN_ASSOCIATION_FILE_NAME]) ||
            '1' !== $wp->query_vars[self::DOMAIN_ASSOCIATION_FILE_NAME]
        ) {
            return;
        }

        $path = YPRINT_PLUGIN_DIR . 'includes/stripe/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
        header('Content-Type: text/plain;charset=utf-8');
        echo esc_html(file_get_contents($path));
        exit;
    }

    /**
     * Copies and overwrites domain association file.
     *
     * @return string|null Error message or null on success.
     */
    private function copy_and_overwrite_domain_association_file() {
        $well_known_dir = untrailingslashit(ABSPATH) . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR;
        $fullpath       = $well_known_dir . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;

        if (!file_exists($well_known_dir)) {
            if (!@mkdir($well_known_dir, 0755)) {
                return __('Unable to create domain association folder to domain root.', 'yprint-plugin');
            }
        }

        $source_file = YPRINT_PLUGIN_DIR . 'includes/stripe/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
        if (!file_exists($source_file)) {
            return __('Domain association file not found in plugin directory.', 'yprint-plugin');
        }

        if (!@copy($source_file, $fullpath)) {
            return __('Unable to copy domain association file to domain root.', 'yprint-plugin');
        }

        return null;
    }

    /**
     * Verifies if hosted domain association file is up to date.
     *
     * @return bool Whether file is up to date or not.
     */
    private function verify_hosted_domain_association_file_is_up_to_date() {
        // Contents of domain association file from plugin dir.
        $new_contents = @file_get_contents(YPRINT_PLUGIN_DIR . 'includes/stripe/' . self::DOMAIN_ASSOCIATION_FILE_NAME);
        
        // Get file contents from local path and remote URL and check if either of which matches.
        $fullpath        = untrailingslashit(ABSPATH) . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
        $local_contents  = @file_get_contents($fullpath);
        $url             = get_site_url() . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
        $response        = @wp_remote_get($url);
        $remote_contents = @wp_remote_retrieve_body($response);

        return $local_contents === $new_contents || $remote_contents === $new_contents;
    }

    /**
     * Update the domain association file.
     *
     * @return string|null Error message or null on success.
     */
    public function update_domain_association_file() {
        if ($this->verify_hosted_domain_association_file_is_up_to_date()) {
            return null;
        }

        $error_message = $this->copy_and_overwrite_domain_association_file();

        if (isset($error_message)) {
            $url = get_site_url() . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
            error_log(
                'Error: ' . $error_message . ' ' .
                /* translators: expected domain association file URL */
                sprintf(__('To enable Apple Pay, domain association file must be hosted at %s.', 'yprint-plugin'), $url)
            );
        }

        return $error_message;
    }

    /**
 * Makes request to register the domain with Stripe.
 *
 * @return array Response with success/error details.
 */
private function make_domain_registration_request() {
    try {
        $secret_key = YPrint_Stripe_API::get_secret_key();
        error_log('Attempting domain registration with API key: ' . (empty($secret_key) ? 'Missing' : 'Present'));
        
        if (empty($secret_key)) {
            throw new Exception(__('Unable to verify domain - missing secret key.', 'yprint-plugin'));
        }

        $request = [
            'domain_name' => $this->domain_name,
        ];
        
        error_log('Registering domain: ' . $this->domain_name);

        // Request to register domain
        $response = YPrint_Stripe_API::request($request, 'payment_method_domains', 'POST');
        error_log('Domain registration response: ' . wp_json_encode($response));

        if (isset($response->error)) {
            throw new Exception($response->error->message ?? __('Unknown error from Stripe API', 'yprint-plugin'));
        }

        return [
            'success' => true,
            'message' => __('Domain successfully registered with Stripe.', 'yprint-plugin'),
            'data' => $response
        ];
    } catch (Exception $e) {
        error_log('Domain registration error: ' . $e->getMessage());
        $this->apple_pay_verify_notice = $e->getMessage();
        return [
            'success' => false,
            'message' => sprintf(__('Unable to verify domain - %s', 'yprint-plugin'), $e->getMessage()),
            'details' => [
                'error' => $e->getMessage(),
                'domain' => $this->domain_name
            ]
        ];
    }
}

    /**
     * Register domain with Apple Pay via Stripe.
     *
     * @return array Response with success/error details.
     */
    public function register_domain() {
        $response = $this->make_domain_registration_request();
        
        $options = YPrint_Stripe_API::get_stripe_settings();
        
        if ($response['success']) {
            // Update settings
            $options['apple_pay_verified_domain'] = $this->domain_name;
            $options['apple_pay_domain_set'] = 'yes';
            $this->apple_pay_domain_set = true;
            
            // Aktiviere Express Payments automatisch nach erfolgreicher Domain-Registrierung
            if (!isset($options['express_payments'])) {
                $options['express_payments'] = 'yes';
            }
            if (!isset($options['payment_request'])) {
                $options['payment_request'] = 'yes';
            }
            
            YPrint_Stripe_API::update_stripe_settings($options);
            
            error_log('YPrint Apple Pay: Domain successfully verified and Express Payments enabled');
        } else {
            // Update settings with failed status
            $options['apple_pay_verified_domain'] = $this->domain_name;
            $options['apple_pay_domain_set'] = 'no';
            $this->apple_pay_domain_set = false;
            
            YPrint_Stripe_API::update_stripe_settings($options);
            
            error_log('YPrint Apple Pay: Domain registration failed - ' . $response['message']);
        }
        
        return $response;
    }

    /**
     * Process the Apple Pay domain verification.
     *
     * @return array Response with success/error details.
     */
    public function verify_domain_if_configured() {
        $secret_key = YPrint_Stripe_API::get_secret_key();
        $is_enabled = !empty(YPrint_Stripe_API::get_stripe_settings());
        
        if (!$is_enabled || empty($secret_key)) {
            return [
                'success' => false,
                'message' => __('Stripe is not properly configured. Please check your settings.', 'yprint-plugin')
            ];
        }

        // Ensure that domain association file will be served
        flush_rewrite_rules();

        // The rewrite rule method doesn't work if permalinks are set to Plain
        // Create/update domain association file by copying it from the plugin folder as a fallback
        $file_error = $this->update_domain_association_file();
        
        if ($file_error) {
            return [
                'success' => false,
                'message' => $file_error
            ];
        }

        // Register the domain with Apple Pay
        return $this->register_domain();
    }

    /**
 * Test the domain verification
 *
 * @return array Response with success/error details
 */
public function test_domain_verification() {
    error_log('Starting Apple Pay domain verification test');
    
    // Prüfe erst, ob die Domain-Assoziationsdatei existiert und zugänglich ist
    $file_url = get_site_url() . '/' . self::DOMAIN_ASSOCIATION_FILE_DIR . '/' . self::DOMAIN_ASSOCIATION_FILE_NAME;
    error_log('Checking domain association file at: ' . $file_url);
    
    $response = wp_remote_get($file_url, array(
        'timeout' => 15, // Kurzer Timeout für lokale Ressource
        'sslverify' => false, // Für lokale Anfragen keine SSL-Verifizierung nötig
    ));
    
    if (is_wp_error($response)) {
        error_log('Error accessing domain association file: ' . $response->get_error_message());
        
        // Versuche, die Datei zu aktualisieren
        $file_error = $this->update_domain_association_file();
        
        if ($file_error) {
            error_log('Failed to update domain association file: ' . $file_error);
            return [
                'success' => false,
                'message' => $file_error,
                'details' => [
                    'step' => 'file_verification',
                    'url' => $file_url,
                    'error' => $response->get_error_message(),
                    'code' => $response->get_error_code()
                ]
            ];
        }
        
        // Prüfe erneut nach dem Aktualisieren
        error_log('Checking domain association file again after update');
        $response = wp_remote_get($file_url, array(
            'timeout' => 15,
            'sslverify' => false,
        ));
    }
    
    $response_code = wp_remote_retrieve_response_code($response);
    error_log('Domain association file response code: ' . $response_code);
    
    if (is_wp_error($response) || 200 !== $response_code) {
        error_log('Domain association file is still not accessible after update');
        return [
            'success' => false,
            'message' => __('Domain association file is not accessible.', 'yprint-plugin'),
            'details' => [
                'step' => 'file_verification',
                'url' => $file_url,
                'response_code' => $response_code,
                'response_body' => is_wp_error($response) ? $response->get_error_message() : wp_remote_retrieve_body($response)
            ]
        ];
    }
    
    error_log('Domain association file is accessible, proceeding with domain verification');
    
    // Wenn die Datei zugänglich ist, melde nur das zurück ohne direkt die Verifizierung durchzuführen
    // Dies verhindert, dass die AJAX-Anfrage zu lange dauert
    return [
        'success' => true,
        'message' => __('Domain association file is accessible. You can now register your domain with Stripe.', 'yprint-plugin'),
        'details' => [
            'step' => 'file_verification_completed',
            'url' => $file_url,
            'next_step' => 'Run domain registration with Stripe separately',
        ]
    ];
}
}