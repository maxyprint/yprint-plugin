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
        // Nur als Fallback für Bestellungen ohne design_id beibehalten
add_action('wp_ajax_yprint_reorder_item', array(__CLASS__, 'handle_reorder'));
add_action('wp_ajax_nopriv_yprint_reorder_item', array(__CLASS__, 'handle_reorder'));
// Alle anderen werden von Your_Designs übernommen
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

    /* Exakte Your Designs Size Dropdown Styles */
.yprint-size-dropdown {
    position: fixed;
    background: white;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    z-index: 999999;
    opacity: 0;
    visibility: hidden;
    transform: translateY(-10px);
    transition: opacity 0.2s ease, visibility 0.2s ease, transform 0.2s ease;
    padding: 12px;
    min-width: 180px;
    max-width: 250px;
    white-space: nowrap;
    pointer-events: none;
    font-family: system-ui, 'Segoe UI', Roboto, Helvetica, sans-serif;
}

.yprint-size-dropdown.show {
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) !important;
    pointer-events: auto;
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
    background: #e5e7eb;
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

/* Mobile Anpassungen */
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
  font-family: "Roboto", sans-serif;
  font-weight: 500;
  font-size: 13px;
  color: #0079FF; /* edles, zurückhaltendes Dunkelgrau */
  text-transform: none;
  letter-spacing: 0.3px;
  line-height: 1.4;
  margin-bottom: 4px;
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

/* Position relative for share button container */
.yprint-last-order-action-btn.share {
    position: relative !important;
}

.yprint-last-order-action-btn.share:hover {
    color: #374151;
    background-color: #f3f4f6;
    transform: translateY(-1px);
}

.yprint-last-order-action-btn.share.show {
    background-color: #e5e7eb !important;
    color: #374151 !important;
}

/* Share dropdown styling - FIXED VERSION */
.yprint-share-dropdown-desktop {
    position: absolute !important;
    top: calc(100% + 8px) !important;
    right: 0 !important;
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
    padding: 8px !important;
    z-index: 999999 !important;
    min-width: 180px !important;
    
    /* Animation states */
    opacity: 0 !important;
    visibility: hidden !important;
    transform: translateY(-10px) scale(0.95) !important;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    
    /* Prevent interference */
    pointer-events: none !important;
}

.yprint-share-dropdown-desktop.show {
    opacity: 1 !important;
    visibility: visible !important;
    transform: translateY(0) scale(1) !important;
    pointer-events: auto !important;
}

.yprint-share-option {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 10px 12px !important;
    color: #374151 !important;
    text-decoration: none !important;
    border-radius: 6px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}

.yprint-share-option:hover {
    background-color: #f8fafc !important;
    color: #111827 !important;
    transform: translateX(2px) !important;
}

.yprint-share-option:active {
    background-color: #e2e8f0 !important;
    transform: translateX(0) !important;
}

.yprint-share-option i {
    font-size: 16px !important;
    width: 18px !important;
    text-align: center !important;
    transition: transform 0.15s ease !important;
}

.yprint-share-option:hover i {
    transform: scale(1.1) !important;
}

/* Mobile responsive */
@media (max-width: 480px) {
    .yprint-share-dropdown-desktop {
        right: 0 !important;
        left: auto !important;
        min-width: 160px !important;
    }
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

/* Size Selection Modal */
.yprint-size-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}

.yprint-size-modal {
    background: white;
    border-radius: 12px;
    max-width: 400px;
    width: 100%;
    max-height: 80vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
}

.yprint-size-modal-header {
    padding: 20px;
    border-bottom: 1px solid #eee;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.yprint-size-modal-header h3 {
    margin: 0;
    font-size: 18px;
    font-weight: 600;
}

.yprint-size-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #999;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    transition: all 0.2s;
}

.yprint-size-modal-close:hover {
    background: #f5f5f5;
    color: #333;
}

.yprint-size-modal-content {
    padding: 20px;
}

.yprint-size-options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(80px, 1fr));
    gap: 10px;
}

.yprint-size-option {
    padding: 12px;
    border: 2px solid #e0e0e0;
    border-radius: 8px;
    background: white;
    cursor: pointer;
    font-weight: 500;
    transition: all 0.2s;
    text-align: center;
}

.yprint-size-option:hover {
    border-color: #0079FF;
    background: #f8faff;
}

@media (max-width: 480px) {
    .yprint-size-modal {
        margin: 10px;
        max-width: none;
    }
    
    .yprint-size-options {
        grid-template-columns: repeat(3, 1fr);
    }
}

/* Fixed positioned share dropdown */
.yprint-share-dropdown-fixed {
    position: fixed !important;
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 8px !important;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
    padding: 8px !important;
    z-index: 999999 !important;
    min-width: 180px !important;
}

.yprint-share-dropdown-fixed .yprint-share-option {
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    padding: 10px 12px !important;
    color: #374151 !important;
    text-decoration: none !important;
    border-radius: 6px !important;
    font-size: 14px !important;
    font-weight: 500 !important;
    transition: all 0.15s ease !important;
    cursor: pointer !important;
}

.yprint-share-dropdown-fixed .yprint-share-option:hover {
    background-color: #f8fafc !important;
    color: #111827 !important;
    transform: translateX(2px) !important;
}

.yprint-share-dropdown-fixed .yprint-share-option i {
    font-size: 16px !important;
    width: 18px !important;
    text-align: center !important;
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
                <?php
// Generate share URL for design
$share_url = '';
if (!empty($design_data['design_id'])) {
    $share_url = home_url("/design-share/{$design_data['design_id']}/");
} else {
    $share_url = $product_url; // Fallback to product URL
}
?>
<button class="yprint-last-order-action-btn share yprint-share-trigger"
        data-design-name="<?php echo esc_attr($design_name); ?>"
        data-design-image="<?php echo esc_attr($design_image); ?>"
        data-product-url="<?php echo esc_attr($product_url); ?>"
        data-share-url="<?php echo esc_attr($share_url); ?>"
        data-design-id="<?php echo esc_attr($design_data['design_id'] ?? ''); ?>">
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
    console.log('YPrint Debug [Order Actions]: ===== INITIALIZATION START =====');
    const container = document.getElementById('<?php echo esc_js($unique_id); ?>');
    
    if (!container) {
        console.error('YPrint Debug [Order Actions]: Container not found with ID:', '<?php echo esc_js($unique_id); ?>');
        console.log('YPrint Debug [Order Actions]: Available containers:', document.querySelectorAll('[id*="yprint"]'));
        return;
    }
    
    console.log('YPrint Debug [Order Actions]: ✅ Container found successfully:', container);
    console.log('YPrint Debug [Order Actions]: Container innerHTML length:', container.innerHTML.length);
    console.log('YPrint Debug [Order Actions]: Container classes:', container.className);

    // =================================================================
    // REORDER BUTTON FUNCTIONALITY
    // =================================================================
    console.log('YPrint Debug [Order Actions]: Setting up reorder button...');
    const reorderBtn = container.querySelector('.yprint-reorder-btn');
    console.log('YPrint Debug [Order Actions]: Reorder button search result:', reorderBtn);
    
    if (reorderBtn) {
        console.log('YPrint Debug [Order Actions]: ✅ Reorder button found!');
        console.log('YPrint Debug [Order Actions]: Button dataset:', reorderBtn.dataset);
        console.log('YPrint Debug [Order Actions]: Button classes:', reorderBtn.className);
        console.log('YPrint Debug [Order Actions]: Button innerHTML:', reorderBtn.innerHTML);
        
        reorderBtn.addEventListener('click', (e) => {
            console.log('YPrint Debug [Order Actions]: 🎯 REORDER BUTTON CLICKED!');
            console.log('YPrint Debug [Order Actions]: Click event details:', {
                target: e.target,
                currentTarget: e.currentTarget,
                type: e.type,
                timeStamp: e.timeStamp
            });
            
            e.preventDefault();
            e.stopPropagation();
            
            const { orderId, itemId, designId } = e.currentTarget.dataset;
            console.log('YPrint Debug [Order Actions]: Extracted button data:', {
                orderId: orderId,
                itemId: itemId,
                designId: designId,
                hasOrderId: !!orderId,
                hasItemId: !!itemId,
                hasDesignId: !!designId && designId !== 'undefined' && designId !== ''
            });
            
            handleReorder(orderId, itemId, designId, e.currentTarget);
        });
        
        console.log('YPrint Debug [Order Actions]: ✅ Reorder click event listener registered');
    } else {
        console.error('YPrint Debug [Order Actions]: ❌ Reorder button NOT found!');
        console.log('YPrint Debug [Order Actions]: Available buttons:', container.querySelectorAll('button'));
        console.log('YPrint Debug [Order Actions]: Elements with "reorder" class:', container.querySelectorAll('[class*="reorder"]'));
        console.log('YPrint Debug [Order Actions]: Elements with "yprint-reorder-btn" class:', container.querySelectorAll('.yprint-reorder-btn'));
    }

    // =================================================================
    // SHARE BUTTON FUNCTIONALITY
    // =================================================================
    console.log('YPrint Debug [Order Actions]: Setting up share button...');
    const shareButton = container.querySelector('.yprint-share-trigger');
    const shareDropdown = container.querySelector('.yprint-share-dropdown-desktop');
    
    console.log('YPrint Debug [Order Actions]: Share button found:', !!shareButton);
    console.log('YPrint Debug [Order Actions]: Share dropdown found:', !!shareDropdown);
    
    if (shareButton) {
        console.log('YPrint Debug [Order Actions]: Share button dataset:', shareButton.dataset);
        
        shareButton.addEventListener('click', (e) => {
    console.log('YPrint Debug [Order Actions]: 🎯 SHARE BUTTON CLICKED!');
    e.preventDefault();
    e.stopPropagation();
    
    // Close ALL existing dropdowns
    document.querySelectorAll('.yprint-share-dropdown-fixed').forEach(dropdown => {
        dropdown.remove();
    });
    
    // Remove show class from all share buttons
    document.querySelectorAll('.yprint-share-trigger.show').forEach(btn => {
        btn.classList.remove('show');
    });
    
    // Check if this dropdown is already open
    if (shareButton.classList.contains('show')) {
        shareButton.classList.remove('show');
        console.log('YPrint Debug [Order Actions]: Share dropdown CLOSED');
        return;
    }
    
    // Create new dropdown in body with fixed positioning
    createAndShowShareDropdown(shareButton);
});

function createAndShowShareDropdown(button) {
    const dropdown = document.createElement('div');
    dropdown.className = 'yprint-share-dropdown-fixed';
    
    // Get button position
    const buttonRect = button.getBoundingClientRect();
    console.log('YPrint Debug [Order Actions]: Button position:', buttonRect);
    
    // Position dropdown
    dropdown.style.cssText = `
        position: fixed !important;
        top: ${buttonRect.bottom + 8}px !important;
        right: ${window.innerWidth - buttonRect.right}px !important;
        background: #ffffff !important;
        border: 1px solid #e5e7eb !important;
        border-radius: 8px !important;
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15) !important;
        padding: 8px !important;
        z-index: 999999 !important;
        min-width: 180px !important;
        opacity: 0 !important;
        visibility: hidden !important;
        transform: translateY(-10px) scale(0.95) !important;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1) !important;
    `;
    
    dropdown.innerHTML = `
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
    `;
    
    // Add click handlers for share options
    dropdown.addEventListener('click', (e) => {
        const shareOption = e.target.closest('.yprint-share-option');
        if (shareOption) {
            e.preventDefault();
            e.stopPropagation();
            
            const { platform } = shareOption.dataset;
            const designName = button.dataset.designName || 'Mein Design';
const shareUrl = button.dataset.shareUrl || button.dataset.productUrl || window.location.href;
const designId = button.dataset.designId || '';
const shareTitle = '<?php echo esc_js(__('Schau dir mein Design an!', 'yprint-plugin')); ?>';
const shareText = '<?php echo esc_js(__('Individuelles Design erstellt', 'yprint-plugin')); ?>';
const fullShareText = `${shareTitle}: "${designName}" - ${shareText}`;

console.log('YPrint Debug [Order Actions]: Share platform:', platform);
console.log('YPrint Debug [Order Actions]: Share URL:', shareUrl);
console.log('YPrint Debug [Order Actions]: Design ID:', designId);
handleShare(platform, fullShareText, shareUrl);
            
            // Close dropdown
            dropdown.remove();
            button.classList.remove('show');
        }
    });
    
    // Append to body
    document.body.appendChild(dropdown);
    button.classList.add('show');
    
    // Show with animation
    requestAnimationFrame(() => {
        dropdown.style.opacity = '1';
        dropdown.style.visibility = 'visible';
        dropdown.style.transform = 'translateY(0) scale(1)';
    });
    
    console.log('YPrint Debug [Order Actions]: Fixed dropdown created and positioned');
    console.log('YPrint Debug [Order Actions]: Dropdown final position:', {
        top: dropdown.style.top,
        right: dropdown.style.right,
        zIndex: dropdown.style.zIndex
    });
}

// Global click handler to close fixed dropdown
const globalClickHandler = (e) => {
    if (!e.target.closest('.yprint-share-trigger') && !e.target.closest('.yprint-share-dropdown-fixed')) {
        document.querySelectorAll('.yprint-share-dropdown-fixed').forEach(dropdown => {
            dropdown.remove();
        });
        document.querySelectorAll('.yprint-share-trigger.show').forEach(btn => {
            btn.classList.remove('show');
        });
        console.log('YPrint Debug [Order Actions]: Share dropdown closed by outside click');
    }
};

document.addEventListener('click', globalClickHandler);
        
        // Share option clicks - use event delegation
if (shareDropdown) {
    shareDropdown.addEventListener('click', (e) => {
        console.log('YPrint Debug [Order Actions]: Share dropdown clicked:', e.target);
        
        const shareOption = e.target.closest('.yprint-share-option');
        if (shareOption) {
            console.log('YPrint Debug [Order Actions]: 🎯 SHARE OPTION CLICKED!');
            e.preventDefault();
            e.stopPropagation();
            
            const { platform } = shareOption.dataset;
            const designName = shareButton.dataset.designName || 'Mein Design';
            const productUrl = shareButton.dataset.productUrl || window.location.href;
            const shareTitle = '<?php echo esc_js(__('Schau dir mein Design an!', 'yprint-plugin')); ?>';
            const shareText = '<?php echo esc_js(__('Individuelles Design erstellt', 'yprint-plugin')); ?>';
            const fullShareText = `${shareTitle}: "${designName}" - ${shareText}`;

            console.log('YPrint Debug [Order Actions]: Share platform:', platform);
            handleShare(platform, fullShareText, productUrl);
            
            shareDropdown.classList.remove('show');
            shareButton.classList.remove('show');
        }
    });
    
    console.log('YPrint Debug [Order Actions]: ✅ Share dropdown event delegation registered');
}
    }

    // =================================================================
    // MAIN FUNCTIONS
    // =================================================================
    
    function handleReorder(orderId, itemId, designId, button) {
        console.log('YPrint Debug [Order Actions]: ===== HANDLE REORDER START =====');
        console.log('YPrint Debug [Order Actions]: Parameters:', {
            orderId: orderId,
            itemId: itemId,
            designId: designId,
            button: button
        });
        
        console.log('YPrint Debug [Order Actions]: Parameter validation:', {
            orderIdValid: !!orderId && orderId !== 'undefined',
            itemIdValid: !!itemId && itemId !== 'undefined', 
            designIdValid: !!designId && designId !== 'undefined' && designId !== '',
            buttonValid: !!button
        });
        
        if (designId && designId !== 'undefined' && designId !== '') {
            console.log('YPrint Debug [Order Actions]: ✅ Design ID available - using Your Designs logic');
            showSizeSelectionModal(designId, button);
        } else {
            console.log('YPrint Debug [Order Actions]: ⚠️ No design ID - using direct reorder');
            directReorder(orderId, itemId, button);
        }
        
        console.log('YPrint Debug [Order Actions]: ===== HANDLE REORDER END =====');
    }
    
    function showSizeSelectionModal(designId, button) {
    console.log('YPrint Debug [Order Actions]: ===== SIZE DROPDOWN START =====');
    console.log('YPrint Debug [Order Actions]: Design ID:', designId);
    
    // Close all other dropdowns first
    closeAllSizeDropdowns();
    
    // Create the dropdown exactly like Your Designs
    const dropdown = createSizeDropdown(designId, button);
    document.body.appendChild(dropdown);
    
    // Show dropdown with animation
    requestAnimationFrame(() => {
        dropdown.classList.add('show');
    });
    
    // Make AJAX call to load sizes
    jQuery.ajax({
        url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
        type: 'POST',
        dataType: 'json',
        timeout: 10000,
        data: {
            action: 'yprint_get_product_sizes',
            design_id: designId,
            nonce: '<?php echo wp_create_nonce('yprint_design_actions_nonce'); ?>'
        }
    })
    .done(function(response) {
        console.log('YPrint Debug [Order Actions]: Size AJAX response:', response);
        
        const content = dropdown.querySelector('.yprint-size-dropdown-content');
        if (!content) {
            console.error('YPrint Debug [Order Actions]: Content element not found!');
            return;
        }
        
        if (response.success && response.data && response.data.sizes) {
            console.log('YPrint Debug [Order Actions]: ✅ Loading sizes into dropdown');
            loadSizesIntoDropdown(response.data.sizes, content, dropdown);
        } else {
            content.innerHTML = '<div class="yprint-size-error">Keine Größen verfügbar</div>';
        }
    })
    .fail(function(xhr, status, error) {
        console.error('YPrint Debug [Order Actions]: ❌ Size AJAX failed:', {xhr, status, error});
        const content = dropdown.querySelector('.yprint-size-dropdown-content');
        if (content) {
            content.innerHTML = '<div class="yprint-size-error">Fehler beim Laden der Größen</div>';
        }
    });
}

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
    
    // Prevent event bubbling
    dropdown.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    dropdown.innerHTML = `
    <div class="yprint-size-dropdown-header">
        <div class="yprint-size-dropdown-title">Größe wählen</div>
    </div>
    <div class="yprint-size-dropdown-content">
        <div class="yprint-size-loading">Lädt Größen...</div>
    </div>
    <div class="yprint-size-dropdown-actions">
        <button class="yprint-size-dropdown-btn cancel">Abbrechen</button>
        <button class="yprint-size-dropdown-btn confirm" disabled data-design-id="${designId}">Bestätigen</button>
    </div>
`;
    
    // Add button functionality
const confirmBtn = dropdown.querySelector('.yprint-size-dropdown-btn.confirm');
const cancelBtn = dropdown.querySelector('.yprint-size-dropdown-btn.cancel');

confirmBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    const selectedSize = this.getAttribute('data-selected-size');
    if (selectedSize) {
        console.log('YPrint Debug [Order Actions]: Size confirmed:', selectedSize);
        closeDropdown(dropdown);
        proceedWithReorder(designId, selectedSize, button);
    }
});

cancelBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    console.log('YPrint Debug [Order Actions]: Size selection cancelled');
    closeDropdown(dropdown);
});
    
    return dropdown;
}

function loadSizesIntoDropdown(sizes, content, dropdown) {
    console.log('YPrint Debug [Order Actions]: Loading sizes:', sizes);
    
    const confirmBtn = dropdown.querySelector('.yprint-size-dropdown-btn.confirm');
    let html = '<div class="yprint-size-options">';
    
    sizes.forEach(size => {
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
    console.log('YPrint Debug [Order Actions]: Generated HTML:', html);
    content.innerHTML = html;
    
    // Add click listeners to size options
    const sizeOptions = content.querySelectorAll('.yprint-size-option:not(.disabled)');
    console.log('YPrint Debug [Order Actions]: Found clickable size options:', sizeOptions.length);
    
    sizeOptions.forEach(option => {
        option.onclick = function(e) {
            e.stopPropagation();
            console.log('YPrint Debug [Order Actions]: Size option clicked:', this.dataset.size);
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
    console.log('YPrint Debug [Order Actions]: Size selected:', selectedSize);
    confirmBtn.disabled = false;
    confirmBtn.setAttribute('data-selected-size', selectedSize);
}

function closeDropdown(dropdown) {
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
    }, 200);
}

function closeAllSizeDropdowns() {
    document.querySelectorAll('.yprint-size-dropdown').forEach(dropdown => {
        closeDropdown(dropdown);
    });
}
    
    
    function proceedWithReorder(designId, selectedSize, button) {
        console.log('YPrint Debug [Order Actions]: ===== PROCEED WITH REORDER =====');
        console.log('YPrint Debug [Order Actions]: Design ID:', designId, 'Size:', selectedSize);
        
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span>Wird hinzugefügt...</span>';
        button.style.pointerEvents = 'none';

        const reorderData = {
            action: 'yprint_reorder_design',
            design_id: designId,
            selected_size: selectedSize,
            nonce: '<?php echo wp_create_nonce('yprint_design_actions_nonce'); ?>'
        };
        
        console.log('YPrint Debug [Order Actions]: Reorder AJAX data:', reorderData);

        jQuery.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            type: 'POST',
            data: reorderData
        })
        .done(function(response) {
            console.log('YPrint Debug [Order Actions]: ✅ Reorder response:', response);
            
            if (response.success) {
                button.innerHTML = '<i class="fas fa-check"></i><span>Hinzugefügt!</span>';
                
                jQuery(document.body).trigger('added_to_cart', [[], '', button]);
                jQuery(document.body).trigger('wc_fragments_refreshed');
                
                setTimeout(() => {
                    if (typeof window.openYPrintCart === 'function') {
                        window.openYPrintCart();
                    } else {
                        window.location.hash = '#mobile-cart';
                    }
                }, 300);
                
                setTimeout(() => {
                    button.innerHTML = '<i class="fas fa-redo-alt"></i><span>Reorder</span>';
                    button.style.pointerEvents = '';
                }, 2000);
            } else {
                console.error('YPrint Debug [Order Actions]: ❌ Reorder failed:', response.data);
                alert(response.data || 'Fehler beim Hinzufügen zum Warenkorb');
                button.innerHTML = '<i class="fas fa-redo-alt"></i><span>Reorder</span>';
                button.style.pointerEvents = '';
            }
        })
        .fail(function(xhr, status, error) {
            console.error('YPrint Debug [Order Actions]: ❌ Reorder AJAX failed:', {xhr, status, error});
            alert('Netzwerkfehler beim Hinzufügen zum Warenkorb');
            button.innerHTML = '<i class="fas fa-redo-alt"></i><span>Reorder</span>';
            button.style.pointerEvents = '';
        });
    }
    
    function directReorder(orderId, itemId, button) {
        console.log('YPrint Debug [Order Actions]: Direct reorder for order:', orderId, 'item:', itemId);
        
        button.classList.add('loading');
        button.disabled = true;
        const originalText = button.querySelector('span').textContent;
        button.querySelector('span').textContent = 'Wird hinzugefügt...';

        jQuery.ajax({
            url: '<?php echo esc_url(admin_url('admin-ajax.php')); ?>',
            type: 'POST',
            data: {
                action: 'yprint_reorder_item',
                order_id: orderId,
                item_id: itemId,
                nonce: '<?php echo wp_create_nonce('yprint_order_actions_nonce'); ?>'
            }
        })
        .done(response => {
            console.log('YPrint Debug [Order Actions]: Direct reorder response:', response);
            
            if (response.success) {
                button.querySelector('span').textContent = 'Hinzugefügt!';
                setTimeout(() => window.location.hash = '#mobile-cart', 300);
                
                setTimeout(() => {
                    button.querySelector('span').textContent = originalText;
                    button.classList.remove('loading');
                    button.disabled = false;
                }, 2000);
            } else {
                alert(response.data || 'Fehler beim Hinzufügen zum Warenkorb');
                button.querySelector('span').textContent = originalText;
                button.classList.remove('loading');
                button.disabled = false;
            }
        })
        .fail(() => {
            alert('Netzwerkfehler beim Hinzufügen zum Warenkorb');
            button.querySelector('span').textContent = originalText;
            button.classList.remove('loading');
            button.disabled = false;
        });
    }
    
    function handleShare(platform, text, url) {
        console.log('YPrint Debug [Order Actions]: Share to platform:', platform);
        
        let shareUrl = '';
        switch(platform) {
            case 'whatsapp':
                shareUrl = `https://wa.me/?text=${encodeURIComponent(text + ' ' + url)}`;
                break;
            case 'facebook':
                shareUrl = `https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(url)}`;
                break;
            case 'twitter':
                shareUrl = `https://twitter.com/intent/tweet?text=${encodeURIComponent(text)}&url=${encodeURIComponent(url)}`;
                break;
            case 'telegram':
                shareUrl = `https://t.me/share/url?url=${encodeURIComponent(url)}&text=${encodeURIComponent(text)}`;
                break;
            case 'copy':
                navigator.clipboard.writeText(url).then(() => {
                    const notification = document.createElement('div');
                    notification.textContent = 'Link kopiert!';
                    notification.style.cssText = `
                        position: fixed; top: 20px; right: 20px; background: #111827; color: white;
                        padding: 12px 20px; border-radius: 8px; z-index: 10000; font-size: 14px;
                    `;
                    document.body.appendChild(notification);
                    setTimeout(() => notification.remove(), 2000);
                });
                return;
        }
        
        if (shareUrl) {
            window.open(shareUrl, '_blank', 'width=600,height=400');
        }
    }

    console.log('YPrint Debug [Order Actions]: ===== INITIALIZATION COMPLETE =====');
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
        $current_user = wp_get_current_user();
        if (!$current_user || !$current_user->user_email) {
            return false;
        }
        
        $user_email = $current_user->user_email;
        $all_statuses = array_keys(wc_get_order_statuses());
        
        // Erste Methode: Nach customer_user ID suchen
        $customer_orders = wc_get_orders(array(
            'customer' => $user_id,
            'limit'    => 1,
            'type'     => 'shop_order',
            'status'   => $all_statuses,
            'orderby'  => 'date',
            'order'    => 'DESC'
        ));
        
        if (!empty($customer_orders)) {
            return $customer_orders[0];
        }
        
        // Fallback: Nach E-Mail-Adresse suchen (für Gast-Bestellungen)
        $email_orders = wc_get_orders(array(
            'billing_email' => $user_email,
            'limit'    => 1,
            'type'     => 'shop_order',
            'status'   => $all_statuses,
            'orderby'  => 'date',
            'order'    => 'DESC'
        ));
        
        if (!empty($email_orders)) {
            return $email_orders[0];
        }
        
        return false;
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
            $original_design_data = $order_item->get_meta('print_design');

            if (!empty($original_design_data)) {
                // Get stored image data from order item meta if not in cart data
                $stored_design_images = $order_item->get_meta('_design_images');
                $stored_product_images = $order_item->get_meta('_design_product_images');
                $stored_has_multiple = $order_item->get_meta('_design_has_multiple_images');
                
                // Parse JSON data if stored as string
                $design_images_array = array();
                if (!empty($stored_design_images)) {
                    $parsed_images = is_string($stored_design_images) ? json_decode($stored_design_images, true) : $stored_design_images;
                    if (is_array($parsed_images)) {
                        $design_images_array = $parsed_images;
                    }
                }
                
                $product_images_array = array();
                if (!empty($stored_product_images)) {
                    $parsed_product_images = is_string($stored_product_images) ? json_decode($stored_product_images, true) : $stored_product_images;
                    if (is_array($parsed_product_images)) {
                        $product_images_array = $parsed_product_images;
                    }
                }
                
                // Fallback to original data if meta not available
                if (empty($design_images_array) && isset($original_design_data['design_images'])) {
                    $design_images_array = is_array($original_design_data['design_images']) ? $original_design_data['design_images'] : array();
                }
                
                if (empty($product_images_array) && isset($original_design_data['product_images'])) {
                    $product_images_array = is_array($original_design_data['product_images']) ? $original_design_data['product_images'] : array();
                }
                
                // Create complete design data structure compatible with multi-image system
                $complete_design_data = array(
                    'design_id' => $original_design_data['design_id'] ?? $design_id,
                    'name' => $original_design_data['name'] ?? $order_item->get_name(),
                    'template_id' => $original_design_data['template_id'] ?? '',
                    'variation_id' => $original_design_data['variation_id'] ?? '',
                    'variation_name' => $original_design_data['variation_name'] ?? 'Standard',
                    'variation_color' => $original_design_data['variation_color'] ?? '',
                    'size_id' => $original_design_data['size_id'] ?? '',
                    'size_name' => $original_design_data['size_name'] ?? 'One Size',
                    'calculated_price' => $original_design_data['calculated_price'] ?? 0,
                    'preview_url' => $original_design_data['preview_url'] ?? '',
                    'design_width_cm' => $original_design_data['design_width_cm'] ?? $original_design_data['width_cm'] ?? 0,
                    'design_height_cm' => $original_design_data['design_height_cm'] ?? $original_design_data['height_cm'] ?? 0,
                    'design_scaleX' => $original_design_data['design_scaleX'] ?? 1,
                    'design_scaleY' => $original_design_data['design_scaleY'] ?? 1,
                    'design_image_url' => $original_design_data['design_image_url'] ?? $original_design_data['preview_url'] ?? '',
                    // CRITICAL: Include all image data for print provider - from meta or original
                    'design_images' => $design_images_array,
                    'product_images' => $product_images_array,
                    'has_multiple_images' => $stored_has_multiple ?? $original_design_data['has_multiple_images'] ?? (count($design_images_array) > 1),
                    // Ensure backward compatibility
                    'width_cm' => $original_design_data['width_cm'] ?? $original_design_data['design_width_cm'] ?? 0,
                    'height_cm' => $original_design_data['height_cm'] ?? $original_design_data['design_height_cm'] ?? 0
                );

                $cart_item_data['print_design'] = $complete_design_data;
$cart_item_data['_is_design_product'] = true;
$cart_item_data['_is_reorder'] = true; // Mark as reorder for tracking
$cart_item_data['_original_order_id'] = $order_id; // Track source order
$cart_item_data['unique_design_key'] = md5(wp_json_encode($complete_design_data));

// Create unique key for cart identification (prevents merging of different designs)
$cart_item_data['unique_key'] = md5(
    ($complete_design_data['design_id'] ?? '') . 
    ($complete_design_data['variation_id'] ?? '') . 
    ($complete_design_data['size_id'] ?? '') . 
    'reorder_' . $order_id . '_' . microtime()
);

// Store original meta data for debugging and validation
$cart_item_data['_original_meta'] = array(
    'design_images_count' => count($design_images_array),
    'product_images_count' => count($product_images_array),
    'has_multiple_images' => $complete_design_data['has_multiple_images']
);
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
                    'cart_item_key' => $cart_item_key,
                    'design_data_transferred' => !empty($original_design_data)
                ));
            } else {
                wp_send_json_error('Artikel konnte nicht zum Warenkorb hinzugefügt werden');
            }

        } catch (Exception $e) {
            wp_send_json_error('Fehler beim Hinzufügen zum Warenkorb: ' . $e->getMessage());
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
     * Handle get product sizes AJAX request (from your-designs-shortcode.php)
     */
    public static function handle_get_product_sizes() {
        check_ajax_referer('yprint_design_actions_nonce', 'nonce');

        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        
        if (!$design_id) {
            wp_send_json_error('Ungültige Design-ID');
            return;
        }

        try {
            global $wpdb;
            
            // Get sizes from database
            $sizes = $wpdb->get_results(
                "SELECT id, name FROM {$wpdb->prefix}yprint_product_sizes ORDER BY sort_order ASC"
            );
            
            if (empty($sizes)) {
                wp_send_json_error('Keine Größen verfügbar');
                return;
            }

            wp_send_json_success(array(
                'sizes' => $sizes,
                'design_id' => $design_id
            ));

        } catch (Exception $e) {
            wp_send_json_error('Fehler beim Laden der Größen: ' . $e->getMessage());
        }
    }

    /**
     * Handle reorder with size selection AJAX request
     */
    public static function handle_reorder_design_with_size() {
        check_ajax_referer('yprint_order_actions_nonce', 'nonce');

        $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
        $item_id = isset($_POST['item_id']) ? intval($_POST['item_id']) : 0;
        $design_id = isset($_POST['design_id']) ? intval($_POST['design_id']) : 0;
        $size_id = isset($_POST['size_id']) ? intval($_POST['size_id']) : 0;
        $size_name = isset($_POST['size_name']) ? sanitize_text_field($_POST['size_name']) : '';

        if (!$order_id || !$item_id || !$design_id || !$size_id) {
            wp_send_json_error('Ungültige Parameter');
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

            // Get design data
            $design = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}octo_user_designs WHERE id = %d AND user_id = %d",
                $design_id,
                $current_user_id
            ));

            if (!$design) {
                wp_send_json_error('Design nicht gefunden oder keine Berechtigung');
                return;
            }

            // Get base product ID
            $base_product_id = get_option('yprint_base_product_id', 3657);
            
            // Parse design data
            $design_data = json_decode($design->design_data, true);
            $preview_url = $design_data['images'][0]['url'] ?? '';

            // Prepare cart item data (similar to your-designs-shortcode.php)
            $cart_item_data = array(
                'yprint_design_data' => array(
                    'design_id' => $design_id,
                    'design_name' => $design->design_name,
                    'design_image_url' => $preview_url,
                    'preview_url' => $preview_url,
                    'created_at' => $design->created_at,
                    'template_id' => $design->template_id
                ),
                '_design_id' => $design_id,
                '_design_name' => $design->design_name,
                '_design_image_url' => $preview_url,
                '_design_size_id' => $size_id,
                '_design_size_name' => $size_name,
                '_is_design_product' => true,
                'unique_design_key' => md5($design_id . $size_id . time())
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
                'size_selected' => $size_name
            ));

        } catch (Exception $e) {
            wp_send_json_error('Fehler: ' . $e->getMessage());
        }
    }

}

// Initialize the class
YPrint_Order_Actions_Screenshot_Final::init();