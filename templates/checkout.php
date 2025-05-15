<?php
/**
 * Checkout template
 *
 * @package YPrint_Plugin
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>
<div id="yprint-checkout" class="yprint-checkout">

    <div class="yprint-checkout-container">
        <div class="yprint-checkout-messages"></div>
        
        <!-- Checkout Columns Container -->
        <div class="yprint-checkout-columns">
            
            <!-- Form Column -->
            <div class="yprint-checkout-form-column">
                <form id="yprint-checkout-form" class="yprint-checkout-form">
                    <h2><?php _e('Shipping information', 'yprint-plugin'); ?></h2>
                    
                    <!-- Customer Information -->
                    <div class="yprint-checkout-section customer-information">
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="billing_email"><?php _e('Email address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="email" id="billing_email" name="billing_email" class="input-text" value="<?php echo esc_attr($customer->get_billing_email()); ?>" required>
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="billing_phone"><?php _e('Phone', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="tel" id="billing_phone" name="billing_phone" class="input-text" value="<?php echo esc_attr($customer->get_billing_phone()); ?>" required>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Billing Address -->
                    <div class="yprint-checkout-section billing-address">
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="billing_first_name"><?php _e('First name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="billing_first_name" name="billing_first_name" class="input-text" value="<?php echo esc_attr($customer->get_billing_first_name()); ?>" required>
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="billing_last_name"><?php _e('Last name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="billing_last_name" name="billing_last_name" class="input-text" value="<?php echo esc_attr($customer->get_billing_last_name()); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="billing_company"><?php _e('Company (optional)', 'yprint-plugin'); ?></label>
                                <input type="text" id="billing_company" name="billing_company" class="input-text" value="<?php echo esc_attr($customer->get_billing_company()); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="billing_address_1"><?php _e('Street address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="billing_address_1" name="billing_address_1" class="input-text" placeholder="<?php esc_attr_e('House number and street name', 'yprint-plugin'); ?>" value="<?php echo esc_attr($customer->get_billing_address_1()); ?>" required>
                                </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="billing_address_2"><?php _e('Apartment, suite, etc. (optional)', 'yprint-plugin'); ?></label>
                                <input type="text" id="billing_address_2" name="billing_address_2" class="input-text" value="<?php echo esc_attr($customer->get_billing_address_2()); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="billing_postcode"><?php _e('Postcode / ZIP', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="billing_postcode" name="billing_postcode" class="input-text" value="<?php echo esc_attr($customer->get_billing_postcode()); ?>" required>
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="billing_city"><?php _e('City', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="billing_city" name="billing_city" class="input-text" value="<?php echo esc_attr($customer->get_billing_city()); ?>" required>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="billing_country"><?php _e('Country / Region', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <select id="billing_country" name="billing_country" class="select country-select" required data-placeholder="<?php esc_attr_e('Select a country / region', 'yprint-plugin'); ?>">
                                    <option value=""><?php esc_html_e('Select a country / region', 'yprint-plugin'); ?></option>
                                    <?php foreach ($countries as $key => $value) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($customer->get_billing_country(), $key); ?>><?php echo esc_html($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="billing_state"><?php _e('State / County', 'yprint-plugin'); ?></label>
                                <select id="billing_state" name="billing_state" class="select state-select" data-placeholder="<?php esc_attr_e('Select a state', 'yprint-plugin'); ?>">
                                    <option value=""><?php esc_html_e('Select a state', 'yprint-plugin'); ?></option>
                                    <?php 
                                    $country = $customer->get_billing_country();
                                    if (!empty($country) && isset($states[$country])) {
                                        foreach ($states[$country] as $key => $value) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($customer->get_billing_state(), $key); ?>><?php echo esc_html($value); ?></option>
                                        <?php endforeach; 
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ship to different address -->
                    <div class="ship-to-different-address">
                        <label class="checkbox-container">
                            <input id="ship_to_different_address" name="ship_to_different_address" type="checkbox" value="1" class="input-checkbox">
                            <span class="checkmark"></span>
                            <?php _e('Ship to a different address?', 'yprint-plugin'); ?>
                        </label>
                    </div>
                    
                    <!-- Shipping Address (hidden by default) -->
                    <div class="yprint-checkout-section shipping-address" style="display: none;">
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="shipping_first_name"><?php _e('First name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_first_name" name="shipping_first_name" class="input-text" value="<?php echo esc_attr($customer->get_shipping_first_name()); ?>">
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="shipping_last_name"><?php _e('Last name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_last_name" name="shipping_last_name" class="input-text" value="<?php echo esc_attr($customer->get_shipping_last_name()); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="shipping_company"><?php _e('Company (optional)', 'yprint-plugin'); ?></label>
                                <input type="text" id="shipping_company" name="shipping_company" class="input-text" value="<?php echo esc_attr($customer->get_shipping_company()); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="shipping_address_1"><?php _e('Street address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_address_1" name="shipping_address_1" class="input-text" placeholder="<?php esc_attr_e('House number and street name', 'yprint-plugin'); ?>" value="<?php echo esc_attr($customer->get_shipping_address_1()); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="shipping_address_2"><?php _e('Apartment, suite, etc. (optional)', 'yprint-plugin'); ?></label>
                                <input type="text" id="shipping_address_2" name="shipping_address_2" class="input-text" value="<?php echo esc_attr($customer->get_shipping_address_2()); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="shipping_postcode"><?php _e('Postcode / ZIP', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_postcode" name="shipping_postcode" class="input-text" value="<?php echo esc_attr($customer->get_shipping_postcode()); ?>">
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="shipping_city"><?php _e('City', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_city" name="shipping_city" class="input-text" value="<?php echo esc_attr($customer->get_shipping_city()); ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="shipping_country"><?php _e('Country / Region', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <select id="shipping_country" name="shipping_country" class="select country-select" data-placeholder="<?php esc_attr_e('Select a country / region', 'yprint-plugin'); ?>">
                                    <option value=""><?php esc_html_e('Select a country / region', 'yprint-plugin'); ?></option>
                                    <?php foreach ($countries as $key => $value) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($customer->get_shipping_country(), $key); ?>><?php echo esc_html($value); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="shipping_state"><?php _e('State / County', 'yprint-plugin'); ?></label>
                                <select id="shipping_state" name="shipping_state" class="select state-select" data-placeholder="<?php esc_attr_e('Select a state', 'yprint-plugin'); ?>">
                                    <option value=""><?php esc_html_e('Select a state', 'yprint-plugin'); ?></option>
                                    <?php 
                                    $country = $customer->get_shipping_country();
                                    if (!empty($country) && isset($states[$country])) {
                                        foreach ($states[$country] as $key => $value) : ?>
                                            <option value="<?php echo esc_attr($key); ?>" <?php selected($customer->get_shipping_state(), $key); ?>><?php echo esc_html($value); ?></option>
                                        <?php endforeach; 
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order notes -->
                    <div class="yprint-checkout-section order-notes">
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="order_comments"><?php _e('Order notes (optional)', 'yprint-plugin'); ?></label>
                                <textarea id="order_comments" name="order_comments" class="input-text" placeholder="<?php esc_attr_e('Notes about your order, e.g. special notes for delivery.', 'yprint-plugin'); ?>" rows="4"></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <h2><?php _e('Shipping method', 'yprint-plugin'); ?></h2>
                    
                    <!-- Shipping Methods -->
                    <div class="yprint-checkout-section shipping-methods">
                        <div class="shipping-methods-container">
                            <?php
                            // Get shipping methods
                            $packages = WC()->shipping->get_packages();
                            
                            if (!empty($packages)) {
                                foreach ($packages as $package_key => $package) {
                                    if (!empty($package['rates'])) {
                                        // Get chosen shipping method
                                        $chosen_method = WC()->session->get('chosen_shipping_methods')[$package_key] ?? '';
                                        
                                        echo '<div class="shipping-methods-list">';
                                        
                                        foreach ($package['rates'] as $key => $rate) {
                                            ?>
                                            <div class="shipping-method">
                                                <label class="radio-container">
                                                    <input type="radio" name="shipping_method" id="shipping_method_<?php echo esc_attr($rate->id); ?>" value="<?php echo esc_attr($rate->id); ?>" <?php checked($chosen_method, $rate->id); ?> class="shipping-method-input">
                                                    <span class="radio-checkmark"></span>
                                                    <span class="shipping-method-label">
                                                        <?php echo esc_html($rate->label); ?> - <?php echo wc_price($rate->cost); ?>
                                                    </span>
                                                </label>
                                            </div>
                                            <?php
                                        }
                                        
                                        echo '</div>';
                                    } else {
                                        echo '<p class="no-shipping-methods">' . __('No shipping methods available. Please check your address information or contact us.', 'yprint-plugin') . '</p>';
                                    }
                                }
                            } else {
                                echo '<p class="no-shipping-packages">' . __('No shipping options available. Please ensure your address has been entered correctly, or contact us if you need any help.', 'yprint-plugin') . '</p>';
                            }
                            ?>
                        </div>
                    </div>
                    
                    <h2><?php _e('Payment', 'yprint-plugin'); ?></h2>
                    
                    <!-- Payment Methods -->
                    <div class="yprint-checkout-section payment-methods">
                        <div id="yprint-stripe-payment-request-wrapper" class="payment-request-wrapper" style="display: none;">
                            <div id="yprint-stripe-payment-request-button"></div>
                        </div>
                        
                        <div class="payment-method-separator" style="display: none;">
                            <span><?php _e('OR', 'yprint-plugin'); ?></span>
                        </div>
                        
                        <div class="credit-card-section">
                            <div id="yprint-stripe-card-element" class="stripe-card-element"></div>
                            <div id="yprint-stripe-card-errors" role="alert"></div>
                        </div>
                    </div>
                    
                    <div class="yprint-checkout-section form-actions">
                        <input type="hidden" name="payment_method" value="yprint_stripe">
                        <input type="hidden" name="payment_method_id" id="payment_method_id" value="">
                        
                        <button type="submit" class="button checkout-button" id="yprint-place-order">
                            <?php _e('Place order', 'yprint-plugin'); ?>
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Order Summary Column -->
            <div class="yprint-checkout-summary-column">
                <div class="yprint-order-summary">
                    <h2><?php _e('Order summary', 'yprint-plugin'); ?></h2>
                    
                    <div class="yprint-order-summary-content">
                        <!-- Cart Items -->
                        <div class="cart-items">
                            <?php
                            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                                $product = $cart_item['data'];
                                $product_id = $cart_item['product_id'];
                                $quantity = $cart_item['quantity'];
                                $price = WC()->cart->get_product_price($product);
                                $subtotal = WC()->cart->get_product_subtotal($product, $quantity);
                                $image = $product->get_image(array(60, 60));
                                ?>
                                <div class="cart-item">
                                    <div class="cart-item-image">
                                        <?php echo $image; ?>
                                        <span class="item-quantity"><?php echo esc_html($quantity); ?></span>
                                    </div>
                                    <div class="cart-item-details">
                                        <div class="cart-item-name">
                                            <?php echo esc_html($product->get_name()); ?>
                                        </div>
                                        <?php
                                        // Show variation data if available
                                        if (!empty($cart_item['variation'])) {
                                            echo '<div class="cart-item-variation">';
                                            foreach ($cart_item['variation'] as $name => $value) {
                                                $taxonomy = wc_attribute_taxonomy_name(str_replace('attribute_pa_', '', urldecode($name)));
                                                $attribute_label = wc_attribute_label($taxonomy);
                                                echo '<span>' . esc_html($attribute_label) . ': ' . esc_html($value) . '</span>';
                                            }
                                            echo '</div>';
                                        }
                                        ?>
                                    </div>
                                    <div class="cart-item-price">
                                        <?php echo $subtotal; ?>
                                    </div>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                        
                        <!-- Order Totals -->
                        <div class="order-totals">
                            <div class="order-total-row subtotal">
                                <span class="label"><?php _e('Subtotal', 'yprint-plugin'); ?></span>
                                <span class="value"><?php echo $cart_subtotal; ?></span>
                            </div>
                            
                            <div class="order-total-row shipping">
                                <span class="label"><?php _e('Shipping', 'yprint-plugin'); ?></span>
                                <span class="value"><?php echo wc_price($cart_shipping); ?></span>
                            </div>
                            
                            <?php if ($cart_tax > 0) : ?>
                            <div class="order-total-row tax">
                                <span class="label"><?php _e('Tax', 'yprint-plugin'); ?></span>
                                <span class="value"><?php echo wc_price($cart_tax); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <div class="order-total-row total">
                                <span class="label"><?php _e('Total', 'yprint-plugin'); ?></span>
                                <span class="value"><?php echo wc_price($cart_total); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
</div>