<?php
/**
 * YPrint E-Mail Address Integration
 * 
 * Ensures orchestrated addresses are displayed in WooCommerce emails
 */

class YPrint_Email_Address_Integration {
    
    public function __construct() {
        // Hook into WooCommerce email system
        add_filter('woocommerce_email_order_meta', [$this, 'add_orchestrated_addresses_to_email'], 10, 4);
        add_action('woocommerce_email_order_details', [$this, 'override_email_addresses'], 5, 4);
    }
    
    /**
     * Add orchestrated addresses to email content
     */
    public function add_orchestrated_addresses_to_email($order, $sent_to_admin, $plain_text, $email) {
        if (!$order instanceof WC_Order) {
            return;
        }
        
        // Check if orchestrated addresses are available
        $email_shipping = $order->get_meta('_email_template_shipping_address');
        $email_billing = $order->get_meta('_email_template_billing_address');
        $addresses_ready = $order->get_meta('_email_template_addresses_ready');
        
        if (!$addresses_ready || empty($email_shipping) || empty($email_billing)) {
            error_log('YPrint Email: No orchestrated addresses found for Order #' . $order->get_id());
            return;
        }
        
        if ($plain_text) {
            echo "\n\n--- ADRESSDATEN ---\n";
            echo "LIEFERADRESSE:\n";
            echo $this->format_address_plain_text($email_shipping);
            echo "\n\nRECHNUNGSADRESSE:\n";
            echo $this->format_address_plain_text($email_billing);
        } else {
            echo '<h3>Adressdaten</h3>';
            echo '<div style="margin-bottom: 20px;">';
            echo '<strong>Lieferadresse:</strong><br>';
            echo $this->format_address_html($email_shipping);
            echo '</div>';
            echo '<div>';
            echo '<strong>Rechnungsadresse:</strong><br>';
            echo $this->format_address_html($email_billing);
            echo '</div>';
        }
    }
    
    /**
     * Format address for plain text email
     */
    private function format_address_plain_text($address) {
        $lines = [];
        if (!empty($address['first_name']) || !empty($address['last_name'])) {
            $lines[] = trim($address['first_name'] . ' ' . $address['last_name']);
        }
        if (!empty($address['address_1'])) {
            $lines[] = $address['address_1'];
        }
        if (!empty($address['address_2'])) {
            $lines[] = $address['address_2'];
        }
        if (!empty($address['postcode']) || !empty($address['city'])) {
            $lines[] = trim($address['postcode'] . ' ' . $address['city']);
        }
        if (!empty($address['country'])) {
            $lines[] = $address['country'];
        }
        
        return implode("\n", $lines);
    }
    
    /**
     * Format address for HTML email
     */
    private function format_address_html($address) {
        $lines = [];
        if (!empty($address['first_name']) || !empty($address['last_name'])) {
            $lines[] = esc_html(trim($address['first_name'] . ' ' . $address['last_name']));
        }
        if (!empty($address['address_1'])) {
            $lines[] = esc_html($address['address_1']);
        }
        if (!empty($address['address_2'])) {
            $lines[] = esc_html($address['address_2']);
        }
        if (!empty($address['postcode']) || !empty($address['city'])) {
            $lines[] = esc_html(trim($address['postcode'] . ' ' . $address['city']));
        }
        if (!empty($address['country'])) {
            $lines[] = esc_html($address['country']);
        }
        
        return implode('<br>', $lines);
    }
}

// Initialize
new YPrint_Email_Address_Integration();