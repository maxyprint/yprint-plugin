/**
 * YPrint Stripe Checkout JavaScript
 */
(function($) {
    'use strict';

    var stripe = null;
    var elements = null;
    var cardElement = null;
    var paymentRequestButton = null;
    var clientSecret = null;
    var paymentIntentId = null;
    var errorElement = null;

    /**
     * Initialize Stripe Elements
     */
    function initStripe() {
        // Check if the required parameters are available
        if (typeof yprint_stripe_checkout_params === 'undefined') {
            console.error('Stripe checkout parameters not found');
            return;
        }

        // Initialize Stripe with publishable key
        stripe = Stripe(yprint_stripe_checkout_params.publishable_key);
        elements = stripe.elements();

        // Create card element
        cardElement = elements.create('card', {
            style: {
                base: {
                    color: '#32325d',
                    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
                    fontSmoothing: 'antialiased',
                    fontSize: '16px',
                    '::placeholder': {
                        color: '#aab7c4'
                    }
                },
                invalid: {
                    color: '#fa755a',
                    iconColor: '#fa755a'
                }
            }
        });

        // Mount the card element
        cardElement.mount('#yprint-stripe-card-element');
        errorElement = $('#yprint-stripe-card-errors');

        // Handle real-time validation errors
        cardElement.on('change', function(event) {
            if (event.error) {
                showError(event.error.message);
            } else {
                errorElement.html('');
            }
        });

        // Check if we have a payment intent client secret from a 3D Secure redirect
        var url = new URL(window.location.href);
        var payment_intent_client_secret = url.searchParams.get('payment_intent_client_secret');
        var payment_intent = url.searchParams.get('payment_intent');

        if (payment_intent_client_secret && payment_intent) {
            stripe.retrievePaymentIntent(payment_intent_client_secret).then(function(result) {
                if (result.error) {
                    showError(result.error.message);
                } else if (result.paymentIntent.status === 'succeeded') {
                    // The payment succeeded, but we'll let the server handle the success case
                    showSuccess('Payment succeeded! Redirecting...');
                }
            });
        }

        // Add submit event to checkout form
        if (yprint_stripe_checkout_params.is_checkout) {
            handleCheckoutFormSubmit();
        }

        // Handle payment for order page
        if (yprint_stripe_checkout_params.is_order_pay) {
            handleOrderPayPage();
        }
    }

    /**
     * Handle checkout form submission
     */
    function handleCheckoutFormSubmit() {
        var form = $('form.woocommerce-checkout');

        form.on('checkout_place_order_yprint_stripe', function(event) {
            if ($('input[name="payment_method"]:checked').val() !== 'yprint_stripe') {
                return true;
            }

            // Prevent the default form submission
            event.preventDefault();

            // Validate form
            if (!form[0].checkValidity()) {
                form[0].reportValidity();
                return false;
            }

            // Show loading state
            form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            // Create payment method
            stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
                billing_details: {
                    name: $('#billing_first_name').val() + ' ' + $('#billing_last_name').val(),
                    email: $('#billing_email').val(),
                    phone: $('#billing_phone').val(),
                    address: {
                        line1: $('#billing_address_1').val(),
                        line2: $('#billing_address_2').val(),
                        city: $('#billing_city').val(),
                        state: $('#billing_state').val(),
                        postal_code: $('#billing_postcode').val(),
                        country: $('#billing_country').val()
                    }
                }
            }).then(function(result) {
                if (result.error) {
                    showError(result.error.message);
                    form.unblock();
                } else {
                    // Add payment method ID to form
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'yprint_stripe_payment_method_id',
                        value: result.paymentMethod.id
                    }).appendTo(form);

                    // Submit the form
                    form.submit();
                }
            });

            return false;
        });
    }

    /**
     * Handle order pay page
     */
    function handleOrderPayPage() {
        var form = $('form#order_review');
        var orderData = $('#yprint-stripe-payment-data');
        var orderId = orderData.data('order-id');

        form.on('submit', function(event) {
            if ($('input[name="payment_method"]:checked').val() !== 'yprint_stripe') {
                return true;
            }

            // Prevent the default form submission
            event.preventDefault();

            // Show loading state
            form.block({
                message: null,
                overlayCSS: {
                    background: '#fff',
                    opacity: 0.6
                }
            });

            // Create payment method
            stripe.createPaymentMethod({
                type: 'card',
                card: cardElement
            }).then(function(result) {
                if (result.error) {
                    showError(result.error.message);
                    form.unblock();
                } else {
                    // Add payment method ID to form
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'yprint_stripe_payment_method_id',
                        value: result.paymentMethod.id
                    }).appendTo(form);

                    // Submit the form
                    form.submit();
                }
            });

            return false;
        });
    }

    /**
     * Show error message
     */
    function showError(message) {
        errorElement.html('<div class="woocommerce-error">' + message + '</div>');
        $('html, body').animate({
            scrollTop: errorElement.offset().top - 100
        }, 500);
    }

    /**
     * Show success message
     */
    function showSuccess(message) {
        errorElement.html('<div class="woocommerce-message">' + message + '</div>');
    }

    /**
     * Handle 3D Secure authentication if required
     */
    function handlePaymentIntentResult(result) {
        if (result.error) {
            // Show error
            showError(result.error.message);
            return false;
        }

        if (result.paymentIntent.status === 'succeeded') {
            // Payment is successful
            return true;
        }

        if (result.paymentIntent.status === 'requires_action' || 
            result.paymentIntent.status === 'requires_source_action') {
            // 3D Secure is required
            stripe.confirmCardPayment(clientSecret).then(function(result) {
                if (result.error) {
                    showError(result.error.message);
                } else {
                    // The payment has succeeded
                    window.location.href = yprint_stripe_checkout_params.return_url;
                }
            });
            return false;
        }

        return true;
    }

    // Initialize on document ready
    $(document).ready(function() {
        initStripe();
    });

})(jQuery);