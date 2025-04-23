/**
 * Vectorize WP SVG Editor JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('SVG Editor JS loaded');
    
    // SVG Editor Funktionen
    var svgEditorFrame = $('#svg-editor-frame');
    var svgEditorContent = $('#svg-editor-content');
    var svgEditorId = $('#svg-editor-id');
    var svgEditorStatus = $('#svg-editor-status');
    var saveButton = $('#svg-editor-save');
    
    // SVG Editor laden
    var svgEditor = null;
    var editorReady = false;
    
    // SVG Editor Ready-Event
    window.addEventListener('message', function(event) {
        // Sicherheitsüberprüfung für die Quelle
        if (event.source !== svgEditorFrame[0].contentWindow) {
            return;
        }
        
        // SVG-Editor bereit
        if (event.data === 'svgEditor:ready') {
            console.log('SVG Editor is ready');
            editorReady = true;
            
            // Referenz zum SVG Editor im iFrame
            svgEditor = svgEditorFrame[0].contentWindow.svgEditor;
            
            if (!svgEditor) {
                console.error('SVG Editor not found in iFrame');
                showStatus('error', 'SVG Editor konnte nicht geladen werden.');
                return;
            }
            
            // Wenn ein SVG-Inhalt vorhanden ist, in den Editor laden
            var svgContent = svgEditorContent.val();
            if (svgContent) {
                console.log('Loading SVG content into editor');
                
                try {
                    // SVG in den Editor laden
                    loadSvgIntoEditor(svgContent);
                } catch (error) {
                    console.error('Error loading SVG content:', error);
                    showStatus('error', 'Fehler beim Laden des SVG-Inhalts: ' + error.message);
                }
            }
        }
    });
    
    // Funktion zum Laden des SVGs in den Editor
    function loadSvgIntoEditor(svgContent) {
        if (!editorReady || !svgEditor) {
            console.error('Editor not ready or not available');
            setTimeout(function() {
                loadSvgIntoEditor(svgContent);
            }, 500);
            return;
        }
        
        try {
            // SVG bereinigen für den Editor
            svgContent = cleanSvgForEditor(svgContent);
            
            // SVG in den Editor laden
            svgEditorFrame[0].contentWindow.postMessage({
                action: 'loadSVG',
                svg: svgContent
            }, '*');
            
            showStatus('info', 'SVG wurde in den Editor geladen.');
        } catch (error) {
            console.error('Error in loadSvgIntoEditor:', error);
            showStatus('error', 'Fehler beim Laden des SVG: ' + error.message);
        }
    }
    
    // SVG für den Editor bereinigen
    function cleanSvgForEditor(svgContent) {
        // SVG-String bereinigen
        if (typeof svgContent !== 'string') {
            return '';
        }
        
        // XML-Deklaration entfernen, falls vorhanden
        svgContent = svgContent.replace(/<\?xml[^>]*\?>/, '');
        
        // Sicherstellen, dass es sich um ein gültiges SVG handelt
        if (!svgContent.includes('<svg')) {
            return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"></svg>';
        }
        
        return svgContent;
    }
    
    // Speichern-Button
    saveButton.on('click', function() {
        console.log('Save button clicked');
        
        if (!editorReady || !svgEditor) {
            showStatus('error', 'SVG Editor ist nicht verfügbar.');
            return;
        }
        
        try {
            // SVG-Inhalt von Editor anfordern
            svgEditorFrame[0].contentWindow.postMessage({
                action: 'getSVG'
            }, '*');
            
            // Hören auf die Antwort
            window.addEventListener('message', function svgResponseHandler(event) {
                // Sicherheitsüberprüfung für die Quelle
                if (event.source !== svgEditorFrame[0].contentWindow) {
                    return;
                }
                
                // SVG-Antwort
                if (event.data && event.data.action === 'svgResponse' && event.data.svg) {
                    // Event-Listener entfernen
                    window.removeEventListener('message', svgResponseHandler);
                    
                    var svgContent = event.data.svg;
                    var svgId = svgEditorId.val();
                    
                    console.log('SVG content retrieved from editor');
                    
                    // AJAX-Anfrage zum Speichern des SVG
                    $.ajax({
                        url: vectorizeWpEditor.ajaxUrl,
                        method: 'POST',
                        data: {
                            action: 'save_edited_svg',
                            nonce: vectorizeWpEditor.nonce,
                            svg_content: svgContent,
                            svg_id: svgId
                        },
                        beforeSend: function() {
                            showStatus('info', 'SVG wird gespeichert...');
                        },
                        success: function(response) {
                            if (response.success) {
                                console.log('SVG saved successfully:', response.data);
                                showStatus('success', response.data.message);
                                
                                // SVG ID aktualisieren, falls ein neues SVG erstellt wurde
                                if (svgId === '0' && response.data.svg_id) {
                                    svgEditorId.val(response.data.svg_id);
                                }
                            } else {
                                console.error('Error saving SVG:', response.data);
                                showStatus('error', 'Fehler beim Speichern: ' + response.data);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error:', error);
                            showStatus('error', 'AJAX-Fehler: ' + error);
                        }
                    });
                }
            });
        } catch (error) {
            console.error('Error saving SVG:', error);
            showStatus('error', 'Fehler beim Speichern: ' + error.message);
        }
    });
    
    // Hilfsfunktion: Status anzeigen
    function showStatus(type, message) {
        svgEditorStatus.removeClass('status-info status-success status-error');
        svgEditorStatus.addClass('status-' + type);
        svgEditorStatus.find('.status-message').text(message);
        svgEditorStatus.show();
        
        // Nach einiger Zeit ausblenden, außer bei Fehlern
        if (type !== 'error') {
            setTimeout(function() {
                svgEditorStatus.fadeOut();
            }, 3000);
        }
    }
});