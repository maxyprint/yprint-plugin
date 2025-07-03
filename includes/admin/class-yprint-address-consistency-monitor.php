<?php
/**
 * YPrint Address Consistency Monitor
 * 
 * Admin dashboard to monitor address consistency across all systems
 */

class YPrint_Address_Consistency_Monitor {
    
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('wp_ajax_yprint_check_order_consistency', [$this, 'ajax_check_order_consistency']);
    }
    
    /**
     * Add admin menu item
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'YPrint Address Consistency',
            'Address Consistency',
            'manage_woocommerce',
            'yprint-address-consistency',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        ?>
        <div class="wrap">
            <h1>YPrint Address Consistency Monitor</h1>
            
            <form method="post">
                <label for="order_id">Order ID:</label>
                <input type="number" id="order_id" name="order_id" value="5120" />
                <button type="button" onclick="checkOrderConsistency()">Check Consistency</button>
            </form>
            
            <div id="consistency-results" style="margin-top: 20px;"></div>
            
            <script>
            function checkOrderConsistency() {
                const orderId = document.getElementById('order_id').value;
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=yprint_check_order_consistency&order_id=${orderId}&nonce=<?php echo wp_create_nonce('yprint_admin'); ?>`
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('consistency-results').innerHTML = data.data.html;
                });
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * AJAX handler for consistency check
     */
    public function ajax_check_order_consistency() {
        check_ajax_referer('yprint_admin', 'nonce');
        
        $order_id = intval($_POST['order_id']);
        $order = wc_get_order($order_id);
        
        if (!$order) {
            wp_send_json_error(['message' => 'Order not found']);
            return;
        }
        
        $results = $this->analyze_order_consistency($order);
        
        $html = '<div class="consistency-report">';
        $html .= '<h3>Consistency Report for Order #' . $order_id . '</h3>';
        $html .= '<table class="wp-list-table widefat fixed striped">';
        $html .= '<thead><tr><th>System</th><th>Shipping Address</th><th>Billing Address</th><th>Status</th></tr></thead>';
        $html .= '<tbody>';
        
        foreach ($results as $system => $data) {
            $status_class = $data['consistent'] ? 'success' : 'error';
            $status_text = $data['consistent'] ? '✅ Consistent' : '❌ Inconsistent';
            
            $html .= '<tr>';
            $html .= '<td><strong>' . esc_html($system) . '</strong></td>';
            $html .= '<td>' . esc_html($data['shipping']) . '</td>';
            $html .= '<td>' . esc_html($data['billing']) . '</td>';
            $html .= '<td class="' . $status_class . '">' . $status_text . '</td>';
            $html .= '</tr>';
        }
        
        $html .= '</tbody></table></div>';
        
        wp_send_json_success(['html' => $html]);
    }
    
    /**
     * Analyze order consistency across all systems
     */
    private function analyze_order_consistency($order) {
        $results = [];
        
        // 1. WooCommerce Order Data
        $results['WooCommerce Order'] = [
            'shipping' => $order->get_shipping_address_1() . ', ' . $order->get_shipping_postcode() . ' ' . $order->get_shipping_city(),
            'billing' => $order->get_billing_address_1() . ', ' . $order->get_billing_postcode() . ' ' . $order->get_billing_city(),
            'consistent' => true // Base reference
        ];
        
        // 2. AddressOrchestrator Data
        $orchestrator_shipping = $order->get_meta('_stripe_display_shipping_address');
        $orchestrator_billing = $order->get_meta('_stripe_display_billing_address');
        
        if ($orchestrator_shipping && $orchestrator_billing) {
            $results['AddressOrchestrator'] = [
                'shipping' => $orchestrator_shipping['address_1'] . ', ' . $orchestrator_shipping['postcode'] . ' ' . $orchestrator_shipping['city'],
                'billing' => $orchestrator_billing['address_1'] . ', ' . $orchestrator_billing['postcode'] . ' ' . $orchestrator_billing['city'],
                'consistent' => (
                    $results['WooCommerce Order']['shipping'] === ($orchestrator_shipping['address_1'] . ', ' . $orchestrator_shipping['postcode'] . ' ' . $orchestrator_shipping['city']) &&
                    $results['WooCommerce Order']['billing'] === ($orchestrator_billing['address_1'] . ', ' . $orchestrator_billing['postcode'] . ' ' . $orchestrator_billing['city'])
                )
            ];
        }
        
        // 3. Session Data (if available)
        if (WC()->session) {
            $session_shipping = WC()->session->get('yprint_selected_address');
            $session_billing = WC()->session->get('yprint_billing_address');
            
            if ($session_shipping) {
                $results['Session Data'] = [
                    'shipping' => $session_shipping['address_1'] . ', ' . $session_shipping['postcode'] . ' ' . $session_shipping['city'],
                    'billing' => $session_billing ? ($session_billing['address_1'] . ', ' . $session_billing['postcode'] . ' ' . $session_billing['city']) : 'Same as shipping',
                    'consistent' => $results['WooCommerce Order']['shipping'] === ($session_shipping['address_1'] . ', ' . $session_shipping['postcode'] . ' ' . $session_shipping['city'])
                ];
            }
        }
        
        // 4. Stripe Payment Method Data
        $payment_method_id = $order->get_meta('_stripe_payment_method_id');
        if ($payment_method_id) {
            // This would show the original Stripe data - we know it's inconsistent
            $results['Stripe Payment Method'] = [
                'shipping' => 'Original Payment Method Data',
                'billing' => 'Original Payment Method Data',
                'consistent' => false // We know this is inconsistent by design
            ];
        }
        
        return $results;
    }
}

// Initialize
new YPrint_Address_Consistency_Monitor();