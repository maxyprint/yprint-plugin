<?php
/**
 * YPrint Vectorizer
 *
 * @package     YPrint
 * @subpackage  Vectorizer
 * @copyright   Copyright (c) 2025, YPrint
 * @license     GPL-2.0+
 * @since       1.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main Vectorizer Class
 *
 * Handles image to vector conversion functionalities
 */
class YPrint_Vectorizer {

    /**
     * Instance of this class
     *
     * @since 1.0.0
     * @var object
     */
    protected static $instance = null;

    /**
     * Trace engine type: potrace or autotrace
     *
     * @since 1.0.0
     * @var string
     */
    protected $engine_type = 'potrace';

    /**
     * Default vectorization options
     *
     * @since 1.0.0
     * @var array
     */
    protected $default_options = array(
        // General options
        'detail_level'       => 'medium',    // low, medium, high, ultra
        'invert'             => false,
        'remove_background'  => true,
        
        // Potrace specific options
        'brightness_threshold' => 0.45,
        'optitolerance'      => 0.2,
        'alphamax'           => 1.0,
        'turdsize'           => 2,
        'opticurve'          => 1,
        
        // Color options
        'color_type'         => 'mono',      // mono, color, gray
        'colors'             => 8,
        'stack_colors'       => true,
        'smooth_colors'      => false,
    );

    /**
     * Class constructor
     *
     * @since 1.0.0
     */
    public function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Get the singleton instance of this class
     *
     * @since 1.0.0
     * @return YPrint_Vectorizer
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Include required files
     *
     * @since 1.0.0
     */
    private function includes() {
        // Check if we have the potrace binary
        if (!$this->check_potrace_exists()) {
            add_action('admin_notices', array($this, 'missing_potrace_notice'));
        }
    }

    /**
     * Initialize hooks
     *
     * @since 1.0.0
     */
    private function init_hooks() {
        // Admin page and settings
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_yprint_vectorize_image', array($this, 'ajax_vectorize_image'));
        add_action('wp_ajax_yprint_save_svg', array($this, 'ajax_save_svg'));
        
        // Shortcodes
        add_shortcode('yprint_vectorizer', array($this, 'vectorizer_shortcode'));
    }

    /**
     * Check if potrace is installed on the server
     *
     * @since 1.0.0
     * @return boolean
     */
    public function check_potrace_exists() {
        // First check if we have the binary in our plugin directory
        $plugin_potrace = plugin_dir_path(__FILE__) . 'bin/potrace';
        
        if (file_exists($plugin_potrace) && is_executable($plugin_potrace)) {
            return true;
        }
        
        // Check specific IONOS path
        $ionos_potrace = '/homepages/31/d4298451771/htdocs/.local/bin/potrace';
        if (file_exists($ionos_potrace) && is_executable($ionos_potrace)) {
            // Optional: Create a symlink or script in the bin directory for easier access
            if (!file_exists(dirname($plugin_potrace))) {
                @mkdir(dirname($plugin_potrace), 0755, true);
            }
            
            if (!file_exists($plugin_potrace)) {
                // Create a wrapper script that points to the IONOS Potrace
                $script_content = "#!/bin/sh\n$ionos_potrace \"\$@\"\n";
                @file_put_contents($plugin_potrace, $script_content);
                @chmod($plugin_potrace, 0755);
            }
            
            return true;
        }
        
        // Standard system check
        $output = array();
        $return_val = 0;
        exec('which potrace 2>&1', $output, $return_val);
        
        return $return_val === 0;
    }

    /**
     * Display admin notice for missing potrace
     *
     * @since 1.0.0
     */
    public function missing_potrace_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('YPrint Vectorizer requires potrace to be installed on your server. Please install potrace or contact your server administrator.', 'yprint-vectorizer'); ?></p>
        </div>
        <?php
    }

    /**
     * Enqueue required scripts and styles
     *
     * @since 1.0.0
     */
    public function enqueue_scripts($hook) {
        if ('toplevel_page_yprint-vectorizer' !== $hook) {
            return;
        }

        wp_enqueue_media();
        
        wp_enqueue_style(
            'yprint-vectorizer-style',
            plugin_dir_url(__FILE__) . 'assets/css/vectorizer.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'yprint-vectorizer-script',
            plugin_dir_url(__FILE__) . 'assets/js/vectorizer.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script(
            'yprint-vectorizer-script',
            'yprintVectorizer',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('yprint-vectorizer-nonce'),
            )
        );
    }

    /**
     * Render admin page
     *
     * @since 1.0.0
     */
    public function admin_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('YPrint Image Vectorizer', 'yprint-vectorizer'); ?></h1>
            
            <div class="yprint-vectorizer-container">
                <div class="yprint-vectorizer-upload">
                    <h2><?php _e('Upload Image', 'yprint-vectorizer'); ?></h2>
                    <button id="yprint-upload-image" class="button button-primary">
                        <?php _e('Select Image', 'yprint-vectorizer'); ?>
                    </button>
                    <div id="yprint-image-preview" class="image-preview-container"></div>
                </div>
                
                <div class="yprint-vectorizer-options">
                    <h2><?php _e('Vectorization Options', 'yprint-vectorizer'); ?></h2>
                    
                    <div class="option-group">
                        <label><?php _e('Detail Level', 'yprint-vectorizer'); ?></label>
                        <select id="yprint-detail-level">
                            <option value="low"><?php _e('Low (Faster, less details)', 'yprint-vectorizer'); ?></option>
                            <option value="medium" selected><?php _e('Medium', 'yprint-vectorizer'); ?></option>
                            <option value="high"><?php _e('High', 'yprint-vectorizer'); ?></option>
                            <option value="ultra"><?php _e('Ultra (Slower, more details)', 'yprint-vectorizer'); ?></option>
                        </select>
                    </div>
                    
                    <div class="option-group">
                        <label><?php _e('Color Mode', 'yprint-vectorizer'); ?></label>
                        <select id="yprint-color-mode">
                            <option value="mono" selected><?php _e('Black & White', 'yprint-vectorizer'); ?></option>
                            <option value="gray"><?php _e('Grayscale', 'yprint-vectorizer'); ?></option>
                            <option value="color"><?php _e('Color', 'yprint-vectorizer'); ?></option>
                        </select>
                    </div>
                    
                    <div class="option-group color-options" style="display: none;">
                        <label><?php _e('Number of Colors', 'yprint-vectorizer'); ?></label>
                        <input type="range" id="yprint-colors" min="2" max="16" value="8">
                        <span class="color-value">8</span>
                    </div>
                    
                    <div class="option-group">
                        <label>
                            <input type="checkbox" id="yprint-invert" value="1">
                            <?php _e('Invert Colors', 'yprint-vectorizer'); ?>
                        </label>
                    </div>
                    
                    <div class="option-group">
                        <label>
                            <input type="checkbox" id="yprint-remove-bg" value="1" checked>
                            <?php _e('Remove Background', 'yprint-vectorizer'); ?>
                        </label>
                    </div>
                    
                    <div class="option-group advanced-toggle">
                        <a href="#" id="toggle-advanced"><?php _e('Advanced Options', 'yprint-vectorizer'); ?> ▼</a>
                    </div>
                    
                    <div class="advanced-options" style="display: none;">
                        <div class="option-group">
                            <label><?php _e('Brightness Threshold', 'yprint-vectorizer'); ?></label>
                            <input type="range" id="yprint-brightness" min="0" max="1" step="0.05" value="0.45">
                            <span class="brightness-value">0.45</span>
                        </div>
                        
                        <div class="option-group">
                            <label><?php _e('Turd Size (Noise removal)', 'yprint-vectorizer'); ?></label>
                            <input type="range" id="yprint-turdsize" min="0" max="10" value="2">
                            <span class="turd-value">2</span>
                        </div>
                        
                        <div class="option-group">
                            <label>
                                <input type="checkbox" id="yprint-opticurve" value="1" checked>
                                <?php _e('Optimize Curves', 'yprint-vectorizer'); ?>
                            </label>
                        </div>
                    </div>
                    
                    <div class="action-buttons">
                        <button id="yprint-vectorize" class="button button-primary" disabled>
                            <?php _e('Vectorize', 'yprint-vectorizer'); ?>
                        </button>
                        <div id="yprint-progress" class="progress-container" style="display: none;">
                            <div class="progress-bar"></div>
                        </div>
                    </div>
                </div>
                
                <div class="yprint-vectorizer-result" style="display: none;">
                    <h2><?php _e('Vector Result', 'yprint-vectorizer'); ?></h2>
                    <div id="yprint-vector-preview" class="vector-preview-container"></div>
                    
                    <div class="action-buttons">
                        <button id="yprint-save-svg" class="button button-primary">
                            <?php _e('Save to Media Library', 'yprint-vectorizer'); ?>
                        </button>
                        <button id="yprint-download-svg" class="button">
                            <?php _e('Download SVG', 'yprint-vectorizer'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX handler for vectorizing images
     *
     * @since 1.0.0
     */
    public function ajax_vectorize_image() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'yprint-vectorizer-nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }
        
        // Get image ID and options
        $image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;
        
        if (!$image_id) {
            wp_send_json_error('No image provided');
        }
        
        // Get full image path
        $image_path = get_attached_file($image_id);
        
        if (!$image_path || !file_exists($image_path)) {
            wp_send_json_error('Image file not found');
        }
        
        // Get options
        $options = array(
            'detail_level' => isset($_POST['detail_level']) ? sanitize_text_field($_POST['detail_level']) : 'medium',
            'color_type' => isset($_POST['color_type']) ? sanitize_text_field($_POST['color_type']) : 'mono',
            'colors' => isset($_POST['colors']) ? intval($_POST['colors']) : 8,
            'invert' => isset($_POST['invert']) ? (bool) $_POST['invert'] : false,
            'remove_background' => isset($_POST['remove_bg']) ? (bool) $_POST['remove_bg'] : true,
            'brightness_threshold' => isset($_POST['brightness']) ? (float) $_POST['brightness'] : 0.45,
            'turdsize' => isset($_POST['turdsize']) ? intval($_POST['turdsize']) : 2,
            'opticurve' => isset($_POST['opticurve']) ? (bool) $_POST['opticurve'] : true,
        );
        
        // Set detail level parameters
        switch ($options['detail_level']) {
            case 'low':
                $options['alphamax'] = 2.0;
                $options['optitolerance'] = 0.8;
                break;
            
            case 'medium':
                $options['alphamax'] = 1.0;
                $options['optitolerance'] = 0.2;
                break;
            
            case 'high':
                $options['alphamax'] = 0.5;
                $options['optitolerance'] = 0.1;
                break;
                
            case 'ultra':
                $options['alphamax'] = 0.2;
                $options['optitolerance'] = 0.05;
                break;
        }
        
        // Perform vectorization
        $svg_content = $this->vectorize_image($image_path, $options);
        
        if (!$svg_content) {
            wp_send_json_error('Vectorization failed');
        }
        
        // Store in transient for later use
        $transient_key = 'yprint_vector_' . md5($image_id . serialize($options));
        set_transient($transient_key, $svg_content, HOUR_IN_SECONDS);
        
        wp_send_json_success(array(
            'svg' => $svg_content,
            'transient_key' => $transient_key
        ));
    }

    /**
     * Vectorize an image using potrace
     *
     * @since 1.0.0
     * @param string $image_path Path to image file
     * @param array $options Vectorization options
     * @return string|false SVG content or false on failure
     */
    public function vectorize_image($image_path, $options = array()) {
        $options = wp_parse_args($options, $this->default_options);
        
        // Create temporary directory for processing
        $upload_dir = wp_upload_dir();
        $temp_dir = $upload_dir['basedir'] . '/vectorizer-tmp';
        
        if (!file_exists($temp_dir)) {
            wp_mkdir_p($temp_dir);
            // Create .htaccess to protect temp files
            file_put_contents($temp_dir . '/.htaccess', 'deny from all');
        }
        
        $temp_base = $temp_dir . '/' . uniqid('vector_');
        $temp_bmp = $temp_base . '.bmp';
        $temp_ppm = $temp_base . '.ppm';
        $temp_svg = $temp_base . '.svg';
        
        // Check which vectorizing engine to use
        if ($this->engine_type === 'potrace') {
            // For color tracing, we need to prepare multiple files
            if ($options['color_type'] === 'color' || $options['color_type'] === 'gray') {
                return $this->vectorize_color_image($image_path, $temp_dir, $options);
            }
            
            // Prepare image - convert to 1-bit BMP for Potrace
            $this->prepare_image_for_potrace($image_path, $temp_bmp, $options);
            
            // Build potrace command
            $potrace_bin = $this->get_potrace_binary();
            
            $cmd = sprintf(
                '%s %s -s -o %s',
                escapeshellcmd($potrace_bin),
                $this->build_potrace_options($options),
                escapeshellarg($temp_svg)
            );
            
            // Add input file
            $cmd .= ' ' . escapeshellarg($temp_bmp);
            
            // Execute command
            exec($cmd, $output, $return_var);
            
            if ($return_var !== 0) {
                $this->cleanup_temp_files(array($temp_bmp, $temp_svg));
                return false;
            }
            
            // Read SVG output
            $svg_content = file_get_contents($temp_svg);
            
            // Clean up temp files
            $this->cleanup_temp_files(array($temp_bmp, $temp_svg));
            
            return $svg_content;
        }
        
        // Fallback to other methods or external API if needed
        return false;
    }

    /**
     * Vectorize a color image by processing color layers
     *
     * @since 1.0.0
     * @param string $image_path Path to image file
     * @param string $temp_dir Temporary directory for processing
     * @param array $options Vectorization options
     * @return string|false SVG content or false on failure
     */
    protected function vectorize_color_image($image_path, $temp_dir, $options) {
        // Load image
        $image = imagecreatefromstring(file_get_contents($image_path));
        if (!$image) {
            return false;
        }
        
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Create quantized color palette
        $num_colors = $options['colors'];
        $is_grayscale = ($options['color_type'] === 'gray');
        
        // Get potrace binary
        $potrace_bin = $this->get_potrace_binary();
        
        // Create SVG header
        $svg = '<?xml version="1.0" standalone="no"?>' . "\n";
        $svg .= '<!DOCTYPE svg PUBLIC "-//W3C//DTD SVG 1.1//EN" "http://www.w3.org/Graphics/SVG/1.1/DTD/svg11.dtd">' . "\n";
        $svg .= sprintf('<svg width="%d" height="%d" viewBox="0 0 %d %d" version="1.1" xmlns="http://www.w3.org/2000/svg">', 
            $width, $height, $width, $height) . "\n";
        
        // Process color layers
        $color_map = $this->quantize_image_colors($image, $num_colors, $is_grayscale);
        
        // Sort colors from dark to light (for stacking)
        $colors = array_keys($color_map);
        usort($colors, function($a, $b) {
            // Calculate luminance
            $luma_a = (0.299 * (($a >> 16) & 0xFF)) + (0.587 * (($a >> 8) & 0xFF)) + (0.114 * ($a & 0xFF));
            $luma_b = (0.299 * (($b >> 16) & 0xFF)) + (0.587 * (($b >> 8) & 0xFF)) + (0.114 * ($b & 0xFF));
            return $luma_a - $luma_b; // Dark to light
        });
        
        // Skip background color if requested
        if ($options['remove_background'] && count($colors) > 1) {
            array_pop($colors); // Remove last color (lightest, assumed to be background)
        }
        
        // Create temporary bitmap for each color
        $temp_files = array();
        foreach ($colors as $color_index => $color) {
            $temp_bmp = $temp_dir . '/color_' . $color_index . '.bmp';
            $temp_svg = $temp_dir . '/color_' . $color_index . '.svg';
            $temp_files[] = $temp_bmp;
            $temp_files[] = $temp_svg;
            
            // Create bitmap with just this color
            $this->create_color_bitmap($image, $color_map[$color], $temp_bmp);
            
            // Build potrace command for this color
            $cmd = sprintf(
                '%s %s -s -o %s %s',
                escapeshellcmd($potrace_bin),
                $this->build_potrace_options($options),
                escapeshellarg($temp_svg),
                escapeshellarg($temp_bmp)
            );
            
            // Execute command
            exec($cmd, $output, $return_var);
            
            if ($return_var === 0) {
                // Extract path data from SVG
                $color_svg = file_get_contents($temp_svg);
                preg_match('/<path[^>]*d="([^"]*)"[^>]*>/i', $color_svg, $matches);
                
                if (isset($matches[1])) {
                    $hex_color = sprintf('#%06x', $color);
                    $svg .= sprintf('<path d="%s" fill="%s" />' . "\n", $matches[1], $hex_color);
                }
            }
        }
        
        // Close SVG
        $svg .= '</svg>';
        
        // Clean up temporary files
        $this->cleanup_temp_files($temp_files);
        imagedestroy($image);
        
        return $svg;
    }

    /**
     * Quantize image colors for color vectorization
     *
     * @since 1.0.0
     * @param resource $image GD image resource
     * @param int $num_colors Number of colors to quantize to
     * @param bool $grayscale Whether to convert to grayscale
     * @return array Map of color integers to arrays of pixel positions
     */
    protected function quantize_image_colors($image, $num_colors, $grayscale = false) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Create a palette image
        $palette = imagecreatetruecolor($width, $height);
        
        // Convert to grayscale if requested
        if ($grayscale) {
            imagefilter($image, IMG_FILTER_GRAYSCALE);
        }
        
        // Quantize colors
        imagetruecolortopalette($image, false, $num_colors);
        
        // Get color map (color => [pixels])
        $color_map = array();
        
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                
                if (!isset($color_map[$color])) {
                    $color_map[$color] = array();
                }
                
                $color_map[$color][] = array($x, $y);
            }
        }
        
        return $color_map;
    }

    /**
     * Create a bitmap file with only pixels of a specific color
     *
     * @since 1.0.0
     * @param resource $image GD image resource
     * @param array $pixels Array of pixel positions for the color
     * @param string $output_file Path to output BMP file
     */
    protected function create_color_bitmap($image, $pixels, $output_file) {
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Create a black bitmap
        $bmp = imagecreate($width, $height);
        $black = imagecolorallocate($bmp, 0, 0, 0);
        $white = imagecolorallocate($bmp, 255, 255, 255);
        
        // Fill with white (background)
        imagefill($bmp, 0, 0, $white);
        
        // Set specified pixels to black (foreground)
        foreach ($pixels as $pixel) {
            imagesetpixel($bmp, $pixel[0], $pixel[1], $black);
        }
        
        // Save as BMP
        imagebmp($bmp, $output_file);
        imagedestroy($bmp);
    }
    
    /**
     * Get path to potrace binary
     *
     * @since 1.0.0
     * @return string Path to potrace binary
     */
    protected function get_potrace_binary() {
        // First check if we have the binary in our plugin directory
        $plugin_potrace = plugin_dir_path(__FILE__) . 'bin/potrace';
        
        if (file_exists($plugin_potrace) && is_executable($plugin_potrace)) {
            return $plugin_potrace;
        }
        
        // Check specific IONOS path
        $ionos_potrace = '/homepages/31/d4298451771/htdocs/.local/bin/potrace';
        if (file_exists($ionos_potrace) && is_executable($ionos_potrace)) {
            return $ionos_potrace;
        }
        
        // Fall back to system potrace with full path for IONOS servers
        if (file_exists('/homepages/31/d4298451771/htdocs/.local/bin/potrace')) {
            return '/homepages/31/d4298451771/htdocs/.local/bin/potrace';
        }
        
        // Fall back to system potrace (might work if in PATH)
        return 'potrace';
    }

    /**
     * Build potrace command-line options string
     *
     * @since 1.0.0
     * @param array $options Vectorization options
     * @return string Command-line options for potrace
     */
    protected function build_potrace_options($options) {
        $cmd_options = array();
        
        // Optimization level
        if ($options['opticurve']) {
            $cmd_options[] = '-O ' . $options['optitolerance'];
        } else {
            $cmd_options[] = '-n';
        }
        
        // Alphamax (curve optimization)
        $cmd_options[] = '-a ' . $options['alphamax'];
        
        // Turdsize (noise removal)
        $cmd_options[] = '-t ' . $options['turdsize'];
        
        return implode(' ', $cmd_options);
    }

    /**
     * Prepare image for potrace by converting to 1-bit BMP
     *
     * @since 1.0.0
     * @param string $input_file Input image path
     * @param string $output_file Output BMP path
     * @param array $options Conversion options
     * @return bool Success status
     */
    protected function prepare_image_for_potrace($input_file, $output_file, $options) {
        // Load image
        $image = imagecreatefromstring(file_get_contents($input_file));
        
        if (!$image) {
            return false;
        }
        
        // Convert to grayscale
        imagefilter($image, IMG_FILTER_GRAYSCALE);
        
        // Apply threshold
        $threshold = (int)(255 * $options['brightness_threshold']);
        
        // Process image with threshold
        $width = imagesx($image);
        $height = imagesy($image);
        
        // Create 1-bit black and white image
        $bmp = imagecreate($width, $height);
        $white = imagecolorallocate($bmp, 255, 255, 255);
        $black = imagecolorallocate($bmp, 0, 0, 0);
        
        // Fill with white
        imagefill($bmp, 0, 0, $white);
        
        // Apply threshold to create BW image
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $r = ($rgb >> 16) & 0xFF;
                $g = ($rgb >> 8) & 0xFF;
                $b = $rgb & 0xFF;
                
                // Simple grayscale conversion and threshold
                $gray = (int)(($r + $g + $b) / 3);
                
                if ($gray < $threshold) {
                    // Black pixel
                    $color = $options['invert'] ? $white : $black;
                } else {
                    // White pixel
                    $color = $options['invert'] ? $black : $white;
                }
                
                imagesetpixel($bmp, $x, $y, $color);
            }
        }
        
        // Save as BMP (potrace requires BMP input)
        imagebmp($bmp, $output_file);
        
        // Clean up
        imagedestroy($image);
        imagedestroy($bmp);
        
        return true;
    }

    /**
     * Clean up temporary files
     *
     * @since 1.0.0
     * @param array $files Array of file paths to remove
     */
    protected function cleanup_temp_files($files) {
        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    /**
     * AJAX handler for saving SVG to media library
     *
     * @since 1.0.0
     */
    public function ajax_save_svg() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'yprint-vectorizer-nonce')) {
            wp_send_json_error('Invalid nonce');
        }
        
        // Check permissions
        if (!current_user_can('upload_files')) {
            wp_send_json_error('Permission denied');
        }
        
// Get transient key and original image ID
$transient_key = isset($_POST['transient_key']) ? sanitize_text_field($_POST['transient_key']) : '';
$original_image_id = isset($_POST['image_id']) ? intval($_POST['image_id']) : 0;

if (empty($transient_key) || !$original_image_id) {
    wp_send_json_error('Missing required data');
}

// Get SVG content from transient
$svg_content = get_transient($transient_key);

if (!$svg_content) {
    wp_send_json_error('SVG data not found or expired');
}

// Get original image data for the title
$original_image = get_post($original_image_id);

if (!$original_image) {
    wp_send_json_error('Original image not found');
}

$file_name = sanitize_file_name('vector-' . $original_image->post_name . '.svg');

// Get upload directory
$upload_dir = wp_upload_dir();
$file_path = $upload_dir['path'] . '/' . $file_name;

// Save SVG file
file_put_contents($file_path, $svg_content);

// Prepare attachment data
$attachment = array(
    'guid'           => $upload_dir['url'] . '/' . $file_name,
    'post_mime_type' => 'image/svg+xml',
    'post_title'     => sprintf(__('Vector of %s', 'yprint-vectorizer'), $original_image->post_title),
    'post_content'   => '',
    'post_status'    => 'inherit'
);

// Insert attachment
$attachment_id = wp_insert_attachment($attachment, $file_path);

if (is_wp_error($attachment_id)) {
    wp_send_json_error('Failed to save SVG to media library');
}

// Generate metadata
$attachment_data = wp_generate_attachment_metadata($attachment_id, $file_path);
wp_update_attachment_metadata($attachment_id, $attachment_data);

// Add reference to original image
update_post_meta($attachment_id, '_yprint_vector_source', $original_image_id);

// Return success with attachment data
wp_send_json_success(array(
    'attachment_id' => $attachment_id,
    'attachment_url' => wp_get_attachment_url($attachment_id)
));
}

/**
* Handle vectorizer shortcode
*
* @since 1.0.0
* @param array $atts Shortcode attributes
* @return string Shortcode output
*/
public function vectorizer_shortcode($atts) {
// Check if user has permission
if (!current_user_can('upload_files')) {
    return '<p>' . __('You do not have permission to use this feature.', 'yprint-vectorizer') . '</p>';
}

$atts = shortcode_atts(array(
    'button_text' => __('Vectorize Image', 'yprint-vectorizer'),
    'class' => 'yprint-vectorizer-button',
), $atts, 'yprint_vectorizer');

// Enqueue necessary scripts
wp_enqueue_media();
wp_enqueue_script('yprint-vectorizer-frontend', plugin_dir_url(__FILE__) . 'assets/js/vectorizer-frontend.js', array('jquery'), '1.0.0', true);
wp_enqueue_style('yprint-vectorizer-frontend', plugin_dir_url(__FILE__) . 'assets/css/vectorizer-frontend.css', array(), '1.0.0');

wp_localize_script('yprint-vectorizer-frontend', 'yprintVectorizer', array(
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('yprint-vectorizer-nonce'),
));

// Generate output
ob_start();
?>
<div class="yprint-vectorizer-frontend">
    <button class="<?php echo esc_attr($atts['class']); ?>" id="yprint-vectorizer-button">
        <?php echo esc_html($atts['button_text']); ?>
    </button>
    
    <div class="yprint-vectorizer-modal" style="display: none;">
        <div class="yprint-vectorizer-modal-content">
            <span class="yprint-vectorizer-close">&times;</span>
            
            <h2><?php _e('Vectorize Image', 'yprint-vectorizer'); ?></h2>
            
            <div class="yprint-vectorizer-container">
                <div class="yprint-vectorizer-upload">
                    <button id="yprint-frontend-upload" class="button">
                        <?php _e('Select Image', 'yprint-vectorizer'); ?>
                    </button>
                    <div id="yprint-frontend-preview" class="image-preview-container"></div>
                </div>
                
                <div class="yprint-vectorizer-options">
                    <div class="option-group">
                        <label><?php _e('Detail Level', 'yprint-vectorizer'); ?></label>
                        <select id="yprint-frontend-detail">
                            <option value="low"><?php _e('Low', 'yprint-vectorizer'); ?></option>
                            <option value="medium" selected><?php _e('Medium', 'yprint-vectorizer'); ?></option>
                            <option value="high"><?php _e('High', 'yprint-vectorizer'); ?></option>
                            <option value="ultra"><?php _e('Ultra', 'yprint-vectorizer'); ?></option>
                        </select>
                    </div>
                    
                    <div class="option-group">
                        <label><?php _e('Color Mode', 'yprint-vectorizer'); ?></label>
                        <select id="yprint-frontend-color">
                            <option value="mono" selected><?php _e('Black & White', 'yprint-vectorizer'); ?></option>
                            <option value="gray"><?php _e('Grayscale', 'yprint-vectorizer'); ?></option>
                            <option value="color"><?php _e('Color', 'yprint-vectorizer'); ?></option>
                        </select>
                    </div>
                    
                    <button id="yprint-frontend-vectorize" class="button button-primary" disabled>
                        <?php _e('Vectorize', 'yprint-vectorizer'); ?>
                    </button>
                    
                    <div id="yprint-frontend-progress" class="progress-container" style="display: none;">
                        <div class="progress-bar"></div>
                    </div>
                </div>
                
                <div class="yprint-frontend-result" style="display: none;">
                    <div id="yprint-frontend-svg" class="vector-preview-container"></div>
                    
                    <div class="action-buttons">
                        <button id="yprint-frontend-save" class="button button-primary">
                            <?php _e('Save to Media Library', 'yprint-vectorizer'); ?>
                        </button>
                        <button id="yprint-frontend-download" class="button">
                            <?php _e('Download SVG', 'yprint-vectorizer'); ?>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
return ob_get_clean();
}
}

// Initialize the class
function yprint_vectorizer_init() {
return YPrint_Vectorizer::get_instance();
}

// Hook into WordPress
add_action('plugins_loaded', 'yprint_vectorizer_init');

/**
* Create CSS file for Vectorizer
*/
function yprint_vectorizer_create_css() {
$css_dir = plugin_dir_path(__FILE__) . 'assets/css';

if (!file_exists($css_dir)) {
wp_mkdir_p($css_dir);
}

$css_file = $css_dir . '/vectorizer.css';

if (!file_exists($css_file)) {
$css = <<<CSS
.yprint-vectorizer-container {
display: flex;
flex-wrap: wrap;
gap: 20px;
margin-top: 20px;
}

.yprint-vectorizer-upload, 
.yprint-vectorizer-options,
.yprint-vectorizer-result {
flex: 1;
min-width: 300px;
padding: 20px;
background: #fff;
border-radius: 5px;
box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.image-preview-container {
margin-top: 15px;
border: 1px dashed #ccc;
padding: 10px;
min-height: 200px;
display: flex;
align-items: center;
justify-content: center;
}

.image-preview-container img {
max-width: 100%;
max-height: 300px;
}

.vector-preview-container {
margin-top: 15px;
border: 1px solid #eee;
padding: 10px;
min-height: 200px;
background: #f9f9f9;
display: flex;
align-items: center;
justify-content: center;
}

.vector-preview-container svg {
max-width: 100%;
max-height: 300px;
}

.option-group {
margin-bottom: 15px;
}

.option-group label {
display: block;
margin-bottom: 5px;
font-weight: 500;
}

.option-group select,
.option-group input[type="range"] {
width: 100%;
}

.action-buttons {
margin-top: 20px;
display: flex;
gap: 10px;
}

.progress-container {
height: 20px;
background-color: #f5f5f5;
border-radius: 3px;
margin-top: 15px;
overflow: hidden;
}

.progress-bar {
height: 100%;
background-color: #0073aa;
width: 0%;
transition: width 0.3s ease;
}

.advanced-toggle {
margin: 15px 0;
}

.advanced-toggle a {
text-decoration: none;
color: #0073aa;
}

/* Frontend modal styles */
.yprint-vectorizer-modal {
display: none;
position: fixed;
z-index: 999999;
left: 0;
top: 0;
width: 100%;
height: 100%;
overflow: auto;
background-color: rgba(0, 0, 0, 0.5);
}

.yprint-vectorizer-modal-content {
background-color: #fefefe;
margin: 5% auto;
padding: 20px;
border-radius: 5px;
box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
width: 80%;
max-width: 1000px;
}

.yprint-vectorizer-close {
color: #aaa;
float: right;
font-size: 28px;
font-weight: bold;
cursor: pointer;
}

.yprint-vectorizer-close:hover {
color: black;
}
CSS;

file_put_contents($css_file, $css);
}

// Create frontend CSS
$frontend_css_file = $css_dir . '/vectorizer-frontend.css';

if (!file_exists($frontend_css_file)) {
$frontend_css = <<<CSS
.yprint-vectorizer-frontend {
margin: 20px 0;
}

.yprint-vectorizer-button {
display: inline-block;
background: #0073aa;
color: white;
padding: 10px 15px;
border-radius: 3px;
cursor: pointer;
border: none;
text-decoration: none;
font-size: 14px;
font-weight: 500;
text-align: center;
}

.yprint-vectorizer-button:hover {
background: #005f8b;
color: white;
}
CSS;

file_put_contents($frontend_css_file, $frontend_css);
}
}

/**
* Create JavaScript files for Vectorizer
*/
function yprint_vectorizer_create_js() {
$js_dir = plugin_dir_path(__FILE__) . 'assets/js';

if (!file_exists($js_dir)) {
wp_mkdir_p($js_dir);
}

$js_file = $js_dir . '/vectorizer.js';

if (!file_exists($js_file)) {
$js = <<<JS
(function($) {
'use strict';

// Document ready
$(document).ready(function() {
var mediaUploader;
var selectedImageId = 0;
var svgResult = null;
var transientKey = null;

// Update range value displays
$('#yprint-colors').on('input', function() {
    $('.color-value').text($(this).val());
});

$('#yprint-brightness').on('input', function() {
    $('.brightness-value').text($(this).val());
});

$('#yprint-turdsize').on('input', function() {
    $('.turd-value').text($(this).val());
});

// Toggle advanced options
$('#toggle-advanced').on('click', function(e) {
    e.preventDefault();
    $('.advanced-options').slideToggle();
    
    if ($(this).text().indexOf('▼') > -1) {
        $(this).text($(this).text().replace('▼', '▲'));
    } else {
        $(this).text($(this).text().replace('▲', '▼'));
    }
});

// Toggle color options based on color mode
$('#yprint-color-mode').on('change', function() {
    if ($(this).val() === 'color' || $(this).val() === 'gray') {
        $('.color-options').slideDown();
    } else {
        $('.color-options').slideUp();
    }
});

// Handle image upload
$('#yprint-upload-image').on('click', function(e) {
    e.preventDefault();
    
    // If the uploader object has already been created, reopen the dialog
    if (mediaUploader) {
        mediaUploader.open();
        return;
    }
    
    // Create the media uploader
    mediaUploader = wp.media({
        title: 'Select Image to Vectorize',
        button: {
            text: 'Use this image'
        },
        multiple: false,
        library: {
            type: 'image'
        }
    });
    
    // When an image is selected, run a callback
    mediaUploader.on('select', function() {
        var attachment = mediaUploader.state().get('selection').first().toJSON();
        selectedImageId = attachment.id;
        
        // Display image preview
        $('#yprint-image-preview').html('<img src="' + attachment.url + '" alt="' + attachment.title + '"/>');
        
        // Enable vectorize button
        $('#yprint-vectorize').prop('disabled', false);
        
        // Hide result section if visible
        $('.yprint-vectorizer-result').hide();
    });
    
    // Open the uploader dialog
    mediaUploader.open();
});

// Handle vectorize button
$('#yprint-vectorize').on('click', function() {
    if (selectedImageId <= 0) {
        alert('Please select an image first');
        return;
    }
    
    // Show progress bar
    $('#yprint-progress').show();
    $('.progress-bar').css('width', '5%');
    
    // Disable vectorize button during processing
    $(this).prop('disabled', true);
    
    // Get options
    var options = {
        'nonce': yprintVectorizer.nonce,
        'image_id': selectedImageId,
        'detail_level': $('#yprint-detail-level').val(),
        'color_type': $('#yprint-color-mode').val(),
        'colors': $('#yprint-colors').val(),
        'invert': $('#yprint-invert').is(':checked') ? 1 : 0,
        'remove_bg': $('#yprint-remove-bg').is(':checked') ? 1 : 0,
        'brightness': $('#yprint-brightness').val(),
        'turdsize': $('#yprint-turdsize').val(),
        'opticurve': $('#yprint-opticurve').is(':checked') ? 1 : 0
    };
    
    // Perform AJAX request
    $.ajax({
        url: yprintVectorizer.ajaxurl,
        type: 'POST',
        data: {
            'action': 'yprint_vectorize_image',
            ...options
        },
        xhr: function() {
            var xhr = new window.XMLHttpRequest();
            
            // Progress simulation (actual progress not available from server)
            var fakeProgress = 10;
            var progressInterval = setInterval(function() {
                if (fakeProgress < 90) {
                    fakeProgress += 5;
                    $('.progress-bar').css('width', fakeProgress + '%');
                }
            }, 500);
            
            xhr.addEventListener('loadend', function() {
                clearInterval(progressInterval);
                $('.progress-bar').css('width', '100%');
            }, false);
            
            return xhr;
        },
        success: function(response) {
            if (response.success) {
                svgResult = response.data.svg;
                transientKey = response.data.transient_key;
                
                // Display SVG result
                $('#yprint-vector-preview').html(svgResult);
                $('.yprint-vectorizer-result').show();
                
                // Set up download link
                $('#yprint-download-svg').off('click').on('click', function() {
                    downloadSVG(svgResult);
                });
                
                // Re-enable vectorize button
                $('#yprint-vectorize').prop('disabled', false);
            } else {
                alert('Vectorization failed: ' + response.data);
                $('#yprint-vectorize').prop('disabled', false);
            }
            
            // Hide progress bar
            setTimeout(function() {
                $('#yprint-progress').hide();
                $('.progress-bar').css('width', '0%');
            }, 500);
        },
        error: function() {
            alert('An error occurred during vectorization');
            $('#yprint-vectorize').prop('disabled', false);
            
            // Hide progress bar
            $('#yprint-progress').hide();
            $('.progress-bar').css('width', '0%');
        }
    });
});

// Handle save to media library
$('#yprint-save-svg').on('click', function() {
    if (!svgResult || !transientKey) {
        alert('No SVG result available');
        return;
    }
    
    $(this).prop('disabled', true).text('Saving...');
    
    $.ajax({
        url: yprintVectorizer.ajaxurl,
        type: 'POST',
        data: {
            'action': 'yprint_save_svg',
            'nonce': yprintVectorizer.nonce,
            'transient_key': transientKey,
            'image_id': selectedImageId
        },
        success: function(response) {
            if (response.success) {
                alert('SVG saved to media library');
                
                // Create link to media item
                var mediaUrl = response.data.attachment_url;
                var mediaId = response.data.attachment_id;
                
                // Add link to view in media library
                $('#yprint-save-svg')
                    .after('<a href="/wp-admin/post.php?post=' + mediaId + '&action=edit" class="button" style="margin-left:10px;" target="_blank">View in Media Library</a>');
            } else {
                alert('Failed to save SVG: ' + response.data);
            }
            
            $('#yprint-save-svg').prop('disabled', false).text('Save to Media Library');
        },
        error: function() {
            alert('An error occurred while saving');
            $('#yprint-save-svg').prop('disabled', false).text('Save to Media Library');
        }
    });
});

// Download SVG function
function downloadSVG(svgContent) {
    var blob = new Blob([svgContent], {type: 'image/svg+xml'});
    var url = URL.createObjectURL(blob);
    
    var a = document.createElement('a');
    a.href = url;
    a.download = 'vectorized-image.svg';
    document.body.appendChild(a);
    a.click();
    
    setTimeout(function() {
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }, 100);
}
});
})(jQuery);
JS;

file_put_contents($js_file, $js);
}

// Create frontend JavaScript
$frontend_js_file = $js_dir . '/vectorizer-frontend.js';

if (!file_exists($frontend_js_file)) {
$frontend_js = <<<JS
(function($) {
'use strict';

$(document).ready(function() {
var mediaUploader;
var selectedImageId = 0;
var svgResult = null;
var transientKey = null;

// Open modal when button clicked
$('#yprint-vectorizer-button').on('click', function() {
    $('.yprint-vectorizer-modal').fadeIn();
});

// Close modal when X clicked
$('.yprint-vectorizer-close').on('click', function() {
    $('.yprint-vectorizer-modal').fadeOut();
});

// Close modal when clicking outside content
$('.yprint-vectorizer-modal').on('click', function(e) {
    if ($(e.target).hasClass('yprint-vectorizer-modal')) {
        $(this).fadeOut();
    }
});

// Handle image upload
$('#yprint-frontend-upload').on('click', function(e) {
    e.preventDefault();
    
    if (mediaUploader) {
        mediaUploader.open();
        return;
    }
    
    mediaUploader = wp.media({
        title: 'Select Image to Vectorize',
        button: {
            text: 'Use this image'
        },
        multiple: false,
        library: {
            type: 'image'
        }
    });
    
    mediaUploader.on('select', function() {
        var attachment = mediaUploader.state().get('selection').first().toJSON();
        selectedImageId = attachment.id;
        
        $('#yprint-frontend-preview').html('<img src="' + attachment.url + '" alt="' + attachment.title + '"/>');
        $('#yprint-frontend-vectorize').prop('disabled', false);
        $('.yprint-frontend-result').hide();
    });
    
    mediaUploader.open();
});

// Handle vectorize button
$('#yprint-frontend-vectorize').on('click', function() {
    if (selectedImageId <= 0) {
        alert('Please select an image first');
        return;
    }
    
    $('#yprint-frontend-progress').show();
    $('.progress-bar').css('width', '5%');
    $(this).prop('disabled', true);
    
    var options = {
        'nonce': yprintVectorizer.nonce,
        'image_id': selectedImageId,
        'detail_level': $('#yprint-frontend-detail').val(),
        'color_type': $('#yprint-frontend-color').val(),
        'colors': 8,
        'invert': 0,
        'remove_bg': 1
    };
    
    $.ajax({
        url: yprintVectorizer.ajaxurl,
        type: 'POST',
        data: {
            'action': 'yprint_vectorize_image',
            ...options
        },
        xhr: function() {
            var xhr = new window.XMLHttpRequest();
            var fakeProgress = 10;
            var progressInterval = setInterval(function() {
                if (fakeProgress < 90) {
                    fakeProgress += 5;
                    $('.progress-bar').css('width', fakeProgress + '%');
                }
            }, 500);
            
            xhr.addEventListener('loadend', function() {
                clearInterval(progressInterval);
                $('.progress-bar').css('width', '100%');
            }, false);
            
            return xhr;
        },
        success: function(response) {
            if (response.success) {
                svgResult = response.data.svg;
                transientKey = response.data.transient_key;
                
                $('#yprint-frontend-svg').html(svgResult);
                $('.yprint-frontend-result').show();
                
                $('#yprint-frontend-download').off('click').on('click', function() {
                    downloadSVG(svgResult);
                });
                
                $('#yprint-frontend-vectorize').prop('disabled', false);
            } else {
                alert('Vectorization failed: ' + response.data);
                $('#yprint-frontend-vectorize').prop('disabled', false);
            }
            
            setTimeout(function() {
                $('#yprint-frontend-progress').hide();
                $('.progress-bar').css('width', '0%');
            }, 500);
        },
        error: function() {
            alert('An error occurred during vectorization');
            $('#yprint-frontend-vectorize').prop('disabled', false);
            $('#yprint-frontend-progress').hide();
            $('.progress-bar').css('width', '0%');
        }
    });
});

// Handle save to media library
$('#yprint-frontend-save').on('click', function() {
    if (!svgResult || !transientKey) {
        alert('No SVG result available');
        return;
    }
    
    $(this).prop('disabled', true).text('Saving...');
    
    $.ajax({
        url: yprintVectorizer.ajaxurl,
        type: 'POST',
        data: {
            'action': 'yprint_save_svg',
            'nonce': yprintVectorizer.nonce,
            'transient_key': transientKey,
            'image_id': selectedImageId
        },
        success: function(response) {
            if (response.success) {
                alert('SVG saved to media library');
            } else {
                alert('Failed to save SVG: ' + response.data);
            }
            
            $('#yprint-frontend-save').prop('disabled', false).text('Save to Media Library');
        },
        error: function() {
            alert('An error occurred while saving');
            $('#yprint-frontend-save').prop('disabled', false).text('Save to Media Library');
        }
    });
});

// Download SVG function
function downloadSVG(svgContent) {
    var blob = new Blob([svgContent], {type: 'image/svg+xml'});
    var url = URL.createObjectURL(blob);
    
    var a = document.createElement('a');
    a.href = url;
    a.download = 'vectorized-image.svg';
    document.body.appendChild(a);
    a.click();
    
    setTimeout(function() {
        document.body.removeChild(a);
        URL.revokeObjectURL(url);
    }, 100);
}
});
})(jQuery);
JS;

file_put_contents($frontend_js_file, $frontend_js);
}
}

// Create asset files on plugin activation
register_activation_hook(__FILE__, 'yprint_vectorizer_create_css');
register_activation_hook(__FILE__, 'yprint_vectorizer_create_js');