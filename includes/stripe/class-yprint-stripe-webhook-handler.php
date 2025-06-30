<?php
/**
 * Stripe Webhook Handler Class
 * 
 * Handles webhook notifications from Stripe
 *
 * @package YPrint_Plugin
 * @subpackage Stripe
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * YPrint Stripe Webhook Handler Class
 */
class YPrint_Stripe_Webhook_Handler {

    /**
     * Stripe webhook secret key
     *
     * @var string
     */
    private $webhook_secret;

    /**
     * Instance of this class.
     *
     * @var YPrint_Stripe_Webhook_Handler
     */
    protected static $instance = null;

    /**
     * Get the singleton instance of this class
     *
     * @return YPrint_Stripe_Webhook_Handler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $options = YPrint_Stripe_API::get_stripe_settings();
        $gateway_settings = get_option('woocommerce_yprint_stripe_settings', array());
        
        // Get the webhook secret from payment gateway settings
        $this->webhook_secret = isset($gateway_settings['webhook_secret']) ? $gateway_settings['webhook_secret'] : '';
    }

    /**
     * Handle Stripe webhook request
     */
    public function handle_webhook() {
        error_log('Stripe webhook received');
        
        // Check if this is a webhook request
        if (!isset($_SERVER['REQUEST_METHOD']) || 'POST' !== $_SERVER['REQUEST_METHOD']) {
            error_log('Invalid webhook request - not a POST request');
            status_header(400);
            exit;
        }

        // Get the request body
        $request_body = file_get_contents('php://input');
        if (empty($request_body)) {
            error_log('Invalid webhook request - empty body');
            status_header(400);
            exit;
        }

        // Get the webhook signature
        $signature = isset($_SERVER['HTTP_STRIPE_SIGNATURE']) ? sanitize_text_field($_SERVER['HTTP_STRIPE_SIGNATURE']) : '';
        if (empty($signature)) {
            error_log('Invalid webhook request - no signature');
            status_header(400);
            exit;
        }

        // If webhook secret is set, verify the signature
        if (!empty($this->webhook_secret)) {
            try {
                $this->verify_webhook_signature($request_body, $signature);
            } catch (Exception $e) {
                error_log('Webhook signature verification failed: ' . $e->getMessage());
                status_header(401);
                exit;
            }
        } else {
            error_log('Webhook secret not configured - skipping signature verification');
        }

        // Parse the webhook payload
        $event = json_decode($request_body);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($event->type)) {
            error_log('Webhook payload parsing failed');
            status_header(400);
            exit;
        }

        // Process the webhook event
        $this->process_webhook_event($event);

        // Return a 200 response to acknowledge receipt of the webhook
        status_header(200);
        exit;
    }

    /**
     * Verify webhook signature
     *
     * @param string $payload The webhook payload
     * @param string $signature The webhook signature
     * @throws Exception If signature verification fails
     */
    private function verify_webhook_signature($payload, $signature) {
        // Verify the signature
        $timestamp = null;
        $signed_payload = null;
        
        $signature_parts = explode(',', $signature);
        
        foreach ($signature_parts as $part) {
            $key_val = explode('=', $part);
            if (count($key_val) != 2) {
                continue;
            }
            
            if ($key_val[0] == 't') {
                $timestamp = intval($key_val[1]);
            }
            
            if (strpos($key_val[0], 'v1') === 0) {
                $signed_payload = $key_val[1];
            }
        }
        
        if (!$timestamp || !$signed_payload) {
            throw new Exception('Invalid signature format');
        }
        
        // Check timestamp is not too old
        if (time() - $timestamp > 300) { // 5 minutes
            throw new Exception('Timestamp too old');
        }
        
        // Generate the expected signature
        $expected_signature = hash_hmac('sha256', $timestamp . '.' . $payload, $this->webhook_secret);
        
        // Compare the signatures
        if ($expected_signature !== $signed_payload) {
            throw new Exception('Signature verification failed');
        }
    }

    /**
     * Process webhook event
     *
     * @param object $event The webhook event
     */
    private function process_webhook_event($event) {
        error_log('Processing webhook event: ' . $event->type);
        
        switch ($event->type) {
            case 'payment_intent.succeeded':
                $this->process_payment_intent_succeeded($event->data->object);
                break;
                
            case 'payment_intent.payment_failed':
                $this->process_payment_intent_failed($event->data->object);
                break;
                
            case 'charge.succeeded':
                $this->process_charge_succeeded($event->data->object);
                break;
                
            case 'charge.failed':
                $this->process_charge_failed($event->data->object);
                break;
                
            case 'charge.refunded':
                $this->process_charge_refunded($event->data->object);
                break;
                
            default:
                error_log('Unhandled webhook event type: ' . $event->type);
                break;
        }
    }

    /**
 * Process payment intent succeeded event
 *
 * @param object $payment_intent The payment intent object
 */
private function process_payment_intent_succeeded($payment_intent) {
    error_log('Processing payment intent succeeded: ' . $payment_intent->id);
    
    // Find the order by metadata
    $order = $this->get_order_from_intent($payment_intent);
    
    if (!$order) {
        error_log('Could not find order for payment intent: ' . $payment_intent->id);
        return;
    }
    
    // Check if payment is already completed
    if ($order->is_paid()) {
        error_log('Order already paid: ' . $order->get_id());
        return;
    }
    
    // Complete the payment
    $order->payment_complete($payment_intent->id);
    $order->add_order_note(
        sprintf(__('Stripe payment completed (Payment Intent ID: %s)', 'yprint-plugin'), $payment_intent->id)
    );
    
    error_log('Payment completed for order: ' . $order->get_id());
    
    // Automatische Bestellbestätigung senden
    if (function_exists('yprint_send_order_confirmation_email')) {
        error_log('YPrint Webhook: Sende Bestellbestätigung für Order ' . $order->get_id());
        $email_sent = yprint_send_order_confirmation_email($order);
        error_log('YPrint Webhook: Bestellbestätigung gesendet: ' . ($email_sent ? 'ERFOLG' : 'FEHLER'));
    } else {
        error_log('YPrint Webhook: FEHLER - yprint_send_order_confirmation_email Funktion nicht gefunden');
    }
}

    /**
     * Process payment intent failed event
     *
     * @param object $payment_intent The payment intent object
     */
    private function process_payment_intent_failed($payment_intent) {
        error_log('Processing payment intent failed: ' . $payment_intent->id);
        
        // Find the order by metadata
        $order = $this->get_order_from_intent($payment_intent);
        
        if (!$order) {
            error_log('Could not find order for payment intent: ' . $payment_intent->id);
            return;
        }
        
        // Get the error message
        $error_message = isset($payment_intent->last_payment_error->message)
            ? $payment_intent->last_payment_error->message
            : __('Payment failed', 'yprint-plugin');
        
        // Update the order status
        $order->update_status('failed', $error_message);
        
        error_log('Payment failed for order: ' . $order->get_id());
    }

    /**
     * Process charge succeeded event
     *
     * @param object $charge The charge object
     */
    private function process_charge_succeeded($charge) {
        error_log('Processing charge succeeded: ' . $charge->id);
        
        // Find the order by charge ID
        $order = $this->get_order_from_charge($charge);
        
        if (!$order) {
            error_log('Could not find order for charge: ' . $charge->id);
            return;
        }
        
        // Check if payment is already completed
        if ($order->is_paid()) {
            error_log('Order already paid: ' . $order->get_id());
            return;
        }
        
        // Complete the payment
        $order->payment_complete($charge->id);
        $order->add_order_note(
            sprintf(__('Stripe charge complete (Charge ID: %s)', 'yprint-plugin'), $charge->id)
        );
        
        error_log('Payment completed for order: ' . $order->get_id());
    }

    /**
     * Process charge failed event
     *
     * @param object $charge The charge object
     */
    private function process_charge_failed($charge) {
        error_log('Processing charge failed: ' . $charge->id);
        
        // Find the order by charge ID
        $order = $this->get_order_from_charge($charge);
        
        if (!$order) {
            error_log('Could not find order for charge: ' . $charge->id);
            return;
        }
        
        // Get the error message
        $error_message = isset($charge->failure_message)
            ? $charge->failure_message
            : __('Charge failed', 'yprint-plugin');
        
        // Update the order status
        $order->update_status('failed', $error_message);
        
        error_log('Charge failed for order: ' . $order->get_id());
    }

    /**
     * Process charge refunded event
     *
     * @param object $charge The charge object
     */
    private function process_charge_refunded($charge) {
        error_log('Processing charge refunded: ' . $charge->id);
        
        // Find the order by charge ID
        $order = $this->get_order_from_charge($charge);
        
        if (!$order) {
            error_log('Could not find order for charge: ' . $charge->id);
            return;
        }
        
        $refund_amount = $charge->amount_refunded / 100; // Convert from cents
        
        // Check if this is a full or partial refund
        if ($charge->refunded) {
            // Full refund
            $order->update_status('refunded', sprintf(__('Order fully refunded (Charge ID: %s)', 'yprint-plugin'), $charge->id));
        } else {
            // Partial refund
            $order->add_order_note(
                sprintf(__('Order partially refunded - amount: %s (Charge ID: %s)', 'yprint-plugin'),
                    wc_price($refund_amount),
                    $charge->id
                )
            );
        }
        
        error_log('Refund processed for order: ' . $order->get_id());
    }

    /**
     * Get order from payment intent
     *
     * @param object $payment_intent The payment intent object
     * @return WC_Order|false The order object or false if not found
     */
    private function get_order_from_intent($payment_intent) {
        // Check for metadata
        if (isset($payment_intent->metadata->order_id)) {
            $order_id = $payment_intent->metadata->order_id;
            return wc_get_order($order_id);
        }
        
        // Check if we have a charge
        if (isset($payment_intent->latest_charge)) {
            return $this->get_order_from_charge_id($payment_intent->latest_charge);
        }
        
        return false;
    }

    /**
     * Get order from charge
     *
     * @param object $charge The charge object
     * @return WC_Order|false The order object or false if not found
     */
    private function get_order_from_charge($charge) {
        // Check for metadata
        if (isset($charge->metadata->order_id)) {
            $order_id = $charge->metadata->order_id;
            return wc_get_order($order_id);
        }
        
        return $this->get_order_from_charge_id($charge->id);
    }

    /**
     * Get order from charge ID
     *
     * @param string $charge_id The charge ID
     * @return WC_Order|false The order object or false if not found
     */
    private function get_order_from_charge_id($charge_id) {
        global $wpdb;
        
        $order_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id FROM $wpdb->postmeta
            WHERE meta_key = '_transaction_id'
            AND meta_value = %s
        ", $charge_id));
        
        if ($order_id) {
            return wc_get_order($order_id);
        }
        
        return false;
    }

    /**
     * Test the webhook functionality
     *
     * @return array Test results
     */
    public static function test_webhook() {
        try {
            // Check if webhook URL is accessible
            $webhook_url = home_url('wc-api/yprint_stripe');
            $response = wp_remote_get($webhook_url, array(
                'timeout' => 15,
                'sslverify' => false,
            ));
            
            if (is_wp_error($response)) {
                return array(
                    'success' => false,
                    'message' => sprintf(__('Could not access webhook URL: %s', 'yprint-plugin'), $response->get_error_message()),
                    'details' => array(
                        'webhook_url' => $webhook_url,
                        'error' => $response->get_error_message(),
                    ),
                );
            }
            
            // Check if webhook secret is configured
            $gateway_settings = get_option('woocommerce_yprint_stripe_settings', array());
            $has_webhook_secret = !empty($gateway_settings['webhook_secret']);
            
            if (!$has_webhook_secret) {
                return array(
                    'success' => true,
                    'message' => __('Webhook URL is accessible, but webhook secret is not configured. Signature verification will be skipped.', 'yprint-plugin'),
                    'details' => array(
                        'webhook_url' => $webhook_url,
                        'has_webhook_secret' => false,
                    ),
                );
            }
            
            return array(
                'success' => true,
                'message' => __('Webhook URL is accessible and webhook secret is configured.', 'yprint-plugin'),
                'details' => array(
                    'webhook_url' => $webhook_url,
                    'has_webhook_secret' => true,
                ),
            );
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => $e->getMessage(),
            );
        }
    }
}