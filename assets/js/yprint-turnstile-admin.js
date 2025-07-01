jQuery(document).ready(function($) {
    
    // Test-Button Funktionalität
    $('#test-turnstile').on('click', function() {
        const $button = $(this);
        const $results = $('#test-results');
        const $content = $('#test-content');
        
        // Button Status
        $button.prop('disabled', true).text('Teste...');
        
        // Aktuelle Werte aus Formular lesen
        const siteKey = $('input[name="yprint_turnstile_options[site_key]"]').val();
        const secretKey = $('input[name="yprint_turnstile_options[secret_key]"]').val();
        
        if (!siteKey || !secretKey) {
            $content.html('<div class="notice notice-error"><p>Bitte geben Sie sowohl Site Key als auch Secret Key ein.</p></div>');
            $results.show();
            $button.prop('disabled', false).text('Verbindung testen');
            return;
        }
        
        // AJAX Test-Request
        $.ajax({
            url: yprintTurnstileAdmin.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_test_turnstile_connection',
                site_key: siteKey,
                secret_key: secretKey,
                nonce: yprintTurnstileAdmin.nonce
            },
            success: function(response) {
                if (response.success) {
                    $content.html('<div class="notice notice-success"><p>✅ Verbindung erfolgreich! Turnstile ist korrekt konfiguriert.</p></div>');
                } else {
                    $content.html('<div class="notice notice-error"><p>❌ Verbindungsfehler: ' + response.data.message + '</p></div>');
                }
            },
            error: function() {
                $content.html('<div class="notice notice-error"><p>❌ Unerwarteter Fehler beim Testen der Verbindung.</p></div>');
            },
            complete: function() {
                $results.show();
                $button.prop('disabled', false).text('Verbindung testen');
            }
        });
    });
    
    // Status-Indikator Updates
    $('input[name="yprint_turnstile_options[site_key]"], input[name="yprint_turnstile_options[secret_key]"]').on('input', function() {
        const $indicator = $(this).siblings('.yprint-status-indicator');
        const value = $(this).val().trim();
        
        $indicator.removeClass('status-active status-inactive');
        $indicator.addClass(value ? 'status-active' : 'status-inactive');
    });
    
    // Aktivierung Toggle
    $('input[name="yprint_turnstile_options[enabled]"]').on('change', function() {
        const isEnabled = $(this).is(':checked');
        const $protectedPages = $('input[name="yprint_turnstile_options[protected_pages][]"]');
        
        if (!isEnabled) {
            $protectedPages.prop('disabled', true).closest('fieldset').css('opacity', '0.5');
        } else {
            $protectedPages.prop('disabled', false).closest('fieldset').css('opacity', '1');
        }
    }).trigger('change');
    
});