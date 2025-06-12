<?php
/**
 * YPrint Warenkorb Funktionen
 * Alle Warenkorb-bezogenen Funktionen für das YPrint System
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Konsolidiert Warenkorb-Einträge, indem gleiche Produkte zusammengeführt werden
 * Diese Funktion entfernt doppelte Einträge des gleichen Produkts und addiert die Mengen
 * Berücksichtigt Print-Design als Unterscheidungsmerkmal
 *
 * @return bool True if changes were made, false otherwise.
 */
function yprint_consolidate_cart_items() {
    // Wenn WooCommerce nicht aktiv ist oder der Warenkorb leer ist, beenden
    if (!class_exists('WooCommerce') || is_null(WC()->cart) || WC()->cart->is_empty()) {
        return false; // Keine Änderungen möglich, wenn leer oder WC inaktiv
    }

    $cart = WC()->cart->get_cart();
    $temp_grouping = array();
    $changes_made = false;

    // Gruppieren der Artikel
    foreach ($cart as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
        $variation_attributes = isset($cart_item['variation']) ? $cart_item['variation'] : array();

        // Spezialfall: Print-Design oder Design-Product-Flag macht ein Produkt einzigartig
        if (isset($cart_item['print_design']) || isset($cart_item['_is_design_product'])) {
            // Bei Design-Produkten jedes als einzigartiges Produkt behandeln und konsolidierung überspringen
            continue;
        }

        // Sammle alle benutzerdefinierten Daten außer den Standard-Schlüsseln
        $custom_data = array();
        foreach ($cart_item as $key => $value) {
            if (!in_array($key, array('product_id', 'variation_id', 'variation', 'quantity', 'data', 'key'))) {
                $custom_data[$key] = $value;
            }
        }

        // Erstelle einen Hash für die Variationen, sortiert, um Konsistenz zu gewährleisten
        ksort($variation_attributes);
        $variation_hash = md5(serialize($variation_attributes));
        
        // Erstelle einen Hash für benutzerdefinierte Daten
        $custom_data_hash = !empty($custom_data) ? md5(serialize($custom_data)) : '';

        // Unique Key für dieses Produkt/Variation/Custom-Data erstellen
        $unique_group_key = $product_id . '-' . $variation_id . '-' . $variation_hash;
        if (!empty($custom_data_hash)) {
            $unique_group_key .= '-' . $custom_data_hash;
        }

        if (isset($temp_grouping[$unique_group_key])) {
            // Produkt bereits in der Gruppe gesehen, Menge addieren und Schlüssel zum Entfernen vormerken
            $temp_grouping[$unique_group_key]['quantity'] += $cart_item['quantity'];
            $temp_grouping[$unique_group_key]['duplicate_keys'][] = $cart_item_key;
            $changes_made = true;
        } else {
            // Neues Produkt in der Gruppe
            $temp_grouping[$unique_group_key] = array(
                'main_cart_item_key' => $cart_item_key, // Behalte den ersten Schlüssel
                'quantity' => $cart_item['quantity'],
                'duplicate_keys' => array()
            );
        }
    }

    // Aktualisieren und Entfernen von doppelten Einträgen
    if ($changes_made) {
        foreach ($temp_grouping as $group_data) {
            // Entferne die doppelten Einträge
            foreach ($group_data['duplicate_keys'] as $key_to_remove) {
                WC()->cart->remove_cart_item($key_to_remove);
            }

            // Aktualisiere die Menge des Hauptartikels, falls nötig
            if (!empty($group_data['duplicate_keys'])) {
                $main_key = $group_data['main_cart_item_key'];
                // Prüfe, ob der Hauptschlüssel noch im Warenkorb existiert
                if (isset(WC()->cart->get_cart()[$main_key])) {
                    // Menge aktualisieren und Summenwerte sofort neu berechnen
                    WC()->cart->set_quantity($main_key, $group_data['quantity'], true);
                }
            }
        }
        
        // Gesamtsummen einmalig am Ende neu berechnen für bessere Performance
        WC()->cart->calculate_totals();
    }

    return $changes_made;
}

/**
 * AJAX-Handler zur Konsolidierung des Warenkorbs (Backend-Teil)
 */
function yprint_ajax_consolidate_cart_callback() {
    // Warenkorb konsolidieren
    $changes = yprint_consolidate_cart_items();

    // Antwort senden. Frontend JS wird refreshCartContent aufrufen.
    wp_send_json_success(array(
        'changes_made' => $changes,
    ));

    wp_die();
}
add_action('wp_ajax_yprint_consolidate_cart_action', 'yprint_ajax_consolidate_cart_callback');
add_action('wp_ajax_nopriv_yprint_consolidate_cart_action', 'yprint_ajax_consolidate_cart_callback');

/**
 * Minimalistischer Warenkorb-Shortcode
 */
function yprint_minimalist_cart_shortcode() {
    // Debug: Shortcode wird aufgerufen
    error_log('YPRINT: yprint_minimalist_cart_shortcode called');
    
    // Puffer-Output starten
    ob_start();

    // Sicherstellen, dass WooCommerce aktiv ist
    if (!class_exists('WooCommerce') || is_null(WC()->cart) ) {
        return '<p>WooCommerce ist nicht aktiviert.</p>';
    }

    // Eindeutige ID für diesen Warenkorb generieren
    $cart_id = 'yprint-cart-' . uniqid();

    // Warenkorb optimieren - gleiche Produkte zusammenführen VOR dem Rendern
    yprint_consolidate_cart_items();

    // CSS für den minimalistischen Warenkorb
    ?>
    <style>
        .yprint-mini-cart {
    font-family: 'Helvetica Neue', Arial, sans-serif;
    max-width: 100%;
    margin: 0 auto;
    position: relative; /* Für absolute Positionierung des Loading Overlays */
    box-sizing: border-box; /* Ensure padding doesn't affect width */
    padding: 0; /* Padding entfernt */
    background-color: transparent; /* Hintergrund transparent */
    border-radius: 0; /* Keine abgerundeten Ecken */
    box-shadow: none; /* Kein Schatten */
}
        .yprint-mini-cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px; /* Increased padding */
            margin-bottom: 15px;
        }
        .yprint-mini-cart-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #333; /* Darker title color */
        }
        .yprint-mini-cart-count {
            background: #0079FF;
            color: #fff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0; /* Verhindert Schrumpfen */
            font-weight: 600;
        }
        .yprint-mini-cart-items {
            max-height: 300px; /* Oder eine andere passende Höhe */
            overflow-y: auto;
            margin-bottom: 15px;
             padding-right: 10px; /* Add padding to the right for scrollbar space */
        }
         /* Style the scrollbar */
        .yprint-mini-cart-items::-webkit-scrollbar {
            width: 8px;
        }
        .yprint-mini-cart-items::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .yprint-mini-cart-items::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .yprint-mini-cart-items::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .yprint-mini-cart-item {
            display: flex;
            align-items: flex-start; /* Richte oben aus */
            padding: 15px 0; /* Mehr Padding für besseren Abstand */
            border-bottom: 1px solid #f5f5f5;
            position: relative; /* Wichtig für absolute Positionierung des Remove Buttons */
        }
        .yprint-mini-cart-item:last-child {
             border-bottom: none; /* Kein Border am letzten Element */
        }
        .yprint-mini-cart-item-image {
            width: 80px; /* Etwas größer für bessere Sichtbarkeit */
            margin-right: 15px; /* Mehr Abstand zum Text */
            flex-shrink: 0; /* Verhindert Schrumpfen */
            border: 1px solid #eee; /* Light border around image */
            border-radius: 4px; /* Leichte Rundung */
             overflow: hidden; /* Hide anything outside the border-radius */
        }
        .yprint-mini-cart-item-image img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .yprint-mini-cart-item-details {
            flex-grow: 1;
            display: flex; /* Erlaube Flexbox für Titel, Preis, Menge */
            flex-direction: column; /* Richte Details untereinander aus */
            justify-content: center;
        }
        .yprint-mini-cart-item-title {
            font-size: 15px; /* Etwas größer */
            margin: 0 0 5px;
            font-weight: 600; /* Titel fetter */
            color: #333;
        }
         /* Ensure no double title if accidentally rendered */
         .yprint-mini-cart-item-details .yprint-mini-cart-item-title + .yprint-mini-cart-item-title {
             display: none;
         }

        .yprint-mini-cart-item-price {
            font-size: 14px;
            font-weight: 600;
            color: #0079FF; /* Preis in Akzentfarbe */
            margin-bottom: 10px; /* Abstand zur Menge */
        }
        .yprint-mini-cart-item-quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #ddd; /* Border um Mengen-Kontrolle */
            border-radius: 4px; /* Leichte Rundung */
            width: fit-content; /* Passt sich dem Inhalt an */
            overflow: hidden; /* Hide borders at edges */
        }

        .qty-btn {
            background: #f9f9f9; /* Leichter Hintergrund */
            border: none;
            padding: 5px 10px; /* Mehr Padding */
            color: #333; /* Dunklere Farbe */
            font-size: 16px;
            cursor: pointer;
            width: auto;
            height: auto;
            transition: background-color 0.2s ease; /* Sanfter Übergang */
            line-height: 1; /* Adjust line height */
        }

        .qty-btn:hover {
            background: #eee; /* Hintergrund bei Hover */
            color: #000;
        }

        .qty-value {
            padding: 5px 8px;
            font-size: 14px;
            min-width: 20px;
            text-align: center;
            cursor: pointer;
             border-left: 1px solid #ddd;
             border-right: 1px solid #ddd;
             background: #fff; /* Heller Hintergrund für den Wert */
             line-height: 1; /* Adjust line height */
        }
        .qty-input {
            width: 30px; /* Breiteres Inputfeld */
            text-align: center;
            border: none; /* Inputfeld Border entfernen, da Container den Border hat */
            font-size: 14px;
            padding: 5px 0; /* Gleiches vertikales Padding wie Buttons */
            display: none;
             box-sizing: border-box; /* Padding/Border in die Breite einbeziehen */
             line-height: 1; /* Adjust line height */
        }
        /* Positioniere den Remove Button absolut */
        .yprint-mini-cart-item-remove {
            position: absolute;
            top: 10px; /* Abstand vom oberen Rand */
            right: 0; /* Abstand vom rechten Rand */
            cursor: pointer;
            color: #999;
            font-size: 20px; /* Etwas größer */
            line-height: 1;
            padding: 0; /* Padding entfernen, da Position absolut ist */
            background: none; /* Sicherstellen, dass kein Hintergrund da ist */
            border: none; /* Sicherstellen, dass kein Border da ist */
            transition: color 0.2s ease; /* Smooth color change */
        }
        .yprint-mini-cart-item-remove:hover {
            color: #FF4136; /* Farbe ändern bei Hover */
        }
        .yprint-mini-cart-subtotal {
            display: flex;
            justify-content: space-between;
            padding: 15px 0 10px; /* Mehr Padding oben */
            font-weight: 600;
            border-top: 1px solid #eee;
            font-size: 16px; /* Subtotal etwas größer */
            color: #333;
        }
        .yprint-mini-cart-subtotal span:last-child {
             color: #0079FF; /* Subtotal Wert in Akzentfarbe */
        }
        .yprint-mini-cart-checkout {
            display: block;
            width: 100%;
            background: #0079FF;
            color: #fff;
            border: none;
            padding: 12px 15px;
            font-size: 16px; /* Checkout Button Text größer */
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border-radius: 4px; /* Button abrunden */
            margin-top: 15px; /* Abstand zum Subtotal */
        }
        .yprint-mini-cart-checkout:hover {
            background: #0062cc;
        }
        .yprint-mini-cart-empty {
            text-align: center;
            padding: 20px 0;
            color: #777;
            font-style: italic; /* Kursiv */
        }
        .yprint-loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
            pointer-events: none; /* Ermöglicht Klicks auf Elemente darunter, wenn nicht aktiv */
             border-radius: 8px; /* Überlagerung an Ecken anpassen */
        }
        .yprint-loading-overlay.active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto; /* Deaktiviert Klicks, wenn aktiv */
        }
        .yprint-loading-overlay img {
            width: 80px;
            height: auto;
            /* Removed spin animation */
             animation: pulse 1.5s infinite alternate; /* Pulsing animation */
        }

        /* Define Pulse Animation */
        @keyframes pulse {
            0% { opacity: 0.6; transform: scale(1); }
            100% { opacity: 1; transform: scale(1.05); }
        }

    </style>
    <div id="<?php echo esc_attr($cart_id); ?>" class="yprint-mini-cart">
        <div class="yprint-loading-overlay"><img src="https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg" alt="Loading..."></div>
        <div class="yprint-mini-cart-header">
            <h3 class="yprint-mini-cart-title">Dein Warenkorb</h3>
            <div class="yprint-mini-cart-count"><?php echo WC()->cart->get_cart_contents_count(); ?></div>
        </div>

        <div class="yprint-mini-cart-items">
            <?php
            // Warenkorb-Einträge nach der Konsolidierung abrufen
            $cart_items = WC()->cart->get_cart();

            if (empty($cart_items)) {
                echo '<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>';
            } else {
                foreach ($cart_items as $cart_item_key => $cart_item) {
                    $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                    $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

                    // Sicherstellen, dass das Produkt gültig ist und die Menge > 0
                    if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                        $product_name = $_product->get_name();
                        $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);

                        // Zeige den Gesamtpreis für diesen Artikel (Preis pro Stück * Menge)
                        $item_total_price_html = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );

                        // --- CUSTOM PRICE LOGIC (if needed) ---
                        // If you have a custom price from the Design Tool stored in $cart_item data,
                        // uncomment and adapt the following lines to use that price instead of the standard subtotal.
                        /*
                        if (isset($cart_item['print_design']['custom_price'])) {
                            // Assuming 'custom_price' is the price for the quantity in the cart item
                            $custom_price = floatval($cart_item['print_design']['custom_price']);
                            // Format the custom price using WooCommerce formatting
                            $item_total_price_html = wc_price($custom_price);
                        }
                        */
                        // --- END CUSTOM PRICE LOGIC ---


                        $product_quantity = $cart_item['quantity'];
                        ?>
                        <div class="yprint-mini-cart-item" data-item-key="<?php echo esc_attr($cart_item_key); ?>">
                            <div class="yprint-mini-cart-item-image">
                                <?php echo $thumbnail; ?>
                            </div>
                            <div class="yprint-mini-cart-item-details">
                                <?php
// Prüfe, ob es ein Design-Produkt ist und verwende Designtitel
if (isset($cart_item['print_design']) && !empty($cart_item['print_design']['name'])) {
    $display_name = $cart_item['print_design']['name'];
    
    // Füge Variation und Größe hinzu, falls vorhanden
    $details = [];
    if (!empty($cart_item['print_design']['variation_name'])) {
        $details[] = $cart_item['print_design']['variation_name'];
    }
    if (!empty($cart_item['print_design']['size_name'])) {
        $details[] = $cart_item['print_design']['size_name'];
    }
    
    if (!empty($details)) {
        $display_name .= ' (' . implode(', ', $details) . ')';
    }
} else {
    $display_name = $product_name;
}
?>
<h4 class="yprint-mini-cart-item-title"><?php echo esc_html($display_name); ?></h4>
                                <div class="yprint-mini-cart-item-price"><?php echo $item_total_price_html; ?></div>
                                <div class="yprint-mini-cart-item-quantity-control">
                                    <button class="qty-btn qty-minus" data-action="minus">−</button>
                                    <span class="qty-value"><?php echo esc_html($product_quantity); ?></span>
                                    <input type="number" class="qty-input" value="<?php echo esc_attr($product_quantity); ?>" min="1">
                                    <button class="qty-btn qty-plus" data-action="plus">+</button>
                                </div>
                            </div>
                            <button class="yprint-mini-cart-item-remove" aria-label="Artikel entfernen">×</button>
                        </div>
                        <?php
                    }
                }
            }
            ?>
        </div>

        <div class="yprint-mini-cart-subtotal">
            <span>Zwischensumme:</span>
            <span class="cart-subtotal-value"><?php echo WC()->cart->get_cart_subtotal(); ?></span>
        </div>

        <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="yprint-mini-cart-checkout">Zur Kasse</a>
    </div>

    <script>
    (function($) {
        // Eindeutige ID für diesen Warenkorb
        var cartId = '<?php echo esc_js($cart_id); ?>'; // esc_js() für sicheren JavaScript Output
        var $cart = $('#' + cartId);

        // WICHTIG: Event-Handler nur für diesen Warenkorb initialisieren
        // Verhindert doppelte Event-Bindung bei mehreren Warenkörben

        // Prüfen, ob dieser Warenkorb bereits initialisiert wurde
        if ($cart.data('initialized')) {
            return;
        }

        // Warenkorb als initialisiert markieren
        $cart.data('initialized', true);

        // Variable, um zu verfolgen, ob ein Eingabefeld aktiv ist
        var isEditing = false;

        // Funktion zur Anzeige/Ausblenden des Loading Overlays
        function toggleLoading(show) {
            if (show) {
                $cart.find('.yprint-loading-overlay').addClass('active');
                 // Start pulse animation
                 $cart.find('.yprint-loading-overlay img').css('animation', 'pulse 1.5s infinite alternate');
            } else {
                $cart.find('.yprint-loading-overlay').removeClass('active');
                 // Stop pulse animation (optional, but good practice)
                 // The animation property in CSS is now permanent while active,
                 // so no need to explicitly stop it here unless we want to hard reset.
                 // Keeping the animation defined in CSS makes it simpler.
            }
        }

        // Funktion zur Aktualisierung des Warenkorb-Inhalts per AJAX
        function refreshCartContent() {
            toggleLoading(true); // Overlay aktivieren

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_refresh_cart_content'
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Cart refresh successful:', response); // Log success

                        // Cart-Items aktualisieren - Handle beide Response-Strukturen
const cartHtml = response.data?.cart_items_html || response.cart_items_html;
const cartCount = response.data?.cart_count || response.cart_count;
const cartSubtotal = response.data?.cart_subtotal || response.cart_subtotal;

$cart.find('.yprint-mini-cart-items').html(cartHtml);
$cart.find('.yprint-mini-cart-count').text(cartCount);
$cart.find('.yprint-mini-cart-subtotal .cart-subtotal-value').html(cartSubtotal);

                        // Trigger custom event after cart is refreshed
                        $(document.body).trigger('yprint_mini_cart_refreshed');
                    } else {
                         console.error('Fehler beim Aktualisieren des Warenkorbs:', response);
                    }

                    toggleLoading(false); // Overlay deaktivieren
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Fehler beim Aktualisieren des Warenkorbs:', status, error);
                    toggleLoading(false); // Overlay deaktivieren bei Fehlern
                }
            });
        }

        // Initialisiere die Aktualisierung, wenn der Warenkorb sichtbar wird (oder DOM bereit ist)
        // Dies funktioniert sowohl für Popups als auch normale Einbindungen
        // Einmalige Aktualisierung beim Laden der Seite, falls der Warenkorb sichtbar ist
        $(document).ready(function() {
             if ($cart.is(':visible')) {
                 refreshCartContent();
             }
        });

         // Oder verwenden Sie den IntersectionObserver für dynamische Anzeige (z.B. in Popups)
         var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting && !$cart.data('content-loaded')) {
                    // Warenkorb ist jetzt sichtbar und Inhalt noch nicht geladen
                    console.log('Cart is intersecting, refreshing content.');
                    refreshCartContent();
                    $cart.data('content-loaded', true); // Markiere Inhalt als geladen
                } else if (!entry.isIntersecting && $cart.data('content-loaded')) {
                    // Warenkorb ist nicht mehr sichtbar, setze Flag zurück für erneutes Laden bei nächster Anzeige
                     $cart.data('content-loaded', false);
                }
            });
        }, { threshold: 0.01 }); // Schon bei 1% Sichtbarkeit

        // Beobachte den Warenkorb
        if ($cart[0]) {
            observer.observe($cart[0]);
        }


        // Add to Cart Event abfangen, um den Warenkorb nach dem Hinzufügen zu aktualisieren
        // und Konsolidierung im Backend anzustoßen, bevor UI aktualisiert wird
        $(document.body).on('added_to_cart', function() {
            console.log('Product added to cart.');
            toggleLoading(true); // Overlay aktivieren

            // Sende AJAX-Request zur Konsolidierung im Backend
            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_consolidate_cart_action' // Neuer AJAX-Action Name
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Consolidation successful:', response);
                        // Nachdem die Konsolidierung im Backend abgeschlossen ist,
                        // den Warenkorb-Inhalt aktualisieren.
                        refreshCartContent();
                    } else {
                        console.error('Fehler bei der Warenkorb-Konsolidierung:', response);
                         toggleLoading(false); // Overlay bei Fehler deaktivieren
                    }
                },
                 error: function(xhr, status, error) {
                    console.error('AJAX Fehler bei der Warenkorb-Konsolidierung:', status, error);
                     toggleLoading(false); // Overlay bei Fehler deaktivieren
                }
            });
        });

        // ENTFERNEN-BUTTON
$cart.on('click', '.yprint-mini-cart-item-remove', function() {
    var $item = $(this).closest('.yprint-mini-cart-item');
    var cartItemKey = $item.data('item-key');

    if (!cartItemKey) {
        console.error('Artikel-Schlüssel nicht gefunden.');
        return;
    }

    toggleLoading(true); // Overlay aktivieren

    // Debug-Ausgabe
    console.log('Entferne Artikel mit Schlüssel:', cartItemKey);

    $.ajax({
        url: wc_add_to_cart_params.ajax_url,
        type: 'POST',
        data: {
            action: 'yprint_remove_from_cart',
            cart_item_key: cartItemKey
        },
        dataType: 'json',
        success: function(response) {
            console.log('Antwort vom Server:', response);
            
            if (response.success) {
                // Artikel visuell entfernen
                $item.fadeOut(300, function() {
                    $(this).remove();

                    // Warenkorb-Anzahl im Header aktualisieren
                    $cart.find('.yprint-mini-cart-count').text(response.data.cart_count);
                    
                    // Zwischensumme aktualisieren
                    $cart.find('.yprint-mini-cart-subtotal .cart-subtotal-value').html(response.data.cart_subtotal);

                    // Leeren Warenkorb zeigen, wenn keine Artikel mehr
                    if (response.data.cart_count === 0) {
                        $cart.find('.yprint-mini-cart-items').html('<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>');
                    }

                    // Trigger custom event after item is removed
                    $(document.body).trigger('yprint_mini_cart_item_removed', [cartItemKey]);
                });
            } else {
                console.error('Fehler beim Entfernen:', response.data);
                alert('Der Artikel konnte nicht entfernt werden.');
            }
            
            toggleLoading(false);
        },
        error: function(xhr, status, error) {
            console.error('AJAX-Fehler beim Entfernen:', status, error);
            console.log('XHR Objekt:', xhr);
            alert('Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.');
            toggleLoading(false);
        }
    });
});

// PLUS/MINUS BUTTONS
$cart.on('click', '.qty-btn', function() {
    if (isEditing) return; // Ignorieren, wenn Eingabe aktiv

    var $btn = $(this);
    var $item = $btn.closest('.yprint-mini-cart-item');
    var $qtyValue = $item.find('.qty-value');
    var cartItemKey = $item.data('item-key');
    
    if (!cartItemKey) {
        console.error('Kein cart-item-key gefunden!');
        return;
    }
    
    var currentQty = parseInt($qtyValue.text());
    
    // Sicherstellen, dass die aktuelle Menge eine gültige Zahl ist
    if (isNaN(currentQty)) {
        currentQty = 1;
    }
    
    var newQty = $btn.data('action') === 'minus' ?
                Math.max(1, currentQty - 1) : // Mindestmenge 1
                currentQty + 1;

    if (newQty === currentQty) return; // Keine Aktion, wenn Menge gleich bleibt

    // Deaktiviere den Button, um Mehrfachklicks zu verhindern
    $btn.prop('disabled', true);
    
    // Menge sofort in der UI aktualisieren für bessere Benutzerreaktion
    $qtyValue.text(newQty);
    $item.find('.qty-input').val(newQty);

    // AJAX-Update der Menge im Backend
    updateQuantity(cartItemKey, newQty, $item);
    
    // Button nach kurzer Verzögerung wieder aktivieren
    setTimeout(function() {
        $btn.prop('disabled', false);
    }, 500);
});

        // DIREKTEINGABE DER MENGE
        // Klick auf Menge (zum Bearbeiten)
        $cart.on('click', '.qty-value', function() {
            if (isEditing) return; // Bereits im Bearbeitungsmodus

            isEditing = true;
            var $value = $(this);
            var $input = $value.siblings('.qty-input');
            var $item = $value.closest('.yprint-mini-cart-item');

            // Eingabefeld vorbereiten und anzeigen
            $input.val($value.text());
            $value.hide();
            $input.show().focus().select();

            // Fügen Sie eine Klasse hinzu, um den Bearbeitungszustand anzuzeigen
            $item.addClass('editing-quantity');
        });

        // Eingabefeld verlassen (Blur)
        $cart.on('blur', '.qty-input', function() {
            var $input = $(this);
            var $item = $input.closest('.yprint-mini-cart-item');
            var $value = $input.siblings('.qty-value');
            var cartItemKey = $item.data('item-key');
            var oldQty = parseInt($value.text());
            var newQty = parseInt($input.val());

            // Gültigen Wert sicherstellen
            if (isNaN(newQty) || newQty < 1) {
                 newQty = oldQty; // Setze auf alte Menge, wenn ungültig
                 $input.val(oldQty); // Aktualisiere das Inputfeld
            }

            // UI zurücksetzen
            $input.hide();
            $value.text(newQty).show();

            // Bearbeitungsmodus beenden
            isEditing = false;
             $item.removeClass('editing-quantity');


            // Nur bei Änderung aktualisieren
            if (newQty !== oldQty) {
                updateQuantity(cartItemKey, newQty, $item); // Übergabe des Items
            }
        });

        // Enter-Taste im Eingabefeld
        $cart.on('keypress', '.qty-input', function(e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                $(this).blur(); // Verlasse das Feld, was blur() auslöst und das Update triggert
            }
        });

         // Klick außerhalb schließt Eingabefeld
        $(document).on('mousedown', function(e) {
            if (isEditing) {
                var $target = $(e.target);
                // Prüfen, ob der Klick außerhalb des Mengen-Inputs oder des Mengen-Werts war
                if (!$target.closest('.qty-input').length && !$target.closest('.qty-value').length && !$target.closest('.qty-btn').length) {
                     // Finden Sie alle aktiven Eingabefelder innerhalb dieses spezifischen Warenkorbs
                    $cart.find('.qty-input:visible').blur();
                }
            }
        });


// Funktion zum Aktualisieren der Menge per AJAX
function updateQuantity(cartItemKey, quantity, $item_element) {
    toggleLoading(true); // Overlay aktivieren
    
    // Direkt die UI aktualisieren für bessere Benutzererfahrung
    if($item_element) {
        $item_element.find('.qty-value').text(quantity);
        $item_element.find('.qty-input').val(quantity);
    }

    // AJAX-Request zum Aktualisieren der Menge im Backend
    $.ajax({
        url: wc_add_to_cart_params.ajax_url,
        type: 'POST',
        data: {
            action: 'yprint_update_cart_quantity',
            cart_item_key: cartItemKey,
            quantity: quantity
        },
        success: function(response) {
            if (response.success) {
                console.log('Quantity update successful:', response);
                refreshCartContent();
            } else {
                console.error('Fehler beim Aktualisieren der Menge:', response.data);
                toggleLoading(false);
            }
        },
        error: function(xhr, status, error) {
            console.error('AJAX Fehler beim Aktualisieren der Menge:', status, error);
            toggleLoading(false);
        }
    });
}

         // Removed the transitionend listener for animation control,
         // as the animation is now permanently set in CSS when the overlay is active.

    })(jQuery);
    </script>
    <?php

    // Puffer-Output zurückgeben
    return ob_get_clean();
}

/**
 * Ajax-Funktion zum Entfernen eines Produkts aus dem Warenkorb
 */
function yprint_remove_from_cart() {
    // Debug-Ausgabe (nur während der Entwicklung)
    // error_log('yprint_remove_from_cart aufgerufen. POST: ' . print_r($_POST, true));
    
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';

    if (empty($cart_item_key)) {
        wp_send_json_error('Kein Artikel-Schlüssel angegeben');
        return;
    }
    
    if (is_null(WC()->cart)) {
        wp_send_json_error('Warenkorb nicht initialisiert');
        return;
    }
    
    if (WC()->cart->remove_cart_item($cart_item_key)) {
        // Warenkorb-Summen neu berechnen nach dem Entfernen
        WC()->cart->calculate_totals();

        // Daten zurückgeben
        wp_send_json_success(array(
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_subtotal' => WC()->cart->get_cart_subtotal(),
            'message' => 'Artikel erfolgreich entfernt'
        ));
    } else {
        wp_send_json_error('Artikel konnte nicht entfernt werden');
    }
}
add_action('wp_ajax_yprint_remove_from_cart', 'yprint_remove_from_cart');
add_action('wp_ajax_nopriv_yprint_remove_from_cart', 'yprint_remove_from_cart');

function yprint_update_cart_quantity() {
    global $wpdb;
    
    // Einfache Fehlerbehandlung für Debugging aktivieren
    $wpdb->show_errors(); 
    
    try {
        // Parameter abrufen
        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        
        if (empty($cart_item_key) || $quantity <= 0) {
            wp_send_json_error(array('message' => 'Ungültige Parameter'));
            return;
        }
        
        // WooCommerce-Initialisierung sicherstellen
        if (!function_exists('WC') || is_null(WC()->cart)) {
            wp_send_json_error(array('message' => 'WooCommerce nicht verfügbar'));
            return;
        }
        
        // Menge aktualisieren - einmal versuchen
        WC()->cart->set_quantity($cart_item_key, $quantity, true);
        
        // Summen neu berechnen
        WC()->cart->calculate_totals();
        
        // Antwort senden
        wp_send_json_success(array(
            'cart_subtotal' => WC()->cart->get_cart_subtotal(),
            'cart_count' => WC()->cart->get_cart_contents_count()
        ));
        
    } catch (Exception $e) {
        // Fehlerdetails loggen
        error_log('YPrint Cart Error: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Ein Fehler ist aufgetreten'));
    }
}
add_action('wp_ajax_yprint_update_cart_quantity', 'yprint_update_cart_quantity');
add_action('wp_ajax_nopriv_yprint_update_cart_quantity', 'yprint_update_cart_quantity');

/**
 * Verhindert doppelte Artikel beim Laden aus der Session und nach AJAX-Updates
 */
function yprint_cart_loaded_from_session_and_ajax($cart) {
    // Stelle sicher, dass der Warenkorb initialisiert ist, bevor auf Methoden zugegriffen wird
    if (is_null($cart)) {
        return;
    }

    // Führe die Konsolidierung aus
     yprint_consolidate_cart_items();

     // Stelle sicher, dass die Totals nach der Konsolidierung korrekt sind
     // Dies ist wichtig, falls die Konsolidierung Mengen geändert hat
     $cart->calculate_totals(); // Use $cart object passed by the hook

}
// Haken für die Konsolidierung beim Laden der Session
add_action('woocommerce_cart_loaded_from_session', 'yprint_cart_loaded_from_session_and_ajax', 10); // Priorität 10, um früh ausgeführt zu werden

/**
 * AJAX-Handler zur Aktualisierung des Warenkorb-Inhalts (Frontend-HTML)
 */
function yprint_refresh_cart_content_callback() {
    ob_start();

    // Stelle sicher, dass der Warenkorb initialisiert ist
     if (is_null(WC()->cart)) {
        wp_send_json_error('WooCommerce cart is not initialized.');
        wp_die();
     }

    // Warenkorb optimieren VOR dem Generieren des HTML
    // Dies stellt sicher, dass das generierte HTML den konsolidierten Zustand widerspiegelt
    yprint_consolidate_cart_items();

    // Warenkorb-Einträge nach der Konsolidierung abrufen
    $cart_items = WC()->cart->get_cart();

    if (empty($cart_items)) {
        echo '<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>';
    } else {
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

             // Sicherstellen, dass das Produkt gültig ist und die Menge > 0
            if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                $product_name = $_product->get_name();
                $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);

                 // Zeige den Gesamtpreis für diesen Artikel (Preis pro Stück * Menge)
                $item_total_price_html = apply_filters( 'woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal( $_product, $cart_item['quantity'] ), $cart_item, $cart_item_key );

                // --- CUSTOM PRICE LOGIC (if needed) ---
                /*
                if (isset($cart_item['print_design']['custom_price'])) {
                    $custom_price = floatval($cart_item['print_design']['custom_price']);
                    $item_total_price_html = wc_price($custom_price);
                }
                */
                // --- END CUSTOM PRICE LOGIC ---

                $product_quantity = $cart_item['quantity'];
                ?>
                <div class="yprint-mini-cart-item" data-item-key="<?php echo esc_attr($cart_item_key); ?>">
                    <div class="yprint-mini-cart-item-image">
                        <?php echo $thumbnail; ?>
                    </div>
                    <div class="yprint-mini-cart-item-details">
                        <?php
// Prüfe, ob es ein Design-Produkt ist und verwende Designtitel
if (isset($cart_item['print_design']) && !empty($cart_item['print_design']['name'])) {
    $display_name = $cart_item['print_design']['name'];
    
    // Füge Variation und Größe hinzu, falls vorhanden
    $details = [];
    if (!empty($cart_item['print_design']['variation_name'])) {
        $details[] = $cart_item['print_design']['variation_name'];
    }
    if (!empty($cart_item['print_design']['size_name'])) {
        $details[] = $cart_item['print_design']['size_name'];
    }
    
    if (!empty($details)) {
        $display_name .= ' (' . implode(', ', $details) . ')';
    }
} else {
    $display_name = $product_name;
}
?>
<h4 class="yprint-mini-cart-item-title"><?php echo esc_html($display_name); ?></h4>
                        <div class="yprint-mini-cart-item-price"><?php echo $item_total_price_html; ?></div>
                        <div class="yprint-mini-cart-item-quantity-control">
                            <button class="qty-btn qty-minus" data-action="minus">−</button>
                            <span class="qty-value"><?php echo esc_html($product_quantity); ?></span>
                            <input type="number" class="qty-input" value="<?php echo esc_attr($product_quantity); ?>" min="1">
                            <button class="qty-btn qty-plus" data-action="plus">+</button>
                        </div>
                    </div>
                    <button class="yprint-mini-cart-item-remove" aria-label="Artikel entfernen">×</button>
                </div>
                <?php
            }
        }
    }

    $cart_items_html = ob_get_clean();

    // Nach dem Rendern des HTML, aktualisierte Warenkorb-Daten abrufen
    // WC()->cart->calculate_totals(); // Should already be current

    wp_send_json_success(array(
        'cart_items_html' => $cart_items_html,
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_subtotal' => WC()->cart->get_cart_subtotal()
    ));

    wp_die();
}
add_action('wp_ajax_yprint_refresh_cart_content', 'yprint_refresh_cart_content_callback');
add_action('wp_ajax_nopriv_yprint_refresh_cart_content', 'yprint_refresh_cart_content_callback');

/**
 * Stellt sicher, dass die print_design-Daten während des Checkouts erhalten bleiben
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_add_design_data_to_order_item', 10, 4);
function yprint_add_design_data_to_order_item($item, $cart_item_key, $values, $order) {
    // Überprüfen, ob es print_design-Daten gibt
    if (isset($values['print_design']) && !empty($values['print_design'])) {
        $design = $values['print_design'];
        
        // Debugging: Protokolliere die Design-Daten
        error_log("Verarbeite Design-Daten für Bestellung: " . print_r($design, true));
        
        // Design-Metadaten zum Bestelleintrag hinzufügen
        foreach ($design as $meta_key => $meta_value) {
            if (is_array($meta_value) || is_object($meta_value)) {
                $meta_value = wp_json_encode($meta_value);
            }
            $item->add_meta_data('_design_' . $meta_key, $meta_value);
        }
        
        // Setze die Hauptinformationen für die einfache Anzeige in der Bestellansicht
        $item->add_meta_data('_design_id', $design['design_id'] ?? '');
        $item->add_meta_data('_design_name', $design['name'] ?? '');
        $item->add_meta_data('_design_color', $design['variation_name'] ?? '');
        $item->add_meta_data('_design_size', $design['size_name'] ?? '');
        $item->add_meta_data('_design_preview_url', $design['preview_url'] ?? '');

        // Erweiterte erforderliche Meta-Daten hinzufügen
        $item->add_meta_data('_design_width_cm', $design['width_cm'] ?? '25.4');
        $item->add_meta_data('_design_height_cm', $design['height_cm'] ?? '30.2');
        $item->add_meta_data('_design_image_url', $design['design_image_url'] ?? '');
        $item->add_meta_data('_design_product_images', $design['product_images'] ?? '');
        $item->add_meta_data('_design_images', $design['design_images'] ?? '');
        
        // Back Design URLs falls vorhanden
        $item->add_meta_data('_design_back_preview_url', $design['back_preview_url'] ?? '');
        $item->add_meta_data('_design_back_image_url', $design['back_design_image_url'] ?? '');
        
        // Erweiterte Multiple Images Flag Berechnung
        $product_images_count = 0;
        $design_images_count = 0;
        
        if (!empty($design['product_images'])) {
            $product_images_array = is_string($design['product_images']) ? 
                json_decode($design['product_images'], true) : $design['product_images'];
            $product_images_count = is_array($product_images_array) ? count($product_images_array) : 0;
        }
        
        if (!empty($design['design_images'])) {
            $design_images_array = is_string($design['design_images']) ? 
                json_decode($design['design_images'], true) : $design['design_images'];
            $design_images_count = is_array($design_images_array) ? count($design_images_array) : 0;
        }
        
        $has_multiple = ($product_images_count > 1 || $design_images_count > 1) ? 'yes' : 'no';
        $views_count = max($product_images_count, $design_images_count);
        
        $item->add_meta_data('_design_has_multiple_images', $has_multiple);
        $item->add_meta_data('_design_views_count', $views_count);
        
        // Zusätzliche Scale-Daten aus design_images extrahieren
        if (!empty($design_images_array) && isset($design_images_array[0])) {
            $item->add_meta_data('_design_scaleX', $design_images_array[0]['scaleX'] ?? 1.0);
            $item->add_meta_data('_design_scaleY', $design_images_array[0]['scaleY'] ?? 1.0);
        }
        
        // Template ID falls vorhanden
        if (!empty($design['template_id'])) {
            $item->add_meta_data('_design_template_id', $design['template_id']);
        }
        
        // Design Type für bessere Kategorisierung
        $design_type = $has_multiple === 'yes' ? 'multi_view' : 'single_view';
        $item->add_meta_data('_design_type', $design_type);
        
        // Stelle sicher, dass diese Informationen auch für das OctoPrint Plugin verfügbar sind
        $item->add_meta_data('_has_print_design', 'yes');
        
        // Zusätzliche spezifische Meta-Daten für Octo Plugin
        $item->add_meta_data('print_design', $design);
        
        // Speichere den Item, um sicherzustellen, dass die Meta-Daten persistiert werden
        $item->save();
    }
}

/**
 * Erweiterte Warenkorb-Konsolidierungsfunktion mit verbessertem Design-Produkt-Handling
 */
function yprint_extended_consolidate_cart_items() {
    if (!class_exists('WooCommerce') || is_null(WC()->cart)) {
        return false;
    }
    
    $cart = WC()->cart->get_cart();
    $temp_grouping = array();
    $changes_made = false;

    // Gruppieren der Artikel
    foreach ($cart as $cart_item_key => $cart_item) {
        // Wenn es ein Design-Produkt ist, diesen Artikel auslassen
        if (isset($cart_item['print_design']) || isset($cart_item['_is_design_product'])) {
            continue;
        }

        $product_id = $cart_item['product_id'];
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
        $variation_attributes = isset($cart_item['variation']) ? $cart_item['variation'] : array();
        
        // Erstelle einen Hash für die Variationen, sortiert für Konsistenz
        ksort($variation_attributes);
        $variation_hash = md5(serialize($variation_attributes));
        
        // Sammle alle benutzerdefinierten Daten außer den Standard-Schlüsseln
        $custom_data = array();
        foreach ($cart_item as $key => $value) {
            if (!in_array($key, array('product_id', 'variation_id', 'variation', 'quantity', 'data', 'key'))) {
                $custom_data[$key] = $value;
            }
        }
        
        // Erstelle einen Hash für benutzerdefinierte Daten
        $custom_data_hash = !empty($custom_data) ? md5(serialize($custom_data)) : '';

        // Unique Key für dieses Produkt/Variation erstellen
        $unique_group_key = $product_id . '-' . $variation_id . '-' . $variation_hash;
        if (!empty($custom_data_hash)) {
            $unique_group_key .= '-' . $custom_data_hash;
        }

        if (isset($temp_grouping[$unique_group_key])) {
            // Produkt bereits in der Gruppe gesehen, Menge addieren und Schlüssel zum Entfernen vormerken
            $temp_grouping[$unique_group_key]['quantity'] += $cart_item['quantity'];
            $temp_grouping[$unique_group_key]['duplicate_keys'][] = $cart_item_key;
            $changes_made = true;
        } else {
            // Neues Produkt in der Gruppe
            $temp_grouping[$unique_group_key] = array(
                'main_cart_item_key' => $cart_item_key, // Behalte den ersten Schlüssel
                'quantity' => $cart_item['quantity'],
                'duplicate_keys' => array()
            );
        }
    }

    // Aktualisieren und Entfernen von doppelten Einträgen
    if ($changes_made) {
        foreach ($temp_grouping as $group_data) {
            // Entferne die doppelten Einträge
            foreach ($group_data['duplicate_keys'] as $key_to_remove) {
                WC()->cart->remove_cart_item($key_to_remove);
            }

            // Aktualisiere die Menge des Hauptartikels, falls nötig
            if (!empty($group_data['duplicate_keys'])) {
                $main_key = $group_data['main_cart_item_key'];
                // Prüfe, ob der Hauptschlüssel noch im Warenkorb existiert
                if (isset(WC()->cart->get_cart()[$main_key])) {
                    // Menge aktualisieren und Summenwerte sofort neu berechnen
                    WC()->cart->set_quantity($main_key, $group_data['quantity'], false);
                }
            }
        }
        
        // Gesamtsummen einmalig am Ende neu berechnen für bessere Performance
        WC()->cart->calculate_totals();
    }

    return $changes_made;
}

 /**
 * Bearbeitet cart sessions, um sicherzustellen, dass print_design-Daten erhalten bleiben
 */
add_filter('woocommerce_get_cart_item_from_session', 'yprint_get_cart_item_from_session', 10, 2);
function yprint_get_cart_item_from_session($cart_item, $values) {
    if (isset($values['print_design'])) {
        $cart_item['print_design'] = $values['print_design'];
        
        // WICHTIG: Enhance-Funktion auch für Session-Recovery ausführen
        // Dies stellt sicher, dass existierende Cart-Items die erweiterten Daten erhalten
        $enhanced_data = yprint_enhance_design_data_for_existing_cart_item(
            $values['print_design'], 
            $cart_item['product_id'], 
            $cart_item['variation_id'] ?? 0
        );
        $cart_item['print_design'] = $enhanced_data;
        
        // Markiere das Item als Design-Produkt, damit es nicht konsolidiert wird
        $cart_item['_is_design_product'] = true;
        
        // Stelle sicher, dass der unique_design_key erhalten bleibt
        if (isset($values['unique_design_key'])) {
            $cart_item['unique_design_key'] = $values['unique_design_key'];
        } else {
            // Erstelle einen neuen Schlüssel, falls keiner existiert
            $cart_item['unique_design_key'] = md5(wp_json_encode($values['print_design']));
        }
        
        // Original Preis wiederherstellen, falls gespeichert
        if (isset($values['original_price'])) {
            $cart_item['original_price'] = $values['original_price'];
        }
        
        // Wenn ein berechneter Preis vorliegt, setze diesen
        if (isset($values['print_design']['calculated_price']) && !empty($values['print_design']['calculated_price'])) {
            $product = $cart_item['data'];
            
            // Originalpreis speichern, falls nicht gesetzt
            if (!isset($cart_item['original_price'])) {
                $cart_item['original_price'] = $product->get_price();
            }
            
            // Preis basierend auf berechneter Designpreis setzen
            $product->set_price($values['print_design']['calculated_price']);
        }
    }
    
    return $cart_item;
}

/**
 * Überwacht Mengenänderungen bei Design-Produkten
 */
add_action('woocommerce_before_calculate_totals', 'yprint_before_calculate_totals', 10, 1);
function yprint_before_calculate_totals($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return;
    }
    
    // Durchlaufe alle Warenkorb-Einträge
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        // Nur Design-Produkte bearbeiten
        if (isset($cart_item['print_design']) && isset($cart_item['original_price'])) {
            $product = $cart_item['data'];
            
            // Stelle sicher, dass der Preis korrekt gesetzt ist
            if (isset($cart_item['print_design']['calculated_price']) && !empty($cart_item['print_design']['calculated_price'])) {
                // Setze den berechneten Preis
                $product->set_price($cart_item['print_design']['calculated_price']);
            } else {
                // Setze den Originalpreis zurück, falls kein berechneter Preis existiert
                $product->set_price($cart_item['original_price']);
            }
        }
    }
}

/**
 * Designdaten in den Bestelldetails anzeigen
 */
add_action('woocommerce_order_item_meta_end', 'yprint_display_design_data_in_order', 10, 3);
function yprint_display_design_data_in_order($item_id, $item, $order) {
    // Überprüfen, ob es sich um ein Print-Design-Produkt handelt
    if ($item->get_meta('_has_print_design') === 'yes') {
        echo '<div class="print-design-info" style="margin-top: 10px; padding-top: 10px; border-top: 1px dotted #ccc;">';
        echo '<p><strong>Design-Details:</strong></p>';
        
        $design_name = $item->get_meta('_design_name');
        if (!empty($design_name)) {
            echo '<p>Name: ' . esc_html($design_name) . '</p>';
        }
        
        $design_color = $item->get_meta('_design_color');
        if (!empty($design_color)) {
            echo '<p>Farbe: ' . esc_html($design_color) . '</p>';
        }
        
        $design_size = $item->get_meta('_design_size');
        if (!empty($design_size)) {
            echo '<p>Größe: ' . esc_html($design_size) . '</p>';
        }
        
        echo '</div>';
    }
}

/**
 * Passt den Produkttitel im Warenkorb an, um Designinformationen anzuzeigen
 */
add_filter('woocommerce_cart_item_name', 'yprint_modify_cart_item_name', 10, 3);
function yprint_modify_cart_item_name($name, $cart_item, $cart_item_key) {
    if (isset($cart_item['print_design'])) {
        $design = $cart_item['print_design'];
        
        // Bei Design-Produkten: Designtitel statt Produkttitel verwenden
        if (!empty($design['name'])) {
            $name = '<span class="design-name">' . esc_html($design['name']) . '</span>';
        }
        
        // Variation und Größe hinzufügen, falls vorhanden
        $details = [];
        if (!empty($design['variation_name'])) {
            $details[] = esc_html($design['variation_name']);
        }
        if (!empty($design['size_name'])) {
            $details[] = esc_html($design['size_name']);
        }
        
        if (!empty($details)) {
            $name .= ' <span class="design-details">(' . implode(', ', $details) . ')</span>';
        }
    }
    
    return $name;
}

/**
 * Fügt eine Vorschaubilder-Miniaturansicht zum Warenkorbartikel hinzu
 * (Kommentiert, wie in deinem Ursprungscode)
 */
// add_filter('woocommerce_cart_item_thumbnail', 'yprint_custom_cart_item_thumbnail', 10, 3);
// function yprint_custom_cart_item_thumbnail($thumbnail, $cart_item, $cart_item_key) {
//     if (isset($cart_item['print_design']) && !empty($cart_item['print_design']['preview_url'])) {
//         $preview_url = $cart_item['print_design']['preview_url'];
//         
//         // Original-Thumbnail erhalten und darunter Designvorschau anzeigen
//         $thumbnail .= '<div class="design-preview" style="margin-top: 5px;"><img src="' . esc_url($preview_url) . '" style="max-width: 50px; height: auto;" alt="Design Preview"></div>';
//     }
//     
//     return $thumbnail;
// }

// Shortcode registrieren
add_shortcode('yprint_minimalist_cart', 'yprint_minimalist_cart_shortcode');

/**
 * Wird aufgerufen, nachdem ein Produkt zum Warenkorb hinzugefügt wurde
 */
add_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20, 6);
function yprint_after_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Verhindert rekursive Aufrufe
    static $is_processing = false;
    
    if ($is_processing) {
        return;
    }
    
    $is_processing = true;
    
    // Entferne den Hook temporär
    remove_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20);
    
    // Prüfe auf print_design Daten im aktuellen Produkt
    $has_print_design = isset($cart_item_data['print_design']);
    
    // Konsolidiere den Warenkorb, aber nur wenn es kein Print-Design-Produkt ist
    if (!$has_print_design) {
        yprint_consolidate_cart_items();
    } else {
        // Wenn es ein Print-Design-Produkt ist, markieren wir es als solches
        $current_cart_item = WC()->cart->get_cart_item($cart_item_key);
        if ($current_cart_item && isset($current_cart_item['print_design'])) {
            // Speichere das Design-Flag für dieses Produkt
            WC()->cart->cart_contents[$cart_item_key]['_is_design_product'] = true;
            
            // Generiere einen eindeutigen Schlüssel für dieses Design
            if (isset($current_cart_item['print_design']['design_id'])) {
                $unique_design_key = md5(wp_json_encode($current_cart_item['print_design']));
                WC()->cart->cart_contents[$cart_item_key]['unique_design_key'] = $unique_design_key;
            }
            
            // Persistiere den Warenkorb
            WC()->cart->set_session();
        }
    }
    
    // Füge den Hook wieder hinzu
    add_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20, 6);
    
    // Event für AJAX-Aktualisierung triggern
    do_action('yprint_cart_updated');
    
    $is_processing = false;
}

/**
 * Spezielle Funktion zum Persistieren von Design-Daten während des Checkouts
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_preserve_design_data_in_order', 20, 4);
function yprint_preserve_design_data_in_order($item, $cart_item_key, $values, $order) {
    // Überprüfe, ob es ein Design-Produkt ist
    if (isset($values['print_design']) && !empty($values['print_design'])) {
        // Speichere die Design-Daten direkt als Meta-Feld für das Octo-Plugin
        $item->update_meta_data('print_design', $values['print_design']);
        
        // Stelle sicher, dass es als Design-Produkt markiert ist
        $item->update_meta_data('_is_design_product', true);
        
        // Speichere auch alle individuellen Design-Parameter als Meta-Daten
        foreach ($values['print_design'] as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = wp_json_encode($value);
            }
            $item->update_meta_data('design_' . $key, $value);
        }
    }
 

/**
 * Erweitert Design-Daten beim Hinzufügen zum Warenkorb
 * Diese Funktion stellt sicher, dass alle erforderlichen Design-Felder verfügbar sind
 */
add_filter('woocommerce_add_cart_item_data', 'yprint_enhance_cart_item_design_data', 10, 3);
function yprint_enhance_cart_item_design_data($cart_item_data, $product_id, $variation_id) {
    // Debug: Prüfe ob überhaupt Design-Daten vorhanden sind
    error_log('YPRINT: enhance_cart_item_design_data called for product ' . $product_id);
    error_log('YPRINT: cart_item_data keys: ' . implode(', ', array_keys($cart_item_data)));
    
    // Nur wenn bereits print_design Daten vorhanden sind
    if (isset($cart_item_data['print_design'])) {
        $design_data = $cart_item_data['print_design'];
        
        // Erweiterte WooCommerce-Daten ergänzen mit debugging
        $product = wc_get_product($product_id);
        error_log('YPRINT: Processing product ' . $product_id . ', variation: ' . ($variation_id ?: 'none'));
        
        // Variation-Daten extrahieren - erweiterte Fallback-Logik
        if ($variation_id && !isset($design_data['variation_name'])) {
            $variation = wc_get_product($variation_id);
            if ($variation) {
                $attributes = $variation->get_attributes();
                error_log('YPRINT: Variation attributes: ' . print_r($attributes, true));
                
                // Methode 1: Direkte Attribute
                if (isset($attributes['pa_color'])) {
                    $term = get_term_by('slug', $attributes['pa_color'], 'pa_color');
                    $design_data['variation_name'] = $term ? $term->name : $attributes['pa_color'];
                } elseif (isset($attributes['color'])) {
                    $design_data['variation_name'] = $attributes['color'];
                } elseif (isset($attributes['attribute_pa_color'])) {
                    $design_data['variation_name'] = $attributes['attribute_pa_color'];
                }
                
                // Methode 2: Größe extrahieren
                if (isset($attributes['pa_size'])) {
                    $term = get_term_by('slug', $attributes['pa_size'], 'pa_size');
                    $design_data['size_name'] = $term ? $term->name : $attributes['pa_size'];
                } elseif (isset($attributes['size'])) {
                    $design_data['size_name'] = $attributes['size'];
                } elseif (isset($attributes['attribute_pa_size'])) {
                    $design_data['size_name'] = $attributes['attribute_pa_size'];
                }
                
                // Methode 3: Aus Variation Name extrahieren
                if (!isset($design_data['variation_name']) && $variation->get_name()) {
                    $variation_name = str_replace($product->get_name() . ' - ', '', $variation->get_name());
                    // Wenn Name Format wie "Farbe - Größe" ist, splitten
                    $name_parts = explode(' - ', $variation_name);
                    if (count($name_parts) >= 1) {
                        $design_data['variation_name'] = trim($name_parts[0]);
                    }
                    if (count($name_parts) >= 2 && !isset($design_data['size_name'])) {
                        $design_data['size_name'] = trim($name_parts[1]);
                    }
                }
                
                // Methode 4: Aus WooCommerce Meta-Daten
                if (!isset($design_data['variation_name'])) {
                    $variation_meta = get_post_meta($variation_id);
                    foreach ($variation_meta as $key => $values) {
                        if (strpos($key, 'attribute_') === 0 && !empty($values[0])) {
                            if (strpos($key, 'color') !== false || strpos($key, 'farbe') !== false) {
                                $design_data['variation_name'] = $values[0];
                            } elseif (strpos($key, 'size') !== false || strpos($key, 'grosse') !== false || strpos($key, 'größe') !== false) {
                                $design_data['size_name'] = $values[0];
                            }
                        }
                    }
                }
            }
        }
        
        // Erweiterte Fallbacks für Basis-Produkt ohne Variation
        if (!isset($design_data['variation_name'])) {
            // Versuche aus Produkt-Attributen zu extrahieren
            if ($product && $product->is_type('simple')) {
                $product_attributes = $product->get_attributes();
                foreach ($product_attributes as $attribute) {
                    if ($attribute->get_name() === 'pa_color' || $attribute->get_name() === 'color') {
                        $terms = $attribute->get_terms();
                        if (!empty($terms)) {
                            $design_data['variation_name'] = $terms[0]->name;
                            break;
                        }
                    }
                }
            }
            
            // Final fallback
            if (!isset($design_data['variation_name'])) {
                $design_data['variation_name'] = 'Standard';
            }
        }
        
        if (!isset($design_data['size_name'])) {
            // Versuche aus Produkt-Attributen zu extrahieren
            if ($product && $product->is_type('simple')) {
                $product_attributes = $product->get_attributes();
                foreach ($product_attributes as $attribute) {
                    if ($attribute->get_name() === 'pa_size' || $attribute->get_name() === 'size') {
                        $terms = $attribute->get_terms();
                        if (!empty($terms)) {
                            $design_data['size_name'] = $terms[0]->name;
                            break;
                        }
                    }
                }
            }
            
            // Final fallback
            if (!isset($design_data['size_name'])) {
                $design_data['size_name'] = 'One Size';
            }
        }
        
        error_log('YPRINT: Final variation_name: ' . ($design_data['variation_name'] ?? 'NOT SET'));
        error_log('YPRINT: Final size_name: ' . ($design_data['size_name'] ?? 'NOT SET'));
             
        
        // Dimensionen aus Produkt-Meta oder Standard-Werten setzen
        if (!isset($design_data['width_cm'])) {
            $design_data['width_cm'] = get_post_meta($product_id, '_design_width_cm', true) ?: '25.4';
        }
        if (!isset($design_data['height_cm'])) {
            $design_data['height_cm'] = get_post_meta($product_id, '_design_height_cm', true) ?: '30.2';
        }
        
        // Verbesserte Original-Datei URL-Generierung
        if (!isset($design_data['design_image_url']) && isset($design_data['preview_url'])) {
            // Mehrere Konvertierungsstrategien für Original Design URLs
            $preview_url = $design_data['preview_url'];
            
            // Strategie 1: preview_ zu original_ 
            $original_url = str_replace('/preview_', '/original_', $preview_url);
            $original_url = str_replace('-1.png', '.png', $original_url);
            $original_url = str_replace('-1.jpg', '.jpg', $original_url);
            
            // Strategie 2: Falls preview im Dateinamen, entfernen
            if (strpos($original_url, 'preview') === false) {
                $original_url = str_replace('/previews/', '/originals/', $preview_url);
                $original_url = str_replace('_preview', '', $original_url);
                $original_url = str_replace('-preview', '', $original_url);
            }
            
            $design_data['design_image_url'] = $original_url;
        }
        
        // Back Design URLs ableiten falls vorhanden
        if (!isset($design_data['back_design_image_url']) && isset($design_data['back_preview_url'])) {
            $back_preview_url = $design_data['back_preview_url'];
            
            $back_original_url = str_replace('/preview_', '/original_', $back_preview_url);
            $back_original_url = str_replace('-1.png', '.png', $back_original_url);
            $back_original_url = str_replace('-1.jpg', '.jpg', $back_original_url);
            $back_original_url = str_replace('/previews/', '/originals/', $back_original_url);
            $back_original_url = str_replace('_preview', '', $back_original_url);
            $back_original_url = str_replace('-preview', '', $back_original_url);
            
            $design_data['back_design_image_url'] = $back_original_url;
        }
        
        // Verbesserte Multi-View JSON-Strukturen erstellen
        if (!isset($design_data['product_images'])) {
            $product_images = array();
            
            // Front View (Standard)
            if (!empty($design_data['preview_url'])) {
                $product_images[] = array(
                    'url' => $design_data['preview_url'],
                    'view_name' => 'Front',
                    'view_id' => 'front',
                    'width_cm' => $design_data['width_cm'] ?? '25.4',
                    'height_cm' => $design_data['height_cm'] ?? '30.2'
                );
            }
            
            // Back View (falls vorhanden)
            if (!empty($design_data['back_preview_url'])) {
                $product_images[] = array(
                    'url' => $design_data['back_preview_url'],
                    'view_name' => 'Back',
                    'view_id' => 'back',
                    'width_cm' => $design_data['width_cm'] ?? '25.4',
                    'height_cm' => $design_data['height_cm'] ?? '30.2'
                );
            }
            
            // Fallback falls keine Preview URLs vorhanden
            if (empty($product_images) && !empty($design_data['design_image_url'])) {
                $product_images[] = array(
                    'url' => $design_data['design_image_url'],
                    'view_name' => 'Front',
                    'view_id' => 'front',
                    'width_cm' => $design_data['width_cm'] ?? '25.4',
                    'height_cm' => $design_data['height_cm'] ?? '30.2'
                );
            }
            
            $design_data['product_images'] = wp_json_encode($product_images);
        }
        
        if (!isset($design_data['design_images'])) {
            $design_images = array();
            
            // Front Design (Original-Datei)
            if (!empty($design_data['design_image_url'])) {
                $design_images[] = array(
                    'url' => $design_data['design_image_url'],
                    'view_name' => 'Front',
                    'view_id' => 'front',
                    'scaleX' => 1.0,
                    'scaleY' => 1.0,
                    'width_cm' => $design_data['width_cm'] ?? '25.4',
                    'height_cm' => $design_data['height_cm'] ?? '30.2'
                );
            }
            
            // Back Design (falls vorhanden)
            if (!empty($design_data['back_design_image_url'])) {
                $design_images[] = array(
                    'url' => $design_data['back_design_image_url'],
                    'view_name' => 'Back',
                    'view_id' => 'back',
                    'scaleX' => 1.0,
                    'scaleY' => 1.0,
                    'width_cm' => $design_data['width_cm'] ?? '25.4',
                    'height_cm' => $design_data['height_cm'] ?? '30.2'
                );
            }
            
            // Fallback: Falls nur preview_url vorhanden ist, als Original verwenden
            if (empty($design_images) && !empty($design_data['preview_url'])) {
                $design_images[] = array(
                    'url' => $design_data['preview_url'],
                    'view_name' => 'Front',
                    'view_id' => 'front',
                    'scaleX' => 1.0,
                    'scaleY' => 1.0,
                    'width_cm' => $design_data['width_cm'] ?? '25.4',
                    'height_cm' => $design_data['height_cm'] ?? '30.2'
                );
            }
            
            $design_data['design_images'] = wp_json_encode($design_images);
        }
        
        // Multiple Images Flag basierend auf tatsächlicher Anzahl setzen
        $product_images_array = is_string($design_data['product_images']) ? 
            json_decode($design_data['product_images'], true) : [];
        $design_images_array = is_string($design_data['design_images']) ? 
            json_decode($design_data['design_images'], true) : [];
            
        $design_data['has_multiple_images'] = (count($product_images_array) > 1 || count($design_images_array) > 1);
        
        // Stelle sicher, dass has_multiple_images korrekt als String gespeichert wird für Checkout
        $design_data['has_multiple_images_flag'] = $design_data['has_multiple_images'] ? 'yes' : 'no';
        
        // Erweiterte Design-Daten zurück in cart_item_data speichern
        $cart_item_data['print_design'] = $design_data;
        
        // Debug-Ausgabe
        error_log('YPRINT: Enhanced design data for product ' . $product_id . ': ' . print_r($design_data, true));
    }
    
     return $cart_item_data;
}

/**
 * Debug AJAX-Handler für Cart-Audit (vereinfacht)
 */
function yprint_debug_cart_callback() {
    if (!class_exists('WooCommerce') || is_null(WC()->cart)) {
        wp_send_json_error('WooCommerce nicht verfügbar');
        return;
    }
    
    $cart_contents = array();
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $cart_contents[$cart_item_key] = array(
            'product_id' => $cart_item['product_id'],
            'variation_id' => $cart_item['variation_id'] ?? 0,
            'quantity' => $cart_item['quantity'],
            'has_print_design' => isset($cart_item['print_design']),
            'print_design_full' => $cart_item['print_design'] ?? null
        );
    }
    
    wp_send_json_success(array(
        'cart_contents' => $cart_contents,
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_total' => WC()->cart->get_cart_subtotal()
    ));
}
add_action('wp_ajax_yprint_debug_cart', 'yprint_debug_cart_callback');
add_action('wp_ajax_nopriv_yprint_debug_cart', 'yprint_debug_cart_callback');

}

/**
 * Hilfsfunktion zum Erweitern von Design-Daten für existierende Cart-Items
 * Diese Funktion wird beim Session-Recovery ausgeführt
 */
function yprint_enhance_design_data_for_existing_cart_item($design_data, $product_id, $variation_id) {
    error_log('YPRINT: enhance_design_data_for_existing_cart_item called for product ' . $product_id);
    
    // Prüfe, ob bereits erweiterte Daten vorhanden sind
    if (isset($design_data['variation_name']) && isset($design_data['size_name']) && 
        isset($design_data['width_cm']) && isset($design_data['height_cm']) &&
        isset($design_data['design_image_url']) && isset($design_data['product_images']) && 
        isset($design_data['design_images'])) {
        error_log('YPRINT: Design data already enhanced, skipping');
        return $design_data;
    }
    
    // Verwende die gleiche Logik wie in yprint_enhance_cart_item_design_data
    $product = wc_get_product($product_id);
    error_log('YPRINT: Processing existing cart item - product ' . $product_id . ', variation: ' . ($variation_id ?: 'none'));
    
    // Variation-Daten extrahieren - erweiterte Fallback-Logik
    if ($variation_id && !isset($design_data['variation_name'])) {
        $variation = wc_get_product($variation_id);
        if ($variation) {
            $attributes = $variation->get_attributes();
            error_log('YPRINT: Variation attributes: ' . print_r($attributes, true));
            
            // Methode 1: Direkte Attribute
            if (isset($attributes['pa_color'])) {
                $term = get_term_by('slug', $attributes['pa_color'], 'pa_color');
                $design_data['variation_name'] = $term ? $term->name : $attributes['pa_color'];
            } elseif (isset($attributes['color'])) {
                $design_data['variation_name'] = $attributes['color'];
            } elseif (isset($attributes['attribute_pa_color'])) {
                $design_data['variation_name'] = $attributes['attribute_pa_color'];
            }
            
            // Methode 2: Größe extrahieren
            if (isset($attributes['pa_size'])) {
                $term = get_term_by('slug', $attributes['pa_size'], 'pa_size');
                $design_data['size_name'] = $term ? $term->name : $attributes['pa_size'];
            } elseif (isset($attributes['size'])) {
                $design_data['size_name'] = $attributes['size'];
            } elseif (isset($attributes['attribute_pa_size'])) {
                $design_data['size_name'] = $attributes['attribute_pa_size'];
            }
            
            // Methode 3: Aus Variation Name extrahieren
            if (!isset($design_data['variation_name']) && $variation->get_name()) {
                $variation_name = str_replace($product->get_name() . ' - ', '', $variation->get_name());
                // Wenn Name Format wie "Farbe - Größe" ist, splitten
                $name_parts = explode(' - ', $variation_name);
                if (count($name_parts) >= 1) {
                    $design_data['variation_name'] = trim($name_parts[0]);
                }
                if (count($name_parts) >= 2 && !isset($design_data['size_name'])) {
                    $design_data['size_name'] = trim($name_parts[1]);
                }
            }
            
            // Methode 4: Aus WooCommerce Meta-Daten
            if (!isset($design_data['variation_name'])) {
                $variation_meta = get_post_meta($variation_id);
                foreach ($variation_meta as $key => $values) {
                    if (strpos($key, 'attribute_') === 0 && !empty($values[0])) {
                        if (strpos($key, 'color') !== false || strpos($key, 'farbe') !== false) {
                            $design_data['variation_name'] = $values[0];
                        } elseif (strpos($key, 'size') !== false || strpos($key, 'grosse') !== false || strpos($key, 'größe') !== false) {
                            $design_data['size_name'] = $values[0];
                        }
                    }
                }
            }
        }
    }
    
    // Erweiterte Fallbacks für Basis-Produkt ohne Variation
    if (!isset($design_data['variation_name'])) {
        // Versuche aus Produkt-Attributen zu extrahieren
        if ($product && $product->is_type('simple')) {
            $product_attributes = $product->get_attributes();
            foreach ($product_attributes as $attribute) {
                if ($attribute->get_name() === 'pa_color' || $attribute->get_name() === 'color') {
                    $terms = $attribute->get_terms();
                    if (!empty($terms)) {
                        $design_data['variation_name'] = $terms[0]->name;
                        break;
                    }
                }
            }
        }
        
        // Final fallback
        if (!isset($design_data['variation_name'])) {
            $design_data['variation_name'] = 'Standard';
        }
    }
    
    if (!isset($design_data['size_name'])) {
        // Versuche aus Produkt-Attributen zu extrahieren
        if ($product && $product->is_type('simple')) {
            $product_attributes = $product->get_attributes();
            foreach ($product_attributes as $attribute) {
                if ($attribute->get_name() === 'pa_size' || $attribute->get_name() === 'size') {
                    $terms = $attribute->get_terms();
                    if (!empty($terms)) {
                        $design_data['size_name'] = $terms[0]->name;
                        break;
                    }
                }
            }
        }
        
        // Final fallback
        if (!isset($design_data['size_name'])) {
            $design_data['size_name'] = 'One Size';
        }
    }
    
    error_log('YPRINT: Final variation_name: ' . ($design_data['variation_name'] ?? 'NOT SET'));
    error_log('YPRINT: Final size_name: ' . ($design_data['size_name'] ?? 'NOT SET'));
    
    // Dimensionen aus Produkt-Meta oder Standard-Werten setzen
    if (!isset($design_data['width_cm'])) {
        $design_data['width_cm'] = get_post_meta($product_id, '_design_width_cm', true) ?: '25.4';
    }
    if (!isset($design_data['height_cm'])) {
        $design_data['height_cm'] = get_post_meta($product_id, '_design_height_cm', true) ?: '30.2';
    }
    
    // Verbesserte Original-Datei URL-Generierung
    if (!isset($design_data['design_image_url']) && isset($design_data['preview_url'])) {
        // Mehrere Konvertierungsstrategien für Original Design URLs
        $preview_url = $design_data['preview_url'];
        
        // Strategie 1: preview_ zu original_ 
        $original_url = str_replace('/preview_', '/original_', $preview_url);
        $original_url = str_replace('-1.png', '.png', $original_url);
        $original_url = str_replace('-1.jpg', '.jpg', $original_url);
        
        // Strategie 2: Falls preview im Dateinamen, entfernen
        if (strpos($original_url, 'preview') === false) {
            $original_url = str_replace('/previews/', '/originals/', $preview_url);
            $original_url = str_replace('_preview', '', $original_url);
            $original_url = str_replace('-preview', '', $original_url);
        }
        
        $design_data['design_image_url'] = $original_url;
    }
    
    // Back Design URLs ableiten falls vorhanden
    if (!isset($design_data['back_design_image_url']) && isset($design_data['back_preview_url'])) {
        $back_preview_url = $design_data['back_preview_url'];
        
        $back_original_url = str_replace('/preview_', '/original_', $back_preview_url);
        $back_original_url = str_replace('-1.png', '.png', $back_original_url);
        $back_original_url = str_replace('-1.jpg', '.jpg', $back_original_url);
        $back_original_url = str_replace('/previews/', '/originals/', $back_original_url);
        $back_original_url = str_replace('_preview', '', $back_original_url);
        $back_original_url = str_replace('-preview', '', $back_original_url);
        
        $design_data['back_design_image_url'] = $back_original_url;
    }
    
    // Verbesserte Multi-View JSON-Strukturen erstellen
    if (!isset($design_data['product_images'])) {
        $product_images = array();
        
        // Front View (Standard)
        if (!empty($design_data['preview_url'])) {
            $product_images[] = array(
                'url' => $design_data['preview_url'],
                'view_name' => 'Front',
                'view_id' => 'front',
                'width_cm' => $design_data['width_cm'] ?? '25.4',
                'height_cm' => $design_data['height_cm'] ?? '30.2'
            );
        }
        
        // Back View (falls vorhanden)
        if (!empty($design_data['back_preview_url'])) {
            $product_images[] = array(
                'url' => $design_data['back_preview_url'],
                'view_name' => 'Back',
                'view_id' => 'back',
                'width_cm' => $design_data['width_cm'] ?? '25.4',
                'height_cm' => $design_data['height_cm'] ?? '30.2'
            );
        }
        
        // Fallback falls keine Preview URLs vorhanden
        if (empty($product_images) && !empty($design_data['design_image_url'])) {
            $product_images[] = array(
                'url' => $design_data['design_image_url'],
                'view_name' => 'Front',
                'view_id' => 'front',
                'width_cm' => $design_data['width_cm'] ?? '25.4',
                'height_cm' => $design_data['height_cm'] ?? '30.2'
            );
        }
        
        $design_data['product_images'] = wp_json_encode($product_images);
    }
    
    if (!isset($design_data['design_images'])) {
        $design_images = array();
        
        // Front Design (Original-Datei)
        if (!empty($design_data['design_image_url'])) {
            $design_images[] = array(
                'url' => $design_data['design_image_url'],
                'view_name' => 'Front',
                'view_id' => 'front',
                'scaleX' => 1.0,
                'scaleY' => 1.0,
                'width_cm' => $design_data['width_cm'] ?? '25.4',
                'height_cm' => $design_data['height_cm'] ?? '30.2'
            );
        }
        
        // Back Design (falls vorhanden)
        if (!empty($design_data['back_design_image_url'])) {
            $design_images[] = array(
                'url' => $design_data['back_design_image_url'],
                'view_name' => 'Back',
                'view_id' => 'back',
                'scaleX' => 1.0,
                'scaleY' => 1.0,
                'width_cm' => $design_data['width_cm'] ?? '25.4',
                'height_cm' => $design_data['height_cm'] ?? '30.2'
            );
        }
        
        // Fallback: Falls nur preview_url vorhanden ist, als Original verwenden
        if (empty($design_images) && !empty($design_data['preview_url'])) {
            $design_images[] = array(
                'url' => $design_data['preview_url'],
                'view_name' => 'Front',
                'view_id' => 'front',
                'scaleX' => 1.0,
                'scaleY' => 1.0,
                'width_cm' => $design_data['width_cm'] ?? '25.4',
                'height_cm' => $design_data['height_cm'] ?? '30.2'
            );
        }
        
        $design_data['design_images'] = wp_json_encode($design_images);
    }
    
    // Multiple Images Flag basierend auf tatsächlicher Anzahl setzen
    $product_images_array = is_string($design_data['product_images']) ? 
        json_decode($design_data['product_images'], true) : [];
    $design_images_array = is_string($design_data['design_images']) ? 
        json_decode($design_data['design_images'], true) : [];
        
    $design_data['has_multiple_images'] = (count($product_images_array) > 1 || count($design_images_array) > 1);
    
    // Debug-Ausgabe
    error_log('YPRINT: Enhanced existing cart item design data for product ' . $product_id . ': ' . print_r($design_data, true));
    
    return $design_data;
}