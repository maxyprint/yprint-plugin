<?php
/**
 * Modern & Efficient Order Actions Shortcode for YPrint
 * Creates action buttons for orders with social sharing, reorder, feedback, etc.
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle order action buttons (Modern & Efficient)
 */
class YPrint_Order_Actions_Modern {

    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode('yprint_order_actions', array(__CLASS__, 'render_order_actions'));
        add_action('wp_ajax_yprint_reorder_item', array(__CLASS__, 'handle_reorder'));
        add_action('wp_ajax_nopriv_yprint_reorder_item', array(__CLASS__, 'handle_reorder'));
    }

    /**
     * Render order actions shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_order_actions($atts) {
        $atts = shortcode_atts(array(
            'class' => 'yprint-order-actions'
        ), $atts, 'yprint_order_actions');

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return '<p class="yprint-order-actions-login-required">' .
                   __('Bitte melde dich an, um deine Bestellaktionen zu sehen.', 'yprint-plugin') .
                   ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">' .
                   __('Jetzt anmelden', 'yprint-plugin') . '</a></p>';
        }

        if (!class_exists('WooCommerce')) {
            return '<p class="error-message">' . __('WooCommerce ist nicht aktiviert.', 'yprint-plugin') . '</p>';
        }

        $latest_order = self::get_latest_user_order($current_user_id);
        if (!$latest_order) {
            return '<p class="yprint-no-orders">' .
                   __('Du hast noch keine Bestellungen.', 'yprint-plugin') .
                   ' <a href="' . esc_url(home_url('/products')) . '">' .
                   __('Jetzt einkaufen', 'yprint-plugin') . '</a></p>';
        }

        $order_items = $latest_order->get_items();
        if (empty($order_items)) {
            return '<p class="error-message">' . __('Keine Artikel in der letzten Bestellung gefunden.', 'yprint-plugin') . '</p>';
        }

        $latest_item = reset($order_items);
        $order_id = $latest_order->get_id();
        $item_id = $latest_item->get_id();
        $product = $latest_item->get_product();

        $design_data = $latest_item->get_meta('print_design');
        $design_id = $design_data['design_id'] ?? '';
        $design_name = $design_data['name'] ?? ($product ? $product->get_name() : $latest_item->get_name());
        $design_image = $design_data['preview_url'] ?? ($product ? wp_get_attachment_image_url($product->get_image_id(), 'medium') : '');
        $product_url = $product ? get_permalink($product->get_id()) : '';

        $css_class = sanitize_html_class($atts['class']);
        $unique_id = 'yprint-order-actions-' . uniqid();

        $share_title = __('Schau dir mein Design bei YPrint an!', 'yprint-plugin');
        $share_text = __('Individuelles Streetwear Design bei YPrint erstellt', 'yprint-plugin');
        $adding_to_cart_text = __('Artikel wird hinzugefügt...', 'yprint-plugin');
        $added_to_cart_text = __('Artikel wurde zum Warenkorb hinzugefügt', 'yprint-plugin');
        $error_adding_text = __('Fehler beim Hinzufügen zum Warenkorb', 'yprint-plugin');
        $copy_link_success = __('Link wurde kopiert!', 'yprint-plugin');
        $copy_insta_success = __('Text wurde kopiert! Du kannst es jetzt in Instagram einfügen.', 'yprint-plugin');

        ob_start();
        ?>

        <style>
        .yprint-order-actions {
            display: flex;
            gap: 10px;
            margin: 20px 0;
            flex-wrap: wrap;
        }

        .yprint-order-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border: 1px solid #e0e0e0;
            border-radius: 25px;
            background: #ffffff;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            min-width: 120px;
            box-sizing: border-box;
        }

        .yprint-order-action-btn:hover {
            background: #f8f9fa;
            border-color: #0079FF;
            color: #0079FF;
        }

        .yprint-order-action-btn.primary {
            background: #0079FF;
            color: white;
            border-color: #0079FF;
        }

        .yprint-order-action-btn.primary:hover {
            background: #0056b3;
            border-color: #0056b3;
            color: white;
        }

        .yprint-order-action-btn i {
            font-size: 16px;
        }

        .yprint-order-action-btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .yprint-order-action-btn.loading::after {
            content: '';
            width: 16px;
            height: 16px;
            border: 2px solid currentColor;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 8px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .yprint-share-menu {
            position: relative;
            display: inline-block;
        }

        .yprint-share-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 10px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
            padding: 10px;
            min-width: 200px;
            z-index: 1000;
            display: none;
        }

        .yprint-share-dropdown.show {
            display: block;
        }

        .yprint-share-option {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            color: #333;
            transition: background 0.2s ease;
        }

        .yprint-share-option:hover {
            background: #f8f9fa;
        }

        .yprint-share-option i {
            width: 20px;
            font-size: 18px;
        }

        @media (max-width: 768px) {
            .yprint-order-actions {
                flex-direction: column;
                gap: 8px;
            }

            .yprint-order-action-btn {
                width: 100%;
            }
        }
        </style>

        <div id="<?php echo esc_attr($unique_id); ?>" class="<?php echo esc_attr($css_class); ?>">
            <a href="<?php echo esc_url(home_url('/orders')); ?>" class="yprint-order-action-btn">
                <i class="fas fa-arrow-left"></i>
                <span><?php _e('Bestellungen', 'yprint-plugin'); ?></span>
            </a>

            <div class="yprint-share-menu">
                <button class="yprint-order-action-btn yprint-share-trigger"
                        data-design-name="<?php echo esc_attr($design_name); ?>"
                        data-design-image="<?php echo esc_attr($design_image); ?>"
                        data-product-url="<?php echo esc_attr($product_url); ?>">
                    <i class="fas fa-share"></i>
                    <span><?php _e('Teilen', 'yprint-plugin'); ?></span>
                </button>

                <div class="yprint-share-dropdown">
                    <a href="#" class="yprint-share-option" data-platform="whatsapp">
                        <i class="fab fa-whatsapp" style="color: #25D366;"></i>
                        <span>WhatsApp</span>
                    </a>
                    <a href="#" class="yprint-share-option" data-platform="facebook">
                        <i class="fab fa-facebook" style="color: #1877F2;"></i>
                        <span>Facebook</span>
                    </a>
                    <a href="#" class="yprint-share-option" data-platform="twitter">
                        <i class="fab fa-twitter" style="color: #1DA1F2;"></i>
                        <span>Twitter</span>
                    </a>
                    <a href="#" class="yprint-share-option" data-platform="instagram">
                        <i class="fab fa-instagram" style="color: #E4405F;"></i>
                        <span>Instagram</span>
                    </a>
                    <a href="#" class="yprint-share-option" data-platform="telegram">
                        <i class="fab fa-telegram" style="color: #0088CC;"></i>
                        <span>Telegram</span>
                    </a>
                    <a href="#" class="yprint-share-option" data-platform="copy">
                        <i class="fas fa-copy" style="color: #666;"></i>
                        <span>Link kopieren</span>
                    </a>
                </div>
            </div>

            <a href="https://de.trustpilot.com/evaluate/yprint.de" target="_blank" rel="noopener" class="yprint-order-action-btn">
                <i class="fas fa-star"></i>
                <span><?php _e('Feedback', 'yprint-plugin'); ?></span>
            </a>

            <button class="yprint-order-action-btn primary yprint-reorder-btn"
                    data-order-id="<?php echo esc_attr($order_id); ?>"
                    data-item-id="<?php echo esc_attr($item_id); ?>"
                    data-design-id="<?php echo esc_attr($design_id); ?>">
                <i class="fas fa-redo"></i>
                <span><?php _e('Erneut bestellen', 'yprint-plugin'); ?></span>
            </button>
        </div>

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) return;

            // Share Menu Toggle
            const shareButton = container.querySelector('.yprint-share-trigger');
            const shareDropdown = container.querySelector('.yprint-share-dropdown');

            if (shareButton && shareDropdown) {
                shareButton.addEventListener('click', (e) => {
                    e.preventDefault();
                    shareDropdown.classList.toggle('show');
                });

                document.addEventListener('click', (e) => {
                    if (!container.contains(e.target)) {
                        shareDropdown.classList.remove('show');
                    }
                });
            }

            // Share Options
            const shareOptions = container.querySelectorAll('.yprint-share-option');
            shareOptions.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.preventDefault();
                    const { platform } = option.dataset;
                    const designName = shareButton.dataset.designName || 'Mein YPrint Design';
                    const designImage = shareButton.dataset.designImage || '';
                    const productUrl = shareButton.dataset.productUrl || 'https://yprint.de';
                    const shareTitle = '<?php echo esc_js($share_title); ?>';
                    const shareText = '<?php echo esc_js($share_text); ?>';
                    const fullShareText = `<span class="math-inline">\{shareTitle\} "</span>{designName}" - ${shareText}`;

                    handleShare(platform, fullShareText, productUrl, designImage);
                    shareDropdown.classList.remove('show');
                });
            });

            const handleShare = (platform, text, url, image) => {
                const encodedText = encodeURIComponent(text);
                const encodedUrl = encodeURIComponent(url);
                let shareUrl = '';

                switch (platform) {
                    case 'whatsapp':
                        shareUrl = `https://wa.me/?text=<span class="math-inline">\{encodedText\}%20</span>{encodedUrl}`;
                        break;
                    case 'facebook':
                        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=<span class="math-inline">\{encodedUrl\}&quote\=</span>{encodedText}`;
                        break;
                    case 'twitter':
                        shareUrl = `https://twitter.com/intent/tweet?text=<span class="math-inline">\{encodedText\}&url\=</span>{encodedUrl}`;
                        break;
                    case 'instagram':
                        navigator.clipboard.writeText(`${text} ${url}`).then(() => {
                            alert('<?php echo esc_js($copy_insta_success); ?>');
                        });
                        return;
                    case 'telegram':
                        shareUrl = `https://t.me/share/url?url=<span class="math-inline">\{encodedUrl\}&text\=</span>{encodedText}`;
                        break;
                    case 'copy':
                        navigator.clipboard.writeText(`${text} ${url}`).then(() => {
                            alert('<?php echo esc_js($copy_link_success); ?>');
                        });
                        return;
                }

                if (shareUrl) {
                    window.open(shareUrl, '_blank', 'width=600,height=400');
                }
            };

            // Reorder Button
            const reorderBtn = container.querySelector('.yprint-reorder-btn');
            if (reorderBtn) {
                reorderBtn.addEventListener('click', (e) => {
                    const { orderId, itemId, designId } = e.currentTarget.dataset;
                    handleReorder(orderId, itemId, designId, e.currentTarget);
                });
            }

            const handleReorder = (orderId, itemId, designId, button) => {
                button.classList.add('loading');
                button.disabled = true;
                const originalText = button.querySelector('span').textContent;
                button.querySelector('span').textContent = '<?php echo esc_js($adding_to_cart_text); ?>';

                jQuery.ajax({
                    url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
                    type: 'POST',
                    data: {
                        action: 'yprint_reorder_item',
                        order_id: orderId,
                        item_id: itemId,
                        design_id: designId,
                        nonce: '<?php echo wp_create_nonce('yprint_order_actions_nonce'); ?>'
                    }
                })
                .done(response => {
                    if (response.success) {
                        button.querySelector('span').textContent = '<?php echo esc_js($added_to_cart_text); ?>';
                        setTimeout(() => {
                            window.location.href = 'https://yprint.de/checkout';
                        }, 1000);
                    } else {
                        alert(response.data || '<?php echo esc_js($error_adding_text); ?>');
                        button.querySelector('span').textContent = originalText;
                        button.classList.remove('loading');
                        button.disabled = false;
                    }
                })
                .fail(() => {
                    alert('<?php echo esc_js($error_adding_text); ?>');
                    button.querySelector('span').textContent = originalText;
                    button.classList.remove('loading');
                    button.disabled = false;
                });
            };
        });
        </script>

        <?php
        return ob_get_clean();
    }

    /**
     * Get the latest order for a specific user
     *
     * @param
     * * @param int $user_id User ID
     * @return WC_Order|false Latest order or false if none found
     */
    private static function get_latest_user_order($user_id) {
        $orders = wc_get_orders(array(
            'customer' => $user_id,
            'limit' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'status' => array_keys(wc_get_order_statuses()),
            'type' => 'shop_order'
        ));

        return !empty($orders) ? $orders[0] : false;
    }

    /**
     * Handle reorder AJAX request
     */
    public static function handle_reorder() {
        check_ajax_referer('yprint_order_actions_nonce', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;

        if (!$order_id || !$item_id) {
            wp_send_json_error('Ungültige Parameter');
            return;
        }

        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce ist nicht aktiv');
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Bestellung nicht gefunden');
            return;
        }

        $current_user_id = get_current_user_id();
        if (!$current_user_id || $order->get_customer_id() !== $current_user_id) {
            wp_send_json_error('Keine Berechtigung für diese Bestellung');
            return;
        }

        $order_item = null;
        foreach ($order->get_items() as $item) {
            if ($item->get_id() === $item_id) {
                $order_item = $item;
                break;
            }
        }

        if (!$order_item) {
            wp_send_json_error('Artikel nicht gefunden');
            return;
        }

        $product = $order_item->get_product();
        if (!$product) {
            wp_send_json_error('Produkt nicht mehr verfügbar');
            return;
        }

        try {
            $cart_item_data = array();
            $design_data = $order_item->get_meta('print_design');

            if (!empty($design_data) && $design_id) {
                $cart_item_data['print_design'] = $design_data;
                $cart_item_data['_is_design_product'] = true;
                $cart_item_data['unique_design_key'] = md5(wp_json_encode($design_data));
            }

            $cart_item_key = WC()->cart->add_to_cart(
                $product->get_id(),
                $order_item->get_quantity(),
                $product->is_type('variation') ? $product->get_id() : 0,
                $product->is_type('variation') ? $product->get_attributes() : array(),
                $cart_item_data
            );

            if ($cart_item_key) {
                wp_send_json_success(array(
                    'message' => 'Artikel wurde zum Warenkorb hinzugefügt',
                    'cart_item_key' => $cart_item_key
                ));
            } else {
                wp_send_json_error('Artikel konnte nicht zum Warenkorb hinzugefügt werden');
            }

        } catch (Exception $e) {
            wp_send_json_error('Fehler beim Hinzufügen zum Warenkorb: ' . $e->getMessage());
        }
    }
}

// Initialize the class
YPrint_Order_Actions_Modern::init();