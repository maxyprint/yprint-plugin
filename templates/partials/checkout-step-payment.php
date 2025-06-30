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

/* Neue Button-Styles für Rechnungsadresse */
.btn-outline {
    background-color: transparent;
    border: 2px solid #0079FF;
    color: #0079FF;
}

.btn-outline:hover {
    background-color: #0079FF;
    color: #ffffff;
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 121, 255, 0.3);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.875rem;
}

.btn-danger {
    background-color: #dc3545;
    color: #ffffff;
    border: 1px solid #dc3545;
}

.btn-danger:hover {
    background-color: #c82333;
    border-color: #bd2130;
    transform: translateY(-1px);
    box-shadow: 0 2px 8px rgba(220, 53, 69, 0.3);
}

/* Debug Panel Styles */
.yprint-debug-panel {
    position: fixed;
    top: 10px;
    right: 10px;
    background: rgba(0, 0, 0, 0.9);
    color: #00ff00;
    padding: 15px;
    border-radius: 8px;
    font-family: 'Courier New', monospace;
    font-size: 12px;
    max-width: 300px;
    z-index: 10000;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}

.yprint-debug-panel h4 {
    color: #ffff00;
    margin: 0 0 10px 0;
    font-size: 14px;
    border-bottom: 1px solid #333;
    padding-bottom: 5px;
}

.yprint-debug-panel .debug-item {
    margin: 5px 0;
    padding: 3px 0;
    border-bottom: 1px dotted #333;
}

.yprint-debug-panel .debug-label {
    color: #ff9900;
    font-weight: bold;
}

.yprint-debug-panel .debug-value {
    color: #00ffff;
}

.yprint-debug-panel .debug-status-success {
    color: #00ff00;
}

.yprint-debug-panel .debug-status-error {
    color: #ff0000;
}

.yprint-debug-panel .debug-status-warning {
    color: #ffaa00;
}

.yprint-debug-toggle {
    position: fixed;
    top: 10px;
    right: 320px;
    background: #0079FF;
    color: white;
    border: none;
    padding: 8px 12px;
    border-radius: 4px;
    cursor: pointer;
    z-index: 10001;
    font-size: 12px;
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

            <!-- Apple Pay Hinweis -->
<div class="apple-pay-notice" style="margin-bottom: 15px; padding: 10px; background: #f8f9fa; border-radius: 8px; font-size: 14px; color: #666;">
    <strong>Hinweis:</strong> Bei Zahlungen über Wallet-Dienste werden unter Umständen automatisch hinterlegte Adressdaten übermittelt. Für den Versand verwenden wir jedoch ausschließlich die Lieferadresse, die im letzten Schritt des Bestellprozesses von Ihnen bestätigt wurde.
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
    
    <!-- RECHTLICH ERFORDERLICHE SEPA-MANDAT-ZUSTIMMUNG -->
    <div class="sepa-mandate-section">
        <div class="payment-checkbox-container">
            <input type="checkbox" id="sepa-mandate-consent" class="payment-checkbox" required>
            <label for="sepa-mandate-consent" class="payment-checkbox-label">
                <?php esc_html_e('Ich erteile YPrint ein SEPA-Lastschriftmandat', 'yprint-checkout'); ?>
            </label>
        </div>
        <div class="sepa-mandate-text">
            <p class="sepa-mandate-details">
                <?php esc_html_e('Ich ermächtige YPrint, Zahlungen von meinem Konto mittels Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die von YPrint auf mein Konto gezogenen Lastschriften einzulösen.', 'yprint-checkout'); ?>
            </p>
            <p class="sepa-mandate-note">
                <?php esc_html_e('Hinweis: Sie können innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit Ihrem Kreditinstitut vereinbarten Bedingungen.', 'yprint-checkout'); ?>
            </p>
        </div>
    </div>
    
    <div class="sepa-info">
        <div class="sepa-info-content">
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
            
            <!-- Redundanter Bereich entfernt - korrekte Rechnungsadresse-Sektion folgt weiter unten -->
            <!-- Rechnungsadresse-Sektion -->
    <div class="mt-6">
        <h3 class="form-label mb-3 flex items-center">
            <i class="fas fa-file-invoice mr-2 text-yprint-blue"></i>
            <?php esc_html_e('Rechnungsadresse', 'yprint-checkout'); ?>
        </h3>
        
        <!-- Button zum Hinzufügen einer abweichenden Rechnungsadresse -->
<div id="add-billing-button-container" class="mt-4">
    <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="flex-1">
                <p class="text-sm text-gray-700 mb-1 font-medium">
                    <?php esc_html_e('Rechnungsadresse', 'yprint-checkout'); ?>
                </p>
                <p class="text-sm text-gray-600">
                    <?php esc_html_e('Standardmäßig entspricht die Rechnungsadresse der Lieferadresse.', 'yprint-checkout'); ?>
                </p>
            </div>
            <div class="flex-shrink-0">
                <button type="button" id="add-billing-address-btn" class="btn btn-secondary whitespace-nowrap">
                    <i class="fas fa-plus mr-2"></i>
                    <?php esc_html_e('Abweichende Adresse', 'yprint-checkout'); ?>
                </button>
            </div>
        </div>
    </div>
</div>
            
        <!-- Nach Auswahl: Anzeige der gewählten Rechnungsadresse -->
        <div id="selected-billing-display" class="hidden">
            <div class="p-4 bg-white rounded-lg border-2 border-green-200">
                <div class="flex justify-between items-start">
                    <div>
                        <div class="flex items-center mb-2">
                            <i class="fas fa-check-circle text-green-600 mr-2"></i>
                            <span class="font-semibold text-green-800">
                                <?php esc_html_e('Abweichende Rechnungsadresse festgelegt', 'yprint-checkout'); ?>
                            </span>
                        </div>
                        <div id="billing-address-content" class="text-sm text-gray-700">
                            <!-- Wird via JavaScript gefüllt -->
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <button type="button" id="change-billing-address" class="btn btn-sm btn-secondary">
                            <i class="fas fa-edit mr-1"></i>
                            <?php esc_html_e('Ändern', 'yprint-checkout'); ?>
                        </button>
                        <button type="button" id="remove-billing-address" class="btn btn-sm btn-danger">
                            <i class="fas fa-trash mr-1"></i>
                            <?php esc_html_e('Entfernen', 'yprint-checkout'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
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
jQuery(document).ready(function($) {

    // 🔧 Debug-System
    const BillingDebug = {
        enabled: localStorage.getItem('yprint_billing_debug') === 'true',
        panel: null,
        logs: [],
        
        init() {
            this.createPanel();
            this.createToggle();
            this.log('🚀 Debug-System initialisiert', 'success');
        },
        
        createPanel() {
            const html = `
                <div id="billing-debug-panel" style="display: ${this.enabled ? 'block' : 'none'}; position:fixed; bottom:0; right:0; background:#fff; padding:10px; border:1px solid #ccc; z-index:9999; font-size:12px;">
                    <div><strong>🔍 Billing Debug</strong></div>
                    <div>Status: <span id="debug-billing-status">Init</span></div>
                    <div>Session: <span id="debug-session-status">Unknown</span></div>
                    <div>Step Nav: <span id="debug-step-nav">Ready</span></div>
                    <div>Letzter AJAX: <span id="debug-last-ajax">None</span></div>
                    <div>Button: <span id="debug-button-state">Init</span></div>
                </div>
            `;
            $('body').append(html);
            this.panel = $('#billing-debug-panel');
        },
        
        createToggle() {
            const toggle = `<button id="toggle-billing-debug" style="position:fixed; bottom:0; left:0; z-index:9999;">${this.enabled ? '❌ Hide Debug' : '🐞 Show Debug'}</button>`;
            $('body').append(toggle);
            $('#toggle-billing-debug').on('click', () => {
                this.enabled = !this.enabled;
                localStorage.setItem('yprint_billing_debug', this.enabled);
                this.panel.toggle(this.enabled);
                $('#toggle-billing-debug').text(this.enabled ? '❌ Hide Debug' : '🐞 Show Debug');
            });
        },
        
        log(msg, type = 'info') {
            const ts = new Date().toLocaleTimeString();
            const styles = {
                success: 'color: green;',
                error: 'color: red;',
                warning: 'color: orange;',
                info: 'color: blue;'
            };
            console.log(`%c[BILLING DEBUG ${ts}] ${msg}`, styles[type] || '');
            this.logs.push({ ts, msg, type });
        },
        
        update(id, value) {
            $(`#debug-${id}`).text(value);
        }
    };

    BillingDebug.init();
    
    // 🔓 Debug sofort global verfügbar machen
    window.YPrintBillingDebug = BillingDebug;

    // 🔗 DOM-Referenzen
    const $addBtn = $('#add-billing-address-btn');
    const $billingStep = $('#step-2-5');
    const $selectedDisplay = $('#selected-billing-display');
    const $billingContent = $('#billing-address-content');
    const $container = $('#add-billing-button-container');

    // 🧪 Session prüfen beim Load und nach Step-Wechsel
checkBillingSessionStatus();
// ✅ Zusätzlich: Rechnungsadresse beim Payment Step Load anzeigen
getCurrentBillingAddress();

// Prüfe Session auch nach Step-Wechsel vom Billing zurück
$(document).on('yprint_step_changed', function(event, stepData) {
    if (stepData.step === 'payment') {
        console.log('🔄 Payment Step geladen - lade Rechnungsadresse');
        setTimeout(() => {
            checkBillingSessionStatus();
            getCurrentBillingAddress(); // ✅ Immer die aktuelle Rechnungsadresse laden
        }, 100);
    }
});

// ✅ Auch bei Rückkehr von anderen Steps die Rechnungsadresse aktualisieren
$(document).on('yprint_step_changed', function(event, stepData) {
    if (stepData.step === 'payment' && (stepData.from === 'billing' || stepData.from === 'address')) {
        console.log('🔄 Zurück zum Payment-Step - aktualisiere Rechnungsadresse');
        setTimeout(() => {
            getCurrentBillingAddress();
        }, 150);
    }
});
    
    // Event-Handler für Billing-Adresse-Auswahl
$(document).on('yprint_billing_address_selected', function(event, data) {
    console.log('🎯 Billing address selected event received:', data);
    // Kurz warten und dann Session prüfen
    setTimeout(() => {
        checkBillingSessionStatus();
    }, 200);
});

// ✅ Vollständige displayBilling() Funktion implementieren
function displayBilling(billingData, isDifferentBilling = false) {
    safeDebugLog('🏠 Zeige Billing Address an', 'success');
    safeDebugUpdate('last-action', 'displayBilling');
    
    // HTML für Adress-Anzeige generieren
    let addressHtml = '';
    if (billingData && typeof billingData === 'object') {
        const fullName = [billingData.first_name, billingData.last_name].filter(Boolean).join(' ');
        const company = billingData.company ? `<div class="text-sm text-gray-600">${billingData.company}</div>` : '';
        const address1 = billingData.address_1 || '';
        const address2 = billingData.address_2 ? ` ${billingData.address_2}` : '';
        const city = [billingData.postcode, billingData.city].filter(Boolean).join(' ');
        const country = billingData.country || 'DE';
        
        addressHtml = `
            <div class="text-sm">
                <div class="font-semibold text-gray-800">${fullName}</div>
                ${company}
                <div class="text-gray-700">${address1}${address2}</div>
                <div class="text-gray-700">${city}</div>
                <div class="text-gray-600">${country}</div>
            </div>
        `;
    }
    
    // Content in das Display-Element einfügen
    $billingContent.html(addressHtml);
    
    // Anzeige-Status je nach Adresstyp anpassen
    if (isDifferentBilling) {
        // Separate Rechnungsadresse gewählt
        $selectedDisplay.find('.text-green-800').text('<?php esc_html_e('Abweichende Rechnungsadresse festgelegt', 'yprint-checkout'); ?>');
        $selectedDisplay.removeClass('hidden').show();
        $container.hide();
        safeDebugLog('✅ Separate Billing Address angezeigt', 'success');
    } else {
        // Lieferadresse als Rechnungsadresse
        $selectedDisplay.find('.text-green-800').text('<?php esc_html_e('Rechnungsadresse (entspricht Lieferadresse)', 'yprint-checkout'); ?>');
        $selectedDisplay.removeClass('hidden').show();
        $container.hide();
        safeDebugLog('✅ Shipping als Billing Address angezeigt', 'info');
    }
}

// ✅ Funktion zum Abrufen der aktuellen Rechnungsadresse
function getCurrentBillingAddress() {
    safeDebugLog('🔍 Hole aktuelle Rechnungsadresse von Session', 'info');
    
    $.ajax({
        url: yprint_address_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'yprint_get_billing_session',
            nonce: yprint_address_ajax.nonce
        },
        success: function(response) {
            if (response.success && response.data) {
                const data = response.data;
                safeDebugLog('✅ Billing Session Daten erhalten', 'success');
                safeDebugUpdate('session-status', 'Geladen');
                
                if (data.has_billing_address) {
                    // Zeige die entsprechende Rechnungsadresse an
                    displayBilling(data.billing_address, data.is_different_billing);
                } else {
                    // Keine Rechnungsadresse in Session - zeige Add Button
                    showAddBillingButton();
                    safeDebugLog('ℹ️ Keine Billing Address in Session', 'info');
                }
            } else {
                safeDebugLog('⚠️ Billing Session leer oder Fehler', 'warning');
                showAddBillingButton();
            }
        },
        error: function(xhr, status, error) {
            safeDebugLog(`❌ AJAX Error: ${error}`, 'error');
            showAddBillingButton();
        }
    });
}

    // 🔧 Sichere Debug-Funktion
function safeDebugLog(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString();
    const styles = {
        success: 'color: green;',
        error: 'color: red;',
        warning: 'color: orange;',
        info: 'color: blue;'
    };
    
    // Standard Console-Log
    console.log(`%c[BILLING ${timestamp}] ${message}`, styles[type] || '');
    
    // Optional: BillingDebug wenn verfügbar
    if (typeof window.YPrintBillingDebug !== 'undefined' && window.YPrintBillingDebug.log) {
        window.YPrintBillingDebug.log(message, type);
    } else if (typeof BillingDebug !== 'undefined' && BillingDebug.log) {
        BillingDebug.log(message, type);
    }
}

function safeDebugUpdate(id, value) {
    if (typeof window.YPrintBillingDebug !== 'undefined' && window.YPrintBillingDebug.update) {
        window.YPrintBillingDebug.update(id, value);
    } else if (typeof BillingDebug !== 'undefined' && BillingDebug.update) {
        BillingDebug.update(id, value);
    }
}

// ➕ Add Billing Button
$(document).on('click', '#add-billing-address-btn', function(e) {
    e.preventDefault();
    const $btn = $(this);
    const original = $btn.html();
    
    safeDebugLog('🎯 Klick auf Add Billing Button', 'success');
    
    // 🚀 Flag für separate Billing aktivieren - Direkt das Flag setzen
jQuery.ajax({
    url: yprint_address_ajax.ajax_url,
    type: 'POST',
    data: {
        action: 'yprint_clear_billing_session', // Erst löschen um sauber zu starten
        nonce: yprint_address_ajax.nonce
    },
    success: function() {
        // Dann das Flag aktivieren ohne Adressdaten
        jQuery.ajax({
            url: yprint_address_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_activate_billing_different',
                nonce: yprint_address_ajax.nonce
            },
            success: function(response) {
                safeDebugLog('✅ Billing Different Flag aktiviert', 'success');
            },
            error: function() {
                safeDebugLog('⚠️ Billing Flag Fehler - fahre trotzdem fort', 'warning');
            }
        });
    },
    error: function() {
        safeDebugLog('⚠️ Billing Clear Fehler - fahre trotzdem fort', 'warning');
    }
});
    safeDebugLog('⏰ Button-Klick Timestamp: ' + new Date().toLocaleTimeString(), 'info');
    safeDebugUpdate('button-state', 'Loading...');
    $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Lade...');

    // 🧭 Step anzeigen und initialisieren
try {
    safeDebugLog('🔄 Starte Step-Wechsel zu Billing', 'info');
    
    jQuery('.checkout-step').removeClass('active').hide();
    $billingStep.addClass('active').show();
    safeDebugLog('✅ Billing Step DOM sichtbar gemacht', 'success');
    
    const newUrl = new URL(window.location);
    newUrl.searchParams.set('step', 'billing');
    history.pushState({step: 'billing'}, '', newUrl);
    safeDebugLog('✅ URL auf billing step aktualisiert', 'success');
    
    $(document).trigger('yprint_step_changed', {step: 'billing', from: 'payment'});
    safeDebugLog('✅ yprint_step_changed Event ausgelöst', 'success');
    
    // Address Manager Verfügbarkeit prüfen vor Initialisierung
    safeDebugLog('🔍 Prüfe Address Manager Verfügbarkeit...', 'info');
    safeDebugLog('YPrintAddressManager available: ' + (typeof window.YPrintAddressManager !== 'undefined'), 'info');
    safeDebugLog('initializeBillingStep available: ' + (typeof window.initializeBillingStep === 'function'), 'info');
    
    // Billing-Step initialisieren nach dem Anzeigen
    setTimeout(() => {
        safeDebugLog('⏳ Timeout erreicht - starte Billing Initialisierung', 'info');
        if (typeof window.initializeBillingStep === 'function') {
            safeDebugLog('✅ initializeBillingStep gefunden - rufe auf', 'success');
            window.initializeBillingStep();
        } else {
            safeDebugLog('❌ initializeBillingStep nicht verfügbar', 'error');
            // Fallback: Direkter Aufruf der Address Manager Funktion
            if (typeof window.YPrintAddressManager !== 'undefined' && 
                typeof window.YPrintAddressManager.loadSavedAddresses === 'function') {
                safeDebugLog('🔄 Fallback: Direkter Address Manager Aufruf', 'warning');
                window.currentAddressContext = 'billing';
                window.YPrintAddressManager.loadSavedAddresses('billing');
            }
        }
    }, 100);

    safeDebugLog('✅ Billing-Step sichtbar gemacht und wird initialisiert', 'success');
    safeDebugUpdate('step-nav', 'OK');
} catch (err) {
    safeDebugLog('❌ Fehler bei Navigation: ' + err.message, 'error');
    safeDebugLog('❌ Stack Trace: ' + err.stack, 'error');
    safeDebugUpdate('step-nav', 'Fehler');
}

        setTimeout(() => {
            $btn.prop('disabled', false).html(original);
            safeDebugUpdate('button-state', 'Bereit');
        }, 400);
    });

    // ✏️ Change Button
    $('#change-billing-address').on('click', function(e) {
        e.preventDefault();
        safeDebugLog('✏️ Klick auf Change Billing', 'info');
        showBillingStep();
    });

    // 🗑️ Remove Button
    $('#remove-billing-address').on('click', function(e) {
        e.preventDefault();
        safeDebugLog('🗑️ Klick auf Remove Billing', 'warning');
        if (confirm('Rechnungsadresse entfernen?')) {
            clearBillingAddress();
        }
    });

    // 📦 Funktionen
    function checkBillingSessionStatus() {
        safeDebugLog('🔍 AJAX: Session prüfen', 'info');
        safeDebugUpdate('last-ajax', 'get_billing_session');
        safeDebugUpdate('session-status', 'Lade...');
        
        jQuery.ajax({
            url: yprint_address_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_get_billing_session',
                nonce: yprint_address_ajax.nonce
            },
            success(response) {
                if (response.success && response.data?.has_billing_address) {
                    safeDebugLog('✅ Session hat Billing Address', 'success');
                    safeDebugUpdate('session-status', 'OK');
                    displayBilling(response.data.billing_address);
                } else {
                    safeDebugLog('ℹ️ Keine Billing Address vorhanden', 'info');
                    safeDebugUpdate('session-status', 'Leer');
                    showAddBillingButton();
                }
            },
            error(err) {
                safeDebugLog('❌ Fehler bei Session-Check: ' + err.statusText, 'error');
                safeDebugUpdate('session-status', 'Fehler');
                showAddBillingButton();
            }
        });
    }

    function displayBilling(data) {
        safeDebugLog('📋 Zeige Billing Address an', 'success');
        let html = `<strong>${data.first_name || ''} ${data.last_name || ''}</strong><br>`;
        if (data.company) html += `${data.company}<br>`;
        html += `${data.address_1 || ''} ${data.address_2 || ''}<br>`;
        html += `${data.postcode || ''} ${data.city || ''}<br>`;
        html += `${data.country || ''}`;
        $billingContent.html(html);
        $container.hide();
        $selectedDisplay.show();
        safeDebugUpdate('billing-status', 'Angezeigt');
    }

    function showAddBillingButton() {
        safeDebugLog('🔘 Zeige Add Billing Button', 'info');
        $selectedDisplay.hide();
        $container.show();
        $addBtn.prop('disabled', false).html('<i class="fas fa-plus mr-2"></i> Abweichende Rechnungsadresse hinzufügen');
        safeDebugUpdate('button-state', 'Bereit');
    }

    function clearBillingAddress() {
        safeDebugLog('🗑️ Lösche Billing Address via AJAX', 'warning');
        safeDebugUpdate('last-ajax', 'clear_billing_session');
        
        jQuery.ajax({
            url: yprint_address_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_clear_billing_session',
                nonce: yprint_address_ajax.nonce
            },
            success() {
                safeDebugLog('✅ Billing Address gelöscht', 'success');
                showAddBillingButton();
                safeDebugUpdate('session-status', 'Geklärt');
            },
            error(err) {
                safeDebugLog('❌ Fehler beim Löschen: ' + err.statusText, 'error');
                safeDebugUpdate('session-status', 'Fehler');
            }
        });
    }

    function showBillingStep() {
    try {
        $('.checkout-step').removeClass('active').hide();
        $billingStep.addClass('active').show();
        safeDebugLog('🔁 Billing Step sichtbar (Change)', 'info');
        safeDebugUpdate('step-nav', 'OK');
    } catch (err) {
        safeDebugLog('❌ Navigation Fehler: ' + err.message, 'error');
    }
}

// ✅ Funktion zum Anzeigen des Add Billing Button
function showAddBillingButton() {
    $selectedDisplay.hide();
    $container.show();
    safeDebugLog('🔄 Add Billing Button angezeigt', 'info');
    safeDebugUpdate('display-status', 'Add Button');
}

    });
</script>
