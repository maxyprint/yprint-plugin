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
            <h1>🎯 YPrint Address Problem Diagnostic</h1>
            <p><strong>Diagnose-Tool für die Chat-Probleme:</strong> Bestätigungsmail leer | Confirmation Page OK | Stripe falsch | WooCommerce korrekt</p>
            
            <div style="background: #f1f1f1; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
                <h3>🔍 Überprüfte Systeme:</h3>
                <ul>
                    <li><strong>WooCommerce Order:</strong> Tatsächlich gespeicherte Adressen</li>
                    <li><strong>Stripe Payment Method:</strong> An Stripe übertragene Adressen</li>
                    <li><strong>E-Mail System:</strong> Für Bestätigungsmail verwendete Adressen</li>
                    <li><strong>Confirmation Page:</strong> Angezeigte Adressen im Checkout</li>
                </ul>
            </div>
            
            <form method="post" style="margin-bottom: 20px;">
                <table class="form-table">
                    <tr>
                        <th scope="row">Order ID</th>
                        <td>
                            <input type="number" id="order_id" name="order_id" value="5125" style="width: 100px;" />
                            <button type="button" onclick="checkOrderConsistency()" class="button button-primary">🔍 Probleme Analysieren</button>
                        </td>
                    </tr>
                </table>
            </form>
            
            <div id="consistency-results" style="margin-top: 20px;"></div>
            
            <style>
            .problem-critical { background-color: #ffebee; border-left: 4px solid #f44336; }
            .problem-warning { background-color: #fff3e0; border-left: 4px solid #ff9800; }
            .problem-success { background-color: #e8f5e8; border-left: 4px solid #4caf50; }
            .address-block { padding: 10px; margin: 5px 0; border-radius: 3px; }
            .system-title { font-weight: bold; color: #333; margin-bottom: 8px; }
            </style>
            
            <script>
            function checkOrderConsistency() {
                const orderId = document.getElementById('order_id').value;
                const button = event.target;
                button.disabled = true;
                button.innerHTML = '🔄 Analysiere...';
                
                fetch(ajaxurl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=yprint_check_order_consistency&order_id=${orderId}&nonce=<?php echo wp_create_nonce('yprint_admin'); ?>`
                })
                .then(response => response.json())
                .then(data => {
                    document.getElementById('consistency-results').innerHTML = data.data.html;
                    button.disabled = false;
                    button.innerHTML = '🔍 Probleme Analysieren';
                })
                .catch(error => {
                    console.error('Error:', error);
                    button.disabled = false;
                    button.innerHTML = '🔍 Probleme Analysieren';
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
        $html .= '<h2>🎯 Address Problem Diagnostic - Order #' . $order_id . '</h2>';
        
        // WooCommerce Order (Referenz)
        $html .= '<div class="address-block problem-success">';
        $html .= '<div class="system-title">✅ WooCommerce Order (FUNKTIONIERT)</div>';
        if ($results['woocommerce']['shipping']) {
            $html .= '<strong>Shipping:</strong> ' . $this->format_address_oneline($results['woocommerce']['shipping']) . '<br>';
            $html .= '<strong>Billing:</strong> ' . $this->format_address_oneline($results['woocommerce']['billing']) . '<br>';
        }
        $html .= '<em>' . $results['woocommerce']['note'] . '</em>';
        $html .= '</div>';
        
        // Stripe (Problem)
        $html .= '<div class="address-block problem-warning">';
        $html .= '<div class="system-title">🟡 Stripe Payment Method (PROBLEM ERWARTET)</div>';
        if ($results['stripe']['status'] !== 'error') {
            if (isset($results['stripe']['billing'])) {
                $html .= '<strong>Payment Method ID:</strong> ' . ($results['stripe']['payment_method_id'] ?? 'N/A') . '<br>';
                $html .= '<strong>Billing:</strong> ' . $this->format_address_oneline($results['stripe']['billing']) . '<br>';
            }
        }
        $html .= '<em>' . $results['stripe']['note'] . '</em>';
        $html .= '</div>';
        
        // E-Mail System (Kritisches Problem)
        $status_class = $results['email']['status'] === 'critical' ? 'problem-critical' : 'problem-success';
        $html .= '<div class="address-block ' . $status_class . '">';
        $html .= '<div class="system-title">' . ($results['email']['status'] === 'critical' ? '🔴 E-Mail System (KRITISCHES PROBLEM)' : '✅ E-Mail System (OK)') . '</div>';
        if ($results['email']['status'] === 'critical') {
            $html .= '<strong>PROBLEM:</strong> ' . $results['email']['note'] . '<br>';
            if (isset($results['email']['debug'])) {
                $html .= '<strong>Debug Info:</strong><br>';
                foreach ($results['email']['debug'] as $key => $value) {
                    $html .= '&nbsp;&nbsp;• ' . $key . ': ' . $value . '<br>';
                }
            }
        } else {
            $html .= '<strong>Shipping:</strong> ' . $this->format_address_oneline($results['email']['shipping']) . '<br>';
            $html .= '<strong>Billing:</strong> ' . $this->format_address_oneline($results['email']['billing']) . '<br>';
        }
        $html .= '<em>' . $results['email']['note'] . '</em>';
        $html .= '</div>';
        
        // Confirmation Page
        $html .= '<div class="address-block problem-success">';
        $html .= '<div class="system-title">✅ Confirmation Page (FUNKTIONIERT)</div>';
        if ($results['confirmation']['status'] === 'success' && isset($results['confirmation']['shipping'])) {
            $html .= '<strong>Session Shipping:</strong> ' . $this->format_address_oneline($results['confirmation']['shipping']) . '<br>';
            if (!empty($results['confirmation']['billing'])) {
                $html .= '<strong>Session Billing:</strong> ' . $this->format_address_oneline($results['confirmation']['billing']) . '<br>';
            }
        }
        $html .= '<em>' . $results['confirmation']['note'] . '</em>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        wp_send_json_success(['html' => $html]);
    }
    
    private function analyze_order_consistency($order) {
        $problems = [];
        
        // 1. 🏗️ WooCommerce Order (REFERENZ - das was funktioniert)
        $wc_data = $this->analyze_woocommerce_order($order);
        
        // 2. 🔴 Stripe Payment Method (PROBLEM: zeigt falsche Adressen)
        $stripe_data = $this->analyze_stripe_addresses($order);
        
        // 3. 🔴 E-Mail System (PROBLEM: leere Adressen)
        $email_data = $this->analyze_email_addresses($order);
        
        // 4. ✅ Confirmation Page (FUNKTIONIERT)
        $confirmation_data = $this->analyze_confirmation_addresses($order);
        
        return [
            'woocommerce' => $wc_data,
            'stripe' => $stripe_data,
            'email' => $email_data,
            'confirmation' => $confirmation_data
        ];
    }
    
    /**
 * Formatiert Adressdaten für eine detaillierte Anzeige.
 *
 * Diese Funktion nimmt ein Array von Adressdaten entgegen und gibt einen formatierten String
 * zurück, der die Adresse zeilenweise für die Anzeige darstellt. Es werden gängige Adressfelder
 * wie Name, Firma, Adresse, Stadt, Postleitzahl, Land, E-Mail und Telefon berücksichtigt.
 * Leere Felder werden ignoriert.
 *
 * @param array|null $address Das Adressdaten-Array. Erwartet Schlüssel wie 'first_name',
 * 'last_name', 'company', 'address_1', 'address_2', 'city',
 * 'postcode', 'country', 'email', 'phone'.
 * @return string Der formatierte Adress-String als HTML (mit <br> für Zeilenumbrüche)
 * oder eine Fehlermeldung, wenn keine gültigen Adressdaten vorliegen.
 */
private function format_detailed_address(?array $address): string
{
    // Prüfen, ob Adressdaten vorhanden und ein Array sind
    if (empty($address) || !is_array($address)) {
        return '❌ Keine Adressdaten verfügbar.';
    }

    $lines = [];

    // Name
    $fullName = trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''));
    if (!empty($fullName)) {
        $lines[] = '👤 ' . esc_html($fullName);
    }

    // Firma
    if (!empty($address['company'])) {
        $lines[] = '🏢 ' . esc_html($address['company']);
    }

    // Adresse (Zeile 1 und 2 kombinieren)
    $addressLine = [];
    if (!empty($address['address_1'])) {
        $addressLine[] = esc_html($address['address_1']);
    }
    if (!empty($address['address_2'])) {
        $addressLine[] = esc_html($address['address_2']);
    }
    if (!empty($addressLine)) {
        $lines[] = '📍 ' . implode(', ', $addressLine);
    }

    // Stadt und Postleitzahl
    $cityPostcodeLine = [];
    if (!empty($address['postcode'])) {
        $cityPostcodeLine[] = esc_html($address['postcode']);
    }
    if (!empty($address['city'])) {
        $cityPostcodeLine[] = esc_html($address['city']);
    }
    if (!empty($cityPostcodeLine)) {
        $lines[] = '🏙️ ' . implode(' ', $cityPostcodeLine);
    }

    // Land
    if (!empty($address['country'])) {
        $lines[] = '🌍 ' . esc_html($address['country']);
    }

    // Kontaktinformationen
    if (!empty($address['email'])) {
        $lines[] = '✉️ ' . esc_html($address['email']);
    }
    if (!empty($address['phone'])) {
        $lines[] = '📞 ' . esc_html($address['phone']);
    }

    // Wenn nach allen Prüfungen keine Zeilen hinzugefügt wurden, bedeutet das, dass das Array leer war
    // oder nur unbekannte Schlüssel enthielt.
    if (empty($lines)) {
        return '❌ Keine gültigen Adressdaten zum Anzeigen gefunden.';
    }

    return implode('<br>', $lines);
}
    
    /**
     * Compare addresses for detailed consistency check
     */
    private function compare_addresses_detailed($wc_shipping, $orch_shipping, $wc_billing, $orch_billing) {
        // Prüfe Shipping-Konsistenz
        $shipping_match = $this->compare_addresses_simple($orch_shipping, [
            'address_1' => trim(str_replace('📍 ', '', explode('<br>', $wc_shipping)[1] ?? '')),
            'city' => $orch_shipping['city'] ?? '',
            'postcode' => $orch_shipping['postcode'] ?? '',
            'country' => $orch_shipping['country'] ?? ''
        ]);
        
        // Prüfe Billing-Konsistenz
        $billing_match = $this->compare_addresses_simple($orch_billing, [
            'address_1' => trim(str_replace('📍 ', '', explode('<br>', $wc_billing)[1] ?? '')),
            'city' => $orch_billing['city'] ?? '',
            'postcode' => $orch_billing['postcode'] ?? '',
            'country' => $orch_billing['country'] ?? ''
        ]);
        
        return $shipping_match && $billing_match;
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

    /**
     * Analysiere WooCommerce Order Adressen (Referenz - funktioniert)
     */
    private function analyze_woocommerce_order($order) {
        $shipping = [
            'first_name' => $order->get_shipping_first_name(),
            'last_name' => $order->get_shipping_last_name(),
            'address_1' => $order->get_shipping_address_1(),
            'city' => $order->get_shipping_city(),
            'postcode' => $order->get_shipping_postcode(),
            'country' => $order->get_shipping_country()
        ];
        
        $billing = [
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'address_1' => $order->get_billing_address_1(),
            'city' => $order->get_billing_city(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country(),
            'email' => $order->get_billing_email()
        ];
        
        return [
            'status' => 'success',
            'shipping' => $shipping,
            'billing' => $billing,
            'note' => 'WooCommerce Order zeigt korrekte Adressen (laut Chat: 97525 Schwebheim)'
        ];
    }
    
    /**
     * Analysiere Stripe Payment Method Adressen (Problem-System)
     */
    private function analyze_stripe_addresses($order) {
        // Stripe Payment Method ID aus Order Meta
        $payment_method_id = $order->get_meta('_stripe_payment_method_id');
        
        // Original Stripe Billing Details aus Meta
        $stripe_billing = $order->get_meta('_stripe_billing_details');
        
        // Payment Method Details falls verfügbar
        $payment_details = $order->get_meta('_payment_method_details');
        
        if (!empty($stripe_billing)) {
            $address = $stripe_billing['address'] ?? [];
            return [
                'status' => 'warning',
                'payment_method_id' => $payment_method_id,
                'billing' => [
                    'name' => $stripe_billing['name'] ?? '',
                    'email' => $stripe_billing['email'] ?? '',
                    'address_1' => $address['line1'] ?? '',
                    'city' => $address['city'] ?? '',
                    'postcode' => $address['postal_code'] ?? '',
                    'country' => $address['country'] ?? ''
                ],
                'note' => 'Stripe zeigt originale Payment Method Daten (laut Chat: Buchental 15 Schonungen, 97453, DE)'
            ];
        }
        
        return [
            'status' => 'error',
            'note' => 'Keine Stripe Payment Method Daten gefunden'
        ];
    }
    
    /**
     * Analysiere E-Mail System Adressen (Problem-System)
     */
    private function analyze_email_addresses($order) {
        // AddressOrchestrator E-Mail Meta-Keys
        $email_shipping = $order->get_meta('_email_template_shipping_address');
        $email_billing = $order->get_meta('_email_template_billing_address');
        $addresses_ready = $order->get_meta('_email_template_addresses_ready');
        
        if ($addresses_ready && !empty($email_shipping) && !empty($email_billing)) {
            return [
                'status' => 'success',
                'shipping' => $email_shipping,
                'billing' => $email_billing,
                'note' => 'E-Mail Template Adressen verfügbar - sollten in Bestätigungsmail erscheinen'
            ];
        } else {
            return [
                'status' => 'critical',
                'shipping' => null,
                'billing' => null,
                'note' => 'PROBLEM: Keine E-Mail Template Adressen verfügbar (laut Chat: Bestätigungsmails leer)',
                'debug' => [
                    'addresses_ready' => $addresses_ready ? 'true' : 'false',
                    'email_shipping_empty' => empty($email_shipping) ? 'true' : 'false',
                    'email_billing_empty' => empty($email_billing) ? 'true' : 'false'
                ]
            ];
        }
    }
    
    /**
     * Analysiere Confirmation Page Adressen (funktioniert laut Chat)
     */
    private function analyze_confirmation_addresses($order) {
        // Session-basierte Daten die für Confirmation Page verwendet werden
        if (WC()->session) {
            $session_shipping = WC()->session->get('yprint_selected_address');
            $session_billing = WC()->session->get('yprint_billing_address');
            
            if (!empty($session_shipping)) {
                return [
                    'status' => 'success',
                    'shipping' => $session_shipping,
                    'billing' => $session_billing,
                    'note' => 'Confirmation Page funktioniert (laut Chat: super) - Session-Daten verfügbar'
                ];
            }
        }
        
        return [
            'status' => 'warning',
            'note' => 'Session nicht verfügbar im Admin - Confirmation Page nutzt Session-Daten'
        ];
    }

    /**
     * Formatiert Adresse in einer Zeile für kompakte Anzeige
     */
    private function format_address_oneline($address) {
        if (empty($address)) {
            return '❌ Leer';
        }
        
        $parts = [];
        if (!empty($address['first_name']) || !empty($address['last_name'])) {
            $parts[] = trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? ''));
        }
        if (!empty($address['address_1'])) {
            $parts[] = $address['address_1'];
        }
        if (!empty($address['postcode']) || !empty($address['city'])) {
            $parts[] = trim(($address['postcode'] ?? '') . ' ' . ($address['city'] ?? ''));
        }
        if (!empty($address['email'])) {
            $parts[] = $address['email'];
        }
        
        return !empty($parts) ? implode(', ', $parts) : '❌ Keine Daten';
    }
    
}

