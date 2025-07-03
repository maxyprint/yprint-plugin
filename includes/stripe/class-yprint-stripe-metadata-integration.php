<?php
/**
 * YPrint Stripe Metadata Integration
 * 
 * Displays correct addresses in Stripe dashboard via metadata
 */

class YPrint_Stripe_Metadata_Integration {
    
    public function __construct() {
        add_action('woocommerce_payment_complete', [$this, 'update_stripe_payment_intent_metadata'], 20, 1);
    }
    
    /**
     * Update Stripe Payment Intent with orchestrated addresses
     */
    public function update_stripe_payment_intent_metadata($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }
        
        // Get Payment Intent ID
        $payment_intent_id = $order->get_meta('_stripe_intent_id');
        if (!$payment_intent_id) {
            error_log('YPrint Stripe Metadata: No Payment Intent ID found for Order #' . $order_id);
            return;
        }
        
        // Get orchestrated addresses
        $final_shipping = $order->get_meta('_stripe_display_shipping_address');
        $final_billing = $order->get_meta('_stripe_display_billing_address');
        
        if (empty($final_shipping) || empty($final_billing)) {
            error_log('YPrint Stripe Metadata: No orchestrated addresses found for Order #' . $order_id);
            return;
        }
        
        try {
            // Initialize Stripe
            if (!class_exists('YPrint_Stripe_Service')) {
                return;
            }
            
            $stripe_service = YPrint_Stripe_Service::get_instance();
            $stripe = $stripe_service->get_stripe_client();
            
            // Update Payment Intent metadata
            $stripe->paymentIntents->update($payment_intent_id, [
                'metadata' => [
                    'yprint_final_shipping_address' => $final_shipping['address_1'] . ', ' . $final_shipping['postcode'] . ' ' . $final_shipping['city'],
                    'yprint_final_billing_address' => $final_billing['address_1'] . ', ' . $final_billing['postcode'] . ' ' . $final_billing['city'],
                    'yprint_address_source' => 'AddressOrchestrator',
                    'yprint_original_note' => 'Payment Method shows original data - these are the final processed addresses'
                ]
            ]);
            
            error_log('YPrint Stripe Metadata: Successfully updated Payment Intent metadata for Order #' . $order_id);
            
        } catch (Exception $e) {
            error_log('YPrint Stripe Metadata: Error updating Payment Intent: ' . $e->getMessage());
        }
    }
}

// Initialize
new YPrint_Stripe_Metadata_Integration();