jQuery(document).ready(function($) {
    // Test Connection Button
    $('#yprint_stripe_test_connection_button').on('click', function() {
        var button = $(this);
        var resultSpan = $('#yprint_stripe_test_connection_result');
        var detailsDiv = $('#yprint_stripe_test_details');
        var detailsContent = $('#yprint_stripe_test_details_content');
        
        // Disable button and show loading message
        button.prop('disabled', true);
        resultSpan.html('<span style="color: #777;">' + yprint_stripe_admin.testing_connection + '</span>');
        detailsDiv.hide();
        
        // Send AJAX request
        $.ajax({
            url: yprint_stripe_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_stripe_test_connection',
                nonce: yprint_stripe_admin.nonce
            },
            success: function(response) {
                button.prop('disabled', false);
                
                if (response.success) {
                    resultSpan.html('<span style="color: green;">' + yprint_stripe_admin.connection_success + '</span>');
                    
                    // Display details
                    detailsContent.html('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                    detailsDiv.show();
                } else {
                    resultSpan.html('<span style="color: red;">' + yprint_stripe_admin.connection_error + (response.data.message || 'Unknown error') + '</span>');
                }
            },
            error: function(xhr, status, error) {
                button.prop('disabled', false);
                resultSpan.html('<span style="color: red;">' + yprint_stripe_admin.connection_error + error + '</span>');
            }
        });
    });
});