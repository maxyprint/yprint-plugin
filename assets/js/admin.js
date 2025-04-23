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
    
    // SVG bearbeiten - Funktion entfernt, da SVG-Editor nicht mehr verfügbar
function editSvg() {
    alert('Die SVG-Bearbeitungsfunktion ist nicht mehr verfügbar. Bitte nutze das Design Tool zur Bearbeitung von SVG-Dateien.');
    
    // Alternativ zur Design-Tool-Seite navigieren
    // window.location.href = vectorizeWP.adminUrl + 'admin.php?page=vectorize-wp-designtool';
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
