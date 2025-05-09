// IIFE to prevent pollution of global namespace
(function($) {
    // Variable to track if the button click is already being processed
    var isProcessing = false;
    
    // Function that will be executed once when the document is ready
    $(function() {
        console.log('YPrint Stripe Admin JS loaded!');
        
        // Remove any existing click handlers to prevent multiple bindings
        $('#yprint_stripe_test_connection_button').off('click');
        
        // Test Connection Button
        $('#yprint_stripe_test_connection_button').on('click', function(e) {
            // Prevent the default action
            e.preventDefault();
            
            // If already processing, don't start another request
            if (isProcessing) {
                console.log('Already processing a request, skipping');
                return;
            }
            
            console.log('Test button clicked!');
            var button = $(this);
            var resultSpan = $('#yprint_stripe_test_connection_result');
            var detailsDiv = $('#yprint_stripe_test_details');
            var detailsContent = $('#yprint_stripe_test_details_content');
            
            // Set processing flag
            isProcessing = true;
            
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
                    console.log('AJAX success:', response);
                    button.prop('disabled', false);
                    
                    if (response.success) {
                        resultSpan.html('<span style="color: green;">' + yprint_stripe_admin.connection_success + '</span>');
                        
                        // Display details if they exist
                        if (response.data && response.data.details) {
                            detailsContent.html('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                            detailsDiv.show();
                        }
                    } else {
                        resultSpan.html('<span style="color: red;">' + yprint_stripe_admin.connection_error + (response.data && response.data.message ? response.data.message : 'Unknown error') + '</span>');
                    }
                    
                    // Reset processing flag
                    isProcessing = false;
                },
                error: function(xhr, status, error) {
                    console.log('AJAX error:', xhr, status, error);
                    button.prop('disabled', false);
                    resultSpan.html('<span style="color: red;">' + yprint_stripe_admin.connection_error + error + '</span>');
                    
                    // Reset processing flag
                    isProcessing = false;
                }
            });
        });
    });
})(jQuery);