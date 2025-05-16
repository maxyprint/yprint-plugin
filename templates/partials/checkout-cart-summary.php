<?php
/**
 * YPrint Checkout Cart Summary
 *
 * @package YPrint
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>
<div class="yprint-cart-summary">
    <h3>Warenkorb</h3>
    
    <div class="yprint-cart-items">
        <?php 
        // Display cart items
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            
            if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                $thumbnail = $_product->get_image('thumbnail');
                $product_name = $_product->get_name();
                $product_price = WC()->cart->get_product_price($_product);
                $product_subtotal = WC()->cart->get_product_subtotal($_product, $cart_item['quantity']);
                ?>
                <div class="yprint-cart-item">
                    <div class="yprint-cart-item-image">
                        <?php if ($product_permalink) : ?>
                            <a href="<?php echo esc_url($product_permalink); ?>">
                                <?php echo $thumbnail; ?>
                            </a>
                        <?php else : ?>
                            <?php echo $thumbnail; ?>
                        <?php endif; ?>
                    </div>
                    <div class="yprint-cart-item-details">
                        <div class="yprint-cart-item-title">
                            <?php if ($product_permalink) : ?>
                                <a href="<?php echo esc_url($product_permalink); ?>">
                                    <?php echo esc_html($product_name); ?>
                                </a>
                            <?php else : ?>
                                <?php echo esc_html($product_name); ?>
                            <?php endif; ?>
                        </div>
                        <div class="yprint-cart-item-meta">
                            <div class="yprint-cart-item-quantity">
                                <?php echo sprintf('Anzahl: %s', $cart_item['quantity']); ?>
                            </div>
                            <div class="yprint-cart-item-price">
                                <?php echo sprintf('Preis: %s', $product_price); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
        }
        ?>
    </div>
    
    <div class="yprint-cart-totals">
        <div class="yprint-cart-subtotal">
            <span>Zwischensumme</span>
            <span><?php echo WC()->cart->get_cart_subtotal(); ?></span>
        </div>
        
        <?php if (WC()->cart->needs_shipping() && WC()->cart->get_cart_shipping_total()) : ?>
        <div class="yprint-cart-shipping">
            <span>Versand</span>
            <span><?php echo WC()->cart->get_cart_shipping_total(); ?></span>
        </div>
        <?php endif; ?>
        
        <?php if (wc_tax_enabled()) : ?>
        <div class="yprint-cart-tax">
            <span>MwSt.</span>
            <span><?php echo WC()->cart->get_taxes_total(true, true); ?></span>
        </div>
        <?php endif; ?>
        
        <div class="yprint-cart-total">
            <span>Gesamtsumme</span>
            <span><?php echo WC()->cart->get_total(); ?></span>
        </div>
    </div>
    
    <?php if (wc_coupons_enabled()) : ?>
    <div class="yprint-cart-coupon">
        <form class="yprint-coupon-form">
            <input type="text" name="coupon_code" class="yprint-input yprint-coupon-input" id="yprint-coupon-code" placeholder="Gutscheincode" />
            <button type="submit" class="yprint-button yprint-button-secondary yprint-apply-coupon" name="apply_coupon">Anwenden</button>
        </form>
    </div>
    <?php endif; ?>
</div>