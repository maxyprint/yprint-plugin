/**
 * YPrint Stripe Admin JavaScript
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Test Connection Button
        $('#yprint_stripe_test_connection_button').on('click', function() {
            var $button = $(this);
            var $result = $('#yprint_stripe_test_connection_result');
            
            $button.prop('disabled', true);
            $result.html(yprint_stripe_admin.testing_connection);
            
            $.ajax({
                url: yprint_stripe_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_stripe_test_connection',
                    nonce: yprint_stripe_admin.nonce,
                    test_type: 'connection'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">' + response.data.message + '</span>');
                        
                        // Show details
                        $('#yprint_stripe_test_details').show();
                        $('#yprint_stripe_test_details_content').html('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                    } else {
                        $result.html('<span style="color: red;">' + yprint_stripe_admin.connection_error + response.data.message + '</span>');
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">Ajax error. Please check console logs.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
        
        // Test Apple Pay Domain Button
        $('#yprint_stripe_test_apple_pay_button').on('click', function() {
            var $button = $(this);
            var $result = $('#yprint_stripe_test_connection_result');
            
            $button.prop('disabled', true);
            $result.html(yprint_stripe_admin.testing_connection);
            
            $.ajax({
                url: yprint_stripe_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_stripe_test_connection',
                    nonce: yprint_stripe_admin.nonce,
                    test_type: 'apple_pay'
                },
                success: function(response) {
                    if (response.success) {
                        $result.html('<span style="color: green;">' + response.data.message + '</span>');
                        
                        // Show details
                        $('#yprint_stripe_test_details').show();
                        $('#yprint_stripe_test_details_content').html('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                    } else {
                        $result.html('<span style="color: red;">' + yprint_stripe_admin.connection_error + response.data.message + '</span>');
                        
                        // Show error details if available
                        if (response.data.details) {
                            $('#yprint_stripe_test_details').show();
                            $('#yprint_stripe_test_details_content').html('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                        }
                    }
                },
                error: function() {
                    $result.html('<span style="color: red;">Ajax error. Please check console logs.</span>');
                },
                complete: function() {
                    $button.prop('disabled', false);
                }
            });
        });
    });

})(jQuery);