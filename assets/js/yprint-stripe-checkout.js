/**
 * YPrint Stripe Checkout JavaScript
 * 
 * Handles Stripe specific checkout functionality
 */
jQuery(document).ready(function($) {
    // Stripe client objects
    let stripe = null;
    let elements = null;
    let cardElement = null;
    let paymentRequest = null;
    
    /**
     * Initialize Stripe for checkout
     */
    function initStripeCheckout() {
        if (typeof Stripe === 'undefined' || !yprintStripe.public_key) {
            console.error('Stripe not available or missing configuration');
            return;
        }
        
        // Initialize Stripe
        stripe = Stripe(yprintStripe.public_key);
        elements = stripe.elements();
        
        // Create card element
        setupCardElement();
        
        // Setup payment request buttons (Apple Pay, Google Pay)
        setupPaymentRequest();
    }
    
    /**
     * Setup Stripe card element
     */
    function setupCardElement() {
        // Create and mount the card element
        const cardElementContainer = document.getElementById('yprint-stripe-card-element');
        
        if (!cardElementContainer) {
            return;
        }
        
        // Card element options
        const cardOptions = {
            style: {
                base: {
                    color: '#1d1d1f',
                    fontFamily: '"SF Pro Text", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, sans-serif',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#6e6e73'
                    }
                },
                invalid: {
                    color: '#dc3545',
                    iconColor: '#dc3545'
                }
            }
        };
        
        // Create the card element
        cardElement = elements.create('card', cardOptions);
        cardElement.mount(cardElementContainer);
        
        // Handle card validation errors
        cardElement.on('change', function(event) {
            const displayError = document.getElementById('yprint-stripe-card-errors');
            
            if (event.error) {
                displayError.textContent = event.error.message;
            } else {
                displayError.textContent = '';
            }
        });
        
        // Handle form submission to create payment method
        $('#yprint-payment-form').on('submit', function(e) {
            e.preventDefault();
            
            const paymentMethod = $('input[name="payment_method"]:checked').val();
            
            if (paymentMethod !== 'stripe') {
                // If not using Stripe, handle normally
                savePaymentMethod(paymentMethod);
                return;
            }
            
            const savedPaymentMethod = $('input[name="saved_payment_method"]:checked').val();
            
            if (savedPaymentMethod && savedPaymentMethod !== 'new') {
                // Using a saved payment method
                savePaymentMethod(paymentMethod, savedPaymentMethod);
                return;
            }
            
            // Using a new card
            createStripePaymentMethod();
        });
    }
    
    /**
     * Create a new Stripe payment method
     */
    function createStripePaymentMethod() {
        // Show loading state
        $('#yprint-payment-form').addClass('yprint-loading');
        $('#yprint-stripe-card-errors').text('');
        
        // Create payment method
        stripe.createPaymentMethod({
            type: 'card',
            card: cardElement,
            billing_details: {
                name: $('#billing_first_name').val() + ' ' + $('#billing_last_name').val(),
                email: $('#billing_email').val()
            }
        }).then(function(result) {
            if (result.error) {
                // Show error
                $('#yprint-stripe-card-errors').text(yprintStripe.card_error + result.error.message);
                $('#yprint-payment-form').removeClass('yprint-loading');
            } else {
                // Payment method created successfully
                const paymentMethodId = result.paymentMethod.id;
                savePaymentMethod('stripe', null, paymentMethodId);
            }
        });
    }
    
    /**
     * Save the selected payment method
     */
    function savePaymentMethod(method, savedId = null, token = null) {
        const paymentData = {
            method: method,
            saved_id: savedId,
            token: token,
            save: $('#save_payment_method').is(':checked')
        };
        
        // Save payment method via AJAX
        $.ajax({
            url: yprintCheckout.ajaxurl,
            type: 'POST',
            data: {
                action: 'yprint_set_payment_method',
                security: yprintCheckout.checkout_nonce,
                payment: paymentData
            },
            beforeSend: function() {
                $('#yprint-payment-form').addClass('yprint-loading');
            },
            success: function(response) {
                if (response.success) {
                    // Go to next step
                    window.location.href = window.location.pathname + '?step=confirmation';
                } else {
                    alert(response.data.message || yprintStripe.generic_error);
                }
            },
            error: function() {
                alert(yprintStripe.generic_error);
            },
            complete: function() {
                $('#yprint-payment-form').removeClass('yprint-loading');
            }
        });
    }
    
    /**
     * Setup Payment Request buttons (Apple Pay, Google Pay)
     */
    function setupPaymentRequest() {
        // Check if Payment Request is available
        if (!stripe || !stripe.paymentRequest) {
            return;
        }
        
        // Get cart total
        const cartTotal = parseFloat($('.yprint-cart-total span:last-child').text().replace(/[^0-9,.]/g, '').replace(',', '.'));
        
        if (isNaN(cartTotal) || cartTotal <= 0) {
            return;
        }
        
        // Create a Payment Request
        paymentRequest = stripe.paymentRequest({
            country: 'DE',
            currency: 'eur',
            total: {
                label: 'YPrint Bestellung',
                amount: Math.round(cartTotal * 100) // Convert to cents
            },
            requestPayerName: true,
            requestPayerEmail: true,
            requestPayerPhone: true,
            requestShipping: true
        });
        
        // Create Payment Request Button
        const prButton = elements.create('paymentRequestButton', {
            paymentRequest: paymentRequest,
            style: {
                paymentRequestButton: {
                    type: 'default',
                    theme: 'dark',
                    height: '48px'
                }
            }
        });
        
        // Check if Payment Request is supported
        paymentRequest.canMakePayment().then(function(result) {
            if (result) {
                // Show relevant button
                if (result.applePay) {
                    $('#yprint-apple-pay-button').show();
                }
                if (result.googlePay) {
                    $('#yprint-google-pay-button').show();
                }
                
                // Mount the Payment Request Button
                prButton.mount('#yprint-payment-request-button');
            }
        });
        
        // Handle Payment Request Button click
        $('#yprint-apple-pay-button, #yprint-google-pay-button').on('click', function() {
            paymentRequest.show();
        });
        
        // Handle payment success
        paymentRequest.on('paymentmethod', function(ev) {
            // Process the payment method and confirm payment
            processExpressPayment(ev.paymentMethod.id, ev);
        });
    }
    
    /**
     * Process express payment (Apple Pay, Google Pay)
     */
    function processExpressPayment(paymentMethodId, ev) {
        // Save express payment method and create order
        $.ajax({
            url: yprintCheckout.ajaxurl,
            type: 'POST',
            data: {
                action: 'yprint_process_express_checkout',
                security: yprintCheckout.checkout_nonce,
                payment_method_id: paymentMethodId,
                billing_details: {
                    name: ev.payerName,
                    email: ev.payerEmail,
                    phone: ev.payerPhone
                },
                shipping_details: ev.shippingAddress
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.requires_confirmation) {
                        // Confirm the payment with Stripe
                        stripe.confirmCardPayment(
                            response.data.client_secret,
                            { payment_method: paymentMethodId }
                        ).then(function(result) {
                            if (result.error) {
                                ev.complete('fail');
                                alert(result.error.message);
                            } else {
                                ev.complete('success');
                                window.location.href = response.data.redirect;
                            }
                        });
                    } else {
                        // No confirmation needed
                        ev.complete('success');
                        window.location.href = response.data.redirect;
                    }
                } else {
                    ev.complete('fail');
                    alert(response.data.message || yprintStripe.generic_error);
                }
            },
            error: function() {
                ev.complete('fail');
                alert(yprintStripe.generic_error);
            }
        });
    }
    
    // Make Stripe initialization function available globally
    window.initStripeCheckout = initStripeCheckout;
    
    // Initialize Stripe checkout
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        initStripeCheckout();
    } else {
        document.addEventListener('DOMContentLoaded', initStripeCheckout);
    }
});