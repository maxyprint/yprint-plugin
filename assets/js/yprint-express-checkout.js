/**
 * YPrint Express Checkout (Apple Pay / Google Pay)
 */
(function($) {
    'use strict';

    class YPrintExpressCheckout {
        constructor() {
            this.stripe = null;
            this.paymentRequest = null;
            this.prButton = null;
            this.initialized = false;
            
            // Warte auf DOM und Stripe-Verfügbarkeit
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => this.init());
            } else {
                this.init();
            }
        }

        async init() {
            console.log('YPrint Express Checkout: Initializing...');
            
            // Prüfe ob Stripe verfügbar ist
            if (typeof Stripe === 'undefined') {
                console.error('YPrint Express Checkout: Stripe.js not loaded');
                this.hideExpressPaymentContainer();
                return;
            }

            // Prüfe ob Parameter verfügbar sind
            if (typeof yprint_express_payment_params === 'undefined') {
                console.error('YPrint Express Checkout: Parameters not available');
                this.hideExpressPaymentContainer();
                return;
            }

            // Initialisiere Stripe
            try {
                this.stripe = Stripe(yprint_express_payment_params.stripe.publishable_key);
                console.log('YPrint Express Checkout: Stripe initialized');
            } catch (error) {
                console.error('YPrint Express Checkout: Stripe initialization failed:', error);
                this.hideExpressPaymentContainer();
                return;
            }

            // Erstelle Payment Request
            await this.createPaymentRequest();
        }

        async createPaymentRequest() {
            const params = yprint_express_payment_params;
            
            // Erstelle Payment Request Objekt
            this.paymentRequest = this.stripe.paymentRequest({
                country: params.checkout.country,
                currency: params.checkout.currency,
                total: {
                    label: params.checkout.total_label,
                    amount: params.cart.total,
                },
                requestPayerName: true,
                requestPayerEmail: true,
                requestPayerPhone: true,
                requestShipping: params.cart.needs_shipping,
            });

            console.log('YPrint Express Checkout: Payment Request created with total:', params.cart.total);

            // Prüfe Verfügbarkeit
            try {
                const result = await this.paymentRequest.canMakePayment();
                if (result) {
                    console.log('YPrint Express Checkout: Payment methods available:', result);
                    this.mountPaymentRequestButton(result);
                    this.setupEventHandlers();
                } else {
                    console.log('YPrint Express Checkout: No payment methods available');
                    this.hideExpressPaymentContainer();
                }
            } catch (error) {
                console.error('YPrint Express Checkout: canMakePayment failed:', error);
                this.hideExpressPaymentContainer();
            }
        }

        mountPaymentRequestButton(result) {
            const container = document.getElementById('yprint-payment-request-button');
            if (!container) {
                console.error('YPrint Express Checkout: Button container not found');
                return;
            }

            // Erstelle Payment Request Button mit Settings aus WordPress
            const buttonSettings = yprint_express_payment_params.settings || {};
            this.prButton = this.stripe.elements().create('paymentRequestButton', {
                paymentRequest: this.paymentRequest,
                style: {
                    paymentRequestButton: {
                        type: buttonSettings.button_type || 'default',
                        theme: buttonSettings.button_theme || 'dark',
                        height: (buttonSettings.button_height || '48') + 'px',
                    },
                },
            });

            // Mounte den Button
            try {
                this.prButton.mount('#yprint-payment-request-button');
                this.showExpressPaymentContainer();
                console.log('YPrint Express Checkout: Button mounted successfully');
            } catch (error) {
                console.error('YPrint Express Checkout: Button mount failed:', error);
                this.hideExpressPaymentContainer();
            }
        }

        setupEventHandlers() {
            // Payment Method Event
            this.paymentRequest.on('paymentmethod', (event) => {
                console.log('YPrint Express Checkout: Payment method received:', event.paymentMethod);
                this.handlePaymentMethod(event);
            });

            // Shipping Address Change Event
            if (yprint_express_payment_params.cart.needs_shipping) {
                this.paymentRequest.on('shippingaddresschange', (event) => {
                    console.log('YPrint Express Checkout: Shipping address changed:', event.shippingAddress);
                    this.handleShippingAddressChange(event);
                });
            }
        }

        async handlePaymentMethod(event) {
            console.log('YPrint Express Checkout: Processing payment method...');
            
            try {
                // Hier würde in Phase 3 die tatsächliche Zahlungsverarbeitung stattfinden
                // Für Phase 1 simulieren wir nur eine erfolgreiche Verarbeitung
                
                // Simuliere Backend-Verarbeitung
                await new Promise(resolve => setTimeout(resolve, 1000));
                
                // Markiere als erfolgreich
                event.complete('success');
                
                // Zeige Erfolgsmeldung
                this.showSuccessMessage();
                
                console.log('YPrint Express Checkout: Payment completed successfully');
                
            } catch (error) {
                console.error('YPrint Express Checkout: Payment processing failed:', error);
                event.complete('fail', { message: 'Payment processing failed' });
            }
        }

        async handleShippingAddressChange(event) {
            console.log('YPrint Express Checkout: Processing shipping address change...');
            
            try {
                // Hier würde in Phase 2 die Versandkostenberechnung stattfinden
                // Für Phase 1 simulieren wir nur eine einfache Antwort
                
                event.updateWith({
                    status: 'success',
                    shippingOptions: [{
                        id: 'standard',
                        label: 'Standard Versand',
                        detail: '3-5 Werktage',
                        amount: 499, // 4,99 EUR in Cent
                    }],
                });
                
                console.log('YPrint Express Checkout: Shipping options updated');
                
            } catch (error) {
                console.error('YPrint Express Checkout: Shipping update failed:', error);
                event.updateWith({ status: 'fail' });
            }
        }

        showExpressPaymentContainer() {
            const container = document.getElementById('yprint-express-payment-container');
            if (container) {
                container.style.display = 'block';
            }
        }

        hideExpressPaymentContainer() {
            const container = document.getElementById('yprint-express-payment-container');
            if (container) {
                container.style.display = 'none';
            }
        }

        showSuccessMessage() {
            // Temporäre Erfolgsmeldung für Phase 1 Testing
            const message = document.createElement('div');
            message.className = 'yprint-express-success-message';
            message.innerHTML = '<i class="fas fa-check-circle"></i> Express Payment erfolgreich! (Demo-Modus)';
            message.style.cssText = 'position: fixed; top: 20px; right: 20px; background: #28a745; color: white; padding: 15px; border-radius: 5px; z-index: 9999;';
            
            document.body.appendChild(message);
            
            setTimeout(() => {
                if (message.parentNode) {
                    message.parentNode.removeChild(message);
                }
            }, 3000);
        }

        // Öffentliche Methode zum Aktualisieren des Betrags (für Phase 2)
        updateAmount(newAmount) {
            if (this.paymentRequest) {
                this.paymentRequest.update({
                    total: {
                        label: yprint_express_payment_params.checkout.total_label,
                        amount: newAmount,
                    },
                });
                console.log('YPrint Express Checkout: Amount updated to:', newAmount);
            }
        }
    }

    // Global verfügbar machen für Integration
    window.YPrintExpressCheckout = YPrintExpressCheckout;

    // Automatisch initialisieren
    new YPrintExpressCheckout();

})(jQuery);