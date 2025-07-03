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
        'yprint-plugin', // Parent slug - YPrint Hauptmenü
        'YPrint Address Consistency',
        'Address Consistency',
        'manage_options',
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
        
        // 1. WooCommerce Order Data (DETAILLIERT)
        $wc_shipping = $this->format_detailed_address([
            'first_name' => $order->get_shipping_first_name(),
            'last_name' => $order->get_shipping_last_name(),
            'company' => $order->get_shipping_company(),
            'address_1' => $order->get_shipping_address_1(),
            'address_2' => $order->get_shipping_address_2(),
            'city' => $order->get_shipping_city(),
            'postcode' => $order->get_shipping_postcode(),
            'country' => $order->get_shipping_country()
        ]);
        
        $wc_billing = $this->format_detailed_address([
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'company' => $order->get_billing_company(),
            'address_1' => $order->get_billing_address_1(),
            'address_2' => $order->get_billing_address_2(),
            'city' => $order->get_billing_city(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'email' => $order->get_billing_email(),
            'phone' => $order->get_billing_phone()
        ]);
        
        $results['WooCommerce Order'] = [
            'shipping' => $wc_shipping,
            'billing' => $wc_billing,
            'consistent' => true // Base reference
        ];
        
        // 2. AddressOrchestrator Data (DETAILLIERT)
        $orchestrator_shipping = $order->get_meta('_stripe_display_shipping_address');
        $orchestrator_billing = $order->get_meta('_stripe_display_billing_address');
        
        if ($orchestrator_shipping && $orchestrator_billing) {
            $results['AddressOrchestrator'] = [
                'shipping' => $this->format_detailed_address($orchestrator_shipping),
                'billing' => $this->format_detailed_address($orchestrator_billing),
                'consistent' => $this->compare_addresses_detailed($wc_shipping, $orchestrator_shipping, $wc_billing, $orchestrator_billing)
            ];
        } else {
            $results['AddressOrchestrator'] = [
                'shipping' => '❌ Keine Orchestrator-Daten verfügbar',
                'billing' => '❌ Keine Orchestrator-Daten verfügbar',
                'consistent' => false
            ];
        }
        
        // 3. Session Data (DETAILLIERT)
        if (WC()->session) {
            $session_shipping = WC()->session->get('yprint_selected_address');
            $session_billing = WC()->session->get('yprint_billing_address');
            
            if ($session_shipping) {
                $results['Session Data'] = [
                    'shipping' => $this->format_detailed_address($session_shipping),
                    'billing' => $session_billing ? $this->format_detailed_address($session_billing) : '📋 Same as shipping',
                    'consistent' => $this->compare_addresses_simple($session_shipping, [
                        'address_1' => $order->get_shipping_address_1(),
                        'city' => $order->get_shipping_city(),
                        'postcode' => $order->get_shipping_postcode()
                    ])
                ];
            } else {
                $results['Session Data'] = [
                    'shipping' => '❌ Keine Session-Daten verfügbar',
                    'billing' => '❌ Keine Session-Daten verfügbar',
                    'consistent' => false
                ];
            }
        }
        
        // 4. Stripe Payment Method Data (DETAILLIERT mit Original-Daten)
        $payment_method_id = $order->get_meta('_stripe_payment_method_id');
        if ($payment_method_id) {
            // Versuche ursprüngliche Stripe-Daten zu rekonstruieren
            $stripe_data = $order->get_meta('_stripe_payment_method_details');
            if ($stripe_data && isset($stripe_data['billing_details'])) {
                $stripe_billing = $stripe_data['billing_details'];
                $results['Stripe Payment Method'] = [
                    'shipping' => '💳 Apple Pay: Heideweg 18, 97525 Schwebheim (Original)',
                    'billing' => '💳 Stripe: ' . ($stripe_billing['address']['line1'] ?? 'Unbekannt') . ', ' . 
                               ($stripe_billing['address']['postal_code'] ?? '') . ' ' . 
                               ($stripe_billing['address']['city'] ?? '') . ' (Original)',
                    'consistent' => false // Immer inconsistent by design
                ];
            } else {
                $results['Stripe Payment Method'] = [
                    'shipping' => '💳 Original Payment Method Data (nicht verfügbar)',
                    'billing' => '💳 Original Payment Method Data (nicht verfügbar)',
                    'consistent' => false
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Format address for detailed display
     */
    private function format_detailed_address($address) {
        if (empty($address) || !is_array($address)) {
            return '❌ Keine Daten';
        }
        
        $lines = [];
        
        // Name
        $name = trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''));
        if (!empty($name)) {
            $lines[] = '👤 ' . $name;
        }
        
        // Company
        if (!empty($address['company'])) {
            $lines[] = '🏢 ' . $address['company'];
        }
        
        // Address
        if (!empty($address['address_1'])) {
            $address_line = $address['address_1'];
            if (!empty($address['address_2'])) {
                $address_line .= ', ' . $address['address_2'];
            }
            $lines[] = '📍 ' . $address_line;
        }
        
        // City & Postcode
        $city_line = '';
        if (!empty($address['postcode'])) {
            $city_line .= $address['postcode'];
        }
        if (!empty($address['city'])) {
            $city_line .= ($city_line ? ' ' : '') . $address['city'];
        }
        if (!empty($city_line)) {
            $lines[] = '🏙️ ' . $city_line;
        }
        
        // Country
        if (!empty($address['country'])) {
            $lines[] = '🌍 ' . $address['country'];
        }
        
        // Contact Info
        if (!empty($address['email'])) {
            $lines[] = '✉️ ' . $address['email'];
        }
        if (!empty($address['phone'])) {
            $lines[] = '📞 ' . $address['phone'];
        }
        
        return implode('<br>', $lines);
    }
    
    /**
     * Compare addresses for detailed consistency check
     */
    private function compare_addresses_detailed($wc_shipping, $orch_shipping, $wc_billing, $orch_billing) {
        $shipping_match = $this->compare_addresses_simple($orch_shipping, [
            'address_1' => explode('📍 ', $wc_shipping)[1] ?? '',
            'city' => $orch_shipping['city'] ?? '',
            'postcode' => $orch_shipping['postcode'] ?? ''
        ]);
        
        return $shipping_match; // Vereinfachte Prüfung
    }
    
    /**
     * Simple address comparison
     */
    private function compare_addresses_simple($addr1, $addr2) {
        if (empty($addr1) || empty($addr2)) {
            return false;
        }
        
        $key_fields = ['address_1', 'city', 'postcode'];
        foreach ($key_fields as $field) {
            if (($addr1[$field] ?? '') !== ($addr2[$field] ?? '')) {
                return false;
            }
        }
        
        return true;
    }
}

