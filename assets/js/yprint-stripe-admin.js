jQuery(document).ready(function($) {
    // Test connection button
    $('#yprint_stripe_test_connection_button').on('click', function() {
        var button = $(this);
        var result = $('#yprint_stripe_test_connection_result');
        var details = $('#yprint_stripe_test_details');
        var detailsContent = $('#yprint_stripe_test_details_content');
        
        // Disable button and show loading text
        button.prop('disabled', true);
        result.html(yprint_stripe_admin.testing_connection);
        
        // Hide previous test details
        details.hide();
        
        // Make AJAX call
        $.ajax({
            url: yprint_stripe_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_stripe_test_connection',
                nonce: yprint_stripe_admin.nonce
            },
            success: function(response) {
                if (response.success) {
                    result.html('<span style="color: green;">' + yprint_stripe_admin.connection_success + '</span>');
                    
                    // Display test details
                    detailsContent.html('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                    details.show();
                } else {
                    result.html('<span style="color: red;">' + yprint_stripe_admin.connection_error + response.data.message + '</span>');
                }
            },
            error: function() {
                result.html('<span style="color: red;">' + yprint_stripe_admin.connection_error + 'AJAX request failed.</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});