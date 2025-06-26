<?php
/**
 * YPrint Your Designs Shortcode
 * Displays user's saved designs in a modern, minimalist dashboard
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle Your Designs display
 */
class YPrint_Your_Designs {

    /**
     * Initialize the class
     */
     public static function init() {
        add_shortcode('your_designs', array(__CLASS__, 'render_your_designs'));
        add_action('wp_ajax_yprint_reorder_design', array(__CLASS__, 'handle_reorder_design'));
        add_action('wp_ajax_nopriv_yprint_reorder_design', array(__CLASS__, 'handle_reorder_design'));
        add_action('wp_ajax_yprint_delete_design', array(__CLASS__, 'handle_delete_design'));
        add_action('wp_ajax_nopriv_yprint_delete_design', array(__CLASS__, 'handle_delete_design'));
        add_action('wp_ajax_yprint_get_product_sizes', array(__CLASS__, 'handle_get_product_sizes'));
        add_action('wp_ajax_nopriv_yprint_get_product_sizes', array(__CLASS__, 'handle_get_product_sizes'));
    }

    /**
     * Render Your Designs shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_your_designs($atts) {
        $atts = shortcode_atts(array(
            'limit' => 12,
            'class' => 'yprint-your-designs'
        ), $atts, 'your_designs');

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return '<div class="yprint-your-designs-login">' .
                   '<p>' . __('Bitte melde dich an, um deine Designs zu sehen.', 'yprint-plugin') . '</p>' .
                   '</div>';
        }

        $designs = self::get_user_designs($current_user_id, intval($atts['limit']));
        $css_class = sanitize_html_class($atts['class']);
        $unique_id = 'yprint-your-designs-' . uniqid();

        ob_start();
        ?>

        <style>

            /* DEBUG: Ensure buttons are clickable */
        .yprint-design-action.reorder {
            pointer-events: auto !important;
            z-index: 10;
            background: #F9FAFB; /* Temporary red background for debugging */
        }

        .yprint-your-designs {
    font-family: system-ui, 'Segoe UI', Roboto, Helvetica, sans-serif;
    background-color: #ffffff;
    border-radius: 12px;
    padding: 1.5rem;
    border: 1px solid #DFDFDF;
    margin: 0;

if (!$design_id || empty($new_title) || strlen($new_title) > 255) {
    wp_send_json_error('Ungültige Parameter: Titel ist erforderlich und darf maximal 255 Zeichen lang sein');
    return;

    /**
     * Handle update design title AJAX request
     */
    public static function handle_update_design_title() {
        // Debug-Ausgabe
        error_log('YPrint: handle_update_design_title called');
        error_log('YPrint: POST data: ' . print_r($_POST, true));
        
        // Nonce prüfen
        if (!wp_verify_nonce($_POST['nonce'], 'yprint_design_actions_nonce')) {
            error_log('YPrint: Nonce verification failed');
            wp_send_json_error('Sicherheitsprüfung fehlgeschlagen');
            return;
        }

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        $new_title = isset($_POST['new_title']) ? trim(sanitize_text_field($_POST['new_title'])) : '';

if (!$design_id || empty($new_title) || strlen($new_title) > 255) {
    wp_send_json_error('Ungültige Parameter: Titel ist erforderlich und darf maximal 255 Zeichen lang sein');
    return;
}

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            error_log('YPrint: User not logged in');
            wp_send_json_error('Du musst angemeldet sein');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'octo_user_designs';
        
        // Prüfen ob Design existiert und dem User gehört
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE id = %d AND user_id = %d",
            $design_id,
            $current_user_id
        ));
        
        if (!$existing) {
            error_log('YPrint: Design not found or no permission');
            wp_send_json_error('Design nicht gefunden oder keine Berechtigung');
            return;
        }
        
        $result = $wpdb->update(
            $table_name,
            array('name' => $new_title),
            array(
                'id' => $design_id,
                'user_id' => $current_user_id
            ),
            array('%s'),
            array('%d', '%d')
        );

        error_log('YPrint: Update result: ' . var_export($result, true));

        if ($result === false) {
            error_log('YPrint: Database update failed: ' . $wpdb->last_error);
            wp_send_json_error('Fehler beim Speichern des Titels');
            return;
        }

        wp_send_json_success(array(
            'message' => 'Titel wurde erfolgreich geändert',
            'design_id' => $design_id,
            'new_title' => $new_title
        ));
    }
}

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            error_log('YPrint: User not logged in');
            wp_send_json_error('Du musst angemeldet sein');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'octo_user_designs';
        
        // Prüfen ob Design existiert und dem User gehört
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE id = %d AND user_id = %d",
            $design_id,
            $current_user_id
        ));
        
        if (!$existing) {
            error_log('YPrint: Design not found or no permission');
            wp_send_json_error('Design nicht gefunden oder keine Berechtigung');
            return;
        }
        
        $result = $wpdb->update(
            $table_name,
            array('name' => $new_title),
            array(
                'id' => $design_id,
                'user_id' => $current_user_id
            ),
            array('%s'),
            array('%d', '%d')
        );

        error_log('YPrint: Update result: ' . var_export($result, true));

        if ($result === false) {
            error_log('YPrint: Database update failed: ' . $wpdb->last_error);
            wp_send_json_error('Fehler beim Speichern des Titels');
            return;
        }

        wp_send_json_success(array(
            'message' => 'Titel wurde erfolgreich geändert',
            'design_id' => $design_id,
            'new_title' => $new_title
        ));
    }


        .yprint-your-designs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }

        .yprint-your-designs-title {
            font-size: 18px;
            font-weight: 600;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }

        .yprint-your-designs-count {
            font-size: 14px;
            color: #6B7280;
            font-weight: 400;
        }

        .yprint-designs-container {
            overflow-x: auto;
            overflow-y: visible; /* Allow dropdowns to show */
            padding-bottom: 0.5rem;
        }

        .yprint-designs-list {
            display: flex;
            gap: 1rem;
            padding: 0.25rem 0;
            min-width: max-content;
        }

        .yprint-design-card {
    display: flex;
    flex-direction: column;
    width: 200px;
    min-width: 200px;
    background-color: #ffffff;
    border-radius: 8px;
    border: 1px solid #e5e5e5;
    transition: all 0.2s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.yprint-design-card:hover {
    border-color: #0079FF;
    transform: translateY(-2px);
}

        .yprint-design-clickable-area {
            flex: 1;
            cursor: pointer;
        }

        .yprint-design-image-container {
            position: relative;
            width: 100%;
            height: 160px;
            overflow: hidden;
        }

        .yprint-design-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            background-color: #F9FAFB;
        }

        .yprint-design-image-placeholder {
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .yprint-design-image-placeholder i {
            font-size: 24px;
            color: white;
            opacity: 0.7;
        }

        .yprint-design-content {
            padding: 0.75rem;
            flex: 1;
        }

        .yprint-design-name {
            font-size: 14px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 0.25rem 0;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .yprint-design-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0.75rem;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            background-color: #F9FAFB;
        }

        .yprint-design-action {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
            color: #6B7280;
            flex: 1;
        }

        .yprint-design-action:hover {
            color: #111827;
            background-color: rgba(0, 0, 0, 0.05);
        }

        .yprint-design-action i {
            font-size: 16px;
            margin-bottom: 0.125rem;
            transition: color 0.2s ease;
        }

        .yprint-design-action-label {
            font-size: 10px;
            font-weight: 500;
            color: #374151;
            line-height: 1;
        }

        .yprint-design-action.delete {
            color: #DC2626;
        }

        .yprint-design-action.delete:hover {
            color: #B91C1C;
            background-color: rgba(220, 38, 38, 0.1);
        }

        /* Scrollbar styling */
        .yprint-designs-container::-webkit-scrollbar {
            height: 6px;
        }

        .yprint-designs-container::-webkit-scrollbar-track {
            background: #F3F4F6;
            border-radius: 3px;
        }

        .yprint-designs-container::-webkit-scrollbar-thumb {
            background: #D1D5DB;
            border-radius: 3px;
        }

        .yprint-designs-container::-webkit-scrollbar-thumb:hover {
            background: #9CA3AF;
        }

        .yprint-no-designs {
            text-align: center;
            padding: 3rem 1.5rem;
            color: #6B7280;
        }

        .yprint-no-designs-icon {
            font-size: 48px;
            color: #D1D5DB;
            margin-bottom: 1rem;
        }

        .yprint-no-designs-title {
            font-size: 18px;
            font-weight: 600;
            color: #374151;
            margin: 0 0 0.5rem 0;
        }

        .yprint-no-designs-text {
            font-size: 14px;
            margin: 0 0 1.5rem 0;
            line-height: 1.5;
            color: #6B7280;
        }

        .yprint-create-design-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background-color: #0079FF;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .yprint-create-design-btn:hover {
            background-color: #0056b3;
            color: white;
        }

        .yprint-loading {
            text-align: center;
            padding: 2rem;
            color: #6B7280;
        }

        .yprint-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #D1D5DB;
            border-top: 2px solid #0079FF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 0.5rem;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
    .yprint-your-designs {
        padding: 1rem;
        border-radius: 8px;
        margin: 0;
    }

            .yprint-your-designs-title {
                font-size: 16px;
            }

            .yprint-design-card {
                width: 160px;
                min-width: 160px;
            }

            .yprint-design-image-container {
                height: 130px;
            }

            .yprint-design-content {
                padding: 0.5rem;
            }

            .yprint-design-name {
                font-size: 12px;
            }

            .yprint-design-meta {
                font-size: 10px;
            }

            .yprint-design-action i {
                font-size: 14px;
            }

            .yprint-design-action-label {
                font-size: 9px;
            }
        }

        .yprint-create-new-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 200px;
            min-width: 200px;
            height: 278px;
            background-color: #F9FAFB;
            border: 2px dashed #D1D5DB;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-decoration: none;
            color: #6B7280;
        }

        .yprint-create-new-card:hover {
            border-color: #0079FF;
            background-color: #F3F9FF;
            color: #0079FF;
            transform: translateY(-2px);
        }

        .yprint-create-new-icon {
            font-size: 32px;
            margin-bottom: 0.75rem;
            opacity: 0.7;
            transition: opacity 0.2s ease;
            color: inherit;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .yprint-create-new-card:hover .yprint-create-new-icon {
            opacity: 1;
        }

        .yprint-create-new-icon i {
            display: block !important;
            font-style: normal !important;
            font-weight: 900 !important;
            font-family: "Font Awesome 5 Free" !important;
            line-height: 1 !important;
        }

        .yprint-create-new-icon i:before {
            content: "\f553" !important; /* T-shirt Unicode für FontAwesome */
        }

        /* Fallback wenn FontAwesome nicht lädt */
        .yprint-create-new-icon i.fa-tshirt:before {
            content: "👕" !important;
            font-family: inherit !important;
        }

        .yprint-create-new-text {
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            line-height: 1.3;
            margin: 0;
        }

        @media (max-width: 768px) {
            .yprint-create-new-card {
                width: 160px;
                min-width: 160px;
                height: 238px;
            }

            .yprint-create-new-icon {
                font-size: 28px;
            }

            .yprint-create-new-text {
                font-size: 12px;
            }
        }

        @media (max-width: 480px) {
            .yprint-your-designs-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .yprint-design-card {
                width: 140px;
                min-width: 140px;
            }

            .yprint-create-new-card {
                width: 140px;
                min-width: 140px;
                height: 218px;
            }

            .yprint-create-new-icon {
                font-size: 24px;
            }

            .yprint-create-new-text {
                font-size: 11px;
            }
        }

        /* Size Selection Dropdown Styles */
        .yprint-design-action.reorder {
            position: relative;
        }

        .yprint-size-dropdown {
    position: fixed; /* Changed to fixed to avoid parent influence */
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    z-index: 999999; /* Higher z-index */
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: opacity 0.2s ease, visibility 0.2s ease, transform 0.2s ease; /* Specific transitions only */
    padding: 12px;
    min-width: 180px;
    max-width: 250px;
    white-space: nowrap;
    pointer-events: none; /* Prevent interference when hidden */
}

.yprint-size-dropdown.show {
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) !important;
    pointer-events: auto; /* Enable interactions when visible */
}

/* Prevent parent button hover effects when dropdown is open */
.yprint-design-action.reorder.dropdown-open {
    background-color: rgba(0, 0, 0, 0.05) !important;
    color: #111827 !important;
}

.yprint-design-action.reorder.dropdown-open:hover {
    background-color: rgba(0, 0, 0, 0.05) !important; /* Lock hover state */
    color: #111827 !important;
}

        .yprint-size-dropdown.show {
            opacity: 1 !important;
            visibility: visible !important;
            transform: translateY(0) !important;
            background: #e5e7eb
        }

        .yprint-size-dropdown.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .yprint-size-dropdown-header {
            margin-bottom: 8px;
            text-align: center;
        }

        .yprint-size-dropdown-title {
            font-size: 12px;
            font-weight: 600;
            margin: 0;
            color: #374151;
        }

        .yprint-size-options {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 6px;
            margin: 8px 0;
        }

        .yprint-size-option {
            padding: 8px 4px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
            font-weight: 500;
            font-size: 11px;
            line-height: 1;
        }

        .yprint-size-option:hover:not(.disabled) {
            border-color: #0079FF;
            background: #f3f9ff;
        }

        .yprint-size-option.selected {
            border-color: #0079FF;
            background: #0079FF;
            color: white;
        }

        .yprint-size-option.disabled {
            background: #f5f5f5;
            color: #9ca3af;
            border-color: #e5e7eb;
            cursor: not-allowed;
            text-decoration: line-through;
            opacity: 0.6;
        }

        .yprint-size-dropdown-actions {
            display: flex;
            gap: 6px;
            margin-top: 8px;
        }

        .yprint-size-dropdown-btn {
            flex: 1;
            padding: 6px 8px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            font-size: 11px;
            transition: all 0.2s ease;
        }

        .yprint-size-dropdown-btn.cancel {
            background: #f3f4f6;
            color: #374151;
        }

        .yprint-size-dropdown-btn.cancel:hover {
            background: #ffffff;
        }

        .yprint-size-dropdown-btn.confirm {
            background: #0079FF;
            color: white;
        }

        .yprint-size-dropdown-btn.confirm:hover {
            background: #0056b3;
        }

        .yprint-size-dropdown-btn:disabled {
            background: #ffffff;
            color: #9ca3af;
            cursor: not-allowed;
        }

        .yprint-size-loading {
            text-align: center;
            padding: 12px;
            color: #6B7280;
            font-size: 11px;
        }

        .yprint-size-error {
            text-align: center;
            padding: 12px;
            color: #dc2626;
            font-size: 11px;
        }

        /* Mobile anpassungen */
        @media (max-width: 768px) {
            .yprint-size-dropdown {
                min-width: 160px;
                padding: 10px;
            }

            .yprint-size-options {
                grid-template-columns: repeat(2, 1fr);
                gap: 4px;
            }

            .yprint-size-option {
                padding: 6px 4px;
                font-size: 10px;
            }

            .yprint-size-dropdown-btn {
                font-size: 10px;
                padding: 5px 6px;
            }
        }
        </style>

        <div id="<?php echo esc_attr($unique_id); ?>" class="<?php echo esc_attr($css_class); ?>">
            <div class="yprint-your-designs-header">
                <h2 class="yprint-your-designs-title"><?php _e('Deine Designs', 'yprint-plugin'); ?></h2>
                <span class="yprint-your-designs-count">
                    <?php echo sprintf(_n('%d Design', '%d Designs', count($designs), 'yprint-plugin'), count($designs)); ?>
                </span>
            </div>

            <?php if (empty($designs)) : ?>
                <div class="yprint-no-designs">
                    <div class="yprint-no-designs-icon">
                        <i class="fas fa-palette"></i>
                    </div>
                    <h3 class="yprint-no-designs-title"><?php _e('Noch keine Designs erstellt', 'yprint-plugin'); ?></h3>
                    <p class="yprint-no-designs-text">
                        <?php _e('Erstelle dein erstes individuelles Design und bringe deine Kreativität zum Ausdruck.', 'yprint-plugin'); ?>
                    </p>
                    <a href="<?php echo esc_url(home_url('/designer')); ?>" class="yprint-create-design-btn">
                        <i class="fas fa-plus"></i>
                        <?php _e('Design erstellen', 'yprint-plugin'); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="yprint-designs-container">
                    <div class="yprint-designs-list">
                    <?php foreach ($designs as $design) : 
    $template_id = self::get_template_id_for_design($design);
    // Temporärer Debug-Code
    self::debug_design_data($design);
?>
                            <div class="yprint-design-card" data-design-id="<?php echo esc_attr($design->id); ?>">
                                <div class="yprint-design-clickable-area" 
                                     data-design-id="<?php echo esc_attr($design->id); ?>"
                                     data-template-id="<?php echo esc_attr($template_id); ?>">
                                    <div class="yprint-design-image-container">
                                        <?php 
                                        $preview_url = self::get_design_preview_url($design);
                                        if ($preview_url) : ?>
                                            <img src="<?php echo esc_url($preview_url); ?>" 
                                                 alt="<?php echo esc_attr($design->name); ?>" 
                                                 class="yprint-design-image">
                                        <?php else : ?>
                                            <div class="yprint-design-image-placeholder">
                                                <i class="fas fa-image"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="yprint-design-content">
                                    <h3 class="yprint-design-name" 
                                            title="<?php echo esc_attr($design->name ?: 'Unbenanntes Design'); ?>">
                                            <?php echo esc_html($design->name ?: 'Unbenanntes Design'); ?>
                                        </h3>
                                        <p class="yprint-design-meta">
                                            <?php echo sprintf(__('Erstellt am %s', 'yprint-plugin'), 
                                                date_i18n('d.m.Y', strtotime($design->created_at))); ?>
                                        </p>
                                    </div>
                                </div>

                                <div class="yprint-design-actions">
                                    <div class="yprint-design-action reorder" 
                                         data-design-id="<?php echo esc_attr($design->id); ?>"
                                         title="<?php _e('Erneut bestellen', 'yprint-plugin'); ?>">
                                        <i class="fas fa-redo-alt"></i>
                                        <div class="yprint-design-action-label"><?php _e('Order', 'yprint-plugin'); ?></div>
                                    </div>
                                    
                                    <div class="yprint-design-action delete" 
                                         data-design-id="<?php echo esc_attr($design->id); ?>"
                                         title="<?php _e('Design löschen', 'yprint-plugin'); ?>">
                                        <i class="fas fa-trash"></i>
                                        <div class="yprint-design-action-label"><?php _e('Löschen', 'yprint-plugin'); ?></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <!-- New "Design something!" card -->
                        <a href="<?php echo esc_url(home_url('/basics')); ?>" class="yprint-create-new-card">
                            <div class="yprint-create-new-icon">
                                <i class="fas fa-tshirt" aria-hidden="true"></i>
                            </div>
                            <p class="yprint-create-new-text"><?php _e('Design something!', 'yprint-plugin'); ?></p>
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- This will be dynamically created by JavaScript -->

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) {
                console.error('YPrint Designs: Container not found!');
                return;
            }

            console.log('YPrint Designs: Initializing...', container);

            // DEBUG: Log container and buttons
            console.log('YPrint Debug: Container found:', container);
            
            // Handle reorder buttons
            const reorderButtons = container.querySelectorAll('.reorder');
            console.log('YPrint Debug: Found reorder buttons:', reorderButtons.length);
            
            reorderButtons.forEach((button, index) => {
                console.log(`YPrint Debug: Processing button ${index}:`, button);
                console.log(`YPrint Debug: Button dataset:`, button.dataset);
                
                button.addEventListener('click', function(e) {
                    console.log('YPrint Debug: Reorder button clicked!', this);
                    e.preventDefault();
                    e.stopPropagation();
                    const designId = this.dataset.designId;
                    console.log('YPrint Debug: Design ID:', designId);
                    handleReorder(designId, this);
                });
            });

             

            // Handle delete buttons
            const deleteButtons = container.querySelectorAll('.delete');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const designId = this.dataset.designId;
                    if (confirm('<?php echo esc_js(__('Möchtest du dieses Design wirklich löschen?', 'yprint-plugin')); ?>')) {
                        handleDelete(designId, this);
                    }
                });
            });

            function handleReorder(designId, button) {
                console.log('YPrint Debug: handleReorder called with:', designId, button);
                // First, load product sizes and show selection modal
                showSizeSelectionModal(designId, button);
            }

            /**
 * Zeigt das Größenauswahl-Dropdown an und lädt die Daten per AJAX.
 * @param {number} designId - Die ID des Designs.
 * @param {HTMLElement} button - Das geklickte Button-Element.
 */
function showSizeSelectionModal(designId, button) {
    console.log('YPrint Debug: showSizeSelectionModal called');
    console.log('YPrint Debug: Design ID:', designId);
    console.log('YPrint Debug: Button:', button);
    
    // Schließt alle anderen geöffneten Dropdowns
    closeAllSizeDropdowns();
    
    // Erstellt das Dropdown-Element und hängt es an den Body,
    // um Positionierungsprobleme zu vermeiden.
    const dropdown = createSizeDropdown(designId, button);
    console.log('YPrint Debug: Created dropdown:', dropdown);
    
    document.body.appendChild(dropdown);
    console.log('YPrint Debug: Dropdown appended to body');
    
    // Zeigt das Dropdown mit einer leichten Verzögerung für die Animation an
    // requestAnimationFrame wird für eine flüssigere Animation empfohlen
    requestAnimationFrame(() => {
        dropdown.classList.add('show');
        console.log('YPrint Debug: Added show class to dropdown');
    });
    
    // Startet den AJAX-Aufruf, um die Produktgrößen zu laden
    console.log('YPrint Debug: Starting AJAX request...');
    
    // Stellt sicher, dass jQuery verfügbar ist
    if (typeof jQuery === 'undefined') {
        console.error('YPrint Error: jQuery is not loaded.');
        const content = dropdown.querySelector('.yprint-size-dropdown-content');
        if (content) {
            content.innerHTML = '<div class="yprint-size-error">Ein Fehler ist aufgetreten (jQuery nicht gefunden).</div>';
        }
        return;
    }

    jQuery.ajax({
        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
        type: 'POST',
        dataType: 'json',
        timeout: 10000, // Timeout von 10 Sekunden
        data: {
            action: 'yprint_get_product_sizes',
            design_id: designId,
            nonce: '<?php echo wp_create_nonce('yprint_design_actions_nonce'); ?>'
        }
    })
    .done(function(response) {
        // Wird ausgeführt, wenn die Anfrage erfolgreich war (HTTP 200)
        console.log('YPrint Debug: AJAX response received:', response);
        
        const content = dropdown.querySelector('.yprint-size-dropdown-content');
        if (!content) {
            console.error('YPrint Debug: Content element not found!');
            return;
        }
        
        // Überprüft, ob die Antwort erfolgreich war und Daten enthält
        if (response && response.success && response.data && Array.isArray(response.data.sizes)) {
            console.log('YPrint Debug: Sizes found:', response.data.sizes);
            if (response.data.sizes.length > 0) {
                 renderSizeOptions(response.data.sizes, dropdown);
            } else {
                content.innerHTML = '<div class="yprint-size-info">Keine Größen für dieses Produkt verfügbar.</div>';
            }
        } else {
            // Behandelt Fehler, die vom Server als "success: false" gesendet wurden
            console.log('YPrint Debug: No sizes or error in response:', response);
            const errorMsg = response && response.data ? response.data[0]?.message || 'Keine Größen verfügbar' : 'Keine Größen verfügbar';
            content.innerHTML = '<div class="yprint-size-error">' + errorMsg + '</div>';
        }
    })
    .fail(function(xhr, status, error) {
        // Wird bei einem Netzwerkfehler oder HTTP-Fehlerstatus ausgeführt
        console.error('YPrint Debug: AJAX failed:', status, error);
        console.error('YPrint Debug: XHR status:', xhr.status);
        console.error('YPrint Debug: XHR response:', xhr.responseText);
        
        const content = dropdown.querySelector('.yprint-size-dropdown-content');
        if (content) {
            content.innerHTML = '<div class="yprint-size-error">Fehler beim Laden der Größen: ' + error + '</div>';
        }
    })
    .always(function() {
        // Wird immer ausgeführt, egal ob erfolgreich oder nicht
        console.log('YPrint Debug: AJAX request completed');
        // Hier könnte z.B. ein Lade-Spinner entfernt werden
    });
}


// DEBUG: Test if jQuery is available
console.log('YPrint Debug: jQuery available:', typeof jQuery !== 'undefined');
            console.log('YPrint Debug: AJAX URL:', '<?php echo esc_url(admin_url('admin-ajax.php')); ?>');
            console.log('YPrint Debug: Nonce:', '<?php echo wp_create_nonce('yprint_design_actions_nonce'); ?>');
            
            // DEBUG: Test manual button click
            window.testReorderClick = function() {
                const firstButton = container.querySelector('.reorder');
                if (firstButton) {
                    console.log('YPrint Debug: Testing first reorder button click');
                    firstButton.click();
                } else {
                    console.log('YPrint Debug: No reorder button found for testing');
                }
            };
            
            console.log('YPrint Debug: Initialization complete. Test with: testReorderClick()');

            function createSizeDropdown(designId, button) {
                const dropdown = document.createElement('div');
                dropdown.className = 'yprint-size-dropdown';
                dropdown.setAttribute('data-design-id', designId);
                
                dropdown.innerHTML = `
                    <div class="yprint-size-dropdown-header">
                        <div class="yprint-size-dropdown-title">Größe wählen</div>
                    </div>
                    <div class="yprint-size-dropdown-content">
                        <div class="yprint-size-loading">
                            <div class="yprint-spinner"></div>
                            Lade Größen...
                        </div>
                    </div>
                    <div class="yprint-size-dropdown-actions">
                        <button class="yprint-size-dropdown-btn cancel">Abbrechen</button>
                        <button class="yprint-size-dropdown-btn confirm" disabled>Bestellen</button>
                    </div>
                `;
                
                console.log('YPrint Debug: Dropdown HTML set:', dropdown.innerHTML);
                
                // Event listeners
                const cancelBtn = dropdown.querySelector('.cancel');
                const confirmBtn = dropdown.querySelector('.confirm');
                
                cancelBtn.onclick = function(e) {
                    e.stopPropagation();
                    closeDropdown(dropdown);
                };
                
                confirmBtn.onclick = function(e) {
                    e.stopPropagation();
                    const selectedSize = this.getAttribute('data-selected-size');
                    if (selectedSize) {
                        closeDropdown(dropdown);
                        proceedWithReorder(designId, button, selectedSize);
                    }
                };
                
                return dropdown;
            }

            // DEBUG: Test with fake sizes
            window.testSizesDisplay = function() {
                const firstButton = container.querySelector('.reorder');
                if (firstButton) {
                    const testDropdown = createSizeDropdown('999', firstButton);
                    firstButton.parentElement.appendChild(testDropdown);
                    
                    setTimeout(() => {
                        testDropdown.classList.add('show');
                        
                        // Fake sizes data
                        const fakeSizes = [
                            {size: 'S', out_of_stock: false},
                            {size: 'M', out_of_stock: false},
                            {size: 'L', out_of_stock: true},
                            {size: 'XL', out_of_stock: false}
                        ];
                        
                        renderSizeOptions(fakeSizes, testDropdown);
                    }, 100);
                }
            };

            function renderSizeOptions(sizes, dropdown) {
                console.log('YPrint Debug: renderSizeOptions called with sizes:', sizes);
                console.log('YPrint Debug: dropdown element:', dropdown);
                
                const content = dropdown.querySelector('.yprint-size-dropdown-content');
                const confirmBtn = dropdown.querySelector('.confirm');
                
                console.log('YPrint Debug: content element:', content);
                console.log('YPrint Debug: confirm button:', confirmBtn);
                
                if (!sizes || sizes.length === 0) {
                    content.innerHTML = '<div class="yprint-size-error">Keine Größen verfügbar.</div>';
                    return;
                }
                
                let html = '<div class="yprint-size-options">';
                
                sizes.forEach(function(size) {
                    const isDisabled = size.out_of_stock;
                    const disabledClass = isDisabled ? ' disabled' : '';
                    
                    html += `
                        <div class="yprint-size-option${disabledClass}" 
                             data-size="${size.size}">
                            ${size.size}
                        </div>
                    `;
                });
                
                html += '</div>';
                console.log('YPrint Debug: Generated HTML:', html);
                content.innerHTML = html;
                console.log('YPrint Debug: Content innerHTML set to:', content.innerHTML);
                
                // Add click listeners to size options
                const sizeOptions = content.querySelectorAll('.yprint-size-option:not(.disabled)');
                console.log('YPrint Debug: Found size options:', sizeOptions.length);
                
                sizeOptions.forEach(option => {
                    option.onclick = function(e) {
                        e.stopPropagation();
                        selectSize(this, confirmBtn);
                    };
                });
            }

            function selectSize(element, confirmBtn) {
                // Remove previous selection
                element.closest('.yprint-size-dropdown').querySelectorAll('.yprint-size-option.selected').forEach(el => {
                    el.classList.remove('selected');
                });
                
                // Select current
                element.classList.add('selected');
                
                // Enable confirm button
                const selectedSize = element.getAttribute('data-size');
                confirmBtn.disabled = false;
                confirmBtn.setAttribute('data-selected-size', selectedSize);
            }

            function closeDropdown(dropdown) {
    // Remove locked state from button
    const designId = dropdown.getAttribute('data-design-id');
    const button = document.querySelector(`[data-design-id="${designId}"].reorder`);
    if (button) {
        button.classList.remove('dropdown-open');
    }
    
    dropdown.classList.remove('show');
    setTimeout(() => {
        if (dropdown.parentElement) {
            dropdown.parentElement.removeChild(dropdown);
        }
    }, 200); // Reduced timeout
}

            function closeAllSizeDropdowns() {
                document.querySelectorAll('.yprint-size-dropdown').forEach(dropdown => {
                    closeDropdown(dropdown);
                });
            }

            // DEBUG: Check dropdown visibility
            window.checkDropdownVisibility = function() {
                const dropdowns = document.querySelectorAll('.yprint-size-dropdown');
                console.log('Found dropdowns:', dropdowns.length);
                dropdowns.forEach((dropdown, index) => {
                    const rect = dropdown.getBoundingClientRect();
                    const styles = window.getComputedStyle(dropdown);
                    console.log(`Dropdown ${index}:`, {
                        element: dropdown,
                        visible: dropdown.classList.contains('show'),
                        opacity: styles.opacity,
                        visibility: styles.visibility,
                        display: styles.display,
                        position: styles.position,
                        top: styles.top,
                        left: styles.left,
                        width: rect.width,
                        height: rect.height,
                        boundingRect: rect
                    });
                });
            };

            function createSizeDropdown(designId, button) {
    const dropdown = document.createElement('div');
    dropdown.className = 'yprint-size-dropdown';
    dropdown.setAttribute('data-design-id', designId);
    
    // Get button position for fixed positioning
    const buttonRect = button.getBoundingClientRect();
    
    dropdown.style.position = 'fixed';
    dropdown.style.top = (buttonRect.bottom + 4) + 'px';
    dropdown.style.left = buttonRect.left + 'px';
    dropdown.style.zIndex = '999999';
    
    // Prevent event bubbling from dropdown
    dropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    dropdown.addEventListener('mouseenter', function(e) {
        e.stopPropagation();
    });
    
    dropdown.addEventListener('mouseleave', function(e) {
        e.stopPropagation();
    });
                
                dropdown.innerHTML = `
                    <div class="yprint-size-dropdown-header">
                        <div class="yprint-size-dropdown-title">Größe wählen</div>
                    </div>
                    <div class="yprint-size-dropdown-content">
                        <div class="yprint-size-loading">
                            <div class="yprint-spinner"></div>
                            Lade Größen...
                        </div>
                    </div>
                    <div class="yprint-size-dropdown-actions">
                        <button class="yprint-size-dropdown-btn cancel">Abbrechen</button>
                        <button class="yprint-size-dropdown-btn confirm" disabled>Bestellen</button>
                    </div>
                `;
                
                console.log('YPrint Debug: Dropdown HTML set:', dropdown.innerHTML);
                
                // Event listeners
                const cancelBtn = dropdown.querySelector('.cancel');
                const confirmBtn = dropdown.querySelector('.confirm');
                
                cancelBtn.onclick = function(e) {
                    e.stopPropagation();
                    closeDropdown(dropdown);
                };
                
                confirmBtn.onclick = function(e) {
                    e.stopPropagation();
                    const selectedSize = this.getAttribute('data-selected-size');
                    if (selectedSize) {
                        closeDropdown(dropdown);
                        proceedWithReorder(designId, button, selectedSize);
                    }
                };
                
                // Append to body instead of button parent
                document.body.appendChild(dropdown);
                
                return dropdown;
            }

            // Close dropdown when clicking outside - with better event handling
document.addEventListener('click', function(e) {
    const dropdown = e.target.closest('.yprint-size-dropdown');
    const reorderButton = e.target.closest('.reorder');
    
    if (!dropdown && !reorderButton) {
        closeAllSizeDropdowns();
    }
}, true); // Use capture phase for better control

            function proceedWithReorder(designId, button, selectedSize) {
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i><div class="yprint-design-action-label">Lädt...</div>';
                button.style.pointerEvents = 'none';

                jQuery.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'yprint_reorder_design',
                        design_id: designId,
                        selected_size: selectedSize,
                        nonce: '<?php echo wp_create_nonce('yprint_design_actions_nonce'); ?>'
                    }
                })
                .done(function(response) {
                    if (response.success) {
                        button.innerHTML = '<i class="fas fa-check"></i><div class="yprint-design-action-label">Hinzugefügt!</div>';
                        
                        // Trigger cart update events
                        jQuery(document.body).trigger('added_to_cart', [[], '', button]);
                        jQuery(document.body).trigger('wc_fragments_refreshed');
                        
                        // Open mobile cart popup with multiple fallback methods
                        setTimeout(() => {
                            // Method 1: Try YPrint global function
                            if (typeof window.openYPrintCart === 'function') {
                                window.openYPrintCart();
                                return;
                            }
                            
                            // Method 2: Try jQuery trigger
                            if (jQuery('#mobile-cart-popup').length) {
                                jQuery(document).trigger('yprint:open-cart-popup');
                                return;
                            }
                            
                            // Method 3: Direct popup manipulation
                            const mobileCartPopup = document.getElementById('mobile-cart-popup');
                            if (mobileCartPopup) {
                                mobileCartPopup.classList.add('open');
                                document.body.classList.add('cart-popup-open');
                                mobileCartPopup.setAttribute('aria-hidden', 'false');
                                return;
                            }
                            
                            // Method 4: Hash navigation fallback
                            window.location.hash = '#mobile-cart';
                        }, 300);
                        
                        // Reset button after delay
                        setTimeout(() => {
                            button.innerHTML = originalContent;
                            button.style.pointerEvents = '';
                        }, 2000);
                    } else {
                        alert(response.data || 'Fehler beim Hinzufügen zum Warenkorb');
                        button.innerHTML = originalContent;
                        button.style.pointerEvents = '';
                    }
                })
                .fail(function() {
                    alert('Netzwerkfehler beim Hinzufügen zum Warenkorb');
                    button.innerHTML = originalContent;
                    button.style.pointerEvents = '';
                });
            }

            function handleDelete(designId, button) {
                const card = button.closest('.yprint-design-card');
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i><div class="yprint-design-action-label"></div>';
                button.style.pointerEvents = 'none';

                jQuery.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'yprint_delete_design',
                        design_id: designId,
                        nonce: '<?php echo wp_create_nonce('yprint_design_actions_nonce'); ?>'
                    }
                })
                .done(function(response) {
                    if (response.success) {
                        card.style.transform = 'scale(0.95)';
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            // Update design count
                            const countElement = container.querySelector('.yprint-your-designs-count');
                            const currentCount = parseInt(countElement.textContent.match(/\d+/)[0]);
                            const newCount = currentCount - 1;
                            countElement.textContent = newCount + (newCount === 1 ? ' Design' : ' Designs');
                            
                            // Show empty state if no designs left
                            if (newCount === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        alert(response.data || '<?php echo esc_js(__('Fehler beim Löschen des Designs', 'yprint-plugin')); ?>');
                        button.innerHTML = originalContent;
                        button.style.pointerEvents = 'auto';
                    }
                })
                .fail(function() {
                    alert('<?php echo esc_js(__('Ein Fehler ist aufgetreten', 'yprint-plugin')); ?>');
                    button.innerHTML = originalContent;
                    button.style.pointerEvents = 'auto';
                });
            }
        });
        </script>

        <?php
        return ob_get_clean();
    }


    
    /**
     * Get user designs from database
     *
     * @param int $user_id User ID
     * @param int $limit Number of designs to retrieve
     * @return array Array of design objects
     */
    private static function get_user_designs($user_id, $limit = 12) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'octo_user_designs';
        
        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") !== $table_name) {
            return array();
        }
        
        $designs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name 
             WHERE user_id = %d 
             ORDER BY created_at DESC 
             LIMIT %d",
            $user_id,
            $limit
        ));
        
        return $designs ?: array();
    }

    /**
     * Get template ID for a design from WooCommerce product
     * Korrigiert: Zuordnung zwischen design_template Custom Post Type und WooCommerce Produkt
     *
     * @param object $design Design object
     * @return int|null WooCommerce Product ID or null if not found
     */
    private static function get_template_id_for_design($design) {
        // Debug: Log the design object
        echo "<script>console.log('YPrint PHP Debug: get_template_id_for_design() called for design ID: " . $design->id . "');</script>";
        echo "<script>console.log('YPrint PHP Debug: Design object template_id field: " . (isset($design->template_id) ? $design->template_id : 'NOT SET') . "');</script>";
        
        if (empty($design->template_id)) {
            echo "<script>console.log('YPrint PHP Debug: Design template_id is empty for design ID: " . $design->id . "');</script>";
            return null;
        }
    
        // Für das Designer-Tool benötigen wir die template_id (design_template Post ID), nicht die WooCommerce Product ID
        $design_template_id = $design->template_id;
        echo "<script>console.log('YPrint PHP Debug: Returning design_template_id: " . $design_template_id . "');</script>";
        
        // Prüfen ob das design_template existiert
        $design_template = get_post($design_template_id);
        if (!$design_template || $design_template->post_type !== 'design_template') {
            echo "<script>console.log('YPrint PHP Debug: Design template post not found or wrong type for ID: " . $design_template_id . "');</script>";
            return null;
        }
        
        echo "<script>console.log('YPrint PHP Debug: Found valid design_template: " . esc_js($design_template->post_title) . " (ID: " . $design_template_id . ")');</script>";
        
        // Direkt die design_template ID zurückgeben, da das Designer-Tool diese benötigt
        return intval($design_template_id);
    }

    /**
     * Get preview URL for a design
     *
     * @param object $design Design object
     * @return string Preview URL or empty string
     */
    private static function get_design_preview_url($design) {
        // First try to get from product_images
        if (!empty($design->product_images)) {
            $product_images = json_decode($design->product_images, true);
            if (is_array($product_images) && !empty($product_images)) {
                // Return first product image URL
                return $product_images[0]['url'] ?? '';
            }
        }
        
        // Fallback to design_data
        if (!empty($design->design_data)) {
            $design_data = json_decode($design->design_data, true);
            if (is_array($design_data) && !empty($design_data['images'])) {
                return $design_data['images'][0]['url'] ?? '';
            }
        }
        
        return '';
    }

    /**
     * Check if design has been ordered
     *
     * @param int $design_id Design ID
     * @return bool True if design has orders
     */
    private static function design_has_orders($design_id) {
        global $wpdb;
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_order_itemmeta oim
             INNER JOIN {$wpdb->prefix}woocommerce_order_items oi ON oim.order_item_id = oi.order_item_id
             INNER JOIN {$wpdb->prefix}posts p ON oi.order_id = p.ID
             WHERE oim.meta_key = '_design_id' 
             AND oim.meta_value = %s
             AND p.post_status IN ('wc-completed', 'wc-processing', 'wc-on-hold')",
            $design_id
        ));
        
        return $count > 0;
    }


    
    /**
     * Handle reorder design AJAX request
     */
    public static function handle_reorder_design() {
        check_ajax_referer('yprint_design_actions_nonce', 'nonce');

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        
        if (!$design_id) {
            wp_send_json_error('Ungültige Design-ID');
            return;
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_send_json_error('Du musst angemeldet sein');
            return;
        }

        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce ist nicht aktiv');
            return;
        }

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'octo_user_designs';
            
            // Get design from database
            $design = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
                $design_id,
                $current_user_id
            ));
            
            if (!$design) {
                wp_send_json_error('Design nicht gefunden');
                return;
            }

            // Get base product ID from Octo Print Designer settings
            $base_product_id = null;
            if (class_exists('Octo_Print_Designer_Settings')) {
                $base_product_id = Octo_Print_Designer_Settings::get_base_product_id();
            }
            
            if (!$base_product_id) {
                wp_send_json_error('Base Product nicht konfiguriert');
                return;
            }

            // Check if product exists and is purchasable
            $product = wc_get_product($base_product_id);
            if (!$product || !$product->is_purchasable()) {
                wp_send_json_error('Produkt ist nicht verfügbar');
                return;
            }

            // Parse design variations und design_data für korrekte Defaults
            $variations = json_decode($design->variations ?? '{}', true);
            $design_data = json_decode($design->design_data ?? '{}', true);
            
            if (!is_array($variations)) {
                $variations = array();
            }
            
            if (!is_array($design_data)) {
                $design_data = array();
            }

            $default_variation = '';
            $default_size = '';
            
            // Zuerst versuchen aus design_data die ursprünglichen Werte zu extrahieren
            if (isset($design_data['selectedVariationId']) && !empty($design_data['selectedVariationId'])) {
                $default_variation = $design_data['selectedVariationId'];
            } elseif (!empty($variations)) {
                $variation_keys = array_keys($variations);
                $default_variation = $variation_keys[0];
            }
            
            if (isset($design_data['selectedSizeId']) && !empty($design_data['selectedSizeId'])) {
                $default_size = $design_data['selectedSizeId'];
            } elseif (!empty($variations) && !empty($default_variation) && isset($variations[$default_variation]['sizes'])) {
                $size_keys = array_keys($variations[$default_variation]['sizes']);
                $default_size = $size_keys[0];
            }
            
            // Fallback zu Template-Defaults wenn immer noch leer
            if (empty($default_variation) || empty($default_size)) {
                $template_id = $design->template_id;
                if ($template_id) {
                    $template_variations = get_post_meta($template_id, 'template_variations', true);
                    if (is_array($template_variations)) {
                        if (empty($default_variation)) {
                            $template_var_keys = array_keys($template_variations);
                            $default_variation = $template_var_keys[0] ?? '1';
                        }
                        
                        if (empty($default_size) && isset($template_variations[$default_variation]['sizes'])) {
                            $template_size_keys = array_keys($template_variations[$default_variation]['sizes']);
                            $default_size = $template_size_keys[0] ?? 's';
                        }
                    }
                }
            }

            // Extrahiere alle wichtigen Daten aus dem ursprünglichen Design
            $preview_url = '';
            $design_images = array();
            $product_images = array();
            $design_width_cm = 0;
            $design_height_cm = 0;
            
            // Zuerst product_images verarbeiten
            if (!empty($design->product_images)) {
                $product_images = json_decode($design->product_images, true);
                if (is_array($product_images) && !empty($product_images)) {
                    $preview_url = $product_images[0]['url'] ?? '';
                }
            }
            
            // Dann design_images verarbeiten (für Print Provider)
            if (!empty($design->design_images)) {
                $design_images = json_decode($design->design_images, true);
                if (is_array($design_images) && !empty($design_images)) {
                    // Größe aus erstem Design-Element extrahieren
                    $first_image = $design_images[0];
                    $design_width_cm = floatval($first_image['width_cm'] ?? 0);
                    $design_height_cm = floatval($first_image['height_cm'] ?? 0);
                }
            }
            
            // Fallback zu design_data wenn nötig
            if (empty($preview_url) && is_array($design_data)) {
                if (isset($design_data['preview_url'])) {
                    $preview_url = $design_data['preview_url'];
                }
                
                // Versuche Dimensionen aus variationImages zu extrahieren
                if (($design_width_cm == 0 || $design_height_cm == 0) && isset($design_data['variationImages'])) {
                    foreach ($design_data['variationImages'] as $var_key => $images) {
                        if (is_array($images) && !empty($images)) {
                            $first_img = is_array($images[0]) ? $images[0] : $images;
                            if (isset($first_img['width_cm']) && isset($first_img['height_cm'])) {
                                $design_width_cm = floatval($first_img['width_cm']);
                                $design_height_cm = floatval($first_img['height_cm']);
                                break;
                            }
                        }
                    }
                }
            }
            
            // Template-Informationen laden für Variation/Size Details
$variation_name = 'Standard';
$variation_color = '#000000';
$size_name = 'One Size';

// Zuerst die Fallback-Werte aus dem Template laden
if ($design->template_id && !empty($default_variation)) {
    $template_variations = get_post_meta($design->template_id, 'template_variations', true);
    if (is_array($template_variations) && isset($template_variations[$default_variation])) {
        $var_data = $template_variations[$default_variation];
        $variation_name = $var_data['name'] ?? 'Standard';
        $variation_color = $var_data['color_code'] ?? '#000000';
        
        if (isset($var_data['sizes'][$default_size])) {
            $size_name = $var_data['sizes'][$default_size]['name'] ?? 'One Size';
        }
    }
}

// Design-Standardfarbe aus dem korrekten Meta-Feld laden
$design_color = get_post_meta($base_product_id, '_design_color', true);
if (!empty($design_color)) {
    $variation_name = $design_color;
}

            // Get selected size from AJAX request
$selected_size = isset($_POST['selected_size']) ? sanitize_text_field($_POST['selected_size']) : '';

// Use selected size as size_name if provided, otherwise fallback to default
$final_size_name = !empty($selected_size) ? $selected_size : $size_name;

// === URL QUALITY DEBUGGING FÜR CART TRANSFER ===
$final_design_url = self::get_final_design_url($design_id, $preview_url);

// SAFE DEBUG LOGGING (nicht in AJAX Response)
error_log('CART URL QUALITY DEBUG - Design ID: ' . $design_id);
error_log('CART URL QUALITY DEBUG - Preview URL from DB: ' . $preview_url);
error_log('CART URL QUALITY DEBUG - Final Design URL: ' . $final_design_url);
error_log('CART URL QUALITY DEBUG - URLs match (Preview used): ' . ($preview_url === $final_design_url ? 'true' : 'false'));

// Add to cart with vollständigen design metadata für Print Provider System
$cart_item_data = array(
    '_design_id' => $design_id,
    '_selected_size' => $selected_size,
    'print_design' => array(
    'design_id' => $design_id,
    'name' => $design->name ?? 'Custom Design',
    'template_id' => $design->template_id ?? '',
    'variation_id' => $default_variation,
    'variation_name' => $variation_name,
    'variation_color' => $variation_color,
    'size_id' => $default_size,
    'size_name' => $final_size_name,
    'preview_url' => $preview_url,
    'product_design_color' => $variation_name,
    'design_color' => $variation_name, // <- HINZUGEFÜGT: Primäres Feld für Cart-Anzeige
    // Dimensionen für Print Provider
    'design_width_cm' => $design_width_cm,
    'design_height_cm' => $design_height_cm,
                    // Multi-View Daten für Print Provider
                    'design_images' => $design_images,
                    'product_images' => $product_images,
                    'has_multiple_images' => !empty($design_images),
                    // Legacy Kompatibilität
                    'design_image_url' => $final_design_url,
                    'design_scaleX' => 1,
                    'design_scaleY' => 1,
                    // Pricing
                    'calculated_price' => $product->get_price(),
                    // Selected size information
                    'selected_size' => $selected_size
                ),
                '_design_template_id' => $design->template_id ?? '',
                '_design_variation_id' => $default_variation,
                '_design_size_id' => $default_size,
                '_is_design_product' => true,
                'unique_design_key' => md5($design_id . time())
            );

            $cart_item_key = WC()->cart->add_to_cart(
                $base_product_id,
                1,
                0,
                array(),
                $cart_item_data
            );

            if (!$cart_item_key) {
                wp_send_json_error('Design konnte nicht zum Warenkorb hinzugefügt werden');
                return;
            }

            wp_send_json_success(array(
                'message' => 'Design wurde zum Warenkorb hinzugefügt',
                'cart_item_key' => $cart_item_key,
                'open_cart' => true,
                'debug_design_color' => array(
                    'base_product_id' => $base_product_id,
                    'variation_name_used' => $variation_name,
                    'design_color_set' => $variation_name,
                    'yprint_zusatzdaten_keys' => is_array($yprint_zusatzdaten) ? array_keys($yprint_zusatzdaten) : 'KEINE'
                ),
                'debug_url_quality' => array(
                    'preview_url' => $preview_url,
                    'final_design_url' => $final_design_url,
                    'urls_match' => ($preview_url === $final_design_url),
                    'get_final_called' => true
                )
            ));

        } catch (Exception $e) {
            wp_send_json_error('Fehler: ' . $e->getMessage());
        }
    }

/**
 * Get final design URL from database or fallback to preview
 */
private static function get_final_design_url($design_id, $preview_fallback) {
    global $wpdb;
    
    $design = $wpdb->get_row($wpdb->prepare(
        "SELECT design_data FROM {$wpdb->prefix}octo_user_designs WHERE id = %d",
        $design_id
    ));
    
    if ($design && !empty($design->design_data)) {
        $design_data = json_decode($design->design_data, true);
        
        // Priorität 1: Finale Design-URL aus Datenbank
        if (!empty($design_data['final_design_url'])) {
            return $design_data['final_design_url'];
        }
        
        // Priorität 2: Original URL aus Datenbank
        if (!empty($design_data['design_image_url'])) {
            return $design_data['design_image_url'];
        }
    }
    
    // Fallback: Preview URL
    return $preview_fallback;
}

    /**
     * Handle delete design AJAX request
     */
    public static function handle_delete_design() {
        check_ajax_referer('yprint_design_actions_nonce', 'nonce');

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        
        if (!$design_id) {
            wp_send_json_error('Ungültige Design-ID');
            return;
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_send_json_error('Du musst angemeldet sein');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'octo_user_designs';
        
        $result = $wpdb->delete(
            $table_name,
            array(
                'id' => $design_id,
                'user_id' => $current_user_id
            ),
            array('%d', '%d')
        );

        if ($result === false) {
            wp_send_json_error('Fehler beim Löschen des Designs');
            return;
        }

        if ($result === 0) {
            wp_send_json_error('Design nicht gefunden oder keine Berechtigung');
            return;
        }

        wp_send_json_success(array(
            'message' => 'Design wurde erfolgreich gelöscht',
            'design_id' => $design_id
        ));
    }

    /**
     * Handle get product sizes AJAX request
     */
    public static function handle_get_product_sizes() {
        check_ajax_referer('yprint_design_actions_nonce', 'nonce');

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        
        if (!$design_id) {
            wp_send_json_error('Ungültige Design-ID');
            return;
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            wp_send_json_error('Du musst angemeldet sein');
            return;
        }

        try {
            global $wpdb;
            $table_name = $wpdb->prefix . 'octo_user_designs';
            
            // Get design from database
            $design = $wpdb->get_row($wpdb->prepare(
                "SELECT template_id FROM {$table_name} WHERE id = %d AND user_id = %d",
                $design_id,
                $current_user_id
            ));
            
            if (!$design || !$design->template_id) {
                wp_send_json_error('Design oder Template nicht gefunden');
                return;
            }

            // Get base product ID from Octo Print Designer settings
            $base_product_id = null;
            if (class_exists('Octo_Print_Designer_Settings')) {
                $base_product_id = Octo_Print_Designer_Settings::get_base_product_id();
            }
            
            if (!$base_product_id) {
                wp_send_json_error('Base Product nicht konfiguriert');
                return;
            }

            // Get product sizes from WooCommerce product meta
            $sizes_json = get_post_meta($base_product_id, '_yprint_product_sizes', true);
            
            if (empty($sizes_json)) {
                wp_send_json_success(array('sizes' => array()));
                return;
            }
            
            $sizes_data = json_decode($sizes_json, true);
            
            if (!is_array($sizes_data)) {
                wp_send_json_success(array('sizes' => array()));
                return;
            }

            wp_send_json_success(array(
                'sizes' => $sizes_data,
                'design_id' => $design_id,
                'product_id' => $base_product_id
            ));

        } catch (Exception $e) {
            wp_send_json_error('Fehler beim Laden der Größen: ' . $e->getMessage());
        }
    }

    // Füge diese Funktion nach der get_template_id_for_design() Funktion hinzu:
private static function debug_design_data($design) {
    echo "<script>console.log('=== DESIGN DATA DEBUG ===');</script>";
    echo "<script>console.log('Design ID: " . $design->id . "');</script>";
    echo "<script>console.log('Template ID: " . $design->template_id . "');</script>";
    echo "<script>console.log('Design Data Length: " . strlen($design->design_data ?? '') . "');</script>";
    
    if (!empty($design->design_data)) {
        $decoded_data = json_decode($design->design_data, true);
        if ($decoded_data) {
            $data_keys = array_keys($decoded_data);
            echo "<script>console.log('Design Data Keys: " . esc_js(implode(', ', $data_keys)) . "');</script>";
            
            // Prüfe speziell nach variationImages
            if (isset($decoded_data['variationImages'])) {
                $variation_keys = array_keys($decoded_data['variationImages']);
                echo "<script>console.log('Variation Images Keys: " . esc_js(implode(', ', $variation_keys)) . "');</script>";
            } else {
                echo "<script>console.log('ERROR: No variationImages found in design_data!');</script>";
            }
        } else {
            echo "<script>console.log('ERROR: Could not decode design_data JSON!');</script>";
            echo "<script>console.log('Raw design_data (first 200 chars): " . esc_js(substr($design->design_data, 0, 200)) . "');</script>";
        }
    } else {
        echo "<script>console.log('ERROR: design_data is empty!');</script>";
    }
    echo "<script>console.log('=== END DEBUG ===');</script>";
}

/**
     * Debug Reorder Cart Data
     */
    private static function debug_reorder_data($design, $cart_item_data) {
        echo "<script>console.log('=== REORDER DEBUG ===');</script>";
        echo "<script>console.log('Design ID: " . $design->id . "');</script>";
        echo "<script>console.log('Template ID: " . ($design->template_id ?? 'NULL') . "');</script>";
        echo "<script>console.log('Default Variation: " . ($cart_item_data['print_design']['variation_id'] ?? 'NULL') . "');</script>";
        echo "<script>console.log('Default Size: " . ($cart_item_data['print_design']['size_id'] ?? 'NULL') . "');</script>";
        echo "<script>console.log('Design Images Count: " . count($cart_item_data['print_design']['design_images'] ?? []) . "');</script>";
        echo "<script>console.log('Product Images Count: " . count($cart_item_data['print_design']['product_images'] ?? []) . "');</script>";
        echo "<script>console.log('Width CM: " . ($cart_item_data['print_design']['design_width_cm'] ?? 'NULL') . "');</script>";
        echo "<script>console.log('Height CM: " . ($cart_item_data['print_design']['design_height_cm'] ?? 'NULL') . "');</script>";
        echo "<script>console.log('=== END REORDER DEBUG ===');</script>";
    }
    
}



// Initialize the class
YPrint_Your_Designs::init();