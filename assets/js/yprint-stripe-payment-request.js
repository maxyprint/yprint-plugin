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

        // Store detected payment method type (apple_pay, google_pay, etc.)
        paymentMethodType: 'payment_request', // Default

        /**
         * Initialize Payment Request
         */
        init: function() {
            console.log('Initializing YPrint Stripe Payment Request');

            // Check if jQuery is available
            if (typeof $ === 'undefined') {
                console.error('YPrint Stripe Payment Request: jQuery is not loaded.');
                return;
            }

            // Check if Stripe is available
            if (typeof Stripe === 'undefined') {
                console.error('YPrint Stripe Payment Request: Stripe.js is not loaded.');
                this.hidePaymentRequestElements(); // Hide elements if Stripe is missing
                return;
            }

            // Check if params are available and have required properties
            if (typeof yprint_stripe_payment_request_params === 'undefined' ||
                !yprint_stripe_payment_request_params.stripe ||
                !yprint_stripe_payment_request_params.stripe.key ||
                !yprint_stripe_payment_request_params.checkout ||
                !yprint_stripe_payment_request_params.checkout.country_code ||
                !yprint_stripe_payment_request_params.checkout.currency_code
               ) {
                console.error('YPrint Stripe Payment Request: Payment Request params are incomplete or not defined.');
                 this.hidePaymentRequestElements(); // Hide elements if params are missing
                return;
            }

            // Init elements
            this.$wrapper = $('#yprint-stripe-payment-request-wrapper');
            this.$separator = $('#yprint-stripe-payment-request-separator');

            // Check if wrapper exists - if not, there's nowhere to mount the button
            if (!this.$wrapper.length) {
                console.log('YPrint Stripe Payment Request: Payment Request wrapper element not found.');
                return;
            }

            // Initialize Stripe
            try {
                this.stripe = Stripe(yprint_stripe_payment_request_params.stripe.key, {
                    locale: yprint_stripe_payment_request_params.stripe.locale || 'auto'
                });
                 console.log('YPrint Stripe Payment Request: Stripe initialized.');
            } catch (error) {
                console.error('YPrint Stripe Payment Request: Error initializing Stripe:', error);
                 this.hidePaymentRequestElements();
                 return;
            }


            // Initialize Elements
            try {
                 this.elements = this.stripe.elements();
                 console.log('YPrint Stripe Payment Request: Stripe Elements initialized.');
            } catch (error) {
                 console.error('YPrint Stripe Payment Request: Error initializing Stripe Elements:', error);
                 this.hidePaymentRequestElements();
                 return;
            }


            // Initialize Payment Request
            this.initPaymentRequest();

            this.initialized = true;
        },

        /**
         * Initialize Payment Request (Cart/Checkout or Product)
         */
        initPaymentRequest: function() {
            var self = this;
            var params = yprint_stripe_payment_request_params;

            // Determine if it's a product page or cart/checkout
            var isProductPage = params.product && params.is_product;

            var paymentRequestOptions = {
                country: params.checkout.country_code,
                currency: params.checkout.currency_code.toLowerCase(),
                total: {
                    label: params.checkout.total_label || 'Total',
                    amount: 0, // Will be updated
                    pending: true // Start as pending until totals are known
                },
                requestPayerName: true,
                requestPayerEmail: true,
                requestPayerPhone: params.checkout.needs_payer_phone === 'yes',
                requestShipping: isProductPage ? (params.product.requestShipping || false) : (params.checkout.needs_shipping === 'yes'),
                displayItems: isProductPage ? (params.product.displayItems || []) : [] // Use product display items if on product page
            };

            // If on product page, use the initial product total
            if (isProductPage && params.product.total && typeof params.product.total.amount !== 'undefined') {
                paymentRequestOptions.total = params.product.total;
                 paymentRequestOptions.total.pending = false; // Total is known
            }


            try {
                this.paymentRequest = this.stripe.paymentRequest(paymentRequestOptions);
                 console.log('YPrint Stripe Payment Request: Payment Request object created with options:', paymentRequestOptions);
            } catch (error) {
                 console.error('YPrint Stripe Payment Request: Error creating Payment Request object:', error);
                 this.hidePaymentRequestElements();
                 return;
            }


            // Check if payment request is supported by the browser
            this.paymentRequest.canMakePayment().then(function(result) {
                if (result) {
                    console.log('YPrint Stripe Payment Request: Payment Request is available:', result);
                    self.setupPaymentRequestButton(result);

                    // If on cart/checkout page, get cart details to update the total
                    if (!isProductPage) {
                         self.getCartDetails();
                    }

                } else {
                    console.log('YPrint Stripe Payment Request: Payment Request is not available in your browser or no payment method saved.');
                    // Hide the wrapper and separator completely for better UX
                    self.hidePaymentRequestElements();
                }
            }).catch(function(error) {
                console.error('YPrint Stripe Payment Request: Error checking Payment Request availability:', error);
                // Hide the wrapper and separator if there's an error
                self.hidePaymentRequestElements();
            });

            // Handle shipping address changes
            if (paymentRequestOptions.requestShipping) {
                this.paymentRequest.on('shippingaddresschange', function(event) {
                    console.log('YPrint Stripe Payment Request: shippingaddresschange event', event.shippingAddress);
                    if (isProductPage) {
                         self.updateProductShippingOptions(event, params.product.id);
                    } else {
                         self.updateShippingOptions(event);
                    }
                });

                this.paymentRequest.on('shippingoptionchange', function(event) {
                    console.log('YPrint Stripe Payment Request: shippingoptionchange event', event.shippingOption);
                    if (isProductPage) {
                         self.updateProductShippingMethod(event, params.product.id);
                    } else {
                         self.updateShippingMethod(event);
                    }
                });
            }

            // Handle payment method changes
            this.paymentRequest.on('paymentmethod', function(event) {
                console.log('YPrint Stripe Payment Request: paymentmethod event', event.paymentMethod);
                if (isProductPage) {
                     self.handleProductPaymentMethodReceived(event, params.product.id);
                } else {
                     self.handlePaymentMethodReceived(event);
                }
            });
        },

        /**
         * Setup Payment Request Button
         */
        setupPaymentRequestButton: function(paymentMethodResult) {
            var self = this;
            var params = yprint_stripe_payment_request_params;

            // Detect payment method type for later use
            var paymentMethodType = 'payment_request'; // Default
            if (paymentMethodResult.applePay) {
                paymentMethodType = 'apple_pay';
            } else if (paymentMethodResult.googlePay) {
                paymentMethodType = 'google_pay';
            } else if (paymentMethodResult.microsoftPay) {
                 paymentMethodType = 'microsoft_pay';
            }
            this.paymentMethodType = paymentMethodType;
            console.log('YPrint Stripe Payment Request: Payment method detected:', this.paymentMethodType);

            // Ensure params.button exists before accessing its properties
            var buttonStyleOptions = {};
            if (params.button) {
                 buttonStyleOptions = {
                     type: params.button.type || 'default',
                     theme: params.button.theme || 'dark',
                     height: (params.button.height || '48') + 'px' // Ensure height is a string with 'px'
                 };
            } else {
                 console.warn('YPrint Stripe Payment Request: yprint_stripe_payment_request_params.button is not defined. Using default button style.');
                 buttonStyleOptions = {
                     type: 'default',
                     theme: 'dark',
                     height: '48px'
                 };
            }


            // Create payment request button
            try {
                 this.paymentRequestButton = this.elements.create('paymentRequestButton', {
                     paymentRequest: this.paymentRequest,
                     style: {
                         paymentRequestButton: buttonStyleOptions
                     }
                 });
                 console.log('YPrint Stripe Payment Request: Payment Request Button element created.');
            } catch (error) {
                 console.error('YPrint Stripe Payment Request: Error creating Payment Request Button element:', error);
                 self.hidePaymentRequestElements();
                 return;
            }


            // Mount button
            var buttonElementContainer = document.getElementById('yprint-stripe-payment-request-button');
            if (buttonElementContainer) {
                try {
                     this.paymentRequestButton.mount(buttonElementContainer);
                     console.log('YPrint Stripe Payment Request: Payment Request Button mounted.');

                     // Show wrapper and separator
                     this.$wrapper.show();
                     if (this.$separator.length) {
                         this.$separator.show();
                     }
                      console.log('YPrint Stripe Payment Request: Payment Request elements shown.');

                } catch (error) {
                     console.error('YPrint Stripe Payment Request: Error mounting Payment Request Button:', error);
                     self.hidePaymentRequestElements();
                }
            } else {
                console.error('YPrint Stripe Payment Request: Payment request button element #yprint-stripe-payment-request-button not found.');
                self.hidePaymentRequestElements(); // Hide wrapper if button container is missing
            }
        },

        /**
         * Hide Payment Request elements
         */
        hidePaymentRequestElements: function() {
            if (this.$wrapper && this.$wrapper.length) {
                this.$wrapper.hide();
                 console.log('YPrint Stripe Payment Request: Wrapper hidden.');
            }
             if (this.$separator && this.$separator.length) {
                 this.$separator.hide();
                  console.log('YPrint Stripe Payment Request: Separator hidden.');
             }
        },

        /**
         * Get Cart Details (for Cart/Checkout page)
         */
        getCartDetails: function() {
            var self = this;
            var params = yprint_stripe_payment_request_params;

            // Ensure nonce is available
            if (!params.nonce || !params.nonce.get_cart_details) {
                 console.error('YPrint Stripe Payment Request: Nonce for get_cart_details is missing.');
                 // Cannot proceed without nonce, maybe update PRB to pending or show error?
                 // For now, just log and stop.
                 return;
            }

            // Ensure AJAX URL is correctly formed
            var ajaxUrl = params.ajax_url;
             if (ajaxUrl.indexOf('%%endpoint%%') > -1) {
                  ajaxUrl = ajaxUrl.replace('%%endpoint%%', 'yprint_stripe_get_cart_details');
             } else {
                  // Fallback if %%endpoint%% is not in the URL (e.g., if ajax_url is already admin-ajax.php)
                  ajaxUrl = params.ajax_url + '?action=yprint_stripe_get_cart_details';
             }
             console.log('YPrint Stripe Payment Request: Calling getCartDetails AJAX endpoint:', ajaxUrl);


            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: {
                    security: params.nonce.get_cart_details,
                    // Add any other data needed by the backend (e.g., current form data)
                     form_data: $('#yprint-checkout-form').serialize() // Send form data if on checkout page
                },
                dataType: 'json',
                success: function(response) {
                    console.log('YPrint Stripe Payment Request: getCartDetails AJAX success:', response);
                    // Robust check for response structure
                    if (response && response.success && response.data && response.data.total) {
                        self.updatePaymentRequestWithDetails(response.data);
                    } else {
                        console.error('YPrint Stripe Payment Request: Error getting cart details: Invalid response structure or success is false.');
                        console.error('Received response:', response);
                        // Optionally update PRB total to 0 or pending and show an error message
                        self.updatePaymentRequestWithDetails({
                             total: { label: params.checkout.total_label || 'Total', amount: 0, pending: true },
                             displayItems: []
                        });
                         // Maybe show a message to the user on the page?
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('YPrint Stripe Payment Request: AJAX error getting cart details:', xhr.status, textStatus, errorThrown);
                     console.error('XHR Response:', xhr.responseText);
                    // Update PRB total to 0 or pending on AJAX error
                     self.updatePaymentRequestWithDetails({
                         total: { label: params.checkout.total_label || 'Total', amount: 0, pending: true },
                         displayItems: []
                    });
                     // Maybe show a message to the user on the page?
                }
            });
        },

        /**
         * Update Payment Request with details (total, displayItems)
         */
        updatePaymentRequestWithDetails: function(data) {
            if (!this.paymentRequest) {
                 console.warn('YPrint Stripe Payment Request: paymentRequest object is null, cannot update details.');
                 return;
            }
             console.log('YPrint Stripe Payment Request: Updating Payment Request with details:', data);
            // Update payment request with details
            try {
                 this.paymentRequest.update({
                     total: data.total,
                     displayItems: data.displayItems || []
                 });
                 console.log('YPrint Stripe Payment Request: Payment Request updated.');
            } catch (error) {
                 console.error('YPrint Stripe Payment Request: Error updating Payment Request object:', error);
            }
        },

        /**
         * Update Shipping Options (for Cart/Checkout page)
         */
        updateShippingOptions: function(event) {
            var self = this;
            var params = yprint_stripe_payment_request_params;

            // Ensure nonce is available
             if (!params.nonce || !params.nonce.shipping) {
                 console.error('YPrint Stripe Payment Request: Nonce for shipping is missing.');
                 event.updateWith({ status: 'fail' });
                 return;
             }

            // Ensure AJAX URL is correctly formed
            var ajaxUrl = params.ajax_url;
             if (ajaxUrl.indexOf('%%endpoint%%') > -1) {
                  ajaxUrl = ajaxUrl.replace('%%endpoint%%', 'yprint_stripe_get_shipping_options');
             } else {
                  ajaxUrl = params.ajax_url + '?action=yprint_stripe_get_shipping_options';
             }
             console.log('YPrint Stripe Payment Request: Calling updateShippingOptions AJAX endpoint:', ajaxUrl);


            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: {
                    security: params.nonce.shipping,
                    shipping_address: event.shippingAddress,
                    // Include current form data as well for context
                     form_data: $('#yprint-checkout-form').serialize()
                },
                dataType: 'json',
                success: function(response) {
                    console.log('YPrint Stripe Payment Request: updateShippingOptions AJAX success:', response);
                    // Robust check for response structure
                    if (response && response.success && response.data && response.data.total && response.data.shipping_options) {
                        event.updateWith({
                            status: 'success',
                            displayItems: response.data.displayItems || [],
                            total: response.data.total,
                            shippingOptions: response.data.shipping_options
                        });
                    } else {
                        console.error('YPrint Stripe Payment Request: Error updating shipping options: Invalid response structure or success is false.');
                        console.error('Received response:', response);
                        event.updateWith({
                            status: 'invalid_shipping_address' // Or 'fail' depending on desired UX
                        });
                         // Optionally show an error message to the user
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('YPrint Stripe Payment Request: AJAX error updating shipping options:', xhr.status, textStatus, errorThrown);
                     console.error('XHR Response:', xhr.responseText);
                    event.updateWith({
                        status: 'fail'
                    });
                     // Optionally show an error message to the user
                }
            });
        },

        /**
         * Update Shipping Method (for Cart/Checkout page)
         */
        updateShippingMethod: function(event) {
            var self = this;
            var params = yprint_stripe_payment_request_params;

            // Ensure nonce is available
             if (!params.nonce || !params.nonce.update_shipping) {
                 console.error('YPrint Stripe Payment Request: Nonce for update_shipping is missing.');
                 event.updateWith({ status: 'fail' });
                 return;
             }

             // Ensure AJAX URL is correctly formed
            var ajaxUrl = params.ajax_url;
             if (ajaxUrl.indexOf('%%endpoint%%') > -1) {
                  ajaxUrl = ajaxUrl.replace('%%endpoint%%', 'yprint_stripe_update_shipping_method');
             } else {
                  ajaxUrl = params.ajax_url + '?action=yprint_stripe_update_shipping_method';
             }
             console.log('YPrint Stripe Payment Request: Calling updateShippingMethod AJAX endpoint:', ajaxUrl);


            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: {
                    security: params.nonce.update_shipping,
                    shipping_option_id: event.shippingOption.id,
                    // Include current form data as well for context
                     form_data: $('#yprint-checkout-form').serialize()
                },
                dataType: 'json',
                success: function(response) {
                    console.log('YPrint Stripe Payment Request: updateShippingMethod AJAX success:', response);
                    // Robust check for response structure
                    if (response && response.success && response.data && response.data.total) {
                        event.updateWith({
                            status: 'success',
                            displayItems: response.data.displayItems || [],
                            total: response.data.total
                        });
                    } else {
                        console.error('YPrint Stripe Payment Request: Error updating shipping method: Invalid response structure or success is false.');
                        console.error('Received response:', response);
                        event.updateWith({
                            status: 'fail'
                        });
                         // Optionally show an error message to the user
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('YPrint Stripe Payment Request: AJAX error updating shipping method:', xhr.status, textStatus, errorThrown);
                     console.error('XHR Response:', xhr.responseText);
                    event.updateWith({
                        status: 'fail'
                    });
                     // Optionally show an error message to the user
                }
            });
        },

        /**
         * Update Product Shipping Options (for Product page)
         */
        updateProductShippingOptions: function(event, productId) {
             var self = this;
             var params = yprint_stripe_payment_request_params;

             // Ensure nonce is available
             if (!params.nonce || !params.nonce.shipping) {
                 console.error('YPrint Stripe Payment Request: Nonce for shipping is missing.');
                 event.updateWith({ status: 'fail' });
                 return;
             }

              // Ensure AJAX URL is correctly formed
            var ajaxUrl = params.ajax_url;
             if (ajaxUrl.indexOf('%%endpoint%%') > -1) {
                  ajaxUrl = ajaxUrl.replace('%%endpoint%%', 'yprint_stripe_get_shipping_options');
             } else {
                  ajaxUrl = params.ajax_url + '?action=yprint_stripe_get_shipping_options';
             }
              console.log('YPrint Stripe Payment Request: Calling updateProductShippingOptions AJAX endpoint:', ajaxUrl);


             $.ajax({
                 type: 'POST',
                 url: ajaxUrl,
                 data: {
                     security: params.nonce.shipping,
                     shipping_address: event.shippingAddress,
                     product_id: productId,
                     is_product: true,
                     // Include product quantity or other relevant data
                      quantity: $('input.qty').val() || 1
                 },
                 dataType: 'json',
                 success: function(response) {
                     console.log('YPrint Stripe Payment Request: updateProductShippingOptions AJAX success:', response);
                     // Robust check for response structure
                     if (response && response.success && response.data && response.data.total && response.data.shipping_options) {
                         event.updateWith({
                             status: 'success',
                             displayItems: response.data.displayItems || [],
                             total: response.data.total,
                             shippingOptions: response.data.shipping_options
                         });
                     } else {
                         console.error('YPrint Stripe Payment Request: Error updating product shipping options: Invalid response structure or success is false.');
                         console.error('Received response:', response);
                         event.updateWith({
                             status: 'invalid_shipping_address' // Or 'fail'
                         });
                          // Optionally show an error message
                     }
                 },
                 error: function(xhr, textStatus, errorThrown) {
                     console.error('YPrint Stripe Payment Request: AJAX error updating product shipping options:', xhr.status, textStatus, errorThrown);
                      console.error('XHR Response:', xhr.responseText);
                     event.updateWith({
                         status: 'fail'
                     });
                      // Optionally show an error message
                 }
             });
        },

        /**
         * Update Product Shipping Method (for Product page)
         */
        updateProductShippingMethod: function(event, productId) {
             var self = this;
             var params = yprint_stripe_payment_request_params;

             // Ensure nonce is available
             if (!params.nonce || !params.nonce.update_shipping) {
                 console.error('YPrint Stripe Payment Request: Nonce for update_shipping is missing.');
                 event.updateWith({ status: 'fail' });
                 return;
             }

             // Ensure AJAX URL is correctly formed
            var ajaxUrl = params.ajax_url;
             if (ajaxUrl.indexOf('%%endpoint%%') > -1) {
                  ajaxUrl = ajaxUrl.replace('%%endpoint%%', 'yprint_stripe_update_shipping_method');
             } else {
                  ajaxUrl = params.ajax_url + '?action=yprint_stripe_update_shipping_method';
             }
              console.log('YPrint Stripe Payment Request: Calling updateProductShippingMethod AJAX endpoint:', ajaxUrl);


             $.ajax({
                 type: 'POST',
                 url: ajaxUrl,
                 data: {
                     security: params.nonce.update_shipping,
                     shipping_option_id: event.shippingOption.id,
                     product_id: productId,
                     is_product: true,
                     // Include product quantity or other relevant data
                      quantity: $('input.qty').val() || 1
                 },
                 dataType: 'json',
                 success: function(response) {
                     console.log('YPrint Stripe Payment Request: updateProductShippingMethod AJAX success:', response);
                     // Robust check for response structure
                     if (response && response.success && response.data && response.data.total) {
                         event.updateWith({
                             status: 'success',
                             displayItems: response.data.displayItems || [],
                             total: response.data.total
                         });
                     } else {
                         console.error('YPrint Stripe Payment Request: Error updating product shipping method: Invalid response structure or success is false.');
                         console.error('Received response:', response);
                         event.updateWith({
                             status: 'fail'
                         });
                          // Optionally show an error message
                     }
                 },
                 error: function(xhr, textStatus, errorThrown) {
                     console.error('YPrint Stripe Payment Request: AJAX error updating product shipping method:', xhr.status, textStatus, errorThrown);
                      console.error('XHR Response:', xhr.responseText);
                     event.updateWith({
                         status: 'fail'
                     });
                      // Optionally show an error message
                 }
             });
        },


        /**
         * Handle Payment Method Received (for Cart/Checkout page)
         */
        handlePaymentMethodReceived: function(event) {
            console.log('YPrint Stripe Payment Request: handlePaymentMethodReceived called.');
            // Process payment directly
            this.processPayment(event.paymentMethod.id, event);
        },

        /**
         * Handle Product Payment Method Received (for Product page)
         */
        handleProductPaymentMethodReceived: function(event, productId) {
            var self = this;
            var params = yprint_stripe_payment_request_params;

            console.log('YPrint Stripe Payment Request: handleProductPaymentMethodReceived called for product ID:', productId);

            // Ensure nonce is available
             if (!params.nonce || !params.nonce.add_to_cart) {
                 console.error('YPrint Stripe Payment Request: Nonce for add_to_cart is missing.');
                 event.complete('fail');
                 return;
             }

             // Ensure AJAX URL is correctly formed
            var ajaxUrl = params.ajax_url;
             if (ajaxUrl.indexOf('%%endpoint%%') > -1) {
                  ajaxUrl = ajaxUrl.replace('%%endpoint%%', 'yprint_stripe_add_to_cart');
             } else {
                  ajaxUrl = params.ajax_url + '?action=yprint_stripe_add_to_cart';
             }
              console.log('YPrint Stripe Payment Request: Calling add_to_cart AJAX endpoint:', ajaxUrl);


            // Add product to cart first
            $.ajax({
                type: 'POST',
                url: ajaxUrl,
                data: {
                    security: params.nonce.add_to_cart,
                    product_id: productId,
                    quantity: $('input.qty').val() || 1 // Get quantity from input field
                },
                dataType: 'json',
                success: function(response) {
                    console.log('YPrint Stripe Payment Request: add_to_cart AJAX success:', response);
                    if (response && response.success) {
                        console.log('YPrint Stripe Payment Request: Product added to cart successfully.');
                        // Process payment after adding to cart
                        self.processPayment(event.paymentMethod.id, event);
                    } else {
                        event.complete('fail');
                        console.error('YPrint Stripe Payment Request: Error adding product to cart:', (response && response.data && response.data.message) ? response.data.message : 'Unknown error.');
                         // Optionally show an error message to the user
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    event.complete('fail');
                    console.error('YPrint Stripe Payment Request: AJAX error adding product to cart:', xhr.status, textStatus, errorThrown);
                     console.error('XHR Response:', xhr.responseText);
                     // Optionally show an error message to the user
                }
            });
        },

        /**
         * Process Payment
         */
        processPayment: function(paymentMethodId, event) {
            var self = this;
            var params = yprint_stripe_payment_request_params;

             console.log('YPrint Stripe Payment Request: processPayment called with method ID:', paymentMethodId);

             // Ensure nonce is available
             if (!params.nonce || !params.nonce.payment) {
                 console.error('YPrint Stripe Payment Request: Nonce for payment is missing.');
                 event.complete('fail');
                 // Optionally show an error message
                 return;
             }

            // Ensure AJAX URL is correctly formed
            var ajaxUrl = params.ajax_url;
             if (ajaxUrl.indexOf('%%endpoint%%') > -1) {
                  ajaxUrl = ajaxUrl.replace('%%endpoint%%', 'yprint_stripe_process_payment');
             } else {
                  ajaxUrl = params.ajax_url + '?action=yprint_stripe_process_payment';
             }
             console.log('YPrint Stripe Payment Request: Calling processPayment AJAX endpoint:', ajaxUrl);


            // Gather payment and customer data
            var paymentData = {
                security: params.nonce.payment,
                payment_method_id: paymentMethodId,
                payment_request_type: this.paymentMethodType || 'payment_request'
            };

            // Add billing details if available from the event
            if (event.payerName) {
                // Attempt to split name into first and last (basic)
                var nameParts = event.payerName.split(' ');
                paymentData.billing_first_name = nameParts[0];
                paymentData.billing_last_name = nameParts.slice(1).join(' ');
            }
            if (event.payerEmail) {
                paymentData.billing_email = event.payerEmail;
            }
            if (event.payerPhone) {
                paymentData.billing_phone = event.payerPhone;
            }

            // Add billing address if available from the payment method details
             if (event.paymentMethod && event.paymentMethod.billing_details && event.paymentMethod.billing_details.address) {
                 var billingAddress = event.paymentMethod.billing_details.address;
                 paymentData.billing_address_1 = billingAddress.line1 || undefined;
                 paymentData.billing_address_2 = billingAddress.line2 || undefined;
                 paymentData.billing_city = billingAddress.city || undefined;
                 paymentData.billing_state = billingAddress.state || undefined; // Use 'state' property
                 paymentData.billing_postcode = billingAddress.postal_code || undefined; // Use 'postal_code' property
                 paymentData.billing_country = billingAddress.country || undefined;
             }


            // Add shipping details if available from the event
            if (event.shippingAddress) {
                 console.log('YPrint Stripe Payment Request: Including shipping address from Payment Request event.');
                 // Handle recipient name format (Apple Pay vs Standard)
                 if (event.shippingAddress.recipient) {
                     var recipientParts = event.shippingAddress.recipient.split(' ');
                     paymentData.shipping_first_name = recipientParts[0];
                     paymentData.shipping_last_name = recipientParts.slice(1).join(' ');
                 }
                 // Standard format
                 else if (event.shippingAddress.name) {
                      var nameParts = event.shippingAddress.name.split(' ');
                      paymentData.shipping_first_name = nameParts[0];
                      paymentData.shipping_last_name = nameParts.slice(1).join(' ');
                 }

                 paymentData.shipping_country = event.shippingAddress.country || undefined;
                 paymentData.shipping_state = event.shippingAddress.region || event.shippingAddress.state || event.shippingAddress.administrativeArea || undefined; // Handle variations
                 paymentData.shipping_postcode = event.shippingAddress.postalCode || event.shippingAddress.postal_code || undefined; // Handle variations
                 paymentData.shipping_city = event.shippingAddress.city || event.shippingAddress.locality || undefined; // Handle variations

                 // Handle address lines (different formats)
                 if (event.shippingAddress.addressLine) {
                     paymentData.shipping_address_1 = event.shippingAddress.addressLine.length > 0 ? event.shippingAddress.addressLine[0] : undefined;
                     paymentData.shipping_address_2 = event.shippingAddress.addressLine.length > 1 ? event.shippingAddress.addressLine[1] : undefined;
                 } else if (event.shippingAddress.line1) {
                     paymentData.shipping_address_1 = event.shippingAddress.line1 || undefined;
                     paymentData.shipping_address_2 = event.shippingAddress.line2 || undefined;
                 }

                 // Indicate that shipping is to a different address if shipping address is provided via PRB
                 paymentData.ship_to_different_address = 1;

            } else {
                 console.log('YPrint Stripe Payment Request: No shipping address from Payment Request event.');
                 // If shipping is required but not provided by PRB, the backend needs to handle this.
                 // It might use the billing address or fail validation.
                 // We could potentially send form data here as a fallback, but the backend needs to be ready for it.
                 // For now, just send what PRB provides.
            }

            // Include current form data as well for context on checkout page
            // This allows the backend to merge PRB data with form data
             if ($('#yprint-checkout-form').length) {
                  paymentData.form_data = $('#yprint-checkout-form').serialize();
             }


            $.ajax({
                type: 'POST',
                url: ajaxUrl, // URL already includes action
                data: paymentData,
                dataType: 'json',
                success: function(response) {
                    console.log('YPrint Stripe Payment Request: processPayment AJAX success:', response);
                    if (response && response.success) {
                        console.log('YPrint Stripe Payment Request: Payment processed successfully.');
                        event.complete('success');

                        // Redirect to thank you page or handle next action (e.g., 3D Secure)
                        if (response.data && response.data.redirect) {
                            window.location.href = response.data.redirect;
                        } else {
                             console.warn('YPrint Stripe Payment Request: processPayment success but no redirect URL provided.');
                             // What to do if no redirect? Maybe reload the page or show a success message.
                             // For now, just log.
                        }

                    } else {
                        console.error('YPrint Stripe Payment Request: Error processing payment:', (response && response.data && response.data.message) ? response.data.message : 'Unknown error.');
                        event.complete('fail');
                         // Optionally show an error message to the user
                    }
                },
                error: function(xhr, textStatus, errorThrown) {
                    console.error('YPrint Stripe Payment Request: AJAX error processing payment:', xhr.status, textStatus, errorThrown);
                     console.error('XHR Response:', xhr.responseText);
                    event.complete('fail');
                     // Optionally show an error message to the user
                }
            });
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        YPrintStripePaymentRequest.init();
    });

     // Make globally accessible for debugging
     window.YPrintStripePaymentRequest = YPrintStripePaymentRequest;

})(jQuery);
