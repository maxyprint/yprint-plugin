<?php
/**
 * WooCommerce Integration für YPrint
 * 
 * Diese Datei enthält alle WooCommerce-spezifischen Funktionen und Hooks
 * für die YPrint Plugin-Integration.
 */

// Direktaufruf verhindern
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
            border-radius: 15px !important;
            margin-bottom: 15px;
            font-size: 16px;
            transition: all 0.2s ease;
            color: #333;
            box-sizing: border-box;
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
            border-bottom: 1px solid #e0e0e0;
        }

        .yprint-order-info {
            display: grid;
            grid-template-columns: 80px 1fr 100px 100px 120px;
            gap: 20px;
            align-items: center;
            width: 100%;
        }

        .yprint-order-number {
            font-weight: 600;
            color: #1d1d1f;
            font-size: 16px;
        }

        .yprint-order-items {
            color: #666;
        }

        .yprint-order-date {
            color: #999;
            font-size: 14px;
        }

        .yprint-order-status {
            padding: 4px 12px;
            border-radius: 15px;
            font-size: 12px;
            font-weight: 500;
            text-align: center;
            white-space: nowrap;
        }

        .status-pending {
            background-color: #fff3cd;
            color: #856404;
        }

        .status-processing {
            background-color: #cce5ff;
            color: #004085;
        }

        .status-completed {
            background-color: #d4edda;
            color: #155724;
        }

        .status-cancelled {
            background-color: #f8d7da;
            color: #721c24;
        }

        .yprint-order-total {
            font-weight: 600;
            color: #1d1d1f;
            text-align: right;
        }

        .yprint-order-actions {
            display: flex;
            gap: 8px;
        }

        .yprint-btn {
            padding: 6px 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            background: white;
            color: #374151;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .yprint-btn:hover {
            background: #f9fafb;
            border-color: #9ca3af;
            color: #111827;
            text-decoration: none;
        }

        .yprint-btn-primary {
            background: #0079FF;
            border-color: #0079FF;
            color: white;
        }

        .yprint-btn-primary:hover {
            background: #0056b3;
            border-color: #0056b3;
            color: white;
        }

        .yprint-btn-danger {
            background: #dc3545;
            border-color: #dc3545;
            color: white;
        }

        .yprint-btn-danger:hover {
            background: #c82333;
            border-color: #c82333;
            color: white;
        }

        .yprint-empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .yprint-empty-state h3 {
            color: #1d1d1f;
            margin-bottom: 8px;
        }

        @media (max-width: 768px) {
            .yprint-order-info {
                grid-template-columns: 1fr;
                gap: 8px;
            }
            
            .yprint-order-card {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .yprint-order-actions {
                justify-content: flex-start;
            }
        }
    </style>

    <div class="yprint-order-history">
        <div class="yprint-order-content">
            <input 
                type="text" 
                id="order-search" 
                class="yprint-order-search" 
                placeholder="Bestellnummer, Artikel oder Status suchen..."
            >
            
            <?php if (empty($customer_orders)): ?>
                <div class="yprint-empty-state">
                    <h3>Noch keine Bestellungen</h3>
                    <p>Du hast noch keine Bestellungen aufgegeben.</p>
                </div>
            <?php else: ?>
                <div class="yprint-order-list" id="order-list">
                    <?php foreach ($customer_orders as $order): ?>
                        <?php
                        $order_id = $order->get_id();
                        $order_date = $order->get_date_created()->format('d.m.Y');
                        $order_status = $order->get_status();
                        $order_total = $order->get_formatted_order_total();
                        $order_items = $order->get_items();
                        $status_name = wc_get_order_status_name($order_status);
                        $can_cancel = $order->has_status(array('pending', 'on-hold'));
                        
                        // Erstelle Item-Liste
                        $item_names = array();
                        foreach ($order_items as $item) {
                            $item_names[] = $item->get_name();
                        }
                        $items_text = count($item_names) > 2 
                            ? implode(', ', array_slice($item_names, 0, 2)) . ' + ' . (count($item_names) - 2) . ' weitere'
                            : implode(', ', $item_names);
                        ?>
                        <div class="yprint-order-card" data-order-id="<?php echo $order_id; ?>">
                            <div class="yprint-order-info">
                                <div class="yprint-order-number">#<?php echo $order_id; ?></div>
                                <div class="yprint-order-items"><?php echo esc_html($items_text); ?></div>
                                <div class="yprint-order-date"><?php echo esc_html($order_date); ?></div>
                                <div class="yprint-order-status status-<?php echo esc_attr($order_status); ?>">
                                    <?php echo esc_html($status_name); ?>
                                </div>
                                <div class="yprint-order-total"><?php echo $order_total; ?></div>
                            </div>
                            
                            <div class="yprint-order-actions">
                                <a href="<?php echo esc_url($order->get_view_order_url()); ?>" class="yprint-btn">
                                    Details
                                </a>
                                
                                <?php if ($can_cancel): ?>
                                    <button onclick="cancelOrder(<?php echo $order_id; ?>)" class="yprint-btn yprint-btn-danger">
                                        Stornieren
                                    </button>
                                <?php endif; ?>
                                
                                <?php if ($order->has_status('completed')): ?>
                                    <button class="yprint-btn yprint-btn-primary" onclick="alert('Nachbestellung-Feature kommt bald!');">
                                        Erneut bestellen
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('order-search');
        const orderList = document.getElementById('order-list');
        
        if (searchInput && orderList) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const orders = orderList.querySelectorAll('.yprint-order-card');
                
                orders.forEach(function(order) {
                    const orderText = order.textContent.toLowerCase();
                    if (orderText.includes(searchTerm)) {
                        order.style.display = 'grid';
                    } else {
                        order.style.display = 'none';
                    }
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
// Shortcode registrieren
add_shortcode('woocommerce_history', 'woo_order_history');

/**
 * AJAX handler for cancelling an order
 */
function yprint_cancel_order() {
    check_ajax_referer('yprint-order-cancel', 'security');
    
    if (!class_exists('WooCommerce')) {
        wp_send_json_error('WooCommerce ist nicht aktiviert');
        return;
    }
    
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if (!$order_id) {
        wp_send_json_error('Ungültige Bestellungs-ID');
        return;
    }
    
    if (!is_user_logged_in()) {
        wp_send_json_error('Du musst angemeldet sein, um eine Bestellung zu stornieren');
        return;
    }
    
    $order = wc_get_order($order_id);
    
    if (!$order) {
        wp_send_json_error('Bestellung nicht gefunden');
        return;
    }
    
    $current_user_id = get_current_user_id();
    if ($order->get_customer_id() != $current_user_id) {
        wp_send_json_error('Du bist nicht berechtigt, diese Bestellung zu stornieren');
        return;
    }
    
    if (!$order->has_status(array('pending', 'on-hold'))) {
        wp_send_json_error('Diese Bestellung kann nicht mehr storniert werden');
        return;
    }
    
    try {
        $order->update_status('cancelled', 'Vom Kunden storniert');
        wp_send_json_success('Bestellung wurde erfolgreich storniert');
    } catch (Exception $e) {
        wp_send_json_error('Fehler beim Stornieren der Bestellung: ' . $e->getMessage());
    }
}
add_action('wp_ajax_yprint_cancel_order', 'yprint_cancel_order');

/**
 * Minimalistischer Warenkorb-Shortcode
 */
function yprint_minimalist_cart_shortcode() {
    ob_start();

    if (!class_exists('WooCommerce') || is_null(WC()->cart)) {
        return '<p>WooCommerce ist nicht aktiviert.</p>';
    }

    $cart_id = 'yprint-cart-' . uniqid();
    ?>
    <div id="<?php echo esc_attr($cart_id); ?>" class="yprint-minimalist-cart">
        <div class="yprint-cart-items-container" id="<?php echo esc_attr($cart_id); ?>-items">
            <?php
            // Cart-Inhalt wird durch AJAX geladen
            echo 'Lade Warenkorb...';
            ?>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Lade initial Cart-Inhalt
        loadCartContent('<?php echo $cart_id; ?>');
        
        function loadCartContent(cartId) {
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'yprint_refresh_cart_content',
                    cart_id: cartId
                },
                success: function(response) {
                    if (response.success) {
                        $('#' + cartId + '-items').html(response.data.cart_items_html);
                    }
                }
            });
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('yprint_minimalist_cart', 'yprint_minimalist_cart_shortcode');

/**
 * Wird aufgerufen, nachdem ein Produkt zum Warenkorb hinzugefügt wurde
 */
add_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20, 6);
function yprint_after_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    static $is_processing = false;
    
    if ($is_processing) {
        return;
    }
    
    $is_processing = true;
    
    remove_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20);
    
    // Debug-Informationen
    error_log('=== YPRINT ADD TO CART DEBUG ===');
    error_log('Cart Item Key: ' . $cart_item_key);
    error_log('Product ID: ' . $product_id);
    error_log('Cart Item Data: ' . print_r($cart_item_data, true));
    
    $has_print_design = isset($cart_item_data['print_design']);
    
    if (!$has_print_design) {
        yprint_consolidate_cart_items();
    } else {
        $current_cart_item = WC()->cart->get_cart_item($cart_item_key);
        if ($current_cart_item && isset($current_cart_item['print_design'])) {
            WC()->cart->cart_contents[$cart_item_key]['_is_design_product'] = true;
            
            if (isset($current_cart_item['print_design']['design_id'])) {
                $unique_design_key = md5(wp_json_encode($current_cart_item['print_design']));
                WC()->cart->cart_contents[$cart_item_key]['unique_design_key'] = $unique_design_key;
            }
            
            WC()->cart->set_session();
        }
    }
    
    add_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20, 6);
    
    do_action('yprint_cart_updated');
    
    $is_processing = false;
}

/**
 * Konsolidiert identische Artikel im Warenkorb (außer Design-Produkte)
 */
function yprint_consolidate_cart_items() {
    if (!WC()->cart || WC()->cart->is_empty()) {
        return false;
    }

    $cart_contents = WC()->cart->get_cart();
    $consolidated_items = array();
    $items_to_remove = array();
    $changes_made = false;

    foreach ($cart_contents as $cart_item_key => $cart_item) {
        if (isset($cart_item['print_design']) || isset($cart_item['_is_design_product'])) {
            continue;
        }

        $product_id = $cart_item['product_id'];
        $variation_id = $cart_item['variation_id'] ?? 0;
        $variation_data = $cart_item['variation'] ?? array();
        
        $consolidation_key = $product_id . '_' . $variation_id . '_' . md5(serialize($variation_data));

        if (isset($consolidated_items[$consolidation_key])) {
            $existing_key = $consolidated_items[$consolidation_key]['cart_key'];
            $new_quantity = $consolidated_items[$consolidation_key]['quantity'] + $cart_item['quantity'];
            
            WC()->cart->set_quantity($existing_key, $new_quantity, false);
            $items_to_remove[] = $cart_item_key;
            $changes_made = true;
            
            error_log("YPRINT: Consolidated item {$cart_item_key} into {$existing_key}");
        } else {
            $consolidated_items[$consolidation_key] = array(
                'cart_key' => $cart_item_key,
                'quantity' => $cart_item['quantity']
            );
        }
    }

    foreach ($items_to_remove as $key_to_remove) {
        WC()->cart->remove_cart_item($key_to_remove);
    }

    if ($changes_made) {
        WC()->cart->calculate_totals();
    }

    return $changes_made;
}

/**
 * AJAX-Callback für Warenkorb-Konsolidierung
 */
function yprint_ajax_consolidate_cart_callback() {
    $changes = yprint_consolidate_cart_items();
    
    wp_send_json_success(array(
        'changes_made' => $changes,
    ));

    wp_die();
}
add_action('wp_ajax_yprint_consolidate_cart_action', 'yprint_ajax_consolidate_cart_callback');
add_action('wp_ajax_nopriv_yprint_consolidate_cart_action', 'yprint_ajax_consolidate_cart_callback');

/**
 * AJAX-Handler zur Aktualisierung des Warenkorb-Inhalts
 */
function yprint_refresh_cart_content_callback() {
    ob_start();

    if (is_null(WC()->cart)) {
        wp_send_json_error('WooCommerce cart is not initialized.');
        return;
    }

    if (WC()->cart->is_empty()) {
        echo '<p>Dein Warenkorb ist leer.</p>';
    } else {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $product_quantity = $cart_item['quantity'];
            $item_total = WC()->cart->get_product_subtotal($product, $product_quantity);
            $item_total_price_html = wp_strip_all_tags($item_total);
            $product_name = $product->get_name();
            
            $details = array();
            if (isset($cart_item['variation']) && !empty($cart_item['variation'])) {
                foreach ($cart_item['variation'] as $attribute => $value) {
                    if (!empty($value)) {
                        $details[] = ucfirst(str_replace('attribute_', '', $attribute)) . ': ' . $value;
                    }
                }
            }
            
            if (isset($cart_item['print_design'])) {
                $design = $cart_item['print_design'];
                if (isset($design['name']) && !empty($design['name'])) {
                    $details[] = 'Design: ' . $design['name'];
                }
                if (isset($design['variation_name']) && !empty($design['variation_name'])) {
                    $details[] = 'Farbe: ' . $design['variation_name'];
                }
                if (isset($design['size_name']) && !empty($design['size_name'])) {
                    $details[] = 'Größe: ' . $design['size_name'];
                }
            }
            
            ?>
            <div class="yprint-mini-cart-item" data-cart-item-key="<?php echo esc_attr($cart_item_key); ?>">
                <div class="yprint-mini-cart-item-content">
                    <?php
                    if (!empty($details)) {
                        $display_name = $product_name . ' (' . implode(', ', $details) . ')';
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

    $cart_items_html = ob_get_clean();

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
 * KONSOLIDIERTE DESIGN-TRANSFER-FUNKTION
 * Vereint alle Design-Transfer-Mechanismen in einer Funktion
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_unified_design_transfer', 5, 4);
function yprint_unified_design_transfer($item, $cart_item_key, $values, $order) {
    // Log this hook execution
    yprint_log_hook_execution('checkout_create_order_line_item', 
        "Cart Key: $cart_item_key | Product: " . ($values['product_id'] ?? 'unknown'));
    
    $has_design = isset($values['print_design']) && !empty($values['print_design']);
    
    if (!$has_design) {
        error_log("YPrint: No design data for cart item $cart_item_key");
        return $item;
    }
    
    $design_data = $values['print_design'];
    error_log("YPrint: Processing design data for cart item $cart_item_key: " . print_r($design_data, true));
    
    $item->update_meta_data('print_design', $design_data);
    $item->update_meta_data('_yprint_design_transferred', current_time('mysql'));
    $item->update_meta_data('_cart_item_key', $cart_item_key);
    $item->update_meta_data('_is_design_product', true);
    
    foreach ($design_data as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $value = wp_json_encode($value);
        }
        $item->update_meta_data('design_' . $key, $value);
    }
    
    $item->update_meta_data('_design_id', $design_data['design_id'] ?? '');
    $item->update_meta_data('_design_name', $design_data['name'] ?? '');
    $item->update_meta_data('_design_template_id', $design_data['template_id'] ?? '');
    
    error_log("YPrint: Design data successfully added to order item $cart_item_key");
    
    return $item;
}

/**
 * DEBUG: Order-Erstellungs-Prozess verfolgen
 */
add_action('woocommerce_checkout_create_order', 'yprint_debug_order_creation', 5, 2);
function yprint_debug_order_creation($order, $data) {
    error_log("YPrint: Order creation started - Order ID: " . $order->get_id());
    error_log("YPrint: Cart items during order creation: " . WC()->cart->get_cart_contents_count());
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $has_design = isset($cart_item['print_design']);
        error_log("YPrint: Cart item $cart_item_key has design: " . ($has_design ? 'YES' : 'NO'));
    }
    
    yprint_log_hook_execution('checkout_create_order', 
        "Order ID: " . $order->get_id() . " | Cart items: " . WC()->cart->get_cart_contents_count());
}

/**
 * DEBUG: Finale Order-Verifikation
 */
add_action('woocommerce_checkout_order_processed', 'yprint_debug_final_order', 5, 3);
function yprint_debug_final_order($order_id, $posted_data, $order) {
    error_log("YPrint: Final order processed - Order ID: $order_id");
    error_log("YPrint: Order items count: " . count($order->get_items()));
    
    foreach ($order->get_items() as $item_id => $item) {
        $has_design = !empty($item->get_meta('print_design'));
        error_log("YPrint: Order item $item_id has design: " . ($has_design ? 'YES' : 'NO'));
    }
    
    yprint_log_hook_execution('checkout_order_processed', 
        "Final Order ID: $order_id | Items: " . count($order->get_items()));
}

/**
 * Erweiterte Backup-Transfer-Funktion
 */
add_action('woocommerce_new_order', 'yprint_enhanced_backup_transfer', 5, 1);
function yprint_enhanced_backup_transfer($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    yprint_log_hook_execution('new_order_backup_check', "Order ID: $order_id");
    
    $order_items = $order->get_items();
    if (empty($order_items)) {
        error_log("YPrint: Order $order_id has NO ITEMS - this is a critical issue!");
        yprint_log_hook_execution('order_creation_error', "Order has no items!");
        return;
    }
    
    $items_need_design = 0;
    $items_have_design = 0;
    
    foreach ($order_items as $item_id => $item) {
        if (!$item->get_meta('print_design') && !$item->get_meta('_yprint_design_transferred')) {
            $items_need_design++;
        } else {
            $items_have_design++;
        }
    }
    
    error_log("YPrint: Order $order_id - Items needing design: $items_need_design, Items with design: $items_have_design");
    
    if ($items_need_design > 0) {
        $backup_applied = false;
        
        if (WC()->session) {
            $session_backup = WC()->session->get('yprint_express_design_backup');
            if (!empty($session_backup)) {
                $backup_applied = yprint_apply_session_backup($order, $session_backup, 'session_v1');
            }
            
            if (!$backup_applied) {
                $session_backup_v2 = WC()->session->get('yprint_express_design_backup_v2');
                if (!empty($session_backup_v2)) {
                    $backup_applied = yprint_apply_session_backup($order, $session_backup_v2, 'session_v2');
                }
            }
        }
        
        if (!$backup_applied && WC()->session) {
            $transient_backup = get_transient('yprint_express_backup_' . WC()->session->get_customer_id());
            if (!empty($transient_backup)) {
                $backup_applied = yprint_apply_session_backup($order, $transient_backup, 'transient');
            }
        }
        
        yprint_log_hook_execution('backup_transfer_result', 
            "Backup applied: " . ($backup_applied ? 'YES' : 'NO'));
    }
}

/**
 * Hilfsfunktion: Session Backup anwenden
 */
function yprint_apply_session_backup($order, $backup_data, $source) {
    $successful_transfers = 0;
    
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        
        foreach ($backup_data as $backup_key => $backup_info) {
            $backup_design = isset($backup_info['design_data']) ? $backup_info['design_data'] : $backup_info;
            $backup_product_id = isset($backup_info['product_id']) ? $backup_info['product_id'] : null;
            
            if ($backup_product_id == $product_id || ($successful_transfers == 0 && !$item->get_meta('print_design'))) {
                $item->update_meta_data('print_design', $backup_design);
                $item->update_meta_data('_yprint_design_backup_applied', current_time('mysql'));
                $item->update_meta_data('_yprint_backup_source', $source);
                $item->save_meta_data();
                
                $successful_transfers++;
                error_log("YPrint: Applied $source backup to item $item_id (product $product_id)");
                break;
            }
        }
    }
    
    if ($successful_transfers > 0 && WC()->session) {
        WC()->session->__unset('yprint_express_design_backup');
        WC()->session->__unset('yprint_express_design_backup_v2');
    }
    
    return $successful_transfers > 0;
}

/**
 * HOOK EXECUTION TRACKER
 */
function yprint_log_hook_execution($hook_name, $details = '') {
    $hook_log = get_option('yprint_hook_execution_log', array());
    
    $hook_log[] = array(
        'hook' => $hook_name,
        'timestamp' => current_time('mysql'),
        'details' => $details,
        'user_id' => get_current_user_id(),
        'session_id' => WC()->session ? WC()->session->get_customer_id() : 'no_session'
    );
    
    if (count($hook_log) > 50) {
        $hook_log = array_slice($hook_log, -50);
    }
    
    update_option('yprint_hook_execution_log', $hook_log);
}

/**
 * Stellt sicher, dass die print_design-Daten während des Checkouts erhalten bleiben
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_add_design_data_to_order_item', 10, 4);
function yprint_add_design_data_to_order_item($item, $cart_item_key, $values, $order) {
    if (isset($values['print_design']) && !empty($values['print_design'])) {
        $design = $values['print_design'];
        
        error_log("Verarbeite Design-Daten für Bestellung: " . print_r($design, true));
        
        foreach ($design as $meta_key => $meta_value) {
            if (is_array($meta_value) || is_object($meta_value)) {
                $meta_value = wp_json_encode($meta_value);
            }
            $item->add_meta_data('_design_' . $meta_key, $meta_value);
        }
        
        $item->add_meta_data('_design_id', $design['design_id'] ?? '');
        $item->add_meta_data('_design_name', $design['name'] ?? '');
        $item->add_meta_data('_design_color', $design['variation_name'] ?? '');
        $item->add_meta_data('_design_size', $design['size_name'] ?? '');
        $item->add_meta_data('_design_preview_url', $design['preview_url'] ?? '');
        $item->add_meta_data('_design_template_id', $design['template_id'] ?? '');
    }
}

/**
 * Spezielle Funktion zum Persistieren von Design-Daten während des Checkouts
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_preserve_design_data_in_order', 20, 4);
function yprint_preserve_design_data_in_order($item, $cart_item_key, $values, $order) {
    if (isset($values['print_design']) && !empty($values['print_design'])) {
        $item->update_meta_data('print_design', $values['print_design']);
        $item->update_meta_data('_is_design_product', true);
        
        foreach ($values['print_design'] as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = wp_json_encode($value);
            }
            $item->update_meta_data('design_' . $key, $value);
        }
    }
}

/**
 * KRITISCHER FIX: Hook-Registrierung sofort beim Plugin-Load
 */
add_action('init', 'yprint_register_critical_hooks', 1);
function yprint_register_critical_hooks() {
    add_filter('woocommerce_checkout_create_order_line_item', 'yprint_add_design_data_to_order_item', 1, 4);
    add_action('woocommerce_checkout_create_order', 'yprint_backup_design_transfer', 1, 2);
    add_action('woocommerce_new_order', 'yprint_emergency_design_transfer', 1, 2);
    add_action('woocommerce_checkout_order_processed', 'yprint_final_design_transfer', 1, 3);
    
    error_log('YPRINT: Critical hooks registered at init');
}

/**
 * NOTFALL 2: Design-Daten nach Order-Erstellung übertragen
 */
function yprint_emergency_design_transfer($order_id, $order) {
    error_log('=== YPRINT EMERGENCY DESIGN TRANSFER ===');
    error_log('Order ID: ' . $order_id);
    
    if (!$order_id || !$order) {
        error_log('Keine Order bereitgestellt');
        return;
    }
    
    $has_design_data = false;
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('print_design')) {
            $has_design_data = true;
            break;
        }
    }
    
    if ($has_design_data) {
        error_log('Design-Daten bereits vorhanden, Transfer nicht nötig');
        return;
    }
    
    if (!WC()->cart || WC()->cart->is_empty()) {
        error_log('Cart ist leer, kann keine Design-Daten übertragen');
        return;
    }
    
    $cart_contents = WC()->cart->get_cart();
    error_log('Cart-Inhalt Anzahl: ' . count($cart_contents));
    
    foreach ($cart_contents as $cart_item_key => $cart_item) {
        if (isset($cart_item['print_design']) && !empty($cart_item['print_design'])) {
            error_log('TRANSFER: Design-Daten gefunden in Cart-Item: ' . $cart_item_key);
            error_log('TRANSFER: Design-Daten: ' . print_r($cart_item['print_design'], true));
            
            foreach ($order->get_items() as $item_id => $order_item) {
                if ($order_item->get_product_id() == $cart_item['product_id']) {
                    error_log('TRANSFER: Füge Design-Daten zu Order-Item hinzu: ' . $item_id);
                    
                    $design_data = $cart_item['print_design'];
                    $order_item->add_meta_data('print_design', $design_data);
                    $order_item->add_meta_data('_has_print_design', 'yes');
                    $order_item->add_meta_data('_design_id', $design_data['design_id'] ?? '');
                    $order_item->add_meta_data('_design_name', $design_data['name'] ?? '');
                    $order_item->add_meta_data('_design_preview_url', $design_data['preview_url'] ?? '');
                    
                    $order_item->save();
                    $order->save();
                    
                    error_log('TRANSFER: Design-Daten erfolgreich zu Order-Item hinzugefügt: ' . $item_id);
                    break;
                }
            }
        }
    }
}

/**
 * FINAL BACKUP: Nach kompletter Order-Verarbeitung
 */
function yprint_final_design_transfer($order_id, $posted_data, $order) {
    error_log('=== YPRINT FINAL DESIGN TRANSFER ===');
    error_log('Order ID: ' . $order_id);
    
    $has_design_data = false;
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('print_design')) {
            $has_design_data = true;
            break;
        }
    }
    
    if (!$has_design_data) {
        error_log('No design data found in order, attempting final transfer...');
        yprint_emergency_design_transfer($order_id, $order);
    } else {
        error_log('Design data already present in order');
    }
}

/**
 * EXPRESS-CHECKOUT: Spezielle Behandlung für Express-Payments
 */
add_action('yprint_express_order_created', 'yprint_express_design_transfer', 1, 2);
function yprint_express_design_transfer($order_id, $payment_data) {
    error_log('=== YPRINT EXPRESS DESIGN TRANSFER ===');
    error_log('Express Order ID: ' . $order_id);
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    yprint_emergency_design_transfer($order_id, $order);
}

/**
 * EXPRESS ORDER: Design-Daten-Transfer für Express-Orders
 */
add_action('woocommerce_new_order', 'yprint_express_order_design_transfer', 1, 2);
function yprint_express_order_design_transfer($order_id, $order) {
    error_log('=== YPRINT EXPRESS ORDER DESIGN TRANSFER ===');
    error_log('Checking order ' . $order_id . ' for express checkout...');
    
    $express_design_backup = WC()->session->get('yprint_express_design_backup');
    
    if (!empty($express_design_backup)) {
        error_log('EXPRESS: Design-Backup gefunden, übertrage zu Order...');
        
        foreach ($order->get_items() as $item_id => $order_item) {
            $product_id = $order_item->get_product_id();
            
            foreach ($express_design_backup as $cart_key => $design_data) {
                if (isset($design_data['template_id'])) {
                    error_log('EXPRESS: Füge Design-Daten zu Order-Item ' . $item_id . ' hinzu');
                    
                    $order_item->add_meta_data('print_design', $design_data);
                    $order_item->add_meta_data('_has_print_design', 'yes');
                    $order_item->add_meta_data('_design_id', $design_data['design_id'] ?? '');
                    $order_item->add_meta_data('_design_name', $design_data['name'] ?? '');
                    $order_item->add_meta_data('_express_checkout_transfer', 'yes');
                    
                    $order_item->save();
                    
                    error_log('EXPRESS: Design-Daten erfolgreich übertragen!');
                    break;
                }
            }
        }
        
        $order->save();
        
        WC()->session->__unset('yprint_express_design_backup');
        error_log('EXPRESS: Design-Backup aus Session entfernt');
    } else {
        error_log('EXPRESS: Kein Design-Backup gefunden');
    }
}

/**
 * ERWEITERTE DEBUG-AUSGABE für Bestellungs-Erstellung
 */
add_action('woocommerce_checkout_order_processed', 'yprint_debug_order_processed', 1, 3);
function yprint_debug_order_processed($order_id, $posted_data, $order) {
    error_log('=== YPRINT ORDER PROCESSED DEBUG ===');
    error_log('Order ID: ' . $order_id);
    
    foreach ($order->get_items() as $item_id => $item) {
        error_log('Order Item ' . $item_id . ':');
        error_log('  - Product ID: ' . $item->get_product_id());
        error_log('  - Meta Data: ' . print_r($item->get_meta_data(), true));
        error_log('  - Has print_design: ' . ($item->get_meta('print_design') ? 'YES' : 'NO'));
        
        if ($item->get_meta('print_design')) {
            error_log('  - Print Design: ' . print_r($item->get_meta('print_design'), true));
        }
    }
}

/**
 * EMERGENCY BACKUP: Falls Haupthook fehlschlägt
 */
add_action('woocommerce_checkout_order_processed', 'yprint_emergency_design_backup', 1, 3);
function yprint_emergency_design_backup($order_id, $posted_data, $order) {
    error_log('=== YPRINT EMERGENCY BACKUP HOOK ===');
    error_log('Order ID: ' . $order_id);
    
    $hooks_executed = false;
    $design_data_found = false;
    
    $debug_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($debug_file)) {
        $log_content = file_get_contents($debug_file);
        
        $patterns = [
            'EMERGENCY DESIGN TRANSFER',
            'FINAL DESIGN TRANSFER', 
            'Order ID: ' . $order_id,
            'ADDING design data to order item'
        ];
        
        foreach ($patterns as $pattern) {
            if (strpos($log_content, $pattern) !== false) {
                $hooks_executed = true;
                break;
            }
        }
    }
    
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('print_design') || 
            $item->get_meta('_has_print_design') || 
            $item->get_meta('_design_id')) {
            $design_data_found = true;
            break;
        }
    }
    
    $verification_result = array(
        'order_id' => $order_id,
        'hooks_executed' => $hooks_executed,
        'design_data_found' => $design_data_found,
        'debug_file_exists' => file_exists($debug_file),
        'order_status' => $order->get_status()
    );
    
    error_log('Hook verification result: ' . print_r($verification_result, true));
}

/**
 * NOTFALL 3: Finale Design-Daten-Rettung nach Order-Verarbeitung
 */
function yprint_final_design_rescue($order_id, $posted_data, $order) {
    error_log('=== YPRINT FINAL DESIGN RESCUE ===');
    error_log('Order ID: ' . $order_id);
    
    $design_data_found = false;
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('print_design')) {
            $design_data_found = true;
            break;
        }
    }
    
    if (!$design_data_found) {
        error_log('NO DESIGN DATA FOUND - Starting emergency rescue...');
        yprint_emergency_design_transfer($order_id, $order);
    }
}

/**
 * KRITISCHE NOTFALL-HOOKS: Design-Daten retten wenn Standard-Hooks versagen
 */
add_action('plugins_loaded', 'yprint_register_emergency_hooks', 1);
function yprint_register_emergency_hooks() {
    add_action('woocommerce_checkout_create_order', 'yprint_emergency_design_backup', 1, 2);
    add_action('woocommerce_new_order', 'yprint_emergency_design_transfer', 1, 2);
    add_action('woocommerce_checkout_order_processed', 'yprint_final_design_rescue', 1, 3);
    
    error_log('YPRINT: Emergency Design-Transfer-Hooks registriert');
}

/**
 * Backup-Transfer für Express Payments
 */
add_action('woocommerce_new_order', 'yprint_backup_design_transfer', 5, 1);
function yprint_backup_design_transfer($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $items_need_design = false;
    foreach ($order->get_items() as $item) {
        if (!$item->get_meta('print_design') && !$item->get_meta('_yprint_design_transferred')) {
            $items_need_design = true;
            break;
        }
    }
    
    if ($items_need_design && WC()->session) {
        $backup_designs = WC()->session->get('yprint_express_design_backup');
        if (!empty($backup_designs)) {
            error_log("YPrint: Applying backup design transfer for order $order_id");
            
            foreach ($order->get_items() as $item_id => $item) {
                $cart_key = $item->get_meta('_cart_item_key');
                if ($cart_key && isset($backup_designs[$cart_key])) {
                    $item->update_meta_data('print_design', $backup_designs[$cart_key]);
                    $item->update_meta_data('_yprint_design_backup_applied', current_time('mysql'));
                    $item->save_meta_data();
                }
            }
            
            WC()->session->__unset('yprint_express_design_backup');
        }
    }
}

/**
 * Verhindert doppelte Artikel beim Laden aus der Session und nach AJAX-Updates
 */
function yprint_cart_loaded_from_session_and_ajax($cart) {
    if (is_null($cart)) {
        return;
    }

    yprint_consolidate_cart_items();
    $cart->calculate_totals();
}
add_action('woocommerce_cart_loaded_from_session', 'yprint_cart_loaded_from_session_and_ajax', 10);

/**
 * AJAX-Debug-Handler für Cart-Debugging
 */
add_action('wp_ajax_yprint_debug_cart', 'yprint_debug_cart_callback');
add_action('wp_ajax_nopriv_yprint_debug_cart', 'yprint_debug_cart_callback');

function yprint_debug_cart_callback() {
    error_log('=== YPRINT CART DEBUG CALLBACK START ===');
    
    if (!WC()->cart) {
        wp_send_json_error(array('message' => 'Cart not available'));
        return;
    }
    
    $cart_contents = WC()->cart->get_cart();
    $debug_data = array();
    
    foreach ($cart_contents as $cart_item_key => $cart_item) {
        $item_debug = array(
            'key' => $cart_item_key,
            'product_id' => $cart_item['product_id'] ?? null,
            'quantity' => $cart_item['quantity'] ?? null,
            'has_print_design' => isset($cart_item['print_design']),
            'print_design_keys' => isset($cart_item['print_design']) ? 
                array_keys($cart_item['print_design']) : array(),
            'all_keys' => array_keys($cart_item)
        );
        
        if (isset($cart_item['print_design'])) {
            $item_debug['print_design_full'] = $cart_item['print_design'];
            error_log('DESIGN DATA FOUND: ' . print_r($cart_item['print_design'], true));
        }
        
        $debug_data[$cart_item_key] = $item_debug;
        error_log('Cart Item ' . $cart_item_key . ': ' . print_r($item_debug, true));
    }
    
    wp_send_json_success(array(
        'cart_contents' => $debug_data,
        'total_items' => count($cart_contents),
        'session_data' => WC()->session->get_session_data()
    ));
}

/**
 * Mengen-Update für Warenkorb-Artikel
 */
function yprint_update_cart_quantity() {
    $cart_item_key = sanitize_text_field($_POST['cart_item_key'] ?? '');
    $quantity = intval($_POST['quantity'] ?? 1);

    if (empty($cart_item_key) || $quantity < 1) {
        wp_send_json_error(array('message' => 'Ungültige Parameter'));
        return;
    }

    try {
        $updated = WC()->cart->set_quantity($cart_item_key, $quantity);
        
        if ($updated) {
            WC()->cart->calculate_totals();
            
            wp_send_json_success(array(
                'message' => 'Menge erfolgreich aktualisiert',
                'cart_count' => WC()->cart->get_cart_contents_count(),
                'cart_total' => WC()->cart->get_cart_total()
            ));
        } else {
            wp_send_json_error(array('message' => 'Fehler beim Aktualisieren der Menge'));
        }
    } catch (Exception $e) {
        error_log('YPrint Cart Quantity Update Error: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Ein Fehler ist aufgetreten'));
    }
}
add_action('wp_ajax_yprint_update_cart_quantity', 'yprint_update_cart_quantity');
add_action('wp_ajax_nopriv_yprint_update_cart_quantity', 'yprint_update_cart_quantity');

/**
 * DEBUG: Recent Debug-Logs abrufen
 */
add_action('wp_ajax_yprint_get_recent_debug_logs', 'yprint_get_recent_debug_logs');
add_action('wp_ajax_nopriv_yprint_get_recent_debug_logs', 'yprint_get_recent_debug_logs');

function yprint_get_recent_debug_logs() {
    $debug_file = WP_CONTENT_DIR . '/debug.log';
    
    if (!file_exists($debug_file)) {
        wp_send_json_success(array('logs' => array()));
        return;
    }
    
    $lines = array();
    $file = file($debug_file);
    if ($file) {
        $lines = array_slice($file, -20);
        
        $yprint_logs = array();
        foreach ($lines as $line) {
            if (strpos($line, 'YPRINT') !== false || 
                strpos($line, 'DESIGN') !== false || 
                strpos($line, 'EMERGENCY') !== false) {
                $yprint_logs[] = trim($line);
            }
        }
        
        wp_send_json_success(array('logs' => $yprint_logs));
    } else {
        wp_send_json_success(array('logs' => array()));
    }
}

/**
 * DEBUG: Hook-Verifikation für Order
 */
add_action('wp_ajax_yprint_verify_order_hooks', 'yprint_verify_order_hooks');
add_action('wp_ajax_nopriv_yprint_verify_order_hooks', 'yprint_verify_order_hooks');

function yprint_verify_order_hooks() {
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    
    if (!$order_id) {
        wp_send_json_error('Keine Order ID');
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order nicht gefunden');
        return;
    }
    
    error_log('=== YPRINT HOOK VERIFICATION FOR ORDER ' . $order_id . ' ===');
    
    $hooks_executed = false;
    $design_data_found = false;
    
    $debug_file = WP_CONTENT_DIR . '/debug.log';
    if (file_exists($debug_file)) {
        $log_content = file_get_contents($debug_file);
        
        $patterns = [
            'EMERGENCY DESIGN TRANSFER',
            'FINAL DESIGN TRANSFER', 
            'Order ID: ' . $order_id,
            'ADDING design data to order item'
        ];
        
        foreach ($patterns as $pattern) {
            if (strpos($log_content, $pattern) !== false) {
                $hooks_executed = true;
                break;
            }
        }
    }
    
    foreach ($order->get_items() as $item) {
        if ($item->get_meta('print_design') || 
            $item->get_meta('_has_print_design') || 
            $item->get_meta('_design_id')) {
            $design_data_found = true;
            break;
        }
    }
    
    $verification_result = array(
        'order_id' => $order_id,
        'hooks_executed' => $hooks_executed,
        'design_data_found' => $design_data_found,
        'debug_file_exists' => file_exists($debug_file),
        'order_status' => $order->get_status()
    );
    
    error_log('Hook verification result: ' . print_r($verification_result, true));
    
    wp_send_json_success($verification_result);
}

/**
 * Fügt Design-Details zum Produktnamen im Warenkorb hinzu
 */
add_filter('woocommerce_cart_item_name', 'yprint_add_design_to_cart_item_name', 10, 3);
function yprint_add_design_to_cart_item_name($name, $cart_item, $cart_item_key) {
    if (!isset($cart_item['print_design'])) {
        return $name;
    }
    
    $design = $cart_item['print_design'];
    
    if (!empty($design['name'])) {
        $name .= '<br><span class="design-name">Design: ' . esc_html($design['name']) . '</span>';
    }
    
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
    
    return $name;
}

/**
 * Get orders for a specific customer
 */
add_action('wp_ajax_yprint_get_customer_orders', 'yprint_get_customer_orders');
add_action('wp_ajax_nopriv_yprint_get_customer_orders', 'yprint_get_customer_orders');

function yprint_get_customer_orders() {
    $current_user_id = get_current_user_id();
    
    if (!$current_user_id) {
        wp_send_json_error('User not logged in');
        return;
    }
    
    $customer_orders = wc_get_orders(array(
        'customer' => $current_user_id,
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ));
    
    $order_data = array();
    foreach ($customer_orders as $order) {
        $order_info = array(
            'id' => $order->get_id(),
            'status' => $order->get_status(),
            'total' => $order->get_formatted_order_total(),
            'date' => $order->get_date_created()->format('Y-m-d H:i:s'),
            'items' => array()
        );
        
        foreach ($order->get_items() as $item) {
            $order_info['items'][] = array(
                'name' => $item->get_name(),
                'quantity' => $item->get_quantity(),
                'total' => $item->get_total()
            );
        }
        
        $order_data[] = $order_info;
        error_log('Customer order data: ' . print_r($order_info, true));
    }
    
    wp_send_json_success(array(
        'orders' => $order_data,
        'current_user_id' => $current_user_id,
        'total_found' => count($order_data)
    ));
}

/**
 * Helper: Log-Speicherung für Debug-Zwecke
 */
function yprint_store_debug_log($message) {
    $logs = get_option('yprint_debug_logs', array());
    
    $logs[] = '[' . current_time('mysql') . '] ' . $message;
    
    if (count($logs) > 100) {
        $logs = array_slice($logs, -100);
    }
    
    update_option('yprint_debug_logs', $logs);
}

/**
 * Schlanke, nicht-störende Debug-Hooks
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_silent_design_transfer', 10, 4);
function yprint_silent_design_transfer($item, $cart_item_key, $values, $order) {
    if (isset($values['print_design']) && !empty($values['print_design'])) {
        $item->update_meta_data('print_design', $values['print_design']);
        $item->update_meta_data('_yprint_design_transferred', current_time('mysql'));
        
        error_log("YPrint: Design transferred for item $cart_item_key");
    }
    
    return $item;
}

/**
 * Tracked Design Transfer with Hook Execution Logging
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_tracked_design_transfer', 10, 4);
function yprint_tracked_design_transfer($item, $cart_item_key, $values, $order) {
    $has_design = isset($values['print_design']) && !empty($values['print_design']);
    
    yprint_log_hook_execution('checkout_create_order_line_item', 
        "Cart Key: $cart_item_key | Has Design: " . ($has_design ? 'YES' : 'NO'));
    
    if ($has_design) {
        $item->update_meta_data('print_design', $values['print_design']);
        $item->update_meta_data('_yprint_design_transferred', current_time('mysql'));
        $item->update_meta_data('_cart_item_key', $cart_item_key);
        
        error_log("YPrint: Design transferred for item $cart_item_key via standard hook");
    }
    
    return $item;
}

/**
 * Backup-Transfer mit Tracking
 */
add_action('woocommerce_new_order', 'yprint_tracked_backup_transfer', 5, 1);
function yprint_tracked_backup_transfer($order_id) {
    yprint_log_hook_execution('new_order_backup_check', "Order ID: $order_id");
    
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $items_need_design = false;
    $missing_items = array();
    
    foreach ($order->get_items() as $item_id => $item) {
        if (!$item->get_meta('print_design') && !$item->get_meta('_yprint_design_transferred')) {
            $items_need_design = true;
            $missing_items[] = $item_id;
        }
    }
    
    if ($items_need_design && WC()->session) {
        $backup_designs = WC()->session->get('yprint_express_design_backup');
        
        yprint_log_hook_execution('backup_transfer_attempt', 
            "Missing Items: " . implode(',', $missing_items) . 
            " | Backup Available: " . (!empty($backup_designs) ? 'YES' : 'NO'));
        
        if (!empty($backup_designs)) {
            $successful_transfers = 0;
            
            foreach ($order->get_items() as $item_id => $item) {
                $cart_key = $item->get_meta('_cart_item_key');
                
                if ($cart_key && isset($backup_designs[$cart_key])) {
                    $item->update_meta_data('print_design', $backup_designs[$cart_key]);
                    $item->update_meta_data('_yprint_design_backup_applied', current_time('mysql'));
                    $item->save_meta_data();
                    $successful_transfers++;
                    
                    error_log("YPrint: Backup design applied to item $item_id (cart key: $cart_key)");
                } else {
                    $product_id = $item->get_product_id();
                    foreach ($backup_designs as $backup_key => $backup_design) {
                        error_log("YPrint: Could not match item $item_id (product $product_id) to backup designs");
                        break;
                    }
                }
            }
            
            yprint_log_hook_execution('backup_transfer_result', 
                "Successful Transfers: $successful_transfers of " . count($missing_items));
            
            if ($successful_transfers > 0) {
                WC()->session->__unset('yprint_express_design_backup');
            }
        }
    }
}

/**
 * WooCommerce Custom Fields für Produkte
 */
add_action('woocommerce_product_options_general_product_data', 'yprint_add_custom_general_fields');
function yprint_add_custom_general_fields() {
    global $woocommerce, $post;
    
    echo '<div class="options_group">';
    
    woocommerce_wp_checkbox(
        array(
            'id' => '_is_design_product',
            'wrapper_class' => 'show_if_simple',
            'label' => __('Design-Produkt', 'yprint'),
            'description' => __('Aktiviere diese Option, wenn dieses Produkt individuell designt werden kann.', 'yprint')
        )
    );
    
    woocommerce_wp_text_input(
        array(
            'id' => '_design_template_id',
            'label' => __('Design Template ID', 'yprint'),
            'placeholder' => 'z.B. 3657',
            'desc_tip' => 'true',
            'description' => __('Die Template ID aus dem Octo Print Designer für dieses Produkt.', 'yprint')
        )
    );
    
    echo '</div>';
}

/**
 * Speichere Custom Fields für WooCommerce Produkte
 */
add_action('woocommerce_process_product_meta', 'yprint_save_custom_general_fields');
function yprint_save_custom_general_fields($post_id) {
    $is_design_product = isset($_POST['_is_design_product']) ? 'yes' : 'no';
    update_post_meta($post_id, '_is_design_product', $is_design_product);
    
    $template_id = isset($_POST['_design_template_id']) ? sanitize_text_field($_POST['_design_template_id']) : '';
    update_post_meta($post_id, '_design_template_id', $template_id);
}

/**
 * Zeige Design-Informationen in der Produktliste (Admin)
 */
add_filter('manage_edit-product_columns', 'yprint_add_product_columns');
function yprint_add_product_columns($columns) {
    $new_columns = array();
    
    foreach ($columns as $key => $column) {
        $new_columns[$key] = $column;
        
        if ($key === 'name') {
            $new_columns['design_product'] = __('Design-Produkt', 'yprint');
        }
    }
    
    return $new_columns;
}

add_action('manage_product_posts_custom_column', 'yprint_render_product_columns', 2);
function yprint_render_product_columns($column) {
    global $post;
    
    switch ($column) {
        case 'design_product':
            $is_design_product = get_post_meta($post->ID, '_is_design_product', true);
            $template_id = get_post_meta($post->ID, '_design_template_id', true);
            
            if ($is_design_product === 'yes') {
                echo '<span style="color: green; font-weight: bold;">✓ Ja</span>';
                if ($template_id) {
                    echo '<br><small>Template: ' . esc_html($template_id) . '</small>';
                }
            } else {
                echo '<span style="color: #999;">– Nein</span>';
            }
            break;
    }
}

/**
 * Mini-Cart HTML für AJAX-Updates
 */
function yprint_get_mini_cart_html() {
    ob_start();
    
    if (WC()->cart->is_empty()) {
        echo '<div class="mini-cart-empty">';
        echo '<p>Dein Warenkorb ist leer</p>';
        echo '</div>';
    } else {
        echo '<div class="mini-cart-items">';
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $product_id = $cart_item['product_id'];
            $quantity = $cart_item['quantity'];
            $price = WC()->cart->get_product_price($product);
            $subtotal = WC()->cart->get_product_subtotal($product, $quantity);
            
            echo '<div class="mini-cart-item" data-key="' . esc_attr($cart_item_key) . '">';
            echo '<div class="item-details">';
            echo '<h4>' . esc_html($product->get_name()) . '</h4>';
            
            if (isset($cart_item['print_design'])) {
                $design = $cart_item['print_design'];
                echo '<div class="design-details">';
                if (isset($design['name'])) {
                    echo '<span class="design-name">Design: ' . esc_html($design['name']) . '</span>';
                }
                if (isset($design['variation_name'])) {
                    echo '<span class="design-variation"> | ' . esc_html($design['variation_name']) . '</span>';
                }
                echo '</div>';
            }
            
            echo '<div class="item-meta">';
            echo '<span class="quantity">Menge: ' . $quantity . '</span>';
            echo '<span class="price">' . $subtotal . '</span>';
            echo '</div>';
            echo '</div>';
            
            echo '<button class="remove-item" data-key="' . esc_attr($cart_item_key) . '">×</button>';
            echo '</div>';
        }
        
        echo '</div>';
        
        echo '<div class="mini-cart-totals">';
        echo '<div class="total-line">';
        echo '<span>Zwischensumme:</span>';
        echo '<span>' . WC()->cart->get_cart_subtotal() . '</span>';
        echo '</div>';
        echo '</div>';
    }
    
    return ob_get_clean();
}

/**
 * AJAX Handler für Mini-Cart Updates
 */
add_action('wp_ajax_yprint_update_mini_cart', 'yprint_update_mini_cart_callback');
add_action('wp_ajax_nopriv_yprint_update_mini_cart', 'yprint_update_mini_cart_callback');

function yprint_update_mini_cart_callback() {
    if (!WC()->cart) {
        wp_send_json_error('Cart not available');
        return;
    }
    
    WC()->cart->calculate_totals();
    
    $html = yprint_get_mini_cart_html();
    
    wp_send_json_success(array(
        'html' => $html,
        'count' => WC()->cart->get_cart_contents_count(),
        'total' => WC()->cart->get_cart_total(),
        'subtotal' => WC()->cart->get_cart_subtotal()
    ));
}

/**
 * AJAX Handler für Warenkorb-Item entfernen
 */
add_action('wp_ajax_yprint_remove_cart_item', 'yprint_remove_cart_item_callback');
add_action('wp_ajax_nopriv_yprint_remove_cart_item', 'yprint_remove_cart_item_callback');

function yprint_remove_cart_item_callback() {
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    
    if (empty($cart_item_key)) {
        wp_send_json_error('Invalid cart item key');
        return;
    }
    
    $removed = WC()->cart->remove_cart_item($cart_item_key);
    
    if ($removed) {
        WC()->cart->calculate_totals();
        
        wp_send_json_success(array(
            'message' => 'Item removed successfully',
            'cart_html' => yprint_get_mini_cart_html(),
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_cart_total()
        ));
    } else {
        wp_send_json_error('Failed to remove item');
    }
}

/**
 * AJAX Handler für Warenkorb-Mengen-Update
 */
add_action('wp_ajax_yprint_update_cart_quantity', 'yprint_update_cart_quantity_callback');
add_action('wp_ajax_nopriv_yprint_update_cart_quantity', 'yprint_update_cart_quantity_callback');

function yprint_update_cart_quantity_callback() {
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
    $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 1;
    
    if (empty($cart_item_key) || $quantity < 1) {
        wp_send_json_error('Invalid parameters');
        return;
    }
    
    $updated = WC()->cart->set_quantity($cart_item_key, $quantity);
    
    if ($updated) {
        WC()->cart->calculate_totals();
        
        wp_send_json_success(array(
            'message' => 'Quantity updated successfully',
            'cart_html' => yprint_get_mini_cart_html(),
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_total' => WC()->cart->get_cart_total()
        ));
    } else {
        wp_send_json_error('Failed to update quantity');
    }
}

/**
 * Erweiterte Order Meta Anzeige im Admin
 */
add_action('woocommerce_admin_order_data_after_order_details', 'yprint_display_admin_order_meta');
function yprint_display_admin_order_meta($order) {
    echo '<div class="yprint-order-meta" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">';
    echo '<h3>YPrint Order Information</h3>';
    
    $has_design_products = false;
    foreach ($order->get_items() as $item_id => $item) {
        $design_data = $item->get_meta('print_design');
        if (!empty($design_data)) {
            $has_design_products = true;
            
            echo '<div style="margin-bottom: 15px; padding: 10px; background: white; border-left: 4px solid #2271b1;">';
            echo '<h4>Design Item: ' . esc_html($item->get_name()) . '</h4>';
            
            if (is_array($design_data)) {
                echo '<ul>';
                foreach ($design_data as $key => $value) {
                    if (!is_array($value) && !is_object($value)) {
                        echo '<li><strong>' . esc_html($key) . ':</strong> ' . esc_html($value) . '</li>';
                    }
                }
                echo '</ul>';
                
                if (isset($design_data['preview_url'])) {
                    echo '<p><strong>Preview:</strong> <a href="' . esc_url($design_data['preview_url']) . '" target="_blank">Vorschau öffnen</a></p>';
                }
            } else {
                echo '<p>Design Data: ' . esc_html($design_data) . '</p>';
            }
            echo '</div>';
        }
    }
    
    if (!$has_design_products) {
        echo '<p style="color: #666;">Keine Design-Produkte in dieser Bestellung gefunden.</p>';
    }
    
    echo '</div>';
}

/**
 * Checkout-spezifische Funktionen
 */
add_action('wp_enqueue_scripts', 'yprint_checkout_scripts');
function yprint_checkout_scripts() {
    if (is_page('checkout') || is_cart()) {
        wp_enqueue_script('yprint-checkout', YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js', array('jquery'), YPRINT_VERSION, true);
        wp_localize_script('yprint-checkout', 'yprint_checkout_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yprint_checkout_nonce')
        ));
    }
}

/**
 * Design-Daten in Bestellbestätigung anzeigen
 */
add_action('woocommerce_order_item_meta_end', 'yprint_display_design_info_in_order', 10, 4);
function yprint_display_design_info_in_order($item_id, $item, $order, $plain_text) {
    $design_data = $item->get_meta('print_design');
    
    if (!empty($design_data) && is_array($design_data)) {
        if ($plain_text) {
            echo "\n" . __('Design Information:', 'yprint') . "\n";
            if (isset($design_data['name'])) {
                echo __('Design Name:', 'yprint') . ' ' . $design_data['name'] . "\n";
            }
            if (isset($design_data['design_id'])) {
                echo __('Design ID:', 'yprint') . ' ' . $design_data['design_id'] . "\n";
            }
        } else {
            echo '<div class="yprint-design-info" style="margin-top: 10px; padding: 10px; background: #f8f8f8; border-radius: 4px;">';
            echo '<strong>' . __('Design Information:', 'yprint') . '</strong><br>';
            
            if (isset($design_data['name'])) {
                echo '<span style="color: #666;">' . __('Design:', 'yprint') . '</span> ' . esc_html($design_data['name']) . '<br>';
            }
            
            if (isset($design_data['variation_name'])) {
                echo '<span style="color: #666;">' . __('Farbe:', 'yprint') . '</span> ' . esc_html($design_data['variation_name']) . '<br>';
            }
            
            if (isset($design_data['size_name'])) {
                echo '<span style="color: #666;">' . __('Größe:', 'yprint') . '</span> ' . esc_html($design_data['size_name']) . '<br>';
            }
            
            if (isset($design_data['preview_url'])) {
                echo '<a href="' . esc_url($design_data['preview_url']) . '" target="_blank" style="color: #2271b1;">Vorschau anzeigen</a>';
            }
            
            echo '</div>';
        }
    }
}

/**
 * Zusätzliche Sicherheitsprüfungen für Checkout
 */
add_action('woocommerce_checkout_process', 'yprint_validate_checkout_design_data');
function yprint_validate_checkout_design_data() {
    if (!WC()->cart) {
        return;
    }
    
    $design_products_without_data = 0;
    
    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $is_design_product = get_post_meta($product_id, '_is_design_product', true);
        
        if ($is_design_product === 'yes' && !isset($cart_item['print_design'])) {
            $design_products_without_data++;
        }
    }
    
    if ($design_products_without_data > 0) {
        wc_add_notice(
            sprintf(
                __('Es befinden sich %d Design-Produkte ohne Design-Daten in Ihrem Warenkorb. Bitte gehen Sie zurück und erstellen Sie Designs für alle Produkte.', 'yprint'),
                $design_products_without_data
            ),
            'error'
        );
    }
}

/**
 * Session-Cleanup nach erfolgreicher Bestellung
 */
add_action('woocommerce_thankyou', 'yprint_cleanup_after_order', 10, 1);
function yprint_cleanup_after_order($order_id) {
    if (!$order_id || !WC()->session) {
        return;
    }
    
    WC()->session->__unset('yprint_express_design_backup');
    WC()->session->__unset('yprint_express_design_backup_v2');
    
    $customer_id = WC()->session->get_customer_id();
    if ($customer_id) {
        delete_transient('yprint_express_backup_' . $customer_id);
    }
    
    error_log("YPrint: Session cleanup completed for order $order_id");
}

/**
 * Debug-Informationen für Entwickler in Checkout
 */
if (defined('WP_DEBUG') && WP_DEBUG && current_user_can('administrator')) {
    add_action('wp_footer', 'yprint_debug_checkout_info');
}

function yprint_debug_checkout_info() {
    if (!is_checkout() && !is_cart()) {
        return;
    }
    
    if (!WC()->cart || WC()->cart->is_empty()) {
        return;
    }
    
    echo '<div id="yprint-debug-info" style="position: fixed; bottom: 10px; right: 10px; background: #000; color: #fff; padding: 10px; border-radius: 5px; font-size: 12px; max-width: 300px; z-index: 9999;">';
    echo '<strong>YPrint Debug Info:</strong><br>';
    echo 'Cart Items: ' . WC()->cart->get_cart_contents_count() . '<br>';
    
    $design_items = 0;
    foreach (WC()->cart->get_cart() as $cart_item) {
        if (isset($cart_item['print_design'])) {
            $design_items++;
        }
    }
    
    echo 'Design Items: ' . $design_items . '<br>';
    echo 'Session ID: ' . (WC()->session ? WC()->session->get_customer_id() : 'N/A') . '<br>';
    echo '<button onclick="this.parentElement.style.display=\'none\'" style="background: #fff; color: #000; border: none; padding: 2px 5px; margin-top: 5px;">Hide</button>';
    echo '</div>';
}

?>