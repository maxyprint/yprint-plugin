// YPrint Checkout JavaScript - Allgemeine Funktionen
// WordPress-kompatibles jQuery Wrapping

// DEBUG: Check for missing script dependencies
console.log('=== YPRINT CHECKOUT SCRIPT DEBUG ===');
console.log('Loaded scripts check:');
document.querySelectorAll('script[src]').forEach(script => {
    if (script.src.includes('yprint-checkout-header') || script.src.includes('yprint') && script.src.includes('header')) {
        console.log('FOUND problematic script:', script.src);
        console.log('Script element:', script);
        console.log('Script loaded successfully:', !script.onerror);
    }
});

// === YPRINT ADDRESS AJAX VERFÜGBARKEIT PRÜFEN ===
if (typeof yprint_address_ajax === 'undefined') {
    console.warn('CRITICAL: yprint_address_ajax not loaded! Payment Method creation will fail.');
    // Fallback-Definition falls nicht geladen
    window.yprint_address_ajax = {
        ajax_url: (typeof yprint_checkout_params !== 'undefined') ? yprint_checkout_params.ajax_url : '/wp-admin/admin-ajax.php',
        nonce: (typeof yprint_checkout_params !== 'undefined') ? yprint_checkout_params.nonce : ''
    };
}

// Check for any 404 script errors
const originalError = window.onerror;
window.onerror = function(msg, url, line, col, error) {
    if (url && url.includes('yprint-checkout-header.js')) {
        console.error('CRITICAL: yprint-checkout-header.js failed to load from:', url);
        console.error('This script was requested from line:', line);
        console.error('Error details:', error);
    }
    if (originalError) originalError.apply(this, arguments);
};

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
    console.log('=== LOAD REAL CART DATA DEBUG START ===');
    console.log('forceRefresh:', forceRefresh);
    console.log('cartDataCache exists:', !!cartDataCache);
    console.log('cartDataCacheTime:', cartDataCacheTime);
    console.log('CACHE_DURATION:', CACHE_DURATION);
    console.log('yprint_checkout_params exists:', typeof yprint_checkout_params !== 'undefined');
    
    if (typeof yprint_checkout_params === 'undefined') {
        console.error('CRITICAL: yprint_checkout_params is undefined!');
        return;
    }
    
    console.log('yprint_checkout_params.ajax_url:', yprint_checkout_params.ajax_url);
    console.log('yprint_checkout_params.nonce:', yprint_checkout_params.nonce);
    
    // Cache prüfen für bessere Performance
    if (!forceRefresh && cartDataCache && (Date.now() - cartDataCacheTime) < CACHE_DURATION) {
        console.log('Verwende Cache für Warenkorbdaten');
        try {
            applyCartData(cartDataCache);
            console.log('Cache successfully applied');
            return;
        } catch (cacheError) {
            console.error('Error applying cache data:', cacheError);
            console.error('Cache data:', cartDataCache);
        }
    }

    console.log('Making AJAX request for cart data...');
    
    try {
        console.log('About to call isMinimalLoadNeeded()...');
        const minimal = isMinimalLoadNeeded();
        console.log('isMinimalLoadNeeded result:', minimal);
        
        const requestBody = {
            action: 'yprint_get_cart_data',
            nonce: yprint_checkout_params.nonce,
            minimal: minimal ? '1' : '0'
        };
        console.log('Request body:', requestBody);
        
        console.log('Starting fetch request...');
        const response = await fetch(yprint_checkout_params.ajax_url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(requestBody)
        });
        
        console.log('Fetch response received, status:', response.status);
        console.log('Response ok:', response.ok);
        
        console.log('Parsing JSON...');
        const data = await response.json();
        console.log('JSON parsed successfully:', data);
        
        if (data.success) {
            console.log('AJAX success, updating cache...');
            // Cache aktualisieren
            cartDataCache = data.data;
            cartDataCacheTime = Date.now();
            console.log('Cache updated, calling applyCartData...');
            
            applyCartData(data.data);
            console.log('Zentrale Warenkorbdaten geladen:', cartItems);
        } else {
            console.error('AJAX returned success=false:', data);
            console.error('Error data:', data.data);
        }
    } catch (error) {
        console.error('EXCEPTION in loadRealCartData:', error);
        console.error('Error name:', error.name);
        console.error('Error message:', error.message);
        console.error('Error stack:', error.stack);
        
        // Prüfe spezifische Fehlertypen
        if (error instanceof ReferenceError) {
            console.error('ReferenceError detected - checking variable availability:');
            console.error('- cartDataCache:', typeof cartDataCache);
            console.error('- cartDataCacheTime:', typeof cartDataCacheTime);
            console.error('- CACHE_DURATION:', typeof CACHE_DURATION);
            console.error('- isMinimalLoadNeeded:', typeof isMinimalLoadNeeded);
            console.error('- applyCartData:', typeof applyCartData);
        }
    }
    
    console.log('=== LOAD REAL CART DATA DEBUG END ===');
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
    
     // Debug: Express Payment Problem analysieren
    console.log('=== EXPRESS PAYMENT DEBUG ===');
    console.log('window.YPrintExpressCheckout exists:', !!window.YPrintExpressCheckout);
    console.log('window.YPrintExpressCheckout type:', typeof window.YPrintExpressCheckout);
    console.log('window.YPrintExpressCheckout methods:', window.YPrintExpressCheckout ? Object.getOwnPropertyNames(Object.getPrototypeOf(window.YPrintExpressCheckout)) : 'N/A');
    console.log('window.YPrintExpressCheckout updateAmount exists:', !!(window.YPrintExpressCheckout && window.YPrintExpressCheckout.updateAmount));
    console.log('window.checkoutContext exists:', !!window.checkoutContext);
    console.log('window.checkoutContext.express_payment exists:', !!(window.checkoutContext && window.checkoutContext.express_payment));
    
    // Express Payment nur wenn verfügbar und benötigt
    if (window.YPrintExpressCheckout && window.checkoutContext?.express_payment) {
        if (typeof window.YPrintExpressCheckout.updateAmount === 'function') {
            console.log('Calling updateAmount with:', window.checkoutContext.express_payment.total.amount);
            window.YPrintExpressCheckout.updateAmount(window.checkoutContext.express_payment.total.amount);
        } else {
            console.error('updateAmount method not found. Available methods:', Object.getOwnPropertyNames(window.YPrintExpressCheckout));
        }
    }
    console.log('=== EXPRESS PAYMENT DEBUG END ===');
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

// VERBESSERTE Design-Backup-Aktivierung vor Express Checkout mit Retry-Mechanismus
function secureExpressDesignData() {
    console.log('=== SECURING EXPRESS DESIGN DATA ===');
    
    return new Promise((resolve, reject) => {
        // SOFORTIGER BACKUP-AUFRUF ohne Warten
        const formData = new FormData();
        formData.append('action', 'yprint_secure_express_design_data');
        formData.append('nonce', yprint_checkout_params.nonce);
        formData.append('force_immediate', 'true'); // Flag für sofortiges Backup
        
        fetch(yprint_checkout_params.ajax_url, {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('EXPRESS SECURE: Design data secured successfully');
                console.log('Design count:', data.data.design_count);
                console.log('Backup keys:', data.data.backup_keys);
                
                // ZUSÄTZLICHE SICHERHEIT: Lokale Browser-Session-Speicherung
                if (data.data.design_count > 0) {
                    sessionStorage.setItem('yprint_express_design_backup_browser', JSON.stringify({
                        backup_created: new Date().toISOString(),
                        design_count: data.data.design_count,
                        backup_keys: data.data.backup_keys
                    }));
                    console.log('EXPRESS SECURE: Browser session backup created');
                }
                
                resolve(data.data);
            } else {
                console.log('EXPRESS SECURE: No design data found or error:', data.data.message);
                resolve(null); // Nicht rejekten, da leerer Cart okay ist
            }
        })
        .catch(error => {
            console.error('EXPRESS SECURE: Error securing design data:', error);
            // Retry einmal bei Fehler
            setTimeout(() => {
                fetch(yprint_checkout_params.ajax_url, {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('EXPRESS SECURE: Retry successful');
                        resolve(data.data);
                    } else {
                        resolve(null);
                    }
                })
                .catch(retryError => {
                    console.error('EXPRESS SECURE: Retry failed:', retryError);
                    resolve(null); // Auch bei Retry-Fehler nicht blockieren
                });
            }, 1000);
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
            // Prüfen ob bereits gemounted - erweiterte Validierung
const isMounted = sepaElementContainer.querySelector('.StripeElement') || 
sepaElementContainer.querySelector('.__PrivateStripeElement') ||
sepaElementContainer.querySelector('iframe[name*="privateStripeFrame"]') ||
(sepaElementContainer.innerHTML.length > 100 && sepaElementContainer.innerHTML.includes('__PrivateStripeElement'));

if (isMounted) {
console.log('YPrint Stripe Checkout: SEPA element already mounted');
return true;
}
    
            // Container leeren bevor mounting
sepaElementContainer.innerHTML = '';

console.log('DEBUG: Mounting SEPA element to container');
this.sepaElement.mount('#stripe-sepa-element');

// Warte kurz und validiere dann den Mount
setTimeout(() => {
    const mountCheck = sepaElementContainer.querySelector('.__PrivateStripeElement') || 
                      sepaElementContainer.querySelector('iframe[name*="privateStripeFrame"]') ||
                      (sepaElementContainer.innerHTML.length > 100 && sepaElementContainer.innerHTML.includes('iframe'));
    
    if (mountCheck) {
        console.log('YPrint Stripe Checkout: SEPA element mounted and validated successfully');
    } else {
        console.warn('YPrint Stripe Checkout: SEPA element mount validation failed');
        // Retry mount nach kurzer Verzögerung
        setTimeout(() => {
            if (sepaElementContainer.innerHTML.length === 0) {
                console.log('Retrying SEPA element mount...');
                this.sepaElement.mount('#stripe-sepa-element');
            }
        }, 200);
    }
}, 100);

console.log('YPrint Stripe Checkout: SEPA element mount initiated');

            // CSS-Fix für Klickbarkeit hinzufügen
            setTimeout(() => {
                const sepaInput = sepaElementContainer.querySelector('.__PrivateStripeElement-input');
                if (sepaInput) {
                    sepaInput.style.pointerEvents = 'auto';
                }
                const sepaIframe = sepaElementContainer.querySelector('iframe');
                if (sepaIframe) {
                    sepaIframe.style.pointerEvents = 'auto';
                    sepaIframe.style.cursor = 'text';
                }
            }, 200);

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
    
    // "Bestellung anzeigen" Button auf Bestätigungsseite ausblenden
    const orderDisplayButtons = document.querySelectorAll('.yprint-summary-toggle, .btn-view-order, [class*="order-display"], [id*="order-display"]');
    if (stepNumber === 3) {
        orderDisplayButtons.forEach(button => {
            button.style.display = 'none';
        });
        // Auch im Checkout Header ausblenden
        const checkoutHeader = document.getElementById('yprint-checkout-header');
        if (checkoutHeader) {
            const headerToggle = checkoutHeader.querySelector('.yprint-summary-toggle');
            if (headerToggle) {
                headerToggle.style.display = 'none';
            }
        }
    } else {
        orderDisplayButtons.forEach(button => {
            button.style.display = '';
        });
        // Checkout Header Button wieder einblenden
        const checkoutHeader = document.getElementById('yprint-checkout-header');
        if (checkoutHeader) {
            const headerToggle = checkoutHeader.querySelector('.yprint-summary-toggle');
            if (headerToggle) {
                headerToggle.style.display = '';
            }
        }
    }
    
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
     * Speichert die Bestelldaten vor der Zahlungsverarbeitung
     */
    async function saveOrderDataBeforePayment() {
        console.log('=== SAVING ORDER DATA BEFORE PAYMENT ===');
        
        try {
            // Lade aktuelle Warenkorbdaten ein letztes Mal
            await loadRealCartData();
            
            // Speichere Bestelldaten im sessionStorage für die Bestätigungsseite
            const orderData = {
                items: JSON.parse(JSON.stringify(cartItems)), // Deep copy
                totals: JSON.parse(JSON.stringify(cartTotals)), // Deep copy
                shipping: JSON.parse(JSON.stringify(formData.shipping)),
                billing: JSON.parse(JSON.stringify(formData.billing)),
                payment: JSON.parse(JSON.stringify(formData.payment)),
                timestamp: new Date().toISOString(),
                isBillingSameAsShipping: formData.isBillingSameAsShipping,
                voucher: formData.voucher
            };
            
            sessionStorage.setItem('yprint_confirmation_order_data', JSON.stringify(orderData));
            
            console.log('Order data saved to sessionStorage:', orderData);
            
        } catch (error) {
            console.error('Error saving order data:', error);
            // Nicht kritisch - Weiter mit dem Checkout-Flow
        }
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
    console.log('=== POPULATE CONFIRMATION DEBUG START ===');
    console.log('🎯 populateConfirmation() aufgerufen');
    
    // Markiere dass populateConfirmation aufgerufen wurde
    window.confirmationPopulated = true;
    window.confirmationTimestamp = new Date().toISOString();
    
    console.log('🎯 Confirmation populated timestamp:', window.confirmationTimestamp);
    
    // Verwende gespeicherte Bestelldaten statt aktuelle Warenkorbdaten
    let orderData = null;
    try {
        const savedOrderData = sessionStorage.getItem('yprint_confirmation_order_data');
        if (savedOrderData) {
            orderData = JSON.parse(savedOrderData);
            console.log('Using saved order data for confirmation:', orderData);
            
            // Überschreibe aktuelle Daten mit gespeicherten Bestelldaten
            cartItems = orderData.items || [];
            cartTotals = orderData.totals || cartTotals;
            
            // Überschreibe formData falls gespeichert
            if (orderData.shipping) formData.shipping = orderData.shipping;
            if (orderData.billing) formData.billing = orderData.billing;
            if (orderData.payment) formData.payment = orderData.payment;
            if (typeof orderData.isBillingSameAsShipping !== 'undefined') {
                formData.isBillingSameAsShipping = orderData.isBillingSameAsShipping;
            }
            if (orderData.voucher) formData.voucher = orderData.voucher;
        } else {
            // Prüfe ob Express Payment Daten verfügbar sind BEVOR Cart-Daten geladen werden
            if (window.confirmationPaymentData && window.confirmationPaymentData.order_data) {
                console.log('Using Express Payment data instead of cart data');
                // Für Express Payments: Verwende Payment Data als Order Data
                const paymentOrderData = window.confirmationPaymentData.order_data;
                orderData = {
                    items: [], // Express Payments haben keine Items-Details, das ist OK
                    totals: {
                        subtotal: parseFloat(paymentOrderData.amount) || 0,
                        total: parseFloat(paymentOrderData.amount) || 0,
                        shipping: 0, // Kann später aus anderen Quellen ergänzt werden
                        tax: 0,
                        discount: 0
                    },
                    shipping: paymentOrderData.shipping_address ? {
                        first_name: paymentOrderData.shipping_address.recipient ? paymentOrderData.shipping_address.recipient.split(' ')[0] : '',
                        last_name: paymentOrderData.shipping_address.recipient ? paymentOrderData.shipping_address.recipient.split(' ').slice(1).join(' ') : '',
                        street: paymentOrderData.shipping_address.addressLine ? paymentOrderData.shipping_address.addressLine[0] : '',
                        housenumber: '', // Nicht verfügbar in Express Payment Daten
                        city: paymentOrderData.shipping_address.city || '',
                        zip: paymentOrderData.shipping_address.postalCode || '',
                        country: paymentOrderData.shipping_address.country || '',
                        phone: paymentOrderData.shipping_address.phone || ''
                    } : {},
                    billing: paymentOrderData.billing_address ? {
                        first_name: paymentOrderData.customer_details.name ? paymentOrderData.customer_details.name.split(' ')[0] : '',
                        last_name: paymentOrderData.customer_details.name ? paymentOrderData.customer_details.name.split(' ').slice(1).join(' ') : '',
                        street: paymentOrderData.billing_address.line1 || '',
                        housenumber: '', // Nicht getrennt verfügbar
                        city: paymentOrderData.billing_address.city || '',
                        zip: paymentOrderData.billing_address.postal_code || '',
                        country: paymentOrderData.billing_address.country || '',
                        email: paymentOrderData.customer_details.email || '',
                        phone: paymentOrderData.customer_details.phone || ''
                    } : {},
                    payment: {
                        method: 'express_payment' // Express Payment Marker
                    },
                    timestamp: new Date().toISOString(),
                    isBillingSameAsShipping: false, // Für Express Payments oft getrennt
                    voucher: ''
                };
                
                // Weise Order Data zu Hauptvariablen zu
                cartItems = orderData.items;
                cartTotals = orderData.totals;
                if (orderData.shipping) formData.shipping = orderData.shipping;
                if (orderData.billing) formData.billing = orderData.billing;
                if (orderData.payment) formData.payment = orderData.payment;
                formData.isBillingSameAsShipping = orderData.isBillingSameAsShipping;
                if (orderData.voucher) formData.voucher = orderData.voucher;
                
                console.log('✅ Express Payment data successfully applied to confirmation');
            } else {
                console.log('No saved order data found, loading cart data...');
                await loadRealCartData();
            }
        }
        
    } catch (error) {
        console.error('Error loading saved order data:', error);
        // Fallback: Lade aktuelle Warenkorbdaten NUR wenn keine Payment Data verfügbar
        if (!window.confirmationPaymentData || !window.confirmationPaymentData.order_data) {
            try {
                await loadRealCartData();
            } catch (fallbackError) {
                console.error('Fallback cart data loading failed:', fallbackError);
            }
        } else {
            console.log('Skipping cart data fallback - using available payment data');
        }
    }
        
        // Check for pending order data from payment processing
        
        if (new URLSearchParams(window.location.search).get('step') === 'confirmation') {
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
    
        // Produkte statisch anzeigen (nicht mehr dynamisch aktualisieren)
        const productListEl = document.getElementById('confirm-product-list');
        if (productListEl && cartItems.length > 0) {
            productListEl.innerHTML = '';
            
            cartItems.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'flex justify-between items-center py-3 border-b border-gray-100 confirmation-product-item';
                itemEl.setAttribute('data-static', 'true'); // Markiere als statisch
                
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
                
                // Design-Badge für bessere Kennzeichnung
                const designBadge = item.is_design_product ? 
                    `<span class="design-badge text-xs bg-blue-600 text-white px-2 py-1 rounded-full ml-2">Design</span>` : '';
                
                itemEl.innerHTML = `
                    <div class="flex items-center flex-1">
                        <img src="${item.image}" alt="${item.name}" class="w-16 h-16 object-cover rounded border mr-3 bg-gray-50">
                        <div class="flex-1">
                            <p class="font-medium text-sm text-gray-800">${item.name}${designBadge}</p>
                            <p class="text-xs text-gray-600">Menge: ${item.quantity}</p>
                            ${designDetailsHtml}
                            ${item.quantity > 1 ? `<p class="text-xs text-gray-500 mt-1">€${item.price.toFixed(2)} pro Stück</p>` : ''}
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="font-medium text-sm text-gray-800">€${(item.price * item.quantity).toFixed(2)}</p>
                    </div>
                `;
                productListEl.appendChild(itemEl);
            });
            
            // Füge einen Hinweis hinzu, dass dies die finale Bestellung ist
            const finalOrderNote = document.createElement('div');
            finalOrderNote.className = 'mt-4 p-3 bg-green-50 border border-green-200 rounded-lg';
            finalOrderNote.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-check-circle text-green-600 mr-2"></i>
                    <span class="text-sm text-green-800 font-medium">Bestellung bestätigt - Diese Artikel wurden erfolgreich bestellt.</span>
                </div>
            `;
            productListEl.appendChild(finalOrderNote);
        }
/**
 * Füllt die Bestätigungsseite mit Zahlungsdaten aus dem Payment Processing.
 * @param {object} paymentData - Die Zahlungsdaten vom Payment Processing.
 */
function populateConfirmationWithPaymentData(paymentData) {
    console.log('Populating confirmation with payment data:', paymentData);

    // Zeige Erfolgsmeldung an.
    showMessage('Zahlung erfolgreich! Ihre Bestellung wird verarbeitet.', 'success');

    // Zahlungsart basierend auf Payment Data setzen.
    const confirmPaymentMethodEl = document.getElementById('confirm-payment-method');
    if (confirmPaymentMethodEl && paymentData.payment_method_id) {
        let paymentMethodText = null;

        // Versuche, den Titel der Zahlungsart über die vorhandene Funktion zu erhalten.
        if (typeof getPaymentMethodTitle === 'function') {
            paymentMethodText = getPaymentMethodTitle(paymentData.payment_method_id);
        }

        // Fallback, falls getPaymentMethodTitle nicht verfügbar ist oder null zurückgibt.
        if (!paymentMethodText) {
            paymentMethodText = '<i class="fas fa-credit-card mr-2"></i> Express-Zahlung (Stripe)';
        }
        confirmPaymentMethodEl.innerHTML = paymentMethodText;
    }

    // Speichere Payment Data global für spätere Payment Method Detection.
    window.confirmationPaymentData = paymentData;

    // KRITISCH: Trigger Payment Method Detection für Express Payments.
    console.log('=== TRIGGERING PAYMENT METHOD DETECTION FOR EXPRESS PAYMENTS ===');
    console.log('Payment Data set:', paymentData);

    // Erzwinge Payment Method Detection Update via Event.
    console.log('🔥 Line 1736 - About to trigger events and updates');
    
    // Event für Payment Data Update triggen
    const event = new CustomEvent('yprint_payment_data_updated', {
        detail: paymentData
    });
    document.dispatchEvent(event);
    console.log('✅ yprint_payment_data_updated Event dispatched');

    // Force Payment Method Display Update
    if (typeof updatePaymentMethodDisplay === 'function') {
        console.log('✅ Calling updatePaymentMethodDisplay()');
        updatePaymentMethodDisplay();
    } else {
        console.warn('⚠️ updatePaymentMethodDisplay function not available');
    }

    // Trigger Payment Method Update Attempts
    if (typeof attemptPaymentMethodUpdate === 'function') {
        console.log('✅ Calling attemptPaymentMethodUpdate()');
        setTimeout(() => attemptPaymentMethodUpdate(1, 5), 50);
    } else {
        console.warn('⚠️ attemptPaymentMethodUpdate function not available');
    }

    setTimeout(() => {
        console.log('Dispatching yprint_payment_data_updated event');
        window.dispatchEvent(new CustomEvent('yprint_payment_data_updated', {
            detail: { source: 'populateConfirmationWithPaymentData', paymentData: paymentData }
        }));
    }, 100);

    // Backup-Trigger für Payment Method Detection.
    setTimeout(() => {
        if (typeof window.updatePaymentMethodDisplay === 'function') {
            console.log('🔄 Direct backup call updatePaymentMethodDisplay');
            window.updatePaymentMethodDisplay();
        }
        if (typeof window.attemptPaymentMethodUpdate === 'function') {
            console.log('🔄 Direct backup call attemptPaymentMethodUpdate');
            window.attemptPaymentMethodUpdate(1, 5);
        }
    }, 300);

    // Setze Kundeninformationen, falls vorhanden.
    if (paymentData.order_data && paymentData.order_data.customer_details) {
        const customerDetails = paymentData.order_data.customer_details;
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

    // Verstecke Rechnungsadresse, da sie gleich der Lieferadresse ist (Apple Pay Standard).
    const confirmBillingContainer = document.getElementById('confirm-billing-address-container');
    if (confirmBillingContainer) {
        confirmBillingContainer.classList.add('hidden');
    }

    // Zeige Test-Modus Hinweis an, falls im Test-Modus.
    // Bevorzugt paymentData.order_data.livemode für die Prüfung, falls vorhanden
    // Sonst Fallback auf paymentData.test_mode (alte Implementierung)
    const isTestMode = (paymentData.order_data && !paymentData.order_data.livemode) || paymentData.test_mode;

    if (isTestMode) {
        const testModeNotice = document.createElement('div');
        testModeNotice.className = 'mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded-lg'; // Original-Klassen
        
        // Prüfe, ob eine der beiden Test-Modus-Nachrichten bevorzugt wird oder ob beide angezeigt werden sollen
        if (paymentData.order_data && !paymentData.order_data.livemode) {
             testModeNotice.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle text-yellow-600 mr-2"></i>
                    <span class="text-sm text-yellow-800 font-medium">Test-Modus: Diese Zahlung wurde im Test-Modus verarbeitet.</span>
                </div>
            `;
        } else if (paymentData.test_mode) {
            // Wenn nur paymentData.test_mode vorhanden ist oder bevorzugt wird
            testModeNotice.className = 'bg-yellow-100 border border-yellow-400 text-yellow-700 px-4 py-3 rounded mb-4'; // Andere Klassen für diesen Fall
            testModeNotice.innerHTML = `
                <div class="flex items-center">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <span><strong>Test-Modus:</strong> Diese Zahlung wurde im Test-Modus verarbeitet. Kein echtes Geld wurde belastet.</span>
                </div>
            `;
        }
       
        const confirmationContainer = document.querySelector('#step-3 .checkout-step-content'); // Ursprünglicher Selektor
        if (confirmationContainer) {
            confirmationContainer.appendChild(testModeNotice);
        } else {
             // Fallback für den Fall, dass der erste Container nicht gefunden wird
            const confirmationStep = document.getElementById('step-3');
            if (confirmationStep) {
                confirmationStep.insertBefore(testModeNotice, confirmationStep.firstChild);
            }
        }
    }

    // Lade und zeige Warenkorbdaten
    // HINWEIS: Hier wurden loadRealCartData() und populateConfirmation() hinzugefügt.
    // Es wird angenommen, dass diese Funktionen global verfügbar sind oder importiert wurden.
    if (typeof loadRealCartData === 'function') {
        loadRealCartData().then(() => {
            if (typeof updateConfirmationProductList === 'function') {
                updateConfirmationProductList();
            }
            if (typeof updateConfirmationTotals === 'function') {
                updateConfirmationTotals();
            }
            if (typeof populateConfirmation === 'function') {
                populateConfirmation();
            }
        });
    }

    console.log('=== populateConfirmationWithPaymentData COMPLETED ===');
}


    // Globale Warenkorb-Anzeige Funktionalität
class YPrintOrderDisplay {
    constructor() {
        this.isVisible = false;
        this.init();
    }

    init() {
        // Event-Listener für alle "Bestellung anzeigen" Buttons
        $(document).on('click', '.yprint-summary-toggle', (e) => {
            e.preventDefault();
            this.toggleOrderDisplay(e.target);
        });
    }

    async toggleOrderDisplay(buttonElement) {
        const container = this.getOrCreateContainer(buttonElement);
        
        if (this.isVisible) {
            this.hideOrderDisplay(container);
        } else {
            await this.showOrderDisplay(container);
        }
    }

    getOrCreateContainer(buttonElement) {
        const buttonContainer = $(buttonElement).closest('.order-display-wrapper, .checkout-step, .button-container');
        let container = buttonContainer.find('.inline-order-display');
        
        if (container.length === 0) {
            container = $('<div class="inline-order-display" style="display: none; margin-top: 1rem;"></div>');
            buttonContainer.append(container);
        }
        
        return container;
    }

    async showOrderDisplay(container) {
        console.log('Showing order display...');
        
        // Lade aktuelle Warenkorbdaten
        await loadRealCartData();
        
        // Erstelle HTML für Warenkorb-Anzeige
        const orderHTML = this.generateOrderHTML();
        
        // Zeige mit Animation
        container.html(orderHTML);
        container.slideDown(300, () => {
            // Scroll sanft zum Container
            $('html, body').animate({
                scrollTop: container.offset().top - 100
            }, 300);
        });
        
        this.isVisible = true;
        this.updateButtonText(true);
    }

    hideOrderDisplay(container) {
        console.log('Hiding order display...');
        
        container.slideUp(300, () => {
            container.empty();
        });
        
        this.isVisible = false;
        this.updateButtonText(false);
    }

    updateButtonText(isVisible) {
        const buttons = $('.yprint-summary-toggle');
        buttons.each(function() {
            const $btn = $(this);
            const originalText = $btn.data('original-text') || $btn.text();
            
            if (!$btn.data('original-text')) {
                $btn.data('original-text', originalText);
            }
            
            if (isVisible) {
                $btn.html('<i class="fas fa-eye-slash mr-2"></i>Bestellung ausblenden');
            } else {
                $btn.html('<i class="fas fa-eye mr-2"></i>' + originalText.replace(/^.*?(Bestellung|Order)/, 'Bestellung anzeigen'));
            }
        });
    }

    generateOrderHTML() {
        if (!cartItems || cartItems.length === 0) {
            return `
                <div class="inline-cart-display border border-gray-200 rounded-lg p-4 bg-gray-50">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="font-semibold text-lg text-gray-800">
                            <i class="fas fa-shopping-cart mr-2 text-blue-600"></i>
                            Ihre Bestellung
                        </h3>
                        <span class="text-sm text-gray-500">Warenkorb ist leer</span>
                    </div>
                    <p class="text-gray-600 text-center py-4">Ihr Warenkorb ist leer.</p>
                </div>
            `;
        }

        const prices = calculatePrices();
        
        // Produktliste generieren
        const itemsHTML = cartItems.map(item => {
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

            // Design-Badge für Design-Produkte
            const designBadge = item.is_design_product ? 
                `<div class="design-badge" style="position: absolute; top: -5px; right: -5px; background: #0079FF; color: white; border-radius: 50%; width: 16px; height: 16px; display: flex; align-items: center; justify-content: center; font-size: 8px; border: 2px solid white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);" title="Design-Produkt">
                    <i class="fas fa-palette"></i>
                </div>` : '';

            // Stückpreis anzeigen wenn Menge > 1
            const unitPrice = item.quantity > 1 ? 
                `<div class="text-xs text-gray-500 mt-1">€${item.price.toFixed(2)} / Stück</div>` : '';

            return `
                <div class="cart-item flex items-start justify-between py-3 border-b border-gray-100 last:border-b-0">
                    <div class="flex items-start flex-1">
                        <div class="item-image-container relative mr-3 flex-shrink-0">
                            <img src="${item.image}" alt="${item.name}" 
                                 class="w-16 h-16 object-cover rounded border border-gray-200 bg-white">
                            ${designBadge}
                        </div>
                        <div class="flex-1">
                            <h4 class="font-medium text-sm text-gray-800 leading-tight">${item.name}</h4>
                            <p class="text-xs text-gray-600 mt-1">Menge: ${item.quantity}</p>
                            ${designDetailsHtml}
                            ${unitPrice}
                        </div>
                    </div>
                    <div class="text-right flex-shrink-0 ml-3">
                        <div class="font-medium text-sm text-gray-800">€${(item.price * item.quantity).toFixed(2)}</div>
                    </div>
                </div>
            `;
        }).join('');

        // Gesamtübersicht generieren
        const totalsHTML = `
            <div class="cart-totals mt-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Zwischensumme:</span>
                    <span class="font-medium">€${prices.subtotal.toFixed(2)}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Versand:</span>
                    <span class="font-medium">€${prices.shipping.toFixed(2)}</span>
                </div>
                ${prices.vat > 0 ? `
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">MwSt.:</span>
                    <span class="font-medium">€${prices.vat.toFixed(2)}</span>
                </div>` : ''}
                ${prices.discount > 0 ? `
                <div class="flex justify-between text-sm text-green-600">
                    <span>Rabatt:</span>
                    <span class="font-medium">-€${prices.discount.toFixed(2)}</span>
                </div>` : ''}
                <div class="flex justify-between text-base font-bold pt-2 border-t border-gray-200">
                    <span class="text-gray-800">Gesamtsumme:</span>
                    <span class="text-blue-600">€${prices.total.toFixed(2)}</span>
                </div>
            </div>
        `;

        return `
            <div class="inline-cart-display border border-gray-200 rounded-lg p-4 bg-white shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold text-lg text-gray-800">
                        <i class="fas fa-shopping-cart mr-2 text-blue-600"></i>
                        Ihre Bestellung
                    </h3>
                    <span class="text-sm text-gray-500">${cartItems.length} Artikel</span>
                </div>
                <div class="cart-items max-h-80 overflow-y-auto">
                    ${itemsHTML}
                </div>
                ${totalsHTML}
            </div>
        `;
    }
}

// Initialisiere Order Display System
window.YPrintOrderDisplay = new YPrintOrderDisplay();
    
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

    // Neue Funktion für statische Preisanzeige auf Bestätigungsseite
    function updateConfirmationTotalsStatic() {
        const prices = calculatePrices();
        
        const confirmSubtotalEl = document.getElementById('confirm-subtotal');
        const confirmShippingEl = document.getElementById('confirm-shipping');
        const confirmDiscountRowEl = document.getElementById('confirm-discount-row');
        const confirmDiscountEl = document.getElementById('confirm-discount');
        const confirmVatEl = document.getElementById('confirm-vat');
        const confirmTotalEl = document.getElementById('confirm-total');
    
        if (confirmSubtotalEl) {
            confirmSubtotalEl.textContent = `€${prices.subtotal.toFixed(2)}`;
            confirmSubtotalEl.setAttribute('data-static', 'true');
        }
        if (confirmShippingEl) {
            confirmShippingEl.textContent = `€${prices.shipping.toFixed(2)}`;
            confirmShippingEl.setAttribute('data-static', 'true');
        }
        if (confirmDiscountRowEl && confirmDiscountEl) {
            if (prices.discount > 0) {
                confirmDiscountEl.textContent = `-€${prices.discount.toFixed(2)}`;
                confirmDiscountRowEl.classList.remove('hidden');
            } else {
                confirmDiscountRowEl.classList.add('hidden');
            }
            confirmDiscountEl.setAttribute('data-static', 'true');
        }
        if (confirmVatEl) {
            confirmVatEl.textContent = `€${prices.vat.toFixed(2)}`;
            confirmVatEl.setAttribute('data-static', 'true');
        }
        if (confirmTotalEl) {
            confirmTotalEl.textContent = `€${prices.total.toFixed(2)}`;
            confirmTotalEl.setAttribute('data-static', 'true');
            
            // Hervorhebung des finalen Betrags
            confirmTotalEl.style.color = '#059669'; // Grün für bestätigte Bestellung
            confirmTotalEl.style.fontWeight = 'bold';
        }
        
        // Füge Bestätigungshinweis hinzu
        const totalsContainer = confirmTotalEl?.closest('.border-t');
        if (totalsContainer && !totalsContainer.querySelector('.final-order-confirmation')) {
            const confirmationNote = document.createElement('div');
            confirmationNote.className = 'final-order-confirmation mt-3 text-center text-sm text-green-700 bg-green-50 p-2 rounded';
            confirmationNote.innerHTML = '<i class="fas fa-shield-alt mr-1"></i> Finale Bestellsumme - Keine Änderungen mehr möglich';
            totalsContainer.appendChild(confirmationNote);
        }
    }
    

    // Debug: Prüfe ob Bestellung anzeigen Button erstellt werden sollte
    console.log('🔍 CONFIRMATION BUTTON CHECK:');
    
    // Suche nach Bereichen wo der Button stehen könnte
    const possibleContainers = [
        '#step-3',
        '.confirmation-section', 
        '.order-section',
        '.checkout-step.active',
        '.order-confirmation',
        '.order-complete'
    ];
    
    possibleContainers.forEach(selector => {
        const container = $(selector);
        if (container.length > 0) {
            console.log(`   Container "${selector}" gefunden:`);
            console.log(`     - Visible: ${container.is(':visible')}`);
            console.log(`     - Buttons: ${container.find('button, a').length}`);
            
            container.find('button, a').each(function(i) {
                const text = $(this).text().trim();
                console.log(`       Button ${i+1}: "${text}" (ID: ${$(this).attr('id') || 'no-id'})`);
            });
        } else {
            console.log(`   Container "${selector}" NICHT gefunden`);
        }
    });
    
    // Prüfe ob Order-ID verfügbar ist
    const urlParams = new URLSearchParams(window.location.search);
    const orderIdFromUrl = urlParams.get('order_id') || urlParams.get('order');
    const orderIdFromSession = sessionStorage.getItem('yprint_last_order_id');
    
    console.log('🔍 ORDER-ID VERFÜGBARKEIT:');
    console.log(`   - URL Parameter: ${orderIdFromUrl}`);
    console.log(`   - Session Storage: ${orderIdFromSession}`);
    console.log(`   - Should create button: ${!!(orderIdFromUrl || orderIdFromSession)}`);
    
    console.log('=== POPULATE CONFIRMATION DEBUG END ===');

    // Global verfügbar machen für Stripe Service
    window.populateConfirmationWithPaymentData = populateConfirmationWithPaymentData;
    
        // Verwende statische Preisanzeige für die Bestätigungsseite
        updateConfirmationTotalsStatic();
        
        console.log('=== POPULATE CONFIRMATION DEBUG END ===');
    

        
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
                
                // WICHTIG: Bestelldaten VOR Zahlungsverarbeitung speichern
                await saveOrderDataBeforePayment();
                
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
                        console.log('=== NORMAL CARD PAYMENT SUCCESS - UPDATING CONFIRMATION ===');
                        await populateConfirmation();
                        
                        // Stelle sicher, dass Payment Data gesetzt sind (bereits in processStripePaymentImmediately gemacht)
                        if (window.confirmationPaymentData) {
                            console.log('✅ confirmationPaymentData already set by payment success handler');
                        } else {
                            console.log('⚠️ Setting confirmationPaymentData from paymentResult');
                            window.confirmationPaymentData = paymentResult.data;
                            window.populateConfirmationWithPaymentData(paymentResult.data);
                        }
                        
                        showStep(3);
                        
                        // Force Payment Method Display Update nach kurzer Verzögerung
                        setTimeout(() => {
                            if (typeof updatePaymentMethodDisplay === 'function') {
                                console.log('🔄 Force updating payment method display for normal card');
                                updatePaymentMethodDisplay();
                            }
                        }, 100);
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
            const isSepaMounted = sepaContainer && (
    sepaContainer.querySelector('.StripeElement') ||
    sepaContainer.querySelector('.__PrivateStripeElement') ||
    sepaContainer.querySelector('iframe[name*="privateStripeFrame"]') ||
    (sepaContainer.innerHTML.length > 100 && sepaContainer.innerHTML.includes('iframe')) ||
    (sepaContainer.children.length > 0 && sepaContainer.innerHTML.includes('stripe'))
);

// Zusätzliche Logs für besseres Debugging
console.log('DEBUG SEPA: Container content length:', sepaContainer.innerHTML.length);
console.log('DEBUG SEPA: Contains iframe:', sepaContainer.innerHTML.includes('iframe'));
console.log('DEBUG SEPA: Contains PrivateStripeElement:', sepaContainer.innerHTML.includes('__PrivateStripeElement'));
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
    
    // Überspringe die Mount-Prüfung - das Element funktioniert bereits
    const stripe = window.YPrintStripeService.getStripe();
    console.log('Stripe instance:', stripe);
    
    if (!stripe) {
        console.error('DEBUG: Stripe instance not available from YPrintStripeService');
        throw new Error('Stripe Service nicht verfügbar');
    }
    
    if (!window.YPrintStripeCheckout.cardElement) {
        console.error('DEBUG: Card element not available');
        throw new Error('Card Element nicht verfügbar');
    }
    
    console.log('DEBUG: All systems ready, creating payment method...');
    
    // Nutze zentrale Kundendaten-Funktion (gleiche wie in SEPA-Implementierung)
const customerData = await getCustomerDataForPayment();
console.log('DEBUG: Customer data for payment method:', customerData);

if (!customerData.name || !customerData.email) {
    throw new Error('Kundendaten nicht verfügbar für Card Payment Method');
}

// KORREKTUR: Prüfe ob abweichende Rechnungsadresse existiert
let billingAddress = formData.shipping; // Fallback: Shipping als Billing

// Session-Abfrage für YPrint Billing-Adresse
try {
    // Prüfe ob yprint_address_ajax verfügbar ist
    if (typeof yprint_address_ajax === 'undefined' || !yprint_address_ajax.nonce) {
        console.warn('DEBUG: yprint_address_ajax not available, using shipping as billing');
        throw new Error('yprint_address_ajax nicht verfügbar');
    }
    
    const sessionResponse = await fetch(yprint_address_ajax.ajax_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            action: 'yprint_get_billing_session',
            nonce: yprint_address_ajax.nonce
        })
    });
    
    const sessionData = await sessionResponse.json();
    
    if (sessionData.success && sessionData.data.has_different_billing && sessionData.data.billing_address) {
        // Verwende YPrint Rechnungsadresse für Stripe Payment Method
        const yprintBilling = sessionData.data.billing_address;
        billingAddress = {
            street: yprintBilling.address_1 || '',
            housenumber: yprintBilling.address_2 || '',
            city: yprintBilling.city || '',
            zip: yprintBilling.postcode || '',
            country: yprintBilling.country || 'DE'
        };
        console.log('DEBUG: Using YPrint billing address for Stripe Payment Method:', billingAddress);
    } else {
        console.log('DEBUG: Using shipping address as billing for Stripe Payment Method');
    }
} catch (error) {
    console.warn('DEBUG: Could not fetch billing session, using shipping as fallback:', error);
}

// Sammle Billing-Details mit KORREKTER Rechnungsadresse
const billingDetails = {
    name: customerData.name,
    email: customerData.email,
    phone: customerData.phone || '',
    address: {
        line1: billingAddress.street || '',
        line2: billingAddress.housenumber || '',
        city: billingAddress.city || '',
        postal_code: billingAddress.zip || '',
        country: billingAddress.country || 'DE',
    }
};
    
    console.log('Creating card payment method with billing details:', billingDetails);
    console.log('DEBUG: About to call stripe.createPaymentMethod...');
    console.log('DEBUG: Card element before createPaymentMethod:', window.YPrintStripeCheckout.cardElement);

    try {
        // Timeout-Wrapper für createPaymentMethod
        const createPaymentMethodWithTimeout = () => {
            return Promise.race([
                stripe.createPaymentMethod({
                    type: 'card',
                    card: window.YPrintStripeCheckout.cardElement,
                    billing_details: billingDetails,
                }),
                new Promise((_, reject) => 
                    setTimeout(() => reject(new Error('createPaymentMethod timeout after 10 seconds')), 10000)
                )
            ]);
        };

        const { paymentMethod, error } = await createPaymentMethodWithTimeout();
        
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
}

// Hilfsfunktion für SEPA-Payment Method
async function createStripeSepaPaymentMethod() {
    console.log('=== DEBUG: createStripeSepaPaymentMethod START ===');
    
    const stripe = window.YPrintStripeService.getStripe();
    console.log('Stripe instance:', stripe);
    
    if (!stripe) {
        console.error('DEBUG: Stripe instance not available from YPrintStripeService');
        throw new Error('Stripe Service nicht verfügbar');
    }
    
    console.log('DEBUG: All systems ready, creating payment method...');
    
    // Nutze zentrale Kundendaten-Funktion (gleiche wie in Validierung)
    const customerData = await getCustomerDataForPayment();
    console.log('DEBUG: Customer data for payment method:', customerData);
    
    if (!customerData.name || !customerData.email) {
        throw new Error('Kundendaten nicht verfügbar für SEPA Payment Method');
    }
    
    // Sammle Billing-Details mit korrekten Kundendaten
    const billingDetails = {
        name: customerData.name,
        email: customerData.email,
        address: {
            line1: formData.shipping?.street || '',
            line2: formData.shipping?.housenumber || '',
            city: formData.shipping?.city || '',
            postal_code: formData.shipping?.zip || '',
            country: formData.shipping?.country || 'DE',
        }
    };
    
    console.log('Creating SEPA payment method with billing details:', billingDetails);
    
    // Korrektur: SEPA Payment Method erstellen, nicht Card
    const createPaymentMethodWithTimeout = () => {
        return Promise.race([
            stripe.createPaymentMethod({
                type: 'sepa_debit',
                sepa_debit: window.YPrintStripeCheckout.sepaElement,
                billing_details: billingDetails,
            }),
            new Promise((_, reject) => 
                setTimeout(() => reject(new Error('createPaymentMethod timeout after 10 seconds')), 10000)
            )
        ]);
    };

    console.log('DEBUG: Starting SEPA createPaymentMethod with timeout...');

    let createPaymentResult;
    try {
        createPaymentResult = await createPaymentMethodWithTimeout();
        console.log('DEBUG: SEPA createPaymentMethod completed normally');
    } catch (timeoutError) {
        console.error('DEBUG: SEPA createPaymentMethod timed out or failed:', timeoutError);
        throw new Error('SEPA Stripe Kommunikation fehlgeschlagen. Bitte versuchen Sie es erneut.');
    }

    const { paymentMethod, error } = createPaymentResult;
    console.log('DEBUG: Extracted SEPA paymentMethod and error from result');
    
    if (error) {
        console.error('SEPA payment method creation error:', error);
        throw new Error(error.message);
    }
    
    console.log('DEBUG: SEPA Payment method created successfully:', paymentMethod.id);
    return paymentMethod;
}

// Verarbeitung über Stripe Service
async function processPaymentViaStripeService(paymentMethod) {
    // CRITICAL: Secure design data BEFORE payment processing
console.log('=== SECURING DESIGN DATA BEFORE PAYMENT ===');
try {
    await secureExpressDesignData();
    console.log('Design data secured successfully');
} catch (error) {
    console.error('Failed to secure design data:', error);
    // Fortfahren, aber mit Warnung
}

// Processing payment via Stripe Service
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
        
        // KRITISCH: Stelle sicher, dass Payment Data an Confirmation weitergegeben werden
        console.log('=== STORING CONFIRMATION PAYMENT DATA FOR NORMAL CARDS ===');
        window.confirmationPaymentData = data;
        
        // Rufe populateConfirmationWithPaymentData auf (gleich wie bei Apple Pay)
        if (typeof window.populateConfirmationWithPaymentData === 'function') {
            console.log('✅ Calling populateConfirmationWithPaymentData for normal card payment');
            window.populateConfirmationWithPaymentData(data);
        } else {
            console.warn('⚠️ populateConfirmationWithPaymentData function not available');
        }
        
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
        if (!sepaElementContainer || (!sepaElementContainer.querySelector('.StripeElement') && !sepaElementContainer.querySelector('.__PrivateStripeElement'))) {
            console.log('SEPA element not mounted or visible');
            return false;
        }
        
        console.log('DEBUG: SEPA element is mounted and available');
        
        // Nutze zentrale Kundendaten-Funktion
        const customerData = await getCustomerDataForPayment();
        console.log('DEBUG: Customer data retrieved:', customerData);
        
        // SEPA-Validierung: Name UND Email sind für rechtsgültige Mandate erforderlich
if (!customerData.name || !customerData.email) {
    console.log('SEPA validation failed: Missing customer data');
    console.log('DEBUG: Name available:', !!customerData.name);
    console.log('DEBUG: Email available:', !!customerData.email);
    console.log('DEBUG: Data source:', customerData.source);
    return false;
}

// KRITISCH: Prüfe SEPA-Mandat-Zustimmung (EU-Recht erforderlich)
const sepaMandateConsent = document.getElementById('sepa-mandate-consent');
if (!sepaMandateConsent || !sepaMandateConsent.checked) {
    console.log('SEPA validation failed: Missing mandate consent');
    showMessage('Bitte stimmen Sie dem SEPA-Lastschriftmandat zu.', 'error');
    return false;
}

console.log('DEBUG: SEPA validation passed - complete customer data and mandate consent available');
console.log('DEBUG: Customer name:', customerData.name);
console.log('DEBUG: Customer email:', customerData.email);
console.log('DEBUG: Mandate consent given:', sepaMandateConsent.checked);
console.log('DEBUG: Data source:', customerData.source);

return true;
        
        console.log('DEBUG: SEPA validation passed - complete customer data available');
        console.log('DEBUG: Customer name:', customerData.name);
        console.log('DEBUG: Customer email:', customerData.email);
        console.log('DEBUG: Data source:', customerData.source);
        
        return true;
        
    } catch (e) {
        console.log('SEPA validation exception:', e);
        return false;
    }
}
    
/**
 * Zentrale Funktion für Kundendaten - nutzt WordPress REST API als primäre Quelle
 * Fallback-System für maximale Kompatibilität
 */
async function getCustomerDataForPayment() {
    console.log('DEBUG: Getting customer data for payment...');
    
    let customerName = '';
    let customerEmail = '';
    
    try {
        // PRIMÄRE QUELLE: WordPress REST API (funktioniert nachweislich)
let nonce = '';
if (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) {
    nonce = wpApiSettings.nonce;
} else if (typeof yprint_checkout_params !== 'undefined' && yprint_checkout_params.nonce) {
    nonce = yprint_checkout_params.nonce;
} else if (typeof wc_checkout_params !== 'undefined' && wc_checkout_params.checkout_nonce) {
    nonce = wc_checkout_params.checkout_nonce;
}

console.log('DEBUG: Using nonce source:', nonce ? 'Found' : 'NOT FOUND');

const userResponse = await fetch('/wp-json/wp/v2/users/me?context=edit', {
    method: 'GET',
    headers: {
        'X-WP-Nonce': nonce,
        'Content-Type': 'application/json'
    }
});
        
        if (userResponse.ok) {
            const userData = await userResponse.json();
            console.log('DEBUG: WordPress REST API data received:', userData);
            
            customerEmail = userData.email || '';
            
            // Name zusammensetzen - verschiedene Varianten probieren
            if (userData.name && userData.name.trim()) {
                customerName = userData.name.trim();
            } else if (userData.first_name || userData.last_name) {
                customerName = `${userData.first_name || ''} ${userData.last_name || ''}`.trim();
            } else if (userData.display_name) {
                customerName = userData.display_name.trim();
            }
            
            console.log('DEBUG: WordPress REST API - Name:', customerName);
            console.log('DEBUG: WordPress REST API - Email:', customerEmail);
            
            // Wenn wir vollständige Daten haben, können wir zurückgeben
            if (customerName && customerEmail) {
                return { name: customerName, email: customerEmail, source: 'WordPress REST API' };
            }
        }
    } catch (error) {
        console.log('DEBUG: WordPress REST API failed:', error);
    }

    // TEMPORÄRER DEBUG - entfernen nach Test
console.log('DEBUG: Available global variables:', {
    wpApiSettings: typeof wpApiSettings !== 'undefined' ? wpApiSettings : 'NOT AVAILABLE',
    yprint_checkout_params: typeof yprint_checkout_params !== 'undefined' ? yprint_checkout_params : 'NOT AVAILABLE',
    wc_checkout_params: typeof wc_checkout_params !== 'undefined' ? wc_checkout_params : 'NOT AVAILABLE'
});
    
    // FALLBACK 1: DOM-Felder (falls jemand doch etwas eingetippt hat)
    if (!customerEmail) {
        const emailField = document.getElementById('email') || 
                          document.getElementById('billing_email') ||
                          document.querySelector('input[type="email"]');
        if (emailField && emailField.value) {
            customerEmail = emailField.value;
            console.log('DEBUG: Email from DOM field:', customerEmail);
        }
    }
    
    // FALLBACK 2: formData (falls verfügbar)
    if (typeof formData !== 'undefined' && formData.shipping) {
        if (!customerName) {
            customerName = `${formData.shipping.first_name || ''} ${formData.shipping.last_name || ''}`.trim();
            console.log('DEBUG: Name from formData:', customerName);
        }
    }
    
    // FALLBACK 3: Address Manager Session (nur wenn andere Quellen versagen)
    if (!customerName || !customerEmail) {
        try {
            const selectedAddress = WC?.session?.get('yprint_selected_address');
            if (selectedAddress) {
                if (!customerName) {
                    customerName = `${selectedAddress.first_name || ''} ${selectedAddress.last_name || ''}`.trim();
                    console.log('DEBUG: Name from Address Manager:', customerName);
                }
                if (!customerEmail) {
                    customerEmail = selectedAddress.email || '';
                    console.log('DEBUG: Email from Address Manager:', customerEmail);
                }
            }
        } catch (error) {
            console.log('DEBUG: Address Manager fallback failed:', error);
        }
    }
    
    return { 
        name: customerName, 
        email: customerEmail, 
        source: customerName || customerEmail ? 'Fallback sources' : 'No data found' 
        
    };
    
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
        if (!sepaElementContainer || (!sepaElementContainer.querySelector('.StripeElement') && !sepaElementContainer.querySelector('.__PrivateStripeElement'))) {
            console.log('SEPA element not mounted or visible');
            return false;
        }
        
        console.log('DEBUG: SEPA element is mounted and available');
        
        // Nutze zentrale Kundendaten-Funktion
        const customerData = await getCustomerDataForPayment();
        console.log('DEBUG: Customer data retrieved:', customerData);
        
        // SEPA-Validierung: Name UND Email sind für rechtsgültige Mandate erforderlich
if (!customerData.name || !customerData.email) {
    console.log('SEPA validation failed: Missing customer data');
    console.log('DEBUG: Name available:', !!customerData.name);
    console.log('DEBUG: Email available:', !!customerData.email);
    console.log('DEBUG: Data source:', customerData.source);
    return false;
}

// KRITISCH: Prüfe SEPA-Mandat-Zustimmung (EU-Recht erforderlich)
const sepaMandateConsent = document.getElementById('sepa-mandate-consent');
if (!sepaMandateConsent || !sepaMandateConsent.checked) {
    console.log('SEPA validation failed: Missing mandate consent');
    showMessage('Bitte stimmen Sie dem SEPA-Lastschriftmandat zu.', 'error');
    return false;
}

console.log('DEBUG: SEPA validation passed - complete customer data and mandate consent available');
console.log('DEBUG: Customer name:', customerData.name);
console.log('DEBUG: Customer email:', customerData.email);
console.log('DEBUG: Mandate consent given:', sepaMandateConsent.checked);
console.log('DEBUG: Data source:', customerData.source);

return true;
        
        console.log('DEBUG: SEPA validation passed - complete customer data available');
        console.log('DEBUG: Customer name:', customerData.name);
        console.log('DEBUG: Customer email:', customerData.email);
        console.log('DEBUG: Data source:', customerData.source);
        
        return true;
        
    } catch (e) {
        console.log('SEPA validation exception:', e);
        return false;
    }
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
    // Event-Integration mit Address Manager (standardisiert)
    jQuery(document).on('address_selected', function(event, addressId, addressData) {
        console.log('📨 [DEBUG-CHECKOUT] ========================================');
        console.log('📨 [DEBUG-CHECKOUT] address_selected Event empfangen:', {
            addressId: addressId,
            addressData: addressData,
            eventTarget: event.target,
            timestamp: new Date().toISOString(),
            callStack: new Error().stack.split('\n').slice(1, 3)
        });
        
        // Prüfe Kontext (Shipping vs Billing)
        const addressType = $(event.target).closest('[data-address-type]').attr('data-address-type');
        const currentContext = window.currentAddressContext;
        const currentUrl = window.location.href;
        
        console.log('📨 [DEBUG-CHECKOUT] Kontext-Analyse:', {
            domAddressType: addressType,
            windowCurrentAddressContext: currentContext,
            currentUrl: currentUrl,
            isBillingUrl: currentUrl.includes('step=billing')
        });
        
        const isShippingContext = addressType === 'shipping' || currentContext === 'shipping' || !addressType;
        const isBillingContext = addressType === 'billing' || currentContext === 'billing' || currentUrl.includes('step=billing');
        
        console.log('📨 [DEBUG-CHECKOUT] Kontext-Entscheidung:', {
            isShippingContext: isShippingContext,
            isBillingContext: isBillingContext,
            willExecutePopulateFields: isShippingContext && !isBillingContext
        });
        
        if (isShippingContext && !isBillingContext) {
            console.log('📨 [DEBUG-CHECKOUT] ✅ Shipping-Kontext - populateCheckoutFields wird ausgeführt');
            
            // Session State VOR populateCheckoutFields (wenn verfügbar)
            if (typeof yprint_address_ajax !== 'undefined') {
                $.ajax({
                    url: yprint_address_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'yprint_debug_session_state',
                        nonce: yprint_address_ajax.nonce
                    },
                    success: function(debugResponse) {
                        console.log('🔍 [DEBUG-CHECKOUT] Session VOR populateCheckoutFields:', debugResponse.data);
                    }
                });
            }
            
            // Für Shipping-Kontext: Formular ausfüllen
            populateCheckoutFields(addressData);
            validateAddressForm(); // Button-Status aktualisieren
            
            // Session State NACH populateCheckoutFields (nach 100ms)
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
                            console.log('🔍 [DEBUG-CHECKOUT] Session NACH populateCheckoutFields:', debugResponse.data);
                        }
                    });
                }
            }, 100);
            
        } else {
            console.log('📨 [DEBUG-CHECKOUT] ❌ Shipping-Kontext abgelehnt - KEINE populateCheckoutFields');
            console.log('📨 [DEBUG-CHECKOUT] Grund: isBillingContext =', isBillingContext);
        }
    });
    
    // Address Manager Events für Shipping
    jQuery(document).on('address_saved', function(event, addressData) {
        if (addressData.type === 'shipping' || !addressData.type) {
            console.log('✅ Shipping address saved via Address Manager');
        }
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
                // Robuste Stripe SEPA Element Initialisierung mit Fallback
                setTimeout(async () => {
                    console.log('DEBUG: Ensuring Stripe SEPA Element is ready');
                    const isReady = await ensureStripeSepaElementReady();

                    // Fallback wenn ensureStripeSepaElementReady fehlschlägt
                    if (!isReady) {
                        console.log('DEBUG: Fallback - Direct SEPA init call');
                        if (window.YPrintStripeCheckout && window.YPrintStripeCheckout.initSepaElement) {
                            window.YPrintStripeCheckout.initSepaElement();
                        }
                    }

                    // CSS-Fix nach SEPA-Initialisierung
                    setTimeout(() => {
                        console.log('DEBUG: Applied CSS fixes for SEPA element');
                    }, 200);
                }, 100);
            }
        }, 50);

        // Session-Update mit der neuen Zahlungsart
        updatePaymentMethodSession(methodValue);

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
        console.log(`DEBUG MOUNT: Attempt ${attempts}/${maxAttempts} to ensure Card Element ready`);
        
        // Prüfe YPrintStripeCheckout Verfügbarkeit
        if (!window.YPrintStripeCheckout) {
            console.log('DEBUG MOUNT: YPrintStripeCheckout not available, waiting...');
            await new Promise(resolve => setTimeout(resolve, 1000));
            continue;
        }
        
        // Prüfe Initialisierung
        if (!window.YPrintStripeCheckout.initialized) {
            console.log('DEBUG MOUNT: YPrintStripeCheckout not initialized, waiting...');
            await new Promise(resolve => setTimeout(resolve, 1000));
            continue;
        }
        
        // DETAILLIERTE DEBUG-INFOS
        const cardContainer = document.getElementById('stripe-card-element');
        const hasContainer = !!cardContainer;
        const hasStripeElement = cardContainer ? !!cardContainer.querySelector('.StripeElement') : false;
        const hasCardElement = !!window.YPrintStripeCheckout.cardElement;
        
        console.log('DEBUG MOUNT: Container exists:', hasContainer);
        console.log('DEBUG MOUNT: Container has .StripeElement:', hasStripeElement);
        console.log('DEBUG MOUNT: cardElement exists:', hasCardElement);
        console.log('DEBUG MOUNT: Container innerHTML length:', cardContainer ? cardContainer.innerHTML.length : 'N/A');
        
        // Prüfe Card Element UND versuche es zu initialisieren
if (!window.YPrintStripeCheckout.cardElement) {
    console.log('DEBUG MOUNT: Card Element not available, trying to initialize...');
    
    try {
        // Versuche Card Element zu initialisieren/mounten
        const success = await window.YPrintStripeCheckout.initCardElement();
        
        console.log('DEBUG MOUNT: initCardElement returned:', success);
        console.log('DEBUG MOUNT: cardElement after init:', !!window.YPrintStripeCheckout.cardElement);
        
        if (success && window.YPrintStripeCheckout.cardElement) {
            console.log('DEBUG MOUNT: Card Element successfully initialized and mounted!');
            return true;
        }
    } catch (error) {
        console.error('DEBUG MOUNT: Error initializing Card Element:', error);
    }
    
    await new Promise(resolve => setTimeout(resolve, 500));
    continue;
}

// Element existiert - prüfe ob es gemountet ist (korrigierte Selektoren)
const isAlreadyMounted = cardContainer && (
    cardContainer.querySelector('.StripeElement') || 
    cardContainer.querySelector('.__PrivateStripeElement') ||
    cardContainer.querySelector('iframe[name*="privateStripeFrame"]')
);

if (cardContainer && !isAlreadyMounted) {
    console.log('DEBUG MOUNT: Card Element exists but not mounted, mounting now...');
    console.log('DEBUG MOUNT: Before mount - container content:', cardContainer.innerHTML);
    
    try {
        const success = await window.YPrintStripeCheckout.initCardElement();
        console.log('DEBUG MOUNT: Mount attempt returned:', success);
        
        // Nach Mount prüfen mit korrigierten Selektoren
        const afterMountHasElement = !!(
            cardContainer.querySelector('.StripeElement') ||
            cardContainer.querySelector('.__PrivateStripeElement') ||
            cardContainer.querySelector('iframe[name*="privateStripeFrame"]')
        );
        console.log('DEBUG MOUNT: After mount - has Stripe element:', afterMountHasElement);
        console.log('DEBUG MOUNT: After mount - container content length:', cardContainer.innerHTML.length);
        
        if (success && afterMountHasElement) {
            console.log('DEBUG MOUNT: Card Element successfully mounted!');
            return true;
        } else {
            console.log('DEBUG MOUNT: Mount failed or incomplete');
        }
    } catch (error) {
        console.error('DEBUG MOUNT: Error mounting Card Element:', error);
    }
    await new Promise(resolve => setTimeout(resolve, 500));
    continue;
}
        
        // Element existiert - prüfe ob es gemountet ist
        if (cardContainer && !cardContainer.querySelector('.StripeElement')) {
            console.log('DEBUG MOUNT: Card Element exists but not mounted, mounting now...');
            console.log('DEBUG MOUNT: Before mount - container content:', cardContainer.innerHTML);
            
            try {
                const success = await window.YPrintStripeCheckout.initCardElement();
                console.log('DEBUG MOUNT: Mount attempt returned:', success);
                
                // Nach Mount prüfen
                const afterMountHasElement = !!cardContainer.querySelector('.StripeElement');
                console.log('DEBUG MOUNT: After mount - has .StripeElement:', afterMountHasElement);
                console.log('DEBUG MOUNT: After mount - container content length:', cardContainer.innerHTML.length);
                
                if (success && afterMountHasElement) {
                    console.log('DEBUG MOUNT: Card Element successfully mounted!');
                    return true;
                } else {
                    console.log('DEBUG MOUNT: Mount failed or incomplete');
                }
            } catch (error) {
                console.error('DEBUG MOUNT: Error mounting Card Element:', error);
            }
            await new Promise(resolve => setTimeout(resolve, 500));
            continue;
        }
        
        // Alles ist bereit
        console.log('DEBUG MOUNT: Card Element is ready and mounted!');
        return true;
    }
    
    console.error('DEBUG MOUNT: Failed to ensure Card Element ready after', maxAttempts, 'attempts');
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
        
        // Prüfe sowohl Existenz als auch Mount-Status
const sepaContainer = document.getElementById('stripe-sepa-element');
const isProperlyMounted = sepaContainer && (
    sepaContainer.querySelector('.__PrivateStripeElement') ||
    sepaContainer.querySelector('iframe[name*="privateStripeFrame"]') ||
    (sepaContainer.innerHTML.length > 100 && sepaContainer.innerHTML.includes('iframe'))
);

if (!window.YPrintStripeCheckout.sepaElement || !isProperlyMounted) {
    console.log('SEPA Element not available or not mounted, trying to initialize...');
    
    try {
        if (window.YPrintStripeCheckout.initSepaElement) {
            const initResult = window.YPrintStripeCheckout.initSepaElement();
            console.log('SEPA Element init result:', initResult);
            
            await new Promise(resolve => setTimeout(resolve, 800));
            
            // Nochmalige Validierung nach Init
            const isNowMounted = sepaContainer && (
                sepaContainer.querySelector('.__PrivateStripeElement') ||
                sepaContainer.querySelector('iframe[name*="privateStripeFrame"]') ||
                (sepaContainer.innerHTML.length > 100 && sepaContainer.innerHTML.includes('iframe'))
            );
            
            if (window.YPrintStripeCheckout.sepaElement && isNowMounted) {
                console.log('SEPA Element successfully initialized and mounted!');
                return true;
            } else {
                console.log('SEPA Element init incomplete - element exists:', !!window.YPrintStripeCheckout.sepaElement, 'mounted:', isNowMounted);
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

jQuery(document).ready(function($) {
    // Umfassendes Debug für "Bestellung anzeigen" Button Problem
    function debugOrderViewButton() {
        console.log('=== COMPREHENSIVE ORDER VIEW BUTTON DEBUG ===');
        
        // 1. Suche nach allen möglichen Button-Varianten
        console.log('🔍 1. BUTTON-SUCHE NACH SELEKTOREN:');
        
        const selectors = [
            '#btn-view-order',
            '.btn-view-order', 
            'button[id*="view-order"]',
            'a[id*="view-order"]',
            'button[class*="view-order"]',
            'a[class*="view-order"]'
        ];
        
        selectors.forEach(selector => {
            // Hier wird $ korrekt auf jQuery verweisen
            const elements = $(selector); 
            console.log(`   Selector "${selector}": ${elements.length} gefunden`);
            elements.each(function(i) {
                console.log(`     - Element ${i+1}:`, this);
                console.log(`     - Text: "${$(this).text().trim()}"`);
                console.log(`     - Visible: ${$(this).is(':visible')}`);
            });
        });
        
        // 2. Suche nach Text-basierten Buttons
        console.log('🔍 2. TEXT-BASIERTE BUTTON-SUCHE:');
        
        const textSearches = [
            'Bestellung anzeigen',
            'bestellung anzeigen', 
            'Zur Bestellung',
            'zur bestellung',
            'Order View',
            'order view',
            'View Order',
            'view order',
            'Bestelldetails',
            'bestelldetails'
        ];
        
        textSearches.forEach(searchText => {
            // Hier wird $ korrekt auf jQuery verweisen
            const buttons = $('button, a').filter(function() {
                return $(this).text().toLowerCase().includes(searchText.toLowerCase());
            });
            console.log(`   Text "${searchText}": ${buttons.length} gefunden`);
            buttons.each(function(i) {
                console.log(`     - Button ${i+1}:`, this);
                console.log(`     - Exact Text: "${$(this).text().trim()}"`);
                console.log(`     - Visible: ${$(this).is(':visible')}`);
                console.log(`     - Parent: `, $(this).parent());
            });
        });
        
        // 3. Analysiere aktuelle Seiten-Struktur
        console.log('🔍 3. SEITEN-STRUKTUR ANALYSE:');
        console.log(`   - Aktueller Step: ${window.currentStep || 'undefined'}`);
        console.log(`   - URL: ${window.location.href}`);
        console.log(`   - URL Search: ${window.location.search}`);
        
        // 4. Checkout-Steps analysieren
        console.log('🔍 4. CHECKOUT-STEPS ANALYSE:');
        // Hier wird $ korrekt auf jQuery verweisen
        $('.checkout-step').each(function(i) { 
            const stepId = $(this).attr('id');
            const isActive = $(this).hasClass('active');
            const isVisible = $(this).is(':visible');
            const buttonCount = $(this).find('button, a').length;
            
            console.log(`   Step ${i+1} (${stepId}):`);
            console.log(`     - Active: ${isActive}`);
            console.log(`     - Visible: ${isVisible}`);
            console.log(`     - Buttons: ${buttonCount}`);
            
            if (buttonCount > 0) {
                // Hier wird $ korrekt auf jQuery verweisen
                $(this).find('button, a').each(function(j) { 
                    const buttonText = $(this).text().trim().toLowerCase();
                    if (buttonText.includes('bestellung') || buttonText.includes('order') || buttonText.includes('anzeigen') || buttonText.includes('view')) {
                        console.log(`       Button ${j+1}: "${buttonText}" (${$(this).attr('id') || 'no-id'})`);
                    }
                });
            }
        });
        
        // 5. Session/Storage Daten prüfen
        console.log('🔍 5. SESSION/STORAGE DATEN:');
        if (window.sessionStorage) {
            console.log(`   - yprint_last_order_id: ${sessionStorage.getItem('yprint_last_order_id')}`);
            console.log(`   - yprint_pending_order: ${sessionStorage.getItem('yprint_pending_order')}`);
        }
        
        if (window.localStorage) {
            const relevantKeys = [];
            for (let i = 0; i < localStorage.length; i++) {
                const key = localStorage.key(i);
                if (key.includes('order') || key.includes('yprint')) {
                    relevantKeys.push(`${key}: ${localStorage.getItem(key)}`);
                }
            }
            console.log(`   - LocalStorage relevante Keys: `, relevantKeys);
        }
        
        // 6. WooCommerce Session prüfen (falls verfügbar)
        console.log('🔍 6. WOOCOMMERCE SESSION:');
        if (window.yprint_checkout_params) {
            console.log(`   - Ajax URL: ${yprint_checkout_params.ajax_url}`);
            console.log(`   - Nonce: ${yprint_checkout_params.nonce}`);
            console.log(`   - Is logged in: ${yprint_checkout_params.is_logged_in}`);
        }
        
        // 7. Prüfe ob populateConfirmation aufgerufen wurde
        console.log('🔍 7. CONFIRMATION POPULATION CHECK:');
        console.log(`   - window.confirmationPopulated: ${window.confirmationPopulated || 'nicht gesetzt'}`);
        
        console.log('=== END COMPREHENSIVE DEBUG ===');
    }

    // Debug-Funktion alle 3 Sekunden ausführen
    let debugInterval = setInterval(() => {
        debugOrderViewButton();
    }, 3000);

    // Debug stoppen nach 30 Sekunden
    setTimeout(() => {
        clearInterval(debugInterval);
        console.log('🛑 DEBUG INTERVAL STOPPED nach 30 Sekunden');
    }, 30000);

    // Sofortiges Debug
    debugOrderViewButton();
});

/**
 * Aktualisiert die Session mit der gewählten Zahlungsart
 */
function updatePaymentMethodSession(paymentMethod) {
    console.log('DEBUG: Updating payment method session:', paymentMethod);
    
    const formData = new FormData();
    formData.append('action', 'yprint_set_payment_method');
    formData.append('payment_method', paymentMethod);
    formData.append('security', yprint_checkout.nonce);

    fetch(yprint_checkout.ajax_url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('DEBUG: Payment method session updated successfully');
            if (data.display_name) {
                console.log('DEBUG: Display name set to:', data.display_name);
            }
        } else {
            console.error('DEBUG: Failed to update payment method session:', data.message);
        }
    })
    .catch(error => {
        console.error('DEBUG: Error updating payment method session:', error);
    });
}

// Hilfsfunktion für Fallback Payment Method Text
function getFallbackPaymentMethodText(paymentMethodId) {
    if (paymentMethodId.startsWith('pm_') && paymentMethodId.includes('card')) {
        return '<i class="fas fa-credit-card mr-2"></i> Kreditkarte (Stripe)';
    } else if (paymentMethodId.includes('apple')) {
        return '<i class="fab fa-apple-pay mr-2"></i> Apple Pay';
    } else if (paymentMethodId.includes('sepa')) {
        return '<i class="fas fa-university mr-2"></i> SEPA Lastschrift';
    } else {
        return '<i class="fas fa-credit-card mr-2"></i> Express-Zahlung (Stripe)';
    }
}

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