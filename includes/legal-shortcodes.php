<?php
/**
 * Legal pages shortcodes for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lädt die Rechtstexte aus dem Rechtstexte/ Ordner
 *
 * @return array Array mit den Rechtstexten und ihren Metadaten
 */
function yprint_load_legal_texts() {
    $legal_texts_dir = YPRINT_PLUGIN_DIR . 'Rechtstexte/';
    $legal_texts = array();
    
    // Mapping für Dateinamen zu Anzeigenamen und Icons
    $text_mapping = array(
        'AGB.htm' => array(
            'title' => 'Allgemeine Geschäftsbedingungen',
            'icon' => 'file-contract',
            'slug' => 'agb'
        ),
        'Datenschutzerklaerung.htm' => array(
            'title' => 'Datenschutzerklärung',
            'icon' => 'shield',
            'slug' => 'datenschutz'
        ),
        'Impressum.htm' => array(
            'title' => 'Impressum',
            'icon' => 'info',
            'slug' => 'impressum'
        ),
        'Versandbedingungen.htm' => array(
            'title' => 'Versandbedingungen',
            'icon' => 'truck',
            'slug' => 'versandbedingungen'
        ),
        'Widerrufsrecht.htm' => array(
            'title' => 'Widerrufsrecht',
            'icon' => 'undo',
            'slug' => 'widerrufsrecht'
        )
    );
    
    // Scanne den Rechtstexte/ Ordner
    if (is_dir($legal_texts_dir)) {
        $files = scandir($legal_texts_dir);
        
        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'htm') {
                $filename = basename($file);
                
                if (isset($text_mapping[$filename])) {
                    $mapping = $text_mapping[$filename];
                    $legal_texts[$mapping['slug']] = array(
                        'title' => $mapping['title'],
                        'icon' => $mapping['icon'],
                        'slug' => $mapping['slug'],
                        'file' => $file,
                        'filepath' => $legal_texts_dir . $file
                    );
                }
            }
        }
    }
    
    return $legal_texts;
}

/**
 * Rendert einen Rechtstext aus der Datei
 *
 * @param string $slug Der Slug des Rechtstexts
 * @return string Der gerenderte HTML-Inhalt
 */
function yprint_render_legal_text($slug) {
    $legal_texts = yprint_load_legal_texts();
    
    if (!isset($legal_texts[$slug])) {
        return '<p>Rechtstext nicht gefunden.</p>';
    }
    
    $text_info = $legal_texts[$slug];
    $filepath = $text_info['filepath'];
    
    if (!file_exists($filepath)) {
        return '<p>Datei nicht gefunden: ' . esc_html($filepath) . '</p>';
    }
    
    $content = file_get_contents($filepath);
    
    if ($content === false) {
        return '<p>Fehler beim Lesen der Datei.</p>';
    }
    
    // HTML-Inhalt bereinigen und formatieren
    $content = wp_kses_post($content);
    
    return $content;
}

/**
 * Shortcode für die Rechtstext-Navigation
 * 
 * @return string HTML für die Navigation
 */
function legal_navigation_shortcode($atts = array()) {
    // Session starten für die Zurück-Funktion
    if (!session_id()) {
        session_start();
    }
    
    // Shortcode-Attribute verarbeiten
    $atts = shortcode_atts(array(
        'slug' => ''
    ), $atts);
    
    // Aktuelle Seite speichern für Zurück-Button
    $_SESSION['previous_page'] = $_SERVER['HTTP_REFERER'] ?? home_url();
    
    // Aktuelle Seite ermitteln
    $current_page_id = get_queried_object_id();
    $current_page_slug = get_post_field('post_name', $current_page_id);
    
    // Falls ein Slug übergeben wurde, verwende diesen
    if (!empty($atts['slug'])) {
        $current_page_slug = $atts['slug'];
    }
    
    // Rechtstexte laden
    $legal_texts = yprint_load_legal_texts();
    
    // Content laden falls Slug vorhanden
    $content = '';
    if (!empty($current_page_slug) && isset($legal_texts[$current_page_slug])) {
        $content = yprint_render_legal_text($current_page_slug);
    }
    
    ob_start();
    ?>
    <div class="yprint-legal-platform">
        <div class="yprint-legal-sidebar">
            <a href="<?php echo $_SESSION['previous_page']; ?>" class="yprint-legal-button yprint-back-button">
                <i class="fas fa-arrow-left"></i> Zurück
            </a>

            <div class="yprint-legal-nav-title">Rechtliche Informationen</div>

            <?php 
            // Zeige die Rechtstexte aus dem Ordner
            foreach ($legal_texts as $slug => $text_info): 
                $is_current = ($slug === $current_page_slug);
                $class = $is_current ? 'yprint-current-legal-page' : '';
                $permalink = home_url('/' . $slug . '/');
            ?>
                <a href="<?php echo $permalink; ?>" class="yprint-legal-button <?php echo $class; ?>">
                    <i class="fas fa-<?php echo $text_info['icon']; ?>"></i> <?php echo $text_info['title']; ?>
                </a>
            <?php endforeach; ?>

            <?php
            // Zusätzliche statische Seiten
            $static_pages = array(
                'cookies' => array('title' => 'Cookies', 'icon' => 'cookie-bite'),
                'rechtlicher-hinweis' => array('title' => 'Rechtlicher Hinweis', 'icon' => 'gavel'),
                'gesetz-ueber-digitale-dienste' => array('title' => 'Gesetz über digitale Dienste', 'icon' => 'globe-europe'),
                'produktsicherheitsverordnung' => array('title' => 'Produktsicherheit', 'icon' => 'shield-check'),
            );

            foreach ($static_pages as $slug => $page): 
                $page_obj = get_page_by_path($slug);
                if ($page_obj) {
                    $is_current = ($slug === $current_page_slug || $page_obj->ID == $current_page_id);
                    $class = $is_current ? 'yprint-current-legal-page' : '';
            ?>
                <a href="<?php echo get_permalink($page_obj->ID); ?>" class="yprint-legal-button <?php echo $class; ?>">
                    <i class="fas fa-<?php echo $page['icon']; ?>"></i> <?php echo $page['title']; ?>
                </a>
            <?php 
                }
            endforeach; 
            ?>

            <div class="yprint-legal-footer">
                <img src="https://yprint.de/wp-content/uploads/2024/10/y-icon.svg" alt="yprint Logo" class="yprint-footer-logo">
                <div class="yprint-footer-text">yprint legal</div>
            </div>
        </div>

        <div class="yprint-legal-content-area">
            <?php if (!empty($content)): ?>
                <div class="yprint-legal-content-wrapper">
                    <?php echo $content; ?>
                </div>
            <?php else: ?>
                <div class="yprint-legal-content-wrapper">
                    <div class="yprint-legal-placeholder">
                        <i class="fas fa-file-alt"></i>
                        <h2>Wählen Sie einen Rechtstext</h2>
                        <p>Bitte wählen Sie aus der Navigation links einen Rechtstext aus, um ihn anzuzeigen.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php echo yprint_get_legal_platform_styles(); ?>
    <?php
    return ob_get_clean();
}
add_shortcode('legal_navigation', 'legal_navigation_shortcode');

/**
 * Generiert die CSS-Styles für die Legal Platform
 */
function yprint_get_legal_platform_styles() {
    return '
    <style>
        /* ===== YPRINT LEGAL PLATFORM STYLES ===== */
        
        /* Hauptcontainer */
        .yprint-legal-platform {
            display: flex;
            gap: 30px;
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px;
            min-height: 100vh;
            font-family: "Roboto", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }

        /* Sidebar Navigation - Feste Breite, Sticky */
        .yprint-legal-sidebar {
            width: 320px;
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 30px;
            border-radius: 16px;
            background: #ffffff;
            border: 1px solid #e1e8ed;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            height: fit-content;
            position: sticky;
            top: 20px;
            align-self: flex-start;
        }

        /* Content Area - Flexibel */
        .yprint-legal-content-area {
            flex: 1;
            min-width: 0;
        }

        .yprint-legal-content-wrapper {
            background: #ffffff;
            border-radius: 16px;
            padding: 50px;
            border: 1px solid #e1e8ed;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
            min-height: 600px;
        }

        /* Navigation Elemente */
        .yprint-legal-nav-title {
            font-weight: 700;
            font-size: 20px;
            color: #1a202c;
            margin: 0 0 20px 0;
            padding: 0 0 15px 0;
            border-bottom: 2px solid #0079FF;
            text-align: center;
        }

        .yprint-legal-button {
            display: flex;
            align-items: center;
            padding: 16px 18px;
            text-decoration: none;
            color: #4a5568;
            background: #ffffff;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.2s ease;
            border: 1px solid #e2e8f0;
            margin-bottom: 4px;
        }

        .yprint-legal-button:hover {
            background: #f7fafc;
            border-color: #0079FF;
            color: #0079FF;
        }

        .yprint-legal-button i {
            margin-right: 14px;
            color: #0079FF;
            font-size: 16px;
            width: 20px;
            text-align: center;
        }

        /* Zurück-Button */
        .yprint-back-button {
            background: #0079FF;
            color: white;
            border: none;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .yprint-back-button:hover {
            background: #0056b3;
            color: white;
        }

        .yprint-back-button i {
            color: white;
        }

        /* Aktuelle Seite */
        .yprint-current-legal-page {
            background: #0079FF;
            border-color: #0079FF;
            color: white;
            font-weight: 600;
        }

        .yprint-current-legal-page i {
            color: white;
        }

        .yprint-current-legal-page:hover {
            background: #0079FF;
            color: white;
        }

        /* Footer */
        .yprint-legal-footer {
            margin-top: 30px;
            padding-top: 25px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 10px;
        }

        .yprint-footer-logo {
            width: 45px;
            height: 45px;
            opacity: 0.8;
        }

        .yprint-footer-text {
            font-size: 12px;
            color: #718096;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Content Styling - Minimal */
        .yprint-legal-content-wrapper {
            font-family: "Roboto", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            font-size: 16px;
            line-height: 1.6;
            color: #2d3748;
        }

        /* Placeholder */
        .yprint-legal-placeholder {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 500px;
            color: #718096;
            text-align: center;
            padding: 50px;
        }

        .yprint-legal-placeholder i {
            font-size: 4rem;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .yprint-legal-placeholder h2 {
            font-size: 1.8rem;
            color: #4a5568;
            margin-bottom: 10px;
            font-weight: 600;
        }

        .yprint-legal-placeholder p {
            font-size: 1.1rem;
            max-width: 400px;
            margin: 0 auto;
        }

        /* ===== RESPONSIVE DESIGN ===== */
        
        @media (max-width: 1200px) {
            .yprint-legal-platform {
                gap: 25px;
                padding: 15px;
            }

            .yprint-legal-sidebar {
                width: 280px;
                padding: 25px;
            }
        }

        @media (max-width: 1024px) {
            .yprint-legal-platform {
                flex-direction: column;
                gap: 20px;
                padding: 15px;
            }

            .yprint-legal-sidebar {
                width: 100%;
                position: static;
                order: 1;
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 10px;
                align-items: start;
            }

            .yprint-legal-nav-title {
                grid-column: 1 / -1;
                text-align: center;
                margin-bottom: 15px;
            }

            .yprint-back-button {
                grid-column: 1 / -1;
                margin-bottom: 15px;
            }

            .yprint-legal-footer {
                grid-column: 1 / -1;
                margin-top: 20px;
            }

            .yprint-legal-content-area {
                order: 2;
            }

            .yprint-legal-content-wrapper {
                padding: 30px;
            }
        }

        @media (max-width: 768px) {
            .yprint-legal-platform {
                padding: 10px;
            }

            .yprint-legal-sidebar {
                padding: 20px;
                grid-template-columns: 1fr;
            }

            .yprint-legal-button {
                padding: 14px 16px;
                font-size: 14px;
            }

            .yprint-legal-nav-title {
                font-size: 18px;
            }

            .yprint-legal-content-wrapper {
                padding: 25px;
            }
        }

        @media (max-width: 480px) {
            .yprint-legal-platform {
                padding: 5px;
            }

            .yprint-legal-sidebar {
                padding: 15px;
            }

            .yprint-legal-content-wrapper {
                padding: 20px;
            }

            .yprint-legal-placeholder {
                height: 400px;
                padding: 30px;
            }

            .yprint-legal-placeholder i {
                font-size: 3rem;
            }

            .yprint-legal-placeholder h2 {
                font-size: 1.5rem;
            }
        }
    </style>';
}

/**
 * Shortcode für die Anzeige eines Rechtstexts
 *
 * Usage: [legal_text slug="agb"]
 *
 * @param array $atts Shortcode attributes
 * @return string The formatted HTML output
 */
function legal_text_shortcode($atts) {
    $atts = shortcode_atts(array(
        'slug' => ''
    ), $atts);
    
    if (empty($atts['slug'])) {
        return '<p>Kein Rechtstext-Slug angegeben.</p>';
    }
    
    $content = yprint_render_legal_text($atts['slug']);
    
    return $content;
}
add_shortcode('legal_text', 'legal_text_shortcode');

/**
 * Erstellt automatisch WordPress-Seiten für die Rechtstexte
 */
function yprint_create_legal_pages() {
    $legal_texts = yprint_load_legal_texts();
    
    foreach ($legal_texts as $slug => $text_info) {
        // Prüfe, ob die Seite bereits existiert
        $existing_page = get_page_by_path($slug);
        
        if (!$existing_page) {
            // Erstelle die Seite
            $page_data = array(
                'post_title' => $text_info['title'],
                'post_content' => '[legal_navigation slug="' . $slug . '"]',
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_name' => $slug,
                'post_author' => 1,
                'comment_status' => 'closed',
                'ping_status' => 'closed'
            );
            
            $page_id = wp_insert_post($page_data);
            
            if ($page_id) {
                // Setze die Seite als rechtliche Seite
                update_post_meta($page_id, '_yprint_legal_page', 'true');
                update_post_meta($page_id, '_yprint_legal_slug', $slug);
                
                // Debug-Ausgabe
                error_log('YPrint: Rechtstext-Seite erstellt: ' . $slug . ' (ID: ' . $page_id . ')');
            } else {
                error_log('YPrint: Fehler beim Erstellen der Seite: ' . $slug);
            }
        } else {
            // Debug-Ausgabe für existierende Seiten
            error_log('YPrint: Rechtstext-Seite existiert bereits: ' . $slug . ' (ID: ' . $existing_page->ID . ')');
        }
    }
}

/**
 * Hook für die automatische Erstellung der Rechtstext-Seiten
 * Wird bei jedem Seitenaufruf ausgeführt, falls Seiten fehlen
 */
function yprint_activate_legal_pages() {
    // Erstelle die Seiten immer, falls sie nicht existieren
    yprint_create_legal_pages();
}
add_action('init', 'yprint_activate_legal_pages', 5);

/**
 * Manuelle Test-Funktion für die Rechtstext-Erstellung
 * Aufruf: /?yprint_test_legal_pages=1
 */
function yprint_test_legal_pages() {
    if (isset($_GET['yprint_test_legal_pages']) && current_user_can('manage_options')) {
        echo '<h2>YPrint Rechtstext-Test</h2>';
        
        $legal_texts = yprint_load_legal_texts();
        echo '<h3>Gefundene Rechtstexte:</h3>';
        echo '<ul>';
        foreach ($legal_texts as $slug => $text_info) {
            echo '<li>' . $text_info['title'] . ' (Slug: ' . $slug . ')</li>';
        }
        echo '</ul>';
        
        echo '<h3>Erstelle Seiten...</h3>';
        yprint_create_legal_pages();
        
        echo '<h3>Test abgeschlossen!</h3>';
        echo '<p><a href="/datenschutz/">Teste Datenschutz-Seite</a></p>';
        echo '<p><a href="/agb/">Teste AGB-Seite</a></p>';
        
        exit;
    }
}
add_action('init', 'yprint_test_legal_pages');

/**
 * Diese Funktion ermöglicht die Speicherung der zurückliegenden URL
 */
function start_session_for_legal_pages() {
    // Lade alle Rechtstext-Slugs
    $legal_texts = yprint_load_legal_texts();
    $legal_slugs = array_keys($legal_texts);
    
    // Zusätzliche statische Seiten
    $legal_slugs[] = 'cookies';
    $legal_slugs[] = 'rechtlicher-hinweis';
    $legal_slugs[] = 'gesetz-ueber-digitale-dienste';
    $legal_slugs[] = 'produktsicherheitsverordnung';
    
    $current_page = get_post_field('post_name', get_post());
    
    if (in_array($current_page, $legal_slugs) && !session_id()) {
        session_start();
    }
    
    // Handler für das Beenden der Session nach der Anfrage!
    add_action('shutdown', function() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    });
}
add_action('init', 'start_session_for_legal_pages', 1);

/**
 * Laden der Roboto Schriftart global
 */
function add_roboto_font_for_legal_pages() {
    $legal_texts = yprint_load_legal_texts();
    $legal_slugs = array_keys($legal_texts);
    
    // Zusätzliche statische Seiten
    $legal_slugs[] = 'cookies';
    $legal_slugs[] = 'rechtlicher-hinweis';
    $legal_slugs[] = 'gesetz-ueber-digitale-dienste';
    $legal_slugs[] = 'produktsicherheitsverordnung';
    
    if (is_page($legal_slugs)) {
        wp_enqueue_style('roboto-font', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');
    }
}
add_action('wp_enqueue_scripts', 'add_roboto_font_for_legal_pages');