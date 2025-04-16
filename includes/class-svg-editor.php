<?php
/**
 * SVG-Editor-Integration für Vectorize WP
 *
 * Diese Klasse implementiert die Integration des SVG-Edit-Editors.
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}

class Vectorize_WP_SVG_Editor {
    /**
     * Konstruktor
     */
    public function __construct() {
        // Hooks registrieren
        add_action('admin_menu', array($this, 'add_editor_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_editor_scripts'));
        
        // AJAX-Handler für die SVG-Editor-Aktionen
        add_action('wp_ajax_save_edited_svg', array($this, 'ajax_save_edited_svg'));
        add_action('wp_ajax_load_svg_to_editor', array($this, 'ajax_load_svg_to_editor'));
    }
    
    /**
     * Fügt die Editor-Seite zum Admin-Menü hinzu
     */
    public function add_editor_page() {
        add_submenu_page(
            'vectorize-wp', // Parent slug
            __('SVG-Editor', 'vectorize-wp'), // Page title
            __('SVG-Editor', 'vectorize-wp'), // Menu title
            'manage_options', // Capability
            'vectorize-wp-svg-editor', // Menu slug
            array($this, 'render_editor_page') // Callback function
        );
    }
    
    /**
     * Registriert und lädt die für den SVG-Editor benötigten Skripte
     *
     * @param string $hook Hook-Name der aktuellen Admin-Seite
     */
    public function enqueue_editor_scripts($hook) {
        if ('vectorize-wp_page_vectorize-wp-svg-editor' !== $hook) {
            return;
        }
        
        // SVG-Edit Hauptskripte
        wp_enqueue_script(
            'svg-edit-main', 
            VECTORIZE_WP_URL . 'svg-edit/svg-editor.js',
            array('jquery'),
            VECTORIZE_WP_VERSION,
            true
        );
        
        // SVG-Edit CSS
        wp_enqueue_style(
            'svg-edit-main', 
            VECTORIZE_WP_URL . 'svg-edit/svg-editor.css',
            array(),
            VECTORIZE_WP_VERSION
        );
        
        // Integrationsscript
        wp_enqueue_script(
            'vectorize-wp-svg-editor', 
            VECTORIZE_WP_URL . 'assets/js/svg-editor.js',
            array('jquery', 'svg-edit-main'),
            VECTORIZE_WP_VERSION,
            true
        );
        
        // Daten für das JavaScript verfügbar machen
        wp_localize_script(
            'vectorize-wp-svg-editor',
            'vectorizeWpEditor',
            array(
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vectorize_wp_editor_nonce'),
                'svgId' => isset($_GET['svg_id']) ? intval($_GET['svg_id']) : 0,
                'svgContent' => isset($_GET['svg_content']) ? sanitize_text_field($_GET['svg_content']) : '',
                'returnUrl' => isset($_GET['return_url']) ? esc_url_raw($_GET['return_url']) : admin_url('admin.php?page=vectorize-wp'),
            )
        );
    }
    
    /**
     * Rendert die SVG-Editor-Seite
     */
    public function render_editor_page() {
        // SVG-ID aus URL abrufen, falls vorhanden
        $svg_id = isset($_GET['svg_id']) ? intval($_GET['svg_id']) : 0;
        $svg_content = isset($_GET['svg_content']) ? sanitize_text_field($_GET['svg_content']) : '';
        
        // Rückgabe-URL für den "Zurück"-Button
        $return_url = isset($_GET['return_url']) ? esc_url_raw($_GET['return_url']) : admin_url('admin.php?page=vectorize-wp');
        
        // Editor-Template einbinden
        include VECTORIZE_WP_PATH . 'templates/svg-editor-page.php';
    }
    
    /**
     * AJAX-Handler zum Speichern des bearbeiteten SVG
     */
    public function ajax_save_edited_svg() {
        // Sicherheitsüberprüfungen
        check_ajax_referer('vectorize_wp_editor_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung zum Bearbeiten von SVG-Dateien.', 'vectorize-wp'));
            return;
        }
        
        // SVG-Inhalt und ID überprüfen
        if (empty($_POST['svg_content'])) {
            wp_send_json_error(__('Kein SVG-Inhalt vorhanden.', 'vectorize-wp'));
            return;
        }
        
        $svg_content = $_POST['svg_content'];
        $svg_id = isset($_POST['svg_id']) ? intval($_POST['svg_id']) : 0;
        
        // SVG-Handler-Instanz abrufen
        $svg_handler = new Vectorize_WP_SVG_Handler();
        
        // SVG-Inhalt sanitieren
        $sanitized_svg = $svg_handler->sanitize_svg($svg_content);
        
        if (empty($sanitized_svg)) {
            wp_send_json_error(__('Ungültiger SVG-Inhalt.', 'vectorize-wp'));
            return;
        }
        
        // Wenn ein vorhandenes SVG aktualisiert wird
        if ($svg_id > 0) {
            // Bestehende Datei aktualisieren
            $attachment = get_post($svg_id);
            
            if (!$attachment || 'attachment' !== $attachment->post_type) {
                wp_send_json_error(__('Ungültige Anhang-ID.', 'vectorize-wp'));
                return;
            }
            
            // Pfad zur Datei abrufen
            $file_path = get_attached_file($svg_id);
            
            if (!$file_path || !file_exists($file_path)) {
                wp_send_json_error(__('Datei nicht gefunden.', 'vectorize-wp'));
                return;
            }
            
            // SVG-Inhalt in die Datei schreiben
            $result = file_put_contents($file_path, $sanitized_svg);
            
            if (false === $result) {
                wp_send_json_error(__('Fehler beim Speichern der Datei.', 'vectorize-wp'));
                return;
            }
            
            // Erfolgreiche Antwort senden
            wp_send_json_success(array(
                'svg_id' => $svg_id,
                'message' => __('SVG erfolgreich aktualisiert.', 'vectorize-wp'),
                'file_url' => wp_get_attachment_url($svg_id)
            ));
        } else {
            // Neues SVG speichern
            $result = $svg_handler->save_to_media_library($sanitized_svg, 'edited-svg.svg');
            
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
                return;
            }
            
            // Erfolgreiche Antwort senden
            wp_send_json_success(array(
                'svg_id' => $result,
                'message' => __('SVG erfolgreich gespeichert.', 'vectorize-wp'),
                'file_url' => wp_get_attachment_url($result)
            ));
        }
    }
    
    /**
     * AJAX-Handler zum Laden eines SVG in den Editor
     */
    public function ajax_load_svg_to_editor() {
        // Sicherheitsüberprüfungen
        check_ajax_referer('vectorize_wp_editor_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Keine Berechtigung zum Bearbeiten von SVG-Dateien.', 'vectorize-wp'));
            return;
        }
        
        // SVG-ID überprüfen
        if (empty($_POST['svg_id'])) {
            wp_send_json_error(__('Keine SVG-ID angegeben.', 'vectorize-wp'));
            return;
        }
        
        $svg_id = intval($_POST['svg_id']);
        
        // Anhang überprüfen
        $attachment = get_post($svg_id);
        
        if (!$attachment || 'attachment' !== $attachment->post_type) {
            wp_send_json_error(__('Ungültige Anhang-ID.', 'vectorize-wp'));
            return;
        }
        
        // Pfad zur Datei abrufen
        $file_path = get_attached_file($svg_id);
        
        if (!$file_path || !file_exists($file_path)) {
            wp_send_json_error(__('Datei nicht gefunden.', 'vectorize-wp'));
            return;
        }
        
        // SVG-Inhalt lesen
        $svg_content = file_get_contents($file_path);
        
        if (false === $svg_content) {
            wp_send_json_error(__('Fehler beim Lesen der Datei.', 'vectorize-wp'));
            return;
        }
        
        // Erfolgreiche Antwort senden
        wp_send_json_success(array(
            'svg_id' => $svg_id,
            'svg_content' => $svg_content,
            'file_url' => wp_get_attachment_url($svg_id)
        ));
    }
}