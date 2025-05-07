<?php
/**
 * WooCommerce integration functions for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Display order history for a customer with search and cancel functionality
 * 
 * Usage: [woocommerce_history order_count="-1"]
 * 
 * @param array $atts Shortcode attributes
 * @return string HTML output of order history
 */
function woo_order_history($atts) {
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        return '<p>WooCommerce ist nicht aktiviert.</p>';
    }

    extract(shortcode_atts(array(
        'order_count' => -1
    ), $atts));

    ob_start();
    $customer_id = get_current_user_id();
    
    // If user is not logged in, show login message
    if ($customer_id === 0) {
        return '<p>Bitte <a href="' . esc_url(get_permalink(get_option('woocommerce_myaccount_page_id'))) . '">melde dich an</a>, um deine Bestellungen zu sehen.</p>';
    }
    
    $all_statuses = array_keys(wc_get_order_statuses());
    
    $customer_orders = wc_get_orders(array(
        'customer' => $customer_id,
        'limit'    => $order_count,
        'type'     => 'shop_order',
        'status'   => $all_statuses,
    ));
    
    ?>
    <style>
        .yprint-order-history {
            font-family: 'SF Pro Display', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
        }

        .yprint-order-content {
            width: 100%;
        }

        .yprint-order-search {
            width: 100%;
            padding: 12px 35px 12px 15px;
            background-color: #FFFFFF;
            border: 1px solid #e0e0e0 !important;
            border-radius: 15px !important;  /* Wichtig: !important hinzugefügt */
            margin-bottom: 15px;
            font-size: 16px;
            transition: all 0.2s ease;
            color: #333;
            box-sizing: border-box;  /* Stellt sicher, dass Padding nicht die Breite verändert */
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='18' height='18' viewBox='0 0 24 24' fill='none' stroke='%23999' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 12px center;
        }

        .yprint-order-search:focus {
            outline: none;
            border-color: #0079FF;
            box-shadow: 0 0 0 2px rgba(0, 121, 255, 0.1);
        }

        .yprint-order-list {
            display: grid;
            gap: 15px;
        }

        .yprint-order-card {
            padding: 15px 0;
            display: grid;
            grid-template-columns: auto 1fr auto;
            gap: 20px;
            align-items: center;
        }
        
        .yprint-order-card:not(:last-child) {
            border-bottom: 1px solid #f0f0f0;
        }

        .yprint-order-number {
            font-weight: 600;
            color: #333;
        }

        .yprint-order-meta {
            color: #888;
            font-size: 14px;
        }

        .yprint-order-details {
            display: grid;
            gap: 8px;
        }

        .yprint-order-price {
            font-weight: 600;
            color: #333;
        }

        .yprint-order-status {
            display: inline-block;
            font-weight: 500;
            font-size: 13px;
            padding: 4px 10px;
            border-radius: 20px;
            background-color: #e8f5fd;
            color: #0079FF;
        }

        .yprint-order-actions {
            display: flex;
            gap: 10px;
        }

        .yprint-order-btn {
            padding: 8px 15px;
            border-radius: 20px;
            text-decoration: none;
            text-align: center;
            font-weight: 500;
            font-size: 14px;
            border: none;
            cursor: pointer;
            transition: all 0.2s;
        }

        .yprint-btn-support {
            background-color: #f5f5f7;
            color: #333;
        }

        .yprint-btn-support:hover {
            background-color: #e5e5e7;
        }

        .yprint-btn-cancel {
            background-color: #ffeaee;
            color: #ff3b50;
        }

        .yprint-btn-cancel:hover {
            background-color: #ffe0e5;
        }

        .yprint-no-orders {
            text-align: center;
            padding: 40px 0;
            color: #888;
            font-size: 16px;
        }

        @media (max-width: 768px) {
            .yprint-checkout-columns {
                flex-direction: column;
            }
            
            .yprint-checkout-container {
                padding: 15px;
                min-height: auto;
            }
            
            .yprint-checkout-section {
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            .yprint-section-title {
                font-size: 20px;
                margin-bottom: 15px;
            }
            
            .yprint-payment-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
                gap: 10px;
            }
            
            .yprint-form-row {
                flex-direction: column;
                gap: 10px;
            }
            
            .yprint-form-row input,
            .yprint-form-row select {
                width: 100%;
            }
            
            .yprint-back-button-container {
                margin-bottom: 15px;
            }
        }

        @media (max-width: 480px) {
            .yprint-address-slot {
                min-width: 100%;
            }
            
            .yprint-payment-grid {
                grid-template-columns: 1fr;
            }
            
            .yprint-order-item {
                flex-wrap: wrap;
            }
            
            .yprint-item-image {
                width: 50px;
                height: 50px;
            }
            
            .yprint-item-details {
                flex: 0 0 calc(100% - 60px);
            }
            
            .yprint-item-total {
                margin-top: 8px;
                margin-left: auto;
            }
        }
    </style>

    <div class="yprint-order-history">
        <div class="yprint-order-content">
            <input 
                type="text" 
                id="orderSearch" 
                class="yprint-order-search" 
                placeholder="Bestellung suchen..."
            >

            <?php if (!empty($customer_orders)) : ?>
                <div class="yprint-order-list" id="orderList">
                    <?php foreach ($customer_orders as $order): 
                        $order_data = $order->get_data();
                        $order_date = date_i18n('d.m.Y', strtotime($order_data['date_created']));
                        $order_time = date_i18n('H:i', strtotime($order_data['date_created']));
                        $price = wc_price($order_data['total']);
                        $status = $order->get_status();
                    ?>
                    <div class="yprint-order-card" data-order-id="<?php echo $order->get_id(); ?>">
                        <div>
                            <div class="yprint-order-number">
                                Bestellung #<?php echo $order->get_order_number(); ?>
                            </div>
                            <div class="yprint-order-meta">
                                <?php echo $order_date; ?> | <?php echo $order_time; ?>
                            </div>
                        </div>

                        <div class="yprint-order-details">
                            <div class="yprint-order-price"><?php echo $price; ?></div>
                            <div class="yprint-order-status">
                                <?php echo wc_get_order_status_name($status); ?>
                            </div>
                        </div>

                        <div class="yprint-order-actions">
                            <a href="mailto:info@yprint.de?subject=Support-Anfrage für Bestellung <?php echo $order->get_order_number(); ?>" 
                               class="yprint-order-btn yprint-btn-support">
                                Support
                            </a>

                            <?php if (in_array($status, array('pending', 'processing', 'on-hold'))): ?>
                                <button 
                                    class="yprint-order-btn yprint-btn-cancel" 
                                    onclick="cancelOrder(<?php echo $order->get_id(); ?>)">
                                    Stornieren
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <div class="yprint-no-orders">
                    Du hast noch keine Bestellungen.
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('orderSearch');
        const orderList = document.getElementById('orderList');

        if (searchInput && orderList) {
            searchInput.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const orderCards = orderList.querySelectorAll('.yprint-order-card');

                orderCards.forEach(card => {
                    const orderText = card.textContent.toLowerCase();
                    card.style.display = orderText.includes(searchTerm) ? 'grid' : 'none';
                });
            });
        }
    });

    function cancelOrder(orderId) {
        if (confirm('Möchtest du diese Bestellung wirklich stornieren?')) {
            jQuery.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'yprint_cancel_order',
                    order_id: orderId,
                    security: '<?php echo wp_create_nonce('yprint-order-cancel'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Bestellung wurde erfolgreich storniert.');
                        location.reload();
                    } else {
                        alert('Fehler beim Stornieren der Bestellung: ' + response.data);
                    }
                },
                error: function() {
                    alert('Es ist ein Fehler aufgetreten. Bitte versuche es später erneut.');
                }
            });
        }
    }
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('woocommerce_history', 'woo_order_history');

/**
 * AJAX handler for cancelling an order
 * Validates the user session, order ownership, and allowed statuses
 */
function yprint_cancel_order() {
    // Check nonce for security
    check_ajax_referer('yprint-order-cancel', 'security');
    
    // Check if WooCommerce is active
    if (!class_exists('WooCommerce')) {
        wp_send_json_error('WooCommerce ist nicht aktiviert');
        return;
    }
    
    $order_id = isset($_POST['order_id']) ? intval($_POST['order_id']) : 0;
    $user_id = get_current_user_id();
    
    // Check if user is logged in
    if ($user_id === 0) {
        wp_send_json_error('Du musst angemeldet sein, um Bestellungen zu stornieren');
        return;
    }
    
    // Check if order ID is provided
    if (!$order_id) {
        wp_send_json_error('Keine Bestellnummer angegeben');
        return;
    }
    
    // Get order object
    $order = wc_get_order($order_id);
    
    // Check if order exists
    if (!$order) {
        wp_send_json_error('Bestellung nicht gefunden');
        return;
    }
    
    // Check if order belongs to current user
    if ($order->get_customer_id() !== $user_id) {
        wp_send_json_error('Du bist nicht berechtigt, diese Bestellung zu stornieren');
        return;
    }
    
    // Check if order status is cancellable
    $cancellable_statuses = array('pending', 'processing', 'on-hold');
    if (!in_array($order->get_status(), $cancellable_statuses)) {
        wp_send_json_error('Diese Bestellung kann nicht mehr storniert werden');
        return;
    }
    
    // Update order status to cancelled
    $order->update_status('cancelled', 'Bestellung vom Kunden storniert');
    
    // Restock items
    foreach ($order->get_items() as $item_id => $item) {
        $product = $item->get_product();
        if ($product && $product->managing_stock()) {
            $item_quantity = $item->get_quantity();
            wc_update_product_stock($product, $item_quantity, 'increase');
        }
    }
    
    // Log the cancellation
    $order->add_order_note('Bestellung wurde vom Kunden über die Website storniert.', false, true);
    
    do_action('yprint_order_cancelled_by_customer', $order_id);
    
    wp_send_json_success('Bestellung erfolgreich storniert');
}
add_action('wp_ajax_yprint_cancel_order', 'yprint_cancel_order');