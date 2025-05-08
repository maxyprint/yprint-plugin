<?php
/**
 * WooCommerce integration functions for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display order history for a customer with search and cancel functionality
 * 
 * Usage: [woocommerce_history order_count="-1"]
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output of order history
 */
function woo_order_history($atts) {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return '<p>WooCommerce ist nicht aktiviert.</p>';
    }

    extract(shortcode_atts(array(
        'order_count' => -1
    ), $atts));

    ob_start();
    $customer_id = get_current_user_id();
    
    // If user is not logged in, show login message
    if ($customer_id === 0) {
        return '<p>Bitte <a href="' . esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))) . '">melde dich an</a>, um deine Bestellungen zu sehen.</p>';
    }
    
    $all_statuses = array_keys(wc_get_order_statuses());
    
    $customer_orders = wc_get_orders(array(
        'customer' => $customer_id,
        'limit'    => $order_count,
        'type'     => 'shop_order',
        'status'   => $all_statuses,
    ));
    
    ?>
    <style>
        .yprint-order-history {
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
        }

        .yprint-order-content {
            width: 100%;
        }

        .yprint-order-search {
            width: 100%;
            padding: 12px 35px 12px 15px;
            background-color: #FFFFFF;
            border: 1px solid #e0e0e0 !important;
            border-radius: 15px !important;  /* Wichtig: !important hinzugefügt */
            margin-bottom: 15px;
            font-size: 16px;
            transition: all 0.2s ease;
            color: #333;
            box-sizing: border-box;  /* Stellt sicher, dass Padding nicht die Breite verändert */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }

        .yprint-order-search:focus {
            outline: none;
            border-color: #0079FF;
            box-shadow: 0 0 0 2px rgba(0, 121, 255, 0.1);
        }

        .yprint-order-list {
            display: grid;
            gap: 15px;
        }

        .yprint-order-card {
            padding: 15px 0;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        .yprint-order-card:not(:last-child) {
            border-bottom: 1px solid #f0f0f0;
        }

        .yprint-order-number {
            font-weight: 600;
            color: #333;
        }

        .yprint-order-meta {
            color: #888;
            font-size: 14px;
        }

        .yprint-order-details {
            display: grid;
            gap: 8px;
        }

        .yprint-order-price {
            font-weight: 600;
            color: #333;
        }

        .yprint-order-status {
            display: inline-block;
            font-weight: 500;
            font-size: 13px;
            padding: 4px 10px;
            border-radius: 20px;
            background-color: #e8f5fd;
            color: #0079FF;
        }

        .yprint-order-actions {
            display: flex;
            gap: 10px;
        }

        .yprint-order-btn {
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .yprint-btn-support {
            background-color: #f5f5f7;
            color: #333;
        }

        .yprint-btn-support:hover {
            background-color: #e5e5e7;
        }

        .yprint-btn-cancel {
            background-color: #ffeaee;
            color: #ff3b50;
        }

        .yprint-btn-cancel:hover {
            background-color: #ffe0e5;
        }

        .yprint-no-orders {
            text-align: center;
            padding: 40px 0;
            color: #888;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .yprint-checkout-columns {
                flex-direction: column;
            }
            
            .yprint-checkout-container {
                padding: 15px;
                min-height: auto;
            }
            
            .yprint-checkout-section {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            .yprint-section-title {
                font-size: 20px;
                margin-bottom: 15px;
            }
            
            .yprint-payment-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .yprint-form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .yprint-form-row input,
            .yprint-form-row select {
                width: 100%;
            }
            
            .yprint-back-button-container {
                margin-bottom: 15px;
            }
        }

        @media (max-width: 480px) {
            .yprint-address-slot {
                min-width: 100%;
            }
            
            .yprint-payment-grid {
                grid-template-columns: 1fr;
            }
            
            .yprint-order-item {
                flex-wrap: wrap;
            }
            
            .yprint-item-image {
                width: 50px;
                height: 50px;
            }
            
            .yprint-item-details {
                flex: 0 0 calc(100% - 60px);
            }
            
            .yprint-item-total {
                margin-top: 8px;
                margin-left: auto;
            }
        }
    </style>

    <div class="yprint-order-history">
        <div class="yprint-order-content">
            <input 
                type="text" 
                id="orderSearch" 
                class="yprint-order-search" 
                placeholder="Bestellung suchen..."
            >

            <?php if (!empty($customer_orders)) : ?>
                <div class="yprint-order-list" id="orderList">
                    <?php foreach ($customer_orders as $order): 
                        $order_data = $order->get_data();
                        $order_date = date_i18n('d.m.Y', strtotime($order_data['date_created']));
                        $order_time = date_i18n('H:i', strtotime($order_data['date_created']));
                        $price = wc_price($order_data['total']);
                        $status = $order->get_status();
                    ?>
                    <div class="yprint-order-card" data-order-id="<?php echo $order->get_id(); ?>">
                        <div>
                            <div class="yprint-order-number">
                                Bestellung #<?php echo $order->get_order_number(); ?>
                            </div>
                            <div class="yprint-order-meta">
                                <?php echo $order_date; ?> | <?php echo $order_time; ?>
                            </div>
                        </div>

                        <div class="yprint-order-details">
                            <div class="yprint-order-price"><?php echo $price; ?></div>
                            <div class="yprint-order-status">
                                <?php echo wc_get_order_status_name($status); ?>
                            </div>
                        </div>

                        <div class="yprint-order-actions">
                            <a href="mailto:info@yprint.de?subject=Support-Anfrage für Bestellung <?php echo $order->get_order_number(); ?>" 
                               class="yprint-order-btn yprint-btn-support">
                                Support
                            </a>

                            <?php if (in_array($status, array('pending', 'processing', 'on-hold'))): ?>
                                <button 
                                    class="yprint-order-btn yprint-btn-cancel" 
                                    onclick="cancelOrder(<?php echo $order->get_id(); ?>)">
                                    Stornieren
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="yprint-no-orders">
                    Du hast noch keine Bestellungen.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('orderSearch');
        const orderList = document.getElementById('orderList');

        if (searchInput && orderList) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const orderCards = orderList.querySelectorAll('.yprint-order-card');

                orderCards.forEach(card => {
                    const orderText = card.textContent.toLowerCase();
                    card.style.display = orderText.includes(searchTerm) ? 'grid' : 'none';
                });
            });
        }
    });

    function cancelOrder(orderId) {
        if (confirm('Möchtest du diese Bestellung wirklich stornieren?')) {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'yprint_cancel_order',
                    order_id: orderId,
                    security: '<?php echo wp_create_nonce('yprint-order-cancel'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Bestellung wurde erfolgreich storniert.');
                        location.reload();
                    } else {
                        alert('Fehler beim Stornieren der Bestellung: ' + response.data);
                    }
                },
                error: function() {
                    alert('Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.');
                }
            });
        }
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('woocommerce_history', 'woo_order_history');

/**
 * AJAX handler for cancelling an order
 * Validates the user session, order ownership, and allowed statuses
 */
function yprint_cancel_order() {
    // Check nonce for security
    check_ajax_referer('yprint-order-cancel', 'security');
    
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        wp_send_json_error('WooCommerce ist nicht aktiviert');
        return;
    }
    
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $user_id = get_current_user_id();
    
    // Check if user is logged in
    if ($user_id === 0) {
        wp_send_json_error('Du musst angemeldet sein, um Bestellungen zu stornieren');
        return;
    }
    
    // Check if order ID is provided
    if (!$order_id) {
        wp_send_json_error('Keine Bestellnummer angegeben');
        return;
    }
    
    // Get order object
    $order = wc_get_order($order_id);
    
    // Check if order exists
    if (!$order) {
        wp_send_json_error('Bestellung nicht gefunden');
        return;
    }
    
    // Check if order belongs to current user
    if ($order->get_customer_id() !== $user_id) {
        wp_send_json_error('Du bist nicht berechtigt, diese Bestellung zu stornieren');
        return;
    }
    
    // Check if order status is cancellable
    $cancellable_statuses = array('pending', 'processing', 'on-hold');
    if (!in_array($order->get_status(), $cancellable_statuses)) {
        wp_send_json_error('Diese Bestellung kann nicht mehr storniert werden');
        return;
    }
    
    // Update order status to cancelled
    $order->update_status('cancelled', 'Bestellung vom Kunden storniert');
    
    // Restock items
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if ($product && $product->managing_stock()) {
            $item_quantity = $item->get_quantity();
            wc_update_product_stock($product, $item_quantity, 'increase');
        }
    }
    
    // Log the cancellation
    $order->add_order_note('Bestellung wurde vom Kunden über die Website storniert.', false, true);
    
    do_action('yprint_order_cancelled_by_customer', $order_id);
    
    wp_send_json_success('Bestellung erfolgreich storniert');
}
add_action('wp_ajax_yprint_cancel_order', 'yprint_cancel_order');

/**
 * YPrint Minimalist Cart Shortcode
 *
 * Creates a minimalistic WooCommerce cart for popup display
 */

// Verhindere direkten Zugriff
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

    // Temporäre Struktur zur Identifizierung gleicher Produkte
    // Key: product_id-variation_id-variation_attributes_hash(-print_design_hash)
    // Value: array( 'cart_item_key' => first key found, 'quantity' => total quantity, 'duplicate_keys' => array of keys to remove )

    // Gruppieren der Artikel
    foreach ($cart as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
        $variation_attributes = isset($cart_item['variation']) ? $cart_item['variation'] : array();

        // Erstelle einen Hash für die Variationen, sortiert, um Konsistenz zu gewährleisten
        ksort($variation_attributes);
        $variation_hash = md5(serialize($variation_attributes));

        // Spezialfall: Print-Design macht ein Produkt einzigartig
        $print_design_hash = '';
        if (isset($cart_item['print_design'])) {
             // Erstelle einen Hash für das Print-Design-Daten (Annahme: ist serialisierbar)
             // Achtung: Die Struktur von 'print_design' muss serialisierbar sein.
            $print_design_hash = md5(serialize($cart_item['print_design']));
        }

        // Unique Key für dieses Produkt/Variation/Design erstellen
        $unique_group_key = $product_id . '-' . $variation_id . '-' . $variation_hash . ($print_design_hash ? '-' . $print_design_hash : '');

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
                 // Die Menge wurde in der Gruppierung bereits addiert, setze sie für den Hauptartikel
                 // Prüfe, ob der Hauptartikel noch existiert (er wurde nicht als Duplikat entfernt)
                 if (isset(WC()->cart->get_cart()[$group_data['main_cart_item_key']])) {
                    // Use true to recalculate totals immediately after setting quantity for the main item
                    WC()->cart->set_quantity($group_data['main_cart_item_key'], $group_data['quantity'], true);
                 }
            }
        }
         // After all quantity updates, ensure totals are recalculated.
         // This might be redundant if set_quantity(..., true) works for all items,
         // but adds robustness, especially if multiple items were consolidated.
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
                                <h4 class="yprint-mini-cart-item-title"><?php echo esc_html($product_name); ?></h4>
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

                        // Cart-Items aktualisieren
                        $cart.find('.yprint-mini-cart-items').html(response.cart_items_html);
                        // Warenkorb-Anzahl im Header aktualisieren
                        $cart.find('.yprint-mini-cart-count').text(response.cart_count);
                        // Zwischensumme aktualisieren - Target the class added to the span
                        $cart.find('.yprint-mini-cart-subtotal .cart-subtotal-value').html(response.cart_subtotal);

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

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_remove_from_cart',
                    cart_item_key: cartItemKey
                },
                success: function(response) {
                    if (response.cart_count !== undefined && response.cart_subtotal !== undefined) {
                         console.log('Item removal successful:', response); // Log success

                         // Artikel visuell entfernen
                        $item.fadeOut(300, function() {
                            $(this).remove();

                             // Warenkorb-Anzahl im Header aktualisieren
                            $cart.find('.yprint-mini-cart-count').text(response.cart_count);
                             // Zwischensumme aktualisieren - Target the class
                            $cart.find('.yprint-mini-cart-subtotal .cart-subtotal-value').html(response.cart_subtotal);

                            // Leeren Warenkorb zeigen, wenn keine Artikel mehr
                            if (response.cart_count === 0) {
                                $cart.find('.yprint-mini-cart-items').html('<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>');
                                 // Optional: hide subtotal and checkout button if cart is empty
                                 // $cart.find('.yprint-mini-cart-subtotal').hide();
                                 // $cart.find('.yprint-mini-cart-checkout').hide();
                            }

                            // Trigger custom event after item is removed
                            $(document.body).trigger('yprint_mini_cart_item_removed', [cartItemKey]);
                        });
                    } else {
                         console.error('Ungültige Antwort beim Entfernen:', response);
                    }


                    toggleLoading(false); // Overlay deaktivieren
                },
                error: function(xhr, status, error) {
                    console.error('Fehler beim Entfernen:', status, error);
                    toggleLoading(false); // Overlay deaktivieren bei Fehlern
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

    // Kurze Verzögerung, um die UI-Aktualisierung anzuzeigen
    setTimeout(function() {
        // Den vollständigen Warenkorb neu laden - einfacherer und zuverlässigerer Ansatz
        refreshCartContent();
    }, 500);
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
    // Sicherheit: Nonce überprüfen (Empfohlen für AJAX-Aufrufe)
    // if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'yprint_cart_nonce' ) ) {
    //     wp_send_json_error( 'Nonce verification failed' );
    //     wp_die();
    // }

    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';

    if ($cart_item_key && !is_null(WC()->cart) && WC()->cart->remove_cart_item($cart_item_key)) {
        // Warenkorb-Summen neu berechnen nach dem Entfernen
        WC()->cart->calculate_totals();

        // Minimale Daten zurückgeben
        wp_send_json(array(
            'success' => true, // Indicate success explicitly
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_subtotal' => WC()->cart->get_cart_subtotal()
        ));
    } else {
         wp_send_json_error('Could not remove item or invalid key');
    }

    wp_die();
}

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
                        <h4 class="yprint-mini-cart-item-title"><?php echo esc_html($product_name); ?></h4>
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

    wp_send_json(array(
        'success' => true,
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
        
        // Stelle sicher, dass diese Informationen auch für das OctoPrint Plugin verfügbar sind
        $item->add_meta_data('_has_print_design', 'yes');
        
        // Zusätzliche spezifische Meta-Daten für Octo Plugin
        $item->add_meta_data('print_design', $design);
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
        
        $preview_url = $item->get_meta('_design_preview_url');
        if (!empty($preview_url)) {
            echo '<p><img src="' . esc_url($preview_url) . '" style="max-width: 100px; height: auto;" alt="Vorschau"></p>';
        }
        
        echo '</div>';
    }
}

/**
 * Designdaten in Bestell-E-Mails anzeigen
 */
add_action('woocommerce_email_order_item_meta', 'yprint_display_design_data_in_email', 10, 4);
function yprint_display_design_data_in_email($item_id, $item, $order, $plain_text) {
    if ($plain_text) {
        return; // Überspringe einfachen Text und füge nur HTML hinzu
    }
    
    // Überprüfen, ob es sich um ein Print-Design-Produkt handelt
    if ($item->get_meta('_has_print_design') === 'yes') {
        echo '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px dotted #ccc;">';
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
        
        // Designname hinzufügen, falls vorhanden
        if (!empty($design['name'])) {
            $name .= ' - <span class="design-name">' . esc_html($design['name']) . '</span>';
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