<?php
// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap vectorize-wp-admin">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <!-- API-Schlüssel Warnung -->
    <?php 
    $options = get_option('vectorize_wp_options', array());
    $api_key = isset($options['api_key']) ? $options['api_key'] : '';
    
    if (empty($api_key)) {
        echo '<div class="notice notice-warning is-dismissible"><p>';
        echo sprintf(
            __('Bitte konfiguriere deinen API-Schlüssel in den <a href="%s">Einstellungen</a>.', 'vectorize-wp'),
            admin_url('admin.php?page=vectorize-wp-settings')
        );
        echo '</p></div>';
    }
    ?>
    
    <div class="vectorize-wp-container">
        <div class="vectorize-wp-upload-section">
            <h2><?php _e('Bild in SVG konvertieren', 'vectorize-wp'); ?></h2>
            
            <div class="vectorize-wp-upload-box">
                <!-- Vereinfachtes Upload-Formular ohne komplexe JavaScript-Interaktionen -->
                <form id="vectorize-upload-form" method="post" enctype="multipart/form-data">
                    <?php wp_nonce_field('vectorize_wp_upload', 'vectorize_wp_upload_nonce'); ?>
                    
                    <p><?php _e('Wähle ein Bild aus, um es zu vektorisieren:', 'vectorize-wp'); ?></p>
                    
                    <!-- Einfaches File-Input ohne Drag & Drop -->
                    <input type="file" id="vectorize-file-input" name="vectorize_image" accept="image/*" style="margin-bottom: 15px; display: block;" />
                    
                    <!-- Bild-Vorschau (wird per JavaScript angezeigt) -->
                    <div id="vectorize-image-preview" style="display: none; margin: 15px 0;">
                        <h3><?php _e('Bildvorschau', 'vectorize-wp'); ?></h3>
                        <div style="max-width: 100%; text-align: center; border: 1px solid #ddd; padding: 10px; background-color: #f9f9f9;">
                            <img id="vectorize-preview-image" src="" alt="Vorschau" style="max-width: 100%; max-height: 300px; display: block; margin: 0 auto;" />
                        </div>
                    </div>
                    
                    <!-- Konvertierungsoptionen -->
                    <div id="vectorize-options" style="margin-top: 15px;">
                        <h3><?php _e('Konvertierungsoptionen', 'vectorize-wp'); ?></h3>
                        
                        <div style="margin-bottom: 15px;">
                            <label for="vectorize-detail-level" style="display: inline-block; width: 120px; font-weight: 500;">
                                <?php _e('Detailgrad', 'vectorize-wp'); ?>
                            </label>
                            <select id="vectorize-detail-level" name="detail_level">
                                <option value="low"><?php _e('Niedrig', 'vectorize-wp'); ?></option>
                                <option value="medium" selected><?php _e('Mittel', 'vectorize-wp'); ?></option>
                                <option value="high"><?php _e('Hoch', 'vectorize-wp'); ?></option>
                            </select>
                        </div>
                        
                        <div style="margin-top: 15px;">
                            <button type="button" id="vectorize-start-conversion" class="button button-primary">
                                <?php _e('Vektorisieren', 'vectorize-wp'); ?>
                            </button>
                        </div>
                    </div>
                </form>
                
                <!-- Fortschrittsanzeige (wird per JavaScript angezeigt) -->
                <div id="vectorize-progress" style="display: none; margin-top: 20px;">
                    <h3><?php _e('Fortschritt', 'vectorize-wp'); ?></h3>
                    <div style="height: 20px; background-color: #f1f1f1; border-radius: 10px; overflow: hidden; margin-bottom: 10px;">
                        <div class="vectorize-progress-bar-inner" style="height: 100%; width: 0; background-color: #007cba; transition: width 0.3s ease;"></div>
                    </div>
                    <p class="vectorize-progress-message" style="text-align: center; font-style: italic; color: #555;">
                        <?php _e('Vorbereitung...', 'vectorize-wp'); ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Ergebnisbereich (wird per JavaScript angezeigt) -->
        <div class="vectorize-wp-result-section" id="vectorize-result-section" style="display: none; flex: 1; min-width: 300px; background: #fff; border-radius: 4px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 20px;">
            <h2><?php _e('Vektorisierungsergebnis', 'vectorize-wp'); ?></h2>
            
            <div style="margin-top: 15px; border: 1px solid #ddd; min-height: 400px; background-color: #f9f9f9;">
                <div id="svg-editor-container" style="width: 100%; height: 400px;"></div>
            </div>
            
            <div style="margin-top: 15px; text-align: right;">
                <button type="button" id="vectorize-save-media" class="button button-primary">
                    <?php _e('In Mediathek speichern', 'vectorize-wp'); ?>
                </button>
                <button type="button" id="vectorize-download" class="button button-secondary">
                    <?php _e('SVG herunterladen', 'vectorize-wp'); ?>
                </button>

                <div style="margin-top: 15px; text-align: right;">
    <button type="button" id="vectorize-edit-svg" class="button button-secondary">
        <?php _e('SVG bearbeiten', 'vectorize-wp'); ?>
    </button>
    <button type="button" id="vectorize-save-media" class="button button-primary">
        <?php _e('In Mediathek speichern', 'vectorize-wp'); ?>
    </button>
    <button type="button" id="vectorize-download" class="button button-secondary">
        <?php _e('SVG herunterladen', 'vectorize-wp'); ?>
    </button>
</div>

<!-- Dann im JavaScript-Bereich der admin-page.php folgendes hinzufügen: -->

// SVG bearbeiten Funktion entfernt
/*
$('#vectorize-edit-svg').on('click', function() {
    var svgContent = $('#svg-editor-container').html();
    if (!svgContent) {
        alert('Kein SVG-Inhalt vorhanden zum Bearbeiten.');
        return;
    }
    
    // URL zum SVG-Editor mit dem aktuellen SVG-Inhalt erstellen
    var editorUrl = '<?php echo admin_url('admin.php?page=vectorize-wp-svg-editor'); ?>';
    editorUrl += '&svg_content=' + encodeURIComponent(svgContent);
    editorUrl += '&return_url=' + encodeURIComponent(window.location.href);
    
    // Zum SVG-Editor navigieren
    window.location.href = editorUrl;
});
*/
            </div>
        </div>
    </div>
    
    <!-- Minimales JavaScript -->
    <script type="text/javascript">
// Sicherheitscheck für reset-form (mit verbesserter Fehlerbehandlung)
document.addEventListener('DOMContentLoaded', function() {
    var resetForm = document.getElementById('reset-form');
    // Nur wenn das Element existiert, einen Event-Listener hinzufügen
    if (resetForm) {
        console.log('Reset-Form gefunden, füge Event-Listener hinzu');
        resetForm.addEventListener('submit', function(e) {
            // Event-Handler-Logik hier
        });
    } else {
        console.log('Reset-Form nicht gefunden, überspringe Event-Listener');
    }
    
    // Design Tool Initialisierung ausführen, falls noch nicht geschehen
    if (typeof DesignTool !== 'undefined' && !window._designtoolInitialized) {
        console.log('Manuell Design Tool initialisieren');
        DesignTool.init();
    }
});
        
        // Vektorisieren-Button
        $('#vectorize-start-conversion').on('click', function() {
            var fileInput = $('#vectorize-file-input')[0];
            if (!fileInput.files || !fileInput.files[0]) {
                alert('Bitte wähle zuerst ein Bild aus.');
                return;
            }
            
            $('#vectorize-options').hide();
            $('#vectorize-progress').show();
            
            // Simulierte Fortschrittsanzeige (für Demo)
            var progressBar = $('.vectorize-progress-bar-inner');
            var progressMsg = $('.vectorize-progress-message');
            
            progressBar.css('width', '10%');
            progressMsg.text('Bild wird hochgeladen...');
            
            setTimeout(function() {
                progressBar.css('width', '30%');
                progressMsg.text('Bild wird analysiert...');
                
                setTimeout(function() {
                    progressBar.css('width', '60%');
                    progressMsg.text('Vektorisierung läuft...');
                    
                    setTimeout(function() {
                        progressBar.css('width', '90%');
                        progressMsg.text('SVG wird erstellt...');
                        
                        setTimeout(function() {
                            progressBar.css('width', '100%');
                            progressMsg.text('Fertig!');
                            
                            // Demo-SVG anzeigen
                            $('#svg-editor-container').html('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><circle cx="50" cy="50" r="40" stroke="black" stroke-width="2" fill="red" /></svg>');
                            $('#vectorize-result-section').show();
                        }, 500);
                    }, 800);
                }, 800);
            }, 800);
            
            // Im echten Plugin würde hier ein AJAX-Aufruf stehen
        });
        
        // In Mediathek speichern
        $('#vectorize-save-media').on('click', function() {
            alert('Diese Funktion ist in der Demo-Version nicht aktiv.');
        });
        
        // SVG herunterladen
        $('#vectorize-download').on('click', function() {
            alert('Diese Funktion ist in der Demo-Version nicht aktiv.');
        });
    });
    </script>
</div>