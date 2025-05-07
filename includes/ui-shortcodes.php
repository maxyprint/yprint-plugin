<?php
/**
 * UI-related shortcodes for YPrint
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