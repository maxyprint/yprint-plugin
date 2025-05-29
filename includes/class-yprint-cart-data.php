<?php
/**
 * Cart Data Manager - Zentrale Verwaltung der Warenkorbdaten
 * 
 * Handles centralized cart data management for YPrint checkout system
 *
 * @package YPrint_Plugin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Cart Data Manager Class
 */
class YPrint_Cart_Data {

    /**
     * Singleton instance
     *
     * @var YPrint_Cart_Data
     */
    protected static $instance = null;

    /**
     * Cache für Warenkorbdaten
     *
     * @var array
     */
    private $cache = array();

    /**
     * Cache-Lebensdauer in Sekunden
     *
     * @var int
     */
    private $cache_lifetime = 300; // 5 Minuten

    /**
     * Get singleton instance
     *
     * @return YPrint_Cart_Data
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Private constructor to prevent direct instantiation
        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // WooCommerce Cart-Events überwachen
        add_action('woocommerce_cart_updated', array($this, 'clear_cache'));
        add_action('woocommerce_add_to_cart', array($this, 'clear_cache'));
        add_action('woocommerce_cart_item_removed', array($this, 'clear_cache'));
        add_action('woocommerce_cart_item_restored', array($this, 'clear_cache'));
        add_action('woocommerce_applied_coupon', array($this, 'clear_cache'));
        add_action('woocommerce_removed_coupon', array($this, 'clear_cache'));
    }

    /**
     * Get complete checkout context
     *
     * @param string $format Optional. Format type: 'full', 'summary', 'express_payment'
     * @return array Checkout context data
     */
    public function get_checkout_context($format = 'full') {
        $cache_key = 'checkout_context_' . $format;
        
        // Prüfe Cache
        if ($this->is_cached($cache_key)) {
            return $this->get_cache($cache_key);
        }

        try {
            // Basis-Daten sammeln
            $cart_items = $this->get_cart_items();
            $cart_totals = $this->get_cart_totals();

            // Format-spezifische Daten
            switch ($format) {
                case 'summary':
                    $context = array(
                        'cart_items' => $cart_items,
                        'cart_totals' => $cart_totals,
                        'item_count' => $this->get_cart_item_count(),
                    );
                    break;

                case 'express_payment':
                    $context = $this->build_express_payment_context($cart_items, $cart_totals);
                    break;

                case 'full':
                default:
                    $context = array(
                        'cart_items' => $cart_items,
                        'cart_totals' => $cart_totals,
                        'shipping_info' => $this->get_shipping_info(),
                        'customer_info' => $this->get_customer_info(),
                        'express_payment' => $this->build_express_payment_context($cart_items, $cart_totals),
                        'metadata' => array(
                            'timestamp' => time(),
                            'currency' => get_woocommerce_currency(),
                            'currency_symbol' => get_woocommerce_currency_symbol(),
                            'needs_shipping' => WC()->cart->needs_shipping(),
                            'cart_hash' => WC()->cart->get_cart_hash(),
                        ),
                    );
                    break;
            }

            // In Cache speichern
            $this->set_cache($cache_key, $context);
            
            return $context;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error in get_checkout_context: ' . $e->getMessage());
            return $this->get_empty_context($format);
        }
    }

    /**
     * Get formatted cart items
     *
     * @return array Formatted cart items
     */
    public function get_cart_items() {
        if (!$this->is_woocommerce_available()) {
            return array();
        }

        $cache_key = 'cart_items';
        if ($this->is_cached($cache_key)) {
            return $this->get_cache($cache_key);
        }

        try {
            $cart_items = array();
            
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                $validated_item = $this->validate_and_format_cart_item($cart_item, $cart_item_key);
                if ($validated_item) {
                    $cart_items[] = $validated_item;
                }
            }

            $this->set_cache($cache_key, $cart_items);
            return $cart_items;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error in get_cart_items: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Get cart totals
     *
     * @return array Cart totals
     */
    public function get_cart_totals() {
        if (!$this->is_woocommerce_available()) {
            return $this->get_empty_totals();
        }

        $cache_key = 'cart_totals';
        if ($this->is_cached($cache_key)) {
            return $this->get_cache($cache_key);
        }

        try {
            // Stelle sicher, dass Totals aktuell sind
            WC()->cart->calculate_totals();

            $totals = array(
                'subtotal' => (float) WC()->cart->get_subtotal(),
                'subtotal_tax' => (float) WC()->cart->get_subtotal_tax(),
                'shipping' => (float) WC()->cart->get_shipping_total(),
                'shipping_tax' => (float) WC()->cart->get_shipping_tax(),
                'discount' => (float) WC()->cart->get_discount_total(),
                'discount_tax' => (float) WC()->cart->get_discount_tax(),
                'fees' => (float) WC()->cart->get_fee_total(),
                'fees_tax' => (float) WC()->cart->get_fee_tax(),
                'vat' => (float) WC()->cart->get_total_tax(),
                'total' => (float) WC()->cart->get_total('edit'),
                'formatted' => array(
                    'subtotal' => wc_price(WC()->cart->get_subtotal()),
                    'shipping' => wc_price(WC()->cart->get_shipping_total()),
                    'discount' => wc_price(WC()->cart->get_discount_total()),
                    'vat' => wc_price(WC()->cart->get_total_tax()),
                    'total' => wc_price(WC()->cart->get_total('edit')),
                ),
            );

            $this->set_cache($cache_key, $totals);
            return $totals;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error in get_cart_totals: ' . $e->getMessage());
            return $this->get_empty_totals();
        }
    }

    /**
     * Validate and format a single cart item
     *
     * @param array $cart_item WooCommerce cart item
     * @param string $cart_item_key Cart item key
     * @return array|false Formatted cart item or false if invalid
     */
    private function validate_and_format_cart_item($cart_item, $cart_item_key) {
        try {
            // Grundlegende Validierung
            if (!$this->validate_cart_item($cart_item)) {
                error_log('YPrint Cart Data: Invalid cart item structure for key: ' . $cart_item_key);
                return false;
            }

            $product = $cart_item['data'];
            $product_id = $product->get_id();
            $quantity = $cart_item['quantity'];

            // Basis-Daten
            $formatted_item = array(
                'key' => $cart_item_key,
                'product_id' => $product_id,
                'variation_id' => $cart_item['variation_id'] ?? 0,
                'name' => $product->get_name(),
                'price' => (float) $product->get_price(),
                'quantity' => (int) $quantity,
                'line_total' => (float) $cart_item['line_total'],
                'line_subtotal' => (float) $cart_item['line_subtotal'],
                'image' => $this->get_product_image_url($product),
                'permalink' => $product->get_permalink(),
                'sku' => $product->get_sku(),
                'weight' => $product->get_weight(),
                'dimensions' => array(
                    'length' => $product->get_length(),
                    'width' => $product->get_width(),
                    'height' => $product->get_height(),
                ),
                'is_design_product' => false,
                'design_details' => array(),
            );

            // Design-Produkt Erkennung und Verarbeitung
            if (isset($cart_item['print_design'])) {
                $formatted_item = $this->enhance_with_design_data($formatted_item, $cart_item);
            }

            // Variation-spezifische Daten
            if ($cart_item['variation_id']) {
                $formatted_item = $this->enhance_with_variation_data($formatted_item, $cart_item);
            }

            return $formatted_item;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error formatting cart item: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Validate cart item structure
     *
     * @param array $cart_item Cart item to validate
     * @return bool True if valid, false otherwise
     */
    private function validate_cart_item($cart_item) {
        // Grundlegende Struktur prüfen
        if (!is_array($cart_item)) {
            return false;
        }

        // Erforderliche Felder prüfen
        $required_fields = array('data', 'quantity', 'line_total', 'line_subtotal');
        foreach ($required_fields as $field) {
            if (!isset($cart_item[$field])) {
                return false;
            }
        }

        // Produkt-Objekt validieren
        if (!isset($cart_item['data']) || !$cart_item['data'] instanceof WC_Product) {
            return false;
        }

        // Menge validieren
        if (!is_numeric($cart_item['quantity']) || $cart_item['quantity'] <= 0) {
            return false;
        }

        // Preise validieren
        if (!is_numeric($cart_item['line_total']) || !is_numeric($cart_item['line_subtotal'])) {
            return false;
        }

        return true;
    }

    /**
     * Enhance cart item with design product data
     *
     * @param array $formatted_item Base formatted item
     * @param array $cart_item Original cart item
     * @return array Enhanced formatted item
     */
    private function enhance_with_design_data($formatted_item, $cart_item) {
        try {
            $design_data = $cart_item['print_design'];
            
            $formatted_item['is_design_product'] = true;
            $formatted_item['design_id'] = $design_data['design_id'] ?? 0;
            $formatted_item['name'] = $design_data['name'] ?? $formatted_item['name'];
            
            // Design-Details für die Anzeige
            $details = array();
            
            if (!empty($design_data['variation_name'])) {
                $details[] = $design_data['variation_name'];
            }
            
            if (!empty($design_data['size_name'])) {
                $details[] = __('Größe:', 'yprint-plugin') . ' ' . $design_data['size_name'];
            }
            
            if (!empty($design_data['design_width_cm']) && !empty($design_data['design_height_cm'])) {
                $details[] = sprintf(
                    __('Druckmaße: %s x %s cm', 'yprint-plugin'),
                    number_format($design_data['design_width_cm'], 1),
                    number_format($design_data['design_height_cm'], 1)
                );
            }
            
            $formatted_item['design_details'] = $details;
            
            // Preview-Bild überschreiben falls vorhanden
            if (!empty($design_data['preview_url'])) {
                $formatted_item['image'] = $design_data['preview_url'];
            }
            
            // Preis überschreiben falls kalkulierter Preis vorhanden
            if (!empty($design_data['calculated_price'])) {
                $formatted_item['price'] = (float) $design_data['calculated_price'];
            }

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error enhancing design data: ' . $e->getMessage());
        }

        return $formatted_item;
    }

    /**
     * Enhance cart item with variation data
     *
     * @param array $formatted_item Base formatted item
     * @param array $cart_item Original cart item
     * @return array Enhanced formatted item
     */
    private function enhance_with_variation_data($formatted_item, $cart_item) {
        try {
            if (!empty($cart_item['variation']) && is_array($cart_item['variation'])) {
                $variation_details = array();
                
                foreach ($cart_item['variation'] as $attribute => $value) {
                    if (!empty($value)) {
                        $attribute_name = str_replace('attribute_', '', $attribute);
                        $attribute_label = wc_attribute_label($attribute_name);
                        $variation_details[] = $attribute_label . ': ' . $value;
                    }
                }
                
                if (!empty($variation_details)) {
                    $formatted_item['variation_details'] = $variation_details;
                    // Zu design_details hinzufügen falls nicht schon ein Design-Produkt
                    if (!$formatted_item['is_design_product']) {
                        $formatted_item['design_details'] = array_merge(
                            $formatted_item['design_details'],
                            $variation_details
                        );
                    }
                }
            }
        } catch (Exception $e) {
            error_log('YPrint Cart Data Error enhancing variation data: ' . $e->getMessage());
        }

        return $formatted_item;
    }

    /**
     * Get product image URL
     *
     * @param WC_Product $product
     * @return string Image URL or placeholder
     */
    private function get_product_image_url($product) {
        try {
            $image_id = $product->get_image_id();
            
            if ($image_id) {
                $image_url = wp_get_attachment_image_url($image_id, 'woocommerce_thumbnail');
                if ($image_url) {
                    return $image_url;
                }
            }
            
            // Fallback zu Platzhalter
            return wc_placeholder_img_src('woocommerce_thumbnail');
            
        } catch (Exception $e) {
            error_log('YPrint Cart Data Error getting product image: ' . $e->getMessage());
            return wc_placeholder_img_src('woocommerce_thumbnail');
        }
    }

    /**
     * Build express payment context (for Stripe, Apple Pay, etc.)
     *
     * @param array $cart_items
     * @param array $cart_totals
     * @return array Express payment context
     */
    private function build_express_payment_context($cart_items, $cart_totals) {
        try {
            $currency = strtolower(get_woocommerce_currency());
            $total_amount = $this->get_stripe_amount($cart_totals['total'], $currency);
            
            // Display items for payment request
            $display_items = array();
            
            foreach ($cart_items as $item) {
                $amount = $this->get_stripe_amount($item['line_total'], $currency);
                $label = $item['name'];
                
                if ($item['quantity'] > 1) {
                    $label .= ' (x' . $item['quantity'] . ')';
                }
                
                $display_items[] = array(
                    'label' => $label,
                    'amount' => $amount,
                );
            }
            
            // Versandkosten hinzufügen falls vorhanden
            if ($cart_totals['shipping'] > 0) {
                $display_items[] = array(
                    'label' => __('Versand', 'yprint-plugin'),
                    'amount' => $this->get_stripe_amount($cart_totals['shipping'], $currency),
                );
            }
            
            // Rabatt hinzufügen falls vorhanden
            if ($cart_totals['discount'] > 0) {
                $display_items[] = array(
                    'label' => __('Rabatt', 'yprint-plugin'),
                    'amount' => -$this->get_stripe_amount($cart_totals['discount'], $currency),
                );
            }

            return array(
                'country' => substr(get_option('woocommerce_default_country'), 0, 2),
                'currency' => $currency,
                'total' => array(
                    'label' => get_bloginfo('name') . ' ' . __('Bestellung', 'yprint-plugin'),
                    'amount' => $total_amount,
                ),
                'displayItems' => $display_items,
                'requestShipping' => WC()->cart->needs_shipping(),
                'requestPayerName' => true,
                'requestPayerEmail' => true,
                'requestPayerPhone' => true,
            );

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error building express payment context: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Get shipping information
     *
     * @return array Shipping info
     */
    private function get_shipping_info() {
        try {
            if (!WC()->cart->needs_shipping()) {
                return array('needs_shipping' => false);
            }

            $packages = WC()->shipping->get_packages();
            $methods = array();
            
            foreach ($packages as $package) {
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $rate) {
                        $methods[] = array(
                            'id' => $rate->id,
                            'label' => $rate->label,
                            'cost' => (float) $rate->cost,
                            'formatted_cost' => wc_price($rate->cost),
                        );
                    }
                }
            }

            return array(
                'needs_shipping' => true,
                'methods' => $methods,
                'destination' => array(
                    'country' => WC()->customer->get_shipping_country(),
                    'state' => WC()->customer->get_shipping_state(),
                    'postcode' => WC()->customer->get_shipping_postcode(),
                    'city' => WC()->customer->get_shipping_city(),
                ),
            );

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error getting shipping info: ' . $e->getMessage());
            return array('needs_shipping' => false);
        }
    }

    /**
     * Get customer information
     *
     * @return array Customer info
     */
    private function get_customer_info() {
        try {
            if (!WC()->customer) {
                return array();
            }

            return array(
                'is_logged_in' => is_user_logged_in(),
                'billing' => array(
                    'first_name' => WC()->customer->get_billing_first_name(),
                    'last_name' => WC()->customer->get_billing_last_name(),
                    'email' => WC()->customer->get_billing_email(),
                    'phone' => WC()->customer->get_billing_phone(),
                    'country' => WC()->customer->get_billing_country(),
                ),
                'shipping' => array(
                    'first_name' => WC()->customer->get_shipping_first_name(),
                    'last_name' => WC()->customer->get_shipping_last_name(),
                    'country' => WC()->customer->get_shipping_country(),
                    'state' => WC()->customer->get_shipping_state(),
                    'city' => WC()->customer->get_shipping_city(),
                    'postcode' => WC()->customer->get_shipping_postcode(),
                ),
            );

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error getting customer info: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Get cart item count
     *
     * @return int Item count
     */
    public function get_cart_item_count() {
        try {
            if (!$this->is_woocommerce_available()) {
                return 0;
            }
            
            return WC()->cart->get_cart_contents_count();

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error getting cart item count: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Add item to cart
     *
     * @param int $product_id Product ID
     * @param int $quantity Quantity
     * @param int $variation_id Variation ID
     * @param array $variation Variation data
     * @param array $cart_item_data Additional cart item data
     * @return string|false Cart item key or false on failure
     */
    public function add_to_cart($product_id, $quantity = 1, $variation_id = 0, $variation = array(), $cart_item_data = array()) {
        try {
            if (!$this->is_woocommerce_available()) {
                throw new Exception('WooCommerce not available');
            }

            // Validierung
            if (!is_numeric($product_id) || $product_id <= 0) {
                throw new Exception('Invalid product ID');
            }

            if (!is_numeric($quantity) || $quantity <= 0) {
                throw new Exception('Invalid quantity');
            }

            $product = wc_get_product($product_id);
            if (!$product || !$product->exists()) {
                throw new Exception('Product not found');
            }

            // Zum Warenkorb hinzufügen
            $cart_item_key = WC()->cart->add_to_cart(
                $product_id,
                $quantity,
                $variation_id,
                $variation,
                $cart_item_data
            );

            if ($cart_item_key) {
                $this->clear_cache();
                return $cart_item_key;
            }

            return false;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error adding to cart: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Remove item from cart
     *
     * @param string $cart_item_key Cart item key
     * @return bool Success status
     */
    public function remove_from_cart($cart_item_key) {
        try {
            if (!$this->is_woocommerce_available()) {
                return false;
            }

            if (empty($cart_item_key)) {
                return false;
            }

            $success = WC()->cart->remove_cart_item($cart_item_key);
            
            if ($success) {
                $this->clear_cache();
            }

            return $success;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error removing from cart: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update cart item quantity
     *
     * @param string $cart_item_key Cart item key
     * @param int $quantity New quantity
     * @return bool Success status
     */
    public function update_cart_item_quantity($cart_item_key, $quantity) {
        try {
            if (!$this->is_woocommerce_available()) {
                return false;
            }

            if (empty($cart_item_key) || !is_numeric($quantity) || $quantity < 0) {
                return false;
            }

            $success = WC()->cart->set_quantity($cart_item_key, $quantity);
            
            if ($success) {
                $this->clear_cache();
            }

            return $success;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error updating cart quantity: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Empty cart
     *
     * @return bool Success status
     */
    public function empty_cart() {
        try {
            if (!$this->is_woocommerce_available()) {
                return false;
            }

            WC()->cart->empty_cart();
            $this->clear_cache();
            
            return true;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error emptying cart: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get Stripe amount (in cents)
     *
     * @param float $amount Amount in major currency unit
     * @param string $currency Currency code
     * @return int Amount in cents
     */
    private function get_stripe_amount($amount, $currency = null) {
        if (!$currency) {
            $currency = get_woocommerce_currency();
        }
        
        $currency = strtolower($currency);
        
        // Zero decimal currencies
        $zero_decimal_currencies = array(
            'bif', 'djf', 'jpy', 'krw', 'pyg', 'vnd', 'xaf', 'xpf', 'kmf', 'mga', 'rwf', 'xof'
        );
        
        if (in_array($currency, $zero_decimal_currencies, true)) {
            return absint($amount);
        } else {
            return absint($amount * 100);
        }
    }

    /**
     * Check if WooCommerce is available and cart exists
     *
     * @return bool
     */
    private function is_woocommerce_available() {
        return class_exists('WooCommerce') && WC() && WC()->cart;
    }

    /**
     * Get empty totals array
     *
     * @return array
     */
    private function get_empty_totals() {
        return array(
            'subtotal' => 0.0,
            'subtotal_tax' => 0.0,
            'shipping' => 0.0,
            'shipping_tax' => 0.0,
            'discount' => 0.0,
            'discount_tax' => 0.0,
            'fees' => 0.0,
            'fees_tax' => 0.0,
            'vat' => 0.0,
            'total' => 0.0,
            'formatted' => array(
                'subtotal' => wc_price(0),
                'shipping' => wc_price(0),
                'discount' => wc_price(0),
                'vat' => wc_price(0),
                'total' => wc_price(0),
            ),
        );
    }

    /**
     * Get empty context based on format
     *
     * @param string $format
     * @return array
     */
    private function get_empty_context($format) {
        $base = array(
            'cart_items' => array(),
            'cart_totals' => $this->get_empty_totals(),
        );

        switch ($format) {
            case 'summary':
                return array_merge($base, array('item_count' => 0));
                
            case 'express_payment':
                return array(
                    'country' => 'DE',
                    'currency' => 'eur',
                    'total' => array('label' => 'Empty Cart', 'amount' => 0),
                    'displayItems' => array(),
                    'requestShipping' => false,
                );
                
            default:
                return array_merge($base, array(
                    'shipping_info' => array('needs_shipping' => false),
                    'customer_info' => array(),
                    'express_payment' => array(),
                    'metadata' => array(
                        'timestamp' => time(),
                        'currency' => get_woocommerce_currency(),
                        'currency_symbol' => get_woocommerce_currency_symbol(),
                        'needs_shipping' => false,
                        'cart_hash' => '',
                    ),
                ));
        }
    }

    /**
     * Cache management methods
     */

    /**
     * Check if data is cached and still valid
     *
     * @param string $key Cache key
     * @return bool
     */
    private function is_cached($key) {
        if (!isset($this->cache[$key])) {
            return false;
        }
        
        $cache_data = $this->cache[$key];
        return (time() - $cache_data['timestamp']) < $this->cache_lifetime;
    }

    /**
     * Get cached data
     *
     * @param string $key Cache key
     * @return mixed Cached data or null
     */
    private function get_cache($key) {
        if ($this->is_cached($key)) {
            return $this->cache[$key]['data'];
        }
        return null;
    }

    /**
     * Set cache data
     *
     * @param string $key Cache key
     * @param mixed $data Data to cache
     */
    private function set_cache($key, $data) {
        $this->cache[$key] = array(
            'data' => $data,
            'timestamp' => time(),
        );
    }

    /**
     * Clear all cache
     */
    public function clear_cache() {
        $this->cache = array();
    }

    /**
     * Clear specific cache key
     *
     * @param string $key Cache key to clear
     */
    public function clear_cache_key($key) {
        if (isset($this->cache[$key])) {
            unset($this->cache[$key]);
        }
    }

    /**
     * Get cache statistics for debugging
     *
     * @return array Cache statistics
     */
    public function get_cache_stats() {
        $stats = array(
            'total_keys' => count($this->cache),
            'keys' => array_keys($this->cache),
            'cache_lifetime' => $this->cache_lifetime,
        );

        foreach ($this->cache as $key => $data) {
            $age = time() - $data['timestamp'];
            $stats['key_ages'][$key] = $age;
            $stats['key_valid'][$key] = $age < $this->cache_lifetime;
        }

        return $stats;
    }

    /**
     * Force refresh all cached data
     *
     * @return array Fresh checkout context
     */
    public function force_refresh() {
        $this->clear_cache();
        return $this->get_checkout_context('full');
    }

    /**
     * Get cart contents weight
     *
     * @return float Total weight
     */
    public function get_cart_weight() {
        try {
            if (!$this->is_woocommerce_available()) {
                return 0.0;
            }

            return (float) WC()->cart->get_cart_contents_weight();

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error getting cart weight: ' . $e->getMessage());
            return 0.0;
        }
    }

    /**
     * Check if cart needs payment
     *
     * @return bool
     */
    public function needs_payment() {
        try {
            if (!$this->is_woocommerce_available()) {
                return false;
            }

            return WC()->cart->needs_payment();

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error checking payment need: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get applied coupons
     *
     * @return array Applied coupons with details
     */
    public function get_applied_coupons() {
        try {
            if (!$this->is_woocommerce_available()) {
                return array();
            }

            $applied_coupons = WC()->cart->get_applied_coupons();
            $coupon_details = array();

            foreach ($applied_coupons as $coupon_code) {
                $coupon = new WC_Coupon($coupon_code);
                if ($coupon->is_valid()) {
                    $coupon_details[] = array(
                        'code' => $coupon_code,
                        'description' => $coupon->get_description(),
                        'discount_type' => $coupon->get_discount_type(),
                        'amount' => $coupon->get_amount(),
                        'formatted_amount' => wc_price($coupon->get_amount()),
                    );
                }
            }

            return $coupon_details;

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error getting applied coupons: ' . $e->getMessage());
            return array();
        }
    }

    /**
     * Apply coupon to cart
     *
     * @param string $coupon_code Coupon code
     * @return array Result with success status and message
     */
    public function apply_coupon($coupon_code) {
        try {
            if (!$this->is_woocommerce_available()) {
                return array(
                    'success' => false,
                    'message' => __('WooCommerce nicht verfügbar', 'yprint-plugin')
                );
            }

            if (empty($coupon_code)) {
                return array(
                    'success' => false,
                    'message' => __('Gutscheincode ist erforderlich', 'yprint-plugin')
                );
            }

            // Prüfe ob Gutschein bereits angewendet
            if (WC()->cart->has_discount($coupon_code)) {
                return array(
                    'success' => false,
                    'message' => __('Gutschein wurde bereits angewendet', 'yprint-plugin')
                );
            }

            // Gutschein anwenden
            $success = WC()->cart->apply_coupon($coupon_code);

            if ($success) {
                $this->clear_cache();
                return array(
                    'success' => true,
                    'message' => __('Gutschein erfolgreich angewendet', 'yprint-plugin'),
                    'totals' => $this->get_cart_totals()
                );
            } else {
                // Fehlermeldungen von WooCommerce abrufen
                $notices = wc_get_notices('error');
                $error_message = !empty($notices) ? $notices[0]['notice'] : __('Gutschein konnte nicht angewendet werden', 'yprint-plugin');
                wc_clear_notices();

                return array(
                    'success' => false,
                    'message' => $error_message
                );
            }

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error applying coupon: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Fehler beim Anwenden des Gutscheins', 'yprint-plugin')
            );
        }
    }

    /**
     * Remove coupon from cart
     *
     * @param string $coupon_code Coupon code
     * @return array Result with success status and message
     */
    public function remove_coupon($coupon_code) {
        try {
            if (!$this->is_woocommerce_available()) {
                return array(
                    'success' => false,
                    'message' => __('WooCommerce nicht verfügbar', 'yprint-plugin')
                );
            }

            if (empty($coupon_code)) {
                return array(
                    'success' => false,
                    'message' => __('Gutscheincode ist erforderlich', 'yprint-plugin')
                );
            }

            $success = WC()->cart->remove_coupon($coupon_code);

            if ($success) {
                $this->clear_cache();
                return array(
                    'success' => true,
                    'message' => __('Gutschein entfernt', 'yprint-plugin'),
                    'totals' => $this->get_cart_totals()
                );
            } else {
                return array(
                    'success' => false,
                    'message' => __('Gutschein konnte nicht entfernt werden', 'yprint-plugin')
                );
            }

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error removing coupon: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Fehler beim Entfernen des Gutscheins', 'yprint-plugin')
            );
        }
    }

    /**
     * Calculate shipping for given address
     *
     * @param array $address Shipping address
     * @return array Shipping calculation result
     */
    public function calculate_shipping($address) {
        try {
            if (!$this->is_woocommerce_available()) {
                return array(
                    'success' => false,
                    'message' => __('WooCommerce nicht verfügbar', 'yprint-plugin')
                );
            }

            // Adresse setzen
            WC()->customer->set_shipping_address_1($address['address_1'] ?? '');
            WC()->customer->set_shipping_address_2($address['address_2'] ?? '');
            WC()->customer->set_shipping_city($address['city'] ?? '');
            WC()->customer->set_shipping_state($address['state'] ?? '');
            WC()->customer->set_shipping_postcode($address['postcode'] ?? '');
            WC()->customer->set_shipping_country($address['country'] ?? 'DE');

            // Versandkosten neu berechnen
            WC()->customer->set_calculated_shipping(true);
            WC()->cart->calculate_shipping();
            WC()->cart->calculate_totals();

            $this->clear_cache();

            $shipping_info = $this->get_shipping_info();

            return array(
                'success' => true,
                'shipping_info' => $shipping_info,
                'totals' => $this->get_cart_totals()
            );

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error calculating shipping: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Fehler bei der Versandkostenberechnung', 'yprint-plugin')
            );
        }
    }

    /**
     * Set chosen shipping method
     *
     * @param string $method_id Shipping method ID
     * @return array Result with success status
     */
    public function set_shipping_method($method_id) {
        try {
            if (!$this->is_woocommerce_available()) {
                return array(
                    'success' => false,
                    'message' => __('WooCommerce nicht verfügbar', 'yprint-plugin')
                );
            }

            if (empty($method_id)) {
                return array(
                    'success' => false,
                    'message' => __('Versandmethoden-ID ist erforderlich', 'yprint-plugin')
                );
            }

            // Versandmethode setzen
            WC()->session->set('chosen_shipping_methods', array($method_id));
            WC()->cart->calculate_totals();

            $this->clear_cache();

            return array(
                'success' => true,
                'message' => __('Versandmethode gesetzt', 'yprint-plugin'),
                'totals' => $this->get_cart_totals()
            );

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error setting shipping method: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Fehler beim Setzen der Versandmethode', 'yprint-plugin')
            );
        }
    }

    /**
     * Get cart for specific user (admin function)
     *
     * @param int $user_id User ID
     * @return array User's cart data
     */
    public function get_user_cart($user_id) {
        try {
            if (!current_user_can('manage_options')) {
                return array(
                    'success' => false,
                    'message' => __('Keine Berechtigung', 'yprint-plugin')
                );
            }

            // Diese Funktion könnte für Admin-Zwecke erweitert werden
            // um Warenkörbe anderer Benutzer zu analysieren
            
            return array(
                'success' => true,
                'message' => __('Funktion noch nicht implementiert', 'yprint-plugin'),
                'user_id' => $user_id
            );

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error getting user cart: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Fehler beim Abrufen des Benutzer-Warenkorbs', 'yprint-plugin')
            );
        }
    }

    /**
     * Export cart data for debugging or backup
     *
     * @return array Cart export data
     */
    public function export_cart_data() {
        try {
            $export_data = array(
                'timestamp' => current_time('mysql'),
                'user_id' => get_current_user_id(),
                'session_id' => WC()->session ? WC()->session->get_customer_id() : null,
                'cart_hash' => WC()->cart ? WC()->cart->get_cart_hash() : null,
                'context' => $this->get_checkout_context('full'),
                'cache_stats' => $this->get_cache_stats(),
                'wc_notices' => wc_get_notices(),
            );

            return array(
                'success' => true,
                'data' => $export_data
            );

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error exporting cart data: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => __('Fehler beim Exportieren der Warenkorbdaten', 'yprint-plugin')
            );
        }
    }

    /**
     * Validate cart before checkout
     *
     * @return array Validation result
     */
    public function validate_cart_for_checkout() {
        try {
            if (!$this->is_woocommerce_available()) {
                return array(
                    'valid' => false,
                    'errors' => array(__('WooCommerce nicht verfügbar', 'yprint-plugin'))
                );
            }

            $errors = array();
            $cart_items = $this->get_cart_items();

            // Warenkorb leer prüfen
            if (empty($cart_items)) {
                $errors[] = __('Der Warenkorb ist leer', 'yprint-plugin');
            }

            // Jedes Item validieren
            foreach ($cart_items as $item) {
                // Produktverfügbarkeit prüfen
                $product = wc_get_product($item['product_id']);
                if (!$product || !$product->exists()) {
                    $errors[] = sprintf(__('Produkt "%s" nicht gefunden', 'yprint-plugin'), $item['name']);
                    continue;
                }

                // Lagerbestand prüfen
                if (!$product->has_enough_stock($item['quantity'])) {
                    $errors[] = sprintf(
                        __('Nicht genügend Lagerbestand für "%s" (verfügbar: %d, angefragt: %d)', 'yprint-plugin'),
                        $item['name'],
                        $product->get_stock_quantity(),
                        $item['quantity']
                    );
                }

                // Preis-Validierung
                if ($item['price'] <= 0) {
                    $errors[] = sprintf(__('Ungültiger Preis für "%s"', 'yprint-plugin'), $item['name']);
                }

                // Design-Produkt spezifische Validierung
                if ($item['is_design_product']) {
                    if (empty($item['design_id'])) {
                        $errors[] = sprintf(__('Design-ID fehlt für "%s"', 'yprint-plugin'), $item['name']);
                    }
                }
            }

            // Versand prüfen falls erforderlich
            if (WC()->cart->needs_shipping()) {
                $shipping_info = $this->get_shipping_info();
                if (empty($shipping_info['methods'])) {
                    $errors[] = __('Keine Versandmethode verfügbar für die angegebene Adresse', 'yprint-plugin');
                }
            }

            // Minimum Bestellwert prüfen (falls konfiguriert)
            $min_amount = get_option('woocommerce_minimum_order_amount', 0);
            if ($min_amount > 0) {
                $totals = $this->get_cart_totals();
                if ($totals['total'] < $min_amount) {
                    $errors[] = sprintf(
                        __('Mindestbestellwert von %s nicht erreicht', 'yprint-plugin'),
                        wc_price($min_amount)
                    );
                }
            }

            return array(
                'valid' => empty($errors),
                'errors' => $errors,
                'item_count' => count($cart_items),
                'total_amount' => $this->get_cart_totals()['total']
            );

        } catch (Exception $e) {
            error_log('YPrint Cart Data Error validating cart: ' . $e->getMessage());
            return array(
                'valid' => false,
                'errors' => array(__('Fehler bei der Warenkorb-Validierung', 'yprint-plugin'))
            );
        }
    }

    /**
     * Debug method to get detailed cart information
     *
     * @return array Debug information
     */
    public function get_debug_info() {
        if (!current_user_can('manage_options')) {
            return array('error' => 'No permission');
        }

        try {
            $debug_info = array(
                'timestamp' => current_time('mysql'),
                'woocommerce_available' => $this->is_woocommerce_available(),
                'cart_exists' => WC() && WC()->cart ? true : false,
                'session_exists' => WC() && WC()->session ? true : false,
                'customer_exists' => WC() && WC()->customer ? true : false,
                'cache_stats' => $this->get_cache_stats(),
                'cart_validation' => $this->validate_cart_for_checkout(),
            );

            if ($this->is_woocommerce_available()) {
                $debug_info['wc_cart_details'] = array(
                    'is_empty' => WC()->cart->is_empty(),
                    'cart_contents_count' => WC()->cart->get_cart_contents_count(),
                    'cart_hash' => WC()->cart->get_cart_hash(),
                    'needs_payment' => WC()->cart->needs_payment(),
                    'needs_shipping' => WC()->cart->needs_shipping(),
                    'applied_coupons' => WC()->cart->get_applied_coupons(),
                );

                if (WC()->session) {
                    $debug_info['wc_session_details'] = array(
                        'customer_id' => WC()->session->get_customer_id(),
                        'session_expiry' => WC()->session->get_session_expiry(),
                    );
                }
            }

            return $debug_info;

        } catch (Exception $e) {
            return array('error' => $e->getMessage());
        }
    }
}