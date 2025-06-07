// YPrint Checkout Header JavaScript - Isolierte Button-Instanzen
jQuery(document).ready(function($) {
    'use strict';
    
    console.log('[YPRINT HEADER] Checkout Header JS wird geladen...');
    
    // Prüfe ob yprintCheckoutHeader-Variable existiert
    if (typeof yprintCheckoutHeader === 'undefined') {
        console.error('[YPRINT HEADER] yprintCheckoutHeader Variable ist nicht definiert!');
        return;
    }
    
    // Klasse für isolierte Button-Instanzen
    class YPrintSummaryToggle {
        constructor(toggleElement) {
            this.toggle = $(toggleElement);
            this.buttonId = this.toggle.attr('id') || 'toggle-' + Date.now() + '-' + Math.random();
            this.popup = this.findRelatedPopup();
            this.content = this.findRelatedContent();
            this.icon = this.toggle.find('.yprint-summary-icon');
            this.text = this.toggle.find('.yprint-summary-text');
            
            // Isolierte Zustände pro Button-Instanz
            this.isOpen = false;
            this.contentLoaded = false;
            
            console.log('[YPRINT HEADER] Button-Instanz erstellt:', this.buttonId, {
                toggle: this.toggle.length,
                popup: this.popup.length,
                content: this.content.length
            });
            
            this.init();
        }
        
        findRelatedPopup() {
            // Suche nach verwandtem Popup in der Nähe des Buttons
            let popup = this.toggle.siblings('#yprint-summary-popup');
            if (popup.length === 0) {
                popup = this.toggle.closest('.checkout-step, .yprint-checkout-header').find('#yprint-summary-popup');
            }
            if (popup.length === 0) {
                popup = $('#yprint-summary-popup').first(); // Fallback zum ersten gefundenen
            }
            return popup;
        }
        
        findRelatedContent() {
            // Suche nach verwandtem Content-Container
            let content = this.popup.find('#yprint-summary-content');
            if (content.length === 0) {
                content = $('#yprint-summary-content').first(); // Fallback
            }
            return content;
        }
        
        init() {
            // Event-Listener für diesen spezifischen Button
            this.toggle.off('click.yprintHeader').on('click.yprintHeader', (e) => {
                e.preventDefault();
                e.stopPropagation(); // Verhindere Bubble-Up zu anderen Handlern
                
                console.log('[YPRINT HEADER] Button geklickt:', this.buttonId, 'isOpen:', this.isOpen);
                
                if (!this.isOpen) {
                    this.openSummary();
                } else {
                    this.closeSummary();
                }
            });
        }
        
        openSummary() {
            console.log('[YPRINT HEADER] Opening summary für:', this.buttonId);
            this.isOpen = true;
            this.toggle.addClass('active').attr('aria-expanded', 'true');
            this.text.text(yprintCheckoutHeader.texts.hide_summary);
            this.popup.removeClass('hidden').addClass('visible');
            
            // Lade Inhalte wenn noch nicht geladen
            if (!this.contentLoaded) {
                this.loadCartContent();
            }
        }
        
        closeSummary() {
            console.log('[YPRINT HEADER] Closing summary für:', this.buttonId);
            this.isOpen = false;
            this.toggle.removeClass('active').attr('aria-expanded', 'false');
            this.text.text(yprintCheckoutHeader.texts.show_summary);
            this.popup.removeClass('visible').addClass('hidden');
        }
        
        loadCartContent() {
            console.log('[YPRINT HEADER] Loading cart content für:', this.buttonId);
            
            $.ajax({
                url: yprintCheckoutHeader.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_get_checkout_header_cart',
                    nonce: yprintCheckoutHeader.nonce
                },
                success: (response) => {
                    console.log('[YPRINT HEADER] AJAX Antwort für:', this.buttonId, response);
                    
                    // Sichere Fehlerbehandlung
                    if (response && response.success) {
                        this.content.html(response.data.html);
                        this.contentLoaded = true;
                    } else {
                        // Sichere Zugriff auf Fehlermeldung
                        const errorMessage = response && response.data && response.data.message 
                            ? response.data.message 
                            : 'Unbekannter Fehler beim Laden der Warenkorbdaten';
                        
                        console.error('[YPRINT HEADER] AJAX Fehler:', errorMessage, response);
                        this.content.html('<p style="text-align: center; color: #dc3545;">' + errorMessage + '</p>');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('[YPRINT HEADER] AJAX Verbindungsfehler für:', this.buttonId, {
                        status: status,
                        error: error,
                        responseText: xhr.responseText
                    });
                    this.content.html('<p style="text-align: center; color: #dc3545;">Verbindungsfehler: ' + error + '</p>');
                }
            });
        }
    }
    
    // Initialisiere alle Toggle-Buttons als separate Instanzen
    const toggleButtons = $('.yprint-summary-toggle');
    console.log('[YPRINT HEADER] Gefundene Toggle-Buttons:', toggleButtons.length);
    
    const buttonInstances = [];
    toggleButtons.each(function() {
        const instance = new YPrintSummaryToggle(this);
        buttonInstances.push(instance);
    });
    
    // Speichere Instanzen global für Debugging
    window.yprintSummaryInstances = buttonInstances;
    
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
            success: (response) => {
                console.log('[YPRINT HEADER] AJAX Antwort für:', this.buttonId, response);
                
                // Sichere Fehlerbehandlung
                if (response && response.success) {
                    this.content.html(response.data.html);
                    this.contentLoaded = true;
                } else {
                    // Sichere Zugriff auf Fehlermeldung
                    const errorMessage = response && response.data && response.data.message 
                        ? response.data.message 
                        : 'Unbekannter Fehler beim Laden der Warenkorbdaten';
                    
                    console.error('[YPRINT HEADER] AJAX Fehler:', errorMessage, response);
                    this.content.html('<p style="text-align: center; color: #dc3545;">' + errorMessage + '</p>');
                }
            },
            error: (xhr, status, error) => {
                console.error('[YPRINT HEADER] AJAX Verbindungsfehler für:', this.buttonId, {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                this.content.html('<p style="text-align: center; color: #dc3545;">Verbindungsfehler: ' + error + '</p>');
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