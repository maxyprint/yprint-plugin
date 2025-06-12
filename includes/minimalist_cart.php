<?php
/**
 * YPrint Minimalist Cart System
 * Vollständiges Warenkorb-System mit Design-Produkt-Support und automatischer Konsolidierung
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * KERN-FUNKTION: Warenkorb-Konsolidierung
 * Konsolidiert Warenkorb-Einträge, indem gleiche Produkte zusammengeführt werden
 * Diese Funktion entfernt doppelte Einträge des gleichen Produkts und addiert die Mengen
 * Berücksichtigt Print-Design als Unterscheidungsmerkmal - Design-Produkte werden NICHT konsolidiert
 *
 * @return bool True if changes were made, false otherwise.
 */
function yprint_consolidate_cart_items() {
    // Wenn WooCommerce nicht aktiv ist oder der Warenkorb leer ist, beenden
    if (!class_exists('WooCommerce') || is_null(WC()->cart) || WC()->cart->is_empty()) {
        return false;
    }

    $cart = WC()->cart->get_cart();
    $temp_grouping = array();
    $changes_made = false;

    // Gruppieren der Artikel
    foreach ($cart as $cart_item_key => $cart_item) {
        $product_id = $cart_item['product_id'];
        $variation_id = isset($cart_item['variation_id']) ? $cart_item['variation_id'] : 0;
        $variation_attributes = isset($cart_item['variation']) ? $cart_item['variation'] : array();

        // WICHTIG: Print-Design oder Design-Product-Flag macht ein Produkt einzigartig
        if (isset($cart_item['print_design']) || isset($cart_item['_is_design_product'])) {
            // Bei Design-Produkten jedes als einzigartiges Produkt behandeln und Konsolidierung überspringen
            continue;
        }

        // Sammle alle benutzerdefinierten Daten außer den Standard-Schlüsseln
        $custom_data = array();
        foreach ($cart_item as $key => $value) {
            if (!in_array($key, array('product_id', 'variation_id', 'variation', 'quantity', 'data', 'key'))) {
                $custom_data[$key] = $value;
            }
        }

        // Erstelle einen Hash für die Variationen, sortiert für Konsistenz
        ksort($variation_attributes);
        $variation_hash = md5(serialize($variation_attributes));
        
        // Erstelle einen Hash für benutzerdefinierte Daten
        $custom_data_hash = !empty($custom_data) ? md5(serialize($custom_data)) : '';

        // Unique Key für dieses Produkt/Variation/Custom-Data erstellen
        $unique_group_key = $product_id . '-' . $variation_id . '-' . $variation_hash;
        if (!empty($custom_data_hash)) {
            $unique_group_key .= '-' . $custom_data_hash;
        }

        if (isset($temp_grouping[$unique_group_key])) {
            // Produkt bereits in der Gruppe gesehen, Menge addieren und Schlüssel zum Entfernen vormerken
            $temp_grouping[$unique_group_key]['quantity'] += $cart_item['quantity'];
            $temp_grouping[$unique_group_key]['duplicate_keys'][] = $cart_item_key;
            $changes_made = true;
        } else {
            // Neues Produkt in der Gruppe
            $temp_grouping[$unique_group_key] = array(
                'main_cart_item_key' => $cart_item_key,
                'quantity' => $cart_item['quantity'],
                'duplicate_keys' => array()
            );
        }
    }

    // Aktualisieren und Entfernen von doppelten Einträgen
    if ($changes_made) {
        foreach ($temp_grouping as $group_data) {
            // Entferne die doppelten Einträge
            foreach ($group_data['duplicate_keys'] as $key_to_remove) {
                WC()->cart->remove_cart_item($key_to_remove);
            }

            // Aktualisiere die Menge des Hauptartikels, falls nötig
            if (!empty($group_data['duplicate_keys'])) {
                $main_key = $group_data['main_cart_item_key'];
                if (isset(WC()->cart->get_cart()[$main_key])) {
                    WC()->cart->set_quantity($main_key, $group_data['quantity'], true);
                }
            }
        }
        
        // Gesamtsummen einmalig am Ende neu berechnen
        WC()->cart->calculate_totals();
    }

    return $changes_made;
}

/**
 * AJAX-Handler zur Konsolidierung des Warenkorbs (Backend-Teil)
 */
function yprint_ajax_consolidate_cart_callback() {
    $changes = yprint_consolidate_cart_items();

    wp_send_json_success(array(
        'changes_made' => $changes,
    ));

    wp_die();
}
add_action('wp_ajax_yprint_consolidate_cart_action', 'yprint_ajax_consolidate_cart_callback');
add_action('wp_ajax_nopriv_yprint_consolidate_cart_action', 'yprint_ajax_consolidate_cart_callback');

/**
 * HAUPTFUNKTION: Minimalistischer Warenkorb-Shortcode
 * Erstellt einen minimalistischen WooCommerce-Warenkorb für Popup-Anzeige
 * 
 * Verwendung: [yprint_minimalist_cart]
 */
function yprint_minimalist_cart_shortcode() {
    ob_start();

    // Sicherstellen, dass WooCommerce aktiv ist
    if (!class_exists('WooCommerce') || is_null(WC()->cart)) {
        return '<p>WooCommerce ist nicht aktiviert.</p>';
    }

    // Eindeutige ID für diesen Warenkorb generieren (verhindert Konflikte bei mehreren Instanzen)
    $cart_id = 'yprint-cart-' . uniqid();

    // Warenkorb optimieren - gleiche Produkte zusammenführen VOR dem Rendern
    yprint_consolidate_cart_items();

    ?>
    <style>
        .yprint-mini-cart {
            font-family: 'Helvetica Neue', Arial, sans-serif;
            max-width: 100%;
            margin: 0 auto;
            position: relative;
            box-sizing: border-box;
            padding: 0;
            background-color: transparent;
            border-radius: 0;
            box-shadow: none;
        }
        .yprint-mini-cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .yprint-mini-cart-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #333;
        }
        .yprint-mini-cart-count {
            background: #0079FF;
            color: #fff;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            flex-shrink: 0;
            font-weight: 600;
        }
        .yprint-mini-cart-items {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 15px;
            padding-right: 10px;
        }
        .yprint-mini-cart-items::-webkit-scrollbar {
            width: 8px;
        }
        .yprint-mini-cart-items::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .yprint-mini-cart-items::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .yprint-mini-cart-items::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
        .yprint-mini-cart-item {
            display: flex;
            align-items: flex-start;
            padding: 15px 0;
            border-bottom: 1px solid #f5f5f5;
            position: relative;
        }
        .yprint-mini-cart-item:last-child {
            border-bottom: none;
        }
        .yprint-mini-cart-item-image {
            width: 80px;
            margin-right: 15px;
            flex-shrink: 0;
            border: 1px solid #eee;
            border-radius: 4px;
            overflow: hidden;
        }
        .yprint-mini-cart-item-image img {
            max-width: 100%;
            height: auto;
            display: block;
        }
        .yprint-mini-cart-item-details {
            flex-grow: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .yprint-mini-cart-item-title {
            font-size: 15px;
            margin: 0 0 5px;
            font-weight: 600;
            color: #333;
        }
        .yprint-mini-cart-item-price {
            font-size: 14px;
            font-weight: 600;
            color: #0079FF;
            margin-bottom: 10px;
        }
        .yprint-mini-cart-item-quantity-control {
            display: flex;
            align-items: center;
            border: 1px solid #ddd;
            border-radius: 4px;
            width: fit-content;
            overflow: hidden;
        }
        .qty-btn {
            background: #f9f9f9;
            border: none;
            padding: 5px 10px;
            color: #333;
            font-size: 16px;
            cursor: pointer;
            width: auto;
            height: auto;
            transition: background-color 0.2s ease;
            line-height: 1;
        }
        .qty-btn:hover {
            background: #eee;
            color: #000;
        }
        .qty-value {
            padding: 5px 8px;
            font-size: 14px;
            min-width: 20px;
            text-align: center;
            cursor: pointer;
            border-left: 1px solid #ddd;
            border-right: 1px solid #ddd;
            background: #fff;
            line-height: 1;
        }
        .qty-input {
            width: 30px;
            text-align: center;
            border: none;
            font-size: 14px;
            padding: 5px 0;
            display: none;
            box-sizing: border-box;
            line-height: 1;
        }
        .yprint-mini-cart-item-remove {
            position: absolute;
            top: 10px;
            right: 0;
            cursor: pointer;
            color: #999;
            font-size: 20px;
            line-height: 1;
            padding: 0;
            background: none;
            border: none;
            transition: color 0.2s ease;
        }
        .yprint-mini-cart-item-remove:hover {
            color: #FF4136;
        }
        .yprint-mini-cart-subtotal {
            display: flex;
            justify-content: space-between;
            padding: 15px 0 10px;
            font-weight: 600;
            border-top: 1px solid #eee;
            font-size: 16px;
            color: #333;
        }
        .yprint-mini-cart-subtotal span:last-child {
            color: #0079FF;
        }
        .yprint-mini-cart-checkout {
            display: block;
            width: 100%;
            background: #0079FF;
            color: #fff;
            border: none;
            padding: 12px 15px;
            font-size: 16px;
            font-weight: 600;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
            transition: background-color 0.3s ease;
            border-radius: 4px;
            margin-top: 15px;
        }
        .yprint-mini-cart-checkout:hover {
            background: #0062cc;
        }
        .yprint-mini-cart-empty {
            text-align: center;
            padding: 20px 0;
            color: #777;
            font-style: italic;
        }
        .yprint-loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(255, 255, 255, 0.8);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s ease;
            pointer-events: none;
            border-radius: 8px;
        }
        .yprint-loading-overlay.active {
            opacity: 1;
            visibility: visible;
            pointer-events: auto;
        }
        .yprint-loading-overlay img {
            width: 80px;
            height: auto;
            animation: pulse 1.5s infinite alternate;
        }
        @keyframes pulse {
            0% { opacity: 0.6; transform: scale(1); }
            100% { opacity: 1; transform: scale(1.05); }
        }
    </style>

    <div id="<?php echo esc_attr($cart_id); ?>" class="yprint-mini-cart">
        <div class="yprint-loading-overlay">
            <img src="https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg" alt="Loading...">
        </div>
        
        <div class="yprint-mini-cart-header">
            <h3 class="yprint-mini-cart-title">Dein Warenkorb</h3>
            <div class="yprint-mini-cart-count"><?php echo WC()->cart->get_cart_contents_count(); ?></div>
        </div>

        <div class="yprint-mini-cart-items">
            <?php
            $cart_items = WC()->cart->get_cart();

            if (empty($cart_items)) {
                echo '<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>';
            } else {
                foreach ($cart_items as $cart_item_key => $cart_item) {
                    $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
                    $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

                    if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                        $product_name = $_product->get_name();
                        $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                        $item_total_price_html = apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key);
                        $product_quantity = $cart_item['quantity'];

                        // Design-Produkt-Behandlung
                        if (isset($cart_item['print_design']) && !empty($cart_item['print_design']['name'])) {
                            $display_name = $cart_item['print_design']['name'];
                            
                            $details = [];
                            if (!empty($cart_item['print_design']['variation_name'])) {
                                $details[] = $cart_item['print_design']['variation_name'];
                            }
                            if (!empty($cart_item['print_design']['size_name'])) {
                                $details[] = $cart_item['print_design']['size_name'];
                            }
                            
                            if (!empty($details)) {
                                $display_name .= ' (' . implode(', ', $details) . ')';
                            }
                        } else {
                            $display_name = $product_name;
                        }
                        ?>
                        <div class="yprint-mini-cart-item" data-item-key="<?php echo esc_attr($cart_item_key); ?>">
                            <div class="yprint-mini-cart-item-image">
                                <?php echo $thumbnail; ?>
                            </div>
                            <div class="yprint-mini-cart-item-details">
                                <h4 class="yprint-mini-cart-item-title"><?php echo esc_html($display_name); ?></h4>
                                <div class="yprint-mini-cart-item-price"><?php echo $item_total_price_html; ?></div>
                                <div class="yprint-mini-cart-item-quantity-control">
                                    <button class="qty-btn qty-minus" data-action="minus">−</button>
                                    <span class="qty-value"><?php echo esc_html($product_quantity); ?></span>
                                    <input type="number" class="qty-input" value="<?php echo esc_attr($product_quantity); ?>" min="1">
                                    <button class="qty-btn qty-plus" data-action="plus">+</button>
                                </div>
                            </div>
                            <button class="yprint-mini-cart-item-remove" aria-label="Artikel entfernen">×</button>
                        </div>
                        <?php
                    }
                }
            }
            ?>
        </div>

        <div class="yprint-mini-cart-subtotal">
            <span>Zwischensumme:</span>
            <span class="cart-subtotal-value"><?php echo WC()->cart->get_cart_subtotal(); ?></span>
        </div>

        <a href="<?php echo esc_url(wc_get_checkout_url()); ?>" class="yprint-mini-cart-checkout">Zur Kasse</a>
    </div>

    <script>
    (function($) {
        var cartId = '<?php echo esc_js($cart_id); ?>';
        var $cart = $('#' + cartId);

        // Verhindere doppelte Initialisierung
        if ($cart.data('initialized')) {
            return;
        }
        $cart.data('initialized', true);

        var isEditing = false;

        // Loading Overlay Management
        function toggleLoading(show) {
            if (show) {
                $cart.find('.yprint-loading-overlay').addClass('active');
            } else {
                $cart.find('.yprint-loading-overlay').removeClass('active');
            }
        }

        // AJAX Warenkorb-Aktualisierung
        function refreshCartContent() {
            toggleLoading(true);

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_refresh_cart_content'
                },
                success: function(response) {
                    if (response.success) {
                        refreshCartContent();
                    } else {
                        console.error('Fehler bei der Warenkorb-Konsolidierung:', response);
                        toggleLoading(false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Fehler bei der Warenkorb-Konsolidierung:', status, error);
                    toggleLoading(false);
                }
            });
        });

        // ENTFERNEN-BUTTON Event Handler
        $cart.on('click', '.yprint-mini-cart-item-remove', function() {
            var $item = $(this).closest('.yprint-mini-cart-item');
            var cartItemKey = $item.data('item-key');

            if (!cartItemKey) {
                console.error('Artikel-Schlüssel nicht gefunden.');
                return;
            }

            toggleLoading(true);

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_remove_from_cart',
                    cart_item_key: cartItemKey
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $item.fadeOut(300, function() {
                            $(this).remove();
                            $cart.find('.yprint-mini-cart-count').text(response.data.cart_count);
                            $cart.find('.yprint-mini-cart-subtotal .cart-subtotal-value').html(response.data.cart_subtotal);

                            if (response.data.cart_count === 0) {
                                $cart.find('.yprint-mini-cart-items').html('<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>');
                            }

                            $(document.body).trigger('yprint_mini_cart_item_removed', [cartItemKey]);
                        });
                    } else {
                        console.error('Fehler beim Entfernen:', response.data);
                        alert('Der Artikel konnte nicht entfernt werden.');
                    }
                    toggleLoading(false);
                },
                error: function(xhr, status, error) {
                    console.error('AJAX-Fehler beim Entfernen:', status, error);
                    alert('Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.');
                    toggleLoading(false);
                }
            });
        });

        // PLUS/MINUS BUTTONS Event Handler
        $cart.on('click', '.qty-btn', function() {
            if (isEditing) return;

            var $btn = $(this);
            var $item = $btn.closest('.yprint-mini-cart-item');
            var $qtyValue = $item.find('.qty-value');
            var cartItemKey = $item.data('item-key');
            
            if (!cartItemKey) {
                console.error('Kein cart-item-key gefunden!');
                return;
            }
            
            var currentQty = parseInt($qtyValue.text());
            if (isNaN(currentQty)) {
                currentQty = 1;
            }
            
            var newQty = $btn.data('action') === 'minus' ?
                        Math.max(1, currentQty - 1) :
                        currentQty + 1;

            if (newQty === currentQty) return;

            $btn.prop('disabled', true);
            $qtyValue.text(newQty);
            $item.find('.qty-input').val(newQty);

            updateQuantity(cartItemKey, newQty, $item);
            
            setTimeout(function() {
                $btn.prop('disabled', false);
            }, 500);
        });

        // DIREKTEINGABE DER MENGE
        $cart.on('click', '.qty-value', function() {
            if (isEditing) return;

            isEditing = true;
            var $value = $(this);
            var $input = $value.siblings('.qty-input');
            var $item = $value.closest('.yprint-mini-cart-item');

            $input.val($value.text());
            $value.hide();
            $input.show().focus().select();
            $item.addClass('editing-quantity');
        });

        // Eingabefeld verlassen (Blur)
        $cart.on('blur', '.qty-input', function() {
            var $input = $(this);
            var $item = $input.closest('.yprint-mini-cart-item');
            var $value = $input.siblings('.qty-value');
            var cartItemKey = $item.data('item-key');
            var oldQty = parseInt($value.text());
            var newQty = parseInt($input.val());

            if (isNaN(newQty) || newQty < 1) {
                newQty = oldQty;
                $input.val(oldQty);
            }

            $input.hide();
            $value.text(newQty).show();
            isEditing = false;
            $item.removeClass('editing-quantity');

            if (newQty !== oldQty) {
                updateQuantity(cartItemKey, newQty, $item);
            }
        });

        // Enter-Taste im Eingabefeld
        $cart.on('keypress', '.qty-input', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $(this).blur();
            }
        });

        // Klick außerhalb schließt Eingabefeld
        $(document).on('mousedown', function(e) {
            if (isEditing) {
                var $target = $(e.target);
                if (!$target.closest('.qty-input').length && !$target.closest('.qty-value').length && !$target.closest('.qty-btn').length) {
                    $cart.find('.qty-input:visible').blur();
                }
            }
        });

        // Menge aktualisieren Funktion
        function updateQuantity(cartItemKey, quantity, $item_element) {
            toggleLoading(true);
            
            if($item_element) {
                $item_element.find('.qty-value').text(quantity);
                $item_element.find('.qty-input').val(quantity);
            }

            $.ajax({
                url: wc_add_to_cart_params.ajax_url,
                type: 'POST',
                data: {
                    action: 'yprint_update_cart_quantity',
                    cart_item_key: cartItemKey,
                    quantity: quantity
                },
                success: function(response) {
                    if (response.success) {
                        refreshCartContent();
                    } else {
                        console.error('Fehler beim Aktualisieren der Menge:', response.data);
                        toggleLoading(false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Fehler beim Aktualisieren der Menge:', status, error);
                    toggleLoading(false);
                }
            });
        }

    })(jQuery);
    </script>
    <?php

    return ob_get_clean();
}

/**
 * AJAX-Handler: Artikel aus Warenkorb entfernen
 */
function yprint_remove_from_cart() {
    $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';

    if (empty($cart_item_key)) {
        wp_send_json_error('Kein Artikel-Schlüssel angegeben');
        return;
    }
    
    if (is_null(WC()->cart)) {
        wp_send_json_error('Warenkorb nicht initialisiert');
        return;
    }
    
    if (WC()->cart->remove_cart_item($cart_item_key)) {
        WC()->cart->calculate_totals();

        wp_send_json_success(array(
            'cart_count' => WC()->cart->get_cart_contents_count(),
            'cart_subtotal' => WC()->cart->get_cart_subtotal(),
            'message' => 'Artikel erfolgreich entfernt'
        ));
    } else {
        wp_send_json_error('Artikel konnte nicht entfernt werden');
    }
}
add_action('wp_ajax_yprint_remove_from_cart', 'yprint_remove_from_cart');
add_action('wp_ajax_nopriv_yprint_remove_from_cart', 'yprint_remove_from_cart');

/**
 * AJAX-Handler: Artikel-Menge im Warenkorb aktualisieren
 */
function yprint_update_cart_quantity() {
    global $wpdb;
    $wpdb->show_errors(); 
    
    try {
        $cart_item_key = isset($_POST['cart_item_key']) ? sanitize_text_field($_POST['cart_item_key']) : '';
        $quantity = isset($_POST['quantity']) ? intval($_POST['quantity']) : 0;
        
        if (empty($cart_item_key) || $quantity <= 0) {
            wp_send_json_error(array('message' => 'Ungültige Parameter'));
            return;
        }
        
        if (!function_exists('WC') || is_null(WC()->cart)) {
            wp_send_json_error(array('message' => 'WooCommerce nicht verfügbar'));
            return;
        }
        
        WC()->cart->set_quantity($cart_item_key, $quantity, true);
        WC()->cart->calculate_totals();
        
        wp_send_json_success(array(
            'cart_subtotal' => WC()->cart->get_cart_subtotal(),
            'cart_count' => WC()->cart->get_cart_contents_count()
        ));
        
    } catch (Exception $e) {
        error_log('YPrint Cart Error: ' . $e->getMessage());
        wp_send_json_error(array('message' => 'Ein Fehler ist aufgetreten'));
    }
}
add_action('wp_ajax_yprint_update_cart_quantity', 'yprint_update_cart_quantity');
add_action('wp_ajax_nopriv_yprint_update_cart_quantity', 'yprint_update_cart_quantity');

/**
 * AJAX-Handler: Warenkorb-Inhalt aktualisieren (Frontend-HTML)
 */
function yprint_refresh_cart_content_callback() {
    ob_start();

    if (is_null(WC()->cart)) {
        wp_send_json_error('WooCommerce cart is not initialized.');
        wp_die();
    }

    // Warenkorb optimieren VOR dem Generieren des HTML
    yprint_consolidate_cart_items();

    $cart_items = WC()->cart->get_cart();

    if (empty($cart_items)) {
        echo '<div class="yprint-mini-cart-empty">Dein Warenkorb ist leer.</div>';
    } else {
        foreach ($cart_items as $cart_item_key => $cart_item) {
            $_product = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

            if ($_product && $_product->exists() && $cart_item['quantity'] > 0) {
                $product_name = $_product->get_name();
                $thumbnail = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                $item_total_price_html = apply_filters('woocommerce_cart_item_subtotal', WC()->cart->get_product_subtotal($_product, $cart_item['quantity']), $cart_item, $cart_item_key);
                $product_quantity = $cart_item['quantity'];

                // Design-Produkt-Behandlung
                if (isset($cart_item['print_design']) && !empty($cart_item['print_design']['name'])) {
                    $display_name = $cart_item['print_design']['name'];
                    
                    $details = [];
                    if (!empty($cart_item['print_design']['variation_name'])) {
                        $details[] = $cart_item['print_design']['variation_name'];
                    }
                    if (!empty($cart_item['print_design']['size_name'])) {
                        $details[] = $cart_item['print_design']['size_name'];
                    }
                    
                    if (!empty($details)) {
                        $display_name .= ' (' . implode(', ', $details) . ')';
                    }
                } else {
                    $display_name = $product_name;
                }
                ?>
                <div class="yprint-mini-cart-item" data-item-key="<?php echo esc_attr($cart_item_key); ?>">
                    <div class="yprint-mini-cart-item-image">
                        <?php echo $thumbnail; ?>
                    </div>
                    <div class="yprint-mini-cart-item-details">
                        <h4 class="yprint-mini-cart-item-title"><?php echo esc_html($display_name); ?></h4>
                        <div class="yprint-mini-cart-item-price"><?php echo $item_total_price_html; ?></div>
                        <div class="yprint-mini-cart-item-quantity-control">
                            <button class="qty-btn qty-minus" data-action="minus">−</button>
                            <span class="qty-value"><?php echo esc_html($product_quantity); ?></span>
                            <input type="number" class="qty-input" value="<?php echo esc_attr($product_quantity); ?>" min="1">
                            <button class="qty-btn qty-plus" data-action="plus">+</button>
                        </div>
                    </div>
                    <button class="yprint-mini-cart-item-remove" aria-label="Artikel entfernen">×</button>
                </div>
                <?php
            }
        }
    }

    $cart_items_html = ob_get_clean();

    wp_send_json(array(
        'success' => true,
        'cart_items_html' => $cart_items_html,
        'cart_count' => WC()->cart->get_cart_contents_count(),
        'cart_subtotal' => WC()->cart->get_cart_subtotal()
    ));

    wp_die();
}
add_action('wp_ajax_yprint_refresh_cart_content', 'yprint_refresh_cart_content_callback');
add_action('wp_ajax_nopriv_yprint_refresh_cart_content', 'yprint_refresh_cart_content_callback');

/**
 * Hook: Verhindert doppelte Artikel beim Laden aus der Session
 */
function yprint_cart_loaded_from_session_and_ajax($cart) {
    if (is_null($cart)) {
        return;
    }

    yprint_consolidate_cart_items();
    $cart->calculate_totals();
}
add_action('woocommerce_cart_loaded_from_session', 'yprint_cart_loaded_from_session_and_ajax', 10);

/**
 * Hook: Nach dem Hinzufügen zum Warenkorb
 */
add_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20, 6);
function yprint_after_add_to_cart($cart_item_key, $product_id, $quantity, $variation_id, $variation, $cart_item_data) {
    static $is_processing = false;
    
    if ($is_processing) {
        return;
    }
    
    $is_processing = true;
    remove_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20);
    
    $has_print_design = isset($cart_item_data['print_design']);
    
    if (!$has_print_design) {
        yprint_consolidate_cart_items();
    } else {
        $current_cart_item = WC()->cart->get_cart_item($cart_item_key);
        if ($current_cart_item && isset($current_cart_item['print_design'])) {
            WC()->cart->cart_contents[$cart_item_key]['_is_design_product'] = true;
            
            if (isset($current_cart_item['print_design']['design_id'])) {
                $unique_design_key = md5(wp_json_encode($current_cart_item['print_design']));
                WC()->cart->cart_contents[$cart_item_key]['unique_design_key'] = $unique_design_key;
            }
            
            WC()->cart->set_session();
        }
    }
    
    add_action('woocommerce_add_to_cart', 'yprint_after_add_to_cart', 20, 6);
    do_action('yprint_cart_updated');
    $is_processing = false;
}

/**
 * DESIGN-PRODUKTE: Session-Management
 */
add_filter('woocommerce_get_cart_item_from_session', 'yprint_get_cart_item_from_session', 10, 2);
function yprint_get_cart_item_from_session($cart_item, $values) {
    if (isset($values['print_design'])) {
        $cart_item['print_design'] = $values['print_design'];
        $cart_item['_is_design_product'] = true;
        
        if (isset($values['unique_design_key'])) {
            $cart_item['unique_design_key'] = $values['unique_design_key'];
        } else {
            $cart_item['unique_design_key'] = md5(wp_json_encode($values['print_design']));
        }
        
        if (isset($values['original_price'])) {
            $cart_item['original_price'] = $values['original_price'];
        }
        
        if (isset($values['print_design']['calculated_price']) && !empty($values['print_design']['calculated_price'])) {
            $product = $cart_item['data'];
            
            if (!isset($cart_item['original_price'])) {
                $cart_item['original_price'] = $product->get_price();
            }
            
            $product->set_price($values['print_design']['calculated_price']);
        }
    }
    
    return $cart_item;
}

/**
 * DESIGN-PRODUKTE: Preis-Management bei Mengenänderungen
 */
add_action('woocommerce_before_calculate_totals', 'yprint_before_calculate_totals', 10, 1);
function yprint_before_calculate_totals($cart) {
    if (is_admin() && !defined('DOING_AJAX')) {
        return;
    }
    
    if (did_action('woocommerce_before_calculate_totals') >= 2) {
        return;
    }
    
    foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
        if (isset($cart_item['print_design']) && isset($cart_item['original_price'])) {
            $product = $cart_item['data'];
            
            if (isset($cart_item['print_design']['calculated_price']) && !empty($cart_item['print_design']['calculated_price'])) {
                $product->set_price($cart_item['print_design']['calculated_price']);
            } else {
                $product->set_price($cart_item['original_price']);
            }
        }
    }
}

/**
 * DESIGN-PRODUKTE: Checkout-Integration
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_add_design_data_to_order_item', 10, 4);
function yprint_add_design_data_to_order_item($item, $cart_item_key, $values, $order) {
    if (isset($values['print_design']) && !empty($values['print_design'])) {
        $design = $values['print_design'];
        
        foreach ($design as $meta_key => $meta_value) {
            if (is_array($meta_value) || is_object($meta_value)) {
                $meta_value = wp_json_encode($meta_value);
            }
            $item->add_meta_data('_design_' . $meta_key, $meta_value);
        }
        
        $item->add_meta_data('_design_id', $design['design_id'] ?? '');
        $item->add_meta_data('_design_name', $design['name'] ?? '');
        $item->add_meta_data('_design_color', $design['variation_name'] ?? '');
        $item->add_meta_data('_design_size', $design['size_name'] ?? '');
        $item->add_meta_data('_design_preview_url', $design['preview_url'] ?? '');
        $item->add_meta_data('_has_print_design', 'yes');
        $item->add_meta_data('print_design', $design);
        
        $item->save();
    }
}

/**
 * DESIGN-PRODUKTE: Zusätzliche Checkout-Persistierung
 */
add_filter('woocommerce_checkout_create_order_line_item', 'yprint_preserve_design_data_in_order', 20, 4);
function yprint_preserve_design_data_in_order($item, $cart_item_key, $values, $order) {
    if (isset($values['print_design']) && !empty($values['print_design'])) {
        $item->update_meta_data('print_design', $values['print_design']);
        $item->update_meta_data('_is_design_product', true);
        
        foreach ($values['print_design'] as $key => $value) {
            if (is_array($value) || is_object($value)) {
                $value = wp_json_encode($value);
            }
            $item->update_meta_data('design_' . $key, $value);
        }
    }
}

/**
 * DESIGN-PRODUKTE: Warenkorb-Anzeige anpassen
 */
add_filter('woocommerce_cart_item_name', 'yprint_modify_cart_item_name', 10, 3);
function yprint_modify_cart_item_name($name, $cart_item, $cart_item_key) {
    if (isset($cart_item['print_design'])) {
        $design = $cart_item['print_design'];
        
        if (!empty($design['name'])) {
            $name = '<span class="design-name">' . esc_html($design['name']) . '</span>';
        }
        
        $details = [];
        if (!empty($design['variation_name'])) {
            $details[] = esc_html($design['variation_name']);
        }
        if (!empty($design['size_name'])) {
            $details[] = esc_html($design['size_name']);
        }
        
        if (!empty($details)) {
            $name .= ' <span class="design-details">(' . implode(', ', $details) . ')</span>';
        }
    }
    
    return $name;
}

/**
 * DESIGN-PRODUKTE: Bestellungsanzeige
 */
add_action('woocommerce_order_item_meta_end', 'yprint_display_design_data_in_order', 10, 3);
function yprint_display_design_data_in_order($item_id, $item, $order) {
    if ($item->get_meta('_has_print_design') === 'yes') {
        echo '<div class="print-design-info" style="margin-top: 10px; padding-top: 10px; border-top: 1px dotted #ccc;">';
        echo '<p><strong>Design-Details:</strong></p>';
        
        $design_name = $item->get_meta('_design_name');
        if (!empty($design_name)) {
            echo '<p>Name: ' . esc_html($design_name) . '</p>';
        }
        
        $design_color = $item->get_meta('_design_color');
        if (!empty($design_color)) {
            echo '<p>Farbe: ' . esc_html($design_color) . '</p>';
        }
        
        $design_size = $item->get_meta('_design_size');
        if (!empty($design_size)) {
            echo '<p>Größe: ' . esc_html($design_size) . '</p>';
        }
        
        $preview_url = $item->get_meta('_design_preview_url');
        if (!empty($preview_url)) {
            echo '<p><img src="' . esc_url($preview_url) . '" style="max-width: 100px; height: auto;" alt="Vorschau"></p>';
        }
        
        echo '</div>';
    }
}

/**
 * DESIGN-PRODUKTE: E-Mail-Integration
 */
add_action('woocommerce_email_order_item_meta', 'yprint_display_design_data_in_email', 10, 4);
function yprint_display_design_data_in_email($item_id, $item, $order, $plain_text) {
    if ($plain_text) {
        return;
    }
    
    if ($item->get_meta('_has_print_design') === 'yes') {
        echo '<div style="margin-top: 10px; padding-top: 10px; border-top: 1px dotted #ccc;">';
        echo '<p><strong>Design-Details:</strong></p>';
        
        $design_name = $item->get_meta('_design_name');
        if (!empty($design_name)) {
            echo '<p>Name: ' . esc_html($design_name) . '</p>';
        }
        
        $design_color = $item->get_meta('_design_color');
        if (!empty($design_color)) {
            echo '<p>Farbe: ' . esc_html($design_color) . '</p>';
        }
        
        $design_size = $item->get_meta('_design_size');
        if (!empty($design_size)) {
            echo '<p>Größe: ' . esc_html($design_size) . '</p>';
        }
        
        echo '</div>';
    }
}

// Shortcode registrieren
add_shortcode('yprint_minimalist_cart', 'yprint_minimalist_cart_shortcode');
