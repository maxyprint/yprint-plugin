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
            
            // Prüfe Parameter
            if (typeof yprint_express_payment_params === 'undefined') {
                console.error('YPrint Express Checkout: Parameters not available');
                this.hideExpressPaymentContainer();
                return;
            }
    
            if (!yprint_express_payment_params.stripe || !yprint_express_payment_params.stripe.publishable_key) {
                console.error('YPrint Express Checkout: Stripe publishable key not available');
                this.hideExpressPaymentContainer();
                return;
            }
    
            // Initialisiere über zentralen Service
            const success = await window.YPrintStripeService.initialize(yprint_express_payment_params.stripe.publishable_key);
            
            if (!success) {
                console.error('YPrint Express Checkout: Stripe initialization failed');
                this.hideExpressPaymentContainer();
                return;
            }
    
            // Warte auf Service-Bereitschaft
            if (window.YPrintStripeService.isInitialized()) {
                await this.createPaymentRequest();
            } else {
                window.YPrintStripeService.on('initialized', async () => {
                    await this.createPaymentRequest();
                });
            }
        }

        async createPaymentRequest() {
            const stripe = window.YPrintStripeService.getStripe();
            if (!stripe) {
                console.error('YPrint Express Checkout: Stripe not available');
                this.hideExpressPaymentContainer();
                return;
            }
    
            const params = yprint_express_payment_params;
            
            console.log('YPrint Express Checkout: Creating payment request with params:', params);
            
            // Erstelle Payment Request Objekt
            this.paymentRequest = stripe.paymentRequest({
                country: params.checkout.country || 'DE',
                currency: params.checkout.currency || 'eur',
                total: {
                    label: params.checkout.total_label || 'YPrint Order',
                    amount: params.cart.total || 0,
                },
                requestPayerName: true,
                requestPayerEmail: true,
                requestPayerPhone: true,
                requestShipping: params.cart.needs_shipping || false,
            });
    
            console.log('YPrint Express Checkout: Payment Request created with total:', params.cart.total);
    
            // Prüfe Verfügbarkeit
            try {
                const result = await this.paymentRequest.canMakePayment();
                console.log('YPrint Express Checkout: canMakePayment result:', result);
                
                if (result) {
                    console.log('YPrint Express Checkout: Payment methods available:', result);
                    this.mountPaymentRequestButton(result);
                    this.setupEventHandlers();
                } else {
                    console.log('YPrint Express Checkout: No payment methods available on this device/browser');
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
                this.hideExpressPaymentContainer();
                return;
            }
    
            console.log('YPrint Express Checkout: Mounting payment request button...');
    
            // Erstelle Payment Request Button mit Settings aus WordPress
            const buttonSettings = yprint_express_payment_params.settings || {};
            
            try {
                const elements = window.YPrintStripeService.getElements();
                this.prButton = elements.create('paymentRequestButton', {
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
                this.prButton.mount('#yprint-payment-request-button');
                
                // Verstecke Loading und zeige Container
                this.hideExpressPaymentLoading();
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
                window.YPrintStripeService.handlePaymentMethod(event, { 
                    source: 'express_checkout',
                    type: 'payment_request'
                });
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

        hideExpressPaymentLoading() {
            const loading = document.querySelector('.express-payment-loading');
            if (loading) {
                loading.style.display = 'none';
            }
        }
    
        showExpressPaymentContainer() {
            const container = document.getElementById('yprint-express-payment-container');
            if (container) {
                container.style.display = 'block';
            }
            
            // Verstecke auch das Loading
            this.hideExpressPaymentLoading();
        }
    
        hideExpressPaymentContainer() {
            const container = document.getElementById('yprint-express-payment-container');
            if (container) {
                container.style.display = 'none';
            }
            
            const loading = document.querySelector('.express-payment-loading');
            if (loading) {
                loading.innerHTML = '<span style="color: #999; font-size: 14px;">Express-Zahlungen sind auf diesem Gerät nicht verfügbar</span>';
            }
            
            // Verstecke die gesamte Express Payment Section nach kurzer Zeit
            setTimeout(() => {
                const section = document.querySelector('.express-payment-section');
                if (section) {
                    section.style.display = 'none';
                }
            }, 2000);
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
const expressCheckoutInstance = new YPrintExpressCheckout();

// Integration mit Hauptcheckout
if (typeof window.showStep === 'function') {
    // Hook in main checkout navigation
    const originalShowStep = window.showStep;
    window.showStep = function(stepNumber) {
        originalShowStep(stepNumber);
        
        // Wenn Zahlungsschritt angezeigt wird, aktualisiere Express Payment Buttons
        if (stepNumber === 2 && expressCheckoutInstance && expressCheckoutInstance.updateAmount) {
            // Hole aktuelle Warenkorbsumme und aktualisiere Express Payment
            if (typeof calculatePrices === 'function') {
                const prices = calculatePrices();
                const amountInCents = Math.round(prices.total * 100);
                expressCheckoutInstance.updateAmount(amountInCents);
            }
        }
    };
}

// Checkout-Integration für Warenkorb-Updates
if (typeof jQuery !== 'undefined') {
    jQuery(document).on('checkout_updated', function(event, data) {
        if (expressCheckoutInstance && expressCheckoutInstance.updateAmount && data.total) {
            const amountInCents = Math.round(parseFloat(data.total) * 100);
            expressCheckoutInstance.updateAmount(amountInCents);
        }
    });
}

})(jQuery);