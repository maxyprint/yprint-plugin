<?php
/**
 * UI-related shortcodes and components for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to display the current page title with custom styling
 * 
 * Usage: [styled_page_title]
 * 
 * @return string The styled page title
 */
function styled_page_title_shortcode() {
    $title = get_the_title(); // Holt den aktuellen Seitentitel
    return '<div style="font-family: \'Roboto\', sans-serif; font-size: 25pt; font-weight: 600;">' . esc_html($title) . '</div>';
}
add_shortcode('styled_page_title', 'styled_page_title_shortcode');

/**
 * Shortcode to display a styled heading with custom text
 * 
 * Usage: [styled_heading text="Your Heading Text"]
 * 
 * @param array $atts Shortcode attributes
 * @return string The styled heading
 */
function styled_heading_shortcode($atts) {
    $atts = shortcode_atts(array(
        'text' => '',
        'size' => '25pt',
        'weight' => '600',
        'align' => 'left'
    ), $atts, 'styled_heading');
    
    return '<div style="font-family: \'Roboto\', sans-serif; font-size: ' . esc_attr($atts['size']) . '; font-weight: ' . esc_attr($atts['weight']) . '; text-align: ' . esc_attr($atts['align']) . ';">' . esc_html($atts['text']) . '</div>';
}
add_shortcode('styled_heading', 'styled_heading_shortcode');

/**
 * Shortcode to create a styled separator (horizontal line)
 * 
 * Usage: [styled_separator]
 * 
 * @param array $atts Shortcode attributes
 * @return string The styled separator
 */
function styled_separator_shortcode($atts) {
    $atts = shortcode_atts(array(
        'color' => '#0079FF',
        'width' => '100%',
        'height' => '3px',
        'margin' => '20px 0'
    ), $atts, 'styled_separator');
    
    return '<div style="background-color: ' . esc_attr($atts['color']) . '; width: ' . esc_attr($atts['width']) . '; height: ' . esc_attr($atts['height']) . '; margin: ' . esc_attr($atts['margin']) . ';"></div>';
}
add_shortcode('styled_separator', 'styled_separator_shortcode');

/**
 * Designer Button Shortcode - Links to the yprint designer tool
 * 
 * Usage: [designer_button label="Design" fallback_id="3657"]
 * 
 * @param array $atts Shortcode attributes
 * @return string The designer button HTML
 */
function yprint_designer_button_shortcode($atts) {
    // Default attributes
    $atts = shortcode_atts(array(
        'fallback_id' => '', // Fallback product ID
        'label' => 'Design' // Customizable button text
    ), $atts);
    
    // Get current product
    global $product;
    
    // Get product SKU (article number)
    $template_id = $product ? $product->get_sku() : $atts['fallback_id'];
    
    // If no SKU is available, use fallback ID
    if (empty($template_id)) {
        $template_id = $atts['fallback_id'];
    }
    
    // Fallback, if still no ID
    if (empty($template_id)) {
        $template_id = '3657'; // Default template ID
    }
    
    // Generate button HTML
    $button_html = sprintf(
        '<a href="%s" class="custom-designer-button" style="display: inline-block; background-color: #0079FF; color: white; padding: 5px 20px; text-decoration: none; border-radius: 15px; font-weight: bold; text-align: center; width: 100%%; box-sizing: border-box;">%s</a>',
        esc_url(add_query_arg('template_id', $template_id, 'https://yprint.de/designer')),
        esc_html($atts['label'])
    );
    
    return $button_html;
}
add_shortcode('designer_button', 'yprint_designer_button_shortcode');

/**
 * Loading Animation for specified pages
 */
function yprint_loading_animation() {
    // Only show on products page
    if (!is_page('products')) {
        return;
    }
    
    ?>
    <div id="yprint-loading-overlay">
        <div id="yprint-loading-animation">
            <img src="https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg" alt="Loading...">
        </div>
    </div>

    <style>
    #yprint-loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        z-index: 9999;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    #yprint-loading-animation {
        width: 200px;
        height: 200px;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    #yprint-loading-animation img {
        width: 100%;
        height: 100%;
        object-fit: contain;
        animation: yprint-glow 2s ease-in-out infinite alternate;
    }

    @keyframes yprint-glow {
        0% {
            filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.7));
        }
        100% {
            filter: drop-shadow(0 0 20px rgba(255, 255, 255, 1));
        }
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hide animation after 2 seconds
        setTimeout(function() {
            var overlay = document.getElementById('yprint-loading-overlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }, 2000);
    });
    </script>
    <?php
}
add_action('wp_footer', 'yprint_loading_animation');

/**
 * Shortcode to display the current URL path with styling
 * 
 * Usage: [current_page]
 * 
 * @return string The formatted current page path
 */
function yprint_current_page_shortcode() {
    // Get the path after the domain
    $current_path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    
    // Set default styling, can be customized via parameters if needed in the future
    $style = 'font-family: \'Roboto\', sans-serif; font-size: 40px; font-weight: 600;';
    
    // Return the formatted path
    return '<div style="' . esc_attr($style) . '">' . esc_html($current_path) . '</div>';
}
add_shortcode('current_page', 'yprint_current_page_shortcode');