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

    private function __construct() {
        // Initialize hooks for ALL payment methods (Universal System)
        $this->init_all_payment_hooks();
        
        // Initialize AJAX handlers for frontend communication
        $this->init_ajax_handlers();
        
        // Initialize payment method type detection
        $this->init_payment_method_detection();
    }
    
    /**
     * Initialize payment method detection capabilities
     */
    private function init_payment_method_detection() {
        // Add filter for payment method type detection
        add_filter('yprint_detected_payment_method_type', [$this, 'filter_payment_method_type'], 10, 2);
        
        error_log('🎯 AddressOrchestrator: Payment method detection initialized');
    }

    /**
 * Initialize hooks for wallet payment integration (pilot)
 */
private function init_all_payment_hooks() {
    // === UNIVERSAL PAYMENT HOOKS (ALLE STRIPE ZAHLUNGEN) ===
    
    // 1. Hook in Stripe Payment Gateway für alle Payment-Methoden
    add_action('yprint_stripe_payment_processing', [$this, 'process_stripe_payment_addresses'], 5, 3);
    
    // 2. Hook für Express Payment processing (Apple Pay, Google Pay)
    add_action('yprint_express_payment_complete', [$this, 'finalize_wallet_addresses'], 1, 2);
    
    // 3. Hook für alle WooCommerce Order Creation Events
    add_action('woocommerce_checkout_order_processed', [$this, 'orchestrate_addresses_for_order'], 5, 2);
    
    // 4. Hook für Payment Complete (alle Zahlungsarten)
    add_action('woocommerce_payment_complete', [$this, 'orchestrate_addresses_for_order'], 1, 1);
    
    // 5. Spezifischer Hook für Stripe Payment Gateway Integration
    add_action('woocommerce_api_yprint_stripe', [$this, 'handle_stripe_webhook_addresses'], 10, 1);
    
    // 6. SEPA und Card-spezifische Hooks
    add_action('yprint_sepa_payment_processing', [$this, 'process_sepa_payment_addresses'], 5, 2);
    add_action('yprint_card_payment_processing', [$this, 'process_card_payment_addresses'], 5, 2);
    
    error_log('🎯 AddressOrchestrator: Universal payment hooks initialisiert (ALL STRIPE PAYMENTS)');
}

/**
 * Process addresses for all Stripe payment methods (Universal Handler)
 * 
 * @param WC_Order $order WooCommerce order object
 * @param string $payment_method_type Type of payment (card, sepa_debit, etc.)
 * @param array $payment_data Payment method data from Stripe
 */
public function process_stripe_payment_addresses($order, $payment_method_type, $payment_data = null) {
    $this->context = 'Stripe ' . strtoupper($payment_method_type) . ' Payment - Order #' . $order->get_id();
    $this->log_step('=== UNIVERSAL STRIPE PAYMENT PROCESSING ===', 'info');
    $this->log_step('Payment Method Type: ' . $payment_method_type, 'info');
    
    // Sammle Payment-Method-spezifische Daten
    $enhanced_payment_data = $this->enhance_payment_data($payment_data, $payment_method_type);
    
    // Orchestriere Adressen für alle Stripe-Zahlungen
    return $this->orchestrate_addresses_for_order($order, $enhanced_payment_data);
}

/**
 * Process SEPA-specific address handling
 */
public function process_sepa_payment_addresses($order, $payment_data) {
    $this->log_step('SEPA Payment Address Processing', 'info');
    
    // SEPA benötigt oft Billing = Shipping wegen Mandat-Anforderungen
    if (empty($payment_data['billing']) && !empty($payment_data['shipping'])) {
        $payment_data['billing'] = $payment_data['shipping'];
        $this->log_step('SEPA: Copied shipping to billing for mandate compliance', 'info');
    }
    
    return $this->process_stripe_payment_addresses($order, 'sepa_debit', $payment_data);
}

/**
 * Process Card-specific address handling
 */
public function process_card_payment_addresses($order, $payment_data) {
    $this->log_step('Card Payment Address Processing', 'info');
    
    // Check für Wallet-Karten (Apple Pay, Google Pay als Karte verarbeitet)
    if (isset($payment_data['card']['wallet'])) {
        $wallet_type = $payment_data['card']['wallet']['type'];
        $this->log_step('Card is Wallet Payment: ' . $wallet_type, 'info');
        
        // Route to wallet handler if needed
        return $this->finalize_wallet_addresses($order, $payment_data);
    }
    
    return $this->process_stripe_payment_addresses($order, 'card', $payment_data);
}

/**
 * Enhance payment data with type-specific information
 */
private function enhance_payment_data($payment_data, $payment_method_type) {
    if (!is_array($payment_data)) {
        $payment_data = [];
    }
    
    // Add metadata für bessere Traceability
    $payment_data['orchestrator_metadata'] = [
        'payment_method_type' => $payment_method_type,
        'processed_at' => current_time('mysql'),
        'source' => 'stripe_payment_gateway',
        'orchestrator_version' => '2.0.0'
    ];
    
    return $payment_data;
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
            
            // PHASE 4: PROTECTION (Neu)
            $this->apply_protected_order_update($order);
        
            $this->log_step('=== ORCHESTRATION COMPLETE (WITH PROTECTION) ===', 'success');
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
 * Collect addresses from YPrint manual selection (highest priority).
 * This method retrieves addresses manually selected by the user via the YPrint integration
 * from the WooCommerce session and stores them with the highest priority.
 */
private function collect_manual_addresses() {
    $this->log_step('Collecting YPrint Manual Addresses...', 'collection');

    // Ensure WooCommerce session is available.
    if (!WC()->session) {
        $this->log_step('└─ WooCommerce Session not available. Cannot collect manual addresses.', 'collection');
        return;
    }

    // Retrieve YPrint specific address data from the session.
    $selected_address = WC()->session->get('yprint_selected_address', []);
    $billing_address = WC()->session->get('yprint_billing_address', []);
    $billing_different = WC()->session->get('yprint_billing_address_different', false);

    $this->log_step('└─ Session data read:', 'collection');
    $this->log_step('    ├─ Selected Address: ' . (!empty($selected_address) ? 'PRESENT' : 'EMPTY'), 'collection');
    $this->log_step('    ├─ Billing Different: ' . ($billing_different ? 'YES' : 'NO'), 'collection');
    $this->log_step('    └─ Billing Address: ' . (!empty($billing_address) ? 'PRESENT' : 'EMPTY'), 'collection');

    // CRITICAL: Validate if manual selection data is genuinely available and usable.
    // A selected address must be present and contain at least the first address line.
    $has_manual_selection = !empty($selected_address) && !empty($selected_address['address_1']);

    if ($has_manual_selection) {
        $this->log_step('✅ MANUAL SELECTION DETECTED - Priority 1 (HIGHEST)', 'collection');

        // Normalize the selected (shipping) address to the orchestrator's internal format.
        $normalized_shipping_address = $this->normalize_yprint_address($selected_address);
        $normalized_billing_address = null;

        // If a different billing address was indicated and is available, normalize it.
        if ($billing_different && !empty($billing_address)) {
            $normalized_billing_address = $this->normalize_yprint_address($billing_address);
            $this->log_step('    └─ Separate billing address normalized.', 'collection');
        } else {
            // If billing is not different or not provided, assume billing is the same as shipping.
            $normalized_billing_address = $normalized_shipping_address;
            $this->log_step('    └─ Billing address set to be the same as shipping address.', 'collection');
        }

        // Store the collected manual addresses with the highest priority.
        $this->collected_addresses['manual'] = [
            'source' => 'manual',
            'priority' => self::PRIORITY_HIERARCHY['manual'], // Priority 1 (highest)
            'shipping' => $normalized_shipping_address,
            'billing' => $normalized_billing_address,
            'billing_different' => $billing_different,
            'metadata' => [
                'session_timestamp' => WC()->session->get('timestamp', 'unknown'),
                'selected_address_id' => $selected_address['id'] ?? 'unknown',
                'billing_address_id' => $billing_address['id'] ?? null,
                'collected_at' => current_time('mysql') // Record when addresses were collected.
            ]
        ];

        $this->log_step('🎯 MANUAL ADDRESSES COLLECTED WITH HIGHEST PRIORITY:', 'collection');
        $this->log_step('    ├─ Source: MANUAL (Priority 1)', 'collection');
        $this->log_step('    ├─ Shipping: ' . ($normalized_shipping_address['address_1'] ?? 'empty'), 'collection');
        $this->log_step('    ├─ Billing Different: ' . ($billing_different ? 'YES' : 'NO'), 'collection');
        $this->log_step('    └─ Billing: ' . (($normalized_billing_address ?? $normalized_shipping_address)['address_1'] ?? 'empty'), 'collection');

    } else {
        $this->log_step('ℹ️ No manual selection found - user did not select addresses manually.', 'collection');
    }
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

    private function apply_hierarchy() {
        $this->log_step('=== ENTSCHEIDUNG START (SEPARATE SHIPPING/BILLING) ===', 'phase');
        
        if (empty($this->collected_addresses)) {
            $this->log_step('Keine Adressen zum Priorisieren gefunden', 'warning');
            return;
        }
    
        // Sort addresses by priority (1 = highest priority)
        $sorted_sources = $this->collected_addresses;
        uasort($sorted_sources, function($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        
        // CRITICAL: Enforce manual selection supremacy
        $has_manual_selection = !empty($this->collected_addresses['manual']);
        if ($has_manual_selection) {
            $this->log_step('🚨 MANUAL SELECTION SUPREMACY ENFORCED - Alle anderen Quellen haben niedrigere Priorität', 'priority');
            
            // Temporarily demote wallet priority if manual exists
            if (isset($sorted_sources['wallet'])) {
                $sorted_sources['wallet']['priority'] = 10; // Much lower than manual
                $this->log_step('└─ Wallet Priority herabgestuft: 2 → 10 (Manual überschreibt)', 'priority');
            }
            
            // Re-sort after priority adjustment
            uasort($sorted_sources, function($a, $b) {
                return $a['priority'] <=> $b['priority'];
            });
        }
    
        $this->log_step('🎯 SEPARATE HIERARCHIE-LOGIK:', 'decision');
        $this->log_step('Verfügbare Quellen nach Priorität:', 'decision');
        foreach ($sorted_sources as $source => $data) {
            $shipping_available = !empty($data['shipping']['address_1']) ? '✅' : '❌';
            $billing_available = !empty($data['billing']['address_1']) ? '✅' : '❌';
            $this->log_step("└─ {$data['priority']}.{$source}: Shipping {$shipping_available}, Billing {$billing_available}", 'decision');
        }
    
        // **SEPARATE ENTSCHEIDUNG FÜR SHIPPING UND BILLING**
        $final_shipping = null;
        $final_billing = null;
        $shipping_source = 'none';
        $billing_source = 'none';
    
        // **SYMMETRIC PRIORITY LOGIC - Shipping und Billing gleich behandeln**
$final_shipping = null;
$final_billing = null;
$shipping_source = 'none';
$billing_source = 'none';

// **SHIPPING ADDRESS HIERARCHIE (mit Manual Supremacy)**
$this->log_step('🚚 SHIPPING ADDRESS AUSWAHL (SYMMETRIC LOGIC):', 'decision');
foreach ($sorted_sources as $source => $data) {
    if (!empty($data['shipping']['address_1'])) {
        $final_shipping = $data['shipping'];
        $shipping_source = $source;
        $this->log_step("└─ ✅ SHIPPING gewählt aus: {$source} (Priorität {$data['priority']})", 'success');
        $this->log_step("    └─ Adresse: {$final_shipping['address_1']}, {$final_shipping['city']}", 'decision');
        
        // CRITICAL: Manual selection validation
        if ($source === 'manual') {
            $this->log_step("    └─ 🛡️ MANUAL SELECTION SCHUTZ: Shipping gesichert gegen Überschreibung", 'priority');
        }
        break; // Erste verfügbare Shipping-Adresse nach Priorität
    } else {
        $this->log_step("└─ ❌ SHIPPING nicht verfügbar in: {$source}", 'decision');
    }
}

// **BILLING ADDRESS HIERARCHIE (identische Logik)**
$this->log_step('💳 BILLING ADDRESS AUSWAHL (SYMMETRIC LOGIC):', 'decision');
foreach ($sorted_sources as $source => $data) {
    if (!empty($data['billing']['address_1'])) {
        $final_billing = $data['billing'];
        $billing_source = $source;
        $this->log_step("└─ ✅ BILLING gewählt aus: {$source} (Priorität {$data['priority']})", 'success');
        $this->log_step("    └─ Adresse: {$final_billing['address_1']}, {$final_billing['city']}", 'decision');
        
        // CRITICAL: Manual selection validation (identisch zu Shipping)
        if ($source === 'manual') {
            $this->log_step("    └─ 🛡️ MANUAL SELECTION SCHUTZ: Billing gesichert gegen Überschreibung", 'priority');
        }
        break; // Erste verfügbare Billing-Adresse nach Priorität
    } else {
        $this->log_step("└─ ❌ BILLING nicht verfügbar in: {$source}", 'decision');
    }
}
    
        // **FALLBACK: Wenn keine Shipping-Adresse gefunden, verwende Billing**
        if (empty($final_shipping) && !empty($final_billing)) {
            $final_shipping = $final_billing;
            $shipping_source = $billing_source . '_fallback';
            $this->log_step('🔄 FALLBACK: Shipping = Billing (keine separate Shipping gefunden)', 'warning');
        }
    
        // **FALLBACK: Wenn keine Billing-Adresse gefunden, verwende Shipping**
        if (empty($final_billing) && !empty($final_shipping)) {
            $final_billing = $final_shipping;
            $billing_source = $shipping_source . '_fallback';
            $this->log_step('🔄 FALLBACK: Billing = Shipping (keine separate Billing gefunden)', 'warning');
        }
    
        // **BESTIMME BILLING_DIFFERENT FLAG**
        $billing_different = false;
        if (!empty($final_shipping) && !empty($final_billing)) {
            $billing_different = ($final_shipping['address_1'] !== $final_billing['address_1']);
        }
    
        // **FINALE ADRESSEN SETZEN**
        $this->final_addresses = [
            'source' => 'mixed', // Kann verschiedene Quellen haben
            'priority' => 'mixed',
            'shipping' => $final_shipping,
            'billing' => $final_billing,
            'billing_different' => $billing_different,
            'metadata' => [
                'shipping_source' => $shipping_source,
                'billing_source' => $billing_source,
                'decision_logic' => 'separate_hierarchy',
                'alternatives_available' => array_keys($sorted_sources),
                'decision_timestamp' => current_time('mysql')
            ]
        ];
    
        // **ERFOLGS-LOGGING**
        $this->log_step('🎯 FINALE ENTSCHEIDUNG (SEPARATE LOGIK):', 'success');
        $this->log_step("└─ 🚚 SHIPPING: {$shipping_source} → " . ($final_shipping['address_1'] ?? 'FEHLT') . ', ' . ($final_shipping['city'] ?? 'FEHLT'), 'success');
        $this->log_step("└─ 💳 BILLING: {$billing_source} → " . ($final_billing['address_1'] ?? 'FEHLT') . ', ' . ($final_billing['city'] ?? 'FEHLT'), 'success');
        $this->log_step("└─ ⚖️ BILLING_DIFFERENT: " . ($billing_different ? 'YES' : 'NO'), 'success');
        
        $this->log_step('=== ENTSCHEIDUNG END (SEPARATE LOGIK) ===', 'phase');
    }

    /**
     * PHASE 3: DISTRIBUTION - Erweitert um alle Zielsysteme
     * 
     * Distribute final addresses to all target systems
     * 
     * @param WC_Order $order WooCommerce order object
     */
    private function distribute_addresses($order) {
        $this->log_step('=== VERTEILUNG START (ERWEITERT) ===', 'phase');

        if (empty($this->final_addresses)) {
            $this->log_step('Keine finalen Adressen zum Verteilen', 'warning');
            return;
        }

        // Bestehende Verteilungen
        $this->distribute_to_woocommerce_order($order);
        $this->distribute_to_session();
        $this->distribute_to_customer();

        // NEUE VERTEILUNGEN für konsistente Adressdaten
$this->distribute_to_stripe_metadata($order);
$this->distribute_to_email_templates($order);
$this->distribute_to_consistency_monitor($order);
$this->validate_distribution_consistency($order);

        $this->log_step('=== VERTEILUNG END (ERWEITERT) ===', 'phase');
    }

    /**
 * Protected order update that prevents external overrides
 * 
 * @param WC_Order $order WooCommerce order object
 */
private function apply_protected_order_update($order) {
    $this->log_step('🛡️ PROTECTED ORDER UPDATE - Verhindert externe Überschreibungen...', 'distribution');
    
    $shipping = $this->final_addresses['shipping'];
    $billing = $this->final_addresses['billing'];
    
    // Apply addresses with high-priority hook to prevent overrides
    add_action('woocommerce_checkout_update_order_meta', function($order_id) use ($shipping, $billing) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        // Force-set shipping address
        if (!empty($shipping)) {
            $order->set_shipping_first_name($shipping['first_name'] ?? '');
            $order->set_shipping_last_name($shipping['last_name'] ?? '');
            $order->set_shipping_company($shipping['company'] ?? '');
            $order->set_shipping_address_1($shipping['address_1'] ?? '');
            $order->set_shipping_address_2($shipping['address_2'] ?? '');
            $order->set_shipping_city($shipping['city'] ?? '');
            $order->set_shipping_state($shipping['state'] ?? '');
            $order->set_shipping_postcode($shipping['postcode'] ?? '');
            $order->set_shipping_country($shipping['country'] ?? '');
            $order->set_shipping_phone($shipping['phone'] ?? '');
        }
        
        // Force-set billing address  
        if (!empty($billing)) {
            $order->set_billing_first_name($billing['first_name'] ?? '');
            $order->set_billing_last_name($billing['last_name'] ?? '');
            $order->set_billing_company($billing['company'] ?? '');
            $order->set_billing_address_1($billing['address_1'] ?? '');
            $order->set_billing_address_2($billing['address_2'] ?? '');
            $order->set_billing_city($billing['city'] ?? '');
            $order->set_billing_state($billing['state'] ?? '');
            $order->set_billing_postcode($billing['postcode'] ?? '');
            $order->set_billing_country($billing['country'] ?? '');
            $order->set_billing_phone($billing['phone'] ?? '');
            $order->set_billing_email($billing['email'] ?? '');
        }
        
        // Save with high priority
        $order->save();
        
        error_log('🛡️ AddressOrchestrator: Protected update applied for Order #' . $order_id);
        
    }, 999); // Very high priority to run last
    
    $this->log_step('└─ Protected Update Hook installiert (Priority 999)', 'distribution');
}

    /**
     * Distribute addresses to Stripe metadata (for UI consistency)
     * 
     * @param WC_Order $order WooCommerce order object
     */
    private function distribute_to_stripe_metadata($order) {
        $this->log_step('Verteile an Stripe Metadata...', 'distribution');
        
        // Stripe Payment Method kann nicht geändert werden, aber wir können Order Meta setzen
        $final_shipping = $this->final_addresses['shipping'];
        $final_billing = $this->final_addresses['billing'];
        
        // Meta-Daten für Stripe Dashboard Anzeige
        $order->update_meta_data('_stripe_display_shipping_address', $final_shipping);
        $order->update_meta_data('_stripe_display_billing_address', $final_billing);
        $order->update_meta_data('_stripe_address_override_reason', 'AddressOrchestrator processed - Payment Method shows original data');
        
        $this->log_step('└─ Stripe Display Meta-Daten gesetzt', 'distribution');
    }

    /**
     * Distribute addresses to email template system
     * 
     * @param WC_Order $order WooCommerce order object
     */
    private function distribute_to_email_templates($order) {
        $this->log_step('🎯 VERTEILE AN E-MAIL TEMPLATES (KRITISCH)...', 'distribution');
        
        $shipping = $this->final_addresses['shipping'];
        $billing = $this->final_addresses['billing'];
        
        // Validiere Daten vor Verteilung
        if (empty($shipping['address_1']) || empty($billing['address_1'])) {
            $this->log_step('🔴 WARNUNG: Unvollständige Adressdaten für E-Mail Templates', 'distribution');
            $this->log_step('🔍 Shipping Address: ' . ($shipping['address_1'] ?? 'LEER'), 'distribution');
            $this->log_step('🔍 Billing Address: ' . ($billing['address_1'] ?? 'LEER'), 'distribution');
        }
        
        // Setze Meta-Daten für E-Mail Templates mit sofortiger Validierung
$order->update_meta_data('_email_template_shipping_address', $shipping);
$order->update_meta_data('_email_template_billing_address', $billing);
$order->update_meta_data('_email_template_addresses_ready', true);
$order->update_meta_data('_email_template_timestamp', current_time('mysql'));

// KRITISCH: Sofort speichern um Race Conditions zu vermeiden
$order->save();

// VALIDIERUNG: Prüfe ob Meta-Daten korrekt geschrieben wurden
$saved_shipping = $order->get_meta('_email_template_shipping_address');
$saved_billing = $order->get_meta('_email_template_billing_address');

if (empty($saved_shipping['address_1']) || empty($saved_billing['address_1'])) {
    $this->log_step('🔴 KRITISCHER FEHLER: E-Mail Meta-Daten nicht korrekt gespeichert!', 'distribution');
    
    // Retry-Mechanismus
    $order->update_meta_data('_email_template_shipping_address', $shipping);
    $order->update_meta_data('_email_template_billing_address', $billing);
    $order->save();
    
    $this->log_step('🔄 RETRY: E-Mail Meta-Daten erneut gespeichert', 'distribution');
} else {
    $this->log_step('✅ E-Mail Meta-Daten erfolgreich validiert und gespeichert', 'distribution');
}
        
        // Log finale Daten für Debugging
        $this->log_step('✅ E-Mail Shipping gesetzt: ' . ($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '') . ', ' . ($shipping['address_1'] ?? ''), 'distribution');
        $this->log_step('✅ E-Mail Billing gesetzt: ' . ($billing['first_name'] ?? '') . ' ' . ($billing['last_name'] ?? '') . ', ' . ($billing['address_1'] ?? ''), 'distribution');
        $this->log_step('✅ E-Mail Template Meta-Daten VOLLSTÄNDIG gesetzt', 'distribution');
    }

    /**
 * Distribute addresses specifically for Consistency Monitor
 * 
 * @param WC_Order $order WooCommerce order object
 */
private function distribute_to_consistency_monitor($order) {
    $this->log_step('Verteile an Consistency Monitor...', 'distribution');
    
    // Spezielle Meta-Keys für Consistency Monitor
    $order->update_meta_data('_yprint_orchestrator_final_shipping', $this->final_addresses['shipping']);
    $order->update_meta_data('_yprint_orchestrator_final_billing', $this->final_addresses['billing']);
    $order->update_meta_data('_yprint_orchestrator_source', $this->final_addresses['source']);
    $order->update_meta_data('_yprint_orchestrator_priority', $this->final_addresses['priority']);
    $order->update_meta_data('_yprint_orchestrator_processed', true);
    
    $this->log_step('└─ Consistency Monitor Meta-Daten gesetzt', 'distribution');
}

    /**
 * Validate consistency across all distribution targets - ERWEITERT für Checkout/Stripe/Email
 * 
 * @param WC_Order $order WooCommerce order object
 */
private function validate_distribution_consistency($order) {
    $this->log_step('=== ERWEITERTE KONSISTENZ-VALIDIERUNG START ===', 'validation');
    
    $shipping = $this->final_addresses['shipping'];
    $billing = $this->final_addresses['billing'];
    $order_id = $order->get_id();
    
    // 1. BESTEHENDE WOOCOMMERCE ORDER VALIDIERUNG (erweitert)
    $this->log_step('🎯 1. WOOCOMMERCE ORDER VALIDIERUNG', 'validation');
    
    $order_shipping = $order->get_shipping_address_1();
    $order_billing = $order->get_billing_address_1();
    
    if ($order_shipping !== $shipping['address_1']) {
        $this->log_step('🔴 ORDER SHIPPING INKONSISTENZ - Erwartet: ' . $shipping['address_1'] . ', Gefunden: ' . $order_shipping, 'validation');
    } else {
        $this->log_step('✅ Order Shipping konsistent: ' . $shipping['address_1'], 'validation');
    }
    
    if ($order_billing !== $billing['address_1']) {
        $this->log_step('🔴 ORDER BILLING INKONSISTENZ - Erwartet: ' . $billing['address_1'] . ', Gefunden: ' . $order_billing, 'validation');
    } else {
        $this->log_step('✅ Order Billing konsistent: ' . $billing['address_1'], 'validation');
    }
    
    // 2. BESTEHENDE SESSION VALIDIERUNG (erweitert)
    $this->log_step('🎯 2. SESSION VALIDIERUNG', 'validation');
    
    if (WC()->session) {
        $session_shipping = WC()->session->get('yprint_selected_address');
        $session_billing = WC()->session->get('yprint_billing_address');
        $session_billing_different = WC()->session->get('yprint_billing_address_different', false);
        
        if ($session_shipping && $session_shipping['address_1'] !== $shipping['address_1']) {
            $this->log_step('🔴 SESSION SHIPPING INKONSISTENZ - Erwartet: ' . $shipping['address_1'] . ', Gefunden: ' . $session_shipping['address_1'], 'validation');
        } else {
            $this->log_step('✅ Session Shipping konsistent', 'validation');
        }
        
        if ($session_billing_different && $session_billing && $session_billing['address_1'] !== $billing['address_1']) {
            $this->log_step('🔴 SESSION BILLING INKONSISTENZ - Erwartet: ' . $billing['address_1'] . ', Gefunden: ' . $session_billing['address_1'], 'validation');
        } else {
            $this->log_step('✅ Session Billing konsistent', 'validation');
        }
        
        // Store session state for confirmation display validation
        $order->update_meta_data('_yprint_session_state_shipping', $session_shipping);
        $order->update_meta_data('_yprint_session_state_billing', $session_billing);
        $order->update_meta_data('_yprint_session_state_billing_different', $session_billing_different);
    }
    
    // 3. NEUE: CHECKOUT STEP CONFIRMATION VALIDIERUNG
    $this->log_step('🎯 3. CHECKOUT CONFIRMATION STEP VALIDIERUNG', 'validation');
    
    // Meta-Daten für Confirmation Step setzen
    $order->update_meta_data('_yprint_confirmation_shipping_expected', $shipping);
    $order->update_meta_data('_yprint_confirmation_billing_expected', $billing);
    $order->update_meta_data('_yprint_confirmation_validation_timestamp', current_time('mysql'));
    
    $this->log_step('✅ Confirmation Step Meta-Daten gesetzt für Validierung', 'validation');
    
    // 4. NEUE: STRIPE METADATA KONSISTENZ
    $this->log_step('🎯 4. STRIPE METADATA VALIDIERUNG', 'validation');
    
    // Prüfe ob Stripe Meta-Daten korrekt gesetzt wurden
    $stripe_shipping_meta = $order->get_meta('_stripe_display_shipping_address');
    $stripe_billing_meta = $order->get_meta('_stripe_display_billing_address');
    
    if (!empty($stripe_shipping_meta) && $stripe_shipping_meta['address_1'] !== $shipping['address_1']) {
        $this->log_step('🔴 STRIPE SHIPPING META INKONSISTENZ - Erwartet: ' . $shipping['address_1'] . ', Gefunden: ' . $stripe_shipping_meta['address_1'], 'validation');
    } else {
        $this->log_step('✅ Stripe Shipping Metadata konsistent', 'validation');
    }
    
    if (!empty($stripe_billing_meta) && $stripe_billing_meta['address_1'] !== $billing['address_1']) {
        $this->log_step('🔴 STRIPE BILLING META INKONSISTENZ - Erwartet: ' . $billing['address_1'] . ', Gefunden: ' . $stripe_billing_meta['address_1'], 'validation');
    } else {
        $this->log_step('✅ Stripe Billing Metadata konsistent', 'validation');
    }
    
    // 5. NEUE: E-MAIL TEMPLATE KONSISTENZ
    $this->log_step('🎯 5. E-MAIL TEMPLATE VALIDIERUNG', 'validation');
    
    // Prüfe E-Mail Template Meta-Daten
    $email_shipping_meta = $order->get_meta('_email_template_shipping_address');
    $email_billing_meta = $order->get_meta('_email_template_billing_address');
    $email_ready = $order->get_meta('_email_template_addresses_ready');
    
    if (!$email_ready) {
        $this->log_step('🔴 E-MAIL TEMPLATE NICHT BEREIT - Meta-Flag fehlt', 'validation');
    } else {
        $this->log_step('✅ E-Mail Template bereit für Versendung', 'validation');
    }
    
    if (!empty($email_shipping_meta) && $email_shipping_meta['address_1'] !== $shipping['address_1']) {
        $this->log_step('🔴 E-MAIL SHIPPING TEMPLATE INKONSISTENZ - Erwartet: ' . $shipping['address_1'] . ', Template: ' . $email_shipping_meta['address_1'], 'validation');
    } else {
        $this->log_step('✅ E-Mail Shipping Template konsistent', 'validation');
    }
    
    if (!empty($email_billing_meta) && $email_billing_meta['address_1'] !== $billing['address_1']) {
        $this->log_step('🔴 E-MAIL BILLING TEMPLATE INKONSISTENZ - Erwartet: ' . $billing['address_1'] . ', Template: ' . $email_billing_meta['address_1'], 'validation');
    } else {
        $this->log_step('✅ E-Mail Billing Template konsistent', 'validation');
    }
    
    // 6. NEUE: BESTELLBESTÄTIGUNG KONSISTENZ (WooCommerce E-Mail)
    $this->log_step('🎯 6. BESTELLBESTÄTIGUNG VALIDIERUNG', 'validation');
    
    // WooCommerce E-Mails verwenden direkt die Order-Methoden
    $wc_email_shipping = $order->get_shipping_address_1();
    $wc_email_billing = $order->get_billing_address_1();
    
    if ($wc_email_shipping !== $shipping['address_1']) {
        $this->log_step('🔴 WOOCOMMERCE E-MAIL SHIPPING INKONSISTENZ - Erwartet: ' . $shipping['address_1'] . ', E-Mail: ' . $wc_email_shipping, 'validation');
    } else {
        $this->log_step('✅ WooCommerce E-Mail Shipping konsistent', 'validation');
    }
    
    if ($wc_email_billing !== $billing['address_1']) {
        $this->log_step('🔴 WOOCOMMERCE E-MAIL BILLING INKONSISTENZ - Erwartet: ' . $billing['address_1'] . ', E-Mail: ' . $wc_email_billing, 'validation');
    } else {
        $this->log_step('✅ WooCommerce E-Mail Billing konsistent', 'validation');
    }
    
    // 7. FINALE KONSISTENZ-BEWERTUNG
    $this->log_step('🎯 7. FINALE KONSISTENZ-BEWERTUNG', 'validation');
    
    // Setze Konsistenz-Status Meta-Daten für Admin-Monitoring
    $order->update_meta_data('_yprint_consistency_check_timestamp', current_time('mysql'));
    $order->update_meta_data('_yprint_consistency_validation_extended', true);
    
    $this->log_step('=== ERWEITERTE KONSISTENZ-VALIDIERUNG END ===', 'validation');
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