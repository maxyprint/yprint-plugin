// YPrint Checkout Header JavaScript
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('[YPRINT HEADER] Checkout Header JS wird geladen...');
    
    // Prüfe ob yprintCheckoutHeader-Variable existiert
    if (typeof yprintCheckoutHeader === 'undefined') {
        console.error('[YPRINT HEADER] yprintCheckoutHeader Variable ist nicht definiert!');
        return;
    }
    
    // DOM-Elemente
    const $toggle = $('#yprint-summary-toggle');
    const $popup = $('#yprint-summary-popup');
    const $content = $('#yprint-summary-content');
    const $icon = $('.yprint-summary-icon');
    const $text = $('.yprint-summary-text');
    
    let isOpen = false;
    let contentLoaded = false;
    
    console.log('[YPRINT HEADER] DOM-Elemente gefunden:', {
        toggle: $toggle.length,
        popup: $popup.length,
        content: $content.length
    });
    
    // Toggle Button Click Handler
    $toggle.on('click', function(e) {
        e.preventDefault();
        console.log('[YPRINT HEADER] Button geklickt, isOpen:', isOpen);
        
        if (!isOpen) {
            openSummary();
        } else {
            closeSummary();
        }
    });
    
    function openSummary() {
        console.log('[YPRINT HEADER] Opening summary...');
        isOpen = true;
        $toggle.addClass('active').attr('aria-expanded', 'true');
        $text.text(yprintCheckoutHeader.texts.hide_summary);
        $popup.removeClass('hidden').addClass('visible');
        
        // Lade Inhalte wenn noch nicht geladen
        if (!contentLoaded) {
            loadCartContent();
        }
    }
    
    function closeSummary() {
        console.log('[YPRINT HEADER] Closing summary...');
        isOpen = false;
        $toggle.removeClass('active').attr('aria-expanded', 'false');
        $text.text(yprintCheckoutHeader.texts.show_summary);
        $popup.removeClass('visible').addClass('hidden');
    }
    
    function loadCartContent() {
        console.log('[YPRINT HEADER] Loading cart content...');
        
        $.ajax({
            url: yprintCheckoutHeader.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_get_checkout_header_cart',
                nonce: yprintCheckoutHeader.nonce
            },
            success: function(response) {
                console.log('[YPRINT HEADER] AJAX erfolg:', response);
                if (response.success) {
                    $content.html(response.data.html);
                    contentLoaded = true;
                } else {
                    $content.html('<p style="text-align: center; color: #dc3545;">' + 
                                (response.data.message || 'Fehler beim Laden der Warenkorbdaten') + '</p>');
                }
            },
            error: function() {
                console.error('[YPRINT HEADER] AJAX Fehler');
                $content.html('<p style="text-align: center; color: #dc3545;">Verbindungsfehler</p>');
            }
        });
    }
    
    // Preisupdate via Custom Event
    $(document).on('yprint_cart_updated', function(event, data) {
        if (data && data.totals && data.totals.total) {
            $('#yprint-header-total').text('€' + parseFloat(data.totals.total).toFixed(2).replace('.', ','));
        }
    });
    
    console.log('[YPRINT HEADER] Checkout Header JS initialisiert');
});