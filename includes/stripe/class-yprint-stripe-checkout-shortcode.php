<?php
/**
 * Checkout Shortcode Implementation
 *
 * @package YPrint
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to implement the YPrint custom checkout experience
 */
class YPrint_Stripe_Checkout_Shortcode {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode('yprint_checkout', array(__CLASS__, 'checkout_shortcode'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_checkout_assets'));
    }
    
    /**
     * Enqueue necessary CSS and JS for checkout
     */
    public static function enqueue_checkout_assets() {
        // Only load on pages with our shortcode
        global $post;
        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'yprint_checkout')) {
            // CSS
            wp_enqueue_style(
                'yprint-checkout-style',
                YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css',
                array(),
                YPRINT_PLUGIN_VERSION
            );
            
            // JavaScript
            wp_enqueue_script(
                'yprint-checkout-js',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js',
                array('jquery'),
                YPRINT_PLUGIN_VERSION,
                true
            );
            
            // Pass data to JavaScript
            wp_localize_script(
                'yprint-checkout-js',
                'yprintCheckout',
                array(
                    'ajaxurl' => admin_url('admin-ajax.php'),
                    'security' => wp_create_nonce('yprint-checkout-nonce'),
                    'is_logged_in' => is_user_logged_in() ? 'yes' : 'no',
                )
            );
        }
    }
    
    /**
     * Checkout shortcode implementation
     * 
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function checkout_shortcode($atts) {
        // Parse attributes
        $atts = shortcode_atts(
            array(
                'test_mode' => 'no',
            ),
            $atts,
            'yprint_checkout'
        );
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            return self::get_login_message();
        }
        
        // Check if cart is empty
        if (function_exists('WC') && WC()->cart->is_empty()) {
            return self::get_empty_cart_message();
        }
        
        // Start output buffer
        ob_start();
        
        // Get user ID
        $user_id = get_current_user_id();
        
        // Main container
        ?>
        <div class="yprint-checkout-container" data-test-mode="<?php echo esc_attr($atts['test_mode']); ?>">
            
            <!-- Progress Indicator -->
            <div class="yprint-checkout-progress">
                <div class="yprint-progress-bar">
                    <div class="yprint-progress-track">
                        <div class="yprint-progress-fill"></div>
                    </div>
                    <div class="yprint-progress-steps">
                        <div class="yprint-progress-step active" data-step="1">
                            <div class="yprint-step-indicator">1</div>
                            <div class="yprint-step-label">Adresse</div>
                        </div>
                        <div class="yprint-progress-step" data-step="2">
                            <div class="yprint-step-indicator">2</div>
                            <div class="yprint-step-label">Zahlung</div>
                        </div>
                        <div class="yprint-progress-step" data-step="3">
                            <div class="yprint-step-indicator">3</div>
                            <div class="yprint-step-label">Bestätigung</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Main Checkout Layout -->
            <div class="yprint-checkout-layout">
                <!-- Left Column - Checkout Steps -->
                <div class="yprint-checkout-main">
                    <!-- Step 1: Address -->
                    <div class="yprint-checkout-step active" id="yprint-step-address" data-step="1">
                        <h2>Lieferadresse auswählen</h2>
                        <p class="yprint-step-description">Bitte wähle eine deiner Adressen aus oder gib eine neue Adresse ein.</p>
                        
                        <!-- Address content will be loaded here -->
                        <div class="yprint-address-selection">
                            <!-- This will be populated via JavaScript -->
                            <div class="yprint-address-placeholder">
                                <div class="yprint-address-loading">
                                    <div class="yprint-loader"></div>
                                    <p>Adressen werden geladen...</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="yprint-checkout-buttons">
                            <button type="button" class="yprint-button yprint-continue-button" data-next-step="2">Weiter zur Zahlung</button>
                        </div>
                    </div>
                    
                    <!-- Step 2: Payment -->
                    <div class="yprint-checkout-step" id="yprint-step-payment" data-step="2">
                        <h2>Bezahlmethode</h2>
                        <p class="yprint-step-description">Wähle deine bevorzugte Zahlungsmethode.</p>
                        
                        <!-- Payment content will be implemented in next steps -->
                        <div class="yprint-payment-selection">
                            <!-- Placeholder for payment methods -->
                            <div class="yprint-payment-placeholder">
                                <p>Zahlungsmethoden werden in einem späteren Schritt implementiert.</p>
                            </div>
                        </div>
                        
                        <div class="yprint-checkout-buttons">
                            <button type="button" class="yprint-button yprint-back-button" data-prev-step="1">Zurück</button>
                            <button type="button" class="yprint-button yprint-continue-button" data-next-step="3">Weiter zur Bestätigung</button>
                        </div>
                    </div>
                    
                    <!-- Step 3: Confirmation -->
                    <div class="yprint-checkout-step" id="yprint-step-confirmation" data-step="3">
                        <h2>Bestellung bestätigen</h2>
                        <p class="yprint-step-description">Überprüfe deine Bestellung und bestätige den Kauf.</p>
                        
                        <!-- Confirmation content will be implemented in next steps -->
                        <div class="yprint-confirmation-section">
                            <!-- Placeholder for confirmation -->
                            <div class="yprint-confirmation-placeholder">
                                <p>Bestätigungsinhalte werden in einem späteren Schritt implementiert.</p>
                            </div>
                        </div>
                        
                        <div class="yprint-checkout-buttons">
                            <button type="button" class="yprint-button yprint-back-button" data-prev-step="2">Zurück</button>
                            <button type="button" class="yprint-button yprint-order-button" id="yprint-place-order">Kostenpflichtig bestellen</button>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Cart Summary -->
                <div class="yprint-checkout-sidebar">
                    <div class="yprint-cart-summary">
                        <h3>Warenkorb</h3>
                        
                        <!-- Cart items -->
                        <div class="yprint-cart-items">
                            <?php if (function_exists('WC')): ?>
                                <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item): 
                                    $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                                    $product_name = $_product->get_name();
                                    $thumbnail = $_product->get_image('thumbnail');
                                    $product_price = WC()->cart->get_product_price($_product);
                                    $product_subtotal = WC()->cart->get_product_subtotal($_product, $cart_item['quantity']);
                                ?>
                                <div class="yprint-cart-item">
                                    <div class="yprint-cart-item-image">
                                        <?php echo $thumbnail; ?>
                                    </div>
                                    <div class="yprint-cart-item-details">
                                        <div class="yprint-cart-item-title"><?php echo esc_html($product_name); ?></div>
                                        <div class="yprint-cart-item-quantity">Anzahl: <?php echo esc_html($cart_item['quantity']); ?></div>
                                        <div class="yprint-cart-item-price"><?php echo $product_subtotal; ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="yprint-cart-placeholder">
                                    <p>Warenkorb wird geladen...</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Cart totals -->
                        <div class="yprint-cart-totals">
                            <?php if (function_exists('WC')): ?>
                                <div class="yprint-cart-subtotal">
                                    <span>Zwischensumme</span>
                                    <span><?php echo WC()->cart->get_cart_subtotal(); ?></span>
                                </div>
                                <?php if (WC()->cart->get_cart_shipping_total()): ?>
                                <div class="yprint-cart-shipping">
                                    <span>Versand</span>
                                    <span><?php echo WC()->cart->get_cart_shipping_total(); ?></span>
                                </div>
                                <?php endif; ?>
                                <?php if (WC()->cart->get_taxes_total()): ?>
                                <div class="yprint-cart-tax">
                                    <span>Steuern</span>
                                    <span><?php echo wc_price(WC()->cart->get_taxes_total()); ?></span>
                                </div>
                                <?php endif; ?>
                                <div class="yprint-cart-total">
                                    <span>Gesamtsumme</span>
                                    <span><?php echo WC()->cart->get_total(); ?></span>
                                </div>
                            <?php else: ?>
                                <div class="yprint-totals-placeholder">
                                    <p>Berechnung wird geladen...</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
        <?php
        
        // Return the buffered content
        return ob_get_clean();
    }
    
    /**
     * Get login message HTML
     *
     * @return string HTML
     */
    private static function get_login_message() {
        ob_start();
        ?>
        <div class="yprint-checkout-login-required">
            <h2>Anmeldung erforderlich</h2>
            <p>Bitte melde dich an, um mit dem Checkout fortzufahren.</p>
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="yprint-button">Zum Login</a>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Get empty cart message HTML
     *
     * @return string HTML
     */
    private static function get_empty_cart_message() {
        ob_start();
        ?>
        <div class="yprint-checkout-empty-cart">
            <h2>Dein Warenkorb ist leer</h2>
            <p>Bitte füge Produkte zu deinem Warenkorb hinzu, bevor du zur Kasse gehst.</p>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="yprint-button">Zum Shop</a>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Initialize the class
YPrint_Stripe_Checkout_Shortcode::init();