<?php
/**
 * YPrint Product Slider Shortcode
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
            return '<p class="yprint-no-products">Keine Produkte gefunden.</p>';
        }

        $unique_id = 'yprint-product-slider-' . uniqid();
        $css_class = sanitize_html_class($atts['class']);
        $title = sanitize_text_field($atts['title']);

        ob_start();
        ?>

        <style>
        .yprint-product-slider-container {
            font-family: -apple-system, BlinkMacSystemFont, 'Inter', 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            margin: 32px 0;
            background: #ffffff;
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
        }

        .yprint-product-slider-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .yprint-product-slider-title {
            font-size: 28px;
            font-weight: 700;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }

        .yprint-product-slider-controls {
            display: flex;
            gap: 8px;
        }

        .yprint-slider-nav-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            border: 1px solid #e5e7eb;
            background: #ffffff;
            color: #6b7280;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            font-size: 16px;
        }

        .yprint-slider-nav-btn:hover {
            background: #f9fafb;
            color: #374151;
            border-color: #d1d5db;
            transform: translateY(-1px);
        }

        .yprint-slider-nav-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
        }

        .yprint-slider-nav-btn:disabled:hover {
            background: #ffffff;
            color: #6b7280;
            border-color: #e5e7eb;
            transform: none;
        }

        .yprint-product-slider-wrapper {
            position: relative;
            overflow: hidden;
            border-radius: 12px;
        }

        .yprint-product-slider-track {
            display: flex;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            gap: 20px;
            padding: 4px;
        }

        .yprint-product-card {
            flex: 0 0 280px;
            background: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            overflow: hidden;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .yprint-product-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
            border-color: #d1d5db;
        }

        .yprint-product-image-container {
            position: relative;
            width: 100%;
            height: 200px;
            background: #f9fafb;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .yprint-product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .yprint-product-card:hover .yprint-product-image {
            transform: scale(1.05);
        }

        .yprint-product-info {
            padding: 20px;
            position: relative;
        }

        .yprint-product-name {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 8px 0;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .yprint-product-price {
            font-size: 16px;
            font-weight: 500;
            color: #6b7280;
            margin: 0 0 8px 0;
        }

        .yprint-product-description {
            font-size: 14px;
            color: #6b7280;
            line-height: 1.4;
            margin: 0 0 16px 0;
            height: 40px;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .yprint-product-button {
            position: absolute;
            bottom: 20px;
            right: 20px;
            background: #007AFF;
            color: white;
            border: none;
            border-radius: 50px;
            padding: 10px 20px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 2px 8px rgba(0, 122, 255, 0.2);
        }

        .yprint-product-button:hover {
            background: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 122, 255, 0.3);
        }

        .yprint-product-button i {
            font-size: 12px;
        }

        .yprint-no-products {
            text-align: center;
            padding: 40px;
            color: #6b7280;
            font-style: italic;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .yprint-product-card {
                flex: 0 0 260px;
            }
        }

        @media (max-width: 768px) {
            .yprint-product-slider-container {
                padding: 20px;
                margin: 20px 0;
            }

            .yprint-product-slider-title {
                font-size: 24px;
            }

            .yprint-product-card {
                flex: 0 0 240px;
            }

            .yprint-product-slider-track {
                gap: 16px;
            }

            .yprint-product-slider-controls {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .yprint-product-card {
                flex: 0 0 200px;
            }

            .yprint-product-slider-track {
                gap: 12px;
            }

            .yprint-product-info {
                padding: 16px;
            }

            .yprint-product-name {
                font-size: 16px;
            }

            .yprint-product-price {
                font-size: 14px;
            }

            .yprint-product-description {
                font-size: 13px;
                height: 35px;
            }

            .yprint-product-button {
                bottom: 16px;
                right: 16px;
                padding: 8px 16px;
                font-size: 13px;
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
                                    echo '<div class="yprint-product-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">';
                                    echo '<i class="fas fa-image" style="font-size: 32px; color: white; opacity: 0.7;"></i>';
                                    echo '</div>';
                                }
                                ?>
                            </div>
                            
                            <div class="yprint-product-info">
                                <h3 class="yprint-product-name"><?php echo esc_html($product->get_name()); ?></h3>
                                <div class="yprint-product-price"><?php echo $product->get_price_html(); ?></div>
                                <div class="yprint-product-description">
                                    <?php 
                                    $description = $product->get_short_description();
                                    if (empty($description)) {
                                        $categories = get_the_terms($product->get_id(), 'product_cat');
                                        if ($categories && !is_wp_error($categories)) {
                                            $description = $categories[0]->name . ' - Individuell gestaltbar';
                                        } else {
                                            $description = 'Individuell gestaltbares Produkt';
                                        }
                                    }
                                    echo wp_trim_words($description, 12, '...');
                                    ?>
                                </div>
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
            const cardWidth = 300; // Card width + gap
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
                    currentIndex = Math.max(0, currentIndex - visibleCards);
                    moveSlider();
                }
            });

            // Next button
            nextBtn.addEventListener('click', function() {
                if (currentIndex < maxIndex) {
                    currentIndex = Math.min(maxIndex, currentIndex + visibleCards);
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
                    currentIndex = 0;
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
        $args = array(
            'post_type' => 'product',
            'post_status' => 'publish',
            'posts_per_page' => intval($atts['limit']),
            'meta_query' => array(
                array(
                    'key' => '_visibility',
                    'value' => array('catalog', 'visible'),
                    'compare' => 'IN'
                )
            )
        );

        // Add category filter if specified
        if (!empty($atts['category'])) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => explode(',', $atts['category'])
                )
            );
        }

        $query = new WP_Query($args);
        $products = array();

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $product = wc_get_product(get_the_ID());
                if ($product && $product->is_visible()) {
                    $products[] = $product;
                }
            }
            wp_reset_postdata();
        }

        return $products;
    }
}

// Initialize the class
YPrint_Product_Slider::init();