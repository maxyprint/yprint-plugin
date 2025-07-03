<?php
/**
 * YPrint Address Orchestrator - Central Address Authority
 * 
 * PILOT IMPLEMENTATION: Wallet Payment Pipeline Only
 * 
 * This class implements the Hybrid Pipeline Architecture with three unbreakable principles:
 * 1. Central Address Authority - ALL payment methods must route through this orchestrator
 * 2. Complete Traceability - Every step is logged for full transparency  
 * 3. Guaranteed Timing - Process completes before confirmation step
 * 
 * @package YPrint
 * @version 1.0.0 (Pilot - Wallet Only)
 */

if (!defined('ABSPATH')) {
    exit;
}

class YPrint_Address_Orchestrator {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Logging prefix for consistency
     */
    private const LOG_PREFIX = '🎯 AddressOrchestrator:';

    /**
     * Address sources priority hierarchy
     */
    private const PRIORITY_HIERARCHY = [
        'manual' => 1,    // Highest priority (future)
        'wallet' => 2,    // High priority (current pilot)
        'form' => 3,      // Medium priority (future)
        'legacy' => 4     // Lowest priority (future)
    ];

    /**
     * Collected addresses from all sources
     */
    private $collected_addresses = [];

    /**
     * Final resolved addresses
     */
    private $final_addresses = [];

    /**
     * Processing context for logging
     */
    private $context = '';

    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor (singleton)
     */
    private function __construct() {
        // Initialize hooks for pilot wallet payment integration
        $this->init_wallet_payment_hooks();
        
        // Initialize AJAX handlers for frontend communication
        $this->init_ajax_handlers();
    }

    /**
 * Initialize hooks for wallet payment integration (pilot)
 */
private function init_wallet_payment_hooks() {
    // Hook into Stripe payment processing for wallet payments
    add_action('yprint_wallet_payment_processing', [$this, 'process_wallet_payment_addresses'], 5, 2);
    
    // HOOK ENTFERNT: Verhindert doppelten Aufruf - Orchestrator wird manuell von Payment Gateways aufgerufen
    // add_action('woocommerce_checkout_create_order', [$this, 'orchestrate_addresses_for_order'], 5, 2);
    
    // Hook for Express Payment processing
    add_action('yprint_express_payment_complete', [$this, 'finalize_wallet_addresses'], 10, 2);
}

    /**
     * MAIN ORCHESTRATION METHOD
     * 
     * Implements the three-phase process: Collection → Decision → Distribution
     * 
     * @param WC_Order $order WooCommerce order object
     * @param array $payment_data Payment method data from Stripe
     * @return bool Success status
     */
    public function orchestrate_addresses_for_order($order, $payment_data = null) {
        if (!$order instanceof WC_Order) {
            $this->log_step('ERROR: Invalid order object provided', 'error');
            return false;
        }

        $this->context = 'Order #' . $order->get_id();
        $this->log_step('=== ORCHESTRATION START ===', 'info');
        $this->log_step('Processing for ' . $this->context, 'info');

        try {
            // PHASE 1: COLLECTION
            $this->collect_addresses($order, $payment_data);

            // PHASE 2: DECISION  
            $this->apply_hierarchy();

            // PHASE 3: DISTRIBUTION
            $this->distribute_addresses($order);

            $this->log_step('=== ORCHESTRATION COMPLETE ===', 'success');
            return true;

        } catch (Exception $e) {
            $this->log_step('ORCHESTRATION FAILED: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * PHASE 1: COLLECTION
     * 
     * Collect addresses from all available sources (pilot: wallet only)
     * 
     * @param WC_Order $order WooCommerce order object
     * @param array $payment_data Payment method data
     */
    private function collect_addresses($order, $payment_data = null) {
        $this->log_step('=== SAMMLUNG START ===', 'phase');
        $this->collected_addresses = [];
    
        // PRODUCTION: Collect all address sources with proper priority
        $this->collect_manual_addresses();      // Priority 1 (highest)
        $this->collect_wallet_addresses($order, $payment_data);  // Priority 2
        $this->collect_form_addresses($order);  // Priority 3  
        $this->collect_legacy_addresses($order); // Priority 4 (fallback)
    
        $this->log_step('Sammlung abgeschlossen. Quellen gefunden: ' . count($this->collected_addresses), 'info');
        $this->log_step('=== SAMMLUNG END ===', 'phase');
    }

    /**
     * Collect addresses from wallet payments (Apple Pay, Google Pay, etc.)
     * 
     * @param WC_Order $order WooCommerce order object  
     * @param array $payment_data Payment method data
     */
    private function collect_wallet_addresses($order, $payment_data = null) {
        $this->log_step('Sammle Wallet-Adressen...', 'collection');

        $wallet_address = null;

        // Method 1: Extract from payment_data (preferred)
        if (!empty($payment_data) && isset($payment_data['billing_details'])) {
            $wallet_address = $this->extract_address_from_payment_method($payment_data);
            $this->log_step('└─ Wallet-Adresse aus Payment Method extrahiert', 'collection');
        }

        // Method 2: Extract from order (if payment_data not available)
        if (empty($wallet_address)) {
            $wallet_address = $this->extract_address_from_order($order);
            $this->log_step('└─ Wallet-Adresse aus Order extrahiert', 'collection');
        }

        // Method 3: Check session for Apple Pay address (fallback)
        if (empty($wallet_address) && WC()->session) {
            $apple_pay_address = WC()->session->get('yprint_apple_pay_address', array());
            if (!empty($apple_pay_address)) {
                $wallet_address = $this->normalize_apple_pay_address($apple_pay_address);
                $this->log_step('└─ Apple Pay Adresse aus Session geladen', 'collection');
            }
        }

        if (!empty($wallet_address)) {
            $this->collected_addresses['wallet'] = [
                'source' => 'wallet',
                'priority' => self::PRIORITY_HIERARCHY['wallet'],
                'shipping' => $wallet_address['shipping'] ?? [],
                'billing' => $wallet_address['billing'] ?? [],
                'billing_different' => $wallet_address['billing_different'] ?? false,
                'metadata' => [
                    'payment_method' => $payment_data['type'] ?? 'unknown',
                    'wallet_type' => $this->detect_wallet_type($payment_data),
                    'collected_at' => current_time('mysql')
                ]
            ];

            $this->log_step('└─ Wallet-Adresse erfolgreich gesammelt:', 'collection');
            $this->log_step('    ├─ Wallet-Typ: ' . $this->collected_addresses['wallet']['metadata']['wallet_type'], 'collection');
            $this->log_step('    ├─ Shipping: ' . ($wallet_address['shipping']['address_1'] ?? 'leer'), 'collection');
            $this->log_step('    └─ Billing Different: ' . ($wallet_address['billing_different'] ? 'ja' : 'nein'), 'collection');
        } else {
            $this->log_step('└─ Keine Wallet-Adressen gefunden', 'collection');
        }
    }

    /**
 * Collect addresses from YPrint manual selection (highest priority)
 */
private function collect_manual_addresses() {
    $this->log_step('Sammle YPrint Manual-Adressen...', 'collection');

    if (!WC()->session) {
        $this->log_step('└─ WooCommerce Session nicht verfügbar', 'collection');
        return;
    }

    // Collect YPrint session data
    $selected_address = WC()->session->get('yprint_selected_address', array());
    $billing_address = WC()->session->get('yprint_billing_address', array());
    $billing_different = WC()->session->get('yprint_billing_address_different', false);

    $this->log_step('└─ Session-Daten gelesen:', 'collection');
    $this->log_step('    ├─ Selected Address: ' . (!empty($selected_address) ? 'VORHANDEN' : 'LEER'), 'collection');
    $this->log_step('    ├─ Billing Different: ' . ($billing_different ? 'JA' : 'NEIN'), 'collection');
    $this->log_step('    └─ Billing Address: ' . (!empty($billing_address) ? 'VORHANDEN' : 'LEER'), 'collection');

    // Only proceed if we have manual selection data
    if (empty($selected_address)) {
        $this->log_step('└─ Keine YPrint Manual-Auswahl gefunden', 'collection');
        return;
    }

    // Validate and normalize selected address
    $normalized_shipping = $this->normalize_yprint_address($selected_address);
    if (empty($normalized_shipping)) {
        $this->log_step('└─ Fehler bei Normalisierung der Shipping-Adresse', 'collection');
        return;
    }

    // Determine billing address
    $normalized_billing = null;
    $is_billing_different = false;

    if ($billing_different && !empty($billing_address)) {
        // User selected different billing address
        $normalized_billing = $this->normalize_yprint_address($billing_address);
        $is_billing_different = true;
        $this->log_step('└─ Separate Billing-Adresse normalisiert', 'collection');
    } else {
        // Billing same as shipping
        $normalized_billing = $normalized_shipping;
        $is_billing_different = false;
        $this->log_step('└─ Billing = Shipping (gleiche Adresse)', 'collection');
    }

    // Store collected manual addresses
    $this->collected_addresses['manual'] = [
        'source' => 'manual',
        'priority' => self::PRIORITY_HIERARCHY['manual'],
        'shipping' => $normalized_shipping,
        'billing' => $normalized_billing,
        'billing_different' => $is_billing_different,
        'metadata' => [
            'selection_method' => 'yprint_session',
            'shipping_id' => $selected_address['id'] ?? null,
            'billing_id' => $billing_address['id'] ?? null,
            'collected_at' => current_time('mysql'),
            'session_consistent' => $this->validate_session_consistency($selected_address, $billing_address)
        ]
    ];

    $this->log_step('└─ YPrint Manual-Adressen erfolgreich gesammelt:', 'collection');
    $this->log_step('    ├─ Shipping: ' . $normalized_shipping['address_1'] . ', ' . $normalized_shipping['city'], 'collection');
    $this->log_step('    ├─ Billing: ' . $normalized_billing['address_1'] . ', ' . $normalized_billing['city'], 'collection');
    $this->log_step('    └─ Billing Different: ' . ($is_billing_different ? 'JA' : 'NEIN'), 'collection');
}

/**
 * Collect addresses from direct form input (fallback)
 * 
 * @param WC_Order $order WooCommerce order object
 */
private function collect_form_addresses($order) {
    $this->log_step('Sammle Form-Adressen...', 'collection');

    // Extract current order addresses as form input
    $shipping_data = [
        'first_name' => $order->get_shipping_first_name(),
        'last_name' => $order->get_shipping_last_name(),
        'company' => $order->get_shipping_company(),
        'address_1' => $order->get_shipping_address_1(),
        'address_2' => $order->get_shipping_address_2(),
        'city' => $order->get_shipping_city(),
        'postcode' => $order->get_shipping_postcode(),
        'country' => $order->get_shipping_country(),
        'state' => $order->get_shipping_state()
    ];

    $billing_data = [
        'first_name' => $order->get_billing_first_name(),
        'last_name' => $order->get_billing_last_name(),
        'company' => $order->get_billing_company(),
        'address_1' => $order->get_billing_address_1(),
        'address_2' => $order->get_billing_address_2(),
        'city' => $order->get_billing_city(),
        'postcode' => $order->get_billing_postcode(),
        'country' => $order->get_billing_country(),
        'state' => $order->get_billing_state(),
        'email' => $order->get_billing_email(),
        'phone' => $order->get_billing_phone()
    ];

    // Only collect if addresses contain meaningful data
    if (!empty($shipping_data['address_1']) || !empty($billing_data['address_1'])) {
        
        $this->collected_addresses['form'] = [
            'source' => 'form',
            'priority' => self::PRIORITY_HIERARCHY['form'],
            'shipping' => !empty($shipping_data['address_1']) ? $shipping_data : $billing_data,
            'billing' => $billing_data,
            'billing_different' => $this->addresses_are_different($shipping_data, $billing_data),
            'metadata' => [
                'form_type' => 'woocommerce_checkout',
                'collected_at' => current_time('mysql')
            ]
        ];

        $this->log_step('└─ Form-Adressen gesammelt (Fallback)', 'collection');
    } else {
        $this->log_step('└─ Keine Form-Adressen gefunden', 'collection');
    }
}

/**
 * Collect legacy addresses from WooCommerce customer (final fallback)
 * 
 * @param WC_Order $order WooCommerce order object
 */
private function collect_legacy_addresses($order) {
    $this->log_step('Sammle Legacy-Adressen...', 'collection');

    if (!WC()->customer) {
        $this->log_step('└─ WooCommerce Customer nicht verfügbar', 'collection');
        return;
    }

    $shipping_data = [
        'first_name' => WC()->customer->get_shipping_first_name(),
        'last_name' => WC()->customer->get_shipping_last_name(),
        'company' => WC()->customer->get_shipping_company(),
        'address_1' => WC()->customer->get_shipping_address_1(),
        'address_2' => WC()->customer->get_shipping_address_2(),
        'city' => WC()->customer->get_shipping_city(),
        'postcode' => WC()->customer->get_shipping_postcode(),
        'country' => WC()->customer->get_shipping_country(),
        'state' => WC()->customer->get_shipping_state()
    ];

    $billing_data = [
        'first_name' => WC()->customer->get_billing_first_name(),
        'last_name' => WC()->customer->get_billing_last_name(),
        'company' => WC()->customer->get_billing_company(),
        'address_1' => WC()->customer->get_billing_address_1(),
        'address_2' => WC()->customer->get_billing_address_2(),
        'city' => WC()->customer->get_billing_city(),
        'postcode' => WC()->customer->get_billing_postcode(),
        'country' => WC()->customer->get_billing_country(),
        'state' => WC()->customer->get_billing_state(),
        'email' => WC()->customer->get_billing_email(),
        'phone' => WC()->customer->get_billing_phone()
    ];

    // Only collect if customer has stored addresses
    if (!empty($shipping_data['address_1']) || !empty($billing_data['address_1'])) {
        
        $this->collected_addresses['legacy'] = [
            'source' => 'legacy',
            'priority' => self::PRIORITY_HIERARCHY['legacy'],
            'shipping' => !empty($shipping_data['address_1']) ? $shipping_data : $billing_data,
            'billing' => $billing_data,
            'billing_different' => $this->addresses_are_different($shipping_data, $billing_data),
            'metadata' => [
                'customer_id' => WC()->customer->get_id(),
                'collected_at' => current_time('mysql')
            ]
        ];

        $this->log_step('└─ Legacy Customer-Adressen gesammelt (Final Fallback)', 'collection');
    } else {
        $this->log_step('└─ Keine Legacy-Adressen im Customer Object', 'collection');
    }
}

/**
 * Check if two addresses are different
 * 
 * @param array $addr1 First address
 * @param array $addr2 Second address  
 * @return bool True if addresses are different
 */
private function addresses_are_different($addr1, $addr2) {
    $compare_fields = ['address_1', 'city', 'postcode', 'country'];
    
    foreach ($compare_fields as $field) {
        if (($addr1[$field] ?? '') !== ($addr2[$field] ?? '')) {
            return true;
        }
    }
    
    return false;
}

/**
 * Normalize YPrint address format to orchestrator standard
 * 
 * @param array $yprint_address YPrint address data from session
 * @return array|null Normalized address or null if invalid
 */
private function normalize_yprint_address($yprint_address) {
    if (empty($yprint_address) || !is_array($yprint_address)) {
        return null;
    }

    // Validate required fields
    $required_fields = ['address_1', 'city', 'postcode', 'country'];
    foreach ($required_fields as $field) {
        if (empty($yprint_address[$field])) {
            $this->log_step("Validation failed: Missing $field in YPrint address", 'warning');
            return null;
        }
    }

    // Normalize to standard format
    return [
        'first_name' => sanitize_text_field($yprint_address['first_name'] ?? ''),
        'last_name' => sanitize_text_field($yprint_address['last_name'] ?? ''),
        'company' => sanitize_text_field($yprint_address['company'] ?? ''),
        'address_1' => sanitize_text_field($yprint_address['address_1']),
        'address_2' => sanitize_text_field($yprint_address['address_2'] ?? ''),
        'city' => sanitize_text_field($yprint_address['city']),
        'postcode' => sanitize_text_field($yprint_address['postcode']),
        'country' => sanitize_text_field($yprint_address['country'] ?? 'DE'),
        'state' => sanitize_text_field($yprint_address['state'] ?? ''),
        'phone' => sanitize_text_field($yprint_address['phone'] ?? ''),
        'email' => sanitize_email($yprint_address['email'] ?? '')
    ];
}

/**
 * Validate consistency between session addresses
 * 
 * @param array $shipping_address Shipping address from session
 * @param array $billing_address Billing address from session
 * @return bool True if addresses are consistent
 */
private function validate_session_consistency($shipping_address, $billing_address) {
    // Basic consistency checks
    if (empty($shipping_address)) {
        return false;
    }

    // Check if addresses are properly structured
    $shipping_valid = !empty($shipping_address['address_1']) && !empty($shipping_address['city']);
    
    if (!empty($billing_address)) {
        $billing_valid = !empty($billing_address['address_1']) && !empty($billing_address['city']);
        return $shipping_valid && $billing_valid;
    }

    return $shipping_valid;
}

    /**
     * Extract address from Stripe payment method data
     * 
     * @param array $payment_data Payment method data from Stripe
     * @return array|null Normalized address data
     */
    private function extract_address_from_payment_method($payment_data) {
        if (empty($payment_data['billing_details'])) {
            return null;
        }

        $billing_details = $payment_data['billing_details'];
        $address = $billing_details['address'] ?? [];

        if (empty($address)) {
            return null;
        }

        // Normalize Stripe address format to YPrint format
        $normalized_shipping = [
            'first_name' => $billing_details['name'] ? explode(' ', $billing_details['name'])[0] : '',
            'last_name' => $billing_details['name'] ? implode(' ', array_slice(explode(' ', $billing_details['name']), 1)) : '',
            'company' => '',
            'address_1' => $address['line1'] ?? '',
            'address_2' => $address['line2'] ?? '',
            'city' => $address['city'] ?? '',
            'postcode' => $address['postal_code'] ?? '',
            'country' => $address['country'] ?? 'DE',
            'state' => $address['state'] ?? '',
            'phone' => $billing_details['phone'] ?? ''
        ];

        // For wallet payments, shipping = billing (typically)
        return [
            'shipping' => $normalized_shipping,
            'billing' => $normalized_shipping,
            'billing_different' => false
        ];
    }

    /**
     * Extract address from WooCommerce order (fallback method)
     * 
     * @param WC_Order $order WooCommerce order object
     * @return array|null Normalized address data
     */
    private function extract_address_from_order($order) {
        $shipping_address = $order->get_shipping_address();
        $billing_address = $order->get_billing_address();

        if (empty($shipping_address) && empty($billing_address)) {
            return null;
        }

        // Use existing order addresses as wallet source
        return [
            'shipping' => $shipping_address ?: $billing_address,
            'billing' => $billing_address ?: $shipping_address,
            'billing_different' => !empty($shipping_address) && !empty($billing_address) && 
                                 ($shipping_address['address_1'] !== $billing_address['address_1'])
        ];
    }

    /**
     * Normalize Apple Pay address from session to standard format
     * 
     * @param array $apple_pay_address Apple Pay address from session
     * @return array Normalized address data
     */
    private function normalize_apple_pay_address($apple_pay_address) {
        $normalized = [
            'first_name' => $apple_pay_address['first_name'] ?? '',
            'last_name' => $apple_pay_address['last_name'] ?? '',
            'company' => $apple_pay_address['company'] ?? '',
            'address_1' => $apple_pay_address['address_1'] ?? '',
            'address_2' => $apple_pay_address['address_2'] ?? '',
            'city' => $apple_pay_address['city'] ?? '',
            'postcode' => $apple_pay_address['postcode'] ?? '',
            'country' => $apple_pay_address['country'] ?? 'DE',
            'state' => $apple_pay_address['state'] ?? '',
            'phone' => $apple_pay_address['phone'] ?? ''
        ];

        return [
            'shipping' => $normalized,
            'billing' => $normalized,
            'billing_different' => false
        ];
    }

    /**
     * Detect wallet type from payment data
     * 
     * @param array $payment_data Payment method data
     * @return string Wallet type
     */
    private function detect_wallet_type($payment_data) {
        if (empty($payment_data)) {
            return 'unknown';
        }

        // Check for wallet information in payment method
        if (isset($payment_data['card']['wallet'])) {
            return $payment_data['card']['wallet']['type'] ?? 'wallet';
        }

        // Check payment method type
        if (isset($payment_data['type'])) {
            if (strpos($payment_data['type'], 'apple_pay') !== false) {
                return 'apple_pay';
            }
            if (strpos($payment_data['type'], 'google_pay') !== false) {
                return 'google_pay';
            }
        }

        return 'card';
    }

    /**
     * PHASE 2: DECISION
     * 
     * Apply priority hierarchy to select final addresses
     */
    private function apply_hierarchy() {
        $this->log_step('=== ENTSCHEIDUNG START ===', 'phase');
        
        if (empty($this->collected_addresses)) {
            $this->log_step('Keine Adressen zum Priorisieren gefunden', 'warning');
            return;
        }
    
        // Sort addresses by priority (1 = highest priority)
        $sorted_sources = $this->collected_addresses;
        uasort($sorted_sources, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
    
        $this->log_step('Priorisierungs-Reihenfolge:', 'decision');
        foreach ($sorted_sources as $source => $data) {
            $this->log_step("└─ {$data['priority']}. {$source} ({$data['source']})", 'decision');
        }
    
        // Select highest priority addresses
        $selected_source = array_key_first($sorted_sources);
        $selected_data = $sorted_sources[$selected_source];
    
        $this->final_addresses = [
            'source' => $selected_data['source'],
            'priority' => $selected_data['priority'],
            'shipping' => $selected_data['shipping'],
            'billing' => $selected_data['billing'],
            'billing_different' => $selected_data['billing_different'],
            'metadata' => array_merge($selected_data['metadata'], [
                'selected_from' => $selected_source,
                'alternatives_available' => array_keys($sorted_sources),
                'decision_timestamp' => current_time('mysql')
            ])
        ];
    
        $this->log_step("ENTSCHEIDUNG: {$selected_data['source']} (Priorität {$selected_data['priority']}) gewählt", 'success');
        $this->log_step('└─ Shipping: ' . $this->final_addresses['shipping']['address_1'] . ', ' . $this->final_addresses['shipping']['city'], 'decision');
        $this->log_step('└─ Billing: ' . $this->final_addresses['billing']['address_1'] . ', ' . $this->final_addresses['billing']['city'], 'decision');
        
        $this->log_step('=== ENTSCHEIDUNG END ===', 'phase');
    }

    /**
     * PHASE 3: DISTRIBUTION
     * 
     * Distribute final addresses to all target systems
     * 
     * @param WC_Order $order WooCommerce order object
     */
    private function distribute_addresses($order) {
        $this->log_step('=== VERTEILUNG START ===', 'phase');

        if (empty($this->final_addresses)) {
            $this->log_step('Keine finalen Adressen zum Verteilen', 'warning');
            return;
        }

        // Distribute to WooCommerce Order
        $this->distribute_to_woocommerce_order($order);

        // Distribute to Session (for UI consistency)
        $this->distribute_to_session();

        // Distribute to Customer Object (for WooCommerce integration)
        $this->distribute_to_customer();

        $this->log_step('=== VERTEILUNG END ===', 'phase');
    }

    /**
     * Distribute addresses to WooCommerce order fields
     * 
     * @param WC_Order $order WooCommerce order object
     */
    private function distribute_to_woocommerce_order($order) {
        $this->log_step('Verteile an WooCommerce Order...', 'distribution');

        $shipping = $this->final_addresses['shipping'];
        $billing = $this->final_addresses['billing'];

        // Set shipping address
        if (!empty($shipping)) {
            $order->set_shipping_first_name($shipping['first_name'] ?? '');
            $order->set_shipping_last_name($shipping['last_name'] ?? '');
            $order->set_shipping_company($shipping['company'] ?? '');
            $order->set_shipping_address_1($shipping['address_1'] ?? '');
            $order->set_shipping_address_2($shipping['address_2'] ?? '');
            $order->set_shipping_city($shipping['city'] ?? '');
            $order->set_shipping_postcode($shipping['postcode'] ?? '');
            $order->set_shipping_country($shipping['country'] ?? 'DE');
            $order->set_shipping_state($shipping['state'] ?? '');

            $this->log_step('└─ Shipping Fields geschrieben: ' . ($shipping['address_1'] ?? 'leer'), 'distribution');
        }

        // Set billing address
        if (!empty($billing)) {
            $order->set_billing_first_name($billing['first_name'] ?? '');
            $order->set_billing_last_name($billing['last_name'] ?? '');
            $order->set_billing_company($billing['company'] ?? '');
            $order->set_billing_address_1($billing['address_1'] ?? '');
            $order->set_billing_address_2($billing['address_2'] ?? '');
            $order->set_billing_city($billing['city'] ?? '');
            $order->set_billing_postcode($billing['postcode'] ?? '');
            $order->set_billing_country($billing['country'] ?? 'DE');
            $order->set_billing_state($billing['state'] ?? '');
            // Note: Billing email and phone are typically preserved from payment method

            $this->log_step('└─ Billing Fields geschrieben: ' . ($billing['address_1'] ?? 'leer'), 'distribution');
        }

        // Add metadata for traceability
        $order->update_meta_data('_yprint_address_source', $this->final_addresses['source']);
        $order->update_meta_data('_yprint_address_processed_by', 'AddressOrchestrator');
        $order->update_meta_data('_yprint_address_processed_at', current_time('mysql'));
        $order->update_meta_data('_yprint_address_metadata', $this->final_addresses['metadata']);

        $this->log_step('└─ Order Meta-Data für Traceability geschrieben', 'distribution');
    }

    /**
     * Distribute addresses to session (for UI consistency)
     */
    private function distribute_to_session() {
        if (!WC()->session) {
            $this->log_step('└─ WooCommerce Session nicht verfügbar', 'distribution');
            return;
        }

        $this->log_step('Verteile an WooCommerce Session...', 'distribution');

        // Update YPrint sessions to reflect final addresses
        WC()->session->set('yprint_selected_address', $this->final_addresses['shipping']);
        
        if ($this->final_addresses['billing_different']) {
            WC()->session->set('yprint_billing_address', $this->final_addresses['billing']);
            WC()->session->set('yprint_billing_address_different', true);
        } else {
            WC()->session->set('yprint_billing_address', $this->final_addresses['shipping']);
            WC()->session->set('yprint_billing_address_different', false);
        }

        $this->log_step('└─ YPrint Sessions aktualisiert', 'distribution');
    }

    /**
     * Distribute addresses to WooCommerce customer object
     */
    private function distribute_to_customer() {
        if (!WC()->customer) {
            $this->log_step('└─ WooCommerce Customer nicht verfügbar', 'distribution');
            return;
        }

        $this->log_step('Verteile an WooCommerce Customer...', 'distribution');

        $shipping = $this->final_addresses['shipping'];
        $billing = $this->final_addresses['billing'];

        // Update customer shipping address
        if (!empty($shipping)) {
            WC()->customer->set_shipping_first_name($shipping['first_name'] ?? '');
            WC()->customer->set_shipping_last_name($shipping['last_name'] ?? '');
            WC()->customer->set_shipping_company($shipping['company'] ?? '');
            WC()->customer->set_shipping_address_1($shipping['address_1'] ?? '');
            WC()->customer->set_shipping_address_2($shipping['address_2'] ?? '');
            WC()->customer->set_shipping_city($shipping['city'] ?? '');
            WC()->customer->set_shipping_postcode($shipping['postcode'] ?? '');
            WC()->customer->set_shipping_country($shipping['country'] ?? 'DE');
            WC()->customer->set_shipping_state($shipping['state'] ?? '');
        }

        // Update customer billing address (preserve email from payment method)
        if (!empty($billing)) {
            WC()->customer->set_billing_first_name($billing['first_name'] ?? '');
            WC()->customer->set_billing_last_name($billing['last_name'] ?? '');
            WC()->customer->set_billing_company($billing['company'] ?? '');
            WC()->customer->set_billing_address_1($billing['address_1'] ?? '');
            WC()->customer->set_billing_address_2($billing['address_2'] ?? '');
            WC()->customer->set_billing_city($billing['city'] ?? '');
            WC()->customer->set_billing_postcode($billing['postcode'] ?? '');
            WC()->customer->set_billing_country($billing['country'] ?? 'DE');
            WC()->customer->set_billing_state($billing['state'] ?? '');
        }

        WC()->customer->save();
        $this->log_step('└─ Customer Object aktualisiert und gespeichert', 'distribution');
    }

    /**
     * Public method to process wallet payment addresses
     * 
     * @param WC_Order $order WooCommerce order
     * @param array $payment_data Payment method data
     */
    public function process_wallet_payment_addresses($order, $payment_data = null) {
        return $this->orchestrate_addresses_for_order($order, $payment_data);
    }

    /**
     * Finalize wallet addresses after express payment completion
     * 
     * @param int $order_id Order ID
     * @param array $payment_data Payment data
     */
    public function finalize_wallet_addresses($order_id, $payment_data = null) {
        $order = wc_get_order($order_id);
        if (!$order) {
            $this->log_step('ERROR: Could not load order #' . $order_id . ' for finalization', 'error');
            return false;
        }

        return $this->orchestrate_addresses_for_order($order, $payment_data);
    }

    /**
     * Validation method to ensure address consistency
     * 
     * @param array $addresses Address data to validate
     * @return bool Valid status
     */
    private function validate_addresses($addresses) {
        if (empty($addresses)) {
            return false;
        }

        // Basic validation: required fields
        $required_fields = ['address_1', 'city', 'postcode', 'country'];
        
        foreach (['shipping', 'billing'] as $type) {
            if (empty($addresses[$type])) {
                continue;
            }

            foreach ($required_fields as $field) {
                if (empty($addresses[$type][$field])) {
                    $this->log_step("Validation failed: Missing $field in $type address", 'warning');
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Comprehensive logging method with context and types
     * 
     * @param string $message Log message
     * @param string $type Log type (phase|collection|decision|distribution|info|warning|error|success)
     */
    private function log_step($message, $type = 'info') {
        $prefix = self::LOG_PREFIX;
        $context_suffix = $this->context ? ' [' . $this->context . ']' : '';
        
        // Console log for frontend - AJAX-Blockade entfernt für Debug-Zwecke
if (!is_admin()) {
    $js_message = esc_js($prefix . ' ' . $message . $context_suffix);
    echo "<script>console.log('{$js_message}');</script>";
}
    
        // Backend error log
        $log_message = $prefix . ' ' . $message . $context_suffix;
        error_log($log_message);
    
        // Store in instance for debugging (optional)
        if (!isset($this->debug_logs)) {
            $this->debug_logs = [];
        }
        $this->debug_logs[] = [
            'timestamp' => current_time('mysql'),
            'type' => $type,
            'message' => $message,
            'context' => $this->context
        ];
    }

    /**
     * Get debug logs for development/testing
     * 
     * @return array Debug logs
     */
    public function get_debug_logs() {
        return $this->debug_logs ?? [];
    }

    /**
     * Reset orchestrator state (useful for testing)
     */
    public function reset_state() {
        $this->collected_addresses = [];
        $this->final_addresses = [];
        $this->context = '';
        $this->debug_logs = [];
    }
    
    /**
     * Initialize AJAX handlers for frontend communication
     */
    private function init_ajax_handlers() {
        // Status and debug endpoints
        add_action('wp_ajax_yprint_orchestrator_status', array($this, 'ajax_get_status'));
        add_action('wp_ajax_nopriv_yprint_orchestrator_status', array($this, 'ajax_get_status'));
        
        // Session state debugging
        add_action('wp_ajax_yprint_orchestrator_debug', array($this, 'ajax_debug_state'));
        add_action('wp_ajax_nopriv_yprint_orchestrator_debug', array($this, 'ajax_debug_state'));
        
        // Address collection testing
        add_action('wp_ajax_yprint_orchestrator_collect', array($this, 'ajax_collect_addresses'));
        add_action('wp_ajax_nopriv_yprint_orchestrator_collect', array($this, 'ajax_collect_addresses'));
    }
    
    /**
     * AJAX: Get orchestrator status and configuration
     */
    public function ajax_get_status() {
        // Basic security check
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'yprint_checkout_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $session_available = WC()->session !== null;
        $user_id = get_current_user_id();
        
        wp_send_json_success([
            'orchestrator_active' => true,
            'session_available' => $session_available,
            'user_logged_in' => $user_id > 0,
            'user_id' => $user_id,
            'current_context' => $this->context,
            'debug_logs_count' => count($this->debug_logs ?? []),
            'priority_hierarchy' => self::PRIORITY_HIERARCHY,
            'timestamp' => current_time('mysql')
        ]);
    }
    
    /**
     * AJAX: Debug current orchestrator state and session data
     */
    public function ajax_debug_state() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'yprint_checkout_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        if (!WC()->session) {
            wp_send_json_error(['message' => 'WooCommerce session not available']);
            return;
        }
        
        try {
            // Collect session data for debugging
            $session_data = [
                'customer_data' => WC()->session->get('customer', []),
                'yprint_selected_address' => WC()->session->get('yprint_selected_address'),
                'yprint_billing_address' => WC()->session->get('yprint_billing_address'),
                'yprint_billing_different' => WC()->session->get('yprint_billing_address_different', false),
                'yprint_apple_pay_address' => WC()->session->get('yprint_apple_pay_address')
            ];
            
            wp_send_json_success([
                'session_data' => $session_data,
                'collected_addresses' => $this->collected_addresses,
                'final_addresses' => $this->final_addresses,
                'debug_logs' => $this->get_debug_logs(),
                'context' => $this->context,
                'timestamp' => current_time('mysql')
            ]);
            
        } catch (Exception $e) {
            wp_send_json_error(['message' => 'Debug state access failed: ' . $e->getMessage()]);
        }
    }
    
    /**
     * AJAX: Test address collection from available sources
     */
    public function ajax_collect_addresses() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'yprint_checkout_nonce')) {
            wp_send_json_error(['message' => 'Security check failed']);
            return;
        }
        
        $test_context = sanitize_text_field($_POST['context'] ?? 'frontend_test');
        $this->context = $test_context;
        
        try {
            // Reset state for clean test
            $this->reset_state();
            $this->context = $test_context;
            
            // Use existing collection method with dummy order for testing
            $dummy_order = wc_create_order();
            $this->collect_addresses($dummy_order, null);
            
            // Clean up dummy order
            $dummy_order->delete(true);
            
            $this->log_step("AJAX address collection test completed", 'success');
            
            wp_send_json_success([
                'collected_addresses' => $this->collected_addresses,
                'collection_successful' => !empty($this->collected_addresses),
                'sources_found' => array_keys($this->collected_addresses),
                'debug_logs' => $this->get_debug_logs(),
                'timestamp' => current_time('mysql')
            ]);
            
        } catch (Exception $e) {
            $this->log_step("AJAX collection test failed: " . $e->getMessage(), 'error');
            wp_send_json_error([
                'message' => $e->getMessage(),
                'debug_logs' => $this->get_debug_logs()
            ]);
        }
    }
}