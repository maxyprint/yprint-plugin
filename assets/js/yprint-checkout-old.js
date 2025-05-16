/**
 * YPrint Checkout Client-Side JavaScript
 * Handles form interactions, AJAX updates, and Stripe integration.
 */

jQuery(document).ready(function($) {

    // Ensure yprint_checkout_params is defined and contains necessary data
    if (typeof yprint_checkout_params === 'undefined') {
        console.error('YPrint Checkout: yprint_checkout_params is not defined. Check wp_localize_script.');
        return; // Stop script execution if params are missing
    }

    const params = yprint_checkout_params;
    const $form = $('#yprint-checkout-form');
    const $checkoutContainer = $('.yprint-checkout-container'); // Main container
    const $messages = $('.yprint-checkout-messages'); // Container for messages
    const $orderSummary = $('.yprint-order-summary'); // Order summary container
    const $orderTotals = $orderSummary.find('.order-totals'); // Totals section
    const $shippingMethodsContainer = $form.find('.shipping-methods-container .shipping-methods-list'); // Shipping methods list
    const $placeOrderButton = $('#yprint-place-order'); // Place order button
    const $shippingAddressNewFields = $form.find('.shipping-address-new-fields'); // New shipping fields section
    const $billingAddressSection = $form.find('.billing-address-section'); // Billing address section
    const $billingAddressSameAsShipping = $billingAddressSection.find('.billing-address-same-as-shipping'); // "Same as shipping" message
    const $savedBillingAddressesSection = $billingAddressSection.find('.yprint-saved-billing-addresses-section'); // Saved billing addresses section
    const $billingAddressNewFields = $billingAddressSection.find('.billing-address-new-fields'); // New billing fields section
    const $shipToDifferentAddressCheckbox = $('#ship_to_different_address'); // Separate billing checkbox
    const $savedShippingAddressesList = $form.find('.yprint-saved-shipping-addresses-section .saved-addresses-list'); // Saved shipping addresses list
    const $savedBillingAddressesList = $billingAddressSection.find('.yprint-saved-billing-addresses-section .saved-addresses-list'); // Saved billing addresses list


    // --- Stripe Integration ---
    let stripe = null;
    let cardElement = null; // Stripe Card Element
    let paymentRequest = null; // Stripe Payment Request object
    let paymentRequestButton = null; // Stripe Payment Request Button element

    // Initialize Stripe if key is provided
    if (params.stripe && params.stripe.key) {
        try {
             stripe = Stripe(params.stripe.key, {
                 locale: params.stripe.locale // Set Stripe locale
             });
             console.log('Stripe initialized.');

             // Setup Card Element
             const elements = stripe.elements();
             cardElement = elements.create('card', {
                 style: { // Basic styling for card element
                     base: {
                         fontSize: '16px',
                         color: '#32325d',
                         '::placeholder': {
                             color: '#aab7c4',
                         },
                     },
                     invalid: {
                         color: '#fa755a',
                         iconColor: '#fa755a',
                     },
                 },
             });
             const $cardElementContainer = $('#yprint-stripe-card-element');
             if ($cardElementContainer.length) {
                 cardElement.mount($cardElementContainer[0]);
                 console.log('Stripe Card Element mounted.');

                 // Listen for errors on the card element
                 cardElement.on('change', function(event) {
                     const displayError = $('#yprint-stripe-card-errors');
                     if (event.error) {
                         displayError.text(event.error.message);
                     } else {
                         displayError.text('');
                     }
                 });
             } else {
                 console.warn('Stripe Card Element container #yprint-stripe-card-element not found.');
             }

             // Setup Payment Request Button (Apple Pay, Google Pay etc.)
             if (stripe && params.shipping_required && params.stripe.apple_pay_enabled) { // Only show PRB if shipping is required and Apple Pay is enabled (adjust logic as needed for other methods)
                  setupPaymentRequest();
             }


        } catch (e) {
             console.error('Error initializing Stripe:', e);
             displayMessage(params.i18n.checkout_error, 'error'); // Show a user-friendly error
        }
    } else {
         console.warn('Stripe publishable key not provided. Stripe payment methods may not work.');
         // Hide Stripe related fields or show a message
         $('.credit-card-section').hide();
         $('#yprint-stripe-payment-request-wrapper').hide();
         $('.payment-method-separator').hide();
    }


    // --- Event Handlers ---

    // Handle form submission for placing order
    $form.on('submit', function(e) {
        e.preventDefault(); // Prevent default form submission
        processCheckout(); // Call our checkout processing function
    });

    // Handle changes in shipping address selection (saved vs new)
    $savedShippingAddressesList.on('change', 'input[name="yprint_shipping_address_selection"]', function() {
        const selectedValue = $(this).val();
        if (selectedValue === 'new_address') {
            $shippingAddressNewFields.slideDown(); // Show new fields
        } else {
            $shippingAddressNewFields.slideUp(); // Hide new fields
        }
        // Trigger checkout update to recalculate shipping based on address change
        updateCheckout();
    });

    // Handle changes in billing address selection (saved vs new)
    $savedBillingAddressesList.on('change', 'input[name="yprint_billing_address_selection"]', function() {
        const selectedValue = $(this).val();
        if (selectedValue === 'new_address') {
            $billingAddressNewFields.slideDown(); // Show new fields
        } else {
            $billingAddressNewFields.slideUp(); // Hide new fields
        }
        // Trigger checkout update (billing address change might affect taxes)
        updateCheckout();
    });


    // Handle change in "Ship to different address" checkbox
    $shipToDifferentAddressCheckbox.on('change', function() {
        if ($(this).is(':checked')) {
            // Separate billing address is used
            $billingAddressSameAsShipping.slideUp(); // Hide "same as shipping" message
            if (params.is_user_logged_in && $savedBillingAddressesList.find('.saved-address-option').length > 0) {
                 // Show saved billing addresses if user is logged in and has saved addresses
                 $savedBillingAddressesSection.slideDown();
                 // Check the selected billing address option (saved or new)
                 const selectedBillingOption = $savedBillingAddressesList.find('input[name="yprint_billing_address_selection"]:checked').val();
                 if (selectedBillingOption === 'new_address' || $savedBillingAddressesList.find('input[name="yprint_billing_address_selection"]').length === 0) {
                      // Show new billing fields if 'new address' is selected or no saved addresses exist
                      $billingAddressNewFields.slideDown();
                 } else {
                      $billingAddressNewFields.slideUp();
                 }
            } else {
                 // No saved billing addresses or guest user, just show new billing fields
                 $savedBillingAddressesSection.slideUp();
                 $billingAddressNewFields.slideDown();
            }
        } else {
            // Billing address is same as shipping
            $billingAddressSameAsShipping.slideDown(); // Show "same as shipping" message
            $savedBillingAddressesSection.slideUp(); // Hide saved billing addresses
            $billingAddressNewFields.slideUp(); // Hide new billing fields
        }
        // Trigger checkout update (billing address change might affect taxes)
        updateCheckout();
    });


    // Handle changes in shipping method selection
    $shippingMethodsContainer.on('change', 'input[name="shipping_method"]', function() {
        // Trigger checkout update to recalculate totals based on shipping method change
        updateCheckout();
    });

    // Trigger checkout update when certain address fields change (for new address)
    // This is important for real-time shipping/tax calculation as user types
    $shippingAddressNewFields.find('input, select').on('change keyup', function() {
        // Debounce this event to avoid excessive AJAX calls
        debounceUpdateCheckout();
    });
     $billingAddressNewFields.find('input, select').on('change keyup', function() {
         // Debounce this event
         debounceUpdateCheckout();
     });


    // --- Initial Setup ---

    // Initial state of billing address section based on checkbox
    $shipToDifferentAddressCheckbox.trigger('change'); // Trigger change to set initial visibility

    // Trigger initial checkout update to calculate shipping/taxes for the default address
    updateCheckout();


    // --- Functions ---

    /**
     * Displays a message to the user.
     * @param {string} message The message to display.
     * @param {string} type The message type ('success', 'error', 'info', 'processing').
     */
    function displayMessage(message, type = 'info') {
        const messageClass = `yprint-checkout-${type}`;
        const html = `<div class="${messageClass}">${message}</div>`;
        $messages.html(html); // Replace existing messages
        $messages[0].scrollIntoView({ behavior: 'smooth' }); // Scroll message into view
    }

    /**
     * Clears all displayed messages.
     */
    function clearMessages() {
        $messages.empty();
    }

    /**
     * Shows a processing indicator and disables the place order button.
     * @param {string} message Optional processing message.
     */
    function showProcessing(message = params.i18n.processing) {
        displayMessage(message, 'processing');
        $placeOrderButton.prop('disabled', true).text(params.i18n.processing);
    }

    /**
     * Hides the processing indicator and enables the place order button.
     */
    function hideProcessing() {
        clearMessages();
        $placeOrderButton.prop('disabled', false).text(params.i18n.place_order);
    }

    /**
     * Updates the order summary totals and shipping methods via AJAX.
     */
    let updateCheckoutTimer;
    function debounceUpdateCheckout() {
        clearTimeout(updateCheckoutTimer);
        updateCheckoutTimer = setTimeout(updateCheckout, 500); // Wait 500ms after last input
    }

    function updateCheckout() {
        showProcessing(params.i18n.processing); // Show processing message

        const formData = $form.serialize(); // Get all form data

        $.ajax({
            type: 'POST',
            url: params.ajax_url,
            data: {
                action: 'yprint_update_checkout', // The AJAX action defined in PHP
                nonce: params.nonce, // Security nonce
                checkout_data: formData // Send all form data
            },
            dataType: 'json',
            success: function(response) {
                hideProcessing(); // Hide processing message

                if (response.success) {
                    console.log('Checkout updated successfully:', response.data);
                    updateOrderSummary(response.data.totals);
                    updateShippingMethods(response.data.shipping_methods);
                    // If you implemented cart item HTML updates, call that function here
                    // updateCartItems(response.data.cart_items_html);

                } else {
                    console.error('Error updating checkout:', response.data.message);
                    displayMessage(response.data.message || params.i18n.checkout_error, 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                hideProcessing(); // Hide processing message
                console.error('AJAX Error updating checkout:', textStatus, errorThrown, jqXHR.responseText);
                displayMessage(params.i18n.checkout_error, 'error');
            }
        });
    }

    /**
     * Updates the order summary display with new totals.
     * @param {object} totals Object containing updated totals.
     */
    function updateOrderSummary(totals) {
        if (!totals) return;

        // Find and update the elements displaying totals
        $orderTotals.find('.order-total-row.subtotal .value').html(totals.subtotal);
        $orderTotals.find('.order-total-row.shipping .value').html(totals.shipping);
        $orderTotals.find('.order-total-row.tax .value').html(totals.tax);
        $orderTotals.find('.order-total-row.total .value').html(totals.total_formatted); // Use formatted total for display

        // Store raw total for Stripe (if needed elsewhere)
        $form.data('order_total', totals.total);

        console.log('Order summary updated.');
    }

    /**
     * Updates the list of available shipping methods.
     * @param {array} shippingMethods Array of available shipping methods.
     */
    function updateShippingMethods(shippingMethods) {
        $shippingMethodsContainer.empty(); // Clear current list

        if (shippingMethods && shippingMethods.length > 0) {
            let firstMethodId = null;
            shippingMethods.forEach(function(method, index) {
                const isChecked = index === 0; // Select the first method by default
                if (isChecked) {
                     firstMethodId = method.id;
                }
                const html = `
                    <div class="shipping-method">
                        <label class="radio-container">
                            <input type="radio" name="shipping_method" id="shipping_method_${method.id}" value="${method.id}" class="shipping-method-input" ${isChecked ? 'checked' : ''}>
                            <span class="radio-checkmark"></span>
                            <span class="shipping-method-label">${method.label} - ${method.cost_formatted}</span>
                        </label>
                    </div>
                `;
                $shippingMethodsContainer.append(html);
            });

            // Trigger change on the newly selected method to ensure WC session is updated
            if (firstMethodId) {
                 $shippingMethodsContainer.find(`input[value="${firstMethodId}"]`).trigger('change');
            }

        } else {
            // No shipping methods available
            const noMethodsHtml = `<p class="no-shipping-methods">${params.i18n.no_shipping_methods}</p>`;
            $shippingMethodsContainer.html(noMethodsHtml);
            // Potentially disable shipping-dependent place order button or show error
        }
        console.log('Shipping methods updated.');
    }

    /**
     * Processes the checkout form submission and initiates the order placement.
     */
    function processCheckout() {
        showProcessing(params.i18n.payment_processing); // Show payment processing message
        clearMessages(); // Clear previous messages

        const formData = $form.serializeArray(); // Get form data as array

        // Add Stripe payment method ID to form data if available
        let paymentMethodId = '';
        if (cardElement) {
             // Create a PaymentMethod from the card element
             stripe.createPaymentMethod({
                 type: 'card',
                 card: cardElement,
                 billing_details: { // Include billing details if available
                     name: `${$('#billing_first_name').val()} ${$('#billing_last_name').val()}`,
                     email: $('#billing_email').val(),
                     phone: $('#billing_phone').val(),
                     address: {
                         line1: $('#billing_address_1').val(),
                         line2: $('#billing_address_2').val(),
                         city: $('#billing_city').val(),
                         state: $('#billing_state').val(),
                         postal_code: $('#billing_postcode').val(),
                         country: $('#billing_country').val(),
                     },
                 },
             }).then(function(result) {
                 if (result.error) {
                     // Show error to the user
                     hideProcessing();
                     displayMessage(result.error.message, 'error');
                     console.error('Stripe createPaymentMethod Error:', result.error);
                 } else {
                     // PaymentMethod created successfully, add ID to form data
                     paymentMethodId = result.paymentMethod.id;
                     formData.push({ name: 'payment_method_id', value: paymentMethodId });
                     formData.push({ name: 'payment_method', value: 'yprint_stripe' }); // Assuming Stripe gateway ID is 'yprint_stripe'

                     // Proceed with AJAX request to process order
                     sendProcessCheckoutRequest($.param(formData)); // Serialize array back to string
                 }
             });
        } else {
             // If not using Stripe card element, send other payment method data
             // Ensure a payment method is selected in the form
             const selectedPaymentMethod = $('input[name="payment_method"]:checked').val();
             if (!selectedPaymentMethod) {
                  hideProcessing();
                  displayMessage(__('Please select a payment method.', 'yprint-plugin'), 'error');
                  return;
             }
             formData.push({ name: 'payment_method', value: selectedPaymentMethod });

             // Proceed with AJAX request
             sendProcessCheckoutRequest($.param(formData)); // Serialize array back to string
        }
    }

    /**
     * Sends the AJAX request to the backend to process the order.
     * @param {string} serializedFormData Serialized form data string.
     */
    function sendProcessCheckoutRequest(serializedFormData) {
         $.ajax({
             type: 'POST',
             url: params.ajax_url,
             data: {
                 action: 'yprint_process_checkout', // The AJAX action defined in PHP
                 nonce: params.nonce, // Security nonce
                 checkout_data: serializedFormData // Send all form data
             },
             dataType: 'json',
             success: function(response) {
                 hideProcessing(); // Hide processing message

                 if (response.success) {
                     console.log('Checkout process successful:', response.data);
                     if (response.data.redirect) {
                         // Redirect the user to the order received page or authentication page
                         window.location.href = response.data.redirect;
                     } else {
                         // Unexpected success response without redirect
                         displayMessage(params.i18n.checkout_error, 'error');
                         console.error('Checkout process successful but no redirect URL provided.');
                     }
                 } else {
                     console.error('Error processing checkout:', response.data.message);
                     displayMessage(response.data.message || params.i18n.checkout_error, 'error');
                 }
             },
             error: function(jqXHR, textStatus, errorThrown) {
                 hideProcessing(); // Hide processing message
                 console.error('AJAX Error processing checkout:', textStatus, errorThrown, jqXHR.responseText);
                 displayMessage(params.i18n.checkout_error, 'error');
             }
         });
    }


    /**
     * Sets up the Stripe Payment Request Button.
     */
    function setupPaymentRequest() {
         if (!stripe) {
             console.warn('Stripe not initialized, cannot setup Payment Request.');
             return;
         }

         // Get current cart total from the form data (set by updateCheckout)
         // Ensure updateCheckout has run at least once before setting up PRB
         const currentTotal = parseFloat($form.data('order_total')); // Get raw total as float

         if (isNaN(currentTotal) || currentTotal <= 0) {
             console.warn('Invalid or zero total, cannot setup Payment Request.');
             // Hide the PRB wrapper if total is invalid
             $('#yprint-stripe-payment-request-wrapper').hide();
             return;
         }

         const options = {
             country: params.country_code, // Use base country code initially
             currency: params.currency.toLowerCase(), // Stripe requires lowercase currency
             total: {
                 label: params.total_label, // Label for the total (e.g., "My Store")
                 amount: Math.round(currentTotal * 100), // Amount in cents (integer)
             },
             requestPayerName: true,
             requestPayerEmail: true,
             requestPayerPhone: true,
             requestShipping: params.shipping_required, // Request shipping address if cart needs it
         };

         paymentRequest = stripe.paymentRequest(options);

         const $paymentRequestButtonContainer = $('#yprint-stripe-payment-request-button');
         if ($paymentRequestButtonContainer.length === 0) {
              console.warn('Payment Request Button container #yprint-stripe-payment-request-button not found.');
              return;
         }

         // Check the availability of the Payment Request API first.
         paymentRequest.canMakePayment().then(function(result) {
             if (result) {
                 // Create the Payment Request Button element
                 paymentRequestButton = elements.create('paymentRequestButton', {
                     paymentRequest: paymentRequest,
                     style: {
                         paymentRequestButton: {
                             type: params.button.type, // 'default', 'buy', 'donate'
                             theme: params.button.theme, // 'dark', 'light', 'light-outline'
                             height: params.button.height + 'px', // Button height
                         },
                     },
                 });

                 // Mount the button to the DOM
                 paymentRequestButton.mount($paymentRequestButtonContainer[0]);
                 console.log('Stripe Payment Request Button mounted.');

                 // Show the PRB wrapper
                 $('#yprint-stripe-payment-request-wrapper').show();
                 // Hide the standard credit card form initially if PRB is available
                 $('.credit-card-section').hide();
                 $('.payment-method-separator').hide();


                 // --- Payment Request Event Listeners ---

                 // Handle shipping address change in Payment Request interface
                 if (params.shipping_required) {
                      paymentRequest.on('shippingaddresschange', function(event) {
                          const address = event.shippingAddress;
                          console.log('Payment Request shipping address changed:', address);

                          // Update customer location in WC session via AJAX
                          // This is similar to updateCheckout but only for the address part
                          // We need to send the address data to the backend to get updated shipping options and totals
                          const addressData = {
                              country: address.country,
                              state: address.region, // Stripe uses 'region' for state
                              postcode: address.postalCode,
                              city: address.city,
                              address_1: address.addressLine && address.addressLine.length > 0 ? address.addressLine[0] : '',
                              address_2: address.addressLine && address.addressLine.length > 1 ? address.addressLine[1] : '',
                              // Include name, company etc if needed by your shipping calculation
                              first_name: address.recipient, // Stripe sometimes puts full name in recipient
                              // Attempt to split name if possible, or add separate fields in PRB options
                          };

                          // Send this address data to a backend endpoint to get updated shipping options and totals
                          // This requires a new AJAX handler or modifying yprint_update_checkout
                          // For simplicity here, we'll simulate an update and respond to Stripe
                          // A real implementation needs a backend call here.

                          // --- SIMULATED BACKEND CALL (REPLACE WITH ACTUAL AJAX) ---
                          // In a real scenario, you'd make an AJAX call here with addressData
                          // and the backend would calculate shipping methods and updated totals.
                          // The backend would return an array of shipping options and the new total.
                          // Example response structure:
                          // {
                          //     shippingOptions: [{ id: 'flat_rate:1', label: 'Flat Rate', amount: 595 }, ...],
                          //     newTotal: { label: 'Total', amount: 8330 } // Amount in cents
                          // }
                          const simulatedShippingOptions = [
                              { id: 'flat_rate:1', label: 'Standard Shipping', amount: 595 }, // Example cost in cents
                              { id: 'express_shipping:2', label: 'Express Shipping', amount: 1250 }, // Example cost in cents
                          ];
                           const simulatedNewTotal = { label: params.total_label, amount: Math.round((currentTotal + 5.95) * 100) }; // Example: add flat rate cost

                          // Respond to Stripe with updated shipping options and total
                          event.updateWith({
                              status: 'success', // 'success' or 'fail'
                              shippingOptions: simulatedShippingOptions,
                              total: simulatedNewTotal,
                          });
                          // --- END SIMULATED BACKEND CALL ---

                      });
                 }


                 // Handle Payment Request token creation
                 paymentRequest.on('paymentmethod', function(event) {
                     console.log('Payment Request paymentmethod event:', event);

                     // Send the PaymentMethod ID and other checkout data to your backend
                     const formData = $form.serializeArray();
                     formData.push({ name: 'payment_method_id', value: event.paymentMethod.id });
                     formData.push({ name: 'payment_method', value: 'yprint_stripe' }); // Assuming Stripe gateway ID
                     formData.push({ name: 'payment_request_type', value: event.methodName }); // e.g., 'apple_pay', 'google_pay'

                     // Include shipping details from the Payment Request event if available
                     if (event.shippingAddress) {
                          formData.push({ name: 'shipping_first_name', value: event.shippingAddress.recipient }); // Might need parsing
                          formData.push({ name: 'shipping_address_1', value: event.shippingAddress.addressLine && event.shippingAddress.addressLine.length > 0 ? event.shippingAddress.addressLine[0] : '' });
                          formData.push({ name: 'shipping_address_2', value: event.shippingAddress.addressLine && event.shippingAddress.addressLine.length > 1 ? event.shippingAddress.addressLine[1] : '' });
                          formData.push({ name: 'shipping_city', value: event.shippingAddress.city });
                          formData.push({ name: 'shipping_state', value: event.shippingAddress.region });
                          formData.push({ name: 'shipping_postcode', value: event.shippingAddress.postalCode });
                          formData.push({ name: 'shipping_country', value: event.shippingAddress.country });
                     }
                      // Include billing details from the event if available (might be same as shipping)
                     if (event.payerName) formData.push({ name: 'billing_first_name', value: event.payerName }); // Might need parsing
                     if (event.payerEmail) formData.push({ name: 'billing_email', value: event.payerEmail });
                     if (event.payerPhone) formData.push({ name: 'billing_phone', value: event.payerPhone });
                     // Add other billing fields if available in event or derived


                     // Send data to backend to create order and confirm payment
                     $.ajax({
                         type: 'POST',
                         url: params.ajax_url,
                         data: {
                             action: 'yprint_process_checkout', // Use the same process checkout action
                             nonce: params.nonce,
                             checkout_data: $.param(formData) // Serialize and send
                         },
                         dataType: 'json',
                         success: function(response) {
                             if (response.success) {
                                 console.log('Payment Request checkout process successful:', response.data);
                                 // Complete the Payment Request session
                                 event.complete('success');
                                 // Redirect the user
                                 if (response.data.redirect) {
                                     window.location.href = response.data.redirect;
                                 } else {
                                      displayMessage(params.i18n.checkout_error, 'error');
                                      console.error('PRB checkout process successful but no redirect URL provided.');
                                 }
                             } else {
                                 console.error('Error processing Payment Request checkout:', response.data.message);
                                 // Complete the Payment Request session with failure
                                 event.complete('fail');
                                 displayMessage(response.data.message || params.i18n.checkout_error, 'error');
                             }
                         },
                         error: function(jqXHR, textStatus, errorThrown) {
                             console.error('AJAX Error processing Payment Request checkout:', textStatus, errorThrown, jqXHR.responseText);
                             // Complete the Payment Request session with failure
                             event.complete('fail');
                             displayMessage(params.i18n.checkout_error, 'error');
                         }
                     });
                 });

             } else {
                 console.log('Payment Request API not available or no payment methods configured.');
                 // Hide the PRB wrapper if not available
                 $('#yprint-stripe-payment-request-wrapper').hide();
                 // Show the standard credit card form
                 $('.credit-card-section').show();
                 $('.payment-method-separator').show();
             }
         }).catch(function(error) {
             console.error('Error checking Payment Request API availability:', error);
              // Hide the PRB wrapper on error
             $('#yprint-stripe-payment-request-wrapper').hide();
              // Show the standard credit card form
              $('.credit-card-section').show();
              $('.payment-method-separator').show();
         });
    }


    // --- Helper for Country/State Selects (Leverages wc-country-select) ---
    // The wc-country-select script automatically handles updating the state dropdown
    // when the country changes, using the 'country_to_state_json' localized data
    // and AJAX calls to the 'wc_get_states' action.
    // We just need to ensure our select elements have the correct classes ('country-select', 'state-select')
    // and IDs ('shipping_country', 'shipping_state', 'billing_country', 'billing_state').
    // The wc-country-select script should be enqueued as a dependency.

    // Initial setup for country/state selects
    $form.find('select.country-select').each(function() {
        const $countrySelect = $(this);
        const countryId = $countrySelect.attr('id');
        const stateId = countryId.replace('_country', '_state');
        const $stateSelect = $form.find('#' + stateId);

        if ($stateSelect.length) {
            // Trigger the WC function to load states on page load if a country is already selected
            if ($countrySelect.val()) {
                 $(document.body).trigger('country_to_state_changing', [$countrySelect.val(), $stateSelect.attr('id')]);
            }

            // The wc-country-select script handles the change event on country selects automatically.
            // We just need to ensure our updateCheckout is called after the state is updated.
            // We can listen for the 'country_to_state_changed' event triggered by WC.
            $(document.body).on('country_to_state_changed', function(event, country, $state_select) {
                 // Check if the state select that changed belongs to our form
                 if ($state_select.closest('#yprint-checkout-form').length) {
                      console.log('WC country_to_state_changed event fired for:', $state_select.attr('id'));
                      // Debounce the checkout update after state changes
                      debounceUpdateCheckout();
                 }
            });
        }
    });


    // --- Initial Address Field Visibility ---
    // Based on the initial state (e.g., if a default saved address is set)
    const initialShippingSelection = $savedShippingAddressesList.find('input[name="yprint_shipping_address_selection"]:checked').val();
    if (initialShippingSelection !== 'new_address') {
         $shippingAddressNewFields.hide(); // Hide new fields if a saved address is initially selected
    } else {
         $shippingAddressNewFields.show(); // Show new fields if 'new address' is selected or no saved addresses
    }

    const initialBillingSelection = $billingAddressSection.find('input[name="yprint_billing_address_selection"]:checked').val();
    const initialShipToDifferent = $shipToDifferentAddressCheckbox.is(':checked');

    if (!initialShipToDifferent) {
        $billingAddressSameAsShipping.show();
        $savedBillingAddressesSection.hide();
        $billingAddressNewFields.hide();
    } else {
        $billingAddressSameAsShipping.hide();
         if (params.is_user_logged_in && $savedBillingAddressesList.find('.saved-address-option').length > 0) {
              $savedBillingAddressesSection.show();
              if (initialBillingSelection !== 'new_address') {
                   $billingAddressNewFields.hide();
              } else {
                   $billingAddressNewFields.show();
              }
         } else {
              $savedBillingAddressesSection.hide();
              $billingAddressNewFields.show();
         }
    }


}); // End document ready
