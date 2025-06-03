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
    
    // CSS und JavaScript einbinden
    wp_enqueue_script('yprint-checkout-header', plugins_url('assets/js/yprint-checkout-header.js', __FILE__), ['jquery'], '1.0.0', true);
    
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
                    action: 'yprint_get_checkout_header_cart',
                    nonce: yprintCheckoutHeader.nonce
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