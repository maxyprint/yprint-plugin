<?php
/**
 * Partial Template: Schritt 2.5 - Rechnungsadresse (optional)
 * Nutzt die zentralen Address Manager Funktionen für maximale Effizienz
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Address Manager Instanz holen
$address_manager = YPrint_Address_Manager::get_instance();

// Session-Daten für vorausgefüllte Felder abrufen  
$session_billing_address = WC()->session->get('yprint_billing_address', array());

// Prüfe ob User eingeloggt ist und lade Adressen
$user_addresses = array();
$has_saved_addresses = false;
if (is_user_logged_in()) {
    $user_addresses = $address_manager->get_user_addresses(get_current_user_id());
    $has_saved_addresses = !empty($user_addresses);
}
?>

<h2 class="flex items-center">
    <i class="fas fa-file-invoice mr-2 text-yprint-blue"></i>
    <?php esc_html_e('Rechnungsadresse', 'yprint-checkout'); ?>
</h2>

<?php if (is_user_logged_in()) : ?>
    <!-- Adresskarten-Container (wird nur angezeigt wenn Adressen vorhanden) -->
    <div class="yprint-saved-addresses mt-6" data-address-type="billing" style="display: <?php echo $has_saved_addresses ? 'block' : 'none'; ?>;">
        <h3 class="saved-addresses-title">
            <i class="fas fa-file-invoice mr-2"></i>
            <?php _e('Gespeicherte Rechnungsadressen', 'yprint-plugin'); ?>
        </h3>
        
        <div class="address-cards-grid">
            <?php if ($has_saved_addresses) : ?>
                <?php foreach ($user_addresses as $address_id => $address_data) : ?>
                    <div class="address-card" data-address-id="<?php echo esc_attr($address_id); ?>">
                        <div class="address-card-content border-2 border-gray-200 rounded-lg p-4 transition-colors hover:border-blue-500 cursor-pointer">
                            <div class="flex items-center justify-between mb-2">
                                <h4 class="font-semibold">
                                    <?php echo esc_html(($address_data['first_name'] ?? '') . ' ' . ($address_data['last_name'] ?? '')); ?>
                                </h4>
                                <button type="button" class="btn-select-address btn btn-sm btn-primary" data-address-id="<?php echo esc_attr($address_id); ?>">
                                    <?php esc_html_e('Auswählen', 'yprint-checkout'); ?>
                                </button>
                            </div>
                            <div class="address-details text-sm text-gray-600">
                                <?php if (!empty($address_data['company'])) : ?>
                                    <div class="font-medium"><?php echo esc_html($address_data['company']); ?></div>
                                <?php endif; ?>
                                <div><?php echo esc_html($address_data['address_1'] . ' ' . ($address_data['address_2'] ?? '')); ?></div>
                                <div><?php echo esc_html(($address_data['postcode'] ?? '') . ' ' . ($address_data['city'] ?? '')); ?></div>
                                <div><?php echo esc_html($address_data['country'] ?? 'DE'); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
            
            <!-- "Neue Adresse hinzufügen" Karte -->
            <div class="address-card add-new-address-card cursor-pointer">
                <div class="address-card-content border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-colors hover:border-yprint-blue add-new-address-content">
                    <i class="fas fa-plus text-3xl text-gray-400 mb-2"></i>
                    <h4 class="font-semibold text-gray-600"><?php esc_html_e('Neue Rechnungsadresse hinzufügen', 'yprint-checkout'); ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <?php
    // Modal HTML bereitstellen
    try {
        echo $address_manager->get_address_modal_html();
    } catch (Exception $e) {
        if (current_user_can('administrator')) {
            echo '<div class="notice notice-error"><p>Address Modal Error: ' . esc_html($e->getMessage()) . '</p></div>';
        }
        error_log('YPrint Billing Address Modal Error: ' . $e->getMessage());
    }
    ?>
<?php endif; ?>

<!-- Billing-Formular (wird ausgeblendet wenn gespeicherte Adressen vorhanden sind) -->
<form id="billing-address-form" class="space-y-6 mt-6" style="display: <?php echo $has_saved_addresses ? 'none' : 'block'; ?>;">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="billing_first_name" class="form-label"><?php esc_html_e('Vorname', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_first_name" name="billing_first_name" 
                   value="<?php echo esc_attr($session_billing_address['first_name'] ?? ''); ?>" 
                   class="form-input" required>
        </div>
        <div>
            <label for="billing_last_name" class="form-label"><?php esc_html_e('Nachname', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_last_name" name="billing_last_name" 
                   value="<?php echo esc_attr($session_billing_address['last_name'] ?? ''); ?>" 
                   class="form-input" required>
        </div>
    </div>

    <div>
        <label for="billing_company" class="form-label"><?php esc_html_e('Firma (optional)', 'yprint-checkout'); ?></label>
        <input type="text" id="billing_company" name="billing_company" 
               value="<?php echo esc_attr($session_billing_address['company'] ?? ''); ?>" 
               class="form-input">
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
            <label for="billing_street" class="form-label"><?php esc_html_e('Straße', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_street" name="billing_street" 
                   value="<?php echo esc_attr($session_billing_address['address_1'] ?? ''); ?>" 
                   class="form-input" required>
        </div>
        <div>
            <label for="billing_housenumber" class="form-label"><?php esc_html_e('Hausnummer', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_housenumber" name="billing_housenumber" 
                   value="<?php echo esc_attr($session_billing_address['address_2'] ?? ''); ?>" 
                   class="form-input" required>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="billing_zip" class="form-label"><?php esc_html_e('PLZ', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_zip" name="billing_zip" 
                   value="<?php echo esc_attr($session_billing_address['postcode'] ?? ''); ?>" 
                   class="form-input" required>
        </div>
        <div>
            <label for="billing_city" class="form-label"><?php esc_html_e('Stadt', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_city" name="billing_city" 
                   value="<?php echo esc_attr($session_billing_address['city'] ?? ''); ?>" 
                   class="form-input" required>
        </div>
    </div>

    <div>
        <label for="billing_country" class="form-label"><?php esc_html_e('Land', 'yprint-checkout'); ?></label>
        <select id="billing_country" name="billing_country" class="form-select" required>
            <option value="DE" <?php selected($session_billing_address['country'] ?? '', 'DE'); ?>><?php esc_html_e('Deutschland', 'yprint-checkout'); ?></option>
            <option value="AT" <?php selected($session_billing_address['country'] ?? '', 'AT'); ?>><?php esc_html_e('Österreich', 'yprint-checkout'); ?></option>
            <option value="CH" <?php selected($session_billing_address['country'] ?? '', 'CH'); ?>><?php esc_html_e('Schweiz', 'yprint-checkout'); ?></option>
            <option value="NL" <?php selected($session_billing_address['country'] ?? '', 'NL'); ?>><?php esc_html_e('Niederlande', 'yprint-checkout'); ?></option>
        </select>
    </div>

    <?php if (is_user_logged_in()) : ?>
        <div class="yprint-save-address-actions mt-6">
            <button type="button" id="save-billing-address-button" class="btn btn-secondary">
                <i class="fas fa-save mr-2"></i><?php esc_html_e('Rechnungsadresse speichern', 'yprint-checkout'); ?>
            </button>
            <div id="save-billing-address-feedback" class="mt-2 text-sm hidden"></div>
        </div>
    <?php endif; ?>

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
(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('🚀 Optimized Billing Step loaded - using central Address Manager functions');
        
        // Initiale Validierung
        validateBillingForm();

        // Event-Handler für Adressauswahl (nutzt zentrale Address Manager Funktion)
        $(document).on('click', '.btn-select-address', function(e) {
            e.preventDefault();
            
            const $btn = $(this);
            const addressId = $btn.data('address-id');
            const originalText = $btn.html();
            
            console.log('📍 Selecting billing address:', addressId);
            
            // Loading-State
            $btn.html('<i class="fas fa-spinner fa-spin mr-2"></i>Wird ausgewählt...');
            
            // Nutze zentrale Address Manager AJAX-Funktion
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_set_checkout_address',  // Zentrale Funktion
                    nonce: yprint_address_ajax.nonce,
                    address_id: addressId
                },
                success: function(response) {
                    if (response.success && response.data && response.data.address_data) {
                        const addressData = response.data.address_data;
                        
                        // Speichere in Session für Payment Step
                        saveAddressToSession(addressData, function() {
                            console.log('✅ Billing address selected and saved to session');
                            
                            // Event für Payment Step UI-Update triggern
                            $(document).trigger('yprint_billing_address_selected', {
                                addressData: addressData,
                                addressId: addressId
                            });
                            
                            // Navigation zurück zum Payment Step
                            navigateToPaymentStep();
                        });
                        
                    } else {
                        console.error('❌ Invalid response structure:', response);
                        $btn.html(originalText);
                        alert('Fehler beim Auswählen der Adresse. Bitte versuchen Sie es erneut.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('❌ AJAX error:', error);
                    $btn.html(originalText);
                    alert('Fehler beim Auswählen der Adresse. Bitte versuchen Sie es erneut.');
                }
            });
        });

        // Event-Handler für "Neue Adresse hinzufügen"
        $(document).on('click', '.add-new-address-card', function() {
            console.log('📝 Adding new billing address');
            
            // Zeige Formular, verstecke Adresskarten
            $('.yprint-saved-addresses[data-address-type="billing"]').slideUp(300);
            $('#billing-address-form').slideDown(300);
            
            // Form-Felder leeren
            $('#billing-address-form input[type="text"]').val('');
            $('#billing_country').val('DE');
            
            // Validierung triggern
            setTimeout(validateBillingForm, 100);
        });

        // Event-Handler für "Rechnungsadresse speichern"
        $(document).on('click', '#save-billing-address-button', function(e) {
            e.preventDefault();
            
            console.log('💾 Saving new billing address');
            
            const billingData = {
                first_name: $('#billing_first_name').val().trim(),
                last_name: $('#billing_last_name').val().trim(),
                company: $('#billing_company').val().trim(),
                address_1: $('#billing_street').val().trim(),
                address_2: $('#billing_housenumber').val().trim(),
                postcode: $('#billing_zip').val().trim(),
                city: $('#billing_city').val().trim(),
                country: $('#billing_country').val() || 'DE'
            };
            
            // Validierung
            if (!billingData.first_name || !billingData.last_name || !billingData.address_1 || !billingData.postcode || !billingData.city) {
                $('#save-billing-address-feedback').removeClass('hidden text-green-600').addClass('text-red-500').text('Bitte füllen Sie alle Pflichtfelder aus.');
                return;
            }
            
            // Nutze zentrale Address Manager Speicherfunktion
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_save_checkout_address',  // Zentrale Funktion
                    nonce: yprint_address_ajax.nonce,
                    address_data: billingData
                },
                success: function(response) {
                    if (response.success) {
                        $('#save-billing-address-feedback').removeClass('hidden text-red-500').addClass('text-green-600').text('Rechnungsadresse gespeichert!');
                        
                        // Reload page to show new address in cards
                        setTimeout(() => {
                            location.reload();
                        }, 1500);
                    } else {
                        $('#save-billing-address-feedback').removeClass('hidden text-green-600').addClass('text-red-500').text(response.data?.message || 'Fehler beim Speichern');
                    }
                },
                error: function() {
                    $('#save-billing-address-feedback').removeClass('hidden text-green-600').addClass('text-red-500').text('Fehler beim Speichern der Adresse');
                }
            });
        });

        // Navigation zurück zur Zahlung
        $(document).on('click', '#btn-back-to-payment, #btn-billing-to-payment', function(e) {
            e.preventDefault();
            
            const isComplete = $(this).attr('id') === 'btn-billing-to-payment';
            console.log('🧭 Navigation button clicked:', $(this).attr('id'));
            
            if (isComplete) {
                // Speichere Formular-Daten wenn ausgefüllt
                saveBillingFormData(navigateToPaymentStep);
            } else {
                // Direkt zurück navigieren
                navigateToPaymentStep();
            }
        });

        // Event-Handler für Formular-Validierung
        $('#billing-address-form input, #billing-address-form select').on('input change', validateBillingForm);

        // Hilfsfunktionen
        function validateBillingForm() {
            const requiredFields = ['#billing_first_name', '#billing_last_name', '#billing_street', '#billing_housenumber', '#billing_zip', '#billing_city'];
            let allValid = true;
            
            requiredFields.forEach(selector => {
                const $field = $(selector);
                const value = $field.val().trim();
                
                if (!value) {
                    allValid = false;
                    $field.addClass('border-red-500');
                } else {
                    $field.removeClass('border-red-500');
                }
            });
            
            $('#btn-billing-to-payment').prop('disabled', !allValid);
        }

        function saveAddressToSession(addressData, callback) {
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_save_billing_session',  // Nutze existierende Session-Funktion
                    nonce: yprint_address_ajax.nonce,
                    billing_data: addressData
                },
                success: function(response) {
                    console.log('💾 Address saved to session:', response);
                    if (callback) callback();
                },
                error: function(error) {
                    console.error('❌ Session save error:', error);
                    if (callback) callback(); // Trotzdem weiter navigieren
                }
            });
        }

        function saveBillingFormData(callback) {
            const billingData = {
                first_name: $('#billing_first_name').val().trim(),
                last_name: $('#billing_last_name').val().trim(),
                company: $('#billing_company').val().trim(),
                address_1: $('#billing_street').val().trim(),
                address_2: $('#billing_housenumber').val().trim(),
                postcode: $('#billing_zip').val().trim(),
                city: $('#billing_city').val().trim(),
                country: $('#billing_country').val() || 'DE'
            };
            
            // Prüfe ob mindestens Name und Adresse vorhanden sind
            const hasValidData = billingData.first_name && billingData.last_name && 
                                 billingData.address_1 && billingData.postcode && billingData.city;
            
            if (hasValidData) {
                saveAddressToSession(billingData, callback);
            } else {
                // Keine/unvollständige Daten - direkt navigieren
                if (callback) callback();
            }
        }

        function navigateToPaymentStep() {
            console.log('🔄 Navigating back to Payment Step');
            
            // Step wechseln
            $('.checkout-step').removeClass('active').hide();
            $('#step-2').addClass('active').show();
            
            // URL aktualisieren
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('step', 'payment');
            history.pushState({step: 'payment'}, '', newUrl);
            
            // Event triggern für Payment Step UI-Update
            $(document).trigger('yprint_step_changed', {step: 'payment', from: 'billing'});
            
            console.log('✅ Navigation completed');
        }
    });

})(jQuery);
</script>