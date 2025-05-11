/**
 * YPrint Stripe Payment Request JavaScript
 */
(function($) {
    'use strict';

    /**
     * Payment Request Handler
     */
    var YPrintStripePaymentRequest = {
        // Stripe object instance
        stripe: null,
        
        // Elements instance
        elements: null,
        
        // Payment request instance
        paymentRequest: null,
        
        // Payment request button element
        paymentRequestButton: null,
        
        // Payment request button wrapper element
        $wrapper: null,
        
        // Payment request button separator element
        $separator: null,
        
        // Is initialized flag
        initialized: false,
        
        /**
         * Initialize Payment Request
         */
        init: function() {
            console.log('Initializing YPrint Stripe Payment Request');
            
            // Check if already initialized
            if (this.initialized) {
                return;
            }
            
            // Check if Stripe is available
            if (typeof Stripe === 'undefined') {
                console.error('Stripe.js is not loaded');
                return;
            }
            
            // Check if params are available
            if (typeof yprint_stripe_payment_request_params === 'undefined') {
                console.error('Payment Request params are not defined');
                return;
            }
            
            // Init elements
            this.$wrapper = $('#yprint-stripe-payment-request-wrapper');
            this.$separator = $('#yprint-stripe-payment-request-separator');
            
            // Check if wrapper exists
            if (!this.$wrapper.length) {
                console.log('Payment Request wrapper not found');
                return;
            }
            
            // Initialize Stripe
            this.stripe = Stripe(yprint_stripe_payment_request_params.stripe.key, {
                locale: yprint_stripe_payment_request_params.stripe.locale || 'auto'
            });
            
            // Initialize Elements
            this.elements = this.stripe.elements();
            
            // Initialize Payment Request
            this.initPaymentRequest();
            
            this.initialized = true;
        },
        
        /**
         * Initialize Payment Request
         */
        initPaymentRequest: function() {
            var self = this;
            var params = yprint_stripe_payment_request_params;
// Update the AJAX URL to use standard WordPress AJAX
params.ajax_url = params.ajax_url.replace('%%endpoint%%', '');
            
            // Check if product data is available
            if (params.product && params.is_product) {
                this.initProductPaymentRequest();
                return;
            }
            
            // Create payment request object for cart/checkout
            var paymentRequestOptions = {
                country: params.checkout.country_code,
                currency: params.checkout.currency_code.toLowerCase(),
                total: {
                    label: params.checkout.total_label || 'Total',
                    amount: 0, // Will be updated via AJAX
                    pending: true
                },
                requestPayerName: true,
                requestPayerEmail: true,
                requestPayerPhone: params.checkout.needs_payer_phone === 'yes',
                requestShipping: params.checkout.needs_shipping === 'yes',
            };
            
            this.paymentRequest = this.stripe.paymentRequest(paymentRequestOptions);
            
            // Check if payment request is supported by the browser
            this.paymentRequest.canMakePayment().then(function(result) {
                if (result) {
                    self.setupPaymentRequestButton(result);
                    self.getCartDetails();
                } else {
                    console.log('Payment Request is not available in your browser');
                }
            });
            
            // Handle shipping address changes
            if (params.checkout.needs_shipping === 'yes') {
                this.paymentRequest.on('shippingaddresschange', function(event) {
                    self.updateShippingOptions(event);
                });
                
                this.paymentRequest.on('shippingoptionchange', function(event) {
                    self.updateShippingMethod(event);
                });
            }
            
            // Handle payment method changes
            this.paymentRequest.on('paymentmethod', function(event) {
                self.handlePaymentMethodReceived(event);
            });
        },
        
        /**
         * Initialize Product Page Payment Request
         */
        initProductPaymentRequest: function() {
            var self = this;
            var params = yprint_stripe_payment_request_params;
// Update the AJAX URL to use standard WordPress AJAX
params.ajax_url = params.ajax_url.replace('%%endpoint%%', '');

            var product = params.product;
            
            // Create payment request object for product page
            var paymentRequestOptions = {
                country: params.checkout.country_code,
                currency: params.checkout.currency_code.toLowerCase(),
                total: product.total,
                requestPayerName: true,
                requestPayerEmail: true,
                requestPayerPhone: params.checkout.needs_payer_phone === 'yes',
                requestShipping: product.requestShipping,
                displayItems: product.displayItems || []
            };
            
            this.paymentRequest = this.stripe.paymentRequest(paymentRequestOptions);
            
            // Check if payment request is supported by the browser
            this.paymentRequest.canMakePayment().then(function(result) {
                if (result) {
                    self.setupPaymentRequestButton(result);
                } else {
                    console.log('Payment Request is not available in your browser');
                }
            });
            
            // Handle shipping address changes
            if (product.requestShipping) {
                this.paymentRequest.on('shippingaddresschange', function(event) {
                    self.updateProductShippingOptions(event, product.id);
                });
                
                this.paymentRequest.on('shippingoptionchange', function(event) {
                    self.updateProductShippingMethod(event, product.id);
                });
            }
            
            // Handle payment method changes
            this.paymentRequest.on('paymentmethod', function(event) {
                self.handleProductPaymentMethodReceived(event, product.id);
            });
        },
        
        /**
         * Setup Payment Request Button
         */
        setupPaymentRequestButton: function(paymentMethodResult) {
            var self = this;
            var params = yprint_stripe_payment_request_params;
// Update the AJAX URL to use standard WordPress AJAX
params.ajax_url = params.ajax_url.replace('%%endpoint%%', '');

            
            // Detect payment method
            var paymentMethod = 'payment_request';
            if (paymentMethodResult.applePay) {
                paymentMethod = 'apple_pay';
            } else if (paymentMethodResult.googlePay) {
                paymentMethod = 'google_pay';
            }
            
            console.log('Payment method detected: ' + paymentMethod);
            
            // Create payment request button
            this.paymentRequestButton = this.elements.create('paymentRequestButton', {
                paymentRequest: this.paymentRequest,
                style: {
                    paymentRequestButton: {
                        type: params.button.type || 'default',
                        theme: params.button.theme || 'dark',
                        height: params.button.height + 'px'
                    }
                }
            });
            
            // Mount button
            this.paymentRequestButton.mount('#yprint-stripe-payment-request-button');
            
            // Show wrapper and separator
            this.$wrapper.show();
            if (this.$separator.length) {
                this.$separator.show();
            }
            
            // Store payment method type for later use
            this.paymentMethodType = paymentMethod;
        },
        
        /**
         * Get Cart Details
         */
        getCartDetails: function() {
            var self = this;
            
            $.ajax({
                type: 'POST',
                url: yprint_stripe_payment_request_params.ajax_url.replace('%%endpoint%%', 'yprint_stripe_get_cart_details'),
                data: {
                    security: yprint_stripe_payment_request_params.nonce.get_cart_details
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        self.updatePaymentRequestWithCartDetails(response.data);
                    } else {
                        console.error('Error getting cart details');
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX error getting cart details:', errorThrown);
                }
            });
        },
        
        /**
         * Update Payment Request with cart details
         */
        updatePaymentRequestWithCartDetails: function(data) {
            // Update payment request with cart details
            this.paymentRequest.update({
                total: data.total,
                displayItems: data.displayItems || []
            });
        },
        
        /**
         * Update Shipping Options
         */
        updateShippingOptions: function(event) {
            var self = this;
            
            $.ajax({
                type: 'POST',
                url: yprint_stripe_payment_request_params.ajax_url.replace('%%endpoint%%', 'yprint_stripe_get_shipping_options'),
                data: {
                    security: yprint_stripe_payment_request_params.nonce.shipping,
                    shipping_address: event.shippingAddress
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        event.updateWith({
                            status: 'success',
                            displayItems: response.data.displayItems,
                            total: response.data.total,
                            shippingOptions: response.data.shipping_options || []
                        });
                    } else {
                        event.updateWith({
                            status: 'invalid_shipping_address'
                        });
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX error updating shipping options:', errorThrown);
                    event.updateWith({
                        status: 'fail'
                    });
                }
            });
        },
        
        /**
         * Update Shipping Method
         */
        updateShippingMethod: function(event) {
            var self = this;
            
            $.ajax({
                type: 'POST',
                url: yprint_stripe_payment_request_params.ajax_url.replace('%%endpoint%%', 'yprint_stripe_update_shipping_method'),
                data: {
                    security: yprint_stripe_payment_request_params.nonce.update_shipping,
                    shipping_option_id: event.shippingOption.id
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        event.updateWith({
                            status: 'success',
                            displayItems: response.data.displayItems,
                            total: response.data.total
                        });
                    } else {
                        event.updateWith({
                            status: 'fail'
                        });
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX error updating shipping method:', errorThrown);
                    event.updateWith({
                        status: 'fail'
                    });
                }
            });
        },
        
        /**
         * Update Product Shipping Options
         */
        updateProductShippingOptions: function(event, productId) {
            var self = this;
            
            $.ajax({
                type: 'POST',
                url: yprint_stripe_payment_request_params.ajax_url.replace('%%endpoint%%', 'yprint_stripe_get_shipping_options'),
                data: {
                    security: yprint_stripe_payment_request_params.nonce.shipping,
                    shipping_address: event.shippingAddress,
                    product_id: productId,
                    is_product: true
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        event.updateWith({
                            status: 'success',
                            displayItems: response.data.displayItems,
                            total: response.data.total,
                            shippingOptions: response.data.shipping_options || []
                        });
                    } else {
                        event.updateWith({
                            status: 'invalid_shipping_address'
                        });
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX error updating product shipping options:', errorThrown);
                    event.updateWith({
                        status: 'fail'
                    });
                }
            });
        },
        
        /**
         * Update Product Shipping Method
         */
        updateProductShippingMethod: function(event, productId) {
            var self = this;
            
            $.ajax({
                type: 'POST',
                url: yprint_stripe_payment_request_params.ajax_url.replace('%%endpoint%%', 'yprint_stripe_update_shipping_method'),
                data: {
                    security: yprint_stripe_payment_request_params.nonce.update_shipping,
                    shipping_option_id: event.shippingOption.id,
                    product_id: productId,
                    is_product: true
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        event.updateWith({
                            status: 'success',
                            displayItems: response.data.displayItems,
                            total: response.data.total
                        });
                    } else {
                        event.updateWith({
                            status: 'fail'
                        });
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('AJAX error updating product shipping method:', errorThrown);
                    event.updateWith({
                        status: 'fail'
                    });
                }
            });
        },
        
        /**
         * Handle Payment Method Received
         */
        handlePaymentMethodReceived: function(event) {
            var self = this;
            
            // Process payment
            this.processPayment(event.paymentMethod.id, event);
        },
        
        /**
         * Handle Product Payment Method Received
         */
        handleProductPaymentMethodReceived: function(event, productId) {
            var self = this;
            
            // Add product to cart first
            $.ajax({
                type: 'POST',
                url: yprint_stripe_payment_request_params.ajax_url.replace('%%endpoint%%', 'yprint_stripe_add_to_cart'),
                data: {
                    security: yprint_stripe_payment_request_params.nonce.add_to_cart,
                    product_id: productId,
                    quantity: $('input.qty').val() || 1
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Process payment after adding to cart
                        self.processPayment(event.paymentMethod.id, event);
                    } else {
                        event.complete('fail');
                        console.error('Error adding product to cart');
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    event.complete('fail');
                    console.error('AJAX error adding product to cart:', errorThrown);
                }
            });
        },
        
        /**
         * Process Payment
         */
        processPayment: function(paymentMethodId, event) {
            var self = this;
            
            $.ajax({
                type: 'POST',
                url: yprint_stripe_payment_request_params.ajax_url.replace('%%endpoint%%', 'yprint_stripe_process_payment'),
                data: {
                    security: yprint_stripe_payment_request_params.nonce.payment,
                    payment_method_id: paymentMethodId,
                    payment_request_type: this.paymentMethodType || 'payment_request'
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        event.complete('success');
                        
                        // Redirect to thank you page
                        window.location.href = response.data.redirect;
                    } else {
                        event.complete('fail');
                        console.error('Error processing payment:', response.data.message);
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    event.complete('fail');
                    console.error('AJAX error processing payment:', errorThrown);
                }
            });
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        YPrintStripePaymentRequest.init();
    });
    
})(jQuery);