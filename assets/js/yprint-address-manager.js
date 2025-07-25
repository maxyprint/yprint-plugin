/**
 * YPrint Address Manager JavaScript
 */
(function($) {
    'use strict';

    let addressManager = {
        modal: null,
        currentAddressType: 'shipping',
        selectedAddressId: null,
        
        // Neue Elements-Eigenschaft hinzufügen
        elements: {
            modal: null,
            addressContainer: null,
            loadingIndicator: null,
            shippingFieldsContainer: null,
            billingFieldsContainer: null,
            addNewAddressButton: null,
            saveAddressToggle: null
        },
        

        triggerSaveNewAddress: function() {
            const self = this;
            const form = $('#new-address-form');
            const saveButton = $('.btn-save-address');
            const originalText = saveButton.html();
        
            console.log('🚀 triggerSaveNewAddress: Speichervorgang gestartet');
            console.log('🔍 triggerSaveNewAddress: Modal-Status:', {
                modalExists: this.modal.length > 0,
                modalVisible: this.modal.is(':visible'),
                formExists: form.length > 0,
                buttonExists: saveButton.length > 0
            });
        
            // Deaktiviere den Button und zeige einen Ladezustand
            saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Speichere...');
            console.log('🔄 triggerSaveNewAddress: Button deaktiviert, Ladezustand aktiviert');
        
            // Debug: Alle Formularfelder ausgeben
            console.log('📝 triggerSaveNewAddress: Aktuelle Formularwerte:');
            form.find('input, select').each(function() {
                const $field = $(this);
                console.log(`  ${$field.attr('id') || $field.attr('name')}: "${$field.val()}"`);
            });
        
            // Formularvalidierung mit erweiterten Debug-Infos
            console.log('🔍 triggerSaveNewAddress: Starte erweiterte Formularvalidierung');
            const isValid = this.validateForm();
            
            if (!isValid) {
                console.error('❌ triggerSaveNewAddress: Validierung fehlgeschlagen');
                // Zusätzliche Diagnose: Prüfe ob Felder wirklich leer sind
                const requiredFields = form.find('input[required], select[required]');
                console.log('🔬 triggerSaveNewAddress: Detailanalyse der Required-Felder:');
                requiredFields.each(function() {
                    const $field = $(this);
                    const value = $field.val();
                    const isEmpty = !value || !value.trim();
                    console.log(`  ${$field.attr('id')}: Value="${value}", isEmpty=${isEmpty}, visible=${$field.is(':visible')}`);
                });
                
                saveButton.prop('disabled', false).html(originalText);
                console.log('🔄 triggerSaveNewAddress: Button wieder aktiviert nach Validierungsfehler');
                return;
            }
            
            console.log('✅ triggerSaveNewAddress: Validierung erfolgreich, fahre mit Speichern fort');
        
            // Prüfen, ob wir im Bearbeitungs-Modus sind (Vorhandensein einer Adress-ID im Modal-Data-Attribut oder im versteckten Feld)
            let addressId = self.modal.data('editing-address-id') || $('#new_address_edit_id').val();
            const isEditing = !!addressId;
            console.log('YPrint Debug: Bearbeitungsmodus:', isEditing, 'Adress-ID:', addressId);
        
            // Sammle die Formulardaten
            const formData = {
                action: 'yprint_save_address',
                yprint_address_nonce: yprint_address_ajax.nonce,
                name: $('#new_address_name').val() || ('Adresse vom ' + new Date().toLocaleDateString('de-DE')),
                first_name: $('#new_address_first_name').val(),
                last_name: $('#new_last_name').val(),
                company: $('#new_company').val(),
                address_1: $('#new_address_1').val(),
                address_2: $('#new_address_2').val(),
                postcode: $('#new_postcode').val(),
                city: $('#new_city').val(),
                country: $('#new_country').val(),
                is_company: $('#new_is_company').is(':checked' ? 1 : 0) // Sende 1 für true, 0 für false
            };
            console.log('YPrint Debug: Formulardaten gesammelt:', formData);
        
            // Füge die Adress-ID hinzu, wenn wir eine bestehende Adresse bearbeiten
            if (isEditing) {
                formData.id = addressId;
                console.log('YPrint Debug: Adress-ID für Bearbeitung hinzugefügt:', addressId);
            }
        
            // AJAX-Anfrage zum Speichern/Aktualisieren der Adresse
            console.log('YPrint Debug: Starte AJAX-Anfrage zum Speichern/Aktualisieren.');
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    console.log('YPrint Debug: AJAX-Antwort erhalten:', response);
                    if (response.success) {
                        console.log('YPrint Debug: Speichern/Aktualisieren erfolgreich.');
                        self.closeAddressModal(); // Schließe das Modal nach erfolgreicher Speicherung/Aktualisierung
                        console.log('YPrint Debug: Adressmodal geschlossen.');
                        self.loadSavedAddresses(); // Lade die aktualisierten Adressen neu
                        console.log('YPrint Debug: loadSavedAddresses() aufgerufen, um Adressen neu zu laden.');
                        self.showMessage(isEditing ? 'Adresse erfolgreich aktualisiert!' : 'Adresse erfolgreich gespeichert!', 'success');
                        console.log('YPrint Debug: Erfolgsmeldung angezeigt.');
                    } else {
                        console.log('YPrint Debug: Fehler beim Speichern/Aktualisieren:', response.data.message);
                        self.showFormError(response.data.message || 'Fehler beim Speichern der Adresse.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('YPrint Debug: AJAX-Fehler beim Speichern der Adresse:', error);
                    self.showFormError('Ein unerwarteter Fehler ist beim Speichern aufgetreten.');
                },
                complete: function() {
                    // Reaktiviere den Button und setze den ursprünglichen Text zurück
                    saveButton.prop('disabled', false).html(originalText);
                    console.log('YPrint Debug: Speichern-Button wieder aktiviert.');
                }
            });
        },

        init: function() {
            console.log('=== YPrint Address Manager: Starting Initialization ===');
            console.log('Current URL:', window.location.href);
            console.log('User Agent:', navigator.userAgent);
            
            // DEBUG: Event-Bubbling-Test
            $(document).on('click', function (e) {
                if ($(e.target).hasClass('btn-save-address') || $(e.target).closest('.btn-save-address').length) {
                    console.log('🧪 document click reached for btn-save-address', e.target);
                }
            });
            
            // DEBUG: Shadow DOM Test
            this.checkForShadowDOM();
            
            // DOM Elements
            this.elements.modal = $('#new-address-modal');
            this.elements.addressContainer = $('.yprint-saved-addresses');
            this.elements.loadingIndicator = this.elements.addressContainer.find('.loading-addresses');
            this.elements.shippingFieldsContainer = $('#address-form'); // Anpassen an deine ID
            this.elements.billingFieldsContainer = $('#billing-address-fields'); // Anpassen an deine ID
            this.elements.addNewAddressButton = $('.add-new-address-card');
            this.elements.saveAddressToggle = $('#yprint_save_new_address');
            
            // Lokale Referenzen für Rückwärtskompatibilität
            this.modal = this.elements.modal;
            this.addressContainer = this.elements.addressContainer;
            this.loadingIndicator = this.elements.loadingIndicator;
            
            console.log('DOM Elements found:');
            console.log('  - Modal:', this.elements.modal.length, this.elements.modal);
            console.log('  - Address Container:', this.elements.addressContainer.length, this.elements.addressContainer);
            console.log('  - Loading Indicator:', this.elements.loadingIndicator.length, this.elements.loadingIndicator);
            console.log('  - Shipping Fields:', this.elements.shippingFieldsContainer.length);
            console.log('  - Billing Fields: ', this.elements.billingFieldsContainer.length);
            console.log('  - Add New Button:', this.elements.addNewAddressButton.length);
            console.log('  - Save Toggle:', this.elements.saveAddressToggle.length);
            
            console.log('DOM Elements found:');
            console.log('  - Modal:', this.modal.length, this.modal);
            console.log('  - Address Container:', this.addressContainer.length, this.addressContainer);
            console.log('  - Loading Indicator:', this.loadingIndicator.length, this.loadingIndicator);
            console.log('  - Container initial visibility:', this.addressContainer.is(':visible'));
            console.log('  - Container CSS display:', this.addressContainer.css('display'));
            
            // Check for required dependencies mit Fallback
if (typeof yprint_address_ajax === 'undefined') {
    console.error('ERROR: yprint_address_ajax object not found! Check wp_localize_script.');
    console.warn('Creating fallback yprint_address_ajax object');
    window.yprint_address_ajax = {
        ajax_url: window.ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '', // Beachten Sie: Ein leerer Nonce kann zu Sicherheitsproblemen führen, wenn Ihr Backend Nonces erwartet.
        messages: {
            address_saved: 'Adresse gespeichert',
            error_saving: 'Fehler beim Speichern',
            loading_addresses: 'Lade Adressen...'
        }
    };
}
console.log('AJAX Config:', yprint_address_ajax);
            
            // Events binden
            this.bindEvents();
            console.log('Events bound successfully');
            
            // Gespeicherte Adressen laden wenn eingeloggt
            const isLoggedIn = this.isUserLoggedIn();
            console.log('User logged in check:', isLoggedIn);
            
            if (isLoggedIn) {
                console.log('User is logged in - loading saved addresses...');
                this.loadSavedAddresses();
            } else {
                console.log('User not logged in - hiding address container and showing form');
                this.addressContainer.hide();
                $('#address-form').show();
            }
            
            console.log('=== YPrint Address Manager: Initialization Complete ===');
        },
        
        bindEvents: function() {
            const self = this;
        
            // Neue Adresse hinzufügen
            $(document).on('click', '.add-new-address-card', function() {
                // Kontext setzen
                const isBillingContext = $(this).closest('[data-address-type="billing"]').length > 0;
                window.currentAddressContext = isBillingContext ? 'billing' : 'shipping';
                
                // Einfach nur das Modal öffnen - mehr nicht!
                if (window.YPrintAddressManager && window.YPrintAddressManager.openAddressModal) {
                    window.YPrintAddressManager.openAddressModal();
                }
            });
        
            // Adresse auswählen
$(document).on('click', '.btn-select-address', function(e) {
    e.preventDefault();
    const addressCard = $(this).closest('.address-card');
    const addressId = addressCard.data('address-id');
    
    // Lade-Status anzeigen
    $(this).html('<i class="fas fa-spinner fa-spin mr-2"></i>Wird ausgewählt...');
    $(this).prop('disabled', true);
    
    self.selectAddress(addressId);
});

// Die ganze Adresskarte anklickbar machen (für bessere UX)
$(document).on('click', '.address-card', function(e) {
    // Nur triggern, wenn nicht auf einen Button innerhalb der Karte geklickt wurde
    if (!$(e.target).closest('button').length) {
        $(this).find('.btn-select-address').trigger('click');
    }
});
        
            // Standard-Adresse setzen
            $(document).on('click', '.btn-set-default', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const addressCard = $(this).closest('.address-card');
                const addressId = addressCard.data('address-id');
                self.setDefaultAddress(addressId);
            });
        
            // Adresse löschen
            $(document).on('click', '.btn-delete-address', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const addressCard = $(this).closest('.address-card');
                const addressId = addressCard.data('address-id');
                self.deleteAddress(addressId);
            });
            
            // Event für das Bearbeiten einer Adresse mit verbesserter Behandlung
$(document).on('click', '.btn-edit-address', function(e) {
    e.preventDefault();
    e.stopPropagation();
    
    const $button = $(this);
    const addressCard = $button.closest('.address-card');
    const addressId = addressCard.data('address-id') || $button.data('address-id');
    
    // Loading-Zustand anzeigen
    $button.addClass('loading').prop('disabled', true);
    
    console.log('Bearbeiten button clicked for address ID:', addressId);
    
    try {
        // Adressdaten aus dem data-Attribut abrufen
        const addressDataStr = addressCard.data('address-data');
        
        if (!addressDataStr) {
            throw new Error('Keine Adressdaten gefunden');
        }
        
        const addressData = JSON.parse(decodeURIComponent(addressDataStr));
        
        // Modal öffnen
        self.openAddressModal(addressId, addressData);
        
    } catch (error) {
        console.error('Error parsing address data from card:', error);
        self.showMessage('Fehler beim Laden der Adresse', 'error');
    } finally {
        // Loading-Zustand entfernen nach kurzer Verzögerung
        setTimeout(() => {
            $button.removeClass('loading').prop('disabled', false);
        }, 300);
    }
});

// Keyboard Navigation Support
$(document).on('keydown', '.btn-address-action', function(e) {
    // Enter oder Space aktiviert den Button
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        $(this).trigger('click');
    }
});

// Touch-Ereignisse für bessere mobile Erfahrung
$(document).on('touchstart', '.btn-address-action', function(e) {
    $(this).addClass('touch-active');
});

$(document).on('touchend touchcancel', '.btn-address-action', function(e) {
    $(this).removeClass('touch-active');
});
        
            // Modal schließen
            $(document).on('click', '.btn-close-modal, .address-modal-overlay', function() {
                self.closeAddressModal();
            });
        
            // Modal-Buttons
            $(document).on('click', '.btn-cancel-address', function() {
                self.closeAddressModal();
            });
        
            // Mehrschichtiger Event-Binding-Ansatz
$(document).on('click', '.btn-save-address', function(e) {
    console.log('📢 Delegierter Click-Handler erreicht!', e.target);
    e.preventDefault();
    e.stopPropagation();
    self.saveNewAddress();
    self.triggerSaveNewAddress();
});

// Backup: Direkter Event-Handler für den Fall, dass Delegation fehlschlägt
$(document).on('DOMNodeInserted', function(e) {
    if ($(e.target).hasClass('btn-save-address') || $(e.target).find('.btn-save-address').length) {
        console.log('🔄 Neue .btn-save-address Elemente erkannt, binde direkte Handler');
        $('.btn-save-address').off('click.backup').on('click.backup', function(e) {
            console.log('🎯 Backup direkter Click-Handler ausgelöst!');
            e.preventDefault();
            e.stopPropagation();
            self.saveNewAddress();
            self.triggerSaveNewAddress();
        });
    }
});

// Sofortige Bindung für bereits existierende Buttons
$('.btn-save-address').off('click.direct').on('click.direct', function(e) {
    console.log('🎯 Direkter Click-Handler ausgelöst!');
    e.preventDefault();
    e.stopPropagation();
    self.saveNewAddress();
    self.triggerSaveNewAddress();
});
        
            // ESC-Taste zum Schließen
            $(document).on('keyup', function(e) {
                if (e.key === 'Escape' && self.modal.hasClass('active')) {
                    self.closeAddressModal();
                }
            });
        
            // Form-Validierung
            $('#new-address-form input').on('input', function() {
                self.validateForm();
            });
        
            // Adresse speichern Button für das Checkout-Formular
$(document).on('click', '#save-address-button', function() {
    self.saveAddressFromForm();
    self.triggerSaveNewAddress(); self.closeAddressModal();
});

// Billing-Adresse speichern Button
$(document).on('click', '#save-billing-address-button', function() {
    self.saveBillingAddressFromForm();
});
            
            // Event für "Andere Adresse wählen" Link
            $(document).on('click', '.change-address-link button', function() {
                self.showSavedAddressesContainer(true);
                $(this).closest('.change-address-link').remove();
                self.showAddressForm(false);
            });
        },

/**
 * Speichert eine Adresse aus dem Checkout-Formular
 */
saveAddressFromForm: function() {
    const self = this;
    const saveButton = $('#save-address-button');
    const feedbackElement = $('#save-address-feedback');

    // Erkenne den Kontext (Address oder Billing Step)
    const isBillingContext = $('#billing_first_name').length > 0;
    
    // Definiere Feldmapping je nach Kontext
    const fieldMapping = isBillingContext ? {
        first_name: 'billing_first_name',
        last_name: 'billing_last_name',
        company: 'billing_company',
        street: 'billing_street',
        housenumber: 'billing_housenumber',
        zip: 'billing_zip',
        city: 'billing_city',
        country: 'billing_country',
        phone: 'billing_phone'
    } : {
        first_name: 'first_name',
        last_name: 'last_name',
        company: 'company',
        street: 'street',
        housenumber: 'housenumber',
        zip: 'zip',
        city: 'city',
        country: 'country',
        phone: 'phone'
    };

    // Prüfe, ob die erforderlichen Felder vorhanden sind
    const requiredFields = ['first_name', 'last_name', 'street', 'housenumber', 'zip', 'city', 'country'];
    let isValid = true;
    let missingFields = [];

    requiredFields.forEach(field => {
        const fieldId = fieldMapping[field];
        const value = $('#' + fieldId).val();
        if (!value || !value.trim()) {
            isValid = false;
            missingFields.push(field);
            $('#' + fieldId).addClass('border-yprint-error');
        } else {
            $('#' + fieldId).removeClass('border-yprint-error');
        }
    });

    if (!isValid) {
        const errorMessage = 'Bitte füllen Sie alle Pflichtfelder aus: ' + missingFields.join(', ');
        feedbackElement.removeClass('hidden text-yprint-success').addClass('text-yprint-error').html(errorMessage);
        return;
    }
    
    // Sammle die Daten aus den Formularfeldern
    const addressData = {
        name: (isBillingContext ? 'Rechnungsadresse vom ' : 'Adresse vom ') + new Date().toLocaleDateString('de-DE'),
        first_name: $('#' + fieldMapping.first_name).val(),
        last_name: $('#' + fieldMapping.last_name).val(),
        company: $('#' + fieldMapping.company).val() || '',
        address_1: $('#' + fieldMapping.street).val(),
        address_2: $('#' + fieldMapping.housenumber).val(),
        postcode: $('#' + fieldMapping.zip).val(),
        city: $('#' + fieldMapping.city).val(),
        country: $('#' + fieldMapping.country).val() || 'DE',
        phone: $('#' + fieldMapping.phone).val() || ''
    };
    
    // Aktualisiere Button und zeige Feedback
    saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Speichern...');
    feedbackElement.removeClass('text-yprint-success text-yprint-error').addClass('text-yprint-text-secondary').html('Adresse wird gespeichert...').removeClass('hidden');
    
    // Bestimme die AJAX-Action basierend auf dem Kontext
    const ajaxAction = isBillingContext ? 'yprint_save_billing_session' : 'yprint_save_checkout_address';
    const ajaxData = isBillingContext ? {
        action: ajaxAction,
        nonce: yprint_address_ajax.nonce,
        billing_data: addressData
    } : {
        action: ajaxAction,
        nonce: yprint_address_ajax.nonce,
        address_data: addressData
    };
    
    // AJAX-Request zum Speichern
    $.ajax({
        url: yprint_address_ajax.ajax_url,
        type: 'POST',
        data: ajaxData,
        success: function(response) {
            if (response.success) {
                feedbackElement.removeClass('text-yprint-text-secondary').addClass('text-yprint-success').html('<i class="fas fa-check-circle mr-1"></i>' + (response.data.message || 'Adresse erfolgreich gespeichert.'));
                
                // Lade gespeicherte Adressen neu
                self.loadSavedAddresses();
            } else {
                feedbackElement.removeClass('text-yprint-text-secondary').addClass('text-yprint-error').html('<i class="fas fa-exclamation-circle mr-1"></i>' + (response.data.message || 'Fehler beim Speichern der Adresse.'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error saving address:', error);
            feedbackElement.removeClass('text-yprint-text-secondary').addClass('text-yprint-error').html('<i class="fas fa-exclamation-circle mr-1"></i>Ein Fehler ist aufgetreten.');
        },
        complete: function() {
            saveButton.prop('disabled', false).html('<i class="fas fa-save mr-2"></i>Adresse speichern');
            
            // Blende das Feedback nach 5 Sekunden aus
            setTimeout(function() {
                feedbackElement.fadeOut(function() {
                    $(this).addClass('hidden').css('display', '');
                });
            }, 5000);
        }
    });
},

// Funktion komplett entfernen - wird nicht mehr benötigt
        
        isUserLoggedIn: function() {
            // Prüfe ob User eingeloggt ist
            return $('body').hasClass('logged-in') || $('#wpadminbar').length > 0;
        },
        
        loadSavedAddresses: function(addressType = 'shipping') {
            const self = this;
        
            console.log('=== Loading Saved Addresses ===');
            console.log('Initial Address Type:', addressType);
        
            // CRITICAL FIX: Optionaler Context-Override für korrekte address_type Setzung
            // Wenn ein expliziter contextType übergeben wird, überschreibt er den addressType
            // Dies ist nützlich, wenn die Funktion von außerhalb mit einem spezifischen Kontext aufgerufen wird.
            if (typeof arguments[0] === 'string' && (arguments[0] === 'billing' || arguments[0] === 'shipping')) {
                addressType = arguments[0];
                console.log('🎯 loadSavedAddresses: Address Type overridden to', addressType);
            }
        
            // Bestimme den richtigen Container basierend auf dem Adresstyp
            let targetContainer, targetLoadingIndicator;
        
            if (addressType === 'billing') {
                targetContainer = $('.yprint-saved-addresses[data-address-type="billing"]');
                targetLoadingIndicator = targetContainer.find('.loading-addresses');
            } else {
                // Falls addressType nicht 'billing' ist, verwenden wir die Standard-Container für 'shipping'
                targetContainer = this.addressContainer;
                targetLoadingIndicator = this.loadingIndicator;
            }
        
            // Container-Status vor AJAX-Call
            targetLoadingIndicator.show();
            targetContainer.find('.address-cards-grid').hide();
            targetContainer.show();
        
            const ajaxData = {
                action: 'yprint_get_saved_addresses',
                nonce: yprint_address_ajax.nonce,
                address_type: addressType // Hier wird der (potenziell überschriebene) addressType verwendet
            };
        
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    // Beginn der integrierten Erfolgslogik
                    if (response && response.success && response.data && response.data.addresses) {
                        const addresses = response.data.addresses;
                        const addressCount = Object.keys(addresses).length;
        
                        console.log('Success: Address data received, count:', addressCount);
        
                        // CRITICAL FIX: addressType parameter korrekt weiterleiten
                        self.renderAddresses(addresses, addressType); // addressType hier übergeben
        
                        // Zeigt den Container für gespeicherte Adressen an und blendet das Formular aus
                        self.showSavedAddressesContainer(true);
                        self.showAddressForm(false);
        
                    } else {
                        const errorMsg = (response && response.data && response.data.message) || 'Fehler beim Laden der Adressen.';
                        console.error('Error loading addresses:', errorMsg);
                        self.showMessage(errorMsg, 'error');
                        self.showSavedAddressesContainer(false);
                        self.showAddressForm(true);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    self.showMessage('Fehler beim Laden der Adressen: ' + error, 'error');
                    self.showSavedAddressesContainer(false);
                    self.showAddressForm(true);
                },
                complete: function() {
                    targetLoadingIndicator.hide();
                }
            });
        },
        
        renderAddresses: function(addresses, addressType = 'shipping') {
            const self = this;
        
            // Referenz zum Container und Grid - nutze den aktuell aktiven Container
            let container = this.addressContainer;
        
            // Falls der Container über data-address-type spezifiziert ist, nutze diesen
            if (addressType === 'billing') {
                const billingContainer = $('.yprint-saved-addresses[data-address-type="billing"]');
                if (billingContainer.length > 0) {
                    container = billingContainer;
                }
            }
        
            if (container.length === 0) return;
        
            const grid = container.find('.address-cards-grid');
        
            // Grid komplett leeren
            grid.empty();
        
            // Wenn keine Adressen vorhanden sind, nur "Neue Adresse" Kachel anzeigen
            if (Object.keys(addresses).length === 0) {
                const addNewCard = `
                    <div class="address-card add-new-address-card cursor-pointer">
                        <div class="address-card-content border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-colors hover:border-yprint-blue">
                            <i class="fas fa-plus text-3xl text-gray-400 mb-2"></i>
                            <h4 class="font-semibold text-gray-600">Neue Adresse hinzufügen</h4>
                        </div>
                    </div>
                `;
                grid.html(addNewCard);
                console.log('No addresses found - showing add new card only');
                container.show();
                grid.show();
                return;
            }
        
            // Sortiere Adressen: Standard-Adresse zuerst, dann alphabetisch nach Name
            const sortedAddresses = Object.entries(addresses).sort(([idA, addrA], [idB, addrB]) => {
                // Standard-Adresse hat Priorität
                if (addrA.is_default && !addrB.is_default) return -1;
                if (!addrA.is_default && addrB.is_default) return 1;
        
                // Alphabetisch nach Name sortieren
                const nameA = (addrA.name || 'Gespeicherte Adresse').toLowerCase();
                const nameB = (addrB.name || 'Gespeicherte Adresse').toLowerCase();
                return nameA.localeCompare(nameB);
            });
        
            sortedAddresses.forEach(([addressId, address]) => {
                // CRITICAL FIX: Verwende createAddressCard Funktion mit korrekt übergebenem addressType
                const card = self.createAddressCard(address, addressType);
                card.attr('data-address-id', addressId);
                card.attr('data-address-type', addressType);
            
                // Adressdaten als JSON für Debug-Zwecke hinzufügen
                const addressDataJson = encodeURIComponent(JSON.stringify(address));
                card.attr('data-address-data', addressDataJson);
            
                grid.append(card);
            });
        
            // Zum Schluss "Neue Adresse" Kachel hinzufügen
            const addNewCard = `
                <div class="address-card add-new-address-card cursor-pointer">
                    <div class="address-card-content border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-colors hover:border-yprint-blue">
                        <i class="fas fa-plus text-3xl text-gray-400 mb-2"></i>
                        <h4 class="font-semibold text-gray-600">Neue Adresse hinzufügen</h4>
                    </div>
                </div>
            `;
        
            grid.append(addNewCard);
        
            // Container und Grid anzeigen
            container.show();
            grid.show();
        
            console.log('Rendered addresses:', Object.keys(addresses).length, 'Container visible:', container.is(':visible'));
        },

        // Debug-Methoden
debugDOMState: function() {
    console.log('=== Debug DOM State ===');
    console.log('Address Container:');
    console.log('  - Length:', this.addressContainer.length);
    console.log('  - Visible:', this.addressContainer.is(':visible'));
    console.log('  - Display:', this.addressContainer.css('display'));
    console.log('  - Opacity:', this.addressContainer.css('opacity'));
    console.log('  - Height:', this.addressContainer.height());
    console.log('  - Classes:', this.addressContainer.attr('class'));
    
    console.log('Address Cards Grid:');
    const grid = this.addressContainer.find('.address-cards-grid');
    console.log('  - Length:', grid.length);
    console.log('  - Visible:', grid.is(':visible'));
    console.log('  - Display:', grid.css('display'));
    console.log('  - Children:', grid.children().length);
    
    console.log('Loading Indicator:');
    console.log('  - Length:', this.loadingIndicator.length);
    console.log('  - Visible:', this.loadingIndicator.is(':visible'));
    console.log('  - Display:', this.loadingIndicator.css('display'));
    
    console.log('Address Form:');
    const form = $('#address-form');
    console.log('  - Length:', form.length);
    console.log('  - Visible:', form.is(':visible'));
    console.log('  - Display:', form.css('display'));
    
    console.log('Body Classes:', $('body').attr('class'));
    console.log('=== End Debug DOM State ===');
},

debugUserState: function() {
    console.log('=== Debug User State ===');
    console.log('isUserLoggedIn():', this.isUserLoggedIn());
    console.log('Body has logged-in class:', $('body').hasClass('logged-in'));
    console.log('WP Admin Bar exists:', $('#wpadminbar').length > 0);
    console.log('Current user (if available):', typeof current_user !== 'undefined' ? current_user : 'Not defined');
    console.log('=== End Debug User State ===');
},

debugAjaxConfig: function() {
    console.log('=== Debug AJAX Config ===');
    console.log('yprint_address_ajax defined:', typeof yprint_address_ajax !== 'undefined');
    if (typeof yprint_address_ajax !== 'undefined') {
        console.log('AJAX URL:', yprint_address_ajax.ajax_url);
        console.log('Nonce:', yprint_address_ajax.nonce);
        console.log('Messages:', yprint_address_ajax.messages);
    }
    console.log('jQuery version:', $.fn.jquery);
    console.log('=== End Debug AJAX Config ===');
},

checkForShadowDOM: function() {
    setTimeout(() => {
        const btn = document.querySelector('.btn-save-address');
        if (btn) {
            let el = btn;
            while (el) {
                if (el instanceof ShadowRoot) {
                    console.warn('👻 Button ist in einem Shadow DOM!');
                    this.handleShadowDOMBinding(el);
                    return;
                }
                el = el.parentNode || el.host;
            }
            console.log('✅ Button ist NICHT in einem Shadow DOM');
        }
    }, 1000);
},

handleShadowDOMBinding: function(shadowRoot) {
    console.log('🔧 Binde Event-Listener im Shadow DOM');
    $(shadowRoot).on('click', '.btn-save-address', (e) => {
        console.log('📢 Shadow DOM Click-Handler erreicht!');
        this.saveNewAddress();
    });
},
        
createAddressCard: function(address, addressType) {
    // CRITICAL FIX: addressType als Parameter hinzugefügt mit Fallback
    addressType = addressType || 'shipping';
    
    const isDefault = address.is_default;
    const canDelete = !['billing_default', 'shipping_default'].includes(address.id);
    
    let actions = '';
    if (canDelete) {
        actions = `
            <div class="address-card-actions">
                <button type="button" class="btn-address-action btn-set-default" 
                        title="${yprint_address_ajax.messages.set_as_default || 'Als Standard setzen'}">
                    <i class="fas fa-star"></i>
                </button>
                <button type="button" class="btn-address-action btn-edit-address" title="Adresse bearbeiten">
                    <i class="fas fa-edit"></i>
                </button>
                <button type="button" class="btn-address-action btn-delete-address" 
                        title="${yprint_address_ajax.messages.delete_address || 'Adresse löschen'}">
                    <i class="fas fa-trash"></i>
                </button>
            </div>
        `;
    }
    
    let defaultBadge = '';
    if (isDefault) {
        defaultBadge = `
            <span class="default-badge">
                <i class="fas fa-star"></i>
                Standard
            </span>
        `;
    }
    
    const formattedAddress = this.formatAddressDisplay(address);

return $(`
    <div class="address-card" data-address-id="${address.id}" data-address-type="${addressType}">

                    <div class="address-card-header">
                        <div class="address-card-title">
                            ${address.name}
                            ${defaultBadge}
                        </div>
                        ${actions}
                    </div>
                    <div class="address-card-content">
                        ${formattedAddress}
                    </div>
                    <div class="address-card-footer">
                        <button type="button" class="btn btn-primary btn-select-address">
                            <i class="fas fa-check mr-2"></i>
                            Diese Adresse verwenden
                        </button>
                    </div>
                </div>
            `);
        },
        
/**
 * Fügt WooCommerce Standard-Adresse zur Auswahl hinzu
 */
addWooCommerceDefaultAddress: function(grid) {
    // Prüfe ob WooCommerce-Daten in den Feldern vorhanden sind
    const wcAddress = {
        address_1: $('#street').val() || '',
        address_2: $('#housenumber').val() || '',
        postcode: $('#zip').val() || '',
        city: $('#city').val() || '',
        country: $('#country').val() || 'DE',
        phone: $('#phone').val() || '',
        first_name: '', // Könnte aus anderen Feldern kommen
        last_name: '',
        company: ''
    };
    
    // Nur hinzufügen wenn mindestens Straße oder Stadt vorhanden
    if (wcAddress.address_1 || wcAddress.city) {
        const card = $(`
            <div class="address-card">
                <label class="cursor-pointer">
                    <input type="radio" name="selected_address" value="wc_default" 
                           data-address-type="wc_default" 
                           data-address-data='${JSON.stringify(wcAddress)}' 
                           class="sr-only">
                    <div class="address-card-content border-2 border-gray-200 rounded-lg p-4 transition-colors hover:border-blue-500">
                        <div class="flex items-center justify-between mb-2">
                            <h4 class="font-semibold">${yprint_address_ajax.messages.standard_address || 'Standard-Adresse'}</h4>
                            <i class="fas fa-check text-blue-500 opacity-0 address-selected-icon"></i>
                        </div>
                        <div class="text-sm text-gray-600">
                            ${wcAddress.address_1} ${wcAddress.address_2}<br>
                            ${wcAddress.postcode} ${wcAddress.city}<br>
                            ${wcAddress.country}
                        </div>
                    </div>
                </label>
            </div>
        `);
        grid.prepend(card);
    }
},
        formatAddressDisplay: function(address) {
            let formatted = '';
            
            // Name
            if (address.first_name || address.last_name) {
                formatted += `${address.first_name || ''} ${address.last_name || ''}`.trim();
            }
            
            // Firma
            if (address.company) {
                if (formatted) formatted += '<br>';
                formatted += address.company;
            }
            
            // Adresse
            if (formatted) formatted += '<br>';
            formatted += address.address_1;
            if (address.address_2) {
                formatted += ' ' + address.address_2;
            }
            
            // Stadt
            if (formatted) formatted += '<br>';
            formatted += `${address.postcode} ${address.city}`;
            
            // Land
            if (formatted) formatted += '<br>';
            formatted += address.country;
            
            return formatted;
        },
        
        selectAddress: function(addressId) {
            const self = this;
        
            // Visuelle Auswahl aktualisieren
            $('.address-card').removeClass('selected');
            $(`.address-card[data-address-id="${addressId}"]`).addClass('selected');
        
            this.selectedAddressId = addressId;
        
            // Loading-Anzeige
            const btnSelectAddress = $(`.address-card[data-address-id="${addressId}"] .btn-select-address`);
            const originalBtnText = btnSelectAddress.html();
            btnSelectAddress.html('<i class="fas fa-spinner fa-spin mr-2"></i>Wird ausgewählt...');
        
            // KONSISTENTE CONTEXT DETECTION: Eindeutige Prioritätsreihenfolge
            let addressType = 'shipping'; // Sicherer Default, wird von der Logik überschrieben
            let billingContext = false;
        
            // Priorität 1: Explizite DOM-Kontext-Attribute
            // Hier wird direkt auf das Element zugegriffen, das die Funktion aufgerufen hat oder ein Elternelement davon.
            const containerContext = $(event.target).closest('[data-address-type]').attr('data-address-type');
            if (containerContext === 'billing') {
                addressType = 'billing';
                billingContext = true;
            }
        
            // Priorität 2: URL-Parameter (nur wenn DOM-Kontext fehlt)
            if (!billingContext && window.location.href.includes('step=billing')) {
                addressType = 'billing';
                billingContext = true;
            }
        
            // Priorität 3: Step-Status (nur wenn andere Methoden fehlschlagen)
            if (!billingContext) {
                const billingStepActive = $('#step-2-5').hasClass('active') || $('.checkout-step[data-step="billing"]').hasClass('active');
                if (billingStepActive) {
                    addressType = 'billing';
                    billingContext = true;
                }
            }
        
            // Methode 4 (Fallback/Globaler Kontext): Nur anwenden, wenn der Kontext noch nicht eindeutig ist
            // Dies ist eine niedrigere Priorität, um die konsistente Erkennung nicht zu überschreiben.
            if (!billingContext && window.currentAddressContext === 'billing') {
                addressType = 'billing';
                billingContext = true; // Setzen, damit spätere Checks nicht erneut überschreiben
            }
        
            console.log('🎯 Address Manager Context erkannt:', addressType);
            console.log('🔍 [DEBUG-AM] ========================================');
            console.log('🔍 [DEBUG-AM] selectAddress() called with:', {
                addressId: addressId,
                addressType: addressType,
                timestamp: new Date().toISOString(),
                url: window.location.href,
                callStack: new Error().stack.split('\n').slice(1, 4)
            });
        
            // AJAX-Aufruf, um die Adresse zu speichern
            $.ajax({
                url: yprint_address_ajax.ajax_url, // Annahme: Deine AJAX-URL ist hier definiert
                method: 'POST',
                data: {
                    action: 'yprint_set_checkout_address',
                    nonce: yprint_address_ajax.nonce,
                    address_id: addressId,
                    address_type: addressType,
                    billing_context: billingContext ? 'true' : 'false', // Explizite Kontext-Info
                    step: billingContext ? 'billing' : 'shipping' // Zusätzlicher Fallback-Parameter
                },
                success: function(response) {
                    if (response.success) {
                        console.log('Adresse erfolgreich ausgewählt und gespeichert:', response.data);
                        // Optional: Weiterleitung oder DOM-Update basierend auf der Antwort
                        if (response.data.redirect_url) {
                            window.location.href = response.data.redirect_url;
                        }
                    } else {
                        console.error('Fehler beim Auswählen der Adresse:', response.data.message);
                        alert('Es gab einen Fehler: ' + response.data.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Fehler:', status, error);
                    alert('Ein unerwarteter Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
                },
                complete: function() {
                    // Originaltext des Buttons wiederherstellen
                    btnSelectAddress.html(originalBtnText);
                }
            });
        
        
            // Adresse für Checkout setzen und Formular füllen
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_set_checkout_address',
                    nonce: yprint_address_ajax.nonce,
                    address_id: addressId,
                    address_type: addressType, // WICHTIG: Kontext mitschicken
                    debug_context: {
                        url: window.location.href,
                        referrer: document.referrer,
                        timestamp: new Date().toISOString()
                    }
                },
                beforeSend: function(xhr, settings) {
                    console.log('🚀 [DEBUG-AM] AJAX Request wird gesendet:', {
                        action: 'yprint_set_checkout_address',
                        address_id: addressId,
                        address_type: addressType,
                        timestamp: new Date().toISOString()
                    });
                },
                success: function(response) {
                    console.log('🚀 [DEBUG-AM] AJAX Response erhalten:', response);
        
                    if (response.success) {
                        console.log('✅ Address erfolgreich gesetzt als:', response.data.address_type || addressType);
        
                        // Session State Check nach 100ms
                        setTimeout(() => {
                            if (typeof yprint_address_ajax !== 'undefined') {
                                $.ajax({
                                    url: yprint_address_ajax.ajax_url,
                                    type: 'POST',
                                    data: {
                                        action: 'yprint_debug_session_state',
                                        nonce: yprint_address_ajax.nonce
                                    },
                                    success: function(debugResponse) {
                                        console.log('🔍 [DEBUG-AM] Session nach Address Manager AJAX:', debugResponse.data);
                                    }
                                });
                            }
                        }, 100);
        
                        self.fillAddressForm(response.data.address_data);
        
                        // KRITISCHER FIX: Express Payment nur bei shipping addresses updaten
                        if (addressType === 'shipping') {
                            console.log('Address Manager: Address data for Express Payment:', response.data.address_data);
        
                            // Aktualisiere Express Payment mit neuer Adresse
                            if (window.YPrintExpressCheckout && window.YPrintExpressCheckout.updateAddress) {
                                console.log('Address Manager: Calling Express Payment updateAddress...');
                                window.YPrintExpressCheckout.updateAddress(response.data.address_data);
                            } else {
                                console.warn('Address Manager: YPrintExpressCheckout not available for address update');
                            }
                        } else {
                            console.log('Address Manager: Billing address selected - SKIPPING Express Payment update');
                        }
        
                        self.showMessage('Adresse ausgewählt und für Checkout gesetzt.');
        
                        // Kontext-spezifisches Event triggern
                        if (addressType === 'billing') {
                            $(document).trigger('billing_address_selected', [addressId, response.data.address_data]);
                        } else {
                            $(document).trigger('address_selected', [addressId, response.data.address_data]);
                        }
        
                        self.closeAddressSelectionView();
        
                        // Wichtige Änderung: Wir simulieren einen Klick auf den "Weiter zur Zahlung"-Button
                        // nach kurzer Verzögerung, damit der Benutzer die Erfolgsmeldung noch sehen kann
                        setTimeout(function() {
                            // Prüfen ob Formular gültig ist und Button nicht deaktiviert
                            const toPaymentBtn = $('#btn-to-payment');
                            console.log('Suche nach btn-to-payment:', toPaymentBtn.length, 'gefunden');
        
                            if (toPaymentBtn.length > 0) {
                                // Aktiviere den Button falls er deaktiviert ist
                                if (toPaymentBtn.prop('disabled')) {
                                    console.log('Button war deaktiviert, wird aktiviert');
                                    toPaymentBtn.prop('disabled', false);
                                }
        
                                console.log('Klicke auf "Weiter zur Zahlung"-Button');
                                toPaymentBtn.trigger('click');
                            } else {
                                // Alternativer Ansatz: Direkt zum nächsten Schritt springen
                                console.log('Button nicht gefunden, versuche direkten Schrittwechsel');
                                if (window.showStep && typeof window.showStep === 'function') {
                                    window.showStep(2); // Direkt zu Schritt 2 (Zahlung) wechseln
                                } else if (typeof showStep === 'function') {
                                    showStep(2);
                                } else {
                                    console.error('Weder Button noch showStep-Funktion gefunden');
                                }
                            }
                        }, 1000); // 1 Sekunde Verzögerung
                    } else {
                        self.showMessage(response.data.message || 'Fehler beim Setzen der Adresse', 'error');
                        btnSelectAddress.html(originalBtnText);
                    }
                },
                error: function() {
                    self.showMessage('Fehler beim Setzen der Adresse für Checkout', 'error');
                    btnSelectAddress.html(originalBtnText);
                },
                complete: function() {
                    // Loading-Anzeige zurücksetzen (kann auch in success/error, aber hier für alle Fälle)
                    btnSelectAddress.html(originalBtnText);
                }
            });
        },
        
        closeAddressSelectionView: function() {
            this.addressContainer.slideUp();
            $('#address-form').slideDown();
            this.showChangeAddressLink();
            // Validierung des Hauptformulars triggern
            $('#address-form input').trigger('input');
        },
        
        fillAddressForm: function(address) {
            // Standard Checkout-Formular füllen
            $('#street').val(address.address_1 || '');
            $('#housenumber').val(address.address_2 || '');
            $('#zip').val(address.postcode || '');
            $('#city').val(address.city || '');
            $('#country').val(address.country || 'DE');
            $('#phone').val(address.phone || '');
            
            // Trigger change events für Validierung
            $('#street, #housenumber, #zip, #city, #country').trigger('input');
            
            // Adressauswahl-Container verstecken nach Auswahl
            $('.yprint-saved-addresses').slideUp();
            
            // "Andere Adresse wählen" Link anzeigen!
            this.showChangeAddressLink();
        },
        
        showChangeAddressLink: function() {
            const existingLink = $('.change-address-link');
            if (existingLink.length > 0) return;
            
            const link = $(`
                <div class="change-address-link mt-3">
                    <button type="button" class="text-yprint-blue hover:underline">
                        <i class="fas fa-edit mr-1"></i>
                        Andere Adresse wählen
                    </button>
                </div>
            `);
            
            const self = this;
            link.on('click', function() {
                $('.yprint-saved-addresses').slideDown();
                link.remove();
            });
            
            $('#step-1 .space-y-6').prepend(link);
        },
        
        openAddressModal: function(addressId = null, addressData = null) {
            const self = this;
            
            console.log('openAddressModal (ID Handling): Called with addressId:', addressId);
            if (addressData) {
                console.log('openAddressModal (ID Handling): Address data:', JSON.stringify(addressData));
            }
            
            // Stelle sicher, dass das Modal im Body verfügbar ist
            if (self.modal.length === 0) {
                self.modal = $('#new-address-modal');
                if (self.modal.length === 0) {
                    console.error('Modal not found in DOM. Creating fallback modal.');
                    self.createFallbackModal();
                }
            }
            
            // Setze Kontext basierend auf aktueller Situation
            const currentContext = window.currentAddressContext || 'shipping';
            self.modal.attr('data-context', currentContext);
            console.log('Modal context set to:', currentContext);
            
            // Modal-spezifische Event-Bindung als zusätzliche Sicherheit
            setTimeout(() => {
                const modalSaveBtn = self.modal.find('.btn-save-address');
                if (modalSaveBtn.length) {
                    console.log('🔧 Binde Modal-spezifischen Save-Handler');
                    modalSaveBtn.off('click.modal').on('click.modal', function(e) {
                        console.log('🎯 Modal Save-Handler ausgelöst!');
                        e.preventDefault();
                        e.stopPropagation();
                        self.saveNewAddress();
                        self.triggerSaveNewAddress();
                    });
                }
            }, 100);
            
            // Formular zurücksetzen
            $('#new-address-form')[0].reset();
            $('.address-form-errors').hide();
            
            // Modal-Titel anpassen
            const modalTitle = document.querySelector('.address-modal-header h3');
            if (modalTitle) {
                modalTitle.textContent = addressId ? 'Adresse bearbeiten' : 'Neue Adresse hinzufügen';
            }
            
            // Button-Text anpassen
            const saveButton = document.querySelector('.btn-save-address');
            if (saveButton) {
                saveButton.innerHTML = addressId ? 
                    '<i class="fas fa-save mr-2"></i>Adresse aktualisieren' : 
                    '<i class="fas fa-save mr-2"></i>Adresse speichern';
            }
            
            // Wichtig: Sicherstellen, dass bestehende editing-address-id entfernt wird
            self.modal.removeData('editing-address-id');
            console.log('openAddressModal (ID Handling): Removed existing editing-address-id');
            
            // Explizites Verstecken des hidden input-Feldes für die ID
            $('#new_address_edit_id').val('');
            
            // Wenn Adressdaten übergeben wurden (Bearbeiten-Modus)
            if (addressId && addressData) {
                // Speichere die ID für später - WICHTIG: Hier wird die ID im modal-Objekt gespeichert
                self.modal.data('editing-address-id', addressId);
                console.log('openAddressModal (ID Handling): Set editing-address-id to:', addressId);
                
                // Speichere die ID auch in einem versteckten Input-Feld im Formular als Backup
                $('#new_address_edit_id').val(addressId);
                
                // Felder im Modal füllen
                $('#new_address_name').val(addressData.name || '');
                $('#new_address_first_name').val(addressData.first_name || '');
                $('#new_last_name').val(addressData.last_name || '');
                $('#new_address_1').val(addressData.address_1 || '');
                $('#new_address_2').val(addressData.address_2 || '');
                $('#new_postcode').val(addressData.postcode || '');
                $('#new_city').val(addressData.city || '');
                $('#new_country').val(addressData.country || 'DE');
                
                // Firma-Checkbox und -Feld setzen
                const isCompany = !!addressData.company;
                $('#new_is_company').prop('checked', isCompany);
                $('#new_company').val(addressData.company || '');
                $('#new_company_field').toggle(isCompany);
        
                console.log('openAddressModal (ID Handling): Modal fields populated with address data');
                
                // Double-check ob die ID korrekt gesetzt wurde
                console.log('openAddressModal (ID Handling): Checking if ID was properly set:', self.modal.data('editing-address-id'));
                console.log('openAddressModal (ID Handling): Checking if ID in hidden input:', $('#new_address_edit_id').val());
            } else {
                console.log('openAddressModal (ID Handling): Opening modal for new address (no ID)');
            }
            
            // WICHTIG: CSS und Sichtbarkeit sicherstellen
self.modal.css({
    'display': 'block',
    'visibility': 'visible',
    'opacity': '1'
}).show().addClass('active');

// Body Scroll sperren
$('body').css('overflow', 'hidden');

// Debug: Erweiterte Sichtbarkeits-Prüfung
console.log('Modal visibility check:', {
    display: self.modal.css('display'),
    visibility: self.modal.css('visibility'),
    opacity: self.modal.css('opacity'),
    visible: self.modal.is(':visible'),
    hasActive: self.modal.hasClass('active'),
    zIndex: self.modal.css('z-index'),
    position: self.modal.css('position')
});

// Fallback: Modal direkt in den Viewport bringen
setTimeout(() => {
    // DOM-Diagnose erweitern
    console.log('🔍 DOM-Diagnose:', {
        modalExists: self.modal.length,
        modalInDOM: document.contains(self.modal[0]),
        modalHTML: self.modal[0] ? self.modal[0].outerHTML.substring(0, 200) + '...' : 'NICHT GEFUNDEN',
        modalParent: self.modal.parent().length,
        modalOffset: self.modal.offset(),
        modalDimensions: {
            width: self.modal.width(),
            height: self.modal.height()
        }
    });
    
    if (!self.modal.is(':visible')) {
        console.warn('Modal still not visible, applying fallback styles');
        
        // Problem: Negative Dimensionen - fixe Modal-Content Styles
        console.log('🛠️ Fixe Modal-Content Dimensionen...');
        
        // Modal Container forcieren
        self.modal.attr('style', 'display: block !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; z-index: 99999 !important; background: rgba(0,0,0,0.5) !important;');
        
        // Modal Content forcieren
        const modalContent = self.modal.find('.address-modal-content');
        modalContent.attr('style', 'position: absolute !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; background: white !important; border-radius: 12px !important; padding: 20px !important; max-width: 500px !important; width: 400px !important; min-height: 300px !important; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important; z-index: 100000 !important;');
        
        // Modal Header forcieren
        const modalHeader = self.modal.find('.address-modal-header');
        modalHeader.attr('style', 'padding: 20px 20px 10px 20px !important; border-bottom: 1px solid #eee !important; margin-bottom: 15px !important;');
        
        // Modal Body forcieren
        const modalBody = self.modal.find('.address-modal-body');
        modalBody.attr('style', 'padding: 0 20px !important; max-height: 400px !important; overflow-y: auto !important;');
        
        // Modal Footer forcieren
        const modalFooter = self.modal.find('.address-modal-footer');
        modalFooter.attr('style', 'padding: 15px 20px 20px 20px !important; border-top: 1px solid #eee !important; display: flex !important; gap: 10px !important; justify-content: flex-end !important;');
        
        console.log('✅ Modal-Content Styles forciert, prüfe Dimensionen erneut:', {
            modalWidth: self.modal.width(),
            modalHeight: self.modal.height(),
            contentWidth: modalContent.width(),
            contentHeight: modalContent.height()
        });
    }
}, 100);
            
            $('body').css('overflow', 'hidden');
        },
        
        closeAddressModal: function() {
            this.modal.removeClass('active');
            // WICHTIG: Diese Zeile stellt sicher, dass das Modal explizit wieder ausgeblendet wird.
            this.modal.css('display', 'none'); 
            $('body').css('overflow', 'auto');
        },
        
        validateForm: function() {
            console.log('🔍 validateForm: Starte Validierung');
            const form = $('#new-address-form');
            
            if (form.length === 0) {
                console.error('❌ validateForm: Formular #new-address-form nicht gefunden!');
                return false;
            }
            
            const requiredFields = form.find('input[required], select[required]');
            console.log('🔍 validateForm: Gefundene Required-Felder:', requiredFields.length);
            
            let isValid = true;
            let invalidFields = [];
            
            requiredFields.each(function() {
                const $field = $(this);
                const value = $field.val() ? $field.val().trim() : '';
                const fieldId = $field.attr('id') || $field.attr('name') || 'unbekannt';
                
                console.log(`🔍 validateForm: Prüfe Feld ${fieldId}: "${value}"`);
                
                if (!value) {
                    isValid = false;
                    invalidFields.push(fieldId);
                    $field.addClass('border-red-500'); // Visuelles Feedback
                } else {
                    $field.removeClass('border-red-500');
                }
            });
            
            if (!isValid) {
                console.warn('❌ validateForm: Ungültige Felder:', invalidFields);
                this.showFormError(`Bitte füllen Sie folgende Pflichtfelder aus: ${invalidFields.join(', ')}`);
            } else {
                console.log('✅ validateForm: Alle Felder gültig');
                $('.address-form-errors').hide();
            }
            
            $('.btn-save-address').prop('disabled', !isValid);
            return isValid;
        },

        createFallbackModal: function() {
            console.log('🛠️ Erstelle Fallback-Modal...');
            
            const fallbackModalHTML = `
                <div id="new-address-modal" class="address-modal active" style="display: block !important; position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; z-index: 99999 !important; background: rgba(0,0,0,0.5);">
                    <div class="address-modal-overlay"></div>
                    <div class="address-modal-content" style="position: absolute !important; top: 50% !important; left: 50% !important; transform: translate(-50%, -50%) !important; background: white !important; border-radius: 12px !important; padding: 20px !important; max-width: 500px !important; width: 90% !important; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3) !important;">
                        <div class="address-modal-header">
                            <h3>Neue Rechnungsadresse hinzufügen</h3>
                            <button type="button" class="btn-close-modal" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999;">&times;</button>
                        </div>
                        <div class="address-modal-body">
                            <form id="new-address-form" class="space-y-4">
                                <div>
                                    <label for="new_address_name" class="form-label">Name der Adresse</label>
                                    <input type="text" id="new_address_name" name="name" class="form-input" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="new_address_first_name" class="form-label">Vorname</label>
                                        <input type="text" id="new_address_first_name" name="first_name" class="form-input" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                    </div>
                                    <div>
                                        <label for="new_last_name" class="form-label">Nachname</label>
                                        <input type="text" id="new_last_name" name="last_name" class="form-input" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                    </div>
                                </div>
                                <div>
                                    <label for="new_address_1" class="form-label">Straße und Hausnummer</label>
                                    <input type="text" id="new_address_1" name="address_1" class="form-input" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                </div>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="new_postcode" class="form-label">PLZ</label>
                                        <input type="text" id="new_postcode" name="postcode" class="form-input" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                    </div>
                                    <div>
                                        <label for="new_city" class="form-label">Stadt</label>
                                        <input type="text" id="new_city" name="city" class="form-input" required style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                    </div>
                                </div>
                                <div>
                                    <label for="new_country" class="form-label">Land</label>
                                    <select id="new_country" name="country" class="form-select" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                                        <option value="DE">Deutschland</option>
                                        <option value="AT">Österreich</option>
                                        <option value="CH">Schweiz</option>
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="address-modal-footer">
                            <button type="button" class="btn btn-secondary btn-cancel-address" style="padding: 10px 20px; margin-right: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px; cursor: pointer;">Abbrechen</button>
                            <button type="button" class="btn btn-primary btn-save-address" style="padding: 10px 20px; background: #0079FF; color: white; border: none; border-radius: 5px; cursor: pointer;">Adresse speichern</button>
                        </div>
                    </div>
                </div>
            `;
            
            // Entferne altes Modal falls vorhanden
            $('#new-address-modal').remove();
            
            // Füge neues Modal zum Body hinzu
            $('body').append(fallbackModalHTML);
            
            // Modal-Referenz aktualisieren
            this.modal = $('#new-address-modal');
            
            // Body Scroll sperren
            $('body').css('overflow', 'hidden');
            
            console.log('✅ Fallback-Modal erstellt und angezeigt');
        },

        // NEU: Funktion zum Anzeigen des "Neue Adresse hinzufügen"-Formulars
openNewAddressForm: function() {
    console.log('YPrint Debug: Opening new address form');
    this.elements.addressContainer.hide(); // Gespeicherte Adressen ausblenden
    
    // Adressfelder anzeigen
    if (this.elements.shippingFieldsContainer.length > 0) {
        this.elements.shippingFieldsContainer.show();
    }
    
    // Rechnungsadressfelder nur anzeigen, wenn sie nicht durch die "Rechnungsadresse identisch" Checkbox versteckt werden
    const isBillingSameAsShipping = $('#billing-same-as-shipping').is(':checked');
    if (this.elements.billingFieldsContainer.length > 0 && !isBillingSameAsShipping) {
        this.elements.billingFieldsContainer.show();
    }
    
    // Speichern-Toggle anzeigen
    if (this.elements.saveAddressToggle.length > 0) {
        this.elements.saveAddressToggle.closest('.form-row').show();
    }
    
    // Fokus auf das erste Feld der neuen Adresse setzen (optional für UX)
    if (this.elements.shippingFieldsContainer.length > 0) {
        this.elements.shippingFieldsContainer.find('input[type="text"]:first').focus();
    }
},

// NEU: Methode, um den Status des "Adresse speichern"-Schalters abzufragen
shouldSaveNewAddress: function() {
    return this.elements.saveAddressToggle.length > 0 && this.elements.saveAddressToggle.is(':checked');
},
    
// Neuer Code (Ersatz) - Fortsetzung
saveNewAddress: function() {
    const self = this;
    const form = $('#new-address-form');
    
    if (!this.validateForm()) {
        this.showFormError('Bitte füllen Sie alle Pflichtfelder aus.');
        return;
    }
    
    // Prüfen, ob wir im Bearbeitungs-Modus sind - ID aus beiden möglichen Quellen holen
    let addressId = self.modal.data('editing-address-id');
    
    // Falls die jQuery data() Methode nichts zurückgibt, versuche es mit dem Hidden Input
    if (!addressId) {
        addressId = $('#new_address_edit_id').val();
    }
    
    const isEditing = !!addressId; // true, wenn addressId einen Wert hat; false sonst
    
    console.log('saveNewAddress (ID Handling): isEditing:', isEditing, 'addressId:', addressId);
    console.log('saveNewAddress (ID Handling): addressId from data():', self.modal.data('editing-address-id'));
    console.log('saveNewAddress (ID Handling): addressId from hidden input:', $('#new_address_edit_id').val());
    
    const formData = {
        action: 'yprint_save_address',
        yprint_address_nonce: yprint_address_ajax.nonce, // WICHTIG: Ursprünglicher Nonce-Name
        name: $('#new_address_name').val() || ('Adresse vom ' + new Date().toLocaleDateString('de-DE')),
        first_name: $('#new_address_first_name').val(),
        last_name: $('#new_last_name').val(),
        company: $('#new_company').val(),
        address_1: $('#new_address_1').val(),
        address_2: $('#new_address_2').val(),
        postcode: $('#new_postcode').val(),
        city: $('#new_city').val(),
        country: $('#new_country').val(),
        is_company: $('#new_is_company').is(':checked')
    };
    
    // Wenn wir eine bestehende Adresse bearbeiten, füge die ID hinzu.
    if (isEditing) {
        formData.id = addressId;
        console.log('saveNewAddress (ID Handling): Adding ID to formData:', addressId);
    }
    
    console.log('saveNewAddress (ID Handling): formData being sent:', formData);
    
    // Loading state
    const saveButton = $('.btn-save-address');
    const originalText = saveButton.html();
    saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Speichere...');
    
    $.ajax({
        url: yprint_address_ajax.ajax_url,
        type: 'POST',
        data: formData,
        success: function(response) {
            console.log('saveNewAddress (ID Handling): AJAX response:', response);
            if (response.success) {
                self.closeAddressModal(); // Schließt das Modal bei Erfolg
                self.loadSavedAddresses(); // Lädt die Adressen neu, um Änderungen anzuzeigen
                self.showMessage(isEditing ? 'Adresse erfolgreich aktualisiert!' : 'Adresse erfolgreich gespeichert!', 'success');
            } else {
                self.showFormError(response.data.message || 'Fehler beim Speichern');
            }
        },
        error: function(xhr, status, error) {
            console.error('saveNewAddress (ID Handling): AJAX error:', {xhr: xhr, status: status, error: error});
            self.showFormError('Fehler beim Speichern der Adresse');
        },
        complete: function() {
            saveButton.prop('disabled', false).html(originalText);
        }
    });
},

/**
 * Speichert eine Rechnungsadresse aus dem Billing-Formular
 */
saveBillingAddressFromForm: function() {
    const self = this;
    const saveButton = $('#save-billing-address-button');
    const feedbackElement = $('#save-billing-address-feedback');

    // Feldmapping für Billing-Formular
    const fieldMapping = {
        first_name: 'billing_first_name',
        last_name: 'billing_last_name',
        email: 'billing_email',
        street: 'billing_street',
        housenumber: 'billing_housenumber',
        zip: 'billing_zip',
        city: 'billing_city',
        country: 'billing_country',
        phone: 'billing_phone'
    };

    // Validierung der Pflichtfelder
    const requiredFields = ['first_name', 'last_name', 'email', 'street', 'housenumber', 'zip', 'city', 'country'];
    let isValid = true;
    let missingFields = [];

    requiredFields.forEach(field => {
        const fieldId = fieldMapping[field];
        const value = $('#' + fieldId).val();
        if (!value || !value.trim()) {
            isValid = false;
            missingFields.push(field);
            $('#' + fieldId).addClass('border-yprint-error');
        } else {
            $('#' + fieldId).removeClass('border-yprint-error');
        }
    });

    if (!isValid) {
        const errorMessage = 'Bitte füllen Sie alle Pflichtfelder aus: ' + missingFields.join(', ');
        feedbackElement.removeClass('hidden text-yprint-success').addClass('text-yprint-error').html(errorMessage);
        return;
    }
    
    // Sammle die Daten aus den Formularfeldern
    const addressData = {
        name: 'Rechnungsadresse vom ' + new Date().toLocaleDateString('de-DE'),
        first_name: $('#' + fieldMapping.first_name).val(),
        last_name: $('#' + fieldMapping.last_name).val(),
        company: '', // Kann später erweitert werden
        address_1: $('#' + fieldMapping.street).val(),
        address_2: $('#' + fieldMapping.housenumber).val(),
        postcode: $('#' + fieldMapping.zip).val(),
        city: $('#' + fieldMapping.city).val(),
        country: $('#' + fieldMapping.country).val() || 'DE',
        phone: $('#' + fieldMapping.phone).val() || '',
        email: $('#' + fieldMapping.email).val(),
        address_type: 'billing'
    };
    
    // Button und Feedback aktualisieren
    saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Speichern...');
    feedbackElement.removeClass('text-yprint-success text-yprint-error').addClass('text-yprint-text-secondary').html('Rechnungsadresse wird gespeichert...').removeClass('hidden');
    
    // AJAX-Request zum Speichern
    $.ajax({
        url: yprint_address_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'yprint_save_billing_address',
            nonce: yprint_address_ajax.nonce,
            address_data: addressData
        },
        success: function(response) {
            if (response.success) {
                feedbackElement.removeClass('text-yprint-text-secondary').addClass('text-yprint-success').html('<i class="fas fa-check-circle mr-1"></i>Rechnungsadresse erfolgreich gespeichert.');
                
                // Formular zurücksetzen und ausblenden
                $('#billing-address-form')[0].reset();
                self.showAddressForm(false);
                
                // Gespeicherte Adressen neu laden
                setTimeout(() => {
                    self.loadSavedAddresses('billing');
                }, 500);
            } else {
                feedbackElement.removeClass('text-yprint-text-secondary').addClass('text-yprint-error').html('<i class="fas fa-exclamation-circle mr-1"></i>' + (response.data.message || 'Fehler beim Speichern der Rechnungsadresse.'));
            }
        },
        error: function(xhr, status, error) {
            console.error('Error saving billing address:', error);
            feedbackElement.removeClass('text-yprint-text-secondary').addClass('text-yprint-error').html('<i class="fas fa-exclamation-circle mr-1"></i>Ein Fehler ist aufgetreten.');
        },
        complete: function() {
            saveButton.prop('disabled', false).html('<i class="fas fa-save mr-2"></i>Rechnungsadresse speichern');
            
            // Blende das Feedback nach 5 Sekunden aus
            setTimeout(function() {
                feedbackElement.fadeOut(function() {
                    $(this).addClass('hidden').css('display', '');
                });
            }, 5000);
        }
    });
},
        
        deleteAddress: function(addressId) {
            const self = this;
            
            if (!confirm(yprint_address_ajax.messages.confirm_delete || 'Adresse wirklich löschen?')) {
                return;
            }
            
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_delete_address',
                    nonce: yprint_address_ajax.nonce,
                    address_id: addressId
                },
                success: function(response) {
                    if (response.success) {
                        self.loadSavedAddresses();
                        self.showMessage(response.data.message || 'Adresse gelöscht', 'success');
                    } else {
                        self.showMessage(response.data.message || 'Fehler beim Löschen', 'error');
                    }
                },
                error: function() {
                    self.showMessage('Fehler beim Löschen der Adresse', 'error');
                }
            });
        },
        
        setDefaultAddress: function(addressId) {
            const self = this;
            
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_set_default_address',
                    nonce: yprint_address_ajax.nonce,
                    address_id: addressId
                },
                success: function(response) {
                    if (response.success) {
                        self.loadSavedAddresses();
                        self.showMessage(response.data.message || 'Standard-Adresse gesetzt', 'success');
                    } else {
                        self.showMessage(response.data.message || 'Fehler beim Setzen', 'error');
                    }
                },
                error: function() {
                    self.showMessage('Fehler beim Setzen der Standard-Adresse', 'error');
                }
            });
        },
        
        showMessage: function(message, type) {
            const messageEl = $(`
                <div class="address-message ${type}">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'}"></i>
                    ${message}
                </div>
            `);
            
            // Existing messages entfernen
            $('.address-message').remove();
            
            // Message einfügen
            $('.yprint-saved-addresses').before(messageEl);
            
            // Nach 5 Sekunden automatisch verstecken
            setTimeout(() => {
                messageEl.fadeOut(() => messageEl.remove());
            }, 5000);
        },

        showAddressForm: function(show) {
            // Bestimme den aktuellen Kontext
            const currentContext = window.currentAddressContext || 'shipping';
            let targetForm;
            
            if (currentContext === 'billing') {
                targetForm = $('#billing-address-form');
                const infoSection = $('#billing-info-section');
                
                if (show) {
                    targetForm.removeClass('hidden').show();
                    infoSection.hide();
                } else {
                    targetForm.addClass('hidden').hide();
                    infoSection.show();
                }
            } else {
                // Standard Address-Step Verhalten
                targetForm = $('#address-form');
                
                if (show) {
                    targetForm.removeClass('hidden').show();
                } else {
                    targetForm.addClass('hidden').hide();
                }
            }
        },
        
        showSavedAddressesContainer: function(show) {
            if (show) {
                this.addressContainer.removeClass('hidden').show();
            } else {
                this.addressContainer.addClass('hidden').hide();
            }
        },
        
        showChangeAddressLink: function() {
            // Entferne bestehenden Link falls vorhanden
            $('.change-address-link').remove();
            
            // Neuen Link erstellen
            const link = $(`
                <div class="change-address-link mt-3">
                    <button type="button" class="text-yprint-blue hover:underline">
                        <i class="fas fa-edit mr-1"></i>
                        Andere Adresse wählen
                    </button>
                </div>
            `);
            
            // Link einfügen
            $('#step-1 .space-y-6').prepend(link);
        },

        // Debug-Funktion für manuelle Validierungstests
debugValidation: function() {
    console.log('🔬 === VALIDIERUNGS-DEBUG ===');
    
    const form = $('#new-address-form');
    console.log('Form gefunden:', form.length > 0);
    
    if (form.length === 0) {
        console.error('❌ Formular nicht gefunden!');
        return;
    }
    
    console.log('📋 Alle Formularfelder:');
    form.find('input, select, textarea').each(function(index) {
        const $field = $(this);
        console.log(`${index + 1}. ${$field.attr('id') || $field.attr('name') || 'unbekannt'}:`, {
            value: $field.val(),
            required: $field.attr('required') !== undefined,
            visible: $field.is(':visible'),
            type: $field.attr('type') || $field.prop('tagName')
        });
    });
    
    console.log('🎯 Required Felder:');
    const requiredFields = form.find('input[required], select[required]');
    console.log('Anzahl Required Felder:', requiredFields.length);
    
    let validationResult = this.validateForm();
    console.log('🔍 Validierungsergebnis:', validationResult);
    
    console.log('🔬 === ENDE VALIDIERUNGS-DEBUG ===');
    return validationResult;
},
        
        showFormError: function(message) {
            console.log('🚨 showFormError: Zeige Fehler an:', message);
            let errorEl = $('.address-form-errors');
            
            // Falls Error-Element nicht existiert, erstelle es
            if (errorEl.length === 0) {
                console.log('🔧 showFormError: Error-Element nicht gefunden, erstelle neues');
                errorEl = $('<div class="address-form-errors bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded mb-4"></div>');
                $('#new-address-form').prepend(errorEl);
            }
            
            errorEl.html(`
                <div class="flex items-center">
                    <i class="fas fa-exclamation-circle mr-2"></i>
                    <span>${message}</span>
                </div>
            `).show();
            
            // Auto-hide nach 8 Sekunden
            setTimeout(() => {
                errorEl.fadeOut();
            }, 8000);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        addressManager.init();
    });
    
    // Global access for debugging
    window.YPrintAddressManager = addressManager;


    
})(jQuery);