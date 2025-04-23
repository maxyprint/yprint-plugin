<?php
// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap vectorize-wp-svg-editor-page">
    <h1><?php _e('SVG-Editor', 'vectorize-wp'); ?></h1>
    
    <div class="vectorize-wp-editor-header">
        <div class="vectorize-wp-editor-actions">
            <button type="button" id="svg-editor-save" class="button button-primary">
                <?php _e('Änderungen speichern', 'vectorize-wp'); ?>
            </button>
            <a href="<?php echo esc_url($return_url); ?>" class="button button-secondary">
                <?php _e('Zurück', 'vectorize-wp'); ?>
            </a>
        </div>
    </div>
    
    <div class="vectorize-wp-editor-container">
        <!-- SVG-Edit wird hier im iFrame geladen -->
        <iframe id="svg-editor-frame" 
                src="<?php echo esc_url(VECTORIZE_WP_URL . 'svg-edit/svg-editor.html'); ?>" 
                width="100%" 
                height="600px" 
                frameborder="0">
        </iframe>
    </div>
    
    <div id="svg-editor-status" class="vectorize-wp-editor-status" style="display: none;">
        <p class="status-message"></p>
    </div>
    
    <input type="hidden" id="svg-editor-id" value="<?php echo esc_attr($svg_id); ?>" />
    <input type="hidden" id="svg-editor-content" value="<?php echo esc_attr($svg_content); ?>" />
</div>