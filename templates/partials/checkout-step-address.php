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
<div id="step-1" class="checkout-step active"> <?php // 'active' Klasse wird serverseitig oder per JS initial gesetzt ?>
    <h2 class="flex items-center"><i class="fas fa-map-marker-alt mr-2 text-yprint-blue"></i><?php esc_html_e('Lieferadresse', 'yprint-checkout'); ?></h2>
    
    <?php if (is_user_logged_in()): ?>
    <div class="saved-addresses-container mb-6">
        <h3 class="text-lg font-semibold mb-3"><?php esc_html_e('Gespeicherte Adressen', 'yprint-checkout'); ?></h3>
        <div class="saved-addresses-grid grid grid-cols-1 md:grid-cols-2 gap-4" id="saved-shipping-addresses">
            <?php 
            // Lade gespeicherte Adressen
            $user_id = get_current_user_id();
            $additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
            $default_shipping_address_id = get_user_meta($user_id, 'default_shipping_address', true);
            
            // Standardlieferadresse als erste Option
            ?>
            <div class="address-card p-4 border border-yprint-border-gray rounded-lg hover:border-yprint-blue cursor-pointer transition-all duration-200 <?php echo empty($default_shipping_address_id) ? 'border-yprint-blue ring-2 ring-yprint-blue' : ''; ?>" 
                 data-address-id="standard">
                <div class="flex justify-between items-start mb-2">
                    <span class="font-medium"><?php esc_html_e('Standard-Adresse', 'yprint-checkout'); ?></span>
                    <?php if (empty($default_shipping_address_id)): ?>
                        <span class="bg-yprint-blue text-white text-xs px-2 py-1 rounded-full"><?php esc_html_e('Standard', 'yprint-checkout'); ?></span>
                    <?php endif; ?>
                </div>
                <?php 
                // Hole Standardadresse aus WooCommerce Kundendaten
                $customer = new WC_Customer($user_id);
                ?>
                <p class="text-sm text-yprint-text-secondary">
                    <?php echo esc_html($customer->get_shipping_first_name() . ' ' . $customer->get_shipping_last_name()); ?><br>
                    <?php echo esc_html($customer->get_shipping_address_1() . ' ' . $customer->get_shipping_address_2()); ?><br>
                    <?php echo esc_html($customer->get_shipping_postcode() . ' ' . $customer->get_shipping_city()); ?><br>
                    <?php echo esc_html(WC()->countries->get_countries()[$customer->get_shipping_country()] ?? $customer->get_shipping_country()); ?>
                </p>
            </div>

            <?php // Zusätzliche Adressen, wenn vorhanden
            if (is_array($additional_addresses) && !empty($additional_addresses)): 
                foreach ($additional_addresses as $address_id => $address): ?>
                    <div class="address-card p-4 border border-yprint-border-gray rounded-lg hover:border-yprint-blue cursor-pointer transition-all duration-200 <?php echo $address_id === $default_shipping_address_id ? 'border-yprint-blue ring-2 ring-yprint-blue' : ''; ?>" 
                         data-address-id="<?php echo esc_attr($address_id); ?>">
                        <div class="flex justify-between items-start mb-2">
                            <span class="font-medium"><?php echo esc_html($address['name'] ?? __('Adresse', 'yprint-checkout')); ?></span>
                            <?php if ($address_id === $default_shipping_address_id): ?>
                                <span class="bg-yprint-blue text-white text-xs px-2 py-1 rounded-full"><?php esc_html_e('Standard', 'yprint-checkout'); ?></span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-yprint-text-secondary">
                            <?php echo esc_html($address['first_name'] . ' ' . $address['last_name']); ?><br>
                            <?php echo esc_html($address['address_1'] . ' ' . $address['address_2']); ?><br>
                            <?php echo esc_html($address['postcode'] . ' ' . $address['city']); ?><br>
                            <?php echo esc_html(WC()->countries->get_countries()[$address['country']] ?? $address['country']); ?>
                        </p>
                    </div>
                <?php endforeach; 
            endif; ?>

            <!-- Option für neue Adresse -->
            <div class="address-card p-4 border border-yprint-border-gray rounded-lg hover:border-yprint-blue cursor-pointer transition-all duration-200" data-address-id="new">
                <div class="flex justify-center items-center h-full">
                    <div class="text-center">
                        <i class="fas fa-plus-circle text-yprint-blue text-xl mb-2"></i>
                        <p class="font-medium"><?php esc_html_e('Neue Adresse hinzufügen', 'yprint-checkout'); ?></p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Verstecktes Feld für ausgewählte Adresse -->
        <input type="hidden" id="selected_shipping_address" name="selected_shipping_address" value="<?php echo empty($default_shipping_address_id) ? 'standard' : esc_attr($default_shipping_address_id); ?>">
    </div>
<?php endif; ?>

<form id="address-form" class="space-y-6 mt-6">
    <?php // Nonce-Feld für Sicherheit ?>
    <?php wp_nonce_field('yprint_checkout_nonce', 'address_nonce'); ?>
    
    <div id="new-address-form" class="<?php echo is_user_logged_in() ? 'hidden' : ''; ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="street" class="form-label"><?php esc_html_e('Straße', 'yprint-checkout'); ?></label>
                <input type="text" id="street" name="shipping_street" class="form-input" required autocomplete="shipping street-address">
            </div>
            <div>
                <label for="housenumber" class="form-label"><?php esc_html_e('Hausnummer', 'yprint-checkout'); ?></label>
                <input type="text" id="housenumber" name="shipping_housenumber" class="form-input" required autocomplete="shipping address-line2">
            </div>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label for="zip" class="form-label"><?php esc_html_e('PLZ', 'yprint-checkout'); ?></label>
                <input type="text" id="zip" name="shipping_zip" class="form-input" required autocomplete="shipping postal-code" inputmode="numeric">
            </div>
            <div>
                <label for="city" class="form-label"><?php esc_html_e('Ort', 'yprint-checkout'); ?></label>
                <input type="text" id="city" name="shipping_city" class="form-input" required autocomplete="shipping address-level2">
            </div>
        </div>
        <div>
            <label for="country" class="form-label"><?php esc_html_e('Land', 'yprint-checkout'); ?></label>
            <select id="country" name="shipping_country" class="form-select" required autocomplete="shipping country">
                <option value="DE" selected><?php esc_html_e('Deutschland', 'yprint-checkout'); ?></option>
                <option value="AT"><?php esc_html_e('Österreich', 'yprint-checkout'); ?></option>
                <option value="CH"><?php esc_html_e('Schweiz', 'yprint-checkout'); ?></option>
                <option value="NL"><?php esc_html_e('Niederlande', 'yprint-checkout'); ?></option>
            </select>
        </div>
        <div>
            <label for="phone" class="form-label"><?php esc_html_e('Telefonnummer', 'yprint-checkout'); ?> <span class="text-sm text-yprint-text-secondary">(<?php esc_html_e('optional, für Versand-Updates', 'yprint-checkout'); ?>)</span></label>
            <input type="tel" id="phone" name="shipping_phone" class="form-input" autocomplete="shipping tel" inputmode="tel">
        </div>
        
        <?php if (is_user_logged_in()): ?>
        <div class="mt-4">
            <div class="flex items-center">
                <input type="checkbox" id="save_address" name="save_address" class="form-checkbox" value="1">
                <label for="save_address" class="ml-2 text-sm"><?php esc_html_e('Diese Adresse für zukünftige Bestellungen speichern', 'yprint-checkout'); ?></label>
            </div>
            <div class="mt-2 save-address-options hidden">
                <label for="address_name" class="form-label"><?php esc_html_e('Adressbezeichnung', 'yprint-checkout'); ?></label>
                <input type="text" id="address_name" name="address_name" class="form-input" placeholder="<?php esc_attr_e('z.B. Büro, Eltern', 'yprint-checkout'); ?>">
                
                <div class="mt-2">
                    <input type="checkbox" id="set_as_default" name="set_as_default" class="form-checkbox" value="1">
                    <label for="set_as_default" class="ml-2 text-sm"><?php esc_html_e('Als Standardadresse festlegen', 'yprint-checkout'); ?></label>
                </div>
            </div>
        </div>
        <?php endif; ?>
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
    <div class="pt-6 text-right">
        <?php // Der Button-Typ ist "button", da die Navigation per JS erfolgt und AJAX verwendet wird. ?>
        <button type="button" id="btn-to-payment" class="btn btn-primary w-full md:w-auto">
            <?php esc_html_e('Weiter zur Zahlung', 'yprint-checkout'); ?> <i class="fas fa-arrow-right ml-2"></i>
        </button>
    </div>
</form>
</div>
