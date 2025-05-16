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

// Prepare saved shipping addresses for JavaScript
$saved_shipping_addresses_json = json_encode(isset($saved_addresses['shipping']) ? $saved_addresses['shipping'] : array());

?>

<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Optional: Custom styles if needed, though Tailwind should suffice */
        .modal {
            display: none; /* Initially hidden */
        }
        .modal.open {
            display: flex; /* Show when open */
        }
        /* Style for the selected address block */
        .address-block.selected {
            border-color: #3b82f6; /* blue-500 */
            font-weight: bold;
        }
         /* Style for non-selected address block */
        .address-block {
             color: #6b7280; /* gray-500 */
        }
        .address-block.selected h3,
        .address-block.selected p {
             color: #1f2937; /* gray-800 */
        }
        /* Basic layout for the two columns */
        .yprint-checkout-container {
            display: flex;
            flex-wrap: wrap; /* Allow wrapping on smaller screens */
            gap: 24px; /* Gap between columns */
        }
        .yprint-checkout-form-column {
            flex: 1 1 60%; /* Takes 60% of space, can shrink and grow */
            min-width: 300px; /* Minimum width before wrapping */
        }
        .yprint-checkout-summary-column {
            flex: 1 1 35%; /* Takes 35% of space, can shrink and grow */
            min-width: 250px; /* Minimum width before wrapping */
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .yprint-checkout-container {
                flex-direction: column; /* Stack columns on small screens */
            }
            .yprint-checkout-form-column,
            .yprint-checkout-summary-column {
                flex-basis: 100%; /* Full width on small screens */
            }
        }
    </style>
</head>
<body class="bg-gray-100 font-sans leading-normal tracking-normal">

    <div class="yprint-checkout container mx-auto p-6">
        <div class="yprint-checkout-container">
            <div class="yprint-checkout-form-column">
                <div class="yprint-checkout-messages"></div>
                <form id="yprint-checkout-form" class="yprint-checkout-form">
                    <?php wp_nonce_field('yprint-checkout', 'nonce'); // Security nonce field ?>

                    <?php if ($is_user_logged_in): ?>
                    <div class="checkout-header mb-6">
                        <h2 class="text-2xl font-bold text-gray-800"><?php printf(__('Hello %s!', 'yprint-plugin'), $customer->get_first_name()); ?></h2>
                    </div>
                    <?php else: ?>
                    <div class="checkout-header mb-6">
                        <h2 class="text-2xl font-bold text-gray-800"><?php _e('Checkout', 'yprint-plugin'); ?></h2>
                    </div>
                    <?php endif; ?>

                    <div class="yprint-shipping-address-section mb-6">
                         <h2 class="text-2xl font-bold mb-6 text-gray-800">Ihre Adressen</h2>

                        <div id="saved-addresses" class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                            </div>

                        <div id="no-addresses-placeholder" class="bg-white p-6 rounded-lg shadow-md border border-gray-200 text-center cursor-pointer hover:border-blue-500 transition duration-300 ease-in-out">
                            <p class="text-gray-600 text-lg">Es sind noch keine Adressen gespeichert.</p>
                            <p class="text-blue-500 mt-2">Klicken Sie hier, um eine neue Adresse hinzuzufügen.</p>
                        </div>
                    </div>
                    <div class="form-row mb-6">
                         <div class="form-field full-width separate-billing-address-checkbox">
                             <label class="checkbox-container">
                                 <input id="ship_to_different_address" type="checkbox" name="ship_to_different_address" value="1" <?php checked( apply_filters('woocommerce_ship_to_different_address_checked', false) ); // Default unchecked ?>>
                                 <span class="checkmark"></span>
                                 <?php _e('Set a separate billing address', 'yprint-plugin'); ?>
                             </label>
                         </div>
                     </div>

                    <div class="billing-address-section mb-6">
                         <h2 class="text-2xl font-bold mb-6 text-gray-800"><?php _e('Billing Details', 'yprint-plugin'); ?></h2>

                         <div class="billing-address-same-as-shipping" style="display: none;">
                             <p><?php _e('Billing address is the same as shipping address.', 'yprint-plugin'); ?></p>
                             </div>

                         <div class="yprint-saved-billing-addresses-section" style="display: none;">
                             <h3 class="text-xl font-bold mb-4 text-gray-800"><?php _e('Choose Billing Address', 'yprint-plugin'); ?></h3>
                             <div class="saved-addresses-list">
                                 <?php
                                 // PHP logic for saved billing addresses remains here for now,
                                 // but consider handling this with JS as well for consistency
                                 $saved_billing_addresses = isset($saved_addresses['billing']) ? $saved_addresses['billing'] : array();

                                 if ( ! empty( $saved_billing_addresses ) ) : ?>
                                     <p class="text-gray-600 mb-4"><?php _e('Select one of your saved addresses or enter a new one.', 'yprint-plugin'); ?></p>
                                     <?php foreach ( $saved_billing_addresses as $address_id => $address ) :
                                          // Ensure required keys exist (handled in get_user_saved_addresses)
                                           $is_default = ( $address_id == $default_billing_address_id );
                                           $address_display = esc_html( $address['address_1'] . ', ' . $address['postcode'] . ' ' . $address['city'] );
                                           if ( ! empty( $address['company'] ) ) $address_display .= ' (' . esc_html( $address['company'] ) . ')';
                                          ?>
                                          <div class="saved-address-option bg-white p-4 rounded-lg shadow-md border <?php echo $is_default ? 'border-blue-500' : 'border-gray-200'; ?> cursor-pointer hover:border-blue-500 transition duration-300 ease-in-out">
                                              <label class="flex items-center">
                                                  <input type="radio" name="yprint_billing_address_selection" value="<?php echo esc_attr($address_id); ?>" <?php checked( $is_default ); ?> class="mr-2">
                                                  <div>
                                                      <span class="address-title font-semibold"><?php echo esc_html( !empty($address['title']) ? $address['title'] : __('Saved Address', 'yprint-plugin') ); ?></span>
                                                      <div class="address-details text-gray-700 text-sm">
                                                          <?php echo esc_html( $address['first_name'] . ' ' . $address['last_name'] ); ?><br/>
                                                          <?php echo $address_display; ?><br/>
                                                          <?php echo esc_html( $address['country'] ); ?>
                                                      </div>
                                                  </div>
                                              </label>
                                          </div>
                                      <?php endforeach;

                                      // Option to enter a new address
                                      // Check if 'new_address' should be checked initially
                                      $check_new_billing = empty( $default_billing_address_id ) || !array_key_exists($default_billing_address_id, $saved_billing_addresses);
                                      ?>
                                      <div class="saved-address-option bg-white p-4 rounded-lg shadow-md border <?php echo $check_new_billing ? 'border-blue-500' : 'border-gray-200'; ?> cursor-pointer hover:border-blue-500 transition duration-300 ease-in-out">
                                           <label class="flex items-center">
                                               <input type="radio" name="yprint_billing_address_selection" value="new_address" <?php checked( $check_new_billing ); ?> class="mr-2">
                                               <div>
                                                   <span class="address-title font-semibold"><?php _e('Enter a new address', 'yprint-plugin'); ?></span>
                                               </div>
                                           </label>
                                       </div>
                                  <?php else :
                                      // Message if no addresses are saved
                                      ?>
                                      <p class="no-saved-addresses text-gray-600 mb-4"><?php _e('No billing addresses saved yet. Please enter an address below.', 'yprint-plugin'); ?></p>
                                       <input type="hidden" name="yprint_billing_address_selection" value="new_address">
                                  <?php endif; ?>
                             </div>
                         </div>

                         <div class="billing-address-new-fields" style="display: none;">
                              <h3 class="text-xl font-bold mb-4 text-gray-800"><?php _e('Billing Details (New Address)', 'yprint-plugin'); ?></h3>
                              <div class="form-row">
                                   <div class="form-field full-width">
                                       <label for="billing_address_title" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Address Title (e.g., Home, Work)', 'yprint-plugin'); ?></label>
                                       <input type="text" id="billing_address_title" name="billing_address_title" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="">
                                   </div>
                               </div>
                              <div class="form-row">
                                  <div class="form-field half-width w-1/2 pr-2">
                                      <label for="billing_first_name" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('First Name', 'yprint-plugin'); ?> <span class="required text-red-500">*</span></label>
                                      <input type="text" id="billing_first_name" name="billing_first_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo esc_attr($customer->get_billing_first_name()); ?>" required>
                                  </div>

                                  <div class="form-field half-width w-1/2 pl-2">
                                      <label for="billing_last_name" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Last Name', 'yprint-plugin'); ?> <span class="required text-red-500">*</span></label>
                                      <input type="text" id="billing_last_name" name="billing_last_name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo esc_attr($customer->get_billing_last_name()); ?>" required>
                                  </div>
                              </div>

                              <div class="form-row">
                                  <div class="form-field full-width">
                                      <label for="billing_company" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Company Name', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                                      <input type="text" id="billing_company" name="billing_company" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo esc_attr($customer->get_billing_company()); ?>">
                                  </div>
                              </div>

                              <div class="form-row">
                                  <div class="form-field full-width">
                                      <label for="billing_country" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Country / Region', 'yprint-plugin'); ?> <span class="required text-red-500">*</span></label>
                                      <select id="billing_country" name="billing_country" class="country-select shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
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
                                      <label for="billing_address_1" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Street Address', 'yprint-plugin'); ?> <span class="required text-red-500">*</span></label>
                                      <input type="text" id="billing_address_1" name="billing_address_1" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="<?php _e('House number and street name', 'yprint-plugin'); ?>" value="<?php echo esc_attr($customer->get_billing_address_1()); ?>" required>
                                  </div>
                              </div>

                              <div class="form-row">
                                  <div class="form-field full-width">
                                      <label for="billing_address_2" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Apartment, suite, unit, etc.', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                                      <input type="text" id="billing_address_2" name="billing_address_2" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="<?php _e('Apartment, suite, unit, etc. (optional)', 'yprint-plugin'); ?>" value="<?php echo esc_attr($customer->get_billing_address_2()); ?>">
                                  </div>
                              </div>

                              <div class="form-row">
                                  <div class="form-field half-width w-1/2 pr-2">
                                      <label for="billing_postcode" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Postcode / ZIP', 'yprint-plugin'); ?> <span class="required text-red-500">*</span></label>
                                      <input type="text" id="billing_postcode" name="billing_postcode" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo esc_attr($customer->get_billing_postcode()); ?>" required>
                                  </div>

                                  <div class="form-field half-width w-1/2 pl-2">
                                      <label for="billing_city" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Town / City', 'yprint-plugin'); ?> <span class="required text-red-500">*</span></label>
                                      <input type="text" id="billing_city" name="billing_city" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo esc_attr($customer->get_billing_city()); ?>" required>
                                  </div>
                              </div>

                              <div class="form-row">
                                  <div class="form-field full-width">
                                      <label for="billing_state" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('State / County', 'yprint-plugin'); ?></label>
                                      <select id="billing_state" name="billing_state" class="state-select shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
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
                                  <div class="form-field half-width w-1/2 pr-2">
                                      <label for="billing_phone" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Phone', 'yprint-plugin'); ?> <span class="required text-red-500">*</span></label>
                                      <input type="tel" id="billing_phone" name="billing_phone" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo esc_attr($customer->get_billing_phone()); ?>" required>
                                  </div>

                                  <div class="form-field half-width w-1/2 pl-2">
                                      <label for="billing_email" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Email Address', 'yprint-plugin'); ?> <span class="required text-red-500">*</span></label>
                                      <input type="email" id="billing_email" name="billing_email" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" value="<?php echo esc_attr($customer->get_billing_email()); ?>" required>
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

                    <div class="form-row mb-6">
                        <div class="form-field full-width">
                            <label for="order_comments" class="block text-gray-700 text-sm font-bold mb-2"><?php _e('Order Notes', 'yprint-plugin'); ?> (<?php _e('Optional', 'yprint-plugin'); ?>)</label>
                            <textarea id="order_comments" name="order_comments" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" placeholder="<?php esc_attr_e('Notes about your order, e.g. special notes for delivery.', 'yprint-plugin'); ?>"></textarea>
                        </div>
                    </div>

                    <h2 class="text-2xl font-bold mb-6 text-gray-800"><?php _e('Payment Method', 'yprint-plugin'); ?></h2>

                    <div class="express-checkout mb-6">
                        <div id="yprint-stripe-payment-request-wrapper" style="display: none;">
                            <div id="yprint-stripe-payment-request-button"></div>
                        </div>

                        <div class="payment-method-separator text-center my-4">
                            <span class="bg-gray-100 px-2 text-gray-500"><?php _e('OR', 'yprint-plugin'); ?></span>
                        </div>

                        <div class="credit-card-section">
                            <div class="card-input-section mb-4">
                                <div id="yprint-stripe-card-element" class="stripe-card-element border rounded p-3 bg-white"></div>
                            </div>
                            <div id="yprint-stripe-card-errors" role="alert" class="text-red-500 text-sm"></div>
                        </div>
                    </div>

                    <input type="hidden" id="payment_method_id" name="payment_method_id" value="">
                    <input type="hidden" name="payment_method" value="">

                    <button type="submit" id="yprint-place-order" class="checkout-button bg-blue-500 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded w-full focus:outline-none focus:shadow-outline">
                        <?php _e('Place Order', 'yprint-plugin'); ?>
                    </button>
                </form>
            </div>

            <div class="yprint-checkout-summary-column">
                <div class="yprint-order-summary bg-white p-6 rounded-lg shadow-md">
                    <h2 class="text-2xl font-bold mb-6 text-gray-800"><?php _e('Order Summary', 'yprint-plugin'); ?></h2>

                    <div class="cart-items border-b border-gray-200 mb-6 pb-6">
                        <?php
                        // Loop through items in the WooCommerce cart
                        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) :
                            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                            $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

                            // Check if product exists, is visible, and has quantity > 0
                            if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_cart_item_visible', true, $cart_item, $cart_item_key)) :
                                $product_name = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
                                $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image('thumbnail'), $cart_item, $cart_item_key); // Use 'thumbnail' size
                                $product_price = WC()->cart->get_product_price($_product); // Get single item price
                                ?>
                                <div class="cart-item flex items-center mb-4">
                                    <div class="cart-item-image w-16 h-16 mr-4 relative">
                                        <?php echo $thumbnail; // Escaped by WC function ?>
                                        <span class="item-quantity absolute -top-2 -right-2 bg-blue-500 text-white text-xs font-bold rounded-full h-5 w-5 flex items-center justify-center"><?php echo esc_html($cart_item['quantity']); ?></span>
                                    </div>
                                    <div class="cart-item-details flex-grow">
                                        <div class="cart-item-name font-semibold text-gray-800">
                                            <?php echo wp_kses_post($product_name); ?>
                                        </div>
                                        <small class="text-gray-600"><?php echo esc_html($cart_item['quantity']); ?>x <?php echo wp_kses_post($product_price); ?></small>
                                        <?php if (!empty($cart_item['variation'])) : ?>
                                            <div class="cart-item-variation text-sm text-gray-600 mt-1">
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
                        <div class="order-total-row subtotal flex justify-between mb-2 text-gray-700">
                            <div class="label font-semibold"><?php _e('Subtotal', 'yprint-plugin'); ?></div>
                            <div class="value"><?php echo wp_kses_post($cart_subtotal); ?></div>
                        </div>

                        <?php if (WC()->cart->needs_shipping()) : ?>
                        <div class="order-total-row shipping flex justify-between mb-2 text-gray-700">
                            <div class="label font-semibold"><?php _e('Shipping', 'yprint-plugin'); ?></div>
                            <div class="value"><?php echo wp_kses_post($cart_shipping_total); ?></div>
                        </div>
                        <?php endif; ?>

                        <?php if (wc_tax_enabled()) : ?>
                        <div class="order-total-row tax flex justify-between mb-2 text-gray-700">
                            <div class="label font-semibold"><?php _e('Tax', 'yprint-plugin'); ?></div>
                            <div class="value"><?php echo wp_kses_post($cart_tax_total); ?></div>
                        </div>
                        <?php endif; ?>

                        <hr class="my-4 border-gray-200" />
                        <div class="order-total-row total flex justify-between text-lg font-bold text-gray-800">
                            <div class="label"><?php _e('Total', 'yprint-plugin'); ?></div>
                            <div class="value"><?php echo wp_kses_post($cart_total); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="add-address-modal" class="modal fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full items-center justify-center z-50">
            <div class="relative p-8 bg-white w-96 mx-auto rounded-lg shadow-lg">
                <h3 class="text-xl font-bold mb-4 text-gray-800">Neue Adresse hinzufügen</h3>

                <form id="add-address-form">
                    <div class="mb-4">
                        <label for="address-title" class="block text-gray-700 text-sm font-bold mb-2">Titel (z.B. Zuhause, Arbeit)</label>
                        <input type="text" id="address-title" name="title" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div class="mb-4">
                        <label for="address-name" class="block text-gray-700 text-sm font-bold mb-2">Name</label>
                        <input type="text" id="address-name" name="name" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div class="mb-4">
                        <label for="address-street" class="block text-gray-700 text-sm font-bold mb-2">Straße und Hausnummer</label>
                        <input type="text" id="address-street" name="street" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div class="mb-4">
                        <label for="address-city" class="block text-gray-700 text-sm font-bold mb-2">Stadt</label>
                        <input type="text" id="address-city" name="city" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                    <div class="mb-4">
                        <label for="address-zip" class="block text-gray-700 text-sm font-bold mb-2">Postleitzahl</label>
                        <input type="text" id="address-zip" name="zip" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>
                     <div class="mb-4">
                        <label for="address-country" class="block text-gray-700 text-sm font-bold mb-2">Land</label>
                        <input type="text" id="address-country" name="country" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" required>
                    </div>

                    <div class="flex items-center justify-between">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Adresse speichern
                        </button>
                        <button type="button" id="close-modal" class="inline-block align-baseline font-bold text-sm text-blue-500 hover:text-blue-800">
                            Abbrechen
                        </button>
                    </div>
                </form>
            </div>
        </div>


    </div>

    <script>
        // JavaScript für die Modal-Logik und Adress-Anzeige
        const noAddressesPlaceholder = document.getElementById('no-addresses-placeholder');
        const addAddressModal = document.getElementById('add-address-modal');
        const closeModalButton = document.getElementById('close-modal');
        const addAddressForm = document.getElementById('add-address-form');
        const savedAddressesContainer = document.getElementById('saved-addresses');

        // Übergeben Sie die gespeicherten Adressen von PHP an JavaScript
        const savedShippingAddresses = <?php echo $saved_shipping_addresses_json; ?>;
        // Holen Sie die Standard-Versandadress-ID von PHP
        const defaultShippingAddressId = '<?php echo esc_js($default_shipping_address_id); ?>';


        // Funktion zum Öffnen des Modals
        function openAddAddressModal() {
            addAddressModal.classList.add('open');
        }

        // Funktion zum Schließen des Modals
        function closeAddAddressModal() {
            addAddressModal.classList.remove('open');
            addAddressForm.reset(); // Formular zurücksetzen
        }

        // Event Listener für den Platzhalter
        if (noAddressesPlaceholder) { // Prüfen, ob das Element existiert
             noAddressesPlaceholder.addEventListener('click', openAddAddressModal);
        }


        // Event Listener für den Schließen-Button im Modal
        if (closeModalButton) { // Prüfen, ob das Element existiert
            closeModalButton.addEventListener('click', closeAddAddressModal);
        }


        // Event Listener, um das Modal zu schließen, wenn außerhalb geklickt wird
        if (addAddressModal) { // Prüfen, ob das Element existiert
            addAddressModal.addEventListener('click', function(event) {
                if (event.target === addAddressModal) {
                    closeAddAddressModal();
                }
            });
        }


        // Funktion zum Anzeigen von Adressen basierend auf dem neuen Design
        function displayAddresses(addresses, selectedAddressId = null) {
            savedAddressesContainer.innerHTML = ''; // Vorherige Adressen entfernen

            if (addresses && Object.keys(addresses).length > 0) {
                 if (noAddressesPlaceholder) {
                    noAddressesPlaceholder.style.display = 'none'; // Platzhalter ausblenden
                 }


                // Konvertieren Sie das assoziative Array in ein Array von Objekten, falls nötig
                const addressesArray = Object.keys(addresses).map(key => ({ id: key, ...addresses[key] }));


                addressesArray.forEach(address => {
                    const addressElement = document.createElement('div');
                    // Verwenden Sie die echte ID von PHP
                    addressElement.dataset.addressId = address.id;
                    addressElement.classList.add(
                        'address-block', // Eigene Klasse für Styling
                        'bg-white',
                        'p-4',
                        'rounded-lg',
                        'shadow-md',
                        'border',
                        'cursor-pointer', // Zeigt an, dass der Block klickbar ist
                        'hover:border-blue-500', // Hover-Effekt
                        'transition', 'duration-300', 'ease-in-out'
                    );

                    // Prüfen, ob diese Adresse die ausgewählte ist
                    if (address.id === selectedAddressId) {
                         addressElement.classList.add('selected', 'border-blue-500');
                    } else {
                        addressElement.classList.add('border-gray-200');
                    }

                    // Sicherstellen, dass alle benötigten Felder vorhanden sind, bevor sie angezeigt werden
                    const addressTitle = address.title ? address.title : 'Gespeicherte Adresse'; // Fallback Titel
                    const addressName = address.first_name && address.last_name ? `${address.first_name} ${address.last_name}` : (address.first_name || address.last_name || '');
                    const addressStreet = address.address_1 ? address.address_1 : '';
                    const addressCity = address.city ? address.city : '';
                    const addressZip = address.postcode ? address.postcode : '';
                    const addressCountry = address.country ? address.country : '';


                    addressElement.innerHTML = `
                        <div class="flex justify-between items-center mb-2">
                            <h3 class="font-semibold text-lg">${addressTitle}</h3>
                            <a href="#" class="text-sm text-blue-500 hover:underline">Edit</a>
                        </div>
                        <p class="text-gray-700">${addressName}</p>
                        <p class="text-gray-700">${addressStreet}</p>
                        <p class="text-gray-700">${addressZip} ${addressCity}</p>
                        <p class="text-gray-700">${addressCountry}</p>
                    `;

                     // Event Listener für das Auswählen der Adresse
                    addressElement.addEventListener('click', function() {
                        // Hier würden Sie die Logik zum Auswählen der Adresse implementieren
                        console.log('Adresse ausgewählt:', address);
                        // Beispiel: Aktualisieren Sie die Anzeige, um diese Adresse als ausgewählt zu markieren
                        // Dies würde normalerweise auch ein Update des Checkouts triggern
                        displayAddresses(addresses, address.id);
                        // TODO: Fügen Sie hier die Logik hinzu, um die ausgewählte Adresse im Backend zu setzen und den Checkout zu aktualisieren
                    });


                    savedAddressesContainer.appendChild(addressElement);
                });
            } else {
                 if (noAddressesPlaceholder) {
                    noAddressesPlaceholder.style.display = 'block'; // Platzhalter anzeigen
                 }
            }
        }

        // Zeigt die gespeicherten Adressen beim Laden der Seite an
        displayAddresses(savedShippingAddresses, defaultShippingAddressId);


        // Event Listener für das Formular (wird später mit Ihrer Speicherlogik und AJAX ersetzt)
        addAddressForm.addEventListener('submit', function(event) {
            event.preventDefault(); // Standard-Formular-Submit verhindern

            // Hier würden Sie normalerweise die Formulardaten sammeln und per AJAX an Ihr Backend senden
            const formData = new FormData(addAddressForm);
            const newAddressData = {
                title: formData.get('title'),
                name: formData.get('name'),
                street: formData.get('street'),
                city: formData.get('city'),
                zip: formData.get('zip'),
                country: formData.get('country')
            };

            console.log('Neue Adresse zum Speichern:', newAddressData);

            // TODO: Implementieren Sie hier die AJAX-Anfrage, um die Adresse zu speichern.
            // Bei Erfolg:
            // 1. Modal schließen: closeAddAddressModal();
            // 2. Backend sollte die neue Adresse speichern und eine ID zurückgeben.
            // 3. Laden Sie die Adressen neu (oder fügen Sie die neue Adresse zum aktuellen Array hinzu)
            // 4. Aktualisieren Sie die Anzeige: displayAddresses(ihrAktualisiertesAdressArray, idDerNeuenAdresse);

            // Für die Frontend-Demo fügen wir die neue Adresse temporär hinzu und aktualisieren die Anzeige
            // Dies simuliert das Hinzufügen, speichert sie aber nicht persistent
            const tempNewAddressId = Math.random().toString(36).substr(2, 9); // Temporäre ID
            const tempNewAddress = {
                 id: tempNewAddressId,
                 title: newAddressData.title,
                 first_name: newAddressData.name.split(' ')[0] || '', // Einfache Aufteilung
                 last_name: newAddressData.name.split(' ')[1] || '', // Einfache Aufteilung
                 address_1: newAddressData.street,
                 city: newAddressData.city,
                 postcode: newAddressData.zip,
                 country: newAddressData.country
            };

            // Fügen Sie die temporäre Adresse zum gespeicherten Array hinzu (nur für die Demo-Anzeige)
            savedShippingAddresses[tempNewAddressId] = tempNewAddress;

            // Aktualisieren Sie die Anzeige und wählen Sie die neue Adresse aus
            displayAddresses(savedShippingAddresses, tempNewAddressId);

            closeAddAddressModal();
        });

        // TODO: Fügen Sie hier weitere JavaScript-Logik hinzu, z.B. für die Umschaltung zwischen
        // gespeicherter Adresse und neuer Adresse, Validierung, AJAX-Aufrufe etc.

    </script>

</body>
</html>
