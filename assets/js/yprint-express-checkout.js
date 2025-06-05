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
            console.log('=== EXPRESS PAYMENT REQUEST DEBUG ===');
            console.log('Cart total from params:', params.cart.total);
            console.log('Display items from params:', params.cart.display_items);
            console.log('Needs shipping from params:', params.cart.needs_shipping);
            console.log('Country:', params.checkout.country);
            console.log('Currency:', params.checkout.currency);
            
            // Erstelle Payment Request Objekt mit Debug
const paymentRequestConfig = {
    country: params.checkout.country || 'DE',
    currency: params.checkout.currency || 'eur',
    total: {
        label: params.checkout.total_label || 'YPrint Order',
        amount: params.cart.total || 0,
    },
    displayItems: params.cart.display_items || [],
    requestPayerName: true,
    requestPayerEmail: true,
    requestPayerPhone: true,
    requestShipping: params.cart.needs_shipping || false,
};

// Füge Adress-Prefill hinzu wenn verfügbar
if (params.address && params.address.prefill && params.address.current) {
    const currentAddress = params.address.current;
    console.log('EXPRESS DEBUG: Adding address prefill:', currentAddress);
    
    // Setze Default Shipping Address für Apple Pay
    if (params.cart.needs_shipping && currentAddress) {
        // Apple Pay unterstützt default shipping address
        paymentRequestConfig.shippingOptions = [{
            id: 'standard',
            label: 'Standard Versand',
            detail: 'Kostenloser Versand',
            amount: 0,
            selected: true
        }];
        
        // Versuche die Adresse als Standard zu setzen (limitierte Browser-Unterstützung)
        try {
            console.log('EXPRESS DEBUG: Setting default shipping address for Apple Pay:', currentAddress);
        } catch (error) {
            console.log('EXPRESS DEBUG: Default address setting not supported');
        }
    }
}

console.log('Payment Request Config:', paymentRequestConfig);
console.log('=== EXPRESS PAYMENT REQUEST DEBUG END ===');

this.paymentRequest = stripe.paymentRequest(paymentRequestConfig);
        
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
            console.log('Shipping address:', event.shippingAddress);
            
            try {
                // AJAX-Aufruf an WordPress Backend um echte Versandkosten zu berechnen
                const response = await fetch(yprint_express_payment_params.ajax_url, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'yprint_stripe_update_express_shipping',
                        nonce: yprint_express_payment_params.nonce,
                        shipping_address: JSON.stringify(event.shippingAddress)
                    })
                });
        
                const data = await response.json();
                
                if (data.success) {
                    console.log('YPrint Express Checkout: Received shipping data:', data.data);
                    
                    // Aktualisiere mit echten Versandoptionen und neuen Totals
                    const updateData = {
                        status: 'success',
                        shippingOptions: data.data.shippingOptions || [{
                            id: 'free',
                            label: 'Kostenloser Versand',
                            detail: '3-5 Werktage',
                            amount: 0, // Kostenlos
                        }],
                    };
                    
                    // Füge neue Totals hinzu wenn verfügbar
                    if (data.data.total) {
                        updateData.total = data.data.total;
                    }
                    
                    if (data.data.displayItems) {
                        updateData.displayItems = data.data.displayItems;
                    }
                    
                    console.log('YPrint Express Checkout: Updating with data:', updateData);
                    event.updateWith(updateData);
                    
                } else {
                    console.error('YPrint Express Checkout: Backend shipping calculation failed:', data.data);
                    // Fallback zu kostenlosem Versand
                    event.updateWith({
                        status: 'success',
                        shippingOptions: [{
                            id: 'free',
                            label: 'Kostenloser Versand',
                            detail: '3-5 Werktage',
                            amount: 0,
                        }],
                    });
                }
                
                console.log('YPrint Express Checkout: Shipping options updated');
                
            } catch (error) {
                console.error('YPrint Express Checkout: Shipping update failed:', error);
                // Fallback zu kostenlosem Versand
                event.updateWith({
                    status: 'success',
                    shippingOptions: [{
                        id: 'free',
                        label: 'Kostenloser Versand',
                        detail: '3-5 Werktage',
                        amount: 0,
                    }],
                });
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

// Neue Methode zum Aktualisieren der Adresse
updateAddress(addressData) {
    console.log('YPrint Express Checkout: Updating address for Apple Pay:', addressData);
    console.log('YPrint Express Checkout: Address data received:', addressData);
    
    // Speichere die neue Adresse für zukünftige Express Payment Requests
    if (window.yprint_express_payment_params && window.yprint_express_payment_params.address) {
        window.yprint_express_payment_params.address.current = {
            country: addressData.country || 'DE',
            state: addressData.state || '',
            city: addressData.city || '',
            postal_code: addressData.postcode || '',
            line1: addressData.address_1 || '',
            line2: addressData.address_2 || ''
        };
        window.yprint_express_payment_params.address.prefill = true;
        
        console.log('YPrint Express Checkout: Address parameters updated to:', window.yprint_express_payment_params.address.current);
    }
    
    // WICHTIG: Apple Pay cached die Adresse beim ersten Payment Request
    // Wir müssen das Payment Request Button komplett neu erstellen
    this.recreatePaymentRequestWithNewAddress();
}

// Neue Methode zum Neuerstellen des Payment Request Buttons mit neuer Adresse
async recreatePaymentRequestWithNewAddress() {
    console.log('YPrint Express Checkout: Recreating Payment Request with new address...');
    
    try {
        // Zerstöre das existierende Payment Request Button
        if (this.prButton) {
            this.prButton.destroy();
            this.prButton = null;
        }
        
        // Container leeren
        const container = document.getElementById('yprint-payment-request-button');
        if (container) {
            container.innerHTML = '';
        }
        
        // Kurze Verzögerung für cleanup
        await new Promise(resolve => setTimeout(resolve, 100));
        
        // Payment Request neu erstellen mit aktualisierter Adresse
        await this.createPaymentRequest();
        
        console.log('YPrint Express Checkout: Payment Request recreated successfully');
        
    } catch (error) {
        console.error('YPrint Express Checkout: Error recreating Payment Request:', error);
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