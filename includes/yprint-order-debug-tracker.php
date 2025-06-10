<?php
/**
 * YPRINT SIMPLIFIED ORDER DEBUG
 * Leichtgewichtiges Debug-System das den Checkout nicht stört
 */

// Verhindere direkte Aufrufe
if (!defined('ABSPATH')) {
    exit;
}

/**
 * SCHLANKES DEBUG-SYSTEM - nur passive Protokollierung
 */
class YPrint_Simple_Debug {
    
    private static $instance = null;
    private $debug_log = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Nur passive Hooks die garantiert nicht stören
        add_action('woocommerce_new_order', array($this, 'log_final_order'), 999, 1);
        add_action('woocommerce_admin_order_data_after_order_details', array($this, 'display_debug_in_admin'));
    }
    
   public function log_final_order($order_id) {
    $timestamp = current_time('Y-m-d H:i:s');
    $order = wc_get_order($order_id);
    
    if (!$order) return;
    
    $debug_data = array();
    $design_count = 0;
    $trail = array();
    
    // SCHRITT 1: Prüfe aktuellen Cart-Status
    $trail[] = "=== CART ANALYSIS ===";
    if (WC()->cart && !WC()->cart->is_empty()) {
        $trail[] = "✅ Cart available with " . WC()->cart->get_cart_contents_count() . " items";
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $has_cart_design = isset($cart_item['print_design']);
            $trail[] = "Cart Item $cart_item_key: " . ($has_cart_design ? "HAS DESIGN ✅" : "NO DESIGN ❌");
            
            if ($has_cart_design) {
                $design_data = $cart_item['print_design'];
                $trail[] = "  └─ Design ID: " . ($design_data['design_id'] ?? 'MISSING');
                $trail[] = "  └─ Template ID: " . ($design_data['template_id'] ?? 'MISSING');
            }
        }
    } else {
        $trail[] = "❌ Cart is empty or unavailable";
    }
    
    // SCHRITT 2: Prüfe Session-Backup
    $trail[] = "=== SESSION BACKUP ANALYSIS ===";
    if (WC()->session) {
        $session_backup = WC()->session->get('yprint_express_design_backup');
        if (!empty($session_backup)) {
            $trail[] = "✅ Session backup found with " . count($session_backup) . " designs";
            foreach ($session_backup as $key => $design) {
                $trail[] = "  └─ Backup Key $key: Design ID " . ($design['design_id'] ?? 'MISSING');
            }
        } else {
            $trail[] = "❌ No session backup found";
        }
    } else {
        $trail[] = "❌ WC Session unavailable";
    }
    
    // SCHRITT 3: Analysiere Order Items detailliert
    $trail[] = "=== ORDER ITEMS ANALYSIS ===";
    foreach ($order->get_items() as $item_id => $item) {
        $product_id = $item->get_product_id();
        $item_name = $item->get_name();
        
        // Prüfe alle möglichen Design-Meta-Felder
        $design_meta = $item->get_meta('print_design');
        $design_transferred = $item->get_meta('_yprint_design_transferred');
        $backup_applied = $item->get_meta('_yprint_design_backup_applied');
        $cart_item_key = $item->get_meta('_cart_item_key');
        
        $has_design = !empty($design_meta);
        if ($has_design) {
            $design_count++;
        }
        
        $trail[] = "Item $item_id ($item_name):";
        $trail[] = "  ├─ Product ID: $product_id";
        $trail[] = "  ├─ Cart Key: " . ($cart_item_key ?: 'MISSING');
        $trail[] = "  ├─ Has Design: " . ($has_design ? 'YES ✅' : 'NO ❌');
        $trail[] = "  ├─ Transfer Flag: " . ($design_transferred ?: 'MISSING');
        $trail[] = "  └─ Backup Applied: " . ($backup_applied ?: 'MISSING');
        
        $debug_data[] = array(
            'item_id' => $item_id,
            'product_id' => $product_id,
            'name' => $item_name,
            'cart_key' => $cart_item_key,
            'has_design' => $has_design,
            'design_data' => $design_meta,
            'transfer_timestamp' => $design_transferred,
            'backup_applied' => $backup_applied
        );
    }
    
    // SCHRITT 4: Prüfe Hook-Execution-History
    $trail[] = "=== HOOK EXECUTION HISTORY ===";
    $hook_history = get_option('yprint_hook_execution_log', array());
    $recent_hooks = array_slice($hook_history, -10); // Letzte 10 Hook-Ausführungen
    
    foreach ($recent_hooks as $hook_entry) {
        if (isset($hook_entry['timestamp']) && 
            strtotime($hook_entry['timestamp']) > (time() - 300)) { // Letzte 5 Minuten
            $trail[] = "  └─ " . $hook_entry['hook'] . " @ " . $hook_entry['timestamp'];
        }
    }
    
    // Bestimme Root Cause
    $root_cause = $this->determine_root_cause($debug_data, $trail);
    $trail[] = "=== ROOT CAUSE ANALYSIS ===";
    $trail[] = "🔍 " . $root_cause;
    
    // Speichere erweiterte Debug-Info
    $summary = array(
        'timestamp' => $timestamp,
        'order_id' => $order_id,
        'total_items' => count($debug_data),
        'design_items' => $design_count,
        'status' => ($design_count > 0) ? 'SUCCESS' : 'NO_DESIGNS',
        'root_cause' => $root_cause,
        'investigation_trail' => $trail,
        'items' => $debug_data
    );
    
    update_post_meta($order_id, '_yprint_debug_summary', $summary);
    
    // Detaillierter Error-Log
    error_log("YPrint Order $order_id Debug: $design_count design items | Root Cause: $root_cause");
}

/**
 * Bestimme die wahrscheinliche Ursache des Problems
 */
private function determine_root_cause($debug_data, $trail) {
    $trail_text = implode(' ', $trail);
    
    // Verschiedene Szenarien analysieren
    if (strpos($trail_text, 'Cart is empty') !== false) {
        return "Cart wurde vor Order-Erstellung geleert (Express Checkout?)";
    }
    
    if (strpos($trail_text, 'Cart available') !== false && 
        strpos($trail_text, 'HAS DESIGN') !== false &&
        strpos($trail_text, 'Transfer Flag: MISSING') !== false) {
        return "Design-Daten im Cart vorhanden, aber Hook-Transfer fehlgeschlagen";
    }
    
    if (strpos($trail_text, 'Session backup found') !== false &&
        strpos($trail_text, 'Backup Applied: MISSING') !== false) {
        return "Session-Backup vorhanden, aber nicht angewendet";
    }
    
    if (strpos($trail_text, 'Cart Key: MISSING') !== false) {
        return "Cart-Item-Keys fehlen - Cart-zu-Order-Zuordnung verloren";
    }
    
    if (strpos($trail_text, 'No session backup') !== false &&
        strpos($trail_text, 'Cart is empty') !== false) {
        return "Sowohl Cart als auch Session-Backup fehlen - Daten komplett verloren";
    }
    
    return "Unbekannte Ursache - weitere Analyse erforderlich";
}
    
    /**
     * Zeige Debug-Info im Admin (kompakt)
     */
    public function display_debug_in_admin($order) {
        $debug_summary = get_post_meta($order->get_id(), '_yprint_debug_summary', true);
        
        if (empty($debug_summary)) {
            echo '<div style="margin-top: 20px; padding: 15px; background: #fff3cd; border-left: 4px solid orange;">';
            echo '<h3>⚠️ YPrint Debug-Info</h3>';
            echo '<p>Kein Debug-Protokoll verfügbar. Debug-System war möglicherweise nicht aktiv.</p>';
            echo '</div>';
            return;
        }
        
        $status_color = ($debug_summary['status'] === 'SUCCESS') ? 'green' : 'red';
        
        echo '<div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid ' . $status_color . ';">';
        echo '<h3>🎨 YPrint Design-Status</h3>';
        
        echo '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 15px;">';
        echo '<div>';
        echo '<p><strong>Status:</strong> <span style="color: ' . $status_color . '; font-weight: bold;">' . $debug_summary['status'] . '</span></p>';
        echo '<p><strong>Design Items:</strong> ' . $debug_summary['design_items'] . ' von ' . $debug_summary['total_items'] . '</p>';
        echo '<p><strong>Timestamp:</strong> ' . $debug_summary['timestamp'] . '</p>';
        echo '</div>';
        echo '<div>';
        if (isset($debug_summary['root_cause'])) {
            echo '<p><strong>🔍 Wahrscheinliche Ursache:</strong></p>';
            echo '<p style="background: #fff; padding: 8px; border: 1px solid #ddd; font-style: italic;">' . 
                 esc_html($debug_summary['root_cause']) . '</p>';
        }
        echo '</div>';
        echo '</div>';
        
        // Investigation Trail
        if (!empty($debug_summary['investigation_trail'])) {
            echo '<details style="margin-top: 15px;"><summary><strong>🔍 Detaillierte Untersuchung</strong></summary>';
            echo '<div style="background: #fff; padding: 10px; border: 1px solid #ddd; font-family: monospace; font-size: 12px; white-space: pre-wrap; max-height: 300px; overflow-y: auto; margin-top: 10px;">';
            
            foreach ($debug_summary['investigation_trail'] as $trail_entry) {
                $entry = esc_html($trail_entry);
                
                // Coloriere verschiedene Einträge
                if (strpos($entry, '✅') !== false) {
                    $entry = '<span style="color: green;">' . $entry . '</span>';
                } elseif (strpos($entry, '❌') !== false) {
                    $entry = '<span style="color: red;">' . $entry . '</span>';
                } elseif (strpos($entry, '===') !== false) {
                    $entry = '<strong style="color: #0073aa;">' . $entry . '</strong>';
                } elseif (strpos($entry, '🔍') !== false) {
                    $entry = '<span style="color: orange; font-weight: bold;">' . $entry . '</span>';
                }
                
                echo $entry . "\n";
            }
            
            echo '</div></details>';
        }
        
        // Item Details
        if (!empty($debug_summary['items'])) {
            echo '<details style="margin-top: 15px;"><summary><strong>📦 Item Details</strong></summary>';
            echo '<table style="width: 100%; margin-top: 10px; border-collapse: collapse;">';
            echo '<tr style="background: #f0f0f0;">';
            echo '<th style="padding: 8px; border: 1px solid #ddd;">Item</th>';
            echo '<th style="padding: 8px; border: 1px solid #ddd;">Design</th>';
            echo '<th style="padding: 8px; border: 1px solid #ddd;">Cart Key</th>';
            echo '<th style="padding: 8px; border: 1px solid #ddd;">Transfer</th>';
            echo '</tr>';
            
            foreach ($debug_summary['items'] as $item) {
                $icon = $item['has_design'] ? '✅' : '❌';
                $transfer_info = '';
                
                if (isset($item['transfer_timestamp']) && !empty($item['transfer_timestamp'])) {
                    $transfer_info = '📅 ' . $item['transfer_timestamp'];
                } elseif (isset($item['backup_applied']) && !empty($item['backup_applied'])) {
                    $transfer_info = '🔄 ' . $item['backup_applied'];
                } else {
                    $transfer_info = '❌ Kein Transfer';
                }
                
                echo '<tr>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;">' . $icon . ' ' . esc_html($item['name']) . '</td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;">' . ($item['has_design'] ? 'Ja' : 'Nein') . '</td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd;">' . ($item['cart_key'] ?: 'Fehlt') . '</td>';
                echo '<td style="padding: 8px; border: 1px solid #ddd; font-size: 11px;">' . $transfer_info . '</td>';
                echo '</tr>';
            }
            
            echo '</table></details>';
        }
        
        echo '</div>';
    }
}

// Initialisiere das schlanke Debug-System
add_action('init', function() {
    YPrint_Simple_Debug::get_instance();
});

?>