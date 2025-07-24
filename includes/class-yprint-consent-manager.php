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
        add_action('wp_enqueue_scripts', array($this, 'enqueue_consent_assets'));
        add_action('wp_footer', array($this, 'render_cookie_banner'));
        add_action('wp_footer', array($this, 'render_consent_icon'));
        
        // AJAX-Handler
        add_action('wp_ajax_yprint_save_consent', array($this, 'save_consent'));
        add_action('wp_ajax_nopriv_yprint_save_consent', array($this, 'save_consent'));
        add_action('wp_ajax_yprint_get_consent_status', array($this, 'get_consent_status'));
        add_action('wp_ajax_nopriv_yprint_get_consent_status', array($this, 'get_consent_status'));
        
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
        if ($this->should_show_icon()) {
            include YPRINT_PLUGIN_DIR . 'templates/consent/consent-icon.php';
        }
    }
    
    /**
     * Prüfen ob Banner initial angezeigt werden soll
     */
    public function should_show_banner_initially() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            return !$this->has_any_consent_decision($user_id);
        } else {
            return !$this->has_guest_consent_decision();
        }
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
        // Icon IMMER anzeigen für Nutzer mit getroffener Entscheidung
        if (is_user_logged_in()) {
            return $this->has_any_consent_decision(get_current_user_id());
        } else {
            return $this->has_guest_consent_decision();
        }
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
        return isset($_COOKIE['yprint_consent_decision']) || isset($_COOKIE['yprint_consent_preferences']);
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
     * Prüfen ob Gast Consent gegeben hat
     */
    private function has_guest_given_consent() {
        return isset($_COOKIE['yprint_consent_preferences']);
    }
    
    /**
     * Consent-Texte abrufen
     */
    private function get_consent_texts() {
        global $wpdb;
        
        $texts = $wpdb->get_results(
            "SELECT text_key, content FROM {$wpdb->prefix}yprint_legal_texts 
             WHERE is_active = 1 AND language = 'de'",
            OBJECT_K
        );
        
        $result = array();
        foreach ($texts as $key => $text) {
            $result[$key] = $text->content;
        }
        
        return $result;
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
        
        foreach ($consent_data as $consent_type => $granted) {
            $granted = (bool) $granted;
            
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
        $cookie_data = array(
            'consents' => $consent_data,
            'timestamp' => time(),
            'version' => '1.0'
        );
        
        setcookie(
            'yprint_consent_preferences',
            json_encode($cookie_data),
            time() + (365 * 24 * 60 * 60), // 1 Jahr
            '/',
            '',
            is_ssl(),
            true
        );
        
        return true;
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
        
        if (is_user_logged_in()) {
            $consents = $this->get_user_consents(get_current_user_id());
        } else {
            $consents = $this->get_guest_consents();
        }
        
        wp_send_json_success($consents);
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
            return json_decode($_COOKIE['yprint_consent_preferences'], true);
        }
        
        return array();
    }
}

// Initialisieren
add_action('plugins_loaded', function() {
    YPrint_Consent_Manager::get_instance();
});