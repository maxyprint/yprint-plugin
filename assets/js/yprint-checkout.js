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

        // Store payment method type for Payment Request Button (Apple Pay, Google Pay, etc.)
        paymentMethodType: 'standard',

        init: function() {
            console.log('Initializing YPrint Checkout');

            // Check if jQuery is available
            if (typeof $ === 'undefined') {
                console.error('jQuery is not available. YPrint Checkout cannot initialize.');
                return;
            }

            // Check if yprint_checkout_params is available
            if (typeof yprint_checkout_params === 'undefined') {
                console.error('yprint_checkout_params is not available. YPrint Checkout cannot initialize.');
                return;
            }

            // Initialize variables
            this.$form = $('#yprint-checkout-form');
            this.$messages = $('.yprint-checkout-messages');
            // Updated selectors for new address sections
            this.$shippingAddressNewFields = $('.shipping-address-new-fields');
            this.$billingAddressSection = $('.billing-address-section');
            this.$billingAddressSameAsShipping = $('.billing-address-same-as-shipping');
            this.$billingSavedAddressesSection = $('.yprint-saved-billing-addresses-section');
            this.$billingNewFields = $('.billing-address-new-fields');
            this.$placeOrderButton = $('#yprint-place-order');

            // Setup event handlers
            this.setupEventHandlers();

            // Initialize Stripe
            this.initStripe();

            // Update checkout on load
            this.updateCheckout();

            // Initial display state for address sections
            this.updateAddressSectionsDisplay();
        },

        setupEventHandlers: function() {
            var self = this;

            // Shipping address selection (saved vs new)
            $('input[name="yprint_shipping_address_selection"]').on('change', function() {
                self.updateAddressSectionsDisplay();
                self.updateCheckout(); // Update checkout when shipping address changes
            });

            // Separate billing address checkbox
            $('#ship_to_different_address').on('change', function() {
                self.updateAddressSectionsDisplay();
                self.updateCheckout(); // Update checkout when billing address visibility changes
            });

             // Billing address selection (saved vs new) - only relevant when separate billing is checked
            $('input[name="yprint_billing_address_selection"]').on('change', function() {
                 self.updateAddressSectionsDisplay();
                 self.updateCheckout(); // Update checkout when billing address changes
             });


            // Country/state selectors
            // Use delegated events for potentially dynamically loaded content
            $(document).on('change', 'select.country-select', function() {
                var $this = $(this);
                var country = $this.val();

                if (!country) {
                    // If country is empty, clear states and update checkout
                    var $stateField;
                    if ($this.attr('id') === 'billing_country') {
                        $stateField = $('#billing_state');
                    } else {
                        $stateField = $('#shipping_state');
                    }
                    $stateField.empty().append('<option value="">' + (yprint_checkout_params.i18n && yprint_checkout_params.i18n.select_state_text ? yprint_checkout_params.i18n.select_state_text : 'Select a state...') + '</option>').prop('disabled', true);
                    self.updateCheckout();
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

                // Update checkout (will be triggered after states update in updateStates callback)
                // self.updateCheckout(); // Removed, called in updateStates success
            });

            // State selectors
            $(document).on('change', 'select.state-select', function() {
                self.updateCheckout();
            });

            // Shipping method
            $(document).on('change', 'input[name="shipping_method"]', function() {
                self.updateCheckout();
            });

            // Postcode / city fields - Update checkout on change and blur
            $(document).on('change blur', '#billing_postcode, #billing_city, #shipping_postcode, #shipping_city', function() {
                 // Add a small delay to avoid multiple rapid updates on blur
                 clearTimeout($(this).data('updateCheckoutTimer'));
                 $(this).data('updateCheckoutTimer', setTimeout(function() {
                     self.updateCheckout();
                 }, 250)); // Delay of 250ms
            });


            // Form submission
            this.$form.on('submit', function(e) {
                e.preventDefault();
                self.placeOrder();
            });
        },

        initStripe: function() {
            var self = this;

            // Check if Stripe is available and key is provided
            if (typeof Stripe === 'undefined' || !yprint_checkout_params.stripe || !yprint_checkout_params.stripe.key) {
                console.error('Stripe is not available or publishable key is missing.');
                // Hide Stripe card and payment request elements if Stripe is not available
                $('#yprint-stripe-card-element').closest('.form-row').hide(); // Hide the whole form-row
                $('#yprint-stripe-card-errors').hide();
                paymentRequestWrapper.hide();
                paymentMethodSeparator.hide();
                return;
            }

            try {
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
                var cardElementContainer = document.getElementById('yprint-stripe-card-element');
                if (cardElementContainer) {
                     cardElement.mount(cardElementContainer);
                } else {
                     console.error('Stripe card element container not found.');
                }


                // Handle card errors
                cardElement.on('change', function(event) {
                    var displayError = document.getElementById('yprint-stripe-card-errors');

                    if (displayError) {
                        if (event.error) {
                            displayError.textContent = event.error.message;
                        } else {
                            displayError.textContent = '';
                        }
                    }
                });

                // Initialize Payment Request Button
                this.initPaymentRequest();

            } catch (error) {
                console.error('Error initializing Stripe:', error);
                 // Hide Stripe card and payment request elements in case of initialization error
                $('#yprint-stripe-card-element').closest('.form-row').hide();
                $('#yprint-stripe-card-errors').hide();
                paymentRequestWrapper.hide();
                paymentMethodSeparator.hide();
            }
        },

        initPaymentRequest: function() {
            var self = this;

            // Check if Payment Request API is supported by the browser and Stripe is initialized
            if (!stripe || typeof stripe.paymentRequest !== 'function' || !yprint_checkout_params.currency || !yprint_checkout_params.country_code) {
                 console.log('Stripe Payment Request API not supported or parameters missing.');
                 paymentRequestWrapper.hide();
                 paymentMethodSeparator.hide();
                 return;
            }

            try {
                var paymentRequestOptions = {
                    country: yprint_checkout_params.country_code,
                    currency: yprint_checkout_params.currency.toLowerCase(),
                    total: {
                        label: yprint_checkout_params.total_label || 'Order Total', // Use localized label
                        amount: 0, // Will be updated
                        pending: true
                    },
                    requestPayerName: true,
                    requestPayerEmail: true,
                    requestPayerPhone: true,
                };

                // Add shipping request if required
                if (yprint_checkout_params.shipping_required) {
                    paymentRequestOptions.requestShipping = true;
                }

                paymentRequest = stripe.paymentRequest(paymentRequestOptions);

                // Handle shipping address changes (only if shipping is required)
                if (yprint_checkout_params.shipping_required) {
                    paymentRequest.on('shippingaddresschange', function(event) {
                        console.log('Payment Request Shipping Address Change:', event.shippingAddress);
                        self.updatePaymentRequestShipping(event);
                    });

                    // Handle shipping option changes
                    paymentRequest.on('shippingoptionchange', function(event) {
                         console.log('Payment Request Shipping Option Change:', event.shippingOption);
                         self.updatePaymentRequestShippingOption(event);
                    });
                }


                // Handle payment method
                paymentRequest.on('paymentmethod', function(event) {
                    console.log('Payment method received:', event.paymentMethod);
                    self.handlePaymentMethodReceived(event);
                });

                // Check if Payment Request is available
                paymentRequest.canMakePayment().then(function(result) {
                    if (result) {
                        console.log('Payment Request available:', result);
                        // Display payment request button
                        self.displayPaymentRequestButton(result);
                    } else {
                        console.log('Payment Request is not available in this browser/device or no payment method saved.');
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
                // This is already called at the end of YPrintCheckout.init()
                // self.updateCheckout();

            } catch (error) {
                console.error('Error initializing Payment Request:', error);
                 paymentRequestWrapper.hide();
                 paymentMethodSeparator.hide();
            }
        },

        displayPaymentRequestButton: function(paymentMethod) {
            console.log('Setting up Payment Request Button with method:', paymentMethod);

            // Determine payment method type for analytics/later use
            var paymentMethodType = 'standard'; // Default
            if (paymentMethod.applePay) paymentMethodType = 'apple_pay';
            else if (paymentMethod.googlePay) paymentMethodType = 'google_pay';
            else if (paymentMethod.microsoftPay) paymentMethodType = 'microsoft_pay';

            console.log('Detected Payment method type:', paymentMethodType);

            // Create payment request button
            // Ensure yprint_checkout_params.button exists
            var buttonStyleOptions = {};
            if (yprint_checkout_params.button) {
                 buttonStyleOptions = {
                     type: yprint_checkout_params.button.type || 'default',
                     theme: yprint_checkout_params.button.theme || 'dark',
                     height: (yprint_checkout_params.button.height || '48') + 'px' // Ensure height is a string with 'px'
                 };
            } else {
                 console.warn('yprint_checkout_params.button is not defined. Using default button style.');
                 buttonStyleOptions = {
                     type: 'default',
                     theme: 'dark',
                     height: '48px'
                 };
            }


            paymentRequestButton = elements.create('paymentRequestButton', {
                paymentRequest: paymentRequest,
                style: {
                    paymentRequestButton: buttonStyleOptions
                }
            });

            // Check if the element exists before mounting
            var buttonElement = document.getElementById('yprint-stripe-payment-request-button');
            if (buttonElement) {
                // Mount the button
                paymentRequestButton.mount(buttonElement);

                // Show the wrapper and separator
                paymentRequestWrapper.show();
                paymentMethodSeparator.show();

                // Store payment method type for later use
                this.paymentMethodType = paymentMethodType;
            } else {
                console.error('Payment request button element #yprint-stripe-payment-request-button not found.');
                paymentRequestWrapper.hide(); // Hide if element not found
                paymentMethodSeparator.hide();
            }
        },

        updatePaymentRequestShipping: function(event) {
            var self = this;

            // Get the address
            var address = event.shippingAddress;

            // Update the checkout data with the address for AJAX call
            var data = {
                shipping_country: address.country,
                shipping_state: address.region || address.administrativeArea, // Handle potential different property names
                shipping_postcode: address.postalCode,
                shipping_city: address.city || address.locality, // Handle potential different property names
                shipping_address_1: address.addressLine && address.addressLine.length > 0 ? address.addressLine[0] : '',
                shipping_address_2: address.addressLine && address.addressLine.length > 1 ? address.addressLine[1] : '',
                // Include other relevant fields if needed by your backend update logic
                // We need to send the currently selected billing address or new billing fields as well
                // to ensure the backend has the full context for calculations.
                // This requires reading values from the appropriate billing fields based on
                // the 'ship_to_different_address' checkbox and 'yprint_billing_address_selection' radio.
                // For simplicity here, we'll send the form values. Your backend needs to handle this.
                billing_first_name: $('#billing_first_name').val(),
                billing_last_name: $('#billing_last_name').val(),
                billing_company: $('#billing_company').val(),
                billing_country: $('#billing_country').val(),
                billing_address_1: $('#billing_address_1').val(),
                billing_address_2: $('#billing_address_2').val(),
                billing_postcode: $('#billing_postcode').val(),
                billing_city: $('#billing_city').val(),
                billing_state: $('#billing_state').val(),
                billing_phone: $('#billing_phone').val(),
                billing_email: $('#billing_email').val(),
                ship_to_different_address: $('#ship_to_different_address').is(':checked') ? 1 : 0,
                yprint_shipping_address_selection: $('input[name="yprint_shipping_address_selection"]:checked').val(),
                yprint_billing_address_selection: $('input[name="yprint_billing_address_selection"]:checked').val(),
                shipping_address_title: $('#shipping_address_title').val(), // Include title if new address
                billing_address_title: $('#billing_address_title').val() // Include title if new address
            };

            // Make AJAX request to get shipping options and updated totals
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: {
                    action: 'yprint_update_checkout',
                    nonce: yprint_checkout_params.nonce,
                    checkout_data: $.param(data) // Serialize the data object
                },
                success: function(response) {
                    console.log('updatePaymentRequestShipping AJAX success:', response);
                    // Robust check for response structure
                    if (response && response.success && response.data && response.data.totals && response.data.shipping_methods) {
                        var data = response.data;

                        // Prepare shipping options
                        var shippingOptions = [];

                        if (data.shipping_methods.length > 0) {
                            data.shipping_methods.forEach(function(method) {
                                // Ensure cost is a number
                                var cost = parseFloat(method.cost);
                                if (!isNaN(cost)) {
                                    shippingOptions.push({
                                        id: method.id,
                                        label: method.label,
                                        detail: method.cost_formatted || '', // Use formatted cost for detail if available
                                        amount: Math.round(cost * 100) // Amount in cents
                                    });
                                } else {
                                    console.warn('Invalid shipping method cost:', method.cost);
                                }
                            });
                        }

                        // Prepare total
                        // Ensure total is a number
                        var totalAmount = parseFloat(data.totals.total);
                        var total = {
                            label: yprint_checkout_params.total_label || 'Total',
                            amount: !isNaN(totalAmount) ? Math.round(totalAmount * 100) : 0, // Amount in cents
                            pending: false // Totals are now calculated
                        };

                        // Update event
                        event.updateWith({
                            status: shippingOptions.length > 0 ? 'success' : 'invalid_shipping_address', // Indicate success or no shipping
                            shippingOptions: shippingOptions,
                            total: total
                        });
                         // Also update the totals and shipping methods displayed on the page
                         self.updateDisplayedTotals(data.totals);
                         self.updateShippingMethods(data.shipping_methods);

                    } else {
                        console.error('updatePaymentRequestShipping AJAX failed: Invalid response structure or success is false.');
                        console.error('Received response:', response);
                        event.updateWith({
                            status: 'invalid_shipping_address' // Indicate failure
                        });
                         // Optionally show an error message on the page
                         self.showError(yprint_checkout_params.i18n.checkout_error || 'Error updating shipping.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('updatePaymentRequestShipping AJAX error:', xhr.status, status, error);
                    console.error('XHR Response:', xhr.responseText);
                    event.updateWith({
                        status: 'fail' // Indicate complete failure
                    });
                    // Show a generic error message
                    self.showError(yprint_checkout_params.i18n.checkout_error || 'Error updating shipping.');
                }
            });
        },

        updatePaymentRequestShippingOption: function(event) {
            var self = this;

            // Get the selected shipping option
            var shippingOption = event.shippingOption;
            console.log('Selected shipping option:', shippingOption);

            // Make AJAX request to update the chosen shipping method in the cart/session
            // Include current address data to ensure context for recalculation
            var data = {
                shipping_method: shippingOption.id,
                 // Include current billing/shipping address data as well to ensure context
                 billing_first_name: $('#billing_first_name').val(),
                 billing_last_name: $('#billing_last_name').val(),
                 billing_company: $('#billing_company').val(),
                 billing_country: $('#billing_country').val(),
                 billing_address_1: $('#billing_address_1').val(),
                 billing_address_2: $('#billing_address_2').val(),
                 billing_postcode: $('#billing_postcode').val(),
                 billing_city: $('#billing_city').val(),
                 billing_state: $('#billing_state').val(),
                 billing_phone: $('#billing_phone').val(),
                 billing_email: $('#billing_email').val(),
                 shipping_first_name: $('#shipping_first_name').val(),
                 shipping_last_name: $('#shipping_last_name').val(),
                 shipping_company: $('#shipping_company').val(),
                 shipping_country: $('#shipping_country').val(),
                 shipping_address_1: $('#shipping_address_1').val(),
                 shipping_address_2: $('#shipping_address_2').val(),
                 shipping_postcode: $('#shipping_postcode').val(),
                 shipping_city: $('#shipping_city').val(),
                 shipping_state: $('#shipping_state').val(),
                 ship_to_different_address: $('#ship_to_different_address').is(':checked') ? 1 : 0,
                 yprint_shipping_address_selection: $('input[name="yprint_shipping_address_selection"]:checked').val(),
                 yprint_billing_address_selection: $('input[name="yprint_billing_address_selection"]:checked').val(),
                 shipping_address_title: $('#shipping_address_title').val(), // Include title if new address
                 billing_address_title: $('#billing_address_title').val() // Include title if new address
            };

            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: {
                    action: 'yprint_update_checkout', // Use the same update action
                    nonce: yprint_checkout_params.nonce,
                    checkout_data: $.param(data) // Serialize the data object
                },
                success: function(response) {
                    console.log('updatePaymentRequestShippingOption AJAX success:', response);
                    // Robust check for response structure
                    if (response && response.success && response.data && response.data.totals) {
                        var data = response.data;

                        // Prepare updated total based on the new shipping method
                         var totalAmount = parseFloat(data.totals.total);
                        var total = {
                            label: yprint_checkout_params.total_label || 'Total',
                            amount: !isNaN(totalAmount) ? Math.round(totalAmount * 100) : 0, // Amount in cents
                            pending: false // Totals are now calculated
                        };

                        // Update event
                        event.updateWith({
                            status: 'success',
                            total: total
                        });
                         // Also update the totals displayed on the page
                         self.updateDisplayedTotals(data.totals);
                         // No need to update shipping methods list here, as it's already done in updateCheckout

                    } else {
                        console.error('updatePaymentRequestShippingOption AJAX failed: Invalid response structure or success is false.');
                        console.error('Received response:', response);
                        event.updateWith({
                            status: 'fail'
                        });
                         self.showError(yprint_checkout_params.i18n.checkout_error || 'Error updating shipping option.');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('updatePaymentRequestShippingOption AJAX error:', xhr.status, status, error);
                    console.error('XHR Response:', xhr.responseText);
                    event.updateWith({
                        status: 'fail'
                    });
                     self.showError(yprint_checkout_params.i18n.checkout_error || 'Error updating shipping option.');
                }
            });
        },

        handlePaymentMethodReceived: function(event) {
            var self = this;

            console.log('Payment method received:', event.paymentMethod);

            // Set the payment method ID in a hidden field
            $('#payment_method_id').val(event.paymentMethod.id);

            // Get form data (this will include the selected address radio buttons)
            var formData = this.$form.serialize();

            // Add payment method type (Apple Pay, Google Pay, etc.)
            formData += '&payment_request_type=' + encodeURIComponent(this.paymentMethodType || 'payment_request');

            // Add billing and shipping data from the payment request if available
            // This data should override the form data if provided by the Payment Request Button
            var additionalData = {};

            // Payer details
            if (event.payerName) {
                 var nameParts = event.payerName.split(' ');
                 additionalData.billing_first_name = nameParts[0];
                 additionalData.billing_last_name = nameParts.slice(1).join(' ');
            }
            if (event.payerEmail) additionalData.billing_email = event.payerEmail;
            if (event.payerPhone) additionalData.billing_phone = event.payerPhone;


            // Billing address from payment method details
            if (event.paymentMethod.billing_details && event.paymentMethod.billing_details.address) {
                var billingAddress = event.paymentMethod.billing_details.address;
                if (billingAddress.line1) additionalData.billing_address_1 = billingAddress.line1;
                if (billingAddress.line2) additionalData.billing_address_2 = billingAddress.line2;
                if (billingAddress.city) additionalData.billing_city = billingAddress.city;
                if (billingAddress.state) additionalData.billing_state = billingAddress.state;
                if (billingAddress.postal_code) additionalData.billing_postcode = billingAddress.postal_code;
                if (billingAddress.country) additionalData.billing_country = billingAddress.country;
            } else {
                 // Fallback to form values if billing details not provided by payment method
                 console.warn('Billing details not provided by Payment Method. Using form values.');
                 // No need to explicitly add form values here, as they are already in formData.
                 // The backend should handle which data source to prioritize.
            }


            // Shipping details from payment request event
            if (event.shippingAddress) {
                console.log('Using shipping address from Payment Request event.');
                // Handle recipient name format (Apple Pay vs Standard)
                if (event.shippingAddress.recipient) {
                    var recipientParts = event.shippingAddress.recipient.split(' ');
                    additionalData.shipping_first_name = recipientParts[0];
                    additionalData.shipping_last_name = recipientParts.slice(1).join(' ');
                }
                // Standard format
                else if (event.shippingAddress.name) {
                     var nameParts = event.shippingAddress.name.split(' ');
                     additionalData.shipping_first_name = nameParts[0];
                     additionalData.shipping_last_name = nameParts.slice(1).join(' ');
                }

                additionalData.shipping_country = event.shippingAddress.country;
                additionalData.shipping_state = event.shippingAddress.region || event.shippingAddress.state || event.shippingAddress.administrativeArea; // Handle variations
                additionalData.shipping_postcode = event.shippingAddress.postalCode || event.shippingAddress.postal_code; // Handle variations
                additionalData.shipping_city = event.shippingAddress.city || event.shippingAddress.locality; // Handle variations

                // Handle address lines (different formats)
                if (event.shippingAddress.addressLine) {
                    additionalData.shipping_address_1 = event.shippingAddress.addressLine.length > 0 ? event.shippingAddress.addressLine[0] : '';
                    additionalData.shipping_address_2 = event.shippingAddress.addressLine.length > 1 ? event.shippingAddress.addressLine[1] : '';
                } else if (event.shippingAddress.line1) {
                    additionalData.shipping_address_1 = event.shippingAddress.line1;
                    additionalData.shipping_address_2 = event.shippingAddress.line2 || '';
                }

                // Mark that shipping is to a different address if shipping address is provided via PRB
                // This might override the user's checkbox selection, which is correct behavior for PRB
                additionalData.ship_to_different_address = 1;
                // Also, since PRB provides a specific address, the selection is implicitly "new_address"
                additionalData.yprint_shipping_address_selection = 'new_address';


            } else {
                 console.warn('Shipping address not provided by Payment Request. Using form values.');
                 // No need to explicitly add form values here, as they are already in formData.
                 // The backend should handle which data source to prioritize.
            }


            // Add additional data to form data, overwriting existing fields if provided by PRB
            for (var key in additionalData) {
                if (additionalData.hasOwnProperty(key) && additionalData[key] !== undefined && additionalData[Data] !== null) {
                     // Use a regex to replace existing key=value pairs or add if not present
                     // This ensures PRB data overrides form data
                     var regex = new RegExp('(^|&)' + key + '=[^&]*', 'g');
                     if (formData.match(regex)) {
                         formData = formData.replace(regex, '$1' + key + '=' + encodeURIComponent(additionalData[key]));
                     } else {
                         formData += '&' + key + '=' + encodeURIComponent(additionalData[key]);
                     }
                }
            }

            // Add the payment method ID to the form data
             formData += '&payment_method_id=' + encodeURIComponent(event.paymentMethod.id);
             formData += '&payment_method=yprint_stripe'; // Ensure the correct payment method is set

            console.log('Final form data for process_checkout:', formData);

            // Show processing message
            self.showProcessingMessage();

            // Process the payment
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: {
                    action: 'yprint_process_checkout',
                    nonce: yprint_checkout_params.nonce,
                    checkout_data: formData // Send the combined form data
                },
                success: function(response) {
                    console.log('processCheckout AJAX success:', response);
                    if (response && response.success && response.data && response.data.redirect) {
                        // Complete the payment in the Payment Request UI
                        event.complete('success');

                        // Redirect to thank you page
                        window.location.href = response.data.redirect;
                    } else {
                        console.error('processCheckout AJAX failed: Invalid response structure or success is false.');
                        console.error('Received response:', response);
                        // Complete the payment with failure in the Payment Request UI
                        event.complete('fail');

                        // Show error message
                        var errorMessage = (response && response.data && response.data.message)
                            ? response.data.message
                            : (yprint_checkout_params.i18n.checkout_error || 'Error processing checkout.');
                        self.showError(errorMessage);

                         // Re-enable the place order button
                        var placeOrderText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.place_order ? yprint_checkout_params.i18n.place_order : 'Verbindlich Bestellen';
                        self.$placeOrderButton.prop('disabled', false).text(placeOrderText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('processCheckout AJAX error:', xhr.status, status, error);
                    console.error('XHR Response:', xhr.responseText);
                    // Complete the payment with failure in the Payment Request UI
                    event.complete('fail');

                    // Show error message
                    var errorMessage = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                        ? xhr.responseJSON.data.message
                        : (yprint_checkout_params.i18n.checkout_error || 'Error processing checkout.');

                    self.showError(errorMessage);

                    // Re-enable the place order button
                    var placeOrderText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.place_order ? yprint_checkout_params.i18n.place_order : 'Verbindlich Bestellen';
                    self.$placeOrderButton.prop('disabled', false).text(placeOrderText);
                }
            });
        },

        updateStates: function(country, $stateField) {
             var self = this; // Keep reference to self
            // Check if WooCommerce country select params are available
             if (typeof wc_country_select_params === 'undefined' || !wc_country_select_params.ajax_url || !wc_country_select_params.states_nonce) {
                 console.error('WooCommerce country select params not available.');
                 $stateField.empty().append('<option value="">' + (yprint_checkout_params.i18n && yprint_checkout_params.i18n.select_state_text ? yprint_checkout_params.i18n.select_state_text : 'Select a state...') + '</option>').prop('disabled', true);
                 self.updateCheckout(); // Still attempt to update checkout even if states fail
                 return;
             }

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
                    console.log('WooCommerce get states AJAX success:', data);
                    $stateField.empty();
                    // Use localized text for select state option
                    var selectStateText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.select_state_text ? yprint_checkout_params.i18n.select_state_text : (wc_country_select_params.i18n_select_state_text || 'Select a state...');
                    $stateField.append('<option value="">' + selectStateText + '</option>');

                    if (typeof(data) === 'object' && Object.keys(data).length > 0) {
                        $.each(data, function(index, state) {
                            $stateField.append('<option value="' + index + '">' + state + '</option>');
                        });

                        $stateField.prop('disabled', false);
                    } else {
                        $stateField.prop('disabled', true);
                    }
                     // Always update checkout after states are loaded/updated
                     self.updateCheckout();
                },
                 error: function(xhr, status, error) {
                     console.error('WooCommerce get states AJAX error:', xhr.status, status, error);
                     $stateField.empty().append('<option value="">' + (yprint_checkout_params.i18n && yprint_checkout_params.i18n.select_state_text ? yprint_checkout_params.i18n.select_state_text : 'Select a state...') + '</option>').prop('disabled', true);
                      self.updateCheckout(); // Still attempt to update checkout even if states fail
                 }
            });
        },

        updateCheckout: function() {
            var self = this;

            // Check if AJAX URL and nonce are available
             if (!yprint_checkout_params.ajax_url || !yprint_checkout_params.nonce) {
                 console.error('AJAX URL or nonce missing for updateCheckout.');
                 return;
             }

            // Collect form data (this will include the selected address radio buttons)
            var formData = this.$form.serialize();

            // Add action and nonce to data
            var postData = {
                 action: 'yprint_update_checkout',
                 nonce: yprint_checkout_params.nonce,
                 checkout_data: formData // Send serialized form data
            };

            // Make AJAX request to update checkout
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: postData,
                success: function(response) {
                    console.log('updateCheckout AJAX success:', response);
                    // Robust check for response structure
                    if (response && response.success && response.data && response.data.totals && response.data.shipping_methods) {
                        var data = response.data;

                        // Update totals displayed on the page
                        self.updateDisplayedTotals(data.totals);

                        // Update shipping methods displayed on the page
                        self.updateShippingMethods(data.shipping_methods);

                        // Update payment request button if available
                        if (paymentRequest) {
                             // Ensure data.totals.total is a number before passing
                             var totalAmount = parseFloat(data.totals.total);
                             if (!isNaN(totalAmount)) {
                                 self.updatePaymentRequest(totalAmount);
                             } else {
                                 console.warn('Invalid total amount received for Payment Request update:', data.totals.total);
                                 self.updatePaymentRequest(0); // Update with 0 if invalid
                             }
                        }
                    } else {
                         console.error('updateCheckout AJAX failed: Invalid response structure or success is false.');
                         console.error('Received response:', response);
                         // Optionally show an error message for the user
                         self.showError(yprint_checkout_params.i18n.checkout_error || 'Error updating checkout details.');
                    }
                },
                 error: function(xhr, status, error) {
                     console.error('updateCheckout AJAX error:', xhr.status, status, error);
                     console.error('XHR Response:', xhr.responseText);
                      // Show a generic error message
                     self.showError(yprint_checkout_params.i18n.checkout_error || 'Error updating checkout details.');
                 }
            });
        },

        updateDisplayedTotals: function(totals) {
            // Ensure totals object exists before updating
            if (totals) {
                 // Use the formatted values from the response
                 $('.order-total-row.subtotal .value').html(totals.subtotal_formatted || '');
                 $('.order-total-row.shipping .value').html(totals.shipping_formatted || '');
                 $('.order-total-row.tax .value').html(totals.tax_formatted || '');
                 $('.order-total-row.total .value').html(totals.total_formatted || '');
            } else {
                 console.warn('Totals object is missing, cannot update displayed totals.');
            }
        },

        updateShippingMethods: function(shippingMethods) {
            var $shippingMethodsList = $('.shipping-methods-list');

            // Clear current shipping methods
            $shippingMethodsList.empty();

            // Check if we have shipping methods
            if (!shippingMethods || shippingMethods.length === 0) {
                var noShippingText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.no_shipping_methods ? yprint_checkout_params.i18n.no_shipping_methods : 'No shipping methods available';
                $shippingMethodsList.append('<p class="no-shipping-methods">' + noShippingText + '</p>');
                return;
            }

            // Get currently selected method from the form data (if any)
            // This is important if updateCheckout is triggered by something other than shipping method change
            var selectedMethod = $('input[name="shipping_method"]:checked').val();
            console.log('Currently selected shipping method:', selectedMethod);


            // Add shipping methods
            var firstAvailableMethodId = null;
            $.each(shippingMethods, function(index, method) {
                 if (method.enabled) { // Only show enabled methods
                     if (firstAvailableMethodId === null) {
                         firstAvailableMethodId = method.id;
                     }
                     // Determine if this method should be selected
                     // Select if it was previously selected OR if it's the first enabled method and nothing was selected
                     var isSelected = selectedMethod === method.id || (selectedMethod === undefined && index === 0);


                     $shippingMethodsList.append(
                         '<div class="shipping-method">' +
                             '<label class="radio-container">' +
                                 '<input type="radio" name="shipping_method" id="shipping_method_' + method.id.replace(/[^a-zA-Z0-9]/g, '_') + // Sanitize ID for HTML
                                 '" value="' + method.id + '"' + (isSelected ? ' checked="checked"' : '') + ' class="shipping-method-input">' +
                                 '<span class="radio-checkmark"></span>' +
                                 '<span class="shipping-method-label">' + (method.label || 'Shipping Method') + ' - ' + (method.cost_formatted || method.cost || 'N/A') + '</span>' +
                             '</label>' +
                         '</div>'
                     );
                 }
            });

             // After adding all methods, check if any method is checked.
             // If not, and there are available methods, check the first one.
             var newlySelectedMethod = $('input[name="shipping_method"]:checked').val();
             if (!newlySelectedMethod && firstAvailableMethodId !== null) {
                 console.log('No shipping method selected after update, selecting the first available:', firstAvailableMethodId);
                 $('input[name="shipping_method"][value="' + firstAvailableMethodId + '"]').prop('checked', true);
                 // Trigger update checkout to ensure session is updated with the newly selected method
                 self.updateCheckout();
             } else if (selectedMethod && selectedMethod !== newlySelectedMethod) {
                  console.log('Previously selected method is no longer available or changed. New selection:', newlySelectedMethod);
                  // Trigger update checkout if the selection changed (e.g., previously selected method disappeared)
                   self.updateCheckout(); // This will trigger another update, but ensures the session is correct
             }
        },

        updatePaymentRequest: function(total) {
            if (!paymentRequest) {
                return;
            }

            // Update payment request total
            // Ensure total is a number before rounding
            var totalAmount = parseFloat(total);
            if (!isNaN(totalAmount)) {
                 paymentRequest.update({
                     total: {
                         label: yprint_checkout_params.total_label || 'Order Total',
                         amount: Math.round(totalAmount * 100), // Amount in cents
                         pending: false
                     }
                 });
            } else {
                 console.warn('Invalid total amount provided for Payment Request update:', total);
                 // Optionally update with 0 or keep pending if total is invalid
                 paymentRequest.update({
                      total: {
                          label: yprint_checkout_params.total_label || 'Order Total',
                          amount: 0,
                          pending: true // Mark as pending if total is invalid
                      }
                 });
            }
        },

        placeOrder: function() {
            var self = this;

            // Prevent multiple clicks
            if (this.$placeOrderButton.prop('disabled')) {
                return;
            }

            // Disable the place order button and show processing text
            var processingText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.processing ? yprint_checkout_params.i18n.processing : 'Processing...';
            this.$placeOrderButton.prop('disabled', true).text(processingText);

            // Clear previous messages
            this.$messages.empty();

            // Basic form validation before proceeding
            if (!self.validateFormFields()) {
                // Re-enable the place order button
                var placeOrderText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.place_order ? yprint_checkout_params.i18n.place_order : 'Verbindlich Bestellen';
                self.$placeOrderButton.prop('disabled', false).text(placeOrderText);
                return; // Stop if validation fails
            }


            // Check if we already have a payment method ID (from Payment Request Button)
            var paymentMethodId = $('#payment_method_id').val();

            if (paymentMethodId) {
                console.log('Payment method ID already available from Payment Request Button.');
                // If Payment Method ID is already set (from PRB), proceed to process checkout directly.
                // The handlePaymentMethodReceived function should have already been triggered by the PRB flow.
                // We just need to ensure the form data includes the necessary details.
                 self.processCheckout();

            } else {
                console.log('No payment method ID available. Creating Payment Method from Card Element.');
                // We need to create a payment method from card element

                // Validate billing address fields before creating payment method
                // This is already covered by the initial validateFormFields call,
                // but specifically highlight billing fields for Stripe if needed.
                // For now, rely on the general validation.


                stripe.createPaymentMethod({
                    type: 'card',
                    card: cardElement,
                    billing_details: {
                        // Ensure fields are not empty before sending to Stripe
                        name: ($('#billing_first_name').val() + ' ' + $('#billing_last_name').val()).trim() || undefined,
                        email: $('#billing_email').val() || undefined, // Send undefined if empty
                        phone: $('#billing_phone').val() || undefined, // Send undefined if empty
                        address: {
                            line1: $('#billing_address_1').val() || undefined,
                            line2: $('#billing_address_2').val() || undefined,
                            city: $('#billing_city').val() || undefined,
                            state: $('#billing_state').val() || undefined,
                            postal_code: $('#billing_postcode').val() || undefined,
                            country: $('#billing_country').val() || undefined
                        }
                    }
                }).then(function(result) {
                    if (result.error) {
                        console.error('Stripe createPaymentMethod error:', result.error);
                        // Show error
                        self.showError(result.error.message);

                        // Re-enable the place order button
                        var placeOrderText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.place_order ? yprint_checkout_params.i18n.place_order : 'Verbindlich Bestellen';
                        self.$placeOrderButton.prop('disabled', false).text(placeOrderText);
                    } else {
                        console.log('Stripe createPaymentMethod success:', result.paymentMethod);
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

            // Check if AJAX URL and nonce are available
             if (!yprint_checkout_params.ajax_url || !yprint_checkout_params.nonce) {
                 console.error('AJAX URL or nonce missing for processCheckout.');
                 self.showError(yprint_checkout_params.i18n.checkout_error || 'Error processing checkout.');
                 var placeOrderText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.place_order ? yprint_checkout_params.i18n.place_order : 'Verbindlich Bestellen';
                 self.$placeOrderButton.prop('disabled', false).text(placeOrderText);
                 return;
             }

            // Collect form data (this will include the selected address radio buttons and potentially new address fields)
            var formData = this.$form.serialize();

            // Add action and nonce to data
            var postData = {
                action: 'yprint_process_checkout',
                nonce: yprint_checkout_params.nonce,
                checkout_data: formData // Send serialized form data
            };

            // Show processing message (already done in placeOrder, but good to be sure)
             self.showProcessingMessage();


            // Make AJAX request to process checkout
            $.ajax({
                type: 'POST',
                url: yprint_checkout_params.ajax_url,
                data: postData,
                success: function(response) {
                    console.log('processCheckout AJAX success:', response);
                    if (response && response.success && response.data && response.data.redirect) {
                        // Redirect to thank you page
                        window.location.href = response.data.redirect;
                    } else {
                        console.error('processCheckout AJAX failed: Invalid response structure or success is false.');
                        console.error('Received response:', response);
                        // Show error message
                        var errorMessage = (response && response.data && response.data.message)
                            ? response.data.message
                            : (yprint_checkout_params.i18n.checkout_error || 'Error processing checkout.');
                        self.showError(errorMessage);

                        // Re-enable the place order button
                        var placeOrderText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.place_order ? yprint_checkout_params.i18n.place_order : 'Verbindlich Bestellen';
                        self.$placeOrderButton.prop('disabled', false).text(placeOrderText);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('processCheckout AJAX error:', xhr.status, status, error);
                    console.error('XHR Response:', xhr.responseText);
                    // Show error message
                    var errorMessage = (xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message)
                        ? xhr.responseJSON.data.message
                        : (yprint_checkout_params.i18n.checkout_error || 'Error processing checkout.');

                    self.showError(errorMessage);

                    // Re-enable the place order button
                    var placeOrderText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.place_order ? yprint_checkout_params.i18n.place_order : 'Verbindlich Bestellen';
                    self.$placeOrderButton.prop('disabled', false).text(placeOrderText);
                }
            });
        },

        // Function to show/hide address sections based on user selection
        updateAddressSectionsDisplay: function() {
            var self = this;

            // Shipping Address Display
            if ($('input[name="yprint_shipping_address_selection"]:checked').val() === 'new_address') {
                self.$shippingAddressNewFields.slideDown();
                // Make fields required for new address input
                self.$shippingAddressNewFields.find('input, select').prop('required', true);
                 // Ensure required fields for new shipping address have the '*' indicator
                 self.$shippingAddressNewFields.find('.required').show();

            } else {
                self.$shippingAddressNewFields.slideUp();
                // Remove required attribute for saved address selection
                self.$shippingAddressNewFields.find('input, select').prop('required', false);
                 // Hide required indicators for new shipping address fields
                 self.$shippingAddressNewFields.find('.required').hide();
            }

            // Billing Address Display
            if ($('#ship_to_different_address').is(':checked')) {
                self.$billingAddressSameAsShipping.slideUp();
                self.$billingSavedAddressesSection.slideDown();

                // Check if "Enter a new address" is selected for billing
                if ($('input[name="yprint_billing_address_selection"]:checked').val() === 'new_address') {
                     self.$billingNewFields.slideDown();
                     // Make fields required for new billing address input
                     self.$billingNewFields.find('input, select').prop('required', true);
                     // Ensure required fields for new billing address have the '*' indicator
                     self.$billingNewFields.find('.required').show();
                } else {
                     self.$billingNewFields.slideUp();
                     // Remove required attribute for saved billing address selection
                     self.$billingNewFields.find('input, select').prop('required', false);
                      // Hide required indicators for new billing address fields
                     self.$billingNewFields.find('.required').hide();
                }

            } else {
                self.$billingAddressSameAsShipping.slideDown();
                self.$billingSavedAddressesSection.slideUp();
                self.$billingNewFields.slideUp(); // Hide new fields if not separate billing
                // Remove required attribute for billing fields when same as shipping
                self.$billingNewFields.find('input, select').prop('required', false);
                 // Hide required indicators for billing fields when same as shipping
                 self.$billingNewFields.find('.required').hide();
            }

             // Note: The actual data population for saved addresses when selected
             // needs to happen in your backend logic (e.g., in yprint_checkout_load_user_data)
             // or via a separate AJAX call triggered by the radio button change if you prefer
             // a more dynamic client-side update. The current JS only controls visibility.
        },


        // Basic form validation - updated to consider visible fields
        validateFormFields: function() {
             var self = this;
             var isValid = true;
             var messages = [];

             // Clear previous errors
             this.$messages.empty();
             $('.yprint-checkout-invalid').removeClass('yprint-checkout-invalid'); // Clear previous invalid highlights

             // Validate visible required fields
             this.$form.find('input:visible, select:visible, textarea:visible').each(function() {
                 var $field = $(this);

                 // Check if the field is required and empty
                 if ($field.prop('required') && $field.val().trim() === '') {
                     isValid = false;
                     var fieldLabel = $field.closest('.form-field').find('label').text().replace('*', '').trim(); // Get label text
                     messages.push((yprint_checkout_params.i18n && yprint_checkout_params.i18n.required ? yprint_checkout_params.i18n.required : 'This field is required.').replace('%s', fieldLabel));
                     $field.addClass('yprint-checkout-invalid'); // Add a class for styling
                 }
             });

             // Validate email format for the visible email field
             var $emailField = $('#billing_email'); // Assuming billing email is always required/visible in one form
             if ($emailField.is(':visible') && $emailField.val().trim() !== '' && !self.isValidEmail($emailField.val().trim())) {
                 isValid = false;
                 messages.push(yprint_checkout_params.i18n && yprint_checkout_params.i18n.invalid_email ? yprint_checkout_params.i18n.invalid_email : 'Please enter a valid email address.');
                 $emailField.addClass('yprint-checkout-invalid');
             }


             if (!isValid) {
                 self.showError(messages.join('<br>'));
             }

             return isValid;
        },

         // Helper function for email validation
         isValidEmail: function(email) {
             var pattern = new RegExp(/^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/);
             return pattern.test(email);
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
            var processingPaymentText = yprint_checkout_params.i18n && yprint_checkout_params.i18n.payment_processing ? yprint_checkout_params.i18n.payment_processing : 'Processing payment. Please wait...';
            this.$messages.html('<div class="yprint-checkout-processing">' + processingPaymentText + '</div>');
             // Scroll to messages
            $('html, body').animate({
                scrollTop: this.$messages.offset().top - 100
            }, 500);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        YPrintCheckout.init();
    });

    // Make YPrintCheckout globally accessible for debugging if needed
    window.YPrintCheckout = YPrintCheckout;

})(jQuery);
