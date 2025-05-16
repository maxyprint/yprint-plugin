<?php
/**
 * YPrint Checkout Address Step
 *
 * @package YPrint
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Get user data
$user_id = get_current_user_id();
$customer = new WC_Customer($user_id);

// Get saved addresses
$billing_address = array(
    'first_name' => $customer->get_billing_first_name(),
    'last_name' => $customer->get_billing_last_name(),
    'company' => $customer->get_billing_company(),
    'address_1' => $customer->get_billing_address_1(),
    'address_2' => $customer->get_billing_address_2(),
    'city' => $customer->get_billing_city(),
    'postcode' => $customer->get_billing_postcode(),
    'country' => $customer->get_billing_country(),
);

$shipping_address = array(
    'first_name' => $customer->get_shipping_first_name(),
    'last_name' => $customer->get_shipping_last_name(),
    'company' => $customer->get_shipping_company(),
    'address_1' => $customer->get_shipping_address_1(),
    'address_2' => $customer->get_shipping_address_2(),
    'city' => $customer->get_shipping_city(),
    'postcode' => $customer->get_shipping_postcode(),
    'country' => $customer->get_shipping_country(),
);

// Additional shipping addresses
$additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
if (!is_array($additional_addresses)) {
    $additional_addresses = array();
}

// Default address ID
$default_address_id = get_user_meta($user_id, 'default_shipping_address', true);

// Next step URL
$next_step_url = add_query_arg('step', 'payment');
?>

<div class="yprint-checkout-step yprint-address-step">
    <h2>Lieferadresse auswählen</h2>
    <p class="yprint-step-description">Wähle eine Adresse für die Lieferung deiner Bestellung aus.</p>
    
    <!-- Address Selection Section -->
    <div class="yprint-address-selection">
        <h3>Deine Adressen</h3>
        
        <div class="yprint-address-grid">
            <!-- Standard Shipping Address Card -->
            <?php if (!empty($shipping_address['address_1'])) : ?>
            <div class="yprint-address-card <?php echo empty($default_address_id) ? 'default' : ''; ?>" data-address-type="shipping">
                <?php if (empty($default_address_id)) : ?>
                <div class="yprint-address-default-badge">Standard</div>
                <?php endif; ?>
                
                <div class="yprint-address-content">
                    <h4>Standard-Lieferadresse</h4>
                    <div class="yprint-address-details">
                        <?php if (!empty($shipping_address['company'])) : ?>
                        <p class="yprint-address-company"><?php echo esc_html($shipping_address['company']); ?></p>
                        <?php endif; ?>
                        
                        <p class="yprint-address-name">
                            <?php echo esc_html($shipping_address['first_name'] . ' ' . $shipping_address['last_name']); ?>
                        </p>
                        
                        <p class="yprint-address-street">
                            <?php echo esc_html($shipping_address['address_1']); ?>
                            <?php if (!empty($shipping_address['address_2'])) : ?>
                                <?php echo esc_html($shipping_address['address_2']); ?>
                            <?php endif; ?>
                        </p>
                        
                        <p class="yprint-address-city">
                            <?php echo esc_html($shipping_address['postcode'] . ' ' . $shipping_address['city']); ?>
                        </p>
                        
                        <p class="yprint-address-country">
                            <?php echo esc_html(WC()->countries->get_countries()[$shipping_address['country']]); ?>
                        </p>
                    </div>
                </div>
                
                <div class="yprint-address-actions">
                    <button type="button" class="yprint-button yprint-button-small yprint-select-address" data-address-type="shipping">
                        Auswählen
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Additional Addresses -->
            <?php foreach ($additional_addresses as $address) : ?>
            <div class="yprint-address-card <?php echo ($default_address_id === $address['id']) ? 'default' : ''; ?>" data-address-id="<?php echo esc_attr($address['id']); ?>">
                <?php if ($default_address_id === $address['id']) : ?>
                <div class="yprint-address-default-badge">Standard</div>
                <?php endif; ?>
                
                <div class="yprint-address-content">
                    <h4><?php echo esc_html($address['name']); ?></h4>
                    <div class="yprint-address-details">
                        <?php if (!empty($address['company'])) : ?>
                        <p class="yprint-address-company"><?php echo esc_html($address['company']); ?></p>
                        <?php endif; ?>
                        
                        <p class="yprint-address-name">
                            <?php echo esc_html($address['first_name'] . ' ' . $address['last_name']); ?>
                        </p>
                        
                        <p class="yprint-address-street">
                            <?php echo esc_html($address['address_1']); ?>
                            <?php if (!empty($address['address_2'])) : ?>
                                <?php echo esc_html($address['address_2']); ?>
                            <?php endif; ?>
                        </p>
                        
                        <p class="yprint-address-city">
                            <?php echo esc_html($address['postcode'] . ' ' . $address['city']); ?>
                        </p>
                        
                        <p class="yprint-address-country">
                            <?php echo esc_html(WC()->countries->get_countries()[$address['country']]); ?>
                        </p>
                    </div>
                </div>
                
                <div class="yprint-address-actions">
                    <button type="button" class="yprint-button yprint-button-small yprint-select-address" data-address-id="<?php echo esc_attr($address['id']); ?>">
                        Auswählen
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- New Address Card -->
            <div class="yprint-address-card yprint-new-address">
                <div class="yprint-address-content">
                    <h4>Neue Adresse hinzufügen</h4>
                    <div class="yprint-address-placeholder">
                        <div class="yprint-address-icon">
                            <i class="fas fa-plus-circle"></i>
                        </div>
                        <p>Füge eine neue Lieferadresse hinzu</p>
                    </div>
                </div>
                
                <div class="yprint-address-actions">
                    <button type="button" class="yprint-button yprint-button-small yprint-add-address">
                        Hinzufügen
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- New Address Form (hidden by default) -->
    <div class="yprint-address-form" style="display: none;">
        <h3>Neue Adresse eingeben</h3>
        
        <form id="yprint-new-address-form" class="yprint-shipping-form">
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="address_name" class="yprint-form-label">Bezeichnung</label>
                    <input type="text" id="address_name" name="address_name" class="yprint-form-input" placeholder="z.B. Büro, Eltern, Ferienhaus" required>
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="shipping_first_name" class="yprint-form-label">Vorname</label>
                    <input type="text" id="shipping_first_name" name="shipping_first_name" class="yprint-form-input" placeholder="Vorname" required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="shipping_last_name" class="yprint-form-label">Nachname</label>
                    <input type="text" id="shipping_last_name" name="shipping_last_name" class="yprint-form-input" placeholder="Nachname" required>
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="shipping_company" class="yprint-form-label">Firma (optional)</label>
                    <input type="text" id="shipping_company" name="shipping_company" class="yprint-form-input" placeholder="Firmenname">
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="shipping_address_1" class="yprint-form-label">Straße</label>
                    <input type="text" id="shipping_address_1" name="shipping_address_1" class="yprint-form-input" placeholder="Straße" required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="shipping_address_2" class="yprint-form-label">Hausnummer</label>
                    <input type="text" id="shipping_address_2" name="shipping_address_2" class="yprint-form-input" placeholder="Hausnummer" required>
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="shipping_postcode" class="yprint-form-label">PLZ</label>
                    <input type="text" id="shipping_postcode" name="shipping_postcode" class="yprint-form-input" placeholder="PLZ" required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="shipping_city" class="yprint-form-label">Stadt</label>
                    <input type="text" id="shipping_city" name="shipping_city" class="yprint-form-input" placeholder="Stadt" required>
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="shipping_country" class="yprint-form-label">Land</label>
                    <select id="shipping_country" name="shipping_country" class="yprint-form-select" required>
                        <?php foreach (WC()->countries->get_shipping_countries() as $code => $name) : ?>
                            <option value="<?php echo esc_attr($code); ?>" <?php selected($code, 'DE'); ?>>
                                <?php echo esc_html($name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div class="yprint-form-checkbox">
                <input type="checkbox" id="save_address" name="save_address" checked>
                <label for="save_address">Diese Adresse für zukünftige Bestellungen speichern</label>
            </div>
            
            <div class="yprint-form-actions">
                <button type="button" class="yprint-button yprint-button-secondary yprint-cancel-address">Abbrechen</button>
                <button type="submit" class="yprint-button yprint-save-address">Adresse speichern</button>
            </div>
        </form>
    </div>
    
    <!-- Step Navigation -->
    <div class="yprint-step-navigation">
        <a href="<?php echo esc_url(wc_get_cart_url()); ?>" class="yprint-button yprint-button-secondary">Zurück zum Warenkorb</a>
        <a href="<?php echo esc_url($next_step_url); ?>" class="yprint-button yprint-button-primary yprint-continue-button">Weiter zur Zahlung</a>
    </div>
</div>