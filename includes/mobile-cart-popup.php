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
 * Fügt das HTML-Struktur für das Warenkorb-Popup in den Footer ein.
 */
function yprint_cart_popup_html() {
    ?>
    <div id="mobile-cart-popup" class="mobile-cart-popup">
        <?php echo yprint_minimalist_cart_shortcode(); ?>
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

        #mobile-cart-popup > div { /* Direktes div-Kind (der Warenkorb) */
            background-color: #fff;
            color: #1d1d1f;
            padding: 20px;
            width: 300px; /* Beispielbreite für den Warenkorb */
            height: 100%;
            position: absolute;
            top: 0;
            right: 0;
            box-shadow: -2px 0px 5px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
            box-sizing: border-box;
        }

        body.cart-popup-open {
            overflow: hidden; /* Verhindert Body-Scroll, wenn Warenkorb offen ist */
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
            var $cartTriggerLinks = $('a[href="#mobile-cart"], button[data-target="#mobile-cart"]');

            // Funktion zum Öffnen des Warenkorb-Popups
            function openCartPopup() {
                $cartPopup.addClass('open');
                $('body').addClass('cart-popup-open');
                $cartTriggerLinks.attr('aria-expanded', 'true');
                $cartPopup.attr('aria-hidden', 'false');
            }

            // Funktion zum Schließen des Warenkorb-Popups
            function closeCartPopup() {
                $cartPopup.removeClass('open');
                $('body').removeClass('cart-popup-open');
                $cartTriggerLinks.attr('aria-expanded', 'false');
                $cartPopup.attr('aria-hidden', 'true');
            }

            // Event-Listener für Klicks auf Trigger-Elemente
            $cartTriggerLinks.on('click', function(e) {
                e.preventDefault();
                $cartPopup.toggleClass('open');
                $('body').toggleClass('cart-popup-open');
                var isExpanded = $cartPopup.hasClass('open');
                $(this).attr('aria-expanded', isExpanded);
                $cartPopup.attr('aria-hidden', !isExpanded);
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

            // Aktualisiere den Warenkorb, wenn ein Produkt hinzugefügt wurde (AJAX-Event aus minimalistischem Warenkorb)
            $(document.body).on('yprint_mini_cart_refreshed yprint_cart_updated', function() {
                // Wenn das Cart-Popup geöffnet ist, aktualisiere es (der Inhalt wird ja bereits über den Shortcode geladen/aktualisiert)
                if ($cartPopup.hasClass('open')) {
                    // Optional: Hier könnten Sie eine visuelle Bestätigung einfügen
                    console.log('Cart popup content potentially updated.');
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'yprint_add_mobile_cart_popup_js');

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
        'class' => '',
    ), $atts, 'mobile_cart_button' );

    $class_attr = !empty( $atts['class'] ) ? ' class="' . sanitize_html_class( $atts['class'] ) . '"' : '';

    return '<a href="#mobile-cart"' . $class_attr . '>' . esc_html( $atts['text'] ) . '</a>';
}
add_shortcode( 'mobile_cart_button', 'mobile_cart_button_shortcode' );

// Um den Trigger-Button an einer bestimmten Stelle im Theme anzuzeigen,
// verwenden Sie den folgenden Shortcode im WordPress-Editor:
// [mobile_cart_button text="Warenkorb öffnen" class="mein-stil"]

// Das Popup-HTML und die CSS-Stile werden automatisch im Footer bzw. Header eingefügt.
// Das JavaScript für die Funktionalität wird ebenfalls automatisch im Footer eingefügt.

// Die Funktionen für den Warenkorb-Inhalt (yprint_minimalist_cart_shortcode)
// und die AJAX-Funktionalität (yprint_update_cart_quantity, yprint_remove_from_cart, etc.)
// sind bereits im vorherigen Code definiert und werden hier wiederverwendet.
?>