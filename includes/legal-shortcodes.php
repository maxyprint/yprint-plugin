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
 * Shortcode für die Navigation der rechtlichen Seiten
 * 
 * Usage: [legal_navigation]
 * 
 * @return string The formatted HTML output
 */
function legal_navigation_shortcode() {
    // Sammle alle rechtlichen Seiten in einem Array
    $legal_pages = array(
        'cookies' => array(
            'title' => 'Cookies',
            'id' => get_page_by_path('cookies')->ID,
            'icon' => 'cookie'
        ),
        'impressum' => array(
            'title' => 'Impressum',
            'id' => get_page_by_path('impressum')->ID,
            'icon' => 'info'
        ),
        'datenschutz' => array(
            'title' => 'Datenschutz',
            'id' => get_page_by_path('datenschutz')->ID,
            'icon' => 'shield'
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

    // Bestimme die aktuelle Seite
    $current_page_id = get_the_ID();
    $current_page_slug = get_post_field('post_name', get_post());

    // Speichere die vorherige URL in einer Session, falls sie nicht aus einer rechtlichen Seite kommt
    if (!isset($_SESSION)) {
        session_start();
    }

    $referer = wp_get_referer();
    if ($referer && !preg_match('/(cookies|impressum|datenschutz|rechtlicher-hinweis|gesetz-ueber-digitale-dienste|produktsicherheitsverordnung)/i', $referer)) {
        $_SESSION['previous_page'] = $referer;
    }

    // Wenn keine vorherige Seite in der Session gespeichert ist, setze Startseite als Standard
    if (!isset($_SESSION['previous_page'])) {
        $_SESSION['previous_page'] = home_url();
    }

    // Beginne mit dem Output-Buffering
    ob_start();
    ?>
    <!-- Google Fonts für Roboto -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" rel="stylesheet">
    <!-- Font Awesome für Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <div class="yprint-legal-container">
        <div class="yprint-legal-sidebar">
            <!-- Zurück-Button immer als erster Button -->
            <a href="<?php echo $_SESSION['previous_page']; ?>" class="yprint-legal-button yprint-back-button">
                <i class="fas fa-arrow-left"></i> Zurück
            </a>
            
            <!-- Navigation für rechtliche Seiten -->
            <div class="yprint-legal-nav-title">Rechtliche Informationen</div>
            
            <?php foreach ($legal_pages as $slug => $page): ?>
                <?php 
                // Prüfe, ob dies die aktuelle Seite ist
                $is_current = ($page['id'] == $current_page_id || $slug == $current_page_slug);
                $class = $is_current ? 'yprint-current-legal-page' : '';
                ?>
                <a href="<?php echo get_permalink($page['id']); ?>" class="yprint-legal-button <?php echo $class; ?>">
                    <i class="fas fa-<?php echo $page['icon']; ?>"></i> <?php echo $page['title']; ?>
                </a>
            <?php endforeach; ?>
            
            <!-- Logo im Footer -->
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
 * Diese Funktion ermöglicht die Speicherung der zurückliegenden URL
 */
function start_session_for_legal_pages() {
    // Nur Sessions starten, wenn sie wirklich benötigt werden
    $legal_pages = array('cookies', 'impressum', 'datenschutz', 'rechtlicher-hinweis', 
                         'gesetz-ueber-digitale-dienste', 'produktsicherheitsverordnung');
    
    $current_page = get_post_field('post_name', get_post());
    
    if (in_array($current_page, $legal_pages) && !session_id()) {
        session_start();
    }
    
    // Handler für das Beenden der Session nach der Anfrage
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
    if (is_page(array('cookies', 'impressum', 'datenschutz', 'rechtlicher-hinweis', 'gesetz-ueber-digitale-dienste', 'produktsicherheitsverordnung'))) {
        wp_enqueue_style('roboto-font', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap');
    }
}
add_action('wp_enqueue_scripts', 'add_roboto_font_for_legal_pages');