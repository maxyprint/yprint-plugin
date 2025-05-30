<?php
/**
 * Generiert ein Popup-Menü mit der beschriebenen Designstruktur.
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
 * Fügt die notwendigen CSS-Stile in den Header ein.
 */
function yprint_add_mobile_menu_popup_css() {
    ?>
    <style type="text/css">
        /* Grundlegendes Styling für das Popup */
        #mobile-menu-popup {
            display: none; /* Standardmäßig ausgeblendet */
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 9999; /* Sollte über anderen Inhalten liegen */
            background-color: rgba(0, 0, 0, 0.5); /* Optionaler Hintergrund-Dimmer */
            overflow: hidden; /* Verhindert Scrollen des Body, wenn Popup offen ist */
            transition: opacity 0.3s ease-in-out;
            opacity: 0;
        }

        #mobile-menu-popup.open {
            display: block;
            opacity: 1;
        }

        #mobile-menu-popup .sidebar {
            background-color: #111;
            color: #fff;
            padding: 2rem 1rem;
            width: 250px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            align-items: flex-start; /* Linksbündige Ausrichtung der Sidebar-Inhalte */
            position: absolute;
            left: 0;
            top: 0;
            transform: translateX(-100%); /* Standardmäßig außerhalb des Bildschirms */
            transition: transform 0.3s ease-in-out;
        }

        #mobile-menu-popup.open .sidebar {
            transform: translateX(0);
        }

        /* Styling für die Sidebar-Inhalte */
        .user-info {
            margin-bottom: 2rem;
        }

        .username {
            font-size: 1.5rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }

        .main-menu ul {
            list-style: none;
            padding: 0;
            margin: 0;
            width: 100%; /* Stellt sicher, dass die Menüpunkte die volle Breite nutzen */
        }

        .menu-item {
            margin: 0.75rem 0;
            font-weight: 500;
            cursor: pointer;
            width: 100%; /* Menüpunkte nehmen die volle Breite ein */
        }

        .menu-item a {
            display: block; /* Macht den Link zum Blockelement für besseres Klickverhalten und Styling */
            color: #fff;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 8px; /* Für die aktiven Elemente */
        }

        .menu-item.active a {
            background-color: #2a2a2a;
            font-weight: 600;
        }

        .footer {
            font-size: 0.8rem;
            color: #aaa;
            padding-top: 2rem;
            width: 100%;
        }

        .footer p {
            margin: 0.25rem 0;
        }

        .menu-item.logout a {
            color: #f44336; /* Beispiel-Farbe für Logout */
        }

        body.menu-open {
            overflow: hidden; /* Verhindert Body-Scroll, wenn Menü offen ist */
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

            // Optional: Schließen des Menüs beim Klicken außerhalb des Menüs
            $(document).on('click', function(event) {
                if ($menuPopup.hasClass('open') && !$(event.target).closest('.mobile-menu-popup, a[href="#mobile-menu"], button[data-target="#mobile-menu"]').length) {
                    closeMenu();
                }
            });

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
 * Shortcode für einen Button zum Triggern des Mobile Menüs
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

    return '<a href="#mobile-menu"' . $class_attr . '>' . esc_html( $atts['text'] ) . '</a>';
}
add_shortcode( 'mobile_menu_button', 'mobile_menu_button_shortcode' );

// Um den Trigger-Button an einer bestimmten Stelle im Theme anzuzeigen,
// verwenden Sie den folgenden Shortcode im WordPress-Editor:
// [mobile_menu_button text="Menü öffnen" class="mein-stil"]
// Ersetzen Sie "Menü öffnen" durch Ihren gewünschten Text und "mein-stil" durch optionale CSS-Klassen.

// Das Popup-HTML und die CSS-Stile werden automatisch im Footer bzw. Header eingefügt.
// Das JavaScript für die Funktionalität wird ebenfalls automatisch im Footer eingefügt.
?>