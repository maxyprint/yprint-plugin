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
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #ffffff;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.08);
            margin-bottom: 32px;
        }

        .yprint-your-designs-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }

        .yprint-your-designs-title {
            font-size: 24px;
            font-weight: 700;
            color: #111827;
            margin: 0;
            line-height: 1.2;
        }

        .yprint-your-designs-count {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }

        .yprint-designs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .yprint-design-card {
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
            transition: all 0.2s ease;
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .yprint-design-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
            border-color: #d1d5db;
        }

        .yprint-design-image-container {
            position: relative;
            width: 100%;
            aspect-ratio: 1;
            background-color: #f9fafb;
            overflow: hidden;
            border-radius: 12px 12px 0 0;
        }

        .yprint-design-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .yprint-design-card:hover .yprint-design-image {
            transform: scale(1.05);
        }

        .yprint-design-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(180deg, rgba(0,0,0,0) 0%, rgba(0,0,0,0.5) 100%);
            opacity: 0;
            transition: opacity 0.2s ease;
            display: flex;
            align-items: flex-end;
            padding: 12px;
        }

        .yprint-design-card:hover .yprint-design-overlay {
            opacity: 1;
        }

        .yprint-design-actions {
            display: flex;
            gap: 8px;
            width: 100%;
        }

        .yprint-design-action-btn {
            flex: 1;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border: none;
            border-radius: 8px;
            padding: 8px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4px;
        }

        .yprint-design-action-btn:hover {
            background: rgba(255, 255, 255, 1);
            transform: translateY(-1px);
        }

        .yprint-design-action-btn.delete {
            background: rgba(239, 68, 68, 0.9);
            color: white;
        }

        .yprint-design-action-btn.delete:hover {
            background: rgba(220, 38, 38, 1);
        }

        .yprint-design-info {
            padding: 16px;
        }

        .yprint-design-name {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
            margin: 0 0 4px 0;
            text-align: center;
            line-height: 1.3;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .yprint-design-meta {
            font-size: 13px;
            color: #6b7280;
            text-align: center;
            margin: 0;
        }

        .yprint-design-status {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 8px;
        }

        .yprint-design-status.saved {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .yprint-design-status.ordered {
            background-color: #d1fae5;
            color: #065f46;
        }

        .yprint-no-designs {
            text-align: center;
            padding: 48px 24px;
            color: #6b7280;
        }

        .yprint-no-designs-icon {
            font-size: 48px;
            color: #d1d5db;
            margin-bottom: 16px;
        }

        .yprint-no-designs-title {
            font-size: 20px;
            font-weight: 600;
            color: #374151;
            margin: 0 0 8px 0;
        }

        .yprint-no-designs-text {
            font-size: 16px;
            margin: 0 0 24px 0;
            line-height: 1.5;
        }

        .yprint-create-design-btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background-color: #0079FF;
            color: white;
            border: none;
            border-radius: 10px;
            padding: 12px 24px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .yprint-create-design-btn:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 121, 255, 0.3);
        }

        .yprint-loading {
            text-align: center;
            padding: 32px;
            color: #6b7280;
        }

        .yprint-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 2px solid #d1d5db;
            border-top: 2px solid #0079FF;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .yprint-your-designs {
                padding: 20px;
                border-radius: 12px;
            }

            .yprint-your-designs-title {
                font-size: 20px;
            }

            .yprint-designs-grid {
                grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
                gap: 12px;
            }

            .yprint-design-info {
                padding: 12px;
            }

            .yprint-design-name {
                font-size: 14px;
            }

            .yprint-design-meta {
                font-size: 12px;
            }

            .yprint-design-actions {
                flex-direction: column;
                gap: 6px;
            }

            .yprint-design-action-btn {
                font-size: 11px;
                padding: 6px 8px;
            }
        }

        @media (max-width: 480px) {
            .yprint-designs-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .yprint-your-designs-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 8px;
            }
        }
        </style>

        <div id="<?php echo esc_attr($unique_id); ?>" class="<?php echo esc_attr($css_class); ?>">
            <div class="yprint-your-designs-header">
                <h2 class="yprint-your-designs-title"><?php _e('Your Designs', 'yprint-plugin'); ?></h2>
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
                    <a href="<?php echo esc_url(home_url('/design-tool')); ?>" class="yprint-create-design-btn">
                        <i class="fas fa-plus"></i>
                        <?php _e('Design erstellen', 'yprint-plugin'); ?>
                    </a>
                </div>
            <?php else : ?>
                <div class="yprint-designs-grid">
                    <?php foreach ($designs as $design) : ?>
                        <div class="yprint-design-card" data-design-id="<?php echo esc_attr($design->id); ?>">
                            <div class="yprint-design-image-container">
                                <?php 
                                $preview_url = self::get_design_preview_url($design);
                                if ($preview_url) : ?>
                                    <img src="<?php echo esc_url($preview_url); ?>" 
                                         alt="<?php echo esc_attr($design->name); ?>" 
                                         class="yprint-design-image">
                                <?php else : ?>
                                    <div class="yprint-design-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-image" style="font-size: 24px; color: white; opacity: 0.7;"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="yprint-design-overlay">
                                    <div class="yprint-design-actions">
                                        <button class="yprint-design-action-btn reorder" 
                                                data-design-id="<?php echo esc_attr($design->id); ?>"
                                                title="<?php _e('Erneut bestellen', 'yprint-plugin'); ?>">
                                            <i class="fas fa-shopping-cart"></i>
                                            <?php _e('Bestellen', 'yprint-plugin'); ?>
                                        </button>
                                        <a href="<?php echo esc_url(home_url('/design-tool?edit=' . $design->id)); ?>" 
                                           class="yprint-design-action-btn edit"
                                           title="<?php _e('Design bearbeiten', 'yprint-plugin'); ?>">
                                            <i class="fas fa-edit"></i>
                                            <?php _e('Bearbeiten', 'yprint-plugin'); ?>
                                        </a>
                                        <button class="yprint-design-action-btn delete" 
                                                data-design-id="<?php echo esc_attr($design->id); ?>"
                                                title="<?php _e('Design löschen', 'yprint-plugin'); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="yprint-design-info">
                                <h3 class="yprint-design-name"><?php echo esc_html($design->name ?: 'Unbenanntes Design'); ?></h3>
                                <p class="yprint-design-meta">
                                    <?php echo sprintf(__('Erstellt am %s', 'yprint-plugin'), 
                                        date_i18n('d.m.Y', strtotime($design->created_at))); ?>
                                </p>
                                <?php if (self::design_has_orders($design->id)) : ?>
                                    <span class="yprint-design-status ordered"><?php _e('Bestellt', 'yprint-plugin'); ?></span>
                                <?php else : ?>
                                    <span class="yprint-design-status saved"><?php _e('Gespeichert', 'yprint-plugin'); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) return;

            // Handle reorder buttons
            const reorderButtons = container.querySelectorAll('.reorder');
            reorderButtons.forEach(button => {
                button.addEventListener('click', function(e) {
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

            // Handle card clicks (navigate to design tool)
            const designCards = container.querySelectorAll('.yprint-design-card');
            designCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    // Don't navigate if clicking on action buttons
                    if (e.target.closest('.yprint-design-action-btn')) {
                        return;
                    }
                    const designId = this.dataset.designId;
                    window.location.href = '<?php echo esc_url(home_url('/design-tool?edit=')); ?>' + designId;
                });
            });

            function handleReorder(designId, button) {
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <?php echo esc_js(__('Lädt...', 'yprint-plugin')); ?>';
                button.disabled = true;

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
                        button.innerHTML = '<i class="fas fa-check"></i> <?php echo esc_js(__('Hinzugefügt', 'yprint-plugin')); ?>';
                        setTimeout(() => {
                            window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>';
                        }, 1000);
                    } else {
                        alert(response.data || '<?php echo esc_js(__('Fehler beim Hinzufügen zum Warenkorb', 'yprint-plugin')); ?>');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .fail(function() {
                    alert('<?php echo esc_js(__('Ein Fehler ist aufgetreten', 'yprint-plugin')); ?>');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }

            function handleDelete(designId, button) {
                const card = button.closest('.yprint-design-card');
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                button.disabled = true;

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
                        card.style.transform = 'scale(0.8)';
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
                        button.innerHTML = originalText;
                        button.disabled = false;
                    }
                })
                .fail(function() {
                    alert('<?php echo esc_js(__('Ein Fehler ist aufgetreten', 'yprint-plugin')); ?>');
                    button.innerHTML = originalText;
                    button.disabled = false;
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

        global $wpdb;
        $table_name = $wpdb->prefix . 'octo_user_designs';
        
        $design = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND user_id = %d",
            $design_id,
            $current_user_id
        ));

        if (!$design) {
            wp_send_json_error('Design nicht gefunden');
            return;
        }

        // Here you would implement the logic to add the design to cart
        // This would typically involve:
        // 1. Getting the template and product data
        // 2. Calculating the price
        // 3. Adding to WooCommerce cart with design data
        
        try {
            // For now, just return success
            // In a real implementation, you'd use your existing add-to-cart logic
            wp_send_json_success(array(
                'message' => 'Design wurde zum Warenkorb hinzugefügt',
                'design_id' => $design_id
            ));
        } catch (Exception $e) {
            wp_send_json_error('Fehler beim Hinzufügen zum Warenkorb: ' . $e->getMessage());
        }
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
}

// Initialize the class
YPrint_Your_Designs::init();