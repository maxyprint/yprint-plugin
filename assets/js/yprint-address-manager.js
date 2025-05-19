/**
 * YPrint Address Manager JavaScript
 */
(function($) {
    'use strict';

    let addressManager = {
        modal: null,
        currentAddressType: 'shipping',
        selectedAddressId: null,
        
        init: function() {
            console.log('YPrint Address Manager: Initializing...');
            
            // DOM Elements
            this.modal = $('#new-address-modal');
            this.addressContainer = $('.yprint-saved-addresses');
            this.loadingIndicator = this.addressContainer.find('.loading-addresses');
            
            // Events binden
            this.bindEvents();
            
            // Gespeicherte Adressen laden wenn eingeloggt
            if (this.isUserLoggedIn()) {
                this.loadSavedAddresses();
            } else {
                this.addressContainer.hide();
                $('#address-form').show();
            }
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
            
            self.addressContainer.show();
            self.loadingIndicator.show();
            self.addressContainer.find('.address-cards-grid').hide();
            
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_get_saved_addresses',
                    nonce: yprint_address_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        self.renderAddresses(response.data.addresses);
                        if (Object.keys(response.data.addresses).length === 0) {
                            self.addressContainer.hide();
                            $('#address-form').show();
                        } else {
                            self.addressContainer.find('.address-cards-grid').show();
                        }
                    } else {
                        console.error('Error loading addresses:', response.data);
                        self.showMessage(response.data.message || 'Fehler beim Laden der Adressen.', 'error');
                        self.addressContainer.hide();
                        $('#address-form').show();
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error loading addresses:', error);
                    self.showMessage('AJAX Fehler beim Laden der Adressen: ' + error, 'error');
                    self.addressContainer.hide();
                    $('#address-form').show();
                },
                complete: function() {
                    self.loadingIndicator.hide();
                }
            });
        },
        
        renderAddresses: function(addresses) {
            const container = $('.yprint-saved-addresses');
            if (container.length === 0) return;
            
            const grid = container.find('.address-cards-grid');
            const addNewCard = grid.find('.add-new-address-card').detach();
            
            // Bestehende Adresskarten entfernen
            grid.find('.address-card:not(.add-new-address-card)').remove();
            
            // Neue Adresskarten hinzufügen
            addresses.forEach(address => {
                const card = this.createAddressCard(address);
                grid.append(card);
            });
            
            // "Neue Adresse" Karte wieder hinzufügen
            grid.append(addNewCard);
            
            // Container anzeigen wenn Adressen vorhanden
            if (addresses.length > 0) {
                container.show();
            }
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