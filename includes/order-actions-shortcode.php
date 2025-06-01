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
    background-color: #ffffff;
    padding: 24px;
    border-radius: 16px;
    display: flex;
    flex-direction: column;
    gap: 20px;
    border: 1px solid #DFDFDF;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
}

.yprint-last-order-header {
    display: flex;
    align-items: center;
    gap: 16px;
}

.yprint-last-order-image-container {
    width: 64px;
    height: 64px;
    border-radius: 12px;
    overflow: hidden;
    background-color: #f9fafb;
    display: flex;
    justify-content: center;
    align-items: center;
    border: none;
    flex-shrink: 0;
}

.yprint-last-order-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.yprint-last-order-details {
    flex-grow: 1;
    min-width: 0;
}

.yprint-last-order-label {
    font-weight: 500;
    font-size: 12px;
    color: #0079FF;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 2px;
    line-height: 1.2;
}

.yprint-last-order-status {
    font-weight: 600;
    font-size: 16px;
    color: #111827;
    margin-bottom: 4px;
    line-height: 1.3;
}

.yprint-last-order-number {
    color: #6b7280;
    font-size: 14px;
    font-weight: 400;
}

.yprint-last-order-actions-buttons {
    display: flex;
    gap: 8px;
    margin-top: 4px;
    justify-content: flex-start;
    flex-wrap: nowrap;
    overflow: hidden;
}

.yprint-last-order-action-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #6b7280;
    text-decoration: none;
    cursor: pointer;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.2s ease;
    background: none;
    border: none;
    padding: 8px 10px;
    border-radius: 8px;
    position: relative;
    white-space: nowrap;
    min-width: 40px;
    justify-content: center;
}

.yprint-last-order-action-btn:hover {
    color: #374151;
    background-color: #f3f4f6;
    transform: translateY(-1px);
}

.yprint-last-order-action-btn:active {
    transform: translateY(0);
    background-color: #e5e7eb;
}

.yprint-last-order-action-btn i {
    font-size: 16px;
    color: inherit;
    transition: all 0.2s ease;
}

.yprint-last-order-action-btn:hover i {
    transform: scale(1.1);
}

.yprint-last-order-arrow {
    font-size: 20px;
    color: #d1d5db;
    flex-shrink: 0;
    text-decoration: none;
    cursor: pointer;
    transition: all 0.2s ease;
    padding: 8px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.yprint-last-order-arrow:hover {
    color: #6b7280;
    background-color: #f3f4f6;
    transform: translateX(2px);
}

.yprint-last-order-arrow:active {
    transform: translateX(0);
    background-color: #e5e7eb;
}

.yprint-last-order-action-btn.loading {
    opacity: 0.6;
    pointer-events: none;
    transform: none !important;
    background-color: transparent !important;
}

.yprint-last-order-action-btn.loading:hover {
    transform: none !important;
    background-color: transparent !important;
}

.yprint-last-order-action-btn.loading::after {
    content: '';
    width: 12px;
    height: 12px;
    border: 2px solid #d1d5db;
    border-top: 2px solid #6b7280;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 6px;
}

.yprint-last-order-action-btn.loading i {
    transform: none !important;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Share dropdown for desktop */
.yprint-share-dropdown-desktop {
    position: absolute;
    top: 100%;
    right: 0;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    padding: 8px;
    display: none;
    z-index: 1000;
    min-width: 160px;
}

@media (max-width: 480px) {
    .yprint-share-dropdown-desktop {
        right: 0;
        left: auto;
        min-width: 140px;
    }
}

.yprint-share-dropdown-desktop.show {
    display: block;
}

.yprint-share-option {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 12px;
    color: #374151;
    text-decoration: none;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s ease;
}

.yprint-share-option:hover {
    background-color: #f3f4f6;
    color: #111827;
    transform: translateX(2px);
}

.yprint-share-option:active {
    background-color: #e5e7eb;
    transform: translateX(0);
}

.yprint-share-option i {
    font-size: 16px;
    width: 16px;
    text-align: center;
    transition: all 0.2s ease;
}

.yprint-share-option:hover i {
    transform: scale(1.1);
}

/* Position relative for share button container */
.yprint-last-order-action-btn.share {
    position: relative;
}

.yprint-last-order-action-btn.share:hover {
    color: #374151;
    background-color: #f3f4f6;
    transform: translateY(-1px);
}

.yprint-last-order-action-btn.share.show {
    background-color: #e5e7eb;
    color: #374151;
}

/* Medium screens - hide text when space gets tight */
@media (max-width: 480px) {
    .yprint-last-order-action-btn span {
        display: none;
    }
    
    .yprint-last-order-action-btn {
        padding: 8px;
        min-width: 40px;
        gap: 0;
    }
    
    .yprint-last-order-actions-buttons {
        gap: 6px;
    }
}

/* Container queries alternative for very tight spaces */
@media (max-width: 400px) {
    .yprint-last-order-actions {
        padding: 16px;
    }
    
    .yprint-last-order-actions-buttons {
        gap: 4px;
    }
    
    .yprint-last-order-action-btn {
        padding: 6px;
        min-width: 36px;
    }
    
    .yprint-last-order-action-btn i {
        font-size: 14px;
    }
}

/* Regular mobile adjustments */
@media (max-width: 600px) {
    .yprint-last-order-actions {
        padding: 20px;
        gap: 16px;
    }
    
    .yprint-last-order-header {
        gap: 12px;
    }
    
    .yprint-last-order-image-container {
        width: 56px;
        height: 56px;
    }
}

/* Show text on larger screens */
@media (min-width: 481px) {
    .yprint-last-order-action-btn span {
        display: inline;
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
    <div class="yprint-last-order-label"><?php _e('Letzte Bestellung', 'yprint-plugin'); ?></div>
    <div class="yprint-last-order-status"><?php echo esc_html($order_status); ?></div>
    <div class="yprint-last-order-number"><?php echo esc_html('#' . $order_number); ?></div>
</div>
                <a href="<?php echo esc_url(home_url('/orders')); ?>" class="yprint-last-order-arrow" title="Alle Bestellungen anzeigen">&rarr;</a>
            </div>
            <div class="yprint-last-order-actions-buttons">
            <button class="yprint-last-order-action-btn reorder yprint-reorder-btn"
        data-order-id="<?php echo esc_attr($order_id); ?>"
        data-item-id="<?php echo esc_attr($latest_item->get_id()); ?>"
        data-design-id="<?php echo esc_attr($design_data['design_id'] ?? ''); ?>">
    <i class="fas fa-redo-alt"></i>
    <span><?php _e('Reorder', 'yprint-plugin'); ?></span>
</button>
                <a href="https://de.trustpilot.com/evaluate/yprint.de" target="_blank" rel="noopener" class="yprint-last-order-action-btn feedback">
                    <i class="far fa-comment-dots"></i>
                    <span><?php _e('Feedback', 'yprint-plugin'); ?></span>
                </a>
                <button class="yprint-last-order-action-btn share yprint-share-trigger"
        data-design-name="<?php echo esc_attr($design_name); ?>"
        data-design-image="<?php echo esc_attr($design_image); ?>"
        data-product-url="<?php echo esc_attr($product_url); ?>">
    <i class="fas fa-share-alt"></i>
    <span><?php _e('Share', 'yprint-plugin'); ?></span>
    <div class="yprint-share-dropdown-desktop">
        <a href="#" class="yprint-share-option" data-platform="whatsapp">
            <i class="fab fa-whatsapp"></i>
            <span>WhatsApp</span>
        </a>
        <a href="#" class="yprint-share-option" data-platform="facebook">
            <i class="fab fa-facebook"></i>
            <span>Facebook</span>
        </a>
        <a href="#" class="yprint-share-option" data-platform="twitter">
            <i class="fab fa-twitter"></i>
            <span>Twitter</span>
        </a>
        <a href="#" class="yprint-share-option" data-platform="telegram">
            <i class="fab fa-telegram"></i>
            <span>Telegram</span>
        </a>
        <a href="#" class="yprint-share-option" data-platform="copy">
            <i class="fas fa-copy"></i>
            <span>Link kopieren</span>
        </a>
    </div>
</button>
            </div>
        </div>

        <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', () => {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) return;

            // Unified Share Functionality
const shareButton = container.querySelector('.yprint-share-trigger');
const shareDropdown = container.querySelector('.yprint-share-dropdown-desktop');

if (shareButton) {
    shareButton.addEventListener('click', async (e) => {
        e.preventDefault();
        
        const designName = shareButton.dataset.designName || 'Mein Design';
        const designImage = shareButton.dataset.designImage || '';
        const productUrl = shareButton.dataset.productUrl || window.location.href;
        const shareTitle = '<?php echo esc_js(__('Schau dir mein Design an!', 'yprint-plugin')); ?>';
        const shareText = '<?php echo esc_js(__('Individuelles Design erstellt', 'yprint-plugin')); ?>';
        
        // Try native share first (mobile)
        if (navigator.share && window.matchMedia('(max-width: 600px)').matches) {
            try {
                await navigator.share({
                    title: shareTitle,
                    text: `${shareTitle}: "${designName}" - ${shareText}`,
                    url: productUrl,
                });
                return;
            } catch (error) {
                console.log('Native share failed, falling back to dropdown');
            }
        }
        
        // Desktop/fallback: toggle dropdown
if (shareDropdown) {
    shareDropdown.classList.toggle('show');
    shareButton.classList.toggle('show');
}
    });

    // Close dropdown when clicking outside
document.addEventListener('click', (e) => {
    if (!shareButton.contains(e.target) && shareDropdown) {
        shareDropdown.classList.remove('show');
        shareButton.classList.remove('show');
    }
});
}

const handleShare = (platform, text, url) => {
    const encodedText = encodeURIComponent(text);
    const encodedUrl = encodeURIComponent(url);
    let shareUrl = '';

    switch (platform) {
        case 'whatsapp':
            shareUrl = `https://wa.me/?text=${encodedText}%20${encodedUrl}`;
            break;
        case 'facebook':
            shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}&quote=${encodedText}`;
            break;
        case 'twitter':
            shareUrl = `https://twitter.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`;
            break;
        case 'telegram':
            shareUrl = `https://t.me/share/url?url=${encodedUrl}&text=${encodedText}`;
            break;
        case 'copy':
            navigator.clipboard.writeText(`${text} ${url}`).then(() => {
                // Create a temporary notification
                const notification = document.createElement('div');
                notification.textContent = 'Link kopiert!';
                notification.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: #111827;
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    z-index: 10000;
                    font-size: 14px;
                `;
                document.body.appendChild(notification);
                setTimeout(() => notification.remove(), 2000);
            });
            return;
    }

    if (shareUrl) {
        window.open(shareUrl, '_blank', 'width=600,height=400');
    }
};

// Share Options
const shareOptions = container.querySelectorAll('.yprint-share-option');
shareOptions.forEach(option => {
    option.addEventListener('click', (e) => {
        e.preventDefault();
        const { platform } = option.dataset;
        const designName = shareButton.dataset.designName || 'Mein Design';
        const productUrl = shareButton.dataset.productUrl || window.location.href;
        const shareTitle = '<?php echo esc_js(__('Schau dir mein Design an!', 'yprint-plugin')); ?>';
        const shareText = '<?php echo esc_js(__('Individuelles Design erstellt', 'yprint-plugin')); ?>';
        const fullShareText = `${shareTitle}: "${designName}" - ${shareText}`;

        handleShare(platform, fullShareText, productUrl);
if (shareDropdown) {
    shareDropdown.classList.remove('show');
    shareButton.classList.remove('show');
}
    });
});

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

        // Dynamic layout adjustment based on available space
const adjustButtonLayout = () => {
    const buttonsContainer = container.querySelector('.yprint-last-order-actions-buttons');
    const buttons = container.querySelectorAll('.yprint-last-order-action-btn');
    
    if (!buttonsContainer || buttons.length === 0) return;
    
    const containerWidth = buttonsContainer.offsetWidth;
    const buttonCount = buttons.length;
    
    // Calculate if we need to hide text (rough calculation)
    const minWidthPerButton = 120; // Width needed for icon + text
    const totalNeededWidth = buttonCount * minWidthPerButton;
    
    buttons.forEach(button => {
        const span = button.querySelector('span');
        if (span) {
            if (containerWidth < totalNeededWidth) {
                span.style.display = 'none';
                button.style.padding = '8px';
                button.style.minWidth = '40px';
            } else {
                span.style.display = 'inline';
                button.style.padding = '8px 10px';
                button.style.minWidth = 'auto';
            }
        }
    });
};

// Call on load and resize
adjustButtonLayout();
window.addEventListener('resize', adjustButtonLayout);
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