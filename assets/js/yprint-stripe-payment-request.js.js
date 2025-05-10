/**
 * YPrint Stripe Payment Request JavaScript
 * Handles Apple Pay button rendering and interaction
 */
(function($) {
    'use strict';

    // Main PaymentRequest object
    var YPrintStripePaymentRequest = {
        // Properties
        stripe: null,
        paymentRequest: null,
        elements: null,
        paymentRequestButton: null,
        isApplePayAvailable: false,
        
        // DOM selectors
        selectors: {
            buttonWrapper: '#yprint-stripe-payment-request-wrapper',
            button: '#yprint-stripe-payment-request-button',
            separator: '#yprint-stripe-payment-request-separator'
        },
        
        // Configuration
        config: {},
        
        /**
         * Initialize the payment request
         */
        init: function() {
            console.log('Initializing YPrint Stripe Payment Request');
            
            // Make sure we have jQuery and the required data
            if (typeof $ === 'undefined' || typeof yprint_stripe_payment_request_params === 'undefined') {
                console.error('Required dependencies not found for payment request.');
                return;
            }
            
            // Load configuration from localized object
            this.config = yprint_stripe_payment_request_params;
            
            // Check if we're on a supported page
            if (!this.isSupportedPage()) {
                console.log('Payment request not supported on this page');
                return;
            }
            
            // Create Stripe instance
            if (!this.config.stripe.key) {
                console.error('Stripe publishable key not found.');
                return;
            }
            
            this.stripe = Stripe(this.config.stripe.key, {
                locale: this.config.stripe.locale
            });
            
            // Create Elements instance
            this.elements = this.stripe.elements();
            
            // Configure the payment request
            this.configurePaymentRequest();
            
            // Detect if Apple Pay is available
            this.detectPaymentRequestAvailability();
        },
        
        /**
         * Configure the payment request with available payment methods
         */
        configurePaymentRequest: function() {
            console.log('Configuring payment request');
            
            // Get product or cart data
            var paymentData = this.getPaymentRequestData();
            if (!paymentData) {
                console.error('No payment data available for this page');
                return;
            }
            
            // Create PaymentRequest
            this.paymentRequest = this.stripe.paymentRequest({
                country: this.config.checkout.country_code,
                currency: this.config.checkout.currency_code,
                total: paymentData.total,
                requestPayerName: true,
                requestPayerEmail: true,
                requestPayerPhone: this.config.checkout.needs_payer_phone,
                requestShipping: paymentData.requestShipping,
                displayItems: paymentData.displayItems
            });
            
            // Set up listeners
            this.setupEventListeners();
        },
        
        /**
         * Detect if Payment Request (including Apple Pay) is available
         */
        detectPaymentRequestAvailability: function() {
            var self = this;
            
            // Check if the browser supports payment request
            this.paymentRequest.canMakePayment().then(function(result) {
                console.log('Payment method availability result:', result);
                
                // Check if Apple Pay is available
                if (result && result.applePay) {
                    self.isApplePayAvailable = true;
                    console.log('Apple Pay is available');
                }
                
                // Show the payment button if a payment method is available
                if (result) {
                    self.mountPaymentRequestButton();
                }
            }).catch(function(error) {
                console.error('Error checking payment method availability:', error);
            });
        },
        
        /**
         * Set up event listeners for the payment request
         */
        setupEventListeners: function() {
            var self = this;
            
            // Payment method selection
            this.paymentRequest.on('paymentmethod', function(event) {
                console.log('Payment method selected:', event);
                self.handlePaymentMethodEvent(event);
            });
            
            // Shipping address change
            if (this.config.checkout.needs_shipping === 'yes') {
                this.paymentRequest.on('shippingaddresschange', function(event) {
                    console.log('Shipping address changed:', event);
                    self.handleShippingAddressChange(event);
                });
            }
            
            // Shipping option change
            if (this.config.checkout.needs_shipping === 'yes') {
                this.paymentRequest.on('shippingoptionchange', function(event) {
                    console.log('Shipping option changed:', event);
                    self.handleShippingOptionChange(event);
                });
            }
        },
        
        /**
         * Mount the payment request button
         */
        mountPaymentRequestButton: function() {
            console.log('Mounting payment request button');
            
            // Create button element
            this.paymentRequestButton = this.elements.create('paymentRequestButton', {
                paymentRequest: this.paymentRequest,
                style: {
                    paymentRequestButton: {
                        type: this.config.button.type || 'default',
                        theme: this.config.button.theme || 'dark',
                        height: this.config.button.height + 'px'
                    }
                }
            });
            
            // Show the button and separator if they exist
            $(this.selectors.buttonWrapper).show();
            $(this.selectors.separator).show();
            
            // Mount the button
            this.paymentRequestButton.mount(this.selectors.button);
        },
        
        /**
         * Handle payment method event
         */
        handlePaymentMethodEvent: function(event) {
            var self = this;
            
            // If we're on the checkout page, just process the payment
            if (this.isCheckout()) {
                this.processCheckoutPayment(event);
                return;
            }
            
            // Otherwise, we need to add the product to cart and redirect
            $.ajax({
                type: 'POST',
                url: this.config.ajax_url.replace('%%endpoint%%', 'yprint_stripe_add_to_cart'),
                data: {
                    security: this.config.nonce.add_to_cart,
                    product_id: this.config.product ? this.config.product.id : '',
                    quantity: 1
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to checkout
                        window.location = self.config.checkout.url;
                    } else {
                        // Show error message
                        event.complete('fail');
                        self.showError(response.data.message || 'Failed to add product to cart');
                    }
                },
                error: function(xhr, textStatus, error) {
                    console.error('Error adding to cart:', error);
                    event.complete('fail');
                    self.showError('Error adding product to cart');
                }
            });
        },
        
        /**
         * Process the payment on the checkout page
         */
        processCheckoutPayment: function(event) {
            var self = this;
            
            // Create an AJAX request to process the payment
            $.ajax({
                type: 'POST',
                url: this.config.ajax_url.replace('%%endpoint%%', 'yprint_stripe_process_payment'),
                data: {
                    security: this.config.nonce.payment,
                    payment_method_id: event.paymentMethod.id,
                    payment_request_type: event.paymentMethod.type === 'apple_pay' ? 'apple_pay' : 'payment_request'
                },
                success: function(response) {
                    if (response.success) {
                        // Complete the payment
                        event.complete('success');
                        
                        // Redirect to thank you page
                        window.location = response.data.redirect;
                    } else {
                        // Show error message
                        event.complete('fail');
                        self.showError(response.data.message || 'Payment failed');
                    }
                },
                error: function(xhr, textStatus, error) {
                    console.error('Error processing payment:', error);
                    event.complete('fail');
                    self.showError('Error processing payment');
                }
            });
        },
        
        /**
         * Handle shipping address change
         */
        handleShippingAddressChange: function(event) {
            var self = this;
            
            $.ajax({
                type: 'POST',
                url: this.config.ajax_url.replace('%%endpoint%%', 'yprint_stripe_get_shipping_options'),
                data: {
                    security: this.config.nonce.shipping,
                    shipping_address: event.shippingAddress
                },
                success: function(response) {
                    if (response.success) {
                        event.updateWith({
                            status: 'success',
                            shippingOptions: response.data.shipping_options,
                            total: response.data.total,
                            displayItems: response.data.displayItems
                        });
                    } else {
                        event.updateWith({
                            status: 'invalid_shipping_address'
                        });
                    }
                },
                error: function(xhr, textStatus, error) {
                    console.error('Error updating shipping:', error);
                    event.updateWith({
                        status: 'fail'
                    });
                }
            });
        },
        
        /**
         * Handle shipping option change
         */
        handleShippingOptionChange: function(event) {
            var self = this;
            
            $.ajax({
                type: 'POST',
                url: this.config.ajax_url.replace('%%endpoint%%', 'yprint_stripe_update_shipping_method'),
                data: {
                    security: this.config.nonce.update_shipping,
                    shipping_option_id: event.shippingOption.id
                },
                success: function(response) {
                    if (response.success) {
                        event.updateWith({
                            status: 'success',
                            total: response.data.total,
                            displayItems: response.data.displayItems
                        });
                    } else {
                        event.updateWith({
                            status: 'fail'
                        });
                    }
                },
                error: function(xhr, textStatus, error) {
                    console.error('Error updating shipping method:', error);
                    event.updateWith({
                        status: 'fail'
                    });
                }
            });
        },
        
        /**
         * Show an error message
         */
        showError: function(message) {
            var errorElement = $('<div class="woocommerce-error">' + message + '</div>');
            
            // Show on checkout page
            if (this.isCheckout() && $('.woocommerce-notices-wrapper').length) {
                $('.woocommerce-notices-wrapper').prepend(errorElement);
                $('html, body').animate({
                    scrollTop: $('.woocommerce-notices-wrapper').offset().top - 100
                }, 500);
            }
            // Show on product page
            else if ($('.product').length) {
                $('.product').before(errorElement);
                $('html, body').animate({
                    scrollTop: errorElement.offset().top - 100
                }, 500);
            }
            // Show on any other page
            else {
                $('body').prepend(errorElement);
                $('html, body').animate({
                    scrollTop: 0
                }, 500);
            }
        },
        
        /**
         * Get payment request data based on current page
         */
        getPaymentRequestData: function() {
            // If we're on a product page, get product data
            if (this.isProductPage() && this.config.product) {
                return this.config.product;
            }
            
            // If we're on the cart or checkout page, get cart data
            if (this.isCartPage() || this.isCheckout()) {
                // For now, we'll just return a placeholder
                // In a real implementation, this would be pulled from the localized data
                return {
                    total: {
                        label: this.config.checkout.total_label || 'Total',
                        amount: 0 // This would be populated from the server
                    },
                    displayItems: [],
                    requestShipping: this.config.checkout.needs_shipping === 'yes'
                };
            }
            
            return null;
        },
        
        /**
         * Check if the current page is supported
         */
        isSupportedPage: function() {
            return this.isProductPage() || this.isCartPage() || this.isCheckout();
        },
        
        /**
         * Check if the current page is a product page
         */
        isProductPage: function() {
            return $('body').hasClass('single-product') || 
                   $('.single-product').length > 0 || 
                   $('body').hasClass('product-template-default');
        },
        
        /**
         * Check if the current page is the cart page
         */
        isCartPage: function() {
            return $('body').hasClass('woocommerce-cart') || 
                   $('.woocommerce-cart').length > 0;
        },
        
        /**
         * Check if the current page is the checkout page
         */
        isCheckout: function() {
            return $('body').hasClass('woocommerce-checkout') || 
                   $('.woocommerce-checkout').length > 0;
        }
    };
    
    // Initialize on document ready
    $(document).ready(function() {
        YPrintStripePaymentRequest.init();
    });
    
})(jQuery);