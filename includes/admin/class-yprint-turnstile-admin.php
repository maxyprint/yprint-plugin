<?php
/**
 * YPrint Turnstile Admin Panel
 * Admin-Interface für Cloudflare Turnstile Einstellungen
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class YPrint_Turnstile_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            error_log('🚀 YPrint_Turnstile_Admin: Creating new instance');
            self::$instance = new self();
            error_log('✅ YPrint_Turnstile_Admin: Instance created successfully');
        }
        return self::$instance;
    }
    
    private function __construct() {
        error_log('🔧 YPrint_Turnstile_Admin: Constructor called');
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        error_log('🎯 YPrint_Turnstile_Admin: All hooks registered');
    }
    
    /**
 * Admin-Menü hinzufügen
 */
public function add_admin_menu() {
    error_log('📋 YPrint_Turnstile_Admin: add_admin_menu called');
    
    // Prüfe ob Parent-Menu existiert
    global $menu, $submenu;
    error_log('📋 Available parent menus: ' . print_r(array_keys($submenu), true));
    
    // Turnstile Untermenü an bestehendes YPrint-Hauptmenü anhängen
    $hook_suffix = add_submenu_page(
        'yprint-plugin',  // <-- Verwende bestehenden Parent-Slug
        'Turnstile Einstellungen',
        'Bot-Schutz (Turnstile)',
        'manage_options',
        'yprint-turnstile',
        array($this, 'render_turnstile_page')
    );
    
    error_log('📋 YPrint_Turnstile_Admin: Submenu added with hook_suffix: ' . $hook_suffix);
    error_log('📋 YPrint_Turnstile_Admin: Callback is callable: ' . (is_callable(array($this, 'render_turnstile_page')) ? 'YES' : 'NO'));
}
    
    /**
     * Einstellungen registrieren
     */
    public function register_settings() {
        register_setting('yprint_turnstile_settings', 'yprint_turnstile_options', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // Turnstile Sektion
        add_settings_section(
            'yprint_turnstile_main',
            'Cloudflare Turnstile Konfiguration',
            array($this, 'render_section_info'),
            'yprint_turnstile_settings'
        );
        
        // Site Key Feld
        add_settings_field(
            'site_key',
            'Site Key',
            array($this, 'render_site_key_field'),
            'yprint_turnstile_settings',
            'yprint_turnstile_main'
        );
        
        // Secret Key Feld
        add_settings_field(
            'secret_key',
            'Secret Key',
            array($this, 'render_secret_key_field'),
            'yprint_turnstile_settings',
            'yprint_turnstile_main'
        );
        
        // Aktivierung
        add_settings_field(
            'enabled',
            'Turnstile aktivieren',
            array($this, 'render_enabled_field'),
            'yprint_turnstile_settings',
            'yprint_turnstile_main'
        );
        
        // Seiten-Auswahl
        add_settings_field(
            'protected_pages',
            'Geschützte Bereiche',
            array($this, 'render_protected_pages_field'),
            'yprint_turnstile_settings',
            'yprint_turnstile_main'
        );
    }
    
    /**
     * Admin-Scripts laden
     */
    public function enqueue_admin_scripts($hook) {
        // Der korrekte Hook für ein Untermenü von 'yprint-plugin'
        if ($hook !== 'yprint-plugin_page_yprint-turnstile') {
            return;
        }
        
        wp_enqueue_script(
            'yprint-turnstile-admin',
            plugins_url('assets/js/yprint-turnstile-admin.js', dirname(__DIR__)),
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('yprint-turnstile-admin', 'yprintTurnstileAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yprint_turnstile_test_nonce')
        ));
    }
    
    /**
     * Hauptseite rendern (falls nicht existiert)
     */
    public function render_main_page() {
        ?>
        <div class="wrap">
            <h1>YPrint Einstellungen</h1>
            <p>Willkommen im YPrint Admin-Panel. Verwenden Sie die Untermenüs für spezifische Einstellungen.</p>
            <div class="card">
                <h2>Verfügbare Einstellungen</h2>
                <ul>
                    <li><a href="<?php echo admin_url('admin.php?page=yprint-turnstile'); ?>">Bot-Schutz (Turnstile)</a> - Cloudflare Turnstile Konfiguration</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
 * Turnstile Admin-Seite rendern
 */
public function render_turnstile_page() {
    error_log('🎨 YPrint_Turnstile_Admin: render_turnstile_page called - PAGE IS RENDERING!');
    error_log('🎨 Current user can manage options: ' . (current_user_can('manage_options') ? 'YES' : 'NO'));
    
    $options = get_option('yprint_turnstile_options', array());
    error_log('🎨 Loaded options: ' . print_r($options, true));
    ?>
        <div class="wrap">
            <h1>Cloudflare Turnstile Bot-Schutz</h1>
            
            <?php settings_errors(); ?>
            
            <div class="yprint-admin-header">
                <p>Schützen Sie Ihre Formulare vor Bots und Spam mit Cloudflare Turnstile - einer datenschutzfreundlichen Alternative zu reCAPTCHA.</p>
            </div>
            
            <div class="yprint-admin-container">
                <div class="yprint-admin-main">
                    <form method="post" action="options.php">
                        <?php
                        settings_fields('yprint_turnstile_settings');
                        do_settings_sections('yprint_turnstile_settings');
                        ?>
                        
                        <div class="yprint-admin-actions">
                            <?php submit_button('Einstellungen speichern', 'primary', 'submit', false); ?>
                            <button type="button" id="test-turnstile" class="button button-secondary">
                                <span class="dashicons dashicons-update-alt"></span>
                                Verbindung testen
                            </button>
                        </div>
                    </form>
                    
                    <div id="test-results" class="yprint-test-results" style="display: none;">
                        <h3>Test-Ergebnisse</h3>
                        <div id="test-content"></div>
                    </div>
                </div>
                
                <div class="yprint-admin-sidebar">
                    <div class="yprint-info-box">
                        <h3>📋 Setup-Anleitung</h3>
                        <ol>
                            <li>Registrieren Sie sich bei <a href="https://dash.cloudflare.com/" target="_blank">Cloudflare</a></li>
                            <li>Gehen Sie zu "Turnstile" im Dashboard</li>
                            <li>Erstellen Sie eine neue Website</li>
                            <li>Kopieren Sie Site Key und Secret Key hierher</li>
                            <li>Aktivieren Sie den Schutz</li>
                        </ol>
                    </div>
                    
                    <div class="yprint-info-box">
                        <h3>🛡️ Geschützte Bereiche</h3>
                        <p>Turnstile wird automatisch auf folgenden Bereichen aktiviert:</p>
                        <ul>
                            <li>✓ Benutzer-Registrierung</li>
                            <li>✓ Login-Formular</li>
                            <li>✓ Passwort-Wiederherstellung</li>
                            <li>✓ Kontakt-Formulare</li>
                            <li>✓ Checkout-Prozess (optional)</li>
                        </ul>
                    </div>
                    
                    <div class="yprint-info-box">
                        <h3>🎨 Anpassung</h3>
                        <p>Das Turnstile-Widget passt sich automatisch an Ihr Theme-Design an und unterstützt Dark/Light Mode.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <style>
        .yprint-admin-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-top: 20px;
        }
        
        .yprint-admin-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-left: 4px solid #0073aa;
            margin-bottom: 20px;
        }
        
        .yprint-info-box {
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 15px;
        }
        
        .yprint-info-box h3 {
            margin-top: 0;
            color: #333;
        }
        
        .yprint-info-box ul {
            margin: 0;
            padding-left: 20px;
        }
        
        .yprint-admin-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            margin-top: 20px;
        }
        
        .yprint-test-results {
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .yprint-status-indicator {
            display: inline-block;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            margin-right: 8px;
        }
        
        .status-active { background-color: #46b450; }
        .status-inactive { background-color: #dc3232; }
        .status-warning { background-color: #ffb900; }
        
        @media (max-width: 768px) {
            .yprint-admin-container {
                grid-template-columns: 1fr;
            }
        }
        </style>
        <?php
    }
    
    /**
     * Sektion Info rendern
     */
    public function render_section_info() {
        echo '<p>Konfigurieren Sie hier Ihre Cloudflare Turnstile Einstellungen für Bot-Schutz.</p>';
    }
    
    /**
     * Site Key Feld rendern
     */
    public function render_site_key_field() {
        $options = get_option('yprint_turnstile_options', array());
        $value = isset($options['site_key']) ? $options['site_key'] : '';
        $status = !empty($value) ? 'status-active' : 'status-inactive';
        
        echo '<div>';
        echo '<span class="yprint-status-indicator ' . $status . '"></span>';
        echo '<input type="text" name="yprint_turnstile_options[site_key]" value="' . esc_attr($value) . '" class="regular-text" placeholder="0x4AAAAAAA..." />';
        echo '<p class="description">Ihr öffentlicher Site Key von Cloudflare Turnstile</p>';
        echo '</div>';
    }
    
    /**
     * Secret Key Feld rendern
     */
    public function render_secret_key_field() {
        $options = get_option('yprint_turnstile_options', array());
        $value = isset($options['secret_key']) ? $options['secret_key'] : '';
        $status = !empty($value) ? 'status-active' : 'status-inactive';
        
        echo '<div>';
        echo '<span class="yprint-status-indicator ' . $status . '"></span>';
        echo '<input type="password" name="yprint_turnstile_options[secret_key]" value="' . esc_attr($value) . '" class="regular-text" placeholder="0x4AAAAAAA..." />';
        echo '<p class="description">Ihr privater Secret Key von Cloudflare Turnstile (wird sicher gespeichert)</p>';
        echo '</div>';
    }
    
    /**
     * Aktivierung Feld rendern
     */
    public function render_enabled_field() {
        $options = get_option('yprint_turnstile_options', array());
        $enabled = isset($options['enabled']) ? $options['enabled'] : false;
        
        echo '<label>';
        echo '<input type="checkbox" name="yprint_turnstile_options[enabled]" value="1" ' . checked(1, $enabled, false) . ' />';
        echo ' Turnstile Bot-Schutz aktivieren';
        echo '</label>';
        echo '<p class="description">Aktiviert den Bot-Schutz auf allen konfigurierten Formularen</p>';
    }
    
    /**
     * Geschützte Seiten Feld rendern
     */
    public function render_protected_pages_field() {
        $options = get_option('yprint_turnstile_options', array());
        $protected = isset($options['protected_pages']) ? $options['protected_pages'] : array();
        
        $available_protections = array(
            'registration' => 'Benutzer-Registrierung',
            'login' => 'Login-Formular',
            'password_recovery' => 'Passwort-Wiederherstellung',
            'contact_forms' => 'Kontakt-Formulare',
            'checkout' => 'Checkout-Prozess',
            'woocommerce_orders' => 'WooCommerce Bestellaktionen'
        );
        
        echo '<fieldset>';
        foreach ($available_protections as $key => $label) {
            $checked = in_array($key, $protected) ? 'checked' : '';
            echo '<label>';
            echo '<input type="checkbox" name="yprint_turnstile_options[protected_pages][]" value="' . esc_attr($key) . '" ' . $checked . ' />';
            echo ' ' . esc_html($label);
            echo '</label><br/>';
        }
        echo '<p class="description">Wählen Sie die Bereiche aus, die durch Turnstile geschützt werden sollen</p>';
        echo '</fieldset>';
    }
    
    /**
     * Einstellungen bereinigen
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['site_key'])) {
            $sanitized['site_key'] = sanitize_text_field($input['site_key']);
        }
        
        if (isset($input['secret_key'])) {
            $sanitized['secret_key'] = sanitize_text_field($input['secret_key']);
        }
        
        $sanitized['enabled'] = isset($input['enabled']) ? true : false;
        
        if (isset($input['protected_pages']) && is_array($input['protected_pages'])) {
            $sanitized['protected_pages'] = array_map('sanitize_text_field', $input['protected_pages']);
        } else {
            $sanitized['protected_pages'] = array();
        }
        
        // Erfolgreiche Speicherung anzeigen
        add_settings_error(
            'yprint_turnstile_options',
            'settings_updated',
            'Turnstile Einstellungen erfolgreich gespeichert!',
            'updated'
        );
        
        return $sanitized;
    }
}

// Initialisierung erfolgt jetzt zentral über yprint-plugin.php mit plugins_loaded Hook