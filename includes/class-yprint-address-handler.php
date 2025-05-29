<?php
/**
 * Zentrale AJAX-Handler-Klasse für Adressverarbeitung
 * 
 * @package YPrint_Plugin
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class YPrint_Address_Handler {
    
    private static $instance = null;
    
    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->register_ajax_hooks();
    }
    
    /**
     * Registriert alle AJAX-Hooks zentral
     */
    public function register_ajax_hooks() {
        // Zentrale Adressspeicherung
        add_action('wp_ajax_yprint_save_address', array($this, 'handle_address_save'));
        add_action('wp_ajax_nopriv_yprint_save_address', array($this, 'handle_address_save'));
        
        // Spezielle Checkout-Adressspeicherung
        add_action('wp_ajax_yprint_save_checkout_address', array($this, 'handle_checkout_address_save'));
        add_action('wp_ajax_nopriv_yprint_save_checkout_address', array($this, 'handle_checkout_address_save'));
    }
    
    /**
     * Zentrale AJAX-Handler-Methode mit Kontext-Routing
     */
    public function handle_address_save() {
        // Kontext bestimmen
        $context = isset($_POST['context']) ? sanitize_text_field($_POST['context']) : 'account';
        
        error_log('YPrint Address Handler: Processing context: ' . $context);
        
        try {
            switch ($context) {
                case 'checkout':
                    $this->handle_checkout_context();
                    break;
                    
                case 'account':
                    $this->handle_account_context();
                    break;
                    
                default:
                    throw new Exception(__('Ungültiger Kontext für Adressspeicherung.', 'yprint-plugin'));
            }
        } catch (Exception $e) {
            error_log('YPrint Address Handler Error: ' . $e->getMessage());
            wp_send_json_error(array(
                'message' => $e->getMessage(),
                'context' => $context
            ));
        }
    }
    
    /**
     * Checkout-Kontext Handler
     */
    private function handle_checkout_context() {
        // Checkout-spezifische Nonce-Prüfung
        $nonce_action = 'yprint_checkout_nonce';
        $nonce_field = 'security';
        
        if (isset($_POST['nonce'])) {
            $nonce_field = 'nonce';
            $nonce_action = 'yprint_checkout_nonce';
        }
        
        check_ajax_referer($nonce_action, $nonce_field);
        
        $data = $_POST;
        
        // Checkout-spezifische Datenverarbeitung
        $address = array_map('sanitize_text_field', array_intersect_key($data, array_flip(array(
            'billing_first_name', 'billing_last_name', 'billing_company', 'billing_address_1',
            'billing_address_2', 'billing_city', 'billing_postcode', 'billing_country', 'billing_state',
            'billing_email', 'billing_phone',
            'shipping_first_name', 'shipping_last_name', 'shipping_company', 'shipping_address_1',
            'shipping_address_2', 'shipping_city', 'shipping_postcode', 'shipping_country', 'shipping_state',
            'ship_to_different_address'
        ))));
        
        // Validierung
        if (empty($address['billing_first_name']) || empty($address['billing_last_name']) ||
            empty($address['billing_address_1']) || empty($address['billing_city']) ||
            empty($address['billing_postcode']) || empty($address['billing_country']) ||
            empty($address['billing_email']) || empty($address['billing_phone'])) {
            throw new Exception(__('Bitte füllen Sie alle Pflichtfelder aus.', 'yprint-plugin'));
        }
        
        // In WooCommerce-Session speichern
        WC()->session->set('yprint_checkout_address', $address);
        
        // Store chosen shipping address ID if it exists
        if (isset($data['chosen_shipping_address_id']) && !empty($data['chosen_shipping_address_id'])) {
            WC()->session->set('chosen_shipping_address_id', sanitize_text_field($data['chosen_shipping_address_id']));
        }
        
        wp_send_json_success(array(
            'message' => __('Checkout-Adresse erfolgreich gespeichert.', 'yprint-plugin'),
            'context' => 'checkout'
        ));
    }
    
    /**
     * Account-Kontext Handler
     */
    private function handle_account_context() {
        // Account-spezifische Nonce-Prüfung
        check_ajax_referer('yprint_save_address_action', 'yprint_address_nonce');
        
        if (!is_user_logged_in()) {
            throw new Exception(__('Sie müssen angemeldet sein, um Adressen zu speichern.', 'yprint-plugin'));
        }
        
        // Verwende Address Manager für Account-Kontext
        $address_manager = YPrint_Address_Manager::get_instance();
        $result = $address_manager->save_new_user_address($_POST);
        
        if (is_wp_error($result)) {
            throw new Exception($result->get_error_message());
        }
        
        wp_send_json_success($result);
    }
    
    /**
     * Spezielle Checkout-Adressspeicherung (für Address Manager Integration)
     */
    public function handle_checkout_address_save() {
        check_ajax_referer('yprint_save_address_action', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Sie müssen angemeldet sein, um Adressen zu speichern.', 'yprint-plugin')));
            return;
        }
        
        // Verwende Address Manager für die Speicherung
        $address_manager = YPrint_Address_Manager::get_instance();
        $result = $address_manager->save_checkout_address($_POST['address_data'] ?? $_POST);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success($result);
        }
    }
}