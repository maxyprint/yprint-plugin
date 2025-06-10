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
    
    /**
     * Protokolliere finale Order-Daten (störungsfrei)
     */
    public function log_final_order($order_id) {
        $timestamp = current_time('Y-m-d H:i:s');
        $order = wc_get_order($order_id);
        
        if (!$order) return;
        
        $debug_data = array();
        $design_count = 0;
        
        // Analysiere Order Items
        foreach ($order->get_items() as $item_id => $item) {
            $product_id = $item->get_product_id();
            $has_design = false;
            
            // Prüfe verschiedene Design-Meta-Felder
            $design_meta = $item->get_meta('print_design');
            if (!empty($design_meta)) {
                $has_design = true;
                $design_count++;
            }
            
            $debug_data[] = array(
                'item_id' => $item_id,
                'product_id' => $product_id,
                'name' => $item->get_name(),
                'has_design' => $has_design,
                'design_data' => $design_meta
            );
        }
        
        // Speichere kompakte Debug-Info
        $summary = array(
            'timestamp' => $timestamp,
            'order_id' => $order_id,
            'total_items' => count($debug_data),
            'design_items' => $design_count,
            'status' => ($design_count > 0) ? 'SUCCESS' : 'NO_DESIGNS',
            'items' => $debug_data
        );
        
        update_post_meta($order_id, '_yprint_debug_summary', $summary);
        
        // Einfacher Error-Log
        error_log("YPrint Order $order_id: $design_count design items found");
    }
    
    /**
     * Zeige Debug-Info im Admin (kompakt)
     */
    public function display_debug_in_admin($order) {
        $debug_summary = get_post_meta($order->get_id(), '_yprint_debug_summary', true);
        
        if (empty($debug_summary)) {
            return;
        }
        
        $status_color = ($debug_summary['status'] === 'SUCCESS') ? 'green' : 'red';
        
        echo '<div style="margin-top: 20px; padding: 15px; background: #f9f9f9; border-left: 4px solid ' . $status_color . ';">';
        echo '<h3>🎨 YPrint Design-Status</h3>';
        echo '<p><strong>Status:</strong> <span style="color: ' . $status_color . ';">' . $debug_summary['status'] . '</span></p>';
        echo '<p><strong>Design Items:</strong> ' . $debug_summary['design_items'] . ' von ' . $debug_summary['total_items'] . '</p>';
        echo '<p><strong>Timestamp:</strong> ' . $debug_summary['timestamp'] . '</p>';
        
        if (!empty($debug_summary['items'])) {
            echo '<details><summary>Item Details</summary>';
            echo '<ul>';
            foreach ($debug_summary['items'] as $item) {
                $icon = $item['has_design'] ? '✅' : '❌';
                echo '<li>' . $icon . ' ' . esc_html($item['name']) . ' (ID: ' . $item['product_id'] . ')</li>';
            }
            echo '</ul></details>';
        }
        
        echo '</div>';
    }
}

// Initialisiere das schlanke Debug-System
add_action('init', function() {
    YPrint_Simple_Debug::get_instance();
});

?>