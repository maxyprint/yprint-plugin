<?php
/**
 * Mobile Product Page Shortcode for YPrint
 * Combines all product components into a single, mobile-optimized layout
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to manage the mobile product page shortcode
 */
class YPrint_Mobile_Product_Page {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode('yprint_mobile_product', array(__CLASS__, 'render_mobile_product_page'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_mobile_product_styles'));
    }
    
    /**
     * Enqueue necessary styles for the mobile product page
     */
    public static function enqueue_mobile_product_styles() {
        // Enqueue Google Fonts
        wp_enqueue_style('google-fonts-roboto', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap');
    }
    
    /**
     * Get current product from URL or global
     */
    private static function get_current_product() {
        global $product;
        
        // First try from URL parameter
        $product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
        if ($product_id) {
            return wc_get_product($product_id);
        }
        
        // Then try from global
        if (is_a($product, 'WC_Product')) {
            return $product;
        }
        
        // Finally try from post
        global $post;
        if (is_a($post, 'WP_Post') && 'product' === get_post_type($post->ID)) {
            return wc_get_product($post->ID);
        }
        
        return false;
    }
    
    private static function render_product_gallery($product) {
        if (!$product) return '';
        
        $gallery_images = array();
        
        // Get main image
        $main_image_id = $product->get_image_id();
        if ($main_image_id) {
            $main_image_url = wp_get_attachment_image_url($main_image_id, 'full');
            if ($main_image_url) {
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
        
        // Wenn keine Bilder vorhanden sind, Galerie nicht anzeigen
        if (empty($gallery_images)) {
            return '<div class="yprint-mobile-no-images"><p>Keine Produktbilder verfügbar</p></div>';
        }
        
        $unique_id = 'mobile-gallery-' . uniqid();
        $gallery_images_json = json_encode($gallery_images);
        
        ob_start();
        ?>
        <div class="yprint-mobile-gallery-container" id="<?php echo esc_attr($unique_id); ?>">
            <div class="yprint-mobile-gallery-slider">
                <div class="yprint-mobile-gallery-slides">
                    <?php foreach ($gallery_images as $index => $image_url): ?>
                    <div class="yprint-mobile-gallery-slide">
                        <img src="<?php echo esc_url($image_url); ?>" alt="Produktbild <?php echo $index + 1; ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="yprint-mobile-gallery-nav">
                <?php foreach ($gallery_images as $index => $image_url): ?>
                <div class="yprint-mobile-gallery-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>">
                    <img src="<?php echo esc_url($image_url); ?>" alt="Thumbnail <?php echo $index + 1; ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const galleryContainer = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!galleryContainer) return;
            
            const slidesContainer = galleryContainer.querySelector('.yprint-mobile-gallery-slides');
            const thumbnails = galleryContainer.querySelectorAll('.yprint-mobile-gallery-thumbnail');
            
            let currentSlide = 0;
            const galleryImages = <?php echo $gallery_images_json; ?>;
            
            function showSlide(index) {
                currentSlide = index;
                slidesContainer.style.transform = `translateX(-${currentSlide * 100}%)`;
                
                thumbnails.forEach((thumb, idx) => {
                    thumb.classList.toggle('active', idx === currentSlide);
                });
            }
            
            thumbnails.forEach((thumb, index) => {
                thumb.addEventListener('click', () => {
                    showSlide(index);
                });
            });
            
            showSlide(0);
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private static function render_product_header($product) {
        if (!$product) return '';
        
        $product_name = $product->get_name();
        $sku = $product->get_sku();
        $manufacturer = get_post_meta($product->get_id(), '_yprint_manufacturer', true);
        
        // Nur anzeigen wenn Daten vorhanden sind
        $info_parts = array();
        if (!empty($sku)) {
            $info_parts[] = '#' . $sku;
        }
        if (!empty($manufacturer)) {
            $info_parts[] = 'by ' . $manufacturer;
        }
        
        $info_html = '';
        if (!empty($info_parts)) {
            $info_html = '<p class="yprint-mobile-product-info">' . esc_html(implode(' ', $info_parts)) . '</p>';
        }
        
        return sprintf(
            '<div class="yprint-mobile-product-header">
                <h1 class="yprint-mobile-product-title">%s</h1>
                %s
            </div>',
            esc_html($product_name),
            $info_html
        );
    }
    
    private static function render_product_description($product) {
        if (!$product) return '';
        
        $description = $product->get_description();
        $short_description = $product->get_short_description();
        
        // Nutze kurze Beschreibung wenn vorhanden, sonst lange Beschreibung
        $content = '';
        if (!empty($short_description)) {
            $content = $short_description;
        } elseif (!empty($description)) {
            $content = $description;
        }
        
        // Nur anzeigen wenn Inhalt vorhanden ist
        if (empty($content)) {
            return '';
        }
        
        return sprintf(
            '<div class="yprint-mobile-product-description">%s</div>',
            wp_kses_post($content)
        );
    }
    
    /**
     * Render color selection
     */
    private static function render_color_selection($product) {
        if (!$product) return '';
        
        $colors = get_post_meta($product->get_id(), '_yprint_colors', true);
        $unique_id = 'mobile-colors-' . uniqid();
        
        ob_start();
        ?>
        <div class="yprint-mobile-color-selection" id="<?php echo esc_attr($unique_id); ?>">
        <div class="yprint-mobile-color-options">
    <!-- Wird nur gefüllt wenn echte WooCommerce-Farben vorhanden sind -->
</div>
            <a href="#" class="yprint-mobile-sizing-link">Sizing</a>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const colorContainer = document.getElementById('<?php echo esc_js($unique_id); ?>');
            const colorOptions = colorContainer.querySelector('.yprint-mobile-color-options');
            let selectedColorId = null;
            
            function parseColorOptions(colorString) {
                if (!colorString) return [];
                
                const colors = [];
                const colorItems = colorString.split(/[;,]/);
                
                colorItems.forEach(item => {
                    const trimmedItem = item.trim();
                    if (!trimmedItem) return;
                    
                    const match = trimmedItem.match(/(.+?)\s*\(ID=(\d+)\)/i);
                    if (match) {
                        const colorName = match[1].trim();
                        const colorId = match[2];
                        
                        let colorCode = '#CCCCCC';
                        const lowerCaseName = colorName.toLowerCase();
                        if (lowerCaseName.includes('schwarz')) colorCode = '#000000';
                        else if (lowerCaseName.includes('weiß') || lowerCaseName.includes('weiss')) colorCode = '#FFFFFF';
                        else if (lowerCaseName.includes('rot')) colorCode = '#FF0000';
                        else if (lowerCaseName.includes('blau')) colorCode = '#0000FF';
                        else if (lowerCaseName.includes('grün') || lowerCaseName.includes('gruen')) colorCode = '#00FF00';
                        else if (lowerCaseName.includes('gelb')) colorCode = '#FFFF00';
                        else if (lowerCaseName.includes('orange')) colorCode = '#FFA500';
                        else if (lowerCaseName.includes('lila') || lowerCaseName.includes('violett')) colorCode = '#800080';
                        else if (lowerCaseName.includes('pink')) colorCode = '#FFC0CB';
                        else if (lowerCaseName.includes('braun')) colorCode = '#8B4513';
                        else if (lowerCaseName.includes('grau') || lowerCaseName.includes('gray')) colorCode = '#808080';
                        
                        colors.push({
                            name: colorName,
                            id: colorId,
                            code: colorCode
                        });
                    }
                });
                
                return colors;
            }
            
            function createColorCircles(colors) {
                colorOptions.innerHTML = '';
                
                colors.forEach(color => {
                    const colorCircle = document.createElement('div');
                    colorCircle.className = 'yprint-mobile-color-circle';
                    colorCircle.dataset.colorId = color.id;
                    colorCircle.dataset.colorName = color.name;
                    colorCircle.style.backgroundColor = color.code;
                    
                    if (color.code === '#FFFFFF') {
                        colorCircle.style.borderColor = '#CCCCCC';
                    }
                    
                    colorCircle.addEventListener('click', function() {
                        document.querySelectorAll('.yprint-mobile-color-circle').forEach(circle => {
                            circle.classList.remove('selected');
                        });
                        
                        colorCircle.classList.add('selected');
                        selectedColorId = color.id;
                        
                        sessionStorage.setItem('selectedColorId', selectedColorId);
                        sessionStorage.setItem('selectedColorName', color.name);
                    });
                    
                    colorOptions.appendChild(colorCircle);
                });
            }
            
            // Load colors from product data
const customColors = '<?php echo esc_js($colors); ?>';
const colors = parseColorOptions(customColors);

if (colors.length > 0) {
    createColorCircles(colors);
} else {
    // Keine Farben verfügbar - Farbauswahl ausblenden
    colorContainer.style.display = 'none';
}
            
            // Sizing link functionality
            const sizingLink = colorContainer.querySelector('.yprint-mobile-sizing-link');
            sizingLink.addEventListener('click', function(e) {
                e.preventDefault();
                // Trigger sizing chart - can be connected to existing sizing functionality
                document.dispatchEvent(new CustomEvent('openSizingChart'));
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private static function render_action_buttons($product) {
        if (!$product) return '';
        
        $product_id = $product->get_id();
        $sku = $product->get_sku();
        
        $buttons_html = '<div class="yprint-mobile-action-buttons">';
        
        // Designer Button nur anzeigen wenn SKU vorhanden
        if (!empty($sku)) {
            $designer_url = add_query_arg('template_id', $sku, 'https://yprint.de/designer');
            $buttons_html .= sprintf(
                '<a href="%s" class="yprint-mobile-designer-btn">Design</a>',
                esc_url($designer_url)
            );
        }
        
        // Buy Blank Button nur anzeigen wenn Produkt kaufbar ist
        if ($product->is_purchasable() && $product->is_in_stock()) {
            $buy_blank_url = '/?add-to-cart=' . $product_id;
            $buttons_html .= sprintf(
                '<a href="%s" class="yprint-mobile-buy-blank-btn" data-product-id="%s">Buy Blank - %s</a>',
                esc_url($buy_blank_url),
                esc_attr($product_id),
                $product->get_price_html()
            );
        }
        
        $buttons_html .= '</div>';
        
        return $buttons_html;
    }
    
    /**
     * Render product accordion with all details
     */
    private static function render_product_accordion($product) {
        if (!$product) return '';
        
        $product_id = $product->get_id();
        $accordion_id = 'mobile-accordion-' . uniqid();
        
        $product_data = array(
            'note' => get_post_meta($product_id, '_yprint_note', true),
            'details' => get_post_meta($product_id, '_yprint_details', true),
            'features' => get_post_meta($product_id, '_yprint_features', true),
            'care' => get_post_meta($product_id, '_yprint_care', true),
            'customizations' => get_post_meta($product_id, '_yprint_customizations', true),
            'fabric' => get_post_meta($product_id, '_yprint_fabric', true)
        );
        
        // Nur Bereiche anzeigen die auch Inhalt haben
        $available_sections = array_filter($product_data, function($value) {
            return !empty($value);
        });
        
        // Wenn keine Daten vorhanden sind, kein Accordion anzeigen
        if (empty($available_sections)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="yprint-mobile-accordion" id="<?php echo esc_attr($accordion_id); ?>">
            <div class="yprint-mobile-note">
                <h3>Note</h3>
                <div><?php echo wpautop(esc_html($product_data['note'])); ?></div>
            </div>
            
            <div class="yprint-mobile-accordion-item">
                <div class="yprint-mobile-accordion-header">
                    <span>Details</span>
                    <span class="yprint-mobile-accordion-icon">+</span>
                </div>
                <div class="yprint-mobile-accordion-content"><?php echo wpautop(esc_html($product_data['details'])); ?></div>
            </div>
            
            <div class="yprint-mobile-accordion-item">
                <div class="yprint-mobile-accordion-header">
                    <span>Features</span>
                    <span class="yprint-mobile-accordion-icon">+</span>
                </div>
                <div class="yprint-mobile-accordion-content"><?php echo wpautop(esc_html($product_data['features'])); ?></div>
            </div>
            
            <div class="yprint-mobile-accordion-item">
                <div class="yprint-mobile-accordion-header">
                    <span>Care Instructions</span>
                    <span class="yprint-mobile-accordion-icon">+</span>
                </div>
                <div class="yprint-mobile-accordion-content"><?php echo wpautop(esc_html($product_data['care'])); ?></div>
            </div>
            
            <div class="yprint-mobile-accordion-item">
                <div class="yprint-mobile-accordion-header">
                    <span>Customizations</span>
                    <span class="yprint-mobile-accordion-icon">+</span>
                </div>
                <div class="yprint-mobile-accordion-content"><?php echo wpautop(esc_html($product_data['customizations'])); ?></div>
            </div>
            
            <div class="yprint-mobile-accordion-item">
                <div class="yprint-mobile-accordion-header">
                    <span>Fabric</span>
                    <span class="yprint-mobile-accordion-icon">+</span>
                </div>
                <div class="yprint-mobile-accordion-content"><?php echo wpautop(esc_html($product_data['fabric'])); ?></div>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const accordion = document.getElementById('<?php echo esc_js($accordion_id); ?>');
            const headers = accordion.querySelectorAll('.yprint-mobile-accordion-header');
            
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const icon = this.querySelector('.yprint-mobile-accordion-icon');
                    
                    // Toggle current
                    content.classList.toggle('active');
                    icon.classList.toggle('active');
                    
                    // Close others
                    headers.forEach(otherHeader => {
                        if (otherHeader !== header) {
                            const otherContent = otherHeader.nextElementSibling;
                            const otherIcon = otherHeader.querySelector('.yprint-mobile-accordion-icon');
                            otherContent.classList.remove('active');
                            otherIcon.classList.remove('active');
                        }
                    });
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Main shortcode rendering function
     */
    public static function render_mobile_product_page($atts) {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return '<p>WooCommerce ist nicht aktiviert.</p>';
        }
        
        $product = self::get_current_product();
        if (!$product) {
            return '<p>Kein Produkt gefunden.</p>';
        }
        
        ob_start();
        ?>
        <style>
            .yprint-mobile-product-container {
                font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px 16px 80px 16px;
                background-color: #fff;
                color: #333;
                line-height: 1.6;
            }
            
            /* Gallery Styles */
            .yprint-mobile-gallery-container {
                margin-bottom: 24px;
                background-color: white;
                border-radius: 20px;
                border: 1px solid #e0e0e0;
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 12px;
            }
            
            .yprint-mobile-gallery-slider {
                overflow: hidden;
                position: relative;
                background: #F6F7FA;
                border-radius: 16px;
                border: 1px solid #DFDFDF;
            }
            
            .yprint-mobile-gallery-slides {
                display: flex;
                transition: transform 0.3s ease;
            }
            
            .yprint-mobile-gallery-slide {
                min-width: 100%;
                box-sizing: border-box;
            }
            
            .yprint-mobile-gallery-slide img {
                width: 100%;
                height: auto;
                display: block;
            }
            
            .yprint-mobile-gallery-nav {
                display: flex;
                gap: 8px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .yprint-mobile-gallery-thumbnail {
                width: 60px;
                height: 60px;
                cursor: pointer;
                opacity: 0.6;
                transition: opacity 0.3s;
                border: 1px solid #DFDFDF;
                border-radius: 8px;
                overflow: hidden;
                background: #F6F7FA;
            }
            
            .yprint-mobile-gallery-thumbnail img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .yprint-mobile-gallery-thumbnail.active {
                opacity: 1;
                border: 2px solid #0079FF;
            }
            
            /* Header Styles */
            .yprint-mobile-product-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            .yprint-mobile-product-title {
                font-size: 32px;
                font-weight: 700;
                margin: 0 0 8px 0;
                color: #1d1d1f;
            }
            
            .yprint-mobile-product-info {
                font-size: 16px;
                font-weight: 500;
                color: #707070;
                margin: 0;
            }
            
            /* Description Styles */
            .yprint-mobile-product-description {
                font-size: 16px;
                color: #707070;
                margin-bottom: 20px;
                line-height: 1.6;
            }
            
            /* Color Selection Styles */
            .yprint-mobile-color-selection {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 24px;
                gap: 16px;
            }
            
            .yprint-mobile-color-options {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .yprint-mobile-color-circle {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                border: 2px solid #E0E0E0;
                cursor: pointer;
                transition: transform 0.2s, border-color 0.2s;
            }
            
            .yprint-mobile-color-circle:hover {
                transform: scale(1.1);
            }
            
            .yprint-mobile-color-circle.selected {
                border-color: #0079FF;
                transform: scale(1.2);
            }
            
            .yprint-mobile-sizing-link {
                font-size: 15px;
                color: #707070;
                text-decoration: underline;
            }
            
            /* Action Buttons Styles */
            .yprint-mobile-action-buttons {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-bottom: 32px;
            }
            
            .yprint-mobile-designer-btn {
                display: block;
                text-align: center;
                padding: 14px 20px;
                background-color: #0079FF;
                color: white;
                text-decoration: none;
                border-radius: 15px;
                font-weight: 600;
                font-size: 16px;
                transition: background-color 0.3s ease;
            }
            
            .yprint-mobile-designer-btn:hover {
                background-color: #0062cc;
            }
            
            .yprint-mobile-buy-blank-btn {
                display: block;
                text-align: center;
                padding: 14px 20px;
                background-color: white;
                color: #333;
                text-decoration: none;
                border-radius: 15px;
                border: 1px solid #C0C0C0;
                font-weight: 500;
                font-size: 16px;
                transition: background-color 0.3s ease;
            }
            
            .yprint-mobile-buy-blank-btn:hover {
                background-color: #f5f5f5;
            }
            
            /* Accordion Styles */
            .yprint-mobile-accordion {
                border-top: 1px solid #e5e5e5;
                margin-bottom: 20px;
            }
            
            .yprint-mobile-note {
                margin-bottom: 20px;
                padding: 16px;
                background-color: #f8f9fa;
                border-radius: 12px;
            }
            
            .yprint-mobile-note h3 {
                font-size: 18px;
                font-weight: 600;
                margin: 0 0 8px 0;
                color: #1d1d1f;
            }
            
            .yprint-mobile-accordion-item {
                border-bottom: 1px solid #e5e5e5;
            }
            
            .yprint-mobile-accordion-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 0;
                cursor: pointer;
                font-size: 16px;
                font-weight: 600;
                color: #1d1d1f;
            }
            
            .yprint-mobile-accordion-header:hover {
                opacity: 0.8;
            }
            
            .yprint-mobile-accordion-content {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease-out;
                padding: 0;
            }
            
            .yprint-mobile-accordion-content.active {
                max-height: 500px;
                padding: 0 0 16px 0;
            }
            
            .yprint-mobile-accordion-icon {
                font-size: 20px;
                transition: transform 0.3s ease;
                color: #0079FF;
            }
            
            .yprint-mobile-accordion-icon.active {
                transform: rotate(45deg);
            }
            
            /* Responsive adjustments */
            @media (max-width: 480px) {
                .yprint-mobile-product-container {
                    padding: 16px 12px 80px 12px;
                }
                
                .yprint-mobile-product-title {
                    font-size: 28px;
                }
                
                .yprint-mobile-gallery-thumbnail {
                    width: 50px;
                    height: 50px;
                }
            }
        </style>
        
        <div class="yprint-mobile-product-container">
            <?php echo self::render_product_gallery($product); ?>
            <?php echo self::render_product_header($product); ?>
            <?php echo self::render_product_description($product); ?>
            <?php echo self::render_color_selection($product); ?>
            <?php echo self::render_action_buttons($product); ?>
            <?php echo self::render_product_accordion($product); ?>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Buy Blank button functionality
            const buyBlankButtons = document.querySelectorAll('.yprint-mobile-buy-blank-btn');
            buyBlankButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productId = this.dataset.productId;
                    if (!productId) return;
                    
                    // Add to cart
                    fetch('/?add-to-cart=' + productId, {
                        method: 'GET',
                        credentials: 'same-origin'
                    })
                    .then(() => {
                        // Try to trigger cart popup
                        const cartPopup = document.querySelector('#cart');
                        if (cartPopup) {
                            cartPopup.style.display = 'block';
                            cartPopup.classList.add('show');
                        }
                        
                        // Trigger custom event
                        document.dispatchEvent(new CustomEvent('open-cart-popup', { 
                            detail: { productId: productId } 
                        }));
                    })
                    .catch(error => {
                        console.error('Error adding to cart:', error);
                    });
                });
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
}

// Initialize the mobile product page
YPrint_Mobile_Product_Page::init();