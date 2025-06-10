<?php
/**
 * YPRINT ORDER DEBUG TRACKER
 * 
 * Fügt ein sichtbares Debug-Protokoll zu jeder WooCommerce-Bestellung hinzu
 * Das zeigt JEDEN Schritt der Design-Daten-Verarbeitung
 */

// === FÜGE DIESEN CODE IN includes/woocommerce.php HINZU ===

/**
 * YPRINT DEBUG TRACKER: Initialisierung
 */
add_action('plugins_loaded', 'yprint_init_order_debug_tracker', 1);
function yprint_init_order_debug_tracker() {
    error_log('YPRINT DEBUG TRACKER: Initialisierung gestartet');
    
    // Registriere alle kritischen Hooks mit Debug-Protokollierung
    add_action('woocommerce_add_to_cart', 'yprint_debug_track_cart_addition', 1, 6);
    add_filter('woocommerce_checkout_create_order_line_item', 'yprint_debug_track_order_line_item', 1, 4);
    add_action('woocommerce_checkout_create_order', 'yprint_debug_track_order_creation', 1, 2);
    add_action('woocommerce_new_order', 'yprint_debug_track_new_order', 1, 2);
    add_action('woocommerce_checkout_order_processed', 'yprint_debug_track_order_processed', 1, 3);
    
    // Express Checkout spezifische Hooks
    add_action('wp_ajax_yprint_process_payment_method', 'yprint_debug_track_express_payment', 1);
    add_action('wp_ajax_nopriv_yprint_process_payment_method', 'yprint_debug_track_express_payment', 1);
    
    // Stripe Payment Request Hooks
    add_action('wp_ajax_yprint_stripe_process_payment', 'yprint_debug_track_stripe_payment', 1);
    add_action('wp_ajax_nopriv_yprint_stripe_process_payment', 'yprint_debug_track_stripe_payment', 1);
    
    error_log('YPRINT DEBUG TRACKER: Alle Hooks registriert');
}

/**
 * DEBUG: Verfolge Cart-Hinzufügungen
 */
function yprint_debug_track_cart_addition($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    $timestamp = current_time('Y-m-d H:i:s');
    $debug_message = "[$timestamp] CART ADDITION: Product $product_id added to cart (Key: $cart_item_key)";
    
    if (isset($cart_item_data['print_design'])) {
        $debug_message .= "\n🎨 DESIGN DATA FOUND: " . json_encode($cart_item_data['print_design']);
    } else {
        $debug_message .= "\n❌ NO DESIGN DATA in cart addition";
    }
    
    // Speichere in Session für spätere Order-Verknüpfung
    yprint_add_debug_to_session($debug_message);
    error_log($debug_message);
}

/**
 * DEBUG: Verfolge Express Payment Aufrufe
 */
function yprint_debug_track_express_payment() {
    $timestamp = current_time('Y-m-d H:i:s');
    $debug_message = "[$timestamp] EXPRESS PAYMENT METHOD CALLED";
    
    // Überprüfe Cart-Status
    if (WC()->cart) {
        $design_items = 0;
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            if (isset($cart_item['print_design'])) {
                $design_items++;
                $debug_message .= "\n🎨 Cart Item $cart_item_key has design: " . json_encode($cart_item['print_design']);
            }
        }
        $debug_message .= "\n📊 Total design items in cart: $design_items";
        
        // Sichere Design-Daten in Session
        if ($design_items > 0) {
            $cart_designs = array();
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if (isset($cart_item['print_design'])) {
                    $cart_designs[$cart_item_key] = $cart_item['print_design'];
                }
            }
            WC()->session->set('yprint_express_design_backup', $cart_designs);
            $debug_message .= "\n💾 Design data backed up to session";
        }
    } else {
        $debug_message .= "\n❌ WC()->cart not available!";
    }
    
    yprint_add_debug_to_session($debug_message);
    error_log($debug_message);
}

/**
 * DEBUG: Verfolge Stripe Payment Request
 */
function yprint_debug_track_stripe_payment() {
    $timestamp = current_time('Y-m-d H:i:s');
    $debug_message = "[$timestamp] STRIPE PAYMENT REQUEST CALLED";
    
    $payment_method_id = isset($_POST['payment_method_id']) ? $_POST['payment_method_id'] : 'not_provided';
    $debug_message .= "\n💳 Payment Method ID: $payment_method_id";
    
    yprint_add_debug_to_session($debug_message);
    error_log($debug_message);
}

/**
 * DEBUG: Verfolge Order Line Item Creation
 */
function yprint_debug_track_order_line_item($item, $cart_item_key, $values, $order) {
    $timestamp = current_time('Y-m-d H:i:s');
    $debug_message = "[$timestamp] ORDER LINE ITEM CREATION: Cart key $cart_item_key";
    
    if (isset($values['print_design'])) {
        $debug_message .= "\n✅ DESIGN DATA AVAILABLE in line item creation";
        $debug_message .= "\n🎨 Design: " . json_encode($values['print_design']);
        
        // Füge Design-Daten zur Order hinzu
        $item->add_meta_data('print_design', $values['print_design']);
        $item->add_meta_data('_has_print_design', 'yes');
        $item->add_meta_data('_design_transfer_method', 'standard_hook');
        $item->add_meta_data('_design_transfer_time', $timestamp);
        
        $debug_message .= "\n✅ Design data added to order item via STANDARD HOOK";
    } else {
        $debug_message .= "\n❌ NO DESIGN DATA in line item creation";
        $debug_message .= "\n🔍 Available keys: " . implode(', ', array_keys($values));
        
        // Versuche Emergency Recovery
        $emergency_designs = WC()->session->get('yprint_express_design_backup');
        if (!empty($emergency_designs)) {
            $debug_message .= "\n🚨 EMERGENCY RECOVERY: Found backed up design data";
            
            // Versuche Design-Daten aus Backup zu finden
            foreach ($emergency_designs as $backup_key => $design_data) {
                if ($backup_key === $cart_item_key) {
                    $item->add_meta_data('print_design', $design_data);
                    $item->add_meta_data('_has_print_design', 'yes');
                    $item->add_meta_data('_design_transfer_method', 'emergency_recovery');
                    $item->add_meta_data('_design_transfer_time', $timestamp);
                    
                    $debug_message .= "\n✅ Design data recovered from emergency backup!";
                    break;
                }
            }
        } else {
            $debug_message .= "\n❌ No emergency backup available";
        }
    }
    
    // Speichere Debug für diese Order
    if ($order && method_exists($order, 'get_id')) {
        yprint_add_debug_to_order($order->get_id(), $debug_message);
    } else {
        yprint_add_debug_to_session($debug_message);
    }
    
    error_log($debug_message);
    return $item;
}

/**
 * DEBUG: Verfolge Order Creation
 */
function yprint_debug_track_order_creation($order, $data) {
    $timestamp = current_time('Y-m-d H:i:s');
    $order_id = $order->get_id();
    $debug_message = "[$timestamp] ORDER CREATION: Order ID $order_id";
    
    // Überprüfe ob Design-Daten in Order vorhanden sind
    $design_items_found = 0;
    foreach ($order->get_items() as $item_id => $order_item) {
        if ($order_item->get_meta('print_design')) {
            $design_items_found++;
            $debug_message .= "\n✅ Item $item_id has design data";
        } else {
            $debug_message .= "\n❌ Item $item_id has NO design data";
        }
    }
    
    $debug_message .= "\n📊 Total items with design data: $design_items_found";
    
    if ($design_items_found === 0) {
        $debug_message .= "\n🚨 CRITICAL: NO DESIGN DATA FOUND IN ORDER!";
        
        // Versuche finale Emergency Recovery
        $emergency_designs = WC()->session->get('yprint_express_design_backup');
        if (!empty($emergency_designs)) {
            $debug_message .= "\n🔧 Attempting final emergency recovery...";
            
            foreach ($order->get_items() as $item_id => $order_item) {
                $product_id = $order_item->get_product_id();
                
                // Versuche Design-Daten zu finden
                foreach ($emergency_designs as $design_data) {
                    if (isset($design_data['template_id'])) {
                        $order_item->add_meta_data('print_design', $design_data);
                        $order_item->add_meta_data('_has_print_design', 'yes');
                        $order_item->add_meta_data('_design_transfer_method', 'final_emergency_recovery');
                        $order_item->add_meta_data('_design_transfer_time', $timestamp);
                        $order_item->save();
                        
                        $debug_message .= "\n✅ Emergency recovery successful for item $item_id";
                        break;
                    }
                }
            }
            
            $order->save();
        }
    }
    
    yprint_add_debug_to_order($order_id, $debug_message);
    error_log($debug_message);
}

/**
 * DEBUG: Verfolge New Order Hook
 */
function yprint_debug_track_new_order($order_id, $order) {
    $timestamp = current_time('Y-m-d H:i:s');
    $debug_message = "[$timestamp] NEW ORDER HOOK: Order ID $order_id";
    
    // Finale Verifikation der Design-Daten
    $final_design_count = 0;
    foreach ($order->get_items() as $item_id => $order_item) {
        if ($order_item->get_meta('print_design')) {
            $final_design_count++;
        }
    }
    
    $debug_message .= "\n📊 Final design data count: $final_design_count";
    
    if ($final_design_count > 0) {
        $debug_message .= "\n🎉 SUCCESS: Order contains design data!";
    } else {
        $debug_message .= "\n💥 FAILURE: Order has NO design data!";
    }
    
    yprint_add_debug_to_order($order_id, $debug_message);
    error_log($debug_message);
}

/**
 * DEBUG: Verfolge Order Processed Hook
 */
function yprint_debug_track_order_processed($order_id, $posted_data, $order) {
    $timestamp = current_time('Y-m-d H:i:s');
    $debug_message = "[$timestamp] ORDER PROCESSED: Order ID $order_id";
    
    // Finale Überprüfung und Cleanup
    $debug_message .= "\n🧹 Cleaning up session data...";
    WC()->session->__unset('yprint_express_design_backup');
    
    yprint_add_debug_to_order($order_id, $debug_message);
    error_log($debug_message);
}

/**
 * HILFS-FUNKTION: Debug zu Session hinzufügen
 */
function yprint_add_debug_to_session($message) {
    if (!WC()->session) return;
    
    $existing_debug = WC()->session->get('yprint_order_debug_log', array());
    $existing_debug[] = $message;
    
    // Behalte nur die letzten 50 Einträge
    if (count($existing_debug) > 50) {
        $existing_debug = array_slice($existing_debug, -50);
    }
    
    WC()->session->set('yprint_order_debug_log', $existing_debug);
}

/**
 * HILFS-FUNKTION: Debug zu Order hinzufügen
 */
function yprint_add_debug_to_order($order_id, $message) {
    if (!$order_id) return;
    
    $existing_debug = get_post_meta($order_id, '_yprint_debug_log', true);
    if (!is_array($existing_debug)) {
        $existing_debug = array();
    }
    
    $existing_debug[] = $message;
    update_post_meta($order_id, '_yprint_debug_log', $existing_debug);
    
    // Auch Session-Debug zur Order übertragen
    if (WC()->session) {
        $session_debug = WC()->session->get('yprint_order_debug_log', array());
        if (!empty($session_debug)) {
            foreach ($session_debug as $session_message) {
                $existing_debug[] = $session_message;
            }
            update_post_meta($order_id, '_yprint_debug_log', $existing_debug);
            WC()->session->__unset('yprint_order_debug_log');
        }
    }
}

/**
 * ADMIN: Debug-Log in Order-Details anzeigen
 */
add_action('woocommerce_admin_order_data_after_order_details', 'yprint_display_order_debug_log');
function yprint_display_order_debug_log($order) {
    $debug_log = get_post_meta($order->get_id(), '_yprint_debug_log', true);
    
    if (!empty($debug_log)) {
        echo '<div class="yprint-debug-log" style="margin-top: 20px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd;">';
        echo '<h3>🐛 YPrint Debug-Protokoll</h3>';
        echo '<div style="font-family: monospace; font-size: 12px; max-height: 400px; overflow-y: auto; background: white; padding: 10px; border: 1px solid #ccc;">';
        
        foreach ($debug_log as $entry) {
            $entry = esc_html($entry);
            $entry = str_replace("\n", "<br>", $entry);
            
            // Coloriere verschiedene Message-Typen
            if (strpos($entry, '✅') !== false) {
                $entry = '<span style="color: green;">' . $entry . '</span>';
            } elseif (strpos($entry, '❌') !== false || strpos($entry, '💥') !== false) {
                $entry = '<span style="color: red;">' . $entry . '</span>';
            } elseif (strpos($entry, '🚨') !== false || strpos($entry, '🔧') !== false) {
                $entry = '<span style="color: orange;">' . $entry . '</span>';
            } elseif (strpos($entry, '🎨') !== false) {
                $entry = '<span style="color: blue;">' . $entry . '</span>';
            }
            
            echo '<div style="margin-bottom: 8px; padding: 4px; border-bottom: 1px solid #eee;">' . $entry . '</div>';
        }
        
        echo '</div>';
        echo '</div>';
    } else {
        echo '<div class="yprint-debug-log" style="margin-top: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeaa7;">';
        echo '<h3>⚠️ YPrint Debug-Protokoll</h3>';
        echo '<p>Kein Debug-Protokoll für diese Bestellung gefunden. Dies könnte bedeuten, dass das Debug-System nicht aktiv war oder die Bestellung vor der Debug-Implementierung erstellt wurde.</p>';
        echo '</div>';
    }
}

/**
 * FRONTEND: Debug-Info auf Order-Success-Seite (für Kunden)
 */
add_action('woocommerce_thankyou', 'yprint_display_order_debug_for_customer');
function yprint_display_order_debug_for_customer($order_id) {
    // Nur für Admins oder in Debug-Modus anzeigen
    if (!current_user_can('manage_options') && !defined('WP_DEBUG') || !WP_DEBUG) {
        return;
    }
    
    $debug_log = get_post_meta($order_id, '_yprint_debug_log', true);
    
    if (!empty($debug_log)) {
        echo '<div style="margin-top: 30px; padding: 15px; background: #f0f8ff; border: 1px solid #b0d4f1; border-radius: 5px;">';
        echo '<h3>🐛 Debug-Informationen (nur für Entwickler sichtbar)</h3>';
        echo '<details><summary>Debug-Protokoll anzeigen</summary>';
        echo '<pre style="background: white; padding: 10px; border: 1px solid #ddd; font-size: 11px; max-height: 300px; overflow-y: auto;">';
        
        foreach ($debug_log as $entry) {
            echo esc_html($entry) . "\n\n";
        }
        
        echo '</pre></details>';
        echo '</div>';
    }
}

/**
 * AJAX ENDPOINT: Debug-Log für bestimmte Order abrufen
 */
add_action('wp_ajax_yprint_get_order_debug', 'yprint_ajax_get_order_debug');
function yprint_ajax_get_order_debug() {
    check_ajax_referer('yprint_debug_nonce', 'nonce');
    
    $order_id = intval($_POST['order_id']);
    if (!$order_id) {
        wp_send_json_error('Invalid order ID');
        return;
    }
    
    $debug_log = get_post_meta($order_id, '_yprint_debug_log', true);
    
    wp_send_json_success(array(
        'order_id' => $order_id,
        'debug_log' => $debug_log ?: array(),
        'has_debug' => !empty($debug_log)
    ));
}

error_log('YPRINT ORDER DEBUG TRACKER: Vollständig geladen und aktiv');

?>

<!-- 
VERWENDUNG:

1. Füge diesen Code komplett in includes/woocommerce.php hinzu
2. Mache eine Testbestellung (normal oder Express)
3. Gehe zu WooCommerce > Bestellungen > [Bestellung öffnen]
4. Du siehst das komplette Debug-Protokoll mit JEDEM Schritt
5. Jeder Hook, jede Design-Daten-Übertragung wird protokolliert

FEATURES:
- ✅ Verfolgt JEDEN Schritt der Design-Daten-Verarbeitung
- ✅ Sichtbar direkt in der WooCommerce-Bestellung (Admin)
- ✅ Emergency Recovery System mit Protokollierung
- ✅ Express Checkout Detection
- ✅ Farbkodiertes Debug-Log
- ✅ Session-zu-Order-Transfer
- ✅ AJAX-Endpoint für programmatischen Zugriff
-->