<?php
/**
 * Generiert ein Popup-Menü mit der YPrint Corporate Design Struktur.
 * Das Popup wird über einen Button mit dem Link '#mobile-menu' ausgelöst.
 */
function yprint_mobile_menu_popup() {
    ?>
    <div id="mobile-menu-popup" class="mobile-menu-popup">
        <aside class="sidebar">
            <div class="user-info">
                <h2 class="username">Ethan Carter</h2>
            </div>
            <nav class="main-menu">
                <ul>
                    <li class="menu-item active">
                        <a href="#">Account</a>
                    </li>
                    <li class="menu-item">
                        <a href="#">Designs</a>
                    </li>
                    <li class="menu-item">
                        <a href="#">Orders</a>
                    </li>
                    <li class="menu-item">
                        <a href="#">Creating Designs</a>
                    </li>
                    <li class="menu-item">
                        <a href="#">Support</a>
                    </li>
                    <li class="menu-item">
                        <a href="#">Settings</a>
                    </li>
                    <li class="menu-item">
                        <a href="#">Language</a>
                    </li>
                    <li class="menu-item logout">
                        <a href="#">Logout</a>
                    </li>
                </ul>
            </nav>
            <div class="footer">
                <p class="version">Version 1.0.0</p>
                <p class="copyright">&copy; Dein Copyright</p>
            </div>
        </aside>
    </div>
    <?php
}
add_action('wp_footer', 'yprint_mobile_menu_popup_html', 99);

/**
 * Fügt das Popup-Menü HTML in den Footer ein.
 */
function yprint_mobile_menu_popup_html() {
    yprint_mobile_menu_popup();
}

/**
 * Fügt die notwendigen CSS-Stile in den Header ein (angepasst für YPrint Design).
 */
function yprint_add_mobile_menu_popup_css() {
    ?>
    <style type="text/css">
        /* Grundlegendes Styling für das Popup */
        #mobile-menu-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            background-color: rgba(0, 0, 0, 0.5); /* Dunkler Overlay */
            overflow: hidden;
            transition: opacity 0.2s ease-in-out; /* Sanftere Transition */
            opacity: 0;
            cursor: pointer;
        }

        #mobile-menu-popup.open {
            display: block;
            opacity: 1;
        }

        #mobile-menu-popup .sidebar {
            background-color: #1d1d1f; /* Dunkelgrau für Hintergrund */
            color: #FFFFFF; /* Weiß für Text */
            padding: 20px 15px; /* Angepasstes Padding */
            width: 250px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start;
            position: absolute;
            left: 0;
            top: 0;
            transform: translateX(-100%);
            transition: transform 0.2s ease-in-out; /* Sanftere Transition */
            cursor: auto;
        }

        #mobile-menu-popup.open .sidebar {
            transform: translateX(0);
        }

        /* Styling für die Sidebar-Inhalte */
        .user-info {
            margin-bottom: 30px; /* Größerer Abstand */
        }

        .username {
            font-size: 24px; /* H2 Größe */
            font-weight: 600; /* H2 Gewicht */
            color: #FFFFFF;
            margin-bottom: 10px;
        }

        .main-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
            width: 100%;
        }

        .menu-item {
            margin: 15px 0; /* Angepasster Abstand */
            font-weight: 400; /* Normales Gewicht */
            cursor: pointer;
            width: 100%;
        }

        .menu-item a {
            display: block;
            color: #FFFFFF;
            text-decoration: none;
            padding: 12px 15px; /* Button-ähnliches Padding */
            border-radius: 10px; /* Standard Border-Radius */
        }

        .menu-item a:hover {
            background-color: #F6F7FA; /* Hellgrauer Hover-Effekt */
            color: #1d1d1f;
        }

        .menu-item.active a {
            background-color: #0079FF; /* YPrint Blau für aktiv */
            color: #FFFFFF;
            font-weight: 600; /* Fett für aktiv */
        }

        .footer {
            font-size: 13px; /* Kleinerer Text */
            color: #6e6e73; /* Sekundärer Textfarbe */
            padding-top: 30px; /* Größerer Abstand */
            width: 100%;
        }

        .footer p {
            margin: 5px 0;
        }

        .menu-item.logout a {
            color: #FFFFFF; /* Rot für Logout */
        }

        body.menu-open {
            overflow: hidden;
        }
    </style>
    <?php
}
add_action('wp_head', 'yprint_add_mobile_menu_popup_css');

/**
 * Fügt das notwendige JavaScript in den Footer ein.
 */
function yprint_add_mobile_menu_popup_js() {
    ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            var $menuPopup = $('#mobile-menu-popup');
            var $menuTriggerLinks = $('a[href="#mobile-menu"], button[data-target="#mobile-menu"]');

            // Funktion zum Öffnen des Menüs
            function openMenu() {
                $menuPopup.addClass('open');
                $('body').addClass('menu-open');
                $menuTriggerLinks.attr('aria-expanded', 'true');
                $menuPopup.attr('aria-hidden', 'false');
            }

            // Funktion zum Schließen des Menüs
            function closeMenu() {
                $menuPopup.removeClass('open');
                $('body').removeClass('menu-open');
                $menuTriggerLinks.attr('aria-expanded', 'false');
                $menuPopup.attr('aria-hidden', 'true');
            }

            // Event-Listener für Klicks auf Trigger-Elemente
            $menuTriggerLinks.on('click', function(e) {
                e.preventDefault();
                $menuPopup.toggleClass('open');
                $('body').toggleClass('menu-open');
                var isExpanded = $menuPopup.hasClass('open');
                $(this).attr('aria-expanded', isExpanded);
                $menuPopup.attr('aria-hidden', !isExpanded);
            });

            // Überprüfe beim Laden der Seite, ob '#mobile-menu' im Hash ist
            if (window.location.hash === '#mobile-menu') {
                openMenu();
            }

            // Schließen des Menüs beim Klicken außerhalb des Menüs (auf das Overlay)
            $menuPopup.on('click', function(event) {
                // Überprüfe, ob das geklickte Element das Popup-Overlay selbst ist
                if ($(event.target).is(this)) {
                    closeMenu();
                }
            });

            // Optional: Schließen des Menüs beim Klicken außerhalb des Menüs (alte Methode beibehalten - kann entfernt werden, wenn Overlay immer den Bildschirm füllt)
            /*
            $(document).on('click', function(event) {
                if ($menuPopup.hasClass('open') && !$(event.target).closest('.mobile-menu-popup, a[href="#mobile-menu"], button[data-target="#mobile-menu"]').length) {
                    closeMenu();
                }
            });
            */

            // Optional: Schließen des Menüs mit der Escape-Taste
            $(document).on('keydown', function(event) {
                if (event.key === 'Escape' && $menuPopup.hasClass('open')) {
                    closeMenu();
                }
            });

            // Überwache Änderungen des Hash-Werts in der URL (z.B. durch Zurück/Vorwärts-Buttons)
            $(window).on('hashchange', function() {
                if (window.location.hash === '#mobile-menu') {
                    openMenu();
                } else {
                    closeMenu();
                }
            });
        });
    </script>
    <?php
}
add_action('wp_footer', 'yprint_add_mobile_menu_popup_js');

/**
 * Shortcode für einen Button zum Triggern des Mobile Menüs (angepasst für YPrint Design).
 *
 * Usage: [mobile_menu_button text="Menü öffnen" class="custom-button-class"]
 *
 * @param array $atts Die Attribute des Shortcodes.
 * @return string Der HTML-Code für den Button.
 */
function mobile_menu_button_shortcode( $atts ) {
    $atts = shortcode_atts( array(
        'text'  => 'Menü öffnen',
        'class' => '',
    ), $atts, 'mobile_menu_button' );

    $class_attr = !empty( $atts['class'] ) ? ' class="' . sanitize_html_class( $atts['class'] ) . '"' : '';

    return '<a href="#mobile-menu"' . $class_attr . ' style="background-color: #0079FF; color: #FFFFFF; padding: 12px 20px; border-radius: 10px; text-decoration: none; display: inline-block;">' . esc_html( $atts['text'] ) . '</a>';
}
add_shortcode( 'mobile_menu_button', 'mobile_menu_button_shortcode' );

// Um den Trigger-Button an einer bestimmten Stelle im Theme anzuzeigen,
// verwenden Sie den folgenden Shortcode im WordPress-Editor:
// [mobile_menu_button text="Menü öffnen" class="mein-stil"]
// Ersetzen Sie "Menü öffnen" durch Ihren gewünschten Text und "mein-stil" durch optionale CSS-Klassen.

// Sie können auch einen normalen WordPress-Button verwenden und ihm im Link-Dialog die URL "#mobile-menu" geben.
// Um ihn wie den primären YPrint Button zu gestalten, können Sie ihm die Klasse "wp-block-button__link" geben
// und dann in Ihrem CSS weitere Stile hinzufügen oder die Standardstile überschreiben.

// Das Popup-HTML und die CSS-Stile werden automatisch im Footer bzw. Header eingefügt.
// Das JavaScript für die Funktionalität wird ebenfalls automatisch im Footer eingefügt.
?>