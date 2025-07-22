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

    // 2. Lade Adressdaten - PRIORITÄT: Session-Daten vor Order-Daten
$customer_data = [
    'email'    => '',
    'phone'    => '',
    'shipping' => [],
    'billing'  => [],
];

// Zunächst: Versuche YPrint Session-Daten zu laden
$yprint_selected = WC()->session ? WC()->session->get('yprint_selected_address', array()) : array();
$yprint_billing = WC()->session ? WC()->session->get('yprint_billing_address', array()) : array();
$yprint_billing_different = WC()->session ? WC()->session->get('yprint_billing_address_different', false) : false;

error_log('YPrint Confirmation: Session data check - Selected: ' . (!empty($yprint_selected) ? 'YES' : 'NO'));
error_log('YPrint Confirmation: Session data check - Billing Different: ' . ($yprint_billing_different ? 'YES' : 'NO'));

// Wenn Session-Daten verfügbar sind, nutze diese (AUTORITATIVE)
if (!empty($yprint_selected)) {
    error_log('YPrint Confirmation: Using YPRINT SESSION DATA as primary source');
    
    // Setze Lieferadresse aus Session
    $customer_data['shipping'] = [
        'first_name' => $yprint_selected['first_name'] ?? '',
        'last_name'  => $yprint_selected['last_name'] ?? '',
        'address_1'  => $yprint_selected['address_1'] ?? '',
        'address_2'  => $yprint_selected['address_2'] ?? '',
        'city'       => $yprint_selected['city'] ?? '',
        'postcode'   => $yprint_selected['postcode'] ?? '',
        'country'    => $yprint_selected['country'] ?? 'DE',
        'phone'      => $yprint_selected['phone'] ?? '',
    ];
    
    // templates/partials/checkout-step-confirmation.php - Rechnungsadresse Sektion
// Setze Rechnungsadresse: Entweder separate oder gleiche wie Shipping
if ($yprint_billing_different && !empty($yprint_billing) && !empty($yprint_billing['address_1'])) {
    error_log('YPrint Confirmation: Using SEPARATE BILLING ADDRESS from session');
    $customer_data['billing'] = [
        'first_name' => $yprint_billing['first_name'] ?? '',
        'last_name'  => $yprint_billing['last_name'] ?? '',
        'address_1'  => $yprint_billing['address_1'] ?? '',
        'address_2'  => $yprint_billing['address_2'] ?? '',
        'city'       => $yprint_billing['city'] ?? '',
        'postcode'   => $yprint_billing['postcode'] ?? '',
        'country'    => $yprint_billing['country'] ?? 'DE',
        'phone'      => $yprint_billing['phone'] ?? '',
    ];
    error_log('YPrint Confirmation: Final billing address set to: ' . $customer_data['billing']['address_1']);
} else {
    error_log('YPrint Confirmation: Using SHIPPING AS BILLING ADDRESS from session');
    $customer_data['billing'] = $customer_data['shipping'];
    error_log('YPrint Confirmation: Billing=Shipping, address: ' . $customer_data['billing']['address_1']);
}
    
    // E-Mail und Telefon aus User-Daten oder Order
    if ( $final_order instanceof \WC_Order ) {
        $customer_data['email'] = $final_order->get_billing_email();
        $customer_data['phone'] = $final_order->get_billing_phone();
    } else if ( is_user_logged_in() ) {
        $current_user = wp_get_current_user();
        $customer_data['email'] = $current_user->user_email;
    }
    
} else {
    error_log('YPrint Confirmation: No session data found, using ORDER DATA as fallback');
    // Fallback: Lade aus der Bestellung (bestehende Logik)
    if ( $final_order instanceof \WC_Order ) {
        $customer_data = [
            'email'    => $final_order->get_billing_email(),
            'phone'    => $final_order->get_billing_phone(),
            'shipping' => $final_order->get_address( 'shipping' ),
            'billing'  => $final_order->get_address( 'billing' ),
        ];
    }
}

error_log('YPrint Confirmation: Final shipping address: ' . ($customer_data['shipping']['address_1'] ?? 'NONE'));
error_log('YPrint Confirmation: Final billing address: ' . ($customer_data['billing']['address_1'] ?? 'NONE'));

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
                        
                        // UNIVERSELLE ZAHLUNGSARTEN-ERKENNUNG
if (isset($intent->payment_method_details)) {
    $stripe_payment_details = yprint_parse_stripe_payment_details($intent->payment_method_details);
    error_log('YPrint: Dynamic payment detection result: ' . json_encode($stripe_payment_details));
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

/**
 * Universelle Stripe Payment Method Details Parser
 * Erkennt automatisch alle verfügbaren Zahlungsarten ohne Hardkodierung
 *
 * @param object $payment_details - Stripe payment_method_details Objekt
 * @return array - Normalisierte Zahlungsdetails
 */
function yprint_parse_stripe_payment_details($payment_details) {
    // Payment Method Icons Mapping
    $icon_mapping = [
        'apple_pay' => 'fas fa-mobile-alt',
        'google_pay' => 'fas fa-mobile-alt', 
        'card' => 'fas fa-credit-card',
        'sepa_debit' => 'fas fa-university',
        'bancontact' => 'fas fa-credit-card',
        'ideal' => 'fas fa-university',
        'giropay' => 'fas fa-university',
        'sofort' => 'fas fa-bolt',
        'eps' => 'fas fa-university',
        'p24' => 'fas fa-university',
        'alipay' => 'fab fa-alipay',
        'wechat_pay' => 'fab fa-weixin',
        'klarna' => 'fas fa-credit-card',
        'afterpay_clearpay' => 'fas fa-credit-card',
        'affirm' => 'fas fa-credit-card',
    ];

    // Lokalisierte Zahlungsarten-Namen
    $name_mapping = [
        'apple_pay' => __('Apple Pay', 'yprint-checkout'),
        'google_pay' => __('Google Pay', 'yprint-checkout'),
        'sepa_debit' => __('SEPA-Lastschrift', 'yprint-checkout'),
        'bancontact' => __('Bancontact', 'yprint-checkout'),
        'ideal' => __('iDEAL', 'yprint-checkout'),
        'giropay' => __('Giropay', 'yprint-checkout'),
        'sofort' => __('SOFORT', 'yprint-checkout'),
        'eps' => __('EPS', 'yprint-checkout'),
        'p24' => __('Przelewy24', 'yprint-checkout'),
        'alipay' => __('Alipay', 'yprint-checkout'),
        'wechat_pay' => __('WeChat Pay', 'yprint-checkout'),
        'klarna' => __('Klarna', 'yprint-checkout'),
        'afterpay_clearpay' => __('Afterpay/Clearpay', 'yprint-checkout'),
        'affirm' => __('Affirm', 'yprint-checkout'),
    ];

    // Hauptlogik: Finde den primären Payment Type
    $payment_types = array_keys((array) $payment_details);
    $primary_type = $payment_types[0] ?? 'unknown';
    
    $result = [
        'type' => $primary_type,
        'title' => '',
        'icon' => $icon_mapping['card'], // Fallback
        'details' => []
    ];

    // Verarbeite basierend auf dem erkannten Typ
    if ($primary_type === 'card' && isset($payment_details->card)) {
        $card = $payment_details->card;
        
        // Prüfe auf Wallet-Zahlungen (Apple Pay, Google Pay)
        if (isset($card->wallet)) {
            $wallet_type = $card->wallet->type;
            $result['type'] = $wallet_type;
            $result['title'] = $name_mapping[$wallet_type] ?? ucfirst(str_replace('_', ' ', $wallet_type));
            $result['icon'] = $icon_mapping[$wallet_type] ?? $icon_mapping['card'];
            
            // Zusätzliche Wallet-Details
            if (isset($card->brand)) {
                $result['details']['brand'] = $card->brand;
            }
            if (isset($card->last4)) {
                $result['details']['last4'] = $card->last4;
            }
        } else {
            // Normale Kartenzahlung
            $brand = isset($card->brand) ? ucfirst($card->brand) : 'Kreditkarte';
            $last4 = isset($card->last4) ? ' ****' . $card->last4 : '';
            
            $result['title'] = $brand . $last4;
            $result['details']['brand'] = $card->brand ?? 'unknown';
            $result['details']['last4'] = $card->last4 ?? '';
        }
    } else {
        // Alle anderen Zahlungsarten (SEPA, iDEAL, etc.)
        $payment_data = $payment_details->{$primary_type} ?? null;
        
        // Verwende vordefinierte Namen oder generiere automatisch
        $result['title'] = $name_mapping[$primary_type] ?? ucfirst(str_replace('_', ' ', $primary_type));
        $result['icon'] = $icon_mapping[$primary_type] ?? $icon_mapping['card'];
        
        // Füge spezifische Details hinzu (z.B. IBAN last4)
        if ($payment_data && isset($payment_data->last4)) {
            $result['title'] .= ' ****' . $payment_data->last4;
            $result['details']['last4'] = $payment_data->last4;
        }
    }
    
    // Füge immer "(Stripe)" hinzu
    $result['title'] .= ' (Stripe)';
    
    return $result;
}
?>

<div id="confirmation-loader" class="text-center py-10">
    <i class="fas fa-spinner fa-spin text-2xl text-blue-600"></i><br>
    <span>Bestellung wird finalisiert ...</span>
</div>
<div id="step-3" class="checkout-step" style="display:none">
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
                <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3">Lieferadresse</h3>
                <div id="shipping-address" class="text-yprint-text-secondary text-sm leading-relaxed bg-gray-50 p-4 rounded-lg"></div>
            </div>
            <div>
                <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3">Rechnungsadresse</h3>
                <div id="billing-address" class="text-yprint-text-secondary text-sm leading-relaxed bg-gray-50 p-4 rounded-lg"></div>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3">Gewählte Zahlungsart</h3>
            <div id="payment-method" class="text-yprint-text-secondary text-sm bg-gray-50 p-4 rounded-lg"></div>
        </div>
        
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Hole Order-ID aus URL
    const urlParams = new URLSearchParams(window.location.search);
    const orderId = urlParams.get('order_id');
    if (!orderId) return;

    function formatAddress(addr) {
        if (!addr || !addr.address_1) return '<span class="text-gray-500">Keine Adresse angegeben.</span>';
        let out = '';
        if (addr.first_name || addr.last_name) out += `${addr.first_name || ''} ${addr.last_name || ''}<br>`;
        out += addr.address_1;
        if (addr.address_2) out += ' ' + addr.address_2;
        out += '<br>' + (addr.postcode || '') + ' ' + (addr.city || '');
        if (addr.country) out += '<br>' + (window.wc_country_names?.[addr.country] || addr.country);
        return out;
    }

    function renderPaymentMethod(payment) {
        if (!payment) return '<span class="text-gray-500">Keine Zahlungsart erkannt.</span>';
        try {
            const details = typeof payment === 'string' ? JSON.parse(payment) : payment;
            // Card
            if (details.card) {
                let brand = details.card.brand ? details.card.brand.charAt(0).toUpperCase() + details.card.brand.slice(1) : 'Kreditkarte';
                let last4 = details.card.last4 ? ' ****' + details.card.last4 : '';
                if (details.card.wallet && details.card.wallet.type === 'apple_pay') {
                    return '<i class="fab fa-apple mr-2"></i> Apple Pay (' + brand + last4 + ')';
                }
                if (details.card.wallet && details.card.wallet.type === 'google_pay') {
                    return '<i class="fab fa-google-pay mr-2"></i> Google Pay (' + brand + last4 + ')';
                }
                return '<i class="fas fa-credit-card mr-2"></i> ' + brand + last4;
            }
            // SEPA
            if (details.sepa_debit) {
                let last4 = details.sepa_debit.last4 ? ' ****' + details.sepa_debit.last4 : '';
                return '<i class="fas fa-university mr-2"></i> SEPA-Lastschrift' + last4;
            }
            // Fallback
            return '<i class="fas fa-credit-card mr-2"></i> Stripe-Zahlung';
        } catch (e) {
            return '<i class="fas fa-credit-card mr-2"></i> Stripe-Zahlung';
        }
    }

    function pollOrderMetaReady(orderId, onReady) {
        let attempts = 0, maxAttempts = 20;
        function poll() {
            fetch(ajaxurl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=yprint_check_confirmation_ready&order_id=${orderId}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success && data.data.ready) {
                    onReady(data.data);
                } else if (attempts++ < maxAttempts) {
                    setTimeout(poll, 500);
                } else {
                    document.getElementById('confirmation-loader').innerHTML = 'Fehler: Bestelldaten konnten nicht geladen werden.';
                }
            });
        }
        poll();
    }

    pollOrderMetaReady(orderId, function(meta) {
        document.getElementById('shipping-address').innerHTML = formatAddress(meta.shipping);
        document.getElementById('billing-address').innerHTML = formatAddress(meta.billing);
        document.getElementById('payment-method').innerHTML = renderPaymentMethod(meta.payment);

        document.getElementById('confirmation-loader').style.display = 'none';
        document.getElementById('step-3').style.display = '';
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
                    <?php esc_html_e( 'Danke für deine Bestellung! Alle Details kriegst du per E-Mail.', 'yprint-checkout' ); ?>
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