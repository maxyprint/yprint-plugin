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
    <!-- Adresskarten-Container für Address Manager -->
    <div class="yprint-saved-addresses mt-6" data-address-type="billing">
        <h3 class="saved-addresses-title">
            <i class="fas fa-file-invoice mr-2"></i>
            <?php _e('Gespeicherte Rechnungsadressen', 'yprint-plugin'); ?>
        </h3>
        
        <div class="address-cards-grid">
            <!-- Address Cards werden dynamisch durch Address Manager geladen -->
            <div id="billing-address-cards-container">
                <!-- Wird durch YPrintAddressManager.loadSavedAddresses('billing') gefüllt -->
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

<?php if (current_user_can('administrator') && isset($_GET['debug'])) : ?>
    <div class="debug-info bg-yellow-100 p-4 mt-4 rounded">
        <h4>🔍 Debug Information:</h4>
        <p><strong>User logged in:</strong> <?php echo is_user_logged_in() ? 'Yes' : 'No'; ?></p>
        <p><strong>Has saved addresses:</strong> <?php echo $has_saved_addresses ? 'Yes' : 'No'; ?></p>
        <p><strong>Address count:</strong> <?php echo count($user_addresses); ?></p>
        <?php if ($has_saved_addresses) : ?>
            <p><strong>Address IDs:</strong></p>
            <ul>
                <?php foreach ($user_addresses as $address_id => $address_data) : ?>
                    <li><?php echo esc_html($address_id); ?> - <?php echo esc_html(($address_data['first_name'] ?? '') . ' ' . ($address_data['last_name'] ?? '')); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
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
        console.log('🚀 Billing Step loaded - initializing with Address Manager');
        
        // Initiale Validierung
        validateBillingForm();
        
        // Address Manager für Billing initialisieren
        if (typeof window.YPrintAddressManager !== 'undefined' && isUserLoggedIn()) {
            console.log('🏗️ Loading billing addresses with Address Manager');
            
            // Address Manager für Billing-Typ laden
            setTimeout(() => {
                window.YPrintAddressManager.loadSavedAddresses('billing');
            }, 300);
        }

        // Event-Handler für Address Manager Events
        $(document).on('yprint_address_selected', function(event, data) {
            if (data.type === 'billing') {
                console.log('🎯 Billing address selected via Address Manager:', data);
                
                // Session speichern
                saveBillingAddressToSession(data.address, () => {
                    console.log('✅ Billing address saved, navigating to payment');
                    navigateToPaymentStep();
                });
            }
        });

        // Event-Handler für Adresse-Speichern
        $('#save-billing-address-button').on('click', function(e) {
            e.preventDefault();
            
            const addressData = {
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
            if (!addressData.first_name || !addressData.last_name || !addressData.address_1 || !addressData.postcode || !addressData.city) {
                $('#save-billing-address-feedback').removeClass('hidden text-green-600').addClass('text-red-500').text('Bitte füllen Sie alle Pflichtfelder aus.');
                return;
            }

            // AJAX Speichern
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_save_address',
                    yprint_address_nonce: yprint_address_ajax.nonce,
                    ...addressData
                },
                success: function(response) {
                    if (response.success) {
                        $('#save-billing-address-feedback').removeClass('hidden text-red-500').addClass('text-green-600').text('Adresse erfolgreich gespeichert!');
                        
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
                    action: 'yprint_save_billing_session',
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

        // Hilfsfunktion für Login-Status
        function isUserLoggedIn() {
            return document.body.classList.contains('logged-in') || 
                   (typeof yprint_checkout_params !== 'undefined' && yprint_checkout_params.is_logged_in === 'yes');
        }

        // Hilfsfunktion für Billing Session speichern
        function saveBillingAddressToSession(addressData, callback) {
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_save_billing_session',
                    nonce: yprint_address_ajax.nonce,
                    billing_data: addressData
                },
                success: function(response) {
                    console.log('💾 Billing address saved to session:', response);
                    if (callback) callback();
                },
                error: function(error) {
                    console.error('❌ Billing session save error:', error);
                    if (callback) callback(); // Trotzdem weiter navigieren
                }
            });
        }
    });

})(jQuery);
</script>