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
 * Shortcode für die Navigation der rechtlichen Seiten
 *
 * Usage: [legal_navigation]
 *
 * @return string The formatted HTML output
 */
function legal_navigation_shortcode() {
    // Lade die Rechtstexte dynamisch
    $legal_texts = yprint_load_legal_texts();
    
    // Bestimme die aktuelle Seite
    $current_page_id = get_the_ID();
    $current_page_slug = get_post_field('post_name', get_post());

    // Speichere die vorherige URL in einer Session, falls sie nicht aus einer rechtlichen Seite kommt
    if (!isset($_SESSION)) {
        session_start();
    }

    // Erweitere das Regex um alle Rechtstext-Slugs
    $referer = wp_get_referer();
    $legal_slugs = array_keys($legal_texts);
    $legal_slugs[] = 'cookies'; // Cookies-Seite hinzufügen
    $legal_slugs[] = 'rechtlicher-hinweis';
    $legal_slugs[] = 'gesetz-ueber-digitale-dienste';
    $legal_slugs[] = 'produktsicherheitsverordnung';
    
    $legal_pattern = implode('|', $legal_slugs);
    if ($referer && !preg_match('/(' . $legal_pattern . ')/i', $referer)) {
        $_SESSION['previous_page'] = $referer;
    }

    // Wenn keine vorherige Seite in der Session gespeichert ist, setze Startseite als Standard
    if (!isset($_SESSION['previous_page'])) {
        $_SESSION['previous_page'] = home_url();
    }

    // Beginne mit dem Output-Buffering
    ob_start();
    ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <div class="yprint-legal-container">
        <div class="yprint-legal-sidebar">
            <a href="<?php echo $_SESSION['previous_page']; ?>" class="yprint-legal-button yprint-back-button">
                <i class="fas fa-arrow-left"></i> Zurück
            </a>

            <div class="yprint-legal-nav-title">Rechtliche Informationen</div>

            <?php 
            // Zeige zuerst die Rechtstexte aus dem Ordner
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
            // Zusätzliche statische Seiten (falls vorhanden)
            $static_pages = array(
                'cookies' => array(
                    'title' => 'Cookies',
                    'id' => get_page_by_path('cookies')->ID,
                    'icon' => 'cookie'
                ),
                'rechtlicher-hinweis' => array(
                    'title' => 'Rechtlicher Hinweis',
                    'id' => get_page_by_path('rechtlicher-hinweis')->ID,
                    'icon' => 'gavel'
                ),
                'gesetz-ueber-digitale-dienste' => array(
                    'title' => 'Gesetz über digitale Dienste',
                    'id' => get_page_by_path('gesetz-ueber-digitale-dienste')->ID,
                    'icon' => 'globe'
                ),
                'produktsicherheitsverordnung' => array(
                    'title' => 'Produktsicherheit',
                    'id' => get_page_by_path('produktsicherheitsverordnung')->ID,
                    'icon' => 'check-circle'
                ),
            );

            foreach ($static_pages as $slug => $page): 
                if ($page['id']) { // Nur anzeigen wenn die Seite existiert
                    $is_current = ($page['id'] == $current_page_id || $slug == $current_page_slug);
                    $class = $is_current ? 'yprint-current-legal-page' : '';
            ?>
                <a href="<?php echo get_permalink($page['id']); ?>" class="yprint-legal-button <?php echo $class; ?>">
                    <i class="fas fa-<?php echo $page['icon']; ?>"></i> <?php echo $page['title']; ?>
                </a>
            <?php 
                }
            endforeach; 
            ?>

            <div class="yprint-legal-footer">
                <img src="https://yprint.de/wp-content/uploads/2024/10/y-icon.svg" alt="yprint Logo" class="yprint-footer-logo">
            </div>
        </div>

        <style>
            /* Container für das gesamte Layout */
            .yprint-legal-container {
                font-family: 'Roboto', sans-serif;
                max-width: 1400px;
                margin: 0 auto;
                padding: 20px;
                display: flex;
                gap: 40px;
                min-height: 100vh;
                background-color: #f8f9fa;
            }

            /* Sidebar Navigation */
            .yprint-legal-sidebar {
                width: 300px;
                flex-shrink: 0;
                display: flex;
                flex-direction: column;
                gap: 12px;
                padding: 25px;
                border-radius: 12px;
                background-color: #FFFFFF;
                border: 1px solid #e9ecef;
                box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
                height: fit-content;
                position: sticky;
                top: 20px;
            }

            .yprint-legal-nav-title {
                font-weight: 600;
                font-size: 18px;
                color: #1a1a1a;
                margin: 20px 0 15px 0;
                padding-left: 12px;
                border-left: 4px solid #0079FF;
            }

            .yprint-legal-button {
                display: flex;
                align-items: center;
                padding: 14px 16px;
                text-decoration: none;
                color: #4a5568;
                background-color: #fff;
                border-radius: 8px;
                font-size: 15px;
                font-weight: 500;
                transition: all 0.2s ease;
                border: 1px solid #e2e8f0;
                position: relative;
            }

            .yprint-legal-button:hover {
                background-color: #f7fafc;
                border-color: #0079FF;
                color: #0079FF;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 121, 255, 0.15);
            }

            .yprint-legal-button i {
                margin-right: 12px;
                color: #0079FF;
                font-size: 16px;
                width: 20px;
                text-align: center;
            }

            /* Zurück-Button spezielles Styling */
            .yprint-back-button {
                background: linear-gradient(135deg, #0079FF 0%, #0056b3 100%);
                color: white;
                border: none;
                font-weight: 600;
                margin-bottom: 10px;
            }

            .yprint-back-button:hover {
                background: linear-gradient(135deg, #0056b3 0%, #004085 100%);
                color: white;
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(0, 121, 255, 0.3);
            }

            .yprint-back-button i {
                color: white;
            }

            /* Hervorhebung der aktuellen Seite */
            .yprint-current-legal-page {
                background: linear-gradient(135deg, #0079FF 0%, #0056b3 100%);
                border-color: #0079FF;
                color: white;
                font-weight: 600;
                padding: 16px;
                box-shadow: 0 4px 12px rgba(0, 121, 255, 0.25);
            }

            .yprint-current-legal-page i {
                color: white;
            }

            /* Footer der Sidebar */
            .yprint-legal-footer {
                margin-top: 25px;
                padding-top: 20px;
                border-top: 1px solid #e2e8f0;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .yprint-footer-logo {
                width: 40px;
                height: 40px;
                opacity: 0.7;
            }

            /* Responsive Anpassungen */
            @media (max-width: 1024px) {
                .yprint-legal-container {
                    flex-direction: column;
                    gap: 20px;
                    padding: 15px;
                }

                .yprint-legal-sidebar {
                    width: 100%;
                    position: static;
                }
            }

            @media (max-width: 768px) {
                .yprint-legal-container {
                    padding: 10px;
                }

                .yprint-legal-sidebar {
                    padding: 20px;
                }

                .yprint-legal-button {
                    padding: 12px 14px;
                    font-size: 14px;
                }

                .yprint-legal-nav-title {
                    font-size: 16px;
                }
            }

            @media (max-width: 480px) {
                .yprint-legal-container {
                    padding: 5px;
                }

                .yprint-legal-sidebar {
                    padding: 15px;
                }
            }
        </style>
    </div>
    <?php
    // Gib den gepufferten Inhalt zurück
    return ob_get_clean();
}
add_shortcode('legal_navigation', 'legal_navigation_shortcode');

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
    
    ob_start();
    ?>
    <div class="yprint-legal-content">
        <div class="yprint-legal-content-inner">
            <?php echo $content; ?>
        </div>
    </div>
    
    <style>
        /* Container für den Rechtstext-Inhalt */
        .yprint-legal-content {
            flex: 1;
            min-width: 0;
        }

        .yprint-legal-content-inner {
            background: #FFFFFF;
            border-radius: 12px;
            padding: 40px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            line-height: 1.7;
            color: #2d3748;
            font-size: 16px;
        }

        /* Typography für Rechtstexte */
        .yprint-legal-content-inner h1,
        .yprint-legal-content-inner h2,
        .yprint-legal-content-inner h3,
        .yprint-legal-content-inner h4,
        .yprint-legal-content-inner h5,
        .yprint-legal-content-inner h6 {
            color: #1a202c;
            font-weight: 600;
            margin-top: 2em;
            margin-bottom: 1em;
            line-height: 1.3;
        }

        .yprint-legal-content-inner h1 {
            font-size: 2.5em;
            border-bottom: 3px solid #0079FF;
            padding-bottom: 0.5em;
            margin-top: 0;
        }

        .yprint-legal-content-inner h2 {
            font-size: 2em;
            color: #0079FF;
        }

        .yprint-legal-content-inner h3 {
            font-size: 1.5em;
            color: #2d3748;
        }

        .yprint-legal-content-inner h4 {
            font-size: 1.25em;
            color: #4a5568;
        }

        /* Paragraphs und Text */
        .yprint-legal-content-inner p {
            margin-bottom: 1.2em;
            text-align: justify;
        }

        .yprint-legal-content-inner strong {
            color: #1a202c;
            font-weight: 600;
        }

        /* Links */
        .yprint-legal-content-inner a {
            color: #0079FF;
            text-decoration: none;
            border-bottom: 1px solid transparent;
            transition: border-color 0.2s ease;
        }

        .yprint-legal-content-inner a:hover {
            border-bottom-color: #0079FF;
        }

        /* Lists */
        .yprint-legal-content-inner ul,
        .yprint-legal-content-inner ol {
            margin: 1.5em 0;
            padding-left: 2em;
        }

        .yprint-legal-content-inner li {
            margin-bottom: 0.5em;
        }

        /* Tables */
        .yprint-legal-content-inner table {
            width: 100%;
            border-collapse: collapse;
            margin: 1.5em 0;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .yprint-legal-content-inner th,
        .yprint-legal-content-inner td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .yprint-legal-content-inner th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #2d3748;
        }

        .yprint-legal-content-inner tr:hover {
            background-color: #f8f9fa;
        }

        /* Blockquotes */
        .yprint-legal-content-inner blockquote {
            margin: 1.5em 0;
            padding: 1em 1.5em;
            border-left: 4px solid #0079FF;
            background-color: #f7fafc;
            border-radius: 0 8px 8px 0;
            font-style: italic;
        }

        /* Code und Pre */
        .yprint-legal-content-inner code {
            background-color: #f1f5f9;
            padding: 0.2em 0.4em;
            border-radius: 4px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 0.9em;
        }

        .yprint-legal-content-inner pre {
            background-color: #1a202c;
            color: #e2e8f0;
            padding: 1.5em;
            border-radius: 8px;
            overflow-x: auto;
            margin: 1.5em 0;
        }

        /* Responsive Anpassungen für den Inhalt */
        @media (max-width: 1024px) {
            .yprint-legal-content-inner {
                padding: 30px;
            }
        }

        @media (max-width: 768px) {
            .yprint-legal-content-inner {
                padding: 25px;
                font-size: 15px;
            }

            .yprint-legal-content-inner h1 {
                font-size: 2em;
            }

            .yprint-legal-content-inner h2 {
                font-size: 1.75em;
            }

            .yprint-legal-content-inner h3 {
                font-size: 1.4em;
            }
        }

        @media (max-width: 480px) {
            .yprint-legal-content-inner {
                padding: 20px;
                font-size: 14px;
            }

            .yprint-legal-content-inner h1 {
                font-size: 1.75em;
            }

            .yprint-legal-content-inner h2 {
                font-size: 1.5em;
            }

            .yprint-legal-content-inner h3 {
                font-size: 1.3em;
            }
        }

        /* Spezielle Anpassungen für Rechtstexte */
        .yprint-legal-content-inner .Haendlerbund_Rechtstext_Titel {
            font-size: 1.8em;
            font-weight: 700;
            color: #0079FF;
            margin-bottom: 1.5em;
            display: block;
        }

        .yprint-legal-content-inner .Haendlerbund_Rechtstext_Paragraph {
            font-size: 1.3em;
            font-weight: 600;
            color: #2d3748;
            margin-top: 2em;
            margin-bottom: 1em;
            display: block;
        }

        .yprint-legal-content-inner .Haendlerbund_Rechtstext_Absatz {
            margin-bottom: 1em;
            display: block;
        }

        /* HR Styling */
        .yprint-legal-content-inner hr {
            border: none;
            height: 2px;
            background: linear-gradient(90deg, #0079FF 0%, #e2e8f0 100%);
            margin: 2em 0;
            border-radius: 1px;
        }
    </style>
    <?php
    return ob_get_clean();
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
                'post_name' => $slug,
                'post_status' => 'publish',
                'post_type' => 'page',
                'post_content' => '[legal_navigation][legal_text slug="' . $slug . '"]',
                'page_template' => 'default'
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