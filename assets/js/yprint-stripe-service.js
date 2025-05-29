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

            const response = await fetch(yprint_stripe_ajax.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'yprint_create_payment_intent',
                    nonce: yprint_stripe_ajax.nonce,
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
            console.log('YPrint Stripe Service: Processing payment method:', event.paymentMethod);

            try {
                // Standard-Verarbeitung
                const response = await fetch(yprint_stripe_ajax.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'yprint_process_payment_method',
                        nonce: yprint_stripe_ajax.nonce,
                        payment_method: JSON.stringify(event.paymentMethod),
                        shipping_address: event.shippingAddress ? JSON.stringify(event.shippingAddress) : '',
                        ...options
                    })
                });

                const data = await response.json();

                if (data.success) {
                    event.complete('success');
                    this.emit('payment_success', data.data);
                } else {
                    throw new Error(data.data?.message || 'Payment processing failed');
                }

            } catch (error) {
                console.error('YPrint Stripe Service: Payment processing failed:', error);
                event.complete('fail', { message: error.message });
                this.emit('payment_error', { error: error.message });
            }
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