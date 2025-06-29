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
 * ENHANCED DESIGN TRANSFER - Zieht vollständige Daten aus DB-Tabelle
 */
function yprint_tracked_design_transfer($item, $cart_item_key, $values, $order) {
    global $wpdb;
    
    error_log('=== YPRINT ENHANCED DESIGN TRANSFER ===');
    error_log('Hook: woocommerce_checkout_create_order_line_item');
    error_log('Cart Item Key: ' . $cart_item_key);
    error_log('Order ID: ' . $order->get_id());
    
    if (isset($values['print_design']) && !empty($values['print_design'])) {
        $design_data = $values['print_design'];
        $design_id = $design_data['design_id'] ?? null;
        
        error_log('ENHANCED: Design ID found: ' . $design_id);
        
        // VOLLSTÄNDIGE DATEN AUS DATENBANK ZIEHEN
        if ($design_id) {
            $db_design = $wpdb->get_row($wpdb->prepare(
                "SELECT id, user_id, template_id, name, design_data, created_at, product_name, product_description 
                 FROM deo6_octo_user_designs 
                 WHERE id = %d",
                $design_id
            ), ARRAY_A);
            
            if ($db_design) {
                error_log('ENHANCED: Database design found, processing...');
                
                // Parse JSON design_data
                $parsed_design_data = json_decode($db_design['design_data'], true);
                
                if (json_last_error() === JSON_ERROR_NONE && $parsed_design_data) {
                    error_log('ENHANCED: JSON design data parsed successfully');
                    
                    // BASIS META-DATEN (Bestehend)
                    $item->update_meta_data('print_design', $design_data);
                    $item->update_meta_data('_is_design_product', true);
                    $item->update_meta_data('_has_print_design', 'yes');
                    $item->update_meta_data('_design_id', $design_id);
                    $item->update_meta_data('_design_name', $db_design['name']);
                    
                    // ERWEITERTE DATENBANK-DATEN
                    $item->update_meta_data('_db_design_template_id', $db_design['template_id']);
                    $item->update_meta_data('_db_design_user_id', $db_design['user_id']);
                    $item->update_meta_data('_db_design_created_at', $db_design['created_at']);
                    $item->update_meta_data('_db_design_product_name', $db_design['product_name']);
                    $item->update_meta_data('_db_design_product_description', $db_design['product_description']);
                    
                    // PARSED DESIGN DATA (Full JSON)
                    $item->update_meta_data('_db_design_raw_json', $db_design['design_data']);
                    $item->update_meta_data('_db_design_parsed_data', wp_json_encode($parsed_design_data));
                    
                    // TEMPLATE INFO
                    if (isset($parsed_design_data['templateId'])) {
                        $item->update_meta_data('_db_template_id', $parsed_design_data['templateId']);
                    }
                    if (isset($parsed_design_data['currentVariation'])) {
                        $item->update_meta_data('_db_current_variation', $parsed_design_data['currentVariation']);
                    }
                    
                    // VARIATION IMAGES - VOLLSTÄNDIGE VERARBEITUNG
                    if (isset($parsed_design_data['variationImages'])) {
                        $variation_images = $parsed_design_data['variationImages'];
                        $processed_views = array();
                        
                        error_log('ENHANCED: Processing ' . count($variation_images) . ' variation images');
                        
                        foreach ($variation_images as $view_key => $images) {
                            $view_parts = explode('_', $view_key);
                            $variation_id = $view_parts[0] ?? '';
                            $view_system_id = $view_parts[1] ?? '';
                            
                            $view_data = array(
                                'view_key' => $view_key,
                                'variation_id' => $variation_id,
                                'system_id' => $view_system_id,
                                'view_name' => yprint_get_view_name_by_system_id($view_system_id),
                                'images' => array()
                            );
                            
                            foreach ($images as $image_index => $image) {
                                $image_data = array(
                                    'id' => $image['id'] ?? '',
                                    'url' => $image['url'] ?? '',
                                    'filename' => basename($image['url'] ?? ''),
                                    'transform' => $image['transform'] ?? array(),
                                    'visible' => $image['visible'] ?? true
                                );
                                
                                // BERECHNE PRINT-DIMENSIONEN
                                if (isset($image['transform'])) {
                                    $transform = $image['transform'];
                                    $original_width = $transform['width'] ?? 0;
                                    $original_height = $transform['height'] ?? 0;
                                    $scale_x = $transform['scaleX'] ?? 0;
                                    $scale_y = $transform['scaleY'] ?? 0;
                                    
                                    // Pixel zu mm Konvertierung (96 DPI Standard)
                                    $image_data['print_width_mm'] = round(($original_width * $scale_x) * 0.26458333, 2);
                                    $image_data['print_height_mm'] = round(($original_height * $scale_y) * 0.26458333, 2);
                                    $image_data['scale_percent'] = round($scale_x * 100, 2);
                                    $image_data['position_left'] = $transform['left'] ?? 0;
                                    $image_data['position_top'] = $transform['top'] ?? 0;
                                    $image_data['angle'] = $transform['angle'] ?? 0;
                                }
                                
                                $view_data['images'][] = $image_data;
                            }
                            
                            $processed_views[$view_key] = $view_data;
                        }
                        
                        // SPEICHERE PROCESSED VIEWS
                        $item->update_meta_data('_db_processed_views', wp_json_encode($processed_views));
                        $item->update_meta_data('_db_view_count', count($processed_views));
                        
                        // SEPARATE VIEW META FIELDS für einfachen Zugriff
                        $view_counter = 1;
                        foreach ($processed_views as $view_key => $view_data) {
                            $item->update_meta_data("_view_{$view_counter}_key", $view_key);
                            $item->update_meta_data("_view_{$view_counter}_name", $view_data['view_name']);
                            $item->update_meta_data("_view_{$view_counter}_system_id", $view_data['system_id']);
                            $item->update_meta_data("_view_{$view_counter}_variation_id", $view_data['variation_id']);
                            $item->update_meta_data("_view_{$view_counter}_image_count", count($view_data['images']));
                            $item->update_meta_data("_view_{$view_counter}_data", wp_json_encode($view_data));
                            $view_counter++;
                        }
                        
                        error_log('ENHANCED: Processed and saved ' . count($processed_views) . ' views');
                    }
                    
                    // PRINT PROVIDER READINESS CHECK
                    $print_ready = true;
                    $print_issues = array();
                    
                    if (empty($design_id)) {
                        $print_ready = false;
                        $print_issues[] = 'Missing Design ID';
                    }
                    if (empty($db_design['name'])) {
                        $print_ready = false;
                        $print_issues[] = 'Missing Design Name';
                    }
                    if (!isset($parsed_design_data['variationImages']) || empty($parsed_design_data['variationImages'])) {
                        $print_ready = false;
                        $print_issues[] = 'No Variation Images';
                    }
                    
                    $item->update_meta_data('_print_provider_ready', $print_ready ? 'yes' : 'no');
                    $item->update_meta_data('_print_provider_issues', implode('; ', $print_issues));
                    
                    // TRANSFER TRACKING
                    $item->update_meta_data('_enhanced_transfer', 'yes');
                    $item->update_meta_data('_transfer_timestamp', current_time('mysql'));
                    $item->update_meta_data('_cart_item_key', $cart_item_key);
                    
                    error_log('ENHANCED: All design data successfully saved with database integration');
                    
                    // Hook für weitere Verarbeitung
                    do_action('yprint_enhanced_design_transferred', $item, $design_id, $db_design, $parsed_design_data);
                    
                    return true;
                } else {
                    error_log('ENHANCED: Failed to parse JSON design data: ' . json_last_error_msg());
                }
            } else {
                error_log('ENHANCED: Design not found in database for ID: ' . $design_id);
            }
        }
        
        // FALLBACK: Standard-Transfer wenn DB-Transfer fehlschlägt
        error_log('ENHANCED: Falling back to standard transfer');
        $item->update_meta_data('print_design', $design_data);
        $item->update_meta_data('_is_design_product', true);
        $item->update_meta_data('_has_print_design', 'yes');
        $item->update_meta_data('_design_id', $design_id);
        $item->update_meta_data('_fallback_transfer', 'yes');
        
        return true;
    }
    
    error_log('ENHANCED: No design data found in values');
    return false;
}

/**
 * HILFSFUNKTION: View-Namen bestimmen
 */
function yprint_get_view_name_by_system_id($system_id) {
    $view_mappings = array(
        '189542' => 'Vorderseite',
        '679311' => 'Rückseite',
        '189543' => 'Linke Seite',
        '189544' => 'Rechte Seite',
        '189545' => 'Oberseite',
        '189546' => 'Unterseite',
        '679312' => 'Innenseite',
        '679313' => 'Ärmelvorderseite Links',
        '679314' => 'Ärmelvorderseite Rechts',
        '679315' => 'Ärmelrückseite Links',
        '679316' => 'Ärmelrückseite Rechts'
    );
    
    return isset($view_mappings[$system_id]) ? $view_mappings[$system_id] : "View $system_id";
}

/**
 * VERSTÄRKTER BACKUP TRANSFER für Express Payments
 */
function yprint_tracked_backup_transfer($order_id) {
    error_log('=== YPRINT TRACKED BACKUP TRANSFER ===');
    error_log('Order ID: ' . $order_id);
    
    // SOFORTIGER Cart-basierter Transfer für Express Payments
    if (!WC()->cart->is_empty()) {
        error_log('BACKUP: Cart has ' . WC()->cart->get_cart_contents_count() . ' items, attempting immediate transfer');
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('BACKUP: Order not found');
            return false;
        }
        
        $cart_contents = WC()->cart->get_cart();
        $transferred = 0;
        
        // Prüfe ob Order Items existieren aber keine Design-Daten haben
        foreach ($order->get_items() as $item_id => $order_item) {
            if ($order_item->get_meta('print_design')) {
                continue; // Skip items that already have design data
            }
            
            $product_id = $order_item->get_product_id();
            $quantity = $order_item->get_quantity();
            
            // Suche passendes Cart Item
            foreach ($cart_contents as $cart_key => $cart_item) {
                if ($cart_item['product_id'] == $product_id && 
                    $cart_item['quantity'] == $quantity &&
                    isset($cart_item['print_design'])) {
                    
                    $design_data = $cart_item['print_design'];
                    error_log('BACKUP: Transferring design data for item ' . $item_id);
                    error_log('Design Data: ' . print_r($design_data, true));
                    
                    $order_item->update_meta_data('print_design', $design_data);
                    $order_item->update_meta_data('_is_design_product', true);
                    $order_item->update_meta_data('_has_print_design', 'yes');
                    
                    // Basis Design-Daten (VOLLSTÄNDIG)
                    $order_item->update_meta_data('_design_id', $design_data['design_id'] ?? '');
                    $order_item->update_meta_data('_design_name', $design_data['name'] ?? '');
                    $order_item->update_meta_data('_design_color', $design_data['variation_name'] ?? '');
                    $order_item->update_meta_data('_design_size', $design_data['size_name'] ?? '');
                    $order_item->update_meta_data('_design_preview_url', $design_data['preview_url'] ?? '');
                    $order_item->update_meta_data('_design_template_id', $design_data['template_id'] ?? '');
                    
                    // Dimensionen für Print Provider
                    $order_item->update_meta_data('_design_width_cm', $design_data['width_cm'] ?? $design_data['design_width_cm'] ?? '');
                    $order_item->update_meta_data('_design_height_cm', $design_data['height_cm'] ?? $design_data['design_height_cm'] ?? '');
                    
                    // Kompatibilitäts-Feld
                    $order_item->update_meta_data('_design_image_url', $design_data['design_image_url'] ?? $design_data['image_url'] ?? '');
                    
                    // Erweiterte Bild-Daten
                    if (isset($design_data['product_images'])) {
                        $order_item->update_meta_data('_design_has_multiple_images', true);
                        $order_item->update_meta_data('_design_product_images', is_array($design_data['product_images']) ? wp_json_encode($design_data['product_images']) : $design_data['product_images']);
                    }
                    if (isset($design_data['design_images'])) {
                        $order_item->update_meta_data('_design_images', is_array($design_data['design_images']) ? wp_json_encode($design_data['design_images']) : $design_data['design_images']);
                    }
                    
                    $order_item->update_meta_data('_backup_transfer', 'yes');
                    $order_item->update_meta_data('_transfer_timestamp', current_time('mysql'));
                    $order_item->save_meta_data();
                    
                    $transferred++;
                    error_log('BACKUP: Successfully transferred design data for item ' . $item_id);
                    break;
                }
            }
        }
        
        if ($transferred > 0) {
            $order->save();
            error_log("BACKUP: Successfully transferred $transferred design items from cart");
            return true;
        }
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        error_log('YPRINT BACKUP: Order not found');
        return false;
    }
    
    yprint_log_hook_execution('new_order_backup_check', "Order ID: $order_id");
    
    // Prüfe ob Items bereits Design-Daten haben
    $missing_designs = false;
    foreach ($order->get_items() as $item) {
        if (!$item->get_meta('print_design')) {
            $missing_designs = true;
            break;
        }
    }
    
    if (!$missing_designs) {
        error_log('YPRINT BACKUP: All items already have design data');
        return false;
    }
    
    if (!WC()->session) {
        error_log('YPRINT BACKUP: No session available');
        return false;
    }
    
    // Suche nach verschiedenen Backup-Quellen
    $backup_sources = array(
        'yprint_express_design_backup',
        'yprint_express_design_backup_v2',
        'yprint_checkout_cart_data'
    );
    
    $successful_transfers = 0;
    
    foreach ($backup_sources as $backup_key) {
        $backup = WC()->session->get($backup_key);
        if (!empty($backup)) {
            error_log("YPRINT BACKUP: Found backup in $backup_key");
            yprint_log_hook_execution('backup_transfer_attempt', "Source: $backup_key | Order: $order_id");
            
            foreach ($order->get_items() as $item_id => $item) {
                if ($item->get_meta('print_design')) {
                    continue; // Skip items that already have design data
                }
                
                foreach ($backup as $design_data) {
                    if (is_array($design_data) && isset($design_data['design_id'])) {
                        error_log("YPRINT BACKUP: Applying design data to item $item_id");
                        
                        $item->update_meta_data('print_design', $design_data);
                        $item->update_meta_data('_is_design_product', true);
                        $item->update_meta_data('_has_print_design', 'yes');
                        $item->update_meta_data('_design_id', $design_data['design_id'] ?? '');
                        $item->update_meta_data('_design_name', $design_data['name'] ?? '');
                        $item->update_meta_data('_yprint_design_backup_applied', current_time('mysql'));
                        $item->update_meta_data('_yprint_backup_source', $backup_key);
                        $item->save_meta_data();
                        
                        $successful_transfers++;
                        break;
                    }
                }
            }
            
            if ($successful_transfers > 0) {
                WC()->session->__unset($backup_key);
                break;
            }
        }
    }
    
    yprint_log_hook_execution('backup_transfer_result', "Transfers: $successful_transfers | Order: $order_id");
    
    if ($successful_transfers > 0) {
        $order->save();
        error_log("YPRINT BACKUP: Successfully transferred $successful_transfers design items");
        return true;
    }
    
    error_log('YPRINT BACKUP: No design data transferred');
    return false;
}

/**
 * EINZIGER DESIGN-TRANSFER-HOOK - Verwendet jetzt tracked Funktionen
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_enhanced_design_transfer_hook', 5, 4);
function yprint_enhanced_design_transfer_hook($item, $cart_item_key, $values, $order) {
    $success = yprint_tracked_design_transfer($item, $cart_item_key, $values, $order);
    return $item;
}

// Diese Funktionen komplett entfernt - Debug läuft über yprint-order-debug-tracker.php



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

// VERSCHOBEN NACH OBEN - vor den Hook-Definitionen

// Diese doppelten Funktionen komplett entfernt

// Hooks werden direkt registriert - keine separate Registrierungsfunktion nötig

/**
 * HOOK EXECUTION TRACKER - Muss vor Hook-Definitionen stehen
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
    
    // Behalte nur die letzten 50 Einträge
    if (count($hook_log) > 50) {
        $hook_log = array_slice($hook_log, -50);
    }
    
    update_option('yprint_hook_execution_log', $hook_log);
    
    // Zusätzlich error_log für sofortige Sichtbarkeit
    error_log("YPRINT HOOK: $hook_name - $details");
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
    
    // Direkte Backup-Anwendung ohne gelöschte Funktion
    if (WC()->session) {
        $backup = WC()->session->get('yprint_express_design_backup');
        if (!empty($backup)) {
            foreach ($order->get_items() as $item_id => $item) {
                foreach ($backup as $design_data) {
                    if (!$item->get_meta('print_design')) {
                        $item->update_meta_data('print_design', $design_data);
                        $item->save_meta_data();
                        break;
                    }
                }
            }
            WC()->session->__unset('yprint_express_design_backup');
        }
    }
}

/**
 * VERSTÄRKTER EXPRESS ORDER: Design-Daten-Transfer für Express-Orders
 */
add_action('woocommerce_new_order', 'yprint_express_order_design_transfer', 1, 1);
function yprint_express_order_design_transfer($order_id) {
    error_log('=== YPRINT VERSTÄRKTER EXPRESS ORDER DESIGN TRANSFER ===');
    error_log('Checking order ' . $order_id . ' for express checkout...');
    
    $order = wc_get_order($order_id);
    if (!$order || !WC()->session) {
        error_log('EXPRESS: Order or session not available');
        return;
    }
    
    // Hook-Tracking
    yprint_log_hook_execution('new_order_backup_check', "Order ID: $order_id");
    
    // Versuche mehrere Backup-Schlüssel
    $backup_keys = array(
        'yprint_express_design_backup_v3',
        'yprint_express_design_backup_v2', 
        'yprint_express_design_backup'
    );
    
    $successful_transfers = 0;
    
    foreach ($backup_keys as $backup_key) {
        $express_design_backup = WC()->session->get($backup_key);
        
        if (!empty($express_design_backup)) {
            error_log('EXPRESS: Design-Backup gefunden in ' . $backup_key . ', übertrage zu Order...');
            
            foreach ($order->get_items() as $item_id => $order_item) {
                // Skip items that already have design data
                if ($order_item->get_meta('print_design')) {
                    continue;
                }
                
                $product_id = $order_item->get_product_id();
                
                foreach ($express_design_backup as $cart_key => $backup_info) {
                    $design_data = isset($backup_info['design_data']) ? $backup_info['design_data'] : $backup_info;
                    $backup_product_id = isset($backup_info['product_id']) ? $backup_info['product_id'] : null;
                    
                    if ($backup_product_id == $product_id || $successful_transfers == 0) {
                        error_log('EXPRESS: Füge Design-Daten zu Order-Item ' . $item_id . ' hinzu');
                        error_log('Design Data: ' . print_r($design_data, true));
                        
                        $order_item->update_meta_data('print_design', $design_data);
                        $order_item->update_meta_data('_is_design_product', true);
                        $order_item->update_meta_data('_has_print_design', 'yes');
                        
                        // Vollständige Design-Meta-Daten für Print Provider
                        $order_item->update_meta_data('_design_id', $design_data['design_id'] ?? '');
                        $order_item->update_meta_data('_design_name', $design_data['name'] ?? '');
                        $order_item->update_meta_data('_design_color', $design_data['variation_name'] ?? '');
                        $order_item->update_meta_data('_design_size', $design_data['size_name'] ?? '');
                        $order_item->update_meta_data('_design_preview_url', $design_data['preview_url'] ?? '');
                        $order_item->update_meta_data('_design_template_id', $design_data['template_id'] ?? '');
                        
                        // Dimensionen
                        $order_item->update_meta_data('_design_width_cm', $design_data['width_cm'] ?? $design_data['design_width_cm'] ?? '');
                        $order_item->update_meta_data('_design_height_cm', $design_data['height_cm'] ?? $design_data['design_height_cm'] ?? '');
                        
                        // Kompatibilitäts- und erweiterte Felder
                        $order_item->update_meta_data('_design_image_url', $design_data['design_image_url'] ?? $design_data['image_url'] ?? '');
                        
                        if (isset($design_data['product_images'])) {
                            $order_item->update_meta_data('_design_has_multiple_images', true);
                            $order_item->update_meta_data('_design_product_images', is_array($design_data['product_images']) ? wp_json_encode($design_data['product_images']) : $design_data['product_images']);
                        }
                        if (isset($design_data['design_images'])) {
                            $order_item->update_meta_data('_design_images', is_array($design_data['design_images']) ? wp_json_encode($design_data['design_images']) : $design_data['design_images']);
                        }
                        
                        $order_item->update_meta_data('_express_checkout_transfer', 'yes');
                        $order_item->update_meta_data('_backup_source', $backup_key);
                        $order_item->update_meta_data('_transfer_timestamp', current_time('mysql'));
                        
                        $order_item->save_meta_data();
                        
                        $successful_transfers++;
                        error_log('EXPRESS: Design-Daten erfolgreich übertragen!');
                        break;
                    }
                }
            }
            
            if ($successful_transfers > 0) {
                // Entferne Backup erst nach erfolgreichem Transfer
                WC()->session->__unset($backup_key);
                break;
            }
        }
    }
    
    // Hook-Tracking für Ergebnis
    yprint_log_hook_execution('backup_transfer_attempt', "Transfers: $successful_transfers | Order: $order_id");
    
    if ($successful_transfers > 0) {
        $order->save();
        error_log('EXPRESS: ' . $successful_transfers . ' Design-Items erfolgreich übertragen');
        yprint_log_hook_execution('backup_transfer_result', "SUCCESS: $successful_transfers items transferred");
    } else {
        error_log('EXPRESS: Kein Design-Backup gefunden oder Transfer fehlgeschlagen');
        yprint_log_hook_execution('backup_transfer_result', "FAILED: No design data transferred");
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

// Diese Funktion komplett entfernt da sie gelöschte Funktionen aufruft

// Alle Emergency-Funktionen entfernt - nur eine saubere Backup-Funktion
add_action('woocommerce_new_order', 'yprint_tracked_backup_transfer', 20, 1);

// Diese Funktion entfernt da sie identisch mit yprint_simple_backup_transfer ist


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



// Debug-Logs AJAX-Handler wurde entfernt

// Hook-Verifikation Debug-Handler wurde entfernt

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

// Debug-Log-Speicher-Funktion wurde entfernt

// Diese doppelten Funktionen komplett entfernt

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

// Diese doppelte Registrierung komplett entfernt - die erste Funktion reicht aus

// YPrint Order Information Meta-Box wurde entfernt

/**
 * Checkout-spezifische Funktionen
 */
add_action('wp_enqueue_scripts', 'yprint_checkout_scripts');
function yprint_checkout_scripts() {
    if (is_page('checkout') || is_cart()) {
        $plugin_url = defined('YPRINT_PLUGIN_URL') ? YPRINT_PLUGIN_URL : plugin_dir_url(__FILE__);
        $version = defined('YPRINT_VERSION') ? YPRINT_VERSION : '1.0.0';
        
        wp_enqueue_script('yprint-checkout', $plugin_url . 'assets/js/yprint-checkout.js', array('jquery'), $version, true);
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

/**
 * EMERGENCY DESIGN TRANSFER - Wird ausgeführt sobald Order existiert
 */
add_action('woocommerce_checkout_order_processed', 'yprint_emergency_final_design_transfer', 5, 3);
function yprint_emergency_final_design_transfer($order_id, $posted_data, $order) {
    error_log('=== YPRINT EMERGENCY FINAL DESIGN TRANSFER ===');
    error_log('Order ID: ' . $order_id);
    
    // Prüfe ob Order Items existieren
    $order_items = $order->get_items();
    error_log('Order Items Count: ' . count($order_items));
    
    if (empty($order_items)) {
        error_log('CRITICAL: Order has NO ITEMS - WooCommerce checkout problem!');
        return;
    }
    
    // Prüfe Cart-Daten
    if (!WC()->cart || WC()->cart->is_empty()) {
        error_log('Cart is empty during order processing');
        return;
    }
    
    error_log('Cart Items Count: ' . WC()->cart->get_cart_contents_count());
    
    // Manuelle Design-Daten-Übertragung
    $cart_contents = WC()->cart->get_cart();
    foreach ($order_items as $item_id => $order_item) {
        $product_id = $order_item->get_product_id();
        
        // Suche passendes Cart-Item
        foreach ($cart_contents as $cart_key => $cart_item) {
            if ($cart_item['product_id'] == $product_id && isset($cart_item['print_design'])) {
                $design_data = $cart_item['print_design'];
                
                error_log('EMERGENCY: Applying design data to order item ' . $item_id);
                error_log('Design Data: ' . print_r($design_data, true));
                
                // VOLLSTÄNDIGE Design-Daten übertragen
                $order_item->update_meta_data('print_design', $design_data);
                $order_item->update_meta_data('_is_design_product', true);
                $order_item->update_meta_data('_has_print_design', 'yes');
                
                // Alle erforderlichen Meta-Felder für Print Provider
                $order_item->update_meta_data('_design_id', $design_data['design_id'] ?? '');
                $order_item->update_meta_data('_design_name', $design_data['name'] ?? '');
                $order_item->update_meta_data('_design_color', $design_data['variation_name'] ?? '');
                $order_item->update_meta_data('_design_size', $design_data['size_name'] ?? '');
                $order_item->update_meta_data('_design_preview_url', $design_data['preview_url'] ?? '');
                $order_item->update_meta_data('_design_template_id', $design_data['template_id'] ?? '');
                $order_item->update_meta_data('_design_width_cm', $design_data['width_cm'] ?? $design_data['design_width_cm'] ?? '');
                $order_item->update_meta_data('_design_height_cm', $design_data['height_cm'] ?? $design_data['design_height_cm'] ?? '');
                $order_item->update_meta_data('_design_image_url', $design_data['design_image_url'] ?? $design_data['image_url'] ?? '');
                
                // Erweiterte Bild-Daten
                if (isset($design_data['product_images'])) {
                    $order_item->update_meta_data('_design_has_multiple_images', true);
                    $order_item->update_meta_data('_design_product_images', is_array($design_data['product_images']) ? wp_json_encode($design_data['product_images']) : $design_data['product_images']);
                }
                if (isset($design_data['design_images'])) {
                    $order_item->update_meta_data('_design_images', is_array($design_data['design_images']) ? wp_json_encode($design_data['design_images']) : $design_data['design_images']);
                }
                
                $order_item->update_meta_data('_emergency_transfer', 'yes');
                $order_item->save_meta_data();
                
                error_log('EMERGENCY: Design data successfully applied!');
                break;
            }
        }
    }
    
    $order->save();
    error_log('EMERGENCY: Order saved with design data');
}

// Checkout Debug-Info wurde entfernt

/**
 * CRITICAL: Add Cart Item Data Filter für Design-Daten
 * Dieser Filter ist essentiell für die korrekte Übertragung der Design-Daten
 */
add_filter('woocommerce_add_cart_item_data', 'yprint_add_design_to_cart_item_data', 10, 3);
function yprint_add_design_to_cart_item_data($cart_item_data, $product_id, $variation_id) {
    error_log('=== YPRINT ADD CART ITEM DATA FILTER ===');
    error_log('Product ID: ' . $product_id);
    error_log('Variation ID: ' . $variation_id);
    error_log('POST Data: ' . print_r($_POST, true));
    
    // Prüfe ob Design-Daten in POST vorhanden sind
    if (isset($_POST['design_data']) || isset($_POST['print_design'])) {
        $design_data = $_POST['design_data'] ?? $_POST['print_design'] ?? array();
        
        // Falls design_data als JSON-String übertragen wurde
        if (is_string($design_data)) {
            $design_data = json_decode(stripslashes($design_data), true);
        }
        
        error_log('YPRINT: Design data found in POST: ' . print_r($design_data, true));
        
        if (!empty($design_data)) {
            $cart_item_data['print_design'] = $design_data;
            error_log('YPRINT: Design data added to cart item data');
            
            // Zusätzliche Express-Backup für problematische Checkouts
            if (WC()->session) {
                $backup_data = WC()->session->get('yprint_express_design_backup_v4', array());
                $backup_data[uniqid()] = array(
                    'product_id' => $product_id,
                    'variation_id' => $variation_id,
                    'design_data' => $design_data
                );
                WC()->session->set('yprint_express_design_backup_v4', $backup_data);
                error_log('YPRINT: Design backup v4 created');
            }
        }
    }
    
    // Prüfe alternative Design-Daten-Felder
    $design_fields = array('design_id', 'design_name', 'variation_name', 'size_name', 'preview_url', 'template_id');
    $has_design_fields = false;
    $extracted_design = array();
    
    foreach ($design_fields as $field) {
        if (isset($_POST[$field]) && !empty($_POST[$field])) {
            $extracted_design[$field] = sanitize_text_field($_POST[$field]);
            $has_design_fields = true;
        }
    }
    
    // Erweiterte Felder
    $extended_fields = array(
        'width_cm' => 'design_width_cm',
        'height_cm' => 'design_height_cm', 
        'design_image_url' => 'image_url',
        'product_images' => 'product_images',
        'design_images' => 'design_images'
    );
    
    foreach ($extended_fields as $post_key => $design_key) {
        if (isset($_POST[$post_key])) {
            $value = $_POST[$post_key];
            if (in_array($post_key, ['product_images', 'design_images']) && is_string($value)) {
                $value = json_decode(stripslashes($value), true);
            }
            $extracted_design[$design_key] = $value;
            $has_design_fields = true;
        }
    }
    
    if ($has_design_fields && !isset($cart_item_data['print_design'])) {
        $cart_item_data['print_design'] = $extracted_design;
        error_log('YPRINT: Extracted design data from individual POST fields: ' . print_r($extracted_design, true));
    }
    
    return $cart_item_data;
}

/**
 * Zusätzlicher Backup-Transfer für neue Backup-Version
 */
add_action('woocommerce_new_order', 'yprint_backup_transfer_v4', 25, 1);
function yprint_backup_transfer_v4($order_id) {
    if (!WC()->session) {
        return;
    }
    
    $backup_v4 = WC()->session->get('yprint_express_design_backup_v4');
    if (empty($backup_v4)) {
        return;
    }
    
    error_log('=== YPRINT BACKUP TRANSFER V4 ===');
    error_log('Order ID: ' . $order_id);
    
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    
    $transferred = 0;
    foreach ($order->get_items() as $item_id => $order_item) {
        if ($order_item->get_meta('print_design')) {
            continue; // Skip items that already have design data
        }
        
        $product_id = $order_item->get_product_id();
        
        foreach ($backup_v4 as $backup_key => $backup_info) {
            if ($backup_info['product_id'] == $product_id && isset($backup_info['design_data'])) {
                $design_data = $backup_info['design_data'];
                
                // Vollständige Meta-Daten setzen
                $order_item->update_meta_data('print_design', $design_data);
                $order_item->update_meta_data('_is_design_product', true);
                $order_item->update_meta_data('_has_print_design', 'yes');
                $order_item->update_meta_data('_design_id', $design_data['design_id'] ?? '');
                $order_item->update_meta_data('_design_name', $design_data['name'] ?? $design_data['design_name'] ?? '');
                $order_item->update_meta_data('_design_color', $design_data['variation_name'] ?? '');
                $order_item->update_meta_data('_design_size', $design_data['size_name'] ?? '');
                $order_item->update_meta_data('_design_preview_url', $design_data['preview_url'] ?? '');
                $order_item->update_meta_data('_design_width_cm', $design_data['width_cm'] ?? $design_data['design_width_cm'] ?? '');
                $order_item->update_meta_data('_design_height_cm', $design_data['height_cm'] ?? $design_data['design_height_cm'] ?? '');
                $order_item->update_meta_data('_design_image_url', $design_data['design_image_url'] ?? $design_data['image_url'] ?? '');
                
                if (isset($design_data['product_images'])) {
                    $order_item->update_meta_data('_design_has_multiple_images', true);
                    $order_item->update_meta_data('_design_product_images', is_array($design_data['product_images']) ? wp_json_encode($design_data['product_images']) : $design_data['product_images']);
                }
                if (isset($design_data['design_images'])) {
                    $order_item->update_meta_data('_design_images', is_array($design_data['design_images']) ? wp_json_encode($design_data['design_images']) : $design_data['design_images']);
                }
                
                $order_item->update_meta_data('_backup_transfer_v4', 'yes');
                $order_item->update_meta_data('_transfer_timestamp', current_time('mysql'));
                $order_item->save_meta_data();
                
                $transferred++;
                error_log('BACKUP V4: Design data transferred to item ' . $item_id);
                break;
            }
        }
    }
    
    if ($transferred > 0) {
        $order->save();
        WC()->session->__unset('yprint_express_design_backup_v4');
        error_log("BACKUP V4: Successfully transferred $transferred items");
    }

    /**
 * AJAX Handler für Payment Method Debugging
 */
function yprint_debug_payment_method_callback() {
    // Security check
    if (!wp_verify_nonce($_POST['nonce'], 'yprint_debug_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $order_id = intval($_POST['order_id']);
    if (!$order_id) {
        wp_send_json_error('No order ID provided');
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
        return;
    }
    
    $debug_data = array(
        'order_id' => $order_id,
        'wc_payment_method' => $order->get_payment_method(),
        'wc_payment_method_title' => $order->get_payment_method_title(),
        'transaction_id' => $order->get_transaction_id(),
        'payment_intent_id' => $order->get_meta('_yprint_stripe_intent_id'),
        'stripe_intent' => null,
        'stripe_error' => null,
        'detected_type' => null,
        'display_title' => null,
        'display_icon' => null
    );
    
    // Get Payment Intent ID
    $payment_intent_id = $debug_data['transaction_id'];
    if (empty($payment_intent_id)) {
        $payment_intent_id = $debug_data['payment_intent_id'];
    }
    
    // Try to fetch Stripe data
    if (!empty($payment_intent_id) && strpos($payment_intent_id, 'pi_') === 0) {
        if (class_exists('YPrint_Stripe_API')) {
            try {
                $intent = YPrint_Stripe_API::request(array(), 'payment_intents/' . $payment_intent_id, 'GET');
                
                if (!empty($intent) && !isset($intent->error)) {
                    $debug_data['stripe_intent'] = $intent;
                    
                    // Analyze payment method details
                    if (isset($intent->payment_method_details)) {
                        $payment_details = $intent->payment_method_details;
                        
                        // Card-based payments
                        if (isset($payment_details->card)) {
                            $card_details = $payment_details->card;
                            
                            // Wallet payments (Apple Pay, Google Pay)
                            if (isset($card_details->wallet)) {
                                $wallet_type = $card_details->wallet->type;
                                
                                switch ($wallet_type) {
                                    case 'apple_pay':
                                        $debug_data['detected_type'] = 'apple_pay';
                                        $debug_data['display_title'] = 'Apple Pay (Stripe)';
                                        $debug_data['display_icon'] = 'fab fa-apple';
                                        break;
                                    case 'google_pay':
                                        $debug_data['detected_type'] = 'google_pay';
                                        $debug_data['display_title'] = 'Google Pay (Stripe)';
                                        $debug_data['display_icon'] = 'fab fa-google-pay';
                                        break;
                                    default:
                                        $debug_data['detected_type'] = 'express_' . $wallet_type;
                                        $debug_data['display_title'] = ucfirst(str_replace('_', ' ', $wallet_type)) . ' (Stripe)';
                                        $debug_data['display_icon'] = 'fas fa-bolt';
                                }
                            } else {
                                // Regular card payment
                                $brand = isset($card_details->brand) ? ucfirst($card_details->brand) : 'Kreditkarte';
                                $last4 = isset($card_details->last4) ? ' ****' . $card_details->last4 : '';
                                
                                $debug_data['detected_type'] = 'card';
                                $debug_data['display_title'] = $brand . $last4 . ' (Stripe)';
                                $debug_data['display_icon'] = 'fas fa-credit-card';
                            }
                        }
                        // SEPA payments
                        elseif (isset($payment_details->sepa_debit)) {
                            $sepa_details = $payment_details->sepa_debit;
                            $last4 = isset($sepa_details->last4) ? ' ****' . $sepa_details->last4 : '';
                            
                            $debug_data['detected_type'] = 'sepa_debit';
                            $debug_data['display_title'] = 'SEPA-Lastschrift' . $last4 . ' (Stripe)';
                            $debug_data['display_icon'] = 'fas fa-university';
                        }
                    }
                } else {
                    $debug_data['stripe_error'] = isset($intent->error) ? $intent->error->message : 'Unknown error';
                }
            } catch (Exception $e) {
                $debug_data['stripe_error'] = $e->getMessage();
            }
        } else {
            $debug_data['stripe_error'] = 'YPrint_Stripe_API class not found';
        }
    } else {
        $debug_data['stripe_error'] = 'No valid Payment Intent ID found';
    }
    
    wp_send_json_success($debug_data);
}
add_action('wp_ajax_yprint_debug_payment_method', 'yprint_debug_payment_method_callback');
add_action('wp_ajax_nopriv_yprint_debug_payment_method', 'yprint_debug_payment_method_callback');
}

/**
 * AJAX Handler für Payment Method Debugging
 */
function yprint_debug_payment_method_callback() {
    if (!wp_verify_nonce($_POST['nonce'], 'yprint_debug_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $order_id = intval($_POST['order_id']);
    if (!$order_id) {
        wp_send_json_error('No order ID provided');
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
        return;
    }
    
    $debug_data = array(
        'order_id' => $order_id,
        'wc_payment_method' => $order->get_payment_method(),
        'wc_payment_method_title' => $order->get_payment_method_title(),
        'transaction_id' => $order->get_transaction_id(),
        'payment_intent_id' => $order->get_meta('_yprint_stripe_intent_id'),
        'stripe_intent' => null,
        'stripe_error' => null
    );
    
    // Get Payment Intent ID
    $payment_intent_id = $debug_data['transaction_id'];
    if (empty($payment_intent_id)) {
        $payment_intent_id = $debug_data['payment_intent_id'];
    }
    
    // Try to fetch Stripe data
    if (!empty($payment_intent_id) && strpos($payment_intent_id, 'pi_') === 0) {
        if (class_exists('YPrint_Stripe_API')) {
            try {
                $intent = YPrint_Stripe_API::request(array(), 'payment_intents/' . $payment_intent_id, 'GET');
                
                if (!empty($intent) && !isset($intent->error)) {
                    $debug_data['stripe_intent'] = $intent;
                } else {
                    $debug_data['stripe_error'] = isset($intent->error) ? $intent->error->message : 'Unknown error';
                }
            } catch (Exception $e) {
                $debug_data['stripe_error'] = $e->getMessage();
            }
        } else {
            $debug_data['stripe_error'] = 'YPrint_Stripe_API class not found';
        }
    }
    
    wp_send_json_success($debug_data);
}
add_action('wp_ajax_yprint_debug_payment_method', 'yprint_debug_payment_method_callback');
add_action('wp_ajax_nopriv_yprint_debug_payment_method', 'yprint_debug_payment_method_callback');

/**
 * AJAX Handler für Payment Method Detection via Stripe API
 */
function yprint_get_payment_method_details_callback() {
    // Verwende die vorhandene Stripe-Nonce
    // Versuche verschiedene bekannte Nonce-Actions
$nonce = $_POST['nonce'];
$valid_nonce = false;

$nonce_actions = array(
    'yprint_stripe_nonce',
    'yprint_checkout_nonce', 
    'yprint_debug_nonce'
);

foreach ($nonce_actions as $action) {
    if (wp_verify_nonce($nonce, $action)) {
        $valid_nonce = true;
        break;
    }
}

if (!$valid_nonce) {
    wp_send_json_error('Security check failed. Tried nonce actions: ' . implode(', ', $nonce_actions));
    return;
}
    
    $order_id = intval($_POST['order_id']);
    if (!$order_id) {
        wp_send_json_error('No order ID provided');
        return;
    }
    
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
        return;
    }
    
    // Get Payment Method ID from order meta
    $payment_method_id = $order->get_meta('_yprint_stripe_payment_method_id');
    
    // Fallback: Transaction ID könnte Payment Method ID sein
    if (empty($payment_method_id)) {
        $transaction_id = $order->get_transaction_id();
        if (strpos($transaction_id, 'pm_') === 0) {
            $payment_method_id = $transaction_id;
        } elseif (strpos($transaction_id, 'pi_') === 0) {
            // Falls Transaction ID ein Payment Intent ist, holen wir daraus die Payment Method
            if (class_exists('YPrint_Stripe_API')) {
                try {
                    $intent = YPrint_Stripe_API::request(array(), 'payment_intents/' . $transaction_id);
                    if (!empty($intent->payment_method)) {
                        $payment_method_id = $intent->payment_method;
                    }
                } catch (Exception $e) {
                    wp_send_json_error('Failed to get Payment Intent: ' . $e->getMessage());
                    return;
                }
            }
        }
    }
    
    if (empty($payment_method_id) || strpos($payment_method_id, 'pm_') !== 0) {
        wp_send_json_error('No valid Payment Method ID found. Transaction ID: ' . $order->get_transaction_id());
        return;
    }
    
    // Get Payment Method Details via Stripe API
    if (!class_exists('YPrint_Stripe_API')) {
        wp_send_json_error('Stripe API not available');
        return;
    }
    
    try {
        $payment_method = YPrint_Stripe_API::request(array(), 'payment_methods/' . $payment_method_id);
        
        $result = array(
            'order_id' => $order_id,
            'payment_method_id' => $payment_method_id,
            'type' => $payment_method->type,
            'display_method' => 'Kreditkarte',
            'icon' => 'fas fa-credit-card'
        );
        
        // Apple Pay / Google Pay Detection
        if ($payment_method->type === 'card' && isset($payment_method->card->wallet)) {
            $wallet = $payment_method->card->wallet;
            
            if ($wallet->type === 'apple_pay') {
                $result['display_method'] = 'Apple Pay';
                $result['icon'] = 'fab fa-apple-pay';
            } elseif ($wallet->type === 'google_pay') {
                $result['display_method'] = 'Google Pay';  
                $result['icon'] = 'fab fa-google-pay';
            }
            
            $result['wallet'] = $wallet;
        } elseif ($payment_method->type === 'card') {
            // Regular card payment
            $brand = ucfirst($payment_method->card->brand);
            $last4 = $payment_method->card->last4;
            $result['display_method'] = $brand . ' ****' . $last4;
        } elseif ($payment_method->type === 'sepa_debit') {
            $last4 = $payment_method->sepa_debit->last4;
            $result['display_method'] = 'SEPA-Lastschrift ****' . $last4;
            $result['icon'] = 'fas fa-university';
        }
        
        wp_send_json_success($result);
        
    } catch (Exception $e) {
        wp_send_json_error('Stripe API error: ' . $e->getMessage());
    }
}

add_action('wp_ajax_yprint_get_payment_method_details', 'yprint_get_payment_method_details_callback');
add_action('wp_ajax_nopriv_yprint_get_payment_method_details', 'yprint_get_payment_method_details_callback');

?>