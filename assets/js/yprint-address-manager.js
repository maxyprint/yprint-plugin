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
            console.log('  - Billing Fields:', this.elements.billingFieldsContainer.length);
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
                self.openAddressModal();
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
        },
        
        isUserLoggedIn: function() {
            // Prüfe ob User eingeloggt ist
            return $('body').hasClass('logged-in') || $('#wpadminbar').length > 0;
        },
        
        loadSavedAddresses: function() {
            const self = this;
            
            console.log('=== Loading Saved Addresses ===');
            console.log('Container before AJAX:');
            console.log('  - Exists:', self.addressContainer.length);
            console.log('  - Visible:', self.addressContainer.is(':visible'));
            console.log('  - Display CSS:', self.addressContainer.css('display'));
            console.log('  - Grid element:', self.addressContainer.find('.address-cards-grid').length);
            
            // Container-Status vor AJAX-Call
            self.loadingIndicator.show();
            self.addressContainer.find('.address-cards-grid').hide();
            self.addressContainer.show();
            
            console.log('Container after show():');
            console.log('  - Visible:', self.addressContainer.is(':visible'));
            console.log('  - Display CSS:', self.addressContainer.css('display'));
            console.log('  - Loading indicator visible:', self.loadingIndicator.is(':visible'));
            
            const ajaxData = {
                action: 'yprint_get_saved_addresses',
                nonce: yprint_address_ajax.nonce
            };
            console.log('AJAX Request Data:', ajaxData);
            console.log('AJAX URL:', yprint_address_ajax.ajax_url);
            
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: ajaxData,
                beforeSend: function(xhr, settings) {
                    console.log('AJAX beforeSend - URL:', settings.url);
                    console.log('AJAX beforeSend - Data:', settings.data);
                },
                success: function(response) {
                    console.log('AJAX Success - Raw response:', response);
                    console.log('Response type:', typeof response);
                    
                    if (response && response.success) {
                        console.log('Success: Address data received:', response.data);
                        console.log('Number of addresses:', Object.keys(response.data.addresses || {}).length);
                        
                        self.renderAddresses(response.data.addresses || {});
                        
                        if (Object.keys(response.data.addresses || {}).length === 0) {
                            console.log('No addresses found - hiding container, showing form');
                            self.addressContainer.hide();
                            $('#address-form').show();
                        } else {
                            console.log('Addresses found - showing grid');
                            self.addressContainer.find('.address-cards-grid').show();
                        }
                    } else {
                        console.error('AJAX Success but response.success is false');
                        console.error('Response:', response);
                        const errorMsg = (response && response.data && response.data.message) || 'Fehler beim Laden der Adressen.';
                        console.error('Error message:', errorMsg);
                        self.showMessage(errorMsg, 'error');
                        self.addressContainer.hide();
                        $('#address-form').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('=== AJAX Error ===');
                    console.error('Status:', status);
                    console.error('Error:', error);
                    console.error('XHR Status:', xhr.status);
                    console.error('XHR Response Text:', xhr.responseText);
                    console.error('XHR Ready State:', xhr.readyState);
                    
                    self.showMessage('AJAX Fehler beim Laden der Adressen: ' + error, 'error');
                    self.addressContainer.hide();
                    $('#address-form').show();
                },
                complete: function(xhr, status) {
                    console.log('AJAX Complete - Status:', status);
                    console.log('Final container state:');
                    console.log('  - Visible:', self.addressContainer.is(':visible'));
                    console.log('  - Grid visible:', self.addressContainer.find('.address-cards-grid').is(':visible'));
                    console.log('  - Loading hidden:', !self.loadingIndicator.is(':visible'));
                    
                    self.loadingIndicator.hide();
                }
            });
            
            console.log('=== AJAX Request Sent ===');
        },
        
        renderAddresses: function(addresses) {
            const container = $('.yprint-saved-addresses');
            if (container.length === 0) return;
            
            const grid = container.find('.address-cards-grid');
            const addNewCard = grid.find('.add-new-address-card').detach();
            
            // Bestehende Adresskarten entfernen
            grid.find('.address-card:not(.add-new-address-card)').remove();
            
            // WooCommerce Standard-Adresse hinzufügen falls vorhanden
            this.addWooCommerceDefaultAddress(grid);
            
            // Neue Adresskarten hinzufügen
            // >>> HIER IST IHRE ÄNDERUNG, UM ÜBER OBJEKTE ZU ITERIEREN <
            Object.values(addresses).forEach(address => { 
                const card = this.createAddressCard(address);
                grid.append(card);
            });
            
            // "Neue Adresse" Karte wieder hinzufügen
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
        
        openAddressModal: function() {
            this.modal.addClass('active');
            $('#new-address-form')[0].reset();
            $('.address-form-errors').hide();
            $('body').css('overflow', 'hidden');
        },
        
        closeAddressModal: function() {
            this.modal.removeClass('active');
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
        
        saveNewAddress: function() {
            const self = this;
            const form = $('#new-address-form');
            
            if (!this.validateForm()) {
                this.showFormError('Bitte füllen Sie alle Pflichtfelder aus.');
                return;
            }
            
            const formData = {
                action: 'yprint_save_new_address',
                nonce: yprint_address_ajax.nonce,
                name: form.find('[name="name"]').val(),
                first_name: form.find('[name="first_name"]').val(),
                last_name: form.find('[name="last_name"]').val(),
                company: form.find('[name="company"]').val(),
                address_1: form.find('[name="address_1"]').val(),
                address_2: form.find('[name="address_2"]').val(),
                postcode: form.find('[name="postcode"]').val(),
                city: form.find('[name="city"]').val(),
                country: form.find('[name="country"]').val(),
                is_company: form.find('[name="is_company"]').is(':checked')
            };
            
            // Loading state
            $('.btn-save-address').prop('disabled', true).html('Speichere...');
            
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.success) {
                        self.closeAddressModal();
                        self.loadSavedAddresses();
                        self.showMessage(response.data.message || 'Adresse gespeichert', 'success');
                    } else {
                        self.showFormError(response.data.message || 'Fehler beim Speichern');
                    }
                },
                error: function() {
                    self.showFormError('Fehler beim Speichern der Adresse');
                },
                complete: function() {
                    $('.btn-save-address').prop('disabled', false).html('<i class="fas fa-save mr-2"></i>Adresse speichern');
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