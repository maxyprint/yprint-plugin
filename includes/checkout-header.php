<?php
/**
 * YPrint Checkout Header Component
 * 
 * Generiert einen responsiven Header für den Checkout mit:
 * - "Show order summary" Button mit animiertem Popup
 * - Gesamtpreis-Anzeige
 * - Checkout-Fortschrittsanzeige
 * 
 * Shortcode: [yprint_checkout_header step="information"]
 */

// Direktaufruf verhindern
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registriert den Checkout Header Shortcode
 */
function yprint_register_checkout_header_shortcode() {
    add_shortcode('yprint_checkout_header', 'yprint_render_checkout_header');
}
add_action('init', 'yprint_register_checkout_header_shortcode');

/**
 * Rendert den Checkout Header
 * 
 * @param array $atts Shortcode Attribute
 * @return string HTML Output
 */
function yprint_render_checkout_header($atts = []) {
    // Attribute mit Defaults
    $atts = shortcode_atts([
        'step' => 'information', // cart, information, shipping, payment
        'show_total' => 'yes',
        'show_progress' => 'yes'
    ], $atts, 'yprint_checkout_header');
    
    // Warenkorbdaten laden
    $cart_data_manager = YPrint_Cart_Data::get_instance();
    $checkout_context = $cart_data_manager->get_checkout_context('summary');
    $cart_totals = $checkout_context['cart_totals'];
    
    // Script wird in enqueue_checkout_assets() geladen - hier nur Flag setzen
if (!defined('YPRINT_HEADER_LOADED')) {
    define('YPRINT_HEADER_LOADED', true);
}

// Localize script mit notwendigen Daten
wp_localize_script(
    'yprint-checkout-header-js',
    'yprintCheckoutHeader',
    array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yprint-checkout-header'),
        'texts' => array(
            'show_summary' => __('Bestellung anzeigen', 'yprint-plugin'),
            'hide_summary' => __('Bestellung ausblenden', 'yprint-plugin')
        )
    )
);

    // Lokalisierung für JavaScript
    wp_localize_script('yprint-checkout-header', 'yprintCheckoutHeader', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yprint_checkout_header_nonce'),
        'texts' => [
            'show_summary' => __('Bestellung anzeigen', 'yprint-checkout'),
            'hide_summary' => __('Bestellung ausblenden', 'yprint-checkout'),
            'loading' => __('Lädt...', 'yprint-checkout')
        ]
    ]);
    
    ob_start();
    ?>
    
    <style>
    /* YPrint Checkout Header Styles */
    .yprint-checkout-header {
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 20px;
        font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
    }
    
    .yprint-header-top {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .yprint-summary-toggle {
        background: #f5f5f7;
        border: 1px solid #DFDFDF;
        border-radius: 10px;
        padding: 12px 20px;
        color: #1d1d1f;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease-in-out;
        display: flex;
        align-items: center;
        gap: 8px;
        font-size: 16px;
    }
    
    .yprint-summary-toggle:hover {
        background: #e9e9ed;
        transform: translateY(-1px);
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .yprint-summary-toggle.active {
        background: #0079FF;
        color: #ffffff;
        border-color: #0079FF;
    }
    
    .yprint-summary-icon {
        transition: transform 0.3s ease;
        font-size: 14px;
    }
    
    .yprint-summary-toggle.active .yprint-summary-icon {
        transform: rotate(180deg);
    }
    
    .yprint-total-price {
        font-size: 20px;
        font-weight: 600;
        color: #0079FF;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .yprint-total-label {
        font-size: 14px;
        color: #6e6e73;
        font-weight: 400;
        margin-right: 8px;
    }
    
    .yprint-progress-steps {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: nowrap;
    padding-top: 15px;
    border-top: 1px solid #e5e5e5;
    overflow-x: auto;
    scrollbar-width: none;
    -ms-overflow-style: none;
}

.yprint-progress-steps::-webkit-scrollbar {
    display: none;
}

.yprint-progress-step {
    color: #6e6e73;
    font-size: 14px;
    font-weight: 400;
    position: relative;
    white-space: nowrap;
    flex-shrink: 0;
}

.yprint-progress-step.active {
    color: #1d1d1f;
    font-weight: 600;
}

.yprint-progress-step.completed {
    color: #28a745;
}

.yprint-progress-step.completed::before {
    content: "✓ ";
    font-weight: bold;
    margin-right: 3px;
}

.yprint-progress-separator {
    color: #e5e5e5;
    font-size: 12px;
    flex-shrink: 0;
}

/* Mobile: Warenkorb-Schritt ausblenden */
@media (max-width: 640px) {
    .yprint-progress-step[data-step="cart"] {
        display: none;
    }
    
    .yprint-progress-step[data-step="cart"] + .yprint-progress-separator {
        display: none;
    }
}
    
    .yprint-summary-popup {
        background: #ffffff;
        border: 1px solid #DFDFDF;
        border-radius: 12px;
        margin-top: 15px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        transform-origin: top;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    .yprint-summary-popup.hidden {
        max-height: 0;
        opacity: 0;
        transform: scaleY(0);
        margin-top: 0;
    }
    
    .yprint-summary-popup.visible {
        max-height: 500px;
        opacity: 1;
        transform: scaleY(1);
    }
    
    .yprint-summary-content {
        padding: 20px;
        max-height: 400px;
        overflow-y: auto;
    }
    
    .yprint-cart-item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .yprint-cart-item:last-child {
        border-bottom: none;
    }
    
    .yprint-item-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 8px;
        margin-right: 12px;
        background: #f5f5f7;
        border: 1px solid #e5e5e5;
    }
    
    .yprint-item-details {
        flex: 1;
        min-width: 0;
    }
    
    .yprint-item-name {
        font-weight: 500;
        color: #1d1d1f;
        font-size: 14px;
        line-height: 1.3;
        margin-bottom: 2px;
    }
    
    .yprint-item-meta {
        font-size: 12px;
        color: #6e6e73;
    }
    
    .yprint-item-price {
        font-weight: 600;
        color: #1d1d1f;
        font-size: 14px;
        white-space: nowrap;
    }
    
    .yprint-summary-totals {
        border-top: 1px solid #e5e5e5;
        padding-top: 15px;
        margin-top: 15px;
    }
    
    .yprint-total-line {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 4px 0;
        font-size: 14px;
    }
    
    .yprint-total-line.final {
        font-weight: 600;
        font-size: 16px;
        color: #0079FF;
        padding-top: 8px;
        border-top: 1px solid #e5e5e5;
        margin-top: 8px;
    }
    
    /* Responsive Design */
    @media (max-width: 640px) {
        .yprint-checkout-header {
            padding: 15px;
        }
        
        .yprint-header-top {
            flex-direction: column;
            gap: 15px;
            align-items: stretch;
        }
        
        .yprint-summary-toggle {
            justify-content: center;
        }
        
        .yprint-total-price {
            justify-content: center;
            font-size: 18px;
        }
        
        .yprint-progress-steps {
            justify-content: center;
            padding-top: 10px;
        }
        
        .yprint-progress-step {
            font-size: 13px;
        }
    }
    
    /* Design-Produkt Unterstützung */
    .yprint-cart-item.design-product {
        background: linear-gradient(135deg, rgba(0, 121, 255, 0.05), rgba(0, 121, 255, 0.02));
        border-radius: 8px;
        padding: 12px;
        margin: 8px 0;
        border: 1px solid rgba(0, 121, 255, 0.1);
    }
    
    .yprint-design-badge {
        display: inline-flex;
        align-items: center;
        gap: 4px;
        background: #0079FF;
        color: white;
        font-size: 10px;
        padding: 2px 6px;
        border-radius: 10px;
        margin-left: 6px;
    }
    
    .yprint-loading-spinner {
        display: inline-block;
        width: 16px;
        height: 16px;
        border: 2px solid #e5e5e5;
        border-left-color: #0079FF;
        border-radius: 50%;
        animation: yprint-spin 1s linear infinite;
    }
    
    @keyframes yprint-spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
    
    /* Font Awesome Icons für Checkout Header */
    .yprint-checkout-header .fas,
    .yprint-checkout-header .far,
    .yprint-checkout-header .fab {
        font-family: "Font Awesome 6 Free" !important;
        font-weight: 900;
        font-style: normal;
        display: inline-block;
        text-rendering: auto;
        -webkit-font-smoothing: antialiased;
    }
    
    /* Spezifische Header Icons */
    .yprint-checkout-header .fa-shopping-bag::before { content: "\f290"; }
    .yprint-checkout-header .fa-chevron-down::before { content: "\f078"; }
    .yprint-checkout-header .fa-check::before { content: "\f00c"; }
    </style>
    
    <div class="yprint-checkout-header" id="yprint-checkout-header">
        <div class="yprint-header-top">
            <button type="button" class="yprint-summary-toggle" id="yprint-summary-toggle" aria-expanded="false">
                <i class="fas fa-shopping-bag"></i>
                <span class="yprint-summary-text"><?php esc_html_e('Bestellung anzeigen', 'yprint-checkout'); ?></span>
                <i class="fas fa-chevron-down yprint-summary-icon"></i>
            </button>
            
            <?php if ($atts['show_total'] === 'yes') : ?>
            <div class="yprint-total-price">
                <span class="yprint-total-label"><?php esc_html_e('Gesamt:', 'yprint-checkout'); ?></span>
                <span id="yprint-header-total">€<?php echo number_format($cart_totals['total'], 2, ',', '.'); ?></span>
            </div>
            <?php endif; ?>
        </div>
        
        <?php if ($atts['show_progress'] === 'yes') : ?>
            <div class="yprint-progress-steps">
    <?php
    $steps = [
        'cart' => __('Warenkorb', 'yprint-checkout'),
        'address' => __('Adresse', 'yprint-checkout'),
        'payment' => __('Zahlung', 'yprint-checkout'),
        'confirmation' => __('Bestätigung', 'yprint-checkout')
    ];
    
    $step_keys = array_keys($steps);
    $current_index = array_search($atts['step'], $step_keys);
    
    // Wenn wir uns nicht im Warenkorb befinden, zeige Warenkorb nicht als "completed" an
    if ($atts['step'] !== 'cart') {
        $current_index = max(1, $current_index); // Mindestens Index 1 (address)
    }
    
    foreach ($steps as $step_key => $step_label) {
        $step_index = array_search($step_key, $step_keys);
        $is_current = ($step_key === $atts['step']);
        $is_completed = ($step_index < $current_index);
        
        // Warenkorb soll niemals als "completed" markiert werden
        if ($step_key === 'cart' && $atts['step'] !== 'cart') {
            $is_completed = false;
        }
        
        $class = 'yprint-progress-step';
        if ($is_current) $class .= ' active';
        if ($is_completed) $class .= ' completed';
        
        echo '<span class="' . esc_attr($class) . '" data-step="' . esc_attr($step_key) . '">';
        if ($is_completed && $step_key !== 'cart') {
            echo '<i class="fas fa-check" style="margin-right: 4px;"></i>';
        }
        echo esc_html($step_label);
        echo '</span>';
        
        // Separator hinzufügen (außer beim letzten Element)
        if ($step_index < count($steps) - 1) {
            echo '<span class="yprint-progress-separator">›</span>';
        }
    }
    ?>
</div>
        <?php endif; ?>
        
        <div class="yprint-summary-popup hidden" id="yprint-summary-popup">
            <div class="yprint-summary-content" id="yprint-summary-content">
                <div class="yprint-loading-spinner" style="margin: 20px auto; display: block;"></div>
                <p style="text-align: center; color: #6e6e73; margin-top: 10px;">
                    <?php esc_html_e('Lade Warenkorbinhalte...', 'yprint-checkout'); ?>
                </p>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        const $toggle = $('#yprint-summary-toggle');
        const $popup = $('#yprint-summary-popup');
        const $content = $('#yprint-summary-content');
        const $icon = $('.yprint-summary-icon');
        const $text = $('.yprint-summary-text');
        
        let isOpen = false;
        let contentLoaded = false;
        
        // Toggle Button Click Handler
        $toggle.on('click', function(e) {
            e.preventDefault();
            
            if (!isOpen) {
                openSummary();
            } else {
                closeSummary();
            }
        });
        
        function openSummary() {
            isOpen = true;
            $toggle.addClass('active').attr('aria-expanded', 'true');
            $text.text(yprintCheckoutHeader.texts.hide_summary);
            $popup.removeClass('hidden').addClass('visible');
            
            // Lade Inhalte wenn noch nicht geladen
            if (!contentLoaded) {
                loadCartContent();
            }
        }
        
        function closeSummary() {
            isOpen = false;
            $toggle.removeClass('active').attr('aria-expanded', 'false');
            $text.text(yprintCheckoutHeader.texts.show_summary);
            $popup.removeClass('visible').addClass('hidden');
        }
        
        function loadCartContent() {
            $.ajax({
                url: yprintCheckoutHeader.ajax_url,
                type: 'POST',
                data: {
    action: 'yprint_get_checkout_header_cart'
    // Nonce entfernt, da für Warenkorb-Anzeige nicht erforderlich
},
                success: function(response) {
                    if (response.success) {
                        $content.html(response.data.html);
                        contentLoaded = true;
                    } else {
                        $content.html('<p style="text-align: center; color: #dc3545;">' + 
                                    (response.data.message || 'Fehler beim Laden der Warenkorbdaten') + '</p>');
                    }
                },
                error: function() {
                    $content.html('<p style="text-align: center; color: #dc3545;">Verbindungsfehler</p>');
                }
            });
        }
        
        // Preisupdate via Custom Event
        $(document).on('yprint_cart_updated', function(event, data) {
            if (data && data.totals && data.totals.total) {
                $('#yprint-header-total').text('€' + parseFloat(data.totals.total).toFixed(2).replace('.', ','));
            }
            
            // Content neu laden wenn Popup offen ist
            if (isOpen && contentLoaded) {
                contentLoaded = false;
                loadCartContent();
            }
        });
    });
    </script>
    
    <?php
    return ob_get_clean();
}

/**
 * AJAX Handler für Checkout Header Cart (Sicher ohne Nonce)
 */
function yprint_ajax_get_checkout_header_cart() {
    // Debug-Info
    error_log('YPRINT HEADER AJAX: Request received');
    error_log('YPRINT HEADER AJAX: User logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
    
    // Basis-Sicherheitscheck: Nur erlauben wenn Request von derselben Domain kommt
    $referer = wp_get_referer();
    $site_url = get_site_url();
    
    if (!$referer || strpos($referer, $site_url) !== 0) {
        error_log('YPRINT HEADER AJAX: Invalid referer: ' . $referer);
        wp_send_json_error(array('message' => 'Invalid request source'));
        return;
    }
    
    try {
        // Prüfe ob WooCommerce verfügbar ist
        if (!class_exists('WooCommerce') || !WC()) {
            wp_send_json_error(array('message' => 'WooCommerce nicht verfügbar'));
            return;
        }
        
        // Prüfe ob Warenkorb existiert
        if (WC()->cart->is_empty()) {
            wp_send_json_success(array('html' => '<p style="text-align: center; padding: 20px;">Ihr Warenkorb ist leer.</p>'));
            return;
        }
        
        // Lade Warenkorbdaten (nur lesend, keine Änderungen)
        ob_start();
        ?>
        <div class="yprint-header-cart-content" style="padding: 20px; font-family: 'Roboto', -apple-system, BlinkMacSystemFont, sans-serif;">
            <h4 style="margin: 0 0 20px 0; color: #1d1d1f; font-size: 18px; font-weight: 600;">
                <i class="fas fa-shopping-bag" style="margin-right: 8px; color: #0079FF;"></i>
                <?php _e('Ihre Bestellung', 'yprint-checkout'); ?>
            </h4>
            
            <?php foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item): ?>
                <?php 
                $product = $cart_item['data']; 
                if (!$product) continue;
                
                // Produktbild laden
                $product_image = $product->get_image('thumbnail', array(), false);
                if (empty($product_image)) {
                    $product_image = wc_placeholder_img('thumbnail');
                }
                
                // Design-Details für Design-Produkte
                $design_details = '';
                if (isset($cart_item['print_design']) && !empty($cart_item['print_design'])) {
                    $design_details = '<div style="margin-top: 4px;">';
                    $design_details .= '<span style="background: #e3f2fd; color: #0079FF; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 500;">Custom Design</span>';
                    $design_details .= '</div>';
                }
                
                // Produktvarianten/Attribute
                $item_data = '';
                if ($cart_item['variation_id'] > 0) {
                    $variation = wc_get_product($cart_item['variation_id']);
                    if ($variation) {
                        $attributes = $variation->get_variation_attributes();
                        if (!empty($attributes)) {
                            $item_data .= '<div style="margin-top: 4px; font-size: 12px; color: #666;">';
                            foreach ($attributes as $attr_name => $attr_value) {
                                $attr_label = wc_attribute_label(str_replace('attribute_', '', $attr_name));
                                $item_data .= '<span style="margin-right: 8px;">' . esc_html($attr_label) . ': ' . esc_html($attr_value) . '</span>';
                            }
                            $item_data .= '</div>';
                        }
                    }
                }
                ?>
                <div class="cart-item" style="display: flex; align-items: flex-start; padding: 12px 0; border-bottom: 1px solid #f0f0f0;">
                    <!-- Produktbild -->
                    <div class="item-image" style="width: 60px; height: 60px; margin-right: 12px; flex-shrink: 0; border-radius: 8px; overflow: hidden; border: 1px solid #e5e5e5;">
                        <?php echo $product_image; ?>
                    </div>
                    
                    <!-- Produktinfo -->
                    <div class="item-info" style="flex: 1; min-width: 0;">
                        <div class="item-name" style="font-weight: 600; color: #1d1d1f; font-size: 14px; line-height: 1.3; margin-bottom: 4px;">
                            <?php echo esc_html($product->get_name()); ?>
                        </div>
                        
                        <?php echo $item_data; ?>
                        <?php echo $design_details; ?>
                        
                        <div class="item-quantity" style="font-size: 13px; color: #666; margin-top: 6px;">
                            <i class="fas fa-cubes" style="margin-right: 4px; color: #999;"></i>
                            Anzahl: <strong><?php echo esc_html($cart_item['quantity']); ?></strong>
                            <?php if ($cart_item['quantity'] > 1): ?>
                                <span style="color: #999; margin-left: 8px;">
                                    (<?php echo wc_price($cart_item['line_total'] / $cart_item['quantity']); ?> je Stück)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Preis -->
                    <div class="item-price" style="font-weight: 700; color: #0079FF; font-size: 15px; margin-left: 8px;">
                        <?php echo wc_price($cart_item['line_total']); ?>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <!-- Kostenaufstellung -->
            <div class="cart-breakdown" style="margin-top: 20px; padding-top: 16px; border-top: 1px solid #e5e5e5;">
                <!-- Zwischensumme -->
                <div class="cost-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: #666;">
                        <i class="fas fa-calculator" style="margin-right: 6px; color: #999;"></i>
                        <?php _e('Zwischensumme:', 'yprint-checkout'); ?>
                    </span>
                    <span style="color: #333; font-weight: 500;">
                        <?php echo wc_price(WC()->cart->get_subtotal()); ?>
                    </span>
                </div>
                
                <!-- Versandkosten -->
                <div class="cost-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: #666;">
                        <i class="fas fa-truck" style="margin-right: 6px; color: #999;"></i>
                        <?php 
                        if (WC()->cart->needs_shipping()) {
                            $shipping_total = WC()->cart->get_shipping_total();
                            if ($shipping_total > 0) {
                                _e('Versandkosten:', 'yprint-checkout');
                            } else {
                                _e('Versandkosten:', 'yprint-checkout');
                            }
                        } else {
                            _e('Versand:', 'yprint-checkout');
                        }
                        ?>
                    </span>
                    <span style="color: #333; font-weight: 500;">
                        <?php 
                        if (WC()->cart->needs_shipping()) {
                            $shipping_total = WC()->cart->get_shipping_total();
                            if ($shipping_total > 0) {
                                echo wc_price($shipping_total);
                            } else {
                                echo '<span style="color: #000000; font-weight: 600;">Kostenlos</span>';
                            }
                        } else {
                            echo '<span style="color: #666;">Nicht erforderlich</span>';
                        }
                        ?>
                    </span>
                </div>
                
                <!-- Steuern -->
                <?php if (wc_tax_enabled() && WC()->cart->get_taxes_total() > 0): ?>
                <div class="cost-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: #666;">
                        <i class="fas fa-receipt" style="margin-right: 6px; color: #999;"></i>
                        <?php _e('inkl. MwSt.:', 'yprint-checkout'); ?>
                    </span>
                    <span style="color: #333; font-weight: 500;">
                        <?php echo wc_price(WC()->cart->get_taxes_total()); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <!-- Rabatte -->
                <?php if (WC()->cart->has_discount()): ?>
                <div class="cost-row" style="display: flex; justify-content: space-between; margin-bottom: 8px; font-size: 14px;">
                    <span style="color: #28a745;">
                        <i class="fas fa-tags" style="margin-right: 6px;"></i>
                        <?php _e('Rabatt:', 'yprint-checkout'); ?>
                    </span>
                    <span style="color: #28a745; font-weight: 600;">
                        -<?php echo wc_price(WC()->cart->get_discount_total()); ?>
                    </span>
                </div>
                <?php endif; ?>
                
                <!-- Gesamtbetrag -->
                <div class="cart-total" style="margin-top: 16px; padding-top: 16px; border-top: 2px solid #0079FF;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <span style="font-size: 16px; font-weight: 600; color: #1d1d1f;">
                            <i class="fas fa-credit-card" style="margin-right: 8px; color: #0079FF;"></i>
                            <?php _e('Gesamtbetrag:', 'yprint-checkout'); ?>
                        </span>
                        <span style="font-size: 20px; font-weight: 700; color: #0079FF;">
                            <?php echo WC()->cart->get_total(); ?>
                        </span>
                    </div>
                    
                    <?php if (WC()->cart->needs_shipping()): ?>
                    <div style="margin-top: 8px; font-size: 12px; color: #666; text-align: right;">
                        <i class="fas fa-info-circle" style="margin-right: 4px;"></i>
                        <?php _e('Alle Preise inkl. gesetzlicher MwSt.', 'yprint-checkout'); ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        $html = ob_get_clean();
        
        error_log('YPRINT HEADER AJAX: Success - HTML length: ' . strlen($html));
        wp_send_json_success(array('html' => $html));
        
    } catch (Exception $e) {
        error_log('YPrint Checkout Header AJAX Error: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Fehler beim Laden der Daten'));
    }
}

// AJAX-Handler registrieren
add_action('wp_ajax_yprint_get_checkout_header_cart', 'yprint_ajax_get_checkout_header_cart');
add_action('wp_ajax_nopriv_yprint_get_checkout_header_cart', 'yprint_ajax_get_checkout_header_cart');

/**
 * AJAX Handler für Warenkorb-Inhalt
 */
function yprint_checkout_header_cart_ajax() {
    // Nonce prüfen
    if (!wp_verify_nonce($_POST['nonce'], 'yprint_checkout_header_nonce')) {
        wp_die('Security check failed');
    }
    
    try {
        // Warenkorbdaten laden
        $cart_data_manager = YPrint_Cart_Data::get_instance();
        $checkout_context = $cart_data_manager->get_checkout_context('full');
        
        $cart_items = $checkout_context['cart_items'];
        $cart_totals = $checkout_context['cart_totals'];
        
        // HTML für Popup generieren
        ob_start();
        
        if (empty($cart_items)) {
            echo '<p style="text-align: center; color: #6e6e73; padding: 20px;">';
            esc_html_e('Ihr Warenkorb ist leer.', 'yprint-checkout');
            echo '</p>';
        } else {
            foreach ($cart_items as $item) {
                $is_design_product = isset($item['is_design_product']) && $item['is_design_product'];
                $item_class = $is_design_product ? 'yprint-cart-item design-product' : 'yprint-cart-item';
                ?>
                <div class="<?php echo esc_attr($item_class); ?>">
                    <img src="<?php echo esc_url($item['image']); ?>" 
                         alt="<?php echo esc_attr($item['name']); ?>" 
                         class="yprint-item-image">
                    
                    <div class="yprint-item-details">
                        <div class="yprint-item-name">
                            <?php echo esc_html($item['name']); ?>
                            <?php if ($is_design_product) : ?>
                                <span class="yprint-design-badge">
                                    <i class="fas fa-palette"></i> Design
                                </span>
                            <?php endif; ?>
                        </div>
                        <div class="yprint-item-meta">
                            <?php 
                            echo sprintf(
                                esc_html__('Menge: %s × €%s', 'yprint-checkout'),
                                $item['quantity'],
                                number_format($item['price'], 2, ',', '.')
                            );
                            ?>
                        </div>
                        <?php if (isset($item['design_details']) && !empty($item['design_details'])) : ?>
                            <div class="yprint-item-meta" style="margin-top: 4px;">
                                <?php echo implode(' • ', array_map('esc_html', $item['design_details'])); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="yprint-item-price">
                        €<?php echo number_format($item['price'] * $item['quantity'], 2, ',', '.'); ?>
                    </div>
                </div>
                <?php
            }
            
            // Summen anzeigen
            ?>
            <div class="yprint-summary-totals">
                <div class="yprint-total-line">
                    <span><?php esc_html_e('Zwischensumme:', 'yprint-checkout'); ?></span>
                    <span>€<?php echo number_format($cart_totals['subtotal'], 2, ',', '.'); ?></span>
                </div>
                
                <?php if ($cart_totals['shipping'] > 0) : ?>
                <div class="yprint-total-line">
                    <span><?php esc_html_e('Versand:', 'yprint-checkout'); ?></span>
                    <span>€<?php echo number_format($cart_totals['shipping'], 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
                
                <?php if ($cart_totals['discount'] > 0) : ?>
                <div class="yprint-total-line" style="color: #28a745;">
                    <span><?php esc_html_e('Rabatt:', 'yprint-checkout'); ?></span>
                    <span>-€<?php echo number_format($cart_totals['discount'], 2, ',', '.'); ?></span>
                </div>
                <?php endif; ?>
                
                <div class="yprint-total-line final">
                    <span><?php esc_html_e('Gesamt:', 'yprint-checkout'); ?></span>
                    <span>€<?php echo number_format($cart_totals['total'], 2, ',', '.'); ?></span>
                </div>
            </div>
            <?php
        }
        
        $html = ob_get_clean();
        
        wp_send_json_success([
            'html' => $html,
            'totals' => $cart_totals
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error([
            'message' => 'Fehler beim Laden der Warenkorbdaten: ' . $e->getMessage()
        ]);
    }
}
add_action('wp_ajax_yprint_get_checkout_header_cart', 'yprint_checkout_header_cart_ajax');
add_action('wp_ajax_nopriv_yprint_get_checkout_header_cart', 'yprint_checkout_header_cart_ajax');

/**
 * Hilfsfunktion zur Shortcode-Verwendung in Templates
 * 
 * @param string $step Aktueller Checkout-Schritt
 * @param array $args Zusätzliche Argumente
 * @return string HTML Output
 */
function yprint_get_checkout_header($step = 'information', $args = []) {
    $default_args = [
        'step' => $step,
        'show_total' => 'yes',
        'show_progress' => 'yes'
    ];
    
    $args = array_merge($default_args, $args);
    
    return do_shortcode('[yprint_checkout_header ' . 
        'step="' . esc_attr($args['step']) . '" ' .
        'show_total="' . esc_attr($args['show_total']) . '" ' .
        'show_progress="' . esc_attr($args['show_progress']) . '"]');
}
?>