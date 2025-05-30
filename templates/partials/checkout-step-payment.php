<?php
/**
 * Partial Template: Schritt 2 - Zahlungsart wählen.
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Debug-Ausgabe
error_log('Loading payment step template from: ' . __FILE__);

// Inline Styling für Payment Step
?>
<style>

.express-payment-section {
    margin: 40px 0 30px 0;
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    display: block; /* Änderung: Immer anzeigen */
}

.express-payment-title {
    text-align: center;
    margin-bottom: 20px;
}

.express-payment-title span {
    font-size: 14px;
    color: #666;
    background: #f8f8f8;
    padding: 8px 15px;
    border-radius: 20px;
}

#yprint-express-payment-container {
    margin: 0 0 20px 0;
}

#yprint-payment-request-button {
    margin: 0;
    min-height: 48px;
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}

.express-payment-separator {
    text-align: center;
    margin: 25px 0;
    position: relative;
}

.express-payment-separator::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e5e5e5;
}

.express-payment-separator span {
    background: #ffffff;
    padding: 0 20px;
    font-size: 0.875rem;
    color: #6e6e73;
    position: relative;
    z-index: 1;
    font-weight: 500;
}

.express-payment-loading {
    text-align: center;
    padding: 20px;
    display: block;
}

/* Spinner Animation */
.fa-spin {
    animation: fa-spin 2s infinite linear;
}

@keyframes fa-spin {
    0% {
        transform: rotate(0deg);
    }
    100% {
        transform: rotate(360deg);
    }
}

.text-blue-500 {
    color: #0079FF;
}

/* YPrint Checkout Payment Styling */
.payment-method-container {
    background: #ffffff;
    border: 1px solid #DFDFDF;
    border-radius: 12px;
    overflow: hidden;
    margin: 20px 0;
}

.payment-method-slider {
    background: #F6F7FA;
    padding: 4px;
}

.slider-container {
    position: relative;
    background: transparent;
    border-radius: 6px;
    overflow: hidden;
}

.slider-track {
    display: flex;
    position: relative;
    z-index: 10;
}

.slider-option {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 12px 16px;
    cursor: pointer;
    transition: all 0.3s ease;
    font-weight: 500;
    color: #6e6e73;
    background: transparent;
    border-radius: 6px;
    position: relative;
    z-index: 3;
    user-select: none;
    border: none;
    outline: none;
}

.slider-option:hover {
    background-color: rgba(0, 121, 255, 0.05);
}

.slider-option.active {
    color: #0079FF;
}

.slider-indicator {
    position: absolute;
    top: 4px;
    bottom: 4px;
    width: 50%;
    background: #ffffff;
    border-radius: 6px;
    transition: transform 0.3s ease;
    z-index: 1;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
}

.slider-indicator.sepa {
    transform: translateX(100%);
}

.payment-input-container {
    padding: 24px;
    background: #ffffff;
}

.payment-fields-wrapper {
    position: relative;
    min-height: 120px;
}

.payment-input-fields {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.3s ease;
}

.payment-input-fields.active {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
    position: relative;
}

.payment-field-group {
    margin-bottom: 16px;
}

.payment-field-label {
    display: block;
    font-weight: 500;
    color: #1d1d1f;
    margin-bottom: 8px;
    font-size: 0.9rem;
}

.payment-stripe-element {
    background: #ffffff;
    border: 2px solid #DFDFDF;
    border-radius: 8px;
    padding: 12px;
    transition: border-color 0.2s ease;
    min-height: 44px;
}

.payment-stripe-element:focus-within {
    border-color: #0079FF;
    box-shadow: 0 0 0 3px rgba(0, 121, 255, 0.1);
}

.payment-error-display {
    color: #dc3545;
    font-size: 0.85rem;
    margin-top: 6px;
    min-height: 20px;
}

.payment-checkbox-container {
    display: flex;
    align-items: center;
    cursor: pointer;
}

.payment-checkbox {
    width: 16px;
    height: 16px;
    margin-right: 8px;
    accent-color: #0079FF;
}

.payment-checkbox-label {
    font-size: 0.85rem;
    color: #6e6e73;
}

.sepa-info {
    margin-top: 16px;
    padding: 12px;
    background: rgba(34, 197, 94, 0.05);
    border-left: 3px solid #22c55e;
    border-radius: 0 6px 6px 0;
}

.sepa-info-content p {
    margin: 0 0 6px 0;
    font-size: 0.8rem;
    color: #6e6e73;
    line-height: 1.4;
}

.sepa-info-content p:last-child {
    margin-bottom: 0;
}

.test-badge {
    display: inline-block;
    background: #fbbf24;
    color: #92400e;
    font-size: 0.75rem;
    font-weight: 600;
    padding: 4px 8px;
    border-radius: 4px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.express-payment-section {
    margin: 40px 0 30px 0;
    padding: 0;
    border: none;
    border-radius: 0;
    background: transparent;
    box-shadow: none;
    display: block; /* Immer anzeigen */
}

.express-payment-title {
    text-align: center;
    margin-bottom: 20px;
}

.express-payment-title span {
    font-size: 14px;
    color: #666;
    background: #f8f8f8;
    padding: 8px 15px;
    border-radius: 20px;
}

#yprint-express-payment-container {
    margin: 0 0 20px 0;
}

#yprint-payment-request-button {
    margin: 0;
    min-height: 48px;
    border-radius: 8px;
    overflow: hidden;
    width: 100%;
}

.express-payment-separator {
    text-align: center;
    margin: 25px 0;
    position: relative;
}

.express-payment-separator::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0;
    right: 0;
    height: 1px;
    background: #e5e5e5;
}

.express-payment-separator span {
    background: #ffffff;
    padding: 0 20px;
    font-size: 0.875rem;
    color: #6e6e73;
    position: relative;
    z-index: 1;
    font-weight: 500;
}

.express-payment-loading {
    text-align: center;
    padding: 20px;
    display: block;
}

/* Grundlegende Button-Styles */
.btn {
    padding: 12px 20px;
    border-radius: 10px;
    font-weight: 500;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
    border: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 1rem;
    text-decoration: none;
}

.btn-primary {
    background-color: #0079FF;
    color: #ffffff;
}

.btn-primary:hover {
    background-color: #0056b3;
    transform: translateY(-1px);
    box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
}

.btn-secondary {
    background-color: #f5f5f7;
    color: #1d1d1f;
    border: 1px solid #DFDFDF;
}

.btn-secondary:hover {
    background-color: #e9e9ed;
    transform: translateY(-1px);
}

/* Form-Elemente */
.form-input, .form-select {
    border: 1px solid #DFDFDF;
    border-radius: 10px;
    padding: 12px 15px;
    width: 100%;
    transition: border-color 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    background-color: #ffffff;
}

.form-input:focus, .form-select:focus {
    border-color: #0079FF;
    box-shadow: 0 0 0 2px rgba(0, 121, 255, 0.25);
    outline: none;
}

.form-label {
    display: block;
    margin-bottom: 6px;
    font-weight: 500;
    color: #1d1d1f;
}

/* Responsive Design */
@media (max-width: 640px) {
    .slider-option {
        padding: 10px 12px;
        font-size: 0.9rem;
    }
    
    .slider-option span {
        display: none;
    }
    
    .payment-input-container {
        padding: 16px;
    }
}

/* Checkout Step Basis */
.checkout-step {
    display: none;
}
.checkout-step.active {
    display: block;
}

h2 {
    font-size: 24px;
    font-weight: 600;
    margin-bottom: 15px;
    color: #1d1d1f;
    display: flex;
    align-items: center;
}

h2 i {
    color: #0079FF;
    margin-right: 8px;
}

.space-y-6 > * + * {
    margin-top: 1.5rem;
}

.flex {
    display: flex;
}

.items-center {
    align-items: center;
}

.justify-between {
    justify-content: space-between;
}

.w-full {
    width: 100%;
}

.md\:w-auto {
    width: auto;
}

@media (min-width: 768px) {
    .md\:flex-row {
        flex-direction: row;
    }
    .md\:w-auto {
        width: auto;
    }
}

.flex-col {
    flex-direction: column;
}

.gap-4 {
    gap: 1rem;
}

.order-1 {
    order: 1;
}

.order-2 {
    order: 2;
}

@media (min-width: 768px) {
    .md\:order-1 {
        order: 1;
    }
    .md\:order-2 {
        order: 2;
    }
}

.pt-6 {
    padding-top: 1.5rem;
}

.mt-6 {
    margin-top: 1.5rem;
}

.mb-2 {
    margin-bottom: 0.5rem;
}

.text-lg {
    font-size: 1.125rem;
}

.text-xl {
    font-size: 1.25rem;
}

.font-semibold {
    font-weight: 600;
}

.font-bold {
    font-weight: 700;
}

.border-t {
    border-top: 1px solid #e5e5e5;
}

.rounded-r-none {
    border-top-right-radius: 0;
    border-bottom-right-radius: 0;
}

.rounded-l-none {
    border-top-left-radius: 0;
    border-bottom-left-radius: 0;
}

.whitespace-nowrap {
    white-space: nowrap;
}

.text-sm {
    font-size: 0.875rem;
}

.mt-1 {
    margin-top: 0.25rem;
}

.mt-4 {
    margin-top: 1rem;
}

.p-3 {
    padding: 0.75rem;
}

.rounded-lg {
    border-radius: 0.5rem;
}

.bg-blue-50 {
    background-color: #eff6ff;
}

.text-blue-800 {
    color: #1e40af;
}

.mr-2 {
    margin-right: 0.5rem;
}

.ml-2 {
    margin-left: 0.5rem;
}

.fas {
    font-family: "Font Awesome 6 Free";
    font-weight: 900;
}
</style>

<h2 class="flex items-center"><i class="fas fa-credit-card mr-2 text-yprint-blue"></i><?php esc_html_e('Zahlungsart wählen', 'yprint-checkout'); ?></h2>

<?php 
// Express Checkout Buttons (Apple Pay, Google Pay) - Immer anzeigen falls Stripe verfügbar
if (class_exists('YPrint_Stripe_Checkout')) {
    $checkout_instance = YPrint_Stripe_Checkout::get_instance();
    
    // Prüfe explizit ob Stripe konfiguriert ist
    if ($checkout_instance->is_stripe_enabled_public()) {
        // Render Express Payment Buttons direkt
        ?>
        <div class="express-payment-section" style="margin: 20px 0;">
            <div class="express-payment-title" style="text-align: center; margin-bottom: 15px;">
                <span style="font-size: 14px; color: #666; background: #f8f8f8; padding: 8px 15px; border-radius: 20px;">
                    <i class="fas fa-bolt mr-2"></i><?php esc_html_e('Express-Zahlung', 'yprint-checkout'); ?>
                </span>
            </div>
            
            <div id="yprint-express-payment-container" style="display: none;">
                <div id="yprint-payment-request-button" style="margin-bottom: 15px;">
                    <!-- Stripe Elements wird hier eingefügt -->
                </div>
                
                <div class="express-payment-separator" style="text-align: center; margin: 20px 0; position: relative;">
                    <span style="background: white; padding: 0 15px; color: #999; font-size: 14px; position: relative; z-index: 2;">
                        <?php esc_html_e('oder', 'yprint-checkout'); ?>
                    </span>
                    <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: #e5e5e5; z-index: 1;"></div>
                </div>
            </div>
        </div>
        <?php
        
        // Debug-Info nur für Administratoren
        if (current_user_can('administrator') && isset($_GET['debug'])) {
            $settings = YPrint_Stripe_API::get_stripe_settings();
            echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-family: monospace; font-size: 12px;">';
            echo '<strong>Debug Express Buttons:</strong><br>';
            echo 'Stripe Enabled: ' . ($checkout_instance->is_stripe_enabled_public() ? 'Yes' : 'No') . '<br>';
            echo 'Payment Request Setting: ' . (isset($settings['payment_request']) ? $settings['payment_request'] : 'Not Set') . '<br>';
            echo 'Express Payments Setting: ' . (isset($settings['express_payments']) ? $settings['express_payments'] : 'Not Set') . '<br>';
            echo 'Live Keys Set: ' . ((!empty($settings['publishable_key']) && !empty($settings['secret_key'])) ? 'Yes' : 'No') . '<br>';
            echo 'Test Keys Set: ' . ((!empty($settings['test_publishable_key']) && !empty($settings['test_secret_key'])) ? 'Yes' : 'No') . '<br>';
            echo 'Is SSL: ' . (is_ssl() ? 'Yes' : 'No') . '<br>';
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
        echo '<strong>Fehler:</strong> YPrint_Stripe_Checkout Klasse nicht gefunden!';
        echo '</div>';
    }
}
?>

<form id="payment-form" class="space-y-6 mt-6">
    <div class="space-y-6">
        <!-- Intelligenter Payment Bereich -->
        <div class="payment-method-container">
            <!-- Payment Method Slider -->
            <div class="payment-method-slider">
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
                
                <?php if (class_exists('YPrint_Stripe_API')) {
                    $stripe_settings = YPrint_Stripe_API::get_stripe_settings();
                    if (isset($stripe_settings['testmode']) && 'yes' === $stripe_settings['testmode']) : ?>
                        <div class="test-mode-indicator">
                            <span class="test-badge"><?php esc_html_e('TEST-MODUS', 'yprint-checkout'); ?></span>
                        </div>
                    <?php endif;
                } ?>
            </div>
        </div>
    </div>

    <!-- Warenkorb-Zusammenfassung -->
    <div class="mt-6 border-t border-yprint-medium-gray pt-6">
        <h3 class="text-lg font-semibold mb-2"><?php esc_html_e('Gesamtübersicht', 'yprint-checkout'); ?></h3>
        <!-- Preisdetails -->
        <div class="flex justify-between text-lg">
            <span><?php esc_html_e('Zwischensumme:', 'yprint-checkout'); ?></span>
            <span id="subtotal-price">€0,00</span>
        </div>
        <div class="flex justify-between text-lg">
            <span><?php esc_html_e('Versandkosten:', 'yprint-checkout'); ?></span>
            <span id="shipping-price">€0,00</span>
        </div>
        <div class="flex justify-between text-xl font-bold mt-2 text-yprint-blue">
            <span><?php esc_html_e('Gesamtpreis:', 'yprint-checkout'); ?></span>
            <span id="total-price-payment">€0,00</span>
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
    <div class="mt-6 p-3 bg-blue-50 rounded-lg text-sm text-blue-800">
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

<script>
// Stelle sicher, dass Payment-Slider sofort funktioniert
document.addEventListener('DOMContentLoaded', function() {
    console.log('Payment step loaded, initializing slider...');
    
    // Fallback für Payment Slider, falls Hauptskript nicht lädt
    if (typeof initPaymentSlider === 'undefined') {
        console.log('Main payment slider not found, creating fallback...');
        
        const sliderOptions = document.querySelectorAll('.slider-option');
        const indicator = document.querySelector('.slider-indicator');
        const hiddenInput = document.getElementById('selected-payment-method');
        
        sliderOptions.forEach(option => {
            option.addEventListener('click', function() {
                // Remove active from all
                sliderOptions.forEach(opt => opt.classList.remove('active'));
                
                // Add active to clicked
                this.classList.add('active');
                
                // Update indicator
                const method = this.getAttribute('data-method');
                if (method === 'sepa') {
                    indicator.classList.add('sepa');
                } else {
                    indicator.classList.remove('sepa');
                }
                
                // Update hidden input
                const methodValue = method === 'card' ? 'yprint_stripe_card' : 'yprint_stripe_sepa';
                if (hiddenInput) {
                    hiddenInput.value = methodValue;
                }
                
                // Show/hide payment fields
                document.querySelectorAll('.payment-input-fields').forEach(field => {
                    field.classList.remove('active');
                });
                
                const targetField = document.getElementById(method + '-payment-fields');
                if (targetField) {
                    targetField.classList.add('active');
                }
                
                console.log('Payment method switched to:', method);
            });
        });
        
        // Initialize Stripe elements after a short delay
        setTimeout(() => {
            if (typeof YPrintStripeCheckout !== 'undefined' && YPrintStripeCheckout.initCardElement) {
                YPrintStripeCheckout.initCardElement();
            }
        }, 500);
    }
});

// Express Payment Integration
setTimeout(() => {
        console.log('Initializing Express Payment after delay...');
        
        // Prüfe ob Express Checkout verfügbar ist
        if (typeof YPrintExpressCheckout !== 'undefined') {
            console.log('YPrintExpressCheckout found, should initialize automatically');
        } else {
            console.log('YPrintExpressCheckout not found, may need to wait longer');
            
            // Versuche erneut nach weiterer Verzögerung
            setTimeout(() => {
                if (typeof YPrintExpressCheckout !== 'undefined') {
                    console.log('YPrintExpressCheckout found on second attempt');
                } else {
                    console.warn('YPrintExpressCheckout still not found - check if script is loaded');
                }
            }, 2000);
        }
    }, 1000);
</script>