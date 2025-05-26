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
// Debug: Express Checkout Buttons (Apple Pay, Google Pay)
if (class_exists('YPrint_Stripe_Checkout_Shortcode')) {
    $shortcode_instance = YPrint_Stripe_Checkout_Shortcode::get_instance();
    $express_buttons = $shortcode_instance->render_express_payment_buttons();
    
    if (current_user_can('administrator') && isset($_GET['debug'])) {
        echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace;">';
        echo '<strong>Debug Express Buttons:</strong><br>';
        echo 'Stripe Enabled: ' . ($shortcode_instance->is_stripe_enabled() ? 'Yes' : 'No') . '<br>';
        echo 'Express Buttons HTML Length: ' . strlen($express_buttons) . '<br>';
        echo 'Stripe Settings: <pre>' . print_r(YPrint_Stripe_API::get_stripe_settings(), true) . '</pre>';
        echo '</div>';
    }
    
    echo $express_buttons;
} else {
    if (current_user_can('administrator')) {
        echo '<div style="background: #ffeeee; padding: 10px; margin: 10px 0;">YPrint_Stripe_Checkout_Shortcode class not found!</div>';
    }
}
?>

<form id="payment-form" class="space-y-6 mt-6">
    <div class="space-y-3">
        <p class="font-medium"><?php esc_html_e('Verfügbare Zahlungsarten:', 'yprint-checkout'); ?></p>
        
        <?php
        // Prüfe verfügbare Zahlungsmethoden
        $stripe_enabled = class_exists('YPrint_Stripe_Checkout_Shortcode') && 
                         YPrint_Stripe_Checkout_Shortcode::get_instance()->is_stripe_enabled_public();
        $stripe_settings = $stripe_enabled ? YPrint_Stripe_API::get_stripe_settings() : array();
        ?>
        
        <?php if ($stripe_enabled) : ?>
        <!-- Stripe Kreditkarte -->
        <div>
            <label class="flex items-center p-3 border border-yprint-border-gray rounded-lg hover:border-yprint-blue cursor-pointer has-[:checked]:border-yprint-blue has-[:checked]:ring-2 has-[:checked]:ring-yprint-blue">
                <input type="radio" name="payment_method" value="yprint_stripe" class="form-radio h-5 w-5 text-yprint-blue mr-3" checked>
                <i class="fas fa-credit-card fa-fw text-2xl mr-3 text-blue-600"></i>
                <?php esc_html_e('Kreditkarte (Stripe)', 'yprint-checkout'); ?>
                <?php if (isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode']) : ?>
                    <span class="ml-2 text-xs bg-yellow-200 text-yellow-800 px-2 py-1 rounded">TEST</span>
                <?php endif; ?>
            </label>
            
            <!-- Stripe Card Element Container -->
            <div id="stripe-card-element-container" class="mt-3 p-3 border border-gray-200 rounded-lg bg-gray-50" style="display: none;">
                <div id="stripe-card-element" class="mb-3"></div>
                <div id="stripe-card-errors" class="text-red-600 text-sm"></div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- PayPal (Fallback) -->
        <div>
            <label class="flex items-center p-3 border border-yprint-border-gray rounded-lg hover:border-yprint-blue cursor-pointer has-[:checked]:border-yprint-blue has-[:checked]:ring-2 has-[:checked]:ring-yprint-blue">
                <input type="radio" name="payment_method" value="paypal" class="form-radio h-5 w-5 text-yprint-blue mr-3" <?php echo !$stripe_enabled ? 'checked' : ''; ?>>
                <i class="fab fa-paypal fa-fw text-2xl mr-3 text-[#00457C]"></i>
                PayPal
            </label>
        </div>
        
        <!-- Klarna (Fallback) -->
        <div>
            <label class="flex items-center p-3 border border-yprint-border-gray rounded-lg hover:border-yprint-blue cursor-pointer has-[:checked]:border-yprint-blue has-[:checked]:ring-2 has-[:checked]:ring-yprint-blue">
                <input type="radio" name="payment_method" value="klarna" class="form-radio h-5 w-5 text-yprint-blue mr-3">
                <svg class="w-8 h-6 mr-3" viewBox="0 0 496 156" fill="#FFB3C7" xmlns="http://www.w3.org/2000/svg">
                    <path d="M248.291 31.0084C265.803 31.0084 280.21 37.1458 291.513 49.4206C302.888 61.6954 308.575 77.0417 308.575 95.4594C308.575 113.877 302.888 129.223 291.513 141.498C280.21 153.773 265.803 159.91 248.291 159.91H180.854V31.0084H248.291ZM213.956 132.621H248.291C258.57 132.621 267.076 129.68 273.808 123.798C280.612 117.844 284.014 109.177 284.014 97.7965C284.014 86.4158 280.612 77.7491 273.808 71.7947C267.076 65.8403 258.57 62.8992 248.291 62.8992H213.956V132.621Z"/>
                </svg>
                Klarna
            </label>
        </div>
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
