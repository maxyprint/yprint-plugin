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

// Performance-optimierte Warenkorbdaten mit Cache
let cartDataCache = null;
let cartDataCacheTime = 0;
const CACHE_DURATION = 30000; // 30 Sekunden Cache

async function loadRealCartData(forceRefresh = false) {
    // Cache prüfen für bessere Performance
    if (!forceRefresh && cartDataCache && (Date.now() - cartDataCacheTime) < CACHE_DURATION) {
        console.log('Verwende Cache für Warenkorbdaten');
        applyCartData(cartDataCache);
        return;
    }

    try {
        const response = await fetch(yprint_checkout_params.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'yprint_get_cart_data',
                nonce: yprint_checkout_params.nonce,
                minimal: isMinimalLoadNeeded() ? '1' : '0' // Neue Option für reduzierte Daten
            })
        });

        const data = await response.json();
        
        if (data.success) {
            // Cache aktualisieren
            cartDataCache = data.data;
            cartDataCacheTime = Date.now();
            
            applyCartData(data.data);
            console.log('Zentrale Warenkorbdaten geladen:', cartItems);
        } else {
            console.error('Fehler beim Laden der Warenkorbdaten:', data.data);
        }
    } catch (error) {
        console.error('AJAX-Fehler beim Laden der Warenkorbdaten:', error);
    }
}

function applyCartData(data) {
    cartItems = data.items || [];
    cartTotals = data.totals || cartTotals;
    
    // Nur relevante UI-Updates durchführen
    if (data.context) {
        window.checkoutContext = data.context;
    }
    
    // UI-Updates nur wenn Elemente sichtbar sind
    const summaryElement = document.getElementById('checkout-cart-summary-items');
    if (summaryElement && isElementVisible(summaryElement)) {
        updateCartSummaryDisplay(summaryElement);
    }
    
    const totalsElement = document.getElementById('checkout-cart-summary-totals');
    if (totalsElement && isElementVisible(totalsElement)) {
        updateCartTotalsDisplay(totalsElement);
    }
    
    // Payment Summary nur auf Checkout-Seite
    if (isCheckoutPage()) {
        updatePaymentStepSummary();
    }
    
    // Express Payment nur wenn verfügbar und benötigt
    if (window.YPrintExpressCheckout && window.checkoutContext?.express_payment) {
        window.YPrintExpressCheckout.updateAmount(window.checkoutContext.express_payment.total.amount);
    }
}

function isMinimalLoadNeeded() {
    // Auf nicht-Checkout-Seiten nur minimale Daten laden
    return !isCheckoutPage() && !isCartPage();
}

function isElementVisible(element) {
    return element && element.offsetParent !== null;
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
        console.log('=== YPrintStripeCheckout Constructor START ===');
        this.cardElement = null;
        this.sepaElement = null;
        this.initialized = false;
        this.initializationPromise = null;
        
        console.log('Constructor - Initial state:', {
            cardElement: this.cardElement,
            sepaElement: this.sepaElement,
            initialized: this.initialized
        });
        
        // Nicht sofort init() aufrufen - das verursacht Race Conditions
        console.log('=== YPrintStripeCheckout Constructor END ===');
    }

    async init() {
        console.log('=== DEBUG: YPrintStripeCheckout.init START ===');
        
        // Verhindere mehrfache Initialisierung
        if (this.initializationPromise) {
            console.log('DEBUG: Init already in progress, waiting...');
            return await this.initializationPromise;
        }
        
        if (this.initialized) {
            console.log('DEBUG: Already initialized');
            return true;
        }
        
        // Erstelle Promise für Initialisierung
        this.initializationPromise = this._performInit();
        const result = await this.initializationPromise;
        this.initializationPromise = null;
        
        return result;
    }
    
    async _performInit() {
        console.log('=== DEBUG: _performInit START ===');
        
        // Prüfe Stripe-Konfiguration
        console.log('DEBUG: Checking yprint_stripe_vars...');
        console.log('DEBUG: typeof yprint_stripe_vars:', typeof yprint_stripe_vars);
        console.log('DEBUG: yprint_stripe_vars exists:', typeof yprint_stripe_vars !== 'undefined');
        
        if (typeof yprint_stripe_vars === 'undefined' || !yprint_stripe_vars.publishable_key) {
            console.error('DEBUG: Stripe-Konfiguration fehlt');
            console.error('DEBUG: yprint_stripe_vars:', typeof yprint_stripe_vars !== 'undefined' ? yprint_stripe_vars : 'undefined');
            this.showStripeError('Stripe-Konfiguration fehlt.');
            return false;
        }
        
        console.log('DEBUG: Stripe config OK, publishable_key length:', yprint_stripe_vars.publishable_key.length);

        // Warte auf YPrintStripeService
        console.log('DEBUG: Checking YPrintStripeService availability...');
        console.log('DEBUG: window.YPrintStripeService exists:', !!window.YPrintStripeService);
        
        if (!window.YPrintStripeService) {
            console.error('DEBUG: YPrintStripeService not available');
            this.showStripeError('Stripe Service nicht verfügbar.');
            return false;
        }
        
        console.log('DEBUG: YPrintStripeService available, checking if already initialized...');
        console.log('DEBUG: YPrintStripeService.isInitialized():', window.YPrintStripeService.isInitialized());
        
        // Initialisiere Service falls nötig
        if (!window.YPrintStripeService.isInitialized()) {
            console.log('DEBUG: Initializing Stripe Service...');
            
            const success = await window.YPrintStripeService.initialize(yprint_stripe_vars.publishable_key);
            console.log('DEBUG: Service initialization result:', success);
            
            if (!success) {
                console.error('DEBUG: Stripe Service Initialisierung fehlgeschlagen');
                this.showStripeError('Stripe Service Initialisierung fehlgeschlagen.');
                return false;
            }
        } else {
            console.log('DEBUG: Stripe Service already initialized');
        }

        // Setze initialized flag
        this.initialized = true;
        console.log('DEBUG: YPrintStripeCheckout.initialized set to:', this.initialized);

        // Erstelle Elements mit ausführlichem Logging
        console.log('DEBUG: About to create Stripe Elements...');
        const elementsCreated = await this._createStripeElements();
        
        if (!elementsCreated) {
            console.error('DEBUG: Failed to create Stripe Elements');
            this.initialized = false;
            return false;
        }
        
        console.log('=== DEBUG: _performInit END ===');
        console.log('DEBUG: Final state - cardElement:', !!this.cardElement, 'sepaElement:', !!this.sepaElement);
        
        return true;
    }
    
    async _createStripeElements() {
        console.log('=== DEBUG: _createStripeElements START ===');
        
        const maxAttempts = 3;
        let attempts = 0;
        
        while (attempts < maxAttempts) {
            attempts++;
            console.log(`DEBUG: Creating elements, attempt ${attempts}/${maxAttempts}`);
            
            try {
                const success = this.prepareCardElement();
                console.log(`DEBUG: prepareCardElement attempt ${attempts} result:`, success);
                
                if (success && this.cardElement && this.sepaElement) {
                    console.log('DEBUG: Elements successfully created on attempt', attempts);
                    return true;
                }
                
                console.log(`DEBUG: Attempt ${attempts} failed, cardElement:`, !!this.cardElement, 'sepaElement:', !!this.sepaElement);
                
                if (attempts < maxAttempts) {
                    console.log('DEBUG: Waiting before retry...');
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }
                
            } catch (error) {
                console.error(`DEBUG: Error on attempt ${attempts}:`, error);
                console.error('DEBUG: Error stack:', error.stack);
                
                if (attempts < maxAttempts) {
                    await new Promise(resolve => setTimeout(resolve, 1000));
                }
            }
        }
        
        console.error('DEBUG: Failed to create elements after', maxAttempts, 'attempts');
        
        // Letzter Verzweiflungsversuch mit direkter API
        console.log('DEBUG: Attempting direct element creation...');
        return await this._directElementCreation();
    }
    
    async _directElementCreation() {
        console.log('=== DEBUG: _directElementCreation START ===');
        
        try {
            const stripe = window.YPrintStripeService.getStripe();
            const elements = window.YPrintStripeService.getElements();
            
            console.log('DEBUG: Direct - stripe available:', !!stripe);
            console.log('DEBUG: Direct - elements available:', !!elements);
            console.log('DEBUG: Direct - stripe type:', typeof stripe);
            console.log('DEBUG: Direct - elements type:', typeof elements);
            
            if (!stripe || !elements) {
                console.error('DEBUG: Direct - Stripe or Elements not available');
                return false;
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
            
            // Direkte Element-Erstellung
            console.log('DEBUG: Direct - Creating card element...');
            this.cardElement = elements.create('card', { 
                style: elementsStyle,
                hidePostalCode: true
            });
            console.log('DEBUG: Direct - Card element created:', !!this.cardElement);
            
            console.log('DEBUG: Direct - Creating SEPA element...');
            this.sepaElement = elements.create('iban', {
                style: elementsStyle,
                supportedCountries: ['SEPA'],
                placeholderCountry: 'DE'
            });
            console.log('DEBUG: Direct - SEPA element created:', !!this.sepaElement);
            
            // Event-Handler hinzufügen
            if (this.cardElement) {
                this.setupCardElementEvents();
                console.log('DEBUG: Direct - Card element events set up');
            }
            
            console.log('=== DEBUG: _directElementCreation END ===');
            return !!(this.cardElement && this.sepaElement);
            
        } catch (error) {
            console.error('DEBUG: Error in _directElementCreation:', error);
            console.error('DEBUG: Error stack:', error.stack);
            return false;
        }
    }

    prepareCardElement() {
        console.log('=== DEBUG: prepareCardElement START ===');
        console.log('window.YPrintStripeService exists:', !!window.YPrintStripeService);
        console.log('this context:', this);
        
        if (!window.YPrintStripeService) {
            console.error('DEBUG: YPrintStripeService not available');
            return false;
        }
        
        if (!window.YPrintStripeService.isInitialized()) {
            console.error('DEBUG: YPrintStripeService not initialized');
            return false;
        }
        
        const elements = window.YPrintStripeService.getElements();
        console.log('DEBUG: Elements from service:', elements);
        
        if (!elements) {
            console.error('DEBUG: Elements not available from Stripe Service');
            return false;
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
    
        // Prüfe ob Elements bereits existieren
        if (this.cardElement) {
            console.log('DEBUG: Card element already exists, skipping creation');
        } else {
            console.log('DEBUG: Creating card element...');
            try {
                this.cardElement = elements.create('card', { 
                    style: elementsStyle,
                    hidePostalCode: true
                });
                console.log('DEBUG: Card element created successfully:', !!this.cardElement);
                
                // Event Listener sofort hinzufügen
                if (this.cardElement) {
                    this.setupCardElementEvents();
                }
            } catch (cardError) {
                console.error('DEBUG: Error creating card element:', cardError);
                this.cardElement = null;
            }
        }
    
        if (this.sepaElement) {
            console.log('DEBUG: SEPA element already exists, skipping creation');
        } else {
            console.log('DEBUG: Creating SEPA element...');
            try {
                this.sepaElement = elements.create('iban', {
                    style: elementsStyle,
                    supportedCountries: ['SEPA'],
                    placeholderCountry: 'DE'
                });
                console.log('DEBUG: SEPA element created successfully:', !!this.sepaElement);
            } catch (sepaError) {
                console.error('DEBUG: Error creating SEPA element:', sepaError);
                this.sepaElement = null;
            }
        }
        
        console.log('=== DEBUG: prepareCardElement END ===');
        console.log('Final state - cardElement:', !!this.cardElement, 'sepaElement:', !!this.sepaElement);
        
        // Globale Referenz setzen für Debugging
        window.debugStripeElements = {
            cardElement: this.cardElement,
            sepaElement: this.sepaElement,
            checkoutInstance: this
        };
        
        return !!(this.cardElement && this.sepaElement);
    }
    
    // Neue Hilfsmethode für Card Element Events
    setupCardElementEvents() {
        if (!this.cardElement) return;
        
        this.cardElement.on('change', (event) => {
            console.log('Card state changed:', {
                complete: event.complete,
                error: event.error?.message
            });
            
            // Store state globally for validation
            window.stripeCardState = {
                complete: event.complete,
                error: event.error
            };
            
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
    }


    initSepaElement() {
        console.log('=== DEBUG: initSepaElement START ===');
        console.log('this.initialized:', this.initialized);
        console.log('this.sepaElement exists:', !!this.sepaElement);
        
        if (!this.initialized) {
            console.warn('YPrint Stripe Checkout: Not initialized');
            return false;
        }
        
        if (!this.sepaElement) {
            console.warn('YPrint Stripe Checkout: SEPA element not available, trying to create...');
            // Versuche Element zu erstellen
            if (window.YPrintStripeService && window.YPrintStripeService.isInitialized()) {
                try {
                    const elements = window.YPrintStripeService.getElements();
                    if (elements) {
                        console.log('DEBUG: Creating SEPA element in initSepaElement');
                        this.sepaElement = elements.create('iban', {
                            supportedCountries: ['SEPA'],
                            placeholderCountry: 'DE'
                        });
                        console.log('DEBUG: SEPA element created in init:', !!this.sepaElement);
                    } else {
                        console.error('DEBUG: No elements available from service');
                        return false;
                    }
                } catch (error) {
                    console.error('DEBUG: Error creating SEPA element in init:', error);
                    return false;
                }
            } else {
                console.error('DEBUG: YPrintStripeService not available or not initialized');
                return false;
            }
        }
    
        const sepaElementContainer = document.getElementById('stripe-sepa-element');
        if (!sepaElementContainer) {
            console.error('YPrint Stripe Checkout: SEPA element container not found');
            return false;
        }
    
        try {
            // Prüfen ob bereits gemounted
            if (sepaElementContainer.querySelector('.StripeElement')) {
                console.log('YPrint Stripe Checkout: SEPA element already mounted');
                return true;
            }
    
            // Container leeren bevor mounting
            sepaElementContainer.innerHTML = '';
            
            console.log('DEBUG: Mounting SEPA element to container');
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
            
            return true;
    
        } catch (error) {
            console.error('YPrint Stripe Checkout: SEPA element mount error:', error);
            this.showStripeError('SEPA-Element konnte nicht geladen werden.');
            return false;
        }
    }

    initCardElement() {
        console.log('=== DEBUG: initCardElement START ===');
        console.log('this.initialized:', this.initialized);
        console.log('this.cardElement exists:', !!this.cardElement);
        console.log('this.cardElement value:', this.cardElement);
        
        if (!this.initialized) {
            console.warn('YPrint Stripe Checkout: Not initialized');
            return false;
        }
        
        if (!this.cardElement) {
            console.warn('YPrint Stripe Checkout: Card element not available, trying to create...');
            // Versuche Element zu erstellen
            if (window.YPrintStripeService && window.YPrintStripeService.isInitialized()) {
                try {
                    const elements = window.YPrintStripeService.getElements();
                    if (elements) {
                        console.log('DEBUG: Creating card element in initCardElement');
                        this.cardElement = elements.create('card', { 
                            hidePostalCode: true
                        });
                        console.log('DEBUG: Card element created in init:', !!this.cardElement);
                    } else {
                        console.error('DEBUG: No elements available from service');
                        return false;
                    }
                } catch (error) {
                    console.error('DEBUG: Error creating card element in init:', error);
                    return false;
                }
            } else {
                console.error('DEBUG: YPrintStripeService not available or not initialized');
                return false;
            }
        }
    
        const cardElementContainer = document.getElementById('stripe-card-element');
        if (!cardElementContainer) {
            console.error('YPrint Stripe Checkout: Card element container not found');
            return false;
        }
    
        try {
            // Prüfen ob bereits gemounted
            if (cardElementContainer.querySelector('.StripeElement')) {
                console.log('YPrint Stripe Checkout: Card element already mounted');
                // ENTFERNE this.trackCardElementState() - die Methode existiert nicht
                return true;
            }
    
            // Container leeren bevor mounting
            cardElementContainer.innerHTML = '';
            
            console.log('DEBUG: Mounting card element to container');
            this.cardElement.mount('#stripe-card-element');
            console.log('YPrint Stripe Checkout: Card element mounted successfully');
    
            // Setup Event-Handler direkt hier (anstatt trackCardElementState)
            this.cardElement.on('change', (event) => {
                console.log('Card state changed:', {
                    complete: event.complete,
                    error: event.error?.message
                });
                
                // Store state globally for validation
                window.stripeCardState = {
                    complete: event.complete,
                    error: event.error
                };
                
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
            
            return true;
    
        } catch (error) {
            console.error('YPrint Stripe Checkout: Card element mount error:', error);
            this.showStripeError('Kartenelement konnte nicht geladen werden.');
            return false;
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

// Sichere Initialisierung ohne Race Conditions
console.log('=== CREATING YPrintStripeCheckout INSTANCE ===');
window.YPrintStripeCheckout = new YPrintStripeCheckout();

// Systematische Initialisierung mit Timing-Kontrolle
async function initializeStripeCheckoutSafely() {
    console.log('=== SAFE STRIPE CHECKOUT INITIALIZATION START ===');
    
    // Warte auf DOM-Bereitschaft
    if (document.readyState !== 'complete') {
        console.log('Waiting for DOM to be ready...');
        await new Promise(resolve => {
            if (document.readyState === 'complete') {
                resolve();
            } else {
                window.addEventListener('load', resolve);
            }
        });
    }
    
    // Warte auf YPrintStripeService
    console.log('Waiting for YPrintStripeService...');
    let serviceAttempts = 0;
    while (serviceAttempts < 20 && (!window.YPrintStripeService)) {
        await new Promise(resolve => setTimeout(resolve, 250));
        serviceAttempts++;
        console.log(`YPrintStripeService check attempt ${serviceAttempts}/20`);
    }
    
    if (!window.YPrintStripeService) {
        console.error('YPrintStripeService not available after 5 seconds');
        return false;
    }
    
    console.log('YPrintStripeService found, initializing checkout...');
    
    try {
        const success = await window.YPrintStripeCheckout.init();
        console.log('YPrintStripeCheckout initialization result:', success);
        
        if (success) {
            console.log('=== STRIPE CHECKOUT SUCCESSFULLY INITIALIZED ===');
            console.log('Final state:', {
                initialized: window.YPrintStripeCheckout.initialized,
                cardElement: !!window.YPrintStripeCheckout.cardElement,
                sepaElement: !!window.YPrintStripeCheckout.sepaElement
            });
        } else {
            console.error('=== STRIPE CHECKOUT INITIALIZATION FAILED ===');
        }
        
        return success;
        
    } catch (error) {
        console.error('Error during safe initialization:', error);
        console.error('Error stack:', error.stack);
        return false;
    }
}

// Starte sichere Initialisierung
initializeStripeCheckoutSafely().then(success => {
    if (!success) {
        console.error('Stripe Checkout could not be initialized');
        
        // Fallback: Versuche nochmal nach 3 Sekunden
        setTimeout(async () => {
            console.log('=== FALLBACK INITIALIZATION ATTEMPT ===');
            const fallbackSuccess = await window.YPrintStripeCheckout.init();
            console.log('Fallback initialization result:', fallbackSuccess);
        }, 3000);
    }
}).catch(error => {
    console.error('Critical error in Stripe Checkout initialization:', error);
});

// Debug-Funktion für manuelle Tests
window.debugStripeCheckout = function() {
    console.log('=== MANUAL DEBUG FUNCTION ===');
    console.log('YPrintStripeCheckout exists:', !!window.YPrintStripeCheckout);
    console.log('YPrintStripeCheckout initialized:', window.YPrintStripeCheckout?.initialized);
    console.log('cardElement exists:', !!(window.YPrintStripeCheckout?.cardElement));
    console.log('sepaElement exists:', !!(window.YPrintStripeCheckout?.sepaElement));
    console.log('YPrintStripeService exists:', !!window.YPrintStripeService);
    console.log('YPrintStripeService initialized:', window.YPrintStripeService?.isInitialized());
    
    // Versuche manuelle Initialisierung
    if (window.YPrintStripeCheckout && !window.YPrintStripeCheckout.initialized) {
        console.log('Attempting manual initialization...');
        return window.YPrintStripeCheckout.init();
    }
    
    return 'Already initialized or not available';
};

document.addEventListener('DOMContentLoaded', function () {
    console.log('YPrint Stripe Checkout JS loaded');
});

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
    
    // Login-optimierte Address Manager Initialisierung
if (stepNumber === 1 && window.YPrintAddressManager) {
    console.log('Verzögerte Address Manager Initialisierung für bessere Login-Performance');
    
    // Nur laden wenn wirklich benötigt UND Nutzer bereits eingeloggt
    if (isUserLoggedIn() && !isInitialPageLoad()) {
        setTimeout(() => {
            if (window.YPrintAddressManager.loadSavedAddresses) {
                window.YPrintAddressManager.loadSavedAddresses();
            }
        }, 500); // Reduzierte Verzögerung nach Login
    } else {
        // Bei initialem Seitenaufruf: Deutlich verzögern
        setTimeout(() => {
            if (window.YPrintAddressManager.loadSavedAddresses && shouldLoadAddresses()) {
                window.YPrintAddressManager.loadSavedAddresses();
            }
        }, 3000); // 3 Sekunden Verzögerung
    }
}

// Neue Hilfsfunktionen
function isUserLoggedIn() {
    return document.body.classList.contains('logged-in') || 
           yprint_checkout_params?.is_logged_in === 'yes';
}

function isInitialPageLoad() {
    return performance.timing.loadEventEnd - performance.timing.navigationStart < 2000;
}

function shouldLoadAddresses() {
    // Nur laden wenn Address-UI sichtbar ist
    const addressElements = document.querySelectorAll('.address-cards-grid, .yprint-saved-addresses');
    return Array.from(addressElements).some(el => el.offsetParent !== null);
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
    
    // Neue Funktion um Preise live zu aktualisieren mit Cart Data Manager
async function refreshCartTotals() {
    try {
        const response = await fetch(yprint_checkout_params.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'yprint_refresh_checkout_context',
                nonce: yprint_checkout_params.nonce,
                format: 'summary'
            })
        });

        const data = await response.json();
        
        if (data.success) {
            cartTotals = data.data.context.cart_totals;
            cartItems = data.data.context.cart_items;
            updatePaymentStepSummary();
            updateCartSummaryDisplay(document.getElementById('checkout-cart-summary-items'));
            updateCartTotalsDisplay(document.getElementById('checkout-cart-summary-totals'));
        }
    } catch (error) {
        console.error('Fehler beim Aktualisieren der Preise:', error);
    }
}

// Gutschein-Funktionalität erweitern
async function applyVoucher(voucherCode) {
    try {
        const response = await fetch(yprint_checkout_params.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'yprint_cart_apply_coupon',
                nonce: yprint_checkout_params.nonce,
                coupon_code: voucherCode
            })
        });

        const data = await response.json();
        
        if (data.success) {
            showMessage(data.message, 'success');
            cartTotals = data.totals;
            await refreshCartTotals();
        } else {
            showMessage(data.message, 'error');
        }
        
        return data.success;
    } catch (error) {
        console.error('Fehler beim Anwenden des Gutscheins:', error);
        showMessage('Fehler beim Anwenden des Gutscheins', 'error');
        return false;
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

    // Event Listener für Gutscheinfeld und Button mit Cart Data Manager
const voucherInput = document.getElementById('voucher') || document.getElementById('cart-voucher');
const voucherButton = document.querySelector('.voucher-button-final') || document.querySelector('#voucher + button') || document.getElementById('apply-voucher-button');

if (voucherInput) {
    voucherInput.addEventListener('input', () => {
        formData.voucher = voucherInput.value;
    });
}

if (voucherButton) {
    voucherButton.addEventListener('click', async (e) => {
        e.preventDefault();
        
        const voucherCode = voucherInput ? voucherInput.value.trim() : '';
        if (!voucherCode) {
            showMessage('Bitte geben Sie einen Gutscheincode ein', 'error');
            return;
        }
        
        // Loading-Status anzeigen
        voucherButton.textContent = 'Wird angewendet...';
        voucherButton.disabled = true;
        
        const success = await applyVoucher(voucherCode);
        
        // Button-Status zurücksetzen
        voucherButton.textContent = 'Einlösen';
        voucherButton.disabled = false;
        
        if (success) {
            formData.voucher = voucherCode;
            // Feedback-Element aktualisieren
            const feedback = document.getElementById('cart-voucher-feedback');
            if (feedback) {
                feedback.textContent = 'Gutschein erfolgreich angewendet';
                feedback.className = 'text-xs mt-1 text-green-600';
            }
        }
    });
}


    async function populateConfirmation() {
        // Lade aktuelle Warenkorbdaten
        await loadRealCartData();
        
        // Check for pending order data from payment processing
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('step') === 'confirmation') {
            // Try to get pending order data
            try {
                const response = await fetch(yprint_checkout_params.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'yprint_get_pending_order',
                        nonce: yprint_checkout_params.nonce
                    })
                });
                
                const data = await response.json();
                if (data.success && data.data) {
                    console.log('Found pending order data:', data.data);
                    // Use pending order data for confirmation
                    populateConfirmationWithOrderData(data.data);
                    return;
                }
            } catch (error) {
                console.log('No pending order data found, using standard confirmation');
            }
        }
        
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

        /**
     * Füllt die Bestätigungsseite mit Zahlungsdaten aus dem Payment Processing
     */
    function populateConfirmationWithPaymentData(paymentData) {
        console.log('Populating confirmation with payment data:', paymentData);
        
        // Zeige Erfolgsmeldung
        showMessage('Zahlung erfolgreich! Ihre Bestellung wird verarbeitet.', 'success');
        
        // Zahlungsart basierend auf Payment Data setzen
        const confirmPaymentMethodEl = document.getElementById('confirm-payment-method');
        if (confirmPaymentMethodEl && paymentData.payment_method_id) {
            let paymentMethodText = '<i class="fas fa-credit-card mr-2"></i> Apple Pay (Stripe)';
            
            // Detect payment method type from payment data
            if (paymentData.order_data && paymentData.order_data.customer_details) {
                const customerDetails = paymentData.order_data.customer_details;
                
                // Set customer info if available
                const confirmShippingAddressEl = document.getElementById('confirm-shipping-address');
                if (confirmShippingAddressEl && customerDetails.name) {
                    const billingAddress = paymentData.order_data.billing_address;
                    const shippingAddress = paymentData.order_data.shipping_address;
                    
                    let addressInfo = customerDetails.name + '<br>';
                    
                    if (shippingAddress && shippingAddress.addressLine) {
                        addressInfo += shippingAddress.addressLine[0] + '<br>';
                        addressInfo += shippingAddress.postalCode + ' ' + shippingAddress.city + '<br>';
                        addressInfo += shippingAddress.country;
                    } else if (billingAddress) {
                        addressInfo += (billingAddress.line1 || '') + '<br>';
                        addressInfo += (billingAddress.postal_code || '') + ' ' + (billingAddress.city || '') + '<br>';
                        addressInfo += (billingAddress.country || '');
                    }
                    
                    if (customerDetails.phone) {
                        addressInfo += '<br>Tel: ' + customerDetails.phone;
                    }
                    
                    confirmShippingAddressEl.innerHTML = addressInfo;
                }
            }
            
            confirmPaymentMethodEl.innerHTML = paymentMethodText;
        }
        
        // Verstecke Rechnungsadresse da sie gleich der Lieferadresse ist (Apple Pay Standard)
        const confirmBillingContainer = document.getElementById('confirm-billing-address-container');
        if (confirmBillingContainer) {
            confirmBillingContainer.classList.add('hidden');
        }
        
        // Zeige Test-Mode Hinweis
        if (paymentData.test_mode) {
            const testModeNotice = document.createElement('div');
            testModeNotice.className = 'bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4';
            testModeNotice.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span><strong>Test-Modus:</strong> Diese Zahlung wurde im Test-Modus verarbeitet. Kein echtes Geld wurde belastet.</span>
                </div>
            `;
            
            const confirmationStep = document.getElementById('step-3');
            if (confirmationStep) {
                confirmationStep.insertBefore(testModeNotice, confirmationStep.firstChild);
            }
        }
        
        // Lade und zeige Warenkorbdaten
        loadRealCartData().then(() => {
            updateConfirmationProductList();
            updateConfirmationTotals();
        });
    }
    
    // Hilfsfunktionen für Bestätigungsseite
    function updateConfirmationProductList() {
        const productListEl = document.getElementById('confirm-product-list');
        if (productListEl && cartItems.length > 0) {
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
    }
    
    function updateConfirmationTotals() {
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
    
    // Global verfügbar machen für Stripe Service
    window.populateConfirmationWithPaymentData = populateConfirmationWithPaymentData;
    
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
            
            console.log('=== BUTTON TO CONFIRMATION CLICKED ===');
            console.log('DEBUG: Event triggered');
            
            // Verhindere mehrfache Klicks
            if (btnToConfirmation.disabled) {
                console.log('DEBUG: Button already disabled, ignoring click');
                return;
            }
            
            btnToConfirmation.disabled = true;
            console.log('DEBUG: Button disabled to prevent multiple clicks');
            
            // Lade-Overlay anzeigen
            toggleLoadingOverlay(true);
            
            try {
                console.log('DEBUG: About to validate payment method');
                // Validiere Zahlungsmethode
                const paymentValid = await validatePaymentMethod();
                console.log('DEBUG: Payment validation result:', paymentValid);
                
                if (!paymentValid) {
                    console.log('DEBUG: Payment validation failed');
                    showMessage('Bitte wählen Sie eine gültige Zahlungsmethode aus oder vervollständigen Sie die Zahlungsdaten.', 'error');
                    btnToConfirmation.disabled = false;
                    return;
                }
                
                console.log('DEBUG: Payment validation passed, continuing...');
                
                // Sammle Zahlungsdaten
                collectPaymentData();
                
                // Prüfe ob es sich um eine Stripe-Zahlung handelt, die sofort verarbeitet werden muss
                const selectedMethod = document.querySelector('input[name="payment_method"]:checked')?.value || 
                                      document.getElementById('selected-payment-method')?.value;
                
                if (selectedMethod && selectedMethod.includes('stripe')) {
                    // Sofortige Zahlungsverarbeitung für Stripe
                    const paymentResult = await processStripePaymentImmediately();
                    
                    if (paymentResult.success) {
                        // Zahlung erfolgreich - zeige Bestätigung mit Zahlungsdaten
                        await populateConfirmation();
                        window.populateConfirmationWithPaymentData(paymentResult.data);
                        showStep(3);
                    } else {
                        throw new Error(paymentResult.message || 'Zahlung fehlgeschlagen');
                    }
                } else {
                    // Für andere Zahlungsmethoden: normaler Workflow
                    await populateConfirmation();
                    showStep(3);
                }
            } catch (error) {
                console.error('Fehler beim Verarbeiten der Zahlung:', error);
                showMessage(error.message || 'Ein Fehler ist bei der Zahlungsverarbeitung aufgetreten.', 'error');
            } finally {
                console.log('DEBUG: Re-enabling button in finally block');
                btnToConfirmation.disabled = false;
                toggleLoadingOverlay(false);
            
            }
        });
    }
    
   // Neue Funktion zur Validierung der Zahlungsmethode mit ausführlichem Debug
async function validatePaymentMethod() {
    console.log('=== PAYMENT METHOD VALIDATION START ===');
    
    // Schritt 1: Prüfe welche Payment Method ausgewählt ist
    const selectedMethodInput = document.querySelector('input[name="payment_method"]:checked');
    const selectedMethodHidden = document.getElementById('selected-payment-method');
    
    console.log('DEBUG: selectedMethodInput:', selectedMethodInput);
    console.log('DEBUG: selectedMethodInput value:', selectedMethodInput?.value);
    console.log('DEBUG: selectedMethodHidden:', selectedMethodHidden);
    console.log('DEBUG: selectedMethodHidden value:', selectedMethodHidden?.value);
    
    const selectedMethod = selectedMethodInput?.value || selectedMethodHidden?.value;
    
    console.log('DEBUG: Final selected method:', selectedMethod);
    
    if (!selectedMethod) {
        console.error('DEBUG: No payment method selected at all');
        return false;
    }
    
    // Schritt 2: Prüfe ob es eine Stripe-Zahlung ist
    console.log('DEBUG: Is Stripe payment?', selectedMethod.includes('stripe'));
    
    if (selectedMethod.includes('stripe')) {
        // Schritt 3: Prüfe Stripe-Initialisierung
        console.log('DEBUG: YPrintStripeCheckout exists:', !!window.YPrintStripeCheckout);
        console.log('DEBUG: YPrintStripeCheckout initialized:', window.YPrintStripeCheckout?.initialized);
        console.log('DEBUG: cardElement exists:', !!(window.YPrintStripeCheckout?.cardElement));
        console.log('DEBUG: sepaElement exists:', !!(window.YPrintStripeCheckout?.sepaElement));
        
        if (!window.YPrintStripeCheckout || !window.YPrintStripeCheckout.initialized) {
            console.error('DEBUG: Stripe checkout not properly initialized');
            showMessage('Stripe ist nicht korrekt initialisiert. Bitte laden Sie die Seite neu.', 'error');
            return false;
        }
        
        // Schritt 4: Prüfe welche Stripe-Zahlungsart aktiv ist
        const activeSliderOption = document.querySelector('.slider-option.active');
        console.log('DEBUG: activeSliderOption:', activeSliderOption);
        console.log('DEBUG: activeSliderOption dataset:', activeSliderOption?.dataset);
        
        const paymentMethod = activeSliderOption?.dataset.method;
        console.log('DEBUG: Active payment method from slider:', paymentMethod);
        
        if (!paymentMethod) {
            console.error('DEBUG: No active payment method in slider');
            showMessage('Bitte wählen Sie eine Zahlungsmethode (Karte oder SEPA).', 'error');
            return false;
        }
        
        // Schritt 5: Validiere basierend auf Zahlungsart
        if (paymentMethod === 'card') {
            console.log('DEBUG: Validating card payment...');
            
            // Prüfe nur ob Card Element existiert (Mount-Prüfung komplett entfernt)
if (!window.YPrintStripeCheckout.cardElement) {
    console.error('DEBUG: Card element does not exist');
    showMessage('Kartenelement ist nicht verfügbar. Bitte laden Sie die Seite neu.', 'error');
    return false;
}

console.log('DEBUG: Card element exists, skipping mount check, going directly to status validation');
            
            // Prüfe Card-Status
            console.log('DEBUG: Checking card state...');
            console.log('DEBUG: window.stripeCardState:', window.stripeCardState);
            
            if (window.stripeCardState) {
                if (window.stripeCardState.error) {
                    console.error('DEBUG: Card has error:', window.stripeCardState.error.message);
                    showMessage('Kartendaten-Fehler: ' + window.stripeCardState.error.message, 'error');
                    return false;
                }
                
                if (!window.stripeCardState.complete) {
                    console.error('DEBUG: Card not complete');
                    showMessage('Bitte vervollständigen Sie Ihre Kartendaten.', 'error');
                    return false;
                }
                
                console.log('DEBUG: Card validation passed');
                return true;
            } else {
                console.warn('DEBUG: No card state available - trying validation test');
                
                // Teste durch Payment Method Creation
                try {
                    const isValid = await validateStripeCardElement();
                    console.log('DEBUG: Card element validation test result:', isValid);
                    
                    if (!isValid) {
                        showMessage('Bitte vervollständigen Sie Ihre Kartendaten.', 'error');
                        return false;
                    }
                    
                    return true;
                } catch (error) {
                    console.error('DEBUG: Card validation test failed:', error);
                    showMessage('Fehler bei der Kartenvalidierung: ' + error.message, 'error');
                    return false;
                }
            }
            
        } else if (paymentMethod === 'sepa') {
            console.log('DEBUG: Validating SEPA payment...');
            
            if (!window.YPrintStripeCheckout.sepaElement) {
                console.error('DEBUG: SEPA element does not exist');
                showMessage('SEPA-Element ist nicht verfügbar. Bitte laden Sie die Seite neu.', 'error');
                return false;
            }
            
            const sepaContainer = document.getElementById('stripe-sepa-element');
            const isSepaMounted = sepaContainer && sepaContainer.querySelector('.StripeElement');
            console.log('DEBUG: SEPA element mounted:', isSepaMounted);
            
            if (!isSepaMounted) {
                console.error('DEBUG: SEPA element not mounted');
                showMessage('SEPA-Element ist nicht geladen. Bitte laden Sie die Seite neu.', 'error');
                return false;
            }
            
            // Teste SEPA-Validierung
            try {
                const isValid = await validateStripeSepaElement();
                console.log('DEBUG: SEPA element validation result:', isValid);
                
                if (!isValid) {
                    showMessage('Bitte vervollständigen Sie Ihre SEPA-Daten.', 'error');
                    return false;
                }
                
                return true;
            } catch (error) {
                console.error('DEBUG: SEPA validation failed:', error);
                showMessage('Fehler bei der SEPA-Validierung: ' + error.message, 'error');
                return false;
            }
        } else {
            console.error('DEBUG: Unknown Stripe payment method:', paymentMethod);
            showMessage('Unbekannte Zahlungsmethode: ' + paymentMethod, 'error');
            return false;
        }
    }
    
    console.log('DEBUG: Non-Stripe payment method is valid');
    console.log('=== PAYMENT METHOD VALIDATION END ===');
    return true;
}

    // Neue Funktion für sofortige Stripe-Zahlungsverarbeitung
async function processStripePaymentImmediately() {
    console.log('=== PROCESSING STRIPE PAYMENT IMMEDIATELY ===');
    
    try {
        // Prüfe welche Zahlungsart aktiv ist
        const activeSliderOption = document.querySelector('.slider-option.active');
        const paymentMethod = activeSliderOption?.dataset.method;
        
        console.log('Active payment method:', paymentMethod);
        
        if (!paymentMethod) {
            throw new Error('Keine Zahlungsmethode ausgewählt');
        }
        
        // Erstelle Payment Method basierend auf ausgewählter Option
        let paymentMethodObject;
        
        if (paymentMethod === 'card') {
            paymentMethodObject = await createStripeCardPaymentMethod();
        } else if (paymentMethod === 'sepa') {
            paymentMethodObject = await createStripeSepaPaymentMethod();
        } else {
            throw new Error('Unbekannte Stripe-Zahlungsmethode');
        }
        
        console.log('Created payment method:', paymentMethodObject);
        
        // Verarbeite Zahlung über Stripe Service
        const result = await processPaymentViaStripeService(paymentMethodObject);
        
        return result;
        
    } catch (error) {
        console.error('Stripe payment processing error:', error);
        return {
            success: false,
            message: error.message || 'Stripe-Zahlung fehlgeschlagen'
        };
    }
}

// Hilfsfunktion für Karten-Payment Method
async function createStripeCardPaymentMethod() {
    console.log('=== DEBUG: createStripeCardPaymentMethod START ===');
    
    // Verwende die neue robuste Funktion
    const cardReady = await ensureStripeCardElementReady();
    
    if (!cardReady) {
        throw new Error('Card Element konnte nicht initialisiert werden nach mehreren Versuchen');
    }
    
    const stripe = window.YPrintStripeService.getStripe();
    console.log('Stripe instance:', stripe);
    
    if (!stripe) {
        console.error('DEBUG: Stripe instance not available from YPrintStripeService');
        throw new Error('Stripe Service nicht verfügbar');
    }
    
    console.log('DEBUG: All systems ready, creating payment method...');
    
    // Sammle Billing-Details aus dem Formular
    const billingDetails = {
        name: `${formData.shipping.first_name || ''} ${formData.shipping.last_name || ''}`.trim(),
        email: document.getElementById('email')?.value || '',
        phone: formData.shipping.phone || '',
        address: {
            line1: formData.shipping.street || '',
            line2: formData.shipping.housenumber || '',
            city: formData.shipping.city || '',
            postal_code: formData.shipping.zip || '',
            country: formData.shipping.country || 'DE',
        }
    };
    
    console.log('Creating card payment method with billing details:', billingDetails);

console.log('DEBUG: About to call stripe.createPaymentMethod...');
console.log('DEBUG: Card element before createPaymentMethod:', window.YPrintStripeCheckout.cardElement);

try {
    const { paymentMethod, error } = await stripe.createPaymentMethod({
        type: 'card',
        card: window.YPrintStripeCheckout.cardElement,
        billing_details: billingDetails,
    });
    
    console.log('DEBUG: createPaymentMethod completed');
    console.log('DEBUG: paymentMethod result:', paymentMethod);
    console.log('DEBUG: error result:', error);
    
    if (error) {
        console.error('DEBUG: Payment method creation error:', error);
        throw new Error(error.message);
    }
    
    console.log('DEBUG: Payment method created successfully:', paymentMethod.id);
    return paymentMethod;
    
} catch (createError) {
    console.error('DEBUG: Exception in createPaymentMethod:', createError);
    throw createError;
}

// TIMEOUT-SCHUTZ HINZUFÜGEN
console.log('DEBUG: Setting up timeout protection...');

// Wenn createPaymentMethod nach 10 Sekunden nicht antwortet
setTimeout(() => {
    console.error('DEBUG: createPaymentMethod TIMEOUT after 10 seconds');
    console.error('DEBUG: This indicates a Stripe communication issue');
}, 10000);
    
    if (error) {
        console.error('Card payment method creation error:', error);
        throw new Error(error.message);
    }
    
    return paymentMethod;
}

// Hilfsfunktion für SEPA-Payment Method
async function createStripeSepaPaymentMethod() {
    console.log('=== DEBUG: createStripeSepaPaymentMethod START ===');
    
    // Verwende die neue robuste Funktion
    const sepaReady = await ensureStripeSepaElementReady();
    
    if (!sepaReady) {
        throw new Error('SEPA Element konnte nicht initialisiert werden nach mehreren Versuchen');
    }
    
    const stripe = window.YPrintStripeService.getStripe();
    console.log('Stripe instance:', stripe);
    
    if (!stripe) {
        console.error('DEBUG: Stripe instance not available from YPrintStripeService');
        throw new Error('Stripe Service nicht verfügbar');
    }
    
    console.log('DEBUG: All systems ready, creating payment method...');
    
    // Sammle Billing-Details aus dem Formular
    const billingDetails = {
        name: `${formData.shipping.first_name || ''} ${formData.shipping.last_name || ''}`.trim(),
        email: document.getElementById('email')?.value || '',
        address: {
            line1: formData.shipping.street || '',
            line2: formData.shipping.housenumber || '',
            city: formData.shipping.city || '',
            postal_code: formData.shipping.zip || '',
            country: formData.shipping.country || 'DE',
        }
    };
    
    console.log('Creating SEPA payment method with billing details:', billingDetails);
    
    // Timeout-Wrapper für Stripe createPaymentMethod
const createPaymentMethodWithTimeout = () => {
    return Promise.race([
        stripe.createPaymentMethod({
            type: 'card',
            card: window.YPrintStripeCheckout.cardElement,
            billing_details: billingDetails,
        }),
        new Promise((_, reject) => 
            setTimeout(() => reject(new Error('createPaymentMethod timeout after 15 seconds')), 15000)
        )
    ]);
};

const { paymentMethod, error } = await createPaymentMethodWithTimeout();
    
    if (error) {
        console.error('SEPA payment method creation error:', error);
        throw new Error(error.message);
    }
    
    return paymentMethod;
}

// Verarbeitung über Stripe Service
async function processPaymentViaStripeService(paymentMethod) {
    console.log('Processing payment via Stripe Service:', paymentMethod.id);
    
    // Simuliere Event-Objekt für Stripe Service
    const mockEvent = {
        paymentMethod: paymentMethod,
        shippingAddress: formData.isBillingSameAsShipping ? null : {
            name: `${formData.shipping.first_name || ''} ${formData.shipping.last_name || ''}`.trim(),
            addressLine: [formData.shipping.street + ' ' + formData.shipping.housenumber],
            city: formData.shipping.city,
            country: formData.shipping.country,
            postalCode: formData.shipping.zip,
        },
        complete: function(status) {
            console.log('Payment completed with status:', status);
        }
    };
    
    // Verwende den Stripe Service für einheitliche Verarbeitung
    return new Promise((resolve, reject) => {
        // Hook in die Stripe Service Events
        window.YPrintStripeService.on('payment_success', (data) => {
            console.log('Payment success received:', data);
            resolve({
                success: true,
                data: data,
                message: 'Zahlung erfolgreich verarbeitet'
            });
        });
        
        window.YPrintStripeService.on('payment_error', (error) => {
            console.log('Payment error received:', error);
            reject(new Error(error.error || 'Zahlung fehlgeschlagen'));
        });
        
        // Trigger Payment Processing
        window.YPrintStripeService.handlePaymentMethod(mockEvent, {
            source: 'checkout_form',
            type: paymentMethod.type
        });
    });
}
    
async function validateStripeCardElement() {
    if (!window.YPrintStripeCheckout || !window.YPrintStripeCheckout.cardElement) {
        console.log('Card element not available');
        return false;
    }
    
    try {
        const stripe = window.YPrintStripeService.getStripe();
        if (!stripe) {
            console.log('Stripe not available');
            return false;
        }
        
        // Prüfe erst den Status des Card Elements
        const cardElementContainer = document.getElementById('stripe-card-element');
        if (!cardElementContainer || !cardElementContainer.querySelector('.StripeElement')) {
            console.log('Card element not mounted or visible');
            return false;
        }
        
        // Prüfe ob Card Element vollständig ausgefüllt ist
        const cardState = window.stripeCardState;
        if (cardState && !cardState.complete) {
            console.log('Card element not complete');
            if (cardState.error) {
                const errorElement = document.getElementById('stripe-card-errors');
                if (errorElement) {
                    errorElement.textContent = cardState.error.message;
                    errorElement.style.display = 'block';
                }
            }
            return false;
        }
        
        // Teste Payment Method Creation mit echten Billing Details
        const billingDetails = {
            name: `${formData.shipping.first_name || ''} ${formData.shipping.last_name || ''}`.trim() || 'Test Name',
            email: document.getElementById('email')?.value || '',
            address: {
                line1: formData.shipping.street || '',
                line2: formData.shipping.housenumber || '',
                city: formData.shipping.city || '',
                postal_code: formData.shipping.zip || '',
                country: formData.shipping.country || 'DE',
            }
        };
        
        const {paymentMethod, error} = await stripe.createPaymentMethod({
            type: 'card',
            card: window.YPrintStripeCheckout.cardElement,
            billing_details: billingDetails
        });
        
        if (error) {
            console.log('Card validation error:', error.message);
            const errorElement = document.getElementById('stripe-card-errors');
            if (errorElement) {
                errorElement.textContent = error.message;
                errorElement.style.display = 'block';
            }
            return false;
        }
        
        console.log('Card validation successful:', paymentMethod.id);
        
        // Verstecke Fehlermeldungen bei Erfolg
        const errorElement = document.getElementById('stripe-card-errors');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
        
        // Speichere gültige Payment Method für späteren Gebrauch
        window.validatedPaymentMethod = paymentMethod;
        
        return true;
    } catch (e) {
        console.log('Card validation exception:', e);
        return false;
    }
}

async function validateStripeSepaElement() {
    if (!window.YPrintStripeCheckout.sepaElement) {
        console.log('SEPA element not available');
        return false;
    }
    
    try {
        const stripe = window.YPrintStripeService.getStripe();
        if (!stripe) {
            console.log('Stripe not available');
            return false;
        }
        
        // Prüfe ob SEPA Element gemountet ist
        const sepaElementContainer = document.getElementById('stripe-sepa-element');
        if (!sepaElementContainer || !sepaElementContainer.querySelector('.StripeElement')) {
            console.log('SEPA element not mounted or visible');
            return false;
        }
        
        // Teste Payment Method Creation mit echten Billing Details
        const billingDetails = {
            name: `${formData.shipping.first_name || ''} ${formData.shipping.last_name || ''}`.trim() || 'Test Name',
            email: document.getElementById('email')?.value || '',
            address: {
                line1: formData.shipping.street || '',
                line2: formData.shipping.housenumber || '',
                city: formData.shipping.city || '',
                postal_code: formData.shipping.zip || '',
                country: formData.shipping.country || 'DE',
            }
        };
        
        const {paymentMethod, error} = await stripe.createPaymentMethod({
            type: 'sepa_debit',
            sepa_debit: window.YPrintStripeCheckout.sepaElement,
            billing_details: billingDetails
        });
        
        if (error) {
            console.log('SEPA validation error:', error.message);
            const errorElement = document.getElementById('stripe-sepa-errors');
            if (errorElement) {
                errorElement.textContent = error.message;
                errorElement.style.display = 'block';
            }
            return false;
        }
        
        console.log('SEPA validation successful:', paymentMethod.id);
        
        // Verstecke Fehlermeldungen bei Erfolg
        const errorElement = document.getElementById('stripe-sepa-errors');
        if (errorElement) {
            errorElement.style.display = 'none';
        }
        
        // Speichere gültige Payment Method für späteren Gebrauch
        window.validatedPaymentMethod = paymentMethod;
        
        return true;
    } catch (e) {
        console.log('SEPA validation exception:', e);
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
            
            // Prüfe ob bereits eine Zahlung verarbeitet wurde (Apple Pay / Express Checkout)
            const urlParams = new URLSearchParams(window.location.search);
            const hasPendingOrder = WC?.session || document.querySelector('.test-mode-notice');
            
            if (hasPendingOrder || currentStep === 3) {
                // Bereits bezahlt - zeige Erfolgsmeldung und simuliere Bestellabschluss
                showMessage('Bestellung erfolgreich abgeschlossen! (Test-Modus)', 'success');
                
                // Ändere Button zu "Bestätigung" oder verstecke ihn
                btnBuyNow.textContent = 'Bestellung abgeschlossen ✓';
                btnBuyNow.disabled = true;
                btnBuyNow.classList.add('bg-green-600', 'cursor-not-allowed');
                
                // Fortschrittsanzeige komplett abschließen
                progressSteps.forEach(pStep => pStep.classList.add('completed'));
                return;
            }
            
            toggleLoadingOverlay(true);
            
            try {
                // Normale Bestellverarbeitung für andere Zahlungsmethoden
                const orderResult = await processRealOrder();
                
                if (orderResult.success) {
                    showMessage('Bestellung erfolgreich aufgegeben!', 'success');
                    
                    // Button Status ändern
                    btnBuyNow.textContent = 'Bestellung abgeschlossen ✓';
                    btnBuyNow.disabled = true;
                    btnBuyNow.classList.add('bg-green-600', 'cursor-not-allowed');
                    
                    // Fortschrittsanzeige abschließen
                    progressSteps.forEach(pStep => pStep.classList.add('completed'));
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

// Login-optimierte Performance - drastisch reduzierte AJAX-Calls
document.addEventListener('DOMContentLoaded', function() {
    // Beim Login/Registrierung: KEINE sofortigen Cart-Calls
    if (isLoginPage() || isRegistrationPage()) {
        console.log('Login/Registrierung: Überspringe Cart-Daten-Laden');
        return; // Komplett überspringen
    }
    
    // Prüfe ob Checkout-Daten wirklich benötigt werden
    if (isCheckoutPage()) {
        // Nur auf Checkout-Seite sofort laden
        loadRealCartData().then(() => {
            updateCartDisplays();
        });
    } else if (isCartPage()) {
        // Cart-Seite: Verzögert laden für bessere Performance
        setTimeout(() => {
            loadRealCartData().then(() => {
                updateCartDisplays();
            });
        }, 1000); // Reduziert auf 1 Sekunde
    } else {
        // Alle anderen Seiten: Nur laden wenn UI-Elemente vorhanden UND sichtbar
        setTimeout(() => {
            if (shouldLoadCartData() && isUserInteracting()) {
                loadRealCartData(true).then(() => { // Minimal-Modus
                    updateCartDisplays();
                });
            }
        }, 5000); // Deutlich verzögert: 5 Sekunden
    }
});

// Neue Hilfsfunktionen für Login-Optimierung
function isLoginPage() {
    return window.location.href.includes('/login/') || 
           window.location.href.includes('/my-account/') ||
           document.querySelector('.login-form') !== null ||
           document.querySelector('#loginform') !== null;
}

function isRegistrationPage() {
    return window.location.href.includes('/register/') ||
           document.querySelector('.registration-form') !== null ||
           document.querySelector('[yprint_registration_form_mobile]') !== null;
}

function isUserInteracting() {
    // Prüfe ob Nutzer schon mit der Seite interagiert hat
    return document.hasFocus() && 
           (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100);
}

function isCheckoutPage() {
    return window.location.href.includes('/checkout/') || 
           document.querySelector('.yprint-checkout-container') !== null;
}

function isCartPage() {
    return window.location.href.includes('/cart/') || 
           document.querySelector('.woocommerce-cart') !== null;
}

function shouldLoadCartData() {
    // Nur laden wenn Warenkorb-Elemente auf der Seite sind
    return document.querySelector('#checkout-cart-summary-items') !== null ||
           document.querySelector('.cart-summary') !== null ||
           document.querySelector('.mini-cart') !== null;
}

function updateCartDisplays() {
    const cartSummaryContainer = document.getElementById('checkout-cart-summary-items');
    if (cartSummaryContainer) {
        updateCartSummaryDisplay(cartSummaryContainer);
    }
    const cartTotalsContainer = document.getElementById('checkout-cart-summary-totals');
    if (cartTotalsContainer) {
        updateCartTotalsDisplay(cartTotalsContainer);
    }
}
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
        // Robuste Stripe Card Element Initialisierung
        setTimeout(async () => {
            console.log('DEBUG: Ensuring Stripe Card Element is ready');
            await ensureStripeCardElementReady();
        }, 100);
    } else if (selectedMethod === 'sepa') {
        jQuery('#sepa-payment-fields').addClass('active');
        console.log('DEBUG: Showing SEPA fields');
        // Robuste Stripe SEPA Element Initialisierung
        setTimeout(async () => {
            console.log('DEBUG: Ensuring Stripe SEPA Element is ready');
            await ensureStripeSepaElementReady();
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

// Neue robuste Initialisierungsfunktion für Card Elements
async function ensureStripeCardElementReady() {
    console.log('=== ENSURING STRIPE CARD ELEMENT READY ===');
    
    const maxAttempts = 5;
    let attempts = 0;
    
    while (attempts < maxAttempts) {
        attempts++;
        console.log(`Attempt ${attempts}/${maxAttempts} to ensure Card Element ready`);
        
        // Prüfe YPrintStripeCheckout Verfügbarkeit
        if (!window.YPrintStripeCheckout) {
            console.log('YPrintStripeCheckout not available, waiting...');
            await new Promise(resolve => setTimeout(resolve, 1000));
            continue;
        }
        
        // Prüfe Initialisierung
        if (!window.YPrintStripeCheckout.initialized) {
            console.log('YPrintStripeCheckout not initialized, waiting...');
            await new Promise(resolve => setTimeout(resolve, 1000));
            continue;
        }
        
        // Prüfe Card Element UND versuche es zu initialisieren
        if (!window.YPrintStripeCheckout.cardElement) {
            console.log('Card Element not available, trying to initialize...');
            
            try {
                // Versuche Card Element zu initialisieren/mounten
                const success = await window.YPrintStripeCheckout.initCardElement();
                
                if (success && window.YPrintStripeCheckout.cardElement) {
                    console.log('Card Element successfully initialized and mounted!');
                    return true;
                }
                
                console.log('Card Element initialization returned:', success);
            } catch (error) {
                console.error('Error initializing Card Element:', error);
            }
            
            await new Promise(resolve => setTimeout(resolve, 500));
            continue;
        }
        
        // Element existiert - prüfe ob es gemountet ist
        const cardContainer = document.getElementById('stripe-card-element');
        if (cardContainer && !cardContainer.querySelector('.StripeElement')) {
            console.log('Card Element exists but not mounted, mounting now...');
            try {
                const success = await window.YPrintStripeCheckout.initCardElement();
                if (success) {
                    console.log('Card Element successfully mounted!');
                    return true;
                }
            } catch (error) {
                console.error('Error mounting Card Element:', error);
            }
            await new Promise(resolve => setTimeout(resolve, 500));
            continue;
        }
        
        // Alles ist bereit
        console.log('Card Element is ready and mounted!');
        return true;
    }
    
    console.error('Failed to ensure Card Element ready after', maxAttempts, 'attempts');
    return false;
}

// Entsprechende Funktion für SEPA Element
async function ensureStripeSepaElementReady() {
    console.log('=== ENSURING STRIPE SEPA ELEMENT READY ===');
    
    const maxAttempts = 5;
    let attempts = 0;
    
    while (attempts < maxAttempts) {
        attempts++;
        console.log(`Attempt ${attempts}/${maxAttempts} to ensure SEPA Element ready`);
        
        if (!window.YPrintStripeCheckout) {
            console.log('YPrintStripeCheckout not available, waiting...');
            await new Promise(resolve => setTimeout(resolve, 1000));
            continue;
        }
        
        if (!window.YPrintStripeCheckout.initialized) {
            console.log('YPrintStripeCheckout not initialized, waiting...');
            await new Promise(resolve => setTimeout(resolve, 1000));
            continue;
        }
        
        if (!window.YPrintStripeCheckout.sepaElement) {
            console.log('SEPA Element not available, trying to initialize...');
            
            try {
                if (window.YPrintStripeCheckout.initSepaElement) {
                    window.YPrintStripeCheckout.initSepaElement();
                    
                    await new Promise(resolve => setTimeout(resolve, 500));
                    
                    if (window.YPrintStripeCheckout.sepaElement) {
                        console.log('SEPA Element successfully initialized!');
                        return true;
                    }
                }
            } catch (error) {
                console.error('Error initializing SEPA Element:', error);
            }
            
            await new Promise(resolve => setTimeout(resolve, 1000));
            continue;
        }
        
        console.log('SEPA Element is ready!');
        return true;
    }
    
    console.error('Failed to ensure SEPA Element ready after', maxAttempts, 'attempts');
    return false;
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

    // Proaktive Initialisierung nach Page Load
setTimeout(async () => {
    console.log('=== PROACTIVE STRIPE INITIALIZATION ===');
    
    // Warte bis alle Komponenten verfügbar sind
    let attempts = 0;
    const maxAttempts = 10;
    
    while (attempts < maxAttempts) {
        attempts++;
        
        if (window.YPrintStripeCheckout && window.YPrintStripeCheckout.initialized) {
            console.log('YPrintStripeCheckout ready, initializing elements proactively');
            
            // Initialisiere Card Element proaktiv (da es standardmäßig aktiv ist)
            await ensureStripeCardElementReady();
            
            break;
        }
        
        console.log(`Waiting for YPrintStripeCheckout... attempt ${attempts}/${maxAttempts}`);
        await new Promise(resolve => setTimeout(resolve, 1000));
    }
    
    if (attempts >= maxAttempts) {
        console.warn('YPrintStripeCheckout not ready after', maxAttempts, 'seconds');
    }
}, 2000); // Start nach 2 Sekunden
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