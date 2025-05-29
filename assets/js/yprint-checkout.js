// YPrint Checkout JavaScript - Allgemeine Funktionen
// WordPress-kompatibles jQuery Wrapping
jQuery(document).ready(function($) {
    'use strict';
    
    // Globale Variablen und Zustand für den Checkout-Prozess
window.currentStep = 1; // Startet immer mit dem ersten Schritt als Standard
let currentStep = window.currentStep; // Lokale Referenz für Kompatibilität
    const formData = { // Objekt zum Speichern der Formulardaten
        shipping: {},
        billing: {},
        payment: { method: 'paypal' }, // Standard-Zahlungsmethode
        voucher: null,
        isBillingSameAsShipping: true,
    };

    // Reale Warenkorbdaten - werden über AJAX geladen
let cartItems = [];
let cartTotals = {
    subtotal: 0,
    shipping: 0,
    discount: 0,
    total: 0,
    vat: 0
};

// Lade reale Warenkorbdaten beim Initialisieren
async function loadRealCartData() {
    try {
        const response = await fetch(yprint_checkout_params.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'yprint_get_cart_data',
                nonce: yprint_checkout_params.nonce
            })
        });

        const data = await response.json();
        
        if (data.success) {
            cartItems = data.data.items || [];
            cartTotals = data.data.totals || cartTotals;
            
            // UI aktualisieren nach dem Laden der Daten
            updateCartSummaryDisplay(document.getElementById('checkout-cart-summary-items'));
            updateCartTotalsDisplay(document.getElementById('checkout-cart-summary-totals'));
            updatePaymentStepSummary();
            
            console.log('Reale Warenkorbdaten geladen:', cartItems);
        } else {
            console.error('Fehler beim Laden der Warenkorbdaten:', data.data);
        }
    } catch (error) {
        console.error('AJAX-Fehler beim Laden der Warenkorbdaten:', error);
    }
}

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

    // Erweiterte Liste der erforderlichen Felder, einschließlich Namensfelder
    const requiredFields = [
        'first_name', 'last_name', 'street', 'housenumber', 'zip', 'city', 'country'
    ];

    // Prüfen, ob eine gespeicherte Adresse ausgewählt wurde ODER ob die Felder bereits gefüllt sind
// Wenn ja, validieren wir nicht jedes Feld individuell
const selectedAddress = jQuery('.address-card.selected').length > 0;
const filledAddress = jQuery('#street').val() && jQuery('#zip').val() && jQuery('#city').val();

if (selectedAddress || filledAddress) {
    console.log('Eine gespeicherte Adresse ist ausgewählt oder die Felder sind bereits gefüllt. Überspringen der Feldvalidierung.');
    if (btnToPayment) {
        console.log('Aktiviere "Weiter zur Zahlung"-Button, da Adressdaten vorliegen');
        btnToPayment.disabled = false;
    }
    return true;
}

    requiredFields.forEach(fieldId => {
        const field = document.getElementById(fieldId);
        if (field) { // Prüfe, ob das Feld existiert
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add('border-yprint-error');
            } else {
                field.classList.remove('border-yprint-error');
            }
        } else {
            // Debug-Ausgabe: Wenn ein Feld nicht gefunden wird
            console.warn(`Pflichtfeld '${fieldId}' wurde nicht gefunden.`);
        }
    });

    if (!formData.isBillingSameAsShipping && billingAddressFieldsContainer) {
        const requiredBillingFields = [
            'billing_first_name', 'billing_last_name', 'billing_street', 'billing_housenumber', 
            'billing_zip', 'billing_city', 'billing_country'
        ];
        
        requiredBillingFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) { // Prüfe, ob das Feld existiert
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-yprint-error');
                } else {
                    field.classList.remove('border-yprint-error');
                }
            } else {
                console.warn(`Pflichtfeld '${fieldId}' wurde nicht gefunden.`);
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

    // Entfernt - wird vom Address Manager übernommen

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

// Entfernt - wird vollständig vom Address Manager übernommen

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

class YPrintStripeCheckout {
    constructor() {
        this.cardElement = null;
        this.sepaElement = null;
        this.initialized = false;
        
        this.init();
    }

    async init() {
        // Warte auf Stripe Service Initialisierung
        if (typeof yprint_stripe_vars === 'undefined' || !yprint_stripe_vars.publishable_key) {
            this.showStripeError('Stripe-Konfiguration fehlt.');
            return;
        }

        // Initialisiere über zentralen Service
        const success = await window.YPrintStripeService.initialize(yprint_stripe_vars.publishable_key);
        
        if (!success) {
            this.showStripeError('Stripe-Initialisierung fehlgeschlagen.');
            return;
        }

        // Höre auf Service-Events
        window.YPrintStripeService.on('initialized', (data) => {
            this.prepareCardElement();
            this.initialized = true;
        });

        // Falls bereits initialisiert
        if (window.YPrintStripeService.isInitialized()) {
            this.prepareCardElement();
            this.initialized = true;
        }
    }

    prepareCardElement() {
        const elements = window.YPrintStripeService.getElements();
        if (!elements) {
            console.error('YPrint Stripe Checkout: Elements not available');
            return;
        }

        const elementsStyle = {
            base: {
                color: '#1d1d1f',
                fontFamily: '"Roboto", sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#6e6e73'
                }
            },
            invalid: {
                color: '#dc3545',
                iconColor: '#dc3545'
            }
        };

        this.cardElement = elements.create('card', { 
            style: elementsStyle,
            hidePostalCode: true
        });

        this.sepaElement = elements.create('iban', {
            style: elementsStyle,
            supportedCountries: ['SEPA'],
            placeholderCountry: 'DE'
        });
    }

    initSepaElement() {
        if (!this.initialized || !this.sepaElement) {
            console.warn('YPrint Stripe Checkout: Not initialized or SEPA element not available');
            return;
        }

        const sepaElementContainer = document.getElementById('stripe-sepa-element');
        if (!sepaElementContainer) {
            console.error('YPrint Stripe Checkout: SEPA element container not found');
            return;
        }

        try {
            // Prüfen ob bereits gemounted - bessere Prüfung
if (sepaElementContainer.querySelector('.StripeElement')) {
    console.log('YPrint Stripe Checkout: SEPA element already mounted');
    return;
}

// Container leeren bevor mounting
sepaElementContainer.innerHTML = '';

this.sepaElement.mount('#stripe-sepa-element');
            console.log('YPrint Stripe Checkout: SEPA element mounted successfully');

            // Error handling für SEPA
            this.sepaElement.on('change', (event) => {
                const displayError = document.getElementById('stripe-sepa-errors');
                if (displayError) {
                    if (event.error) {
                        displayError.textContent = event.error.message;
                        displayError.style.display = 'block';
                    } else {
                        displayError.textContent = '';
                        displayError.style.display = 'none';
                    }
                }
            });

        } catch (error) {
            console.error('YPrint Stripe Checkout: SEPA element mount failed:', error);
            this.showStripeError('SEPA-Element konnte nicht geladen werden.');
        }
    }

    initCardElement() {
        if (!this.initialized || !this.cardElement) {
            console.warn('YPrint Stripe Checkout: Not initialized or card element not available');
            return;
        }
    
        const cardElementContainer = document.getElementById('stripe-card-element');
        if (!cardElementContainer) {
            console.error('YPrint Stripe Checkout: Card element container not found');
            return;
        }
    
        try {
            // Prüfen ob bereits gemounted - bessere Prüfung
            if (cardElementContainer.querySelector('.StripeElement')) {
                console.log('YPrint Stripe Checkout: Card element already mounted');
                return;
            }
    
            // Container leeren bevor mounting
            cardElementContainer.innerHTML = '';
            
            this.cardElement.mount('#stripe-card-element');
            // Card Element erfolgreich gemountet

            // Error handling
            this.cardElement.on('change', (event) => {
                const displayError = document.getElementById('stripe-card-errors');
                if (displayError) {
                    if (event.error) {
                        displayError.textContent = event.error.message;
                        displayError.style.display = 'block';
                    } else {
                        displayError.textContent = '';
                        displayError.style.display = 'none';
                    }
                }
            });

        } catch (error) {
            this.showStripeError('Kartenelement konnte nicht geladen werden.');
        }
    }

    showStripeError(message) {
        const stripeContainers = document.querySelectorAll('#stripe-card-element, .stripe-payment-element-container');
        stripeContainers.forEach(el => {
            el.innerHTML = `<p class="text-red-600 text-sm">${message}</p>`;
        });
    }

    // Öffentliche Methode für Payment Processing (Phase 3)
    async createPaymentMethod() {
        const stripe = window.YPrintStripeService.getStripe();
        if (!stripe || !this.cardElement) {
            throw new Error('Stripe not initialized');
        }

        const { paymentMethod, error } = await stripe.createPaymentMethod({
            type: 'card',
            card: this.cardElement,
        });

        if (error) {
            throw error;
        }

        return paymentMethod;
    }
}

// Global verfügbar machen
window.YPrintStripeCheckout = new YPrintStripeCheckout();

document.addEventListener('DOMContentLoaded', function () {
    console.log('YPrint Stripe Checkout JS loaded');
});

    /**
 * Zeigt den angegebenen Checkout-Schritt an und aktualisiert die Fortschrittsanzeige.
 * @param {number} stepNumber - Die Nummer des anzuzeigenden Schritts (1-basiert, z.B. 1 für Adresse, 2 für Zahlung).
 */
function showStep(stepNumber) {
    // Konstruiere die erwartete ID für den aktiven Schritt (z.B. "step-2")
    const targetStepId = `step-${stepNumber}`;

    // Finde das Element über seine ID, nicht über den Index der NodeList.
    steps.forEach((stepEl) => {
        if (stepEl.id === targetStepId) {
            stepEl.classList.add('active');
            stepEl.style.display = 'block';
        } else {
            stepEl.classList.remove('active');
            stepEl.style.display = 'none';
        }
    });

    // Progress Bar aktualisieren
    progressSteps.forEach((pStep, index) => {
        pStep.classList.remove('active', 'completed');
        if (index < stepNumber - 1) {
            pStep.classList.add('completed');
        } else if (index === stepNumber - 1) {
            pStep.classList.add('active');
        }
    });

    currentStep = stepNumber;
    window.scrollTo({ top: 0, behavior: 'smooth' });
    
    // Address Manager reinitialisieren wenn zu Schritt 1 zurückgekehrt wird
    if (stepNumber === 1 && window.YPrintAddressManager) {
        console.log('Reinitialisiere Address Manager für Schritt 1');
        // Address Manager neu laden
        setTimeout(() => {
            if (window.YPrintAddressManager.loadSavedAddresses) {
                window.YPrintAddressManager.loadSavedAddresses();
            }
        }, 100);
    }

    // Sammle Daten wenn zum Zahlungsschritt gewechselt wird
    if (stepNumber === 2) {
        collectAddressData();
        updatePaymentStepSummary();
    } else if (stepNumber === 3) {
        collectPaymentData();
        populateConfirmation();
    }
}

// Die showStep-Funktion global verfügbar machen
window.showStep = showStep;

    /**
 * Sammelt die Adressdaten aus dem Formular.
 */
function collectAddressData() {
    if (!addressForm) return;

    // Sammle Lieferadressdaten, einschließlich Vor- und Nachname
    formData.shipping.first_name = document.getElementById('first_name')?.value || '';
    formData.shipping.last_name = document.getElementById('last_name')?.value || '';
    formData.shipping.street = document.getElementById('street')?.value || '';
    formData.shipping.housenumber = document.getElementById('housenumber')?.value || '';
    formData.shipping.zip = document.getElementById('zip')?.value || '';
    formData.shipping.city = document.getElementById('city')?.value || '';
    formData.shipping.country = document.getElementById('country')?.value || '';
    formData.shipping.phone = document.getElementById('phone')?.value || '';

    if (formData.isBillingSameAsShipping) {
        formData.billing = { ...formData.shipping }; // Kopiert die Lieferadresse
    } else {
        formData.billing.first_name = document.getElementById('billing_first_name')?.value || '';
        formData.billing.last_name = document.getElementById('billing_last_name')?.value || '';
        formData.billing.street = document.getElementById('billing_street')?.value || '';
        formData.billing.housenumber = document.getElementById('billing_housenumber')?.value || '';
        formData.billing.zip = document.getElementById('billing_zip')?.value || '';
        formData.billing.city = document.getElementById('billing_city')?.value || '';
        formData.billing.country = document.getElementById('billing_country')?.value || '';
    }
    if (YPrintAddressManager && YPrintAddressManager.shouldSaveNewAddress && YPrintAddressManager.shouldSaveNewAddress()) {
            // Adresse speichern mit Kontext
jQuery.ajax({
    url: yprint_checkout_params.ajax_url,
    type: 'POST',
    data: {
        action: 'yprint_save_address',
        context: 'checkout', // Kontext hinzufügen
        yprint_address_nonce: yprint_address_ajax.nonce,
        security: yprint_checkout_params.nonce,
        first_name: formData.shipping.first_name || '',
        last_name: formData.shipping.last_name || '',
        company: formData.shipping.company || '',
        address_1: formData.shipping.street || '',
        address_2: formData.shipping.housenumber || '',
        postcode: formData.shipping.zip || '',
        city: formData.shipping.city || '',
        country: formData.shipping.country || 'DE'
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
        // Zahlungsdaten sind gesammelt und bereit für Verarbeitung
    }

    /**
     * Berechnet die Preise (Zwischensumme, Versand, Rabatt, Gesamt).
     * @returns {object} - Ein Objekt mit den berechneten Preisen.
     */
    function calculatePrices() {
        // Verwende reale Warenkorbdaten aus WooCommerce
        return {
            subtotal: cartTotals.subtotal || 0,
            shipping: cartTotals.shipping || 0,
            discount: cartTotals.discount || 0,
            total: cartTotals.total || 0,
            vat: cartTotals.vat || 0
        };
    }
    
    // Neue Funktion um Preise live zu aktualisieren
    async function refreshCartTotals() {
        try {
            const response = await fetch(yprint_checkout_params.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'yprint_refresh_cart_totals',
                    nonce: yprint_checkout_params.nonce,
                    voucher_code: formData.voucher || ''
                })
            });
    
            const data = await response.json();
            
            if (data.success) {
                cartTotals = data.data.totals;
                updatePaymentStepSummary();
                updateCartTotalsDisplay(document.getElementById('checkout-cart-summary-totals'));
            }
        } catch (error) {
            console.error('Fehler beim Aktualisieren der Preise:', error);
        }
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


    async function populateConfirmation() {
        // Lade aktuelle Warenkorbdaten
        await loadRealCartData();
        
        // Adressen (bestehender Code bleibt)
        const confirmShippingAddressEl = document.getElementById('confirm-shipping-address');
        if (confirmShippingAddressEl) {
            confirmShippingAddressEl.innerHTML = `
                ${formData.shipping.first_name || ''} ${formData.shipping.last_name || ''}<br>
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
                    ${formData.billing.first_name || ''} ${formData.billing.last_name || ''}<br>
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
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 
                                  document.getElementById('selected-payment-method')?.value;
            
            let paymentMethodText = 'Nicht gewählt';
            
            if (selectedMethod?.includes('stripe_card')) {
                paymentMethodText = '<i class="fas fa-credit-card mr-2"></i> Kreditkarte (Stripe)';
            } else if (selectedMethod?.includes('stripe_sepa')) {
                paymentMethodText = '<i class="fas fa-university mr-2"></i> SEPA-Lastschrift (Stripe)';
            } else if (selectedMethod?.includes('paypal')) {
                paymentMethodText = '<i class="fab fa-paypal mr-2"></i> PayPal';
            } else if (selectedMethod?.includes('applepay')) {
                paymentMethodText = '<i class="fab fa-apple-pay mr-2"></i> Apple Pay';
            }
            
            confirmPaymentMethodEl.innerHTML = paymentMethodText;
        }
    
        // Produkte mit Design-Unterstützung
        const productListEl = document.getElementById('confirm-product-list');
        if (productListEl) {
            productListEl.innerHTML = '';
            
            cartItems.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'flex justify-between items-center py-3 border-b border-gray-100';
                
                // Design-Details für Design-Produkte
                let designDetailsHtml = '';
                if (item.is_design_product && item.design_details && item.design_details.length > 0) {
                    designDetailsHtml = `
                        <div class="design-details mt-1">
                            ${item.design_details.map(detail => 
                                `<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded mr-1">${detail}</span>`
                            ).join('')}
                        </div>
                    `;
                }
                
                itemEl.innerHTML = `
                    <div class="flex items-center flex-1">
                        <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded border mr-3">
                        <div class="flex-1">
                            <p class="font-medium text-sm">${item.name}</p>
                            <p class="text-xs text-gray-600">Menge: ${item.quantity}</p>
                            ${designDetailsHtml}
                        </div>
                    </div>
                    <p class="font-medium text-sm">€${(item.price * item.quantity).toFixed(2)}</p>
                `;
                productListEl.appendChild(itemEl);
            });
        }
    
        // Preise - verwende reale Daten
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
        btnToConfirmation.addEventListener('click', async (e) => {
            e.preventDefault();
            
            // Validiere Zahlungsmethode
            const paymentValid = await validatePaymentMethod();
            
            if (!paymentValid) {
                showMessage('Bitte wählen Sie eine gültige Zahlungsmethode aus oder vervollständigen Sie die Zahlungsdaten.', 'error');
                return;
            }
            
            collectPaymentData();
            await populateConfirmation(); // Jetzt async
            showStep(3);
        });
    }
    
    // Neue Funktion zur Validierung der Zahlungsmethode
    async function validatePaymentMethod() {
        const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 
                              document.getElementById('selected-payment-method')?.value;
        
        if (!selectedMethod) {
            return false;
        }
        
        // Stripe-spezifische Validierung
        if (selectedMethod.includes('stripe')) {
            if (window.YPrintStripeCheckout && window.YPrintStripeCheckout.initialized) {
                // Prüfe ob Stripe Elements vollständig ausgefüllt sind
                const activeSliderOption = document.querySelector('.slider-option.active');
                const paymentMethod = activeSliderOption?.dataset.method;
                
                if (paymentMethod === 'card') {
                    // Prüfe Card Element
                    return await validateStripeCardElement();
                } else if (paymentMethod === 'sepa') {
                    // Prüfe SEPA Element  
                    return await validateStripeSepaElement();
                }
            }
            return false;
        }
        
        return true; // Andere Zahlungsmethoden sind ok wenn ausgewählt
    }
    
    async function validateStripeCardElement() {
        if (!window.YPrintStripeCheckout.cardElement) return false;
        
        try {
            // Teste ob Card Element gültig ist
            const {error} = await window.YPrintStripeCheckout.stripe.createPaymentMethod({
                type: 'card',
                card: window.YPrintStripeCheckout.cardElement,
            });
            
            return !error;
        } catch (e) {
            return false;
        }
    }
    
    async function validateStripeSepaElement() {
        if (!window.YPrintStripeCheckout.sepaElement) return false;
        
        try {
            // Teste ob SEPA Element gültig ist
            const {error} = await window.YPrintStripeCheckout.stripe.createPaymentMethod({
                type: 'sepa_debit',
                sepa_debit: window.YPrintStripeCheckout.sepaElement,
            });
            
            return !error;
        } catch (e) {
            return false;
        }
    }

    if (btnBackToPaymentFromConfirm) {
        btnBackToPaymentFromConfirm.addEventListener('click', (e) => {
            e.preventDefault();
            updatePaymentStepSummary();
            showStep(2);
        });
    }

    if (btnBuyNow) {
        btnBuyNow.addEventListener('click', async (e) => {
            e.preventDefault();
            
            toggleLoadingOverlay(true);
            
            try {
                // Echte Bestellverarbeitung
                const orderResult = await processRealOrder();
                
                if (orderResult.success) {
                    populateThankYouPage(orderResult.data);
                    showStep(4);
                    
                    // Fortschrittsanzeige für Danke-Seite anpassen
                    progressSteps.forEach(pStep => pStep.classList.add('completed'));
                    const lastProgressStep = document.getElementById('progress-step-3');
                    if (lastProgressStep) {
                        lastProgressStep.classList.remove('active');
                        lastProgressStep.classList.add('completed');
                    }
                    
                    showMessage('Bestellung erfolgreich aufgegeben!', 'success');
                } else {
                    throw new Error(orderResult.message || 'Bestellung konnte nicht verarbeitet werden');
                }
            } catch (error) {
                console.error('Bestellfehler:', error);
                showMessage(error.message || 'Ein Fehler ist aufgetreten', 'error');
            } finally {
                toggleLoadingOverlay(false);
            }
        });
    }
    
    // Neue Funktion für echte Bestellverarbeitung
    async function processRealOrder() {
        try {
            const response = await fetch(yprint_checkout_params.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'yprint_process_final_checkout',
                    nonce: yprint_checkout_params.nonce,
                    shipping_data: JSON.stringify(formData.shipping),
                    billing_data: JSON.stringify(formData.billing),
                    payment_data: JSON.stringify(formData.payment),
                    billing_same_as_shipping: formData.isBillingSameAsShipping,
                    voucher_code: formData.voucher || '',
                    payment_method: document.getElementById('selected-payment-method')?.value || 
                                   document.querySelector('input[name="payment_method"]:checked')?.value
                })
            });
    
            const data = await response.json();
            return data;
            
        } catch (error) {
            console.error('AJAX-Fehler bei Bestellverarbeitung:', error);
            return {
                success: false,
                message: 'Verbindungsfehler. Bitte versuchen Sie es erneut.'
            };
        }
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


// Zeige den initialen Schritt an
const urlParams = new URLSearchParams(window.location.search);
const stepParam = urlParams.get('step');
let initialStep = 1;
if (stepParam === 'payment') initialStep = 2;
if (stepParam === 'confirmation') initialStep = 3;
if (stepParam === 'thankyou') initialStep = 4;

showStep(initialStep);

// Weitere Initialisierungen je nach aktuellem Schritt
if (initialStep === 1) {
    validateAddressForm();
} else if (initialStep === 2) {
    collectAddressData();
    updatePaymentStepSummary();
} else if (initialStep === 3) {
    collectAddressData();
    collectPaymentData();
    populateConfirmation();
} else if (initialStep === 4) {
    populateThankYouPage();
    progressSteps.forEach(pStep => pStep.classList.add('completed'));
    const lastProgressStep = document.getElementById('progress-step-3');
    if (lastProgressStep) {
        lastProgressStep.classList.remove('active');
        lastProgressStep.classList.add('completed');
    }
}

// Lade reale Warenkorbdaten beim Start
loadRealCartData().then(() => {
    // Warenkorb-Zusammenfassung nach dem Laden der Daten aktualisieren
    const cartSummaryContainer = document.getElementById('checkout-cart-summary-items');
    if (cartSummaryContainer) {
        updateCartSummaryDisplay(cartSummaryContainer);
    }
    const cartTotalsContainer = document.getElementById('checkout-cart-summary-totals');
    if (cartTotalsContainer) {
        updateCartTotalsDisplay(cartTotalsContainer);
    }
});
}); // Ende jQuery Document Ready

// Debug-Button entfernt

/**
 * Aktualisiert die Anzeige der Produkte im Warenkorb-Widget mit Design-Unterstützung.
 * @param {HTMLElement} container - Das HTML-Element, in das die Produktliste gerendert wird.
 */
function updateCartSummaryDisplay(container) {
    if (!container || typeof cartItems === 'undefined') return;

    container.innerHTML = ''; // Bestehende Elemente leeren

    if (cartItems.length === 0) {
        container.innerHTML = '<p class="text-yprint-text-secondary">Ihr Warenkorb ist leer.</p>';
        return;
    }

    cartItems.forEach(item => {
        const itemEl = document.createElement('div');
        const isDesignProduct = item.is_design_product || false;
        const designClass = isDesignProduct ? ' design-product-item' : '';
        
        itemEl.className = `product-summary-item flex justify-between items-center py-2 border-b border-yprint-medium-gray${designClass}`;
        
        // Design-Details aufbereiten
        let designDetailsHtml = '';
        if (item.design_details && item.design_details.length > 0) {
            designDetailsHtml = `
                <div class="design-details" style="margin-top: 4px; display: flex; flex-wrap: wrap; gap: 4px;">
                    ${item.design_details.map(detail => 
                        `<span class="design-detail" style="font-size: 0.7em; color: var(--yprint-blue); background: rgba(0, 121, 255, 0.1); padding: 2px 6px; border-radius: 10px; border: 1px solid rgba(0, 121, 255, 0.2);">${detail}</span>`
                    ).join('')}
                </div>
            `;
        }
        
        // Design-Badge für Design-Produkte
        const designBadge = isDesignProduct ? 
            `<div class="design-badge" style="position: absolute; top: -5px; right: -5px; background: var(--yprint-blue); color: white; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; font-size: 9px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" title="Design-Produkt">
                <i class="fas fa-palette"></i>
            </div>` : '';
        
        // Stückpreis anzeigen wenn Menge > 1
        const unitPrice = item.quantity > 1 ? 
            `<span class="unit-price" style="font-size: 0.7em; color: var(--yprint-text-secondary); margin-left: 4px;">
                (€${item.price.toFixed(2)} / Stk.)
            </span>` : '';
        
        itemEl.innerHTML = `
            <div class="flex items-center">
                <div class="item-image-container" style="position: relative; margin-right: 12px; flex-shrink: 0;">
                    <img src="${item.image}" alt="${item.name}" class="w-12 h-12 object-cover rounded border border-gray-200 bg-white">
                    ${designBadge}
                </div>
                <div>
                    <p class="font-medium text-sm" style="line-height: 1.3; color: var(--yprint-black);">${item.name}</p>
                    <p class="text-xs text-yprint-text-secondary">Menge: ${item.quantity}</p>
                    ${designDetailsHtml}
                </div>
            </div>
            <div class="item-price-container" style="display: flex; flex-direction: column; align-items: flex-end; justify-content: center;">
                <p class="font-medium text-sm">€${(item.price * item.quantity).toFixed(2)}</p>
                ${unitPrice}
            </div>
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

// Integration mit Address Manager statt eigene Handler
if (window.YPrintAddressManager) {
    // Event-Integration mit Address Manager
    jQuery(document).on('address_selected', function(event, addressData) {
        console.log('Adresse vom Address Manager ausgewählt:', addressData);
        validateAddressForm(); // Button-Status aktualisieren
    });
}

// Debugging-Hilfsfunktion
function logCheckoutState() {
    console.log('=== CHECKOUT STATE ===');
    console.log('Aktueller Schritt:', window.currentStep || currentStep || 'undefined');
    console.log('Formular-Gültigkeit:', validateAddressForm());
    console.log('Button-Status:', jQuery('#btn-to-payment').prop('disabled'));
    console.log('Adressfelder:');
    console.log('- Street:', jQuery('#street').val());
    console.log('- Housenumber:', jQuery('#housenumber').val());
    console.log('- ZIP:', jQuery('#zip').val());
    console.log('- City:', jQuery('#city').val());
    console.log('- Country:', jQuery('#country').val());
    console.log('====================');
}

// Rufe die Debug-Funktion initial auf
setTimeout(logCheckoutState, 1000);

// Vereinfachter Payment Method Slider Handler
function initPaymentSlider() {
    console.log('Initializing payment slider...');
    
    // Prüfe ob Slider vorhanden ist
    const sliderOptions = document.querySelectorAll('.slider-option');
    console.log('Found slider options:', sliderOptions.length);
    
    if (sliderOptions.length === 0) {
        console.error('No slider options found!');
        return;
    }
    
    // ENTFERNE alle bestehenden Event-Listener komplett
    jQuery('.slider-option').off('click.paymentSlider');
    
    // EINZIGER Event-Handler mit jQuery (stabiler als native Events)
    jQuery(document).on('click.paymentSlider', '.slider-option', function(e) {
        e.preventDefault();
        e.stopPropagation();
        
        console.log('DEBUG: Slider option clicked:', jQuery(this).data('method'));
        
        if (jQuery(this).hasClass('active')) {
            console.log('DEBUG: Option already active, skipping');
            return;
        }
        
        const selectedMethod = jQuery(this).data('method');
        console.log('DEBUG: Switching to payment method:', selectedMethod);
        
        // Update Slider UI
        jQuery('.slider-option').removeClass('active');
        jQuery(this).addClass('active');
        
        // Update Indicator mit Animation
        const indicator = jQuery('.slider-indicator');
        if (selectedMethod === 'sepa') {
            indicator.addClass('sepa');
        } else {
            indicator.removeClass('sepa');
        }
        
        // Update Hidden Input
        const methodValue = selectedMethod === 'card' ? 'yprint_stripe_card' : 'yprint_stripe_sepa';
        jQuery('#selected-payment-method').val(methodValue);
        console.log('DEBUG: Payment method value set to:', methodValue);
        
        // Switch Payment Fields mit Verzögerung für Animation
        jQuery('.payment-input-fields').removeClass('active');
        
        setTimeout(() => {
            if (selectedMethod === 'card') {
                jQuery('#card-payment-fields').addClass('active');
                console.log('DEBUG: Showing card fields');
                // Stripe Card Element initialisieren
                setTimeout(() => {
                    if (window.YPrintStripeCheckout && window.YPrintStripeCheckout.initCardElement) {
                        console.log('DEBUG: Initializing Stripe Card Element');
                        window.YPrintStripeCheckout.initCardElement();
                    }
                }, 100);
            } else if (selectedMethod === 'sepa') {
                jQuery('#sepa-payment-fields').addClass('active');
                console.log('DEBUG: Showing SEPA fields');
                // Stripe SEPA Element initialisieren
                setTimeout(() => {
                    if (window.YPrintStripeCheckout && window.YPrintStripeCheckout.initSepaElement) {
                        console.log('DEBUG: Initializing Stripe SEPA Element');
                        window.YPrintStripeCheckout.initSepaElement();
                    }
                }, 100);
            }
        }, 50);
        
        console.log('DEBUG: Slider switch completed');
    });
    
    console.log('Payment slider initialized successfully');
}

// Intelligente Zahlungsmethodenerkennung (für späteren Ausbau)
function detectPaymentMethod() {
    const cardElement = window.YPrintStripeCheckout?.cardElement;
    const sepaElement = window.YPrintStripeCheckout?.sepaElement;
    
    // Hier könnte später Logic für automatische Erkennung basierend auf ausgefüllten Feldern
    // implementiert werden
    return jQuery('#selected-payment-method').val();
}

// Sichere Initialisierung mit Stripe-Check
jQuery(document).ready(function() {
    console.log('DOM ready - initializing payment systems');
    
    // Funktion um auf Stripe zu warten
    function waitForStripe(callback, maxAttempts = 10) {
        let attempts = 0;
        
        function checkStripe() {
            attempts++;
            
            if (typeof Stripe !== 'undefined' && window.YPrintStripeCheckout) {
                console.log('Stripe and YPrintStripeCheckout ready after', attempts, 'attempts');
                callback();
            } else if (attempts < maxAttempts) {
                console.log('Waiting for Stripe... attempt', attempts);
                setTimeout(checkStripe, 500);
            } else {
                console.warn('Stripe initialization timeout after', maxAttempts, 'attempts');
                // Initialisiere trotzdem den Payment Slider (ohne Stripe Elements)
                initPaymentSlider();
            }
        }
        
        checkStripe();
    }
    
    // Warte auf Stripe und initialisiere dann
    waitForStripe(() => {
        // Payment Slider initialisieren
        initPaymentSlider();
        
        // Initial Card Element mounten (da Karte standardmäßig aktiv ist)
        setTimeout(() => {
            if (window.YPrintStripeCheckout && window.YPrintStripeCheckout.initCardElement) {
                console.log('DEBUG: Initial Card Element mounting');
                window.YPrintStripeCheckout.initCardElement();
            }
        }, 100);
        
        // Debug: Prüfe vorhandene Elemente
        console.log('DEBUG: Payment slider elements found:', document.querySelectorAll('.slider-option').length);
        console.log('DEBUG: Payment fields found:', document.querySelectorAll('.payment-input-fields').length);
        console.log('DEBUG: Slider indicator found:', document.querySelector('.slider-indicator') ? 'Yes' : 'No');
    });
});

// Express Payment Integration bleibt bestehen
function initExpressPaymentIntegration() {
    if (window.YPrintExpressCheckout) {
        console.log('Express Checkout available, integrating with main checkout...');
    } else {
        console.log('Express Checkout not available');
    }
}

// Nach DOM Ready Express Payment integrieren
jQuery(document).ready(function() {
    setTimeout(initExpressPaymentIntegration, 1000);
});

// Express Payment Integration
function initExpressPaymentIntegration() {
    // Warte auf Express Checkout Initialisierung
    if (window.YPrintExpressCheckout) {
        console.log('Express Checkout available, integrating with main checkout...');
        // Weitere Integration kann hier hinzugefügt werden
    } else {
        console.log('Express Checkout not available');
    }
}

// jQuery ready function - korrekte WordPress-kompatible Syntax
jQuery(document).ready(function($) {
    console.log('DOM ready - initializing payment systems');

    // Payment Slider initialisieren
    initPaymentSlider();

    // Nach DOM Ready Express Payment integrieren
    setTimeout(initExpressPaymentIntegration, 1000);

    // Initial payment method check
    const initialMethod = jQuery('input[name="payment_method"]:checked').val();
    if (initialMethod === 'yprint_stripe') {
        jQuery('#stripe-card-element-container').show();
    }

    // Kreditkarte ist bereits als aktiv markiert im HTML
    setTimeout(() => {
        if (window.YPrintStripeCheckout && window.YPrintStripeCheckout.initCardElement) {
            window.YPrintStripeCheckout.initCardElement();
        }
    }, 500);

    // Debug: Prüfe vorhandene Elemente
    console.log('Payment slider elements found:', document.querySelectorAll('.slider-option').length);
    console.log('Payment fields found:', document.querySelectorAll('.payment-input-fields').length);
    console.log('Slider indicator found:', document.querySelector('.slider-indicator') ? 'Yes' : 'No');
});

// Debug-Button entfernt

// Entfernt - wird vom Address Manager übernommen

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