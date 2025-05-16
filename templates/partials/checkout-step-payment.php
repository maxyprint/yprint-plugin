<?php
/**
 * YPrint Checkout Payment Step
 *
 * @package YPrint
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Previous and next step URLs
$prev_step_url = add_query_arg('step', 'address');
$next_step_url = add_query_arg('step', 'confirmation');

// Get available payment gateways
$available_gateways = WC()->payment_gateways()->get_available_payment_gateways();
?>

<div class="yprint-checkout-step yprint-payment-step">
    <h2>Zahlungsmethode auswählen</h2>
    <p class="yprint-step-description">Wähle deine bevorzugte Zahlungsmethode aus.</p>
    
    <!-- Express Checkout Options -->
    <div class="yprint-express-checkout">
        <h3>Express-Zahlungsmethoden</h3>
        <div class="yprint-express-buttons">
            <div id="yprint-apple-pay-button" class="yprint-express-button yprint-apple-pay" style="display: none;">
                <div class="yprint-express-button-content">
                    <span class="yprint-express-icon apple-pay-icon"></span>
                    <span class="yprint-express-label">Apple Pay</span>
                </div>
            </div>
            
            <div id="yprint-google-pay-button" class="yprint-express-button yprint-google-pay" style="display: none;">
                <div class="yprint-express-button-content">
                    <span class="yprint-express-icon google-pay-icon"></span>
                    <span class="yprint-express-label">Google Pay</span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Standard Payment Methods -->
    <div class="yprint-payment-methods">
        <h3>Standard-Zahlungsmethoden</h3>
        
        <form id="yprint-payment-form">
            <?php if (!empty($available_gateways)) : ?>
                <div class="yprint-payment-options">
                    <?php foreach ($available_gateways as $gateway_id => $gateway) : ?>
                    <div class="yprint-payment-option">
                        <input type="radio" id="payment_method_<?php echo esc_attr($gateway_id); ?>" name="payment_method" value="<?php echo esc_attr($gateway_id); ?>" <?php checked($gateway_id, 'stripe'); ?>>
                        <label for="payment_method_<?php echo esc_attr($gateway_id); ?>">
                            <span class="yprint-payment-option-title"><?php echo esc_html($gateway->get_title()); ?></span>
                            <?php if ($gateway->has_fields() || $gateway->get_description()) : ?>
                                <span class="yprint-payment-option-description"><?php echo wp_kses_post($gateway->get_description()); ?></span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Credit Card Fields for Stripe -->
                <div id="yprint-stripe-card-element" class="yprint-stripe-element">
                    <!-- Stripe Elements will be inserted here -->
                </div>
                <div id="yprint-stripe-card-errors" class="yprint-stripe-errors" role="alert"></div>
                
                <!-- Saved Payment Methods -->
                <?php if (is_user_logged_in()) : 
                    $user_id = get_current_user_id();
                    
                    // Get saved payment methods from custom table
                    global $wpdb;
                    $payment_methods = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT * FROM {$wpdb->prefix}payment_methods WHERE user_id = %d ORDER BY is_default DESC",
                            $user_id
                        ),
                        ARRAY_A
                    );
                    
                    if (!empty($payment_methods)) : ?>
                    <div class="yprint-saved-payment-methods">
                        <h4>Gespeicherte Zahlungsmethoden</h4>
                        
                        <div class="yprint-saved-payment-options">
                            <?php foreach ($payment_methods as $method) : 
                                $method_data = json_decode($method['method_data'], true);
                                $method_type = $method['method_type'];
                                $method_class = '';
                                $method_info = '';
                                
                                // Format display based on method type
                                if ($method_type === 'card') {
                                    $method_class = isset($method_data['card_type']) ? $method_data['card_type'] : 'unknown';
                                    $last_four = substr($method_data['card_number'], -4);
                                    $method_info = 'Karte endet auf ' . $last_four . ' • Gültig bis ' . $method_data['card_expiry'];
                                } elseif ($method_type === 'paypal') {
                                    $method_class = 'paypal';
                                    $method_info = $method_data['paypal_email'];
                                } elseif ($method_type === 'sepa') {
                                    $method_class = 'sepa';
                                    $iban = $method_data['sepa_iban'];
                                    $last_four = substr($iban, -4);
                                    $method_info = 'IBAN endet auf ' . $last_four;
                                }
                            ?>
                            <div class="yprint-saved-payment-option <?php echo esc_attr($method_class); ?> <?php echo ($method['is_default'] == 1) ? 'default' : ''; ?>">
                                <input type="radio" id="saved_payment_<?php echo esc_attr($method['id']); ?>" name="saved_payment_method" value="<?php echo esc_attr($method['id']); ?>" <?php checked($method['is_default'], 1); ?>>
                                <label for="saved_payment_<?php echo esc_attr($method['id']); ?>">
                                    <div class="yprint-payment-icon"></div>
                                    <div class="yprint-payment-details">
                                        <?php if ($method_type === 'card') : ?>
                                            <div class="yprint-payment-name"><?php echo esc_html($method_data['card_name']); ?></div>
                                        <?php elseif ($method_type === 'paypal') : ?>
                                            <div class="yprint-payment-name">PayPal</div>
                                        <?php elseif ($method_type === 'sepa') : ?>
                                            <div class="yprint-payment-name"><?php echo esc_html($method_data['sepa_name']); ?></div>
                                        <?php endif; ?>
                                        
                                        <div class="yprint-payment-info"><?php echo esc_html($method_info); ?></div>
                                    </div>
                                    
                                    <?php if ($method['is_default'] == 1) : ?>
                                    <div class="yprint-payment-default-badge">Standard</div>
                                    <?php endif; ?>
                                </label>
                            </div>
                            <?php endforeach; ?>
                            
                            <!-- Option for a new card -->
                            <div class="yprint-saved-payment-option yprint-new-payment">
                                <input type="radio" id="new_payment_method" name="saved_payment_method" value="new">
                                <label for="new_payment_method">
                                    <div class="yprint-payment-icon new-card-icon"></div>
                                    <div class="yprint-payment-details">
                                        <div class="yprint-payment-name">Neue Zahlungsmethode</div>
                                        <div class="yprint-payment-info">Eine neue Kredit- oder Debitkarte hinzufügen</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                
                <!-- Save Payment Method Option -->
                <div class="yprint-save-payment-option">
                    <input type="checkbox" id="save_payment_method" name="save_payment_method" checked>
                    <label for="save_payment_method">Zahlungsmethode für zukünftige Bestellungen speichern</label>
                </div>
                
            <?php else : ?>
                <div class="yprint-no-payment-methods">
                    <p>Es sind keine Zahlungsmethoden verfügbar. Bitte kontaktiere uns für Unterstützung.</p>
                </div>
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Step Navigation -->
    <div class="yprint-step-navigation">
        <a href="<?php echo esc_url($prev_step_url); ?>" class="yprint-button yprint-button-secondary">Zurück zu Adresse</a>
        <a href="<?php echo esc_url($next_step_url); ?>" class="yprint-button yprint-button-primary yprint-continue-button">Weiter zur Bestätigung</a>
    </div>
</div>