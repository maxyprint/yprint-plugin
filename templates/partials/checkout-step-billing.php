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
// yprint_billing_address ist der Session-Key für die ausgewählte/eingegebene Rechnungsadresse
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
    <div class="yprint-saved-addresses mt-6" data-address-type="billing">
        <h3 class="saved-addresses-title">
            <i class="fas fa-file-invoice mr-2"></i>
            <?php _e('Gespeicherte Rechnungsadressen', 'yprint-plugin'); ?>
        </h3>

        <div class="address-cards-grid">
            <div id="billing-address-cards-container">
                </div>
        </div>
    </div>

    <?php
// Modal HTML bereitstellen mit Billing-Kontext
try {
    $modal_html = $address_manager->get_address_modal_html();
    // Füge Billing-Kontext zum Modal hinzu
    $modal_html = str_replace(
        'class="address-modal"', 
        'class="address-modal" data-context="billing"', 
        $modal_html
    );
    echo $modal_html;
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

<form id="billing-address-form" class="space-y-6 mt-6" style="display: <?php echo $has_saved_addresses && is_user_logged_in() ? 'none' : 'block'; ?>;">
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

    // 🔧 Sichere Debug-Funktionen für Billing-Step
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

    // ➕ Add Billing Button - Nutzt Standard YPrintAddressManager (wie Address Step)
// Setze Billing-Kontext vor dem Standard-Handler
$(document).on('click', '.add-new-address-card', function(e) {
    // Kontext setzen BEVOR der Address Manager Handler ausgeführt wird
    window.currentAddressContext = 'billing';
});
        // ADDRESS MANAGER INITIALISIEREN (ROBUST)
function initializeBillingAddressManager() {
    console.log('🔧 initializeBillingAddressManager() aufgerufen');
    console.log('⏰ Timestamp:', new Date().toLocaleTimeString());
    
    // Prüfe alle Voraussetzungen
    if (typeof window.YPrintAddressManager === 'undefined') {
        console.log('❌ Address Manager not yet available, retrying...');
        return false;
    }
    console.log('✅ Address Manager verfügbar');
    
    if (!isUserLoggedIn()) {
        console.log('👤 User not logged in, skipping address loading');
        return true; // Beenden, aber als erfolgreich markieren
    }
    console.log('✅ User ist eingeloggt');
    
    if (typeof window.YPrintAddressManager.loadSavedAddresses !== 'function') {
        console.log('❌ loadSavedAddresses method not available, retrying...');
        return false;
    }
    console.log('✅ loadSavedAddresses Methode verfügbar');
    
    // Prüfe DOM-Bereitschaft der Ziel-Container
    const targetContainer = document.querySelector('#billing-address-cards-container');
    if (!targetContainer) {
        console.log('❌ Target container (#billing-address-cards-container) not ready, retrying...');
        return false;
    }
    console.log('✅ Target container gefunden:', targetContainer);

    console.log('🏗️ Loading billing addresses with Address Manager');

    try {
    // Standard-Aufruf ohne Kontext-Parameter (wie im Address Step)
    window.YPrintAddressManager.loadSavedAddresses();
    console.log('✅ loadSavedAddresses() erfolgreich aufgerufen');
} catch (error) {
    console.log('❌ Fehler beim Aufruf von loadSavedAddresses():', error);
    return false;
}

        // Sofortige Initialisierung mit Event-basierter Nachladung
$(document).ready(function() {
    // Standard-Initialisierung (wie im Address Step)
    if (typeof window.YPrintAddressManager !== 'undefined') {
        window.YPrintAddressManager.loadSavedAddresses();
        console.log('✅ Billing Address Manager sofort initialisiert');
    } else {
        console.log('⏳ Address Manager noch nicht verfügbar - warte auf Step-Wechsel');
    }
    
    // Event-Listener für Step-Wechsel
    $(document).on('yprint_step_changed', function(e, data) {
        if (data.step === 'billing') {
            console.log('🔄 Step-Wechsel zu Billing erkannt');
            if (typeof window.YPrintAddressManager !== 'undefined') {
                window.YPrintAddressManager.loadSavedAddresses();
                console.log('✅ Address Manager nach Step-Wechsel initialisiert');
            }
        }
    });
});

        // ✅ 3. ADDRESS MANAGER EVENTS ABHÖREN (Wiederverwendung!)
        // Event-Integration mit standardisiertem YPrintAddressManager (wie Address Step)
$(document).on('address_selected', function(event, addressId, addressData) {
    // Nur auf Billing-Kontext reagieren
    if (window.currentAddressContext === 'billing') {
        console.log('✅ Billing Adresse ausgewählt:', addressData);
        
        // UI aktualisieren - Formular ausblenden
        $('#billing-address-form').hide();
        $('.yprint-saved-addresses').show();
        
        // Original Handler aufrufen falls vorhanden
        if (typeof handleBillingAddressSelected === 'function') {
            handleBillingAddressSelected(event, addressId, addressData);
        }
    }
});

$(document).on('address_saved', function(event, addressData) {
    // Nur auf Billing-Kontext reagieren
    if (window.currentAddressContext === 'billing') {
        console.log('💾 Billing Adresse gespeichert:', addressData);
        
        // UI aktualisieren
        $('#billing-address-form').hide();
        $('.yprint-saved-addresses').show();
        
        // Adressen neu laden
        if (typeof window.YPrintAddressManager !== 'undefined') {
            window.YPrintAddressManager.loadSavedAddresses();
        }
        
        // Original Handler aufrufen falls vorhanden
        if (typeof handleBillingAddressSaved === 'function') {
            handleBillingAddressSaved(event, addressData);
        }
    }
});

$(document).on('address_deleted', function(event, addressId) {
    // Nur auf Billing-Kontext reagieren
    if (window.currentAddressContext === 'billing') {
        handleBillingAddressDeleted(event, addressId);
    }
});

$(document).on('modal_opened', function(event, modalContext) {
    // Nur auf Billing-Kontext reagieren
    if (modalContext === 'billing' || window.currentAddressContext === 'billing') {
        handleBillingModalOpened(event, modalContext);
    }
});

        // ✅ 4. BILLING-SPEZIFISCHE NAVIGATION SETUP
        setupBillingNavigation();

        // Event-Handler für Formular-Validierung
        // Triggered by manual input or changes in the form fields
        $('#billing-address-form input, #billing-address-form select').on('input change', validateBillingForm);

        // Event-Handler für den "Rechnungsadresse speichern" Button
        $('#save-billing-address-button').on('click', function(e) {
            e.preventDefault();
            if (typeof window.YPrintAddressManager !== 'undefined') {
                const addressData = {
                    type: 'billing', // Wichtig: Typ setzen
                    first_name: $('#billing_first_name').val().trim(),
                    last_name: $('#billing_last_name').val().trim(),
                    company: $('#billing_company').val().trim(),
                    address_1: $('#billing_street').val().trim(),
                    address_2: $('#billing_housenumber').val().trim(),
                    postcode: $('#billing_zip').val().trim(),
                    city: $('#billing_city').val().trim(),
                    country: $('#billing_country').val() || 'DE'
                };

                // Validierung vor dem Speichern (optional, Address Manager sollte es auch tun)
                if (validateBillingForm(true)) { // Pass true to check validation without UI update
                    window.YPrintAddressManager.saveAddress(addressData);
                } else {
                    $('#save-billing-address-feedback').removeClass('hidden text-green-600').addClass('text-red-500').text('Bitte alle erforderlichen Felder ausfüllen.');
                }
            }
        });
    }); // End of document.ready

    // 🎯 Event Handler für Adressauswahl (address_selected)
    function handleBillingAddressSelected(event, addressId, addressData) {
        // Prüfe Kontext über data-address-type des übergeordneten Containers
        // Oder über die globale Variable window.currentAddressContext
        const addressType = $(event.target).closest('[data-address-type]').attr('data-address-type');

        if (addressType === 'billing' || window.currentAddressContext === 'billing') {
            console.log('🎯 Billing address selected via Address Manager:', addressData);

            // Formular ausblenden und Adresskarten anzeigen, da eine Adresse ausgewählt wurde
            $('#billing-address-form').hide();
            $('.yprint-saved-addresses').show(); // Sicherstellen, dass der Container sichtbar ist

            // Session speichern mit standardisierten Daten
            saveBillingAddressToSession(addressData, () => {
                console.log('✅ Billing address saved to session');
                navigateToPaymentStep(); // Direkt weiter zum Payment Step nach Auswahl
            });
        }
    }

    // 💾 Event Handler für gespeicherte Adresse (address_saved)
    function handleBillingAddressSaved(event, addressData) {
        // Prüfe, ob es sich um eine Rechnungsadresse handelt
        if (addressData.type === 'billing' || window.currentAddressContext === 'billing') {
            console.log('✅ Billing address saved via Address Manager');
            $('#save-billing-address-feedback').removeClass('hidden text-red-500').addClass('text-green-600').text('Adresse erfolgreich gespeichert!');

            // UI aktualisieren - Adresskarten neu laden
            setTimeout(() => {
                if (window.YPrintAddressManager && window.YPrintAddressManager.loadSavedAddresses) {
                    window.YPrintAddressManager.loadSavedAddresses('billing');
                }
                // Nach dem Speichern Formular ausblenden und Karten anzeigen
                $('#billing-address-form').hide();
                $('.yprint-saved-addresses').show();
            }, 500);
        }
    }

    // 🗑️ Event Handler für gelöschte Adresse (address_deleted)
    function handleBillingAddressDeleted(event, addressId) {
        // Die Bedingung addressId.includes('billing_') ist spezifisch für diese Implementierung
        // Besser: Der Address Manager sollte den Typ im Event-Payload liefern.
        // Falls nicht, auf window.currentAddressContext verlassen.
        if (window.currentAddressContext === 'billing' && addressId.startsWith('billing_')) {
            console.log('🗑️ Billing address deleted via Address Manager:', addressId);
            // UI-Reset: Formular anzeigen, falls keine Adressen mehr gespeichert sind
            // Oder wenn der Nutzer keine Adresse ausgewählt hat und das Formular benötigt wird
            const addressCardsContainer = $('#billing-address-cards-container');
            // Eine kleine Verzögerung geben, damit der DOM vom Address Manager aktualisiert wird
            setTimeout(() => {
                // Hier überprüfen wir, ob nach dem Löschen nur der "Neue Adresse" Button übrig ist.
                // Das Element für "Neue Adresse" sollte ebenfalls eine Adresse sein, aber ohne id und mit der Klasse `add-new-address-card`
                // Eine bessere Prüfung wäre: ob der Address Manager tatsächlich keine Adressen mehr rendert (außer dem "add-new" button)
                const renderedAddressCards = addressCardsContainer.find('.address-card:not(.add-new-address-card)');
                if (renderedAddressCards.length === 0) {
                    $('#billing-address-form').show();
                    $('.yprint-saved-addresses').hide();
                    resetBillingForm(); // Felder leeren und Button disablen
                }
            }, 200);
        }
    }

    // 📝 Event Handler für Modal-Öffnung (modal_opened)
    function handleBillingModalOpened(event, modalContext) {
        // Stellt sicher, dass das Modal den korrekten Kontext erhält
        if (modalContext === 'billing' || window.currentAddressContext === 'billing') {
            $('#new-address-modal').attr('data-context', 'billing');
            console.log('Modal opened for billing context.');
        }
    }

    // 🧭 Navigation Setup
    function setupBillingNavigation() {
        $(document).on('click', '#btn-back-to-payment', function(e) {
            e.preventDefault();
            console.log('🧭 Navigation back to Payment Step');
            navigateToPaymentStep(); // Immer zurück zum Payment Step
        });

        $(document).on('click', '#btn-billing-to-payment', function(e) {
            e.preventDefault();
            console.log('🧭 Navigation to Payment Step from Billing Form');
            // Speichere Formulardaten und navigiere dann
            saveBillingFormData(navigateToPaymentStep);
        });
    }

    // 🔧 Hilfsfunktionen

    /**
     * Validiert das Rechnungsformular.
     * @param {boolean} silent If true, no visual feedback (red borders) will be applied.
     * @returns {boolean} True if all required fields are valid, false otherwise.
     */
    function validateBillingForm(silent = false) {
        const requiredFields = ['#billing_first_name', '#billing_last_name', '#billing_street', '#billing_housenumber', '#billing_zip', '#billing_city'];
        let allValid = true;

        requiredFields.forEach(selector => {
            const $field = $(selector);
            const value = $field.val().trim();

            if (!value) {
                allValid = false;
                if (!silent) {
                    $field.addClass('border-red-500');
                }
            } else {
                if (!silent) {
                    $field.removeClass('border-red-500');
                }
            }
        });

        // "Weiter zur Zahlung" Button nur aktivieren, wenn alle Felder gültig sind
        $('#btn-billing-to-payment').prop('disabled', !allValid);
        return allValid;
    }

    function resetBillingForm() {
        $('#billing-address-form')[0]?.reset();
        validateBillingForm(); // Disable button and clear validation styles
    }

    // Speichert die ausgewählte/eingegebene Billing-Adresse in der Session
    function saveBillingAddressToSession(addressData, callback) {
        $.ajax({
            url: yprint_address_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_save_billing_session', // Der spezifische AJAX-Action Hook für Billing
                nonce: yprint_address_ajax.nonce,
                billing_data: addressData
            },
            success: function(response) {
                console.log('💾 Billing address saved to session:', response);
                if (callback) callback();
            },
            error: function(error) {
                console.error('❌ Billing session save error:', error);
                if (callback) callback(); // Trotzdem weiter navigieren, um den Fluss nicht zu blockieren
            }
        });
    }

    // Sammelt Formulardaten und speichert sie in der Session
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

        // Prüfe, ob mindestens die erforderlichen Felder ausgefüllt sind
        const hasValidData = billingData.first_name && billingData.last_name &&
                             billingData.address_1 && billingData.postcode && billingData.city &&
                             validateBillingForm(true); // Überprüft auch visuell, wenn nicht silent

        if (hasValidData) {
            saveBillingAddressToSession(billingData, callback);
        } else {
            console.warn('Form data is incomplete or invalid. Not saving to session, but navigating.');
            if (callback) callback(); // Navigieren auch wenn Daten unvollständig sind (z.B. User ignoriert Felder)
        }
    }

    // Navigiert zum Payment Step
    function navigateToPaymentStep() {
        console.log('🔄 Navigating to Payment Step');

        // Step wechseln
        $('.checkout-step').removeClass('active').hide();
        $('#step-2').addClass('active').show(); // Angenommen, der Payment Step hat die ID 'step-2'

        // URL aktualisieren
        const newUrl = new URL(window.location);
        newUrl.searchParams.set('step', 'payment');
        history.pushState({step: 'payment'}, '', newUrl);

        // Event triggern für Payment Step UI-Update
        $(document).trigger('yprint_step_changed', {step: 'payment', from: 'billing'});

        console.log('✅ Navigation completed');
    }

    // 🔧 Hilfsfunktionen für robuste Initialisierung
    function isUserLoggedIn() {
        // Mehrere Methoden zur User-Status-Prüfung
        return document.body.classList.contains('logged-in') || 
               (typeof yprint_checkout_params !== 'undefined' && yprint_checkout_params.is_logged_in === 'yes') ||
               (typeof window.yprint_address_ajax !== 'undefined' && window.yprint_address_ajax.is_logged_in === 'yes');
    }
    
    function isDOMReady() {
        return document.readyState === 'complete' || document.readyState === 'interactive';
    }

})(jQuery);
</script>