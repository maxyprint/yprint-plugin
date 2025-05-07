<?php
/**
 * Product Fields functionality for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to manage custom product fields, shortcodes, and REST API for YPrint products
 */
class YPrint_Product_Fields {
    
    /**
 * Initialize the class
 */
public static function init() {
    // Admin: Register custom product fields
    add_action('init', array(__CLASS__, 'register_product_custom_fields'));
    
    // Frontend: Register shortcodes for accessing product data
    add_action('init', array(__CLASS__, 'register_product_shortcodes'));
    
    // REST API: Register custom endpoint
    add_action('rest_api_init', array(__CLASS__, 'register_product_endpoint'));
    
    // API Authentication: Allow public access to product data
    add_filter('rest_authentication_errors', array(__CLASS__, 'allow_public_product_access'));
    
    // Enqueue scripts
    add_action('wp_enqueue_scripts', array(__CLASS__, 'add_api_nonce'));
    
    // Register the redirection script in footer
    add_action('wp_footer', array(__CLASS__, 'add_product_redirect_script'));
    
    // Register additional product shortcodes
    add_shortcode('yprint_product', array(__CLASS__, 'product_container_shortcode'));
    add_shortcode('yp_gallery_slider', array(__CLASS__, 'gallery_slider_shortcode'));
}
    
    /**
     * Register custom fields for WooCommerce products
     */
    public static function register_product_custom_fields() {
        // Skip if WooCommerce is not active
        if (!class_exists('WooCommerce')) {
            return;
        }
        
        // Add Product Tab
        add_filter('woocommerce_product_data_tabs', array(__CLASS__, 'add_product_data_tab'));
        
        // Add Tab Contents
        add_action('woocommerce_product_data_panels', array(__CLASS__, 'add_product_data_fields'));
        
        // Save Custom Fields
        add_action('woocommerce_process_product_meta', array(__CLASS__, 'save_product_fields'));
    }
    
    /**
     * Add custom product data tab
     */
    public static function add_product_data_tab($tabs) {
        $tabs['yprint_details'] = array(
            'label'    => __('yprint Zusatzdaten', 'yprint'),
            'target'   => 'yprint_product_data',
            'class'    => array('show_if_simple', 'show_if_variable'),
            'priority' => 21
        );
        return $tabs;
    }
    
    /**
     * Add custom fields to product data tab
     */
    public static function add_product_data_fields() {
        ?>
        <div id="yprint_product_data" class="panel woocommerce_options_panel">
            <div class="options_group">
                <?php
                // Hersteller
                woocommerce_wp_text_input(array(
                    'id'          => '_yprint_manufacturer',
                    'label'       => __('Hersteller', 'yprint'),
                    'placeholder' => '',
                    'desc_tip'    => 'true',
                    'description' => __('Gib den Hersteller des Produkts an.', 'yprint')
                ));

                // Farben
                woocommerce_wp_textarea_input(array(
                    'id'          => '_yprint_colors',
                    'label'       => __('Verfügbare Farben', 'yprint'),
                    'placeholder' => 'z.B. Schwarz, Weiß, Grau',
                    'desc_tip'    => 'true',
                    'description' => __('Liste der verfügbaren Farben.', 'yprint')
                ));

                // Sizing
                woocommerce_wp_textarea_input(array(
                    'id'          => '_yprint_sizing',
                    'label'       => __('Größeninformationen', 'yprint'),
                    'placeholder' => 'Größentabelle oder Hinweise',
                    'desc_tip'    => 'true',
                    'description' => __('Informationen zur Größenauswahl.', 'yprint')
                ));

                // Note
                woocommerce_wp_textarea_input(array(
                    'id'          => '_yprint_note',
                    'label'       => __('Besondere Hinweise', 'yprint'),
                    'placeholder' => 'Wichtige Hinweise zum Produkt',
                    'desc_tip'    => 'true',
                    'description' => __('Besondere Hinweise zum Produkt.', 'yprint')
                ));

                // Details
                woocommerce_wp_textarea_input(array(
                    'id'          => '_yprint_details',
                    'label'       => __('Produktdetails', 'yprint'),
                    'placeholder' => 'Detaillierte Produktinformationen',
                    'desc_tip'    => 'true',
                    'description' => __('Detaillierte Informationen zum Produkt.', 'yprint')
                ));

                // Features
                woocommerce_wp_textarea_input(array(
                    'id'          => '_yprint_features',
                    'label'       => __('Features', 'yprint'),
                    'placeholder' => 'Produktmerkmale',
                    'desc_tip'    => 'true',
                    'description' => __('Besondere Merkmale des Produkts.', 'yprint')
                ));

                // Care Instructions
                woocommerce_wp_textarea_input(array(
                    'id'          => '_yprint_care',
                    'label'       => __('Pflegehinweise', 'yprint'),
                    'placeholder' => 'Waschanleitung, etc.',
                    'desc_tip'    => 'true',
                    'description' => __('Pflegehinweise für das Produkt.', 'yprint')
                ));

                // Customizations
                woocommerce_wp_textarea_input(array(
                    'id'          => '_yprint_customizations',
                    'label'       => __('Anpassungsmöglichkeiten', 'yprint'),
                    'placeholder' => 'Mögliche individuelle Anpassungen',
                    'desc_tip'    => 'true',
                    'description' => __('Anpassungsmöglichkeiten für das Produkt.', 'yprint')
                ));

                // Fabric
                woocommerce_wp_textarea_input(array(
                    'id'          => '_yprint_fabric',
                    'label'       => __('Material/Stoff', 'yprint'),
                    'placeholder' => 'z.B. 100% Bio-Baumwolle',
                    'desc_tip'    => 'true',
                    'description' => __('Informationen zum Material des Produkts.', 'yprint')
                ));
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Save custom product fields
     */
    public static function save_product_fields($post_id) {
        $fields = array(
            '_yprint_manufacturer',
            '_yprint_colors',
            '_yprint_sizing',
            '_yprint_note',
            '_yprint_details',
            '_yprint_features',
            '_yprint_care',
            '_yprint_customizations',
            '_yprint_fabric'
        );

        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                update_post_meta($post_id, $field, sanitize_textarea_field($_POST[$field]));
            }
        }
    }
    
    /**
     * Register product shortcodes
     */
    public static function register_product_shortcodes() {
        // Produkt-Titel
        add_shortcode('yprint_title', array(__CLASS__, 'product_title_shortcode'));
        
        // Produkt-ID
        add_shortcode('yprint_id', array(__CLASS__, 'product_id_shortcode'));
        
        // Produkt-Beschreibung
        add_shortcode('yprint_description', array(__CLASS__, 'product_description_shortcode'));
        
        // Produkt-Bild
        add_shortcode('yprint_image', array(__CLASS__, 'product_image_shortcode'));
        
        // Produkt-Galerie
        add_shortcode('yprint_gallery', array(__CLASS__, 'product_gallery_shortcode'));
        
        // Produkt-Preis
        add_shortcode('yprint_price', array(__CLASS__, 'product_price_shortcode'));
        
        // In den Warenkorb Button
        add_shortcode('yprint_add_to_cart', array(__CLASS__, 'add_to_cart_shortcode'));

        // Produkt-Akkordeon
        add_shortcode('yprint_product_accordion', array(__CLASS__, 'product_accordion_shortcode'));
        
        // Benutzerdefinierte Felder als Shortcodes registrieren
        $custom_fields = array(
            'manufacturer', 'colors', 'sizing', 'note', 
            'details', 'features', 'care', 'customizations', 'fabric'
        );
        
        foreach ($custom_fields as $field) {
            add_shortcode('yprint_' . $field, array(__CLASS__, 'custom_field_shortcode'));
        }
    }
    
    /**
     * Helper function to get current product
     */
    public static function get_current_product() {
        global $product;
        
        // First try to get product from global
        if (is_a($product, 'WC_Product')) {
            return $product;
        }
        
        // Then try from query parameter
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        if ($product_id) {
            return wc_get_product($product_id);
        }
        
        // Then try from post
        global $post;
        if (is_a($post, 'WP_Post') && 'product' === get_post_type($post->ID)) {
            return wc_get_product($post->ID);
        }
        
        return false;
    }
    
    /**
     * Shortcode: Product Title
     */
    public static function product_title_shortcode($atts) {
        $product = self::get_current_product();
        if (!$product) return '';
        
        $atts = shortcode_atts(array(
            'class' => 'yprint-product-title',
            'tag' => 'h1'
        ), $atts);
        
        return '<' . esc_attr($atts['tag']) . ' class="' . esc_attr($atts['class']) . '">' . 
               esc_html($product->get_name()) . 
               '</' . esc_attr($atts['tag']) . '>';
    }
    
    /**
     * Shortcode: Product ID
     */
    public static function product_id_shortcode($atts) {
        $product = self::get_current_product();
        if (!$product) return '';
        
        $atts = shortcode_atts(array(
            'class' => 'yprint-product-id',
            'label' => 'Produkt-ID:',
        ), $atts);
        
        return '<div class="' . esc_attr($atts['class']) . '">' . 
               esc_html($atts['label']) . ' ' . esc_html($product->get_id()) . 
               '</div>';
    }
    
    /**
     * Shortcode: Product Description
     */
    public static function product_description_shortcode($atts) {
        $product = self::get_current_product();
        if (!$product) return '';
        
        $atts = shortcode_atts(array(
            'class' => 'yprint-product-description',
        ), $atts);
        
        return '<div class="' . esc_attr($atts['class']) . '">' . 
               wpautop($product->get_description()) . 
               '</div>';
    }
    
    /**
     * Shortcode: Product Image
     */
    public static function product_image_shortcode($atts) {
        $product = self::get_current_product();
        if (!$product) return '';
        
        $atts = shortcode_atts(array(
            'class' => 'yprint-product-image',
            'size' => 'large'
        ), $atts);
        
        return '<div class="' . esc_attr($atts['class']) . '">' . 
               $product->get_image($atts['size']) . 
               '</div>';
    }
    
    /**
     * Shortcode: Product Gallery
     */
    public static function product_gallery_shortcode($atts) {
        $product = self::get_current_product();
        if (!$product) return '';
        
        $atts = shortcode_atts(array(
            'class' => 'yprint-product-gallery',
            'thumb_class' => 'yprint-gallery-thumb',
            'size' => 'thumbnail'
        ), $atts);
        
        $attachment_ids = $product->get_gallery_image_ids();
        if (empty($attachment_ids)) return '';
        
        $output = '<div class="' . esc_attr($atts['class']) . '">';
        foreach ($attachment_ids as $attachment_id) {
            $output .= '<div class="' . esc_attr($atts['thumb_class']) . '">';
            $output .= wp_get_attachment_image($attachment_id, $atts['size']);
            $output .= '</div>';
        }
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Shortcode: Product Price
     */
    public static function product_price_shortcode($atts) {
        $product = self::get_current_product();
        if (!$product) return '';
        
        $atts = shortcode_atts(array(
            'class' => 'yprint-product-price',
        ), $atts);
        
        return '<div class="' . esc_attr($atts['class']) . '">' . 
               $product->get_price_html() . 
               '</div>';
    }
    
    /**
     * Shortcode: Add to Cart Button
     */
    public static function add_to_cart_shortcode($atts) {
        $product = self::get_current_product();
        if (!$product) return '';
        
        $atts = shortcode_atts(array(
            'class' => 'yprint-add-to-cart-button',
            'text' => 'In den Warenkorb',
        ), $atts);
        
        return '<a href="' . esc_url($product->add_to_cart_url()) . '" class="' . esc_attr($atts['class']) . '">' . 
               esc_html($atts['text']) . 
               '</a>';
    }
    
    /**
     * Shortcode: Custom Field
     */
    public static function custom_field_shortcode($atts, $content = null, $tag = '') {
        $product = self::get_current_product();
        if (!$product) return '';
        
        // Extract field name from shortcode tag
        $field_name = str_replace('yprint_', '_yprint_', $tag);
        
        $field_value = get_post_meta($product->get_id(), $field_name, true);
        if (empty($field_value)) return '';
        
        $atts = shortcode_atts(array(
            'class' => 'yprint-' . str_replace('_yprint_', '', $field_name),
            'label' => '',
            'tag' => 'div',
        ), $atts);
        
        $output = '<' . esc_attr($atts['tag']) . ' class="' . esc_attr($atts['class']) . '">';
        if (!empty($atts['label'])) {
            $output .= '<span class="yprint-field-label">' . esc_html($atts['label']) . '</span> ';
        }
        $output .= wpautop($field_value);
        $output .= '</' . esc_attr($atts['tag']) . '>';
        
        return $output;
    }

    /**
 * Shortcode: Produkt-Akkordeon
 * 
 * Zeigt Produktdetails in einem Akkordeon-Format an
 * Usage: [yprint_product_accordion]
 */
public static function product_accordion_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',  // Optionale direkte Produkt-ID
    ), $atts, 'yprint_product_accordion');
    
    // Produkt-ID aus Attribut, URL oder aktuellem Produkt bestimmen
    $product_id = !empty($atts['id']) ? intval($atts['id']) : 0;
    
    if (empty($product_id)) {
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : get_the_ID();
    }
    
    // Produkt prüfen
    if (!$product_id || !function_exists('wc_get_product')) {
        return '<p class="error-message">Keine gültige Produkt-ID gefunden oder WooCommerce ist nicht aktiv.</p>';
    }
    
    // Produktdaten abrufen mit den korrekten YPrint-Feldern
    $product_data = array(
        'note' => get_post_meta($product_id, '_yprint_note', true) ?: 'Keine besonderen Hinweise verfügbar.',
        'details' => get_post_meta($product_id, '_yprint_details', true) ?: 'Keine Detailinformationen verfügbar.',
        'features' => get_post_meta($product_id, '_yprint_features', true) ?: 'Keine Features verfügbar.',
        'care' => get_post_meta($product_id, '_yprint_care', true) ?: 'Keine Pflegehinweise verfügbar.',
        'customizations' => get_post_meta($product_id, '_yprint_customizations', true) ?: 'Keine Anpassungsinformationen verfügbar.',
        'fabric' => get_post_meta($product_id, '_yprint_fabric', true) ?: 'Keine Materialinformationen verfügbar.'
    );
    
    // Eindeutige ID für dieses Akkordeon generieren
    $accordion_id = 'yprint-accordion-' . uniqid();
    
    // CSS für das Akkordeon
    $output = '<style>
        .yprint-accordion {
            border-top: 1px solid #e5e5e5;
            margin-bottom: 20px;
            width: 100%;
        }
        .yprint-accordion-item {
            border-bottom: 1px solid #e5e5e5;
        }
        .yprint-accordion-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
            cursor: pointer;
            font-size: 18px;
            font-weight: 600;
        }
        .yprint-accordion-header:hover {
            opacity: 0.8;
        }
        .yprint-accordion-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            padding: 0 0 0 0;
        }
        .yprint-accordion-content.active {
            max-height: 1000px;
            padding: 0 0 20px 0;
        }
        .yprint-accordion-icon {
            font-size: 24px;
            transition: transform 0.3s ease;
        }
        .yprint-accordion-icon.active {
            transform: rotate(45deg);
        }
        .yprint-note {
            margin-bottom: 20px;
        }
        .yprint-note p {
            font-style: italic;
            color: #666;
        }
        .error-message {
            color: #777;
            font-style: italic;
        }
    </style>';
    
    // Note-Bereich mit Besonderen Hinweisen
    $output .= '<div class="yprint-note">
        <h2>Note</h2>
        <div>' . wpautop($product_data['note']) . '</div>
    </div>';
    
    // Akkordeon-Container
    $output .= '<div class="yprint-accordion" id="' . esc_attr($accordion_id) . '">';
    
    // Details Bereich
    $output .= '<div class="yprint-accordion-item">
        <div class="yprint-accordion-header">
            <span>Details</span>
            <span class="yprint-accordion-icon">+</span>
        </div>
        <div class="yprint-accordion-content">' . wpautop($product_data['details']) . '</div>
    </div>';
    
    // Features Bereich
    $output .= '<div class="yprint-accordion-item">
        <div class="yprint-accordion-header">
            <span>Features</span>
            <span class="yprint-accordion-icon">+</span>
        </div>
        <div class="yprint-accordion-content">' . wpautop($product_data['features']) . '</div>
    </div>';
    
    // Pflegehinweise (Care Instructions)
    $output .= '<div class="yprint-accordion-item">
        <div class="yprint-accordion-header">
            <span>Care Instructions</span>
            <span class="yprint-accordion-icon">+</span>
        </div>
        <div class="yprint-accordion-content">' . wpautop($product_data['care']) . '</div>
    </div>';
    
    // Anpassungsmöglichkeiten (Customizations)
    $output .= '<div class="yprint-accordion-item">
        <div class="yprint-accordion-header">
            <span>Customizations</span>
            <span class="yprint-accordion-icon">+</span>
        </div>
        <div class="yprint-accordion-content">' . wpautop($product_data['customizations']) . '</div>
    </div>';
    
    // Material/Stoff (Fabric)
    $output .= '<div class="yprint-accordion-item">
        <div class="yprint-accordion-header">
            <span>Fabric</span>
            <span class="yprint-accordion-icon">+</span>
        </div>
        <div class="yprint-accordion-content">' . wpautop($product_data['fabric']) . '</div>
    </div>';
    
    // Akkordeon schließen
    $output .= '</div>';
    
    // JavaScript für Akkordeon-Funktionalität mit spezifischer ID
    $output .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        const accordionId = "' . esc_js($accordion_id) . '";
        const accordion = document.getElementById(accordionId);
        
        if (accordion) {
            const accordionHeaders = accordion.querySelectorAll(".yprint-accordion-header");
            
            accordionHeaders.forEach(header => {
                header.addEventListener("click", function() {
                    const content = this.nextElementSibling;
                    const icon = this.querySelector(".yprint-accordion-icon");
                    
                    // Toggle active class
                    content.classList.toggle("active");
                    icon.classList.toggle("active");
                    
                    // Close other sections in this accordion
                    const allContents = accordion.querySelectorAll(".yprint-accordion-content");
                    const allIcons = accordion.querySelectorAll(".yprint-accordion-icon");
                    
                    allContents.forEach(item => {
                        if (item !== content && item.classList.contains("active")) {
                            item.classList.remove("active");
                        }
                    });
                    
                    allIcons.forEach(item => {
                        if (item !== icon && item.classList.contains("active")) {
                            item.classList.remove("active");
                        }
                    });
                });
            });
        }
    });
    </script>';
    
    return $output;
}
    
/**
 * Add JavaScript for product redirection
 */
public static function add_product_redirect_script() {
    ?>
    <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // All elements with classes starting with "yprint-product-"
        var productElements = document.querySelectorAll('[class*="yprint-product-"]');
        
        productElements.forEach(function(element) {
            // Search through the element's classes
            var classes = element.className.split(' ');
            var productId = null;
            
            // Find the yprint-product-XXXX class and extract the ID
            classes.forEach(function(className) {
                if (className.startsWith('yprint-product-')) {
                    productId = className.replace('yprint-product-', '');
                }
            });
            
            if (productId) {
                // Make element clickable
                element.style.cursor = 'pointer';
                
                // Add visual hint
                element.title = 'Zum Produkt gehen';
                
                // Add click event
                element.addEventListener('click', function(e) {
                    // Don't redirect if clicking on a link or button inside the container
                    if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || 
                        e.target.closest('a') || e.target.closest('button')) {
                        return;
                    }
                    window.location.href = '<?php echo esc_url(home_url("/products/")); ?>?product_id=' + productId;
                });
            }
        });
    });
    </script>
    <?php
}

/**
 * Shortcode to create a clickable container for a specific product
 * Usage: [yprint_product id="3799"]Content[/yprint_product]
 */
public static function product_container_shortcode($atts, $content = null) {
    $atts = shortcode_atts(
        array(
            'id' => '',
            'class' => ''
        ),
        $atts,
        'yprint_product'
    );
    
    $product_id = $atts['id'];
    $extra_class = $atts['class'];
    
    if (empty($product_id)) {
        return $content;
    }
    
    $classes = 'yprint-product-container yprint-product-' . esc_attr($product_id);
    if (!empty($extra_class)) {
        $classes .= ' ' . esc_attr($extra_class);
    }
    
    return '<div class="' . $classes . '">' . do_shortcode($content) . '</div>';
}

/**
 * Shortcode for a modern product gallery slider with vertical thumbnails
 * Usage: [yp_gallery_slider] or [yp_gallery_slider id="123"]
 */
public static function gallery_slider_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'id' => '' // Optional direct product ID
        ),
        $atts,
        'yp_gallery_slider'
    );
    
    ob_start();
    
    // Get product ID from attribute or URL parameter
    $product_id = !empty($atts['id']) ? intval($atts['id']) : 0;
    
    if (empty($product_id)) {
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
    }
    
    // Get product images
    $gallery_images = array();
    $default_image = 'https://yprint.de/wp-content/uploads/2025/03/front.webp';
    
    if ($product_id > 0 && function_exists('wc_get_product')) {
        $product = wc_get_product($product_id);
        
        if ($product) {
            // Get main image
            $main_image_id = $product->get_image_id();
            if ($main_image_id) {
                $main_image_url = wp_get_attachment_image_url($main_image_id, 'full');
                if ($main_image_url) {
                    $default_image = $main_image_url;
                    $gallery_images[] = $main_image_url;
                }
            }
            
            // Get gallery images
            $gallery_ids = $product->get_gallery_image_ids();
            if (!empty($gallery_ids)) {
                foreach ($gallery_ids as $gallery_id) {
                    $gallery_image_url = wp_get_attachment_image_url($gallery_id, 'full');
                    if ($gallery_image_url) {
                        $gallery_images[] = $gallery_image_url;
                    }
                }
            }
        }
    }
    
    // If no images found, use the default image
    if (empty($gallery_images)) {
        $gallery_images[] = $default_image;
    }
    
    // Prepare images as JSON for JavaScript
    $gallery_images_json = json_encode($gallery_images);
    
    // CSS for the slider
    ?>
    <style>
        .yprint-gallery-container {
            max-width: 100%;
            margin: 0 auto 30px auto;
            position: relative;
            display: flex;
            flex-direction: row;
            gap: 10px;
        }

        @media (max-width: 768px) {
            .yprint-gallery-container {
                background-color: white;
                border-radius: 30px;
                border: 1px solid #e0e0e0;
                padding: 15px;
                box-sizing: border-box;
            }
        }
        
        .yprint-gallery-slider {
            flex: 1;
            overflow: hidden;
            position: relative;
            background: #e0e0e0;
            border: 1px solid #DFDFDF;
        }
        
        .yprint-gallery-slides {
            display: flex;
            transition: transform 0.3s ease;
        }
        
        .yprint-gallery-slide {
            min-width: 100%;
            box-sizing: border-box;
            background: #F6F7FA;
        }
        
        .yprint-gallery-slide img {
            width: 100%;
            height: auto;
            display: block;
        }
        
        .yprint-gallery-nav {
            display: flex;
            flex-direction: column;
            gap: 10px;
            width: 80px;
        }
        
        .yprint-gallery-thumbnail {
            width: 80px;
            height: 80px;
            cursor: pointer;
            opacity: 0.6;
            transition: opacity 0.3s;
            border: 1px solid #DFDFDF;
            overflow: hidden;
            background: #e0e0e0;
        }
        
        .yprint-gallery-thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .yprint-gallery-thumbnail.active {
            opacity: 1;
            border: 2px solid #707070;
        }
        
        @media (max-width: 768px) {
            .yprint-gallery-container {
                flex-direction: column;
            }
            
            .yprint-gallery-nav {
                flex-direction: row;
                width: 100%;
                justify-content: center;
            }
            
            .yprint-gallery-thumbnail {
                width: 60px;
                height: 60px;
            }
        }
    </style>
    
    <div class="yprint-gallery-container">
        <div class="yprint-gallery-slider">
            <div class="yprint-gallery-slides" id="yprintGallerySlides">
                <?php foreach ($gallery_images as $index => $image_url): ?>
                <div class="yprint-gallery-slide">
                    <img src="<?php echo esc_url($image_url); ?>" alt="Produktbild <?php echo $index + 1; ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="yprint-gallery-nav" id="yprintGalleryNav">
            <?php foreach ($gallery_images as $index => $image_url): ?>
            <div class="yprint-gallery-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>">
                <img src="<?php echo esc_url($image_url); ?>" alt="Thumbnail <?php echo $index + 1; ?>">
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const slidesContainer = document.getElementById('yprintGallerySlides');
        const thumbnails = document.querySelectorAll('.yprint-gallery-thumbnail');
        
        let currentSlide = 0;
        const galleryImages = <?php echo $gallery_images_json; ?>;
        
        function showSlide(index) {
            currentSlide = index;
            slidesContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
            
            // Mark active thumbnail
            thumbnails.forEach((thumb, idx) => {
                thumb.classList.toggle('active', idx === currentSlide);
            });
        }
        
        // Add event listeners for thumbnails
        thumbnails.forEach((thumb, index) => {
            thumb.addEventListener('click', () => {
                showSlide(index);
            });
        });
        
        // Add keyboard navigation
        document.addEventListener('keydown', (e) => {
            if (e.key === 'ArrowLeft') {
                currentSlide = (currentSlide - 1 + galleryImages.length) % galleryImages.length;
                showSlide(currentSlide);
            } else if (e.key === 'ArrowRight') {
                currentSlide = (currentSlide + 1) % galleryImages.length;
                showSlide(currentSlide);
            }
        });
        
        // Initial display
        showSlide(0);
    });
    </script>
    <?php
    
    return ob_get_clean();
}

    /**
     * Register REST API endpoint for product data
     */
    public static function register_product_endpoint() {
        register_rest_route('yprint/v1', '/product/(?P<id>\d+)', array(
            'methods' => 'GET',
            'callback' => array(__CLASS__, 'get_product_data'),
            'permission_callback' => '__return_true', // Public access
        ));
    }
    
    /**
     * Callback function for REST API endpoint
     */
    public static function get_product_data($request) {
        $product_id = $request['id'];
        $product = wc_get_product($product_id);
        
        if (!$product) {
            return new WP_Error('product_not_found', 'Produkt nicht gefunden', array('status' => 404));
        }
        
        $product_data = array(
            'id' => $product->get_id(),
            'name' => $product->get_name(),
            'description' => $product->get_description(),
            'short_description' => $product->get_short_description(),
            'price' => $product->get_price(),
            'regular_price' => $product->get_regular_price(),
            'sale_price' => $product->get_sale_price(),
            'sku' => $product->get_sku(),
            'stock_status' => $product->get_stock_status(),
            'featured_image' => get_the_post_thumbnail_url($product_id, 'full'),
            
            // Custom fields
            'manufacturer' => get_post_meta($product_id, '_yprint_manufacturer', true),
            'colors' => get_post_meta($product_id, '_yprint_colors', true),
            'sizing' => get_post_meta($product_id, '_yprint_sizing', true),
            'details' => get_post_meta($product_id, '_yprint_details', true),
            'features' => get_post_meta($product_id, '_yprint_features', true),
            'care' => get_post_meta($product_id, '_yprint_care', true),
            'fabric' => get_post_meta($product_id, '_yprint_fabric', true),
            'note' => get_post_meta($product_id, '_yprint_note', true),
            'customizations' => get_post_meta($product_id, '_yprint_customizations', true),
        );
        
        return rest_ensure_response($product_data);
    }
    
    /**
     * Add WP API nonce for frontend access
     */
    public static function add_api_nonce() {
        wp_enqueue_script('wp-api');
        wp_localize_script('wp-api', 'wpApiSettings', array(
            'root' => esc_url_raw(rest_url()),
            'nonce' => wp_create_nonce('wp_rest')
        ));
    }
    
    /**
     * Allow public product access in REST API
     */
    public static function allow_public_product_access($permission) {
        $route = $GLOBALS['wp']->query_vars['rest_route'] ?? '';
        
        if (strpos($route, '/wc/v3/products') === 0 || strpos($route, '/yprint/v1/product') === 0) {
            return true;
        }
        
        return $permission;
    }
}

// Initialize the class
YPrint_Product_Fields::init();

