<?php
/**
 * UI-related shortcodes and components for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to display the current page title with custom styling
 * 
 * Usage: [styled_page_title]
 * 
 * @return string The styled page title
 */
function styled_page_title_shortcode() {
    $title = get_the_title(); // Holt den aktuellen Seitentitel
    return '<div style="font-family: \'Roboto\', sans-serif; font-size: 25pt; font-weight: 600;">' . esc_html($title) . '</div>';
}
add_shortcode('styled_page_title', 'styled_page_title_shortcode');

/**
 * Shortcode to display a styled heading with custom text
 * 
 * Usage: [styled_heading text="Your Heading Text"]
 * 
 * @param array $atts Shortcode attributes
 * @return string The styled heading
 */
function styled_heading_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => '',
        'size' => '25pt',
        'weight' => '600',
        'align' => 'left'
    ), $atts, 'styled_heading');
    
    return '<div style="font-family: \'Roboto\', sans-serif; font-size: ' . esc_attr($atts['size']) . '; font-weight: ' . esc_attr($atts['weight']) . '; text-align: ' . esc_attr($atts['align']) . ';">' . esc_html($atts['text']) . '</div>';
}
add_shortcode('styled_heading', 'styled_heading_shortcode');

/**
 * Shortcode to create a styled separator (horizontal line)
 * 
 * Usage: [styled_separator]
 * 
 * @param array $atts Shortcode attributes
 * @return string The styled separator
 */
function styled_separator_shortcode($atts) {
    $atts = shortcode_atts(array(
        'color' => '#0079FF',
        'width' => '100%',
        'height' => '3px',
        'margin' => '20px 0'
    ), $atts, 'styled_separator');
    
    return '<div style="background-color: ' . esc_attr($atts['color']) . '; width: ' . esc_attr($atts['width']) . '; height: ' . esc_attr($atts['height']) . '; margin: ' . esc_attr($atts['margin']) . ';"></div>';
}
add_shortcode('styled_separator', 'styled_separator_shortcode');

/**
 * Designer Button Shortcode - Links to the yprint designer tool
 * 
 * Usage: [designer_button label="Design" fallback_id="3657"]
 * 
 * @param array $atts Shortcode attributes
 * @return string The designer button HTML
 */
function yprint_designer_button_shortcode($atts) {
    // Default attributes
    $atts = shortcode_atts(array(
        'fallback_id' => '', // Fallback product ID
        'label' => 'Design' // Customizable button text
    ), $atts);
    
    // Get current product
    global $product;
    
    // Get product SKU (article number)
    $template_id = $product ? $product->get_sku() : $atts['fallback_id'];
    
    // If no SKU is available, use fallback ID
    if (empty($template_id)) {
        $template_id = $atts['fallback_id'];
    }
    
    // Fallback, if still no ID
    if (empty($template_id)) {
        $template_id = '3657'; // Default template ID
    }
    
    // Generate button HTML
    $button_html = sprintf(
        '<a href="%s" class="custom-designer-button" style="display: inline-block; background-color: #0079FF; color: white; padding: 5px 20px; text-decoration: none; border-radius: 15px; font-weight: bold; text-align: center; width: 100%%; box-sizing: border-box;">%s</a>',
        esc_url(add_query_arg('template_id', $template_id, 'https://yprint.de/designer')),
        esc_html($atts['label'])
    );
    
    return $button_html;
}
add_shortcode('designer_button', 'yprint_designer_button_shortcode');

/**
 * Add shortcode for CookieYes consent popup trigger button
 * 
 * Usage: [cookieyes_button text="Cookie Settings" class="my-class" style="color: red;"]
 * 
 * @param array $atts Shortcode attributes
 * @param string $content Shortcode content
 * @return string HTML output
 */
function yprint_cookieyes_button_shortcode($atts, $content = null) {
    // Parse attributes
    $attributes = shortcode_atts(array(
        'class' => '',
        'text'  => 'Cookie Settings',
        'style' => '',
    ), $atts);
    
    // Generate unique ID
    $unique_id = 'cky-btn-' . uniqid();
    
    // Combine classes - include cky-banner-element class for automatic CookieYes detection
    $classes = 'cky-banner-element ' . esc_attr($attributes['class']);
    
    // Build button style
    $style = '';
    if ($attributes['style']) {
        $style = ' style="' . esc_attr($attributes['style']) . '"';
    }
    
    // Build the button HTML
    $button = '<button id="' . esc_attr($unique_id) . '" class="' . esc_attr($classes) . '"' . $style . '>';
    $button .= esc_html($attributes['text']);
    $button .= '</button>';
    
    return $button;
}
add_shortcode('cookieyes_button', 'yprint_cookieyes_button_shortcode');

/**
 * Replace CookieYes icon with custom cookie bite SVG icon
 */
function replace_cookieyes_icon_with_cookie_bite() {
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        function checkAndReplaceIcon() {
            const revisitBtn = document.querySelector('[data-cky-tag="revisit-consent"] img');
            const buttonWrapper = document.querySelector('[data-cky-tag="revisit-consent"]');
            
            if (revisitBtn && buttonWrapper) {
                // Hintergrund transparent machen
                buttonWrapper.style.backgroundColor = 'transparent';
                buttonWrapper.style.border = 'none';
                buttonWrapper.style.boxShadow = 'none';
                
                // Das Bild-Element durch das Cookie-Bite-SVG ersetzen
                const cookieSvg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
                cookieSvg.setAttribute('width', '40');
                cookieSvg.setAttribute('height', '40');
                cookieSvg.setAttribute('viewBox', '0 0 512 512');
                cookieSvg.setAttribute('fill', '#1b7cff');
                
                // Exakter Pfad für das Cookie-Bite-Icon
                const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
                path.setAttribute('d', 'M257.5 27.6c-.8-5.4-4.9-9.8-10.3-10.6c-22.1-3.1-44.6 .9-64.4 11.4l-74 39.5C89.1 78.4 73.2 94.9 63.4 115L26.7 190.6c-9.8 20.1-13 42.9-9.1 64.9l14.5 82.8c3.9 22.1 14.6 42.3 30.7 57.9l60.3 58.4c16.1 15.6 36.6 25.6 58.7 28.7l83 11.7c22.1 3.1 44.6-.9 64.4-11.4l74-39.5c19.7-10.5 35.6-27 45.4-47.2l36.7-75.5c9.8-20.1 13-42.9 9.1-64.9c-.9-5.3-5.3-9.3-10.6-10.1c-51.5-8.2-92.8-47.1-104.5-97.4c-1.8-7.6-8-13.4-15.7-14.6c-54.6-8.7-97.7-52-106.2-106.8zM208 144a32 32 0 1 1 0 64 32 32 0 1 1 0-64zM144 336a32 32 0 1 1 64 0 32 32 0 1 1 -64 0zm224-64a32 32 0 1 1 0 64 32 32 0 1 1 0-64z');
                
                cookieSvg.appendChild(path);
                revisitBtn.parentNode.replaceChild(cookieSvg, revisitBtn);
                
                // Optional: Größe des Button-Wrappers anpassen
                buttonWrapper.style.padding = '8px';
                
                return true;
            }
            return false;
        }
        
        // Sofort versuchen, das Icon zu ersetzen
        if (!checkAndReplaceIcon()) {
            // Wenn nicht sofort möglich, MutationObserver verwenden
            const observer = new MutationObserver(function(mutations) {
                if (checkAndReplaceIcon()) {
                    // Icon wurde ersetzt, Observer kann beendet werden
                    observer.disconnect();
                }
            });
            
            // Gesamtes body-Element beobachten
            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
            
            // Sicherheitsabbruch nach 10 Sekunden
            setTimeout(function() {
                observer.disconnect();
            }, 10000);
        }
    });
    </script>
    <?php
}
add_action('wp_footer', 'replace_cookieyes_icon_with_cookie_bite', 999);

/**
 * Loading Animation for specified pages
 */
function yprint_loading_animation() {
    // Only show on products page
    if (!is_page('products')) {
        return;
    }
    
    ?>
    <div id="yprint-loading-overlay">
        <div id="yprint-loading-animation">
            <img src="https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg" alt="Loading...">
        </div>
    </div>

    <style>
    #yprint-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    #yprint-loading-animation {
        width: 200px;
        height: 200px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    #yprint-loading-animation img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        animation: yprint-glow 2s ease-in-out infinite alternate;
    }

    @keyframes yprint-glow {
        0% {
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.7));
        }
        100% {
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 1));
        }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hide animation after 2 seconds
        setTimeout(function() {
            var overlay = document.getElementById('yprint-loading-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }, 2000);
    });
    </script>
    <?php
}
add_action('wp_footer', 'yprint_loading_animation');

/**
 * Shortcode to display the current URL path with styling
 * 
 * Usage: [current_page]
 * 
 * @return string The formatted current page path
 */
function yprint_current_page_shortcode() {
    // Get the path after the domain
    $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    
    // Set default styling, can be customized via parameters if needed in the future
    $style = 'font-family: \'Roboto\', sans-serif; font-size: 40px; font-weight: 600;';
    
    // Return the formatted path
    return '<div style="' . esc_attr($style) . '">' . esc_html($current_path) . '</div>';
}
add_shortcode('current_page', 'yprint_current_page_shortcode');

/**
 * Mobile Navigation Toggle für verbesserte Mobile-Menu-Erfahrung
 * Ermöglicht bessere Benutzererfahrung durch richtiges Umschalten des Menü-Buttons
 */
function yprint_mobile_nav_toggle() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Warten bis die Seite vollständig geladen ist
        $(window).on('load', function() {
            // Diese Funktion überprüft, ob das Menü geöffnet ist
            function isMenuOpen() {
                return window.location.hash === '#mobile-navigation' ||
                       window.location.search.indexOf('nav_open=1') !== -1;
            }

            // Finde alle Navigations-Buttons (es könnten mehrere sein)
            var $navButtons = $('a[href="#mobile-navigation"], a[href*="nav_open=1"]');
            console.log('Gefundene Nav-Buttons:', $navButtons.length);

            // Wenn wir Buttons gefunden haben
            if ($navButtons.length > 0) {
                // Speichere die ursprüngliche Positionierung und andere Stile des Buttons
                $navButtons.each(function() {
                    $(this).data('original-position', $(this).css('position'));
                    $(this).data('original-zIndex', $(this).css('z-index'));
                    $(this).data('original-top', $(this).offset().top);
                    $(this).data('original-left', $(this).offset().left);
                    $(this).data('original-width', $(this).outerWidth());
                    $(this).data('original-height', $(this).outerHeight());
                });

                // Füge einen Event-Listener für Klicks hinzu
                $navButtons.on('click', function(e) {
                    var $clickedButton = $(this);

                    // Überprüfe, ob das Menü gerade geöffnet wird
                    var openingMenu = !isMenuOpen();

                    // Verzögere die Anpassung des Buttons, damit sie nach dem Overlay passiert
                    setTimeout(function() {
                        if (openingMenu) {
                            console.log('Menü wird geöffnet, setze Button auf fixed an der ursprünglichen Position');
                            $clickedButton.css({
                                'position': 'fixed',
                                'top': $clickedButton.data('original-top') + 'px',
                                'left': $clickedButton.data('original-left') + 'px',
                                'width': $clickedButton.data('original-width') + 'px', // Behalte ursprüngliche Breite
                                'height': $clickedButton.data('original-height') + 'px', // Behalte ursprüngliche Höhe
                                'z-index': 1000 // Stellen Sie sicher, dass dies höher als das Overlay ist
                            });
                        } else {
                            console.log('Menü wird geschlossen, setze Button auf ursprüngliche Position zurück');
                            $clickedButton.css({
                                'position': $clickedButton.data('original-position') || '',
                                'z-index': $clickedButton.data('original-zIndex') || '',
                                'top': $clickedButton.data('original-top') !== undefined ? $clickedButton.data('original-top') + 'px' : '',
                                'left': $clickedButton.data('original-left') !== undefined ? $clickedButton.data('original-left') + 'px' : '',
                                'width': $clickedButton.data('original-width') !== undefined ? $clickedButton.data('original-width') + 'px' : '',
                                'height': $clickedButton.data('original-height') !== undefined ? $clickedButton.data('original-height') + 'px' : ''
                            });
                        }
                    }, 100); // Kurze Verzögerung

                    // Standardmäßiges Öffnen/Schließen des Menüs beibehalten
                    if (isMenuOpen()) {
                        console.log('Menü ist offen, schließe es');
                        e.preventDefault();
                        e.stopPropagation();
                        var cleanUrl = window.location.protocol + '//' + window.location.host +
                                       window.location.pathname;
                        var searchParams = new URLSearchParams(window.location.search);
                        searchParams.delete('nav_open');
                        if (searchParams.toString()) {
                            cleanUrl += '?' + searchParams.toString();
                        }
                        window.location.href = cleanUrl;
                        return false;
                    }
                    return true;
                });

                // Funktion zum initialen Überprüfen und Anpassen der Button-Positionierung beim Seitenladen
                function initialButtonPosition() {
                    if (isMenuOpen()) {
                        $navButtons.each(function() {
                            $(this).css({
                                'position': 'fixed',
                                'top': $(this).data('original-top') + 'px',
                                'left': $(this).data('original-left') + 'px',
                                'width': $(this).data('original-width') + 'px',
                                'height': $(this).data('original-height') + 'px',
                                'z-index': 1000
                            });
                        });
                    }
                }

                // Führe die initiale Überprüfung nach dem Laden aus
                $(initialButtonPosition);
            }
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'yprint_mobile_nav_toggle', 999);

/**
 * Shortcode für einen Toggle-Button mit Popup-Funktionalität
 * 
 * Usage: [toggle_button_popup]
 * 
 * @return string Der HTML-Code für den Toggle-Button
 */
function toggle_button_with_popup_shortcode() {
    ob_start();
    ?>
    <!-- Font Awesome wird nur geladen, wenn es nicht bereits im Theme enthalten ist -->
    <?php if (!wp_style_is('font-awesome', 'enqueued')) : ?>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <?php endif; ?>
    
    <a href="#footer_popup" class="footer-button" data-popup-trigger="#footer_popup">
        <i class="fas fa-angle-up"></i>
    </a>
    
    <style>
        /* Stile für den Button */
        .footer-button {
            position: relative;
            background-color: transparent !important; 
            border: none !important; 
            padding: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            outline: none; 
            text-decoration: none; /* Entfernt Unterstreichung für den Link */
        }
        .footer-button:focus {
            outline: none; 
        }
        .footer-button i {
            font-size: 24px;
            color: #0079FF !important; 
            transition: transform 0.3s ease, color 0.3s ease;
        }
        .footer-button.active i {
            color: #0079FF !important;
        }
        .footer-button.active {
            transform: translateY(-80px); 
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const button = document.querySelector('.footer-button');
            if (button) {
                button.addEventListener('click', function(e) {
                    const icon = this.querySelector('i');

                    // Toggle position and icon
                    this.classList.toggle('active');
                    if (icon.classList.contains('fa-angle-up')) {
                        icon.classList.remove('fa-angle-up');
                        icon.classList.add('fa-angle-down');
                    } else {
                        icon.classList.remove('fa-angle-down');
                        icon.classList.add('fa-angle-up');
                    }

                    // Verhindere den Standard-Click auf den Link, da der Popup-Trigger bereits durch das 'href' getriggert wird
                    e.preventDefault();
                });
            }
        });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('toggle_button_popup', 'toggle_button_with_popup_shortcode');



add_action('wp_footer', function() {
    if (wp_is_mobile() && !is_front_page() && !is_page('register') && !is_page('designer') && !is_page('login') && !is_page('impressum') && !is_page('datenschutz') && !is_page('rechtlicher-hinweis') && !is_page('gesetz-ueber-digitale-dienste') && !is_page('produktsicherheitsverordnung') && !is_page('recover-account') && !is_page('password-reset-success') && !is_page('verify-email')) {
        ?>
        <div id="mobile-bottom-nav" class="mobile-nav-wrapper">
            <?php echo do_shortcode('[elementor-template id="4626"]'); ?>
        </div>

        <style>
        #mobile-bottom-nav.mobile-nav-wrapper {
            position: fixed;
            bottom: 0;
            left: 0;
            width: 100vw;
            max-width: 100%;
            background: #fff;
            z-index: 9999;
            margin: 0;
            padding: 0;
            display: none;
            box-shadow: 0px -2px 5px rgba(0, 0, 0, 0.1);
        }

        body.mobile-bottom-padding {
            padding-bottom: 60px; /* Passe an die tatsächliche Höhe deines Navigationsmenüs an */
        }

        @media (max-width: 768px) {
            #mobile-bottom-nav.mobile-nav-wrapper {
                display: block;
            }
        }
        </style>

        <script>
        (function() {
            const nav = document.getElementById('mobile-bottom-nav');
            if (!nav) return;

            const applyPaddingBottom = () => {
                const height = nav.offsetHeight;
                document.body.classList.add('mobile-bottom-padding');
                document.body.style.paddingBottom = height + 'px';
            };

            window.addEventListener('resize', applyPaddingBottom);
            window.addEventListener('orientationchange', applyPaddingBottom);
            document.addEventListener('DOMContentLoaded', applyPaddingBottom);
        })();
        </script>
        <?php
    }
});

add_action('wp_footer', function() {
    if (wp_is_mobile() && !is_front_page() && !is_page('register') && !is_page('designer') && !is_page('login') && !is_page('impressum') && !is_page('datenschutz') && !is_page('rechtlicher-hinweis') && !is_page('gesetz-ueber-digitale-dienste') && !is_page('produktsicherheitsverordnung') && !is_page('recover-account') && !is_page('password-reset-success') && !is_page('verify-email')) {
        ?>
        <div id="mobile-top-bar" class="mobile-top-wrapper">
            <?php echo do_shortcode('[elementor-template id="2575"]'); ?>
        </div>

        <style>
        #mobile-top-bar.mobile-top-wrapper {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            max-width: 100%;
            z-index: 9999;
            background: #fff;
            margin: 0;
            padding: 0;
            display: none;
            box-shadow: 0px 2px 5px rgba(0, 0, 0, 0.1);
        }

        body.mobile-top-padding {
            padding-top: 60px; /* Passe an die *initiale* geschätzte Höhe deines Headers an */
        }

        @media (max-width: 768px) {
            #mobile-top-bar.mobile-top-wrapper {
                display: block;
            }
        }
        </style>

        <script>
        (function() {
            const topBar = document.getElementById('mobile-top-bar');
            if (!topBar) return;

            const applyPaddingTop = () => {
                const height = topBar.offsetHeight;
                document.body.classList.add('mobile-top-padding');
                document.body.style.paddingTop = height + 'px';
            };

            window.addEventListener('resize', applyPaddingTop);
            window.addEventListener('orientationchange', applyPaddingTop);
            document.addEventListener('DOMContentLoaded', applyPaddingTop);
        })();
        </script>
        <?php
    }
});

function my_designtool_section_shortcode($atts) {
    $atts = shortcode_atts(array(
        'template_id' => '3657',
    ), $atts);

    ob_start();
    ?>
    <section id="design-tool-section" class="py-16 md:py-24 pt-[150px]">
        <div class="container mx-auto px-6 text-center">
            <h2 class="text-3xl md:text-4xl font-semibold text-yprint-dark-gray mb-4 fade-in fade-in-up !leading-tight tracking-tight">
                Teste unser Designtool
            </h2>
            <p class="text-lg text-yprint-medium-gray max-w-2xl mx-auto mb-10 fade-in fade-in-up" style="animation-delay: 0.1s;">
                Hier kannst du dein eigenes Design hochladen und direkt am Produkt ausprobieren.
            </p>

            <!-- Zentriert ohne Flex: Shortcode sauber mittig platzieren -->
            <div style="display: block; text-align: center;">
                <div style="display: inline-block; max-width: 100%;">
                    <?php echo do_shortcode('[ops-designer template_id="' . esc_attr($atts['template_id']) . '"]'); ?>
                </div>
            </div>

        </div>
    </section>
    <?php
    return ob_get_clean();
}
add_shortcode('my_designtool_section', 'my_designtool_section_shortcode');

