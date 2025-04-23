<?php
/**
 * Integration der Design-Tool-Funktionalität in das Vectorize WP Plugin
 * Diese Datei ist in der plugin.php einzubinden
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registriert das Design-Tool-Template
 */
function vectorize_wp_register_designtool_template() {
    // Template-Datei kopieren
    $template_source = VECTORIZE_WP_PATH . 'templates/designtool-template.php';
    $template_dest = get_template_directory() . '/designtool-template.php';
    
    // Nur kopieren, wenn die Datei noch nicht existiert oder älter ist
    if (!file_exists($template_dest) || filemtime($template_source) > filemtime($template_dest)) {
        copy($template_source, $template_dest);
    }
    
    // Template für Seiten registrieren
    add_filter('theme_page_templates', 'vectorize_wp_add_designtool_template');
    
    // Template einbinden, wenn es ausgewählt wurde
    add_filter('template_include', 'vectorize_wp_load_designtool_template');
}
add_action('init', 'vectorize_wp_register_designtool_template');

/**
 * Fügt das Design-Tool-Template zur Liste der verfügbaren Templates hinzu
 */
function vectorize_wp_add_designtool_template($templates) {
    $templates['designtool-template.php'] = __('Design Tool', 'vectorize-wp');
    return $templates;
}

/**
 * Lädt das Design-Tool-Template, wenn es für eine Seite ausgewählt wurde
 */
function vectorize_wp_load_designtool_template($template) {
    global $post;
    
    if (!$post) {
        return $template;
    }
    
    // Ausgewähltes Template abrufen
    $selected_template = get_post_meta($post->ID, '_wp_page_template', true);
    
    // Wenn unser Template ausgewählt wurde
    if ($selected_template === 'designtool-template.php') {
        $template_path = VECTORIZE_WP_PATH . 'templates/designtool-template.php';
        
        if (file_exists($template_path)) {
            return $template_path;
        }
    }
    
    return $template;
}

/**
 * Registriert den Shortcode für das Design-Tool
 */
function vectorize_wp_designtool_shortcode($atts) {
    // Standardwerte für Attribute
    $atts = shortcode_atts(array(
        'width' => '100%',
        'height' => '600px',
        'mode' => 'embedded', // 'embedded' oder 'fullscreen'
    ), $atts);
    
    // Assets registrieren und laden
    wp_enqueue_style(
        'vectorize-wp-designtool',
        VECTORIZE_WP_URL . 'assets/css/designtool.css',
        array(),
        VECTORIZE_WP_VERSION
    );
    
    wp_enqueue_script(
        'vectorize-wp-designtool',
        VECTORIZE_WP_URL . 'assets/js/designtool.js',
        array('jquery'),
        VECTORIZE_WP_VERSION,
        true
    );
    
    // AJAX-URL und Nonce für JavaScript verfügbar machen
    wp_localize_script(
        'vectorize-wp-designtool',
        'vectorizeWpFrontend',
        array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vectorize_wp_frontend_nonce'),
            'maxUploadSize' => vectorize_wp_get_max_upload_size(),
            'siteUrl' => site_url(),
            'pluginUrl' => VECTORIZE_WP_URL,
        )
    );
    
    // Direktes Rendering für alle Modi
ob_start();

if ($atts['mode'] === 'embedded') {
    // Embedded Modus: Canvas in Container anzeigen
    ?>
    <div class="vectorize-wp-designtool-container" style="width: <?php echo esc_attr($atts['width']); ?>; height: <?php echo esc_attr($atts['height']); ?>;">
        <div class="designtool-container">
            <?php include VECTORIZE_WP_PATH . 'templates/designtool-canvas-content.php'; ?>
        </div>
    </div>
    <?php
} else {
    // Vollbild-Modus: Canvas in voller Größe anzeigen
    ?>
    <div class="vectorize-wp-designtool-fullscreen">
        <div class="designtool-container">
            <?php include VECTORIZE_WP_PATH . 'templates/designtool-canvas-content.php'; ?>
        </div>
    </div>
    <?php
}

// Stelle sicher, dass die Skripte und Styles eingebunden sind
wp_enqueue_style('vectorize-wp-designtool');
wp_enqueue_script('vectorize-wp-designtool');

// Wichtig: AJAX-URL und andere Daten für das JavaScript verfügbar machen
wp_localize_script(
    'vectorize-wp-designtool',
    'vectorizeWpFrontend',
    array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vectorize_wp_frontend_nonce'),
        'maxUploadSize' => vectorize_wp_get_max_upload_size(),
        'siteUrl' => site_url(),
        'pluginUrl' => VECTORIZE_WP_URL,
    )
);

return ob_get_clean();
}
add_shortcode('vectorize_designtool', 'vectorize_wp_designtool_shortcode');

/**
 * Registriert die benötigten Assets für das Design-Tool
 */
function vectorize_wp_register_design_assets() {
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
add_action('init', 'vectorize_wp_register_design_assets');

/**
 * AJAX-Handler für die Vektorisierung im Design-Tool
 */
function vectorize_wp_designtool_vectorize_ajax() {
    // Sicherheitsüberprüfung
    check_ajax_referer('vectorize_wp_frontend_nonce', 'nonce');
    
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
    
    // Vectorize WP Hauptinstanz abrufen
    $vectorize_wp = vectorize_wp();
    
    // Plugin-Einstellungen abrufen
    $plugin_options = get_option('vectorize_wp_options', array());
    $vectorization_engine = isset($plugin_options['vectorization_engine']) ? $plugin_options['vectorization_engine'] : 'api';
    
    // Debugging-Info hinzufügen
    error_log('Designtool Vektorisierung: Engine = ' . $vectorization_engine);
    
    // Bild vektorisieren
    try {
        // Je nach Engine unterschiedlich vorgehen
        if ($vectorization_engine === 'api') {
            // API-Instanz verwenden
            $result = $vectorize_wp->api->vectorize_image($temp_file, $options);
        } elseif ($vectorization_engine === 'inkscape') {
            // Inkscape CLI verwenden
            $result = $vectorize_wp->inkscape_cli->vectorize_image($temp_file, $options);
        } else {
            // YPrint Vectorizer verwenden (Standard-Fallback)
            $yprint_result = $vectorize_wp->yprint_vectorizer->vectorize_image($temp_file, $options);
            
            // Ergebnisse ins gemeinsame Format umwandeln
            $result = array(
                'content' => $yprint_result,
                'file_path' => $temp_file . '.svg',
                'file_url' => site_url('wp-content/uploads/vectorize-wp/' . basename($temp_file) . '.svg'),
                'format' => 'svg',
                'is_test_mode' => false
            );
        }
        
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
            'test_mode' => isset($result['test_mode']) ? $result['test_mode'] : 'off',
            'engine' => $vectorization_engine // Engine-Info für Debugging
        ));
    } catch (Exception $e) {
        // Fehlerbehandlung
        @unlink($temp_file); // Temporäre Datei löschen
        wp_send_json_error(__('Vektorisierungsfehler: ' . $e->getMessage(), 'vectorize-wp'));
    }
}
add_action('wp_ajax_vectorize_designtool_vectorize', 'vectorize_wp_designtool_vectorize_ajax');
add_action('wp_ajax_nopriv_vectorize_designtool_vectorize', 'vectorize_wp_designtool_vectorize_ajax');

/**
 * AJAX-Handler zum Speichern des SVG im Design-Tool
 */
function vectorize_wp_designtool_save_svg_ajax() {
    // Sicherheitsüberprüfung
    check_ajax_referer('vectorize_wp_frontend_nonce', 'nonce');
    
    // SVG-Inhalt überprüfen
    if (empty($_POST['svg_content'])) {
        wp_send_json_error(__('Kein SVG-Inhalt vorhanden.', 'vectorize-wp'));
        return;
    }
    
    $svg_content = $_POST['svg_content'];
    $filename = isset($_POST['filename']) ? sanitize_file_name($_POST['filename']) : 'designtool-export.svg';
    
    // Vectorize WP Hauptinstanz abrufen
    $vectorize_wp = vectorize_wp();
    
    // SVG-Handler aus der Hauptinstanz abrufen
    $svg_handler = $vectorize_wp->svg_handler;
    
    // SVG in Mediathek speichern
    $attachment_id = $svg_handler->save_to_media_library($svg_content, $filename);
    
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
add_action('wp_ajax_vectorize_designtool_save_svg', 'vectorize_wp_designtool_save_svg_ajax');
add_action('wp_ajax_nopriv_vectorize_designtool_save_svg', 'vectorize_wp_designtool_save_svg_ajax');

// Hilfsfunktion zum Abrufen der maximalen Upload-Größe
function vectorize_wp_get_max_upload_size() {
    // Prüfen, ob Vectorize WP Hauptinstanz existiert
    if (function_exists('vectorize_wp')) {
        $vectorize_wp = vectorize_wp();
        
        // Max Upload Size aus den Optionen abrufen
        $options = get_option('vectorize_wp_options', array());
        $max_size_mb = isset($options['max_upload_size']) ? (int) $options['max_upload_size'] : 5;
        
        // Sicherstellen, dass die Größe zwischen 1 und 20 MB liegt
        $max_size_mb = max(1, min(20, $max_size_mb));
        
        // In Bytes umrechnen
        return $max_size_mb * 1024 * 1024;
    }
    
    // Fallback, wenn die Hauptinstanz nicht verfügbar ist
    return 5 * 1024 * 1024; // 5 MB Standard
}

/**
 * Fügt einen Menüpunkt für das Design-Tool hinzu
 */
function vectorize_wp_add_designtool_menu() {
    // Auskommentiert, um doppelten Eintrag zu vermeiden
    /*
    add_submenu_page(
        'vectorize-wp', // Parent slug
        __('Design Tool', 'vectorize-wp'), // Page title
        __('Design Tool', 'vectorize-wp'), // Menu title
        'manage_options', // Capability
        'vectorize-wp-designtool', // Menu slug
        'vectorize_wp_render_designtool_page' // Callback function
    );
    */
}
// Action auskommentiert
// add_action('admin_menu', 'vectorize_wp_add_designtool_menu');

/**
 * Rendert die Design-Tool Admin-Seite
 */
function vectorize_wp_render_designtool_page() {
    ?>
    <div class="wrap vectorize-wp-admin">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="vectorize-wp-admin-content">
            <div class="vectorize-wp-admin-section">
                <h2><?php _e('Design Tool Einstellungen', 'vectorize-wp'); ?></h2>
                
                <p><?php _e('Das Design Tool ist ein leistungsstarkes Werkzeug zur Erstellung und Bearbeitung von Designs direkt im Browser.', 'vectorize-wp'); ?></p>
                
                <div class="vectorize-wp-preview-section">
                    <h3><?php _e('Design Tool Vorschau', 'vectorize-wp'); ?></h3>
                    <div class="vectorize-wp-preview-container">
                        <img src="<?php echo esc_url(VECTORIZE_WP_URL . 'assets/images/designtool-preview.png'); ?>" alt="Design Tool Vorschau" class="vectorize-wp-preview-image">
                        <div class="vectorize-wp-preview-overlay">
                            <a href="https://yprint.de/designtool" target="_blank" class="button button-primary"><?php _e('Design Tool öffnen', 'vectorize-wp'); ?></a>
                        </div>
                    </div>
                </div>
                
                <h3><?php _e('Verwendung', 'vectorize-wp'); ?></h3>
                
                <p><?php _e('Du kannst das Design Tool auf zwei Arten verwenden:', 'vectorize-wp'); ?></p>
                
                <ol>
                    <li>
                        <strong><?php _e('Als dedizierte Seite:', 'vectorize-wp'); ?></strong><br>
                        <?php _e('Nutze den direkten Link <a href="https://yprint.de/designtool" target="_blank">yprint.de/designtool</a> für den Zugriff auf das vollständige Design Tool.', 'vectorize-wp'); ?>
                    </li>
                    <li>
                        <strong><?php _e('Als Shortcode:', 'vectorize-wp'); ?></strong><br>
                        <?php _e('Füge den Shortcode [vectorize_designtool] in eine beliebige Seite oder einen Beitrag ein.', 'vectorize-wp'); ?>
                    </li>
                </ol>
                
                <h3><?php _e('Shortcode-Parameter', 'vectorize-wp'); ?></h3>
                
                <ul>
                    <li><code>width</code> - <?php _e('Breite des eingebetteten Design Tools (Standard: 100%)', 'vectorize-wp'); ?></li>
                    <li><code>height</code> - <?php _e('Höhe des eingebetteten Design Tools (Standard: 600px)', 'vectorize-wp'); ?></li>
                    <li><code>mode</code> - <?php _e('Anzeigemodus: "embedded" (eingebettet) oder "fullscreen" (Vollbild) (Standard: embedded)', 'vectorize-wp'); ?></li>
                </ul>
                
                <h4><?php _e('Beispiel:', 'vectorize-wp'); ?></h4>
                <pre>[vectorize_designtool width="800px" height="500px" mode="embedded"]</pre>
            </div>
        </div>
    </div>
    
    <style>
    .vectorize-wp-preview-section {
        margin: 20px 0 30px;
    }
    
    .vectorize-wp-preview-container {
        position: relative;
        max-width: 800px;
        border: 1px solid #ddd;
        border-radius: 4px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .vectorize-wp-preview-image {
        display: block;
        width: 100%;
        height: auto;
    }
    
    .vectorize-wp-preview-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.4);
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        transition: opacity 0.3s ease;
    }
    
    .vectorize-wp-preview-container:hover .vectorize-wp-preview-overlay {
        opacity: 1;
    }
    
    .vectorize-wp-admin-section {
        margin-bottom: 30px;
        background: #fff;
        padding: 20px;
        border-radius: 4px;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    }
    </style>
    <?php
}

/**
 * Fügt einen "In Design Tool bearbeiten" Button zur Mediathek hinzu
 */
function vectorize_wp_add_designtool_media_button($form_fields, $post) {
    // Nur für SVG-Dateien anzeigen
    if ($post->post_mime_type === 'image/svg+xml') {
        $url = add_query_arg(array(
            'designtool' => '1',
            'svg_id' => $post->ID
        ), site_url());
        
        $form_fields['vectorize_designtool'] = array(
            'label' => __('Design Tool', 'vectorize-wp'),
            'input' => 'html',
            'html' => '<a href="' . esc_url($url) . '" class="button" target="_blank">' . __('Im Design Tool bearbeiten', 'vectorize-wp') . '</a>',
        );
    }
    
    return $form_fields;
}
add_filter('attachment_fields_to_edit', 'vectorize_wp_add_designtool_media_button', 10, 2);

/**
 * Handler für SVG-Parameter im Design-Tool
 */
function vectorize_wp_handle_designtool_svg_param() {
    // Nur ausführen, wenn das Design-Tool aufgerufen wird
    if (!isset($_GET['designtool']) || !isset($_GET['svg_id'])) {
        return;
    }
    
    $svg_id = intval($_GET['svg_id']);
    
    // SVG-Datei aus der Mediathek abrufen
    $svg_path = get_attached_file($svg_id);
    
    if (!$svg_path || !file_exists($svg_path)) {
        return;
    }
    
    // SVG-Inhalt laden
    $svg_content = file_get_contents($svg_path);
    
    if (!$svg_content) {
        return;
    }
    
    // SVG-Inhalt an das JavaScript übergeben
    wp_localize_script(
        'vectorize-wp-designtool',
        'vectorizeWpSvgData',
        array(
            'svgId' => $svg_id,
            'svgContent' => $svg_content
        )
    );
}
add_action('wp_enqueue_scripts', 'vectorize_wp_handle_designtool_svg_param', 20);