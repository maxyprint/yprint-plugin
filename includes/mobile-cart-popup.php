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
    <div id="mobile-cart-popup" class="mobile-cart-popup" role="dialog" aria-modal="true" aria-hidden="true" tabindex="-1">
        <div class="cart-container">
            <button class="mobile-cart-back-button" type="button" aria-label="Warenkorb schließen">
                ←
            </button>
            <div class="cart-content">
                <?php
                // Sicherstellen, dass die Funktion existiert, bevor sie aufgerufen wird
                if (function_exists('yprint_minimalist_cart_shortcode')) {
                    echo yprint_minimalist_cart_shortcode();
                } else {
                    echo '<p>Warenkorb-Inhalt konnte nicht geladen werden. Bitte stellen Sie sicher, dass `yprint_minimalist_cart_shortcode` verfügbar ist.</p>';
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
            display: block; /* Wichtig, um das Element sichtbar zu machen */
            opacity: 1;
            transform: translateX(0);
        }

        #mobile-cart-popup > .cart-container { /* Gezielter auf .cart-container statt direktes div */
            background-color: #fff;
            color: #1d1d1f;
            padding: 20px;
            width: 300px; /* Beispielbreite für den Warenkorb */
            max-width: 90%; /* Responsiver auf kleineren Bildschirmen */
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
        }

        .mobile-cart-back-button:hover {
            color: #0079FF;
        }

        /* Adjust cart content for back button */
        #mobile-cart-popup .cart-content {
            padding-top: 50px; /* Platz für den Zurück-Button */
        }

        body.cart-popup-open {
            overflow: hidden; /* Verhindert Body-Scroll, wenn Warenkorb offen ist */
            position: fixed; /* Bessere Unterstützung für iOS Safari */
            width: 100%;
        }

        /* Media Queries für Responsivität (Beispiel) */
        @media (max-width: 768px) {
            #mobile-cart-popup > .cart-container {
                width: 100%; /* Auf kleineren Bildschirmen volle Breite */
            }
        }
    </style>
    <?php
}
add_action('wp_head', 'yprint_add_mobile_cart_popup_css');

/**
 * Fügt das notwendige JavaScript für die Warenkorb-Popup-Funktionalität in den Footer ein.
 */
function yprint_add_mobile_cart_popup_js() {
    // Stellen Sie sicher, dass jQuery geladen ist
    if (!wp_script_is('jquery', 'enqueued')) {
        wp_enqueue_script('jquery');
    }
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var $cartPopup = $('#mobile-cart-popup');
            // Trigger-Elemente sollten am besten eine gemeinsame Klasse haben, z.B. 'yprint-cart-trigger'
            var $cartTriggerLinks = $('a[href="#mobile-cart"], button[data-target="#mobile-cart"], .yprint-cart-trigger');

            // Funktion zum Öffnen des Warenkorb-Popups
            function openCartPopup() {
                if ($cartPopup.hasClass('open')) {
                    // Verhindere doppeltes Öffnen, wenn es bereits offen ist
                    return;
                }
                $cartPopup.addClass('open').attr('aria-hidden', 'false');
                $('body').addClass('cart-popup-open');
                $cartTriggerLinks.attr('aria-expanded', 'true');
                // Optional: Fokus auf den Schließen-Button für Barrierefreiheit
                $cartPopup.find('.mobile-cart-back-button').focus();
                console.log('Cart popup opened');
            }

            // Funktion zum Schließen des Warenkorb-Popups
            function closeCartPopup() {
                if (!$cartPopup.hasClass('open')) {
                    // Verhindere doppeltes Schließen, wenn es bereits geschlossen ist
                    return;
                }
                $cartPopup.removeClass('open').attr('aria-hidden', 'true');
                $('body').removeClass('cart-popup-open');
                $cartTriggerLinks.attr('aria-expanded', 'false');
                // Optional: Fokus zurück zum Element, das das Popup geöffnet hat
                // (Dies erfordert komplexeres Speichern des letzten fokussierten Elements)
                console.log('Cart popup closed');
            }

            // Event-Listener für Klicks auf Trigger-Elemente
            $cartTriggerLinks.on('click', function(e) {
                e.preventDefault();
                // Wechsle den Zustand des Popups
                if ($cartPopup.hasClass('open')) {
                    closeCartPopup();
                } else {
                    openCartPopup();
                }
            });

            // Back Button Event-Listener
            $cartPopup.on('click', '.mobile-cart-back-button', function(e) {
                e.preventDefault();
                e.stopPropagation(); // Verhindert, dass das Klick-Event auf das Overlay durchgeht
                closeCartPopup();
            });

            // Schließen des Popups beim Klicken außerhalb des Warenkorbs (auf das Overlay)
            $cartPopup.on('click', function(event) {
                // Überprüfe, ob das geklickte Element direkt das Popup-Overlay ist und nicht dessen Kinder
                if ($(event.target).is(this)) {
                    closeCartPopup();
                }
            });

            // Schließen des Popups mit der Escape-Taste
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

            // Aktualisiere den Warenkorb, wenn ein Produkt hinzugefügt wurde (WooCommerce AJAX-Event)
            // Dies ist ein Standard WooCommerce Event
            $(document.body).on('added_to_cart', function(event, fragments, cart_hash, button) {
                console.log('WooCommerce: Product added to cart - opening cart popup');
                // Der Shortcode-Inhalt sollte sich bei WooCommerce AJAX-Updates automatisch aktualisieren.
                // Wenn nicht, müsste hier ein AJAX-Aufruf zum Neuladen des Shortcodes erfolgen.
                openCartPopup();
            });

            // Event-Listener für YPrint Custom Cart Refresh Events
            // Wenn der Inhalt des minimalistischen Warenkorbs separat aktualisiert wird
            $(document.body).on('yprint_mini_cart_refreshed yprint_cart_updated', function() {
                console.log('YPrint custom cart content refreshed/updated.');
                // Hier könnten Sie eine Lade-Anzeige ausblenden oder eine Animation abspielen
                // Der Shortcode-Inhalt sollte sich selbst aktualisieren.
            });

            // Custom Event-Listener für YPrint Cart Open Events
            $(document).on('yprint:open-cart-popup', function(e) {
                console.log('YPrint cart open event received');
                openCartPopup();
            });

            // Globale Funktion für externes Warenkorb-Öffnen
            window.openYPrintCart = function() {
                openCartPopup();
            };

            // Legacy-Unterstützung für verschiedene Warenkorb-Öffnungsmethoden
            // Dieser Block ist potenziell problematisch, da er andere Popup-Logiken triggert.
            // Überprüfen Sie, ob dieser Teil wirklich notwendig ist oder zu Konflikten führt.
            $(document).on('open-cart-popup', function(e) {
                console.log('Legacy cart open event received. Attempting to open YPrint cart.');
                // Versuche, das YPrint mobile Warenkorb-Popup zu öffnen
                if (typeof window.openYPrintCart === 'function') {
                    window.openYPrintCart();
                } else {
                    jQuery(document).trigger('yprint:open-cart-popup');
                }

                // Dieser Teil scheint sich auf ein anderes Popup oder eine andere Logik zu beziehen.
                // Er sollte entfernt oder angepasst werden, wenn er nicht direkt für das
                // #mobile-cart-popup zuständig ist, um Konflikte zu vermeiden.
                // const cartPopup = document.querySelector('#cart');
                // if (cartPopup) {
                //     cartPopup.style.display = 'block';
                //     cartPopup.classList.add('show');
                //     if (typeof jQuery !== 'undefined') {
                //         jQuery('#cart').fadeIn();
                //     }
                // }

                // document.dispatchEvent(new CustomEvent('YOUR_EVENT_CONFIG_CART_OPEN', {
                //     detail: { productId: e.detail ? e.detail.productId : null, action: 'buy_blank' }
                // }));
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

    // Füge die gemeinsame Klasse hinzu, damit der JS-Selector funktioniert
    $class_attr = !empty( $atts['class'] ) ? ' class="yprint-cart-trigger ' . sanitize_html_class( $atts['class'] ) . '"' : ' class="yprint-cart-trigger"';

    return '<a href="#mobile-cart"' . $class_attr . ' role="button" aria-haspopup="dialog" aria-expanded="false">' . esc_html( $atts['text'] ) . '</a>';
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