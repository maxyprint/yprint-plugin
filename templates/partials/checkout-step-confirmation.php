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
    // PRIORITÄT 1: Order-ID aus URL-Parameter (nach erfolgreicher Zahlung)
    $order_id = null;
    if (isset($_GET['order_id']) && !empty($_GET['order_id'])) {
        $order_id = intval($_GET['order_id']);
        error_log('YPrint Confirmation: Order ID from URL parameter: ' . $order_id);
    }

    // PRIORITÄT 2: Fallback - Versuche, die Order-ID aus Session-Quellen zu ermitteln
    if ( ! $order_id ) {
        $order_data_from_session = WC()->session->get( 'yprint_pending_order' );
        $order_id                = $order_data_from_session['order_id'] ?? null;

        if ( ! $order_id ) {
            $last_order_id_session = WC()->session->get( 'yprint_last_order_id' );
            // Prüfe, ob es sich um eine gültige YP-ID handelt.
            if ( is_string( $last_order_id_session ) && str_starts_with( $last_order_id_session, 'YP-' ) ) {
                $order_id = substr( $last_order_id_session, 3 );
            } else {
                $order_id = $last_order_id_session;
            }
        }
        error_log('YPrint Confirmation: Order ID from session fallback: ' . ($order_id ?: 'NONE'));
    }

    // Lade das Order-Objekt, wenn eine ID gefunden wurde.
    if ( $order_id ) {
        $final_order = wc_get_order( $order_id );
        error_log('YPrint Confirmation: Order loaded successfully: ' . ($final_order ? 'YES' : 'NO'));
    } else {
        error_log('YPrint Confirmation: FEHLER - Keine Order ID gefunden!');
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

    // 3. Lade die Artikel AUS DER BESTELLUNG (nicht aus dem Cart - der ist nach Payment leer!)
    if ( $final_order instanceof \WC_Order ) {
        error_log('YPrint Confirmation: Loading items from order...');
        
        foreach ( $final_order->get_items() as $item_id => $item ) {
            $product = $item->get_product();
            if ( ! $product ) {
                continue;
            }

            $item_data = [
                'name'              => $item->get_name(),
                'quantity'          => $item->get_quantity(),
                'price'             => $item->get_subtotal() / $item->get_quantity(), // Einzelpreis
                'total'             => $item->get_total(),
                'image'             => wp_get_attachment_image_url( $product->get_image_id(), 'thumbnail' ) ?: wc_placeholder_img_src( 'thumbnail' ),
                'is_design_product' => false,
            ];

            // Prüfe auf Design-Produkt über Order Item Meta
            $print_design_meta = $item->get_meta('print_design');
            $design_id_meta = $item->get_meta('_design_id');
            $design_name_meta = $item->get_meta('_design_name');
            
            if ( $print_design_meta || $design_id_meta || $design_name_meta ) {
                $item_data['is_design_product'] = true;
                
                // Design-Details aus Order Meta extrahieren
                if ( $print_design_meta ) {
                    $design_data = is_string($print_design_meta) ? json_decode($print_design_meta, true) : $print_design_meta;
                    if (is_array($design_data)) {
                        $item_data['design_details'] = $design_data['name'] ?? 'Custom Design';
                        $item_data['design_preview'] = $design_data['preview_url'] ?? $design_data['design_image_url'] ?? '';
                    }
                } else {
                    // Fallback zu einzelnen Meta-Feldern
                    $item_data['design_details'] = $design_name_meta ?: 'Custom Design';
                    $item_data['design_preview'] = '';
                }
            }

            $cart_items[] = $item_data;
        }
        
        // 4. Berechne die Gesamtbeträge AUS DER BESTELLUNG
        $cart_totals = [
            'subtotal' => $final_order->get_subtotal(),
            'shipping' => $final_order->get_shipping_total(),
            'tax'      => $final_order->get_total_tax(),
            'discount' => $final_order->get_discount_total(),
            'total'    => $final_order->get_total(),
        ];
        
        error_log('YPrint Confirmation: Loaded ' . count($cart_items) . ' items from order');
    } else {
        error_log('YPrint Confirmation: WARNUNG - Keine gültige Bestellung für Artikel-Ladung gefunden!');
        
        // Fallback: Versuche Artikel aus Cart zu laden (falls noch vorhanden)
        if ( WC()->cart && ! WC()->cart->is_empty() ) {
            error_log('YPrint Confirmation: Fallback - Lade Artikel aus Cart');
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

            // 4. Berechne die Gesamtbeträge aus Cart (Fallback)
            $cart_totals = [
                'subtotal' => WC()->cart->get_subtotal(),
                'shipping' => WC()->cart->get_shipping_total(),
                'tax'      => WC()->cart->get_total_tax(),
                'discount' => WC()->cart->get_discount_total(),
                'total'    => WC()->cart->get_total( 'edit' ),
            ];
        }
    }

    // 5. Ermittle die gewählte Zahlungsmethode
// Priorisiere die zuverlässigsten Quellen zuerst
$chosen_payment_method = null;

// 1. Priorität: Pending Order Data (zuverlässigste Quelle für Stripe-Zahlungen)
$pending_order_data = WC()->session->get( 'yprint_pending_order' );
if ( $pending_order_data && ! empty( $pending_order_data['payment_method_type'] ) ) {
    $chosen_payment_method = $pending_order_data['payment_method_type'];
    error_log( 'YPrint Payment Method from pending_order: ' . $chosen_payment_method );
}

// 2. Priorität: YPrint Checkout Session
if ( empty( $chosen_payment_method ) ) {
    $chosen_payment_method = WC()->session->get( 'yprint_checkout_payment_method' );
    if ( ! empty( $chosen_payment_method ) ) {
        error_log( 'YPrint Payment Method from yprint_checkout_payment_method: ' . $chosen_payment_method );
    }
}

// 3. Priorität: Standard WooCommerce Session
if ( empty( $chosen_payment_method ) ) {
    $chosen_payment_method = WC()->session->get( 'chosen_payment_method' );
    if ( ! empty( $chosen_payment_method ) ) {
        error_log( 'YPrint Payment Method from chosen_payment_method: ' . $chosen_payment_method );
    }
}

// 4. PRIORITÄT: Payment Method Title aus der finalen Order + Stripe API Details
$payment_method_title_from_order = '';
$stripe_payment_details = [];

if ($final_order instanceof \WC_Order) {
    $payment_method_title_from_order = $final_order->get_payment_method_title();
    
    // Hole Payment Intent ID aus der Order
    $payment_intent_id = $final_order->get_transaction_id();
    if (empty($payment_intent_id)) {
        $payment_intent_id = $final_order->get_meta('_yprint_stripe_intent_id');
    }
    
    // Falls Payment Intent ID verfügbar, hole echte Zahlungsdetails von Stripe
    if (!empty($payment_intent_id) && strpos($payment_intent_id, 'pi_') === 0) {
        error_log('YPrint: Fetching Stripe Payment Intent details for: ' . $payment_intent_id);
        
        try {
            // Stripe API Abfrage für Payment Intent Details
            if (class_exists('YPrint_Stripe_API')) {
                $intent = YPrint_Stripe_API::request(array(), 'payment_intents/' . $payment_intent_id, 'GET');
                
                if (!empty($intent) && !isset($intent->error)) {
                    // Extrahiere echte Zahlungsmethoden-Details
                    if (isset($intent->payment_method_details)) {
                        $payment_details = $intent->payment_method_details;
                        
                        // Card-basierte Zahlungen (Apple Pay, Google Pay, etc.)
                        if (isset($payment_details->card)) {
                            $card_details = $payment_details->card;
                            
                            // Wallet-Zahlungen (Apple Pay, Google Pay)
                            if (isset($card_details->wallet)) {
                                $wallet_type = $card_details->wallet->type;
                                
                                switch ($wallet_type) {
                                    case 'apple_pay':
                                        $stripe_payment_details = [
                                            'type' => 'apple_pay',
                                            'title' => __('Apple Pay (Stripe)', 'yprint-checkout'),
                                            'icon' => 'fab fa-apple'
                                        ];
                                        break;
                                    case 'google_pay':
                                        $stripe_payment_details = [
                                            'type' => 'google_pay',
                                            'title' => __('Google Pay (Stripe)', 'yprint-checkout'),
                                            'icon' => 'fab fa-google-pay'
                                        ];
                                        break;
                                    default:
                                        $stripe_payment_details = [
                                            'type' => 'express',
                                            'title' => sprintf(__('%s (Stripe)', 'yprint-checkout'), ucfirst(str_replace('_', ' ', $wallet_type))),
                                            'icon' => 'fas fa-bolt'
                                        ];
                                }
                                error_log('YPrint: Detected wallet payment: ' . $wallet_type);
                            } else {
                                // Normale Kartenzahlung
                                $brand = isset($card_details->brand) ? ucfirst($card_details->brand) : 'Kreditkarte';
                                $last4 = isset($card_details->last4) ? ' ****' . $card_details->last4 : '';
                                
                                $stripe_payment_details = [
                                    'type' => 'card',
                                    'title' => sprintf(__('%s%s (Stripe)', 'yprint-checkout'), $brand, $last4),
                                    'icon' => 'fas fa-credit-card'
                                ];
                                error_log('YPrint: Detected card payment: ' . $brand . $last4);
                            }
                        }
                        // SEPA-Lastschrift
                        elseif (isset($payment_details->sepa_debit)) {
                            $sepa_details = $payment_details->sepa_debit;
                            $last4 = isset($sepa_details->last4) ? ' ****' . $sepa_details->last4 : '';
                            
                            $stripe_payment_details = [
                                'type' => 'sepa_debit',
                                'title' => sprintf(__('SEPA-Lastschrift%s (Stripe)', 'yprint-checkout'), $last4),
                                'icon' => 'fas fa-university'
                            ];
                            error_log('YPrint: Detected SEPA payment: ' . $last4);
                        }
                        // Andere Zahlungsarten
                        else {
                            $payment_type = array_keys((array) $payment_details)[0] ?? 'unknown';
                            $stripe_payment_details = [
                                'type' => $payment_type,
                                'title' => sprintf(__('%s (Stripe)', 'yprint-checkout'), ucfirst(str_replace('_', ' ', $payment_type))),
                                'icon' => 'fas fa-credit-card'
                            ];
                            error_log('YPrint: Detected other payment type: ' . $payment_type);
                        }
                    }
                } else {
                    error_log('YPrint: Stripe API error or empty response for Payment Intent: ' . $payment_intent_id);
                }
            } else {
                error_log('YPrint: YPrint_Stripe_API class not available');
            }
        } catch (Exception $e) {
            error_log('YPrint: Error fetching Stripe Payment Intent: ' . $e->getMessage());
        }
    } else {
        error_log('YPrint: No valid Payment Intent ID found in order');
    }
    
    // Fallback: Verwende ursprüngliche Order-Daten
    if (empty($stripe_payment_details) && !empty($payment_method_title_from_order)) {
        error_log('YPrint: Using fallback payment method title from order: ' . $payment_method_title_from_order);
    }
    
    // Fallback zur Payment Method ID falls kein Title verfügbar
    if (empty($chosen_payment_method)) {
        $payment_method_from_order = $final_order->get_payment_method();
        if (!empty($payment_method_from_order)) {
            $chosen_payment_method = $payment_method_from_order;
            error_log('YPrint Payment Method from final_order: ' . $chosen_payment_method);
        }
    }
} else {
    error_log('YPrint: No final order available for payment method detection');
}

// Debug: Alle verfügbaren Session-Daten loggen
error_log( 'YPrint Payment Method DEBUG - All session data:' );
error_log( '  - yprint_checkout_payment_method: ' . ( WC()->session->get( 'yprint_checkout_payment_method' ) ?: 'empty' ) );
error_log( '  - chosen_payment_method: ' . ( WC()->session->get( 'chosen_payment_method' ) ?: 'empty' ) );
error_log( '  - pending_order.payment_method_type: ' . ( $pending_order_data['payment_method_type'] ?? 'empty' ) );
error_log( '  - Final chosen_payment_method: ' . ( $chosen_payment_method ?: 'empty' ) );
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
// 1. PRIORITÄT: Verwende echte Stripe-Zahlungsdetails (falls verfügbar)
if ( ! empty( $stripe_payment_details ) ) {
    $payment_method_html = sprintf( 
        '<i class="%s mr-2"></i> %s', 
        esc_attr( $stripe_payment_details['icon'] ), 
        esc_html( $stripe_payment_details['title'] ) 
    );
    echo '<span id="dynamic-payment-method-display">' . wp_kses_post( $payment_method_html ) . '</span>';
    
    error_log( 'YPrint: Using Stripe API payment details: ' . $stripe_payment_details['type'] );
}
// 2. FALLBACK: Payment Method Title aus der Order
elseif ( ! empty( $payment_method_title_from_order ) ) {
    // Füge nur ein passendes Icon hinzu basierend auf dem Titel
    $icon = 'fa-credit-card'; // Standard
    if ( stripos( $payment_method_title_from_order, 'apple' ) !== false ) {
        $icon = 'fab fa-apple';
    } elseif ( stripos( $payment_method_title_from_order, 'google' ) !== false ) {
        $icon = 'fab fa-google-pay';
    } elseif ( stripos( $payment_method_title_from_order, 'paypal' ) !== false ) {
        $icon = 'fab fa-paypal';
    } elseif ( stripos( $payment_method_title_from_order, 'sepa' ) !== false ) {
        $icon = 'fas fa-university';
    } elseif ( stripos( $payment_method_title_from_order, 'express' ) !== false ) {
        $icon = 'fas fa-bolt';
    }
    
    $payment_method_html = sprintf( '<i class="%s mr-2"></i> %s', esc_attr( $icon ), esc_html( $payment_method_title_from_order ) );
    echo '<span id="dynamic-payment-method-display">' . wp_kses_post( $payment_method_html ) . '</span>';
    
    error_log( 'YPrint: Using payment method title from order: ' . $payment_method_title_from_order );
} else {
    // 3. FALLBACK: Verwende die bestehende Logik für Fälle ohne finale Order
    $payment_method_html = yprint_get_payment_method_display( $chosen_payment_method );
    echo '<span id="dynamic-payment-method-display">' . wp_kses_post( $payment_method_html ) . '</span>';
    
    error_log( 'YPrint: Using fallback payment method display for: ' . $chosen_payment_method );
}
?>
            </div>
        </div>
        
        <script>
document.addEventListener('DOMContentLoaded', function() {

    // DEBUG: Payment Method Detection
    async function debugPaymentMethodDetection() {
        console.log('=== YPRINT PAYMENT METHOD DEBUG START ===');

        // 1. Check Order ID from URL
        const urlParams = new URLSearchParams(window.location.search);
        const orderIdFromUrl = urlParams.get('order_id');
        console.log('Order ID from URL:', orderIdFromUrl);

        // 2. Check for stored payment data from Apple Pay or other express payments
        if (window.confirmationPaymentData && window.confirmationPaymentData.payment_method_id) {
            console.log('=== PAYMENT DATA FROM confirmationPaymentData ===');
            console.log('Payment Method ID:', window.confirmationPaymentData.payment_method_id);
            console.log('Payment Intent ID:', window.confirmationPaymentData.payment_intent_id);
            console.log('Order ID:', window.confirmationPaymentData.order_id);

            // Check if we have order_data with detailed payment info
            if (window.confirmationPaymentData.order_data) {
                console.log('=== ORDER DATA DETAILS ===');
                const orderData = window.confirmationPaymentData.order_data;
                console.log('Customer Details:', orderData.customer_details);
                console.log('Billing Address:', orderData.billing_address);
                console.log('Shipping Address:', orderData.shipping_address);

                // Try to call our debug AJAX with this order
                if (orderData.order_id) {
                    await callDebugAjax(orderData.order_id);
                }
            } else if (orderIdFromUrl) { // Fallback if no order_data but orderId in URL
                await callDebugAjax(orderIdFromUrl);
            }
        } else if (orderIdFromUrl) {
            // If no confirmationPaymentData but an order ID in URL, try AJAX
            await callDebugAjax(orderIdFromUrl);
        } else {
            console.log('❌ No order ID or confirmationPaymentData found');
        }

        console.log('=== YPRINT PAYMENT METHOD DEBUG END ===');
    }

    // AJAX Debug function
    async function callDebugAjax(orderId) {
        try {
            const formData = new FormData();
            formData.append('action', 'yprint_debug_payment_method');
            formData.append('order_id', orderId);
            formData.append('nonce', '<?php echo wp_create_nonce('yprint_debug_nonce'); ?>');

            const response = await fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();
            console.log('=== SERVER DEBUG RESPONSE ===');
            console.log('Server Response:', data);

            if (data.success) {
                const debug = data.data;

                console.log('=== ORDER PAYMENT DATA ===');
                console.log('Payment Method (WC):', debug.wc_payment_method);
                console.log('Payment Method Title (WC):', debug.wc_payment_method_title);
                console.log('Transaction ID:', debug.transaction_id);
                console.log('Payment Intent ID:', debug.payment_intent_id);

                console.log('=== STRIPE API RESPONSE ===');
                if (debug.stripe_intent) {
                    console.log('Stripe Intent Status:', debug.stripe_intent.status);
                    console.log('Stripe Intent ID:', debug.stripe_intent.id);

                    if (debug.stripe_intent.payment_method_details) {
                        console.log('Payment Method Details:', debug.stripe_intent.payment_method_details);

                        const details = debug.stripe_intent.payment_method_details;

                        // Check for card payments
                        if (details.card) {
                            console.log('=== CARD PAYMENT DETAILS ===');
                            console.log('Card Brand:', details.card.brand);
                            console.log('Card Last4:', details.card.last4);
                            console.log('Card Country:', details.card.country);

                            if (details.card.wallet) {
                                console.log('=== WALLET DETAILS (WICHTIG!) ===');
                                console.log('Wallet Type:', details.card.wallet.type);
                                console.log('Wallet Details:', details.card.wallet);

                                // Das ist der wichtige Teil für Apple Pay Detection!
                                if (details.card.wallet.type === 'apple_pay') {
                                    console.log('✅ APPLE PAY DETECTED!');
                                } else if (details.card.wallet.type === 'google_pay') {
                                    console.log('✅ GOOGLE PAY DETECTED!');
                                }
                            } else {
                                console.log('❌ No wallet info - regular card payment');
                            }
                        }

                        // Check for SEPA payments
                        if (details.sepa_debit) {
                            console.log('=== SEPA PAYMENT DETAILS ===');
                            console.log('SEPA Last4:', details.sepa_debit.last4);
                            console.log('SEPA Bank Code:', details.sepa_debit.bank_code);
                        }
                    } else {
                        console.log('❌ No payment_method_details in Stripe response');
                    }
                } else {
                    console.log('❌ No Stripe Intent data available');
                    console.log('Stripe Error:', debug.stripe_error);
                }

                console.log('=== FINAL DETECTION RESULT ===');
                console.log('Detected Payment Type:', debug.detected_type);
                console.log('Display Title:', debug.display_title);
                console.log('Display Icon:', debug.display_icon);

            } else {
                console.error('Debug AJAX failed:', data.data);
            }

        } catch (error) {
            console.error('Debug AJAX error:', error);
        }
    }

    // Start debugging after short delay
    setTimeout(debugPaymentMethodDetection, 1000);

    function getPaymentMethodTitle() {
    console.log('🔍 getPaymentMethodTitle() aufgerufen');
    
    // PRIORITÄT 1: Nutze verfügbare Payment Method Details von Express Checkout
    if (window.confirmationPaymentData && window.confirmationPaymentData.order_data) {
        console.log('🔍 Checking confirmationPaymentData for payment method details...');
        const orderData = window.confirmationPaymentData.order_data;
        
        // HAUPTLOGIK: Prüfe payment_method_details (Backend-Ergänzung)
        if (orderData.payment_method_details) {
            console.log('✅ payment_method_details gefunden:', orderData.payment_method_details);
            
            // Card-basierte Zahlungen (Apple Pay, Google Pay, normale Karten)
            if (orderData.payment_method_details.card) {
                const cardDetails = orderData.payment_method_details.card;
                console.log('✅ Card details gefunden:', cardDetails);
                
                // Wallet-Zahlungen (Apple Pay, Google Pay)
                if (cardDetails.wallet) {
                    const walletType = cardDetails.wallet.type;
                    const cardBrand = cardDetails.brand || '';
                    const last4 = cardDetails.last4 || '';
                    
                    console.log('✅ Wallet detected:', walletType, 'Brand:', cardBrand, 'Last4:', last4);
                    
                    switch (walletType) {
                        case 'apple_pay':
                            return '<i class="fab fa-apple mr-2"></i> ' + yprint_checkout_l10n.payment_methods.apple_pay;
                        case 'google_pay':
                            return '<i class="fab fa-google-pay mr-2"></i> ' + yprint_checkout_l10n.payment_methods.google_pay;
                        default:
                            return '<i class="fas fa-bolt mr-2"></i> ' + yprint_checkout_l10n.payment_methods.stripe_payment;
                    }
                } else {
                    // Normale Kartenzahlung
                    const cardBrand = (cardDetails.brand || 'Kreditkarte').charAt(0).toUpperCase() + (cardDetails.brand || 'kreditkarte').slice(1);
                    const last4 = cardDetails.last4 ? ' ****' + cardDetails.last4 : '';
                    console.log('✅ Regular card detected:', cardBrand, last4);
                    return '<i class="fas fa-credit-card mr-2"></i> ' + cardBrand + last4 + ' (Stripe)';
                }
            }
            
            // SEPA-Lastschrift
            if (orderData.payment_method_details.sepa_debit) {
                const sepaDetails = orderData.payment_method_details.sepa_debit;
                const last4 = sepaDetails.last4 || '';
                console.log('✅ SEPA detected, Last4:', last4);
                const displayLast4 = last4 ? ' ****' + last4 : '';
                return '<i class="fas fa-university mr-2"></i> ' + yprint_checkout_l10n.payment_methods.sepa_debit + displayLast4 + ' (Stripe)';
            }
            
            // Andere Zahlungsarten
            const paymentType = Object.keys(orderData.payment_method_details)[0];
            if (paymentType) {
                console.log('✅ Other payment type detected:', paymentType);
                return '<i class="fas fa-credit-card mr-2"></i> ' + yprint_checkout_l10n.payment_methods.stripe_payment;
            }
        }
        
        // LEGACY FALLBACKS für Abwärtskompatibilität
        if (window.confirmationPaymentData.payment_method_type) {
            const paymentType = window.confirmationPaymentData.payment_method_type.toLowerCase();
            console.log('⚠️ Using legacy payment_method_type:', paymentType);
            
            if (paymentType.includes('apple_pay') || paymentType === 'apple_pay') {
                return '<i class="fab fa-apple mr-2"></i> ' + yprint_checkout_l10n.payment_methods.apple_pay;
            } else if (paymentType.includes('google_pay') || paymentType === 'google_pay') {
                return '<i class="fab fa-google-pay mr-2"></i> ' + yprint_checkout_l10n.payment_methods.google_pay;
            } else if (paymentType.includes('sepa')) {
                return '<i class="fas fa-university mr-2"></i> ' + yprint_checkout_l10n.payment_methods.sepa_debit + ' (Stripe)';
            }
        }
        
        // FALLBACK: Express Payment Detection basierend auf Payment Method ID-Struktur
        if (orderData.payment_method_id && orderData.payment_method_id.startsWith('pm_')) {
            console.log('⚠️ Using fallback - Express payment detected from payment_method_id structure only');
            return '<i class="fas fa-bolt mr-2"></i> ' + yprint_checkout_l10n.payment_methods.stripe_payment;
        }
        
        console.log('⚠️ Keine spezifische Zahlungsart in payment_method_details erkannt');
    } else {
        console.log('❌ Keine confirmationPaymentData.order_data verfügbar');
    }
    
    // Kein Titel gefunden
    return null;
}

function updatePaymentMethodDisplay() {
    const displayElement = document.getElementById('dynamic-payment-method-display');
    if (!displayElement) return;

    console.log('🔍 updatePaymentMethodDisplay() aufgerufen');
    console.log('🔍 window.confirmationPaymentData verfügbar:', !!window.confirmationPaymentData);
    
    if (window.confirmationPaymentData && window.confirmationPaymentData.order_data) {
        console.log('🔍 order_data verfügbar:', !!window.confirmationPaymentData.order_data);
        console.log('🔍 payment_method_details verfügbar:', !!window.confirmationPaymentData.order_data.payment_method_details);
    }

    // Direkt die getPaymentMethodTitle() Funktion aufrufen - sie hat ihre eigene Intelligenz
    const title = getPaymentMethodTitle();

    if (title) {
        displayElement.innerHTML = title;
        console.log('✅ Payment method display aktualisiert:', title);
    } else {
        // Fallback: Zeige "wird ermittelt" nur wenn gar keine Daten verfügbar sind
        if (!window.confirmationPaymentData) {
            displayElement.innerHTML = '<i class="fas fa-exclamation-triangle mr-2 text-amber-500"></i> <span class="text-amber-600">' + yprint_checkout_l10n.payment_methods.payment_pending + '</span>';
            console.log('⚠️ Keine confirmationPaymentData verfügbar - zeige Warteanzeige');
        } else {
            // Wenn Daten da sind, aber keine Zahlungsart erkannt wurde
            displayElement.innerHTML = '<i class="fas fa-credit-card mr-2"></i> ' + yprint_checkout_l10n.payment_methods.stripe_payment;
            console.log('⚠️ Payment Data verfügbar, aber keine spezifische Zahlungsart erkannt - zeige Fallback');
        }
    }
}

    // Sofort beim Load versuchen
updatePaymentMethodDisplay();

// Multiple Event-Handler für verschiedene Szenarien
const originalPopulateConfirmation = window.populateConfirmationWithPaymentData;
if (originalPopulateConfirmation) {
    window.populateConfirmationWithPaymentData = function(paymentData) {
        // Store Payment Data globally for Payment Method Detection
        window.confirmationPaymentData = paymentData;
        console.log('✅ Confirmation payment data updated via populateConfirmationWithPaymentData:', paymentData);

        // Call the original function
        originalPopulateConfirmation.call(this, paymentData);

        // Force update payment method display after original function runs
        setTimeout(updatePaymentMethodDisplay, 50);
    };
}

// Alternative: Überwache window.confirmationPaymentData direkt
let confirmationDataWatcher = null;
function startConfirmationDataWatcher() {
    if (confirmationDataWatcher) clearInterval(confirmationDataWatcher);
    
    let attempts = 0;
    const maxAttempts = 20; // 10 Sekunden max
    
    confirmationDataWatcher = setInterval(() => {
        attempts++;
        
        if (window.confirmationPaymentData && window.confirmationPaymentData.order_data) {
            console.log('✅ confirmationPaymentData via Watcher erkannt - aktualisiere Payment Method Display');
            clearInterval(confirmationDataWatcher);
            updatePaymentMethodDisplay();
        } else if (attempts >= maxAttempts) {
            console.log('⚠️ confirmationPaymentData Watcher timeout nach', maxAttempts, 'Versuchen');
            clearInterval(confirmationDataWatcher);
        }
    }, 500);
}

// Starte Watcher nach 1 Sekunde
setTimeout(startConfirmationDataWatcher, 1000);

// Event Listener for updates on step change
document.addEventListener('yprint_step_changed', (e) => {
    if (e.detail.step === 3) {
        console.log('🔄 Step 3 detected - aktualisiere Payment Method Display');
        setTimeout(updatePaymentMethodDisplay, 100);
        setTimeout(debugPaymentMethodDetection, 200); // Re-run debug on step 3
    }
});

// Global Event für Payment Data Updates
window.addEventListener('yprint_payment_data_updated', () => {
    console.log('🔄 yprint_payment_data_updated Event empfangen');
    updatePaymentMethodDisplay();
});

    // Event Listener for updates on step change
    document.addEventListener('yprint_step_changed', (e) => {
        if (e.detail.step === 3) {
            setTimeout(updatePaymentMethodDisplay, 100);
            setTimeout(debugPaymentMethodDetection, 200); // Re-run debug on step 3
        }
    });
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