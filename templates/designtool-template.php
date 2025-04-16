<?php
/**
 * Template Name: Design Tool
 * Description: Ein vollständiges Template für die Vectorize Design-Tool-Anwendung.
 * 
 * @package VectorizeWP
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?> class="designtool-html">
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
        html.designtool-html { margin-top: 0 !important; }
        #wpadminbar { display: none; }
    </style>
</head>

<body <?php body_class('designtool-page'); ?>>
<?php wp_body_open(); ?>

<div class="designtool-container">
    <?php include VECTORIZE_WP_PATH . 'templates/designtool-canvas-content.php'; ?>
</div>

<?php
// Sicherstellen, dass die erforderlichen Scripts geladen sind
wp_enqueue_script('jquery');
wp_enqueue_script('vectorize-wp-designtool');
wp_enqueue_style('vectorize-wp-designtool');

// Für SVG-Handhabung
wp_localize_script(
    'vectorize-wp-designtool',
    'vectorizeWpFrontend',
    array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('vectorize_wp_frontend_nonce'),
        'maxUploadSize' => vectorize_wp_get_max_upload_size(),
        'siteUrl' => site_url(),
        'pluginUrl' => VECTORIZE_WP_URL,
    )
);

// Wenn SVG-Parameter übergeben wurden, dann dem JavaScript zur Verfügung stellen
if (isset($_GET['svg_id'])) {
    $svg_id = intval($_GET['svg_id']);
    $svg_path = get_attached_file($svg_id);
    if ($svg_path && file_exists($svg_path)) {
        $svg_content = file_get_contents($svg_path);
        wp_localize_script(
            'vectorize-wp-designtool',
            'vectorizeWpSvgData',
            array(
                'svgId' => $svg_id,
                'svgContent' => $svg_content
            )
        );
    }
}
?>

<?php wp_footer(); ?>
</body>
</html>