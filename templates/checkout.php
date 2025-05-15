<?php
/**
 * YPrint Custom Checkout Template
 *
 * This template is modified to support saved addresses and updated payment layout.
 */
?>

<div class="yprint-checkout">
    <div class="yprint-checkout-container">
        <div class="yprint-checkout-messages"></div>

        <form id="yprint-checkout-form" class="yprint-checkout-form">
            <div class="yprint-checkout-columns">
                <div class="yprint-checkout-form-column">

                    <div class="yprint-saved-shipping-addresses-section">
                        <h2><?php _e('Choose Shipping Address', 'yprint-plugin'); ?></h2>
                        <div class="saved-addresses-list">
                            <?php
                            // --- START: Placeholder for displaying saved shipping addresses ---
                            // You will need to fetch saved shipping addresses (e.g., from user metadata 'additional_shipping_addresses') here.
                            // Example structure (replace with your actual data retrieval and loop):
                            $saved_shipping_addresses = get_user_meta( get_current_user_id(), 'additional_shipping_addresses', true );
                            $default_shipping_address_id = get_user_meta( get_current_user_id(), 'default_shipping_address', true );

                            if ( ! empty( $saved_shipping_addresses ) && is_array( $saved_shipping_addresses ) ) {
                                echo '<p>' . __('Select one of your saved addresses or enter a new one.', 'yprint-plugin') . '</p>';
                                foreach ( $saved_shipping_addresses as $address_id => $address ) {
                                    // Ensure required keys exist to prevent errors
                                    $address = wp_parse_args( $address, array(
                                        'title' => '',
                                        'address_1' => '',
                                        'postcode' => '',
                                        'city' => '',
                                        'company' => ''
                                    ) );
                                    $is_default = ( $address_id == $default_shipping_address_id );
                                    ?>
                                    <div class="saved-address-option">
                                        <label class="radio-container">
                                            <input type="radio" name="yprint_shipping_address_selection" value="<?php echo esc_attr($address_id); ?>" <?php checked( $is_default ); ?>>
                                            <span class="radio-checkmark"></span>
                                            <span class="address-title"><?php echo esc_html( !empty($address['title']) ? $address['title'] : __('Saved Address', 'yprint-plugin') ); ?></span>
                                            <div class="address-details">
                                                <?php echo esc_html( $address['address_1'] . ', ' . $address['postcode'] . ' ' . $address['city'] ); ?>
                                                <?php if ( ! empty( $address['company'] ) ) echo ' (' . esc_html( $address['company'] ) . ')'; ?>
                                            </div>
                                        </label>
                                    </div>
                                    <?php
                                }
                                // Option to enter a new address
                                ?>
                                <div class="saved-address-option">
                                    <label class="radio-container">
                                        <input type="radio" name="yprint_shipping_address_selection" value="new_address" <?php checked( empty( $default_shipping_address_id ) || !array_key_exists($default_shipping_address_id, $saved_shipping_addresses) ); ?>>
                                        <span class="radio-checkmark"></span>
                                        <span class="address-title"><?php _e('Enter a new address', 'yprint-plugin'); ?></span>
                                    </label>
                                </div>
                                <?php
                            } else {
                                // Message if no addresses are saved
                                ?>
                                <p class="no-saved-addresses"><?php _e('No shipping addresses saved yet. Please enter an address below.', 'yprint-plugin'); ?></p>
                                <input type="hidden" name="yprint_shipping_address_selection" value="new_address"> <?php
                            }
                            // --- END: Placeholder for displaying saved shipping addresses ---
                            ?>
                        </div>
                        <?php
                         // Link/Button to manage addresses (optional, depends on your user account page)
                         // echo '<a href="#">' . __('Manage Saved Addresses', 'yprint-plugin') . '</a>';
                        ?>
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
                         <?php
                         // Checkbox to save this new address for future use (optional, backend logic needed)
                         // echo '<div class="form-row"><div class="form-field full-width"><label class="checkbox-container"><input type="checkbox" name="save_shipping_address" value="1"><span class="checkmark"></span>' . __('Save this address for future use?', 'yprint-plugin') . '</label></div></div>';
                         ?>
                     </div>
                    <?php if (WC()->cart->needs_shipping() && WC()->cart->show_shipping()) : ?>
                    <h2><?php _e('Shipping Method', 'yprint-plugin'); ?></h2>
                    <div class="form-row shipping-methods-container">
                        <div class="shipping-methods-list">
                            <?php
                            // This section is typically updated via AJAX based on the selected shipping address.
                            // The initial display might show methods for the default address or base country.
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

                    <div class="form-row">
                         <div class="form-field full-width separate-billing-address-checkbox">
                             <label class="checkbox-container">
                                 <input id="ship_to_different_address" type="checkbox" name="ship_to_different_address" value="1">
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
                                 // --- START: Placeholder for displaying saved billing addresses ---
                                 // You will need to fetch saved billing addresses (e.g., from user metadata 'additional_billing_addresses') here.
                                 // Similar structure as shipping addresses.
                                 $saved_billing_addresses = get_user_meta( get_current_user_id(), 'additional_billing_addresses', true );
                                 $default_billing_address_id = get_user_meta( get_current_user_id(), 'default_billing_address', true ); // Assuming you add this meta field

                                 if ( ! empty( $saved_billing_addresses ) && is_array( $saved_billing_addresses ) ) {
                                     echo '<p>' . __('Select one of your saved addresses or enter a new one.', 'yprint-plugin') . '</p>';
                                     foreach ( $saved_billing_addresses as $address_id => $address ) {
                                          // Ensure required keys exist to prevent errors
                                         $address = wp_parse_args( $address, array(
                                             'title' => '',
                                             'address_1' => '',
                                             'postcode' => '',
                                             'city' => '',
                                             'company' => ''
                                         ) );
                                          $is_default = ( $address_id == $default_billing_address_id );
                                         ?>
                                         <div class="saved-address-option">
                                             <label class="radio-container">
                                                 <input type="radio" name="yprint_billing_address_selection" value="<?php echo esc_attr($address_id); ?>" <?php checked( $is_default ); ?>>
                                                 <span class="radio-checkmark"></span>
                                                  <span class="address-title"><?php echo esc_html( !empty($address['title']) ? $address['title'] : __('Saved Address', 'yprint-plugin') ); ?></span>
                                                  <div class="address-details">
                                                      <?php echo esc_html( $address['address_1'] . ', ' . $address['postcode'] . ' ' . $address['city'] ); ?>
                                                      <?php if ( ! empty( $address['company'] ) ) echo ' (' . esc_html( $address['company'] ) . ')'; ?>
                                                  </div>
                                             </label>
                                         </div>
                                         <?php
                                     }
                                     // Option to enter a new address
                                     ?>
                                     <div class="saved-address-option">
                                          <label class="radio-container">
                                              <input type="radio" name="yprint_billing_address_selection" value="new_address" <?php checked( empty( $default_billing_address_id ) || !array_key_exists($default_billing_address_id, $saved_billing_addresses) ); ?>>
                                              <span class="radio-checkmark"></span>
                                              <span class="address-title"><?php _e('Enter a new address', 'yprint-plugin'); ?></span>
                                          </label>
                                      </div>
                                     <?php
                                 } else {
                                     // Message if no addresses are saved
                                     ?>
                                     <p class="no-saved-addresses"><?php _e('No billing addresses saved yet. Please enter an address below.', 'yprint-plugin'); ?></p>
                                     <input type="hidden" name="yprint_billing_address_selection" value="new_address"> <?php
                                 }
                                 // --- END: Placeholder for displaying saved billing addresses ---
                                 ?>
                             </div>
                             <?php
                              // Link/Button to manage addresses (optional)
                              // echo '<a href="#">' . __('Manage Saved Addresses', 'yprint-plugin') . '</a>';
                             ?>
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
                             <?php
                             // Checkbox to save this new address for future use (optional, backend logic needed)
                             // echo '<div class="form-row"><div class="form-field full-width"><label class="checkbox-container"><input type="checkbox" name="save_billing_address" value="1"><span class="checkmark"></span>' . __('Save this address for future use?', 'yprint-plugin') . '</label></div></div>';
                             ?>
                         </div>
                         </div>
                    <div class="form-row">
                        <div class="form-field full-width">
                            <label for="order_comments"><?php _e('Order Notes', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                            <textarea id="order_comments" name="order_comments" class="input-text" placeholder="<?php _e('Notes about your order, e.g. special notes for delivery.', 'yprint-plugin'); ?>"></textarea>
                        </div>
                    </div>

                    <h2><?php _e('Payment Method', 'yprint-plugin'); ?></h2>

                    <div id="yprint-stripe-payment-request-wrapper">
                        <div id="yprint-stripe-payment-request-button"></div>
                    </div>

                    <div class="payment-method-separator">
                        <span><?php _e('OR', 'yprint-plugin'); ?></span>
                    </div>

                    <div class="credit-card-section">
                        <div class="form-row">
                            <div class="form-field full-width">
                                <label for="yprint-stripe-card-element"><?php _e('Credit Card', 'yprint-plugin'); ?></label>
                                <div id="yprint-stripe-card-element" class="stripe-card-element"></div>
                                <div id="yprint-stripe-card-errors" role="alert"></div>
                            </div>
                        </div>
                    </div>

                    <input type="hidden" id="payment_method_id" name="payment_method_id" value="">
                    <input type="hidden" name="payment_method" value="yprint_stripe"> <div class="form-row">
                        <button type="submit" id="yprint-place-order" class="checkout-button"><?php _e('Verbindlich Bestellen', 'yprint-plugin'); ?></button>
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
                                <div class="value"><?php echo wc_price(WC()->cart->get_subtotal()); // Use WC functions for live data ?></div>
                            </div>

                            <?php if (WC()->cart->needs_shipping()) : ?>
                            <div class="order-total-row shipping">
                                <div class="label"><?php _e('Shipping', 'yprint-plugin'); ?></div>
                                <div class="value"><?php echo wc_price(WC()->cart->get_shipping_total()); // Use WC functions for live data ?></div>
                            </div>
                            <?php endif; ?>

                            <?php if (wc_tax_enabled()) : ?>
                            <div class="order-total-row tax">
                                <div class="label"><?php _e('Tax', 'yprint-plugin'); ?></div>
                                <div class="value"><?php echo wc_price(WC()->cart->get_taxes_total()); // Use WC functions for live data ?></div>
                            </div>
                            <?php endif; ?>

                            <div class="order-total-row total">
                                <div class="label"><?php _e('Total', 'yprint-plugin'); ?></div>
                                <div class="value"><?php echo wc_price(WC()->cart->get_total('edit')); // Use WC functions for live data ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

