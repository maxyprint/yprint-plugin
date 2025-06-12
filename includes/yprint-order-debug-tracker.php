<?php
/**
 * YPRINT ENHANCED ORDER DEBUG MIT DESIGN-DATEN ANALYSE
 * Analysiert Bestellungen und zeigt detaillierte Design-Informationen aus der Datenbank
 */

// Verhindere direkte Aufrufe
if (!defined('ABSPATH')) {
    exit;
}

/**
 * ENHANCED DEBUG-SYSTEM - Mit Datenbank Design-Analyse
 */
class YPrint_Enhanced_Debug {
    
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
        
        // === SCHRITT 1: ORDER ITEMS ANALYSIS ===
        $debug_trail[] = "SCHRITT $step_counter: ORDER ITEMS ANALYSIS";
        $step_counter++;
        
        $order_analysis = $this->analyze_order_items($order);
        $debug_trail = array_merge($debug_trail, $order_analysis['trail']);
        
        // === SCHRITT 2: DESIGN DATABASE ANALYSIS ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: DESIGN DATABASE ANALYSIS";
        $step_counter++;
        
        $database_analysis = $this->analyze_design_database($order_analysis['design_ids']);
        $debug_trail = array_merge($debug_trail, $database_analysis['trail']);
        
        // === SCHRITT 3: DETAILED DESIGN BREAKDOWN ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: DETAILED DESIGN BREAKDOWN";
        $step_counter++;
        
        $design_breakdown = $this->create_detailed_design_breakdown($database_analysis['designs']);
        $debug_trail = array_merge($debug_trail, $design_breakdown['trail']);
        
        // === SCHRITT 4: PRINT PROVIDER READINESS ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: PRINT PROVIDER READINESS";
        $step_counter++;
        
        $print_readiness = $this->analyze_print_provider_readiness($design_breakdown['formatted_designs']);
        $debug_trail = array_merge($debug_trail, $print_readiness['trail']);
        
        // === SCHRITT 5: ROOT CAUSE DETERMINATION ===
        $debug_trail[] = "";
        $debug_trail[] = "SCHRITT $step_counter: ROOT CAUSE DETERMINATION";
        
        $root_cause = $this->determine_enhanced_root_cause(
            $order_analysis, 
            $database_analysis, 
            $design_breakdown,
            $print_readiness
        );
        
        $debug_trail[] = "🎯 ROOT CAUSE: " . $root_cause['cause'];
        $debug_trail[] = "💡 RECOMMENDED ACTION: " . $root_cause['action'];
        $debug_trail[] = "🔧 TECHNICAL DETAILS: " . $root_cause['technical'];
        
        // Speichere alle Analysen
        $summary = array(
            'timestamp' => $timestamp,
            'order_id' => $order_id,
            'status' => ($order_analysis['design_count'] > 0) ? 'SUCCESS' : 'FAILED',
            'design_items_found' => $order_analysis['design_count'],
            'database_designs_found' => $database_analysis['found_count'],
            'formatted_designs' => $design_breakdown['formatted_designs'],
            'print_provider_ready' => $print_readiness['ready'],
            'order_analysis' => $order_analysis,
            'database_analysis' => $database_analysis,
            'design_breakdown' => $design_breakdown,
            'print_readiness' => $print_readiness,
            'root_cause' => $root_cause,
            'debug_trail' => $debug_trail
        );
        
        update_post_meta($order_id, '_yprint_enhanced_debug_summary', $summary);

        // Error-Log Ausgabe
        $log_message = "YPrint Enhanced Debug for Order $order_id: ";
        if (is_array($root_cause) && isset($root_cause['cause'])) {
            $log_message .= $root_cause['cause'];
        } else {
            $log_message .= "Enhanced debug analysis completed";
        }
        error_log($log_message);
    }
    
    /**
     * ANALYSIERE ORDER ITEMS FÜR DESIGN IDs
     */
    private function analyze_order_items($order) {
        $trail = array();
        $design_count = 0;
        $design_ids = array();
        $items_analysis = array();
        
        $trail[] = "├─ Analyzing order items...";
        $trail[] = "│  ├─ Order ID: " . $order->get_id();
        $trail[] = "│  ├─ Order Status: " . $order->get_status();
        $trail[] = "│  └─ Total Items: " . count($order->get_items());
        
        foreach ($order->get_items() as $item_id => $item) {
            $trail[] = "│  ├─ Order Item $item_id:";
            $trail[] = "│  │  ├─ Name: " . $item->get_name();
            $trail[] = "│  │  ├─ Product ID: " . $item->get_product_id();
            
            // Prüfe Design ID in verschiedenen Meta-Feldern
            $design_id = null;
            $design_sources = array();
            
            // Prüfe _design_id Meta
            $meta_design_id = $item->get_meta('_design_id');
            if (!empty($meta_design_id)) {
                $design_id = $meta_design_id;
                $design_sources[] = '_design_id';
            }
            
            // Prüfe print_design Meta
            $print_design = $item->get_meta('print_design');
            if (!empty($print_design) && is_array($print_design) && isset($print_design['design_id'])) {
                if (!$design_id) {
                    $design_id = $print_design['design_id'];
                }
                $design_sources[] = 'print_design';
            }
            
            if ($design_id) {
                $design_count++;
                $design_ids[] = $design_id;
                $trail[] = "│  │  ├─ ✅ DESIGN FOUND";
                $trail[] = "│  │  │  ├─ Design ID: " . $design_id;
                $trail[] = "│  │  │  └─ Sources: " . implode(', ', $design_sources);
            } else {
                $trail[] = "│  │  └─ ❌ NO DESIGN ID FOUND";
            }
            
            $items_analysis[$item_id] = array(
                'name' => $item->get_name(),
                'product_id' => $item->get_product_id(),
                'design_id' => $design_id,
                'has_design' => !empty($design_id),
                'design_sources' => $design_sources
            );
        }
        
        $trail[] = "└─ ORDER SUMMARY: $design_count design items found with IDs: " . implode(', ', $design_ids);
        
        return array(
            'trail' => $trail,
            'design_count' => $design_count,
            'design_ids' => array_unique($design_ids),
            'items' => $items_analysis
        );
    }
    
    /**
     * ANALYSIERE DESIGN-DATEN AUS DATENBANK
     */
    private function analyze_design_database($design_ids) {
        global $wpdb;
        $trail = array();
        $designs = array();
        $found_count = 0;
        
        $trail[] = "├─ Querying design database...";
        $trail[] = "│  ├─ Table: deo6_octo_user_designs";
        $trail[] = "│  └─ Looking for Design IDs: " . implode(', ', $design_ids);
        
        if (empty($design_ids)) {
            $trail[] = "│  ❌ No design IDs to search for";
            return array('trail' => $trail, 'found_count' => 0, 'designs' => array());
        }
        
        // Bereite Query vor
        $placeholders = implode(',', array_fill(0, count($design_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT id, user_id, template_id, name, design_data, created_at, product_name 
             FROM deo6_octo_user_designs 
             WHERE id IN ($placeholders)",
            $design_ids
        );
        
        $trail[] = "│  ├─ Executing query...";
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if ($wpdb->last_error) {
            $trail[] = "│  ❌ Database Error: " . $wpdb->last_error;
            return array('trail' => $trail, 'found_count' => 0, 'designs' => array());
        }
        
        $found_count = count($results);
        $trail[] = "│  ├─ Query Results: $found_count designs found";
        
        foreach ($results as $design_row) {
            $design_id = $design_row['id'];
            $trail[] = "│  ├─ Design ID $design_id:";
            $trail[] = "│  │  ├─ Name: " . $design_row['name'];
            $trail[] = "│  │  ├─ Template ID: " . $design_row['template_id'];
            $trail[] = "│  │  ├─ User ID: " . $design_row['user_id'];
            $trail[] = "│  │  ├─ Created: " . $design_row['created_at'];
            
            // Parse design_data JSON
            $design_data = json_decode($design_row['design_data'], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $trail[] = "│  │  ├─ ✅ Design Data JSON valid";
                $trail[] = "│  │  │  ├─ Template ID: " . ($design_data['templateId'] ?? 'MISSING');
                $trail[] = "│  │  │  ├─ Current Variation: " . ($design_data['currentVariation'] ?? 'MISSING');
                
                if (isset($design_data['variationImages'])) {
                    $view_count = count($design_data['variationImages']);
                    $trail[] = "│  │  │  └─ Variation Images: $view_count views found";
                } else {
                    $trail[] = "│  │  │  └─ ❌ No variationImages found";
                }
            } else {
                $trail[] = "│  │  └─ ❌ Design Data JSON invalid: " . json_last_error_msg();
                $design_data = null;
            }
            
            $designs[$design_id] = array(
                'id' => $design_id,
                'name' => $design_row['name'],
                'template_id' => $design_row['template_id'],
                'user_id' => $design_row['user_id'],
                'created_at' => $design_row['created_at'],
                'product_name' => $design_row['product_name'],
                'design_data' => $design_data,
                'raw_data' => $design_row['design_data']
            );
        }
        
        $trail[] = "└─ DATABASE SUMMARY: $found_count of " . count($design_ids) . " designs found";
        
        return array(
            'trail' => $trail,
            'found_count' => $found_count,
            'designs' => $designs,
            'missing_ids' => array_diff($design_ids, array_keys($designs))
        );
    }
    
    /**
     * ERSTELLE DETAILLIERTE DESIGN BREAKDOWN
     */
    private function create_detailed_design_breakdown($designs) {
        $trail = array();
        $formatted_designs = array();
        
        $trail[] = "├─ Creating detailed design breakdown...";
        
        if (empty($designs)) {
            $trail[] = "│  ❌ No designs to analyze";
            return array('trail' => $trail, 'formatted_designs' => array());
        }
        
        foreach ($designs as $design_id => $design) {
            $trail[] = "│  ├─ Processing Design ID $design_id:";
            
            if (!$design['design_data']) {
                $trail[] = "│  │  └─ ❌ No valid design data to process";
                continue;
            }
            
            $design_data = $design['design_data'];
            $formatted_design = array(
                'id' => $design_id,
                'name' => $design['name'],
                'template_id' => $design['template_id'],
                'views' => array()
            );
            
            // Verarbeite variationImages
            if (isset($design_data['variationImages'])) {
                $trail[] = "│  │  ├─ Processing variation images...";
                
                foreach ($design_data['variationImages'] as $view_id => $images) {
                    $trail[] = "│  │  │  ├─ View ID: $view_id";
                    
                    // Extrahiere Variation und View aus dem Key
                    $parts = explode('_', $view_id);
                    $variation_id = $parts[0] ?? '';
                    $view_system_id = $parts[1] ?? '';
                    
                    $view_data = array(
                        'view_id' => $view_id,
                        'variation_id' => $variation_id,
                        'system_id' => $view_system_id,
                        'images' => array()
                    );
                    
                    $image_count = 0;
                    foreach ($images as $image) {
                        $image_count++;
                        $trail[] = "│  │  │  │  ├─ Image $image_count:";
                        
                        // Extrahiere Dateiname aus URL
                        $filename = basename($image['url'] ?? '');
                        $trail[] = "│  │  │  │  │  ├─ File: $filename";
                        
                        // Berechne Druckgröße
                        $original_width = $image['transform']['width'] ?? 0;
                        $original_height = $image['transform']['height'] ?? 0;
                        $scale_x = $image['transform']['scaleX'] ?? 0;
                        $scale_y = $image['transform']['scaleY'] ?? 0;
                        
                        $print_width_mm = round(($original_width * $scale_x) * 0.26458333, 1); // px zu mm
                        $print_height_mm = round(($original_height * $scale_y) * 0.26458333, 1);
                        
                        $trail[] = "│  │  │  │  │  ├─ Original: {$original_width}px × {$original_height}px";
                        $trail[] = "│  │  │  │  │  ├─ Scale: " . round($scale_x * 100, 1) . "%";
                        $trail[] = "│  │  │  │  │  └─ Print Size: {$print_width_mm}mm × {$print_height_mm}mm";
                        
                        $view_data['images'][] = array(
                            'filename' => $filename,
                            'url' => $image['url'] ?? '',
                            'original_width' => $original_width,
                            'original_height' => $original_height,
                            'position_left' => $image['transform']['left'] ?? 0,
                            'position_top' => $image['transform']['top'] ?? 0,
                            'scale_x' => $scale_x,
                            'scale_y' => $scale_y,
                            'scale_percent' => round($scale_x * 100, 1),
                            'print_width_mm' => $print_width_mm,
                            'print_height_mm' => $print_height_mm,
                            'angle' => $image['transform']['angle'] ?? 0,
                            'visible' => $image['visible'] ?? true
                        );
                    }
                    
                    $trail[] = "│  │  │  └─ View processed: $image_count images";
                    $formatted_design['views'][$view_id] = $view_data;
                }
            }
            
            $trail[] = "│  │  └─ Design processed: " . count($formatted_design['views']) . " views";
            $formatted_designs[$design_id] = $formatted_design;
        }
        
        $trail[] = "└─ BREAKDOWN SUMMARY: " . count($formatted_designs) . " designs formatted";
        
        return array(
            'trail' => $trail,
            'formatted_designs' => $formatted_designs
        );
    }
    
    /**
     * ANALYSIERE PRINT PROVIDER BEREITSCHAFT
     */
    private function analyze_print_provider_readiness($formatted_designs) {
        $trail = array();
        $ready_count = 0;
        $total_count = count($formatted_designs);
        
        $trail[] = "├─ Analyzing Print Provider readiness...";
        
        foreach ($formatted_designs as $design_id => $design) {
            $trail[] = "│  ├─ Design $design_id:";
            
            $issues = array();
            $view_count = count($design['views']);
            
            if ($view_count === 0) {
                $issues[] = "No views found";
            }
            
            foreach ($design['views'] as $view_id => $view) {
                $image_count = count($view['images']);
                if ($image_count === 0) {
                    $issues[] = "View $view_id has no images";
                }
                
                foreach ($view['images'] as $index => $image) {
                    if (empty($image['url'])) {
                        $issues[] = "View $view_id Image " . ($index + 1) . " missing URL";
                    }
                    if ($image['print_width_mm'] <= 0 || $image['print_height_mm'] <= 0) {
                        $issues[] = "View $view_id Image " . ($index + 1) . " invalid print dimensions";
                    }
                }
            }
            
            if (empty($issues)) {
                $ready_count++;
                $trail[] = "│  │  └─ ✅ READY - $view_count views, all data complete";
            } else {
                $trail[] = "│  │  └─ ❌ NOT READY - Issues: " . implode('; ', $issues);
            }
        }
        
        $trail[] = "└─ READINESS SUMMARY: $ready_count of $total_count designs ready";
        
        return array(
            'trail' => $trail,
            'ready' => $ready_count === $total_count && $total_count > 0,
            'ready_count' => $ready_count,
            'total_count' => $total_count
        );
    }
    
    /**
     * BESTIMME ENHANCED ROOT CAUSE
     */
    private function determine_enhanced_root_cause($order_analysis, $database_analysis, $design_breakdown, $print_readiness) {
        
        if ($order_analysis['design_count'] === 0) {
            return array(
                'cause' => 'No Design IDs in Order Items',
                'action' => 'Check design transfer hooks during checkout process',
                'technical' => 'Order was created without any design_id meta fields'
            );
        }
        
        if ($database_analysis['found_count'] === 0) {
            return array(
                'cause' => 'Design IDs Not Found in Database',
                'action' => 'Verify design IDs exist in deo6_octo_user_designs table',
                'technical' => 'Design IDs present in order but missing from database: ' . implode(', ', $order_analysis['design_ids'])
            );
        }
        
        if (count($design_breakdown['formatted_designs']) === 0) {
            return array(
                'cause' => 'Invalid Design Data in Database',
                'action' => 'Check design_data JSON format in database records',
                'technical' => 'Design records found but contain invalid or corrupted JSON data'
            );
        }
        
        if (!$print_readiness['ready']) {
            return array(
                'cause' => 'Design Data Incomplete for Print Provider',
                'action' => 'Verify all design views have complete image data and valid dimensions',
                'technical' => $print_readiness['ready_count'] . ' of ' . $print_readiness['total_count'] . ' designs are print-ready'
            );
        }
        
        return array(
            'cause' => 'All Systems Operational',
            'action' => 'Order is ready for print provider processing',
            'technical' => 'All design data validated and complete'
        );
    }
    
    /**
     * ZEIGE DEBUG INFO IM ADMIN
     */
    public function display_debug_in_admin($order) {
        $debug_summary = get_post_meta($order->get_id(), '_yprint_enhanced_debug_summary', true);
        
        if (empty($debug_summary)) {
            return;
        }
        
        echo '<div style="background: #f9f9f9; padding: 15px; margin: 15px 0; border-left: 4px solid #0073aa;">';
        echo '<h3 style="margin-top: 0; color: #0073aa;">🔍 YPrint Enhanced Debug Analysis</h3>';
        
        // Status Overview
        echo '<div style="margin-bottom: 15px;">';
        echo '<strong>Status:</strong> ';
        if ($debug_summary['status'] === 'SUCCESS') {
            echo '<span style="color: green; font-weight: bold;">✅ SUCCESS</span>';
        } else {
            echo '<span style="color: red; font-weight: bold;">❌ FAILED</span>';
        }
        echo '<br>';
        echo '<strong>Design Items:</strong> ' . $debug_summary['design_items_found'] . '<br>';
        echo '<strong>Database Designs:</strong> ' . $debug_summary['database_designs_found'] . '<br>';
        echo '<strong>Print Ready:</strong> ' . ($debug_summary['print_provider_ready'] ? 'Yes' : 'No') . '<br>';
        echo '</div>';
        
        // Design Details Breakdown
        if (!empty($debug_summary['formatted_designs'])) {
            echo '<details style="margin-bottom: 15px;"><summary><strong>🎨 Design Details</strong></summary>';
            echo '<div style="margin-top: 10px; font-family: monospace; font-size: 12px;">';
            
            foreach ($debug_summary['formatted_designs'] as $design_id => $design) {
                echo "<h4 style='color: #0073aa; margin: 15px 0 5px 0;'>📋 Design: {$design['name']} (ID: $design_id)</h4>";
                
                $view_counter = 1;
                foreach ($design['views'] as $view_id => $view) {
                    // Bestimme View-Namen basierend auf System-ID
                    $view_name = $this->get_view_name_by_system_id($view['system_id']);
                    
                    echo "<div style='margin-left: 20px; margin-bottom: 15px;'>";
                    echo "🔹 <strong>View $view_counter: $view_name</strong><br>";
                    echo "* <strong>System-ID der View:</strong> <code>{$view['system_id']}</code><br>";
                    echo "* <strong>Variation (Produktvariante):</strong> <code>{$view['variation_id']}</code><br>";
                    
                    $image_counter = 1;
                    foreach ($view['images'] as $image) {
                        echo "🎨 <strong>Bild $image_counter:</strong><br>";
                        echo "* <strong>Dateiname:</strong> <code>{$image['filename']}</code><br>";
                        echo "* <strong>URL</strong> (<code>url</code>): <code>{$image['url']}</code><br>";
                        echo "* <strong>Originalgröße</strong> (<code>transform.width</code> / <code>transform.height</code>): <code>{$image['original_width']} px × {$image['original_height']} px</code><br>";
                        echo "* <strong>Platzierung</strong>:<br>";
                        echo "   * <code>left</code>: <code>{$image['position_left']} px</code> (<code>transform.left</code>)<br>";
                        echo "   * <code>top</code>: <code>{$image['position_top']} px</code> (<code>transform.top</code>)<br>";
                        echo "* <strong>Skalierung</strong>:<br>";
                        echo "   * <code>scaleX</code>: <code>{$image['scale_x']}</code> → ca. <strong>{$image['scale_percent']} %</strong><br>";
                        echo "   * <code>scaleY</code>: <code>{$image['scale_y']}</code><br>";
                        echo "* <strong>Druckgröße (berechnet aus Originalgröße × Skalierung)</strong>:<br>";
                        echo "   * <strong>Breite:</strong> <code>{$image['original_width']} × {$image['scale_x']} = ~{$image['print_width_mm']} mm</code><br>";
                        echo "   * <strong>Höhe:</strong> <code>{$image['original_height']} × {$image['scale_y']} = ~{$image['print_height_mm']} mm</code><br>";
                        
                        if ($image['angle'] != 0) {
                            echo "* <strong>Rotation:</strong> <code>{$image['angle']}°</code><br>";
                        }
                        
                        echo "<br>";
                        $image_counter++;
                    }
                    
                    echo "</div>";
                    $view_counter++;
                }
            }
            
            echo '</div></details>';
        }
        
        // Root Cause
        if (isset($debug_summary['root_cause'])) {
            echo '<details style="margin-bottom: 15px;"><summary><strong>🎯 Root Cause Analysis</strong></summary>';
            echo '<div style="margin-top: 10px;">';
            echo '<strong>Cause:</strong> ' . esc_html($debug_summary['root_cause']['cause']) . '<br>';
            echo '<strong>Action:</strong> ' . esc_html($debug_summary['root_cause']['action']) . '<br>';
            echo '<strong>Technical:</strong> ' . esc_html($debug_summary['root_cause']['technical']) . '<br>';
            echo '</div></details>';
        }
        
        // Debug Trail
        if (!empty($debug_summary['debug_trail'])) {
            echo '<details><summary><strong>📋 Complete Debug Trail</strong></summary>';
            echo '<div style="background: #fff; padding: 10px; margin-top: 10px; font-family: monospace; font-size: 11px; max-height: 400px; overflow-y: auto; border: 1px solid #ddd;">';
            
            foreach ($debug_summary['debug_trail'] as $entry) {
                // Farbkodierung
                if (strpos($entry, '✅') !== false) {
                    $entry = '<span style="color: green;">' . $entry . '</span>';
                } elseif (strpos($entry, '❌') !== false) {
                    $entry = '<span style="color: red;">' . $entry . '</span>';
                } elseif (strpos($entry, '⚠️') !== false) {
                    $entry = '<span style="color: orange;">' . $entry . '</span>';
                } elseif (strpos($entry, 'SCHRITT') !== false) {
                    $entry = '<strong style="color: #0073aa; background: #f0f8ff; padding: 2px 4px;">' . $entry . '</strong>';
                } elseif (strpos($entry, '🔍') !== false || strpos($entry, '🎯') !== false || strpos($entry, '💡') !== false) {
                    $entry = '<span style="color: orange; font-weight: bold;">' . $entry . '</span>';
                } elseif (strpos($entry, '├─') !== false || strpos($entry, '└─') !== false || strpos($entry, '│') !== false) {
                    $entry = '<span style="color: #666;">' . $entry . '</span>';
                }
                
                echo $entry . "\n";
            }
            
            echo '</div></details>';
        }
        
        echo '</div>';
    }
    
    /**
     * BESTIMME VIEW-NAMEN ANHAND DER SYSTEM-ID
     */
    private function get_view_name_by_system_id($system_id) {
        // Häufige View-IDs und ihre Namen
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
}

// Initialisiere das Enhanced Debug-System
add_action('init', function() {
    YPrint_Enhanced_Debug::get_instance();
});

?>