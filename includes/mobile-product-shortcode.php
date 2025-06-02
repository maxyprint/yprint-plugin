<?php
/**
 * Fully Dynamic Mobile Product Page Shortcode for YPrint
 * All content is dynamically sourced from WooCommerce with no static fallbacks
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to manage the fully dynamic mobile product page shortcode
 */
class YPrint_Dynamic_Mobile_Product {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode('yprint_dynamic_mobile_product', array(__CLASS__, 'render_dynamic_mobile_product_page'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_dynamic_mobile_styles'));
        add_action('init', array(__CLASS__, 'load_textdomain'));
    }
    
    /**
     * Load plugin textdomain for translations
     */
    public static function load_textdomain() {
        load_plugin_textdomain('yprint', false, dirname(plugin_basename(__FILE__)) . '/languages/');
    }
    
    /**
     * Enqueue necessary styles for the mobile product page
     */
    public static function enqueue_dynamic_mobile_styles() {
        wp_enqueue_style('google-fonts-roboto', 'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap');
    }
    
    /**
     * Get plugin configuration with filters for customization
     */
    private static function get_config() {
        return array(
            'designer_base_url' => apply_filters('yprint_designer_base_url', get_option('yprint_designer_url', home_url('/designer'))),
            'texts' => array(
                'design_button' => __('Design', 'yprint'),
                'buy_blank_button' => __('Buy Blank', 'yprint'),
                'sizing_link' => __('Sizing', 'yprint'),
                'product_note' => __('Product Note', 'yprint'),
                'no_images' => __('No product images available', 'yprint'),
                'no_product' => __('No product found', 'yprint'),
                'woocommerce_inactive' => __('WooCommerce is not activated', 'yprint'),
                'accordion_titles' => array(
                    'details' => __('Details', 'yprint'),
                    'features' => __('Features', 'yprint'),
                    'care' => __('Care Instructions', 'yprint'),
                    'customizations' => __('Customizations', 'yprint'),
                    'fabric' => __('Fabric', 'yprint')
                )
            ),
            'events' => array(
                'sizing_chart' => apply_filters('yprint_sizing_chart_event', 'yprint:open-sizing-chart'),
                'color_selected' => apply_filters('yprint_color_selected_event', 'yprint:color-selected'),
                'cart_open' => apply_filters('yprint_cart_open_event', 'yprint:open-cart-popup')
            ),
            'storage_prefix' => apply_filters('yprint_storage_prefix', 'yprint_product_')
        );
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
    
    /**
     * Render product image gallery slider
     */
    private static function render_product_gallery($product, $config) {
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
        
        // If no images found, don't display gallery
        if (empty($gallery_images)) {
            return '<div class="yprint-dynamic-no-images"><p>' . 
                   esc_html($config['texts']['no_images']) . 
                   '</p></div>';
        }
        
        $product_id = $product->get_id();
        $unique_id = 'wc-product-gallery-' . $product_id;
        $gallery_images_json = json_encode($gallery_images);
        
        ob_start();
        ?>
        <div class="yprint-dynamic-gallery-container" id="<?php echo esc_attr($unique_id); ?>">
            <div class="yprint-dynamic-gallery-slider">
                <div class="yprint-dynamic-gallery-slides">
                    <?php foreach ($gallery_images as $index => $image_url): ?>
                    <div class="yprint-dynamic-gallery-slide">
                        <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr($product->get_name() . ' - ' . sprintf(__('Image %d', 'yprint'), $index + 1)); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="yprint-dynamic-gallery-nav">
                <?php foreach ($gallery_images as $index => $image_url): ?>
                <div class="yprint-dynamic-gallery-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" data-slide="<?php echo $index; ?>">
                    <img src="<?php echo esc_url($image_url); ?>" alt="<?php echo esc_attr(sprintf(__('Thumbnail %d', 'yprint'), $index + 1)); ?>">
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const galleryContainer = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!galleryContainer) return;
            
            const slidesContainer = galleryContainer.querySelector('.yprint-dynamic-gallery-slides');
            const thumbnails = galleryContainer.querySelectorAll('.yprint-dynamic-gallery-thumbnail');
            
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
    
    /**
     * Render product title and info
     */
    private static function render_product_header($product) {
        if (!$product) return '';
        
        $product_name = $product->get_name();
        $sku = $product->get_sku();
        $manufacturer = get_post_meta($product->get_id(), '_yprint_manufacturer', true);
        
        // Only display info if data is available
        $info_parts = array();
        if (!empty($sku)) {
            $info_parts[] = '#' . $sku;
        }
        if (!empty($manufacturer)) {
            $info_parts[] = sprintf(__('by %s', 'yprint'), $manufacturer);
        }
        
        $info_html = '';
        if (!empty($info_parts)) {
            $info_html = '<p class="yprint-dynamic-product-info">' . esc_html(implode(' ', $info_parts)) . '</p>';
        }
        
        return sprintf(
            '<div class="yprint-dynamic-product-header">
                <h1 class="yprint-dynamic-product-title">%s</h1>
                %s
            </div>',
            esc_html($product_name),
            $info_html
        );
    }
    
    /**
     * Render product description
     */
    private static function render_product_description($product) {
        if (!$product) return '';
        
        $description = $product->get_description();
        $short_description = $product->get_short_description();
        
        // Use short description if available, otherwise long description
        $content = '';
        if (!empty($short_description)) {
            $content = $short_description;
        } elseif (!empty($description)) {
            $content = $description;
        }
        
        // Only display if content is available
        if (empty($content)) {
            return '';
        }
        
        return sprintf(
            '<div class="yprint-dynamic-product-description">%s</div>',
            wp_kses_post($content)
        );
    }
    
/**
 * Render color selection
 */
private static function render_color_selection($product, $config) {
    if (!$product) return '';
    
    // Get colors from WooCommerce product custom field (now includes product IDs)
    $colors = get_post_meta($product->get_id(), '_yprint_colors', true);
    $sizing_data = get_post_meta($product->get_id(), '_yprint_sizing', true);
    
    // Don't display if no colors and no sizing
    if (empty($colors) && empty($sizing_data)) {
        return '';
    }
    
    $product_id = $product->get_id();
    $unique_id = 'wc-product-colors-' . $product_id;
    
    ob_start();
    ?>
    <div class="yprint-dynamic-color-selection" id="<?php echo esc_attr($unique_id); ?>">
        <?php if (!empty($colors)): ?>
        <div class="yprint-dynamic-color-options">
            <!-- Will be populated by JavaScript if colors are available -->
        </div>
        <?php endif; ?>
        
        <?php if (!empty($sizing_data)): ?>
        <a href="#" class="yprint-dynamic-sizing-link" data-sizing-content="<?php echo esc_attr($sizing_data); ?>">
            <?php echo esc_html($config['texts']['sizing_link']); ?>
        </a>
        <?php endif; ?>
    </div>
    
    <!-- Sizing Chart Popup -->
    <div id="yprint-sizing-popup-<?php echo esc_attr($product_id); ?>" class="yprint-sizing-popup" style="display: none;">
        <div class="yprint-sizing-popup-overlay">
            <div class="yprint-sizing-popup-content">
                <div class="yprint-sizing-popup-header">
                    <h3><?php echo esc_html(__('Size Chart', 'yprint')); ?></h3>
                    <span class="yprint-sizing-popup-close">&times;</span>
                </div>
                <div class="yprint-sizing-popup-body">
                    <!-- Sizing content will be populated by JavaScript -->
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const colorContainer = document.getElementById('<?php echo esc_js($unique_id); ?>');
        if (!colorContainer) return;
        
        const colorOptions = colorContainer.querySelector('.yprint-dynamic-color-options');
        const productId = '<?php echo esc_js($product_id); ?>';
        const storagePrefix = '<?php echo esc_js($config['storage_prefix']); ?>' + productId + '_';
        const eventConfig = <?php echo json_encode($config['events']); ?>;
        
        let selectedColorId = null;
        
        // Color mapping for common colors
        const colorMap = {
            'black': '#000000',
            'white': '#FFFFFF',
            'red': '#FF0000',
            'blue': '#0000FF',
            'green': '#00FF00',
            'yellow': '#FFFF00',
            'orange': '#FFA500',
            'purple': '#800080',
            'pink': '#FFC0CB',
            'brown': '#8B4513',
            'grey': '#808080',
            'gray': '#808080',
            'navy': '#000080',
            'maroon': '#800000',
            'olive': '#808000',
            'lime': '#00FF00',
            'aqua': '#00FFFF',
            'teal': '#008080',
            'silver': '#C0C0C0',
            'fuchsia': '#FF00FF',
            'beige': '#F5F5DC',
            'khaki': '#F0E68C',
            'coral': '#FF7F50',
            'salmon': '#FA8072',
            'gold': '#FFD700',
            'turquoise': '#40E0D0',
            'violet': '#EE82EE',
            'indigo': '#4B0082',
            'crimson': '#DC143C'
        };
        
        function parseColorsWithProductIds(colorString) {
            if (!colorString) return [];
            
            const colors = [];
            const currentProductId = productId;
            
            // Split by comma and clean up
            const colorItems = colorString.split(',').map(item => item.trim()).filter(item => item.length > 0);
            
            colorItems.forEach((item, index) => {
                // Parse format: "black:3799" or just "black"
                const parts = item.split(':');
                const colorName = parts[0].trim().toLowerCase();
                const linkedProductId = parts[1] ? parts[1].trim() : null;
                
                // Get color code from mapping or use default gray
                const colorCode = colorMap[colorName] || '#CCCCCC';
                
                // Determine if this color represents the current product
                const isCurrentProduct = linkedProductId === currentProductId;
                
                colors.push({
                    name: colorName.charAt(0).toUpperCase() + colorName.slice(1), // Capitalize first letter
                    id: (index + 1).toString(), // Generate sequential ID
                    code: colorCode,
                    originalName: colorName,
                    productId: linkedProductId,
                    isCurrent: isCurrentProduct
                });
            });
            
            return colors;
        }
        
        function createColorCircles(colors) {
            if (!colorOptions) return;
            
            colorOptions.innerHTML = '';
            
            colors.forEach(color => {
                const colorCircle = document.createElement('div');
                colorCircle.className = 'yprint-dynamic-color-circle';
                colorCircle.dataset.colorId = color.id;
                colorCircle.dataset.colorName = color.name;
                colorCircle.dataset.originalName = color.originalName;
                colorCircle.dataset.productId = color.productId || '';
                colorCircle.style.backgroundColor = color.code;
                colorCircle.title = color.name;
                
                // Mark current product's color as selected
                if (color.isCurrent) {
                    colorCircle.classList.add('selected');
                    selectedColorId = color.id;
                    console.log('Current product color detected:', color.name);
                }
                
                // Special styling for white/light colors
                if (color.code === '#FFFFFF' || color.code.toLowerCase() === '#ffffff') {
                    colorCircle.style.borderColor = '#CCCCCC';
                }
                
                colorCircle.addEventListener('click', function() {
                    const targetProductId = color.productId;
                    
                    if (targetProductId && targetProductId !== productId) {
                        // Redirect to the color variant product
                        const currentUrl = new URL(window.location.href);
                        currentUrl.searchParams.set('product_id', targetProductId);
                        
                        // Add loading state to all circles
                        document.querySelectorAll('.yprint-dynamic-color-circle').forEach(circle => {
                            circle.style.opacity = '0.6';
                            circle.style.pointerEvents = 'none';
                        });
                        
                        // Add special loading state to clicked circle
                        colorCircle.style.transform = 'scale(1.1)';
                        colorCircle.style.borderColor = '#0079FF';
                        
                        console.log('Redirecting to product:', targetProductId, 'for color:', color.name);
                        
                        // Show loading feedback and redirect
                        setTimeout(() => {
                            window.location.href = currentUrl.toString();
                        }, 150);
                        
                    } else if (!targetProductId) {
                        // No product ID linked - just select the color
                        document.querySelectorAll('.yprint-dynamic-color-circle').forEach(circle => {
                            circle.classList.remove('selected');
                        });
                        
                        colorCircle.classList.add('selected');
                        selectedColorId = color.id;
                        
                        sessionStorage.setItem(storagePrefix + 'color_id', selectedColorId);
                        sessionStorage.setItem(storagePrefix + 'color_name', color.name);
                        
                        // Dispatch color selected event
                        document.dispatchEvent(new CustomEvent(eventConfig.color_selected, {
                            detail: { colorId: selectedColorId, colorName: color.name, productId: productId }
                        }));
                        
                        console.log('Color selected (no redirect):', color.name, 'ID:', selectedColorId);
                        
                    } else {
                        // Same product - just select
                        document.querySelectorAll('.yprint-dynamic-color-circle').forEach(circle => {
                            circle.classList.remove('selected');
                        });
                        
                        colorCircle.classList.add('selected');
                        selectedColorId = color.id;
                        
                        console.log('Same product color selected:', color.name);
                    }
                });
                
                colorOptions.appendChild(colorCircle);
            });
        }
        
        // Load and parse colors from product data
        const customColors = '<?php echo esc_js($colors); ?>';
        const colors = parseColorsWithProductIds(customColors);
        
        console.log('Parsed colors with product IDs:', colors);
        console.log('Current product ID:', productId);
        
        if (colors.length > 0) {
            createColorCircles(colors);
            console.log('Created color circles for', colors.length, 'colors');
        } else if (colorOptions) {
            // Hide color section if no colors available
            colorOptions.style.display = 'none';
            console.log('No colors available, hiding color options');
        }
        
        // Sizing link functionality
        const sizingLink = colorContainer.querySelector('.yprint-dynamic-sizing-link');
        const sizingPopup = document.getElementById('yprint-sizing-popup-<?php echo esc_js($product_id); ?>');
        
        if (sizingLink && sizingPopup) {
            sizingLink.addEventListener('click', function(e) {
                e.preventDefault();
                
                // Get sizing data
                const sizingData = this.dataset.sizingContent;
                const popupBody = sizingPopup.querySelector('.yprint-sizing-popup-body');
                
                if (popupBody) {
                    // Parse sizing data and create table or display as text
                    if (sizingData.includes('|') || sizingData.includes(';')) {
                        // Table format
                        const table = createSizingTable(sizingData);
                        popupBody.innerHTML = '';
                        popupBody.appendChild(table);
                    } else {
                        // Plain text format
                        popupBody.innerHTML = '<div class="sizing-text">' + sizingData.replace(/\n/g, '<br>') + '</div>';
                    }
                }
                
                // Show popup
                sizingPopup.style.display = 'flex';
                document.body.style.overflow = 'hidden';
                
                console.log('Sizing chart opened for product:', productId);
            });
            
            // Close popup functionality
            const closeBtn = sizingPopup.querySelector('.yprint-sizing-popup-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function() {
                    sizingPopup.style.display = 'none';
                    document.body.style.overflow = '';
                });
            }
            
            // Close on overlay click
            const overlay = sizingPopup.querySelector('.yprint-sizing-popup-overlay');
            if (overlay) {
                overlay.addEventListener('click', function(e) {
                    if (e.target === overlay) {
                        sizingPopup.style.display = 'none';
                        document.body.style.overflow = '';
                    }
                });
            }
            
            // ESC key closes popup
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && sizingPopup.style.display === 'flex') {
                    sizingPopup.style.display = 'none';
                    document.body.style.overflow = '';
                }
            });
        }
        
        function createSizingTable(sizingData) {
            const table = document.createElement('table');
            table.className = 'yprint-sizing-table';
            
            let delimiter = '|';
            if (sizingData.includes(';')) delimiter = ';';
            
            const rows = sizingData.split('\n').map(row => row.trim()).filter(row => row.length > 0);
            
            if (rows.length > 0) {
                // Create header
                const headerRow = document.createElement('tr');
                const headerCells = rows[0].split(delimiter);
                headerCells.forEach(cellText => {
                    const th = document.createElement('th');
                    th.textContent = cellText.trim();
                    headerRow.appendChild(th);
                });
                
                const thead = document.createElement('thead');
                thead.appendChild(headerRow);
                table.appendChild(thead);
                
                // Create body
                const tbody = document.createElement('tbody');
                for (let i = 1; i < rows.length; i++) {
                    const bodyRow = document.createElement('tr');
                    const bodyCells = rows[i].split(delimiter);
                    bodyCells.forEach(cellText => {
                        const td = document.createElement('td');
                        td.textContent = cellText.trim();
                        bodyRow.appendChild(td);
                    });
                    tbody.appendChild(bodyRow);
                }
                table.appendChild(tbody);
            }
            
            return table;
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
    
    /**
     * Render action buttons (Designer & Buy Blank)
     */
    private static function render_action_buttons($product, $config) {
        if (!$product) return '';
        
        $product_id = $product->get_id();
        $sku = $product->get_sku();
        
        $buttons_html = '<div class="yprint-dynamic-action-buttons">';
        
        // Designer Button only if SKU is available
        if (!empty($sku)) {
            $designer_url = add_query_arg('template_id', $sku, $config['designer_base_url']);
            $buttons_html .= sprintf(
                '<a href="%s" class="yprint-dynamic-designer-btn">%s</a>',
                esc_url($designer_url),
                esc_html($config['texts']['design_button'])
            );
        }
        
        // Buy Blank Button only if product is purchasable and in stock
        if ($product->is_purchasable() && $product->is_in_stock()) {
            $buy_blank_url = '/?add-to-cart=' . $product_id;
            $price_html = $product->get_price_html();
            
            $button_text = $config['texts']['buy_blank_button'];
            if (!empty($price_html)) {
                $button_text .= ' - ' . $price_html;
            }
            
            $buttons_html .= sprintf(
                '<a href="%s" class="yprint-dynamic-buy-blank-btn" data-product-id="%s">%s</a>',
                esc_url($buy_blank_url),
                esc_attr($product_id),
                wp_kses_post($button_text)
            );
        }
        
        $buttons_html .= '</div>';
        
        // Only return if we have buttons
        if (strpos($buttons_html, 'yprint-dynamic-designer-btn') !== false || 
            strpos($buttons_html, 'yprint-dynamic-buy-blank-btn') !== false) {
            return $buttons_html;
        }
        
        return '';
    }
    
    /**
     * Render product accordion with all details
     */
    private static function render_product_accordion($product, $config) {
        if (!$product) return '';
        
        $product_id = $product->get_id();
        $accordion_id = 'wc-product-accordion-' . $product_id;
        
        // Get all product data
        $product_data = array(
            'note' => get_post_meta($product_id, '_yprint_note', true),
            'details' => get_post_meta($product_id, '_yprint_details', true),
            'features' => get_post_meta($product_id, '_yprint_features', true),
            'care' => get_post_meta($product_id, '_yprint_care', true),
            'customizations' => get_post_meta($product_id, '_yprint_customizations', true),
            'fabric' => get_post_meta($product_id, '_yprint_fabric', true)
        );
        
        // Only show sections that have content
        $available_sections = array_filter($product_data, function($value) {
            return !empty($value);
        });
        
        // If no data available, don't show accordion
        if (empty($available_sections)) {
            return '';
        }
        
        ob_start();
        ?>
        <div class="yprint-dynamic-accordion" id="<?php echo esc_attr($accordion_id); ?>">
            <?php if (!empty($product_data['note'])): ?>
            <div class="yprint-dynamic-note">
                <h3><?php echo esc_html($config['texts']['product_note']); ?></h3>
                <div><?php echo wpautop(esc_html($product_data['note'])); ?></div>
            </div>
            <?php endif; ?>
            
            <?php
            foreach ($config['texts']['accordion_titles'] as $key => $title) {
                if (!empty($product_data[$key])) {
                    echo '<div class="yprint-dynamic-accordion-item">';
                    echo '<div class="yprint-dynamic-accordion-header">';
                    echo '<span>' . esc_html($title) . '</span>';
                    echo '<span class="yprint-dynamic-accordion-icon">+</span>';
                    echo '</div>';
                    echo '<div class="yprint-dynamic-accordion-content">' . wpautop(esc_html($product_data[$key])) . '</div>';
                    echo '</div>';
                }
            }
            ?>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const accordion = document.getElementById('<?php echo esc_js($accordion_id); ?>');
            if (!accordion) return;
            
            const headers = accordion.querySelectorAll('.yprint-dynamic-accordion-header');
            
            headers.forEach(header => {
                header.addEventListener('click', function() {
                    const content = this.nextElementSibling;
                    const icon = this.querySelector('.yprint-dynamic-accordion-icon');
                    
                    // Toggle current
                    content.classList.toggle('active');
                    icon.classList.toggle('active');
                    
                    // Close others
                    headers.forEach(otherHeader => {
                        if (otherHeader !== header) {
                            const otherContent = otherHeader.nextElementSibling;
                            const otherIcon = otherHeader.querySelector('.yprint-dynamic-accordion-icon');
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
    public static function render_dynamic_mobile_product_page($atts) {
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            $config = self::get_config();
            return '<p>' . esc_html($config['texts']['woocommerce_inactive']) . '</p>';
        }
        
        $product = self::get_current_product();
        if (!$product) {
            $config = self::get_config();
            return '<p>' . esc_html($config['texts']['no_product']) . '</p>';
        }
        
        $config = self::get_config();
        $product_id = $product->get_id();
        
        ob_start();
        ?>
        <style>
            .yprint-dynamic-product-container {
                font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                max-width: 600px;
                margin: 0 auto;
                padding: 20px 16px 80px 16px;
                color: #333;
                line-height: 1.6;
            }
            
            .yprint-dynamic-gallery-container {
    margin-bottom: 24px;
    background-color: white;
    border-radius: 20px;
    border: 1px solid #e0e0e0;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
    max-width: 400px;
    margin-left: auto;
    margin-right: auto;
}

.yprint-dynamic-gallery-slider {
    overflow: hidden;
    position: relative;
    background: #F6F7FA;
    border-radius: 16px;
    border: 1px solid #DFDFDF;
    max-height: 400px;
}

.yprint-dynamic-gallery-slide img {
    width: 100%;
    height: auto;
    display: block;
    max-height: 400px;
    object-fit: contain;
}
            
            .yprint-dynamic-gallery-slides {
                display: flex;
                transition: transform 0.3s ease;
            }
            
            .yprint-dynamic-gallery-slide {
                min-width: 100%;
                box-sizing: border-box;
            }
            
            .yprint-dynamic-gallery-slide img {
                width: 100%;
                height: auto;
                display: block;
            }
            
            .yprint-dynamic-gallery-nav {
                display: flex;
                gap: 8px;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .yprint-dynamic-gallery-thumbnail {
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
            
            .yprint-dynamic-gallery-thumbnail img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }
            
            .yprint-dynamic-gallery-thumbnail.active {
                opacity: 1;
                border: 2px solid #0079FF;
            }
            
            .yprint-dynamic-no-images {
                text-align: center;
                padding: 40px 20px;
                background-color: #f8f9fa;
                border-radius: 20px;
                margin-bottom: 24px;
            }
            
            .yprint-dynamic-no-images p {
                color: #6c757d;
                font-style: italic;
                margin: 0;
            }
            
            /* Header Styles */
            .yprint-dynamic-product-header {
                text-align: center;
                margin-bottom: 20px;
            }
            
            .yprint-dynamic-product-title {
                font-size: 32px;
                font-weight: 700;
                margin: 0 0 8px 0;
                color: #1d1d1f;
            }
            
            .yprint-dynamic-product-info {
                font-size: 16px;
                font-weight: 500;
                color: #707070;
                margin: 0;
            }
            
            /* Description Styles */
            .yprint-dynamic-product-description {
                font-size: 16px;
                color: #707070;
                margin-bottom: 20px;
                line-height: 1.6;
            }
            
            /* Color Selection Styles */
            .yprint-dynamic-color-selection {
                display: flex;
                align-items: center;
                justify-content: space-between;
                margin-bottom: 24px;
                gap: 16px;
            }
            
            .yprint-dynamic-color-options {
                display: flex;
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .yprint-dynamic-color-circle {
                width: 24px;
                height: 24px;
                border-radius: 50%;
                border: 2px solid #E0E0E0;
                cursor: pointer;
                transition: transform 0.2s, border-color 0.2s;
            }
            
            .yprint-dynamic-color-circle:hover {
                transform: scale(1.1);
            }
            
            .yprint-dynamic-color-circle.selected {
                border-color: #0079FF;
                transform: scale(1.2);
            }
            
            .yprint-dynamic-sizing-link {
                font-size: 15px;
                color: #707070;
                text-decoration: underline;
                cursor: pointer;
            }
            
            .yprint-dynamic-sizing-link:hover {
                color: #0079FF;
            }
            
            /* Action Buttons Styles */
            .yprint-dynamic-action-buttons {
                display: flex;
                flex-direction: column;
                gap: 12px;
                margin-bottom: 32px;
            }
            
            .yprint-dynamic-designer-btn {
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
            
            .yprint-dynamic-designer-btn:hover {
                background-color: #0062cc;
                color: white;
            }
            
            .yprint-dynamic-buy-blank-btn {
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
            
            .yprint-dynamic-buy-blank-btn:hover {
                background-color: #f5f5f5;
            }
            
            /* Accordion Styles */
            .yprint-dynamic-accordion {
                border-top: 1px solid #e5e5e5;
                margin-bottom: 20px;
            }
            
            .yprint-dynamic-note {
                margin-bottom: 20px;
                padding: 16px;
                background-color: #f8f9fa;
                border-radius: 12px;
            }
            
            .yprint-dynamic-note h3 {
                font-size: 18px;
                font-weight: 600;
                margin: 0 0 8px 0;
                color: #1d1d1f;
            }
            
            .yprint-dynamic-accordion-item {
                border-bottom: 1px solid #e5e5e5;
            }
            
            .yprint-dynamic-accordion-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 16px 0;
                cursor: pointer;
                font-size: 16px;
                font-weight: 600;
                color: #1d1d1f;
            }
            
            .yprint-dynamic-accordion-header:hover {
                opacity: 0.8;
            }
            
            .yprint-dynamic-accordion-content {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease-out;
                padding: 0;
            }
            
            .yprint-dynamic-accordion-content.active {
                max-height: 500px;
                padding: 0 0 16px 0;
            }
            
            .yprint-dynamic-accordion-icon {
                font-size: 20px;
                transition: transform 0.3s ease;
                color: #0079FF;
            }
            
            .yprint-dynamic-accordion-icon.active {
                transform: rotate(45deg);
            }
            
            @media (max-width: 480px) {
    .yprint-dynamic-product-container {
        padding: 16px 12px 80px 12px;
    }
    
    .yprint-dynamic-product-title {
        font-size: 28px;
    }
    
    .yprint-dynamic-gallery-container {
        max-width: 350px;
    }
    
    .yprint-dynamic-gallery-slider {
        max-height: 350px;
    }
    
    .yprint-dynamic-gallery-slide img {
        max-height: 350px;
    }
    
    .yprint-dynamic-gallery-thumbnail {
        width: 50px;
        height: 50px;
    }
    
    .yprint-dynamic-color-selection {
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
}
/* Sizing Popup Styles */
.yprint-sizing-popup {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: none;
    justify-content: center;
    align-items: center;
}

.yprint-sizing-popup-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 20px;
    box-sizing: border-box;
}

.yprint-sizing-popup-content {
    background-color: white;
    border-radius: 12px;
    max-width: 600px;
    max-height: 80vh;
    width: 100%;
    overflow: hidden;
    position: relative;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
}

.yprint-sizing-popup-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 20px;
    border-bottom: 1px solid #e5e5e5;
    background-color: #f8f9fa;
}

.yprint-sizing-popup-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
    color: #1d1d1f;
}

.yprint-sizing-popup-close {
    font-size: 24px;
    cursor: pointer;
    color: #707070;
    background: none;
    border: none;
    padding: 0;
    line-height: 1;
}

.yprint-sizing-popup-close:hover {
    color: #0079FF;
}

.yprint-sizing-popup-body {
    padding: 20px;
    overflow-y: auto;
    max-height: calc(80vh - 80px);
}

.yprint-sizing-table {
    width: 100%;
    border-collapse: collapse;
    font-family: 'Roboto', sans-serif;
}

.yprint-sizing-table th,
.yprint-sizing-table td {
    border: 1px solid #e0e0e0;
    padding: 12px;
    text-align: center;
}

.yprint-sizing-table th {
    background-color: #f5f5f5;
    color: #707070;
    font-weight: 600;
}

.yprint-sizing-table tr:nth-child(even) {
    background-color: #fafafa;
}

.sizing-text {
    line-height: 1.6;
    color: #333;
}

@media (max-width: 480px) {
    .yprint-sizing-popup-overlay {
        padding: 10px;
    }
    
    .yprint-sizing-popup-content {
        max-height: 90vh;
    }
    
    .yprint-sizing-popup-header,
    .yprint-sizing-popup-body {
        padding: 15px;
    }
    
    .yprint-sizing-table th,
    .yprint-sizing-table td {
        padding: 8px 4px;
        font-size: 14px;
    }
}

        </style>
        
        <div class="yprint-dynamic-product-container" data-product-id="<?php echo esc_attr($product_id); ?>">
            <?php echo self::render_product_gallery($product, $config); ?>
            <?php echo self::render_product_header($product); ?>
            <?php echo self::render_product_description($product); ?>
            <?php echo self::render_color_selection($product, $config); ?>
            <?php echo self::render_action_buttons($product, $config); ?>
            <?php echo self::render_product_accordion($product, $config); ?>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const productContainer = document.querySelector('[data-product-id="<?php echo esc_js($product_id); ?>"]');
            if (!productContainer) return;
            
            const eventConfig = <?php echo json_encode($config['events']); ?>;
            const productId = '<?php echo esc_js($product_id); ?>';
            
            // Buy Blank button functionality
            const buyBlankButtons = productContainer.querySelectorAll('.yprint-dynamic-buy-blank-btn');
            buyBlankButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const productId = this.dataset.productId;
                    if (!productId) return;
                    
                    // Show loading state
                    const originalText = this.textContent;
                    this.textContent = '<?php echo esc_js(__('Adding...', 'yprint')); ?>';
                    this.style.opacity = '0.6';
                    this.style.pointerEvents = 'none';
                    
                    // Add to cart
                    fetch('/?add-to-cart=' + productId, {
                        method: 'GET',
                        credentials: 'same-origin'
                    })
                    .then(response => {
                        // Reset button state
                        this.textContent = originalText;
                        this.style.opacity = '1';
                        this.style.pointerEvents = 'auto';
                        
                        if (response.ok) {
                            // Try to trigger cart popup
                            const cartPopup = document.querySelector('#cart');
                            if (cartPopup) {
                                cartPopup.style.display = 'block';
                                cartPopup.classList.add('show');
                                
                                // jQuery fallback
                                if (typeof jQuery !== 'undefined') {
                                    jQuery('#cart').fadeIn();
                                }
                            }
                            
                            // Dispatch custom event
                            document.dispatchEvent(new CustomEvent(eventConfig.cart_open, { 
                                detail: { productId: productId, action: 'buy_blank' } 
                            }));
                        } else {
                            throw new Error('Failed to add to cart');
                        }
                    })
                    .catch(error => {
                        console.error('Error adding to cart:', error);
                        
                        // Reset button state
                        this.textContent = originalText;
                        this.style.opacity = '1';
                        this.style.pointerEvents = 'auto';
                        
                        // Show error message
                        alert('<?php echo esc_js(__('Error adding product to cart. Please try again.', 'yprint')); ?>');
                    });
                });
            });
            
            // Global event listeners for external integrations
            document.addEventListener(eventConfig.sizing_chart, function(e) {
                if (e.detail && e.detail.productId === productId) {
                    console.log('Sizing chart requested for product:', e.detail.productId);
                    console.log('Sizing data:', e.detail.sizingData);
                    // External sizing chart integration can hook into this event
                }
            });
            
            document.addEventListener(eventConfig.color_selected, function(e) {
                if (e.detail && e.detail.productId === productId) {
                    console.log('Color selected for product:', e.detail.productId);
                    console.log('Color:', e.detail.colorName, 'ID:', e.detail.colorId);
                    // External color handling can hook into this event
                }
            });
        });
        </script>
        <?php
        
        return ob_get_clean();
    }
}

// Initialize the dynamic mobile product page
YPrint_Dynamic_Mobile_Product::init();