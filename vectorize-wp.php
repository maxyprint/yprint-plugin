<?php
/**
 * Plugin Name: Vectorize WP
 * Plugin URI: https://yprint.de/plugins/vectorize-wp
 * Description: Ein WordPress-Plugin zur Vektorisierung von Bildern und SVG-Bearbeitung mit vectorize.ai API und SVG-Edit.
 * Version: 1.0.0
 * Author: YPrint
 * Author URI: https://yprint.de
 * Text Domain: vectorize-wp
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.2
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Plugin-Konstanten definieren
define('VECTORIZE_WP_VERSION', '1.0.0');
define('VECTORIZE_WP_PATH', plugin_dir_path(__FILE__));
define('VECTORIZE_WP_URL', plugin_dir_url(__FILE__));
define('VECTORIZE_WP_BASENAME', plugin_basename(__FILE__));

// Erforderliche Dateien einbinden
require_once VECTORIZE_WP_PATH . 'includes/class-api.php';
require_once VECTORIZE_WP_PATH . 'includes/class-vectorize-api.php';
require_once VECTORIZE_WP_PATH . 'includes/class-svg-handler.php';
require_once VECTORIZE_WP_PATH . 'includes/class-svg-editor.php';
require_once VECTORIZE_WP_PATH . 'includes/designtool-integration.php';

// Hauptklasse des Plugins
class Vectorize_WP {
    // Singleton-Instanz
    private static $instance = null;
    
    // API-Instanz
    public $api = null;
    
    // SVG-Handler-Instanz
    public $svg_handler = null;

    // Konstruktor
    private function __construct() {
        // Hooks und Filter beim Plugin-Start registrieren
        $this->init_hooks();
        
        // Klassen initialisieren
        $this->init_classes();
    }

    // Singleton-Instanz abrufen
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Klassen initialisieren
    private function init_classes() {
        // API-Instanz erstellen
        $options = get_option('vectorize_wp_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        $this->api = new Vectorize_WP_Vectorize_API($api_key);
        
        // SVG-Handler erstellen
        $this->svg_handler = new Vectorize_WP_SVG_Handler();
        
        // SVG-Editor erstellen
        new Vectorize_WP_SVG_Editor();
        
        // Aufräumen-Aktion für temporäre Dateien registrieren
        add_action('vectorize_wp_cleanup_temp_files', array($this->svg_handler, 'cleanup_temp_files'));
        
        // Design-Tool Assets registrieren
        $this->register_designtool_assets();
    }

    // Initialisierung der Hooks
    private function init_hooks() {
        // Plugin aktivieren/deaktivieren Hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Admin-Menü und Assets laden
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX-Aktionen
        add_action('wp_ajax_vectorize_image', array($this, 'ajax_vectorize_image'));
        add_action('wp_ajax_save_svg_to_media', array($this, 'ajax_save_svg_to_media'));
        add_action('wp_ajax_download_svg', array($this, 'ajax_download_svg'));
        add_shortcode('vectorize_wp_canvas', array($this, 'render_canvas_shortcode'));

        add_action('wp_ajax_nopriv_vectorize_image', array($this, 'ajax_vectorize_image'));
        add_action('wp_ajax_nopriv_save_svg_to_media', array($this, 'ajax_save_svg_to_media'));
        add_action('wp_ajax_nopriv_download_svg', array($this, 'ajax_download_svg'));
        
        // Design-Tool AJAX-Aktionen
        add_action('wp_ajax_vectorize_designtool_vectorize', array($this, 'ajax_designtool_vectorize'));
        add_action('wp_ajax_nopriv_vectorize_designtool_vectorize', array($this, 'ajax_designtool_vectorize'));
        add_action('wp_ajax_vectorize_designtool_save_svg', array($this, 'ajax_designtool_save_svg'));
        add_action('wp_ajax_nopriv_vectorize_designtool_save_svg', array($this, 'ajax_designtool_save_svg'));
        add_shortcode('vectorize_designtool', array($this, 'render_designtool_shortcode'));
    }

    // Plugin-Aktivierungsfunktion
    public function activate() {
        // Verzeichnisse für temporäre Dateien erstellen
        $upload_dir = wp_upload_dir();
        $vectorize_dir = $upload_dir['basedir'] . '/vectorize-wp';
        $temp_dir = $vectorize_dir . '/temp';
        
        if (!file_exists($vectorize_dir)) {
            wp_mkdir_p($vectorize_dir);
        }
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        // Initialisierungsoptionen in der Datenbank speichern
        $default_options = array(
            'api_key' => '',
            'max_upload_size' => 5, // in MB
            'default_output_format' => 'svg',
            'test_mode' => 'test', // Standard: Testmodus aktiviert für einfache Entwicklung
        );
        
        // Bestehende Optionen abrufen, falls vorhanden
        $existing_options = get_option('vectorize_wp_options', array());
        
        // Bestehende Optionen mit den Standardwerten zusammenführen
        $merged_options = wp_parse_args($existing_options, $default_options);
        
        // Optionen aktualisieren oder hinzufügen
        update_option('vectorize_wp_options', $merged_options);
    }

    // Plugin-Deaktivierungsfunktion
    public function deactivate() {
        // Temporäre Dateien aufräumen, aber gespeicherte Vektoren behalten
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/vectorize-wp/temp';
        
        if (file_exists($temp_dir)) {
            $files = glob($temp_dir . '/*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                }
            }
        }
    }

    // Admin-Menü hinzufügen
    public function add_admin_menu() {
        add_menu_page(
            __('Vectorize WP', 'vectorize-wp'),
            __('Vectorize WP', 'vectorize-wp'),
            'manage_options',
            'vectorize-wp',
            array($this, 'render_admin_page'),
            'dashicons-image-filter',
            30
        );
        
        // Untermenü für Einstellungen
        add_submenu_page(
            'vectorize-wp',
            __('Einstellungen', 'vectorize-wp'),
            __('Einstellungen', 'vectorize-wp'),
            'manage_options',
            'vectorize-wp-settings',
            array($this, 'render_settings_page')
        );
        
        // Untermenü für Design-Tool
        add_submenu_page(
            'vectorize-wp',
            __('Design Tool', 'vectorize-wp'),
            __('Design Tool', 'vectorize-wp'),
            'manage_options',
            'vectorize-wp-designtool',
            'vectorize_wp_render_designtool_page'
        );
    }

    // Admin-Skripte und Styles laden
    public function enqueue_admin_scripts($hook) {
        $admin_pages = array(
            'toplevel_page_vectorize-wp',
            'vectorize-wp_page_vectorize-wp-settings',
            'vectorize-wp_page_vectorize-wp-designtool'
        );
        
        if (!in_array($hook, $admin_pages)) {
            return;
        }
        
        // CSS laden
        wp_enqueue_style(
            'vectorize-wp-admin',
            VECTORIZE_WP_URL . 'assets/css/admin.css',
            array(),
            VECTORIZE_WP_VERSION
        );
        
        // JavaScript laden
        wp_enqueue_script(
            'vectorize-wp-admin',
            VECTORIZE_WP_URL . 'assets/js/admin.js',
            array('jquery'),
            VECTORIZE_WP_VERSION,
            true
        );
        
        // Admin-AJAX-URL für JavaScript verfügbar machen
        wp_localize_script(
            'vectorize-wp-admin',
            'vectorizeWP',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vectorize_wp_nonce'),
                'maxUploadSize' => $this->get_max_upload_size(),
                'adminUrl' => admin_url(),
            )
        );
    }

    // Hauptadminseite rendern
    public function render_admin_page() {
        // API-Schlüssel überprüfen
        $options = get_option('vectorize_wp_options', array());
        $api_key = isset($options['api_key']) ? $options['api_key'] : '';
        
        if (empty($api_key)) {
            echo '<div class="notice notice-warning is-dismissible"><p>';
            echo sprintf(
                __('Bitte konfiguriere deinen API-Schlüssel in den <a href="%s">Einstellungen</a>.', 'vectorize-wp'),
                admin_url('admin.php?page=vectorize-wp-settings')
            );
            echo '</p></div>';
        }
        
        include VECTORIZE_WP_PATH . 'templates/admin-page.php';
    }

    // Einstellungsseite rendern
    public function render_settings_page() {
        include VECTORIZE_WP_PATH . 'templates/settings-page.php';
    }

    // AJAX-Handler für die Bildvektorisierung
    public function ajax_vectorize_image() {
    // Debugging-Ausgabe
    error_log('AJAX vectorize_image called');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));
    
    // Nonce-Überprüfung mit Debugging
    $nonce_check = check_ajax_referer('vectorize_wp_nonce', 'nonce', false);
    error_log('Nonce check result: ' . ($nonce_check ? 'passed' : 'failed'));
    
    if (!$nonce_check) {
        wp_send_json_error('Nonce validation failed. Security check failed.');
        return;
    }

    // Debugging-Ausgabe
    error_log('AJAX vectorize_image called');
    error_log('POST data: ' . print_r($_POST, true));
    error_log('FILES data: ' . print_r($_FILES, true));
    
    // Nonce-Überprüfung mit Debugging
    $nonce_check = check_ajax_referer('vectorize_wp_nonce', 'nonce', false);
    error_log('Nonce check result: ' . ($nonce_check ? 'passed' : 'failed'));
    
    if (!$nonce_check) {
        wp_send_json_error('Nonce validation failed. Security check failed.');
        return;
    }
    
    if (!current_user_can('upload_files')) {
        wp_send_json_error(__('Keine Berechtigung zum Hochladen von Dateien.', 'vectorize-wp'));
        return;
    }
    
    // Überprüfen, ob eine Datei hochgeladen wurde
    if (empty($_FILES['vectorize_image'])) {
        wp_send_json_error(__('Keine Datei hochgeladen.', 'vectorize-wp'));
        return;
    }
        
        $file = $_FILES['vectorize_image'];
        
        // Überprüfen, ob die Datei gültig ist
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Fehler beim Hochladen der Datei: ' . $file['error'], 'vectorize-wp'));
            return;
        }
        
        // Überprüfen, ob der Dateityp akzeptiert wird
        $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp');
        if (!in_array($file['type'], $allowed_types)) {
            wp_send_json_error(__('Ungültiger Dateityp. Bitte lade ein JPEG-, PNG-, GIF-, BMP- oder WebP-Bild hoch.', 'vectorize-wp'));
            return;
        }
        
        // Datei temporär speichern
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/vectorize-wp/temp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
        }
        
        $temp_file = $temp_dir . '/' . sanitize_file_name($file['name']);
        move_uploaded_file($file['tmp_name'], $temp_file);
        
        // Vektorisierungsoptionen
        $options = array(
            'detail' => isset($_POST['detail_level']) ? sanitize_text_field($_POST['detail_level']) : 'medium',
            'format' => 'svg'
        );
        
        // API-Schlüssel überprüfen
        $api_options = get_option('vectorize_wp_options', array());
        $api_key = isset($api_options['api_key']) ? $api_options['api_key'] : '';
        
        if (empty($api_key)) {
            // Kein API-Schlüssel - Demo-SVG zurückgeben
            @unlink($temp_file); // Temporäre Datei löschen
            
            // Demo-SVG erstellen (basierend auf dem Dateinamen)
            $filename = pathinfo($file['name'], PATHINFO_FILENAME);
            $demo_svg = $this->create_demo_svg($filename);
            
            // Erfolgreiche Antwort senden
            wp_send_json_success(array(
                'svg' => $demo_svg,
                'file_url' => '',
                'is_demo' => true,
                'message' => __('Demo-Modus: Bitte konfiguriere einen API-Schlüssel für echte Vektorisierung.', 'vectorize-wp')
            ));
            return;
        }
        
        // Echte API-Anfrage senden
        try {
            // Bild vektorisieren
            $result = $this->api->vectorize_image($temp_file, $options);
            
            // Temporäre Datei löschen
            @unlink($temp_file);
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }
            
            // Erfolgreiche Antwort senden
            wp_send_json_success(array(
                'svg' => $result['content'],
                'file_url' => $result['file_url'],
                'is_demo' => false,
                'is_test_mode' => isset($result['is_test_mode']) ? $result['is_test_mode'] : false,
                'test_mode' => isset($result['test_mode']) ? $result['test_mode'] : 'off'
            ));
        } catch (Exception $e) {
            // Fehlerbehandlung
            @unlink($temp_file); // Temporäre Datei löschen
            wp_send_json_error(__('API-Fehler: ' . $e->getMessage(), 'vectorize-wp'));
        }
    }
    
    // AJAX-Handler zum Speichern des SVG in der Mediathek
    public function ajax_save_svg_to_media() {
        // Sicherheitsüberprüfungen
        check_ajax_referer('vectorize_wp_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Keine Berechtigung zum Hochladen von Dateien.', 'vectorize-wp'));
            return;
        }
        
        // SVG-Inhalt überprüfen
        if (empty($_POST['svg_content'])) {
            wp_send_json_error(__('Kein SVG-Inhalt vorhanden.', 'vectorize-wp'));
            return;
        }
        
        $svg_content = $_POST['svg_content'];
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : 'vectorized-image.svg';
        
        // Zusätzliche Metadaten
        $attachment_data = array(
            'post_title' => isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '',
            'post_excerpt' => isset($_POST['caption']) ? sanitize_text_field($_POST['caption']) : '',
            'post_content' => isset($_POST['description']) ? wp_kses_post($_POST['description']) : '',
        );
        
        // SVG in Mediathek speichern
        $attachment_id = $this->svg_handler->save_to_media_library($svg_content, $filename, $attachment_data);
        
        if (is_wp_error($attachment_id)) {
            wp_send_json_error($attachment_id->get_error_message());
            return;
        }
        
        // Erfolgreiche Antwort senden
        wp_send_json_success(array(
            'attachment_id' => $attachment_id,
            'attachment_url' => wp_get_attachment_url($attachment_id)
        ));
    }
    
    // AJAX-Handler zum Herunterladen des SVG
    public function ajax_download_svg() {
        // Sicherheitsüberprüfungen
        check_ajax_referer('vectorize_wp_nonce', 'nonce');
        
        // SVG-Inhalt überprüfen
        if (empty($_POST['svg_content'])) {
            wp_send_json_error(__('Kein SVG-Inhalt vorhanden.', 'vectorize-wp'));
            return;
        }
        
        $svg_content = $_POST['svg_content'];
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : 'vectorized-image.svg';
        
        // Download-Link erstellen
        $download_url = $this->svg_handler->create_download_link($svg_content, $filename);
        
        if (empty($download_url)) {
            wp_send_json_error(__('Fehler beim Erstellen des Download-Links.', 'vectorize-wp'));
            return;
        }
        
        // Erfolgreiche Antwort senden
        wp_send_json_success(array(
            'download_url' => $download_url
        ));
    }
    
    /**
     * Rendert die Canvas-Seite für den Shortcode [vectorize_wp_canvas]
     *
     * @param array $atts Shortcode-Attribute
     * @return string HTML-Ausgabe
     */
    public function render_canvas_shortcode($atts) {
        // Standardwerte für Attribute
        $atts = shortcode_atts(array(
            'width' => '100%',
            'height' => '600px',
            'allow_upload' => 'true',
        ), $atts);
        
        // Initialisiere Output-Buffer
        ob_start();
        
        // Frontend-Assets registrieren und laden
        wp_enqueue_style(
            'vectorize-wp-frontend',
            VECTORIZE_WP_URL . 'assets/css/frontend.css',
            array(),
            VECTORIZE_WP_VERSION
        );
        
        wp_enqueue_script(
            'vectorize-wp-frontend',
            VECTORIZE_WP_URL . 'assets/js/frontend.js',
            array('jquery'),
            VECTORIZE_WP_VERSION,
            true
        );
        
        // AJAX-URL und Nonce für JavaScript verfügbar machen
        wp_localize_script(
            'vectorize-wp-frontend',
            'vectorizeWpFrontend',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vectorize_wp_frontend_nonce'),
                'maxUploadSize' => $this->get_max_upload_size(),
            )
        );
        
        // Template für den Canvas-Bereich einbinden
        include VECTORIZE_WP_PATH . 'templates/frontend-canvas.php';
        
        // Buffer-Inhalt zurückgeben
        return ob_get_clean();
    }

    // Hilfsfunktion: Maximale Upload-Größe abrufen (in Bytes)
    private function get_max_upload_size() {
        $options = get_option('vectorize_wp_options', array());
        $max_size_mb = isset($options['max_upload_size']) ? (int) $options['max_upload_size'] : 5;
        
        // Sicherstellen, dass die Größe zwischen 1 und 20 MB liegt
        $max_size_mb = max(1, min(20, $max_size_mb));
        
        // In Bytes umrechnen
        return $max_size_mb * 1024 * 1024;
    }

    // Hilfsfunktion zum Erstellen eines Demo-SVGs
    private function create_demo_svg($name = '') {
        // Ein einfaches Demo-SVG basierend auf dem Dateinamen erstellen
        $text = !empty($name) ? $name : 'Demo';
        
        $colors = array('#FF5733', '#33FF57', '#3357FF', '#F3FF33', '#FF33F3');
        $color = $colors[array_rand($colors)];
        
        $svg = '<?xml version="1.0" encoding="UTF-8" standalone="no"?>
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="200" height="200">
            <rect x="10" y="10" width="180" height="180" rx="20" ry="20" fill="' . $color . '" />
            <text x="100" y="110" font-family="Arial" font-size="20" fill="white" text-anchor="middle">' . $text . '</text>
            <text x="100" y="140" font-family="Arial" font-size="12" fill="white" text-anchor="middle">Demo Mode</text>
        </svg>';
        
        return $svg;
    }
    
    // AJAX-Handler für die Design-Tool Vektorisierung
    public function ajax_designtool_vectorize() {
        // In designtool-integration.php ausgelagert
        vectorize_wp_designtool_vectorize_ajax();
    }

    // AJAX-Handler für das Speichern von SVG-Dateien im Design-Tool
    public function ajax_designtool_save_svg() {
        // In designtool-integration.php ausgelagert
        vectorize_wp_designtool_save_svg_ajax();
    }

    // Rendert den Design-Tool Shortcode
    public function render_designtool_shortcode($atts) {
        // In designtool-integration.php ausgelagert
        return vectorize_wp_designtool_shortcode($atts);
    }

    // Registriert die benötigten Assets für das Design-Tool
    public function register_designtool_assets() {
        // CSS für das Design-Tool registrieren
        wp_register_style(
            'vectorize-wp-designtool',
            VECTORIZE_WP_URL . 'assets/css/designtool.css',
            array(),
            VECTORIZE_WP_VERSION
        );
        
        // JavaScript für das Design-Tool registrieren
        wp_register_script(
            'vectorize-wp-designtool',
            VECTORIZE_WP_URL . 'assets/js/designtool.js',
            array('jquery'),
            VECTORIZE_WP_VERSION,
            true
        );
    }
}

// Plugin-Instanz initialisieren
function vectorize_wp() {
    return Vectorize_WP::get_instance();
}

// Plugin starten
vectorize_wp();

function vectorize_wp_add_canvas_endpoint() {
    add_rewrite_rule(
        'canvas/?$',
        'index.php?vectorize_canvas=1',
        'top'
    );
    
    add_rewrite_tag('%vectorize_canvas%', '([^&]+)');
}
add_action('init', 'vectorize_wp_add_canvas_endpoint');

// Flush Rewrite Rules bei Plugin-Aktivierung
function vectorize_wp_activate() {
    vectorize_wp_add_canvas_endpoint();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'vectorize_wp_activate');

// Template für den Canvas-Endpunkt
function vectorize_wp_template_include($template) {
    if (get_query_var('vectorize_canvas')) {
        return VECTORIZE_WP_PATH . 'templates/canvas-template.php';
    }
    return $template;
}
add_filter('template_include', 'vectorize_wp_template_include');

/**
 * Registriere das Canvas-Template für Seiten
 */
function vectorize_wp_add_canvas_template() {
    // Erstelle ein Array mit den Template-Daten
    $templates = array(
        'canvas-template.php' => 'Canvas Tool',
    );
    
    // Füge das Template der Liste der Seitenvorlagen hinzu
    add_filter('theme_page_templates', function($page_templates) use ($templates) {
        return array_merge($page_templates, $templates);
    });

    // Einbinden des Templates, wenn es ausgewählt wurde
    add_filter('template_include', function($template) use ($templates) {
        global $post;
        
        if (!$post) {
            return $template;
        }
        
        // Hole das für die aktuelle Seite ausgewählte Template
        $selected_template = get_post_meta($post->ID, '_wp_page_template', true);
        
        // Wenn unser Template ausgewählt wurde
        if (isset($templates[$selected_template])) {
            $file = VECTORIZE_WP_PATH . 'templates/' . $selected_template;
            
            // Prüfe, ob die Datei existiert
            if (file_exists($file)) {
                return $file;
            }
        }
        
        return $template;
    });
}
add_action('init', 'vectorize_wp_add_canvas_template');
