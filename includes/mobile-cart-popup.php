<?php
/**
 * Generiert ein Popup-Menü mit einem Warenkorb, der von rechts einfährt.
 * Die Funktionalität und Darstellung werden von dem vorhandenen Code übernommen.
 * Das Popup wird über einen Button oder ein anderes Element getriggert.
 */

// Verhindere direkten Zugriff
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Lädt die Warenkorb-Funktionen aus der separaten Datei
 */
function yprint_load_cart_functions() {
    $cart_file = get_template_directory() . '/includes/minimalist_cart.php';
    
    // Prüfe, ob die Datei existiert und lade sie
    if (file_exists($cart_file)) {
        require_once $cart_file;
    } else {
        // Fallback: Versuche andere mögliche Pfade
        $fallback_paths = array(
            ABSPATH . 'wp-content/themes/' . get_template() . '/includes/minimalist_cart.php',
            ABSPATH . 'wp-content/themes/' . get_stylesheet() . '/includes/minimalist_cart.php',
            plugin_dir_path(__FILE__) . 'includes/minimalist_cart.php',
            dirname(__FILE__) . '/minimalist_cart.php'
        );
        
        foreach ($fallback_paths as $path) {
            if (file_exists($path)) {
                require_once $path;
                break;
            }
        }
    }
}

/**
 * Initialisiert die Warenkorb-Funktionen beim WordPress Init
 */
add_action('init', 'yprint_load_cart_functions', 5);

/**
 * Fügt das HTML-Struktur für das Warenkorb-Popup in den Footer ein.
 */
function yprint_cart_popup_html() {
    // Stelle sicher, dass die Warenkorb-Funktionen geladen sind
    if (!function_exists('yprint_minimalist_cart_shortcode')) {
        yprint_load_cart_functions();
    }
    
    ?>
    <div id="mobile-cart-popup" class="mobile-cart-popup">
        <div class="cart-container">
            <button class="mobile-cart-back-button" type="button" aria-label="Warenkorb schließen">
                ←
            </button>
            <div class="cart-content">
                <?php 
                // Prüfe, ob die Funktion existiert, bevor sie aufgerufen wird
                if (function_exists('yprint_minimalist_cart_shortcode')) {
                    echo yprint_minimalist_cart_shortcode();
                } else {
                    // Fallback: Verwende den WordPress Shortcode
                    echo do_shortcode('[yprint_minimalist_cart]');
                }
                ?>
            </div>
        </div>
    </div>
    <?php
}
add_action('wp_footer', 'yprint_cart_popup_html', 100);

/**
 * Fügt die notwendigen CSS-Stile für das Warenkorb-Popup in den Header ein.
 */
function yprint_add_mobile_cart_popup_css() {
    ?>
    <style type="text/css">
        #mobile-cart-popup {
            display: none;
            position: fixed;
            top: 0;
            right: 0;
            width: 100%;
            height: 100%;
            z-index: 10001; /* Sollte über dem Menü-Popup liegen */
            background-color: rgba(0, 0, 0, 0.5);
            overflow: hidden;
            transition: opacity 0.3s ease-in-out, transform 0.3s ease-in-out;
            opacity: 0;
            transform: translateX(100%); /* Standardmäßig außerhalb des Bildschirms rechts */
        }

        #mobile-cart-popup.open {
            display: block;
            opacity: 1;
            transform: translateX(0);
        }

        #mobile-cart-popup .cart-container {
            background-color: #fff;
            color: #1d1d1f;
            padding: 20px;
            width: 350px; /* Etwas breiter für bessere Darstellung */
            max-width: 90vw; /* Responsive Breite */
            height: 100%;
            position: absolute;
            top: 0;
            right: 0;
            box-shadow: -2px 0px 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            box-sizing: border-box;
        }

        /* Back Button Styles */
        .mobile-cart-back-button {
            position: absolute;
            top: 15px;
            left: 15px;
            background: none;
            border: none;
            font-size: 24px;
            color: #1d1d1f;
            cursor: pointer;
            padding: 5px;
            line-height: 1;
            z-index: 10;
            transition: color 0.2s ease;
            border-radius: 4px;
        }

        .mobile-cart-back-button:hover,
        .mobile-cart-back-button:focus {
            color: #0079FF;
            background-color: rgba(0, 121, 255, 0.1);
        }

        /* Adjust cart content for back button */
        #mobile-cart-popup .cart-content {
            padding-top: 60px; /* Mehr Platz für den Back-Button */
        }

        /* Responsive Anpassungen */
        @media (max-width: 480px) {
            #mobile-cart-popup .cart-container {
                width: 100%;
                max-width: 100vw;
            }
            
            #mobile-cart-popup .cart-content {
                padding-top: 50px;
            }
        }

        body.cart-popup-open {
            overflow: hidden; /* Verhindert Body-Scroll, wenn Warenkorb offen ist */
        }

        /* Spezielle Styles für den Warenkorb im Popup */
        #mobile-cart-popup .yprint-mini-cart {
            background-color: transparent;
            padding: 0;
            box-shadow: none;
            border-radius: 0;
        }

        #mobile-cart-popup .yprint-mini-cart-header {
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
    </style>
    <?php
}
add_action('wp_head', 'yprint_add_mobile_cart_popup_css');

/**
 * Fügt das notwendige JavaScript für die Warenkorb-Popup-Funktionalität in den Footer ein.
 */
function yprint_add_mobile_cart_popup_js() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var $cartPopup = $('#mobile-cart-popup');
            var $cartTriggerLinks = $('a[href="#mobile-cart"], button[data-target="#mobile-cart"], .cart-trigger');

            // Funktion zum Öffnen des Warenkorb-Popups
            function openCartPopup() {
                $cartPopup.addClass('open');
                $('body').addClass('cart-popup-open');
                $cartTriggerLinks.attr('aria-expanded', 'true');
                $cartPopup.attr('aria-hidden', 'false');
                console.log('Cart popup opened');

                // Trigger refresh des Warenkorb-Inhalts beim Öffnen
                if (typeof window.refreshCartContent === 'function') {
                    window.refreshCartContent();
                }
                
                // Custom event für andere Scripte
                $(document).trigger('yprint:cart-popup-opened');
            }

            // Funktion zum Schließen des Warenkorb-Popups
            function closeCartPopup() {
                $cartPopup.removeClass('open');
                $('body').removeClass('cart-popup-open');
                $cartTriggerLinks.attr('aria-expanded', 'false');
                $cartPopup.attr('aria-hidden', 'true');
                console.log('Cart popup closed');
                
                // Custom event für andere Scripte
                $(document).trigger('yprint:cart-popup-closed');
            }

            // Event-Listener für Klicks auf Trigger-Elemente
            $(document).on('click', 'a[href="#mobile-cart"], button[data-target="#mobile-cart"], .cart-trigger', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if ($cartPopup.hasClass('open')) {
                    closeCartPopup();
                } else {
                    openCartPopup();
                }
            });

            // Back Button Event-Listener
            $cartPopup.on('click', '.mobile-cart-back-button', function(e) {
                e.preventDefault();
                e.stopPropagation();
                closeCartPopup();
            });

            // Schließen des Popups beim Klicken außerhalb des Warenkorbs (auf das Overlay)
            $cartPopup.on('click', function(event) {
                if ($(event.target).is(this)) {
                    closeCartPopup();
                }
            });

            // Optional: Schließen des Popups mit der Escape-Taste
            $(document).on('keydown', function(event) {
                if (event.key === 'Escape' && $cartPopup.hasClass('open')) {
                    closeCartPopup();
                }
            });

            // Überprüfe beim Laden der Seite, ob '#mobile-cart' im Hash ist
            if (window.location.hash === '#mobile-cart') {
                openCartPopup();
            }

            // Überwache Änderungen des Hash-Werts in der URL
            $(window).on('hashchange', function() {
                if (window.location.hash === '#mobile-cart') {
                    openCartPopup();
                } else if ($cartPopup.hasClass('open')) {
                    closeCartPopup();
                }
            });

            // Event-Listener für automatisches Öffnen des Carts nach dem Hinzufügen eines Produkts
            $(document.body).on('added_to_cart', function(event, fragments, cart_hash, button) {
                console.log('Product added to cart - opening cart popup');
                // Kleiner Delay, damit der Warenkorb Zeit hat sich zu aktualisieren
                setTimeout(function() {
                    openCartPopup();
                }, 300);
            });

            // Aktualisiere den Warenkorb, wenn ein Produkt hinzugefügt wurde
            $(document.body).on('yprint_mini_cart_refreshed yprint_cart_updated', function() {
                if ($cartPopup.hasClass('open')) {
                    console.log('Cart popup content updated');
                }
            });

            // Custom Event-Listener für YPrint Cart Open Events
            $(document).on('yprint:open-cart-popup', function(e) {
                console.log('YPrint cart open event received');
                openCartPopup();
            });

            // Global function for external cart opening
            window.openYPrintCart = function() {
                openCartPopup();
            };

            // Global function for external cart closing
            window.closeYPrintCart = function() {
                closeCartPopup();
            };

            // Legacy support for various cart opening methods
            $(document).on('open-cart-popup', function(e) {
                console.log('Legacy cart open event received');
                openCartPopup();
            });

            // Support für Design Tool Integration
            $(document).on('design-tool:add-to-cart-success', function(e, data) {
                console.log('Design tool cart success - opening popup');
                setTimeout(function() {
                    openCartPopup();
                }, 500);
            });

            // Fallback für andere Cart-Events
            $(document).on('wc_fragment_refresh', function() {
                if ($cartPopup.hasClass('open')) {
                    // Warenkorb ist offen, aktualisiere Inhalt
                    setTimeout(function() {
                        $(document).trigger('yprint:refresh-cart-content');
                    }, 100);
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'yprint_add_mobile_cart_popup_js', 99);

/**
 * Shortcode für einen Button zum Triggern des Warenkorb-Popups.
 *
 * Usage: [mobile_cart_button text="Warenkorb öffnen" class="custom-button-class"]
 *
 * @param array $atts Die Attribute des Shortcodes.
 * @return string Der HTML-Code für den Button.
 */
function mobile_cart_button_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'text'  => 'Warenkorb öffnen',
        'class' => 'cart-trigger',
        'icon'  => true,
    ), $atts, 'mobile_cart_button' );

    $classes = array('cart-trigger');
    if (!empty($atts['class'])) {
        $classes[] = sanitize_html_class($atts['class']);
    }
    
    $class_attr = ' class="' . implode(' ', $classes) . '"';
    
    $icon_html = '';
    if ($atts['icon'] && $atts['icon'] !== 'false') {
        $icon_html = '<span class="cart-icon">🛒</span> ';
    }

    return '<button type="button" href="#mobile-cart"' . $class_attr . ' data-target="#mobile-cart">' . $icon_html . esc_html( $atts['text'] ) . '</button>';
}
add_shortcode( 'mobile_cart_button', 'mobile_cart_button_shortcode' );

/**
 * Shortcode für einen Link zum Triggern des Warenkorb-Popups.
 *
 * Usage: [mobile_cart_link text="Warenkorb" class="custom-link-class"]
 */
function mobile_cart_link_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'text'  => 'Warenkorb',
        'class' => 'cart-trigger',
        'icon'  => true,
    ), $atts, 'mobile_cart_link' );

    $classes = array('cart-trigger');
    if (!empty($atts['class'])) {
        $classes[] = sanitize_html_class($atts['class']);
    }
    
    $class_attr = ' class="' . implode(' ', $classes) . '"';
    
    $icon_html = '';
    if ($atts['icon'] && $atts['icon'] !== 'false') {
        $icon_html = '<span class="cart-icon">🛒</span> ';
    }

    return '<a href="#mobile-cart"' . $class_attr . '>' . $icon_html . esc_html( $atts['text'] ) . '</a>';
}
add_shortcode( 'mobile_cart_link', 'mobile_cart_link_shortcode' );

/**
 * Hilfsfunktion zur Überprüfung, ob die Warenkorb-Funktionen verfügbar sind
 */
function yprint_cart_functions_available() {
    return function_exists('yprint_minimalist_cart_shortcode');
}

/**
 * Debug-Funktion für die Entwicklung
 */
function yprint_cart_popup_debug() {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('YPrint Cart Popup Debug: Cart functions available: ' . (yprint_cart_functions_available() ? 'Yes' : 'No'));
    }
}
add_action('wp_footer', 'yprint_cart_popup_debug');

// Usage-Anweisungen:
// 
// 1. Button Shortcode:
//    [mobile_cart_button text="Warenkorb öffnen" class="mein-button-stil"]
//
// 2. Link Shortcode:
//    [mobile_cart_link text="Warenkorb" class="mein-link-stil"]
//
// 3. Manuell im Theme:
//    <button class="cart-trigger" data-target="#mobile-cart">Warenkorb</button>
//    <a href="#mobile-cart" class="cart-trigger">Warenkorb</a>
//
// 4. JavaScript:
//    window.openYPrintCart(); // Öffnet den Warenkorb
//    window.closeYPrintCart(); // Schließt den Warenkorb
//
// 5. jQuery Events:
//    $(document).trigger('yprint:open-cart-popup'); // Öffnet den Warenkorb
//
// Das Popup wird automatisch nach dem Hinzufügen von Produkten zum Warenkorb geöffnet.
?>