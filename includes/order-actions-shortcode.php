<?php
/**
 * Modern & Efficient Order Actions Shortcode for YPrint (Screenshot Style - Final)
 * Creates action buttons for the last order with reorder, feedback, share.
 * Optimized for mobile sharing.
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle order action buttons (Screenshot Style - Final)
 */
class YPrint_Order_Actions_Screenshot_Final {

    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode('yprint_last_order_actions', array(__CLASS__, 'render_order_actions'));
        add_action('wp_ajax_yprint_reorder_item', array(__CLASS__, 'handle_reorder'));
        add_action('wp_ajax_nopriv_yprint_reorder_item', array(__CLASS__, 'handle_reorder'));
    }

    /**
     * Render order actions shortcode (Screenshot Style - Final)
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public static function render_order_actions($atts) {
        $atts = shortcode_atts(array(
            'class' => 'yprint-last-order-actions'
        ), $atts, 'yprint_last_order_actions');

        $current_user_id = get_current_user_id();
        if (!$current_user_id) {
            return '<p class="yprint-order-actions-login-required">' .
                   __('Bitte melde dich an, um deine letzte Bestellung zu sehen.', 'yprint-plugin') .
                   '</p>';
        }

        if (!class_exists('WooCommerce')) {
            return '<p class="error-message">' . __('WooCommerce ist nicht aktiviert.', 'yprint-plugin') . '</p>';
        }

        $latest_order = self::get_latest_user_order($current_user_id);
        if (!$latest_order) {
            return '<p class="yprint-no-orders">' .
                   __('Du hast noch keine Bestellungen.', 'yprint-plugin') .
                   '</p>';
        }

        $order_items = $latest_order->get_items();
        if (empty($order_items)) {
            return '<p class="error-message">' . __('Keine Artikel in der letzten Bestellung gefunden.', 'yprint-plugin') . '</p>';
        }

        $latest_item = reset($order_items);
        $order_id = $latest_order->get_id();
        $product = $latest_item->get_product();

        $design_data = $latest_item->get_meta('print_design');
        $design_image = $design_data['preview_url'] ?? ($product ? wp_get_attachment_image_url($product->get_image_id(), 'thumbnail') : '');
        $order_status = wc_get_order_status_name($latest_order->get_status());
        $order_number = $latest_order->get_order_number();
        $product_url = $product ? get_permalink($product->get_id()) : '';
        $design_name = $design_data['name'] ?? ($product ? $product->get_name() : $latest_item->get_name());

        $css_class = sanitize_html_class($atts['class']);
        $unique_id = 'yprint-last-order-actions-' . uniqid();

        $adding_to_cart_text = __('Wird neu bestellt...', 'yprint-plugin');
        $added_to_cart_text = __('Zum Warenkorb hinzugefügt', 'yprint-plugin');
        $error_adding_text = __('Fehler beim erneuten Bestellen', 'yprint-plugin');

        ob_start();
        ?>

        <style>
        .yprint-last-order-actions {
            background-color: #f9f9f9; /* Example background */
            padding: 15px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 10px;
            border: 1px solid #eee; /* Example border */
        }

        .yprint-last-order-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .yprint-last-order-image-container {
            width: 60px;
            height: 60px;
            border-radius: 6px;
            overflow: hidden;
            background-color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            border: 1px solid #ddd;
        }

        .yprint-last-order-image {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }

        .yprint-last-order-details {
            flex-grow: 1;
        }

        .yprint-last-order-status {
            font-weight: bold;
            color: #28a745; /* Example color for "Delivered" */
            margin-bottom: 5px;
        }

        .yprint-last-order-number {
            color: #6c757d;
            font-size: 0.9em;
        }

        .yprint-last-order-actions-buttons {
            display: flex;
            gap: 20px;
            margin-top: 15px;
            justify-content: flex-start; /* Align buttons to the left */
        }

        .yprint-last-order-action-btn {
            display: flex;
            align-items: center;
            gap: 5px;
            color: #007bff; /* Example button text color */
            text-decoration: none;
            cursor: pointer;
            font-size: 0.95em;
        }

        .yprint-last-order-action-btn i {
            font-size: 1.1em;
        }

        .yprint-last-order-action-btn.reorder i {
            /* Style for reorder icon */
        }

        .yprint-last-order-action-btn.feedback i {
            /* Style for feedback icon */
        }

        .yprint-last-order-action-btn.share i {
            /* Style for share icon */
        }

        .yprint-last-order-arrow {
            font-size: 1.5em;
            color: #6c757d;
        }

        .yprint-last-order-action-btn.loading {
            opacity: 0.7;
            pointer-events: none;
        }

        .yprint-last-order-action-btn.loading::after {
            content: '';
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-top: 2px solid transparent;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-left: 5px;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 600px) {
            .yprint-last-order-actions-buttons {
                gap: 10px;
            }
            .yprint-last-order-action-btn span {
                display: none; /* Hide text on smaller screens */
            }
        }
        </style>

        <div id="<?php echo esc_attr($unique_id); ?>" class="<?php echo esc_attr($css_class); ?>">
            <div class="yprint-last-order-header">
                <?php if ($design_image) : ?>
                    <div class="yprint-last-order-image-container">
                        <img src="<?php echo esc_url($design_image); ?>" alt="<?php echo esc_attr($design_name); ?>" class="yprint-last-order-image">
                    </div>
                <?php endif; ?>
                <div class="yprint-last-order-details">
                    <div class="yprint-last-order-status"><?php echo esc_html($order_status); ?></div>
                    <div class="yprint-last-order-number"><?php echo esc_html('#' . $order_number); ?></div>
                </div>
                <span class="yprint-last-order-arrow">&rarr;</span>
            </div>
            <div class="yprint-last-order-actions-buttons">
                <button class="yprint-last-order-action-btn reorder yprint-reorder-btn"
                        data-order-id="<?php echo esc_attr($order_id); ?>"
                        data-item-id="<?php echo esc_attr($item_id); ?>"
                        data-design-id="<?php echo esc_attr($design_data['design_id'] ?? ''); ?>">
                    <i class="fas fa-sync"></i>
                    <span><?php _e('Reorder', 'yprint-plugin'); ?></span>
                </button>
                <a href="https://de.trustpilot.com/evaluate/yprint.de" target="_blank" rel="noopener" class="yprint-last-order-action-btn feedback">
                    <i class="far fa-comment-dots"></i>
                    <span><?php _e('Feedback', 'yprint-plugin'); ?></span>
                </a>
                <?php if (wp_is_mobile()) : ?>
                    <a href="whatsapp://send?text=<?php echo rawurlencode(__('Schau dir mein Design an!', 'yprint-plugin') . ' "' . $design_name . '" - ' . __('Individuelles Design erstellt', 'yprint-plugin') . ' ' . $product_url); ?>"
                       data-action="share/whatsapp/share"
                       class="yprint-last-order-action-btn share">
                        <i class="fab fa-whatsapp" style="color: #25D366;"></i>
                        <span><?php _e('Share', 'yprint-plugin'); ?></span>
                    </a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode($product_url); ?>&quote=<?php echo rawurlencode(__('Schau dir mein Design an!', 'yprint-plugin') . ' "' . $design_name . '" - ' . __('Individuelles Design erstellt', 'yprint-plugin')); ?>"
                       target="_blank"
                       class="yprint-last-order-action-btn share">
                        <i class="fab fa-facebook" style="color: #1877F2;"></i>
                        <span><?php _e('Share', 'yprint-plugin'); ?></span>
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=<?php echo rawurlencode(__('Schau dir mein Design an!', 'yprint-plugin') . ' "' . $design_name . '" - ' . __('Individuelles Design erstellt', 'yprint-plugin') . ' ' . $product_url); ?>"
                       target="_blank"
                       class="yprint-last-order-action-btn share">
                        <i class="fab fa-twitter" style="color: #1DA1F2;"></i>
                        <span><?php _e('Share', 'yprint-plugin'); ?></span>
                    </a>
                    <a href="https://instagram.com/?utm_source=qr&r=nametag" target="_blank" class="yprint-last-order-action-btn share">
                        <i class="fab fa-instagram" style="color: #E4405F;"></i>
                        <span><?php _e('Instagram', 'yprint-plugin'); ?></span>
                    </a>
                    <a href="https://t.me/share/url?url=<?php echo rawurlencode($product_url); ?>&text=<?php echo rawurlencode(__('Schau dir mein Design an!', 'yprint-plugin') . ' "' . $design_name . '" - ' . __('Individuelles Design erstellt', 'yprint-plugin')); ?>"
                       target="_blank"
                       class="yprint-last-order-action-btn share">
                        <i class="fab fa-telegram" style="color: #0088CC;"></i>
                        <span><?php _e('Telegram', 'yprint-plugin'); ?></span>
                    </a>
                    <button class="yprint-last-order-action-btn share yprint-share-trigger-mobile">
                        <i class="fas fa-share-alt"></i>
                        <span><?php _e('Teilen', 'yprint-plugin'); ?></span>
                    </button>
                <?php else : ?>
                    <button class="yprint-last-order-action-btn share yprint-share-trigger-desktop">
                        <i class="fas fa-share-alt"></i>
                        <span><?php _e('Teilen', 'yprint-plugin'); ?></span>
                    </button>
                    <div class="yprint-share-dropdown-desktop">
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
                        <a href="#" class="yprint-share-option" data-platform="telegram">
                            <i class="fab fa-telegram" style="color: #0088CC;"></i>
                            <span>Telegram</span>
                        </a>
                        <a href="#" class="yprint-share-option" data-platform="copy">
                            <i class="fas fa-copy" style="color: #666;"></i>
                            <span>Link kopieren</span>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) return;

            // Desktop Share Menu Toggle
            const shareButtonDesktop = container.querySelector('.yprint-share-trigger-desktop');
            const shareDropdownDesktop = container.querySelector('.yprint-share-dropdown-desktop');

            if (shareButtonDesktop && shareDropdownDesktop) {
                shareButtonDesktop.addEventListener('click', (e) => {
                    e.preventDefault();
                    shareDropdownDesktop.classList.toggle('show');
                });

                document.addEventListener('click', (e) => {
                    if (!container.contains(e.target)) {
                        shareDropdownDesktop.classList.remove('show');
                    }
                });
            }

            const handleShare = (platform, text, url, image) => {
                const encodedText = encodeURIComponent(text);
                const encodedUrl = encodeURIComponent(url);
                let shareUrl = '';

                switch (platform) {
                    case 'whatsapp':
                        shareUrl = `https://wa.me/?text=${encodedText}`;
                        break;
                    case 'facebook':
                        shareUrl = `https://www.facebook.com/sharer/sharer.php?u=<span class="math-inline">\{encodedUrl\}&quote\=</span>{encodedText}`;
                        break;
                    case 'twitter':
                        shareUrl = `https://twitter.com/intent/tweet?text=<span class="math-inline">\{encodedText\}&url\=</span>{encodedUrl}`;
                        break;
                    case 'telegram':
                        shareUrl = `https://t.me/share/url?url=<span class="math-inline">\{encodedUrl\}&text\=</span>{encodedText}`;
                        break;
                    case 'copy':
                        navigator.clipboard.writeText(`${text} ${url}`).then(() => {
                            alert('Link kopiert!');
                        });
                        return;
                }

                if (shareUrl) {
                    window.open(shareUrl, '_blank', 'width=600,height=400');
                }
            };

            // Desktop Share Options
            const shareOptionsDesktop = container.querySelectorAll('.yprint-share-dropdown-desktop .yprint-share-option');
            shareOptionsDesktop.forEach(option => {
                option.addEventListener('click', (e) => {
                    e.preventDefault();
                    const { platform } = option.dataset;
                    const designName = shareButtonDesktop.dataset.designName || 'Mein Design';
                    const designImage = shareButtonDesktop.dataset.designImage || '';
                    const productUrl = shareButtonDesktop.dataset.productUrl || window.location.href;
                    const shareTitle = '<?php echo esc_js(__('Schau dir mein Design an!', 'yprint-plugin')); ?>';
                    const shareText = '<?php echo esc_js(__('Individuelles Design erstellt', 'yprint-plugin')); ?>';
                    const fullShareText = `<span class="math-inline">\{shareTitle\}\: "</span>{designName}" - ${shareText} ${productUrl}`;

                    handleShare(platform, fullShareText, productUrl, designImage);
                    shareDropdownDesktop
                    .classList.remove('show');
                });
            });

            // Mobile Share Trigger (uses native sharing if available)
            const shareButtonMobile = container.querySelector('.yprint-share-trigger-mobile');
            if (shareButtonMobile && navigator.share) {
                shareButtonMobile.addEventListener('click', async (e) => {
                    e.preventDefault();
                    const shareTitleMobile = '<?php echo esc_js(__('Schau dir mein Design an!', 'yprint-plugin')); ?>';
                    const shareTextMobile = '<?php echo esc_js(__('Individuelles Design erstellt', 'yprint-plugin')); ?>';
                    const designNameMobile = shareButtonMobile.dataset.designName || 'Mein Design';
                    const productUrlMobile = shareButtonMobile.dataset.productUrl || window.location.href;

                    try {
                        await navigator.share({
                            title: shareTitleMobile,
                            text: `${shareTitleMobile}: "${designNameMobile}" - ${shareTextMobile}`,
                            url: productUrlMobile,
                        });
                        console.log('Successfully shared');
                    } catch (error) {
                        console.error('Error sharing', error);
                        // Fallback for browsers that don't support native share
                        alert('Das Teilen wird von deinem Browser nicht unterstützt. Der Link wurde in deine Zwischenablage kopiert.');
                        navigator.clipboard.writeText(`${shareTitleMobile}: "${designNameMobile}" - ${shareTextMobile} ${productUrlMobile}`);
                    }
                });
            } else if (shareButtonMobile) {
                shareButtonMobile.style.display = 'none'; // Hide if native share is not available and no desktop fallback
            }

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
                            window.location.href = '<?php echo esc_url(wc_get_checkout_url()); ?>';
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
     * @param int $user_id User ID
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
YPrint_Order_Actions_Screenshot_Final::init();