<?php

// Stellen Sie sicher, dass dieser Code innerhalb Ihrer WordPress-Umgebung ausgeführt wird
// und die Funktionen _e, esc_attr, esc_html, selected, sanitize_text_field,
// get_current_user_id, update_user_meta, get_user_meta, WC() verfügbar sind.

class YPrint_Address_Manager {

    private static $instance = null;
    public $default_countries; // Annahme: Diese Eigenschaft wird anderswo initialisiert

    // Singleton-Muster
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // Konstruktor (angenommen für die Initialisierung von default_countries und Hooks)
    private function __construct() {
        // Beispielinitialisierung, falls nicht anderswo definiert
        $this->default_countries = array(
            'DE' => 'Deutschland',
            'AT' => 'Österreich',
            'CH' => 'Schweiz',
            // ... weitere Länder
        );
        // Beispiel: add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        // Beispiel: add_action('wp_ajax_yprint_save_address', array($this, 'handle_save_address'));
    }

    /**
     * Generiert den HTML-Code für das Adress-Modal.
     * Der HTML-Ausschnitt aus Ihrer Anfrage wurde hier integriert.
     */
    public function get_address_modal_html() {
        ob_start();
        ?>
        <div class="address-modal" style="display: none;">
            <div class="address-modal-overlay"></div>
            <div class="address-modal-content">
                <div class="address-modal-header">
                    <h3><?php _e('Neue Adresse hinzufügen', 'yprint-plugin'); ?></h3>
                    <button type="button" class="btn-close-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="address-modal-body">
                    <form class="address-form" method="post">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div class="col-span-full">
                                <label for="new_city" class="form-label">
                                    <?php _e('Stadt', 'yprint-plugin'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="new_city" name="city" class="form-input" required>
                            </div>
                            <div class="col-span-full">
                                <label for="new_country" class="form-label">
                                    <?php _e('Land', 'yprint-plugin'); ?> <span class="required">*</span>
                                </label>
                                <select id="new_country" name="country" class="form-select" required>
                                    <?php foreach ($this->default_countries as $code => $name) : ?>
                                        <option value="<?php echo esc_attr($code); ?>"
                                                <?php selected($code, 'DE'); ?>>
                                            <?php echo esc_html($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-span-full">
                                <label class="flex items-center">
                                    <input type="checkbox" id="new_is_company" name="is_company" class="form-checkbox mr-2">
                                    <?php _e('Firmenadresse', 'yprint-plugin'); ?>
                                </label>
                            </div>
                        </div>
                        <div class="address-form-errors" style="display: none;"></div>
                    </form>
                </div>
                <div class="address-modal-footer">
                    <button type="button" class="btn btn-secondary btn-cancel-address">
                        <?php _e('Abbrechen', 'yprint-plugin'); ?>
                    </button>
                    <button type="button" class="btn btn-primary btn-save-address">
                        <i class="fas fa-save mr-2"></i>
                        <?php _e('Adresse speichern', 'yprint-plugin'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Generates countries dropdown HTML
     */
    public function get_countries_dropdown($selected = 'DE', $name = 'country', $id = null) {
        $id = $id ?: $name;
        $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" class="form-select">';
        
        foreach ($this->default_countries as $code => $country_name) {
            $html .= '<option value="' . esc_attr($code) . '"';
            if ($code === $selected) {
                $html .= ' selected';
            }
            $html .= '>' . esc_html($country_name) . '</option>';
        }
        
        $html .= '</select>';
        return $html;
    }
    
    /**
     * Validates address data
     */
    public function validate_address_data($data) {
        $errors = array();
        
        // Required fields
        $required_fields = array(
            'first_name' => __('Vorname', 'yprint-plugin'),
            'last_name' => __('Nachname', 'yprint-plugin'),
            'address_1' => __('Straße', 'yprint-plugin'),
            'postcode' => __('PLZ', 'yprint-plugin'),
            'city' => __('Stadt', 'yprint-plugin'),
            'country' => __('Land', 'yprint-plugin')
        );
        
        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('%s ist erforderlich.', 'yprint-plugin'), $label);
            }
        }
        
        // PLZ validation
        if (!empty($data['postcode'])) {
            $country = $data['country'] ?? 'DE';
            
            switch ($country) {
                case 'DE':
                    if (!preg_match('/^\d{5}$/', $data['postcode'])) {
                        $errors[] = __('Deutsche PLZ muss 5 Ziffern haben.', 'yprint-plugin');
                    }
                    break;
                case 'AT':
                    if (!preg_match('/^\d{4}$/', $data['postcode'])) {
                        $errors[] = __('Österreichische PLZ muss 4 Ziffern haben.', 'yprint-plugin');
                    }
                    break;
                case 'CH':
                    if (!preg_match('/^\d{4}$/', $data['postcode'])) {
                        $errors[] = __('Schweizer PLZ muss 4 Ziffern haben.', 'yprint-plugin');
                    }
                    break;
            }
        }
        
        // Country validation
        if (!empty($data['country']) && !array_key_exists($data['country'], $this->default_countries)) {
            $errors[] = __('Ungültiges Land ausgewählt.', 'yprint-plugin');
        }
        
        return $errors;
    }
    
    /**
     * Sanitizes address data
     */
    public function sanitize_address_data($data) {
        $sanitized = array();
        
        $text_fields = array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'name');
        foreach ($text_fields as $field) {
            $sanitized[$field] = isset($data[$field]) ? sanitize_text_field($data[$field]) : '';
        }
        
        $sanitized['postcode'] = isset($data['postcode']) ? sanitize_text_field($data['postcode']) : '';
        $sanitized['country'] = isset($data['country']) ? sanitize_text_field($data['country']) : 'DE';
        $sanitized['is_company'] = isset($data['is_company']) && ($data['is_company'] === 'true' || $data['is_company'] === true);
        
        return $sanitized;
    }
    
    /**
     * Updates WooCommerce customer data with selected address
     */
    public function update_woocommerce_customer_data($address, $type = 'shipping') {
        if (!function_exists('WC') || !WC()->session) {
            return false;
        }
        
        $customer = WC()->customer;
        
        if ($type === 'shipping') {
            $customer->set_shipping_first_name($address['first_name']);
            $customer->set_shipping_last_name($address['last_name']);
            $customer->set_shipping_company($address['company']);
            $customer->set_shipping_address_1($address['address_1']);
            $customer->set_shipping_address_2($address['address_2']);
            $customer->set_shipping_postcode($address['postcode']);
            $customer->set_shipping_city($address['city']);
            $customer->set_shipping_country($address['country']);
        } elseif ($type === 'billing') {
            $customer->set_billing_first_name($address['first_name']);
            $customer->set_billing_last_name($address['last_name']);
            $customer->set_billing_company($address['company']);
            $customer->set_billing_address_1($address['address_1']);
            $customer->set_billing_address_2($address['address_2']);
            $customer->set_billing_postcode($address['postcode']);
            $customer->set_billing_city($address['city']);
            $customer->set_billing_country($address['country']);
        }
        
        $customer->save();
        return true;
    }
    
    /**
     * Exports user addresses for backup/migration
     */
    public function export_user_addresses($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return null;
        }
        
        $addresses = $this->get_user_addresses($user_id); // Annahme: get_user_addresses ist definiert
        
        return array(
            'user_id' => $user_id,
            'export_date' => current_time('mysql'),
            'addresses' => $addresses
        );
    }
    
    /**
     * Imports user addresses from export
     */
    public function import_user_addresses($export_data, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id || !is_array($export_data) || !isset($export_data['addresses'])) {
            return false;
        }
        
        $imported_count = 0;
        $existing_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true) ?: array();
        
        foreach ($export_data['addresses'] as $address) {
            // Skip default addresses
            if (in_array($address['id'], array('billing_default', 'shipping_default'))) {
                continue;
            }
            
            // Generate new ID to avoid conflicts
            $address['id'] = 'addr_' . time() . '_' . rand(1000, 9999);
            
            // Validate and sanitize
            $sanitized_address = $this->sanitize_address_data($address);
            $errors = $this->validate_address_data($sanitized_address);
            
            if (empty($errors)) {
                $existing_addresses[] = $sanitized_address;
                $imported_count++;
            }
        }
        
        if ($imported_count > 0) {
            update_user_meta($user_id, 'additional_shipping_addresses', $existing_addresses);
        }
        
        return $imported_count;
    }

    // Annahme: get_user_addresses Methode existiert, um die gespeicherten Adressen abzurufen
    // Beispiel:
    // private function get_user_addresses($user_id) {
    //     return get_user_meta($user_id, 'additional_shipping_addresses', true) ?: [];
    // }
}

// Initialisieren Sie den Adressmanager, um ihn zu aktivieren (z.B. in Ihrer Haupt-Plugin-Datei).
YPrint_Address_Manager::get_instance();