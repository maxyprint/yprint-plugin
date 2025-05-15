<?php
/**
 * YPrint Custom Checkout Template
 *
 * This template is modified to support saved addresses and updated payment layout.
 *
 * @package YPrint_Plugin
 * @subpackage Checkout_Template
 * @since 1.0.0
 *
 * @global WC_Cart $cart The WooCommerce cart object.
 * @global WC_Customer $customer The WooCommerce customer object.
 * @global array $countries Array of countries.
 * @global array $states Array of states.
 * @global array $saved_addresses Array of user's saved addresses.
 * @global string $default_shipping_address_id Default shipping address ID.
 * @global string $default_billing_address_id Default billing address ID.
 * @global string $currency_symbol The currency symbol.
 * @global string $cart_total The formatted cart total.
 * @global string $cart_subtotal The formatted cart subtotal.
 * @global string $cart_tax_total The formatted total tax.
 * @global string $cart_shipping_total The formatted shipping total.
 */

// Ensure WooCommerce is active and cart is not empty before rendering
if (!class_exists('WooCommerce') || WC()->cart->is_empty()) {
    // This template should ideally not be included if WC is not active or cart is empty,
    // but add a fallback message just in case.
    echo '<div class="yprint-checkout-error">' . esc_html__('Checkout is not available.', 'yprint-plugin') . '</div>';
    return; // Stop rendering the template
}

// Get global variables passed from the shortcode class
global $cart, $customer, $countries, $states, $saved_addresses,
       $default_shipping_address_id, $default_billing_address_id,
       $currency_symbol, $cart_total, $cart_subtotal, $cart_tax_total, $cart_shipping_total;

// Ensure variables are set (they should be if included by the shortcode class)
$cart = WC()->cart;
$customer = WC()->customer;
if (!isset($countries)) $countries = WC()->countries->get_countries();
if (!isset($states)) $states = WC()->countries->get_states(); // Note: states are loaded via AJAX by wc-country-select
if (!isset($saved_addresses)) $saved_addresses = array();
if (!isset($default_shipping_address_id)) $default_shipping_address_id = '';
if (!isset($default_billing_address_id)) $default_billing_address_id = '';
if (!isset($currency_symbol)) $currency_symbol = get_woocommerce_currency_symbol();
// Ensure totals are calculated and available
$cart->calculate_totals();
if (!isset($cart_total)) $cart_total = wc_price($cart->get_total());
if (!isset($cart_subtotal)) $cart_subtotal = $cart->get_cart_subtotal();
if (!isset($cart_tax_total)) $cart_tax_total = wc_price($cart->get_total_tax());
if (!isset($cart_shipping_total)) $cart_shipping_total = wc_price($cart->get_shipping_total());


$user_id = get_current_user_id();
$is_user_logged_in = ($user_id > 0);

?>

<div class="yprint-checkout">
    <div class="yprint-checkout-container">
        <div class="yprint-checkout-form-column">
            <div class="yprint-checkout-messages"></div>
            <form id="yprint-checkout-form" class="yprint-checkout-form">
                <?php wp_nonce_field('yprint-checkout', 'nonce'); // Security nonce field ?>
                
                <?php if ($is_user_logged_in): ?>
                <div class="checkout-header">
                    <h2><?php printf(__('Hello %s!', 'yprint-plugin'), $customer->get_first_name()); ?></h2>
                </div>
                <?php else: ?>
                <div class="checkout-header">
                    <h2><?php _e('Checkout', 'yprint-plugin'); ?></h2>
                </div>
                <?php endif; ?>

                <div class="yprint-saved-shipping-addresses-section">
                    <h2><?php _e('Choose Shipping Address', 'yprint-plugin'); ?></h2>
                    <div class="saved-addresses-list">
                        <?php
                        $saved_shipping_addresses = isset($saved_addresses['shipping']) ? $saved_addresses['shipping'] : array();

                        if ( ! empty( $saved_shipping_addresses ) ) : ?>
                            <?php foreach ( $saved_shipping_addresses as $address_id => $address ) :
                                // Ensure required keys exist to prevent errors (handled in get_user_saved_addresses)
                                $is_default = ( $address_id == $default_shipping_address_id );
                                $address_display = esc_html( $address['address_1'] . ', ' . $address['postcode'] . ' ' . $address['city'] );
                                if ( ! empty( $address['company'] ) ) $address_display .= ' (' . esc_html( $address['company'] ) . ')';
                                ?>
                                <div class="saved-address-option <?php echo $is_default ? 'active' : ''; ?>">
                                    <label class="radio-container">
                                        <input type="radio" name="yprint_shipping_address_selection" value="<?php echo esc_attr($address_id); ?>" <?php checked( $is_default ); ?>>
                                        <span class="radio-checkmark"></span>
                                    </label>
                                    <span class="address-title"><?php echo esc_html( !empty($address['title']) ? $address['title'] : __('Saved Address', 'yprint-plugin') ); ?></span>
                                    <div class="address-details">
                                        <?php echo esc_html( $address['first_name'] . ' ' . $address['last_name'] ); ?><br/>
                                        <?php echo $address_display; ?><br/>
                                        <?php echo esc_html( $address['country'] ); ?>
                                    </div>
                                </div>
                            <?php endforeach;

                            // Option to enter a new address
                            // Check if 'new_address' should be checked initially (no default or default not in saved list)
                            $check_new_shipping = empty( $default_shipping_address_id ) || !array_key_exists($default_shipping_address_id, $saved_shipping_addresses);
                            ?>
                            <div class="saved-address-option <?php echo $check_new_shipping ? 'active' : ''; ?>">
                                <label class="radio-container">
                                    <input type="radio" name="yprint_shipping_address_selection" value="new_address" <?php checked( $check_new_shipping ); ?>>
                                    <span class="radio-checkmark"></span>
                                </label>
                                <span class="address-title"><?php _e('Enter a new address', 'yprint-plugin'); ?></span>
                            </div>
                        <?php else :
                            // Message if no addresses are saved
                            ?>
                            <p class="no-saved-addresses"><?php _e('No shipping addresses saved yet. Please enter an address below.', 'yprint-plugin'); ?></p>
                            <input type="hidden" name="yprint_shipping_address_selection" value="new_address">
                        <?php endif; ?>
                    </div>
                </div>

                <div class="shipping-address-new-fields">
                     <h2><?php _e('Shipping Details (New Address)', 'yprint-plugin'); ?></h2>
                     <div class="form-row">
                         <div class="form-field full-width">
                             <label for="shipping_address_title"><?php _e('Address Title (e.g., Home, Work)', 'yprint-plugin'); ?></label>
                             <input type="text" id="shipping_address_title" name="shipping_address_title" class="input-text" value="">
                         </div>
                     </div>
                     <div class="form-row">
                         <div class="form-field half-width">
                             <label for="shipping_first_name"><?php _e('First Name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                             <input type="text" id="shipping_first_name" name="shipping_first_name" class="input-text" value="<?php echo esc_attr($customer->get_shipping_first_name()); ?>">
                         </div>

                         <div class="form-field half-width">
                             <label for="shipping_last_name"><?php _e('Last Name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                             <input type="text" id="shipping_last_name" name="shipping_last_name" class="input-text" value="<?php echo esc_attr($customer->get_shipping_last_name()); ?>">
                         </div>
                     </div>

                     <div class="form-row">
                         <div class="form-field full-width">
                             <label for="shipping_company"><?php _e('Company Name', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                             <input type="text" id="shipping_company" name="shipping_company" class="input-text" value="<?php echo esc_attr($customer->get_shipping_company()); ?>">
                         </div>
                     </div>

                     <div class="form-row">
                         <div class="form-field full-width">
                             <label for="shipping_country"><?php _e('Country / Region', 'yprint-plugin'); ?> <span class="required">*</span></label>
                             <select id="shipping_country" name="shipping_country" class="country-select">
                                 <option value=""><?php _e('Select a country / region', 'yprint-plugin'); ?></option>
                                 <?php // Populate with countries
                                 foreach ($countries as $code => $name) :
                                     echo '<option value="' . esc_attr($code) . '" ' . selected($customer->get_shipping_country(), $code, false) . '>' . esc_html($name) . '</option>';
                                 endforeach; ?>
                             </select>
                         </div>
                     </div>

                     <div class="form-row">
                         <div class="form-field full-width">
                             <label for="shipping_address_1"><?php _e('Street Address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                             <input type="text" id="shipping_address_1" name="shipping_address_1" class="input-text" placeholder="<?php _e('House number and street name', 'yprint-plugin'); ?>" value="<?php echo esc_attr($customer->get_shipping_address_1()); ?>">
                         </div>
                     </div>

                     <div class="form-row">
                         <div class="form-field full-width">
                             <label for="shipping_address_2"><?php _e('Apartment, suite, unit, etc.', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                             <input type="text" id="shipping_address_2" name="shipping_address_2" class="input-text" placeholder="<?php _e('Apartment, suite, unit, etc. (optional)', 'yprint-plugin'); ?>" value="<?php echo esc_attr($customer->get_shipping_address_2()); ?>">
                         </div>
                     </div>

                     <div class="form-row">
                         <div class="form-field half-width">
                             <label for="shipping_postcode"><?php _e('Postcode / ZIP', 'yprint-plugin'); ?> <span class="required">*</span></label>
                             <input type="text" id="shipping_postcode" name="shipping_postcode" class="input-text" value="<?php echo esc_attr($customer->get_shipping_postcode()); ?>">
                         </div>

                         <div class="form-field half-width">
                             <label for="shipping_city"><?php _e('Town / City', 'yprint-plugin'); ?> <span class="required">*</span></label>
                             <input type="text" id="shipping_city" name="shipping_city" class="input-text" value="<?php echo esc_attr($customer->get_shipping_city()); ?>">
                         </div>
                     </div>

                     <div class="form-row">
                         <div class="form-field full-width">
                             <label for="shipping_state"><?php _e('State / County', 'yprint-plugin'); ?></label>
                             <select id="shipping_state" name="shipping_state" class="state-select">
                                 <option value=""><?php _e('Select a state / county', 'yprint-plugin'); ?></option>
                                 <?php
                                 // States are typically loaded via AJAX by wc-country-select,
                                 // but we can pre-populate if the customer already has one set for the default country.
                                 $current_shipping_country = $customer->get_shipping_country();
                                 if ($current_shipping_country && isset($states[$current_shipping_country])) {
                                     foreach ($states[$current_shipping_country] as $code => $name) {
                                         echo '<option value="' . esc_attr($code) . '" ' . selected($customer->get_shipping_state(), $code, false) . '>' . esc_html($name) . '</option>';
                                     }
                                 }
                                 ?>
                             </select>
                         </div>
                     </div>
                     <?php
                     // Optional: Checkbox to save this new address for future use (requires backend logic)
                     if ($is_user_logged_in) {
                        // echo '<div class="form-row"><div class="form-field full-width"><label class="checkbox-container"><input type="checkbox" name="save_shipping_address" value="1"><span class="checkmark"></span>' . __('Save this address for future use?', 'yprint-plugin') . '</label></div></div>';
                     }
                     ?>
                 </div>

                <?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>
                <h2><?php _e('Shipping Method', 'yprint-plugin'); ?></h2>
                <div class="form-row shipping-methods-container">
                    <div class="shipping-methods-list">
                        <?php
                        // This section is typically updated via AJAX based on the selected shipping address.
                        // The initial display might show methods for the default address or base country.
                        // The JS will replace this content after the initial updateCheckout call.
                        ?>
                        <p><?php _e('Loading shipping methods...', 'yprint-plugin'); ?></p>
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-row">
                     <div class="form-field full-width separate-billing-address-checkbox">
                         <label class="checkbox-container">
                             <input id="ship_to_different_address" type="checkbox" name="ship_to_different_address" value="1" <?php checked( apply_filters('woocommerce_ship_to_different_address_checked', false) ); // Default unchecked ?>>
                             <span class="checkmark"></span>
                             <?php _e('Set a separate billing address', 'yprint-plugin'); ?>
                         </label>
                     </div>
                 </div>


                <div class="billing-address-section">
                     <h2><?php _e('Billing Details', 'yprint-plugin'); ?></h2>

                     <div class="billing-address-same-as-shipping" style="display: none;">
                         <p><?php _e('Billing address is the same as shipping address.', 'yprint-plugin'); ?></p>
                         </div>

                     <div class="yprint-saved-billing-addresses-section" style="display: none;">
                         <h2><?php _e('Choose Billing Address', 'yprint-plugin'); ?></h2>
                         <div class="saved-addresses-list">
                             <?php
                             $saved_billing_addresses = isset($saved_addresses['billing']) ? $saved_addresses['billing'] : array();

                             if ( ! empty( $saved_billing_addresses ) ) : ?>
                                 <p><?php _e('Select one of your saved addresses or enter a new one.', 'yprint-plugin'); ?></p>
                                 <?php foreach ( $saved_billing_addresses as $address_id => $address ) :
                                      // Ensure required keys exist (handled in get_user_saved_addresses)
                                       $is_default = ( $address_id == $default_billing_address_id );
                                       $address_display = esc_html( $address['address_1'] . ', ' . $address['postcode'] . ' ' . $address['city'] );
                                       if ( ! empty( $address['company'] ) ) $address_display .= ' (' . esc_html( $address['company'] ) . ')';
                                      ?>
                                      <div class="saved-address-option <?php echo $is_default ? 'active' : ''; ?>">
                                          <label class="radio-container">
                                              <input type="radio" name="yprint_billing_address_selection" value="<?php echo esc_attr($address_id); ?>" <?php checked( $is_default ); ?>>
                                              <span class="radio-checkmark"></span>
                                          </label>
                                          <span class="address-title"><?php echo esc_html( !empty($address['title']) ? $address['title'] : __('Saved Address', 'yprint-plugin') ); ?></span>
                                          <div class="address-details">
                                              <?php echo esc_html( $address['first_name'] . ' ' . $address['last_name'] ); ?><br/>
                                              <?php echo $address_display; ?><br/>
                                              <?php echo esc_html( $address['country'] ); ?>
                                          </div>
                                      </div>
                                  <?php endforeach;

                                  // Option to enter a new address
                                  // Check if 'new_address' should be checked initially
                                  $check_new_billing = empty( $default_billing_address_id ) || !array_key_exists($default_billing_address_id, $saved_billing_addresses);
                                  ?>
                                  <div class="saved-address-option <?php echo $check_new_billing ? 'active' : ''; ?>">
                                       <label class="radio-container">
                                           <input type="radio" name="yprint_billing_address_selection" value="new_address" <?php checked( $check_new_billing ); ?>>
                                           <span class="radio-checkmark"></span>
                                       </label>
                                       <span class="address-title"><?php _e('Enter a new address', 'yprint-plugin'); ?></span>
                                   </div>
                              <?php else :
                                  // Message if no addresses are saved
                                  ?>
                                  <p class="no-saved-addresses"><?php _e('No billing addresses saved yet. Please enter an address below.', 'yprint-plugin'); ?></p>
                                   <input type="hidden" name="yprint_billing_address_selection" value="new_address">
                              <?php endif; ?>
                         </div>
                     </div>

                     <div class="billing-address-new-fields" style="display: none;">
                          <h2><?php _e('Billing Details (New Address)', 'yprint-plugin'); ?></h2>
                          <div class="form-row">
                              <div class="form-field full-width">
                                  <label for="billing_address_title"><?php _e('Address Title (e.g., Home, Work)', 'yprint-plugin'); ?></label>
                                  <input type="text" id="billing_address_title" name="billing_address_title" class="input-text" value="">
                              </div>
                          </div>
                         <div class="form-row">
                             <div class="form-field half-width">
                                 <label for="billing_first_name"><?php _e('First Name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                 <input type="text" id="billing_first_name" name="billing_first_name" class="input-text" value="<?php echo esc_attr($customer->get_billing_first_name()); ?>" required>
                             </div>

                             <div class="form-field half-width">
                                 <label for="billing_last_name"><?php _e('Last Name', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                 <input type="text" id="billing_last_name" name="billing_last_name" class="input-text" value="<?php echo esc_attr($customer->get_billing_last_name()); ?>" required>
                             </div>
                         </div>

                         <div class="form-row">
                             <div class="form-field full-width">
                                 <label for="billing_company"><?php _e('Company Name', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                                 <input type="text" id="billing_company" name="billing_company" class="input-text" value="<?php echo esc_attr($customer->get_billing_company()); ?>">
                             </div>
                         </div>

                         <div class="form-row">
                             <div class="form-field full-width">
                                 <label for="billing_country"><?php _e('Country / Region', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                 <select id="billing_country" name="billing_country" class="country-select" required>
                                     <option value=""><?php _e('Select a country / region', 'yprint-plugin'); ?></option>
                                     <?php
                                     foreach ($countries as $code => $name) :
                                         echo '<option value="' . esc_attr($code) . '" ' . selected($customer->get_billing_country(), $code, false) . '>' . esc_html($name) . '</option>';
                                     endforeach; ?>
                                 </select>
                             </div>
                         </div>

                         <div class="form-row">
                             <div class="form-field full-width">
                                 <label for="billing_address_1"><?php _e('Street Address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                 <input type="text" id="billing_address_1" name="billing_address_1" class="input-text" placeholder="<?php _e('House number and street name', 'yprint-plugin'); ?>" value="<?php echo esc_attr($customer->get_billing_address_1()); ?>" required>
                             </div>
                         </div>

                         <div class="form-row">
                             <div class="form-field full-width">
                                 <label for="billing_address_2"><?php _e('Apartment, suite, unit, etc.', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                                 <input type="text" id="billing_address_2" name="billing_address_2" class="input-text" placeholder="<?php _e('Apartment, suite, unit, etc. (optional)', 'yprint-plugin'); ?>" value="<?php echo esc_attr($customer->get_billing_address_2()); ?>">
                             </div>
                         </div>

                         <div class="form-row">
                             <div class="form-field half-width">
                                 <label for="billing_postcode"><?php _e('Postcode / ZIP', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                 <input type="text" id="billing_postcode" name="billing_postcode" class="input-text" value="<?php echo esc_attr($customer->get_billing_postcode()); ?>" required>
                             </div>

                             <div class="form-field half-width">
                                 <label for="billing_city"><?php _e('Town / City', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                 <input type="text" id="billing_city" name="billing_city" class="input-text" value="<?php echo esc_attr($customer->get_billing_city()); ?>" required>
                             </div>
                         </div>

                         <div class="form-row">
                             <div class="form-field full-width">
                                 <label for="billing_state"><?php _e('State / County', 'yprint-plugin'); ?></label>
                                 <select id="billing_state" name="billing_state" class="state-select">
                                     <option value=""><?php _e('Select a state / county', 'yprint-plugin'); ?></option>
                                     <?php
                                     // States are typically loaded via AJAX by wc-country-select
                                     $current_billing_country = $customer->get_billing_country();
                                     if ($current_billing_country && isset($states[$current_billing_country])) {
                                         foreach ($states[$current_billing_country] as $code => $name) {
                                             echo '<option value="' . esc_attr($code) . '" ' . selected($customer->get_billing_state(), $code, false) . '>' . esc_html($name) . '</option>';
                                         }
                                     }
                                     ?>
                                 </select>
                             </div>
                         </div>
                         <div class="form-row">
                             <div class="form-field half-width">
                                 <label for="billing_phone"><?php _e('Phone', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                 <input type="tel" id="billing_phone" name="billing_phone" class="input-text" value="<?php echo esc_attr($customer->get_billing_phone()); ?>" required>
                             </div>

                             <div class="form-field half-width">
                                 <label for="billing_email"><?php _e('Email Address', 'yprint-plugin'); ?> <span class="required">*</span></label>
                                 <input type="email" id="billing_email" name="billing_email" class="input-text" value="<?php echo esc_attr($customer->get_billing_email()); ?>" required>
                             </div>
                         </div>
                         <?php
                         // Optional: Checkbox to save this new address for future use (requires backend logic)
                          if ($is_user_logged_in) {
                             // echo '<div class="form-row"><div class="form-field full-width"><label class="checkbox-container"><input type="checkbox" name="save_billing_address" value="1"><span class="checkmark"></span>' . __('Save this address for future use?', 'yprint-plugin') . '</label></div></div>';
                          }
                         ?>
                     </div>
                     </div>
                <div class="form-row">
                    <div class="form-field full-width">
                        <label for="order_comments"><?php _e('Order Notes', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                        <textarea id="order_comments" name="order_comments" class="input-text" placeholder="<?php esc_attr_e('Notes about your order, e.g. special notes for delivery.', 'yprint-plugin'); ?>"></textarea>
                    </div>
                </div>

                <h2><?php _e('Payment Method', 'yprint-plugin'); ?></h2>

                <div class="express-checkout">
                    <div id="yprint-stripe-payment-request-wrapper" style="display: none;">
                        <div id="yprint-stripe-payment-request-button"></div>
                    </div>

                    <div class="payment-method-separator">
                        <span><?php _e('OR', 'yprint-plugin'); ?></span>
                    </div>

                    <div class="credit-card-section">
                        <div class="card-input-section">
                            <div id="yprint-stripe-card-element" class="stripe-card-element"></div>
                        </div>
                        <div id="yprint-stripe-card-errors" role="alert"></div>
                    </div>
                </div>

                <input type="hidden" id="payment_method_id" name="payment_method_id" value="">
                <input type="hidden" name="payment_method" value=""> 
                
                <button type="submit" id="yprint-place-order" class="checkout-button"><?php _e('Place Order', 'yprint-plugin'); ?></button>
            </form>
        </div>
        
        <div class="yprint-checkout-summary-column">
            <div class="yprint-order-summary">
                <h2><?php _e('Order Summary', 'yprint-plugin'); ?></h2>

                <div class="cart-items">
                    <?php
                    // Loop through items in the WooCommerce cart
                    foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) :
                        $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                        $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

                        // Check if product exists, is visible, and has quantity > 0
                        if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) :
                            $product_name = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
                            $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                            $product_price = WC()->cart->get_product_price($_product); // Get single item price
                            ?>
                            <div class="cart-item">
                                <div class="cart-item-image">
                                    <?php echo $thumbnail; // Escaped by WC function ?>
                                    <span class="item-quantity"><?php echo esc_html($cart_item['quantity']); ?></span>
                                </div>
                                <div class="cart-item-details">
                                    <div class="cart-item-name">
                                        <?php echo wp_kses_post($product_name); ?><br/>
                                        <small><?php echo esc_html($cart_item['quantity']); ?>x <?php echo wp_kses_post($product_price); ?></small>
                                    </div>
                                    <?php if (!empty($cart_item['variation'])) : ?>
                                        <div class="cart-item-variation">
                                            <?php
                                            foreach ($cart_item['variation'] as $attr => $value) :
                                                $label = wc_attribute_label(str_replace('attribute_', '', urldecode($attr)));
                                                $taxonomy = wc_attribute_taxonomy_name(str_replace('attribute_pa_', '', urldecode($attr)));
                                                $term = taxonomy_exists($taxonomy) ? get_term_by('slug', $value, $taxonomy) : false;
                                                $display_value = $term && !is_wp_error($term) ? $term->name : $value;
                                                echo esc_html($label) . ': ' . esc_html($display_value) . '<br>';
                                            endforeach;
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endif;
                    endforeach; ?>
                </div>

                <div class="order-totals">
                    <div class="order-total-row subtotal">
                        <div class="label"><?php _e('Subtotal', 'yprint-plugin'); ?></div>
                        <div class="value"><?php echo wp_kses_post($cart_subtotal); ?></div>
                    </div>

                    <?php if (WC()->cart->needs_shipping()) : ?>
                    <div class="order-total-row shipping">
                        <div class="label"><?php _e('Shipping', 'yprint-plugin'); ?></div>
                        <div class="value"><?php echo wp_kses_post($cart_shipping_total); ?></div>
                    </div>
                    <?php endif; ?>

                    <?php if (wc_tax_enabled()) : ?>
                    <div class="order-total-row tax">
                        <div class="label"><?php _e('Tax', 'yprint-plugin'); ?></div>
                        <div class="value"><?php echo wp_kses_post($cart_tax_total); ?></div>
                    </div>
                    <?php endif; ?>

                    <hr />
                    <div class="order-total-row total">
                        <div class="label"><?php _e('Total', 'yprint-plugin'); ?></div>
                        <div class="value"><?php echo wp_kses_post($cart_total); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>