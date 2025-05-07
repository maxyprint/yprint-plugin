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
 */
function yprint_consolidate_cart_items() {
    // Wenn WooCommerce nicht aktiv ist oder der Warenkorb leer ist, beenden
    if (!class_exists('WooCommerce') || WC()->cart->is_empty()) {
        return;
    }
    
    $cart = WC()->cart->get_cart();
    $consolidated = array();
    $changes_made = false;
    
    // Für jedes Produkt im Warenkorb
    foreach ($cart as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
        $variation = isset($cart_item['variation']) ? $cart_item['variation'] : array();
        
        // Spezialfall: Wenn ein Produkt benutzerdefinierte Print-Designs hat, nicht konsolidieren
        if (isset($cart_item['print_design'])) {
            continue;
        }
        
        // Unique Key für dieses Produkt erstellen
        $unique_key = $product_id . '-' . $variation_id . '-' . md5(serialize($variation));
        
        // Prüfen, ob wir dieses Produkt schon gesehen haben
        if (isset($consolidated[$unique_key])) {
            // Wenn ja, Menge zum ersten Eintrag hinzufügen und diesen Eintrag markieren zum Entfernen
            $consolidated[$unique_key]['quantity'] += $cart_item['quantity'];
            $consolidated[$unique_key]['remove_keys'][] = $cart_item_key;
            $changes_made = true;
        } else {
            // Wenn nicht, neuen Eintrag erstellen
            $consolidated[$unique_key] = array(
                'key' => $cart_item_key,
                'quantity' => $cart_item['quantity'],
                'remove_keys' => array()
            );
        }
    }
    
    // Wenn Änderungen vorgenommen wurden, den Warenkorb aktualisieren
    if ($changes_made) {
        // Zunächst alle zu entfernenden Einträge entfernen
        foreach ($consolidated as $unique_key => $data) {
            if (!empty($data['remove_keys'])) {
                foreach ($data['remove_keys'] as $remove_key) {
                    WC()->cart->remove_cart_item($remove_key);
                }
                // Dann den verbleibenden Eintrag aktualisieren
                WC()->cart->set_quantity($data['key'], $data['quantity']);
            }
        }
    }
    
    return $changes_made;
}

/**
 * Nach dem Hinzufügen zum Warenkorb, doppelte Einträge vermeiden
 */
function yprint_after_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    // Wenn ein Produkt mit benutzerdefinierten Print-Designs hinzugefügt wird, nicht konsolidieren
    if (isset($cart_item_data['print_design'])) {
        return;
    }
    
    // 100ms warten, um sicherzustellen, dass der Warenkorb aktualisiert wurde
    // (Dies ist wichtig für die Reihenfolge der Ereignisse)
    usleep(100000);
    
    // Warenkorb konsolidieren
    yprint_consolidate_cart_items();
}

/**
 * AJAX-Handler zur Konsolidierung des Warenkorbs
 */
function yprint_ajax_consolidate_cart() {
    // Warenkorb-Zustand vor der Konsolidierung speichern
    $item_count_before = count(WC()->cart->get_cart());
    
    // Konsolidieren
    $changes = yprint_consolidate_cart_items();
    
    // Warenkorb-Zustand nach der Konsolidierung
    $item_count_after = count(WC()->cart->get_cart());
    
    // Zähle zusammen, ob noch weitere Konsolidierung notwendig ist
    wp_send_json_success(array(
        'changes' => $changes,
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_subtotal' => WC()->cart->get_cart_subtotal(),
        'before' => $item_count_before,
        'after' => $item_count_after
    ));
}

/**
 * Minimalistischer Warenkorb-Shortcode
 */
function yprint_minimalist_cart_shortcode() {
    // Puffer-Output starten
    ob_start();
    
    // Sicherstellen, dass WooCommerce aktiv ist
    if (!class_exists('WooCommerce')) {
        return '<p>WooCommerce ist nicht aktiviert.</p>';
    }
    
    // Eindeutige ID für diesen Warenkorb generieren
    $cart_id = 'yprint-cart-' . uniqid();
    
    // Warenkorb optimieren - gleiche Produkte zusammenführen
    yprint_consolidate_cart_items();
    
    // CSS für den minimalistischen Warenkorb
    ?>
    <style>
        .yprint-mini-cart {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            max-width: 100%;
            margin: 0 auto;
        }
        .yprint-mini-cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .yprint-mini-cart-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
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
        }
        .yprint-mini-cart-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 15px;
        }
        .yprint-mini-cart-item {
            display: flex;
            padding: 10px 0;
            border-bottom: 1px solid #f5f5f5;
        }
        .yprint-mini-cart-item-image {
            width: 60px;
            margin-right: 10px;
        }
        .yprint-mini-cart-item-image img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .yprint-mini-cart-item-details {
            flex-grow: 1;
        }
        .yprint-mini-cart-item-title {
            font-size: 14px;
            margin: 0 0 5px;
        }
        .yprint-mini-cart-item-price {
            font-size: 14px;
            font-weight: 600;
        }
        .yprint-mini-cart-item-quantity-control {
            display: flex;
            align-items: center;
            margin-top: 5px;
        }
        
        .qty-btn {
            background: none;
            border: none;
            padding: 0;
            color: #0079FF;
            font-size: 16px;
            cursor: pointer;
            width: auto;
            height: auto;
        }

        .qty-btn:hover {
            background: none;
            border: none;
            padding: 0;
            color: #0079FF;
            font-size: 16px;
            cursor: pointer;
            width: auto;
            height: auto;
        }

        .qty-value {
            padding: 0 8px;
            font-size: 14px;
            min-width: 20px;
            text-align: center;
            cursor: pointer;
        }
        .qty-input {
            width: 24px;
            text-align: center;
            border: 1px solid #ddd;
            font-size: 14px;
            padding: 0;
            display: none;
        }
        .yprint-mini-cart-item-remove {
            cursor: pointer;
            color: #999;
            font-size: 18px;
            line-height: 1;
            padding: 0 5px;
        }
        .yprint-mini-cart-item-remove:hover {
            color: #0079FF;
        }
        .yprint-mini-cart-subtotal {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            font-weight: 600;
            border-top: 1px solid #eee;
        }
        .yprint-mini-cart-checkout {
            display: block;
            width: 100%;
            background: #0079FF;
            color: #fff;
            border: none;
            padding: 12px 15px;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .yprint-mini-cart-checkout:hover {
            background: #0062cc;
        }
        .yprint-mini-cart-empty {
            text-align: center;
            padding: 20px 0;
            color: #777;
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
        }
        .yprint-loading-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        .yprint-loading-overlay img {
            width: 80px;
            height: auto;
        }
        .yprint-mini-cart {
            position: relative;
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
            $cart_items = WC()->cart->get_cart();
            
            if (empty($cart_items)) {
                echo '<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>';
            } else {
                foreach ($cart_items as $cart_item_key => $cart_item) {
                    $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                    $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
                    
                    if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                        $product_name = $_product->get_name();
                        $thumbnail = $_product->get_image();
                        $product_price = WC()->cart->get_product_price($_product);
                        $product_quantity = $cart_item['quantity'];
                        ?>
                        <div class="yprint-mini-cart-item" data-item-key="<?php echo esc_attr($cart_item_key); ?>">
                            <div class="yprint-mini-cart-item-image">
                                <?php echo $thumbnail; ?>
                            </div>
                            <div class="yprint-mini-cart-item-details">
                                <h4 class="yprint-mini-cart-item-title"><?php echo $product_name; ?></h4>
                                <div class="yprint-mini-cart-item-price"><?php echo $product_price; ?></div>
                                <div class="yprint-mini-cart-item-quantity-control">
                                    <button class="qty-btn qty-minus" data-action="minus">−</button>
                                    <span class="qty-value"><?php echo $product_quantity; ?></span>
                                    <input type="number" class="qty-input" value="<?php echo $product_quantity; ?>" min="1">
                                    <button class="qty-btn qty-plus" data-action="plus">+</button>
                                </div>
                            </div>
                            <div class="yprint-mini-cart-item-remove">×</div>
                        </div>
                        <?php
                    }
                }
            }
            ?>
        </div>
        
        <div class="yprint-mini-cart-subtotal">
            <span>Zwischensumme:</span>
            <span><?php echo WC()->cart->get_cart_subtotal(); ?></span>
        </div>
        
        <a href="https://yprint.de/checkout" class="yprint-mini-cart-checkout">Zur Kasse</a>
    </div>
    
    <script>
    (function($) {
        // Eindeutige ID für diesen Warenkorb
        var cartId = '<?php echo $cart_id; ?>';
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

        // Automatische Aktualisierung beim Öffnen/Anzeigen des Warenkorbs
        function refreshCartContent() {
            // Overlay aktivieren
            $cart.find('.yprint-loading-overlay').addClass('active');
            
            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_refresh_cart_content'
                },
                success: function(response) {
                    if (response.success) {
                        // Cart-Items aktualisieren
                        $cart.find('.yprint-mini-cart-items').html(response.cart_items_html);
                        // Warenkorb-Anzahl aktualisieren
                        $cart.find('.yprint-mini-cart-count').text(response.cart_count);
                        // Zwischensumme aktualisieren
                        $cart.find('.yprint-mini-cart-subtotal span:last-child').html(response.cart_subtotal);
                    }
                    
                    // Overlay deaktivieren
                    $cart.find('.yprint-loading-overlay').removeClass('active');
                },
                error: function() {
                    // Overlay deaktivieren bei Fehlern
                    $cart.find('.yprint-loading-overlay').removeClass('active');
                }
            });
        }

        // Initialisiere die Aktualisierung, wenn der Warenkorb sichtbar wird
        // Dies funktioniert sowohl für Popups als auch normale Einbindungen
        var observer = new IntersectionObserver(function(entries) {
            entries.forEach(function(entry) {
                if (entry.isIntersecting) {
                    // Warenkorb ist jetzt sichtbar
                    refreshCartContent();
                }
            });
        }, { threshold: 0.1 }); // 10% sichtbar reicht aus

        // Beobachte den Warenkorb
        observer.observe($cart[0]);
                
        // Add to Cart Event abfangen, um doppelte Einträge zu verhindern
        $(document.body).on('added_to_cart', function() {
            // Overlay aktivieren
            $cart.find('.yprint-loading-overlay').addClass('active');
            
            // Mehrfach konsolidieren, um sicherzustellen, dass alle doppelten Einträge erwischt werden
            function consolidateCart(attempts) {
                $.ajax({
                    url: wc_add_to_cart_params.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'yprint_consolidate_cart'
                    },
                    success: function(response) {
                        if (response.success) {
                            // Wenn Änderungen vorgenommen wurden und noch Versuche übrig sind, erneut konsolidieren
                            if (response.changes && attempts > 1) {
                                consolidateCart(attempts - 1);
                            } else {
                                // Aktualisiere UI-Elemente nach der Konsolidierung
                                $cart.find('.yprint-mini-cart-count').text(response.cart_count);
                                $cart.find('.yprint-mini-cart-subtotal span:last-child').html(response.cart_subtotal);
                                
                                // Overlay deaktivieren
                                $cart.find('.yprint-loading-overlay').removeClass('active');
                                
                                // Seite neu laden, wenn Änderungen vorgenommen wurden
                                if (response.changes) {
                                    window.location.reload();
                                }
                            }
                        } else {
                            // Overlay deaktivieren
                            $cart.find('.yprint-loading-overlay').removeClass('active');
                        }
                    },
                    error: function() {
                        // Overlay deaktivieren bei Fehlern
                        $cart.find('.yprint-loading-overlay').removeClass('active');
                    }
                });
            }
            
            // Starte mit 2 Versuchen (kann bei Bedarf angepasst werden)
            setTimeout(function() {
                consolidateCart(2);
            }, 200);
        });
                
        // ENTFERNEN-BUTTON
        $cart.on('click', '.yprint-mini-cart-item-remove', function() {
            var $item = $(this).closest('.yprint-mini-cart-item');
            var cartItemKey = $item.data('item-key');
            
            // Overlay aktivieren
            $cart.find('.yprint-loading-overlay').addClass('active');
            
            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_remove_from_cart',
                    cart_item_key: cartItemKey
                },
                success: function(response) {
                    // Artikel visuell entfernen
                    $item.fadeOut(300, function() {
                        $(this).remove();
                        
                        // Warenkorb-Anzahl aktualisieren
                        if (response.cart_count !== undefined) {
                            $cart.find('.yprint-mini-cart-count').text(response.cart_count);
                        }
                        
                        // Zwischensumme aktualisieren
                        if (response.cart_subtotal !== undefined) {
                            $cart.find('.yprint-mini-cart-subtotal span:last-child').html(response.cart_subtotal);
                        }
                        
                        // Leeren Warenkorb zeigen, wenn keine Artikel mehr
                        if (response.cart_count === 0) {
                            $cart.find('.yprint-mini-cart-items').html('<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>');
                        }
                    });
                    
                    // Overlay deaktivieren
                    $cart.find('.yprint-loading-overlay').removeClass('active');
                },
                error: function(xhr, status, error) {
                    console.error('Fehler beim Entfernen:', status, error);
                    $cart.find('.yprint-loading-overlay').removeClass('active');
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
            var currentQty = parseInt($qtyValue.text());
            var newQty = $btn.data('action') === 'minus' ? 
                         Math.max(1, currentQty - 1) : 
                         currentQty + 1;
            
            // Menge sofort aktualisieren
            $qtyValue.text(newQty);
            
            // AJAX-Update der Menge
            updateQuantity(cartItemKey, newQty);
        });
        
        // DIREKTEINGABE DER MENGE
        // Klick auf Menge (zum Bearbeiten)
        $cart.on('click', '.qty-value', function() {
            if (isEditing) return; // Bereits im Bearbeitungsmodus
            
            isEditing = true;
            var $value = $(this);
            var $input = $value.siblings('.qty-input');
            
            // Eingabefeld vorbereiten und anzeigen
            $input.val($value.text());
            $value.hide();
            $input.show().focus().select();
        });
        
        // Eingabefeld verlassen
        $cart.on('blur', '.qty-input', function() {
            var $input = $(this);
            var $item = $input.closest('.yprint-mini-cart-item');
            var $value = $input.siblings('.qty-value');
            var cartItemKey = $item.data('item-key');
            var oldQty = parseInt($value.text());
            var newQty = parseInt($input.val());
            
            // Gültigen Wert sicherstellen
            if (isNaN(newQty) || newQty < 1) newQty = 1;
            
            // UI zurücksetzen
            $input.hide();
            $value.show();
            
            // Bearbeitungsmodus beenden
            isEditing = false;
            
            // Nur bei Änderung aktualisieren
            if (newQty !== oldQty) {
                $value.text(newQty);
                updateQuantity(cartItemKey, newQty);
            }
        });
        
        // Enter-Taste im Eingabefeld
        $cart.on('keypress', '.qty-input', function(e) {
            if (e.which === 13) { // Enter
                e.preventDefault();
                $(this).blur();
            }
        });
        
        // Klick außerhalb schließt Eingabefeld
        $(document).on('mousedown', function(e) {
            if (isEditing) {
                var $target = $(e.target);
                if (!$target.is('.qty-input') && !$target.is('.qty-value')) {
                    $cart.find('.qty-input:visible').blur();
                }
            }
        });
        
        // Funktion zum Aktualisieren der Menge
        function updateQuantity(cartItemKey, quantity) {
            // Overlay aktivieren
            $cart.find('.yprint-loading-overlay').addClass('active');
            
            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_update_cart_quantity',
                    cart_item_key: cartItemKey,
                    quantity: quantity
                },
                success: function(response) {
                    // Zwischensumme aktualisieren
                    if (response.cart_subtotal !== undefined) {
                        $cart.find('.yprint-mini-cart-subtotal span:last-child').html(response.cart_subtotal);
                    }
                    
                    // Overlay deaktivieren
                    $cart.find('.yprint-loading-overlay').removeClass('active');
                },
                error: function(xhr, status, error) {
                    console.error('Fehler beim Aktualisieren:', status, error);
                    $cart.find('.yprint-loading-overlay').removeClass('active');
                }
            });
        }
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
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    
    if ($cart_item_key) {
        WC()->cart->remove_cart_item($cart_item_key);
    }
    
    // Minimale Daten zurückgeben
    wp_send_json(array(
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_subtotal' => WC()->cart->get_cart_subtotal()
    ));
    
    wp_die();
}

/**
 * Ajax-Funktion zum Aktualisieren der Produktmenge
 */
function yprint_update_cart_quantity() {
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if ($cart_item_key && $quantity > 0) {
        WC()->cart->set_quantity($cart_item_key, $quantity);
        
        // Minimale Daten zurückgeben
        wp_send_json(array(
            'cart_subtotal' => WC()->cart->get_cart_subtotal()
        ));
    } else {
        wp_send_json_error('Invalid cart item key or quantity');
    }
    
    wp_die();
}

/**
 * Verhindert doppelte Artikel beim Seitenneuladen
 */
function yprint_cart_loaded_from_session($cart) {
    // Prüfe, ob der Warenkorb Print-Design-Produkte enthält
    $has_print_design = false;
    foreach ($cart->get_cart() as $cart_item) {
        if (isset($cart_item['print_design'])) {
            $has_print_design = true;
            break;
        }
    }
    
    // Konsolidiere den Warenkorb nur, wenn keine Print-Design-Produkte vorhanden sind
    if (!$has_print_design) {
        yprint_consolidate_cart_items();
    }
}

/**
 * AJAX-Handler zur Aktualisierung des Warenkorb-Inhalts
 */
function yprint_refresh_cart_content_callback() {
    ob_start();
    
    // Warenkorb optimieren
    yprint_consolidate_cart_items();
    
    // Warenkorb-Einträge generieren
    $cart_items = WC()->cart->get_cart();
    
    if (empty($cart_items)) {
        echo '<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>';
    } else {
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
            
            if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                $product_name = $_product->get_name();
                $thumbnail = $_product->get_image();
                $product_price = WC()->cart->get_product_price($_product);
                $product_quantity = $cart_item['quantity'];
                ?>
                <div class="yprint-mini-cart-item" data-item-key="<?php echo esc_attr($cart_item_key); ?>">
                    <div class="yprint-mini-cart-item-image">
                        <?php echo $thumbnail; ?>
                    </div>
                    <div class="yprint-mini-cart-item-details">
                        <h4 class="yprint-mini-cart-item-title"><?php echo $product_name; ?></h4>
                        <div class="yprint-mini-cart-item-title"><?php echo $product_name; ?></h4>
                        <div class="yprint-mini-cart-item-price"><?php echo $product_price; ?></div>
                        <div class="yprint-mini-cart-item-quantity-control">
                            <button class="qty-btn qty-minus" data-action="minus">−</button>
                            <span class="qty-value"><?php echo $product_quantity; ?></span>
                            <input type="number" class="qty-input" value="<?php echo $product_quantity; ?>" min="1">
                            <button class="qty-btn qty-plus" data-action="plus">+</button>
                        </div>
                    </div>
                    <div class="yprint-mini-cart-item-remove">×</div>
                </div>
                <?php
            }
        }
    }
    
    $cart_items_html = ob_get_clean();
    
    wp_send_json(array(
        'success' => true,
        'cart_items_html' => $cart_items_html,
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_subtotal' => WC()->cart->get_cart_subtotal()
    ));
}

// Aktionshooks registrieren
add_action('wp_ajax_yprint_consolidate_cart', 'yprint_ajax_consolidate_cart');
add_action('wp_ajax_nopriv_yprint_consolidate_cart', 'yprint_ajax_consolidate_cart');

add_action('wp_ajax_yprint_remove_from_cart', 'yprint_remove_from_cart');
add_action('wp_ajax_nopriv_yprint_remove_from_cart', 'yprint_remove_from_cart');

add_action('wp_ajax_yprint_update_cart_quantity', 'yprint_update_cart_quantity');
add_action('wp_ajax_nopriv_yprint_update_cart_quantity', 'yprint_update_cart_quantity');

add_action('wp_ajax_yprint_refresh_cart_content', 'yprint_refresh_cart_content_callback');
add_action('wp_ajax_nopriv_yprint_refresh_cart_content', 'yprint_refresh_cart_content_callback');

// Add to Cart Hook, um doppelte Artikel direkt zu vermeiden
add_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20, 6);

// Verhindert doppelte Artikel beim Seitenneuladen
add_action('woocommerce_cart_loaded_from_session', 'yprint_cart_loaded_from_session');

// Shortcode registrieren
add_shortcode('yprint_minimalist_cart', 'yprint_minimalist_cart_shortcode');