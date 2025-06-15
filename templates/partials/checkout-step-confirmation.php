<?php
/**
 * Partial Template: Schritt 3 - Bestellung überprüfen und abschließen.
 *
 * Benötigt:
 * JavaScript füllt die dynamischen Daten (Adressen, Produkte, Preise)
 * basierend auf dem `formData` Objekt und den `cartItems`.
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<?php
// Lade aktuelle Bestelldaten bevor der Warenkorb geleert wird
$customer_data = null;
$cart_items = array();
$cart_totals = array();

// Prüfe ob WooCommerce verfügbar ist und Warenkorb Daten hat
if (class_exists('WooCommerce') && WC() && !WC()->cart->is_empty()) {
    // Kundendaten - prioritär aus Address Manager Session laden
$selected_address = WC()->session->get('yprint_selected_address');
$checkout = WC()->checkout();

if ($selected_address && !empty($selected_address)) {
    // Verwende Address Manager Daten
    $customer_data = array(
        'first_name' => $selected_address['first_name'] ?? '',
        'last_name' => $selected_address['last_name'] ?? '',
        'email' => $checkout->get_value('billing_email') ?: WC()->customer->get_email(),
        'phone' => $selected_address['phone'] ?? '',
        'shipping' => array(
            'address_1' => $selected_address['address_1'] ?? '',
            'address_2' => $selected_address['address_2'] ?? '',
            'city' => $selected_address['city'] ?? '',
            'postcode' => $selected_address['postcode'] ?? '',
            'country' => $selected_address['country'] ?? 'DE',
        ),
        'billing' => array(
            'address_1' => $selected_address['address_1'] ?? '',
            'address_2' => $selected_address['address_2'] ?? '',
            'city' => $selected_address['city'] ?? '',
            'postcode' => $selected_address['postcode'] ?? '',
            'country' => $selected_address['country'] ?? 'DE',
        )
    );
} else {
    // Fallback: Standard Checkout-Daten
    $customer_data = array(
        'first_name' => $checkout->get_value('billing_first_name') ?: $checkout->get_value('shipping_first_name'),
        'last_name' => $checkout->get_value('billing_last_name') ?: $checkout->get_value('shipping_last_name'),
        'email' => $checkout->get_value('billing_email'),
        'phone' => $checkout->get_value('billing_phone') ?: $checkout->get_value('shipping_phone'),
        'shipping' => array(
            'address_1' => $checkout->get_value('shipping_address_1'),
            'address_2' => $checkout->get_value('shipping_address_2'),
            'city' => $checkout->get_value('shipping_city'),
            'postcode' => $checkout->get_value('shipping_postcode'),
            'country' => $checkout->get_value('shipping_country'),
        ),
        'billing' => array(
            'address_1' => $checkout->get_value('billing_address_1'),
            'address_2' => $checkout->get_value('billing_address_2'),
            'city' => $checkout->get_value('billing_city'),
            'postcode' => $checkout->get_value('billing_postcode'),
            'country' => $checkout->get_value('billing_country'),
        )
    );
}
    
    // Warenkorb-Items laden
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
        
        // Design Preview URL aus variationImages extrahieren
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
        
        // Fallback Preview URL
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
        <!-- Erfolgsmeldung -->
        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 mr-3 text-xl"></i>
                <div>
                    <h2 class="text-lg font-semibold text-green-800"><?php esc_html_e('Bestellung erfolgreich!', 'yprint-checkout'); ?></h2>
                    <p class="text-green-700 text-sm"><?php esc_html_e('Vielen Dank für Ihre Bestellung. Sie erhalten in Kürze eine Bestätigungs-E-Mail.', 'yprint-checkout'); ?></p>
                </div>
            </div>
        </div>

        <!-- Lieferadresse -->
        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Lieferadresse', 'yprint-checkout'); ?></h3>
            <div class="text-yprint-text-secondary text-sm leading-relaxed bg-gray-50 p-4 rounded-lg">
                <?php if ($customer_data && !empty($customer_data['shipping']['address_1'])): ?>
                    <?php echo esc_html($customer_data['first_name'] . ' ' . $customer_data['last_name']); ?><br>
                    <?php echo esc_html($customer_data['shipping']['address_1']); ?>
                    <?php if ($customer_data['shipping']['address_2']): ?>
                        <?php echo esc_html(' ' . $customer_data['shipping']['address_2']); ?>
                    <?php endif; ?><br>
                    <?php echo esc_html($customer_data['shipping']['postcode'] . ' ' . $customer_data['shipping']['city']); ?><br>
                    <?php echo esc_html(WC()->countries->countries[$customer_data['shipping']['country']] ?? $customer_data['shipping']['country']); ?>
                    <?php if ($customer_data['phone']): ?>
                        <br><?php esc_html_e('Tel:', 'yprint-checkout'); ?> <?php echo esc_html($customer_data['phone']); ?>
                    <?php endif; ?>
                <?php else: ?>
                    <p class="text-gray-600"><?php esc_html_e('Adressdaten werden verarbeitet...', 'yprint-checkout'); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Zahlungsart -->
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

        <!-- Produkte -->
        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Bestellte Artikel', 'yprint-checkout'); ?></h3>
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
            <?php foreach ($cart_items as $index => $item): ?>
    <div class="flex justify-between items-center p-4 <?php echo $index > 0 ? 'border-t border-gray-100' : ''; ?>">
        <div class="flex items-center flex-1">
            <?php if (isset($item['is_design_product']) && $item['is_design_product'] && !empty($item['design_preview'])): ?>
                <!-- Design Preview -->
                <img src="<?php echo esc_url($item['design_preview']); ?>" alt="<?php echo esc_attr($item['design_details']); ?>" class="w-16 h-16 object-cover rounded border mr-3 bg-gray-50">
            <?php elseif (!empty($item['image'])): ?>
                <!-- Standard Produktbild -->
                <img src="<?php echo esc_url($item['image']); ?>" alt="<?php echo esc_attr($item['name']); ?>" class="w-16 h-16 object-cover rounded border mr-3 bg-gray-50">
            <?php else: ?>
                <!-- Placeholder -->
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
                
                <!-- Bestätigungshinweis -->
                <div class="bg-green-50 border-t border-green-200 p-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span class="text-sm text-green-800 font-medium"><?php esc_html_e('Bestellung bestätigt - Diese Artikel wurden erfolgreich bestellt.', 'yprint-checkout'); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gesamtkosten -->
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
                
                <!-- Finale Bestätigung -->
                <div class="mt-3 text-center text-sm text-green-700 bg-green-100 p-3 rounded border border-green-200">
                    <i class="fas fa-shield-alt mr-1"></i> 
                    <?php esc_html_e('Danke für deine Bestellung! Wir machen uns gleich an den Druck.', 'yprint-checkout'); ?>
                </div>
            </div>
        </div>

        <!-- Info-Box -->
        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800 space-y-2">
            <h4 class="font-semibold text-blue-900 mb-2"><?php esc_html_e('Was passiert als nächstes?', 'yprint-checkout'); ?></h4>
            <p><i class="fas fa-clock fa-fw mr-2"></i> <?php esc_html_e('Wir bearbeiten Ihre Bestellung innerhalb von 24 Stunden', 'yprint-checkout'); ?></p>
            <p><i class="fas fa-truck fa-fw mr-2"></i> <?php esc_html_e('Geschätzte Lieferzeit: 4-7 Werktage', 'yprint-checkout'); ?></p>
            <p><i class="fas fa-envelope fa-fw mr-2"></i> <?php esc_html_e('Sie erhalten eine Versandbestätigung per E-Mail', 'yprint-checkout'); ?></p>
            <p><i class="fas fa-headset fa-fw mr-2"></i> <?php esc_html_e('Fragen?', 'yprint-checkout'); ?> <a href="https://yprint.de/help" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline font-medium"><?php esc_html_e('Support kontaktieren', 'yprint-checkout'); ?></a></p>
        </div>

        <!-- Dashboard Button -->
        <div class="mt-6 text-center">
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount') ?: home_url('/mein-konto/')); ?>" 
               class="btn btn-primary text-lg px-8 py-3 inline-flex items-center">
                <i class="fas fa-tachometer-alt mr-2"></i> 
                <?php esc_html_e('Zurück zum Dashboard', 'yprint-checkout'); ?>
            </a>
        </div>
    </div>
</div>
