<?php
/**
 * YPrint Custom Checkout Template
 */
?>

<div class="yprint-checkout">
    <div class="yprint-checkout-container">
        <div class="yprint-checkout-messages"></div>
        
        <form id="yprint-checkout-form" class="yprint-checkout-form">
            <div class="yprint-checkout-columns">
                <div class="yprint-checkout-form-column">
                    <h2><?php _e('Billing Details', 'yprint-plugin'); ?></h2>
                    
                    <div class="form-row">
                        <div class="form-field half-width">
                            <label for="billing_first_name"><?php _e('First Name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                            <input type="text" id="billing_first_name" name="billing_first_name" class="input-text" value="<?php echo isset($saved_addresses['billing']['first_name']) ? esc_attr($saved_addresses['billing']['first_name']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-field half-width">
                            <label for="billing_last_name"><?php _e('Last Name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                            <input type="text" id="billing_last_name" name="billing_last_name" class="input-text" value="<?php echo isset($saved_addresses['billing']['last_name']) ? esc_attr($saved_addresses['billing']['last_name']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="billing_company"><?php _e('Company Name', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                            <input type="text" id="billing_company" name="billing_company" class="input-text" value="<?php echo isset($saved_addresses['billing']['company']) ? esc_attr($saved_addresses['billing']['company']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="billing_country"><?php _e('Country / Region', 'yprint-plugin'); ?> <span class="required">*</span></label>
                            <select id="billing_country" name="billing_country" class="country-select" required>
                                <option value=""><?php _e('Select a country / region', 'yprint-plugin'); ?></option>
                                <?php foreach ($countries as $code => $name) : ?>
                                    <option value="<?php echo esc_attr($code); ?>" <?php selected(isset($saved_addresses['billing']['country']) ? $saved_addresses['billing']['country'] : WC()->countries->get_base_country(), $code); ?>><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="billing_address_1"><?php _e('Street Address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                            <input type="text" id="billing_address_1" name="billing_address_1" class="input-text" placeholder="<?php _e('House number and street name', 'yprint-plugin'); ?>" value="<?php echo isset($saved_addresses['billing']['address_1']) ? esc_attr($saved_addresses['billing']['address_1']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="billing_address_2"><?php _e('Apartment, suite, unit, etc.', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                            <input type="text" id="billing_address_2" name="billing_address_2" class="input-text" placeholder="<?php _e('Apartment, suite, unit, etc. (optional)', 'yprint-plugin'); ?>" value="<?php echo isset($saved_addresses['billing']['address_2']) ? esc_attr($saved_addresses['billing']['address_2']) : ''; ?>">
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field half-width">
                            <label for="billing_postcode"><?php _e('Postcode / ZIP', 'yprint-plugin'); ?> <span class="required">*</span></label>
                            <input type="text" id="billing_postcode" name="billing_postcode" class="input-text" value="<?php echo isset($saved_addresses['billing']['postcode']) ? esc_attr($saved_addresses['billing']['postcode']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-field half-width">
                            <label for="billing_city"><?php _e('Town / City', 'yprint-plugin'); ?> <span class="required">*</span></label>
                            <input type="text" id="billing_city" name="billing_city" class="input-text" value="<?php echo isset($saved_addresses['billing']['city']) ? esc_attr($saved_addresses['billing']['city']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="billing_state"><?php _e('State / County', 'yprint-plugin'); ?></label>
                            <select id="billing_state" name="billing_state" class="state-select">
                                <option value=""><?php _e('Select a state / county', 'yprint-plugin'); ?></option>
                                <?php
                                $country = isset($saved_addresses['billing']['country']) ? $saved_addresses['billing']['country'] : WC()->countries->get_base_country();
                                if (isset($states[$country])) {
                                    foreach ($states[$country] as $code => $name) {
                                        echo '<option value="' . esc_attr($code) . '" ' . selected(isset($saved_addresses['billing']['state']) ? $saved_addresses['billing']['state'] : '', $code, false) . '>' . esc_html($name) . '</option>';
                                    }
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field half-width">
                            <label for="billing_phone"><?php _e('Phone', 'yprint-plugin'); ?> <span class="required">*</span></label>
                            <input type="tel" id="billing_phone" name="billing_phone" class="input-text" value="<?php echo isset($saved_addresses['billing']['phone']) ? esc_attr($saved_addresses['billing']['phone']) : ''; ?>" required>
                        </div>
                        
                        <div class="form-field half-width">
                            <label for="billing_email"><?php _e('Email Address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                            <input type="email" id="billing_email" name="billing_email" class="input-text" value="<?php echo isset($saved_addresses['billing']['email']) ? esc_attr($saved_addresses['billing']['email']) : ''; ?>" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width ship-to-different-address">
                            <label class="checkbox-container">
                                <input id="ship_to_different_address" type="checkbox" name="ship_to_different_address" value="1">
                                <span class="checkmark"></span>
                                <?php _e('Ship to a different address?', 'yprint-plugin'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="shipping-address" style="display: none;">
                        <h2><?php _e('Shipping Details', 'yprint-plugin'); ?></h2>
                        
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="shipping_first_name"><?php _e('First Name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_first_name" name="shipping_first_name" class="input-text" value="<?php echo isset($saved_addresses['shipping']['first_name']) ? esc_attr($saved_addresses['shipping']['first_name']) : ''; ?>">
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="shipping_last_name"><?php _e('Last Name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_last_name" name="shipping_last_name" class="input-text" value="<?php echo isset($saved_addresses['shipping']['last_name']) ? esc_attr($saved_addresses['shipping']['last_name']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="shipping_company"><?php _e('Company Name', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                                <input type="text" id="shipping_company" name="shipping_company" class="input-text" value="<?php echo isset($saved_addresses['shipping']['company']) ? esc_attr($saved_addresses['shipping']['company']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="shipping_country"><?php _e('Country / Region', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <select id="shipping_country" name="shipping_country" class="country-select">
                                    <option value=""><?php _e('Select a country / region', 'yprint-plugin'); ?></option>
                                    <?php foreach ($countries as $code => $name) : ?>
                                        <option value="<?php echo esc_attr($code); ?>" <?php selected(isset($saved_addresses['shipping']['country']) ? $saved_addresses['shipping']['country'] : WC()->countries->get_base_country(), $code); ?>><?php echo esc_html($name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="shipping_address_1"><?php _e('Street Address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_address_1" name="shipping_address_1" class="input-text" placeholder="<?php _e('House number and street name', 'yprint-plugin'); ?>" value="<?php echo isset($saved_addresses['shipping']['address_1']) ? esc_attr($saved_addresses['shipping']['address_1']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="shipping_address_2"><?php _e('Apartment, suite, unit, etc.', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                                <input type="text" id="shipping_address_2" name="shipping_address_2" class="input-text" placeholder="<?php _e('Apartment, suite, unit, etc. (optional)', 'yprint-plugin'); ?>" value="<?php echo isset($saved_addresses['shipping']['address_2']) ? esc_attr($saved_addresses['shipping']['address_2']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field half-width">
                                <label for="shipping_postcode"><?php _e('Postcode / ZIP', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_postcode" name="shipping_postcode" class="input-text" value="<?php echo isset($saved_addresses['shipping']['postcode']) ? esc_attr($saved_addresses['shipping']['postcode']) : ''; ?>">
                            </div>
                            
                            <div class="form-field half-width">
                                <label for="shipping_city"><?php _e('Town / City', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                <input type="text" id="shipping_city" name="shipping_city" class="input-text" value="<?php echo isset($saved_addresses['shipping']['city']) ? esc_attr($saved_addresses['shipping']['city']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="shipping_state"><?php _e('State / County', 'yprint-plugin'); ?></label>
                                <select id="shipping_state" name="shipping_state" class="state-select">
                                    <option value=""><?php _e('Select a state / county', 'yprint-plugin'); ?></option>
                                    <?php
                                    $country = isset($saved_addresses['shipping']['country']) ? $saved_addresses['shipping']['country'] : WC()->countries->get_base_country();
                                    if (isset($states[$country])) {
                                        foreach ($states[$country] as $code => $name) {
                                            echo '<option value="' . esc_attr($code) . '" ' . selected(isset($saved_addresses['shipping']['state']) ? $saved_addresses['shipping']['state'] : '', $code, false) . '>' . esc_html($name) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="order_comments"><?php _e('Order Notes', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                            <textarea id="order_comments" name="order_comments" class="input-text" placeholder="<?php _e('Notes about your order, e.g. special notes for delivery.', 'yprint-plugin'); ?>"></textarea>
                        </div>
                    </div>
                    
                    <?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>
                    <h2><?php _e('Shipping Method', 'yprint-plugin'); ?></h2>
                    <div class="form-row shipping-methods-container">
                        <div class="shipping-methods-list">
                            <?php
                            $packages = WC()->shipping->get_packages();
                            
                            if (!empty($packages)) {
                                foreach ($packages as $package_key => $package) {
                                    if (!empty($package['rates'])) {
                                        foreach ($package['rates'] as $key => $rate) {
                                            ?>
                                            <div class="shipping-method">
                                                <label class="radio-container">
                                                    <input type="radio" name="shipping_method" id="shipping_method_<?php echo esc_attr($rate->id); ?>" value="<?php echo esc_attr($rate->id); ?>" class="shipping-method-input" <?php checked($package_key . ':' . $rate->id, WC()->session->get('chosen_shipping_methods')[$package_key] ?? ''); ?>>
                                                    <span class="radio-checkmark"></span>
                                                    <span class="shipping-method-label"><?php echo esc_html($rate->label); ?> - <?php echo wc_price($rate->cost); ?></span>
                                                </label>
                                            </div>
                                            <?php
                                        }
                                    } else {
                                        ?>
                                        <p class="no-shipping-methods"><?php _e('No shipping methods available for your location. Please check your address and try again.', 'yprint-plugin'); ?></p>
                                        <?php
                                    }
                                }
                            }
                            ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <h2><?php _e('Payment Method', 'yprint-plugin'); ?></h2>
                    
                    <!-- Payment Request Button (Apple Pay, Google Pay, etc.) -->
                    <div id="yprint-stripe-payment-request-wrapper" style="display: none;">
                        <div id="yprint-stripe-payment-request-button"></div>
                    </div>
                    
                    <!-- Payment Method Separator -->
                    <div class="payment-method-separator" style="display: none;">
                        <span><?php _e('OR', 'yprint-plugin'); ?></span>
                    </div>
                    
                    <!-- Standard Credit Card Input -->
                    <div class="credit-card-section">
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="yprint-stripe-card-element"><?php _e('Credit Card', 'yprint-plugin'); ?></label>
                                <div id="yprint-stripe-card-element" class="stripe-card-element"></div>
                                <div id="yprint-stripe-card-errors" role="alert"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Hidden Payment Method ID field -->
                    <input type="hidden" id="payment_method_id" name="payment_method_id" value="">
                    <input type="hidden" name="payment_method" value="yprint_stripe">
                    
                    <div class="form-row">
                        <button type="submit" id="yprint-place-order" class="checkout-button"><?php _e('Place Order', 'yprint-plugin'); ?></button>
                    </div>
                </div>
                
                <div class="yprint-checkout-summary-column">
                    <div class="yprint-order-summary">
                        <h2><?php _e('Your Order', 'yprint-plugin'); ?></h2>
                        
                        <div class="cart-items">
                            <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) : 
                                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                                $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);
                                
                                if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) {
                                    $product_name = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
                                    $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                                    $product_price = apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key);
                                    $product_subtotal = apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key);
                                    ?>
                                    <div class="cart-item">
                                        <div class="cart-item-image">
                                            <?php echo $thumbnail; ?>
                                            <span class="item-quantity"><?php echo $cart_item['quantity']; ?></span>
                                        </div>
                                        <div class="cart-item-details">
                                            <div class="cart-item-name"><?php echo $product_name; ?></div>
                                            <?php
                                            // Display variation data
                                            if (!empty($cart_item['variation'])) {
                                                echo '<div class="cart-item-variation">';
                                                foreach ($cart_item['variation'] as $attr => $value) {
                                                    $taxonomy = wc_attribute_taxonomy_name(str_replace('attribute_pa_', '', urldecode($attr)));
                                                    $term = get_term_by('slug', $value, $taxonomy);
                                                    $label = wc_attribute_label(str_replace('attribute_', '', urldecode($attr)));
                                                    $display_value = $term ? $term->name : $value;
                                                    echo $label . ': ' . $display_value . '<br>';
                                                }
                                                echo '</div>';
                                            }
                                            ?>
                                        </div>
                                        <div class="cart-item-price"><?php echo $product_subtotal; ?></div>
                                    </div>
                                <?php
                                }
                            endforeach; ?>
                        </div>
                        
                        <div class="order-totals">
                            <div class="order-total-row subtotal">
                                <div class="label"><?php _e('Subtotal', 'yprint-plugin'); ?></div>
                                <div class="value"><?php echo $cart_subtotal; ?></div>
                            </div>
                            
                            <?php if (WC()->cart->needs_shipping()) : ?>
                            <div class="order-total-row shipping">
                                <div class="label"><?php _e('Shipping', 'yprint-plugin'); ?></div>
                                <div class="value"><?php echo wc_price($cart_shipping); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (wc_tax_enabled()) : ?>
                            <div class="order-total-row tax">
                                <div class="label"><?php _e('Tax', 'yprint-plugin'); ?></div>
                                <div class="value"><?php echo wc_price($cart_tax); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="order-total-row total">
                                <div class="label"><?php _e('Total', 'yprint-plugin'); ?></div>
                                <div class="value"><?php echo wc_price($cart_total); ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>