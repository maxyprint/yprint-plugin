<?php
/**
 * YPrint Product Slider Shortcode - Redesigned
 * Displays all WooCommerce products in a modern horizontal slider
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle Product Slider functionality
 */
class YPrint_Product_Slider {

    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode('product_slider', array(__CLASS__, 'render_product_slider'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_slider_assets'));
    }

    /**
     * Enqueue slider assets
     */
    public static function enqueue_slider_assets() {
        // Only enqueue on pages that might use the slider
        if (is_page() || is_home() || is_front_page()) {
            wp_enqueue_style(
                'yprint-product-slider',
                YPRINT_PLUGIN_URL . 'assets/css/product-slider.css',
                array(),
                YPRINT_PLUGIN_VERSION
            );
            
            wp_enqueue_script(
                'yprint-product-slider',
                YPRINT_PLUGIN_URL . 'assets/js/product-slider.js',
                array('jquery'),
                YPRINT_PLUGIN_VERSION,
                true
            );
        }
    }

    /**
     * Render Product Slider shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_product_slider($atts) {
        $atts = shortcode_atts(array(
            'title' => 'Design a Shirt',
            'limit' => 12,
            'category' => '',
            'class' => 'yprint-product-slider'
        ), $atts, 'product_slider');

        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            return '<p class="error-message">WooCommerce ist nicht aktiviert.</p>';
        }

        $products = self::get_products($atts);
        

        
        if (empty($products)) {
            return '<p style="text-align: center; padding: 20px; color: #6D6D6D; font-size: 14px; margin: 0;">Keine Produkte gefunden.</p>';
        }

        $unique_id = 'yprint-product-slider-' . uniqid();
        $css_class = sanitize_html_class($atts['class']);
        $title = sanitize_text_field($atts['title']);

        ob_start();
        ?>

        <style>
        .yprint-product-slider-container {
            font-family: system-ui, 'Segoe UI', Roboto, Helvetica, sans-serif;
            background-color: #ffffff;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin: 0 0 2rem 0;
        }

        .yprint-product-slider-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .yprint-product-slider-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }

        .yprint-product-slider-controls {
            display: flex;
            gap: 8px;
        }

        .yprint-slider-nav-btn {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            border: 1px solid #E5E5E5;
            background: #FFFFFF;
            color: #6D6D6D;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .yprint-slider-nav-btn:hover {
            background: #F9F9F9;
            color: #1A1A1A;
            border-color: #6D6D6D;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }

        .yprint-slider-nav-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }

        .yprint-slider-nav-btn:disabled:hover {
            background: #FFFFFF;
            color: #6D6D6D;
            border-color: #E5E5E5;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .yprint-product-slider-wrapper {
            position: relative;
            overflow: hidden;
        }

        .yprint-product-slider-track {
            display: flex;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            gap: 16px;
        }

        .yprint-product-card {
            flex: 0 0 200px;
            background: #FFFFFF;
            border-radius: 16px;
            border: 1px solid #E5E5E5;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            padding: 16px;
        }

        .yprint-product-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.12);
            border-color: #6D6D6D;
        }

        .yprint-product-image-container {
            position: relative;
            width: 100%;
            height: 140px;
            background: #F9F9F9;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            margin-bottom: 12px;
        }

        .yprint-product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .yprint-product-card:hover .yprint-product-image {
            transform: scale(1.02);
        }

        .yprint-product-info {
            position: relative;
            padding: 0;
        }

        .yprint-product-name {
            font-size: 16px;
            font-weight: 600;
            color: #1A1A1A;
            margin: 0 0 12px 0;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .yprint-product-button {
            width: 100%;
            background: #0079FF;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 121, 255, 0.2);
        }

        .yprint-product-button:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 121, 255, 0.3);
            color: white;
            text-decoration: none;
        }

        .yprint-product-button i {
            font-size: 14px;
        }

        /* Mobile Responsive */
        @media (max-width: 1024px) {
            .yprint-product-card {
                flex: 0 0 180px;
            }
        }

        @media (max-width: 768px) {
            .yprint-product-slider-container {
                padding: 1rem;
                border-radius: 8px;
            }

            .yprint-product-slider-title {
                font-size: 16px;
            }

            .yprint-product-card {
                flex: 0 0 160px;
                padding: 12px;
            }

            .yprint-product-slider-track {
                gap: 12px;
            }

            .yprint-product-slider-controls {
                display: none;
            }

            .yprint-product-image-container {
                height: 120px;
                margin-bottom: 8px;
            }

            .yprint-product-name {
                font-size: 14px;
                margin-bottom: 8px;
            }

            .yprint-product-button {
                padding: 8px 12px;
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .yprint-product-slider-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .yprint-product-card {
                flex: 0 0 140px;
                padding: 10px;
            }

            .yprint-product-slider-track {
                gap: 10px;
            }

            .yprint-product-image-container {
                height: 100px;
                margin-bottom: 6px;
            }

            .yprint-product-name {
                font-size: 13px;
                margin-bottom: 6px;
            }

            .yprint-product-button {
                padding: 6px 10px;
                font-size: 12px;
                gap: 4px;
            }
        }

        /* Touch scrolling for mobile */
        @media (max-width: 768px) {
            .yprint-product-slider-wrapper {
                overflow-x: auto;
                overflow-y: hidden;
                scroll-snap-type: x mandatory;
                -webkit-overflow-scrolling: touch;
                scrollbar-width: none;
                -ms-overflow-style: none;
            }

            .yprint-product-slider-wrapper::-webkit-scrollbar {
                display: none;
            }

            .yprint-product-card {
                scroll-snap-align: start;
            }
        }
        </style>

        <div id="<?php echo esc_attr($unique_id); ?>" class="<?php echo esc_attr($css_class); ?>-container">
            <div class="yprint-product-slider-header">
                <h2 class="yprint-product-slider-title"><?php echo esc_html($title); ?></h2>
                <div class="yprint-product-slider-controls">
                    <button class="yprint-slider-nav-btn yprint-slider-prev" aria-label="Vorherige Produkte">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="yprint-slider-nav-btn yprint-slider-next" aria-label="Nächste Produkte">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>

            <div class="yprint-product-slider-wrapper">
                <div class="yprint-product-slider-track">
                    <?php foreach ($products as $product) : ?>
                        <div class="yprint-product-card" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                            <div class="yprint-product-image-container">
                                <?php 
                                $image_id = $product->get_image_id();
                                if ($image_id) {
                                    echo wp_get_attachment_image($image_id, 'medium', false, array(
                                        'class' => 'yprint-product-image',
                                        'alt' => esc_attr($product->get_name())
                                    ));
                                } else {
                                    echo '<div class="yprint-product-image" style="background: linear-gradient(135deg, #6D6D6D 0%, #1A1A1A 100%); display: flex; align-items: center; justify-content: center;">';
                                    echo '<i class="fas fa-image" style="font-size: 24px; color: white; opacity: 0.5;"></i>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            
                            <div class="yprint-product-info">
                                <h3 class="yprint-product-name"><?php echo esc_html($product->get_name()); ?></h3>
                                <a href="<?php echo esc_url(get_permalink($product->get_id())); ?>" 
                                   class="yprint-product-button">
                                    <i class="fas fa-palette"></i>
                                    Design
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) return;

            const track = container.querySelector('.yprint-product-slider-track');
            const prevBtn = container.querySelector('.yprint-slider-prev');
            const nextBtn = container.querySelector('.yprint-slider-next');
            const cards = container.querySelectorAll('.yprint-product-card');
            
            if (!track || !prevBtn || !nextBtn || cards.length === 0) return;

            let currentIndex = 0;
            const cardWidth = 216; // Card width + gap (200px + 16px)
            const visibleCards = Math.floor(container.offsetWidth / cardWidth);
            const maxIndex = Math.max(0, cards.length - visibleCards);

            // Update button states
            function updateButtons() {
                prevBtn.disabled = currentIndex <= 0;
                nextBtn.disabled = currentIndex >= maxIndex;
            }

            // Move slider
            function moveSlider() {
                const translateX = -(currentIndex * cardWidth);
                track.style.transform = `translateX(${translateX}px)`;
                updateButtons();
            }

            // Previous button
            prevBtn.addEventListener('click', function() {
                if (currentIndex > 0) {
                    currentIndex = Math.max(0, currentIndex - Math.max(1, visibleCards - 1));
                    moveSlider();
                }
            });

            // Next button
            nextBtn.addEventListener('click', function() {
                if (currentIndex < maxIndex) {
                    currentIndex = Math.min(maxIndex, currentIndex + Math.max(1, visibleCards - 1));
                    moveSlider();
                }
            });

            // Card click navigation
            cards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't navigate if clicking on the button
                    if (e.target.closest('.yprint-product-button')) {
                        return;
                    }
                    const productId = this.dataset.productId;
                    if (productId) {
                        window.location.href = '<?php echo esc_url(home_url('/products/')); ?>?product_id=' + productId;
                    }
                });
            });

            // Initialize
            updateButtons();

            // Handle window resize
            let resizeTimeout;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimeout);
                resizeTimeout = setTimeout(() => {
                    const newVisibleCards = Math.floor(container.offsetWidth / cardWidth);
                    const newMaxIndex = Math.max(0, cards.length - newVisibleCards);
                    currentIndex = Math.min(currentIndex, newMaxIndex);
                    moveSlider();
                }, 100);
            });

            // Touch/swipe support for mobile
            let startX = 0;
            let isDragging = false;

            track.addEventListener('touchstart', function(e) {
                startX = e.touches[0].clientX;
                isDragging = true;
            });

            track.addEventListener('touchmove', function(e) {
                if (!isDragging) return;
                e.preventDefault();
            });

            track.addEventListener('touchend', function(e) {
                if (!isDragging) return;
                
                const endX = e.changedTouches[0].clientX;
                const diffX = startX - endX;
                
                if (Math.abs(diffX) > 50) { // Minimum swipe distance
                    if (diffX > 0 && currentIndex < maxIndex) {
                        // Swipe left - next
                        currentIndex++;
                        moveSlider();
                    } else if (diffX < 0 && currentIndex > 0) {
                        // Swipe right - previous
                        currentIndex--;
                        moveSlider();
                    }
                }
                
                isDragging = false;
            });
        });
        </script>

        <?php
        return ob_get_clean();
    }

    /**
     * Get products for the slider
     *
     * @param array $atts Shortcode attributes
     * @return array Array of WC_Product objects
     */
    private static function get_products($atts) {
        // Verwende WooCommerce's eigene Produktabfrage
        $args = array(
            'status' => 'publish',
            'limit' => intval($atts['limit']),
            'orderby' => 'date',
            'order' => 'DESC',
        );

        // Add category filter if specified
        if (!empty($atts['category'])) {
            $category_slugs = array_map('trim', explode(',', $atts['category']));
            $args['category'] = $category_slugs;
        }

        // Use WooCommerce's wc_get_products function
        $products = wc_get_products($args);

        // Fallback: If no products found, try simpler query
        if (empty($products)) {
            $fallback_args = array(
                'post_type' => 'product',
                'post_status' => 'publish',
                'posts_per_page' => intval($atts['limit']),
                'orderby' => 'date',
                'order' => 'DESC'
            );

            // Add category filter for fallback
            if (!empty($atts['category'])) {
                $fallback_args['tax_query'] = array(
                    array(
                        'taxonomy' => 'product_cat',
                        'field' => 'slug',
                        'terms' => array_map('trim', explode(',', $atts['category'])),
                        'operator' => 'IN'
                    )
                );
            }

            $query = new WP_Query($fallback_args);
            $products = array();

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $product = wc_get_product(get_the_ID());
                    if ($product) {
                        $products[] = $product;
                    }
                }
                wp_reset_postdata();
            }
        }

        return $products;
    }
}

// Initialize the class
YPrint_Product_Slider::init();