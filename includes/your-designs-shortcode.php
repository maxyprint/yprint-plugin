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
            overflow-y: hidden;
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
                                        <div class="yprint-design-action-label"><?php _e('Reorder', 'yprint-plugin'); ?></div>
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

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) {
                console.error('YPrint Designs: Container not found!');
                return;
            }

            console.log('YPrint Designs: Initializing...', container);

            // Handle reorder buttons
            const reorderButtons = container.querySelectorAll('.reorder');
            reorderButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    const designId = this.dataset.designId;
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
                const originalContent = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i><div class="yprint-design-action-label">Lädt...</div>';
                button.style.pointerEvents = 'none';

                jQuery.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'yprint_reorder_design',
                        design_id: designId,
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
        error_log('YPRINT DEBUG: handle_reorder_design called');
        
        check_ajax_referer('yprint_design_actions_nonce', 'nonce');

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        error_log('YPRINT DEBUG: design_id = ' . $design_id);
        
        if (!$design_id) {
            error_log('YPRINT DEBUG: Invalid design_id');
            wp_send_json_error('Ungültige Design-ID');
            return;
        }

        $current_user_id = get_current_user_id();
        error_log('YPRINT DEBUG: current_user_id = ' . $current_user_id);
        
        if (!$current_user_id) {
            error_log('YPRINT DEBUG: User not logged in');
            wp_send_json_error('Du musst angemeldet sein');
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'yprint_designs';
        error_log('YPRINT DEBUG: table_name = ' . $table_name);
        
        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
        error_log('YPRINT DEBUG: table_exists = ' . ($table_exists ? 'YES' : 'NO'));
        
        if (!$table_exists) {
            error_log('YPRINT DEBUG: Table does not exist!');
            wp_send_json_error('Design-Tabelle nicht gefunden');
            return;
        }
        
        // Debug query
        $query = $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE id = %d AND user_id = %d",
            $design_id,
            $current_user_id
        );
        error_log('YPRINT DEBUG: SQL Query = ' . $query);
        
        // Get design from database
        $design = $wpdb->get_row($query);
        error_log('YPRINT DEBUG: design result = ' . print_r($design, true));
        error_log('YPRINT DEBUG: wpdb last_error = ' . $wpdb->last_error);
        
        if (!$design) {
            // Try without user_id restriction to see if design exists at all
            $design_any_user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$table_name} WHERE id = %d",
                $design_id
            ));
            error_log('YPRINT DEBUG: design_any_user = ' . print_r($design_any_user, true));
            
            wp_send_json_error('Design nicht gefunden - ID: ' . $design_id . ', User: ' . $current_user_id);
            return;
        }

        // TODO: Add to WooCommerce cart
        wp_send_json_success(array(
            'message' => 'Design wurde zum Warenkorb hinzugefügt',
            'redirect_url' => wc_get_cart_url()
        ));
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
}

// Initialize the class
YPrint_Your_Designs::init();