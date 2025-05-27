<?php
/**
 * Order Actions Shortcode for YPrint
 * Creates action buttons for orders with social sharing, reorder, feedback, etc.
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class to handle order action buttons
 */
class YPrint_Order_Actions {
    
    /**
     * Initialize the class
     */
    public static function init() {
        add_shortcode('yprint_order_actions', array(__CLASS__, 'render_order_actions'));
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue_scripts'));
        add_action('wp_ajax_yprint_reorder_item', array(__CLASS__, 'handle_reorder'));
        add_action('wp_ajax_nopriv_yprint_reorder_item', array(__CLASS__, 'handle_reorder'));
    }
    
    /**
     * Enqueue necessary scripts and styles
     */
    public static function enqueue_scripts() {
        wp_enqueue_script('yprint-order-actions', YPRINT_PLUGIN_URL . 'assets/js/yprint-order-actions.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
        
        wp_localize_script('yprint-order-actions', 'yprint_order_actions_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yprint_order_actions_nonce'),
            'messages' => array(
                'adding_to_cart' => __('Artikel wird hinzugefügt...', 'yprint-plugin'),
                'added_to_cart' => __('Artikel wurde zum Warenkorb hinzugefügt', 'yprint-plugin'),
                'error_adding' => __('Fehler beim Hinzufügen zum Warenkorb', 'yprint-plugin'),
                'share_title' => __('Schau dir mein Design bei YPrint an!', 'yprint-plugin'),
                'share_text' => __('Individuelles Streetwear Design bei YPrint erstellt', 'yprint-plugin')
            )
        ));
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
    
    // Check if user is logged in
    $current_user_id = get_current_user_id();
    if ($current_user_id === 0) {
        return '<p class="yprint-order-actions-login-required">' . 
               __('Bitte melde dich an, um deine Bestellaktionen zu sehen.', 'yprint-plugin') . 
               ' <a href="' . esc_url(wp_login_url(get_permalink())) . '">' . 
               __('Jetzt anmelden', 'yprint-plugin') . '</a></p>';
    }
    
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return '<p class="error-message">' . __('WooCommerce ist nicht aktiviert.', 'yprint-plugin') . '</p>';
    }
    
    // Get latest order for current user
    $latest_order = self::get_latest_user_order($current_user_id);
    
    if (!$latest_order) {
        return '<p class="yprint-no-orders">' . 
               __('Du hast noch keine Bestellungen.', 'yprint-plugin') . 
               ' <a href="' . esc_url(home_url('/products')) . '">' . 
               __('Jetzt einkaufen', 'yprint-plugin') . '</a></p>';
    }
    
    // Get the first item from the latest order (most recent item)
    $order_items = $latest_order->get_items();
    if (empty($order_items)) {
        return '<p class="error-message">' . __('Keine Artikel in der letzten Bestellung gefunden.', 'yprint-plugin') . '</p>';
    }
    
    $latest_item = reset($order_items); // Get first item
    
    // Extract order and item data
    $order_id = $latest_order->get_id();
    $item_id = $latest_item->get_id();
    $product = $latest_item->get_product();
    
    // Get design data if available
    $design_data = $latest_item->get_meta('print_design');
    $design_id = '';
    $design_name = $product ? $product->get_name() : $latest_item->get_name();
    $design_image = '';
    $product_url = '';
    
    if (!empty($design_data)) {
        $design_id = isset($design_data['design_id']) ? $design_data['design_id'] : '';
        if (isset($design_data['name'])) {
            $design_name = $design_data['name'];
        }
        if (isset($design_data['preview_url'])) {
            $design_image = $design_data['preview_url'];
        }
    }
    
    // Fallback for product image if no design image
    if (empty($design_image) && $product) {
        $product_image_id = $product->get_image_id();
        if ($product_image_id) {
            $design_image = wp_get_attachment_image_url($product_image_id, 'medium');
        }
    }
    
    // Generate product URL
    if ($product) {
        $product_url = get_permalink($product->get_id());
    }
    
    $css_class = sanitize_html_class($atts['class']);
        
        // Generate unique ID for this instance
        $unique_id = 'yprint-order-actions-' . uniqid();
        
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
            display: flex;
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
            text-decoration: none;
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
            text-decoration: none;
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
                min-width: auto;
                width: 100%;
            }
        }
        </style>
        
        <div id="<?php echo esc_attr($unique_id); ?>" class="<?php echo esc_attr($css_class); ?>">
            <!-- Bestellungen anzeigen Button -->
            <a href="<?php echo esc_url(home_url('/orders')); ?>" class="yprint-order-action-btn">
                <i class="fas fa-arrow-left"></i>
                <span><?php _e('Bestellungen', 'yprint-plugin'); ?></span>
            </a>
            
            <!-- Share Button mit Dropdown -->
            <div class="yprint-share-menu">
                <button class="yprint-order-action-btn yprint-share-trigger" data-design-name="<?php echo esc_attr($design_name); ?>" data-design-image="<?php echo esc_attr($design_image); ?>" data-product-url="<?php echo esc_attr($product_url); ?>">
                    <i class="fas fa-share"></i>
                    <span><?php _e('Share', 'yprint-plugin'); ?></span>
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
            
            <!-- Feedback Button -->
            <a href="https://de.trustpilot.com/evaluate/yprint.de" target="_blank" rel="noopener" class="yprint-order-action-btn">
                <i class="fas fa-star"></i>
                <span><?php _e('Feedback', 'yprint-plugin'); ?></span>
            </a>
            
            <!-- Reorder Button -->
            <button class="yprint-order-action-btn primary yprint-reorder-btn" 
                    data-order-id="<?php echo esc_attr($order_id); ?>" 
                    data-item-id="<?php echo esc_attr($item_id); ?>"
                    data-design-id="<?php echo esc_attr($design_id); ?>">
                <i class="fas fa-redo"></i>
                <span><?php _e('Reorder', 'yprint-plugin'); ?></span>
            </button>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
            if (!container) return;
            
            // Share Menu Toggle
            const shareButton = container.querySelector('.yprint-share-trigger');
            const shareDropdown = container.querySelector('.yprint-share-dropdown');
            
            if (shareButton && shareDropdown) {
                shareButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    shareDropdown.classList.toggle('show');
                });
                
                // Close dropdown when clicking outside
                document.addEventListener('click', function(e) {
                    if (!container.contains(e.target)) {
                        shareDropdown.classList.remove('show');
                    }
                });
            }
            
            // Share Options
            const shareOptions = container.querySelectorAll('.yprint-share-option');
            shareOptions.forEach(option => {
                option.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const platform = this.dataset.platform;
                    const designName = shareButton.dataset.designName || 'Mein YPrint Design';
                    const designImage = shareButton.dataset.designImage || '';
                    const productUrl = shareButton.dataset.productUrl || 'https://yprint.de';
                    
                    const shareText = `${yprint_order_actions_ajax.messages.share_title} "${designName}" - ${yprint_order_actions_ajax.messages.share_text}`;
                    const shareUrl = productUrl || 'https://yprint.de';
                    
                    handleShare(platform, shareText, shareUrl, designImage);
                    shareDropdown.classList.remove('show');
                });
            });
            
            // Reorder Button
            const reorderBtn = container.querySelector('.yprint-reorder-btn');
            if (reorderBtn) {
                reorderBtn.addEventListener('click', function() {
                    const orderId = this.dataset.orderId;
                    const itemId = this.dataset.itemId;
                    const designId = this.dataset.designId;
                    
                    handleReorder(orderId, itemId, designId, this);
                });
            }
        });
        
        function handleShare(platform, text, url, image) {
            const encodedText = encodeURIComponent(text);
            const encodedUrl = encodeURIComponent(url);
            
            let shareUrl = '';
            
            switch(platform) {
                case 'whatsapp':
                    shareUrl = `https://wa.me/?text=${encodedText}%20${encodedUrl}`;
                    break;
                case 'facebook':
                    shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodedUrl}&quote=${encodedText}`;
                    break;
                case 'twitter':
                    shareUrl = `https://twitter.com/intent/tweet?text=${encodedText}&url=${encodedUrl}`;
                    break;
                case 'instagram':
                    // Instagram doesn't support direct sharing, so copy to clipboard
                    navigator.clipboard.writeText(`${text} ${url}`).then(() => {
                        alert('Text wurde kopiert! Du kannst es jetzt in Instagram einfügen.');
                    });
                    return;
                case 'telegram':
                    shareUrl = `https://t.me/share/url?url=${encodedUrl}&text=${encodedText}`;
                    break;
                case 'copy':
                    navigator.clipboard.writeText(`${text} ${url}`).then(() => {
                        alert('Link wurde kopiert!');
                    });
                    return;
            }
            
            if (shareUrl) {
                window.open(shareUrl, '_blank', 'width=600,height=400');
            }
        }

        /**
 * Get the latest order for a specific user
 * 
 * @param int $user_id User ID
 * @return WC_Order|false Latest order or false if none found
 */
private static function get_latest_user_order($user_id) {
    // Get all order statuses
    $all_statuses = array_keys(wc_get_order_statuses());
    
    // Get user's orders
    $orders = wc_get_orders(array(
        'customer' => $user_id,
        'limit' => 1,
        'orderby' => 'date',
        'order' => 'DESC',
        'status' => $all_statuses,
        'type' => 'shop_order'
    ));
    
    return !empty($orders) ? $orders[0] : false;
}
        
        function handleReorder(orderId, itemId, designId, button) {
            // Add loading state
            button.classList.add('loading');
            button.disabled = true;
            
            const originalText = button.querySelector('span').textContent;
            button.querySelector('span').textContent = yprint_order_actions_ajax.messages.adding_to_cart;
            
            jQuery.ajax({
                url: yprint_order_actions_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_reorder_item',
                    order_id: orderId,
                    item_id: itemId,
                    design_id: designId,
                    nonce: yprint_order_actions_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        button.querySelector('span').textContent = yprint_order_actions_ajax.messages.added_to_cart;
                        setTimeout(() => {
                            window.location.href = 'https://yprint.de/checkout';
                        }, 1000);
                    } else {
                        alert(response.data || yprint_order_actions_ajax.messages.error_adding);
                        button.querySelector('span').textContent = originalText;
                        button.classList.remove('loading');
                        button.disabled = false;
                    }
                },
                error: function() {
                    alert(yprint_order_actions_ajax.messages.error_adding);
                    button.querySelector('span').textContent = originalText;
                    button.classList.remove('loading');
                    button.disabled = false;
                }
            });
        }
        </script>
        
        <?php
        
        return ob_get_clean();
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
        
        // Check if WooCommerce is active
        if (!class_exists('WooCommerce')) {
            wp_send_json_error('WooCommerce ist nicht aktiv');
            return;
        }
        
        // Get order
        $order = wc_get_order($order_id);
        if (!$order) {
            wp_send_json_error('Bestellung nicht gefunden');
            return;
        }
        
        // Check if user owns this order
        $current_user_id = get_current_user_id();
        if ($current_user_id === 0 || $order->get_customer_id() !== $current_user_id) {
            wp_send_json_error('Keine Berechtigung für diese Bestellung');
            return;
        }
        
        // Get order item
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
        
        // Get product
        $product = $order_item->get_product();
        if (!$product) {
            wp_send_json_error('Produkt nicht mehr verfügbar');
            return;
        }
        
        try {
            // Add to cart based on whether it's a design product or regular product
            $cart_item_data = array();
            
            // Check if this was a design product
            $design_data = $order_item->get_meta('print_design');
            if (!empty($design_data) && $design_id) {
                // This is a design product - add the design data
                $cart_item_data['print_design'] = $design_data;
                $cart_item_data['_is_design_product'] = true;
                $cart_item_data['unique_design_key'] = md5(wp_json_encode($design_data));
            }
            
            // Add to cart
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
YPrint_Order_Actions::init();