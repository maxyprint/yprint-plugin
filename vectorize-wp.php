<?php
/**
 * Vectorize WP - Bildvektorisierungs-Plugin für WordPress
 *
 * @package     VectorizeWP
 * @author      YPrint
 * @copyright   2025, YPrint
 * @license     GPL-2.0+
 *
 * @wordpress-plugin
 * Plugin Name: Vectorize WP
 * Plugin URI:  https://yprint.de/vectorize-wp
 * Description: Konvertiere Bilder in SVG-Vektorgrafiken direkt in WordPress. Verwandele JPG, PNG und andere Bildformate in skalierbare Vektorgrafiken.
 * Version:     1.0.0
 * Author:      YPrint
 * Author URI:  https://yprint.de
 * Text Domain: vectorize-wp
 * Domain Path: /languages
 * License:     GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

// Direktzugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten definieren
define('VECTORIZE_WP_VERSION', '1.0.0');
define('VECTORIZE_WP_PATH', plugin_dir_path(__FILE__));
define('VECTORIZE_WP_URL', plugin_dir_url(__FILE__));
define('VECTORIZE_WP_BASENAME', plugin_basename(__FILE__));

/**
 * Hauptklasse für das Plugin
 */
class Vectorize_WP {
    /**
     * Plugin-Instanz
     *
     * @var Vectorize_WP
     */
    private static $instance = null;

    /**
     * SVG-Handler-Instanz
     *
     * @var Vectorize_WP_SVG_Handler
     */
    public $svg_handler;

    /**
     * SVG-Editor-Instanz
     *
     * @var Vectorize_WP_SVG_Editor
     */
    public $svg_editor;

    /**
     * YPrint Vectorizer Instanz
     *
     * @var YPrint_Vectorizer
     */
    public $yprint_vectorizer;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Initialisiert die Hooks.
     */
    private function init_hooks() {
        // Internationalisierung
        add_action('plugins_loaded', array($this, 'load_textdomain'));
        
        // Admin-Menü
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Admin-Assets
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Ajax-Handler registrieren
        add_action('wp_ajax_vectorize_image', array($this, 'ajax_vectorize_image'));
        add_action('wp_ajax_save_svg_to_media', array($this, 'ajax_save_svg_to_media'));
        add_action('wp_ajax_download_svg', array($this, 'ajax_download_svg'));
        
        // Frontend-Assets
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
    }

    /**
     * Lädt erforderliche Dateien
     */
    private function includes() {
        // SVG-Handler-Klasse
        require_once VECTORIZE_WP_PATH . 'includes/class-svg-handler.php';
        $this->svg_handler = new Vectorize_WP_SVG_Handler();
        
        // SVG-Editor-Klasse
        require_once VECTORIZE_WP_PATH . 'includes/class-svg-editor.php';
        $this->svg_editor = new Vectorize_WP_SVG_Editor();
        
        // Design Tool Integration
        require_once VECTORIZE_WP_PATH . 'includes/designtool-integration.php';
        
        // YPrint Vectorizer einbinden
if (file_exists(VECTORIZE_WP_PATH . 'yprint_vectorizer.php')) {
    require_once VECTORIZE_WP_PATH . 'yprint_vectorizer.php';
    
    // Sicherstellen, dass die Klasse existiert, bevor wir sie initialisieren
    if (class_exists('YPrint_Vectorizer')) {
        try {
            $this->yprint_vectorizer = YPrint_Vectorizer::get_instance();
        } catch (Exception $e) {
            // Fehlermeldung im Admin-Bereich anzeigen
            add_action('admin_notices', function() use ($e) {
                echo '<div class="notice notice-error"><p>' . 
                     __('YPrint Vectorizer konnte nicht initialisiert werden: ', 'vectorize-wp') . esc_html($e->getMessage()) .
                     '</p></div>';
            });
        }
    } else {
        // Fehlermeldung im Admin-Bereich anzeigen
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>' . 
                 __('YPrint Vectorizer konnte nicht geladen werden. Vectorize WP funktioniert möglicherweise nicht korrekt.', 'vectorize-wp') .
                 '</p></div>';
        });
    }
} else {
    // Fehlermeldung im Admin-Bereich anzeigen
    add_action('admin_notices', function() {
        echo '<div class="notice notice-error"><p>' . 
             __('YPrint Vectorizer wurde nicht gefunden. Vectorize WP funktioniert möglicherweise nicht korrekt.', 'vectorize-wp') .
             '</p></div>';
    });
}
    }

    /**
     * Fügt Admin-Menüpunkte hinzu
     */
    public function add_admin_menu() {
        // Hauptmenüpunkt
        add_menu_page(
            __('Vectorize WP', 'vectorize-wp'),
            __('Vectorize WP', 'vectorize-wp'),
            'manage_options',
            'vectorize-wp',
            array($this, 'render_admin_page'),
            'dashicons-format-image',
            30
        );
        
        // Untermenü für Vektorisierung
        add_submenu_page(
            'vectorize-wp',
            __('Vektorisieren', 'vectorize-wp'),
            __('Vektorisieren', 'vectorize-wp'),
            'manage_options',
            'vectorize-wp', // Gleiche Slug wie Hauptmenü
            array($this, 'render_admin_page')
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
        
        // Untermenü für Design Tool
        add_submenu_page(
            'vectorize-wp',
            __('Design Tool', 'vectorize-wp'),
            __('Design Tool', 'vectorize-wp'),
            'manage_options',
            'vectorize-wp-designtool',
            'vectorize_wp_render_designtool_page'
        );
    }

    /**
     * Rendert die Hauptseite
     */
    public function render_admin_page() {
        include VECTORIZE_WP_PATH . 'templates/admin-page.php';
    }

    /**
     * Rendert die Einstellungsseite
     */
    public function render_settings_page() {
        include VECTORIZE_WP_PATH . 'templates/settings-page.php';
    }

    /**
     * Lädt Übersetzungsdateien
     */
    public function load_textdomain() {
        load_plugin_textdomain('vectorize-wp', false, dirname(VECTORIZE_WP_BASENAME) . '/languages');
    }

    /**
     * Lädt Admin-Assets
     */
    public function enqueue_admin_assets($hook) {
        // Nur auf Plugin-Seiten laden
        if (strpos($hook, 'vectorize-wp') === false) {
            return;
        }
        
        // CSS laden
        wp_enqueue_style(
            'vectorize-wp-admin',
            VECTORIZE_WP_URL . 'assets/css/admin.css',
            array(),
            VECTORIZE_WP_VERSION
        );
        
        // Spezifisches CSS für den SVG-Editor laden
        if ($hook === 'vectorize-wp_page_vectorize-wp-svg-editor') {
            wp_enqueue_style(
                'vectorize-wp-svg-editor',
                VECTORIZE_WP_URL . 'assets/css/svg-editor.css',
                array(),
                VECTORIZE_WP_VERSION
            );
        }
        
        // JavaScript laden
        wp_enqueue_script(
            'vectorize-wp-admin',
            VECTORIZE_WP_URL . 'assets/js/admin.js',
            array('jquery'),
            VECTORIZE_WP_VERSION,
            true
        );
        
        // WordPress Mediathek aktivieren
        wp_enqueue_media();
        
        // Daten für JavaScript verfügbar machen
        wp_localize_script(
            'vectorize-wp-admin',
            'vectorizeWP',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vectorize_wp_nonce'),
                'adminUrl' => admin_url(),
                'maxUploadSize' => $this->get_max_upload_size(),
                'version' => VECTORIZE_WP_VERSION
            )
        );
    }

    /**
     * Lädt Frontend-Assets
     */
    public function enqueue_frontend_assets() {
        // Nur laden, wenn das Shortcode verwendet wird oder auf einer Seite mit dem Template
        global $post;
        $load_assets = false;
        
        if (is_a($post, 'WP_Post')) {
            $template = get_post_meta($post->ID, '_wp_page_template', true);
            $content = $post->post_content;
            
            // Prüfen, ob das Template oder Shortcode verwendet wird
            if (strpos($template, 'designtool-template.php') !== false ||
                strpos($template, 'canvas-template.php') !== false ||
                has_shortcode($content, 'vectorize_designtool')) {
                $load_assets = true;
            }
        }
        
        if ($load_assets) {
            // CSS laden
            wp_enqueue_style(
                'vectorize-wp-frontend',
                VECTORIZE_WP_URL . 'assets/css/designtool.css',
                array(),
                VECTORIZE_WP_VERSION
            );
            
            // JavaScript laden
            wp_enqueue_script(
                'vectorize-wp-frontend',
                VECTORIZE_WP_URL . 'assets/js/designtool.js',
                array('jquery'),
                VECTORIZE_WP_VERSION,
                true
            );
            
            // Daten für JavaScript verfügbar machen
            wp_localize_script(
                'vectorize-wp-frontend',
                'vectorizeWpFrontend',
                array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('vectorize_wp_frontend_nonce'),
                    'maxUploadSize' => $this->get_max_upload_size(),
                    'siteUrl' => site_url(),
                    'pluginUrl' => VECTORIZE_WP_URL
                )
            );
        }
    }

    /**
     * AJAX-Handler für die Bildvektorisierung
     */
    public function ajax_vectorize_image() {
        // Sicherheitsüberprüfung
        check_ajax_referer('vectorize_wp_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Keine Berechtigung zum Hochladen von Dateien.', 'vectorize-wp'));
            return;
        }
        
        // Prüfen, ob eine Datei hochgeladen wurde
        if (empty($_FILES['vectorize_image'])) {
            wp_send_json_error(__('Keine Datei hochgeladen.', 'vectorize-wp'));
            return;
        }
        
        $file = $_FILES['vectorize_image'];
        
        // Prüfen, ob beim Upload ein Fehler aufgetreten ist
        if ($file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(__('Fehler beim Hochladen der Datei.', 'vectorize-wp'));
            return;
        }
        
        // Prüfen, ob der Dateityp unterstützt wird
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
            // .htaccess erstellen, um direkten Zugriff zu verhindern
            file_put_contents($temp_dir . '/.htaccess', 'deny from all');
        }
        
        $temp_file = $temp_dir . '/' . sanitize_file_name($file['name']);
        move_uploaded_file($file['tmp_name'], $temp_file);
        
        // Vektorisierungsoptionen
        $options = array(
            'detail' => isset($_POST['detail_level']) ? sanitize_text_field($_POST['detail_level']) : 'medium',
            'format' => 'svg'
        );
        
        // Je nach konfigurierter Engine vektorisieren
        $result = false;
        
        // YPrint Vectorizer prüfen und verwenden
        if ($this->yprint_vectorizer) {
            try {
                $svg_content = $this->yprint_vectorizer->vectorize_image($temp_file, $options);
                
                if ($svg_content && !is_wp_error($svg_content)) {
                    $result = array(
                        'content' => $svg_content,
                        'file_path' => $temp_file . '.svg',
                        'file_url' => site_url('wp-content/uploads/vectorize-wp/temp/' . basename($temp_file) . '.svg'),
                        'format' => 'svg',
                        'is_test_mode' => false
                    );
                }
            } catch (Exception $e) {
                @unlink($temp_file); // Temporäre Datei löschen
                wp_send_json_error(__('Vektorisierungsfehler: ' . $e->getMessage(), 'vectorize-wp'));
                return;
            }
        }
        
        // Temporäre Datei löschen
        @unlink($temp_file);
        
        // Bei Fehler oder keinem Ergebnis, Fehlermeldung senden
        if (!$result || is_wp_error($result)) {
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            } else {
                wp_send_json_error(__('Die Vektorisierung ist fehlgeschlagen. Bitte versuche es erneut.', 'vectorize-wp'));
            }
            return;
        }
        
        // Erfolgreiche Antwort senden
        wp_send_json_success(array(
            'svg' => $result['content'],
            'file_url' => isset($result['file_url']) ? $result['file_url'] : '',
            'is_demo' => false,
            'is_test_mode' => isset($result['is_test_mode']) ? $result['is_test_mode'] : false,
            'test_mode' => get_option('vectorize_wp_options', array('test_mode' => 'off'))['test_mode']
        ));
    }

    /**
     * AJAX-Handler zum Speichern eines SVG in der Mediathek
     */
    public function ajax_save_svg_to_media() {
        // Sicherheitsüberprüfung
        check_ajax_referer('vectorize_wp_nonce', 'nonce');
        
        if (!current_user_can('upload_files')) {
            wp_send_json_error(__('Keine Berechtigung zum Hochladen von Dateien.', 'vectorize-wp'));
            return;
        }
        
        // SVG-Inhalt prüfen
        if (empty($_POST['svg_content'])) {
            wp_send_json_error(__('Kein SVG-Inhalt vorhanden.', 'vectorize-wp'));
            return;
        }
        
        $svg_content = $_POST['svg_content'];
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : 'vectorized-image.svg';
        $title = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
        
        // Attachment-Daten vorbereiten
        $attachment_data = array();
        if (!empty($title)) {
            $attachment_data['post_title'] = $title;
        }
        
        // SVG in Mediathek speichern mit dem SVG-Handler
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

    /**
     * AJAX-Handler zum Herunterladen eines SVG
     */
    public function ajax_download_svg() {
        // Sicherheitsüberprüfung
        check_ajax_referer('vectorize_wp_nonce', 'nonce');
        
        // SVG-Inhalt prüfen
        if (empty($_POST['svg_content'])) {
            wp_send_json_error(__('Kein SVG-Inhalt vorhanden.', 'vectorize-wp'));
            return;
        }
        
        $svg_content = $_POST['svg_content'];
        $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : 'vectorized-image.svg';
        
        // Download-Link erstellen mit dem SVG-Handler
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
     * Gibt die maximale Upload-Größe zurück
     */
    public function get_max_upload_size() {
        $options = get_option('vectorize_wp_options', array('max_upload_size' => 5));
        $max_size_mb = isset($options['max_upload_size']) ? (int) $options['max_upload_size'] : 5;
        
        // Sicherstellen, dass die Größe zwischen 1 und 20 MB liegt
        $max_size_mb = max(1, min(20, $max_size_mb));
        
        // In Bytes umrechnen
        return $max_size_mb * 1024 * 1024;
    }

    /**
     * Singleton-Instanz abrufen
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}

/**
 * Globale Funktion zum Abrufen der Plugin-Instanz
 */
function vectorize_wp() {
    return Vectorize_WP::get_instance();
}

// Plugin initialisieren
vectorize_wp();

/**
 * Plugin-Aktivierungshook
 */
register_activation_hook(__FILE__, 'vectorize_wp_activate');
function vectorize_wp_activate() {
    // Temporäres Verzeichnis erstellen
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/vectorize-wp/temp';
    
    if (!file_exists($temp_dir)) {
        wp_mkdir_p($temp_dir);
        // .htaccess erstellen, um direkten Zugriff zu verhindern
        file_put_contents($temp_dir . '/.htaccess', 'deny from all');
    }
    
    // Standard-Einstellungen
    $default_options = array(
        'api_key' => '',
        'max_upload_size' => 5,
        'default_output_format' => 'svg',
        'test_mode' => 'off',
        'vectorization_engine' => 'yprint'
    );
    
    // Nur hinzufügen, wenn noch keine Einstellungen vorhanden sind
    if (!get_option('vectorize_wp_options')) {
        add_option('vectorize_wp_options', $default_options);
    }
    
    // Flush Rewrite Rules für benutzerdefinierte Seiten-Templates
    flush_rewrite_rules();
}

/**
 * Plugin-Deaktivierungshook
 */
register_deactivation_hook(__FILE__, 'vectorize_wp_deactivate');
function vectorize_wp_deactivate() {
    // Temporäre Dateien löschen
    $upload_dir = wp_upload_dir();
    $temp_dir = $upload_dir['basedir'] . '/vectorize-wp/temp';
    
    if (file_exists($temp_dir)) {
        // Inhalte des Verzeichnisses leeren
        $files = glob($temp_dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }
    
    // Flush Rewrite Rules
    flush_rewrite_rules();
}

/**
 * Cleanup temporärer Dateien (für Cron)
 */
add_action('vectorize_wp_cleanup_temp_files', 'vectorize_wp_do_cleanup_temp_files');
function vectorize_wp_do_cleanup_temp_files() {
    // SVG-Handler holen und Cleanup-Methode aufrufen
    $svg_handler = vectorize_wp()->svg_handler;
    if (method_exists($svg_handler, 'cleanup_temp_files')) {
        $svg_handler->cleanup_temp_files();
    }
}

// Täglichen Cron-Job für Cleanup registrieren
if (!wp_next_scheduled('vectorize_wp_cleanup_temp_files')) {
    wp_schedule_event(time(), 'daily', 'vectorize_wp_cleanup_temp_files');
}