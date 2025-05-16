/**
 * YPrint Checkout JavaScript
 * 
 * Handles the checkout process functionality
 */
jQuery(document).ready(function($) {
    // Cache DOM elements
    const $container = $('.yprint-checkout-container');
    
    /**
     * Initialize checkout
     */
    function initCheckout() {
        // Setup event handlers
        setupAddressSelection();
        setupAddressForm();
        setupPaymentMethods();
        setupConfirmation();
        
        // Initialize stripe if available
        if (typeof initStripeCheckout === 'function') {
            initStripeCheckout();
        }
        
        console.log('YPrint Checkout initialized');
    }
    
    /**
     * Setup address selection functionality
     */
    function setupAddressSelection() {
        // Handle address selection
        $('.yprint-select-address').on('click', function() {
            const $card = $(this).closest('.yprint-address-card');
            const addressId = $card.data('address-id');
            const addressType = $card.data('address-type') || 'custom';
            
            // Mark this card as selected
            $('.yprint-address-card').removeClass('selected');
            $card.addClass('selected');
            
            // Process address selection via AJAX
            $.ajax({
                url: yprintCheckout.ajaxurl,
                type: 'POST',
                data: {
                    action: 'yprint_save_address',
                    security: yprintCheckout.checkout_nonce,
                    address: {
                        id: addressId,
                        type: addressType
                    }
                },
                beforeSend: function() {
                    $container.addClass('yprint-loading');
                },
                success: function(response) {
                    if (response.success) {
                        // Update cart totals
                        if (response.data.cart_total) {
                            $('.yprint-cart-total span:last-child').html(response.data.cart_total);
                        }
                        
                        if (response.data.cart_subtotal) {
                            $('.yprint-cart-subtotal span:last-child').html(response.data.cart_subtotal);
                        }
                        
                        if (response.data.shipping_total) {
                            $('.yprint-cart-shipping span:last-child').html(response.data.shipping_total);
                        }
                        
                        // Highlight the continue button
                        $('.yprint-continue-button').addClass('highlight');
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(yprintCheckout.i18n.generic_error);
                },
                complete: function() {
                    $container.removeClass('yprint-loading');
                }
            });
        });
        
        // Handle "add new address" button
        $('.yprint-add-address').on('click', function() {
            $('.yprint-address-selection').hide();
            $('.yprint-address-form').show();
        });
    }
    
    /**
     * Setup address form functionality
     */
    function setupAddressForm() {
        // Handle address form submission
        $('#yprint-new-address-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = $(this).serializeArray();
            let addressData = {
                type: 'new',
                save_address: $('#save_address').is(':checked')
            };
            
            // Convert form fields to address object
            formData.forEach(function(field) {
                addressData[field.name] = field.value;
            });
            
            // Validate required fields
            let isValid = true;
            $(this).find('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('has-error');
                    isValid = false;
                } else {
                    $(this).removeClass('has-error');
                }
            });
            
            if (!isValid) {
                return;
            }
            
            // Save new address via AJAX
            $.ajax({
                url: yprintCheckout.ajaxurl,
                type: 'POST',
                data: {
                    action: 'yprint_save_address',
                    security: yprintCheckout.checkout_nonce,
                    address: addressData
                },
                beforeSend: function() {
                    $container.addClass('yprint-loading');
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show the new address
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert(yprintCheckout.i18n.generic_error);
                },
                complete: function() {
                    $container.removeClass('yprint-loading');
                }
            });
        });
        
        // Handle form cancel button
        $('.yprint-cancel-address').on('click', function() {
            $('.yprint-address-form').hide();
            $('.yprint-address-selection').show();
        });
    }
    
    /**
     * Setup payment methods functionality
     */
    function setupPaymentMethods() {
        // Handle payment method selection
        $('input[name="payment_method"]').on('change', function() {
            const paymentMethod = $(this).val();
            
            // Show/hide appropriate payment details section
            $('.yprint-payment-details-section').hide();
            $('#payment_details_' + paymentMethod).show();
            
            // If stripe is selected, show saved payment methods
            if (paymentMethod === 'stripe') {
                $('.yprint-saved-payment-methods').show();
            } else {
                $('.yprint-saved-payment-methods').hide();
            }
        });
        
        // Handle saved payment method selection
        $('input[name="saved_payment_method"]').on('change', function() {
            const savedId = $(this).val();
            
            // Show/hide card element based on selection
            if (savedId === 'new') {
                $('#yprint-stripe-card-element').show();
            } else {
                $('#yprint-stripe-card-element').hide();
            }
        });
    }
    
    /**
     * Setup confirmation step functionality
     */
    function setupConfirmation() {
        // Handle terms acceptance
        $('#terms_acceptance').on('change', function() {
            if ($(this).is(':checked')) {
                $('#yprint-place-order').prop('disabled', false);
            } else {
                $('#yprint-place-order').prop('disabled', true);
            }
        });
        
        // Handle place order button
        $('#yprint-place-order').on('click', function() {
            if (!$('#terms_acceptance').is(':checked')) {
                alert(yprintCheckout.i18n.terms_required);
                return;
            }
            
            // Process the order
            $.ajax({
                url: yprintCheckout.ajaxurl,
                type: 'POST',
                data: {
                    action: 'yprint_process_checkout',
                    security: yprintCheckout.checkout_nonce,
                    terms_accepted: true
                },
                beforeSend: function() {
                    $container.addClass('yprint-loading');
                    $('#yprint-place-order').prop('disabled', true).html(
                        '<span class="yprint-loader"></span>' + yprintCheckout.i18n.processing
                    );
                },
                success: function(response) {
                    if (response.success && response.data.redirect) {
                        window.location.href = response.data.redirect;
                    } else {
                        alert(response.data.message || yprintCheckout.i18n.generic_error);
                        $('#yprint-place-order').prop('disabled', false).text('Jetzt kostenpflichtig bestellen');
                    }
                },
                error: function() {
                    alert(yprintCheckout.i18n.generic_error);
                    $('#yprint-place-order').prop('disabled', false).text('Jetzt kostenpflichtig bestellen');
                },
                complete: function() {
                    $container.removeClass('yprint-loading');
                }
            });
        });
    }
    
    // Initialize the checkout
    initCheckout();
});