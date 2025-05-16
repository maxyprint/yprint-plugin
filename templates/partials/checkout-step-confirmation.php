<?php
/**
 * YPrint Checkout Confirmation Step
 *
 * @package YPrint
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Previous step URL
$prev_step_url = add_query_arg('step', 'payment');

// Get customer data
$customer = WC()->customer;
$cart = WC()->cart;

// Get shipping address
$shipping_address = array(
    'first_name' => $customer->get_shipping_first_name(),
    'last_name' => $customer->get_shipping_last_name(),
    'company' => $customer->get_shipping_company(),
    'address_1' => $customer->get_shipping_address_1(),
    'address_2' => $customer->get_shipping_address_2(),
    'city' => $customer->get_shipping_city(),
    'state' => $customer->get_shipping_state(),
    'postcode' => $customer->get_shipping_postcode(),
    'country' => $customer->get_shipping_country(),
);

// Get billing address
$billing_address = array(
    'first_name' => $customer->get_billing_first_name(),
    'last_name' => $customer->get_billing_last_name(),
    'company' => $customer->get_billing_company(),
    'address_1' => $customer->get_billing_address_1(),
    'address_2' => $customer->get_billing_address_2(),
    'city' => $customer->get_billing_city(),
    'state' => $customer->get_billing_state(),
    'postcode' => $customer->get_billing_postcode(),
    'country' => $customer->get_billing_country(),
    'email' => $customer->get_billing_email(),
    'phone' => $customer->get_billing_phone(),
);

// Get chosen payment method
$chosen_payment_method = WC()->session->get('chosen_payment_method');
$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
$payment_method_title = isset($available_gateways[$chosen_payment_method]) ? $available_gateways[$chosen_payment_method]->get_title() : '';

// Get chosen shipping method
$chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
$packages = WC()->shipping()->get_packages();
$shipping_method_title = '';

if (!empty($chosen_shipping_methods) && !empty($packages)) {
    $chosen_method = $chosen_shipping_methods[0];
    foreach ($packages[0]['rates'] as $rate) {
        if ($rate->id === $chosen_method) {
            $shipping_method_title = $rate->label;
            break;
        }
    }
}
?>

<div class="yprint-checkout-step yprint-confirmation-step">
    <h2>Bestellung überprüfen</h2>
    <p class="yprint-step-description">Überprüfe deine Bestelldetails und bestätige den Kauf.</p>
    
    <div class="yprint-order-summary">
        <!-- Delivery Address -->
        <div class="yprint-summary-section yprint-delivery-address">
            <h3>Lieferadresse</h3>
            <div class="yprint-summary-content">
                <p class="yprint-address-name">
                    <?php echo esc_html($shipping_address['first_name'] . ' ' . $shipping_address['last_name']); ?>
                </p>
                
                <?php if (!empty($shipping_address['company'])) : ?>
                <p class="yprint-address-company"><?php echo esc_html($shipping_address['company']); ?></p>
                <?php endif; ?>
                
                <p class="yprint-address-street">
                    <?php echo esc_html($shipping_address['address_1']); ?>
                    <?php if (!empty($shipping_address['address_2'])) : ?>
                        <?php echo esc_html($shipping_address['address_2']); ?>
                    <?php endif; ?>
                </p>
                
                <p class="yprint-address-city">
                    <?php echo esc_html($shipping_address['postcode'] . ' ' . $shipping_address['city']); ?>
                </p>
                
                <p class="yprint-address-country">
                    <?php echo esc_html(WC()->countries->get_countries()[$shipping_address['country']]); ?>
                </p>
                
                <a href="<?php echo esc_url(add_query_arg('step', 'address')); ?>" class="yprint-edit-link">Ändern</a>
            </div>
        </div>
        
        <!-- Billing Address -->
        <div class="yprint-summary-section yprint-billing-address">
            <h3>Rechnungsadresse</h3>
            <div class="yprint-summary-content">
                <p class="yprint-address-name">
                    <?php echo esc_html($billing_address['first_name'] . ' ' . $billing_address['last_name']); ?>
                </p>
                
                <?php if (!empty($billing_address['company'])) : ?>
                <p class="yprint-address-company"><?php echo esc_html($billing_address['company']); ?></p>
                <?php endif; ?>
                
                <p class="yprint-address-street">
                    <?php echo esc_html($billing_address['address_1']); ?>
                    <?php if (!empty($billing_address['address_2'])) : ?>
                        <?php echo esc_html($billing_address['address_2']); ?>
                    <?php endif; ?>
                </p>
                
                <p class="yprint-address-city">
                    <?php echo esc_html($billing_address['postcode'] . ' ' . $billing_address['city']); ?>
                </p>
                
                <p class="yprint-address-country">
                    <?php echo esc_html(WC()->countries->get_countries()[$billing_address['country']]); ?>
                </p>
                
                <p class="yprint-address-contact">
                    <?php echo esc_html($billing_address['email']); ?><br>
                    <?php echo esc_html($billing_address['phone']); ?>
                </p>
                
                <a href="<?php echo esc_url(add_query_arg('step', 'address')); ?>" class="yprint-edit-link">Ändern</a>
            </div>
        </div>
        
        <!-- Payment Method -->
        <div class="yprint-summary-section yprint-payment-method">
            <h3>Zahlungsmethode</h3>
            <div class="yprint-summary-content">
                <p class="yprint-payment-method-name"><?php echo esc_html($payment_method_title); ?></p>
                <a href="<?php echo esc_url(add_query_arg('step', 'payment')); ?>" class="yprint-edit-link">Ändern</a>
            </div>
        </div>
        
        <!-- Shipping Method -->
        <?php if (!empty($shipping_method_title)) : ?>
        <div class="yprint-summary-section yprint-shipping-method">
            <h3>Versandmethode</h3>
            <div class="yprint-summary-content">
                <p class="yprint-shipping-method-name"><?php echo esc_html($shipping_method_title); ?></p>
                <a href="<?php echo esc_url(add_query_arg('step', 'address')); ?>" class="yprint-edit-link">Ändern</a>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- Order Details -->
    <div class="yprint-order-details">
        <h3>Bestellübersicht</h3>
        
        <div class="yprint-order-products">
            <?php 
            // Display cart items
            foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
                $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                
                if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                    $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                    $thumbnail = $_product->get_image('thumbnail');
                    $product_name = $_product->get_name();
                    $product_price = $cart->get_product_price($_product);
                    $product_subtotal = $cart->get_product_subtotal($_product, $cart_item['quantity']);
                    ?>
                    <div class="yprint-order-product">
                        <div class="yprint-product-image">
                            <?php echo $thumbnail; ?>
                        </div>
                        <div class="yprint-product-details">
                            <div class="yprint-product-name"><?php echo esc_html($product_name); ?></div>
                            <div class="yprint-product-info">
                                <span class="yprint-product-quantity">Anzahl: <?php echo esc_html($cart_item['quantity']); ?></span>
                                <span class="yprint-product-price">Preis: <?php echo $product_subtotal; ?></span>
                            </div>
                        </div>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
        
        <div class="yprint-order-totals">
            <div class="yprint-total-row yprint-subtotal">
                <span>Zwischensumme</span>
                <span><?php echo $cart->get_cart_subtotal(); ?></span>
            </div>
            
            <?php if ($cart->needs_shipping() && $cart->get_cart_shipping_total()) : ?>
            <div class="yprint-total-row yprint-shipping">
                <span>Versand</span>
                <span><?php echo $cart->get_cart_shipping_total(); ?></span>
            </div>
            <?php endif; ?>
            
            <?php if (wc_tax_enabled()) : ?>
            <div class="yprint-total-row yprint-tax">
                <span>MwSt.</span>
                <span><?php echo $cart->get_taxes_total(true, true); ?></span>
            </div>
            <?php endif; ?>
            
            <div class="yprint-total-row yprint-total">
                <span>Gesamtsumme</span>
                <span><?php echo $cart->get_total(); ?></span>
            </div>
        </div>
    </div>
    
    <!-- Terms and Conditions -->
    <div class="yprint-terms-acceptance">
        <input type="checkbox" id="terms_acceptance" name="terms_acceptance" required>
        <label for="terms_acceptance">
            Ich habe die <a href="<?php echo esc_url(get_permalink(wc_get_page_id('terms'))); ?>" target="_blank">AGB</a> und <a href="<?php echo esc_url(get_privacy_policy_url()); ?>" target="_blank">Datenschutzerklärung</a> gelesen und akzeptiere sie.
        </label>
    </div>
    
    <!-- Step Navigation -->
    <div class="yprint-step-navigation">
        <a href="<?php echo esc_url($prev_step_url); ?>" class="yprint-button yprint-button-secondary">Zurück zur Zahlung</a>
        <button type="button" id="yprint-place-order" class="yprint-button yprint-button-primary">Jetzt kostenpflichtig bestellen</button>
    </div>
</div>