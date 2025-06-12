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

// Sichere Error-Log Ausgabe
$log_message = "YPrint Detailed Debug for Order $order_id: ";
if (is_array($root_cause) && isset($root_cause['cause'])) {
    $log_message .= $root_cause['cause'];
} else {
    $log_message .= "Debug analysis completed";
}
error_log($log_message);
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
     * DETAILLIERTE HOOK-ANALYSE (inkl. Express Checkout)
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
        
        // Erweiterte Hook-Liste für Express Checkout
        $critical_hooks = array(
            'checkout_create_order_line_item',
            'express_checkout_order_created',
            'express_order_design_verification',
            'new_order_backup_check',
            'backup_transfer_attempt',
            'backup_transfer_result'
        );
        
        $express_hooks = array(
            'express_checkout_order_created',
            'express_order_design_verification'
        );
        
        $standard_hooks = array(
            'checkout_create_order_line_item'
        );
        
        // Bestimme Checkout-Typ
        $is_express_checkout = false;
        $is_standard_checkout = false;
        
        foreach ($recent_hooks as $hook_entry) {
            if (in_array($hook_entry['hook'], $express_hooks)) {
                $is_express_checkout = true;
            }
            if (in_array($hook_entry['hook'], $standard_hooks)) {
                $is_standard_checkout = true;
            }
        }
        
        if ($is_express_checkout) {
            $trail[] = "│  🔍 CHECKOUT TYPE: EXPRESS CHECKOUT detected";
        } elseif ($is_standard_checkout) {
            $trail[] = "│  🔍 CHECKOUT TYPE: STANDARD CHECKOUT detected";
        } else {
            $trail[] = "│  ⚠️  CHECKOUT TYPE: Unknown or no checkout hooks found";
        }
        
        foreach ($critical_hooks as $hook_name) {
            $hook_found = false;
            foreach ($recent_hooks as $hook_entry) {
                if (strpos($hook_entry['hook'], $hook_name) !== false) {
                    $hook_found = true;
                    
                    // Spezielle Kennzeichnung für Express vs Standard
                    $hook_type = '';
                    if (in_array($hook_name, $express_hooks)) {
                        $hook_type = ' [EXPRESS]';
                    } elseif (in_array($hook_name, $standard_hooks)) {
                        $hook_type = ' [STANDARD]';
                    }
                    
                    $trail[] = "│  ├─ ✅ $hook_name$hook_type executed @ " . $hook_entry['timestamp'];
                    if (!empty($hook_entry['details'])) {
                        $trail[] = "│  │  └─ Details: " . $hook_entry['details'];
                    }
                    break;
                }
            }
            
            if (!$hook_found) {
                // Nur als kritisch markieren wenn es für den erkannten Checkout-Typ relevant ist
                $is_critical = false;
                if ($is_express_checkout && in_array($hook_name, $express_hooks)) {
                    $is_critical = true;
                } elseif ($is_standard_checkout && in_array($hook_name, $standard_hooks)) {
                    $is_critical = true;
                } elseif (!$is_express_checkout && !$is_standard_checkout) {
                    $is_critical = true; // Unknown type - mark all as critical
                }
                
                if ($is_critical) {
                    $trail[] = "│  ├─ ❌ $hook_name NOT executed";
                    $trail[] = "│  │  └─ This is a critical missing hook for this checkout type!";
                } else {
                    $trail[] = "│  ├─ ⚪ $hook_name not executed (not required for this checkout type)";
                }
            }
        }
        
        return array(
            'trail' => $trail,
            'hooks_logged' => true,
            'recent_hooks' => $recent_hooks,
            'is_express_checkout' => $is_express_checkout,
            'is_standard_checkout' => $is_standard_checkout,
            'critical_hooks_missing' => array_filter($critical_hooks, function($hook) use ($recent_hooks, $is_express_checkout, $is_standard_checkout, $express_hooks, $standard_hooks) {
                // Prüfe ob Hook ausgeführt wurde
                $hook_executed = false;
                foreach ($recent_hooks as $entry) {
                    if (strpos($entry['hook'], $hook) !== false) {
                        $hook_executed = true;
                        break;
                    }
                }
                
                if ($hook_executed) {
                    return false; // Hook wurde ausgeführt
                }
                
                // Prüfe ob Hook für aktuellen Checkout-Typ relevant ist
                if ($is_express_checkout && in_array($hook, $express_hooks)) {
                    return true; // Express checkout und Hook fehlt
                } elseif ($is_standard_checkout && in_array($hook, $standard_hooks)) {
                    return true; // Standard checkout und Hook fehlt
                } elseif (!$is_express_checkout && !$is_standard_checkout) {
                    return true; // Unbekannter Typ - alle als fehlend markieren
                }
                
                return false; // Hook nicht relevant für aktuellen Checkout-Typ
            })
        );
    }
    
    /**
 * DETAILLIERTE ORDER-ANALYSE MIT PRINT PROVIDER BEREITSCHAFTSPRÜFUNG
 */
private function analyze_order_items($order) {
    $trail = array();
    $design_count = 0;
    $items_analysis = array();
    $overall_completeness = array();
    $print_provider_ready_count = 0;
    
    $trail[] = "├─ Analyzing order items for Print Provider readiness...";
    $trail[] = "│  ├─ Order ID: " . $order->get_id();
    $trail[] = "│  ├─ Order Status: " . $order->get_status();
    $trail[] = "│  └─ Total Items: " . count($order->get_items());
    
    // Definiere alle erforderlichen Meta-Felder für Print Provider E-Mail System
    $required_meta_fields = array(
        // Basis Design-Daten (KRITISCH für Print Provider)
        '_design_id' => array('type' => 'integer', 'critical' => true, 'print_provider_required' => true),
        '_design_name' => array('type' => 'string', 'critical' => true, 'print_provider_required' => true),
        '_design_color' => array('type' => 'string', 'critical' => false, 'print_provider_required' => true), 
        '_design_size' => array('type' => 'string', 'critical' => false, 'print_provider_required' => true),
        '_design_preview_url' => array('type' => 'string', 'critical' => true, 'print_provider_required' => true),
        
        // Dimensionen (KRITISCH für Druck)
        '_design_width_cm' => array('type' => 'numeric', 'critical' => true, 'print_provider_required' => true),
        '_design_height_cm' => array('type' => 'numeric', 'critical' => true, 'print_provider_required' => true),
        
        // Multi-View Support (KRITISCH für Front/Back-Print)
        '_design_has_multiple_images' => array('type' => 'boolean', 'critical' => false, 'print_provider_required' => true),
        '_design_product_images' => array('type' => 'json', 'critical' => true, 'print_provider_required' => true),
        '_design_images' => array('type' => 'json', 'critical' => true, 'print_provider_required' => true),
        
        // Legacy-Kompatibilität (FALLBACK)
        '_design_image_url' => array('type' => 'string', 'critical' => false, 'print_provider_required' => false)
    );
    
    foreach ($order->get_items() as $item_id => $item) {
        $trail[] = "│  ├─ Order Item $item_id:";
        $trail[] = "│  │  ├─ Name: " . $item->get_name();
        $trail[] = "│  │  ├─ Product ID: " . $item->get_product_id();
        
        // Prüfe alle Meta-Daten
        $meta_data = $item->get_meta_data();
        $trail[] = "│  │  ├─ Total Meta Fields: " . count($meta_data);
        
        // Detaillierte Prüfung aller erforderlichen Meta-Felder
        $trail[] = "│  │  ├─ PRINT PROVIDER META-FIELD ANALYSIS:";
        
        $field_status = array();
        $has_design_data = false;
        $missing_critical_fields = array();
        $missing_optional_fields = array();
        $multi_view_analysis = array();
        $print_provider_warnings = array();
        
        foreach ($required_meta_fields as $field_name => $field_config) {
            $expected_type = $field_config['type'];
            $is_critical = $field_config['critical'];
            $print_provider_required = $field_config['print_provider_required'];
            $field_value = $item->get_meta($field_name);
            $is_present = !empty($field_value) || ($field_value === '0' || $field_value === 0 || $field_value === false);
            $is_valid = $this->validate_meta_field_type($field_value, $expected_type);
            
            if ($is_present && $is_valid) {
                $display_value = $this->format_meta_value_for_display($field_value, $expected_type);
                $trail[] = "│  │  │  ├─ ✅ $field_name: " . $display_value;
                $field_status[$field_name] = 'valid';
                
                // Design ID ist der Haupt-Indikator für Design-Produkt
                if ($field_name === '_design_id') {
                    $has_design_data = true;
                    $design_count++;
                }
                
                // Spezielle Multi-View Validierung
                if ($field_name === '_design_product_images') {
                    $multi_view_analysis['product_images'] = $this->analyze_multi_view_data($field_value, 'product_images', $trail);
                } elseif ($field_name === '_design_images') {
                    $multi_view_analysis['design_images'] = $this->analyze_multi_view_data($field_value, 'design_images', $trail);
                }
                
            } elseif ($is_present && !$is_valid) {
                $trail[] = "│  │  │  ├─ ⚠️  $field_name: INVALID TYPE (expected $expected_type, got " . gettype($field_value) . ")";
                $field_status[$field_name] = 'invalid_type';
                
                if ($print_provider_required) {
                    $print_provider_warnings[] = "$field_name has invalid format";
                }
                
                if ($is_critical) {
                    $missing_critical_fields[] = $field_name;
                } else {
                    $missing_optional_fields[] = $field_name;
                }
                
            } else {
                $trail[] = "│  │  │  ├─ ❌ $field_name: MISSING";
                $field_status[$field_name] = 'missing';
                
                if ($print_provider_required) {
                    $print_provider_warnings[] = "$field_name is required for print provider";
                }
                
                if ($is_critical) {
                    $missing_critical_fields[] = $field_name;
                } else {
                    $missing_optional_fields[] = $field_name;
                }
            }
        }
        
        // Legacy Design Meta prüfen (für Kompatibilität)
        $design_meta = $item->get_meta('print_design');
        if (!empty($design_meta)) {
            $trail[] = "│  │  │  ├─ ✅ print_design (legacy): FOUND";
            $trail[] = "│  │  │  │  └─ Legacy Design ID: " . (is_array($design_meta) ? ($design_meta['design_id'] ?? 'MISSING') : 'INVALID_FORMAT');
        } else {
            $trail[] = "│  │  │  ├─ ❌ print_design (legacy): MISSING";
        }
        
        // Berechne Vollständigkeit
        $total_fields = count($required_meta_fields);
        $valid_fields = count(array_filter($field_status, function($status) {
            return $status === 'valid';
        }));
        $completeness_percentage = round(($valid_fields / $total_fields) * 100, 1);
        
        // Status-Zusammenfassung für dieses Item
        $trail[] = "│  │  │  └─ COMPLETENESS: $completeness_percentage% ($valid_fields/$total_fields fields valid)";
        
        if (!empty($missing_critical_fields)) {
            $trail[] = "│  │  ├─ 🚨 CRITICAL MISSING: " . implode(', ', $missing_critical_fields);
        }
        
        if (!empty($missing_optional_fields)) {
            $trail[] = "│  │  ├─ ⚠️  OPTIONAL MISSING: " . implode(', ', $missing_optional_fields);
        }
        
        // Print Provider Bereitschaftsprüfung
        $critical_fields_valid = $has_design_data && 
                               $field_status['_design_name'] === 'valid' && 
                               $field_status['_design_preview_url'] === 'valid' &&
                               $field_status['_design_width_cm'] === 'valid' &&
                               $field_status['_design_height_cm'] === 'valid';
        
        $multi_view_ready = ($field_status['_design_product_images'] === 'valid' && 
                           $field_status['_design_images'] === 'valid' &&
                           isset($multi_view_analysis['product_images']) &&
                           isset($multi_view_analysis['design_images']) &&
                           $multi_view_analysis['product_images']['valid'] &&
                           $multi_view_analysis['design_images']['valid']);
        
        $legacy_fallback_ready = $field_status['_design_image_url'] === 'valid';
        
        $print_provider_ready = $critical_fields_valid && ($multi_view_ready || $legacy_fallback_ready);
        
        if ($print_provider_ready) {
            $print_provider_ready_count++;
        }
        
        $trail[] = "│  │  ├─ 🎯 PRINT PROVIDER READINESS ANALYSIS:";
        $trail[] = "│  │  │  ├─ Critical Fields: " . ($critical_fields_valid ? '✅ VALID' : '❌ MISSING');
        $trail[] = "│  │  │  ├─ Multi-View Data: " . ($multi_view_ready ? '✅ COMPLETE' : '❌ INCOMPLETE');
        $trail[] = "│  │  │  ├─ Legacy Fallback: " . ($legacy_fallback_ready ? '✅ AVAILABLE' : '❌ N/A');
        $trail[] = "│  │  │  └─ OVERALL STATUS: " . ($print_provider_ready ? '✅ READY TO SEND' : '❌ NOT READY');
        
        if (!empty($print_provider_warnings)) {
            $trail[] = "│  │  ├─ 🚨 PRINT PROVIDER WARNINGS:";
            foreach ($print_provider_warnings as $warning) {
                $trail[] = "│  │  │  └─ ⚠️  $warning";
            }
        }
        
        // Multi-View Zusammenfassung
        if (!empty($multi_view_analysis)) {
            $trail[] = "│  │  ├─ 📋 MULTI-VIEW SUMMARY:";
            
            if (isset($multi_view_analysis['product_images'])) {
                $pimg = $multi_view_analysis['product_images'];
                $trail[] = "│  │  │  ├─ Preview Images: " . $pimg['view_count'] . " views (" . 
                          implode(', ', $pimg['views_found']) . ")";
                if (!empty($pimg['issues'])) {
                    $trail[] = "│  │  │  │  └─ Issues: " . implode('; ', $pimg['issues']);
                }
            }
            
            if (isset($multi_view_analysis['design_images'])) {
                $dimg = $multi_view_analysis['design_images'];
                $trail[] = "│  │  │  └─ Design Files: " . $dimg['view_count'] . " views (" . 
                          implode(', ', $dimg['views_found']) . ")";
                if (!empty($dimg['issues'])) {
                    $trail[] = "│  │  │     └─ Issues: " . implode('; ', $dimg['issues']);
                }
            }
        }
        
        // Speichere detaillierte Analyse
        $items_analysis[$item_id] = array(
            'name' => $item->get_name(),
            'product_id' => $item->get_product_id(),
            'has_design' => $has_design_data,
            'completeness_percentage' => $completeness_percentage,
            'field_status' => $field_status,
            'missing_critical' => $missing_critical_fields,
            'missing_optional' => $missing_optional_fields,
            'email_ready' => $email_ready,
            'cart_key' => $item->get_meta('_cart_item_key'),
            'design_meta' => $design_meta
        );
        
        $overall_completeness[] = $completeness_percentage;
    }
    
    // Gesamtübersicht
    $avg_completeness = !empty($overall_completeness) ? round(array_sum($overall_completeness) / count($overall_completeness), 1) : 0;
    
    $trail[] = "";
    $trail[] = "└─ 📊 PRINT PROVIDER ORDER SUMMARY:";
    $trail[] = "   ├─ Design Items Found: $design_count";
    $trail[] = "   ├─ Print Provider Ready: $print_provider_ready_count of " . count($items_analysis);
    $trail[] = "   ├─ Average Data Completeness: $avg_completeness%";
    
    if ($print_provider_ready_count === count($items_analysis) && $design_count > 0) {
        $trail[] = "   └─ 🎉 STATUS: ORDER READY FOR PRINT PROVIDER!";
    } elseif ($print_provider_ready_count > 0) {
        $trail[] = "   └─ ⚠️  STATUS: PARTIALLY READY - Some items missing data";
    } else {
        $trail[] = "   └─ ❌ STATUS: NOT READY - Critical data missing";
    }
    
    return array(
        'trail' => $trail,
        'design_count' => $design_count,
        'total_items' => count($order->get_items()),
        'print_provider_ready_items' => $print_provider_ready_count,
        'average_completeness' => $avg_completeness,
        'items' => $items_analysis,
        'required_fields' => array_keys($required_meta_fields),
        'print_provider_ready' => $print_provider_ready_count === count($items_analysis) && $design_count > 0,
        'multi_view_support' => true
    );
}

/**
 * Validiere Meta-Feld-Typ
 */
private function validate_meta_field_type($value, $expected_type) {
    if (empty($value) && $value !== '0' && $value !== 0 && $value !== false) {
        return false;
    }
    
    switch ($expected_type) {
        case 'integer':
            return is_numeric($value) && (int)$value == $value;
            
        case 'string':
            return is_string($value) && strlen(trim($value)) > 0;
            
        case 'numeric':
            return is_numeric($value);
            
        case 'boolean':
            return is_bool($value) || in_array($value, array('1', '0', 1, 0, 'true', 'false'), true);
            
        case 'json':
            if (!is_string($value)) return false;
            json_decode($value);
            return json_last_error() === JSON_ERROR_NONE;
            
        default:
            return true;
    }
}

/**
 * Analysiere Multi-View JSON-Daten detailliert
 */
private function analyze_multi_view_data($json_value, $data_type, &$trail) {
    $analysis = array(
        'valid' => false,
        'view_count' => 0,
        'views_found' => array(),
        'issues' => array()
    );
    
    if (empty($json_value)) {
        $analysis['issues'][] = 'JSON data is empty';
        return $analysis;
    }
    
    $decoded = json_decode($json_value, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        $analysis['issues'][] = 'Invalid JSON format: ' . json_last_error_msg();
        return $analysis;
    }
    
    if (!is_array($decoded)) {
        $analysis['issues'][] = 'JSON does not contain array data';
        return $analysis;
    }
    
    $trail[] = "│  │  │  │  └─ " . strtoupper($data_type) . " DETAILED ANALYSIS:";
    
    foreach ($decoded as $index => $view_data) {
        if (!is_array($view_data)) {
            $analysis['issues'][] = "Item $index is not an array";
            continue;
        }
        
        $analysis['view_count']++;
        
        // Prüfe erforderliche Felder je nach Datentyp
        if ($data_type === 'product_images') {
            $required_fields = array('url', 'view_name');
            $optional_fields = array('view_id');
        } else { // design_images
            $required_fields = array('url', 'view_name', 'width_cm', 'height_cm');
            $optional_fields = array('view_id', 'transform', 'scaleX', 'scaleY', 'visible');
        }
        
        $view_name = isset($view_data['view_name']) ? $view_data['view_name'] : 
                    (isset($view_data['view_id']) ? $view_data['view_id'] : "View $index");
        
        $analysis['views_found'][] = $view_name;
        
        $trail[] = "│  │  │  │     ├─ VIEW: $view_name";
        
        $missing_required = array();
        foreach ($required_fields as $field) {
            if (empty($view_data[$field])) {
                $missing_required[] = $field;
            } else {
                $trail[] = "│  │  │  │     │  ├─ ✅ $field: " . 
                          (strlen($view_data[$field]) > 30 ? substr($view_data[$field], 0, 30) . '...' : $view_data[$field]);
            }
        }
        
        if (!empty($missing_required)) {
            $trail[] = "│  │  │  │     │  └─ ❌ Missing: " . implode(', ', $missing_required);
            $analysis['issues'][] = "View '$view_name' missing: " . implode(', ', $missing_required);
        }
        
        // Prüfe URL-Erreichbarkeit (vereinfacht)
        if (!empty($view_data['url'])) {
            $url_valid = filter_var($view_data['url'], FILTER_VALIDATE_URL) !== false;
            if (!$url_valid) {
                $analysis['issues'][] = "View '$view_name' has invalid URL format";
                $trail[] = "│  │  │  │     │  └─ ⚠️  Invalid URL format";
            }
        }
    }
    
    $analysis['valid'] = empty($analysis['issues']) && $analysis['view_count'] > 0;
    
    return $analysis;
}

/**
 * Formatiere Meta-Wert für Anzeige
 */
private function format_meta_value_for_display($value, $type) {
    switch ($type) {
        case 'json':
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return count($decoded) . " items";
            }
            return "valid JSON";
            
        case 'string':
            return '"' . (strlen($value) > 30 ? substr($value, 0, 30) . '...' : $value) . '"';
            
        case 'boolean':
            return $value ? 'true' : 'false';
            
        default:
            return (string)$value;
    }
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
     * PRÄZISE ROOT CAUSE BESTIMMUNG (inkl. Express Checkout)
     */
    private function determine_precise_root_cause($cart_analysis, $session_analysis, $hook_analysis, $order_analysis, $function_analysis) {
        
        // Bestimme Checkout-Typ
        $is_express = $hook_analysis['is_express_checkout'] ?? false;
        $is_standard = $hook_analysis['is_standard_checkout'] ?? false;
        
        // Szenario 1: Funktionen fehlen
        if (in_array(false, $function_analysis['custom_functions'])) {
            return array(
                'cause' => 'Custom Transfer Functions Missing',
                'action' => 'Re-implement yprint_tracked_design_transfer and yprint_tracked_backup_transfer functions',
                'technical' => 'One or more custom functions for design data transfer are not loaded or defined'
            );
        }
        
        // EXPRESS CHECKOUT SPEZIFISCHE SZENARIEN
        if ($is_express) {
            // Express: Cart hat Design, aber Express Hook fehlt
            if ($cart_analysis['design_count'] > 0 && 
                in_array('express_checkout_order_created', $hook_analysis['critical_hooks_missing'] ?? array())) {
                return array(
                    'cause' => 'Express Checkout Design Transfer Failed',
                    'action' => 'Check ajax_process_payment_method function - design transfer logic may not be executed properly',
                    'technical' => 'Cart contains design data but express checkout order creation hook was not triggered'
                );
            }
            
            // Express: Order erstellt aber keine Design-Daten
            if ($order_analysis['design_count'] == 0 && $cart_analysis['design_count'] > 0) {
                return array(
                    'cause' => 'Express Order Created Without Design Data',
                    'action' => 'Debug manual design transfer in ajax_process_payment_method - check if cart items are properly processed',
                    'technical' => 'Express checkout created order but manual design data transfer failed'
                );
            }
            
            // Express: Cart leer, kein Backup
            if ($cart_analysis['design_count'] == 0 && !$session_analysis['has_backup']) {
                return array(
                    'cause' => 'Express Checkout: No Cart Data and No Backup',
                    'action' => 'Check if express payment design backup was created before payment processing',
                    'technical' => 'Express checkout processed but both cart and session backup are empty'
                );
            }
        }
        
        // STANDARD CHECKOUT SPEZIFISCHE SZENARIEN  
        if ($is_standard) {
            // Standard: Cart hat Design, aber Standard Hook fehlt
            if ($cart_analysis['design_count'] > 0 && 
                in_array('checkout_create_order_line_item', $hook_analysis['critical_hooks_missing'] ?? array())) {
                return array(
                    'cause' => 'Standard Checkout Design Transfer Hook Not Executed',
                    'action' => 'Check if woocommerce_checkout_create_order_line_item hook is properly registered',
                    'technical' => 'Cart contains design data but the primary WooCommerce transfer hook was never called'
                );
            }
            
            // Standard: Hook ausgeführt, aber Daten kommen nicht an
            if ($cart_analysis['design_count'] > 0 && 
                !in_array('checkout_create_order_line_item', $hook_analysis['critical_hooks_missing'] ?? array()) &&
                $order_analysis['design_count'] == 0) {
                return array(
                    'cause' => 'Standard Checkout Hook Executed But Data Transfer Failed',
                    'action' => 'Debug the yprint_tracked_design_transfer function - data may be corrupted during transfer',
                    'technical' => 'WooCommerce transfer hook was called but design data did not reach order items'
                );
            }
        }
        
        // UNBEKANNTER CHECKOUT-TYP
        if (!$is_express && !$is_standard) {
            return array(
                'cause' => 'Unknown Checkout Type - No Hooks Detected',
                'action' => 'Check if either standard WooCommerce or Express checkout hooks are being triggered',
                'technical' => 'Neither express nor standard checkout hooks were detected in the log'
            );
        }
        
        // GEMISCHTE SZENARIEN
        if ($is_express && $is_standard) {
            return array(
                'cause' => 'Mixed Checkout Detection - Both Express and Standard Hooks Found',
                'action' => 'Check for conflicts between express and standard checkout processes',
                'technical' => 'Both express and standard checkout hooks were detected, indicating possible conflict'
            );
        }
        
        // Standard-Fall
        return array(
            'cause' => 'Complex Multi-Factor Issue (' . ($is_express ? 'Express' : 'Standard') . ' Checkout)',
            'action' => 'Manual investigation required - check all debug sections above for ' . ($is_express ? 'express' : 'standard') . ' checkout specific issues',
            'technical' => 'Multiple potential issues detected in ' . ($is_express ? 'express' : 'standard') . ' checkout flow, requires detailed analysis'
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
}