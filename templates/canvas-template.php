<?php
/**
 * Template Name: Canvas Tool
 * Description: Ein vollständiges Template für die Vectorize Canvas-Anwendung.
 * 
 * @package VectorizeWP
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="canvas-tool-html">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php wp_title('|', true, 'right'); ?> <?php bloginfo('name'); ?></title>
    <?php wp_head(); ?>
    <style type="text/css">
        /* Inline kritische Stile, um Flackern zu vermeiden */
        html, body { margin: 0; padding: 0; overflow: hidden; height: 100%; }
        body { background-color: #f0f0f0; }
        
        /* WordPress-Admin-Bar anpassen */
        html.canvas-tool-html { margin-top: 0 !important; }
        #wpadminbar { display: none; }
    </style>
</head>

<body <?php body_class('canvas-page'); ?>>
<?php wp_body_open(); ?>

<div class="vectorize-wp-editor-container">
    <div class="vectorize-wp-editor-wrapper">
        <!-- Linke Toolbar -->
        <div class="vectorize-wp-toolbar">
            <div class="vectorize-wp-tool-group">
                <div class="vectorize-wp-tool-title">Werkzeuge</div>
                
                <!-- Dateiupload-Button -->
                <div class="vectorize-wp-tool" id="vectorize-upload-tool" title="PNG hochladen">
                    <svg viewBox="0 0 24 24" width="20" height="20"><path d="M19.35 10.04C18.67 6.59 15.64 4 12 4 9.11 4 6.6 5.64 5.35 8.04 2.34 8.36 0 10.91 0 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96zM14 13v4h-4v-4H7l5-5 5 5h-3z" fill="currentColor"/></svg>
                </div>
                
                <!-- Vektorisierungs-Button -->
                <div class="vectorize-wp-tool" id="vectorize-convert-tool" title="PNG vektorisieren">
                    <svg viewBox="0 0 24 24" width="20" height="20"><path d="M19 3H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 14l-5-5 1.41-1.41L11 13.17l7.59-7.59L20 7l-8 8z" fill="currentColor"/></svg>
                </div>
                
                <!-- Verborgenes Datei-Input-Feld -->
                <input type="file" id="vectorize-file-input" accept="image/png" style="display: none;" />
            </div>
        </div>
        
        <!-- Hauptkanvas-Bereich -->
        <div class="vectorize-wp-canvas-area">
            <div id="svg-editor-container" class="vectorize-wp-canvas">
                <!-- Platzhalter-SVG -->
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 600">
                    <rect x="0" y="0" width="800" height="600" fill="#f0f0f0"/>
                    <text x="400" y="300" font-family="Arial" font-size="24" text-anchor="middle">PNG hier hochladen</text>
                </svg>
            </div>
        </div>
    </div>
</div>

<?php
// Sicherstellen, dass die erforderlichen Scripts geladen sind
wp_enqueue_script('jquery');
wp_enqueue_script('vectorize-wp-frontend');
wp_enqueue_style('vectorize-wp-frontend');
?>

<?php wp_footer(); ?>
</body>
</html>