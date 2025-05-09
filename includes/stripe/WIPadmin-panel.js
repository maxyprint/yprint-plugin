/**
 * YPrint Stripe Admin JavaScript
 * 
 * Handles AJAX requests for testing Stripe connection
 */
(function($) {
    'use strict';

    $(document).ready(function() {
        // Test connection button click handler
        $('#yprint_stripe_test_connection_button').on('click', function() {
            var $resultSpan = $('#yprint_stripe_test_connection_result');
            var $detailsDiv = $('#yprint_stripe_test_details');
            var $detailsContent = $('#yprint_stripe_test_details_content');
            
            // Show testing message
            $resultSpan.html(yprint_stripe_admin.testing_connection).css('color', '#666');
            $detailsDiv.hide();
            
            // Send AJAX request
            $.ajax({
                url: yprint_stripe_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_stripe_test_connection',
                    nonce: yprint_stripe_admin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $resultSpan.html(yprint_stripe_admin.connection_success).css('color', 'green');
                        
                        // Display details
                        var details = response.data.details;
                        var detailsHtml = '<pre>' + JSON.stringify(details, null, 2) + '</pre>';
                        $detailsContent.html(detailsHtml);
                        $detailsDiv.show();
                    } else {
                        $resultSpan.html(yprint_stripe_admin.connection_error + response.data.message).css('color', 'red');
                        $detailsDiv.hide();
                    }
                },
                error: function() {
                    $resultSpan.html(yprint_stripe_admin.connection_error + 'AJAX request failed').css('color', 'red');
                    $detailsDiv.hide();
                }
            });
        });
    });
})(jQuery);