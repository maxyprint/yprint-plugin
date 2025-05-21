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
        
        init: function() {
            console.log('=== YPrint Address Manager: Starting Initialization ===');
            console.log('Current URL:', window.location.href);
            console.log('User Agent:', navigator.userAgent);
            
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
            
            // Check for required dependencies
            if (typeof yprint_address_ajax === 'undefined') {
                console.error('ERROR: yprint_address_ajax object not found! Check wp_localize_script.');
                return;
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
                self.openAddressModal(); // Öffnet das Modal für eine neue Adresse
                self.showAddressForm(true); // Zeigt das Adressformular
                self.showSavedAddressesContainer(false); // Versteckt die gespeicherten Adressen
            });
        
            // Adresse auswählen
            $(document).on('click', '.btn-select-address', function(e) {
                e.preventDefault();
                const addressCard = $(this).closest('.address-card');
                const addressId = addressCard.data('address-id');
                self.selectAddress(addressId);
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
            
            // NEU: Event für das Bearbeiten einer Adresse
            $(document).on('click', '.btn-edit-address', function(e) {
                console.log('Bearbeiten button clicked! Attempting to open modal.'); // Debugging-Ausgabe
                e.preventDefault();
                const addressCard = $(this).closest('.address-card');
                const addressId = addressCard.data('address-id');
                // Adressdaten aus dem data-Attribut abrufen
                try {
                    const addressDataStr = addressCard.data('address-data');
                    const addressData = JSON.parse(decodeURIComponent(addressDataStr));
                    self.openAddressModal(addressId, addressData);
                } catch (error) {
                    console.error('Error parsing address data from card:', error);
                    self.showMessage('Fehler beim Laden der Adresse', 'error');
                }
            });
        
            // Modal schließen
            $(document).on('click', '.btn-close-modal, .address-modal-overlay', function() {
                self.closeAddressModal();
            });
        
            // Modal-Buttons
            $(document).on('click', '.btn-cancel-address', function() {
                self.closeAddressModal();
            });
        
            $(document).on('click', '.btn-save-address', function() {
                self.saveNewAddress();
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

    // Prüfe, ob die erforderlichen Felder vorhanden sind
    const requiredFields = ['first_name', 'last_name', 'street', 'housenumber', 'zip', 'city', 'country'];
    let isValid = true;
    let missingFields = [];

    requiredFields.forEach(field => {
        const value = $('#' + field).val();
        if (!value || !value.trim()) {
            isValid = false;
            missingFields.push(field);
            $('#' + field).addClass('border-yprint-error');
        } else {
            $('#' + field).removeClass('border-yprint-error');
        }
    });

    if (!isValid) {
        const errorMessage = 'Bitte füllen Sie alle Pflichtfelder aus: ' + missingFields.join(', ');
        feedbackElement.removeClass('hidden text-yprint-success').addClass('text-yprint-error').html(errorMessage);
        return;
    }
    
    // Sammle die Daten aus den Formularfeldern
    const addressData = {
        name: 'Adresse vom ' + new Date().toLocaleDateString('de-DE'),
        first_name: $('#first_name').val(),
        last_name: $('#last_name').val(),
        company: $('#company').val() || '',
        address_1: $('#street').val(),
        address_2: $('#housenumber').val(),
        postcode: $('#zip').val(),
        city: $('#city').val(),
        country: $('#country').val() || 'DE',
        phone: $('#phone').val() || ''
    };
    
    // Aktualisiere Button und zeige Feedback
    saveButton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i>Speichern...');
    feedbackElement.removeClass('text-yprint-success text-yprint-error').addClass('text-yprint-text-secondary').html('Adresse wird gespeichert...').removeClass('hidden');
    
    // AJAX-Request zum Speichern
    $.ajax({
        url: yprint_address_ajax.ajax_url,
        type: 'POST',
        data: {
            action: 'yprint_save_checkout_address',
            nonce: yprint_address_ajax.nonce,
            address_data: addressData
        },
        success: function(response) {
            if (response.success) {
                feedbackElement.removeClass('text-yprint-text-secondary').addClass('text-yprint-success').html('<i class="fas fa-check-circle mr-1"></i>' + (response.data.message || 'Adresse erfolgreich gespeichert.'));
                
                // Optional: Lade gespeicherte Adressen neu
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
        
        isUserLoggedIn: function() {
            // Prüfe ob User eingeloggt ist
            return $('body').hasClass('logged-in') || $('#wpadminbar').length > 0;
        },
        
        loadSavedAddresses: function() {
            const self = this;
            
            console.log('=== Loading Saved Addresses ===');
            
            // Container-Status vor AJAX-Call
            self.loadingIndicator.show();
            self.addressContainer.find('.address-cards-grid').hide();
            self.addressContainer.show();
            
            const ajaxData = {
                action: 'yprint_get_saved_addresses',
                nonce: yprint_address_ajax.nonce
            };
            
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                success: function(response) {
                    if (response && response.success) {
                        const addresses = response.data.addresses || {};
                        const addressCount = Object.keys(addresses).length;
                        console.log('Success: Address data received, count:', addressCount);
                        
                        // Adressen rendern
                        self.renderAddresses(addresses);
                        
                        if (addressCount === 0) {
                            // Keine gespeicherten Adressen
                            console.log('No addresses found - hiding container, showing form');
                            self.showSavedAddressesContainer(false);
                            self.showAddressForm(true);
                        } else {
                            // Gespeicherte Adressen vorhanden
                            console.log('Addresses found - showing grid, hiding form');
                            self.showSavedAddressesContainer(true);
                            self.showAddressForm(false);
                            self.showChangeAddressLink();
                        }
                    } else {
                        // Fehler beim Laden
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
                    self.loadingIndicator.hide();
                }
            });
        },
        
        renderAddresses: function(addresses) {
            const self = this;
            
            // Referenz zum Container und Grid
            const container = $('.yprint-saved-addresses');
            if (container.length === 0) return;
            
            const grid = container.find('.address-cards-grid');
            
            // Bestehende Adresskarten entfernen
            grid.find('.address-card:not(.add-new-address-card)').remove();
            
            // "Neue Adresse" Kachel zuerst hinzufügen
            const addNewCard = `
                <div class="address-card add-new-address-card cursor-pointer">
                    <div class="address-card-content border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-colors hover:border-yprint-blue">
                        <i class="fas fa-plus text-3xl text-gray-400 mb-2"></i>
                        <h4 class="font-semibold text-gray-600">Neue Adresse hinzufügen</h4>
                    </div>
                </div>
            `;
            
            // Grid leeren und neue Kachel hinzufügen
            grid.html(addNewCard);
            
            // Wenn keine Adressen vorhanden sind, Funktion beenden
            if (Object.keys(addresses).length === 0) {
                console.log('No addresses found - showing add new card only');
                container.show();
                grid.show();
                return;
            }
            
            // Durch alle Adressen iterieren und Karten hinzufügen
            Object.entries(addresses).forEach(([addressId, address]) => {
                const isDefault = address.is_default || false;
                
                // Aktualisierter Standard-Badge mit Icon
                const defaultBadge = isDefault ? 
                    `<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded ml-2">
                        <i class="fas fa-star mr-1"></i>Standard
                    </span>` : '';
                
                // Adressdaten als JSON für die Bearbeitung
                const addressDataJson = encodeURIComponent(JSON.stringify(address));
                
                const card = $(`
                    <div class="address-card" data-address-id="${addressId}" data-address-data="${addressDataJson}">
                        <div class="address-card-header">
                            <div class="address-card-title">
                                ${address.name || 'Gespeicherte Adresse'}
                                ${defaultBadge}
                            </div>
                            <div class="address-card-actions">
                                ${!isDefault ? 
                                    `<button type="button" class="btn-address-action btn-set-default" title="Als Standard setzen">
                                        <i class="fas fa-star"></i>
                                    </button>` : ''}
                                <button type="button" class="btn-address-action btn-edit-address" title="Adresse bearbeiten">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button type="button" class="btn-address-action btn-delete-address" title="Adresse löschen">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                        <div class="address-card-content">
                            ${self.formatAddressDisplay(address)}
                        </div>
                        <div class="address-card-footer">
                            <button type="button" class="btn btn-primary btn-select-address">
                                <i class="fas fa-check mr-2"></i>
                                Diese Adresse verwenden
                            </button>
                        </div>
                    </div>
                `);
                
                grid.append(card);
            });
            
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
        
        createAddressCard: function(address) {
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
                <div class="address-card" data-address-id="${address.id}">
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
            
            // Adresse für Checkout setzen und Formular füllen
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_set_checkout_address',
                    nonce: yprint_address_ajax.nonce,
                    address_id: addressId
                },
                success: function(response) {
                    if (response.success) {
                        self.fillAddressForm(response.data.address_data);
                        self.showMessage('Adresse ausgewählt und für Checkout gesetzt.', 'success');
                        self.closeAddressSelectionView();
                    } else {
                        self.showMessage(response.data.message || 'Fehler beim Setzen der Adresse', 'error');
                    }
                },
                error: function() {
                    self.showMessage('Fehler beim Setzen der Adresse für Checkout', 'error');
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
            
            // "Andere Adresse wählen" Link anzeigen
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
            
            // WICHTIG: Überschreibe den inline-display-Style explizit!
            self.modal.css('display', 'block'); 
            self.modal.addClass('active');
            
            $('body').css('overflow', 'hidden');
        },
        
        closeAddressModal: function() {
            this.modal.removeClass('active');
            // WICHTIG: Diese Zeile stellt sicher, dass das Modal explizit wieder ausgeblendet wird.
            this.modal.css('display', 'none'); 
            $('body').css('overflow', 'auto');
        },
        
        validateForm: function() {
            const form = $('#new-address-form');
            const requiredFields = form.find('input[required], select[required]');
            let isValid = true;
            
            requiredFields.each(function() {
                if (!$(this).val().trim()) {
                    isValid = false;
                    return false;
                }
            });
            
            $('.btn-save-address').prop('disabled', !isValid);
            return isValid;
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
            const addressForm = $('#address-form');
            if (show) {
                addressForm.removeClass('hidden').show();
            } else {
                addressForm.addClass('hidden').hide();
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
        
        showFormError: function(message) {
            const errorEl = $('.address-form-errors');
            errorEl.html(`<ul><li>${message}</li></ul>`).show();
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        addressManager.init();
    });
    
    // Global access for debugging
    window.YPrintAddressManager = addressManager;


    
})(jQuery);