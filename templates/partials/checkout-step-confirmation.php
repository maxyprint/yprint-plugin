<?php
/**
 * Partial Template: Schritt 3 - Bestellung überprüfen und abschließen.
 *
 * @version 2.0.0
 * @since   1.0.0
 *
 * Benötigt:
 * Die Adress- und Bestelldaten werden aus dem finalen WooCommerce Order-Objekt geladen.
 */

// Direktaufruf des Skripts verhindern.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// --- DATENLADUNG UND VORBEREITUNG ---

// Initialisierung der Template-Variablen.
$customer_data = [];
$cart_items    = [];
$cart_totals   = [];
$final_order   = null;

// Sicherstellen, dass WooCommerce aktiv ist.
if ( ! function_exists( 'WC' ) || ! WC()->session ) {
    // Fallback oder Fehlermeldung, falls WC nicht verfügbar ist.
    // In diesem Fall wird der Fallback-Block am Ende des PHP-Teils greifen.
} else {

    // 1. Lade die WooCommerce-Bestellung (Order)
    // Versuche, die Order-ID aus verschiedenen Session-Quellen zu ermitteln.
    $order_data_from_session = WC()->session->get( 'yprint_pending_order' );
    $order_id                = $order_data_from_session['order_id'] ?? null;

    if ( ! $order_id ) {
        $last_order_id_session = WC()->session->get( 'yprint_last_order_id' );
        // Prüfe, ob es sich um eine gültige YP-ID handelt.
        if ( is_string( $last_order_id_session ) && str_starts_with( $last_order_id_session, 'YP-' ) ) {
            $order_id = substr( $last_order_id_session, 3 );
        }
    }

    // Lade das Order-Objekt, wenn eine ID gefunden wurde.
    if ( $order_id ) {
        $final_order = wc_get_order( $order_id );
    }

    // 2. Extrahiere Adressdaten aus der Bestellung
    if ( $final_order instanceof \WC_Order ) {
        $customer_data = [
            'email'    => $final_order->get_billing_email(),
            'phone'    => $final_order->get_billing_phone(),
            'shipping' => $final_order->get_address( 'shipping' ),
            'billing'  => $final_order->get_address( 'billing' ),
        ];
    }

    // 3. Lade die Artikel aus dem Warenkorb (falls noch nicht geleert)
    if ( WC()->cart && ! WC()->cart->is_empty() ) {
        foreach ( WC()->cart->get_cart() as $cart_item ) {
            $product = $cart_item['data'];
            if ( ! $product ) {
                continue;
            }

            $item_data = [
                'name'              => $product->get_name(),
                'quantity'          => $cart_item['quantity'],
                'price'             => $product->get_price(),
                'total'             => $cart_item['line_total'],
                'image'             => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
                'is_design_product' => false,
            ];

            // Füge erweiterte Design-Details hinzu, falls vorhanden.
            if ( ! empty( $cart_item['print_design'] ) ) {
                $design_data = $cart_item['print_design'];
                $preview_url = '';

                // Versuche, eine Vorschau aus den Variation-Images zu extrahieren.
                if ( ! empty( $design_data['variationImages'] ) ) {
                    $variation_images   = is_string( $design_data['variationImages'] ) ? json_decode( $design_data['variationImages'], true ) : $design_data['variationImages'];
                    $first_variation    = is_array( $variation_images ) ? reset( $variation_images ) : null;
                    $first_image        = is_array( $first_variation ) ? reset( $first_variation ) : null;
                    $preview_url        = $first_image['url'] ?? '';
                }

                $item_data['is_design_product'] = true;
                $item_data['design_name']       = $design_data['name'] ?? __( 'Custom Design', 'yprint-checkout' );
                $item_data['design_details']    = $item_data['design_name'];
                $item_data['design_preview']    = $preview_url ?: $design_data['preview_url'] ?? $design_data['design_image_url'] ?? '';
            }

            $cart_items[] = $item_data;
        }

        // 4. Berechne die Gesamtbeträge
        $cart_totals = [
            'subtotal' => WC()->cart->get_subtotal(),
            'shipping' => WC()->cart->get_shipping_total(),
            'tax'      => WC()->cart->get_total_tax(),
            'discount' => WC()->cart->get_discount_total(),
            'total'    => WC()->cart->get_total( 'edit' ),
        ];
    }

    // 5. Ermittle die gewählte Zahlungsmethode
    // Kette von Fallbacks, um die zuverlässigste Quelle zu finden.
    $chosen_payment_method = WC()->session->get( 'yprint_checkout_payment_method' )
        ?: WC()->session->get( 'chosen_payment_method' )
        ?: ( WC()->session->get( 'yprint_pending_order' )['payment_method_type'] ?? null );
}


// --- FALLBACK-DATEN ---

// Wenn keine Artikel gefunden wurden (z.B. nach Session-Ablauf), zeige Beispieldaten an.
if ( empty( $cart_items ) ) {
    $cart_items = [
        [
            'name'              => __( 'Ihre bestellten Artikel', 'yprint-checkout' ),
            'quantity'          => 1,
            'price'             => 30.00,
            'total'             => 30.00,
            'image'             => '',
            'is_design_product' => false,
        ],
    ];
    $cart_totals = [
        'subtotal' => 30.00,
        'shipping' => 0.00,
        'tax'      => 4.77,
        'discount' => 0.00,
        'total'    => 30.00,
    ];
}


// --- HELPER-FUNKTION FÜR ZAHLUNGSART-ANZEIGE ---
if ( ! function_exists( 'yprint_get_payment_method_display' ) ) {
    /**
     * Erzeugt den Anzeigenamen und das Icon für eine gegebene Zahlungsmethode.
     *
     * @param string|null $method_id Die ID der Zahlungsmethode.
     * @return string Der formatierte HTML-String.
     */
    function yprint_get_payment_method_display( ?string $method_id ): string {
        $method = strtolower( (string) $method_id );
        $title  = __( 'Kreditkarte (Stripe)', 'yprint-checkout' );
        $icon   = 'fa-credit-card';

        if ( str_contains( $method, 'sepa' ) ) {
            $title = __( 'SEPA-Lastschrift (Stripe)', 'yprint-checkout' );
            $icon  = 'fa-university';
        } elseif ( str_contains( $method, 'apple' ) ) {
            $title = __( 'Apple Pay (Stripe)', 'yprint-checkout' );
            $icon  = 'fa-apple fab';
        } elseif ( str_contains( $method, 'google' ) ) {
            $title = __( 'Google Pay (Stripe)', 'yprint-checkout' );
            $icon  = 'fa-google-pay fab';
        } elseif ( str_contains( $method, 'paypal' ) ) {
            $title = __( 'PayPal', 'yprint-checkout' );
            $icon  = 'fa-paypal fab';
        } elseif ( str_contains( $method, 'express' ) || str_contains( $method, 'payment_request' ) ) {
            $title = __( 'Express-Zahlung (Stripe)', 'yprint-checkout' );
            $icon  = 'fa-bolt';
        } elseif ( str_contains( $method, 'card' ) || str_contains( $method, 'stripe' ) ) {
            // Bleibt beim Standard (Kreditkarte)
        } elseif ( ! empty( $method ) ) {
            // Fallback für unbekannte Methoden
            $title = ucfirst( str_replace( [ '_', '-' ], ' ', $method ) );
        }
        
        // Debugging-Logik beibehalten
        error_log( 'YPrint Payment Method Display: ' . $method_id . ' -> ' . $title );

        return sprintf( '<i class="fas %s mr-2"></i> %s', esc_attr( $icon ), esc_html( $title ) );
    }
}
?>

<div id="step-3" class="checkout-step">
    <div class="space-y-6 mt-6">

        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-600 mr-3 text-xl"></i>
                <div>
                    <h2 class="text-lg font-semibold text-green-800"><?php esc_html_e( 'Bestellung erfolgreich!', 'yprint-checkout' ); ?></h2>
                    <p class="text-green-700 text-sm"><?php esc_html_e( 'Vielen Dank für Ihre Bestellung. Sie erhalten in Kürze eine Bestätigungs-E-Mail.', 'yprint-checkout' ); ?></p>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e( 'Lieferadresse', 'yprint-checkout' ); ?></h3>
                <div class="text-yprint-text-secondary text-sm leading-relaxed bg-gray-50 p-4 rounded-lg">
                    <?php if ( ! empty( $customer_data['shipping']['address_1'] ) ) : ?>
                        <?php echo esc_html( $customer_data['shipping']['first_name'] . ' ' . $customer_data['shipping']['last_name'] ); ?><br>
                        <?php echo esc_html( $customer_data['shipping']['address_1'] ); ?>
                        <?php if ( ! empty( $customer_data['shipping']['address_2'] ) ) : ?>
                            <?php echo ' ' . esc_html( $customer_data['shipping']['address_2'] ); ?>
                        <?php endif; ?><br>
                        <?php echo esc_html( $customer_data['shipping']['postcode'] . ' ' . $customer_data['shipping']['city'] ); ?><br>
                        <?php echo esc_html( WC()->countries->countries[ $customer_data['shipping']['country'] ] ?? $customer_data['shipping']['country'] ); ?>
                    <?php else : ?>
                        <span class="text-gray-500"><?php esc_html_e( 'Keine Lieferadresse angegeben.', 'yprint-checkout' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div>
                <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e( 'Rechnungsadresse', 'yprint-checkout' ); ?></h3>
                <div class="text-yprint-text-secondary text-sm leading-relaxed bg-gray-50 p-4 rounded-lg">
                    <?php if ( ! empty( $customer_data['billing']['address_1'] ) ) : ?>
                        <?php echo esc_html( $customer_data['billing']['first_name'] . ' ' . $customer_data['billing']['last_name'] ); ?><br>
                        <?php echo esc_html( $customer_data['billing']['address_1'] ); ?>
                        <?php if ( ! empty( $customer_data['billing']['address_2'] ) ) : ?>
                            <?php echo ' ' . esc_html( $customer_data['billing']['address_2'] ); ?>
                        <?php endif; ?><br>
                        <?php echo esc_html( $customer_data['billing']['postcode'] . ' ' . $customer_data['billing']['city'] ); ?><br>
                        <?php echo esc_html( WC()->countries->countries[ $customer_data['billing']['country'] ] ?? $customer_data['billing']['country'] ); ?>
                        <?php if ( ! empty( $customer_data['email'] ) ) : ?>
                            <br><?php echo esc_html( $customer_data['email'] ); ?>
                        <?php endif; ?>
                        <?php if ( ! empty( $customer_data['phone'] ) ) : ?>
                            <br><?php echo esc_html( $customer_data['phone'] ); ?>
                        <?php endif; ?>
                    <?php else : ?>
                        <span class="text-gray-500"><?php esc_html_e( 'Keine Rechnungsadresse angegeben.', 'yprint-checkout' ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e( 'Gewählte Zahlungsart', 'yprint-checkout' ); ?></h3>
            <div class="text-yprint-text-secondary text-sm bg-gray-50 p-4 rounded-lg">
                <?php
                // PHP-Fallback wird initial angezeigt und kann per JS überschrieben werden.
                $payment_method_html = yprint_get_payment_method_display( $chosen_payment_method );
                echo '<span id="dynamic-payment-method-display">' . wp_kses_post( $payment_method_html ) . '</span>';
                ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            function getPaymentMethodTitle(method) {
                if (!method) return null;
                method = method.toLowerCase();
                
                if (method.includes('sepa') || method === 'sepa_debit') return '<i class="fas fa-university mr-2"></i> <?php echo esc_js( __( 'SEPA-Lastschrift (Stripe)', 'yprint-checkout' ) ); ?>';
                if (method.includes('card')) return '<i class="fas fa-credit-card mr-2"></i> <?php echo esc_js( __( 'Kreditkarte (Stripe)', 'yprint-checkout' ) ); ?>';
                if (method.includes('apple') || method.includes('applepay')) return '<i class="fab fa-apple mr-2"></i> <?php echo esc_js( __( 'Apple Pay (Stripe)', 'yprint-checkout' ) ); ?>';
                if (method.includes('google') || method.includes('googlepay')) return '<i class="fab fa-google-pay mr-2"></i> <?php echo esc_js( __( 'Google Pay (Stripe)', 'yprint-checkout' ) ); ?>';
                if (method.includes('paypal')) return '<i class="fab fa-paypal mr-2"></i> <?php echo esc_js( __( 'PayPal', 'yprint-checkout' ) ); ?>';
                if (method.includes('express') || method.includes('payment_request')) return '<i class="fas fa-bolt mr-2"></i> <?php echo esc_js( __( 'Express-Zahlung (Stripe)', 'yprint-checkout' ) ); ?>';
                if (method.includes('stripe')) return '<i class="fas fa-credit-card mr-2"></i> <?php echo esc_js( __( 'Kreditkarte (Stripe)', 'yprint-checkout' ) ); ?>';
                
                const cleanMethod = method.charAt(0).toUpperCase() + method.slice(1).replace(/[_-]/g, ' ');
                return `<i class="fas fa-credit-card mr-2"></i> ${cleanMethod}`;
            }

            function updatePaymentMethodDisplay() {
                const displayElement = document.getElementById('dynamic-payment-method-display');
                if (!displayElement) return;

                let method = null;
                let title = null;

                // Verschiedene Quellen in priorisierter Reihenfolge prüfen
                try {
                    const orderData = JSON.parse(sessionStorage.getItem('yprint_confirmation_order_data'));
                    method = orderData?.payment?.method;
                } catch (e) { /* silent fail */ }

                if (!method && window.paymentData?.payment_method_type) method = window.paymentData.payment_method_type;
                if (!method) method = document.querySelector('.slider-option.active')?.dataset.method;
                if (!method) method = document.querySelector('input[name="payment_method"]:checked')?.value;

                title = getPaymentMethodTitle(method);
                if (title) {
                    displayElement.innerHTML = title;
                }
            }
            
            updatePaymentMethodDisplay();

            // Event Listener für Updates
            const originalPopulate = window.populateConfirmationWithPaymentData;
            if (originalPopulate) {
                window.populateConfirmationWithPaymentData = function(...args) {
                    originalPopulate.apply(this, args);
                    updatePaymentMethodDisplay();
                };
            }
            document.addEventListener('yprint_step_changed', (e) => (e.detail.step === 3) && setTimeout(updatePaymentMethodDisplay, 100));
        });
        </script>


        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e( 'Bestellte Artikel', 'yprint-checkout' ); ?></h3>
            <div class="bg-white border border-gray-200 rounded-lg overflow-hidden">
                <?php foreach ( $cart_items as $index => $item ) : ?>
                    <div class="flex justify-between items-center p-4 <?php echo $index > 0 ? 'border-t border-gray-100' : ''; ?>">
                        <div class="flex items-center flex-1 min-w-0">
                            <?php if ( $item['is_design_product'] && ! empty( $item['design_preview'] ) ) : ?>
                                <img src="<?php echo esc_url( $item['design_preview'] ); ?>" alt="<?php echo esc_attr( $item['design_details'] ); ?>" class="w-16 h-16 object-cover rounded border mr-3 bg-gray-50 flex-shrink-0">
                            <?php elseif ( ! empty( $item['image'] ) ) : ?>
                                <img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" class="w-16 h-16 object-cover rounded border mr-3 bg-gray-50 flex-shrink-0">
                            <?php else : ?>
                                <div class="w-16 h-16 bg-gray-200 rounded border mr-3 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-image text-gray-400"></i>
                                </div>
                            <?php endif; ?>
                            <div class="flex-1 min-w-0">
                                <p class="font-medium text-sm text-gray-800 truncate">
                                    <?php echo esc_html( $item['name'] ); ?>
                                    <?php if ( $item['is_design_product'] ) : ?>
                                        <span class="text-xs bg-blue-600 text-white px-2 py-1 rounded-full ml-2 whitespace-nowrap"><?php echo esc_html( $item['design_details'] ); ?></span>
                                    <?php endif; ?>
                                </p>
                                <p class="text-xs text-gray-600"><?php printf( esc_html__( 'Menge: %s', 'yprint-checkout' ), esc_html( $item['quantity'] ) ); ?></p>
                                <?php if ( $item['quantity'] > 1 ) : ?>
                                    <p class="text-xs text-gray-500 mt-1">
                                        €<?php echo esc_html( number_format( $item['price'], 2, ',', '.' ) ); ?> <?php esc_html_e( 'pro Stück', 'yprint-checkout' ); ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="text-right pl-4">
                            <p class="font-medium text-sm text-gray-800">€<?php echo esc_html( number_format( $item['total'], 2, ',', '.' ) ); ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <div class="bg-green-50 border-t border-green-200 p-4">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-600 mr-2"></i>
                        <span class="text-sm text-green-800 font-medium"><?php esc_html_e( 'Bestellung bestätigt - Diese Artikel wurden erfolgreich bestellt.', 'yprint-checkout' ); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="mt-6 border-t border-yprint-medium-gray pt-6">
            <h3 class="text-lg font-semibold mb-2"><?php esc_html_e( 'Gesamtkosten', 'yprint-checkout' ); ?></h3>
            <div class="bg-gray-50 p-4 rounded-lg">
                <div class="space-y-1 text-sm">
                    <div class="flex justify-between">
                        <span><?php esc_html_e( 'Zwischensumme:', 'yprint-checkout' ); ?></span> 
                        <span>€<?php echo esc_html( number_format( $cart_totals['subtotal'], 2, ',', '.' ) ); ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span><?php esc_html_e( 'Versand:', 'yprint-checkout' ); ?></span> 
                        <span><?php echo $cart_totals['shipping'] > 0 ? '€' . esc_html( number_format( $cart_totals['shipping'], 2, ',', '.' ) ) : '<span class="text-green-600 font-medium">' . esc_html__( 'Kostenlos', 'yprint-checkout' ) . '</span>'; ?></span>
                    </div>
                    <?php if ( $cart_totals['discount'] > 0 ) : ?>
                    <div class="flex justify-between text-green-600">
                        <span><?php esc_html_e( 'Rabatt (Gutschein):', 'yprint-checkout' ); ?></span> 
                        <span>-€<?php echo esc_html( number_format( $cart_totals['discount'], 2, ',', '.' ) ); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="flex justify-between">
                        <span><?php esc_html_e( 'inkl. MwSt.:', 'yprint-checkout' ); ?></span> 
                        <span>€<?php echo esc_html( number_format( $cart_totals['tax'], 2, ',', '.' ) ); ?></span>
                    </div>
                    <hr class="my-2 border-gray-300">
                    <div class="flex justify-between text-xl font-bold text-green-600">
                        <span><?php esc_html_e( 'Gesamtbetrag:', 'yprint-checkout' ); ?></span> 
                        <span>€<?php echo esc_html( number_format( $cart_totals['total'], 2, ',', '.' ) ); ?></span>
                    </div>
                </div>
                
                <div class="mt-3 text-center text-sm text-green-700 bg-green-100 p-3 rounded border border-green-200">
                    <i class="fas fa-shield-alt mr-1"></i> 
                    <?php esc_html_e( 'Danke für deine Bestellung! Wir machen uns gleich an den Druck.', 'yprint-checkout' ); ?>
                </div>
            </div>
        </div>

        <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg text-sm text-blue-800 space-y-2">
            <h4 class="font-semibold text-blue-900 mb-2"><?php esc_html_e( 'Was passiert als nächstes?', 'yprint-checkout' ); ?></h4>
            <p><i class="fas fa-clock fa-fw mr-2"></i> <?php esc_html_e( 'Wir bearbeiten Ihre Bestellung innerhalb von 24 Stunden.', 'yprint-checkout' ); ?></p>
            <p><i class="fas fa-truck fa-fw mr-2"></i> <?php esc_html_e( 'Geschätzte Lieferzeit: 4-7 Werktage.', 'yprint-checkout' ); ?></p>
            <p><i class="fas fa-envelope fa-fw mr-2"></i> <?php esc_html_e( 'Sie erhalten eine Versandbestätigung per E-Mail.', 'yprint-checkout' ); ?></p>
            <p><i class="fas fa-headset fa-fw mr-2"></i> <?php esc_html_e( 'Fragen?', 'yprint-checkout' ); ?> <a href="https://yprint.de/help" target="_blank" rel="noopener noreferrer" class="text-blue-600 hover:underline font-medium"><?php esc_html_e( 'Support kontaktieren', 'yprint-checkout' ); ?></a></p>
        </div>

        <div class="mt-6 text-center">
            <a href="https://yprint.de/dashboard" class="btn btn-primary text-lg px-8 py-3 inline-flex items-center">
                <i class="fas fa-tachometer-alt mr-2"></i> 
                <?php esc_html_e( 'Zurück zum Dashboard', 'yprint-checkout' ); ?>
            </a>
        </div>

    </div>
</div>