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
add_action('wp_ajax_yprint_get_address_details', array($this, 'ajax_get_address_details'));
add_action('wp_ajax_yprint_delete_address', array($this, 'ajax_delete_address'));
add_action('wp_ajax_yprint_set_default_address', array($this, 'ajax_set_default_address'));
    }

    /**
     * Generiert den HTML-Code für das Adress-Modal zum Hinzufügen einer neuen Adresse.
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
                        <?php wp_nonce_field('yprint_save_address_action', 'yprint_address_nonce'); ?>
                        <input type="hidden" name="action" value="yprint_save_address">
                        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label for="new_first_name" class="form-label">
                                    <?php _e('Vorname', 'yprint-plugin'); ?> <span class="required">*</span>
                                </label>
                                <input type="text" id="new_first_name" name="first_name" class="form-input" required>
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

        // Pflichtfelder
        $required_fields = array(
            'first_name' => __('Vorname', 'yprint-plugin'),
            'last_name' => __('Nachname', 'yprint-plugin'),
            'address_1' => __('Straße und Hausnummer', 'yprint-plugin'),
            'postcode' => __('PLZ', 'yprint-plugin'),
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

        $text_fields = array('first_name', 'last_name', 'company', 'address_1', 'address_2', 'city', 'postcode');
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
        $addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
        return is_array($addresses) ? $addresses : [];
    }

    /**
     * Speichert eine neue Adresse für den aktuellen Benutzer.
     *
     * @param array $address_data Die zu speichernden Adressdaten.
     * @return array|WP_Error Array mit Erfolgsmeldung und ID der neuen Adresse oder WP_Error bei Fehlern.
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

        // Generiere eine eindeutige ID für die neue Adresse
        $new_address_id = 'addr_' . time() . '_' . wp_rand(1000, 9999);
        $sanitized_address['id'] = $new_address_id; // Speichern der ID in den Adressdaten

        $existing_addresses[] = $sanitized_address;

        update_user_meta($user_id, 'additional_shipping_addresses', $existing_addresses);

        return array(
            'success' => true,
            'message' => __('Adresse erfolgreich gespeichert.', 'yprint-plugin'),
            'address_id' => $new_address_id,
            'address_data' => $sanitized_address // Rückgabe der gespeicherten Adresse
        );
    }

    /**
     * Rendert HTML für die Auswahl bestehender Adressen des Benutzers im Checkout.
     *
     * @param string $type 'shipping' oder 'billing' (bestimmt den Kontext der Adressauswahl).
     * @return string HTML-Ausgabe für die Adressauswahl.
     */
    public function render_address_selection($type = 'shipping') {
        if (!is_user_logged_in() || !class_exists('WooCommerce')) {
            return ''; // Nur für eingeloggte Benutzer mit WooCommerce
        }

        $user_id = get_current_user_id();
        $saved_addresses = $this->get_user_addresses($user_id);
        $output = '';

        // Holen Sie die aktuell in der WooCommerce-Session oder vom Kunden gespeicherte Adresse
        $wc_customer = WC()->customer;
        $current_wc_first_name = ($type === 'shipping') ? $wc_customer->get_shipping_first_name() : $wc_customer->get_billing_first_name();
        $current_wc_last_name = ($type === 'shipping') ? $wc_customer->get_shipping_last_name() : $wc_customer->get_billing_last_name();
        $current_wc_address_1 = ($type === 'shipping') ? $wc_customer->get_shipping_address_1() : $wc_customer->get_billing_address_1();
        $current_wc_address_2 = ($type === 'shipping') ? $wc_customer->get_shipping_address_2() : $wc_customer->get_billing_address_2();
        $current_wc_postcode = ($type === 'shipping') ? $wc_customer->get_shipping_postcode() : $wc_customer->get_billing_postcode();
        $current_wc_city = ($type === 'shipping') ? $wc_customer->get_shipping_city() : $wc_customer->get_billing_city();
        $current_wc_country = ($type === 'shipping') ? $wc_customer->get_shipping_country() : $wc_customer->get_billing_country();
        $current_wc_company = ($type === 'shipping') ? $wc_customer->get_shipping_company() : $wc_customer->get_billing_company();


        // Versuchen, die aktuell ausgewählte Adresse aus der Session zu holen
        $selected_address_id = WC()->session->get('selected_' . $type . '_address_id');

        // Wenn keine spezielle Adresse ausgewählt ist, und die WooCommerce-Standardadresse
        // Felder enthält, wähle diese als Standard aus.
        if (empty($selected_address_id) && !empty($current_wc_address_1) && !empty($current_wc_postcode) && !empty($current_wc_city)) {
             $selected_address_id = 'wc_default_' . $type;
        } elseif (empty($selected_address_id) && empty($saved_addresses)) {
            // Wenn keine gespeicherte oder Standard-WC-Adresse, dann "neue Adresse" vorauswählen
            $selected_address_id = 'new_address';
        }


        if (!empty($saved_addresses) || (!empty($current_wc_address_1) && !empty($current_wc_postcode) && !empty($current_wc_city))) {
            $output .= '<div class="saved-addresses-wrapper mt-4">';
            $output .= '<h4>' . esc_html__('Gespeicherte Adressen auswählen:', 'yprint-plugin') . '</h4>';
            $output .= '<div class="grid grid-cols-1 md:grid-cols-2 gap-4">';

            // Option für die Standard WooCommerce Adresse (wenn vorhanden)
            if (!empty($current_wc_address_1)) {
                $output .= '<div class="address-card p-4 border rounded shadow-sm bg-white">';
                $output .= '<label class="flex items-start">';
                $output .= '<input type="radio" name="selected_address" value="wc_default_' . esc_attr($type) . '" class="mr-2 mt-1" ' . checked('wc_default_' . $type, $selected_address_id, false) . ' data-address-type="wc_default">';
                $output .= '<div><strong>' . esc_html__('Standardadresse (WooCommerce)', 'yprint-plugin') . '</strong><br>';
                if (!empty($current_wc_company)) {
                    $output .= esc_html($current_wc_company) . '<br>';
                }
                $output .= esc_html($current_wc_first_name . ' ' . $current_wc_last_name) . '<br>';
                $output .= esc_html($current_wc_address_1) . '<br>';
                if (!empty($current_wc_address_2)) {
                    $output .= esc_html($current_wc_address_2) . '<br>';
                }
                $output .= esc_html($current_wc_postcode) . ' ' . esc_html($current_wc_city) . '<br>';
                $output .= esc_html($this->default_countries[$current_wc_country] ?? $current_wc_country);
                $output .= '</div></label>';
                $output .= '</div>';
            }


            // Optionen für die vom Benutzer gespeicherten Adressen
            foreach ($saved_addresses as $index => $address) {
                // Stellen Sie sicher, dass jede gespeicherte Adresse eine 'id' hat
                $address_id = isset($address['id']) ? $address['id'] : 'saved_addr_' . $index;

                $output .= '<div class="address-card p-4 border rounded shadow-sm bg-white">';
                $output .= '<label class="flex items-start">';
                $output .= '<input type="radio" name="selected_address" value="' . esc_attr($address_id) . '" class="mr-2 mt-1" ' . checked($address_id, $selected_address_id, false) . ' data-address-type="saved" data-address-data="' . esc_attr(json_encode($address)) . '">';
                $output .= '<div>';
                if (!empty($address['company'])) {
                    $output .= '<strong>' . esc_html($address['company']) . '</strong><br>';
                }
                $output .= esc_html($address['first_name'] . ' ' . $address['last_name']) . '<br>';
                $output .= esc_html($address['address_1']) . '<br>';
                if (!empty($address['address_2'])) {
                    $output .= esc_html($address['address_2']) . '<br>';
                }
                $output .= esc_html($address['postcode'] . ' ' . $address['city']) . '<br>';
                $output .= esc_html($this->default_countries[$address['country']] ?? $address['country']);
                $output .= '</div></label>';
                $output .= '</div>';
            }
            $output .= '</div>'; // End grid
            $output .= '</div>'; // End saved-addresses-wrapper
        }

        // Option zum Hinzufügen einer neuen Adresse
        $output .= '<div class="new-address-option mt-4">';
        $output .= '<label class="flex items-center cursor-pointer">';
        $output .= '<input type="radio" name="selected_address" value="new_address" class="mr-2" ' . checked('new_address', $selected_address_id, false) . ' data-address-type="new">';
        $output .= '<span>' . esc_html__('Eine neue Adresse eingeben', 'yprint-plugin') . '</span>';
        $output .= '</label>';
        $output .= '</div>';

        // Das Adress-Modal wird hier direkt eingefügt (ist standardmäßig unsichtbar per CSS/JS)
        $output .= $this->get_address_modal_html();

        return $output;
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
        }

        check_ajax_referer('yprint_save_address_action', 'yprint_address_nonce');

        $address_data = $_POST; // Daten aus dem AJAX-Request

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
    check_ajax_referer('yprint_save_address_action', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht eingeloggt'));
        return;
    }
    
    $addresses = $this->get_user_addresses(get_current_user_id());
    
    wp_send_json_success(array('addresses' => $addresses));
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
}