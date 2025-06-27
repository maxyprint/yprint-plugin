<?php
/**
 * Partial Template: Schritt 3 - Bestellung überprüfen und abschließen.
 *
 * Benötigt:
 * Die Adressdaten werden nun direkt aus dem finalen WooCommerce Order-Objekt geladen.
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php
// Lade aktuelle Bestelldaten
$customer_data = null;
$cart_items = array();
$cart_totals = array();

// Prüfe ob WooCommerce verfügbar ist
if (class_exists('WooCommerce') && WC()) {
    
    // Versuche, die finale WooCommerce Order zu laden
    $final_order = null;
    $order_id = null;
    $order_data_from_session = WC()->session->get('yprint_pending_order');

    // Versuche Order ID aus Session-Daten zu ermitteln
    if ($order_data_from_session && isset($order_data_from_session['order_id'])) {
        $order_id = $order_data_from_session['order_id'];
    } else {
        // Fallback: Letzte Order ID aus Session
        $last_order_id_session = WC()->session->get('yprint_last_order_id');
        if ($last_order_id_session && strpos($last_order_id_session, 'YP-') === 0) {
            $order_id = str_replace('YP-', '', $last_order_id_session);
        }
    }

    // Lade die WooCommerce Order, wenn eine ID gefunden wurde
    if ($order_id) {
        $final_order = wc_get_order($order_id);
    }

    // Wenn die Order existiert, lade die Adressdaten direkt aus der Order
    if ($final_order && is_a($final_order, 'WC_Order')) {
        $customer_data = array(
            'email' => $final_order->get_billing_email(),
            'phone' => $final_order->get_billing_phone(),
            'shipping' => array(
                'first_name' => $final_order->get_shipping_first_name(),
                'last_name'  => $final_order->get_shipping_last_name(),
                'address_1'  => $final_order->get_shipping_address_1(),
                'address_2'  => $final_order->get_shipping_address_2(),
                'city'       => $final_order->get_shipping_city(),
                'postcode'   => $final_order->get_shipping_postcode(),
                'country'    => $final_order->get_shipping_country(),
            ),
            'billing' => array(
                'first_name' => $final_order->get_billing_first_name(),
                'last_name'  => $final_order->get_billing_last_name(),
                'address_1'  => $final_order->get_billing_address_1(),
                'address_2'  => $final_order->get_billing_address_2(),
                'city'       => $final_order->get_billing_city(),
                'postcode'   => $final_order->get_billing_postcode(),
                'country'    => $final_order->get_billing_country(),
            )
        );
    }
    
    // Warenkorb-Items laden (falls der Warenkorb nicht bereits geleert wurde)
    if (WC()->cart && !WC()->cart->is_empty()) {
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            if (!$product) continue;
            
            $item_data = array(
                'name' => $product->get_name(),
                'quantity' => $cart_item['quantity'],
                'price' => $product->get_price(),
                'total' => $cart_item['line_total'],
                'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') ?: wc_placeholder_img_src('thumbnail')
            );
            
            // Erweiterte Design-Details falls vorhanden
            if (isset($cart_item['print_design']) && !empty($cart_item['print_design'])) {
                $design_data = $cart_item['print_design'];
                
                $item_data['is_design_product'] = true;
                $item_data['design_name'] = $design_data['name'] ?? 'Custom Design';
                
                $preview_url = '';
                if (isset($design_data['variationImages']) && !empty($design_data['variationImages'])) {
                    $variation_images = is_string($design_data['variationImages']) ? 
                        json_decode($design_data['variationImages'], true) : $design_data['variationImages'];
                    
                    if ($variation_images && is_array($variation_images)) {
                        $first_variation = reset($variation_images);
                        if (is_array($first_variation) && !empty($first_variation)) {
                            $first_image = reset($first_variation);
                            $preview_url = $first_image['url'] ?? '';
                        }
                    }
                }
                
                if (empty($preview_url)) {
                    $preview_url = $design_data['preview_url'] ?? $design_data['design_image_url'] ?? '';
                }
                
                $item_data['design_preview'] = $preview_url;
                $item_data['design_details'] = $item_data['design_name'];
            }
            
            $cart_items[] = $item_data;
        }
            
        // Preise berechnen
        $cart_totals = array(
            'subtotal' => WC()->cart->get_subtotal(),
            'shipping' => WC()->cart->get_shipping_total(),
            'tax' => WC()->cart->get_total_tax(),
            'discount' => WC()->cart->get_discount_total(),
            'total' => WC()->cart->get_total('edit')
        );
    }
    
    // Zahlungsmethode
    $chosen_payment_method = WC()->session->get('chosen_payment_method');
}


// Fallback falls keine Daten verfügbar
if (empty($cart_items)) {
    $cart_items = array(
        array(
            'name' => 'Ihre bestellten Artikel',
            'quantity' => 1,
            'price' => 30.00,
            'total' => 30.00,
            'image' => '',
            'is_design_product' => false
        )
    );
    $cart_totals = array(
        'subtotal' => 30.00,
        'shipping' => 0.00,
        'tax' => 4.77,
        'discount' => 0.00,
        'total' => 30.00
    );
}
?>

<div id="step-3" class="checkout-step">
   <div class="space-y-6 mt-6">
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 mr-3 text-xl"></i>
                <div>
                    <h2 class="text-lg font-semibold text-green-800"><?php esc_html_e('Bestellung erfolgreich!', 'yprint-checkout'); ?></h2>
                    <p class="text-green-700 text-sm"><?php esc_html_e('Vielen Dank für Ihre Bestellung. Sie erhalten in Kürze eine Bestätigungs-E-Mail.', 'yprint-checkout'); ?></p>
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Lieferadresse', 'yprint-checkout'); ?></h3>
            <div class="text-yprint-text-secondary text-sm leading-relaxed bg-gray-50 p-4 rounded-lg">
                <?php if ($customer_data && !empty($customer_data['shipping']['address_1'])): ?>
                    <?php echo esc_html($customer_data['shipping']['first_name'] . ' ' . $customer_data['shipping']['last_name']); ?><br>
                    <?php echo esc_html($customer_data['shipping']['address_1']); ?>
                    <?php if ( ! empty($customer_data['shipping']['address_2'])): ?>
                        <?php echo esc_html(' ' . $customer_data['shipping']['address_2']); ?>
                    <?php endif; ?><br>
                    <?php echo esc_html($customer_data['shipping']['postcode'] . ' ' . $customer_data['shipping']['city']); ?><br>
                    <?php echo esc_html(WC()->countries->countries[$customer_data['shipping']['country']] ?? $customer_data['shipping']['country']); ?>
                <?php else: ?>
                    <span class="text-gray-500"><?php esc_html_e('Keine Lieferadresse angegeben', 'yprint-checkout'); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Rechnungsadresse', 'yprint-checkout'); ?></h3>
            <div class="text-yprint-text-secondary text-sm leading-relaxed bg-gray-50 p-4 rounded-lg">
                <?php if ($customer_data && !empty($customer_data['billing']['address_1'])): ?>
                    <?php echo esc_html($customer_data['billing']['first_name'] . ' ' . $customer_data['billing']['last_name']); ?><br>
                    <?php echo esc_html($customer_data['billing']['address_1']); ?>
                    <?php if ( ! empty($customer_data['billing']['address_2'])): ?>
                        <?php echo esc_html(' ' . $customer_data['billing']['address_2']); ?>
                    <?php endif; ?><br>
                    <?php echo esc_html($customer_data['billing']['postcode'] . ' ' . $customer_data['billing']['city']); ?><br>
                    <?php echo esc_html(WC()->countries->countries[$customer_data['billing']['country']] ?? $customer_data['billing']['country']); ?>
                    <?php if (!empty($customer_data['email'])): ?>
                        <br><?php echo esc_html($customer_data['email']); ?>
                    <?php endif; ?>
                    <?php if (!empty($customer_data['phone'])): ?>
                        <br><?php echo esc_html($customer_data['phone']); ?>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="text-gray-500"><?php esc_html_e('Keine Rechnungsadresse angegeben', 'yprint-checkout'); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Gewählte Zahlungsart', 'yprint-checkout'); ?></h3>
            <div class="text-yprint-text-secondary text-sm bg-gray-50 p-4 rounded-lg">
                <?php 
                $payment_method_title = 'Kreditkarte (Stripe)';
                if (isset($chosen_payment_method)) {
                    if (strpos($chosen_payment_method, 'stripe_sepa') !== false) {
                        $payment_method_title = '<i class="fas fa-university mr-2"></i> SEPA-Lastschrift (Stripe)';
                    } elseif (strpos($chosen_payment_method, 'stripe') !== false) {
                        $payment_method_title = '<i class="fas fa-credit-card mr-2"></i> Kreditkarte (Stripe)';
                    } elseif (strpos($chosen_payment_method, 'paypal') !== false) {
                        $payment_method_title = '<i class="fab fa-paypal mr-2"></i> PayPal';
                    }
                }
                echo wp_kses_post($payment_method_title);
                ?>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Bestellte Artikel', 'yprint-checkout'); ?></h3>
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <?php foreach ($cart_items as $index => $item): ?>
    <div class="flex justify-between items-center p-4 <?php echo $index > 0 ? 'border-t border-gray-100' : ''; ?>">
        <div class="flex items-center flex-1">
            <?php if (isset($item['is_design_product']) && $item['is_design_product'] && !empty($item['design_preview'])): ?>
                <img src="<?php echo esc_url($item['design_preview']); ?>" alt="<?php echo esc_attr($item['design_details']); ?>" class="w-16 h-16 object-cover rounded border mr-3 bg-gray-50">
            <?php elseif (!empty($item['image'])): ?>
                <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['name']); ?>" class="w-16 h-16 object-cover rounded border mr-3 bg-gray-50">
            <?php else: ?>
                <div class="w-16 h-16 bg-gray-200 rounded border mr-3 flex items-center justify-center">
                    <i class="fas fa-image text-gray-400"></i>
                </div>
            <?php endif; ?>
            <div class="flex-1">
                <p class="font-medium text-sm text-gray-800">
                    <?php echo esc_html($item['name']); ?>
                    <?php if (isset($item['is_design_product']) && $item['is_design_product']): ?>
                        <span class="text-xs bg-blue-600 text-white px-2 py-1 rounded-full ml-2">
                            <?php echo esc_html($item['design_details']); ?>
                        </span>
                    <?php endif; ?>
                </p>
                <p class="text-xs text-gray-600"><?php esc_html_e('Menge:', 'yprint-checkout'); ?> <?php echo esc_html($item['quantity']); ?></p>
                <?php if ($item['quantity'] > 1): ?>
                    <p class="text-xs text-gray-500 mt-1">€<?php echo number_format($item['price'], 2, ',', '.'); ?> <?php esc_html_e('pro Stück', 'yprint-checkout'); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="text-right">
            <p class="font-medium text-sm text-gray-800">€<?php echo number_format($item['total'], 2, ',', '.'); ?></p>
        </div>
    </div>
<?php endforeach; ?>
                
                <div class="bg-green-50 border-t border-green-200 p-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span class="text-sm text-green-800 font-medium"><?php esc_html_e('Bestellung bestätigt - Diese Artikel wurden erfolgreich bestellt.', 'yprint-checkout'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 border-t border-yprint-medium-gray pt-6">
            <h3 class="text-lg font-semibold mb-2"><?php esc_html_e('Gesamtkosten', 'yprint-checkout'); ?></h3>
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span><?php esc_html_e('Zwischensumme:', 'yprint-checkout'); ?></span> 
                        <span>€<?php echo number_format($cart_totals['subtotal'], 2, ',', '.'); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span><?php esc_html_e('Versand:', 'yprint-checkout'); ?></span> 
                        <span><?php echo $cart_totals['shipping'] > 0 ? '€' . number_format($cart_totals['shipping'], 2, ',', '.') : '<span class="text-green-600 font-medium">Kostenlos</span>'; ?></span>
                    </div>
                    <?php if ($cart_totals['discount'] > 0): ?>
                    <div class="flex justify-between text-green-600">
                        <span><?php esc_html_e('Rabatt (Gutschein):', 'yprint-checkout'); ?></span> 
                        <span>-€<?php echo number_format($cart_totals['discount'], 2, ',', '.'); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <span><?php esc_html_e('inkl. MwSt.:', 'yprint-checkout'); ?></span> 
                        <span>€<?php echo number_format($cart_totals['tax'], 2, ',', '.'); ?></span>
                    </div>
                    <hr class="my-2 border-gray-300">
                    <div class="flex justify-between text-xl font-bold text-green-600">
                        <span><?php esc_html_e('Gesamtbetrag:', 'yprint-checkout'); ?></span> 
                        <span>€<?php echo number_format($cart_totals['total'], 2, ',', '.'); ?></span>
                    </div>
                </div>
                
                <div class="mt-3 text-center text-sm text-green-700 bg-green-100 p-3 rounded border border-green-200">
                    <i class="fas fa-shield-alt mr-1"></i> 
                    <?php esc_html_e('Danke für deine Bestellung! Wir machen uns gleich an den Druck.', 'yprint-checkout'); ?>
                </div>
            </div>
        </div>

        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800 space-y-2">
            <h4 class="font-semibold text-blue-900 mb-2"><?php esc_html_e('Was passiert als nächstes?', 'yprint-checkout'); ?></h4>
            <p><i class="fas fa-clock fa-fw mr-2"></i> <?php esc_html_e('Wir bearbeiten Ihre Bestellung innerhalb von 24 Stunden', 'yprint-checkout'); ?></p>
            <p><i class="fas fa-truck fa-fw mr-2"></i> <?php esc_html_e('Geschätzte Lieferzeit: 4-7 Werktage', 'yprint-checkout'); ?></p>
            <p><i class="fas fa-envelope fa-fw mr-2"></i> <?php esc_html_e('Sie erhalten eine Versandbestätigung per E-Mail', 'yprint-checkout'); ?></p>
            <p><i class="fas fa-headset fa-fw mr-2"></i> <?php esc_html_e('Fragen?', 'yprint-checkout'); ?> <a href="https://yprint.de/help" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline font-medium"><?php esc_html_e('Support kontaktieren', 'yprint-checkout'); ?></a></p>
        </div>

        <div class="mt-6 text-center">
    <a href="https://yprint.de/dashboard" 
       class="btn btn-primary text-lg px-8 py-3 inline-flex items-center">
        <i class="fas fa-tachometer-alt mr-2"></i> 
        <?php esc_html_e('Zurück zum Dashboard', 'yprint-checkout'); ?>
    </a>
</div>

    </div>
</div>