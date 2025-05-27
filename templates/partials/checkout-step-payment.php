<?php
/**
 * Partial Template: Schritt 2 - Zahlungsart wählen.
 *
 * Benötigt:
 * $cart_totals_data (array) - Array mit Warenkorbsummen
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Debug-Ausgabe
error_log('Loading payment step template from: ' . __FILE__);

// $cart_totals_data sollte von checkout-multistep.php übergeben werden
// oder hier direkt von WooCommerce geladen werden.
if ( !isset($cart_totals_data) || !is_array($cart_totals_data) ) {
    $cart_totals_data = array( /* Standard-Summen, siehe checkout-cart-summary.php */ );
}

// Verfügbare Zahlungsmethoden (sollten dynamisch geladen werden, z.B. von WooCommerce)
$available_payment_methods = array(
    'paypal' => array(
        'title' => 'PayPal',
        'icon_class' => 'fab fa-paypal',
        'icon_color' => 'text-[#00457C]' // PayPal Blau
    ),
    'applepay' => array(
        'title' => 'Apple Pay',
        'icon_class' => 'fab fa-apple-pay',
        'icon_color' => 'text-black'
    ),
    'creditcard' => array( // Generische Kreditkarte, Stripe-spezifisch wäre anders
        'title' => __('Kreditkarte', 'yprint-checkout'),
        'icon_class' => 'fas fa-credit-card',
        'icon_color' => 'text-gray-600'
    ),
    'klarna' => array(
        'title' => 'Klarna',
        'svg_path' => 'M248.291 31.0084C265.803 31.0084 280.21 37.1458 291.513 49.4206C302.888 61.6954 308.575 77.0417 308.575 95.4594C308.575 113.877 302.888 129.223 291.513 141.498C280.21 153.773 265.803 159.91 248.291 159.91H180.854V31.0084H248.291ZM213.956 132.621H248.291C258.57 132.621 267.076 129.68 273.808 123.798C280.612 117.844 284.014 109.177 284.014 97.7965C284.014 86.4158 280.612 77.7491 273.808 71.7947C267.076 65.8403 258.57 62.8992 248.291 62.8992H213.956V132.621ZM143.061 31.0084H109.959V159.91H143.061V31.0084ZM495.99 31.0084L445.609 159.91H408.009L378.571 79.1557L349.132 159.91H311.532L361.914 31.0084H399.514L428.952 112.661L458.39 31.0084H495.99ZM0 31.0084H33.1017V159.91H0V31.0084Z', // Gekürzt für Lesbarkeit, im JS wird es ersetzt
        'svg_fill' => '#FFB3C7' // Klarna Pink
    ),
    // Hier Stripe Elements Container einfügen, falls Stripe als separate Option angezeigt wird
    // 'stripe_credit_card' => array('title' => 'Kreditkarte (via Stripe)', 'element_id' => 'stripe-card-element')
);
$default_payment_method = 'paypal'; // Standardmäßig vorausgewählt

?>
<?php
/**
 * Partial Template: Schritt 2 - Zahlungsart wählen.
 *
 * Benötigt:
 * $cart_totals_data (array) - Array mit Warenkorbsummen
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Debug-Ausgabe
error_log('Loading payment step template from: ' . __FILE__);

// $cart_totals_data sollte von checkout-multistep.php übergeben werden
// oder hier direkt von WooCommerce geladen werden.
if ( !isset($cart_totals_data) || !is_array($cart_totals_data) ) {
    $cart_totals_data = array( /* Standard-Summen, siehe checkout-cart-summary.php */ );
}

// WICHTIG: Kein umschließendes div-Element mehr, da es bereits in checkout-multistep.php hinzugefügt wird
?>
<h2 class="flex items-center"><i class="fas fa-credit-card mr-2 text-yprint-blue"></i><?php esc_html_e('Zahlungsart wählen', 'yprint-checkout'); ?></h2>

<?php 
// Express Checkout Buttons (Apple Pay, Google Pay)
if (class_exists('YPrint_Stripe_Checkout_Shortcode')) {
    $shortcode_instance = YPrint_Stripe_Checkout_Shortcode::get_instance();
    
    // Prüfe explizit ob Stripe konfiguriert ist
    if ($shortcode_instance->is_stripe_enabled_public()) {
        $express_buttons = $shortcode_instance->render_express_payment_buttons();
        
        if (!empty($express_buttons)) {
            echo $express_buttons;
        } else {
            // Fallback: Express-Zahlungen sind konfiguriert aber nicht verfügbar
            echo '<div class="express-payment-notice" style="padding: 10px; background: #f8f8f8; border: 1px solid #ddd; border-radius: 8px; margin: 15px 0; text-align: center;">';
            echo '<p style="margin: 0; color: #666; font-size: 14px;"><i class="fas fa-info-circle mr-2"></i>' . __('Express-Zahlungsmethoden werden auf unterstützten Geräten angezeigt', 'yprint-checkout') . '</p>';
            echo '</div>';
        }
        
        // Debug-Info nur für Administratoren
        if (current_user_can('administrator') && isset($_GET['debug'])) {
            $settings = YPrint_Stripe_API::get_stripe_settings();
            echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px;">';
            echo '<strong>Debug Express Buttons:</strong><br>';
            echo 'Stripe Enabled: ' . ($shortcode_instance->is_stripe_enabled_public() ? 'Yes' : 'No') . '<br>';
            echo 'Payment Request Setting: ' . (isset($settings['payment_request']) ? $settings['payment_request'] : 'Not Set') . '<br>';
            echo 'Express Payments Setting: ' . (isset($settings['express_payments']) ? $settings['express_payments'] : 'Not Set') . '<br>';
            echo 'Live Keys Set: ' . ((!empty($settings['publishable_key']) && !empty($settings['secret_key'])) ? 'Yes' : 'No') . '<br>';
            echo 'Test Keys Set: ' . ((!empty($settings['test_publishable_key']) && !empty($settings['test_secret_key'])) ? 'Yes' : 'No') . '<br>';
            echo 'Is SSL: ' . (is_ssl() ? 'Yes' : 'No') . '<br>';
            echo 'Express Buttons HTML Length: ' . strlen($express_buttons) . '<br>';
            echo '</div>';
        }
    } else {
        // Stripe nicht konfiguriert
        if (current_user_can('administrator')) {
            echo '<div style="background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffeaa7; border-radius: 5px;">';
            echo '<strong>Admin-Hinweis:</strong> Stripe ist nicht konfiguriert. Express-Zahlungen sind nicht verfügbar.';
            echo '</div>';
        }
    }
} else {
    if (current_user_can('administrator')) {
        echo '<div style="background: #ffeeee; padding: 10px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 5px;">';
        echo '<strong>Fehler:</strong> YPrint_Stripe_Checkout_Shortcode Klasse nicht gefunden!';
        echo '</div>';
    }
}
?>

<form id="payment-form" class="space-y-6 mt-6">
    <div class="space-y-6">
        <?php
        // Prüfe verfügbare Zahlungsmethoden
        $stripe_enabled = class_exists('YPrint_Stripe_Checkout_Shortcode') && 
                         YPrint_Stripe_Checkout_Shortcode::get_instance()->is_stripe_enabled_public();
        $stripe_settings = $stripe_enabled ? YPrint_Stripe_API::get_stripe_settings() : array();
        ?>
        
        <?php if ($stripe_enabled) : ?>
        <!-- Intelligenter Payment Bereich -->
        <div class="payment-method-container">
            <!-- Payment Method Slider -->
            <div class="payment-method-slider mb-4">
                <div class="slider-container">
                    <div class="slider-track">
                        <div class="slider-option active" data-method="card">
                            <i class="fas fa-credit-card"></i>
                            <span><?php esc_html_e('Kreditkarte', 'yprint-checkout'); ?></span>
                        </div>
                        <div class="slider-option" data-method="sepa">
                            <i class="fas fa-university"></i>
                            <span><?php esc_html_e('SEPA-Lastschrift', 'yprint-checkout'); ?></span>
                        </div>
                    </div>
                    <div class="slider-indicator"></div>
                </div>
            </div>

            <!-- Hidden Input für die gewählte Methode -->
            <input type="hidden" name="payment_method" id="selected-payment-method" value="yprint_stripe_card">

            <!-- Universeller Payment Eingabebereich -->
            <div class="payment-input-container">
                <div class="payment-fields-wrapper">
                    
                    <!-- Kreditkarten-Felder (Standard aktiv) -->
                    <div id="card-payment-fields" class="payment-input-fields active">
                        <div class="payment-field-group">
                            <label class="payment-field-label"><?php esc_html_e('Kartendaten', 'yprint-checkout'); ?></label>
                            <div id="stripe-card-element" class="payment-stripe-element"></div>
                            <div id="stripe-card-errors" class="payment-error-display"></div>
                        </div>
                        <div class="payment-options">
                            <label class="payment-checkbox-container">
                                <input type="checkbox" id="save-card" class="payment-checkbox">
                                <span class="payment-checkbox-label"><?php esc_html_e('Karte für zukünftige Zahlungen speichern', 'yprint-checkout'); ?></span>
                            </label>
                        </div>
                    </div>

                    <!-- SEPA-Felder (initial versteckt) -->
                    <div id="sepa-payment-fields" class="payment-input-fields">
                        <div class="payment-field-group">
                            <label class="payment-field-label"><?php esc_html_e('IBAN', 'yprint-checkout'); ?></label>
                            <div id="stripe-sepa-element" class="payment-stripe-element"></div>
                            <div id="stripe-sepa-errors" class="payment-error-display"></div>
                        </div>
                        <div class="sepa-info">
                            <div class="sepa-info-content">
                                <p><?php esc_html_e('Mit der Eingabe Ihrer IBAN erteilen Sie uns ein SEPA-Lastschriftmandat.', 'yprint-checkout'); ?></p>
                                <p><?php esc_html_e('Der Betrag wird von Ihrem Konto abgebucht, nachdem wir Ihre Bestellung bearbeitet haben.', 'yprint-checkout'); ?></p>
                            </div>
                        </div>
                    </div>

                </div>
                
                <?php if (isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode']) : ?>
                    <div class="test-mode-indicator">
                        <span class="test-badge"><?php esc_html_e('TEST-MODUS', 'yprint-checkout'); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php else : ?>
        <!-- Fallback wenn Stripe nicht verfügbar -->
        <div class="p-4 bg-gray-100 rounded-lg text-center">
            <p class="text-gray-600"><?php esc_html_e('Zahlungsmethoden werden geladen...', 'yprint-checkout'); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <!-- Warenkorb-Zusammenfassung -->
    <div class="mt-6 border-t border-yprint-medium-gray pt-6">
        <h3 class="text-lg font-semibold mb-2"><?php esc_html_e('Gesamtübersicht', 'yprint-checkout'); ?></h3>
        <!-- Preisdetails -->
        <div class="flex justify-between text-lg">
            <span><?php esc_html_e('Zwischensumme:', 'yprint-checkout'); ?></span>
            <span id="subtotal-price">€<?php echo isset($cart_totals_data['subtotal']) ? esc_html(number_format_i18n($cart_totals_data['subtotal'], 2)) : '0,00'; ?></span>
        </div>
        <div class="flex justify-between text-lg">
            <span><?php esc_html_e('Versandkosten:', 'yprint-checkout'); ?></span>
            <span id="shipping-price">€<?php echo isset($cart_totals_data['shipping']) ? esc_html(number_format_i18n($cart_totals_data['shipping'], 2)) : '0,00'; ?></span>
        </div>
        <?php if (isset($cart_totals_data['discount']) && $cart_totals_data['discount'] > 0) : ?>
        <div class="flex justify-between text-lg text-yprint-success">
            <span><?php esc_html_e('Rabatt:', 'yprint-checkout'); ?></span>
            <span id="discount-price">-€<?php echo esc_html(number_format_i18n($cart_totals_data['discount'], 2)); ?></span>
        </div>
        <?php endif; ?>
        <div class="flex justify-between text-xl font-bold mt-2 text-yprint-blue">
            <span><?php esc_html_e('Gesamtpreis:', 'yprint-checkout'); ?></span>
            <span id="total-price-payment">€<?php echo isset($cart_totals_data['total']) ? esc_html(number_format_i18n($cart_totals_data['total'], 2)) : '0,00'; ?></span>
        </div>
    </div>

    <!-- Gutscheincode -->
    <div class="mt-4">
        <label for="voucher" class="form-label"><?php esc_html_e('Gutscheincode', 'yprint-checkout'); ?></label>
        <div class="flex">
            <input type="text" id="voucher" name="voucher_code" class="form-input rounded-r-none" placeholder="<?php esc_attr_e('Code eingeben', 'yprint-checkout'); ?>">
            <button type="button" class="btn btn-secondary rounded-l-none whitespace-nowrap">
                <?php esc_html_e('Einlösen', 'yprint-checkout'); ?>
            </button>
        </div>
        <p id="voucher-feedback" class="text-sm mt-1"></p>
    </div>

    <!-- Sicherheitshinweis -->
    <div class="mt-6 p-3 bg-yprint-info rounded-lg text-sm text-yprint-text-secondary">
        <i class="fas fa-lock mr-2"></i> <?php esc_html_e('Ihre Daten werden SSL-verschlüsselt übertragen. Sicher einkaufen!', 'yprint-checkout'); ?>
    </div>

    <!-- Navigation Buttons -->
    <div class="pt-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <button type="button" id="btn-back-to-address" class="btn btn-secondary w-full md:w-auto order-2 md:order-1">
            <i class="fas fa-arrow-left mr-2"></i> <?php esc_html_e('Zurück zur Adresse', 'yprint-checkout'); ?>
        </button>
        <button type="button" id="btn-to-confirmation" class="btn btn-primary w-full md:w-auto order-1 md:order-2">
            <?php esc_html_e('Weiter zur Bestätigung', 'yprint-checkout'); ?> <i class="fas fa-arrow-right ml-2"></i>
        </button>
    </div>
</form>
