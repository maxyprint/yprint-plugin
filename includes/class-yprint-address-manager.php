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
        add_action('wp_ajax_yprint_save_address', array($this, 'handle_save_address_ajax'));
        add_action('wp_ajax_nopriv_yprint_save_address', array($this, 'handle_save_address_ajax'));

        add_action('wp_ajax_yprint_save_checkout_address', array($this, 'ajax_save_checkout_address'));
        add_action('wp_ajax_nopriv_yprint_save_checkout_address', array($this, 'ajax_save_checkout_address'));
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
                    <button type="button" class="btn-close-modal">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="address-modal-body">
                <form id="new-address-form" class="space-y-4">
                <div>
                    <label for="new_address_name" class="form-label">
                        <?php _e('Name der Adresse (z.B. Zuhause, Büro)', 'yprint-plugin'); ?> <span class="required">*</span>
                    </label>
                    <input type="text" id="new_address_name" name="name" class="form-input" required>
                    <input type="hidden" id="new_address_edit_id" name="address_id_for_edit" value="">
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
    error_log('YPrint Debug get_user_addresses: Getting addresses for user ' . $user_id);

    $addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);

    // Sicherstellen, dass wir ein Array zurückgeben und korrigieren, falls es nicht existiert
    if (empty($addresses) || !is_array($addresses)) {
        error_log('YPrint Debug get_user_addresses: No addresses found or not an array for user ' . $user_id);
        return [];
    }

    error_log('YPrint Debug get_user_addresses: Found ' . count($addresses) . ' addresses');

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
    
    // Vereinfachte Prüfung: Existiert die Adresse bereits mit dieser ID?
    $is_editing = isset($address_data['id']) && !empty($address_data['id']) && isset($existing_addresses[$address_data['id']]);

    // Begrenzung auf 3 Adressen prüfen, aber nur wenn es eine neue Adresse ist
    $max_addresses = 3;
    if (!$is_editing && count($existing_addresses) >= $max_addresses) {
        return new WP_Error('address_limit_exceeded', sprintf(__('Sie können maximal %d Adressen speichern. Bitte löschen Sie eine alte Adresse, um eine neue hinzuzufügen.', 'yprint-plugin'), $max_addresses));
    }

    // Generiere eine eindeutige ID für die neue Adresse oder nutze vorhandene bei Bearbeitung
    $address_id = $is_editing ? sanitize_text_field($address_data['id']) : ('addr_' . time() . '_' . wp_rand(1000, 9999));
    $sanitized_address['id'] = $address_id;

    // Speichere als assoziatives Array mit ID als Schlüssel
    $existing_addresses[$address_id] = $sanitized_address;

    update_user_meta($user_id, 'additional_shipping_addresses', $existing_addresses);

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
    public function render_address_selection($type = 'shipping') {
        if (!is_user_logged_in()) {
            return '';
        }

        $user_id = get_current_user_id();
        $addresses = $this->get_user_addresses($user_id);

        $html = '<div class="yprint-saved-addresses mt-6">';
        $html .= '<h3 class="saved-addresses-title"><i class="fas fa-map-marker-alt mr-2"></i>' . __('Gespeicherte Adressen', 'yprint-plugin') . '</h3>';
        $html .= '<div class="address-cards-grid">';

        // WooCommerce Standard-Adresse als erste Option
        $wc_address = array(
            'address_1' => WC()->customer->get_shipping_address_1(),
            'address_2' => WC()->customer->get_shipping_address_2(),
            'postcode' => WC()->customer->get_shipping_postcode(),
            'city' => WC()->customer->get_shipping_city(),
            'country' => WC()->customer->get_shipping_country(),
            'first_name' => WC()->customer->get_shipping_first_name(),
            'last_name' => WC()->customer->get_shipping_last_name(),
            'company' => WC()->customer->get_shipping_company()
        );

        // Nur anzeigen wenn WC-Adresse existiert
        if (!empty($wc_address['address_1']) || !empty($wc_address['city'])) {
            $html .= '<div class="address-card">';
            $html .= '<label class="cursor-pointer">';
            $html .= '<input type="radio" name="selected_address" value="wc_default" data-address-type="wc_default" data-address-data="' . esc_attr(json_encode($wc_address)) . '" class="sr-only">';
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
            $html .= '<input type="radio" name="selected_address" value="' . esc_attr($address_id) . '" data-address-type="saved" data-address-data="' . esc_attr(json_encode($address)) . '" class="sr-only">';
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
        $html .= '<div class="address-card add-new-address-card">'; // 'add-new-address-card' Klasse hinzugefügt
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
        
        // Adresse speichern
        $result = $this->save_new_user_address($address_data);
        
        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        } else {
            wp_send_json_success($result);
        }
    }

    /**
     * Enqueue styles and scripts for the address manager.
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
    $nonce_check = wp_verify_nonce($nonce, 'yprint_save_address_action');
    error_log('YPrint Debug: Nonce check result: ' . ($nonce_check ? 'Valid' : 'Invalid'));
    error_log('YPrint Debug: Provided nonce: ' . $nonce);

    if (!$nonce_check) {
        error_log('YPrint Debug: FAILED - Nonce verification failed');
        wp_send_json_error(array('message' => 'Sicherheitsprüfung fehlgeschlagen', 'debug' => 'nonce_failed'));
        return;
    }

    if (!is_user_logged_in()) {
        error_log('YPrint Debug: FAILED - User not logged in');
        wp_send_json_error(array('message' => 'Nicht eingeloggt', 'debug' => 'not_logged_in'));
        return;
    }

    $user_id = get_current_user_id();
    error_log('YPrint Debug: Getting addresses for user ID: ' . $user_id);

    // Raw user meta abrufen für Debugging
    $raw_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
    error_log('YPrint Debug: Raw user meta: ' . print_r($raw_addresses, true));
    error_log('YPrint Debug: Raw meta type: ' . gettype($raw_addresses));

    // **HIER WURDE DER TEMPORÄRE TESTDATEN-BLOCK ENTFERNT**
    if (empty($raw_addresses) || !is_array($raw_addresses)) {
        $raw_addresses = []; // Sicherstellen, dass es ein leeres Array ist, wenn keine Adressen gefunden wurden
        error_log('YPrint Debug: No addresses found for user: ' . $user_id);
    }

    // Über unsere Methode abrufen
    $addresses = $this->get_user_addresses($user_id);
    error_log('YPrint Debug: Processed addresses: ' . print_r($addresses, true));
    error_log('YPrint Debug: Number of addresses: ' . count($addresses));
    error_log('YPrint Debug: Addresses type: ' . gettype($addresses));

    // Erfolgreiche Antwort senden
    wp_send_json_success(array(
        'addresses' => $addresses,
        'user_id' => $user_id,
        'debug_info' => array(
            'timestamp' => current_time('mysql'),
            'addresses_count' => count($addresses),
            'raw_meta_count' => is_array($raw_addresses) ? count($raw_addresses) : 0,
            'raw_meta_type' => gettype($raw_addresses)
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

    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht eingeloggt'));
        return;
    }

    $address_id = sanitize_text_field($_POST['address_id']);
    $user_id = get_current_user_id();
    $addresses = $this->get_user_addresses($user_id);
    $address_to_set = null;

    if ($address_id === 'wc_default') {
        if (function_exists('WC') && WC()->customer) {
            $address_to_set = array(
                'first_name' => WC()->customer->get_shipping_first_name(),
                'last_name'  => WC()->customer->get_shipping_last_name(),
                'company'    => WC()->customer->get_shipping_company(),
                'address_1'  => WC()->customer->get_shipping_address_1(),
                'address_2'  => WC()->customer->get_shipping_address_2(),
                'postcode'   => WC()->customer->get_shipping_postcode(),
                'city'       => WC()->customer->get_shipping_city(),
                'country'    => WC()->customer->get_shipping_country(),
                'phone'      => WC()->customer->get_billing_phone(),
            );
        }
    } elseif (isset($addresses[$address_id])) {
        $address_to_set = $addresses[$address_id];
    } else {
        wp_send_json_error(array('message' => 'Ausgewählte Adresse nicht gefunden.'));
        return;
    }

    if ($address_to_set) {
        $this->update_woocommerce_customer_data($address_to_set, 'shipping');
        $this->update_woocommerce_customer_data($address_to_set, 'billing');

        wp_send_json_success(array(
            'message' => 'Adresse erfolgreich für den Checkout gesetzt.',
            'address_data' => $address_to_set
        ));
    } else {
        wp_send_json_error(array('message' => 'Fehler beim Setzen der Checkout-Adresse.'));
    }
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