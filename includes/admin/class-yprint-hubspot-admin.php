<?php
/**
 * YPrint HubSpot Admin Integration
 * 
 * @package YPrint
 * @since 1.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class YPrint_HubSpot_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_ajax_yprint_test_hubspot_connection', array($this, 'test_api_connection'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_submenu_page(
            'yprint-plugin', // Parent slug (bestehendes YPrint-Hauptmenü)
            'HubSpot Integration',
            'HubSpot Integration',
            'manage_options',
            'yprint-hubspot-settings',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Admin-Einstellungen initialisieren
     */
    public function admin_init() {
        register_setting('yprint_hubspot_settings', 'yprint_hubspot_api_key');
        register_setting('yprint_hubspot_settings', 'yprint_hubspot_enabled');
        register_setting('yprint_hubspot_settings', 'yprint_hubspot_debug_mode');
        
        add_settings_section(
            'yprint_hubspot_main',
            'HubSpot API Konfiguration',
            array($this, 'settings_section_callback'),
            'yprint-hubspot-settings'
        );
        
        add_settings_field(
            'yprint_hubspot_enabled',
            'HubSpot Integration aktivieren',
            array($this, 'enabled_field_callback'),
            'yprint-hubspot-settings',
            'yprint_hubspot_main'
        );
        
        add_settings_field(
            'yprint_hubspot_api_key',
            'HubSpot API Key',
            array($this, 'api_key_field_callback'),
            'yprint-hubspot-settings',
            'yprint_hubspot_main'
        );
        
        add_settings_field(
            'yprint_hubspot_debug_mode',
            'Debug-Modus',
            array($this, 'debug_mode_field_callback'),
            'yprint-hubspot-settings',
            'yprint_hubspot_main'
        );
    }
    
    /**
     * Settings-Sektion Callback
     */
    public function settings_section_callback() {
        echo '<div class="yprint-hubspot-setup">';
        echo '<h3>🍪 HubSpot Integration für YPrint</h3>';
        echo '<p>Konfiguriere deine HubSpot-Integration für automatische Kontakt-Erstellung bei Registrierungen.</p>';
        echo '<div class="yprint-setup-steps">';
        echo '<h4>📋 Setup-Schritte:</h4>';
        echo '<ol>';
        echo '<li><strong>HubSpot Private App erstellen:</strong><br>';
        echo 'Gehe zu deinem HubSpot Account → Settings → Integrations → Private Apps<br>';
        echo 'Erstelle eine neue Private App mit den Scopes: <code>crm.objects.contacts.read</code> und <code>crm.objects.contacts.write</code><br>';
        echo 'Kopiere den Access Token</li>';
        echo '<li><strong>WordPress Admin konfigurieren:</strong><br>';
        echo 'Aktiviere die Integration unten und füge deinen API Key ein</li>';
        echo '<li><strong>Testen:</strong><br>';
        echo 'Führe eine Testregistrierung durch und prüfe deine WordPress Error Logs</li>';
        echo '</ol>';
        echo '</div>';
        echo '</div>';
    }
    
    /**
     * Aktiviert-Feld Callback
     */
    public function enabled_field_callback() {
        $enabled = get_option('yprint_hubspot_enabled', false);
        echo '<input type="checkbox" name="yprint_hubspot_enabled" value="1" ' . checked(1, $enabled, false) . ' />';
        echo '<label for="yprint_hubspot_enabled">HubSpot-Kontakt-Erstellung bei Registrierungen aktivieren</label>';
    }
    
    /**
     * API-Key-Feld Callback
     */
    public function api_key_field_callback() {
        $api_key = get_option('yprint_hubspot_api_key', '');
        echo '<input type="password" name="yprint_hubspot_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
        echo '<p class="description">Dein HubSpot Private App Access Token</p>';
    }
    
    /**
     * Debug-Modus-Feld Callback
     */
    public function debug_mode_field_callback() {
        $debug_mode = get_option('yprint_hubspot_debug_mode', false);
        echo '<input type="checkbox" name="yprint_hubspot_debug_mode" value="1" ' . checked(1, $debug_mode, false) . ' />';
        echo '<label for="yprint_hubspot_debug_mode">Detailliertes Logging aktivieren (prüfe WordPress Error Logs)</label>';
    }
    
    /**
     * Admin-Seite Template
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1>🍪 YPrint HubSpot Integration</h1>
            
            <div class="yprint-admin-content">
                <form method="post" action="options.php">
                    <?php
                    settings_fields('yprint_hubspot_settings');
                    do_settings_sections('yprint-hubspot-settings');
                    submit_button('Einstellungen speichern');
                    ?>
                </form>
                
                <div class="yprint-test-section">
                    <h2>🔧 Verbindung testen</h2>
                    <p>Teste deine HubSpot API Verbindung:</p>
                    <button type="button" id="test-hubspot-connection" class="button button-secondary">Verbindung testen</button>
                    <div id="hubspot-test-result" style="margin-top: 10px;"></div>
                </div>
                
                <div class="yprint-stats-section">
                    <h2>📊 Integration Statistiken</h2>
                    <?php $this->display_integration_stats(); ?>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                $('#test-hubspot-connection').click(function() {
                    var button = $(this);
                    var resultDiv = $('#hubspot-test-result');
                    
                    button.prop('disabled', true).text('Teste...');
                    resultDiv.html('<p>🔍 Teste Verbindung...</p>');
                    
                    $.post(ajaxurl, {
                        action: 'yprint_test_hubspot_connection',
                        nonce: '<?php echo wp_create_nonce('yprint_hubspot_test'); ?>'
                    }, function(response) {
                        if (response.success) {
                            resultDiv.html('<div class="notice notice-success"><p>✅ ' + response.data.message + '</p></div>');
                        } else {
                            resultDiv.html('<div class="notice notice-error"><p>❌ ' + response.data.message + '</p></div>');
                        }
                    }).always(function() {
                        button.prop('disabled', false).text('Verbindung testen');
                    });
                });
            });
            </script>
        </div>
        
        <style>
        .yprint-hubspot-setup {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        .yprint-setup-steps ol {
            margin-left: 20px;
        }
        
        .yprint-setup-steps li {
            margin-bottom: 15px;
        }
        
        .yprint-setup-steps code {
            background: #e1e1e1;
            padding: 2px 6px;
            border-radius: 3px;
        }
        
        .yprint-admin-content {
            max-width: 800px;
        }
        
        .yprint-test-section,
        .yprint-stats-section {
            background: #fff;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 20px;
        }
        </style>
        <?php
    }
    
    /**
     * Integration-Statistiken anzeigen
     */
    private function display_integration_stats() {
        global $wpdb;
        
        // Zähle User mit HubSpot Contact ID
        $users_with_hubspot = $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->usermeta} 
             WHERE meta_key = 'hubspot_contact_id' AND meta_value != ''"
        );
        
        // Zähle erfolgreiche Registrierungen heute
        $today_registrations = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->users} 
             WHERE DATE(user_registered) = %s",
            current_time('Y-m-d')
        ));
        
        echo '<div class="yprint-stats-grid">';
        echo '<div class="yprint-stat-card">';
        echo '<h3>👥 HubSpot Kontakte</h3>';
        echo '<p class="stat-number">' . ($users_with_hubspot ?: 0) . '</p>';
        echo '<p class="stat-description">Erfolgreich erstellte Kontakte</p>';
        echo '</div>';
        
        echo '<div class="yprint-stat-card">';
        echo '<h3>📅 Heute registriert</h3>';
        echo '<p class="stat-number">' . ($today_registrations ?: 0) . '</p>';
        echo '<p class="stat-description">Neue Registrierungen heute</p>';
        echo '</div>';
        echo '</div>';
        
        echo '<style>
        .yprint-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 15px;
        }
        
        .yprint-stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid #e9ecef;
        }
        
        .yprint-stat-card h3 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 14px;
        }
        
        .stat-number {
            font-size: 32px;
            font-weight: bold;
            color: #007cba;
            margin: 10px 0;
        }
        
        .stat-description {
            color: #6c757d;
            font-size: 12px;
            margin: 0;
        }
        </style>';
    }
    
    /**
     * API-Verbindung via AJAX testen
     */
    public function test_api_connection() {
        check_ajax_referer('yprint_hubspot_test', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unzureichende Berechtigungen'));
        }
        
        if (!class_exists('YPrint_HubSpot_API')) {
            wp_send_json_error(array('message' => 'HubSpot API Klasse nicht gefunden'));
        }
        
        $hubspot_api = YPrint_HubSpot_API::get_instance();
        $result = $hubspot_api->test_connection();
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Verbindung erfolgreich! HubSpot API funktioniert korrekt.'));
        } else {
            wp_send_json_error(array('message' => 'Verbindung fehlgeschlagen: ' . $result['message']));
        }
    }
}