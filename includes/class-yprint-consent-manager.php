<?php
/**
 * YPrint Consent Manager
 * Zentrale Verwaltung für Cookie-Consent und DSGVO-Einwilligungen
 *
 * @package YPrint
 * @since 1.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class YPrint_Consent_Manager {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // AJAX-Handler
        add_action('wp_ajax_yprint_save_consent', array($this, 'save_consent'));
        add_action('wp_ajax_nopriv_yprint_save_consent', array($this, 'save_consent'));
        add_action('wp_ajax_yprint_get_consent_status', array($this, 'get_consent_status'));
        add_action('wp_ajax_nopriv_yprint_get_consent_status', array($this, 'get_consent_status'));
        
        // Frontend-Assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_consent_assets'));
        
        // Banner und Icon rendern
        add_action('wp_footer', array($this, 'render_cookie_banner'));
        add_action('wp_footer', array($this, 'render_consent_icon'));
        
        // ✅ NEU: Automatische Synchronisation beim Plugin-Start
        add_action('init', array($this, 'auto_sync_legal_texts'));
        
        // ✅ NEU: Registriere Admin-Hooks
        add_action('admin_init', array($this, 'register_admin_hooks'));
        
        // Session für Gäste initialisieren
        add_action('init', array($this, 'init_session'));
    }
    
    /**
     * Session für Gäste initialisieren
     */
    public function init_session() {
        if (!session_id() && !is_admin()) {
            session_start();
        }
    }
    
    /**
     * Assets laden
     */
    public function enqueue_consent_assets() {
        wp_enqueue_style('yprint-consent', YPRINT_PLUGIN_URL . 'assets/css/yprint-consent.css', array(), YPRINT_PLUGIN_VERSION);
        wp_enqueue_script('yprint-consent', YPRINT_PLUGIN_URL . 'assets/js/yprint-consent.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
        
        // Inline CSS für höhere Priorität hinzufügen
        wp_add_inline_style('yprint-consent', '
            /* Forciere YPrint Consent Styles */
            .yprint-cookie-banner-close {
                background: none !important;
                border: none !important;
                font-size: 24px !important;
                cursor: pointer !important;
                color: #6b7280 !important;
                padding: 0 !important;
                width: 32px !important;
                height: 32px !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
                border-radius: 50% !important;
                transition: background-color 0.2s !important;
            }
            
            .yprint-consent-icon-btn {
                background: #2997FF !important;
                border: none !important;
                border-radius: 50% !important;
                width: 60px !important;
                height: 60px !important;
                font-size: 24px !important;
                cursor: pointer !important;
                box-shadow: 0 4px 12px rgba(41, 151, 255, 0.3) !important;
                transition: all 0.3s !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }
            
            .yprint-btn {
                padding: 12px 24px !important;
                border: none !important;
                border-radius: 6px !important;
                font-weight: 600 !important;
                cursor: pointer !important;
                transition: all 0.2s !important;
                font-size: 14px !important;
                font-family: "Roboto", Arial, sans-serif !important;
            }
            
            .yprint-btn-primary {
                background: #2997FF !important;
                color: white !important;
            }
        ');
        
        wp_localize_script('yprint-consent', 'yprintConsent', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yprint_consent_nonce'),
            'isLoggedIn' => is_user_logged_in(),
            'texts' => $this->get_consent_texts()
        ));
    }
    
    /**
     * Cookie-Banner rendern
     */
    public function render_cookie_banner() {
        // Banner IMMER rendern, Sichtbarkeit über CSS steuern
        include YPRINT_PLUGIN_DIR . 'templates/consent/cookie-banner.php';
    }
    
    /**
     * Consent-Icon rendern (permanent)
     */
    public function render_consent_icon() {
        // Icon IMMER rendern für einfache Zugänglichkeit
        include YPRINT_PLUGIN_DIR . 'templates/consent/consent-icon.php';
    }
    
    /**
     * Prüfen ob Banner initial angezeigt werden soll
     */
    public function should_show_banner_initially() {
        // Debug-Logging hinzufügen
        $show_banner = false;
        
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $has_decision = $this->has_any_consent_decision($user_id);
            $has_valid_consent = $this->has_valid_user_consent($user_id);
            
            // Banner nur zeigen wenn keine Entscheidung ODER keine gültige Entscheidung
            $show_banner = !$has_decision || !$has_valid_consent;
            
            error_log('🍪 PHP: User logged in, has_decision: ' . ($has_decision ? 'true' : 'false') . 
                     ', has_valid_consent: ' . ($has_valid_consent ? 'true' : 'false') . 
                     ', show_banner: ' . ($show_banner ? 'true' : 'false'));
        } else {
            $has_guest_decision = $this->has_guest_consent_decision();
            $has_valid_guest_consent = $this->has_valid_guest_consent();
            
            // Banner nur zeigen wenn keine Entscheidung ODER keine gültige Entscheidung
            $show_banner = !$has_guest_decision || !$has_valid_guest_consent;
            
            error_log('🍪 PHP: Guest user, has_guest_decision: ' . ($has_guest_decision ? 'true' : 'false') . 
                     ', has_valid_guest_consent: ' . ($has_valid_guest_consent ? 'true' : 'false') . 
                     ', show_banner: ' . ($show_banner ? 'true' : 'false'));
            
            // Debug: Zeige welche Cookies vorhanden sind
            error_log('🍪 PHP: Vorhandene Cookies: ' . json_encode($_COOKIE));
        }
        
        return $show_banner;
    }
    
    /**
     * Prüfen ob Banner gezeigt werden soll
     */
    private function should_show_banner() {
        // Für eingeloggte Nutzer: Nur zeigen wenn noch nie eine Entscheidung getroffen wurde
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return !$this->has_any_consent_decision($user_id);
        } else {
            // Für Gäste: Nur zeigen wenn kein Cookie mit Entscheidung existiert
            return !$this->has_guest_consent_decision();
        }
    }
    
    /**
     * Prüfen ob Icon angezeigt werden soll
     */
    private function should_show_icon() {
        // Icon IMMER anzeigen (nach einer initialen Entscheidung)
        if (is_user_logged_in()) {
            return $this->has_any_consent_decision(get_current_user_id());
        } else {
            return $this->has_guest_consent_decision();
        }
    }

    /**
     * Icon für erste Besucher auch anzeigen (temporär)
     */
    public function should_show_icon_always() {
        return true; // Icon immer anzeigen
    }
    
    /**
     * Prüfen ob User bereits eine Consent-Entscheidung getroffen hat
     */
    private function has_any_consent_decision($user_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}yprint_consents 
             WHERE user_id = %d AND consent_type LIKE 'COOKIE_%'",
            $user_id
        ));
        
        return $count > 0;
    }
    
    /**
     * Prüfen ob Gast bereits eine Consent-Entscheidung getroffen hat
     */
    private function has_guest_consent_decision() {
        // Prüfung auf spezifisches Cookie das Entscheidung dokumentiert
        $has_decision_cookie = isset($_COOKIE['yprint_consent_decision']);
        $has_preferences_cookie = isset($_COOKIE['yprint_consent_preferences']);
        
        // Mindestens eines der beiden Haupt-Cookies muss vorhanden sein
        $has_basic_decision = $has_decision_cookie || $has_preferences_cookie;
        
        if (!$has_basic_decision) {
            error_log('🍪 PHP: Guest consent decision check - keine Cookies vorhanden, Banner wird angezeigt');
            return false;
        }
        
        // Zusätzliche Validierung: Prüfe ob Timestamp nicht zu alt ist (nur wenn vorhanden)
        $has_valid_timestamp = true; // Standard: gültig
        $timestamp_value = 'nicht vorhanden';
        
        if (isset($_COOKIE['yprint_consent_timestamp'])) {
            $timestamp = intval($_COOKIE['yprint_consent_timestamp']);
            $one_year_ago = time() - (365 * 24 * 60 * 60);
            $has_valid_timestamp = $timestamp >= $one_year_ago;
            $timestamp_value = $timestamp;
            
            if (!$has_valid_timestamp) {
                error_log('🍪 PHP: Guest consent decision check - Timestamp zu alt (' . $timestamp . ' vs ' . $one_year_ago . ')');
            }
        } else {
            error_log('🍪 PHP: Guest consent decision check - kein Timestamp vorhanden, aber andere Cookies da');
        }
        
        $has_decision = $has_basic_decision && $has_valid_timestamp;
        
        error_log('🍪 PHP: Guest consent decision check - has_decision_cookie: ' . ($has_decision_cookie ? 'true' : 'false') . 
                 ', has_preferences_cookie: ' . ($has_preferences_cookie ? 'true' : 'false') . 
                 ', has_valid_timestamp: ' . ($has_valid_timestamp ? 'true' : 'false') . 
                 ', timestamp_value: ' . $timestamp_value .
                 ', final_result: ' . ($has_decision ? 'true' : 'false'));
        
        return $has_decision;
    }
    
    /**
     * Prüfen ob eingeloggter User Consent gegeben hat
     */
    private function has_user_given_consent($user_id, $consent_type) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}yprint_consents 
             WHERE user_id = %d AND consent_type = %s AND granted = 1",
            $user_id,
            $consent_type
        ));
        
        return $count > 0;
    }
    
    /**
     * Prüfen ob eingeloggter User gültige Consent-Einstellungen hat
     */
    private function has_valid_user_consent($user_id) {
        global $wpdb;
        
        // Prüfe ob User aktuelle Consent-Einstellungen hat (nicht älter als 1 Jahr)
        $one_year_ago = date('Y-m-d H:i:s', strtotime('-1 year'));
        
        $current_consents = $wpdb->get_results($wpdb->prepare(
            "SELECT consent_type, granted, created_at 
             FROM {$wpdb->prefix}yprint_consents 
             WHERE user_id = %d 
             AND consent_type LIKE 'COOKIE_%'
             AND created_at >= %s
             AND is_current = 1",
            $user_id,
            $one_year_ago
        ));
        
        // Mindestens eine Cookie-Consent-Entscheidung muss vorhanden sein
        if (empty($current_consents)) {
            error_log('🍪 PHP: User ' . $user_id . ' hat keine aktuellen Consent-Einstellungen');
            return false;
        }
        
        // Prüfe ob alle wichtigen Cookie-Typen abgedeckt sind
        $required_types = array('COOKIE_ESSENTIAL');
        $found_types = array();
        
        foreach ($current_consents as $consent) {
            $found_types[] = $consent->consent_type;
        }
        
        // Mindestens essenzielle Cookies müssen vorhanden sein
        $has_essential = in_array('COOKIE_ESSENTIAL', $found_types);
        
        if (!$has_essential) {
            error_log('🍪 PHP: User ' . $user_id . ' hat keine essenziellen Cookie-Einstellungen');
            return false;
        }
        
        error_log('🍪 PHP: User ' . $user_id . ' hat gültige Consent-Einstellungen (' . count($current_consents) . ' Einträge)');
        return true;
    }
    
    /**
     * Prüfen ob Gast Consent gegeben hat
     */
    private function has_guest_given_consent() {
        return isset($_COOKIE['yprint_consent_preferences']);
    }
    
    /**
     * Prüfen ob Gast gültige Consent-Einstellungen hat
     */
    private function has_valid_guest_consent() {
        // Prüfe ob gültige Cookie-Präferenzen vorhanden sind
        if (!isset($_COOKIE['yprint_consent_preferences'])) {
            error_log('🍪 PHP: Gast hat keine Cookie-Präferenzen');
            return false;
        }
        
        $preferences_data = json_decode(stripslashes($_COOKIE['yprint_consent_preferences']), true);
        
        // Prüfe ob die Daten gültig sind
        if (!$preferences_data || !isset($preferences_data['consents'])) {
            error_log('🍪 PHP: Gast-Cookie-Daten sind ungültig');
            return false;
        }
        
        $preferences = $preferences_data['consents'];
        
        // Mindestens essenzielle Cookies müssen akzeptiert sein
        if (!isset($preferences['cookie_essential']) || !$preferences['cookie_essential']) {
            error_log('🍪 PHP: Gast hat keine essenziellen Cookie-Einstellungen');
            return false;
        }
        
        // Prüfe Cookie-Alter (maximal 1 Jahr) - nur wenn Timestamp vorhanden
        if (isset($_COOKIE['yprint_consent_timestamp'])) {
            $timestamp = intval($_COOKIE['yprint_consent_timestamp']);
            $one_year_ago = time() - (365 * 24 * 60 * 60);
            
            if ($timestamp < $one_year_ago) {
                error_log('🍪 PHP: Gast-Cookie ist zu alt (älter als 1 Jahr)');
                return false;
            }
        } else {
            // Kein Timestamp vorhanden - prüfe ob in den Cookie-Daten
            if (isset($preferences_data['timestamp'])) {
                $timestamp = intval($preferences_data['timestamp']);
                $one_year_ago = time() - (365 * 24 * 60 * 60);
                
                if ($timestamp < $one_year_ago) {
                    error_log('🍪 PHP: Gast-Cookie ist zu alt (älter als 1 Jahr) - aus Cookie-Daten');
                    return false;
                }
            }
        }
        
        error_log('🍪 PHP: Gast hat gültige Consent-Einstellungen');
        return true;
    }
    
    /**
     * Consent-Texte abrufen
     */
    private function get_consent_texts() {
        global $wpdb;
        
        // ✅ NEU: Kombiniere Datenbank-Texte mit Datei-Texten
        $db_texts = $wpdb->get_results(
            "SELECT text_key, content FROM {$wpdb->prefix}yprint_legal_texts 
             WHERE is_active = 1 AND language = 'de'",
            OBJECT_K
        );
        
        $result = array();
        foreach ($db_texts as $key => $text) {
            $result[$key] = $text->content;
        }
        
        // ✅ NEU: Fallback auf Datei-Texte für wichtige Rechtstexte
        $fallback_texts = array(
            'PRIVACY_POLICY_CONTENT' => $this->get_privacy_policy_from_file(),
            'COOKIE_BANNER_TITLE' => 'Diese Website verwendet Cookies',
            'COOKIE_BANNER_DESCRIPTION' => 'Wir verwenden Cookies und ähnliche Technologien, um unsere Website zu verbessern und Ihnen ein optimales Nutzererlebnis zu bieten. Weitere Informationen finden Sie in unserer Datenschutzerklärung.'
        );
        
        // Verwende Datenbank-Texte, falls vorhanden, sonst Fallback
        foreach ($fallback_texts as $key => $fallback_content) {
            if (!isset($result[$key]) || empty($result[$key])) {
                $result[$key] = $fallback_content;
            }
        }
        
        return $result;
    }
    
    /**
     * ✅ NEU: Lädt Datenschutzerklärung aus Datei als Fallback
     */
    private function get_privacy_policy_from_file() {
        $privacy_file = YPRINT_PLUGIN_DIR . 'Rechtstexte/Datenschutzerklaerung.htm';
        
        if (file_exists($privacy_file)) {
            $content = file_get_contents($privacy_file);
            return wp_kses_post($content);
        }
        
        return 'Datenschutzerklärung nicht verfügbar.';
    }
    
    /**
     * ✅ NEU: Prüft ob Datenschutzerklärung in Datenbank verfügbar ist
     */
    private function has_privacy_policy_in_db() {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}yprint_legal_texts 
             WHERE text_key = %s AND is_active = 1 AND language = 'de'",
            'PRIVACY_POLICY_CONTENT'
        ));
        
        return $count > 0;
    }
    
    /**
     * ✅ NEU: Synchronisiert Datenschutzerklärung zwischen Datei und DB
     */
    public function sync_privacy_policy() {
        global $wpdb;
        
        $privacy_file = YPRINT_PLUGIN_DIR . 'Rechtstexte/Datenschutzerklaerung.htm';
        
        if (!file_exists($privacy_file)) {
            error_log('🍪 YPrint: Datenschutzerklärung-Datei nicht gefunden');
            return false;
        }
        
        $file_content = file_get_contents($privacy_file);
        $clean_content = wp_kses_post($file_content);
        
        try {
            // Prüfe ob Text bereits in DB existiert
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}yprint_legal_texts 
                 WHERE text_key = %s AND language = 'de'",
                'PRIVACY_POLICY_CONTENT'
            ));
            
            if ($existing) {
                // Update bestehenden Text
                $wpdb->update(
                    $wpdb->prefix . 'yprint_legal_texts',
                    array(
                        'content' => $clean_content,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $existing)
                );
            } else {
                // Erstelle neuen Text
                $wpdb->insert(
                    $wpdb->prefix . 'yprint_legal_texts',
                    array(
                        'text_key' => 'PRIVACY_POLICY_CONTENT',
                        'content' => $clean_content,
                        'version' => '1.0',
                        'language' => 'de',
                        'is_active' => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    )
                );
            }
            
            error_log('🍪 YPrint: Datenschutzerklärung erfolgreich synchronisiert');
            return true;
            
        } catch (Exception $e) {
            error_log('🍪 YPrint: Fehler bei Datenschutz-Synchronisation: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * AJAX: Consent speichern
     */
    public function save_consent() {
        check_ajax_referer('yprint_consent_nonce', 'nonce');
        
        $consent_data = isset($_POST['consents']) ? wp_unslash($_POST['consents']) : array();
        $version = isset($_POST['version']) ? sanitize_text_field($_POST['version']) : '1.0';
        
        if (is_user_logged_in()) {
            $result = $this->save_user_consent(get_current_user_id(), $consent_data, $version);
        } else {
            $result = $this->save_guest_consent($consent_data);
        }
        
        if ($result) {
            wp_send_json_success(array('message' => 'Einstellungen gespeichert'));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Speichern'));
        }
    }
    
    /**
     * User-Consent in Datenbank speichern
     */
    private function save_user_consent($user_id, $consent_data, $version) {
        global $wpdb;
        
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
        
        // ✅ VALIDIERUNG: Prüfe auf verdächtige automatische Klicks
        $this->detect_automated_clicks($consent_data, $user_id);
        
        foreach ($consent_data as $consent_type => $granted) {
            $granted = (bool) $granted;
            
            // ✅ KRITISCH: Essenzielle Cookies IMMER auf true setzen
            if (strtoupper($consent_type) === 'COOKIE_ESSENTIAL') {
                $granted = true;
                error_log('🍪 FORCE: Essenzielle Cookies immer akzeptiert für User ' . $user_id);
            }
            
            // ✅ VALIDIERUNG: Logische Konsistenz prüfen
            $this->validate_consent_logic($consent_data, $consent_type, $granted, $user_id);
            
            // Bestehenden Consent prüfen
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}yprint_consents 
                 WHERE user_id = %d AND consent_type = %s",
                $user_id,
                strtoupper($consent_type)
            ));
            
            if ($existing) {
                $wpdb->update(
                    $wpdb->prefix . 'yprint_consents',
                    array(
                        'granted' => $granted ? 1 : 0,
                        'version' => $version,
                        'ip_address' => $ip_address,
                        'user_agent' => $user_agent,
                        'updated_at' => current_time('mysql')
                    ),
                    array('id' => $existing)
                );
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'yprint_consents',
                    array(
                        'user_id' => $user_id,
                        'consent_type' => strtoupper($consent_type),
                        'granted' => $granted ? 1 : 0,
                        'version' => $version,
                        'ip_address' => $ip_address,
                        'user_agent' => $user_agent,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    )
                );
            }
        }
        
        return true;
    }
    
    /**
     * Gast-Consent in Cookie speichern
     */
    private function save_guest_consent($consent_data) {
        // ✅ VALIDIERUNG: Essenzielle Cookies auch für Gäste erzwingen
        if (isset($consent_data['cookie_essential'])) {
            $consent_data['cookie_essential'] = true;
            error_log('🍪 FORCE: Essenzielle Cookies für Gast immer akzeptiert');
        }
        
        $cookie_data = array(
            'consents' => $consent_data,
            'timestamp' => time(),
            'version' => '1.0'
        );
        
        // Haupt-Cookie setzen
        setcookie(
            'yprint_consent_preferences',
            json_encode($cookie_data),
            time() + (365 * 24 * 60 * 60), // 1 Jahr
            '/',
            '',
            is_ssl(),
            true
        );
        
        // Zusätzliches Timestamp-Cookie für einfachere Validierung
        setcookie(
            'yprint_consent_timestamp',
            time(),
            time() + (365 * 24 * 60 * 60), // 1 Jahr
            '/',
            '',
            is_ssl(),
            true
        );
        
        // Entscheidungs-Cookie setzen
        setcookie(
            'yprint_consent_decision',
            '1',
            time() + (365 * 24 * 60 * 60), // 1 Jahr
            '/',
            '',
            is_ssl(),
            true
        );
        
        return true;
    }
    
    /**
     * ✅ NEU: Automatisierte Klicks erkennen
     */
    private function detect_automated_clicks($consent_data, $user_id) {
        // Prüfe auf "Alle akzeptieren" Pattern (alle Cookies auf true)
        $all_accepted = true;
        $all_denied = true;
        
        foreach ($consent_data as $type => $granted) {
            if ($type !== 'cookie_essential') { // Essenzielle ausnehmen
                if (!$granted) $all_accepted = false;
                if ($granted) $all_denied = false;
            }
        }
        
        if ($all_accepted) {
            error_log('🍪 WARNUNG: Möglicher automatischer "Alle akzeptieren" Klick für User ' . $user_id);
        }
        
        if ($all_denied) {
            error_log('🍪 WARNUNG: Möglicher automatischer "Alle ablehnen" Klick für User ' . $user_id);
        }
    }
    
    /**
     * ✅ NEU: Logische Konsistenz validieren
     */
    private function validate_consent_logic($consent_data, $consent_type, $granted, $user_id) {
        // Prüfe Inkonsistenz: Privacy Policy akzeptiert aber alle Cookies abgelehnt
        if (isset($consent_data['privacy_policy']) && $consent_data['privacy_policy'] && 
            strpos($consent_type, 'cookie_') === 0 && !$granted) {
            error_log('🍪 WARNUNG: Inkonsistenz - Privacy Policy akzeptiert aber Cookie abgelehnt: ' . $consent_type . ' für User ' . $user_id);
        }
        
        // Prüfe ob essenzielle Cookies abgelehnt wurden (sollte nie passieren)
        if (strtoupper($consent_type) === 'COOKIE_ESSENTIAL' && !$granted) {
            error_log('🍪 KRITISCHER FEHLER: Essenzielle Cookies wurden abgelehnt für User ' . $user_id . ' - wird korrigiert');
        }
    }
    
    /**
     * Client IP ermitteln
     */
    private function get_client_ip() {
        $ip_keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (array_map('trim', explode(',', $_SERVER[$key])) as $ip) {
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '0.0.0.0';
    }
    
    /**
     * AJAX: Consent-Status abfragen
     */
    public function get_consent_status() {
        check_ajax_referer('yprint_consent_nonce', 'nonce');
        
        try {
            if (is_user_logged_in()) {
                $consents = $this->get_user_consents(get_current_user_id());
            } else {
                $consents = $this->get_guest_consents();
            }
            
            // Sicherstellen, dass $consents ein Array ist
            if (!is_array($consents)) {
                $consents = array();
            }
            
            error_log('🍪 PHP: Consent-Status abgerufen: ' . json_encode($consents));
            wp_send_json_success($consents);
        } catch (Exception $e) {
            error_log('🍪 PHP ERROR: ' . $e->getMessage());
            wp_send_json_success(array()); // Leeres Array als Fallback
        }
    }
    
    /**
     * User-Consents aus DB abrufen
     */
    private function get_user_consents($user_id) {
        global $wpdb;
        
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT consent_type, granted, version, created_at 
             FROM {$wpdb->prefix}yprint_consents 
             WHERE user_id = %d",
            $user_id
        ));
        
        $consents = array();
        foreach ($results as $result) {
            $consents[strtolower($result->consent_type)] = array(
                'granted' => (bool) $result->granted,
                'version' => $result->version,
                'timestamp' => $result->created_at
            );
        }
        
        return $consents;
    }
    
    /**
     * Gast-Consents aus Cookie abrufen
     */
    private function get_guest_consents() {
        if (isset($_COOKIE['yprint_consent_preferences'])) {
            $decoded = json_decode($_COOKIE['yprint_consent_preferences'], true);
            
            // JSON-Decode-Fehler abfangen
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log('🍪 PHP: Cookie JSON decode error: ' . json_last_error_msg());
                return array();
            }
            
            // Nur die consents zurückgeben, falls verschachtelt
            if (isset($decoded['consents'])) {
                return $decoded['consents'];
            }
            
            return is_array($decoded) ? $decoded : array();
        }
        
        return array();
    }

    /**
     * ✅ NEU: Automatische Synchronisation der Rechtstexte
     */
    public function auto_sync_legal_texts() {
        // Nur einmal pro Session ausführen
        if (isset($_SESSION['yprint_legal_synced'])) {
            return;
        }
        
        // Prüfe ob Datenschutzerklärung in DB verfügbar ist
        if (!$this->has_privacy_policy_in_db()) {
            error_log('🍪 YPrint: Automatische Synchronisation der Datenschutzerklärung...');
            $this->sync_privacy_policy();
        }
        
        $_SESSION['yprint_legal_synced'] = true;
    }
    
    /**
     * ✅ NEU: Registriert Admin-Hooks
     */
    public function register_admin_hooks() {
        // Admin-Menü nur für Administratoren
        if (current_user_can('manage_options')) {
            add_action('admin_menu', array($this, 'add_admin_menu'));
        }
    }
    
    /**
     * ✅ NEU: Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_submenu_page(
            'yprint-plugin',  // Parent slug
            'Cookie-Consent & Datenschutz',
            'Datenschutz & Cookies',
            'manage_options',
            'yprint-consent',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * ✅ NEU: Admin-Seite rendern
     */
    public function render_admin_page() {
        // Verwende die Admin-Klasse für das Rendering
        $admin = YPrint_Consent_Admin::get_instance();
        $admin->render_consent_page();
    }
}

// Initialisieren
add_action('plugins_loaded', function() {
    YPrint_Consent_Manager::get_instance();
});