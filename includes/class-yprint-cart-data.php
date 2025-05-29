<?php
/**
 * YPrint Cart Data Manager
 * 
 * Zentrale Verwaltung aller Checkout-Daten als Single Source of Truth
 *
 * @package YPrint_Plugin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class YPrint_Cart_Data {

    /**
     * Singleton instance
     *
     * @var YPrint_Cart_Data
     */
    protected static $instance = null;

    /**
     * Cached checkout context
     *
     * @var array
     */
    private $cached_context = null;

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
     * Private constructor
     */
    private function __construct() {
        // Clear cache when cart changes
        add_action('woocommerce_cart_item_removed', array($this, 'clear_cache'));
        add_action('woocommerce_cart_item_set_quantity', array($this, 'clear_cache'));
        add_action('woocommerce_applied_coupon', array($this, 'clear_cache'));
        add_action('woocommerce_removed_coupon', array($this, 'clear_cache'));
    }

    /**
     * Clear cached context
     */
    public function clear_cache() {
        $this->cached_context = null;
    }

    /**
     * Get complete checkout context (Single Source of Truth)
     *
     * @param string $format Format: 'full', 'items_only', 'totals_only', 'express_payment'
     * @return array Complete checkout data
     */
    public function get_checkout_context($format = 'full') {
        // Return cached data if available
        if ($this->cached_context !== null && $format === 'full') {
            return $this->cached_context;
        }

        $context = array();

        // Check if WooCommerce and cart are available
        if (!function_exists('WC') || !WC()->cart || WC()->cart->is_empty()) {
            return $this->get_empty_context();
        }

        // Calculate totals first
        WC()->cart->calculate_totals();

        // Build cart items data
        $context['cart_items'] = $this->build_cart_items();
        
        // Build totals data
        $context['cart_totals'] = $this->build_cart_totals();
        
        // Build shipping data
        $context['shipping'] = $this->build_shipping_data();
        
        // Build express payment data
        $context['express_payment'] = $this->build_express_payment_data();
        
        // Build payment context
        $context['payment_context'] = $this->build_payment_context();
        
        // Build customer data
        $context['customer'] = $this->build_customer_data();
        
        // Meta information
        $context['meta'] = array(
            'timestamp' => time(),
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'needs_shipping' => WC()->cart->needs_shipping(),
            'needs_payment' => WC()->cart->needs_payment(),
            'tax_enabled' => wc_tax_enabled(),
            'prices_include_tax' => wc_prices_include_tax(),
        );

        // Cache full context
        if ($format === 'full') {
            $this->cached_context = $context;
        }

        // Return formatted data based on request
        return $this->format_context($context, $format);
    }

    /**
     * Build cart items with design product support
     *
     * @return array
     */
    private function build_cart_items() {
        $items = array();

        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = $cart_item['data'];
            
            if (!$_product || !$_product->exists()) {
                continue;
            }
            
            $item_data = array(
                'id' => $cart_item['product_id'],
                'variation_id' => $cart_item['variation_id'],
                'name' => $_product->get_name(),
                'price' => (float) $_product->get_price(),
                'quantity' => $cart_item['quantity'],
                'line_total' => (float) $cart_item['line_total'],
                'line_subtotal' => (float) $cart_item['line_subtotal'],
                'image' => wp_get_attachment_image_url($_product->get_image_id(), 'thumbnail'),
                'permalink' => $_product->get_permalink(),
                'cart_item_key' => $cart_item_key,
                'is_design_product' => false,
                'design_details' => array(),
            );
            
            // Fallback image
            if (empty($item_data['image'])) {
                $item_data['image'] = wc_placeholder_img_src('thumbnail');
            }
            
            // Design product specific data
            if (isset($cart_item['print_design'])) {
                $design = $cart_item['print_design'];
                
                $item_data['is_design_product'] = true;
                $item_data['design_id'] = $design['design_id'] ?? null;
                $item_data['variation_name'] = $design['variation_name'] ?? '';
                $item_data['size_name'] = $design['size_name'] ?? '';
                
                // Override with design data if available
                if (!empty($design['name'])) {
                    $item_data['name'] = $design['name'];
                }
                
                if (!empty($design['preview_url'])) {
                    $item_data['image'] = $design['preview_url'];
                }
                
                if (!empty($design['calculated_price'])) {
                    $item_data['price'] = (float) $design['calculated_price'];
                }
                
                // Build design details for display
                if (!empty($design['variation_name'])) {
                    $item_data['design_details'][] = __('Farbe: ', 'yprint-checkout') . $design['variation_name'];
                }
                
                if (!empty($design['size_name'])) {
                    $item_data['design_details'][] = __('Größe: ', 'yprint-checkout') . $design['size_name'];
                }
                
                if (!empty($design['design_width_cm']) && !empty($design['design_height_cm'])) {
                    $item_data['design_details'][] = sprintf(
                        __('Maße: %s×%s cm', 'yprint-checkout'),
                        number_format($design['design_width_cm'], 1),
                        number_format($design['design_height_cm'], 1)
                    );
                }
            }
            
            $items[] = $item_data;
        }

        return $items;
    }

    /**
     * Build cart totals
     *
     * @return array
     */
    private function build_cart_totals() {
        return array(
            'subtotal' => (float) WC()->cart->get_subtotal(),
            'subtotal_tax' => (float) WC()->cart->get_subtotal_tax(),
            'shipping_total' => (float) WC()->cart->get_shipping_total(),
            'shipping_tax' => (float) WC()->cart->get_shipping_tax(),
            'discount_total' => (float) WC()->cart->get_discount_total(),
            'discount_tax' => (float) WC()->cart->get_discount_tax(),
            'total_tax' => (float) WC()->cart->get_total_tax(),
            'fee_total' => (float) WC()->cart->get_fee_total(),
            'total' => (float) WC()->cart->get_total('edit'),
            'coupons' => WC()->cart->get_applied_coupons(),
        );
    }

    /**
     * Build shipping data
     *
     * @return array
     */
    private function build_shipping_data() {
        $shipping_data = array(
            'needs_shipping' => WC()->cart->needs_shipping(),
            'shipping_total' => (float) WC()->cart->get_shipping_total(),
            'free_shipping_threshold' => 0,
            'packages' => array(),
        );

        if ($shipping_data['needs_shipping']) {
            $packages = WC()->shipping->get_packages();
            foreach ($packages as $package_key => $package) {
                $shipping_data['packages'][$package_key] = array(
                    'contents' => count($package['contents']),
                    'destination' => $package['destination'],
                    'rates' => array(),
                );
                
                if (!empty($package['rates'])) {
                    foreach ($package['rates'] as $rate_id => $rate) {
                        $shipping_data['packages'][$package_key]['rates'][$rate_id] = array(
                            'id' => $rate->id,
                            'label' => $rate->label,
                            'cost' => (float) $rate->cost,
                            'method_id' => $rate->method_id,
                        );
                    }
                }
            }
        }

        return $shipping_data;
    }

    /**
     * Build express payment data (Stripe specific)
     *
     * @return array
     */
    private function build_express_payment_data() {
        $items = $this->build_cart_items();
        $totals = $this->build_cart_totals();
        
        // Build Stripe-compatible display items
        $display_items = array();
        foreach ($items as $item) {
            $quantity_label = $item['quantity'] > 1 ? ' (x' . $item['quantity'] . ')' : '';
            $display_items[] = array(
                'label' => $item['name'] . $quantity_label,
                'amount' => $this->get_stripe_amount($item['line_total']),
            );
        }

        // Add shipping if applicable
        if ($totals['shipping_total'] > 0) {
            $display_items[] = array(
                'label' => __('Versand', 'yprint-checkout'),
                'amount' => $this->get_stripe_amount($totals['shipping_total']),
            );
        }

        // Add discount if applicable
        if ($totals['discount_total'] > 0) {
            $display_items[] = array(
                'label' => __('Rabatt', 'yprint-checkout'),
                'amount' => -$this->get_stripe_amount($totals['discount_total']),
            );
        }

        return array(
            'total' => array(
                'label' => get_bloginfo('name') . ' (via YPrint)',
                'amount' => $this->get_stripe_amount($totals['total']),
            ),
            'displayItems' => $display_items,
            'currency' => strtolower(get_woocommerce_currency()),
            'country' => substr(get_option('woocommerce_default_country'), 0, 2),
            'requestShipping' => WC()->cart->needs_shipping(),
            'requestPayerName' => true,
            'requestPayerEmail' => true,
            'requestPayerPhone' => true,
        );
    }

    /**
     * Build payment context
     *
     * @return array
     */
    private function build_payment_context() {
        $context = array(
            'currency' => get_woocommerce_currency(),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'total_amount' => (float) WC()->cart->get_total('edit'),
            'total_amount_cents' => $this->get_stripe_amount(WC()->cart->get_total('edit')),
            'needs_payment' => WC()->cart->needs_payment(),
        );

        // Add Stripe-specific data if available
        if (class_exists('YPrint_Stripe_API')) {
            $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
            $context['stripe'] = array(
                'enabled' => !empty($stripe_settings),
                'test_mode' => isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode'],
                'payment_request_enabled' => isset($stripe_settings['payment_request']) && 'yes' === $stripe_settings['payment_request'],
            );
        }

        return $context;
    }

    /**
     * Build customer data
     *
     * @return array
     */
    private function build_customer_data() {
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
                'postcode' => WC()->customer->get_billing_postcode(),
                'city' => WC()->customer->get_billing_city(),
                'address_1' => WC()->customer->get_billing_address_1(),
                'address_2' => WC()->customer->get_billing_address_2(),
            ),
            'shipping' => array(
                'first_name' => WC()->customer->get_shipping_first_name(),
                'last_name' => WC()->customer->get_shipping_last_name(),
                'country' => WC()->customer->get_shipping_country(),
                'postcode' => WC()->customer->get_shipping_postcode(),
                'city' => WC()->customer->get_shipping_city(),
                'address_1' => WC()->customer->get_shipping_address_1(),
                'address_2' => WC()->customer->get_shipping_address_2(),
            ),
        );
    }

    /**
     * Get empty context for when cart is empty
     *
     * @return array
     */
    private function get_empty_context() {
        return array(
            'cart_items' => array(),
            'cart_totals' => array(
                'subtotal' => 0,
                'shipping_total' => 0,
                'discount_total' => 0,
                'total_tax' => 0,
                'total' => 0,
                'coupons' => array(),
            ),
            'shipping' => array('needs_shipping' => false),
            'express_payment' => array(),
            'payment_context' => array('total_amount' => 0),
            'customer' => array(),
            'meta' => array(
                'timestamp' => time(),
                'currency' => get_woocommerce_currency(),
                'needs_shipping' => false,
                'needs_payment' => false,
            ),
        );
    }

    /**
     * Format context based on requested format
     *
     * @param array $context
     * @param string $format
     * @return array
     */
    private function format_context($context, $format) {
        switch ($format) {
            case 'items_only':
                return $context['cart_items'];
            
            case 'totals_only':
                return $context['cart_totals'];
            
            case 'express_payment':
                return $context['express_payment'];
            
            case 'full':
            default:
                return $context;
        }
    }

    /**
     * Convert amount to Stripe format (cents)
     *
     * @param float $amount
     * @return int
     */
    private function get_stripe_amount($amount) {
        $currency = strtolower(get_woocommerce_currency());
        
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
     * Get formatted price for display
     *
     * @param float $amount
     * @param bool $include_currency
     * @return string
     */
    public function format_price($amount, $include_currency = true) {
        if ($include_currency) {
            return wc_price($amount);
        } else {
            return number_format_i18n($amount, 2);
        }
    }
}