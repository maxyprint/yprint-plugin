<?php
/**
 * Design Tool Canvas Content Template
 * 
 * Dieses Template enthält nur den Inhalt des Design Tools ohne die HTML-Struktur.
 * Es wird sowohl vom Shortcode als auch von der Template-Seite verwendet.
 * 
 * @package VectorizeWP
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}
?>

<!-- Linke Seitenleiste (Dateimanager) -->
<div class="designtool-sidebar">
    <div class="designtool-sidebar-header">
        <h3><?php _e('Dateien', 'vectorize-wp'); ?></h3>
    </div>
    
    <div class="designtool-upload">
        <button id="designtool-upload-btn" class="designtool-btn designtool-btn-primary">
            <svg viewBox="0 0 24 24" width="16" height="16"><path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z" fill="currentColor"/></svg>
            <?php _e('Bild hinzufügen', 'vectorize-wp'); ?>
        </button>
        <input type="file" id="designtool-file-input" accept="image/*" style="display: none;">
    </div>
    
    <div class="designtool-filelist">
        <div class="designtool-filelist-header">
            <?php _e('Dateiliste', 'vectorize-wp'); ?>
        </div>
        <div id="designtool-files" class="designtool-files">
            <div class="designtool-empty-notice">
                <?php _e('Keine Dateien vorhanden. Lade ein Bild hoch, um zu starten.', 'vectorize-wp'); ?>
            </div>
        </div>
    </div>
</div>

<!-- Hauptbereich mit Canvas -->
<div class="designtool-main">
    <div class="designtool-toolbar">
        <div class="designtool-toolbar-group">
            <button id="designtool-undo-btn" class="designtool-btn" disabled>
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M12.5 8c-2.65 0-5.05.99-6.9 2.6L2 7v9h9l-3.62-3.62c1.39-1.16 3.16-1.88 5.12-1.88 3.54 0 6.55 2.31 7.6 5.5l2.37-.78C21.08 11.03 17.15 8 12.5 8z" fill="currentColor"/></svg>
                <?php _e('Rückgängig', 'vectorize-wp'); ?>
            </button>
            <button id="designtool-redo-btn" class="designtool-btn" disabled>
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M18.4 10.6C16.55 8.99 14.15 8 11.5 8c-4.65 0-8.58 3.03-9.96 7.22L3.9 16c1.05-3.19 4.05-5.5 7.6-5.5 1.95 0 3.73.72 5.12 1.88L13 16h9V7l-3.6 3.6z" fill="currentColor"/></svg>
                <?php _e('Wiederholen', 'vectorize-wp'); ?>
            </button>
        </div>
        
        <div class="designtool-toolbar-group">
            <button id="designtool-zoom-in-btn" class="designtool-btn">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14zm2.5-4h-2v2H9v-2H7V9h2V7h1v2h2v1z" fill="currentColor"/></svg>
            </button>
            <button id="designtool-zoom-reset-btn" class="designtool-btn">100%</button>
            <button id="designtool-zoom-out-btn" class="designtool-btn">
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M15.5 14h-.79l-.28-.27C15.41 12.59 16 11.11 16 9.5 16 5.91 13.09 3 9.5 3S3 5.91 3 9.5 5.91 16 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14zM7 9h5v1H7z" fill="currentColor"/></svg>
            </button>
        </div>
        
        <div class="designtool-toolbar-group">
            <button id="designtool-vectorize-btn" class="designtool-btn" disabled>
                <svg viewBox="0 0 24 24" width="16" height="16"><path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zM20.71 7.04c.39-.39.39-1.02 0-1.41l-2.34-2.34c-.39-.39-1.02-.39-1.41 0l-1.83 1.83 3.75 3.75 1.83-1.83z" fill="currentColor"/></svg>
                <?php _e('Vektorisieren', 'vectorize-wp'); ?>
            </button>
        </div>
    </div>
    
    <div class="designtool-canvas-container">
        <div id="designtool-canvas" class="designtool-canvas"></div>
    </div>
</div>

<!-- Eigenschaften-Bereich rechts -->
<div class="designtool-properties">
    <div class="designtool-properties-header">
        <h3><?php _e('Eigenschaften', 'vectorize-wp'); ?></h3>
    </div>
    
    <div class="designtool-properties-content">
        <!-- Keine Auswahl -->
        <div id="designtool-no-selection">
            <p><?php _e('Kein Element ausgewählt.', 'vectorize-wp'); ?></p>
            <p><?php _e('Klicke auf ein Element im Canvas, um seine Eigenschaften zu bearbeiten.', 'vectorize-wp'); ?></p>
        </div>
        
        <!-- Bild-Eigenschaften -->
        <div id="designtool-image-properties" style="display: none;">
        <div class="designtool-property-group">
    <h4><?php _e('Größe & Position', 'vectorize-wp'); ?></h4>
    
    <div class="designtool-property">
        <label for="designtool-image-width"><?php _e('Breite', 'vectorize-wp'); ?></label>
        <div class="designtool-input-group">
            <input type="number" id="designtool-image-width" class="designtool-input" min="10" step="1">
            <span class="designtool-input-suffix">px</span>
        </div>
    </div>
    
    <div class="designtool-property">
        <label for="designtool-image-height"><?php _e('Höhe', 'vectorize-wp'); ?></label>
        <div class="designtool-input-group">
            <input type="number" id="designtool-image-height" class="designtool-input" min="10" step="1">
            <span class="designtool-input-suffix">px</span>
        </div>
    </div>
    
    <div class="designtool-property">
        <button id="designtool-reset-aspect" class="designtool-btn">
            <?php _e('Seitenverhältnis zurücksetzen', 'vectorize-wp'); ?>
        </button>
    </div>
    
    <div class="designtool-property">
        <label for="designtool-image-opacity"><?php _e('Deckkraft', 'vectorize-wp'); ?></label>
        <div class="designtool-input-group" style="display: flex; align-items: center;">
            <input type="range" id="designtool-image-opacity" class="designtool-range" min="0" max="1" step="0.01" value="1">
            <span id="designtool-opacity-value" class="designtool-input-display">100%</span>
        </div>
    </div>
</div>
        </div>
        
        <!-- SVG-Eigenschaften -->
        <div id="designtool-svg-properties" style="display: none;">
            <div class="designtool-property-group">
                <h4><?php _e('SVG-Eigenschaften', 'vectorize-wp'); ?></h4>
                
                <div class="designtool-property">
                    <label for="designtool-svg-color"><?php _e('Hauptfarbe', 'vectorize-wp'); ?></label>
                    <input type="color" id="designtool-svg-color" class="designtool-color-picker" value="#000000">
                </div>
            </div>
        </div>
    </div>
    
    <div class="designtool-export">
        <h4><?php _e('Export', 'vectorize-wp'); ?></h4>
        <div class="designtool-export-options">
            <select id="designtool-export-format" class="designtool-select">
                <option value="svg"><?php _e('SVG (Vektor)', 'vectorize-wp'); ?></option>
                <option value="png"><?php _e('PNG (mit Transparenz)', 'vectorize-wp'); ?></option>
                <option value="jpg"><?php _e('JPG (mit weißem Hintergrund)', 'vectorize-wp'); ?></option>
            </select>
            <button id="designtool-export-btn" class="designtool-btn designtool-btn-primary">
                <?php _e('Exportieren', 'vectorize-wp'); ?>
            </button>
            <a id="designtool-download-link" style="display: none;"></a>
        </div>
    </div>
</div>

<!-- Vektorisierungs-Modal -->
<div id="designtool-vectorize-modal" class="designtool-modal">
    <div class="designtool-modal-content">
        <div class="designtool-modal-header">
            <h3><?php _e('Bild vektorisieren', 'vectorize-wp'); ?></h3>
            <button class="designtool-modal-close">&times;</button>
        </div>
        
        <div class="designtool-modal-body">
            <div class="designtool-preview">
                <div class="designtool-preview-original">
                    <h4><?php _e('Original', 'vectorize-wp'); ?></h4>
                    <div id="designtool-preview-original-img"></div>
                </div>
                
                <div class="designtool-preview-result">
                    <h4><?php _e('Vektorisiert', 'vectorize-wp'); ?></h4>
                    <div id="designtool-preview-result-img">
                        <div class="designtool-placeholder"><?php _e('Vorschau wird generiert, sobald die Vektorisierung startet', 'vectorize-wp'); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="designtool-property">
                <label for="designtool-vectorize-detail"><?php _e('Detailgrad', 'vectorize-wp'); ?></label>
                <select id="designtool-vectorize-detail" class="designtool-select">
                    <option value="low"><?php _e('Niedrig (weniger Details, kleinere Datei)', 'vectorize-wp'); ?></option>
                    <option value="medium" selected><?php _e('Mittel', 'vectorize-wp'); ?></option>
                    <option value="high"><?php _e('Hoch (mehr Details, größere Datei)', 'vectorize-wp'); ?></option>
                </select>
            </div>
            
            <div id="designtool-vectorize-progress" class="designtool-progress" style="display: none;">
                <div class="designtool-progress-bar">
                    <div class="designtool-progress-bar-inner"></div>
                </div>
                <div class="designtool-progress-text"><?php _e('Vektorisierung läuft...', 'vectorize-wp'); ?></div>
            </div>
        </div>
        
        <div class="designtool-modal-footer">
            <button id="designtool-vectorize-cancel" class="designtool-btn"><?php _e('Abbrechen', 'vectorize-wp'); ?></button>
            <button id="designtool-vectorize-start" class="designtool-btn designtool-btn-primary"><?php _e('Vektorisieren', 'vectorize-wp'); ?></button>
        </div>
    </div>
</div>