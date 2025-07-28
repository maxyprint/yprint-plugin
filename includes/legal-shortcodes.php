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
            .yprint-legal-container {
                font-family: 'Roboto', sans-serif;
                max-width: 1200px;
                margin: 0 auto 40px auto;
                padding: 0;
                display: flex;
                flex-direction: column;
            }

            .yprint-legal-sidebar {
                width: 100%;
                display: flex;
                flex-direction: column;
                gap: 10px;
                margin-bottom: 30px;
                padding: 20px;
                border-radius: 8px;
                background-color: #FFFFFF;
                border: 1px solid #CFCFCF;
            }

            .yprint-legal-nav-title {
                font-weight: bold;
                font-size: 16px;
                color: #555;
                margin: 10px 0;
                padding-left: 10px;
                border-left: 3px solid #0079FF;
            }

            .yprint-legal-button {
                display: flex;
                align-items: center;
                padding: 12px 15px;
                text-decoration: none;
                color: #333;
                background-color: #fff;
                border-radius: 6px;
                font-size: 15px;
                transition: all 0.3s ease;
                border: 1px solid #eee;
            }

            .yprint-legal-button:hover {
                background-color: #f0f7ff;
                border-color: #0079FF;
            }

            .yprint-legal-button i {
                margin-right: 10px;
                color: #0079FF;
                font-size: 18px;
                width: 20px;
                text-align: center;
            }

            /* Zurück-Button spezielles Styling */
            .yprint-back-button {
                background-color: #0079FF;
                color: white;
                border: none;
            }

            .yprint-back-button:hover {
                background-color: #0056b3;
            }

            .yprint-back-button i {
                color: white;
            }

            /* Hervorhebung der aktuellen Seite */
            .yprint-current-legal-page {
                background-color: #e6f2ff;
                border-color: #0079FF;
                font-size: 20px;
                font-weight: 700;
                color: #0079FF;
                padding: 15px;
                position: relative;
            }

            .yprint-current-legal-page::after {
                content: "";
                position: absolute;
                right: 15px;
                top: 50%;
                transform: translateY(-50%);
                width: 8px;
                height: 8px;
                border-radius: 50%;
                background-color: #0079FF;
            }

            .yprint-current-legal-page i {
                font-size: 22px;
            }

            /* Footer der Sidebar */
            .yprint-legal-footer {
                margin-top: 20px;
                padding-top: 20px;
                border-top: 1px solid #eee;
                display: flex;
                flex-direction: column;
                align-items: center;
            }

            .yprint-footer-logo {
                width: 50px;
                height: 50px;
                margin-bottom: 10px;
            }

            /* Responsive Anpassungen */
            @media (min-width: 768px) {
                .yprint-legal-container {
                    flex-direction: row;
                }

                .yprint-legal-sidebar {
                    width: 280px;
                    position: sticky;
                    top: 20px;
                    align-self: flex-start;
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
        <?php echo $content; ?>
    </div>
    
    <style>
        .yprint-legal-content {
            font-family: 'Roboto', sans-serif;
            line-height: 1.6;
            color: #333;
        }
        
        .yprint-legal-content h1,
        .yprint-legal-content h2,
        .yprint-legal-content h3 {
            color: #0079FF;
            margin-top: 30px;
            margin-bottom: 15px;
        }
        
        .yprint-legal-content p {
            margin-bottom: 15px;
        }
        
        .yprint-legal-content a {
            color: #0079FF;
            text-decoration: none;
        }
        
        .yprint-legal-content a:hover {
            text-decoration: underline;
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
            }
        }
    }
}

/**
 * Hook für die automatische Erstellung der Rechtstext-Seiten
 * Wird nur einmal beim Plugin-Aktivierung ausgeführt
 */
function yprint_activate_legal_pages() {
    // Prüfe, ob die Seiten bereits erstellt wurden
    $legal_pages_created = get_option('yprint_legal_pages_created', false);
    
    if (!$legal_pages_created) {
        yprint_create_legal_pages();
        update_option('yprint_legal_pages_created', true);
    }
}
add_action('init', 'yprint_activate_legal_pages');

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