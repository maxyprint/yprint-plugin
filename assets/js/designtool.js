/**
 * Vectorize WP - Design Tool JavaScript - Verbesserte Version
 * Ein vollwertiges browserbasiertes Design-Tool
 */

(function($) {
    'use strict';
    
    // Sicherstellen, dass jQuery verfügbar ist
    if (typeof $ === 'undefined') {
        console.error('jQuery ist nicht verfügbar. Design Tool kann nicht initialisiert werden.');
        return;
    }
    
    $(document).ready(function() {
        // Prüfen, ob Design Tool Container existiert
        if ($('.designtool-container').length === 0) {
            console.error('Design Tool Container nicht gefunden.');
            return;
        }
        
        console.log('Design Tool wird initialisiert...');
        
        // Design Tool initialisieren
        DesignTool.init();
    });
    
    // Hauptobjekt für das Design-Tool
    var DesignTool = {
        // Konfiguration
        config: {
            canvasWidth: 800,
            canvasHeight: 600,
            maxZoom: 3,
            minZoom: 0.5,
            zoomStep: 0.1
        },
        
        // Status und Daten
        state: {
            zoom: 1,
            currentElement: null,
            elements: [],
            elementCounter: 0,
            history: [],
            historyIndex: -1,
            maxHistorySteps: 30,
            isDragging: false,
            isResizing: false,
            dragStartX: 0,
            dragStartY: 0,
            originalPos: { x: 0, y: 0 },
            originalSize: { width: 0, height: 0 },
            currentResizeHandle: null
        },
        
        // DOM-Elemente
        elements: {},
        
        // Initialisierung
init: function() {
    console.log('DesignTool.init() gestartet');
    
    // Prüfen, ob Container existiert und DOM bereit ist
    if ($('.designtool-container').length === 0) {
        console.error('designtool-container nicht gefunden! DOM möglicherweise noch nicht bereit.');
        var self = this;
        // Nochmals versuchen, wenn das DOM vollständig geladen ist
        $(document).ready(function() {
            console.log('Document ready event - versuche erneut zu initialisieren');
            if ($('.designtool-container').length === 0) {
                console.error('designtool-container fehlt auch nach document.ready!');
                // Trotzdem versuchen, Layout zu reparieren
                self.fixLayout();
                return;
            }
            self.initializeComponents();
        });
        return;
    }
    
    this.initializeComponents();
},

// Neue Methode für die eigentliche Initialisierung
initializeComponents: function() {
    console.log('Initialisiere Design Tool Komponenten...');
    
    // Gleich zu Beginn versuchen, störende Elemente zu entfernen oder zu verstecken
    this.hideIntrusiveElements();
    
    this.cacheElements();
    this.setupCanvas();
    this.bindEvents();
    this.setInitialState();
    
    // Prüfen, ob vorhandene SVG-Daten geladen werden sollen
    this.loadExistingSvgIfAvailable();
    
    // Debug-Button zur Toolbar hinzufügen
    var self = this;
    var $debugBtn = $('<button id="designtool-debug-btn" class="designtool-btn" title="Layout debuggen">' + 
                     '<svg viewBox="0 0 24 24" width="16" height="16"><path d="M20 8h-2.81c-.45-.78-1.07-1.45-1.82-1.96L17 4.41 15.59 3l-2.17 2.17C12.96 5.06 12.49 5 12 5s-.96.06-1.41.17L8.41 3 7 4.41l1.62 1.63C7.88 6.55 7.26 7.22 6.81 8H4v2h2.09c-.05.33-.09.66-.09 1v1H4v2h2v1c0 .34.04.67.09 1H4v2h2.81c1.04 1.79 2.97 3 5.19 3s4.15-1.21 5.19-3H20v-2h-2.09c.05-.33.09-.66.09-1v-1h2v-2h-2v-1c0-.34-.04-.67-.09-1H20V8z" fill="currentColor"/></svg>' +
                     ' Debug</button>');
    
    $('.designtool-toolbar-group:last').after(
        $('<div class="designtool-toolbar-group"></div>').append($debugBtn)
    );
    
    $debugBtn.on('click', function() {
        var result = self.debugLayout();
        console.log(result);
        self.fixLayout(); // Immer fixLayout ausführen, wenn Debug-Button geklickt wird
    });
    
    // Sofort Layout-Fix anwenden, nicht warten
    this.fixLayout();
    
    // Nach kurzer Verzögerung nochmals prüfen
    setTimeout(function() {
        if ($('.designtool-canvas-container').height() < 50 || $('.designtool-sidebar').is(':hidden')) {
            console.warn('Layout-Problem nach Initialisierung erkannt, wende Fix erneut an...');
            self.fixLayout();
            self.hideIntrusiveElements();
        }
    }, 500);
    
    console.log('Design Tool wurde erfolgreich initialisiert');
},

// Neue Methode zum Ausblenden störender Elemente
hideIntrusiveElements: function() {
    // Cookie-Banner und andere Overlay-Elemente ausblenden
    $('.cky-consent-container, .cky-modal, .cky-preference-center').css({
        'display': 'none !important',
        'z-index': '-1 !important'
    });
    
    // Versuche, die Schließen-Buttons zu finden und zu klicken
    $('.cky-btn-close, .cky-btn-reject, [class*="cookie-close"], [id*="cookie-close"]').each(function() {
        try {
            $(this).trigger('click');
        } catch(e) {
            console.log('Konnte Cookie-Button nicht klicken:', e);
        }
    });
    
    // Setze den Z-Index aller anderen Body-Kinder zurück
    $('body > *:not(.designtool-container):not(script):not(style)').css({
        'z-index': '1'
    });
    
    console.log('Potenziell störende Elemente wurden ausgeblendet');
},
        
        // DOM-Elemente zwischenspeichern
        cacheElements: function() {
            this.elements = {
                container: $('.designtool-container'),
                canvas: $('#designtool-canvas'),
                fileInput: $('#designtool-file-input'),
                uploadButton: $('#designtool-upload-btn'),
                filesList: $('#designtool-files'),
                zoomInButton: $('#designtool-zoom-in-btn'),
                zoomOutButton: $('#designtool-zoom-out-btn'),
                zoomResetButton: $('#designtool-zoom-reset-btn'),
                vectorizeButton: $('#designtool-vectorize-btn'),
                undoButton: $('#designtool-undo-btn'),
                redoButton: $('#designtool-redo-btn'),
                noSelection: $('#designtool-no-selection'),
                imageProperties: $('#designtool-image-properties'),
                svgProperties: $('#designtool-svg-properties'),
                imageWidth: $('#designtool-image-width'),
                imageHeight: $('#designtool-image-height'),
                imageOpacity: $('#designtool-image-opacity'),
                opacityValue: $('#designtool-opacity-value'),
                svgColor: $('#designtool-svg-color'),
                exportButton: $('#designtool-export-btn'),
                exportFormat: $('#designtool-export-format'),
                downloadLink: $('#designtool-download-link'),
                // Vektorisierungsmodal
                vectorizeModal: $('#designtool-vectorize-modal'),
                vectorizeClose: $('.designtool-modal-close'),
                vectorizeCancel: $('#designtool-vectorize-cancel'),
                vectorizeStart: $('#designtool-vectorize-start'),
                vectorizeDetail: $('#designtool-vectorize-detail'),
                vectorizeProgress: $('#designtool-vectorize-progress'),
                previewOriginal: $('#designtool-preview-original-img'),
                previewResult: $('#designtool-preview-result-img')
            };
            
            // Überprüfen, ob alle benötigten Elemente gefunden wurden
            var missingElements = [];
            for (var key in this.elements) {
                if (this.elements[key].length === 0) {
                    missingElements.push(key);
                }
            }
            
            if (missingElements.length > 0) {
                console.warn('Design Tool: Folgende DOM-Elemente nicht gefunden:', missingElements.join(', '));
            }
        },
        
        // Canvas einrichten
        setupCanvas: function() {
            this.elements.canvas.css({
                width: this.config.canvasWidth + 'px',
                height: this.config.canvasHeight + 'px'
            });
        },
        
        // Event-Listener binden
        bindEvents: function() {
            // Shortcut für 'this', um im Event-Handling verfügbar zu sein
            var self = this;
            
            // Datei-Upload
            this.elements.uploadButton.on('click', function(e) {
                e.preventDefault();
                self.elements.fileInput.trigger('click');
            });
            
            this.elements.fileInput.on('change', function(e) {
                var files = e.target.files;
                if (files && files.length > 0) {
                    self.processFiles(files);
                }
            });
            
            // Canvas-Events
            this.elements.canvas.on('mousedown', function(e) {
                self.handleCanvasMouseDown(e);
            });
            
            $(document).on('mousemove', function(e) {
                self.handleDocumentMouseMove(e);
            });
            
            $(document).on('mouseup', function(e) {
                self.handleDocumentMouseUp(e);
            });
            
            // Zoom-Controls
            this.elements.zoomInButton.on('click', function() {
                self.zoomIn();
            });
            
            this.elements.zoomOutButton.on('click', function() {
                self.zoomOut();
            });
            
            this.elements.zoomResetButton.on('click', function() {
                self.zoomReset();
            });
            
            // Vektorisieren-Button
            this.elements.vectorizeButton.on('click', function() {
                self.openVectorizeModal();
            });
            
            // Undo/Redo
            this.elements.undoButton.on('click', function() {
                self.undo();
            });
            
            this.elements.redoButton.on('click', function() {
                self.redo();
            });
            
            // Element-Eigenschaften
            this.elements.imageWidth.on('change', function() {
                self.updateElementWidth();
            });
            
            this.elements.imageHeight.on('change', function() {
                self.updateElementHeight();
            });
            
            this.elements.imageOpacity.on('input', function() {
                self.updateElementOpacity();
            });
            
            this.elements.svgColor.on('change', function() {
                self.updateSVGColor();
            });
            
            // Export
            this.elements.exportButton.on('click', function() {
                self.exportCanvas();
            });
            
            // Vektorisierungsmodal
            this.elements.vectorizeClose.on('click', function() {
                self.closeVectorizeModal();
            });
            
            this.elements.vectorizeCancel.on('click', function() {
                self.closeVectorizeModal();
            });
            
            this.elements.vectorizeStart.on('click', function() {
                self.startVectorization();
            });
            
            // Tastatur-Shortcuts
            $(document).on('keydown', function(e) {
                self.handleKeyDown(e);
            });
            
            // Drag & Drop für die Canvas
            this.elements.canvas.on('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('drag-over');
            });
            
            this.elements.canvas.on('dragleave', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
            });
            
            this.elements.canvas.on('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('drag-over');
                
                var files = e.originalEvent.dataTransfer.files;
                if (files && files.length > 0) {
                    self.processFiles(files);
                }
            });
        },
        
        // Initialen Status setzen
        setInitialState: function() {
            this.updateZoomUI();
            this.updateUndoRedoUI();
            this.updatePropertiesPanel();
        },
        
        // Existierendes SVG laden, falls verfügbar
        loadExistingSvgIfAvailable: function() {
            // Prüfen, ob vectorizeWpSvgData definiert ist (wird vom PHP-Code bereitgestellt)
            if (typeof window.vectorizeWpSvgData !== 'undefined' && window.vectorizeWpSvgData.svgContent) {
                console.log('Vorhandenes SVG gefunden, wird geladen...');
                
                // SVG als Blob vorbereiten
                var blob = new Blob([window.vectorizeWpSvgData.svgContent], {type: 'image/svg+xml'});
                var url = URL.createObjectURL(blob);
                
                // Als Datei hinzufügen
                this.addFileToList({
                    id: 'file-' + this.state.elementCounter,
                    name: 'Importiertes SVG',
                    type: 'image/svg+xml',
                    size: this.formatFileSize(blob.size),
                    url: url,
                    isSVG: true
                });
                
                // Zum Canvas hinzufügen
                this.addElementToCanvas({
                    id: 'element-' + this.state.elementCounter,
                    type: 'svg',
                    src: url,
                    left: 100,
                    top: 100,
                    width: 300,
                    height: 300,
                    originalWidth: 300,
                    originalHeight: 300,
                    opacity: 1,
                    rotation: 0,
                    fileReference: 'file-' + this.state.elementCounter
                });
                
                this.state.elementCounter++;
                
                // Im Verlauf speichern
                this.addHistoryStep();
            }
        },
        
        // Event-Handler
        
        // Canvas-Mausklick-Event
        handleCanvasMouseDown: function(e) {
            // Wenn nicht direkt auf ein Element geklickt wurde, Auswahl aufheben
            if (e.target === this.elements.canvas[0]) {
                this.deselectAllElements();
                return;
            }
            
            // Element oder Resize-Handle geklickt?
            var $target = $(e.target);
            
            // Resize-Handle geklickt?
            if ($target.hasClass('designtool-element-control')) {
                this.startResizing(e, $target);
                return;
            }
            
            // Element geklickt?
            var $element = $target.closest('.designtool-element');
            if ($element.length) {
                this.selectElement($element);
                this.startDragging(e);
            }
        },
        
        // Mausbewegung auf dem Dokument
        handleDocumentMouseMove: function(e) {
            if (this.state.isDragging && this.state.currentElement) {
                this.dragElement(e);
            } else if (this.state.isResizing && this.state.currentElement) {
                this.resizeElement(e);
            }
        },
        
        // Mausklick loslassen
        handleDocumentMouseUp: function(e) {
            if (this.state.isDragging || this.state.isResizing) {
                // Position oder Größe geändert, im Verlauf speichern
                this.addHistoryStep();
            }
            
            this.state.isDragging = false;
            this.state.isResizing = false;
            this.state.currentResizeHandle = null;
        },
        
        // Tastatureingaben
        handleKeyDown: function(e) {
            // Prüfen, ob keine Eingabefelder im Fokus sind
            if ($(document.activeElement).is('input, textarea, select')) {
                return;
            }
            
            switch (e.keyCode) {
                case 46: // Entf
                    this.deleteSelectedElement();
                    break;
                case 90: // Z (Undo)
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.undo();
                    }
                    break;
                case 89: // Y (Redo)
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.redo();
                    }
                    break;
                case 187: // Plus
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.zoomIn();
                    }
                    break;
                case 189: // Minus
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.zoomOut();
                    }
                    break;
                case 48: // 0
                    if (e.ctrlKey || e.metaKey) {
                        e.preventDefault();
                        this.zoomReset();
                    }
                    break;
            }
        },
        
        // Dienstfunktionen
        
        // Dateien verarbeiten und in die Anwendung laden
        processFiles: function(files) {
            var self = this;
            
            // Für jede Datei
            Array.from(files).forEach(function(file) {
                // Prüfen, ob es sich um ein Bild handelt
                if (!file.type.match('image.*')) {
                    alert('Nur Bilddateien werden unterstützt.');
                    return;
                }
                
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    var imageUrl = e.target.result;
                    var isSVG = file.type === 'image/svg+xml';
                    
                    // Datei zur Dateiliste hinzufügen
                    self.addFileToList({
                        id: 'file-' + self.state.elementCounter,
                        name: file.name,
                        type: file.type,
                        size: self.formatFileSize(file.size),
                        url: imageUrl,
                        isSVG: isSVG
                    });
                    
                    // Element zum Canvas hinzufügen
                    self.addElementToCanvas({
                        id: 'element-' + self.state.elementCounter,
                        type: isSVG ? 'svg' : 'image',
                        src: imageUrl,
                        left: 100,
                        top: 100,
                        width: isSVG ? 200 : null, // Bei SVG feste Anfangsgröße
                        height: isSVG ? 200 : null,
                        originalWidth: isSVG ? 200 : null,
                        originalHeight: isSVG ? 200 : null,
                        opacity: 1,
                        rotation: 0,
                        fileReference: 'file-' + self.state.elementCounter
                    });
                    
                    self.state.elementCounter++;
                    
                    // Im Verlauf speichern
                    self.addHistoryStep();
                };
                
                reader.readAsDataURL(file);
            });
            
            // Dateieingabefeld zurücksetzen
            this.elements.fileInput.val('');
        },
        
        // Formatiert die Dateigröße in KB, MB, etc.
        formatFileSize: function(bytes) {
            if (bytes < 1024) {
                return bytes + ' B';
            } else if (bytes < 1048576) {
                return (bytes / 1024).toFixed(1) + ' KB';
            } else {
                return (bytes / 1048576).toFixed(1) + ' MB';
            }
        },
        
        // Fügt eine Datei zur Dateiliste hinzu
        addFileToList: function(file) {
            var self = this;
            var $fileItem = $('<div class="designtool-file-item" data-id="' + file.id + '"></div>');
            
            // Inhalt mit Löschbutton hinzufügen
            $fileItem.html(`
                <div class="designtool-file-thumb"></div>
                <div class="designtool-file-info">
                    <div class="designtool-file-name">${file.name}</div>
                    <div class="designtool-file-meta">${file.size}</div>
                </div>
                <div class="designtool-file-actions">
                    <button class="designtool-file-delete" title="Datei löschen">×</button>
                </div>
            `);
            
            // Dann füge den Inhalt zum Thumbnail hinzu
            if (file.isSVG) {
                $fileItem.find('.designtool-file-thumb').html('<svg viewBox="0 0 24 24" width="24" height="24"><path d="M7 14l5-5 5 5z" fill="#4285f4"/></svg>');
                // SVG später ersetzen, wenn geladen
                var img = new Image();
                img.onload = function() {
                    $fileItem.find('.designtool-file-thumb').html('').append($(img).clone().css({ 'max-width': '100%', 'max-height': '100%' }));
                };
                img.src = file.url;
            } else {
                $fileItem.find('.designtool-file-thumb').html('<img src="' + file.url + '" alt="' + file.name + '">');
            }
            
            // Zur Liste hinzufügen
            this.elements.filesList.find('.designtool-empty-notice').remove();
            this.elements.filesList.append($fileItem);
            
            // Event-Handler für Klick hinzufügen
            $fileItem.on('click', function() {
                var fileId = $(this).data('id');
                // Element mit dieser Dateireferenz finden und auswählen
                var $element = self.elements.canvas.find('.designtool-element[data-file-reference="' + fileId + '"]');
                if ($element.length) {
                    self.selectElement($element);
                }
            });
            
            // Hinzufügen des Lösch-Button Event-Handlers
            $fileItem.find('.designtool-file-delete').on('click', function(e) {
                e.stopPropagation(); // Verhindert, dass das Element ausgewählt wird
                var fileId = $fileItem.data('id');
                
                // Element mit dieser Dateireferenz finden und entfernen, falls ausgewählt
                var $element = self.elements.canvas.find('.designtool-element[data-file-reference="' + fileId + '"]');
                if ($element.length) {
                    // Wenn das Element aktuell ausgewählt ist, Auswahl aufheben
                    if (self.state.currentElement && self.state.currentElement.id === $element.attr('id')) {
                        self.deselectAllElements();
                    }
                    
                    // Element aus DOM entfernen
                    $element.remove();
                    
                    // Element aus der Liste entfernen
                    var index = -1;
                    for (var i = 0; i < self.state.elements.length; i++) {
                        if (self.state.elements[i].fileReference === fileId) {
                            index = i;
                            break;
                        }
                    }
                    
                    if (index > -1) {
                        self.state.elements.splice(index, 1);
                    }
                }
                
                // Datei aus der Liste entfernen
                $fileItem.remove();
                
                // Falls die Dateiliste leer ist, Hinweis anzeigen
                if (self.elements.filesList.find('.designtool-file-item').length === 0) {
                    self.elements.filesList.html('<div class="designtool-empty-notice">Keine Dateien vorhanden. Lade ein Bild hoch, um zu starten.</div>');
                }
                
                // Im Verlauf speichern
                self.addHistoryStep();
            });
        },
        
        // Fügt ein Element zum Canvas hinzu
        addElementToCanvas: function(element) {
            var self = this;
            var $element = $('<div class="designtool-element" id="' + element.id + '" data-type="' + element.type + '" data-file-reference="' + element.fileReference + '"></div>');
            
            // Bild oder SVG einfügen
            if (element.type === 'image') {
                var img = new Image();
                img.onload = function() {
                    // Natürliche Bildgröße speichern
                    element.originalWidth = img.naturalWidth;
                    element.originalHeight = img.naturalHeight;
                    
                    // Wenn keine Größe angegeben wurde, Bildgröße verwenden
                    if (!element.width || !element.height) {
                        // Größe auf maximal 400px begrenzen, Proportionen beibehalten
                        var maxDimension = 400;
                        if (img.naturalWidth > maxDimension || img.naturalHeight > maxDimension) {
                            if (img.naturalWidth > img.naturalHeight) {
                                element.width = maxDimension;
                                element.height = (img.naturalHeight / img.naturalWidth) * maxDimension;
                            } else {
                                element.height = maxDimension;
                                element.width = (img.naturalWidth / img.naturalHeight) * maxDimension;
                            }
                        } else {
                            element.width = img.naturalWidth;
                            element.height = img.naturalHeight;
                        }
                    }
                    
                    // Element positionieren und Größe setzen
                    $element.css({
                        'left': element.left + 'px',
                        'top': element.top + 'px',
                        'width': element.width + 'px',
                        'height': element.height + 'px',
                        'opacity': element.opacity
                    });
                    
                    // Element in die Liste aufnehmen
                    self.state.elements.push({
                        id: element.id,
                        $element: $element,
                        type: element.type,
                        src: element.src,
                        left: element.left,
                        top: element.top,
                        width: element.width,
                        height: element.height,
                        originalWidth: element.originalWidth,
                        originalHeight: element.originalHeight,
                        opacity: element.opacity,
                        rotation: element.rotation,
                        fileReference: element.fileReference
                    });
                    
                    // Element auswählen
                    self.selectElement($element);
                };
                img.src = element.src;
                $element.append(img);
            } else if (element.type === 'svg') {
                // SVG direkt einbinden
                $element.css({
                    'left': element.left + 'px',
                    'top': element.top + 'px',
                    'width': element.width + 'px',
                    'height': element.height + 'px',
                    'opacity': element.opacity
                });
                
                // SVG laden und einfügen
                $.get(element.src, function(data) {
                    var $svg = $(data).find('svg');
                    if ($svg.length === 0) {
                        $svg = $(data);
                    }
                    $element.append($svg);
                    
                    // Element in die Liste aufnehmen
                    self.state.elements.push({
                        id: element.id,
                        $element: $element,
                        type: element.type,
                        src: element.src,
                        left: element.left,
                        top: element.top,
                        width: element.width,
                        height: element.height,
                        originalWidth: element.width,
                        originalHeight: element.height,
                        opacity: element.opacity,
                        rotation: element.rotation,
                        fileReference: element.fileReference
                    });
                    
                    // Element auswählen
                    self.selectElement($element);
                }).fail(function(error) {
                    console.error('Fehler beim Laden des SVG:', error);
                    alert('Fehler beim Laden des SVG. Bitte versuche es erneut.');
                });
            }
            
            // Zum Canvas hinzufügen
            this.elements.canvas.append($element);
        },
        
        // Wählt ein Element aus
        selectElement: function($element) {
            // Alle anderen Elemente deselektieren
            this.deselectAllElements();
            
            // Dieses Element auswählen
            $element.addClass('selected');
            
            // Aktuelles Element speichern
            this.state.currentElement = this.findElementById($element.attr('id'));
            
            // Vorhandene Controls entfernen und neue hinzufügen
            $element.find('.designtool-element-controls').remove();
            var $controls = $('<div class="designtool-element-controls"></div>');
            $controls.append('<div class="designtool-element-control tl"></div>');
            $controls.append('<div class="designtool-element-control tr"></div>');
            $controls.append('<div class="designtool-element-control br"></div>');
            $controls.append('<div class="designtool-element-control bl"></div>');

            $element.append($controls);
            
            // Vektorisieren-Button aktivieren, wenn es ein Bild ist
            this.elements.vectorizeButton.prop('disabled', this.state.currentElement.type !== 'image');
            
            // Eigenschaften-Panel aktualisieren
            this.updatePropertiesPanel();
        },
        
        // Hebt die Auswahl aller Elemente auf
        deselectAllElements: function() {
            this.elements.canvas.find('.designtool-element').removeClass('selected');
            this.elements.canvas.find('.designtool-element-controls').remove();
            this.state.currentElement = null;
            
            // Vektorisieren-Button deaktivieren
            this.elements.vectorizeButton.prop('disabled', true);
            
            // Eigenschaften-Panel aktualisieren
            this.updatePropertiesPanel();
        },
        
        // Aktualisiert das Eigenschaften-Panel basierend auf dem ausgewählten Element
        updatePropertiesPanel: function() {
            // Kein Element ausgewählt
            if (!this.state.currentElement) {
                this.elements.noSelection.show();
                this.elements.imageProperties.hide();
                this.elements.svgProperties.hide();
                return;
            }
            
            // Element ist ausgewählt
            this.elements.noSelection.hide();
            
            var element = this.state.currentElement;
            
            // Allgemeine Eigenschaften zeigen
            this.elements.imageProperties.show();
            this.elements.imageWidth.val(Math.round(element.width));
            this.elements.imageHeight.val(Math.round(element.height));
            this.elements.imageOpacity.val(element.opacity);
            this.elements.opacityValue.text(Math.round(element.opacity * 100) + '%');
            
            // SVG-spezifische Eigenschaften
            if (element.type === 'svg') {
                this.elements.svgProperties.show();
            } else {
                this.elements.svgProperties.hide();
            }
        },
        
        // Startet das Ziehen eines Elements
        startDragging: function(e) {
            if (!this.state.currentElement) return;
            
            // Prevent default to avoid browser's default drag behavior
            e.preventDefault();
            
            this.state.isDragging = true;
            
            // Mausposition speichern
            this.state.dragStartX = e.clientX;
            this.state.dragStartY = e.clientY;
            
            // Ursprüngliche Position des Elements speichern
            this.state.originalPos = {
                x: this.state.currentElement.left,
                y: this.state.currentElement.top
            };
            
            // Explizit sicherstellen, dass keine Text-/Elementauswahl stattfindet
            document.getSelection().removeAllRanges();
        },
        
        // Zieht ein Element
        dragElement: function(e) {
            var dx = e.clientX - this.state.dragStartX;
            var dy = e.clientY - this.state.dragStartY;
            
            // Neue Position berechnen
            var newLeft = this.state.originalPos.x + dx / this.state.zoom;
            var newTop = this.state.originalPos.y + dy / this.state.zoom;
            
            // Element aktualisieren
            this.state.currentElement.$element.css({
                'left': newLeft + 'px',
                'top': newTop + 'px'
            });
            
            // Element-Daten aktualisieren
            this.state.currentElement.left = newLeft;
            this.state.currentElement.top = newTop;
        },
        
        // Startet das Skalieren eines Elements
        startResizing: function(e, $handle) {
            if (!this.state.currentElement) return;
            
            this.state.isResizing = true;
            this.state.currentResizeHandle = $handle.attr('class').split(' ')[1]; // tl, tr, bl, br
            
            // Mausposition speichern
            this.state.dragStartX = e.clientX;
            this.state.dragStartY = e.clientY;
            
            // Ursprüngliche Größe und Position des Elements speichern
            this.state.originalSize = {
                width: this.state.currentElement.width,
                height: this.state.currentElement.height
            };
            
            this.state.originalPos = {
                x: this.state.currentElement.left,
                y: this.state.currentElement.top
            };
        },
        
        // Skaliert ein Element
        // Skaliert ein Element
        resizeElement: function(e) {
            var dx = (e.clientX - this.state.dragStartX) / this.state.zoom;
            var dy = (e.clientY - this.state.dragStartY) / this.state.zoom;
            
            var newWidth = this.state.originalSize.width;
            var newHeight = this.state.originalSize.height;
            var newLeft = this.state.originalPos.x;
            var newTop = this.state.originalPos.y;
            
            // Je nach Resize-Handle die Größe und Position anpassen
            switch (this.state.currentResizeHandle) {
                case 'tl': // Top-Left
                    newWidth = this.state.originalSize.width - dx;
                    newHeight = this.state.originalSize.height - dy;
                    newLeft = this.state.originalPos.x + dx;
                    newTop = this.state.originalPos.y + dy;
                    break;
                case 'tr': // Top-Right
                    newWidth = this.state.originalSize.width + dx;
                    newHeight = this.state.originalSize.height - dy;
                    newTop = this.state.originalPos.y + dy;
                    break;
                case 'bl': // Bottom-Left
                    newWidth = this.state.originalSize.width - dx;
                    newHeight = this.state.originalSize.height + dy;
                    newLeft = this.state.originalPos.x + dx;
                    break;
                case 'br': // Bottom-Right
                    newWidth = this.state.originalSize.width + dx;
                    newHeight = this.state.originalSize.height + dy;
                    break;
            }
            
            // Mindestgröße sicherstellen
            if (newWidth < 10) newWidth = 10;
            if (newHeight < 10) newHeight = 10;
            
            // Element aktualisieren
            this.state.currentElement.$element.css({
                'width': newWidth + 'px',
                'height': newHeight + 'px',
                'left': newLeft + 'px',
                'top': newTop + 'px'
            });
            
            // Element-Daten aktualisieren
            this.state.currentElement.width = newWidth;
            this.state.currentElement.height = newHeight;
            this.state.currentElement.left = newLeft;
            this.state.currentElement.top = newTop;
            
            // Eigenschaften-Panel aktualisieren
            if (this.elements.imageWidth.is(':focus') || this.elements.imageHeight.is(':focus')) {
                // Wenn der Nutzer gerade die Werte eingibt, nicht aktualisieren
                return;
            }
            
            this.elements.imageWidth.val(Math.round(newWidth));
            this.elements.imageHeight.val(Math.round(newHeight));
        },
        
        // Sucht ein Element anhand seiner ID
        findElementById: function(id) {
            for (var i = 0; i < this.state.elements.length; i++) {
                if (this.state.elements[i].id === id) {
                    return this.state.elements[i];
                }
            }
            return null;
        },
        
        // Löscht das ausgewählte Element
        deleteSelectedElement: function() {
            if (!this.state.currentElement) return;
            
            // Element aus dem DOM entfernen
            this.state.currentElement.$element.remove();
            
            // Element aus der Liste entfernen
            var index = this.state.elements.indexOf(this.state.currentElement);
            if (index > -1) {
                this.state.elements.splice(index, 1);
            }
            
            // Auswahl zurücksetzen
            this.deselectAllElements();
            
            // Im Verlauf speichern
            this.addHistoryStep();
        },
        
        // Aktualisiert die Breite eines Elements über das Eingabefeld
        updateElementWidth: function() {
            if (!this.state.currentElement) return;
            
            var newWidth = parseFloat(this.elements.imageWidth.val());
            if (isNaN(newWidth) || newWidth < 10) return;
            
            // Seitenverhältnis beibehalten?
            if (this.state.currentElement.originalWidth && this.state.currentElement.originalHeight) {
                var aspectRatio = this.state.currentElement.originalWidth / this.state.currentElement.originalHeight;
                var newHeight = newWidth / aspectRatio;
                
                this.state.currentElement.$element.css({
                    'width': newWidth + 'px',
                    'height': newHeight + 'px'
                });
                
                this.state.currentElement.width = newWidth;
                this.state.currentElement.height = newHeight;
                
                this.elements.imageHeight.val(Math.round(newHeight));
            } else {
                this.state.currentElement.$element.css('width', newWidth + 'px');
                this.state.currentElement.width = newWidth;
            }
            
            // Im Verlauf speichern
            this.addHistoryStep();
        },
        
        // Aktualisiert die Höhe eines Elements über das Eingabefeld
        updateElementHeight: function() {
            if (!this.state.currentElement) return;
            
            var newHeight = parseFloat(this.elements.imageHeight.val());
            if (isNaN(newHeight) || newHeight < 10) return;
            
            // Seitenverhältnis beibehalten?
            if (this.state.currentElement.originalWidth && this.state.currentElement.originalHeight) {
                var aspectRatio = this.state.currentElement.originalWidth / this.state.currentElement.originalHeight;
                var newWidth = newHeight * aspectRatio;
                
                this.state.currentElement.$element.css({
                    'width': newWidth + 'px',
                    'height': newHeight + 'px'
                });
                
                this.state.currentElement.width = newWidth;
                this.state.currentElement.height = newHeight;
                
                this.elements.imageWidth.val(Math.round(newWidth));
            } else {
                this.state.currentElement.$element.css('height', newHeight + 'px');
                this.state.currentElement.height = newHeight;
            }
            
            // Im Verlauf speichern
            this.addHistoryStep();
        },
        
        // Aktualisiert die Deckkraft eines Elements
        updateElementOpacity: function() {
            if (!this.state.currentElement) return;
            
            var opacity = parseFloat(this.elements.imageOpacity.val());
            if (isNaN(opacity) || opacity < 0 || opacity > 1) return;
            
            this.state.currentElement.$element.css('opacity', opacity);
            this.state.currentElement.opacity = opacity;
            
            this.elements.opacityValue.text(Math.round(opacity * 100) + '%');
            
            // Im Verlauf speichern (hier evtl. verzögert, da der Schieberegler kontinuierliche Änderungen erzeugt)
            clearTimeout(this.opacityHistoryTimeout);
            var self = this;
            this.opacityHistoryTimeout = setTimeout(function() {
                self.addHistoryStep();
            }, 300);
        },
        
        // Aktualisiert die Farbe eines SVG-Elements
        updateSVGColor: function() {
            if (!this.state.currentElement || this.state.currentElement.type !== 'svg') return;
            
            var color = this.elements.svgColor.val();
            
            // Alle Pfade im SVG ändern
            this.state.currentElement.$element.find('path').attr('fill', color);
            
            // Im Verlauf speichern
            this.addHistoryStep();
        },
        
        // Zoomfunktionen
        zoomIn: function() {
            if (this.state.zoom >= this.config.maxZoom) return;
            
            this.state.zoom += this.config.zoomStep;
            this.updateCanvasTransform();
            this.updateZoomUI();
        },
        
        zoomOut: function() {
            if (this.state.zoom <= this.config.minZoom) return;
            
            this.state.zoom -= this.config.zoomStep;
            this.updateCanvasTransform();
            this.updateZoomUI();
        },
        
        zoomReset: function() {
            this.state.zoom = 1;
            this.updateCanvasTransform();
            this.updateZoomUI();
        },
        
        updateCanvasTransform: function() {
            this.elements.canvas.css('transform', 'scale(' + this.state.zoom + ')');
        },
        
        updateZoomUI: function() {
            this.elements.zoomResetButton.text(Math.round(this.state.zoom * 100) + '%');
        },
        
        // Verlaufsfunktionen (Undo/Redo)
        addHistoryStep: function() {
            // Wenn wir uns nicht am Ende des Verlaufs befinden, alte Schritte löschen
            if (this.state.historyIndex < this.state.history.length - 1) {
                this.state.history.splice(this.state.historyIndex + 1);
            }
            
            // Aktuellen Zustand speichern
            var currentState = JSON.stringify(this.serializeElements());
            
            // Wenn der Zustand dem letzten Schritt entspricht, nicht speichern
            if (this.state.history.length > 0 && currentState === this.state.history[this.state.historyIndex]) {
                return;
            }
            
            // Zum Verlauf hinzufügen
            this.state.history.push(currentState);
            
            // Zu viele Schritte? Älteste entfernen
            if (this.state.history.length > this.state.maxHistorySteps) {
                this.state.history.shift();
            } else {
                this.state.historyIndex++;
            }
            
            // UI aktualisieren
            this.updateUndoRedoUI();
        },
        
        // Serialisiert alle Elemente für den Verlauf
        serializeElements: function() {
            var elements = [];
            
            for (var i = 0; i < this.state.elements.length; i++) {
                var el = this.state.elements[i];
                elements.push({
                    id: el.id,
                    type: el.type,
                    src: el.src,
                    left: el.left,
                    top: el.top,
                    width: el.width,
                    height: el.height,
                    originalWidth: el.originalWidth,
                    originalHeight: el.originalHeight,
                    opacity: el.opacity,
                    rotation: el.rotation,
                    fileReference: el.fileReference
                });
            }
            
            return elements;
        },
        
        // Undo-Funktion
        undo: function() {
            if (this.state.historyIndex <= 0) return;
            
            this.state.historyIndex--;
            this.restoreFromHistory();
            this.updateUndoRedoUI();
        },
        
        // Redo-Funktion
        redo: function() {
            if (this.state.historyIndex >= this.state.history.length - 1) return;
            
            this.state.historyIndex++;
            this.restoreFromHistory();
            this.updateUndoRedoUI();
        },
        
        // Stellt einen Zustand aus dem Verlauf wieder her
// Stellt einen Zustand aus dem Verlauf wieder her
restoreFromHistory: function() {
    if (!this.state.history[this.state.historyIndex]) {
        console.error('Kein gültiger Verlauf für Index:', this.state.historyIndex);
        return;
    }

    var elements = JSON.parse(this.state.history[this.state.historyIndex]);
    
    // Canvas leeren
    this.elements.canvas.empty();
    
    // Elemente-Liste leeren
    this.state.elements = [];
    this.deselectAllElements();
    
    // Elemente wiederherstellen
    for (var i = 0; i < elements.length; i++) {
        var el = elements[i];
        this.addElementToCanvas(el);
    }
},

// Aktualisiert die Undo/Redo-Buttons
updateUndoRedoUI: function() {
    this.elements.undoButton.prop('disabled', this.state.historyIndex <= 0);
    this.elements.redoButton.prop('disabled', this.state.historyIndex >= this.state.history.length - 1);
},

// Vektorisierungsfunktionen
openVectorizeModal: function() {
    if (!this.state.currentElement || this.state.currentElement.type !== 'image') return;
    
    // Original-Bild in die Vorschau laden
    this.elements.previewOriginal.html('<img src="' + this.state.currentElement.src + '" alt="Original">');
    
    // Vorschau zurücksetzen
    this.elements.previewResult.html('<div class="designtool-placeholder">Vorschau wird generiert, sobald die Vektorisierung startet</div>');
    
    // Fortschrittsanzeige verstecken
    this.elements.vectorizeProgress.hide();
    
    // Modal anzeigen
    this.elements.vectorizeModal.addClass('active');
},

closeVectorizeModal: function() {
    this.elements.vectorizeModal.removeClass('active');
},

// Vektorisierung starten
startVectorization: function() {
    var self = this;
    
    if (!this.state.currentElement || this.state.currentElement.type !== 'image') return;
    
    // Fortschrittsanzeige anzeigen
    this.elements.vectorizeProgress.show();
    $('.designtool-progress-bar-inner').css('width', '10%');
    
    // Detailgrad auslesen
    var detailLevel = this.elements.vectorizeDetail.val();
    
    // Formular erstellen
    var formData = new FormData();
    formData.append('action', 'vectorize_designtool_vectorize');
    formData.append('nonce', vectorizeWpFrontend.nonce || '');
    // Füge zusätzlich hinzu, damit wir sehen, ob nonce überhaupt gesetzt ist
    console.log('Nonce verfügbar:', vectorizeWpFrontend.nonce ? 'Ja' : 'Nein');
    formData.append('detail_level', detailLevel);
    
    // Bild aus der Quelle holen
    var img = new Image();
    img.crossOrigin = 'Anonymous';
    img.onload = function() {
        // Canvas erstellen und Bild zeichnen
        var canvas = document.createElement('canvas');
        canvas.width = img.width;
        canvas.height = img.height;
        var ctx = canvas.getContext('2d');
        ctx.drawImage(img, 0, 0);
        
        // In Blob umwandeln
        canvas.toBlob(function(blob) {
            // Zum Formular hinzufügen
            formData.append('vectorize_image', blob, 'image.png');
            
            console.log('Sende Vektorisierungsanfrage...');
            
            // AJAX-Anfrage senden
            $.ajax({
                url: vectorizeWpFrontend.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = Math.round((e.loaded / e.total) * 25); // Upload ist 25% des Fortschritts
                            $('.designtool-progress-bar-inner').css('width', percent + '%');
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    console.log('AJAX-Antwort erhalten:', response);
                    
                    if (response.success) {
                        // Fortschritt simulieren
                        $('.designtool-progress-bar-inner').css('width', '60%');
                        
                        setTimeout(function() {
                            $('.designtool-progress-bar-inner').css('width', '100%');
                            
                            // SVG in der Vorschau anzeigen
                            if (response.data && response.data.svg) {
                                self.elements.previewResult.html(response.data.svg);
                                
                                // SVG zum Canvas hinzufügen
                                self.addSVGToCanvas(response.data.svg);
                                
                                // Hinweis zeigen, wenn Testmodus aktiv
                                if (response.data.is_test_mode) {
                                    var testModeMsg = 'Testmodus aktiv: ';
                                    if (response.data.test_mode === 'test') {
                                        testModeMsg += 'Kostenloser Test ohne Kontingentverbrauch.';
                                    } else if (response.data.test_mode === 'test_preview') {
                                        testModeMsg += 'Test mit Vorschau ohne Kontingentverbrauch.';
                                    }
                                    alert(testModeMsg);
                                }
                                
                                // Modal schließen
                                setTimeout(function() {
                                    self.closeVectorizeModal();
                                }, 1000);
                            } else {
                                self.elements.previewResult.html('<div class="designtool-placeholder">Fehler: Keine SVG-Daten erhalten</div>');
                            }
                        }, 500);
                    } else {
                        // Fehler anzeigen
                        $('.designtool-progress-bar-inner').css('width', '0%');
                        self.elements.previewResult.html('<div class="designtool-placeholder">Fehler: ' + (response.data || 'Unbekannter Fehler') + '</div>');
                        console.error('Vektorisierungsfehler:', response);
                        
                        // Detaillierte Fehlermeldung anzeigen
                        alert('Fehler bei der Vektorisierung: ' + (response.data || 'Unbekannter Fehler'));
                    }
                },
                error: function(xhr, status, error) {
                    $('.designtool-progress-bar-inner').css('width', '0%');
                    self.elements.previewResult.html('<div class="designtool-placeholder">Fehler bei der AJAX-Anfrage: ' + error + '</div>');
                    console.error('AJAX-Fehler:', xhr.responseText);
                    alert('Fehler bei der AJAX-Anfrage: ' + error);
                }
            });
        }, 'image/png');
    };
    img.onerror = function() {
        self.elements.previewResult.html('<div class="designtool-placeholder">Fehler beim Laden des Bildes</div>');
        console.error('Fehler beim Laden des Bildes:', self.state.currentElement.src);
        alert('Fehler beim Laden des Bildes. Bitte versuche es mit einem anderen Bild.');
    };
    img.src = this.state.currentElement.src;
},

// Fügt ein SVG zum Canvas hinzu (nach der Vektorisierung)
addSVGToCanvas: function(svgContent) {
    var blob = new Blob([svgContent], {type: 'image/svg+xml'});
    var url = URL.createObjectURL(blob);
    
    // Ursprüngliche Position und Größe übernehmen
    var originalElement = this.state.currentElement;
    
    // Element zum Canvas hinzufügen
    this.addElementToCanvas({
        id: 'element-' + this.state.elementCounter,
        type: 'svg',
        src: url,
        left: originalElement.left,
        top: originalElement.top,
        width: originalElement.width,
        height: originalElement.height,
        originalWidth: originalElement.width,
        originalHeight: originalElement.height,
        opacity: 1,
        rotation: 0,
        fileReference: 'file-' + this.state.elementCounter
    });
    
    // SVG zur Dateiliste hinzufügen
    this.addFileToList({
        id: 'file-' + this.state.elementCounter,
        name: 'Vektorisiert ' + (this.state.elementCounter + 1) + '.svg',
        type: 'image/svg+xml',
        size: '?? KB',
        url: url,
        isSVG: true
    });
    
    this.state.elementCounter++;
    
    // Im Verlauf speichern
    this.addHistoryStep();
},

// Exportiert den Canvas
exportCanvas: function() {
    var self = this;
    var format = this.elements.exportFormat.val();
    
    // Canvas-Element erstellen
    var canvas = document.createElement('canvas');
    canvas.width = this.config.canvasWidth;
    canvas.height = this.config.canvasHeight;
    var ctx = canvas.getContext('2d');
    
    // Bei JPG weißen Hintergrund zeichnen
    if (format === 'jpg') {
        ctx.fillStyle = '#ffffff';
        ctx.fillRect(0, 0, canvas.width, canvas.height);
    }
    
    // Elemente der Reihe nach zeichnen
    var elementsToRender = [];
    
    // Elemente nach Z-Index sortieren (Position im DOM)
    this.elements.canvas.find('.designtool-element').each(function() {
        var elementId = $(this).attr('id');
        var element = self.findElementById(elementId);
        if (element) {
            elementsToRender.push(element);
        }
    });
    
    // SVG-Export oder Bild-Export
    if (format === 'svg') {
        this.exportAsSVG(elementsToRender);
    } else {
        this.exportAsImage(canvas, ctx, elementsToRender, format);
    }
},

// Exportiert als SVG
exportAsSVG: function(elements) {
    var self = this;
    
    // Neuen SVG-String erstellen
    var svgContent = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" width="' + this.config.canvasWidth + '" height="' + this.config.canvasHeight + '" viewBox="0 0 ' + this.config.canvasWidth + ' ' + this.config.canvasHeight + '">';
    
    // Zähler für geladene Elemente
    var elementsLoaded = 0;
    var totalElements = elements.length;
    
    // Funktion, die prüft, ob alle Elemente geladen wurden
    function checkAllLoaded() {
        if (elementsLoaded === totalElements) {
            // SVG abschließen und herunterladen
            svgContent += '</svg>';
            self.downloadFile(svgContent, 'designtool-export.svg', 'image/svg+xml');
        }
    }
    
    // Wenn keine Elemente vorhanden sind, leere SVG herunterladen
    if (totalElements === 0) {
        svgContent += '</svg>';
        this.downloadFile(svgContent, 'designtool-export.svg', 'image/svg+xml');
        return;
    }
    
    // Elemente zum SVG hinzufügen
    elements.forEach(function(element, index) {
        if (element.type === 'image') {
            // Bild als eingebettetes Bild hinzufügen
            var posX = element.left;
            var posY = element.top;
            var width = element.width;
            var height = element.height;
            
            // Neues Image erstellen und laden
            var img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = function() {
                // Canvas erstellen
                var canvas = document.createElement('canvas');
                canvas.width = img.width;
                canvas.height = img.height;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(img, 0, 0);
                
                // Als Data-URL einfügen
                var dataURL = canvas.toDataURL('image/png');
                svgContent += '<image x="' + posX + '" y="' + posY + '" width="' + width + '" height="' + height + '" xlink:href="' + dataURL + '" />';
                
                // Element als geladen markieren
                elementsLoaded++;
                checkAllLoaded();
            };
            img.onerror = function() {
                // Fehler beim Laden
                elementsLoaded++;
                checkAllLoaded();
            };
            img.src = element.src;
        } else if (element.type === 'svg') {
            // SVG als Gruppe einfügen
            var posX = element.left;
            var posY = element.top;
            var scaleX = element.width / element.originalWidth;
            var scaleY = element.height / element.originalHeight;
            
            // SVG laden
            $.get(element.src, function(svgData) {
                var $svg = $(svgData);
                var $svgElement = $svg.find('svg');
                if ($svgElement.length === 0) $svgElement = $svg;
                
                // Viewbox auslesen
                var viewBox = $svgElement.attr('viewBox');
                if (!viewBox) {
                    viewBox = '0 0 ' + $svgElement.attr('width') + ' ' + $svgElement.attr('height');
                }
                
                // Inhalt extrahieren (ohne äußeres svg-Element)
                var content = $svgElement.html();
                
                svgContent += '<g transform="translate(' + posX + ',' + posY + ') scale(' + scaleX + ',' + scaleY + ')">';
                svgContent += '<svg viewBox="' + viewBox + '">' + content + '</svg>';
                svgContent += '</g>';
                
                // Element als geladen markieren
                elementsLoaded++;
                checkAllLoaded();
            }).fail(function() {
                // Fehler beim Laden
                elementsLoaded++;
                checkAllLoaded();
            });
        } else {
            // Unbekannter Typ, überspringen
            elementsLoaded++;
            checkAllLoaded();
        }
    });
},

// Exportiert als Bild (PNG/JPG)
exportAsImage: function(canvas, ctx, elements, format) {
    var self = this;
    
    // Zähler für geladene Elemente
    var elementsLoaded = 0;
    var totalElements = elements.length;
    
    // Funktion, die prüft, ob alle Elemente geladen wurden
    function checkAllLoaded() {
        if (elementsLoaded === totalElements) {
            // Canvas als Bild herunterladen
            var mime = (format === 'jpg') ? 'image/jpeg' : 'image/png';
            canvas.toBlob(function(blob) {
                var url = URL.createObjectURL(blob);
                self.elements.downloadLink.attr('href', url);
                self.elements.downloadLink.attr('download', 'designtool-export.' + format);
                self.elements.downloadLink[0].click();
                URL.revokeObjectURL(url);
            }, mime);
        }
    }
    
    // Wenn keine Elemente vorhanden sind, leeres Canvas herunterladen
    if (totalElements === 0) {
        var mime = (format === 'jpg') ? 'image/jpeg' : 'image/png';
        canvas.toBlob(function(blob) {
            var url = URL.createObjectURL(blob);
            self.elements.downloadLink.attr('href', url);
            self.elements.downloadLink.attr('download', 'designtool-export.' + format);
            self.elements.downloadLink[0].click();
            URL.revokeObjectURL(url);
        }, mime);
        return;
    }
    
    // Elemente auf den Canvas zeichnen
    elements.forEach(function(element) {
        if (element.type === 'image') {
            // Bild zeichnen
            var img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = function() {
                ctx.globalAlpha = element.opacity;
                ctx.drawImage(img, element.left, element.top, element.width, element.height);
                ctx.globalAlpha = 1;
                
                // Element als geladen markieren
                elementsLoaded++;
                checkAllLoaded();
            };
            img.onerror = function() {
                // Fehler beim Laden, trotzdem weitermachen
                elementsLoaded++;
                checkAllLoaded();
            };
            img.src = element.src;
        } else if (element.type === 'svg') {
            // SVG zeichnen
            var img = new Image();
            img.crossOrigin = 'Anonymous';
            img.onload = function() {
                ctx.globalAlpha = element.opacity;
                ctx.drawImage(img, element.left, element.top, element.width, element.height);
                ctx.globalAlpha = 1;
                
                // Element als geladen markieren
                elementsLoaded++;
                checkAllLoaded();
            };
            img.onerror = function() {
                // Fehler beim Laden, trotzdem weitermachen
                elementsLoaded++;
                checkAllLoaded();
            };
            img.src = element.src;
        } else {
            // Unbekannter Typ, überspringen
            elementsLoaded++;
            checkAllLoaded();
        }
    });
},

// Hilfsfunktion zum Herunterladen einer Datei
downloadFile: function(content, filename, mime) {
    var blob = new Blob([content], {type: mime});
    var url = URL.createObjectURL(blob);
    this.elements.downloadLink.attr('href', url);
    this.elements.downloadLink.attr('download', filename);
    this.elements.downloadLink[0].click();
    URL.revokeObjectURL(url);
},

// Debug-Funktionen für Layout-Probleme
debugLayout: function() {
    var self = this;
    console.log('=== Design Tool Layout Debug ===');
    
    // Container-Informationen ausgeben
    var $container = $('.designtool-container');
    console.log('Container:', {
        width: $container.width(),
        height: $container.height(),
        visible: $container.is(':visible'),
        display: $container.css('display'),
        position: $container.css('position')
    });
    
    // Alle drei Hauptbereiche überprüfen
    ['sidebar', 'main', 'properties'].forEach(function(area) {
        var $element = $('.designtool-' + area);
        console.log(area + ':', {
            width: $element.width(),
            height: $element.height(),
            visible: $element.is(':visible'),
            display: $element.css('display'),
            overflow: $element.css('overflow')
        });
    });
    
    // Debugging-Klasse zum Container hinzufügen/entfernen
    $container.toggleClass('debug-mode');
    
    // Wenn die Bereiche nicht richtig angezeigt werden, Try-Fix anwenden
    if ($('.designtool-main').height() < 100 || $('.designtool-sidebar').height() < 100) {
        console.log('Layout-Problem erkannt, versuche zu beheben...');
        this.fixLayout();
    }
    
    return 'Debug-Modus ' + ($container.hasClass('debug-mode') ? 'aktiviert' : 'deaktiviert');
},

// Layout-Fix anwenden
fixLayout: function() {
    console.log('Versuche Layout zu reparieren...');
    
    // Prüfen, ob der Haupt-Container überhaupt existiert
    if ($('.designtool-container').length === 0) {
        console.log('Haupt-Container fehlt, versuche ihn zu erstellen...');
        
        // Versuche, die Hauptstruktur zu erstellen, falls sie fehlt
        var $container = $('<div class="designtool-container"></div>');
        var $sidebar = $('<div class="designtool-sidebar"></div>');
        var $main = $('<div class="designtool-main"></div>');
        var $properties = $('<div class="designtool-properties"></div>');
        
        // Bestehende Inhalte finden und umstrukturieren
        var $existingToolbar = $('.designtool-toolbar');
        if ($existingToolbar.length > 0) {
            // Toolbar wurde gefunden, versuche den Rest zu rekonstruieren
            $main.append($existingToolbar);
            $main.append('<div class="designtool-canvas-container"><div id="designtool-canvas" class="designtool-canvas"></div></div>');
            
            // Wrap in den Body oder einen existierenden Container einfügen
            var $parent = $existingToolbar.parent();
            $existingToolbar.remove();
            
            $container.append($sidebar).append($main).append($properties);
            $parent.append($container);
            
            console.log('Fehlende Container-Struktur wurde erstellt');
        } else {
            console.error('Konnte Toolbar nicht finden, kann Layout nicht reparieren');
            return;
        }
    }
    
    // Container-Fix
    $('.designtool-container').css({
        'display': 'grid',
        'grid-template-columns': '200px 1fr 280px',
        'grid-template-rows': '100vh',
        'height': '100vh',
        'position': 'relative',
        'z-index': '999999' // Erhöht für bessere Sichtbarkeit
    });
    
    // Hauptbereiche fixen
    $('.designtool-sidebar, .designtool-main, .designtool-properties').css({
        'position': 'relative',
        'overflow': 'hidden',
        'display': 'flex',
        'flex-direction': 'column',
        'height': '100vh',
        'z-index': '999999' // Erhöht für bessere Sichtbarkeit
    });
    
    // Toolbar fixen
    $('.designtool-toolbar').css({
        'height': '48px',
        'flex-shrink': '0',
        'z-index': '999999' // Erhöht für bessere Sichtbarkeit
    });
    
    // Canvas-Container fixen
    $('.designtool-canvas-container').css({
        'flex': '1',
        'overflow': 'hidden',
        'z-index': '999998' // Erhöht für bessere Sichtbarkeit
    });
    
    // Verhindern, dass andere Elemente das Tool überdecken
    $('body > *:not(.designtool-container)').css('z-index', '1');
    
    // Sidebar und Properties-Panel mit Basis-Struktur füllen, falls leer
    if ($('.designtool-sidebar').children().length === 0) {
        $('.designtool-sidebar').html(
            '<div class="designtool-sidebar-header"><h3>Dateien</h3></div>' +
            '<div class="designtool-upload">' +
            '<button id="designtool-upload-btn" class="designtool-btn designtool-btn-primary">Bild hinzufügen</button>' +
            '<input type="file" id="designtool-file-input" accept="image/*" style="display: none;">' +
            '</div>' +
            '<div class="designtool-filelist">' +
            '<div class="designtool-filelist-header">Dateiliste</div>' +
            '<div id="designtool-files" class="designtool-files">' +
            '<div class="designtool-empty-notice">Keine Dateien vorhanden. Lade ein Bild hoch, um zu starten.</div>' +
            '</div></div>'
        );
    }
    
    if ($('.designtool-properties').children().length === 0) {
        $('.designtool-properties').html(
            '<div class="designtool-properties-header"><h3>Eigenschaften</h3></div>' +
            '<div class="designtool-properties-content">' +
            '<div id="designtool-no-selection"><p>Kein Element ausgewählt.</p></div>' +
            '</div>'
        );
    }
    
    // Cookie-Banner ausblenden (falls vorhanden)
    $('.cky-consent-container, .cky-modal, .cky-preference-center').css({
        'display': 'none',
        'z-index': '1'
    });
    
    console.log('Layout-Fix angewendet.');
    
    // Neu initialisieren, nachdem die Struktur repariert wurde
    this.initializeComponents();
}
};
})(jQuery);