/**
 * YPrint Stripe Admin JavaScript
 */
(function($) {
    'use strict';

    // Erweiterte Hilfsfunktion für AJAX-Tests
    function performStripeTest(testType, button) {
        var $button = $(button);
        var $result = $('#yprint_stripe_test_connection_result');
        
        // Debug-Ausgabe
        console.log('Starting Stripe test: ' + testType);
        
        $button.prop('disabled', true);
        $result.html(testType === 'apple_pay' ? yprint_stripe_admin.testing_apple_pay : yprint_stripe_admin.testing_connection);
        
        // Bestehende Ergebnisse löschen
        $('#yprint_stripe_test_details').hide();
        $('#yprint_stripe_test_details_content').html('');
        
        $.ajax({
            url: yprint_stripe_admin.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_stripe_test_connection',
                nonce: yprint_stripe_admin.nonce,
                test_type: testType
            },
            success: function(response) {
                console.log('Stripe test response:', response);
                
                if (response.success) {
                    $result.html('<span style="color: green;">' + response.data.message + '</span>');
                    
                    // Zeige Details an, wenn vorhanden
                    if (response.data.details && Object.keys(response.data.details).length > 0) {
                        $('#yprint_stripe_test_details').show();
                        $('#yprint_stripe_test_details_content').html('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                    }
                } else {
                    var errorPrefix = testType === 'apple_pay' ? 
                        yprint_stripe_admin.apple_pay_error : 
                        yprint_stripe_admin.connection_error;
                    
                    $result.html('<span style="color: red;">' + errorPrefix + response.data.message + '</span>');
                    
                    // Zeige Fehlerdetails an, wenn vorhanden
                    if (response.data.details && Object.keys(response.data.details).length > 0) {
                        $('#yprint_stripe_test_details').show();
                        $('#yprint_stripe_test_details_content').html('<pre>' + JSON.stringify(response.data.details, null, 2) + '</pre>');
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', status, error);
                console.log('Response:', xhr.responseText);
                
                $result.html('<span style="color: red;">Ajax error: ' + error + '. Please check console logs.</span>');
                
                // Versuche, die Antwort zu parsen, falls es eine JSON-Antwort gab
                try {
                    var jsonResponse = JSON.parse(xhr.responseText);
                    if (jsonResponse) {
                        $('#yprint_stripe_test_details').show();
                        $('#yprint_stripe_test_details_content').html('<pre>' + JSON.stringify(jsonResponse, null, 2) + '</pre>');
                    }
                } catch(e) {
                    // Wenn es kein JSON ist, zeige den rohen Antworttext
                    if (xhr.responseText) {
                        $('#yprint_stripe_test_details').show();
                        $('#yprint_stripe_test_details_content').html('<pre>' + xhr.responseText + '</pre>');
                    }
                }
            },
            complete: function() {
                $button.prop('disabled', false);
                console.log('Test completed: ' + testType);
            }
        });
    }

    $(document).ready(function() {
        console.log('YPrint Stripe Admin JS loaded');
        
        // Test Connection Button
        $('#yprint_stripe_test_connection_button').on('click', function() {
            performStripeTest('connection', this);
        });
        
        // Test Apple Pay Domain Button
        $('#yprint_stripe_test_apple_pay_button').on('click', function() {
            performStripeTest('apple_pay', this);
        });
    });

})(jQuery);