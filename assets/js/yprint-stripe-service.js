/**
 * YPrint Stripe Service - Zentrale Stripe-Verwaltung
 * Singleton-Pattern zur Vermeidung von doppelten Initialisierungen
 */
(function(window) {
    'use strict';

    class YPrintStripeService {
        constructor() {
            this.stripe = null;
            this.elements = null;
            this.initialized = false;
            this.publishableKey = null;
            this.eventListeners = new Map();
        }

        /**
         * Initialisiert Stripe mit dem Public Key
         * @param {string} publishableKey - Stripe Publishable Key
         * @returns {Promise<boolean>} - Success status
         */
        async initialize(publishableKey) {
            if (this.initialized && this.publishableKey === publishableKey) {
                console.log('YPrint Stripe Service: Already initialized');
                return true;
            }

            if (typeof Stripe === 'undefined') {
                console.error('YPrint Stripe Service: Stripe.js not loaded');
                return false;
            }

            if (!publishableKey) {
                console.error('YPrint Stripe Service: No publishable key provided');
                return false;
            }

            try {
                this.stripe = Stripe(publishableKey);
                this.elements = this.stripe.elements();
                this.publishableKey = publishableKey;
                this.initialized = true;
                
                console.log('YPrint Stripe Service: Initialized successfully');
                this.emit('initialized', { stripe: this.stripe, elements: this.elements });
                return true;
            } catch (error) {
                console.error('YPrint Stripe Service: Initialization failed:', error);
                return false;
            }
        }

        /**
         * Liefert die Stripe-Instanz zurück
         * @returns {object|null} - Stripe instance
         */
        getStripe() {
            if (!this.initialized) {
                console.warn('YPrint Stripe Service: Not initialized yet');
                return null;
            }
            return this.stripe;
        }

        /**
         * Liefert die Elements-Instanz zurück
         * @returns {object|null} - Stripe Elements instance
         */
        getElements() {
            if (!this.initialized) {
                console.warn('YPrint Stripe Service: Not initialized yet');
                return null;
            }
            return this.elements;
        }

        /**
         * Prüft ob Service initialisiert ist
         * @returns {boolean}
         */
        isInitialized() {
            return this.initialized;
        }

        /**
         * Erstellt ein Payment Intent
         * @param {number} amount - Betrag in Cent
         * @param {string} currency - Währung (z.B. 'eur')
         * @param {object} metadata - Zusätzliche Metadaten
         * @returns {Promise<object>} - Payment Intent Response
         */
        async createPaymentIntent(amount, currency = 'eur', metadata = {}) {
            if (!this.initialized) {
                throw new Error('Stripe service not initialized');
            }

            // ✅ KORREKT: Verwende die richtige Lokalisierung
            const ajaxConfig = window.yprint_stripe_ajax || window.yprint_stripe_vars;
            if (!ajaxConfig || !ajaxConfig.ajax_url) {
                throw new Error('Stripe AJAX configuration not available');
            }

            const response = await fetch(ajaxConfig.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'yprint_create_payment_intent',
                    nonce: ajaxConfig.nonce,
                    amount: amount,
                    currency: currency,
                    metadata: JSON.stringify(metadata)
                })
            });

            if (!response.ok) {
                throw new Error('Network response was not ok');
            }

            const data = await response.json();
            
            if (!data.success) {
                throw new Error(data.data?.message || 'Payment Intent creation failed');
            }

            return data.data;
        }

        /**
 * Verarbeitet Payment Method Events
 * @param {object} event - Stripe Payment Method Event
 * @param {object} options - Processing options
 * @returns {Promise<void>}
 */
async handlePaymentMethod(event, options = {}) {
    console.log('YPrint Stripe Service: Handling payment method');
    
    // ✅ KORREKT: Verwende die richtige Lokalisierung
    const ajaxConfig = window.yprint_stripe_ajax || window.yprint_stripe_vars;
    console.log('AJAX URL:', ajaxConfig?.ajax_url);
    console.log('Nonce:', ajaxConfig?.nonce);
    
    if (!this.initialized) {
        console.error('YPrint Stripe Service: Not initialized');
        return;
    }

    // Validierung der erforderlichen Parameter
    if (!ajaxConfig?.ajax_url) {
        throw new Error('AJAX URL nicht verfügbar');
    }
    
    if (!ajaxConfig?.nonce) {
        throw new Error('Nonce nicht verfügbar');
    }
    
    if (!event.paymentMethod?.id) {
        throw new Error('Payment Method ID fehlt');
    }

    try {
        // Prepare request data
        const requestData = {
            action: 'yprint_process_payment_method',
            nonce: ajaxConfig.nonce,
            payment_method: JSON.stringify(event.paymentMethod),
            shipping_address: event.shippingAddress ? JSON.stringify(event.shippingAddress) : '',
            ...options
        };
        
        console.log('=== AJAX REQUEST DATA ===');
// Debug boolean parameters specifically
Object.keys(requestData).forEach(key => {
    const value = requestData[key];
    if (typeof value === 'boolean') {
        console.log(`FRONTEND BOOLEAN: ${key} = ${value} (type: ${typeof value})`);
    } else if (value === '1' || value === '0' || value === 'true' || value === 'false') {
        console.log(`FRONTEND STRING BOOLEAN: ${key} = "${value}" (type: ${typeof value})`);
    }
});
console.log('Payment Method Object:', event.paymentMethod);
console.log('Payment Method JSON String:', JSON.stringify(event.paymentMethod));
console.log('Payment Method JSON Length:', JSON.stringify(event.paymentMethod).length);
console.log('Shipping Address Object:', event.shippingAddress);
console.log('Shipping Address JSON String:', event.shippingAddress ? JSON.stringify(event.shippingAddress) : 'null');
console.log('Request Data Serialized:', new URLSearchParams(requestData).toString());
console.log('URLSearchParams size:', new URLSearchParams(requestData).toString().length);

    console.log('=== MAKING AJAX REQUEST ===');
    const response = await fetch(ajaxConfig.ajax_url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams(requestData)
    });

    console.log('=== AJAX RESPONSE RECEIVED ===');
    console.log('Response Status:', response.status);
    console.log('Response Status Text:', response.statusText);
    console.log('Response Headers:', Object.fromEntries(response.headers.entries()));
    console.log('Response OK:', response.ok);

    // Response Text für Debugging lesen
    const responseText = await response.text();
    console.log('=== RAW RESPONSE TEXT ===');
    console.log('Raw Response Length:', responseText.length);
    console.log('Raw Response (first 1000 chars):', responseText.substring(0, 1000));
    
    if (!response.ok) {
        console.error('=== HTTP ERROR ===');
        console.error('Status:', response.status);
        console.error('Status Text:', response.statusText);
        console.error('Response Text:', responseText);
        throw new Error(`HTTP Error ${response.status}: ${response.statusText}`);
    }

    let data;
    try {
        data = JSON.parse(responseText);
console.log('=== PARSED JSON RESPONSE ===');
console.log('Parsed Data:', data);
console.log('Data Success:', data.success);
console.log('Data Message:', data.data?.message);
console.log('Data Details:', data.data);

// AddressOrchestrator Debug-Logs ausgeben (falls vorhanden)
if (data.data && data.data.debug_logs && Array.isArray(data.data.debug_logs)) {
    console.log('🎯 === ADDRESSORCHESTRATOR DEBUG LOGS ===');
    data.data.debug_logs.forEach(log => console.log(log));
    console.log('🎯 === ADDRESSORCHESTRATOR DEBUG END ===');
}
    } catch (parseError) {
        console.error('=== JSON PARSE ERROR ===');
        console.error('Parse Error:', parseError);
        console.error('Response Text:', responseText);
        throw new Error(`JSON Parse Error: ${parseError.message}. Response: ${responseText.substring(0, 200)}`);
    }

    if (data.success) {
        console.log('=== PAYMENT SUCCESS ===');
        console.log('Success Data:', data.data);
        event.complete('success');
        this.emit('payment_success', data.data);

        // === STORING CONFIRMATION PAYMENT DATA FOR EXPRESS PAYMENTS ===
        if (data.data && data.data.order_data && data.data.order_data.payment_method_details) {
            const paymentMethodDetails = data.data.order_data.payment_method_details;
            window.confirmationPaymentData = {
                order_data: {
                    payment_method_details: paymentMethodDetails,
                    order_id: data.data.order_id,
                    payment_intent_id: data.data.payment_intent_id
                },
                source: 'express_payment',
                type: paymentMethodDetails.wallet?.type || paymentMethodDetails.type || 'unknown'
            };
            console.log('✅ Express Payment Confirmation Data gespeichert:', window.confirmationPaymentData);
        }
        // Stay in checkout and go to confirmation step
        if (data.data && data.data.next_step === 'confirmation') {
            setTimeout(() => {
                // Use the global showStep function to go to confirmation
                if (typeof window.showStep === 'function') {
                    window.showStep(3); // Step 3 is confirmation
                } else {
                    console.error('showStep function not available');
                }
            }, 1000); // Small delay to show success state
        }
    } else {
        // KORREKTUR: SEPA "processing" als Erfolg behandeln
        if (data.data?.message === 'Payment Intent confirmation failed: processing') {
            console.log('=== SEPA PROCESSING - TREATING AS SUCCESS ===');
            console.log('SEPA payment is being processed asynchronously');
            event.complete('success');
            this.emit('payment_success', { 
                message: 'SEPA payment initiated successfully',
                sepa_processing: true 
            });
            
            // Gehe zu Confirmation Step mit SEPA-spezifischer Nachricht
            setTimeout(() => {
                if (typeof window.showStep === 'function') {
                    window.showStep(3);
                    
                    // Zeige SEPA-spezifische Bestätigungsnachricht
                    if (typeof window.showSepaProcessingConfirmation === 'function') {
                        window.showSepaProcessingConfirmation();
                    }
                }
            }, 1000);
            return; // Wichtig: Nicht als Fehler behandeln
        }
        
        console.error('=== PAYMENT FAILED ===');
        console.error('Error Message:', data.data?.message);
        console.error('Error Details:', data.data);
        console.error('Full Response Data:', data);
        console.error('Response Success Flag:', data.success);
        console.error('Response Data Type:', typeof data.data);
        
        // Specific debugging for "Invalid payment method data" error
        if (data.data?.message === 'Invalid payment method data') {
            console.error('=== PAYMENT METHOD DATA ERROR DEBUGGING ===');
            console.error('Original Payment Method Object:', event.paymentMethod);
            console.error('Payment Method ID:', event.paymentMethod?.id);
            console.error('Payment Method Type:', event.paymentMethod?.type);
            console.error('JSON Stringified Payment Method:', JSON.stringify(event.paymentMethod));
            console.error('JSON String Length:', JSON.stringify(event.paymentMethod).length);
        }
        
        throw new Error(data.data?.message || 'Payment processing failed');
    }

} catch (error) {
    console.error('=== PAYMENT PROCESSING EXCEPTION ===');
    console.error('Error Name:', error.name);
    console.error('Error Message:', error.message);
    console.error('Error Stack:', error.stack);
    console.error('Full Error Object:', error);
    
    event.complete('fail', { message: error.message });
    this.emit('payment_error', { error: error.message });
}
    
    console.log('=== YPrint Stripe Service: PAYMENT METHOD PROCESSING END ===');
}

        /**
         * DOM-Hilfsfunktionen
         */
        showElement(selector) {
            const element = document.querySelector(selector);
            if (element) {
                element.style.display = 'block';
                element.classList.remove('hidden');
            }
        }

        hideElement(selector) {
            const element = document.querySelector(selector);
            if (element) {
                element.style.display = 'none';
                element.classList.add('hidden');
            }
        }

        /**
         * Event System für Kommunikation zwischen Modulen
         */
        on(eventName, callback) {
            if (!this.eventListeners.has(eventName)) {
                this.eventListeners.set(eventName, []);
            }
            this.eventListeners.get(eventName).push(callback);
        }

        emit(eventName, data) {
            if (this.eventListeners.has(eventName)) {
                this.eventListeners.get(eventName).forEach(callback => {
                    try {
                        callback(data);
                    } catch (error) {
                        console.error(`YPrint Stripe Service: Event listener error for ${eventName}:`, error);
                    }
                });
            }
        }
    }

    // Singleton-Instanz erstellen und global verfügbar machen
    window.YPrintStripeService = new YPrintStripeService();

})(window);