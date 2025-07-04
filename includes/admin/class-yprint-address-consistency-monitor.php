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
            .problem-info { background-color: #e3f2fd; border-left: 4px solid #2196f3; }
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
        
        // 🎯 SESSION VS. ORDER TRANSFER (HAUPTPROBLEM)
        $session_status = $results['session_transfer']['status'];
        $status_class = $session_status === 'critical' ? 'problem-critical' : 'problem-success';
        $status_icon = $session_status === 'critical' ? '🔴' : '✅';
        $status_text = $session_status === 'critical' ? 'KRITISCHES PROBLEM' : 'FUNKTIONIERT';
        
        $html .= '<div class="address-block ' . $status_class . '">';
        $html .= '<div class="system-title">' . $status_icon . ' Session vs. Order Konsistenz (' . $status_text . ')</div>';
        
        if ($session_status === 'critical') {
            $html .= '<div style="background: #ffebee; padding: 10px; margin: 10px 0; border-left: 4px solid #f44336;">';
            $html .= '<strong>🚨 ERKANNTE PROBLEME:</strong><br>';
            foreach ($results['session_transfer']['problems'] as $problem) {
                $html .= '&nbsp;&nbsp;• ' . $problem . '<br>';
            }
            $html .= '</div>';
        }
        
        $html .= '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin: 10px 0;">';
        
        // User Auswahl (Session)
        $html .= '<div style="background: #f0f8ff; padding: 10px; border-radius: 5px;">';
        $html .= '<strong>👤 USER AUSWAHL (Session):</strong><br>';
        if (!empty($results['session_transfer']['session_shipping'])) {
            $html .= '<strong>Shipping:</strong> ' . $this->format_address_oneline($results['session_transfer']['session_shipping']) . '<br>';
        } else {
            $html .= '<strong>Shipping:</strong> ❌ Keine Session-Daten<br>';
        }
        
        if ($results['session_transfer']['billing_different'] && !empty($results['session_transfer']['session_billing'])) {
            $html .= '<strong>Billing:</strong> ' . $this->format_address_oneline($results['session_transfer']['session_billing']) . '<br>';
            $html .= '<em>🔄 User wählte bewusst unterschiedliche Billing</em>';
        } else {
            $html .= '<strong>Billing:</strong> <em>Gleich wie Shipping</em>';
        }
        $html .= '</div>';
        
        // Finale Order 
        $html .= '<div style="background: #fff3e0; padding: 10px; border-radius: 5px;">';
        $html .= '<strong>📝 FINALE ORDER (WooCommerce):</strong><br>';
        $html .= '<strong>Shipping:</strong> ' . $this->format_address_oneline($results['session_transfer']['order_shipping']) . '<br>';
        $html .= '<strong>Billing:</strong> ' . $this->format_address_oneline($results['session_transfer']['order_billing']) . '<br>';
        $html .= '</div>';
        
        $html .= '</div>';
        $html .= '<em><strong>Fazit:</strong> ' . $results['session_transfer']['note'] . '</em>';
        $html .= '</div>';
        
        // 🔍 EXPRESS PAYMENT OVERRIDE DETECTION
        $express_status = $results['express_override']['status'];
        $status_class = $express_status === 'warning' ? 'problem-warning' : 'problem-success';
        $status_icon = $express_status === 'warning' ? '⚠️' : 'ℹ️';
        
        $html .= '<div class="address-block ' . $status_class . '">';
        $html .= '<div class="system-title">' . $status_icon . ' Express Payment Override Detection</div>';
        
        if ($results['express_override']['is_express']) {
            $html .= '<strong>🍎 Express Payment erkannt:</strong> Apple Pay / Google Pay<br>';
            $html .= '<strong>Payment Method ID:</strong> ' . $results['express_override']['stripe_pm_id'] . '<br>';
            
            if (!empty($results['express_override']['express_address'])) {
                $html .= '<br><strong>Express Payment Adresse:</strong><br>';
                $html .= '&nbsp;&nbsp;' . $this->format_address_oneline($results['express_override']['express_address']) . '<br>';
            }
            
            $html .= '<br><div style="background: #fff3cd; padding: 8px; border-radius: 4px;">';
            $html .= '<strong>⚠️ WARNUNG:</strong> Express Payment kann User-Auswahl überschreiben!';
            $html .= '</div>';
        } else {
            $html .= '<strong>Payment Type:</strong> Standard Checkout<br>';
            $html .= '<em>Keine Express Payment Überschreibung erwartet</em>';
        }
        
        $html .= '<br><em>' . $results['express_override']['note'] . '</em>';
        $html .= '</div>';
        
        // ⚖️ SYMMETRIE-ANALYSE
        $symmetry_status = $results['symmetry']['status'];
        $status_class = $symmetry_status === 'critical' ? 'problem-critical' : ($symmetry_status === 'warning' ? 'problem-warning' : 'problem-success');
        $status_icon = $symmetry_status === 'critical' ? '🔴' : ($symmetry_status === 'warning' ? '⚠️' : '✅');
        
        $html .= '<div class="address-block ' . $status_class . '">';
        $html .= '<div class="system-title">' . $status_icon . ' Shipping vs Billing Symmetrie</div>';
        
        if (!empty($results['symmetry']['problems'])) {
            $html .= '<strong>Asymmetrie gefunden:</strong><br>';
            foreach ($results['symmetry']['problems'] as $problem) {
                $html .= '&nbsp;&nbsp;• ' . $problem . '<br>';
            }
        }
        
        $html .= '<strong>Preservation Status:</strong><br>';
        $html .= '&nbsp;&nbsp;Shipping preserved: ' . ($results['symmetry']['shipping_preserved'] ? '✅' : '❌') . '<br>';
        $html .= '&nbsp;&nbsp;Billing preserved: ' . ($results['symmetry']['billing_preserved'] ? '✅' : '❌') . '<br>';
        $html .= '<em>' . $results['symmetry']['note'] . '</em>';
        $html .= '</div>';
        
        // 🏗️ CURRENT ORDER STATE (Baseline)
        $html .= '<div class="address-block problem-info">';
        $html .= '<div class="system-title">ℹ️ Aktuelle Order Adressen (Baseline)</div>';
        $html .= '<strong>Shipping:</strong> ' . $this->format_address_oneline($results['wc_order']['shipping']) . '<br>';
        $html .= '<strong>Billing:</strong> ' . $this->format_address_oneline($results['wc_order']['billing']) . '<br>';
        $html .= '<em>Diese Adressen werden an alle nachgelagerten Systeme weitergegeben</em>';
        $html .= '</div>';
        
        $html .= '</div>';
        
        wp_send_json_success(['html' => $html]);
    }
    
    private function analyze_order_consistency($order) {
        // 🎯 KERN-ANALYSE: Session-zu-Order Transfer (DAS WIRKLICHE PROBLEM)
        $session_analysis = $this->analyze_session_to_order_transfer($order);
        
        // 🔍 Express Payment Override Detection 
        $express_payment_analysis = $this->analyze_express_payment_override($order);
        
        // ⚖️ Symmetrie-Analyse: Shipping vs Billing Behandlung
        $symmetry_analysis = $this->analyze_shipping_billing_symmetry($order);
        
        // 🏗️ WooCommerce Order (Baseline - was steht tatsächlich drin)
        $wc_data = $this->analyze_woocommerce_order_real($order);
        
        // 🔴 Nachgelagerte Systeme (werden alle von WC Order beeinflusst)
        $downstream_analysis = $this->analyze_downstream_systems($order);
        
        return [
            'session_transfer' => $session_analysis,
            'express_override' => $express_payment_analysis,
            'symmetry' => $symmetry_analysis,
            'wc_order' => $wc_data,
            'downstream' => $downstream_analysis
        ];
    }
    
    /**
     * 🎯 KERN-ANALYSE: Session-zu-Order Transfer
     * Das ist das Hauptproblem - User wählt "Liefer Adresse", Order enthält "Buchental 15"
     */
    private function analyze_session_to_order_transfer($order) {
        $problems = [];
        $status = 'success';
        
        // Session-Daten aus Order-Meta rekonstruieren (was User gewählt hat)
        $recovered_session = $this->recover_session_data_from_order_meta($order);
        $session_shipping = $recovered_session['shipping'];
        $session_billing = $recovered_session['billing'];
        $billing_different = $recovered_session['billing_different'];
        
        // Debug logging für Admin
        error_log('🔍 MONITOR DEBUG: Session backup meta: ' . (!empty($session_backup) ? 'FOUND' : 'EMPTY'));
        error_log('🔍 MONITOR DEBUG: Original shipping meta: ' . (!empty($original_shipping) ? 'FOUND' : 'EMPTY'));
        error_log('🔍 MONITOR DEBUG: Reconstructed session_shipping address_1: ' . ($session_shipping['address_1'] ?? 'N/A'));
        
        // Order-Daten (was tatsächlich gespeichert wurde)
        $order_shipping = [
            'first_name' => $order->get_shipping_first_name(),
            'last_name' => $order->get_shipping_last_name(),
            'address_1' => $order->get_shipping_address_1(),
            'city' => $order->get_shipping_city(),
            'postcode' => $order->get_shipping_postcode(),
            'country' => $order->get_shipping_country()
        ];
        
        $order_billing = [
            'first_name' => $order->get_billing_first_name(),
            'last_name' => $order->get_billing_last_name(),
            'address_1' => $order->get_billing_address_1(),
            'city' => $order->get_billing_city(),
            'postcode' => $order->get_billing_postcode(),
            'country' => $order->get_billing_country()
        ];
        
        // 🔍 SHIPPING TRANSFER CHECK
        if (!empty($session_shipping)) {
            $shipping_matches = $this->compare_addresses_strict($session_shipping, $order_shipping);
            if (!$shipping_matches) {
                $status = 'critical';
                $user_choice = $session_shipping['address_1'] ?? 'N/A';
                $final_result = $order_shipping['address_1'] ?? 'N/A';
                
                // Spezielle Erkennung für Ihre "Liefer Adresse" / "Rechnungs Adresse" 
                if ($user_choice === 'Liefer Adresse' || $user_choice === 'Rechnungs Adresse') {
                    $problems[] = '🔴 SHIPPING OVERRIDE: User wählte Adresse "' . $user_choice . '" aber Order enthält "' . $final_result . '" (Apple Pay Override!)';
                } else {
                    $problems[] = '🔴 SHIPPING OVERRIDE: User wählte "' . $user_choice . '" aber Order enthält "' . $final_result . '"';
                }
            }
        } else {
            // Keine Session-Daten gefunden - das ist ein Problem für sich
            $status = 'warning';
            $problems[] = '⚠️ KEINE SESSION-DATEN: Ursprüngliche User-Auswahl nicht in Order-Meta gefunden';
        }
        
        // 🔍 BILLING TRANSFER CHECK
        if ($billing_different && !empty($session_billing)) {
            $billing_matches = $this->compare_addresses_strict($session_billing, $order_billing);
            if (!$billing_matches) {
                $status = 'critical';
                $problems[] = '🔴 BILLING OVERRIDE: User wählte separate Billing aber wurde überschrieben';
            }
        }
        
        return [
            'status' => $status,
            'session_shipping' => $session_shipping,
            'session_billing' => $session_billing,
            'order_shipping' => $order_shipping,
            'order_billing' => $order_billing,
            'billing_different' => $billing_different,
            'problems' => $problems,
            'note' => empty($problems) ? 'Session-zu-Order Transfer funktioniert korrekt' : 'KRITISCH: Session-Auswahl wird überschrieben!'
        ];
    }
    
    /**
     * 🔍 Express Payment Override Detection
     */
    private function analyze_express_payment_override($order) {
        $payment_method = $order->get_payment_method();
        $stripe_payment_method = $order->get_meta('_stripe_payment_method_id');
        $apple_pay_data = $order->get_meta('_stripe_payment_request_data');
        
        $is_express_payment = !empty($apple_pay_data) || 
                             strpos($stripe_payment_method, 'pm_') === 0;
        
        if ($is_express_payment) {
            // Express Payment Daten aus Meta
            $express_billing = $order->get_meta('_stripe_billing_details');
            $express_address = $express_billing['address'] ?? [];
            
            return [
                'status' => 'warning',
                'is_express' => true,
                'payment_method' => $payment_method,
                'stripe_pm_id' => $stripe_payment_method,
                'express_address' => $express_address,
                'note' => 'Express Payment erkannt - potentielle Adress-Überschreibung durch Apple Pay/Google Pay'
            ];
        }
        
        return [
            'status' => 'success',
            'is_express' => false,
            'note' => 'Standard Payment - keine Express Payment Überschreibung'
        ];
    }
    
    /**
     * ⚖️ Symmetrie-Analyse: Shipping vs Billing Behandlung
     */
    private function analyze_shipping_billing_symmetry($order) {
        $session_shipping = WC()->session ? WC()->session->get('yprint_selected_address', []) : [];
        $session_billing = WC()->session ? WC()->session->get('yprint_billing_address', []) : [];
        $billing_different = WC()->session ? WC()->session->get('yprint_billing_address_different', false) : false;
        
        $order_shipping = [
            'address_1' => $order->get_shipping_address_1(),
            'city' => $order->get_shipping_city(),
            'postcode' => $order->get_shipping_postcode()
        ];
        
        $order_billing = [
            'address_1' => $order->get_billing_address_1(),
            'city' => $order->get_billing_city(),
            'postcode' => $order->get_billing_postcode()
        ];
        
        $shipping_preserved = empty($session_shipping) || $this->compare_addresses_strict($session_shipping, $order_shipping);
        $billing_preserved = !$billing_different || empty($session_billing) || $this->compare_addresses_strict($session_billing, $order_billing);
        
        $status = 'success';
        $problems = [];
        
        if ($shipping_preserved && !$billing_preserved) {
            $status = 'warning';
            $problems[] = 'Asymmetrie: Shipping OK, Billing überschrieben';
        } elseif (!$shipping_preserved && $billing_preserved) {
            $status = 'critical';
            $problems[] = 'HAUPTPROBLEM: Shipping überschrieben, Billing OK - ungleiche Behandlung!';
        } elseif (!$shipping_preserved && !$billing_preserved) {
            $status = 'critical';
            $problems[] = 'Beide Adressen überschrieben';
        }
        
        return [
            'status' => $status,
            'shipping_preserved' => $shipping_preserved,
            'billing_preserved' => $billing_preserved,
            'problems' => $problems,
            'note' => empty($problems) ? 'Symmetrische Behandlung - beide Adressen korrekt' : implode(', ', $problems)
        ];
    }
    
    /**
     * Strict address comparison for exact matching
     */
    private function compare_addresses_strict($addr1, $addr2) {
        if (empty($addr1) || empty($addr2)) {
            return false;
        }
        
        $key_fields = ['address_1', 'city', 'postcode'];
        foreach ($key_fields as $field) {
            $val1 = trim($addr1[$field] ?? '');
            $val2 = trim($addr2[$field] ?? '');
            if ($val1 !== $val2) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 🏗️ WooCommerce Order - Echte Daten (nicht "korrekt" annehmen)
     */
    private function analyze_woocommerce_order_real($order) {
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
            'country' => $order->get_billing_country()
        ];
        
        return [
            'status' => 'baseline',
            'shipping' => $shipping,
            'billing' => $billing,
            'note' => 'WooCommerce Order Baseline - diese Daten werden an alle nachgelagerten Systeme weitergegeben'
        ];
    }
    
    /**
     * 🔴 Nachgelagerte Systeme (alle bekommen falsche Daten von WC Order)
     */
    private function analyze_downstream_systems($order) {
        return [
            'status' => 'inherited_problems',
            'note' => 'E-Mail, Stripe, Confirmation, Admin - alle Systeme erben die Inkonsistenzen aus der WooCommerce Order',
            'affected_systems' => [
                'E-Mail Templates',
                'Stripe Dashboard', 
                'Admin Backend',
                'Confirmation Page (teilweise)',
                'Fulfillment/Logistik'
            ]
        ];
    }
    
    /**
     * 🔍 Versuche Session-Daten aus verschiedenen Order-Meta-Keys zu rekonstruieren
     */
    private function recover_session_data_from_order_meta($order) {
        $session_data = [
            'shipping' => [],
            'billing' => [],
            'billing_different' => false
        ];
        
        // 1. Versuche primären Session-Backup Key
        $session_backup = $order->get_meta('_yprint_session_backup');
        if (!empty($session_backup)) {
            error_log('🔍 MONITOR: Found _yprint_session_backup meta');
            return [
                'shipping' => $session_backup['selected'] ?? [],
                'billing' => $session_backup['billing'] ?? [],
                'billing_different' => $session_backup['billing_different'] ?? false
            ];
        }
        
        // 2. Versuche originale Shipping-Adresse 
        $original_shipping = $order->get_meta('_yprint_original_shipping_address');
        if (!empty($original_shipping)) {
            error_log('🔍 MONITOR: Found _yprint_original_shipping_address meta');
            $session_data['shipping'] = $original_shipping;
        }
        
        // 3. Versuche andere Meta-Keys (falls gesetzt)
        $yprint_applied = $order->get_meta('_yprint_addresses_applied');
        $yprint_applied_auth = $order->get_meta('_yprint_addresses_applied_authoritatively');
        
        if ($yprint_applied || $yprint_applied_auth) {
            error_log('🔍 MONITOR: Found YPrint application markers - original selection was probably overridden');
        }
        
        // 4. Debug alle verfügbaren Meta-Keys
        $all_meta = $order->get_meta_data();
        $yprint_meta_keys = [];
        foreach ($all_meta as $meta) {
            if (strpos($meta->key, 'yprint') !== false || strpos($meta->key, '_stripe_') !== false) {
                $yprint_meta_keys[] = $meta->key;
            }
        }
        
        error_log('🔍 MONITOR: Available YPrint/Stripe meta keys: ' . implode(', ', $yprint_meta_keys));
        
        return $session_data;
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

