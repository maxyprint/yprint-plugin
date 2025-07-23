<?php
/**
 * YPrint Consent Admin Panel
 * Admin-Interface für Cookie-Consent und Rechtstexte
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class YPrint_Consent_Admin {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX-Handler für Admin
        add_action('wp_ajax_yprint_save_legal_text', array($this, 'save_legal_text'));
        add_action('wp_ajax_yprint_export_consents', array($this, 'export_consents'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_submenu_page(
            'yprint-plugin',  // Parent slug (dein bestehendes Hauptmenü)
            'Cookie-Consent & Datenschutz',
            'Datenschutz & Cookies',
            'manage_options',
            'yprint-consent',
            array($this, 'render_consent_page')
        );
    }
    
    /**
     * Einstellungen registrieren
     */
    public function register_settings() {
        register_setting('yprint_consent_settings', 'yprint_consent_options', array(
            'sanitize_callback' => array($this, 'sanitize_settings')
        ));
        
        // Allgemeine Einstellungen
        add_settings_section(
            'yprint_consent_general',
            'Allgemeine Einstellungen',
            array($this, 'render_general_section'),
            'yprint_consent_settings'
        );
        
        add_settings_field(
            'consent_enabled',
            'Cookie-Banner aktivieren',
            array($this, 'render_enabled_field'),
            'yprint_consent_settings',
            'yprint_consent_general'
        );
        
        add_settings_field(
            'consent_position',
            'Banner-Position',
            array($this, 'render_position_field'),
            'yprint_consent_settings',
            'yprint_consent_general'
        );
    }
    
    /**
     * Admin-Scripts laden
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'yprint-consent') === false) {
            return;
        }
        
        wp_enqueue_style('yprint-consent-admin', YPRINT_PLUGIN_URL . 'assets/css/yprint-consent-admin.css', array(), YPRINT_PLUGIN_VERSION);
        wp_enqueue_script('yprint-consent-admin', YPRINT_PLUGIN_URL . 'assets/js/yprint-consent-admin.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
        
        wp_localize_script('yprint-consent-admin', 'yprintConsentAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yprint_consent_admin_nonce')
        ));
    }
    
    /**
     * Hauptseite rendern
     */
    public function render_consent_page() {
        $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'settings';
        ?>
        <div class="wrap">
            <h1>Cookie-Consent & Datenschutz</h1>
            
            <nav class="nav-tab-wrapper">
                <a href="?page=yprint-consent&tab=settings" class="nav-tab <?php echo $active_tab === 'settings' ? 'nav-tab-active' : ''; ?>">
                    Einstellungen
                </a>
                <a href="?page=yprint-consent&tab=texts" class="nav-tab <?php echo $active_tab === 'texts' ? 'nav-tab-active' : ''; ?>">
                    Rechtstexte
                </a>
                <a href="?page=yprint-consent&tab=statistics" class="nav-tab <?php echo $active_tab === 'statistics' ? 'nav-tab-active' : ''; ?>">
                    Statistiken
                </a>
                <a href="?page=yprint-consent&tab=export" class="nav-tab <?php echo $active_tab === 'export' ? 'nav-tab-active' : ''; ?>">
                    Export
                </a>
            </nav>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'texts':
                        $this->render_texts_tab();
                        break;
                    case 'statistics':
                        $this->render_statistics_tab();
                        break;
                    case 'export':
                        $this->render_export_tab();
                        break;
                    default:
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Einstellungen-Tab
     */
    private function render_settings_tab() {
        ?>
        <form method="post" action="options.php">
            <?php
            settings_fields('yprint_consent_settings');
            do_settings_sections('yprint_consent_settings');
            submit_button('Einstellungen speichern');
            ?>
        </form>
        <?php
    }
    
    /**
     * Rechtstexte-Tab
     */
    private function render_texts_tab() {
        global $wpdb;
        
        // Rechtstexte abrufen
        $texts = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}yprint_legal_texts 
             WHERE is_active = 1 AND language = 'de' 
             ORDER BY text_key",
            ARRAY_A
        );
        ?>
        <div class="yprint-legal-texts-editor">
            <h2>Rechtstexte bearbeiten</h2>
            <p>Hier kannst du alle Texte für Cookie-Banner und Datenschutzerklärung anpassen.</p>
            
            <?php foreach ($texts as $text): ?>
            <div class="yprint-text-editor-card" data-text-id="<?php echo esc_attr($text['id']); ?>">
                <h3><?php echo esc_html($this->get_text_title($text['text_key'])); ?></h3>
                <div class="yprint-text-editor-meta">
                    <span class="text-key">Schlüssel: <?php echo esc_html($text['text_key']); ?></span>
                    <span class="text-version">Version: <?php echo esc_html($text['version']); ?></span>
                    <span class="text-updated">Aktualisiert: <?php echo esc_html($text['updated_at']); ?></span>
                </div>
                
                <?php if (strpos($text['text_key'], 'CONTENT') !== false): ?>
                    <!-- Großer Editor für längere Texte -->
                    <?php
                    wp_editor($text['content'], 'text_editor_' . $text['id'], array(
                        'textarea_name' => 'legal_text_content',
                        'media_buttons' => false,
                        'textarea_rows' => 8,
                        'teeny' => true,
                        'tinymce' => array(
                            'toolbar1' => 'bold,italic,underline,separator,alignleft,aligncenter,alignright,separator,link,unlink,undo,redo',
                            'toolbar2' => ''
                        )
                    ));
                    ?>
                <?php else: ?>
                    <!-- Einfacher Textbereich für kurze Texte -->
                    <textarea 
                        name="legal_text_content" 
                        rows="3" 
                        style="width: 100%;"
                        class="large-text"
                    ><?php echo esc_textarea($text['content']); ?></textarea>
                <?php endif; ?>
                
                <div class="yprint-text-editor-actions">
                    <button type="button" class="button button-primary save-text-btn">
                        Speichern
                    </button>
                    <button type="button" class="button preview-text-btn">
                        Vorschau
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Statistiken-Tab
     */
    private function render_statistics_tab() {
        global $wpdb;
        
        // Consent-Statistiken abrufen
        $stats = $wpdb->get_results(
            "SELECT 
                consent_type,
                SUM(CASE WHEN granted = 1 THEN 1 ELSE 0 END) as granted_count,
                SUM(CASE WHEN granted = 0 THEN 1 ELSE 0 END) as denied_count,
                COUNT(*) as total_count
             FROM {$wpdb->prefix}yprint_consents 
             GROUP BY consent_type",
            ARRAY_A
        );
        
        // Letzte 30 Tage
        $recent_consents = $wpdb->get_results(
            "SELECT 
                DATE(created_at) as consent_date,
                COUNT(*) as count
             FROM {$wpdb->prefix}yprint_consents 
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(created_at)
             ORDER BY consent_date DESC",
            ARRAY_A
        );
        ?>
        <div class="yprint-consent-statistics">
            <h2>Consent-Statistiken</h2>
            
            <div class="yprint-stats-cards">
                <?php foreach ($stats as $stat): ?>
                <div class="yprint-stat-card">
                    <h3><?php echo esc_html($this->get_consent_type_name($stat['consent_type'])); ?></h3>
                    <div class="stat-numbers">
                        <div class="stat-granted">
                            <span class="number"><?php echo esc_html($stat['granted_count']); ?></span>
                            <span class="label">Akzeptiert</span>
                        </div>
                        <div class="stat-denied">
                            <span class="number"><?php echo esc_html($stat['denied_count']); ?></span>
                            <span class="label">Abgelehnt</span>
                        </div>
                        <div class="stat-total">
                            <span class="number"><?php echo esc_html($stat['total_count']); ?></span>
                            <span class="label">Gesamt</span>
                        </div>
                    </div>
                    
                    <?php if ($stat['total_count'] > 0): ?>
                    <div class="stat-percentage">
                        <?php $percentage = round(($stat['granted_count'] / $stat['total_count']) * 100, 1); ?>
                        <div class="percentage-bar">
                            <div class="percentage-fill" style="width: <?php echo $percentage; ?>%"></div>
                        </div>
                        <span class="percentage-text"><?php echo $percentage; ?>% Zustimmung</span>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="yprint-recent-activity">
                <h3>Aktivität der letzten 30 Tage</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Neue Einwilligungen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_consents as $consent): ?>
                        <tr>
                            <td><?php echo esc_html(date_i18n('d.m.Y', strtotime($consent['consent_date']))); ?></td>
                            <td><?php echo esc_html($consent['count']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
    
    /**
     * Export-Tab
     */
    private function render_export_tab() {
        ?>
        <div class="yprint-consent-export">
            <h2>Consent-Daten exportieren</h2>
            <p>Exportiere alle Einwilligungsdaten für rechtliche Dokumentation oder Analysen.</p>
            
            <div class="export-options">
                <div class="export-card">
                    <h3>Vollständiger Export</h3>
                    <p>Alle Einwilligungsdaten inklusive Metadaten (IP, User-Agent, Zeitstempel).</p>
                    <button type="button" class="button button-primary" id="export-full-consents">
                        CSV-Export starten
                    </button>
                </div>
                
                <div class="export-card">
                    <h3>Anonymisierter Export</h3>
                    <p>Nur Statistiken ohne personenbezogene Daten.</p>
                    <button type="button" class="button button-secondary" id="export-anonymous-stats">
                        Statistik-Export
                    </button>
                </div>
                
                <div class="export-card">
                    <h3>Rechtstexte Export</h3>
                    <p>Aktuelle Versionen aller Rechtstexte für Backup.</p>
                    <button type="button" class="button button-secondary" id="export-legal-texts">
                        Texte exportieren
                    </button>
                </div>
            </div>
            
            <div class="export-filters">
                <h3>Exportfilter</h3>
                <form id="export-filter-form">
                    <table class="form-table">
                        <tr>
                            <th>Zeitraum</th>
                            <td>
                                <input type="date" name="date_from" id="date_from">
                                bis
                                <input type="date" name="date_to" id="date_to">
                            </td>
                        </tr>
                        <tr>
                            <th>Consent-Typen</th>
                            <td>
                                <label><input type="checkbox" name="consent_types[]" value="PRIVACY_POLICY" checked> Datenschutzerklärung</label><br>
                                <label><input type="checkbox" name="consent_types[]" value="COOKIE_ESSENTIAL" checked> Essenzielle Cookies</label><br>
                                <label><input type="checkbox" name="consent_types[]" value="COOKIE_ANALYTICS" checked> Analyse Cookies</label><br>
                                <label><input type="checkbox" name="consent_types[]" value="COOKIE_MARKETING" checked> Marketing Cookies</label><br>
                                <label><input type="checkbox" name="consent_types[]" value="COOKIE_FUNCTIONAL" checked> Funktionale Cookies</label>
                            </td>
                        </tr>
                    </table>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * Hilfsfunktionen
     */
    private function get_text_title($key) {
        $titles = array(
            'COOKIE_BANNER_TITLE' => 'Cookie-Banner Titel',
            'COOKIE_BANNER_DESCRIPTION' => 'Cookie-Banner Beschreibung',
            'REGISTRATION_CONSENT_TEXT' => 'Registrierungs-Einwilligung',
            'PRIVACY_POLICY_CONTENT' => 'Datenschutzerklärung (Volltext)'
        );
        
        return isset($titles[$key]) ? $titles[$key] : $key;
    }
    
    private function get_consent_type_name($type) {
        $names = array(
            'PRIVACY_POLICY' => 'Datenschutzerklärung',
            'COOKIE_ESSENTIAL' => 'Essenzielle Cookies',
            'COOKIE_ANALYTICS' => 'Analyse Cookies',
            'COOKIE_MARKETING' => 'Marketing Cookies',
            'COOKIE_FUNCTIONAL' => 'Funktionale Cookies'
        );
        
        return isset($names[$type]) ? $names[$type] : $type;
    }
    
    /**
     * Einstellungen validieren
     */
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['consent_enabled'])) {
            $sanitized['consent_enabled'] = (bool) $input['consent_enabled'];
        }
        
        if (isset($input['consent_position'])) {
            $sanitized['consent_position'] = sanitize_text_field($input['consent_position']);
        }
        
        return $sanitized;
    }
    
    /**
     * AJAX: Rechtstext speichern
     */
    public function save_legal_text() {
        check_ajax_referer('yprint_consent_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
        }
        
        $text_id = intval($_POST['text_id']);
        $content = wp_kses_post($_POST['content']);
        
        global $wpdb;
        
        // Neue Version erstellen
        $old_text = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yprint_legal_texts WHERE id = %d",
            $text_id
        ));
        
        if (!$old_text) {
            wp_send_json_error(array('message' => 'Text nicht gefunden'));
        }
        
        // Versionsnummer erhöhen
        $version_parts = explode('.', $old_text->version);
        $version_parts[1] = intval($version_parts[1]) + 1;
        $new_version = implode('.', $version_parts);
        
        // Alten Text deaktivieren
        $wpdb->update(
            $wpdb->prefix . 'yprint_legal_texts',
            array('is_active' => 0),
            array('id' => $text_id)
        );
        
        // Neuen Text erstellen
        $result = $wpdb->insert(
            $wpdb->prefix . 'yprint_legal_texts',
            array(
                'text_key' => $old_text->text_key,
                'content' => $content,
                'version' => $new_version,
                'language' => $old_text->language,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
        
        if ($result) {
            wp_send_json_success(array(
                'message' => 'Text erfolgreich gespeichert',
                'new_version' => $new_version
            ));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Speichern'));
        }
    }
    
    /**
     * Render-Funktionen für Felder
     */
    public function render_general_section() {
        echo '<p>Grundeinstellungen für das Cookie-Consent-System.</p>';
    }
    
    public function render_enabled_field() {
        $options = get_option('yprint_consent_options', array());
        $enabled = isset($options['consent_enabled']) ? $options['consent_enabled'] : true;
        ?>
        <label>
            <input type="checkbox" name="yprint_consent_options[consent_enabled]" value="1" <?php checked($enabled); ?>>
            Cookie-Banner für Besucher anzeigen
        </label>
        <p class="description">Wenn deaktiviert, wird kein Cookie-Banner angezeigt.</p>
        <?php
    }
    
    public function render_position_field() {
        $options = get_option('yprint_consent_options', array());
        $position = isset($options['consent_position']) ? $options['consent_position'] : 'modal';
        ?>
        <select name="yprint_consent_options[consent_position]">
            <option value="modal" <?php selected($position, 'modal'); ?>>Modal (zentriert)</option>
            <option value="bottom" <?php selected($position, 'bottom'); ?>>Unten (Banner)</option>
            <option value="top" <?php selected($position, 'top'); ?>>Oben (Banner)</option>
        </select>
        <p class="description">Position des Cookie-Banners auf der Website.</p>
        <?php
    }
}

// Initialisieren (nur im Admin-Bereich)
add_action('plugins_loaded', function() {
    if (is_admin()) {
        YPrint_Consent_Admin::get_instance();
    }
});