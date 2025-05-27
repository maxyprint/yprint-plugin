<?php
/**
 * Partial Template: Schritt 1 - Adresseingabe.
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Hier könnten Daten für eingeloggte Benutzer geladen werden (gespeicherte Adressen)
// $user_addresses = (is_user_logged_in()) ? get_user_meta(get_current_user_id(), 'user_addresses', true) : array();
// $selected_address_id = WC()->session->get('selected_shipping_address_id'); // Beispiel
?>
<div id="step-1" class="checkout-step active">
    <h2 class="flex items-center"><i class="fas fa-map-marker-alt mr-2 text-yprint-blue"></i><?php esc_html_e('Lieferadresse', 'yprint-checkout'); ?></h2>
    
    <?php if (is_user_logged_in()) : ?>
        <div class="yprint-saved-addresses mt-6">
            <h3 class="saved-addresses-title">
                <i class="fas fa-map-marker-alt mr-2"></i>
                <?php _e('Gespeicherte Adressen', 'yprint-plugin'); ?>
            </h3>
            <div class="address-cards-grid">
                <div class="address-card add-new-address-card cursor-pointer">
                    <div class="address-card-content border-2 border-dashed border-gray-300 rounded-lg p-4 text-center transition-colors hover:border-yprint-blue add-new-address-content">
                        <i class="fas fa-plus text-3xl text-gray-400 mb-2"></i>
                        <h4 class="font-semibold text-gray-600"><?php esc_html_e('Neue Adresse hinzufügen', 'yprint-checkout'); ?></h4>
                    </div>
                </div>
            </div>
            <div class="loading-addresses text-center py-4">
                <i class="fas fa-spinner fa-spin text-yprint-blue text-2xl"></i>
                <p><?php esc_html_e('Adressen werden geladen...', 'yprint-checkout'); ?></p>
            </div>
        </div>
        <?php
        // Modal HTML bereitstellen
        if (class_exists('YPrint_Address_Manager')) {
            try {
                $address_manager = YPrint_Address_Manager::get_instance();
                echo $address_manager->get_address_modal_html();
            } catch (Exception $e) {
                if (current_user_can('administrator')) {
                    echo '<div class="notice notice-error"><p>Address Modal Error: ' . esc_html($e->getMessage()) . '</p></div>';
                }
                error_log('YPrint Address Modal Error: ' . $e->getMessage());
            }
        }
        ?>
    <?php endif; ?>
    
    <form id="address-form" class="space-y-6 mt-6">
        <?php // Nonce-Feld für Sicherheit (wird von wp_localize_script bereitgestellt und per JS hinzugefügt oder hier manuell) ?>
        <?php // wp_nonce_field( 'yprint_save_address_action', 'yprint_address_nonce' ); ?>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label for="first_name" class="form-label"><?php esc_html_e('Vorname', 'yprint-checkout'); ?></label>
        <input type="text" id="first_name" name="first_name" class="form-input" required autocomplete="given-name">
    </div>
    <div>
        <label for="last_name" class="form-label"><?php esc_html_e('Nachname', 'yprint-checkout'); ?></label>
        <input type="text" id="last_name" name="last_name" class="form-input" required autocomplete="family-name">
    </div>
</div>
<div>
    <label for="email" class="form-label"><?php esc_html_e('E-Mail-Adresse', 'yprint-checkout'); ?></label>
    <input type="email" id="email" name="email" class="form-input" required autocomplete="email">
    <p class="field-description text-sm text-gray-600 mt-1"><?php esc_html_e('Für die Bestellbestätigung und Versand-Updates', 'yprint-checkout'); ?></p>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
        <label for="street" class="form-label"><?php esc_html_e('Straße', 'yprint-checkout'); ?></label>
        <input type="text" id="street" name="street" class="form-input" required autocomplete="shipping street-address">
    </div>
    <div>
        <label for="housenumber" class="form-label"><?php esc_html_e('Hausnummer', 'yprint-checkout'); ?></label>
        <input type="text" id="housenumber" name="housenumber" class="form-input" required autocomplete="shipping address-line2">
    </div>
</div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="zip" class="form-label"><?php esc_html_e('PLZ', 'yprint-checkout'); ?></label>
                <input type="text" id="zip" name="zip" class="form-input" required autocomplete="shipping postal-code" inputmode="numeric">
            </div>
            <div>
                <label for="city" class="form-label"><?php esc_html_e('Ort', 'yprint-checkout'); ?></label>
                <input type="text" id="city" name="city" class="form-input" required autocomplete="shipping address-level2">
            </div>
        </div>
        <div>
            <label for="country" class="form-label"><?php esc_html_e('Land', 'yprint-checkout'); ?></label>
            <select id="country" name="country" class="form-select" required autocomplete="shipping country">
                <?php /*
                $countries = WC()->countries->get_shipping_countries();
                $current_country = WC()->customer->get_shipping_country();
                foreach ($countries as $key => $value) {
                    echo '<option value="' . esc_attr($key) . '" ' . selected($key, $current_country, false) . '>' . esc_html($value) . '</option>';
                }
                */ ?>
                <option value="DE" selected><?php esc_html_e('Deutschland', 'yprint-checkout'); ?></option>
                <option value="AT"><?php esc_html_e('Österreich', 'yprint-checkout'); ?></option>
                <option value="CH"><?php esc_html_e('Schweiz', 'yprint-checkout'); ?></option>
                <option value="NL"><?php esc_html_e('Niederlande', 'yprint-checkout'); ?></option>
            </select>
        </div>
        <div>
            <label for="phone" class="form-label"><?php esc_html_e('Telefonnummer', 'yprint-checkout'); ?> <span class="text-sm text-yprint-text-secondary">(<?php esc_html_e('optional, für Versand-Updates', 'yprint-checkout'); ?>)</span></label>
            <input type="tel" id="phone" name="phone" class="form-input" autocomplete="shipping tel" inputmode="tel">
        </div>
        <div class="flex items-center mt-4">
            <input type="checkbox" id="billing-same-as-shipping" name="billing_same_as_shipping" class="form-checkbox" checked>
            <label for="billing-same-as-shipping" class="text-sm cursor-pointer"><?php esc_html_e('Rechnungsadresse ist identisch mit Lieferadresse', 'yprint-checkout'); ?></label>
        </div>

        <?php // Container für abweichende Rechnungsadresse, initial versteckt wenn Checkbox aktiv ?>
        <div id="billing-address-fields" class="hidden space-y-4 mt-4 border-t border-yprint-medium-gray pt-6">
    <h3 class="text-lg font-semibold"><?php esc_html_e('Rechnungsadresse (falls abweichend)', 'yprint-checkout'); ?></h3>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="billing_first_name" class="form-label"><?php esc_html_e('Vorname', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_first_name" name="billing_first_name" class="form-input" autocomplete="billing given-name">
        </div>
        <div>
            <label for="billing_last_name" class="form-label"><?php esc_html_e('Nachname', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_last_name" name="billing_last_name" class="form-input" autocomplete="billing family-name">
        </div>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="billing_street" class="form-label"><?php esc_html_e('Straße', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_street" name="billing_street" class="form-input" autocomplete="billing street-address">
        </div>
        <div>
            <label for="billing_housenumber" class="form-label"><?php esc_html_e('Hausnummer', 'yprint-checkout'); ?></label>
            <input type="text" id="billing_housenumber" name="billing_housenumber" class="form-input" autocomplete="billing address-line2">
        </div>
    </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="billing_zip" class="form-label"><?php esc_html_e('PLZ', 'yprint-checkout'); ?></label>
                    <input type="text" id="billing_zip" name="billing_zip" class="form-input" autocomplete="billing postal-code" inputmode="numeric">
                </div>
                <div>
                    <label for="billing_city" class="form-label"><?php esc_html_e('Ort', 'yprint-checkout'); ?></label>
                    <input type="text" id="billing_city" name="billing_city" class="form-input" autocomplete="billing address-level2">
                </div>
            </div>
            <div>
                <label for="billing_country" class="form-label"><?php esc_html_e('Land', 'yprint-checkout'); ?></label>
                <select id="billing_country" name="billing_country" class="form-select" autocomplete="billing country">
                     <option value="DE" selected><?php esc_html_e('Deutschland', 'yprint-checkout'); ?></option>
                     <option value="AT"><?php esc_html_e('Österreich', 'yprint-checkout'); ?></option>
                     <option value="CH"><?php esc_html_e('Schweiz', 'yprint-checkout'); ?></option>
                     <option value="NL"><?php esc_html_e('Niederlande', 'yprint-checkout'); ?></option>
                </select>
            </div>
        </div>
        <div class="yprint-save-address-actions mt-6">
    <button type="button" id="save-address-button" class="btn btn-secondary">
        <i class="fas fa-save mr-2"></i><?php esc_html_e('Adresse speichern', 'yprint-checkout'); ?>
    </button>
    <div id="save-address-feedback" class="mt-2 text-sm hidden"></div>
</div>
        <div class="pt-6 text-right">
            <?php // Der Button-Typ ist "button", da die Navigation per JS erfolgt und AJAX verwendet wird. ?>
            <?php // Für Formular-Fallback ohne JS könnte es "submit" sein, dann müsste die PHP-Logik anders sein. ?>
            <button type="button" id="btn-to-payment" class="btn btn-primary w-full md:w-auto" disabled>
                <?php esc_html_e('Weiter zur Zahlung', 'yprint-checkout'); ?> <i class="fas fa-arrow-right ml-2"></i>
            </button>
        </div>
    </form>
</div>
