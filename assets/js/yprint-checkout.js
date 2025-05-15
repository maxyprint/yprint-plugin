/**
 * YPrint Checkout JavaScript
 */
(function($) {
    'use strict';

    // Stripe variables
    var stripe = null;
    var elements = null;
    var cardElement = null;
    var paymentRequest = null;
    var paymentRequestButton = null;
    var paymentRequestWrapper = $('#yprint-stripe-payment-request-wrapper');
    var paymentMethodSeparator = $('.payment-method-separator');

    // Initialize Checkout
    var YPrintCheckout = {
        
        init: function() {
            console.log('Initializing YPrint Checkout');
            
            // Initialize variables
            this.$form = $('#yprint-checkout-form');
            this.$messages = $('.yprint-checkout-messages');
            this.$shippingAddress = $('.shipping-address');
            this.$placeOrderButton = $('#yprint-place-order');
            
            // Setup event handlers
            this.setupEventHandlers();
            
            // Initialize Stripe
            this.initStripe();
            
            // Update checkout on load
            this.updateCheckout();
        },
        
        setupEventHandlers: function() {
            var self = this;
            
            // Ship to different address checkbox
            $('#ship_to_different_address').on('change', function() {
                if ($(this).is(':checked')) {
                    self.$shippingAddress.slideDown();
                } else {
                    self.$shippingAddress.slideUp();
                }
            });
            
            // Country/state selectors
            $('select.country-select').on('change', function() {
                var $this = $(this);
                var country = $this.val();
                
                if (!country) {
                    return;
                }
                
                var $stateField;
                
                if ($this.attr('id') === 'billing_country') {
                    $stateField = $('#billing_state');
                } else {
                    $stateField = $('#shipping_state');
                }
                
                // Update states
                self.updateStates(country, $stateField);
                
                // Update checkout
                self.updateCheckout();
            });
            
            // State selectors
            $('select.state-select').on('change', function() {
                self.updateCheckout();
            });
            
            // Shipping method
            $(document).on('change', 'input[name="shipping_method"]', function() {
                self.updateCheckout();
            });
            
            // Postcode / city fields
            $('#billing_postcode, #billing_city, #shipping_postcode, #shipping_city').on('blur', function() {
                self.updateCheckout();
            });
            
            // Form submission
            this.$form.on('submit', function(e) {
                e.preventDefault();
                self.placeOrder();
            });
        },
        
        initStripe: function() {
            var self = this;
            
            // Check if Stripe is available
            if (typeof Stripe === 'undefined' || !yprint_checkout_params.stripe.key) {
                console.error('Stripe is not available');
                return;
            }
            
            // Initialize Stripe
            stripe = Stripe(yprint_checkout_params.stripe.key);
            elements = stripe.elements();
            
            // Create card element
            cardElement = elements.create('card', {
                style: {
                    base: {
                        color: '#32325d',
                        fontFamily: '"Roboto", -apple-system, BlinkMacSystemFont, Segoe UI, Helvetica, Arial, sans-serif',
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
            
            // Mount card element
            cardElement.mount('#yprint-stripe-card-element');
            
            // Handle card errors
            cardElement.on('change', function(event) {
                var displayError = document.getElementById('yprint-stripe-card-errors');
                
                if (event.error) {
                    displayError.textContent = event.error.message;
                } else {
                    displayError.textContent = '';
                }
            });
            
            // Initialize Payment Request Button
            this.initPaymentRequest();
        },
        
        initPaymentRequest: function() {
            var self = this;
            
            // Check if Payment Request API is supported by the browser
            if (!yprint_checkout_params.shipping_required) {
                // Simple payment request for non-shipping products
                paymentRequest = stripe.paymentRequest({
                    country: yprint_checkout_params.country_code,
                    currency: yprint_checkout_params.currency.toLowerCase(),
                    total: {
                        label: 'Order Total',
                        amount: 0, // Will be updated
                        pending: true
                    },
                    requestPayerName: true,
                    requestPayerEmail: true,
                    requestPayerPhone: true,
                });
            } else {
                // Payment request with shipping
                paymentRequest = stripe.paymentRequest({
                    country: yprint_checkout_params.country_code,
                    currency: yprint_checkout_params.currency.toLowerCase(),
                    total: {
                        label: 'Order Total',
                        amount: 0, // Will be updated
                        pending: true
                    },
                    requestPayerName: true,
                    requestPayerEmail: true,
                    requestPayerPhone: true,
                    requestShipping: true,
                });
                
                // Handle shipping address changes
                paymentRequest.on('shippingaddresschange', function(event) {
                    self.updatePaymentRequestShipping(event);
                });
                
                // Handle shipping option changes
                paymentRequest.on('shippingoptionchange', function(event) {
                    self.updatePaymentRequestShippingOption(event);
                });
            }
            
            // Handle payment method
            paymentRequest.on('paymentmethod', function(event) {
                self.handlePaymentMethodReceived(event);
            });
            
            // Check if Payment Request is available
            paymentRequest.canMakePayment().then(function(result) {
                if (result) {
                    console.log('Payment Request available:', result);
                    // Display payment request button
                    self.displayPaymentRequestButton(result);
                } else {
                    console.log('Payment Request is not available');
                    // Hide the payment request elements
                    paymentRequestWrapper.hide();
                    paymentMethodSeparator.hide();
                }
            }).catch(function(error) {
                console.error('Error checking Payment Request availability:', error);
                // Hide the payment request elements in case of error
                paymentRequestWrapper.hide();
                paymentMethodSeparator.hide();
            });
            
            // Calculate totals to update the payment request button
            this.updateCheckout();
        },
        
        displayPaymentRequestButton: function(paymentMethod) {
            console.log('Setting up Payment Request Button with method:', paymentMethod);
            
            // Determine payment method type for analytics
            var paymentMethodType = 'standard';
            if (paymentMethod.applePay) paymentMethodType = 'apple_pay';
            if (paymentMethod.googlePay) paymentMethodType = 'google_pay';
            if (paymentMethod.microsoftPay) paymentMethodType = 'microsoft_pay';
            
            console.log('Payment method type:', paymentMethodType);
            
            // Create payment request button
            paymentRequestButton = elements.create('paymentRequestButton', {
                paymentRequest: paymentRequest,
                style: {
                    paymentRequestButton: {
                        type: yprint_checkout_params.button.type || 'default',
                        theme: yprint_checkout_params.button.theme || 'dark',
                        height: yprint_checkout_params.button.height + 'px'
                    }
                }
            });
            
            // Check if the element exists before mounting
            var buttonElement = document.getElementById('yprint-stripe-payment-request-button');
            if (buttonElement) {
                // Mount the button
                paymentRequestButton.mount('#yprint-stripe-payment-request-button');
                
                // Show the wrapper and separator
                paymentRequestWrapper.show();
                paymentMethodSeparator.show();
                
                // Store payment method type for later use
                this.paymentMethodType = paymentMethodType;
            } else {
                console.error('Payment request button element not found');
            }
        },
        
        updatePaymentRequestShipping: function(event) {
            var self = this;
            
            // Get the address
            var address = event.shippingAddress;
            
            // Update the checkout data with the address
            var data = {
                shipping_country: address.country,
                shipping_state: address.region,
                shipping_postcode: address.postalCode,
                shipping_city: address.city,
                shipping_address_1: address.addressLine && address.addressLine.length > 0 ? address.addressLine[0] : '',
                shipping_address_2: address.addressLine && address.addressLine.length > 1 ? address.addressLine[1] : '',
            };
            
            // Make AJAX request to get shipping options
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: {
                    action: 'yprint_update_checkout',
                    nonce: yprint_checkout_params.nonce,
                    checkout_data: $.param(data)
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Prepare shipping options
                        var shippingOptions = [];
                        
                        if (data.shipping_methods.length > 0) {
                            data.shipping_methods.forEach(function(method, index) {
                                shippingOptions.push({
                                    id: method.id,
                                    label: method.label,
                                    detail: '',
                                    amount: Math.round(method.cost * 100)
                                });
                            });
                        }
                        
                        // Prepare total
                        var total = {
                            label: 'Total',
                            amount: data.totals.total * 100,
                            pending: false
                        };
                        
                        // Update event
                        event.updateWith({
                            status: shippingOptions.length > 0 ? 'success' : 'invalid_shipping_address',
                            shippingOptions: shippingOptions,
                            total: total
                        });
                    } else {
                        event.updateWith({
                            status: 'invalid_shipping_address'
                        });
                    }
                },
                error: function() {
                    event.updateWith({
                        status: 'fail'
                    });
                }
            });
        },
        
        updatePaymentRequestShippingOption: function(event) {
            var self = this;
            
            // Get the selected shipping option
            var shippingOption = event.shippingOption;
            
            // Make AJAX request to update the chosen shipping method
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: {
                    action: 'yprint_update_checkout',
                    nonce: yprint_checkout_params.nonce,
                    checkout_data: $.param({
                        shipping_method: shippingOption.id
                    })
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Prepare total
                        var total = {
                            label: 'Total',
                            amount: data.totals.total * 100,
                            pending: false
                        };
                        
                        // Update event
                        event.updateWith({
                            status: 'success',
                            total: total
                        });
                    } else {
                        event.updateWith({
                            status: 'fail'
                        });
                    }
                },
                error: function() {
                    event.updateWith({
                        status: 'fail'
                    });
                }
            });
        },
        
        handlePaymentMethodReceived: function(event) {
            var self = this;
            
            console.log('Payment method received:', event.paymentMethod);
            
            // Set the payment method ID
            $('#payment_method_id').val(event.paymentMethod.id);
            
            // Get form data
            var formData = this.$form.serialize();
            
            // Add payment method type (Apple Pay, Google Pay, etc.)
            formData += '&payment_request_type=' + (this.paymentMethodType || 'payment_request');
            
            // Add billing and shipping data from the payment request
            var additionalData = {
                billing_first_name: event.payerName.split(' ')[0],
                billing_last_name: event.payerName.split(' ').slice(1).join(' '),
                billing_email: event.payerEmail,
                billing_phone: event.payerPhone
            };
            
            // If billing address is provided in the payment method
            if (event.paymentMethod.billing_details && event.paymentMethod.billing_details.address) {
                var billingAddress = event.paymentMethod.billing_details.address;
                if (billingAddress.line1) additionalData.billing_address_1 = billingAddress.line1;
                if (billingAddress.line2) additionalData.billing_address_2 = billingAddress.line2;
                if (billingAddress.city) additionalData.billing_city = billingAddress.city;
                if (billingAddress.state) additionalData.billing_state = billingAddress.state;
                if (billingAddress.postal_code) additionalData.billing_postcode = billingAddress.postal_code;
                if (billingAddress.country) additionalData.billing_country = billingAddress.country;
            }
            
            // Add shipping details if available
            if (event.shippingAddress) {
                // Apple Pay format
                if (event.shippingAddress.recipient) {
                    additionalData.shipping_first_name = event.shippingAddress.recipient.split(' ')[0];
                    additionalData.shipping_last_name = event.shippingAddress.recipient.split(' ').slice(1).join(' ');
                }
                // Standard format
                else if (event.shippingAddress.name) {
                    additionalData.shipping_first_name = event.shippingAddress.name.split(' ')[0];
                    additionalData.shipping_last_name = event.shippingAddress.name.split(' ').slice(1).join(' ');
                }
                
                additionalData.shipping_country = event.shippingAddress.country;
                additionalData.shipping_state = event.shippingAddress.region || event.shippingAddress.state;
                additionalData.shipping_postcode = event.shippingAddress.postalCode || event.shippingAddress.postal_code;
                additionalData.shipping_city = event.shippingAddress.city;
                
                // Handle address lines (different formats)
                if (event.shippingAddress.addressLine) {
                    additionalData.shipping_address_1 = event.shippingAddress.addressLine.length > 0 ? event.shippingAddress.addressLine[0] : '';
                    additionalData.shipping_address_2 = event.shippingAddress.addressLine.length > 1 ? event.shippingAddress.addressLine[1] : '';
                } else if (event.shippingAddress.line1) {
                    additionalData.shipping_address_1 = event.shippingAddress.line1;
                    additionalData.shipping_address_2 = event.shippingAddress.line2 || '';
                }
                
                additionalData.ship_to_different_address = 1;
            }
            
            // Add additional data to form data
            for (var key in additionalData) {
                if (additionalData[key]) {
                    formData += '&' + key + '=' + encodeURIComponent(additionalData[key]);
                }
            }
            
            // Show processing message
            self.showProcessingMessage();
            
            // Process the payment
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: {
                    action: 'yprint_process_checkout',
                    nonce: yprint_checkout_params.nonce,
                    checkout_data: formData
                },
                success: function(response) {
                    if (response.success) {
                        // Complete the payment
                        event.complete('success');
                        
                        // Redirect to thank you page
                        window.location.href = response.data.redirect;
                    } else {
                        // Complete the payment with failure
                        event.complete('fail');
                        
                        // Show error message
                        self.showError(response.data.message);
                    }
                },
                error: function(xhr) {
                    // Complete the payment with failure
                    event.complete('fail');
                    
                    // Show error message
                    var errorMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : yprint_checkout_params.i18n.checkout_error;
                    
                    self.showError(errorMessage);
                }
            });
        },
        
        updateStates: function(country, $stateField) {
            // Make AJAX request to get states
            $.ajax({
                type: 'POST',
                url: wc_country_select_params.ajax_url,
                data: {
                    action: 'woocommerce_get_states',
                    security: wc_country_select_params.states_nonce,
                    country: country
                },
                success: function(data) {
                    if (typeof(data) === 'object') {
                        $stateField.empty();
                        $stateField.append('<option value="">' + wc_country_select_params.i18n_select_state_text + '</option>');
                        
                        $.each(data, function(index, state) {
                            $stateField.append('<option value="' + index + '">' + state + '</option>');
                        });
                        
                        $stateField.prop('disabled', false);
                    } else {
                        $stateField.empty();
                        $stateField.append('<option value="">' + wc_country_select_params.i18n_select_state_text + '</option>');
                        $stateField.prop('disabled', true);
                    }
                }
            });
        },
        
        updateCheckout: function() {
            var self = this;
            
            // Collect form data
            var formData = this.$form.serialize();
            
            // Make AJAX request to update checkout
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: {
                    action: 'yprint_update_checkout',
                    nonce: yprint_checkout_params.nonce,
                    checkout_data: formData
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        
                        // Update totals
                        $('.order-total-row.subtotal .value').html(data.totals.subtotal);
                        $('.order-total-row.shipping .value').html(data.totals.shipping);
                        $('.order-total-row.tax .value').html(data.totals.tax);
                        $('.order-total-row.total .value').html(data.totals.total_formatted);
                        
                        // Update shipping methods
                        self.updateShippingMethods(data.shipping_methods);
                        
                        // Update payment request button if available
                        if (paymentRequest) {
                            self.updatePaymentRequest(data.totals.total);
                        }
                    }
                }
            });
        },
        
        updateShippingMethods: function(shippingMethods) {
            var $shippingMethodsList = $('.shipping-methods-list');
            
            // Clear current shipping methods
            $shippingMethodsList.empty();
            
            // Check if we have shipping methods
            if (shippingMethods.length === 0) {
                $shippingMethodsList.append('<p class="no-shipping-methods">' + 
                    yprint_checkout_params.i18n.no_shipping_methods + '</p>');
                return;
            }
            
            // Get currently selected method
            var selectedMethod = $('input[name="shipping_method"]:checked').val();
            
            // Add shipping methods
            $.each(shippingMethods, function(index, method) {
                var isSelected = selectedMethod === method.id || (index === 0 && !selectedMethod);
                
                $shippingMethodsList.append(
                    '<div class="shipping-method">' +
                        '<label class="radio-container">' +
                            '<input type="radio" name="shipping_method" id="shipping_method_' + method.id + 
                            '" value="' + method.id + '"' + (isSelected ? ' checked="checked"' : '') + ' class="shipping-method-input">' +
                            '<span class="radio-checkmark"></span>' +
                            '<span class="shipping-method-label">' + method.label + ' - ' + method.cost_formatted + '</span>' +
                        '</label>' +
                    '</div>'
                );
            });
        },
        
        updatePaymentRequest: function(total) {
            if (!paymentRequest) {
                return;
            }
            
            // Update payment request total
            paymentRequest.update({
                total: {
                    label: 'Order Total',
                    amount: Math.round(total * 100),
                    pending: false
                }
            });
        },
        
        placeOrder: function() {
            var self = this;
            
            // Disable the place order button
            this.$placeOrderButton.prop('disabled', true).text(yprint_checkout_params.i18n.processing);
            
            // Check if we already have a payment method ID
            var paymentMethodId = $('#payment_method_id').val();
            
            if (paymentMethodId) {
                // We already have a payment method (from Payment Request Button)
                this.processCheckout();
            } else {
                // We need to create a payment method from card element
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
                        // Show error
                        self.showError(result.error.message);
                        
                        // Re-enable the place order button
                        self.$placeOrderButton.prop('disabled', false).text(yprint_checkout_params.i18n.place_order);
                    } else {
                        // Set the payment method ID
                        $('#payment_method_id').val(result.paymentMethod.id);
                        
                        // Process the checkout
                        self.processCheckout();
                    }
                });
            }
        },
        
        processCheckout: function() {
            var self = this;
            
            // Collect form data
            var formData = this.$form.serialize();
            
            // Make AJAX request to process checkout
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: {
                    action: 'yprint_process_checkout',
                    nonce: yprint_checkout_params.nonce,
                    checkout_data: formData
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to thank you page
                        window.location.href = response.data.redirect;
                    } else {
                        // Show error message
                        self.showError(response.data.message);
                        
                        // Re-enable the place order button
                        self.$placeOrderButton.prop('disabled', false).text(yprint_checkout_params.i18n.place_order);
                    }
                },
                error: function(xhr) {
                    // Show error message
                    var errorMessage = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
                        ? xhr.responseJSON.data.message
                        : yprint_checkout_params.i18n.checkout_error;
                    
                    self.showError(errorMessage);
                    
                    // Re-enable the place order button
                    self.$placeOrderButton.prop('disabled', false).text(yprint_checkout_params.i18n.place_order);
                }
            });
        },
        
        showError: function(message) {
            // Add error message
            this.$messages.html('<div class="yprint-checkout-error">' + message + '</div>');
            
            // Scroll to messages
            $('html, body').animate({
                scrollTop: this.$messages.offset().top - 100
            }, 500);
        },
        
        showProcessingMessage: function() {
            this.$messages.html('<div class="yprint-checkout-processing">' + yprint_checkout_params.i18n.payment_processing + '</div>');
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        YPrintCheckout.init();
    });
    
})(jQuery);