<?php

// Stellen Sie sicher, dass dieser Code innerhalb Ihrer WordPress-Umgebung ausgeführt wird
// und die Funktionen _e, esc_attr, esc_html, selected, sanitize_text_field,
// get_current_user_id, update_user_meta, get_user_meta, WC() verfügbar sind.

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

class YPrint_Address_Manager {

    private static $instance = null;
    public $default_countries;

    /**
     * Singleton-Muster
     *
     * @return YPrint_Address_Manager
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor (Initialisierung von default_countries und Hooks)
     */
    private function __construct() {
        // Initialisierung der Standardländer.
        // Diese könnten auch dynamisch aus WooCommerce bezogen werden,
        // z.B. WC()->countries->get_allowed_countries()
        $this->default_countries = array(
            'DE' => 'Deutschland',
            'AT' => 'Österreich',
            'CH' => 'Schweiz',
            'NL' => 'Niederlande',
            // ... weitere Länder, die Sie unterstützen möchten
        );

        // In der __construct() oder init() Methode hinzufügen:
        add_action('wp_ajax_yprint_get_saved_addresses', array($this, 'ajax_get_saved_addresses'));
        add_action('wp_ajax_nopriv_yprint_get_saved_addresses', array($this, 'ajax_get_saved_addresses'));
        add_action('wp_ajax_yprint_get_address_details', array($this, 'ajax_get_address_details'));
        add_action('wp_action_yprint_save_new_address', array($this, 'handle_save_address_ajax')); // Beachten Sie, dass hier 'wp_action' steht, sollte 'wp_ajax' sein, wenn es ein AJAX-Call ist.
        add_action('wp_ajax_yprint_delete_address', array($this, 'ajax_delete_address'));
        add_action('wp_ajax_yprint_set_default_address', array($this, 'ajax_set_default_address'));
        add_action('wp_ajax_yprint_set_checkout_address', array($this, 'ajax_set_checkout_address'));

        // In der __construct() Methode hinzufügen:
        // add_action('wp_ajax_yprint_save_address', array($this, 'handle_save_address_ajax')); // ENTFERNT - zentral verwaltet
        // add_action('wp_ajax_nopriv_yprint_save_address', array($this, 'handle_save_address_ajax')); // ENTFERNT - zentral verwaltet

        add_action('wp_ajax_yprint_save_checkout_address', array($this, 'ajax_save_checkout_address'));
        add_action('wp_ajax_nopriv_yprint_save_checkout_address', array($this, 'ajax_save_checkout_address'));
        
        // Neue AJAX-Handler für Billing Address Step
        add_action('wp_ajax_yprint_save_billing_address', array($this, 'ajax_save_billing_address'));
        add_action('wp_ajax_nopriv_yprint_save_billing_address', array($this, 'ajax_save_billing_address'));
        add_action('wp_ajax_yprint_save_billing_session', array($this, 'ajax_save_billing_session'));
        add_action('wp_ajax_nopriv_yprint_save_billing_session', array($this, 'ajax_save_billing_session'));

        // Session-Handler für Billing Address
        add_action('wp_ajax_yprint_get_billing_session', array($this, 'ajax_get_billing_session'));
        add_action('wp_ajax_nopriv_yprint_get_billing_session', array($this, 'ajax_get_billing_session'));
        add_action('wp_ajax_yprint_clear_billing_session', array($this, 'ajax_clear_billing_session'));
        add_action('wp_ajax_nopriv_yprint_clear_billing_session', array($this, 'ajax_clear_billing_session'));
        
        // CLEAN HOOKS: Eindeutige Prioritäten, keine Duplikate
        add_action('woocommerce_checkout_create_order', array($this, 'apply_addresses_to_order'), 5, 2);
        add_action('woocommerce_store_api_checkout_update_order_from_request', array($this, 'apply_addresses_to_order'), 5, 2);

        // Stripe-spezifischer Hook für Express Payments
        add_action('woocommerce_checkout_order_processed', array($this, 'apply_addresses_to_order_backup'), 20, 3);
        
        // Debug-Hooks
        add_action('woocommerce_checkout_order_processed', array($this, 'debug_order_after_processing'), 25, 1);

        // AJAX-Handler für Billing Different Flag
add_action('wp_ajax_yprint_activate_billing_different', array($this, 'ajax_activate_billing_different'));
add_action('wp_ajax_nopriv_yprint_activate_billing_different', array($this, 'ajax_activate_billing_different'));

// Debug Handler registrieren
add_action('wp_ajax_yprint_debug_session_state', array($this, 'ajax_debug_session_state'));
add_action('wp_ajax_nopriv_yprint_debug_session_state', array($this, 'ajax_debug_session_state'));

// AJAX handlers
add_action('wp_ajax_yprint_get_billing_session', array($this, 'ajax_get_billing_session'));
add_action('wp_ajax_nopriv_yprint_get_billing_session', array($this, 'ajax_get_billing_session'));
    }

/**
 * AJAX-Handler zum Aktivieren des Billing Different Flags ohne Adressdaten
 */
public function ajax_activate_billing_different() {
    check_ajax_referer('yprint_save_address_action', 'nonce');
    
    if (WC()->session) {
        // CRITICAL: Flag setzen aber Billing-Address NICHT leeren
        WC()->session->set('yprint_billing_address_different', true);
        
        // Hole gespeicherte Adressen für die Auswahl
        $user_id = get_current_user_id();
        $user_addresses = array();
        if ($user_id > 0) {
            $user_addresses = $this->get_user_addresses($user_id);
        }
        
        error_log('🔍 YPRINT DEBUG: Billing Different Flag aktiviert - Adressen verfügbar: ' . count($user_addresses));
        self::debug_session_data('activate_billing_different');
        
        wp_send_json_success(array(
            'message' => __('Separate Rechnungsadresse aktiviert.', 'yprint-plugin'),
            'billing_different' => true,
            'addresses' => $user_addresses
        ));
    } else {
        wp_send_json_error(array('message' => __('Session nicht verfügbar.', 'yprint-plugin')));
    }
}

/**
 * AJAX-Handler zum Speichern einer Adresse während des Checkouts
 */
public function ajax_save_checkout_address() {
    check_ajax_referer('yprint_save_address_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Sie müssen angemeldet sein, um Adressen zu speichern.', 'yprint-plugin')));
        return;
    }
    
    // Verbesserte Datenverarbeitung: Hole alle Daten aus dem address_data Array
    $posted_data = isset($_POST['address_data']) ? $_POST['address_data'] : array();
    
    if (empty($posted_data)) {
        wp_send_json_error(array('message' => __('Keine Adressdaten übermittelt.', 'yprint-plugin')));
        return;
    }
    
    $address_data = array(
        'name' => sanitize_text_field($posted_data['name'] ?? ''),
        'first_name' => sanitize_text_field($posted_data['first_name'] ?? ''),
        'last_name' => sanitize_text_field($posted_data['last_name'] ?? ''),
        'company' => sanitize_text_field($posted_data['company'] ?? ''),
        'address_1' => sanitize_text_field($posted_data['address_1'] ?? ''),
        'address_2' => sanitize_text_field($posted_data['address_2'] ?? ''),
        'postcode' => sanitize_text_field($posted_data['postcode'] ?? ''),
        'city' => sanitize_text_field($posted_data['city'] ?? ''),
        'country' => sanitize_text_field($posted_data['country'] ?? 'DE'),
        'phone' => sanitize_text_field($posted_data['phone'] ?? ''),
    );
    
    $result = $this->save_checkout_address($address_data);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        wp_send_json_success(array(
            'message' => __('Adresse erfolgreich gespeichert.', 'yprint-plugin'),
            'address_id' => $result['address_id'] ?? '',
            'address_data' => $result['address_data'] ?? $address_data
        ));
    }
}



    /**
     * Generiert den HTML-Code für das Adress-Modal zum Hinzufügen einer neuen Adresse.
     */
    public function get_address_modal_html() {
        ob_start();
        ?>
        <div class="address-modal" id="new-address-modal" style="display: none;">
            <div class="address-modal-overlay"></div>
            <div class="address-modal-content">
                <div class="address-modal-header">
                    <h3><?php _e('Neue Adresse hinzufügen', 'yprint-plugin'); ?></h3>
                    <button type="button" 
        class="btn-address-action btn-edit-address" 
        title="Adresse bearbeiten"
        aria-label="Adresse bearbeiten"
        data-address-id="<?php echo esc_attr($address_id); ?>">
    <i class="fas fa-edit" aria-hidden="true"></i>
</button>
                </div>
                <div class="address-modal-body">
                <form id="new-address-form" class="space-y-4">
                <div>
    <label for="new_address_name" class="form-label">
        <?php _e('Name der Adresse (z.B. Zuhause, Büro)', 'yprint-plugin'); ?> <span class="required">*</span>
    </label>
    <input type="text" id="new_address_name" name="name" class="form-input" required>
    <input type="hidden" id="new_address_edit_id" name="id" value="">
</div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="new_address_first_name" class="form-label">
                            <?php _e('Vorname', 'yprint-plugin'); ?> <span class="required">*</span>
                        </label>
                        <input type="text" id="new_address_first_name" name="first_name" class="form-input" required>
                    </div>
                            <div>
                                <label for="new_last_name" class="form-label">
                                    <?php _e('Nachname', 'yprint-plugin'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="new_last_name" name="last_name" class="form-input" required>
                            </div>
                            <div class="col-span-full">
                                <label class="flex items-center">
                                    <input type="checkbox" id="new_is_company" name="is_company" class="form-checkbox mr-2">
                                    <?php _e('Firmenadresse', 'yprint-plugin'); ?>
                                </label>
                            </div>
                            <div class="col-span-full" id="new_company_field" style="display: none;">
                                <label for="new_company" class="form-label">
                                    <?php _e('Firma', 'yprint-plugin'); ?>
                                </label>
                                <input type="text" id="new_company" name="company" class="form-input">
                            </div>
                            <div class="col-span-full">
                                <label for="new_address_1" class="form-label">
                                    <?php _e('Straße und Hausnummer', 'yprint-plugin'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="new_address_1" name="address_1" class="form-input" required>
                            </div>
                            <div class="col-span-full">
                                <label for="new_address_2" class="form-label">
                                    <?php _e('Adresszusatz (optional)', 'yprint-plugin'); ?>
                                </label>
                                <input type="text" id="new_address_2" name="address_2" class="form-input">
                            </div>
                            <div>
                                <label for="new_postcode" class="form-label">
                                    <?php _e('PLZ', 'yprint-plugin'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="new_postcode" name="postcode" class="form-input" required>
                            </div>
                            <div>
                                <label for="new_city" class="form-label">
                                    <?php _e('Stadt', 'yprint-plugin'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="new_city" name="city" class="form-input" required>
                            </div>
                            <div class="col-span-full">
                                <label for="new_country" class="form-label">
                                    <?php _e('Land', 'yprint-plugin'); ?> <span class="required">*</span>
                                </label>
                                <?php echo $this->get_countries_dropdown('DE', 'country', 'new_country'); ?>
                            </div>
                        </div>
                        <div class="address-form-errors text-red-500 mt-4" style="display: none;"></div>
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
     * Generiert HTML für ein Dropdown-Menü der Länder.
     *
     * @param string $selected Der Code des vorausgewählten Landes (z.B. 'DE').
     * @param string $name Der 'name'-Attributwert des Select-Elements.
     * @param string|null $id Der 'id'-Attributwert des Select-Elements.
     * @return string Der generierte HTML-String.
     */
    public function get_countries_dropdown($selected = 'DE', $name = 'country', $id = null) {
        $id = $id ?: $name;
        $html = '<select name="' . esc_attr($name) . '" id="' . esc_attr($id) . '" class="form-select">';

        foreach ($this->default_countries as $code => $country_name) {
            $html .= '<option value="' . esc_attr($code) . '"';
            $html .= selected($code, $selected, false); // WordPress selected() helper
            $html .= '>' . esc_html($country_name) . '</option>';
        }

        $html .= '</select>';
        return $html;
    }

    /**
     * Validiert Adressdaten.
     *
     * @param array $data Die zu validierenden Adressdaten.
     * @return array Ein Array von Fehlermeldungen, leer wenn keine Fehler vorliegen.
     */
    public function validate_address_data($data) {
        $errors = array();

        $required_fields = array(
            'name' => __('Name der Adresse', 'yprint-plugin'),
            'first_name' => __('Vorname', 'yprint-plugin'),
            'last_name' => __('Nachname', 'yprint-plugin'),
            'address_1' => __('Straße', 'yprint-plugin'),
            'postcode' => __('Postleitzahl', 'yprint-plugin'),
            'city' => __('Stadt', 'yprint-plugin'),
            'country' => __('Land', 'yprint-plugin')
        );

        foreach ($required_fields as $field => $label) {
            if (empty($data[$field])) {
                $errors[] = sprintf(__('%s ist erforderlich.', 'yprint-plugin'), $label);
            }
        }

        // Firmenname, wenn Firmenadresse ausgewählt ist
        if (isset($data['is_company']) && $data['is_company'] === true) {
            if (empty($data['company'])) {
                $errors[] = __('Der Firmenname ist für Firmenadressen erforderlich.', 'yprint-plugin');
            }
        }

        // PLZ-Validierung
        if (!empty($data['postcode'])) {
            $country = $data['country'] ?? 'DE';

            switch ($country) {
                case 'DE':
                    if (!preg_match('/^\d{5}$/', $data['postcode'])) {
                        $errors[] = __('Die deutsche PLZ muss 5 Ziffern haben.', 'yprint-plugin');
                    }
                    break;
                case 'AT':
                    if (!preg_match('/^\d{4}$/', $data['postcode'])) {
                        $errors[] = __('Die österreichische PLZ muss 4 Ziffern haben.', 'yprint-plugin');
                    }
                    break;
                case 'CH':
                    if (!preg_match('/^\d{4}$/', $data['postcode'])) {
                        $errors[] = __('Die Schweizer PLZ muss 4 Ziffern haben.', 'yprint-plugin');
                    }
                    break;
                // Fügen Sie hier weitere Länder-PLZ-Regeln hinzu
            }
        }

        // Länder-Validierung
        if (!empty($data['country']) && !array_key_exists($data['country'], $this->default_countries)) {
            $errors[] = __('Ungültiges Land ausgewählt.', 'yprint-plugin');
        }

        return $errors;
    }

    /**
     * Bereinigt Adressdaten.
     *
     * @param array $data Die zu bereinigenden Adressdaten.
     * @return array Die bereinigten Adressdaten.
     */
    public function sanitize_address_data($data) {
        $sanitized = array();

        $text_fields = array('name', 'first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode');
        foreach ($text_fields as $field) {
            $sanitized[$field] = isset($data[$field]) ? sanitize_text_field($data[$field]) : '';
        }

        $sanitized['country'] = isset($data['country']) ? sanitize_text_field($data['country']) : 'DE';
        $sanitized['is_company'] = isset($data['is_company']) && ($data['is_company'] === 'true' || $data['is_company'] === true);

        return $sanitized;
    }

    /**
     * Aktualisiert WooCommerce-Kundendaten mit der ausgewählten Adresse.
     *
     * @param array $address Die Adressdaten.
     * @param string $type Der Adresstyp ('shipping' oder 'billing').
     * @return bool True bei Erfolg, false bei Fehlern.
     */
    public function update_woocommerce_customer_data($address, $type = 'shipping') {
        if (!function_exists('WC') || !WC()->session || !WC()->customer) {
            return false;
        }

        $customer = WC()->customer;

        if ($type === 'shipping') {
            $customer->set_shipping_first_name($address['first_name'] ?? '');
            $customer->set_shipping_last_name($address['last_name'] ?? '');
            $customer->set_shipping_company($address['company'] ?? '');
            $customer->set_shipping_address_1($address['address_1'] ?? '');
            $customer->set_shipping_address_2($address['address_2'] ?? '');
            $customer->set_shipping_postcode($address['postcode'] ?? '');
            $customer->set_shipping_city($address['city'] ?? '');
            $customer->set_shipping_country($address['country'] ?? '');
        } elseif ($type === 'billing') {
            $customer->set_billing_first_name($address['first_name'] ?? '');
            $customer->set_billing_last_name($address['last_name'] ?? '');
            $customer->set_billing_company($address['company'] ?? '');
            $customer->set_billing_address_1($address['address_1'] ?? '');
            $customer->set_billing_address_2($address['address_2'] ?? '');
            $customer->set_billing_postcode($address['postcode'] ?? '');
            $customer->set_billing_city($address['city'] ?? '');
            $customer->set_billing_country($address['country'] ?? '');
        }

        $customer->save();
        return true;
    }

    /**
 * Ruft alle zusätzlichen Adressen für einen bestimmten Benutzer ab.
 * Geht davon aus, dass Adressen als Array im 'additional_shipping_addresses'-User-Meta gespeichert sind.
 *
 * @param int $user_id Die ID des Benutzers.
 * @return array Ein Array der gespeicherten Adressen.
 */
public function get_user_addresses($user_id) {
    if (!$user_id) {
        return [];
    }

    // Debug-Informationen hinzufügen
    error_log('YPrint Debug (ID Handling): get_user_addresses called for user ' . $user_id);

    $addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);

    // Sicherstellen, dass wir ein Array zurückgeben und korrigieren, falls es nicht existiert
    if (empty($addresses) || !is_array($addresses)) {
        error_log('YPrint Debug (ID Handling): No addresses found or not an array for user ' . $user_id);
        return [];
    }

    error_log('YPrint Debug (ID Handling): Found ' . count($addresses) . ' addresses');
    
    // Prüfen ob die Adressen wirklich als assoziatives Array mit IDs als Schlüssel gespeichert sind
    error_log('YPrint Debug (ID Handling): Address keys: ' . print_r(array_keys($addresses), true));
    
    // Erste Adresse zur Überprüfung der Struktur ausgeben
    if (count($addresses) > 0) {
        $first_address_key = array_keys($addresses)[0];
        $first_address = $addresses[$first_address_key];
        error_log('YPrint Debug (ID Handling): First address structure check - Key: ' . $first_address_key);
        error_log('YPrint Debug (ID Handling): First address has own ID field: ' . (isset($first_address['id']) ? 'yes' : 'no'));
        if (isset($first_address['id'])) {
            error_log('YPrint Debug (ID Handling): First address ID field value: ' . $first_address['id']);
            error_log('YPrint Debug (ID Handling): ID match with key: ' . ($first_address['id'] === $first_address_key ? 'yes' : 'no'));
        }
    }

    return $addresses;
}

    /**
 * Speichert eine neue Adresse oder aktualisiert eine bestehende für den aktuellen Benutzer.
 *
 * @param array $address_data Die zu speichernden Adressdaten.
 * @return array|WP_Error Array mit Erfolgsmeldung und ID der Adresse oder WP_Error bei Fehlern.
 */
public function save_new_user_address($address_data) {
    if (!is_user_logged_in()) {
        return new WP_Error('not_logged_in', __('Sie müssen angemeldet sein, um Adressen zu speichern.', 'yprint-plugin'));
    }

    $user_id = get_current_user_id();
    $sanitized_address = $this->sanitize_address_data($address_data);
    $errors = $this->validate_address_data($sanitized_address);

    if (!empty($errors)) {
        return new WP_Error('validation_error', implode('<br>', $errors));
    }

    $existing_addresses = $this->get_user_addresses($user_id);
    
    // Debug-Logs für ID-Handling
    error_log('YPrint Debug (ID Handling): Received address data ID: ' . (isset($address_data['id']) ? $address_data['id'] : 'keine ID'));
    error_log('YPrint Debug (ID Handling): Existing address IDs: ' . print_r(array_keys($existing_addresses), true));
    
    // Prüfen, ob wir im Bearbeitungsmodus sind (existierende ID)
    $is_editing = false;
    $address_id = '';
    
    if (isset($address_data['id']) && !empty($address_data['id'])) {
        $address_id = sanitize_text_field($address_data['id']);
        $is_editing = isset($existing_addresses[$address_id]);
        error_log('YPrint Debug (ID Handling): Checking ID ' . $address_id . ' exists: ' . ($is_editing ? 'yes' : 'no'));
    }
    
    error_log('YPrint Debug (ID Handling): is_editing final result: ' . ($is_editing ? 'true' : 'false'));

    // Begrenzung auf 3 Adressen prüfen, aber nur wenn es eine neue Adresse ist
    $max_addresses = 3;
    if (!$is_editing && count($existing_addresses) >= $max_addresses) {
        return new WP_Error('address_limit_exceeded', sprintf(__('Sie können maximal %d Adressen speichern. Bitte löschen Sie eine alte Adresse, um eine neue hinzuzufügen.', 'yprint-plugin'), $max_addresses));
    }

    // Generiere eine eindeutige ID für die neue Adresse oder nutze vorhandene bei Bearbeitung
    if (!$is_editing) {
        $address_id = 'addr_' . time() . '_' . wp_rand(1000, 9999);
    }
    
    $sanitized_address['id'] = $address_id;
    
    error_log('YPrint Debug (ID Handling): Final address_id being saved: ' . $address_id);

    // Speichere als assoziatives Array mit ID als Schlüssel
    $existing_addresses[$address_id] = $sanitized_address;

    error_log('YPrint Debug (ID Handling): Updating user meta with addresses: ' . print_r(array_keys($existing_addresses), true));
    
    $update_result = update_user_meta($user_id, 'additional_shipping_addresses', $existing_addresses);
    error_log('YPrint Debug (ID Handling): update_user_meta result: ' . ($update_result ? 'success' : 'failed'));

    return array(
        'success' => true,
        'message' => $is_editing 
            ? __('Adresse erfolgreich aktualisiert.', 'yprint-plugin') 
            : __('Adresse erfolgreich gespeichert.', 'yprint-plugin'),
        'address_id' => $address_id,
        'address_data' => $sanitized_address,
        'is_editing' => $is_editing
    );
}

    /**
 * Rendert HTML für die Auswahl bestehender Adressen des Benutzers im Checkout.
 *
 * @param string $type 'shipping' oder 'billing' (bestimmt den Kontext der Adressauswahl).
 * @return string HTML-Ausgabe für die Adressauswahl.
 */
public function render_address_selection($type = null) {
    // CRITICAL: Auto-detect context if not provided
    if ($type === null) {
        $type = $this->detect_current_context();
        error_log('🎯 YPRINT: Auto-detected address context: ' . $type);
    }

    // Ensure type is valid
    if (!in_array($type, ['shipping', 'billing'])) {
        $type = 'shipping'; // Fallback to 'shipping' if invalid type is provided
    }

    if (!is_user_logged_in()) {
        return '';
    }

    $user_id = get_current_user_id();
    $addresses = $this->get_user_addresses($user_id);

    $html = '<div class="yprint-saved-addresses mt-6">';
    $html .= '<h3 class="saved-addresses-title"><i class="fas fa-map-marker-alt mr-2"></i>' . __('Gespeicherte Adressen', 'yprint-plugin') . '</h3>';
    $html .= '<div class="address-cards-grid">';

    // WooCommerce Standard-Adresse als erste Option
    // Die WooCommerce-Kundenadresse kann je nach Kontext 'shipping' oder 'billing' sein.
    // Hier verwenden wir $type, um die korrekten Adressdaten abzurufen.
    $wc_address_data_prefix = ($type === 'billing') ? 'billing' : 'shipping';

    $wc_address = array(
        'address_1' => WC()->customer->{"get_{$wc_address_data_prefix}_address_1"}(),
        'address_2' => WC()->customer->{"get_{$wc_address_data_prefix}_address_2"}(),
        'postcode' => WC()->customer->{"get_{$wc_address_data_prefix}_postcode"}(),
        'city' => WC()->customer->{"get_{$wc_address_data_prefix}_city"}(),
        'country' => WC()->customer->{"get_{$wc_address_data_prefix}_country"}(),
        'first_name' => WC()->customer->{"get_{$wc_address_data_prefix}_first_name"}(),
        'last_name' => WC()->customer->{"get_{$wc_address_data_prefix}_last_name"}(),
        'company' => WC()->customer->{"get_{$wc_address_data_prefix}_company"}()
    );

    // Nur anzeigen wenn WC-Adresse existiert
    if (!empty($wc_address['address_1']) || !empty($wc_address['city'])) {
        $html .= '<div class="address-card">';
        $html .= '<label class="cursor-pointer">';
        // Verwende $type im data-address-type Attribut
        $html .= '<input type="radio" name="selected_address" value="wc_default" data-address-type="' . esc_attr($type) . '" data-address-data="' . esc_attr(json_encode($wc_address)) . '" class="sr-only">';
        $html .= '<div class="address-card-content border-2 border-gray-200 rounded-lg p-4 transition-colors hover:border-blue-500">';
        $html .= '<div class="flex items-center justify-between mb-2">';
        $html .= '<h4 class="font-semibold">' . __('Standard-Adresse', 'yprint-plugin') . '</h4>';
        $html .= '<i class="fas fa-check text-blue-500 opacity-0 address-selected-icon"></i>';
        $html .= '</div>';
        $html .= '<div class="text-sm text-gray-600">';
        if (!empty($wc_address['company'])) {
            $html .= '<strong>' . esc_html($wc_address['company']) . '</strong><br>';
        }
        $html .= esc_html($wc_address['first_name'] . ' ' . $wc_address['last_name']) . '<br>';
        $html .= esc_html($wc_address['address_1']);
        if (!empty($wc_address['address_2'])) {
            $html .= ' ' . esc_html($wc_address['address_2']);
        }
        $html .= '<br>';
        $html .= esc_html($wc_address['postcode'] . ' ' . $wc_address['city']) . '<br>';
        $html .= esc_html($wc_address['country']);
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</label>';
        $html .= '</div>';
    }

    // Gespeicherte Adressen
    foreach ($addresses as $address_id => $address) {
        $html .= '<div class="address-card">';
        $html .= '<label class="cursor-pointer">';
        // Verwende $type im data-address-type Attribut
        $html .= '<input type="radio" name="selected_address" value="' . esc_attr($address_id) . '" data-address-type="' . esc_attr($type) . '" data-address-data="' . esc_attr(json_encode($address)) . '" class="sr-only">';
        $html .= '<div class="address-card-content border-2 border-gray-200 rounded-lg p-4 transition-colors hover:border-blue-500">';
        $html .= '<div class="flex items-center justify-between mb-2">';
        $html .= '<h4 class="font-semibold">' . esc_html($address['name'] ?? __('Gespeicherte Adresse', 'yprint-plugin')) . '</h4>';
        $html .= '<div class="flex items-center gap-2">';
        if (isset($address['is_default']) && $address['is_default']) {
            $html .= '<span class="text-xs bg-blue-100 text-blue-800 px-2 py-1 rounded">' . __('Standard', 'yprint-plugin') . '</span>';
        }
        $html .= '<i class="fas fa-check text-blue-500 opacity-0 address-selected-icon"></i>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="text-sm text-gray-600">';
        if (!empty($address['company'])) {
            $html .= '<strong>' . esc_html($address['company']) . '</strong><br>';
        }
        $html .= esc_html(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? '')) . '<br>';
        $html .= esc_html($address['address_1']);
        if (!empty($address['address_2'])) {
            $html .= ' ' . esc_html($address['address_2']);
        }
        $html .= '<br>';
        $html .= esc_html($address['postcode'] . ' ' . $address['city']) . '<br>';
        $html .= esc_html($address['country']);
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</label>';
        $html .= '</div>';
    }

    // "Neue Adresse" Karte
    $html .= '<div class="address-card add-new-address-card">';
    $html .= '<label class="cursor-pointer">';
    $html .= '<input type="radio" name="selected_address" value="new_address" data-address-type="new" class="sr-only">';
    $html .= '<div class="address-card-content border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-colors hover:border-blue-500">';
    $html .= '<i class="fas fa-plus text-3xl text-gray-400 mb-2"></i>';
    $html .= '<h4 class="font-semibold text-gray-600">' . __('Neue Adresse hinzufügen', 'yprint-plugin') . '</h4>';
    $html .= '</div>';
    $html .= '</label>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</div>';

    // Modal HTML hinzufügen
    $html .= $this->get_address_modal_html();

    return $html;
}

/**
 * Erkennt den aktuellen Checkout-Kontext basierend auf URL und anderen Faktoren.
 *
 * @return string 'shipping' oder 'billing'.
 */
private function detect_current_context() {
    // URL-basierte Erkennung
    if (isset($_GET['step']) && $_GET['step'] === 'billing') {
        return 'billing';
    }

    // Referer-basierte Erkennung
    // Vorsicht bei Referer-Prüfung, da diese nicht immer zuverlässig ist und vom Browser abhängt.
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'step=billing') !== false) {
        return 'billing';
    }

    // Session-basierte Erkennung (WooCommerce Session)
    // Dies ist oft der zuverlässigste Weg im Checkout.
    if (WC()->session && WC()->session->get('yprint_current_step') === 'billing') {
        return 'billing';
    }

    return 'shipping'; // Standardwert, wenn kein 'billing' Kontext erkannt wird
}

    /**
 * AJAX-Handler zum Speichern einer Rechnungsadresse
 */
public function ajax_save_billing_address() {
    check_ajax_referer('yprint_save_address_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => __('Sie müssen angemeldet sein, um Adressen zu speichern.', 'yprint-plugin')));
        return;
    }
    
    $posted_data = isset($_POST['address_data']) ? $_POST['address_data'] : array();
    
    if (empty($posted_data)) {
        wp_send_json_error(array('message' => __('Keine Adressdaten übermittelt.', 'yprint-plugin')));
        return;
    }
    
    // Markiere als Rechnungsadresse
    $posted_data['address_type'] = 'billing';
    
    $result = $this->save_checkout_address($posted_data);
    
    if (is_wp_error($result)) {
        wp_send_json_error(array('message' => $result->get_error_message()));
    } else {
        wp_send_json_success(array(
            'message' => __('Rechnungsadresse erfolgreich gespeichert.', 'yprint-plugin'),
            'address_id' => $result['address_id'] ?? '',
            'address_data' => $result['address_data'] ?? $posted_data
        ));
    }
}

/**
 * AJAX-Handler zum Speichern der Rechnungsadresse in der Session
 */
public function ajax_save_billing_session() {
    check_ajax_referer('yprint_save_address_action', 'nonce');
    
    $billing_data = isset($_POST['billing_data']) ? $_POST['billing_data'] : array();
    
    if (!empty($billing_data)) {
        // Daten sanitizen
        $sanitized_billing = array();
        foreach ($billing_data as $key => $value) {
            $sanitized_billing[sanitize_key($key)] = sanitize_text_field($value);
        }
        
        // CRITICAL: E-Mail-Adresse ergänzen wenn nicht vorhanden
        if (empty($sanitized_billing['email']) && WC()->customer) {
            $sanitized_billing['email'] = WC()->customer->get_email();
        }
        
        // In WooCommerce Session speichern
        if (WC()->session) {
            WC()->session->set('yprint_billing_address', $sanitized_billing);
            WC()->session->set('yprint_billing_address_different', true);
            
            // Debug: Verification der Session-Speicherung
            error_log('🔍 YPRINT DEBUG: Billing session saved with email: ' . ($sanitized_billing['email'] ?? 'NO_EMAIL'));
        }
        
        wp_send_json_success(array(
            'message' => __('Rechnungsadresse in Session gespeichert.', 'yprint-plugin'),
            'billing_data' => $sanitized_billing
        ));
    } else {
        wp_send_json_error(array('message' => __('Keine Rechnungsadressdaten erhalten.', 'yprint-plugin')));
    }
}

/**
 * AJAX handler to get billing session data for JavaScript
 */
public function ajax_get_billing_session() {
    // Nonce verification - KRITISCH: Gleiche Nonce wie für andere Address Manager Funktionen
    if (!wp_verify_nonce($_POST['nonce'], 'yprint_address_nonce')) {
        wp_send_json_error('Invalid nonce');
        return;
    }

    $has_different_billing = WC()->session->get('yprint_billing_address_different', false);
    $billing_address = WC()->session->get('yprint_billing_address', array());
    
    wp_send_json_success(array(
        'has_different_billing' => $has_different_billing,
        'billing_address' => $billing_address,
        'has_billing_address' => !empty($billing_address)
    ));
}

/**
 * AJAX-Handler zum Löschen der Billing-Session
 */
public function ajax_clear_billing_session() {
    check_ajax_referer('yprint_save_address_action', 'nonce');
    
    if (WC()->session) {
        WC()->session->set('yprint_billing_address', array());
        WC()->session->set('yprint_billing_address_different', false);
        
        // CRITICAL: Billing-Felder in WooCommerce auf Shipping zurücksetzen
        $selected_address = WC()->session->get('yprint_selected_address', array());
        if (!empty($selected_address)) {
            $this->update_woocommerce_customer_data($selected_address, 'billing');
            error_log('🔍 YPRINT DEBUG: Billing auf Shipping-Adresse zurückgesetzt');
        }
        
        self::debug_session_data('clear_billing_session');
    }
    
    wp_send_json_success(array('message' => __('Rechnungsadresse aus Session entfernt.', 'yprint-plugin')));
}

/**
 * Formatierte Rechnungsadresse aus Session abrufen
 */
public static function getFormattedBillingAddress() {
    if (!WC()->session) {
        return '';
    }
    
    $billing_address = WC()->session->get('yprint_billing_address', array());
    $has_different_billing = WC()->session->get('yprint_billing_address_different', false);
    
    if (!$has_different_billing || empty($billing_address)) {
        return '';
    }
    
    $formatted = '';
    if (!empty($billing_address['first_name']) || !empty($billing_address['last_name'])) {
        $formatted .= trim($billing_address['first_name'] . ' ' . $billing_address['last_name']) . '<br>';
    }
    
    if (!empty($billing_address['company'])) {
        $formatted .= $billing_address['company'] . '<br>';
    }
    
    if (!empty($billing_address['address_1'])) {
        $formatted .= $billing_address['address_1'];
        if (!empty($billing_address['address_2'])) {
            $formatted .= ' ' . $billing_address['address_2'];
        }
        $formatted .= '<br>';
    }
    
    if (!empty($billing_address['postcode']) || !empty($billing_address['city'])) {
        $formatted .= trim($billing_address['postcode'] . ' ' . $billing_address['city']) . '<br>';
    }
    
    if (!empty($billing_address['country'])) {
        $formatted .= $billing_address['country'];
    }
    
    return $formatted;
}

/**
 * Debug-Methode für Session-Daten - immer mit gleichem Präfix
 */
public static function debug_session_data($step = 'unknown') {
    $prefix = '🔍 YPRINT DEBUG';
    error_log("$prefix ==========================================");
    error_log("$prefix STEP: $step");
    error_log("$prefix ==========================================");
    
    if (WC()->session) {
        $selected = WC()->session->get('yprint_selected_address', array());
        $billing = WC()->session->get('yprint_billing_address', array());
        $billing_different = WC()->session->get('yprint_billing_address_different', false);
        
        error_log("$prefix Selected Address: " . print_r($selected, true));
        error_log("$prefix Billing Address: " . print_r($billing, true));
        error_log("$prefix Billing Different: " . ($billing_different ? 'TRUE' : 'FALSE'));
        error_log("$prefix Session ID: " . WC()->session->get_customer_id());
    } else {
        error_log("$prefix ERROR: No WC Session available");
    }
    
    error_log("$prefix ==========================================");
}

/**
 * Debug-Methode für Bestellungsdaten
 */
public static function debug_order_addresses($order, $context = 'unknown') {
    if (!$order instanceof WC_Order) {
        return;
    }
    
    $prefix = '🔍 YPRINT DEBUG';
    error_log("$prefix ==========================================");
    error_log("$prefix ORDER DEBUG - Context: $context");
    error_log("$prefix Order ID: " . $order->get_id());
    error_log("$prefix ==========================================");
    
    $billing = $order->get_billing_address();
    $shipping = $order->get_shipping_address();
    
    error_log("$prefix Order Billing Address: " . print_r($billing, true));
    error_log("$prefix Order Shipping Address: " . print_r($shipping, true));
    
    // Session-Vergleich
    self::debug_session_data("ORDER_CONTEXT_$context");
    
    error_log("$prefix ==========================================");
}



/**
 * Wendet die korrekten Adressen auf WooCommerce-Bestellungen an
 */
public function apply_addresses_to_order($order, $data = null) {
    if (!$order instanceof WC_Order) {
        error_log('🔍 YPRINT DEBUG: apply_addresses_to_order called but order is not WC_Order instance');
        return;
    }
    
    // ENHANCED DEBUG: Vollständige Ausführungsbestätigung
    error_log('🔍 YPRINT DEBUG: ========================================');
    error_log('🔍 YPRINT DEBUG: apply_addresses_to_order STARTED for Order #' . $order->get_id());
    error_log('🔍 YPRINT DEBUG: Context: ' . (defined('DOING_AJAX') && DOING_AJAX ? 'AJAX' : 'STANDARD'));
    error_log('🔍 YPRINT DEBUG: Source: ' . ($_POST['source'] ?? 'unknown'));
    error_log('🔍 YPRINT DEBUG: ========================================');
    
    // Debug Session-Status BEFORE processing
    self::debug_session_data('apply_addresses_BEFORE');
    
    // Lieferadresse aus gewählter Adresse
    $selected_address = WC()->session ? WC()->session->get('yprint_selected_address', array()) : array();
    
    // Prüfe abweichende Rechnungsadresse
    $has_different_billing = WC()->session ? WC()->session->get('yprint_billing_address_different', false) : false;
    $billing_address = WC()->session ? WC()->session->get('yprint_billing_address', array()) : array();
    
    // ENHANCED DEBUG: Session-Daten-Status protokollieren
    error_log('🔍 YPRINT DEBUG: Session Analysis:');
    error_log('🔍 YPRINT DEBUG: - selected_address empty: ' . (empty($selected_address) ? 'TRUE' : 'FALSE'));
    error_log('🔍 YPRINT DEBUG: - has_different_billing: ' . ($has_different_billing ? 'TRUE' : 'FALSE'));
    error_log('🔍 YPRINT DEBUG: - billing_address empty: ' . (empty($billing_address) ? 'TRUE' : 'FALSE'));
    
    // Setze Lieferadresse
    if (!empty($selected_address)) {
        $shipping_data = array(
            'first_name' => $selected_address['first_name'] ?? '',
            'last_name' => $selected_address['last_name'] ?? '',
            'company' => $selected_address['company'] ?? '',
            'address_1' => $selected_address['address_1'] ?? '',
            'address_2' => $selected_address['address_2'] ?? '',
            'city' => $selected_address['city'] ?? '',
            'postcode' => $selected_address['postcode'] ?? '',
            'country' => $selected_address['country'] ?? 'DE',
            'state' => '',
            'phone' => $selected_address['phone'] ?? ''
        );
        $order->set_shipping_address($shipping_data);
        
        // CRITICAL: Zusätzliche Meta-Daten zur Verification
        $order->update_meta_data('_yprint_original_shipping_address', $shipping_data);
        
        error_log('🔍 YPRINT DEBUG: Set shipping address from selected address');
        error_log('🔍 YPRINT DEBUG: Shipping address_1: ' . $selected_address['address_1']);
    }
    
    // Setze Rechnungsadresse
    if ($has_different_billing && !empty($billing_address)) {
        // Verwende abweichende Rechnungsadresse
        $billing_data = array(
            'first_name' => $billing_address['first_name'] ?? '',
            'last_name' => $billing_address['last_name'] ?? '',
            'company' => $billing_address['company'] ?? '',
            'address_1' => $billing_address['address_1'] ?? '',
            'address_2' => $billing_address['address_2'] ?? '',
            'city' => $billing_address['city'] ?? '',
            'postcode' => $billing_address['postcode'] ?? '',
            'country' => $billing_address['country'] ?? 'DE',
            'state' => '',
            'phone' => $billing_address['phone'] ?? '',
            'email' => $billing_address['email'] ?? WC()->customer->get_email()
        );
        $order->set_billing_address($billing_data);
        error_log('YPrint: Set different billing address');
    } else {
        // Verwende Lieferadresse als Rechnungsadresse
        if (!empty($selected_address)) {
            $billing_data = $shipping_data ?? array();
            $billing_data['email'] = WC()->customer->get_email();
            $order->set_billing_address($billing_data);
            error_log('YPrint: Set billing address same as shipping');
        }
    }
    
    // CRITICAL: Markiere dass YPrint-Adressen angewendet wurden
    $order->update_meta_data('_yprint_addresses_applied', true);
    $order->update_meta_data('_yprint_application_timestamp', current_time('mysql'));
    
    $order->save();

// ENHANCED DEBUG: Session-Daten AFTER processing
self::debug_session_data('apply_addresses_AFTER');

// Debug nach dem Setzen der Adressen
self::debug_order_addresses($order, 'apply_addresses_to_order');

// FINAL CONFIRMATION
error_log('🔍 YPRINT DEBUG: ========================================');
error_log('🔍 YPRINT DEBUG: apply_addresses_to_order COMPLETED for Order #' . $order->get_id());
error_log('🔍 YPRINT DEBUG: ========================================');
}

/**
 * Backup-Methode für Express Payment Szenarien wo der Standard-Hook nicht greift
 */
public function apply_addresses_to_order_backup($order_id, $posted_data, $order) {
    error_log('🔍 YPRINT DEBUG: Backup method called for Order #' . $order_id);
    
    if ($order instanceof WC_Order) {
        // Prüfe ob Adressen bereits korrekt gesetzt sind
        $shipping_addr = $order->get_shipping_address();
        $billing_addr = $order->get_billing_address();
        
        // Session-Daten abrufen
        $has_different_billing = WC()->session ? WC()->session->get('yprint_billing_address_different', false) : false;
        $selected_address = WC()->session ? WC()->session->get('yprint_selected_address', array()) : array();
        $billing_address = WC()->session ? WC()->session->get('yprint_billing_address', array()) : array();
        
        // Prüfe Meta-Daten auf YPrint-Originaldaten
        $original_shipping = $order->get_meta('_yprint_original_shipping_address');
        
        // MEHRERE KORREKTUR-BEDINGUNGEN:
        
        // 1. Shipping = Billing aber sollte unterschiedlich sein
        $addresses_identical = ($shipping_addr['address_1'] === $billing_addr['address_1']);
        
        // 2. Original YPrint-Daten wurden überschrieben
        $yprint_data_overwritten = !empty($original_shipping) && 
                                   $shipping_addr['address_1'] !== $original_shipping['address_1'];
        
        // 3. Session zeigt unterschiedliche Adressen
        $session_indicates_different = $has_different_billing && !empty($billing_address) && !empty($selected_address);
        
        if ($addresses_identical && $session_indicates_different) {
            error_log('🔍 YPRINT DEBUG: BACKUP CORRECTION NEEDED - Shipping and Billing identical but should be different');
            $this->apply_addresses_to_order($order);
            $order->save();
        } elseif ($yprint_data_overwritten) {
            error_log('🔍 YPRINT DEBUG: BACKUP CORRECTION NEEDED - YPrint data was overwritten');
            $this->apply_addresses_to_order($order);
            $order->save();
        } else {
            error_log('🔍 YPRINT DEBUG: BACKUP CHECK - No correction needed');
        }
    }
}

/**
 * Debug-Handler für Bestellungsverarbeitung
 */
public function debug_order_after_processing($order_id) {
    $order = wc_get_order($order_id);
    if ($order) {
        self::debug_order_addresses($order, 'order_processed');
    }
}




    /**
     * Exportiert Benutzeradressen für Backup/Migration.
     *
     * @param int|null $user_id Die ID des Benutzers. Wenn null, wird der aktuelle Benutzer verwendet.
     * @return array|null Exportdaten oder null, wenn kein Benutzer gefunden wurde.
     */
    public function export_user_addresses($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return null;
        }

        $addresses = $this->get_user_addresses($user_id);

        return array(
            'user_id' => $user_id,
            'export_date' => current_time('mysql'),
            'addresses' => $addresses
        );
    }

    /**
     * Importiert Benutzeradressen aus Exportdaten.
     *
     * @param array $export_data Die zu importierenden Exportdaten.
     * @param int|null $user_id Die ID des Benutzers, für den importiert werden soll. Wenn null, wird der aktuelle Benutzer verwendet.
     * @return int Die Anzahl der importierten Adressen oder false bei Fehlern.
     */
    public function import_user_addresses($export_data, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id || !is_array($export_data) || !isset($export_data['addresses'])) {
            return false;
        }

        $imported_count = 0;
        $existing_addresses = $this->get_user_addresses($user_id);

        foreach ($export_data['addresses'] as $address) {
            // Überspringe Standardadressen (WooCommerce Standardadressen haben keine 'id' im Meta-Array,
            // aber Ihre benutzerdefinierten könnten eine 'id' haben, die Sie prüfen möchten)
            // Hier wird angenommen, dass exportierte Adressen eine ID haben könnten.
            // if (isset($address['id']) && in_array($address['id'], array('billing_default', 'shipping_default'))) {
            //    continue;
            // }

            // Generiere neue ID, um Konflikte zu vermeiden, falls die importierte Adresse bereits eine ID hat
            // oder wenn sie nicht eindeutig genug ist
            if (!isset($address['id']) || strpos($address['id'], 'addr_') === false) {
                 $address['id'] = 'imported_addr_' . time() . '_' . wp_rand(1000, 9999);
            }

            // Validieren und bereinigen
            $sanitized_address = $this->sanitize_address_data($address);
            $errors = $this->validate_address_data($sanitized_address);

            if (empty($errors)) {
                $existing_addresses[] = $sanitized_address;
                $imported_count++;
            } else {
                error_log('YPrint Address Import Error: ' . implode(', ', $errors) . ' for address: ' . print_r($address, true));
            }
        }

        if ($imported_count > 0) {
            update_user_meta($user_id, 'additional_shipping_addresses', $existing_addresses);
        }

        return $imported_count;
    }

    /**
     * AJAX-Handler zum Speichern einer neuen Adresse.
     */
    public function handle_save_address_ajax() {
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => __('Sie müssen angemeldet sein, um Adressen zu speichern.', 'yprint-plugin')));
            return;
        }
        
        // WICHTIG: Wir verwenden hier die ursprüngliche Nonce-Prüfung, die funktioniert hat
        check_ajax_referer('yprint_save_address_action', 'yprint_address_nonce');
        
        // Adressdaten aus dem AJAX-Request extrahieren
        $address_data = $_POST;
        
        // Debug-Log für eingehende Daten
        error_log('YPrint Debug (ID Handling): handle_save_address_ajax received data: ' . print_r($address_data, true));
        
        // Stelle sicher, dass die ID korrekt behandelt wird
        if (isset($address_data['id']) && !empty($address_data['id'])) {
            error_log('YPrint Debug (ID Handling): ID found in POST data: ' . $address_data['id']);
        } else {
            error_log('YPrint Debug (ID Handling): No ID in POST data, will create new address');
        }
        
        // Adresse speichern
        $result = $this->save_new_user_address($address_data);
        
        if (is_wp_error($result)) {
            error_log('YPrint Debug (ID Handling): Error in save_new_user_address: ' . $result->get_error_message());
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            error_log('YPrint Debug (ID Handling): Address saved successfully: ' . print_r($result, true));
            wp_send_json_success($result);
        }
    }

    /**
     * Enqueue styles and scripts for the address manager
     */
    public function enqueue_styles_and_scripts() {
        // Beispiel: Laden Sie spezifische CSS/JS-Dateien, wenn das Modal verwendet wird
        // wp_enqueue_style('yprint-address-modal-styles', YPRINT_PLUGIN_URL . 'assets/css/address-modal.css', array(), YPRINT_PLUGIN_VERSION);
        // wp_enqueue_script('yprint-address-modal-scripts', YPRINT_PLUGIN_URL . 'assets/js/address-modal.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);

        // Optional: Localize script for AJAX URL and nonce if you have a dedicated script
        // wp_localize_script('yprint-address-modal-scripts', 'yprintAddressManagerAjax', array(
        //    'ajax_url' => admin_url('admin-ajax.php'),
        //    'nonce' => wp_create_nonce('yprint_save_address_action')
        // ));
    }

    /**
 * AJAX-Handler für das Abrufen gespeicherter Adressen
 */
public function ajax_get_saved_addresses() {
    // Verbessertes Debug-Logging
    error_log('=== YPrint Debug: ajax_get_saved_addresses START ===');
    error_log('YPrint Debug: POST data: ' . print_r($_POST, true));
    error_log('YPrint Debug: User logged in: ' . (is_user_logged_in() ? 'Yes' : 'No'));
    error_log('YPrint Debug: Current user ID: ' . get_current_user_id());

    // Nonce-Prüfung
    $nonce = $_POST['nonce'] ?? '';
    // check_ajax_referer wird hier verwendet, um die Nonce zu überprüfen und bei Fehlern automatisch eine JSON-Fehlerantwort zu senden und den Skriptabbruch zu veranlassen.
    // Dies ersetzt die manuelle Überprüfung und den if (!$nonce_check) Block.
    check_ajax_referer('yprint_save_address_action', 'nonce');
    error_log('YPrint Debug: Nonce check successful.'); // Dieser Log wird nur erreicht, wenn die Nonce gültig ist.

    if (!is_user_logged_in()) {
        error_log('YPrint Debug: FAILED - User not logged in');
        wp_send_json_error(array('message' => 'Nicht eingeloggt', 'debug' => 'not_logged_in'));
        return;
    }

    // CRITICAL FIX: Extrahiere address_type Parameter für korrektes Rendering
    $address_type = isset($_POST['address_type']) ? sanitize_text_field($_POST['address_type']) : 'shipping';

    // Validiere address_type
    if (!in_array($address_type, ['shipping', 'billing'])) {
        $address_type = 'shipping'; // Setze auf Standardwert, wenn ungültig
        error_log('YPrint Debug: Invalid address_type received, defaulting to shipping.');
    }

    $user_id = get_current_user_id();
    error_log('YPrint Debug: Getting addresses for user ID: ' . $user_id);

    // Raw user meta abrufen für Debugging (optional, da get_user_addresses die Hauptquelle ist)
    $raw_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
    error_log('YPrint Debug: Raw user meta (for reference): ' . print_r($raw_addresses, true));
    error_log('YPrint Debug: Raw meta type (for reference): ' . gettype($raw_addresses));

    // Über unsere Methode abrufen
    $addresses = $this->get_user_addresses($user_id);
    error_log('YPrint Debug: Processed addresses: ' . print_r($addresses, true));
    error_log('YPrint Debug: Number of addresses: ' . count($addresses));
    error_log('YPrint Debug: Addresses type: ' . gettype($addresses));
    error_log('YPrint Debug: ajax_get_saved_addresses called with address_type: ' . $address_type); // Debug-Log für Kontext-Erkennung

    // Erfolgreiche Antwort senden
    wp_send_json_success(array(
        'addresses' => $addresses,
        'user_id' => $user_id,
        'address_type' => $address_type, // Für Frontend-Verwendung
        'debug_info' => array(
            'timestamp' => current_time('mysql'),
            'addresses_count' => count($addresses),
            'raw_meta_count' => is_array($raw_addresses) ? count($raw_addresses) : 0,
            'raw_meta_type' => gettype($raw_addresses),
            'context' => $address_type // Für Frontend-Verwendung
        )
    ));

    error_log('=== YPrint Debug: ajax_get_saved_addresses END ===');
}


/**
 * AJAX-Handler für das Abrufen von Adressdetails
 */
public function ajax_get_address_details() {
    check_ajax_referer('yprint_save_address_action', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht eingeloggt'));
        return;
    }

    $address_id = sanitize_text_field($_POST['address_id']);
    $user_id = get_current_user_id();
    $addresses = $this->get_user_addresses($user_id);

    if (isset($addresses[$address_id])) {
        wp_send_json_success(array('address' => $addresses[$address_id]));
    } else {
        wp_send_json_error(array('message' => 'Adresse nicht gefunden'));
    }
}

/**
 * AJAX-Handler für das Löschen einer Adresse
 */
public function ajax_delete_address() {
    check_ajax_referer('yprint_save_address_action', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht eingeloggt'));
        return;
    }

    $address_id = sanitize_text_field($_POST['address_id']);
    $user_id = get_current_user_id();
    $addresses = $this->get_user_addresses($user_id);

    if (isset($addresses[$address_id])) {
        unset($addresses[$address_id]);
        update_user_meta($user_id, 'additional_shipping_addresses', $addresses);
        wp_send_json_success(array('message' => 'Adresse gelöscht'));
    } else {
        wp_send_json_error(array('message' => 'Adresse nicht gefunden'));
    }
}

/**
 * AJAX-Handler für das Setzen einer Standard-Adresse
 */
public function ajax_set_default_address() {
    check_ajax_referer('yprint_save_address_action', 'nonce');

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht eingeloggt'));
        return;
    }



    $address_id = sanitize_text_field($_POST['address_id']);
    $user_id = get_current_user_id();
    $addresses = $this->get_user_addresses($user_id);

    // Alle Adressen als nicht-Standard markieren
    foreach ($addresses as $id => $address) {
        $addresses[$id]['is_default'] = false;
    }

    // Gewählte Adresse als Standard markieren
    if (isset($addresses[$address_id])) {
        $addresses[$address_id]['is_default'] = true;
        update_user_meta($user_id, 'additional_shipping_addresses', $addresses);
        wp_send_json_success(array('message' => 'Standard-Adresse gesetzt'));
    } else {
        wp_send_json_error(array('message' => 'Adresse nicht gefunden'));
    }
}

public function ajax_set_checkout_address() {
    check_ajax_referer('yprint_save_address_action', 'nonce');

    $address_id = sanitize_text_field($_POST['address_id'] ?? '');
    $address_data = isset($_POST['address_data']) && is_array($_POST['address_data']) ? 
        $_POST['address_data'] : array();
    $address_type = sanitize_text_field($_POST['address_type'] ?? 'shipping');

    // ERWEITERTE DEBUG-FUNKTIONEN für Session-Tracking
    error_log('🚀 AJAX DEBUG: ========================================');
    error_log('🚀 AJAX DEBUG: ajax_set_checkout_address CALLED');
    error_log('🚀 AJAX DEBUG: POST address_id: ' . $address_id);
    error_log('🚀 AJAX DEBUG: POST address_type (initial): ' . $address_type); // Log initial value
    error_log('🚀 AJAX DEBUG: RAW POST address_type: ' . ($_POST['address_type'] ?? 'NOT_SET'));
    error_log('🚀 AJAX DEBUG: POST keys: ' . implode(', ', array_keys($_POST)));
    error_log('🚀 AJAX DEBUG: POST data: ' . print_r($_POST, true));
    error_log('🚀 AJAX DEBUG: URL: ' . $_SERVER['REQUEST_URI'] ?? 'unknown');
    error_log('🚀 AJAX DEBUG: Referer: ' . $_SERVER['HTTP_REFERER'] ?? 'unknown');
    
    // VORHER: Session-Status
    if (WC()->session) {
        $session_before = array(
            'yprint_selected_address' => WC()->session->get('yprint_selected_address', 'EMPTY'),
            'yprint_billing_address' => WC()->session->get('yprint_billing_address', 'EMPTY'),
            'yprint_billing_address_different' => WC()->session->get('yprint_billing_address_different', false)
        );
        error_log('🚀 AJAX DEBUG: SESSION BEFORE: ' . print_r($session_before, true));
    }

    if (empty($address_id) && empty($address_data)) {
        wp_send_json_error(array('message' => __('Keine Adress-ID oder Adressdaten übermittelt.', 'yprint-plugin')));
        return;
    }

    // Hole Adressdaten wenn nur ID übermittelt wurde
    if (!empty($address_id) && empty($address_data)) {
        $user_addresses = $this->get_user_addresses(get_current_user_id());
        if (isset($user_addresses[$address_id])) {
            $address_data = $user_addresses[$address_id];
        } else {
            wp_send_json_error(array('message' => __('Adresse nicht gefunden.', 'yprint-plugin')));
            return;
        }
    }

    if (WC()->session) {
        // FALLBACK: Context-Erkennung wenn address_type fehlt oder falsch ist
        // Dies ist eine sehr wichtige Fallback-Logik, die bestehen bleiben sollte.
        if (($address_type === 'shipping' || empty($address_type)) && isset($_SERVER['HTTP_REFERER'])) {
            $referer = $_SERVER['HTTP_REFERER'];
            if (strpos($referer, 'step=billing') !== false) {
                $address_type = 'billing';
                error_log('🚀 AJAX DEBUG: FALLBACK - Context auf billing gesetzt (Referer-basiert)');
            }
        }
        
        error_log('🚀 AJAX DEBUG: Entering session logic with resolved address_type: ' . $address_type); // Log resolved value
        
        if ($address_type === 'billing') {
            error_log('🚀 AJAX DEBUG: *** BILLING BRANCH ENTERED ***');
            
            // ABSOLUTER SCHUTZ: yprint_selected_address darf NIEMALS überschrieben werden bei billing
            $existing_shipping = WC()->session->get('yprint_selected_address', array());
            error_log('🚀 AJAX DEBUG: Existing shipping address preserved: ' . (!empty($existing_shipping) ? ($existing_shipping['address_1'] ?? 'no address_1') : 'EMPTY'));
            
            // BILLING: Ausschließlich separate Billing-Session setzen
            error_log('🚀 AJAX DEBUG: Setting yprint_billing_address...');
            WC()->session->set('yprint_billing_address', $address_data);
            
            error_log('🚀 AJAX DEBUG: Setting yprint_billing_address_different = true...');
            WC()->session->set('yprint_billing_address_different', true);
            
            // VERIFICATION: Prüfe ob Session korrekt gesetzt wurde
            $verification_billing = WC()->session->get('yprint_billing_address', 'FAILED_TO_SET');
            $verification_different = WC()->session->get('yprint_billing_address_different', 'FAILED_TO_SET');
            $verification_shipping = WC()->session->get('yprint_selected_address', 'SHOULD_BE_UNCHANGED');
            
            error_log('🚀 AJAX DEBUG: VERIFICATION after setting:');
            error_log('🚀 AJAX DEBUG: - yprint_billing_address: ' . (is_array($verification_billing) ? ($verification_billing['address_1'] ?? 'no address_1') : $verification_billing));
            error_log('🚀 AJAX DEBUG: - yprint_billing_address_different: ' . ($verification_different ? 'TRUE' : 'FALSE')); // Corrected for boolean output
            error_log('🚀 AJAX DEBUG: - yprint_selected_address (should be unchanged): ' . (is_array($verification_shipping) ? ($verification_shipping['address_1'] ?? 'no address_1') : $verification_shipping));
            
            // WooCommerce Customer Billing-Daten direkt setzen (ohne Session-Update)
            if (WC()->customer) {
                WC()->customer->set_billing_first_name($address_data['first_name'] ?? '');
                WC()->customer->set_billing_last_name($address_data['last_name'] ?? '');
                WC()->customer->set_billing_company($address_data['company'] ?? '');
                WC()->customer->set_billing_address_1($address_data['address_1'] ?? '');
                WC()->customer->set_billing_address_2($address_data['address_2'] ?? '');
                WC()->customer->set_billing_postcode($address_data['postcode'] ?? '');
                WC()->customer->set_billing_city($address_data['city'] ?? '');
                WC()->customer->set_billing_country($address_data['country'] ?? 'DE');
            }
            
            error_log('🔍 YPRINT DEBUG: ========================================');
            error_log('🔍 YPRINT DEBUG: BILLING Address ONLY saved to yprint_billing_address');
            error_log('🔍 YPRINT DEBUG: yprint_selected_address STATUS: UNBERÜHRT');
            error_log('🔍 YPRINT DEBUG: ========================================');

        } else { // Dies ist der Shipping-Fall
            // SHIPPING BRANCH mit zusätzlicher Validierung
            error_log('🚀 AJAX DEBUG: *** SHIPPING BRANCH ENTERED ***');
            error_log('🚀 AJAX DEBUG: Final address_type verification: ' . $address_type);
                        
            // SICHERHEITS-CHECK: Ist das wirklich ein shipping context?
            $referer = $_SERVER['HTTP_REFERER'] ?? '';
            if (strpos($referer, 'step=billing') !== false) {
                error_log('🚨 CRITICAL ERROR: Billing context detected but shipping branch executed!');
                error_log('🚨 CRITICAL ERROR: Referer: ' . $referer);
                error_log('🚨 CRITICAL ERROR: address_type: ' . $address_type);
                wp_send_json_error(array('message' => __('Kontext-Fehler erkannt: Rechnungsadresse kann nicht als Versandadresse gesetzt werden.', 'yprint-plugin')));
                return;
            }
            
            // Normale Shipping-Logik nur wenn wirklich Shipping-Context
            WC()->session->set('yprint_selected_address', $address_data);
            
            // Prüfe ob bereits eine separate Rechnungsadresse existiert
            $has_different_billing = WC()->session->get('yprint_billing_address_different', false);
            if (!$has_different_billing) {
                // Fallback: Setze auch als Billing wenn keine separate Billing-Adresse gewählt
                WC()->session->set('yprint_billing_address', $address_data);
            }
            
            error_log('🔍 YPRINT DEBUG: Shipping address set in session: ' . ($address_data['address_1'] ?? 'N/A'));
            
            // WooCommerce Customer Data aktualisieren
            $this->update_woocommerce_customer_data($address_data, 'shipping');

            if (!$has_different_billing) {
                // Nur wenn KEINE separate Rechnungsadresse gewählt wurde, setze diese auch als Rechnungsadresse
                $this->update_woocommerce_customer_data($address_data, 'billing');
            }

            error_log('🔍 YPRINT DEBUG: ========================================');
            error_log('🔍 YPRINT DEBUG: SHIPPING Address saved to session');
            error_log('🔍 YPRINT DEBUG: Selected Address: ' . print_r($address_data, true));
            error_log('🔍 YPRINT DEBUG: Has Different Billing: ' . ($has_different_billing ? 'TRUE' : 'FALSE'));
            self::debug_session_data('ajax_set_checkout_address_SHIPPING');
            error_log('🔍 YPRINT DEBUG: ========================================');
        }
    } else {
        wp_send_json_error(array('message' => __('Fehler beim Setzen der Checkout-Adresse. WooCommerce Session ist nicht verfügbar.', 'yprint-plugin')));
        return;
    }

    // FINAL SESSION DEBUG vor Response
    if (WC()->session) {
        $session_after = array(
            'yprint_selected_address' => WC()->session->get('yprint_selected_address', 'EMPTY'),
            'yprint_billing_address' => WC()->session->get('yprint_billing_address', 'EMPTY'),
            'yprint_billing_address_different' => WC()->session->get('yprint_billing_address_different', false)
        );
        error_log('🚀 AJAX DEBUG: SESSION AFTER: ' . print_r($session_after, true));
        
        // KRITISCHE PRÜFUNG: Wurde yprint_selected_address unerlaubt überschrieben?
        // Diese Prüfung ist nur sinnvoll, wenn wir SICHER sind, dass wir im Billing-Kontext waren
        // und yprint_selected_address vor dem Aufruf einen Wert hatte, der nicht überschrieben werden sollte.
        // Der Absoluter Schutz im Billing-Branch sollte dies bereits verhindern.
        if ($address_type === 'billing') { // Nur prüfen, wenn wir im Billing-Pfad waren
            $selected_addr_1 = is_array($session_after['yprint_selected_address']) ? ($session_after['yprint_selected_address']['address_1'] ?? '') : '';
            $billing_addr_1 = is_array($session_after['yprint_billing_address']) ? ($session_after['yprint_billing_address']['address_1'] ?? '') : '';
            
            // Wenn yprint_selected_address jetzt die gleiche Adresse wie die *neu gesetzte* Billing-Adresse ist,
            // UND sie vorher anders war, dann gab es ein Problem.
            // Diese Prüfung ist komplex, da sie den Zustand *vor* der Operation kennen müsste.
            // Der "Absoluter Schutz" im Billing-Branch ist die primäre Verteidigung.
            // Diese hier ist eher eine nachgelagerte Anomalie-Erkennung.
            // Ich lasse sie drin, aber die primäre Logik ist wichtiger.
            if ($selected_addr_1 === $billing_addr_1 && $selected_addr_1 === ($address_data['address_1'] ?? '')) {
                error_log('🚨 AJAX ERROR: CRITICAL - yprint_selected_address might have been ILLEGALLY OVERWRITTEN during billing operation!');
                error_log('🚨 AJAX ERROR: This should NEVER happen if prior logic is correct! Possible bug detected in session management.');
            }
        }
    }
    
    error_log('🚀 AJAX DEBUG: ajax_set_checkout_address COMPLETED ========================================');

    // Final success response, assuming everything above ran without an error and WooCommerce session was available
    wp_send_json_success(array(
        'message' => $address_type === 'billing'
            ? __('Rechnungsadresse erfolgreich für den Checkout gesetzt.', 'yprint-plugin')
            : __('Adresse erfolgreich für den Checkout gesetzt.', 'yprint-plugin'),
        'address_data' => $address_data,
        'address_type' => $address_type,
        'billing_different' => WC()->session ? WC()->session->get('yprint_billing_address_different', false) : false
    ));
}

/**
 * AJAX-Handler zum Debuggen des Session-States
 */
public function ajax_debug_session_state() {
    check_ajax_referer('yprint_save_address_action', 'nonce');
    
    $timestamp = date('Y-m-d H:i:s.u');
    
    if (WC()->session) {
        $session_data = array(
            'yprint_selected_address' => WC()->session->get('yprint_selected_address', null),
            'yprint_billing_address' => WC()->session->get('yprint_billing_address', null),
            'yprint_billing_address_different' => WC()->session->get('yprint_billing_address_different', false),
            'timestamp' => $timestamp
        );
        
        // Error Log Debug
        error_log('🔍 [SESSION-DEBUG] ' . $timestamp . ' - Session State requested from JS');
        if (isset($session_data['yprint_selected_address']['address_1'])) {
            error_log('🔍 [SESSION-DEBUG] - yprint_selected_address: ' . $session_data['yprint_selected_address']['address_1']);
        } else {
            error_log('🔍 [SESSION-DEBUG] - yprint_selected_address: EMPTY');
        }
        if (isset($session_data['yprint_billing_address']['address_1'])) {
            error_log('🔍 [SESSION-DEBUG] - yprint_billing_address: ' . $session_data['yprint_billing_address']['address_1']);
        } else {
            error_log('🔍 [SESSION-DEBUG] - yprint_billing_address: EMPTY');
        }
        error_log('🔍 [SESSION-DEBUG] - yprint_billing_address_different: ' . ($session_data['yprint_billing_address_different'] ? 'TRUE' : 'FALSE'));
        
    } else {
        $session_data = array('error' => 'WooCommerce Session nicht verfügbar');
        error_log('🔍 [SESSION-DEBUG] ' . $timestamp . ' - WooCommerce Session NICHT verfügbar');
    }
    
    wp_send_json_success($session_data);
}


// **DIESE FUNKTION WIRD ENTFERNT, DA SIE NUR ZU DEBUGGING-ZWECKEN DIENTE**
// public function create_test_addresses($user_id) {
//     if (!current_user_can('administrator')) {
//         return false;
//     }
//
//     $test_addresses = array(
//         'addr_test_1' => array(
//             'id' => 'addr_test_1',
//             'name' => 'Zuhause',
//             'first_name' => 'Max',
//             'last_name' => 'Mustermann',
//             'company' => '',
//             'address_1' => 'Musterstraße 123',
//             'address_2' => '',
//             'postcode' => '12345',
//             'city' => 'Berlin',
//             'country' => 'DE',
//             'is_company' => false,
//             'is_default' => true
//         ),
//         'addr_test_2' => array(
//             'id' => 'addr_test_2',
//             'name' => 'Büro',
//             'first_name' => 'Max',
//             'last_name' => 'Mustermann',
//             'company' => 'YPrint GmbH',
//             'address_1' => 'Geschäftsstraße 456',
//             'address_2' => '2. OG',
//             'postcode' => '54321',
//             'city' => 'München',
//             'country' => 'DE',
//             'is_company' => true,
//             'is_default' => false
//         )
//     );
//
//     $result = update_user_meta($user_id, 'additional_shipping_addresses', $test_addresses);
//     error_log('YPrint Debug: Test addresses created for user ' . $user_id . ': ' . ($result ? 'Success' : 'Failed'));
//
//     return $result;
// }

/**
 * Speichert eine Adresse aus dem Checkout-Formular
 * 
 * @param array $address_data Die Adressdaten aus dem Checkout-Formular
 * @return array|WP_Error Ergebnis der Speicherung
 */
public function save_checkout_address($address_data) {
    if (!is_user_logged_in()) {
        return new WP_Error('not_logged_in', __('Sie müssen angemeldet sein, um Adressen zu speichern.', 'yprint-plugin'));
    }
    
    // Adressdaten für das interne Format aufbereiten
    $formatted_data = array(
        'name' => sprintf(__('Adresse vom %s', 'yprint-plugin'), current_time('d.m.Y')),
        'first_name' => $address_data['first_name'] ?? '',
        'last_name' => $address_data['last_name'] ?? '',
        'company' => $address_data['company'] ?? '',
        'address_1' => $address_data['address_1'] ?? '',
        'address_2' => $address_data['address_2'] ?? '',
        'postcode' => $address_data['postcode'] ?? '',
        'city' => $address_data['city'] ?? '',
        'country' => $address_data['country'] ?? 'DE',
        'is_company' => !empty($address_data['company'])
    );
    
    // Adresse speichern
    return $this->save_new_user_address($formatted_data);
}

}