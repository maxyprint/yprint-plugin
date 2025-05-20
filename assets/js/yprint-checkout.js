// YPrint Checkout JavaScript - Allgemeine Funktionen
// Stellt sicher, dass das DOM vollständig geladen ist, bevor das Skript ausgeführt wird.
document.addEventListener('DOMContentLoaded', function () {
    // Globale Variablen und Zustand für den Checkout-Prozess
    let currentStep = 1; // Startet immer mit dem ersten Schritt als Standard
    const formData = { // Objekt zum Speichern der Formulardaten
        shipping: {},
        billing: {},
        payment: { method: 'paypal' }, // Standard-Zahlungsmethode
        voucher: null,
        isBillingSameAsShipping: true,
    };

    // Dummy Produktdaten für den Warenkorb (sollten in einer echten Anwendung serverseitig geladen werden)
    const cartItems = [
        { id: 1, name: "Individuelles Fotobuch Premium", price: 49.99, quantity: 1, image: "https://placehold.co/100x100/0079FF/FFFFFF?text=Buch" },
        { id: 2, name: "Visitenkarten (250 Stk.)", price: 19.50, quantity: 2, image: "https://placehold.co/100x100/E3F2FD/1d1d1f?text=Karten" },
        { id: 3, name: "Großformat Poster A2", price: 25.00, quantity: 1, image: "https://placehold.co/100x100/CCCCCC/FFFFFF?text=Poster" },
    ];

    // DOM-Elemente auswählen (optimiert, falls Elemente nicht immer vorhanden sind)
    const steps = document.querySelectorAll(".checkout-step");
    const progressSteps = document.querySelectorAll(".progress-step");
    const btnToPayment = document.getElementById('btn-to-payment');
    const btnToConfirmation = document.getElementById('btn-to-confirmation');
    const btnBackToAddress = document.getElementById('btn-back-to-address');
    const btnBackToPaymentFromConfirm = document.getElementById('btn-back-to-payment-from-confirm');
    const btnBuyNow = document.getElementById('btn-buy-now');
    const btnContinueShopping = document.getElementById('btn-continue-shopping');
    const loadingOverlay = document.getElementById('loading-overlay');
    const billingSameAsShippingCheckbox = document.getElementById('billing-same-as-shipping');
    const billingAddressFieldsContainer = document.getElementById('billing-address-fields');
    const addressForm = document.getElementById('address-form');

    // Erforderliche Felder für die Adressvalidierung
    const requiredAddressFields = ['street', 'housenumber', 'zip', 'city', 'country'];
    const requiredBillingFields = ['billing_street', 'billing_housenumber', 'billing_zip', 'billing_city', 'billing_country'];


    /**
     * Validiert das Adressformular.
     * @returns {boolean} - True, wenn das Formular gültig ist, sonst false.
     */
    function validateAddressForm() {
        if (!addressForm) return true; // Wenn kein Adressformular auf der Seite ist, überspringen.
        let isValid = true;

        requiredAddressFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) { // Prüfe, ob das Feld existiert
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-yprint-error');
                } else {
                    field.classList.remove('border-yprint-error');
                }
            }
        });

        if (!formData.isBillingSameAsShipping && billingAddressFieldsContainer) {
            requiredBillingFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field) { // Prüfe, ob das Feld existiert
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('border-yprint-error');
                    } else {
                        field.classList.remove('border-yprint-error');
                    }
                }
            });
        }
        if (btnToPayment) {
            btnToPayment.disabled = !isValid;
        }
        return isValid;
    }

    // Event Listener für das Adressformular zur Live-Validierung
    if (addressForm) {
        addressForm.addEventListener('input', validateAddressForm);
    }

    // Event Listener für die Checkbox "Rechnungsadresse ist identisch"
    if (billingSameAsShippingCheckbox && billingAddressFieldsContainer) {
        billingSameAsShippingCheckbox.addEventListener('change', () => {
            formData.isBillingSameAsShipping = billingSameAsShippingCheckbox.checked;
            billingAddressFieldsContainer.classList.toggle('hidden', formData.isBillingSameAsShipping);
            // Setze 'required' Attribut basierend auf Sichtbarkeit
            const billingInputs = billingAddressFieldsContainer.querySelectorAll('input, select');
            billingInputs.forEach(input => input.required = !formData.isBillingSameAsShipping);
            validateAddressForm(); // Nach Änderung neu validieren
        });
        // Initialer Zustand der Rechnungsadressfelder
        billingAddressFieldsContainer.classList.toggle('hidden', billingSameAsShippingCheckbox.checked);
        const initialBillingInputs = billingAddressFieldsContainer.querySelectorAll('input, select');
        initialBillingInputs.forEach(input => input.required = !billingSameAsShippingCheckbox.checked);
    }

    // Adressauswahl-Handler
function handleAddressSelection() {
    const addressCards = document.querySelectorAll('.address-card input[type="radio"]');
    
    addressCards.forEach(radio => {
        radio.addEventListener('change', function() {
            // Alle Karten deselektieren
            document.querySelectorAll('.address-card-content').forEach(card => {
                card.classList.remove('border-blue-500', 'bg-blue-50');
                card.classList.add('border-gray-200');
            });
            
            document.querySelectorAll('.address-selected-icon').forEach(icon => {
                icon.classList.add('opacity-0');
            });
            
            if (this.checked) {
                // Gewählte Karte markieren
                const cardContent = this.parentElement.querySelector('.address-card-content');
                const selectedIcon = this.parentElement.querySelector('.address-selected-icon');
                
                cardContent.classList.remove('border-gray-200');
                cardContent.classList.add('border-blue-500', 'bg-blue-50');
                selectedIcon.classList.remove('opacity-0');
                
                const addressType = this.dataset.addressType;
                
                if (addressType === 'new') {
                    // Neue Adresse - Modal öffnen
                    const modal = document.getElementById('new-address-modal');
                    if (modal) {
                        modal.classList.add('active');
                        document.body.style.overflow = 'hidden';
                    }
                    // Checkout-Felder leeren
                    clearCheckoutFields();
                } else {
                    // Gespeicherte oder WC-Adresse - Felder füllen
                    const addressData = JSON.parse(this.dataset.addressData);
                    populateCheckoutFields(addressData);
                }
            }
        });
    });
}

// Checkout-Felder mit Adressdaten füllen
function populateCheckoutFields(addressData) {
    const streetField = document.getElementById('street');
    const housenumberField = document.getElementById('housenumber');
    const zipField = document.getElementById('zip');
    const cityField = document.getElementById('city');
    const countryField = document.getElementById('country');
    const phoneField = document.getElementById('phone');
    
    if (streetField) streetField.value = addressData.address_1 || '';
    if (housenumberField) housenumberField.value = addressData.address_2 || '';
    if (zipField) zipField.value = addressData.postcode || '';
    if (cityField) cityField.value = addressData.city || '';
    if (countryField) countryField.value = addressData.country || 'DE';
    if (phoneField) phoneField.value = addressData.phone || '';
    
    // Trigger input events für Validierung
    const fieldsToTrigger = [streetField, housenumberField, zipField, cityField, countryField];
    fieldsToTrigger.forEach(field => {
        if (field) {
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
    
    // Rechnungsadresse synchronisieren wenn gewünscht
    const billingSameCheckbox = document.getElementById('billing-same-as-shipping');
    if (billingSameCheckbox && billingSameCheckbox.checked) {
        syncBillingWithShipping();
    }
    
    // Adressformular-Bereich ausblenden nach Auswahl
    const addressForm = document.getElementById('address-form');
    if (addressForm) {
        addressForm.style.marginTop = '1rem';
    }
}

// Checkout-Felder leeren
function clearCheckoutFields() {
    const fields = ['street', 'housenumber', 'zip', 'city', 'phone'];
    fields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) {
            field.value = '';
            field.dispatchEvent(new Event('input', { bubbles: true }));
        }
    });
    
    const countryField = document.getElementById('country');
    if (countryField) {
        countryField.value = 'DE';
        countryField.dispatchEvent(new Event('change', { bubbles: true }));
    }
}

// Rechnungsadresse mit Lieferadresse synchronisieren
function syncBillingWithShipping() {
    const shippingFields = ['street', 'housenumber', 'zip', 'city', 'country'];
    const billingFields = ['billing_street', 'billing_housenumber', 'billing_zip', 'billing_city', 'billing_country'];
    
    shippingFields.forEach((shippingId, index) => {
        const shippingField = document.getElementById(shippingId);
        const billingField = document.getElementById(billingFields[index]);
        
        if (shippingField && billingField) {
            billingField.value = shippingField.value;
        }
    });
}

// Modal-Handler für neue Adresse
function handleNewAddressModal() {
    const modal = document.getElementById('new-address-modal');
    const closeButtons = modal?.querySelectorAll('.btn-close-modal, .btn-cancel-address');
    const saveButton = modal?.querySelector('.btn-save-address');
    const form = modal?.querySelector('#new-address-form');
    
    // Modal schließen
    closeButtons?.forEach(button => {
        button.addEventListener('click', () => {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
            
            // Radio-Button zurücksetzen
            const newAddressRadio = document.querySelector('input[name="selected_address"][value="new_address"]');
            if (newAddressRadio) {
                newAddressRadio.checked = false;
            }
            
            // Erste verfügbare Adresse auswählen
            const firstAddressRadio = document.querySelector('input[name="selected_address"]:not([value="new_address"])');
            if (firstAddressRadio) {
                firstAddressRadio.checked = true;
                firstAddressRadio.dispatchEvent(new Event('change'));
            }
        });
    });
    
    // ESC-Taste zum Schließen
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal?.classList.contains('active')) {
            closeButtons[0]?.click();
        }
    });
    
    // Neue Adresse speichern
    saveButton?.addEventListener('click', (e) => {
        e.preventDefault();
        
        if (!form) return;
        
        const formData = new FormData(form);
        formData.append('action', 'yprint_save_address');
        formData.append('yprint_address_nonce', yprint_address_ajax.nonce);
        
        // Loading state
        saveButton.disabled = true;
        saveButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Speichere...';
        
        fetch(yprint_address_ajax.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Modal schließen
                modal.classList.remove('active');
                document.body.style.overflow = 'auto';
                
                // Neue Adresse in Checkout-Felder eintragen
                const newAddressData = data.data.address_data;
                populateCheckoutFields(newAddressData);
                
                // Erfolgsmeldung anzeigen
                showMessage('Adresse erfolgreich gespeichert!', 'success');
                
                // Optional: Seite neu laden um die neue Adresse in der Liste anzuzeigen
                // window.location.reload();
            } else {
                showMessage(data.data.message || 'Fehler beim Speichern der Adresse', 'error');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showMessage('Ein Fehler ist aufgetreten', 'error');
        })
        .finally(() => {
            // Loading state zurücksetzen
            saveButton.disabled = false;
            saveButton.innerHTML = '<i class="fas fa-save mr-2"></i>Adresse speichern';
        });
    });
}

// Nachrichten anzeigen
function showMessage(message, type = 'info') {
    const messageDiv = document.createElement('div');
    messageDiv.className = `checkout-message ${type} fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50`;
    messageDiv.innerHTML = `
        <div class="flex items-center">
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} mr-2"></i>
            ${message}
        </div>
    `;
    
    document.body.appendChild(messageDiv);
    
    // Nach 5 Sekunden automatisch entfernen
    setTimeout(() => {
        messageDiv.remove();
    }, 5000);
}

// Nach dem DOMContentLoaded Event hinzufügen:
document.addEventListener('DOMContentLoaded', function () {
    // ... bestehender Code ...
    
    // Adressauswahl initialisieren
    handleAddressSelection();
    handleNewAddressModal();
    
    // Erste Adresse automatisch auswählen wenn keine ausgewählt ist
    const selectedAddress = document.querySelector('input[name="selected_address"]:checked');
    if (!selectedAddress) {
        const firstAddress = document.querySelector('input[name="selected_address"]:not([value="new_address"])');
        if (firstAddress) {
            firstAddress.checked = true;
            firstAddress.dispatchEvent(new Event('change'));
        }
    }
});

    /**
     * Zeigt den angegebenen Checkout-Schritt an und aktualisiert die Fortschrittsanzeige.
     * @param {number} stepNumber - Die Nummer des anzuzeigenden Schritts (1-basiert, z.B. 1 für Adresse, 2 für Zahlung).
     */
    function showStep(stepNumber) {
        console.log("Showing step:", stepNumber);

        // Konstruiere die erwartete ID für den aktiven Schritt (z.B. "step-2")
        const targetStepId = `step-${stepNumber}`;

        // WICHTIGE ÄNDERUNG HIER: Finde das Element über seine ID, nicht über den Index der NodeList.
        // Das ist entscheidend, da PHP nur den HTML-Code des aktuell angefragten Schrittes ausgibt.
        steps.forEach((stepEl) => {
            if (stepEl.id === targetStepId) {
                console.log("Activating step element:", stepEl.id);
                stepEl.classList.add('active');
                stepEl.style.display = 'block'; // Explizit auf 'block' setzen, um CSS-Konflikte zu vermeiden
            } else {
                // Diese Bedingung ist nur relevant, wenn mehrere Schritte im DOM vorhanden sind,
                // was in dieser PHP-Setup-Konfiguration normalerweise nicht der Fall ist.
                // Sie dient der Sicherheit, falls sich das HTML-Rendering ändert.
                stepEl.classList.remove('active');
                stepEl.style.display = 'none'; // Explizit auf 'none' setzen
            }
        });

        // Progress Bar aktualisieren (dieser Teil war bereits korrekt)
        progressSteps.forEach((pStep, index) => {
            pStep.classList.remove('active', 'completed');
            if (index < stepNumber - 1) {
                pStep.classList.add('completed');
            } else if (index === stepNumber - 1) {
                pStep.classList.add('active');
            }
        });

        currentStep = stepNumber; // Aktuellen Schritt speichern
        window.scrollTo({ top: 0, behavior: 'smooth' }); // Nach oben scrollen

        console.log("Active elements after showStep:", document.querySelectorAll('.checkout-step.active'));
    }

    /**
     * Sammelt die Adressdaten aus dem Formular.
     */
    function collectAddressData() {
        if (!addressForm) return;

        formData.shipping.street = document.getElementById('street')?.value || '';
        formData.shipping.housenumber = document.getElementById('housenumber')?.value || '';
        formData.shipping.zip = document.getElementById('zip')?.value || '';
        formData.shipping.city = document.getElementById('city')?.value || '';
        formData.shipping.country = document.getElementById('country')?.value || '';
        formData.shipping.phone = document.getElementById('phone')?.value || '';

        if (formData.isBillingSameAsShipping) {
            formData.billing = { ...formData.shipping }; // Kopiert die Lieferadresse
        } else {
            formData.billing.street = document.getElementById('billing_street')?.value || '';
            formData.billing.housenumber = document.getElementById('billing_housenumber')?.value || '';
            formData.billing.zip = document.getElementById('billing_zip')?.value || '';
            formData.billing.city = document.getElementById('billing_city')?.value || '';
            formData.billing.country = document.getElementById('billing_country')?.value || '';
        }
        // In einer echten Anwendung würde hier ein AJAX Call an 'wp_ajax_yprint_save_address' erfolgen
        console.log("Adressdaten gesammelt:", formData);

        if (YPrintAddressManager && YPrintAddressManager.shouldSaveNewAddress && YPrintAddressManager.shouldSaveNewAddress()) {
            // AJAX-Call zum Speichern der Adresse
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_save_checkout_address',
                    nonce: yprint_address_ajax.nonce,
                    first_name: formData.shipping.first_name || '',
                    last_name: formData.shipping.last_name || '',
                    company: formData.shipping.company || '',
                    address_1: formData.shipping.street || '',
                    address_2: formData.shipping.housenumber || '',
                    postcode: formData.shipping.zip || '',
                    city: formData.shipping.city || '',
                    country: formData.shipping.country || 'DE'
                },
                success: function(response) {
                    console.log('Address saved:', response);
                },
                error: function(xhr, status, error) {
                    console.error('Error saving address:', error);
                }
            });
        }

        if (YPrintAddressManager && YPrintAddressManager.shouldSaveNewAddress && YPrintAddressManager.shouldSaveNewAddress()) {
            // AJAX-Call zum Speichern der Adresse
            $.ajax({
                url: yprint_address_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_save_checkout_address',
                    nonce: yprint_address_ajax.nonce,
                    first_name: formData.shipping.first_name || '',
                    last_name: formData.shipping.last_name || '',
                    company: formData.shipping.company || '',
                    address_1: formData.shipping.street || '',
                    address_2: formData.shipping.housenumber || '',
                    postcode: formData.shipping.zip || '',
                    city: formData.shipping.city || '',
                    country: formData.shipping.country || 'DE'
                },
                success: function(response) {
                    console.log('Address saved:', response);
                },
                error: function(xhr, status, error) {
                    console.error('Error saving address:', error);
                }
            });
        }
    }

    /**
     * Sammelt die Zahlungsdaten.
     */
    function collectPaymentData() {
        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
        if (selectedPaymentMethod) {
            formData.payment.method = selectedPaymentMethod.value;
        }
        const voucherInput = document.getElementById('voucher');
        if (voucherInput) {
            formData.voucher = voucherInput.value;
        }
        // In einer echten Anwendung würde hier ein AJAX Call an 'wp_ajax_yprint_set_payment_method' erfolgen
        console.log("Zahlungsdaten gesammelt:", formData);
    }

    /**
     * Berechnet die Preise (Zwischensumme, Versand, Rabatt, Gesamt).
     * @returns {object} - Ein Objekt mit den berechneten Preisen.
     */
    function calculatePrices() {
        let subtotal = cartItems.reduce((sum, item) => sum + item.price * item.quantity, 0);
        let shipping = 4.99; // Feste Versandkosten (Beispiel)
        let discount = 0;
        const voucherFeedbackEl = document.getElementById('voucher-feedback');

        if (formData.voucher && formData.voucher.toUpperCase() === "YPRINT10") { // Dummy Gutschein Logik
            discount = subtotal * 0.10; // 10% Rabatt
            if (voucherFeedbackEl) {
                voucherFeedbackEl.textContent = `"YPRINT10" angewendet: -€${discount.toFixed(2)}`;
                voucherFeedbackEl.className = 'text-sm mt-1 text-yprint-success';
            }
        } else if (formData.voucher && voucherFeedbackEl) {
            voucherFeedbackEl.textContent = 'Ungültiger Gutscheincode.';
            voucherFeedbackEl.className = 'text-sm mt-1 text-yprint-error';
        } else if (voucherFeedbackEl) {
            voucherFeedbackEl.textContent = ''; // Feedback leeren, wenn Gutschein entfernt wird
        }

        let total = subtotal + shipping - discount;
        let vat = total * 0.19; // Annahme 19% MwSt auf den Gesamtbetrag nach Rabatt

        return { subtotal, shipping, discount, total, vat };
    }

    /**
     * Aktualisiert die Preisanzeige im Zahlungsschritt.
     */
    function updatePaymentStepSummary() {
        const prices = calculatePrices();
        const subtotalPriceEl = document.getElementById('subtotal-price');
        const shippingPriceEl = document.getElementById('shipping-price');
        const totalPricePaymentEl = document.getElementById('total-price-payment');

        if (subtotalPriceEl) subtotalPriceEl.textContent = `€${prices.subtotal.toFixed(2)}`;
        if (shippingPriceEl) shippingPriceEl.textContent = `€${prices.shipping.toFixed(2)}`;
        if (totalPricePaymentEl) totalPricePaymentEl.textContent = `€${prices.total.toFixed(2)}`;
    }

    // Event Listener für Gutscheinfeld und Button, um Preise live zu aktualisieren
    const voucherInput = document.getElementById('voucher');
    // Annahme: Button ist direkt nach dem Input-Feld oder hat eine eindeutige ID
    const voucherButton = document.querySelector('#voucher + button') || document.getElementById('apply-voucher-button');

    if (voucherInput) {
        voucherInput.addEventListener('input', () => {
            formData.voucher = voucherInput.value;
            updatePaymentStepSummary();
            // Optional: updateConfirmationSummary(), falls direkt auf Bestätigungsseite sichtbar
        });
    }
    if (voucherButton) {
        voucherButton.addEventListener('click', () => {
            if(voucherInput) formData.voucher = voucherInput.value;
            updatePaymentStepSummary();
            // Optional: updateConfirmationSummary()
        });
    }


    /**
     * Füllt die Bestätigungsseite mit den gesammelten Daten.
     */
    function populateConfirmation() {
        // Adressen
        const confirmShippingAddressEl = document.getElementById('confirm-shipping-address');
        if (confirmShippingAddressEl) {
            confirmShippingAddressEl.innerHTML = `
                ${formData.shipping.street || ''} ${formData.shipping.housenumber || ''}<br>
                ${formData.shipping.zip || ''} ${formData.shipping.city || ''}<br>
                ${formData.shipping.country || ''}
                ${formData.shipping.phone ? '<br>Tel: ' + formData.shipping.phone : ''}
            `;
        }

        const confirmBillingContainer = document.getElementById('confirm-billing-address-container');
        const confirmBillingAddressEl = document.getElementById('confirm-billing-address');
        if (confirmBillingContainer && confirmBillingAddressEl) {
            if (!formData.isBillingSameAsShipping && Object.keys(formData.billing).length > 0) {
                confirmBillingAddressEl.innerHTML = `
                    ${formData.billing.street || ''} ${formData.billing.housenumber || ''}<br>
                    ${formData.billing.zip || ''} ${formData.billing.city || ''}<br>
                    ${formData.billing.country || ''}
                `;
                confirmBillingContainer.classList.remove('hidden');
            } else {
                confirmBillingContainer.classList.add('hidden');
            }
        }

        // Zahlungsart
        const confirmPaymentMethodEl = document.getElementById('confirm-payment-method');
        if (confirmPaymentMethodEl) {
            let paymentMethodText = 'Nicht gewählt';
            switch (formData.payment.method) {
                case 'paypal': paymentMethodText = '<i class="fab fa-paypal mr-2"></i> PayPal'; break;
                case 'applepay': paymentMethodText = '<i class="fab fa-apple-pay mr-2"></i> Apple Pay'; break;
                case 'creditcard': paymentMethodText = '<i class="fas fa-credit-card mr-2"></i> Kreditkarte'; break;
                case 'klarna': paymentMethodText = '<svg viewBox="0 0 496 156" class="klarna-logo-svg inline" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M248.291 31.0084C265.803 31.0084 280.21 37.1458 291.513 49.4206C302.888 61.6954 308.575 77.0417 308.575 95.4594C308.575 113.877 302.888 129.223 291.513 141.498C280.21 153.773 265.803 159.91 248.291 159.91H180.854V31.0084H248.291ZM213.956 132.621H248.291C258.57 132.621 267.076 129.68 273.808 123.798C280.612 117.844 284.014 109.177 284.014 97.7965C284.014 86.4158 280.612 77.7491 273.808 71.7947C267.076 65.8403 258.57 62.8992 248.291 62.8992H213.956V132.621ZM143.061 31.0084H109.959V159.91H143.061V31.0084ZM495.99 31.0084L445.609 159.91H408.009L378.571 79.1557L349.132 159.91H311.532L361.914 31.0084H399.514L428.952 112.661L458.39 31.0084H495.99ZM0 31.0084H33.1017V159.91H0V31.0084Z" fill="#FFB3C7"></path></svg> Klarna'; break;
            }
            confirmPaymentMethodEl.innerHTML = paymentMethodText;
        }

        // Produkte
        const productListEl = document.getElementById('confirm-product-list');
        if (productListEl) {
            productListEl.innerHTML = ''; // Vorherige Einträge löschen
            cartItems.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'flex justify-between items-center py-2'; // Tailwind Klassen
                itemEl.innerHTML = `
                    <div class="flex items-center">
                        <img src="${item.image}" alt="${item.name}" class="product-item"> <div>
                            <p class="font-medium">${item.name}</p>
                            <p class="text-sm text-yprint-text-secondary">Menge: ${item.quantity}</p>
                        </div>
                    </div>
                    <p class="font-medium">€${(item.price * item.quantity).toFixed(2)}</p>
                `;
                productListEl.appendChild(itemEl);
            });
        }

        // Preise
        const prices = calculatePrices();
        const confirmSubtotalEl = document.getElementById('confirm-subtotal');
        const confirmShippingEl = document.getElementById('confirm-shipping');
        const confirmDiscountRowEl = document.getElementById('confirm-discount-row');
        const confirmDiscountEl = document.getElementById('confirm-discount');
        const confirmVatEl = document.getElementById('confirm-vat');
        const confirmTotalEl = document.getElementById('confirm-total');

        if (confirmSubtotalEl) confirmSubtotalEl.textContent = `€${prices.subtotal.toFixed(2)}`;
        if (confirmShippingEl) confirmShippingEl.textContent = `€${prices.shipping.toFixed(2)}`;
        if (confirmDiscountRowEl && confirmDiscountEl) {
            if (prices.discount > 0) {
                confirmDiscountEl.textContent = `-€${prices.discount.toFixed(2)}`;
                confirmDiscountRowEl.classList.remove('hidden');
            } else {
                confirmDiscountRowEl.classList.add('hidden');
            }
        }
        if (confirmVatEl) confirmVatEl.textContent = `€${prices.vat.toFixed(2)}`;
        if (confirmTotalEl) confirmTotalEl.textContent = `€${prices.total.toFixed(2)}`;
    }

    /**
     * Füllt die Danke-Seite mit Bestelldetails.
     */
    function populateThankYouPage() {
        const orderNumberEl = document.getElementById('order-number');
        if (orderNumberEl) {
            orderNumberEl.textContent = `YP-${Math.floor(Math.random() * 900000000) + 100000000}`; // Zufällige Bestellnummer
        }

        const summaryEl = document.getElementById('thankyou-product-summary');
        if (summaryEl) {
            summaryEl.innerHTML = '<h3 class="font-semibold mb-2 text-lg">Ihre Bestellung:</h3>'; // Tailwind: text-lg
            cartItems.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'flex justify-between items-center py-1 text-sm'; // Tailwind Klassen
                itemEl.innerHTML = `
                    <span>${item.name} (x${item.quantity})</span>
                    <span>€${(item.price * item.quantity).toFixed(2)}</span>
                `;
                summaryEl.appendChild(itemEl);
            });
            const prices = calculatePrices();
            const totalEl = document.createElement('div');
            totalEl.className = 'flex justify-between items-center py-1 text-sm font-bold mt-2 border-t border-yprint-medium-gray pt-2'; // Tailwind Klassen
            totalEl.innerHTML = `
                <span>Gesamt:</span>
                <span>€${prices.total.toFixed(2)}</span>
            `;
            summaryEl.appendChild(totalEl);
        }
    }

    /**
     * Zeigt oder versteckt das Lade-Overlay.
     * @param {boolean} show - True zum Anzeigen, false zum Verstecken.
     */
    function toggleLoadingOverlay(show) {
        if (loadingOverlay) {
            loadingOverlay.classList.toggle('visible', show);
            // Die Klasse 'hidden' steuert die display-Eigenschaft, 'visible' (oder keine) die Deckkraft/Pointer-Events
            // Hier wird angenommen, dass die CSS-Regeln für 'visible' das Overlay korrekt sichtbar machen
            // und dass standardmäßig (ohne 'visible') das Overlay 'display: none' oder 'opacity: 0' hat.
            if (show) {
                loadingOverlay.style.display = 'flex'; // Sicherstellen, dass es sichtbar ist
            } else {
                // Verzögere das Ausblenden des Overlays, damit die Animation abgespielt werden kann (optional)
                setTimeout(() => {
                    loadingOverlay.style.display = 'none';
                }, 300); // Beispiel: Nach 300ms ausblenden (wenn CSS transition max. 300ms ist)
            }
        }
    }

    // Event Listeners für Navigationsbuttons
    if (btnToPayment) {
        btnToPayment.addEventListener('click', (e) => {
            e.preventDefault(); // Verhindert Standard-Formular-Submit
            if (validateAddressForm()) {
                collectAddressData();
                updatePaymentStepSummary();
                showStep(2);
                // In einer echten Anwendung würde hier ein AJAX Call erfolgen.
                // z.B. YPrintAJAX.saveAddress(formData.shipping, formData.billing);
            } else {
                // Optional: Fokussiere das erste invalide Feld oder zeige eine generelle Nachricht
                const firstError = addressForm.querySelector('.border-yprint-error');
                if (firstError) firstError.focus();
            }
        });
    }

    if (btnBackToAddress) {
        btnBackToAddress.addEventListener('click', (e) => {
            e.preventDefault();
            showStep(1);
        });
    }

    if (btnToConfirmation) {
        btnToConfirmation.addEventListener('click', (e) => {
            e.preventDefault();
            collectPaymentData();
            populateConfirmation();
            showStep(3);
            // z.B. YPrintAJAX.setPaymentMethod(formData.payment);
        });
    }

    if (btnBackToPaymentFromConfirm) {
        btnBackToPaymentFromConfirm.addEventListener('click', (e) => {
            e.preventDefault();
            updatePaymentStepSummary();
            showStep(2);
        });
    }

    if (btnBuyNow) {
        btnBuyNow.addEventListener('click', (e) => {
            e.preventDefault();
            toggleLoadingOverlay(true);
            // Simuliere Bestellverarbeitung (AJAX Call an 'wp_ajax_yprint_process_checkout')
            // YPrintAJAX.processCheckout(formData).then(response => { ... });
            setTimeout(() => {
                toggleLoadingOverlay(false);
                populateThankYouPage();
                showStep(4);
                // Fortschrittsanzeige für Danke-Seite anpassen
                progressSteps.forEach(pStep => pStep.classList.add('completed'));
                const lastProgressStep = document.getElementById('progress-step-3');
                if (lastProgressStep) {
                    lastProgressStep.classList.remove('active'); // Letzten Schritt nicht mehr aktiv
                    lastProgressStep.classList.add('completed'); // Auch als completed markieren
                }
            }, 2500); // 2.5 Sekunden Ladezeit-Simulation
        });
    }

    if (btnContinueShopping) {
        btnContinueShopping.addEventListener('click', () => {
            // Hier Weiterleitung zur Startseite oder Kategorieseite
            window.location.href = "/"; // Beispiel für Weiterleitung
            // alert("Weiterleitung zum Shop..."); // Nur für Demo-Zwecke
        });
    }

    // Klarna Logo SVG Path Korrektur (falls im HTML gekürzt)
    // Stellt sicher, dass das Klarna-Logo korrekt angezeigt wird.
    document.querySelectorAll('svg path[d^="M248.291"]').forEach(path => {
        path.setAttribute('d', "M248.291 31.0084C265.803 31.0084 280.21 37.1458 291.513 49.4206C302.888 61.6954 308.575 77.0417 308.575 95.4594C308.575 113.877 302.888 129.223 291.513 141.498C280.21 153.773 265.803 159.91 248.291 159.91H180.854V31.0084H248.291ZM213.956 132.621H248.291C258.57 132.621 267.076 129.68 273.808 123.798C280.612 117.844 284.014 109.177 284.014 97.7965C284.014 86.4158 280.612 77.7491 273.808 71.7947C267.076 65.8403 258.57 62.8992 248.291 62.8992H213.956V132.621ZM143.061 31.0084H109.959V159.91H143.061V31.0084ZM495.99 31.0084L445.609 159.91H408.009L378.571 79.1557L349.132 159.91H311.532L361.914 31.0084H399.514L428.952 112.661L458.39 31.0084H495.99ZM0 31.0084H33.1017V159.91H0V31.0084Z");
        // Die Fill-Farbe sollte idealerweise direkt im SVG oder per CSS Klasse gesetzt werden,
        // hier für Konsistenz noch einmal gesetzt:
        path.setAttribute('fill', "#FFB3C7"); // Klarna Pink
    });


    // Initialisierung des ersten Schritts basierend auf URL-Parametern
    const urlParams = new URLSearchParams(window.location.search);
    const stepParam = urlParams.get('step');
    let initialStep = 1;
    if (stepParam === 'payment') initialStep = 2;
    if (stepParam === 'confirmation') initialStep = 3;
    if (stepParam === 'thankyou') initialStep = 4;

    // Zeige den initialen Schritt an
    showStep(initialStep);

    // Initialisiere Formulare und Zusammenfassungen basierend auf dem Startschritt
    if (initialStep === 1) {
        validateAddressForm();
    } else if (initialStep === 2) {
        // Annahme, dass Adressdaten vorhanden sind, wenn direkt zum Zahlungsschritt navigiert wird
        // In einer echten Anwendung würden diese aus der Session oder dem Backend geladen.
        collectAddressData();
        updatePaymentStepSummary();
    } else if (initialStep === 3) {
        // Annahme, dass Adress- und Zahlungsdaten vorhanden sind
        // In einer echten Anwendung würden diese aus der Session/Backend geladen.
        collectAddressData();
        collectPaymentData();
        populateConfirmation();
    } else if (initialStep === 4) {
        // Für die Danke-Seite
        populateThankYouPage();
        progressSteps.forEach(pStep => pStep.classList.add('completed'));
        const lastProgressStep = document.getElementById('progress-step-3');
        if (lastProgressStep) {
            lastProgressStep.classList.remove('active');
            lastProgressStep.classList.add('completed');
        }
    }

    // Warenkorb-Zusammenfassung initial laden (falls vorhanden)
    const cartSummaryContainer = document.getElementById('checkout-cart-summary-items');
    if (cartSummaryContainer) {
        updateCartSummaryDisplay(cartSummaryContainer);
    }
    const cartTotalsContainer = document.getElementById('checkout-cart-summary-totals');
     if (cartTotalsContainer) {
        updateCartTotalsDisplay(cartTotalsContainer);
    }
}); // Ende DOMContentLoaded

// Debug-Button hinzufügen (nur wenn im Debug-Modus)
if (window.location.search.includes('debug=1') || localStorage.getItem('yprint_debug') === 'true') {
    const debugButton = $('<button type="button" class="btn btn-secondary debug-button" style="position: fixed; top: 10px; right: 10px; z-index: 10000;">Debug Info</button>');
    $('body').append(debugButton);
    
    debugButton.on('click', function() {
        console.log('=== MANUAL DEBUG TRIGGER ===');
        if (window.YPrintAddressManager) {
            window.YPrintAddressManager.debugDOMState();
            window.YPrintAddressManager.debugUserState();
            window.YPrintAddressManager.debugAjaxConfig();
        } else {
            console.log('YPrintAddressManager not available');
        }
        console.log('Current Step:', currentStep);
        console.log('Form Data:', formData);
        console.log('Cart Items:', cartItems);
        console.log('=== END MANUAL DEBUG ===');
    });
}

/**
 * Aktualisiert die Anzeige der Produkte im Warenkorb-Widget.
 * @param {HTMLElement} container - Das HTML-Element, in das die Produktliste gerendert wird.
 */
function updateCartSummaryDisplay(container) {
    // Diese Funktion würde normalerweise von WooCommerce Hooks oder AJAX aktualisiert werden.
    // Hier eine einfache Demo basierend auf den `cartItems`.
    // Annahme: cartItems ist global oder wird anderswoher bezogen.
    // In einer echten Anwendung würden die cartItems dynamisch geladen.

    if (!container || typeof cartItems === 'undefined') return; // 'cartItems' ist im globalen Scope des DOMContentLoaded-Listeners

    container.innerHTML = ''; // Bestehende Elemente leeren

    if (cartItems.length === 0) {
        container.innerHTML = '<p class="text-yprint-text-secondary">Ihr Warenkorb ist leer.</p>';
        return;
    }

    cartItems.forEach(item => {
        const itemEl = document.createElement('div');
        itemEl.className = 'product-summary-item flex justify-between items-center py-2 border-b border-yprint-medium-gray';
        itemEl.innerHTML = `
            <div class="flex items-center">
                <img src="${item.image}" alt="${item.name}" class="w-12 h-12 object-cover rounded mr-3">
                <div>
                    <p class="font-medium text-sm">${item.name}</p>
                    <p class="text-xs text-yprint-text-secondary">Menge: ${item.quantity}</p>
                </div>
            </div>
            <p class="font-medium text-sm">€${(item.price * item.quantity).toFixed(2)}</p>
        `;
        container.appendChild(itemEl);
    });
}


/**
 * Aktualisiert die Preisanzeige in der Warenkorb-Zusammenfassung.
 * @param {HTMLElement} container - Das HTML-Element, in das die Gesamtpreise gerendert werden.
 */
function updateCartTotalsDisplay(container) {
    // Auch diese Funktion würde von WC oder AJAX aktualisiert.
    // Nutzt calculatePrices() für Konsistenz.
    // Annahme: calculatePrices ist global verfügbar.

    if (!container || typeof calculatePrices === 'undefined') return;

    const prices = calculatePrices(); // Verwendet formData.voucher aus dem DOMContentLoaded-Scope

    container.innerHTML = `
        <div class="flex justify-between text-sm mt-3">
            <span>Zwischensumme:</span>
            <span>€${prices.subtotal.toFixed(2)}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span>Versand:</span>
            <span>€${prices.shipping.toFixed(2)}</span>
        </div>
        ${prices.discount > 0 ? `
        <div class="flex justify-between text-sm text-yprint-success">
            <span>Rabatt:</span>
            <span>-€${prices.discount.toFixed(2)}</span>
        </div>` : ''}
        <div class="flex justify-between text-base font-bold mt-2 pt-2 border-t border-yprint-medium-gray">
            <span>Gesamt:</span>
            <span class="text-yprint-blue">€${prices.total.toFixed(2)}</span>
        </div>
    `;
}

// Variable ist bereits global im DOMContentLoaded-Scope definiert, daher keine neue Deklaration nötig
// selectedAddress wird bereits in der document.addEventListener('DOMContentLoaded', function () {...}) definiert

// Neue Funktionen für Adressverwaltung hinzufügen
function populateAddressFields(addressData, type = 'shipping') {
    const prefix = type === 'shipping' ? '' : 'billing_';
    
    document.getElementById(prefix + 'street')?.setAttribute('value', addressData.address_1 || '');
    document.getElementById(prefix + 'housenumber')?.setAttribute('value', addressData.address_2 || '');
    document.getElementById(prefix + 'zip')?.setAttribute('value', addressData.postcode || '');
    document.getElementById(prefix + 'city')?.setAttribute('value', addressData.city || '');
    document.getElementById(prefix + 'country')?.setAttribute('value', addressData.country || 'DE');
    
    // Trigger validation
    validateAddressForm();
}

// Event-Listener für Adressauswahl hinzufügen
document.addEventListener('change', function(e) {
    if (e.target.name === 'selected_address') {
        const addressData = e.target.getAttribute('data-address-data');
        if (addressData) {
            try {
                const parsedData = JSON.parse(addressData);
                populateAddressFields(parsedData, 'shipping');
                
                // Wenn Rechnungsadresse gleich Lieferadresse
                const billingSameCheckbox = document.getElementById('billing-same-as-shipping');
                if (billingSameCheckbox && billingSameCheckbox.checked) {
                    populateAddressFields(parsedData, 'billing');
                }
                
                selectedAddress = parsedData;
            } catch (error) {
                console.error('Error parsing address data:', error);
            }
        }
    }
});

// Beispiel für ein globales Objekt für AJAX-Aufrufe (Platzhalter)
// const YPrintAJAX = {
//     saveAddress: function(shippingData, billingData) {
//         console.log("AJAX: Speichere Adresse", shippingData, billingData);
//         // Hier fetch oder jQuery.ajax an wp_ajax_yprint_save_address
//         return Promise.resolve({success: true}); // Simulierter Erfolg
//     },
//     setPaymentMethod: function(paymentData) {
//         console.log("AJAX: Setze Zahlungsmethode", paymentData);
//         // Hier fetch oder jQuery.ajax an wp_ajax_yprint_set_payment_method
//         return Promise.resolve({success: true});
//     },
//     processCheckout: function(allFormData) {
//         console.log("AJAX: Verarbeite Checkout", allFormData);
//         // Hier fetch oder jQuery.ajax an wp_ajax_yprint_process_checkout
//         return new Promise((resolve) => {
//             setTimeout(() => resolve({success: true, orderId: 'YP-SIM123'}), 2000);
//         });
//     }
// };