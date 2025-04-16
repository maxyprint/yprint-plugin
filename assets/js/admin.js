/**
 * Vectorize WP Admin JavaScript
 */

jQuery(document).ready(function($) {
    'use strict';
    
    console.log('Vectorize WP: Admin JS loaded');
    
    // DOM-Elemente cachen
    var elements = {
        selectFileBtn: $('#vectorize-select-file'),
        fileInput: $('#vectorize-file-input'),
        imagePreview: $('#vectorize-image-preview'),
        previewImage: $('#vectorize-preview-image'),
        removeImageBtn: $('#vectorize-remove-image'),
        optionsSection: $('#vectorize-options'),
        startConversionBtn: $('#vectorize-start-conversion'),
        progressSection: $('#vectorize-progress'),
        progressBar: $('.vectorize-progress-bar-inner'),
        progressMessage: $('.vectorize-progress-message'),
        resultSection: $('#vectorize-result-section'),
        svgEditorContainer: $('#svg-editor-container'),
        saveMediaBtn: $('#vectorize-save-media'),
        downloadBtn: $('#vectorize-download'),
        editSvgBtn: $('#vectorize-edit-svg'),
        uploadForm: $('#vectorize-upload-form')
    };
    
    // Maximalgrößen und akzeptierte Typen
    var maxFileSize = 5 * 1024 * 1024; // 5 MB
    var acceptedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/bmp', 'image/webp'];
    
    // Globale Variablen
    var currentSvg = '';
    var currentFileName = '';
    
    // Wenn vectorizeWP vom PHP definiert wurde
    if (typeof vectorizeWP !== 'undefined') {
        if (vectorizeWP.maxUploadSize) {
            maxFileSize = parseInt(vectorizeWP.maxUploadSize);
        }
    }
    
    // Events registrieren
    function initEvents() {
        // Datei-Auswahl-Button
        elements.selectFileBtn.on('click', function(e) {
            e.preventDefault();
            elements.fileInput.trigger('click');
        });
        
        // Datei-Input Änderung
        elements.fileInput.on('change', function() {
            handleFileSelection(this.files);
        });
        
        // Entfernen-Button
        elements.removeImageBtn.on('click', function(e) {
            e.preventDefault();
            resetForm();
        });
        
        // Vektorisierungs-Button
        elements.startConversionBtn.on('click', function(e) {
            e.preventDefault();
            startVectorization();
        });
        
        // In Mediathek speichern
        elements.saveMediaBtn.on('click', function(e) {
            e.preventDefault();
            saveToMediaLibrary();
        });
        
        // SVG herunterladen
        elements.downloadBtn.on('click', function(e) {
            e.preventDefault();
            downloadSvg();
        });
        
        // SVG bearbeiten
        elements.editSvgBtn.on('click', function(e) {
            e.preventDefault();
            editSvg();
        });
    }
    
    // Dateiauswahl verarbeiten
    function handleFileSelection(files) {
        if (!files || !files[0]) {
            return;
        }
        
        var file = files[0];
        currentFileName = file.name;
        
        console.log('Datei ausgewählt:', file.name, 'Typ:', file.type, 'Größe:', file.size);
        
        // Dateityp prüfen
        if (acceptedTypes.indexOf(file.type) === -1) {
            alert('Ungültiger Dateityp. Bitte wähle ein JPEG-, PNG-, GIF-, BMP- oder WebP-Bild aus.');
            resetForm();
            return;
        }
        
        // Dateigröße prüfen
        if (file.size > maxFileSize) {
            alert('Die Datei ist zu groß. Die maximale Dateigröße beträgt ' + 
                (maxFileSize / (1024 * 1024)) + ' MB.');
            resetForm();
            return;
        }
        
        // Bild anzeigen
        var reader = new FileReader();
        reader.onload = function(e) {
            elements.previewImage.attr('src', e.target.result);
            elements.imagePreview.show();
            elements.optionsSection.show();
        };
        
        reader.onerror = function(e) {
            console.error('Fehler beim Lesen der Datei:', e);
            alert('Fehler beim Lesen der Datei. Bitte versuche es erneut.');
            resetForm();
        };
        
        reader.readAsDataURL(file);
    }
    
    // Formular zurücksetzen
    function resetForm() {
        elements.fileInput.val('');
        elements.previewImage.attr('src', '');
        elements.imagePreview.hide();
        elements.optionsSection.hide();
        elements.progressSection.hide();
        elements.resultSection.hide();
        currentSvg = '';
        currentFileName = '';
    }
    
    // Vektorisierung starten
    function startVectorization() {
        if (!elements.fileInput[0].files || !elements.fileInput[0].files[0]) {
            alert('Bitte wähle zuerst ein Bild aus.');
            return;
        }
        
        // Fortschrittsanzeige zeigen
        elements.optionsSection.hide();
        elements.progressSection.show();
        elements.progressBar.css('width', '10%');
        elements.progressMessage.text('Bild wird hochgeladen...');
        
        // FormData für AJAX-Anfrage erstellen
        var formData = new FormData();
        formData.append('action', 'vectorize_image');
        formData.append('nonce', vectorizeWP.nonce);
        formData.append('vectorize_image', elements.fileInput[0].files[0]);
        formData.append('detail_level', $('#vectorize-detail-level').val());
        
        // AJAX-Anfrage senden
$.ajax({
    url: vectorizeWP.ajaxUrl,
    type: 'POST',
    data: formData,
    processData: false,
    contentType: false,
    xhr: function() {
        var xhr = new window.XMLHttpRequest();
        xhr.upload.addEventListener('progress', function(e) {
            if (e.lengthComputable) {
                var percent = Math.round((e.loaded / e.total) * 25); // Upload is 25% of total progress
                elements.progressBar.css('width', percent + '%');
            }
        }, false);
        return xhr;
    },
    success: function(response) {
        console.log('AJAX Antwort:', response);
        
        if (response.success) {
            // Fortschritt simulieren
            elements.progressBar.css('width', '40%');
            elements.progressMessage.text('Bild wird analysiert...');
            
            setTimeout(function() {
                elements.progressBar.css('width', '70%');
                elements.progressMessage.text('Vektorisierung läuft...');
                
                setTimeout(function() {
                    elements.progressBar.css('width', '90%');
                    elements.progressMessage.text('SVG wird erstellt...');
                    
                    setTimeout(function() {
                        elements.progressBar.css('width', '100%');
                        elements.progressMessage.text('Fertig!');
                        
                        // SVG anzeigen
                        if (response.data && response.data.svg) {
                            currentSvg = response.data.svg;
                            elements.svgEditorContainer.html(currentSvg);
                            elements.resultSection.show();
                            
                            // Demo-Modus oder Testmodus Hinweis anzeigen
                            if (response.data.is_demo) {
                                alert(response.data.message || 'Demo-Modus aktiv. Bitte API-Schlüssel für echte Vektorisierung konfigurieren.');
                            } else if (response.data.is_test_mode) {
                                let testModeMsg = 'Testmodus aktiv: ';
                                if (response.data.test_mode === 'test') {
                                    testModeMsg += 'Kostenloser Test ohne Kontingentverbrauch.';
                                } else if (response.data.test_mode === 'test_preview') {
                                    testModeMsg += 'Test mit Vorschau ohne Kontingentverbrauch.';
                                }
                                alert(testModeMsg);
                            }
                        } else {
                            elements.progressMessage.text('Fehler: Keine SVG-Daten erhalten');
                            alert('Die API hat keine SVG-Daten zurückgegeben. Bitte überprüfe deine API-Einstellungen.');
                        }
                    }, 500);
                }, 500);
            }, 500);
        } else {
            // Fehler anzeigen
            elements.progressBar.css('width', '0%');
            elements.progressMessage.text('Fehler: ' + (response.data || 'Unbekannter Fehler'));
            alert('Fehler bei der Vektorisierung: ' + (response.data || 'Unbekannter Fehler'));
            
            setTimeout(function() {
                elements.progressSection.hide();
                elements.optionsSection.show();
            }, 3000);
        }
    },
    error: function(xhr, status, error) {
        console.error('AJAX Fehler:', error);
        console.error('AJAX Response:', xhr.responseText);
        elements.progressBar.css('width', '0%');
        elements.progressMessage.text('Fehler: ' + error);
        alert('Fehler bei der AJAX-Anfrage: ' + error);
        
        setTimeout(function() {
            elements.progressSection.hide();
            elements.optionsSection.show();
        }, 3000);
    }
});
    }
    
    // In Mediathek speichern
    function saveToMediaLibrary() {
        if (!currentSvg) {
            alert('Kein SVG vorhanden zum Speichern.');
            return;
        }
        
        // Titel für das Attachment erzeugen
        var title = '';
        if (currentFileName) {
            title = currentFileName.replace(/\.[^/.]+$/, ""); // Dateiendung entfernen
        } else {
            title = 'Vectorized Image';
        }
        
        // AJAX-Anfrage
        $.ajax({
            url: vectorizeWP.ajaxUrl,
            type: 'POST',
            data: {
                action: 'save_svg_to_media',
                nonce: vectorizeWP.nonce,
                svg_content: currentSvg,
                filename: (currentFileName ? currentFileName.replace(/\.[^/.]+$/, ".svg") : 'vectorized-image.svg'),
                title: title
            },
            beforeSend: function() {
                alert('Speichere SVG in Mediathek...');
            },
            success: function(response) {
                if (response.success) {
                    alert('SVG erfolgreich in Mediathek gespeichert.');
                    console.log('Mediathek-URL:', response.data.attachment_url);
                } else {
                    alert('Fehler beim Speichern: ' + (response.data || 'Unbekannter Fehler'));
                }
            },
            error: function(xhr, status, error) {
                alert('Fehler bei der AJAX-Anfrage: ' + error);
            }
        });
    }
    
    // SVG herunterladen
    function downloadSvg() {
        if (!currentSvg) {
            alert('Kein SVG vorhanden zum Herunterladen.');
            return;
        }
        
        // AJAX-Anfrage zum Erstellen des Download-Links
        $.ajax({
            url: vectorizeWP.ajaxUrl,
            type: 'POST',
            data: {
                action: 'download_svg',
                nonce: vectorizeWP.nonce,
                svg_content: currentSvg,
                filename: (currentFileName ? currentFileName.replace(/\.[^/.]+$/, ".svg") : 'vectorized-image.svg')
            },
            success: function(response) {
                if (response.success) {
                    // Download-Link erstellen und klicken
                    var downloadLink = document.createElement('a');
                    downloadLink.href = response.data.download_url;
                    downloadLink.download = currentFileName ? currentFileName.replace(/\.[^/.]+$/, ".svg") : 'vectorized-image.svg';
                    document.body.appendChild(downloadLink);
                    downloadLink.click();
                    document.body.removeChild(downloadLink);
                } else {
                    alert('Fehler beim Erstellen des Download-Links: ' + (response.data || 'Unbekannter Fehler'));
                }
            },
            error: function(xhr, status, error) {
                alert('Fehler bei der AJAX-Anfrage: ' + error);
            }
        });
    }
    
    // SVG bearbeiten
    function editSvg() {
        if (!currentSvg) {
            alert('Kein SVG vorhanden zum Bearbeiten.');
            return;
        }
        
        // URL zum SVG-Editor mit dem aktuellen SVG-Inhalt erstellen
        var editorUrl = vectorizeWP.adminUrl + 'admin.php?page=vectorize-wp-svg-editor';
        editorUrl += '&svg_content=' + encodeURIComponent(currentSvg);
        editorUrl += '&return_url=' + encodeURIComponent(window.location.href);
        
        // Zum SVG-Editor navigieren
        window.location.href = editorUrl;
    }
    
    // Initialisierung
    function init() {
        console.log('Initialisiere Vectorize WP Admin...');
        initEvents();
        
        // Verstecke Elemente beim Start
        elements.imagePreview.hide();
        elements.optionsSection.hide();
        elements.progressSection.hide();
        elements.resultSection.hide();
        
        console.log('Vectorize WP Admin initialisiert.');
    }
    
    // Initialisierung starten
    init();
});
