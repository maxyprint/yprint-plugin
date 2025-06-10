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
        
        $debug_trail = array();
        $step_counter = 1;
        
        // === SCHRITT 1: CART DETAILED ANALYSIS ===
        $debug_trail[] = "SCHRITT $step_counter: CART DETAILED ANALYSIS";
        $step_counter++;
        
        $cart_analysis = $this->analyze_cart_state();
        $debug_trail = array_merge($debug_trail, $cart_analysis['trail']);
        
        // === SCHRITT 2: SESSION DETAILED ANALYSIS ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: SESSION DETAILED ANALYSIS";
        $step_counter++;
        
        $session_analysis = $this->analyze_session_state();
        $debug_trail = array_merge($debug_trail, $session_analysis['trail']);
        
        // === SCHRITT 3: HOOK EXECUTION DETAILED ANALYSIS ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: HOOK EXECUTION DETAILED ANALYSIS";
        $step_counter++;
        
        $hook_analysis = $this->analyze_hook_execution();
        $debug_trail = array_merge($debug_trail, $hook_analysis['trail']);
        
        // === SCHRITT 4: ORDER CREATION DETAILED ANALYSIS ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: ORDER CREATION DETAILED ANALYSIS";
        $step_counter++;
        
        $order_analysis = $this->analyze_order_items($order);
        $debug_trail = array_merge($debug_trail, $order_analysis['trail']);
        
        // === SCHRITT 5: FUNCTION AVAILABILITY CHECK ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: FUNCTION AVAILABILITY CHECK";
        $step_counter++;
        
        $function_analysis = $this->analyze_function_availability();
        $debug_trail = array_merge($debug_trail, $function_analysis['trail']);
        
        // === SCHRITT 6: PRECISE ROOT CAUSE DETERMINATION ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: PRECISE ROOT CAUSE DETERMINATION";
        
        $root_cause = $this->determine_precise_root_cause(
            $cart_analysis, 
            $session_analysis, 
            $hook_analysis, 
            $order_analysis,
            $function_analysis
        );
        
        $debug_trail[] = "🎯 PRECISE ROOT CAUSE: " . $root_cause['cause'];
        $debug_trail[] = "💡 RECOMMENDED ACTION: " . $root_cause['action'];
        $debug_trail[] = "🔧 TECHNICAL DETAILS: " . $root_cause['technical'];
        
        // Speichere alle Analysen
        $summary = array(
            'timestamp' => $timestamp,
            'order_id' => $order_id,
            'status' => ($order_analysis['design_count'] > 0) ? 'SUCCESS' : 'FAILED',
            'design_items_found' => $order_analysis['design_count'],
            'total_items' => $order_analysis['total_items'],
            'cart_analysis' => $cart_analysis,
            'session_analysis' => $session_analysis,
            'hook_analysis' => $hook_analysis,
            'order_analysis' => $order_analysis,
            'function_analysis' => $function_analysis,
            'root_cause' => $root_cause,
            'debug_trail' => $debug_trail
        );
        
        update_post_meta($order_id, '_yprint_debug_summary', $summary);
        error_log("YPrint Detailed Debug for Order $order_id: " . $root_cause['cause']);
    }
    
    /**
     * DETAILLIERTE CART-ANALYSE
     */
    private function analyze_cart_state() {
        $trail = array();
        $cart_items = array();
        $design_count = 0;
        
        $trail[] = "├─ Checking WC()->cart availability...";
        
        if (!function_exists('WC') || !WC()) {
            $trail[] = "│  ❌ WC() function not available";
            return array('trail' => $trail, 'available' => false, 'design_count' => 0);
        }
        
        $trail[] = "│  ✅ WC() function available";
        
        if (!WC()->cart) {
            $trail[] = "│  ❌ WC()->cart object is null";
            return array('trail' => $trail, 'available' => false, 'design_count' => 0);
        }
        
        $trail[] = "│  ✅ WC()->cart object exists";
        $trail[] = "├─ Cart status: " . (WC()->cart->is_empty() ? "EMPTY" : "HAS ITEMS");
        $trail[] = "├─ Cart contents count: " . WC()->cart->get_cart_contents_count();
        
        if (WC()->cart->is_empty()) {
            $trail[] = "│  ⚠️  Cart is empty - this explains missing designs";
            return array('trail' => $trail, 'available' => true, 'empty' => true, 'design_count' => 0);
        }
        
        $trail[] = "├─ Analyzing individual cart items...";
        
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $trail[] = "│  ├─ Cart Item: $cart_item_key";
            $trail[] = "│  │  ├─ Product ID: " . ($cart_item['product_id'] ?? 'MISSING');
            $trail[] = "│  │  ├─ Quantity: " . ($cart_item['quantity'] ?? 'MISSING');
            
            // Prüfe Design-Daten sehr detailliert
            if (isset($cart_item['print_design'])) {
                $design_count++;
                $design = $cart_item['print_design'];
                $trail[] = "│  │  ├─ ✅ HAS DESIGN DATA";
                $trail[] = "│  │  │  ├─ Design ID: " . ($design['design_id'] ?? 'MISSING');
                $trail[] = "│  │  │  ├─ Template ID: " . ($design['template_id'] ?? 'MISSING');
                $trail[] = "│  │  │  ├─ Design Name: " . ($design['name'] ?? 'MISSING');
                $trail[] = "│  │  │  └─ Preview URL: " . (isset($design['preview_url']) ? 'SET' : 'MISSING');
                
                // Prüfe Datenintegrität
                if (empty($design['design_id'])) {
                    $trail[] = "│  │  │  ⚠️  WARNING: Design ID is empty!";
                }
            } else {
                $trail[] = "│  │  └─ ❌ NO DESIGN DATA";
            }
            
            // Prüfe weitere relevante Keys
            $other_keys = array_keys($cart_item);
            $relevant_keys = array_filter($other_keys, function($key) {
                return strpos($key, 'design') !== false || strpos($key, 'print') !== false;
            });
            
            if (!empty($relevant_keys)) {
                $trail[] = "│  │  └─ Other design-related keys: " . implode(', ', $relevant_keys);
            }
            
            $cart_items[$cart_item_key] = array(
                'product_id' => $cart_item['product_id'] ?? null,
                'has_design' => isset($cart_item['print_design']),
                'design_data' => $cart_item['print_design'] ?? null
            );
        }
        
        $trail[] = "└─ CART SUMMARY: $design_count design items found";
        
        return array(
            'trail' => $trail,
            'available' => true,
            'empty' => false,
            'design_count' => $design_count,
            'items' => $cart_items
        );
    }
    
    /**
     * DETAILLIERTE SESSION-ANALYSE
     */
    private function analyze_session_state() {
        $trail = array();
        
        $trail[] = "├─ Checking WC()->session availability...";
        
        if (!WC() || !WC()->session) {
            $trail[] = "│  ❌ WC()->session not available";
            return array('trail' => $trail, 'available' => false);
        }
        
        $trail[] = "│  ✅ WC()->session available";
        $trail[] = "├─ Session ID: " . WC()->session->get_customer_id();
        
        // Prüfe Express Backup
        $trail[] = "├─ Checking express design backup...";
        $express_backup = WC()->session->get('yprint_express_design_backup');
        
        if (empty($express_backup)) {
            $trail[] = "│  ❌ No express design backup found";
            $trail[] = "│  └─ Key 'yprint_express_design_backup' is empty or missing";
        } else {
            $trail[] = "│  ✅ Express design backup found";
            $trail[] = "│  ├─ Backup contains " . count($express_backup) . " items";
            
            foreach ($express_backup as $backup_key => $backup_design) {
                $trail[] = "│  │  ├─ Backup Key: $backup_key";
                $trail[] = "│  │  │  ├─ Design ID: " . ($backup_design['design_id'] ?? 'MISSING');
                $trail[] = "│  │  │  └─ Template ID: " . ($backup_design['template_id'] ?? 'MISSING');
            }
        }
        
        // Prüfe andere relevante Session-Daten
        $trail[] = "├─ Checking other session data...";
        $session_data = WC()->session->get_session_data();
        $design_related_keys = array_filter(array_keys($session_data), function($key) {
            return strpos($key, 'design') !== false || strpos($key, 'print') !== false || strpos($key, 'yprint') !== false;
        });
        
        if (!empty($design_related_keys)) {
            $trail[] = "│  ├─ Design-related session keys found: " . implode(', ', $design_related_keys);
        } else {
            $trail[] = "│  └─ No design-related session keys found";
        }
        
        return array(
            'trail' => $trail,
            'available' => true,
            'has_backup' => !empty($express_backup),
            'backup_data' => $express_backup,
            'session_keys' => $design_related_keys
        );
    }
    
    /**
     * DETAILLIERTE HOOK-ANALYSE
     */
    private function analyze_hook_execution() {
        $trail = array();
        
        $trail[] = "├─ Checking hook execution log...";
        $hook_log = get_option('yprint_hook_execution_log', array());
        
        if (empty($hook_log)) {
            $trail[] = "│  ❌ No hook execution log found";
            $trail[] = "│  └─ Hook tracking may not be working";
            return array('trail' => $trail, 'hooks_logged' => false);
        }
        
        $trail[] = "│  ✅ Hook execution log found with " . count($hook_log) . " entries";
        
        // Analysiere letzte 10 Minuten
        $recent_hooks = array_filter($hook_log, function($entry) {
            return isset($entry['timestamp']) && 
                   strtotime($entry['timestamp']) > (time() - 600); // Letzte 10 Minuten
        });
        
        $trail[] = "├─ Recent hooks (last 10 minutes): " . count($recent_hooks);
        
        $critical_hooks = array(
            'checkout_create_order_line_item',
            'new_order_backup_check',
            'backup_transfer_attempt',
            'backup_transfer_result'
        );
        
        foreach ($critical_hooks as $hook_name) {
            $hook_found = false;
            foreach ($recent_hooks as $hook_entry) {
                if (strpos($hook_entry['hook'], $hook_name) !== false) {
                    $hook_found = true;
                    $trail[] = "│  ├─ ✅ $hook_name executed @ " . $hook_entry['timestamp'];
                    if (!empty($hook_entry['details'])) {
                        $trail[] = "│  │  └─ Details: " . $hook_entry['details'];
                    }
                    break;
                }
            }
            
            if (!$hook_found) {
                $trail[] = "│  ├─ ❌ $hook_name NOT executed";
                $trail[] = "│  │  └─ This is a critical missing hook!";
            }
        }
        
        return array(
            'trail' => $trail,
            'hooks_logged' => true,
            'recent_hooks' => $recent_hooks,
            'critical_hooks_missing' => array_filter($critical_hooks, function($hook) use ($recent_hooks) {
                foreach ($recent_hooks as $entry) {
                    if (strpos($entry['hook'], $hook) !== false) {
                        return false;
                    }
                }
                return true;
            })
        );
    }
    
    /**
     * DETAILLIERTE ORDER-ANALYSE
     */
    private function analyze_order_items($order) {
        $trail = array();
        $design_count = 0;
        $items_analysis = array();
        
        $trail[] = "├─ Analyzing order items...";
        $trail[] = "│  ├─ Order ID: " . $order->get_id();
        $trail[] = "│  ├─ Order Status: " . $order->get_status();
        $trail[] = "│  └─ Total Items: " . count($order->get_items());
        
        foreach ($order->get_items() as $item_id => $item) {
            $trail[] = "│  ├─ Order Item $item_id:";
            $trail[] = "│  │  ├─ Name: " . $item->get_name();
            $trail[] = "│  │  ├─ Product ID: " . $item->get_product_id();
            
            // Prüfe alle Meta-Daten
            $meta_data = $item->get_meta_data();
            $trail[] = "│  │  ├─ Total Meta Fields: " . count($meta_data);
            
            // Prüfe spezifische Design-Meta-Felder
            $design_meta = $item->get_meta('print_design');
            $cart_key = $item->get_meta('_cart_item_key');
            $transfer_flag = $item->get_meta('_yprint_design_transferred');
            $backup_flag = $item->get_meta('_yprint_design_backup_applied');
            
            if (!empty($design_meta)) {
                $design_count++;
                $trail[] = "│  │  ├─ ✅ HAS DESIGN META";
                $trail[] = "│  │  │  └─ Design ID: " . (is_array($design_meta) ? ($design_meta['design_id'] ?? 'MISSING') : 'INVALID_FORMAT');
            } else {
                $trail[] = "│  │  ├─ ❌ NO DESIGN META";
            }
            
            $trail[] = "│  │  ├─ Cart Key: " . ($cart_key ?: 'MISSING');
            $trail[] = "│  │  ├─ Transfer Flag: " . ($transfer_flag ?: 'MISSING');
            $trail[] = "│  │  └─ Backup Flag: " . ($backup_flag ?: 'MISSING');
            
            // Liste alle Meta-Keys auf
            $all_meta_keys = array_map(function($meta) {
                return $meta->key;
            }, $meta_data);
            
            $design_related_meta = array_filter($all_meta_keys, function($key) {
                return strpos($key, 'design') !== false || strpos($key, 'print') !== false;
            });
            
            if (!empty($design_related_meta)) {
                $trail[] = "│  │  └─ Design-related meta keys: " . implode(', ', $design_related_meta);
            }
            
            $items_analysis[$item_id] = array(
                'name' => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'has_design' => !empty($design_meta),
                'cart_key' => $cart_key,
                'design_meta' => $design_meta
            );
        }
        
        $trail[] = "└─ ORDER SUMMARY: $design_count design items found in order";
        
        return array(
            'trail' => $trail,
            'design_count' => $design_count,
            'total_items' => count($order->get_items()),
            'items' => $items_analysis
        );
    }
    
    /**
     * FUNCTION AVAILABILITY CHECK
     */
    private function analyze_function_availability() {
        $trail = array();
        
        $trail[] = "├─ Checking critical function availability...";
        
        $critical_functions = array(
            'WC' => function_exists('WC'),
            'wc_get_order' => function_exists('wc_get_order'),
            'wp_verify_nonce' => function_exists('wp_verify_nonce'),
            'current_time' => function_exists('current_time'),
            'get_post_meta' => function_exists('get_post_meta'),
            'update_post_meta' => function_exists('update_post_meta')
        );
        
        foreach ($critical_functions as $func_name => $available) {
            $trail[] = "│  ├─ $func_name: " . ($available ? '✅ Available' : '❌ Missing');
        }
        
        // Prüfe Klassen
        $critical_classes = array(
            'WC_Cart' => class_exists('WC_Cart'),
            'WC_Order' => class_exists('WC_Order'),
            'WC_Session_Handler' => class_exists('WC_Session_Handler')
        );
        
        $trail[] = "├─ Checking critical class availability...";
        foreach ($critical_classes as $class_name => $available) {
            $trail[] = "│  ├─ $class_name: " . ($available ? '✅ Available' : '❌ Missing');
        }
        
        // Prüfe Custom Functions
        $custom_functions = array(
            'yprint_tracked_design_transfer' => function_exists('yprint_tracked_design_transfer'),
            'yprint_tracked_backup_transfer' => function_exists('yprint_tracked_backup_transfer'),
            'yprint_log_hook_execution' => function_exists('yprint_log_hook_execution')
        );
        
        $trail[] = "├─ Checking custom function availability...";
        foreach ($custom_functions as $func_name => $available) {
            $trail[] = "│  └─ $func_name: " . ($available ? '✅ Available' : '❌ Missing');
        }
        
        return array(
            'trail' => $trail,
            'wordpress_functions' => $critical_functions,
            'woocommerce_classes' => $critical_classes,
            'custom_functions' => $custom_functions
        );
    }
    
    /**
     * PRÄZISE ROOT CAUSE BESTIMMUNG
     */
    private function determine_precise_root_cause($cart_analysis, $session_analysis, $hook_analysis, $order_analysis, $function_analysis) {
        
        // Szenario 1: Funktionen fehlen
        if (in_array(false, $function_analysis['custom_functions'])) {
            return array(
                'cause' => 'Custom Transfer Functions Missing',
                'action' => 'Re-implement yprint_tracked_design_transfer and yprint_tracked_backup_transfer functions',
                'technical' => 'One or more custom functions for design data transfer are not loaded or defined'
            );
        }
        
        // Szenario 2: Cart hat Design, aber Hook wird nicht ausgeführt
        if ($cart_analysis['design_count'] > 0 && 
            in_array('checkout_create_order_line_item', $hook_analysis['critical_hooks_missing'] ?? array())) {
            return array(
                'cause' => 'Design Transfer Hook Not Executed',
                'action' => 'Check if woocommerce_checkout_create_order_line_item hook is properly registered',
                'technical' => 'Cart contains design data but the primary transfer hook was never called'
            );
        }
        
        // Szenario 3: Cart leer, aber kein Session Backup
        if ($cart_analysis['design_count'] == 0 && !$session_analysis['has_backup']) {
            return array(
                'cause' => 'Both Cart and Session Backup Empty',
                'action' => 'Investigate why express payment backup was not created',
                'technical' => 'Cart is empty during order creation and no session backup exists for express payments'
            );
        }
        
        // Szenario 4: Hook ausgeführt, aber Daten kommen nicht an
        if ($cart_analysis['design_count'] > 0 && 
            !in_array('checkout_create_order_line_item', $hook_analysis['critical_hooks_missing'] ?? array()) &&
            $order_analysis['design_count'] == 0) {
            return array(
                'cause' => 'Hook Executed But Data Transfer Failed',
                'action' => 'Debug the yprint_tracked_design_transfer function - data may be corrupted during transfer',
                'technical' => 'Transfer hook was called but design data did not reach order items'
            );
        }
        
        // Standard-Fall
        return array(
            'cause' => 'Complex Multi-Factor Issue',
            'action' => 'Manual investigation required - check all debug sections above',
            'technical' => 'Multiple potential issues detected, requires detailed analysis'
        );
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