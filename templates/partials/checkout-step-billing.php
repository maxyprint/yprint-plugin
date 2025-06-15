<?php
/**
 * Partial Template: Schritt 2.5 - Rechnungsadresse (optional)
 * Wird nur angezeigt, wenn separate Rechnungsadresse gewünscht
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Session-Daten für vorausgefüllte Felder abrufen
$session_billing_address = WC()->session->get('yprint_billing_address', array());
?>

<h2 class="flex items-center">
    <i class="fas fa-file-invoice mr-2 text-yprint-blue"></i>
    <?php esc_html_e('Rechnungsadresse', 'yprint-checkout'); ?>
</h2>

<?php if (is_user_logged_in()) : ?>
    <div class="yprint-saved-addresses mt-6" data-address-type="billing">
        <h3 class="saved-addresses-title">
            <i class="fas fa-file-invoice mr-2"></i>
            <?php _e('Gespeicherte Rechnungsadressen', 'yprint-plugin'); ?>
        </h3>
        <div class="address-cards-grid">
            <div class="address-card add-new-address-card cursor-pointer">
                <div class="address-card-content border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-colors hover:border-yprint-blue add-new-address-content">
                    <i class="fas fa-plus text-3xl text-gray-400 mb-2"></i>
                    <h4 class="font-semibold text-gray-600"><?php esc_html_e('Neue Rechnungsadresse hinzufügen', 'yprint-checkout'); ?></h4>
                </div>
            </div>
        </div>
        <div class="loading-addresses text-center py-4">
            <i class="fas fa-spinner fa-spin text-yprint-blue text-2xl"></i>
            <p><?php esc_html_e('Adressen werden geladen...', 'yprint-checkout'); ?></p>
        </div>
    </div>
    <?php
    // Modal HTML bereitstellen
    if (class_exists('YPrint_Address_Manager')) {
        try {
            $address_manager = YPrint_Address_Manager::get_instance();
            echo $address_manager->get_address_modal_html();
        } catch (Exception $e) {
            if (current_user_can('administrator')) {
                echo '<div class="notice notice-error"><p>Address Modal Error: ' . esc_html($e->getMessage()) . '</p></div>';
            }
            error_log('YPrint Billing Address Modal Error: ' . $e->getMessage());
        }
    }
    ?>
<?php endif; ?>

<form id="billing-address-form" class="space-y-6 mt-6">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="billing_first_name" class="form-label"><?php esc_html_e('Vorname', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_first_name" name="billing_first_name" 
                   value="<?php echo esc_attr($session_billing_address['first_name'] ?? ''); ?>"
                   class="form-input" required autocomplete="billing given-name">
        </div>
        <div>
            <label for="billing_last_name" class="form-label"><?php esc_html_e('Nachname', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_last_name" name="billing_last_name" 
                   value="<?php echo esc_attr($session_billing_address['last_name'] ?? ''); ?>"
                   class="form-input" required autocomplete="billing family-name">
        </div>
    </div>
    
    <div>
        <label for="billing_company" class="form-label"><?php esc_html_e('Firma', 'yprint-checkout'); ?> <span class="text-sm text-yprint-text-secondary">(<?php esc_html_e('optional', 'yprint-checkout'); ?>)</span></label>
        <input type="text" id="billing_company" name="billing_company" 
               value="<?php echo esc_attr($session_billing_address['company'] ?? ''); ?>"
               class="form-input" autocomplete="billing organization">
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="billing_street" class="form-label"><?php esc_html_e('Straße', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_street" name="billing_street" 
                   value="<?php echo esc_attr($session_billing_address['address_1'] ?? ''); ?>"
                   class="form-input" required autocomplete="billing street-address">
        </div>
        <div>
            <label for="billing_housenumber" class="form-label"><?php esc_html_e('Hausnummer', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_housenumber" name="billing_housenumber" 
                   value="<?php echo esc_attr($session_billing_address['address_2'] ?? ''); ?>"
                   class="form-input" required autocomplete="billing address-line2">
        </div>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="billing_zip" class="form-label"><?php esc_html_e('PLZ', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_zip" name="billing_zip" 
                   value="<?php echo esc_attr($session_billing_address['postcode'] ?? ''); ?>"
                   class="form-input" required autocomplete="billing postal-code" inputmode="numeric">
        </div>
        <div>
            <label for="billing_city" class="form-label"><?php esc_html_e('Ort', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_city" name="billing_city" 
                   value="<?php echo esc_attr($session_billing_address['city'] ?? ''); ?>"
                   class="form-input" required autocomplete="billing address-level2">
        </div>
    </div>
    
    <div>
        <label for="billing_country" class="form-label"><?php esc_html_e('Land', 'yprint-checkout'); ?></label>
        <select id="billing_country" name="billing_country" class="form-select" required autocomplete="billing country">
            <option value="DE" <?php selected($session_billing_address['country'] ?? 'DE', 'DE'); ?>><?php esc_html_e('Deutschland', 'yprint-checkout'); ?></option>
            <option value="AT" <?php selected($session_billing_address['country'] ?? '', 'AT'); ?>><?php esc_html_e('Österreich', 'yprint-checkout'); ?></option>
            <option value="CH" <?php selected($session_billing_address['country'] ?? '', 'CH'); ?>><?php esc_html_e('Schweiz', 'yprint-checkout'); ?></option>
            <option value="NL" <?php selected($session_billing_address['country'] ?? '', 'NL'); ?>><?php esc_html_e('Niederlande', 'yprint-checkout'); ?></option>
        </select>
    </div>

    <div class="yprint-save-address-actions mt-6" style="display: <?php echo is_user_logged_in() ? 'block' : 'none'; ?>;">
        <button type="button" id="save-billing-address-button" class="btn btn-secondary">
            <i class="fas fa-save mr-2"></i><?php esc_html_e('Rechnungsadresse speichern', 'yprint-checkout'); ?>
        </button>
        <div id="save-billing-address-feedback" class="mt-2 text-sm hidden"></div>
    </div>

    <!-- Navigation Buttons -->
    <div class="pt-6 flex flex-col md:flex-row justify-between items-center gap-4">
        <button type="button" id="btn-back-to-payment" class="btn btn-secondary w-full md:w-auto order-2 md:order-1">
            <i class="fas fa-arrow-left mr-2"></i> <?php esc_html_e('Zurück zur Zahlung', 'yprint-checkout'); ?>
        </button>
        <button type="button" id="btn-billing-to-payment" class="btn btn-primary w-full md:w-auto order-1 md:order-2" disabled>
            <?php esc_html_e('Weiter zur Zahlung', 'yprint-checkout'); ?> <i class="fas fa-arrow-right ml-2"></i>
        </button>
    </div>
</form>

<script>
// Billing Address Step JavaScript
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('Billing address step loaded');
        
        // Form-Validierung für Billing-Felder
        function validateBillingForm() {
            const requiredFields = ['billing_first_name', 'billing_last_name', 'billing_street', 'billing_housenumber', 'billing_zip', 'billing_city'];
            let isValid = true;
            
            requiredFields.forEach(fieldId => {
                const field = $('#' + fieldId);
                const value = field.val().trim();
                
                if (!value) {
                    isValid = false;
                    field.addClass('border-red-500');
                } else {
                    field.removeClass('border-red-500');
                }
            });
            
            $('#btn-billing-to-payment').prop('disabled', !isValid);
            return isValid;
        }
        
        // Event-Handler für Formular-Validierung
        $('#billing-address-form input, #billing-address-form select').on('input change', validateBillingForm);
        
        // Initiale Validierung
        validateBillingForm();
        
        // Rechnungsadresse speichern
        $('#save-billing-address-button').on('click', function() {
            const $this = $(this);
            const originalText = $this.html();
            
            if (!validateBillingForm()) {
                alert('Bitte füllen Sie alle Pflichtfelder aus.');
                return;
            }
            
            $this.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Speichern...');
            
            const billingData = {
                name: 'Rechnungsadresse vom ' + new Date().toLocaleDateString('de-DE'),
                first_name: $('#billing_first_name').val(),
                last_name: $('#billing_last_name').val(),
                company: $('#billing_company').val(),
                address_1: $('#billing_street').val(),
                address_2: $('#billing_housenumber').val(),
                postcode: $('#billing_zip').val(),
                city: $('#billing_city').val(),
                country: $('#billing_country').val()
            };
            
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_save_billing_address',
                    nonce: yprint_address_ajax.nonce,
                    address_data: billingData,
                    address_type: 'billing'
                },
                success: function(response) {
                    if (response.success) {
                        $('#save-billing-address-feedback')
                            .removeClass('hidden text-red-500')
                            .addClass('text-green-500')
                            .html('<i class="fas fa-check-circle mr-1"></i>Rechnungsadresse gespeichert.');
                    } else {
                        $('#save-billing-address-feedback')
                            .removeClass('hidden text-green-500')
                            .addClass('text-red-500')
                            .html('<i class="fas fa-exclamation-circle mr-1"></i>' + (response.data.message || 'Fehler beim Speichern.'));
                    }
                },
                error: function() {
                    $('#save-billing-address-feedback')
                        .removeClass('hidden text-green-500')
                        .addClass('text-red-500')
                        .html('<i class="fas fa-exclamation-circle mr-1"></i>Ein Fehler ist aufgetreten.');
                },
                complete: function() {
                    $this.prop('disabled', false).html(originalText);
                    setTimeout(() => {
                        $('#save-billing-address-feedback').fadeOut(() => {
                            $(this).addClass('hidden').css('display', '');
                        });
                    }, 5000);
                }
            });
        });
        
        // Navigation zurück zur Zahlung
        $('#btn-back-to-payment, #btn-billing-to-payment').on('click', function() {
            // Billing-Daten in Session speichern
            const billingData = {
                first_name: $('#billing_first_name').val(),
                last_name: $('#billing_last_name').val(),
                company: $('#billing_company').val(),
                address_1: $('#billing_street').val(),
                address_2: $('#billing_housenumber').val(),
                postcode: $('#billing_zip').val(),
                city: $('#billing_city').val(),
                country: $('#billing_country').val()
            };
            
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_save_billing_session',
                    nonce: yprint_address_ajax.nonce,
                    billing_data: billingData
                },
                success: function(response) {
                    console.log('Billing data saved to session');
                    // Zurück zum Payment Step
                    if (typeof showStep === 'function') {
                        showStep(2); // Payment Step
                    } else if (window.YPrintCheckout && window.YPrintCheckout.showStep) {
                        window.YPrintCheckout.showStep(2);
                    }
                },
                error: function() {
                    console.error('Error saving billing data to session');
                    // Trotzdem zurück navigieren
                    if (typeof showStep === 'function') {
                        showStep(2);
                    }
                }
            });
        });
    });
})(jQuery);
</script>