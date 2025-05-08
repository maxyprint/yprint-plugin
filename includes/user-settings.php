<?php
/**
 * User Settings System for YPrint
 *
 * @package YPrint
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Main shortcode for the entire settings page
 * 
 * Usage: [yprint_user_settings]
 */
function yprint_user_settings_shortcode() {
    ob_start();
    
    // Get current view (default: 'personal')
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'personal';
    
    // Process message if present
    $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
    $message_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'info';
    
    if ($message) {
        echo '<div class="yprint-message yprint-message-' . esc_attr($message_type) . '">';
        echo esc_html($message);
        echo '</div>';
    }
    
    // Define tabs
    $tabs = array(
        'personal' => 'Persönliche Daten',
        'billing' => 'Rechnungsdaten',
        'shipping' => 'Lieferadresse',
    );
    
    // Desktop tabs navigation
    echo '<div class="yprint-settings-tabs">';
    foreach ($tabs as $tab_id => $tab_name) {
        $active_class = ($current_tab === $tab_id) ? ' active' : '';
        echo '<a href="?tab=' . esc_attr($tab_id) . '" class="yprint-tab' . esc_attr($active_class) . '">' . esc_html($tab_name) . '</a>';
    }
    echo '</div>';

    // Mobile dropdown navigation
    echo '<div class="yprint-mobile-tabs">';
    echo '<select class="yprint-mobile-select" id="yprint-mobile-tab-select">';
    foreach ($tabs as $tab_id => $tab_name) {
        $selected = ($current_tab === $tab_id) ? ' selected' : '';
        echo '<option value="' . esc_attr($tab_id) . '"' . $selected . '>' . esc_html($tab_name) . '</option>';
    }
    echo '</select>';
    echo '</div>';

    // JavaScript for tab navigation
    echo '<script>
    jQuery(document).ready(function($) {
        // Mobile Tab Selection Handler
        $("#yprint-mobile-tab-select").on("change", function() {
            var selectedTab = $(this).val();
            window.location.href = window.location.pathname + "?tab=" + selectedTab;
        });
        
        // Read URL parameters and set tab accordingly
        function getParameterByName(name, url) {
            if (!url) url = window.location.href;
            name = name.replace(/[\[\]]/g, "\\$&");
            var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
                results = regex.exec(url);
            if (!results) return null;
            if (!results[2]) return "";
            return decodeURIComponent(results[2].replace(/\+/g, " "));
        }
        
        var tabParam = getParameterByName("tab");
        if (tabParam) {
            // Desktop-Tabs
            $(".yprint-tab").removeClass("active");
            $(".yprint-tab[href=\"?tab=" + tabParam + "\"]").addClass("active");
            
            // Mobile-Dropdown
            $("#yprint-mobile-tab-select").val(tabParam);
        }
    });
    </script>';
    
    // Display active tab content
    echo '<div class="yprint-settings-content">';
    
    // Insert appropriate shortcode
    switch ($current_tab) {
        case 'personal':
            echo do_shortcode('[yprint_personal_settings]');
            break;
        case 'billing':
            echo do_shortcode('[yprint_billing_settings]');
            break;
        case 'shipping':
            echo do_shortcode('[yprint_shipping_settings]');
            break;
    }
    
    echo '</div>';
    
    // Include CSS
    echo yprint_settings_styles();
    
    return ob_get_clean();
}
add_shortcode('yprint_user_settings', 'yprint_user_settings_shortcode');

/**
 * Shared styles for all settings tabs
 */
function yprint_settings_styles() {
    ob_start();
    ?>
    <style>
        /* Common styles for settings pages */
        .yprint-settings-page {
            font-family: 'SF Pro Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 30px 0;
        }
        
        .yprint-settings-tabs {
            display: flex;
            border-bottom: 1px solid #eaeaea;
            margin-bottom: 30px;
        }
        
        .yprint-tab {
            padding: 12px 20px;
            color: #1d1d1f;
            text-decoration: none;
            font-weight: 500;
            position: relative;
            transition: color 0.2s ease;
            margin-right: 10px;
        }
        
        .yprint-tab:hover {
            color: #2997FF;
        }
        
        .yprint-tab.active {
            color: #2997FF;
        }
        
        .yprint-tab.active:after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: #2997FF;
        }
        
        .yprint-mobile-tabs {
            display: none;
            margin-bottom: 30px;
        }
        
        .yprint-mobile-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #DFDFDF;
            border-radius: 6px;
            font-size: 1rem;
            background-color: #FFF;
            transition: all 0.2s ease;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }
        
        .yprint-mobile-select:focus {
            border-color: #2997FF;
            box-shadow: 0 0 0 2px rgba(41, 151, 255, 0.1);
            outline: none;
        }
        
        .yprint-settings-content {
            padding: 30px 0;
        }
        
        .yprint-settings-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        
        .yprint-form-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .yprint-form-row {
            display: flex;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .yprint-form-row > div {
            flex: 1;
        }
        
        .yprint-form-label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #1d1d1f;
            font-size: 0.95rem;
        }
        
        .yprint-form-input,
        .yprint-form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #DFDFDF;
            border-radius: 6px;
            font-size: 1rem;
            background-color: #FFF;
            transition: all 0.2s ease;
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        
        .yprint-form-select {
            background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%23333' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'></polyline></svg>");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }
        
        .yprint-form-input:focus,
        .yprint-form-select:focus {
            border-color: #2997FF;
            box-shadow: 0 0 0 2px rgba(41, 151, 255, 0.1);
            outline: none;
        }
        
        .yprint-form-input:hover,
        .yprint-form-select:hover {
            border-color: #DFDFDF;
        }
        
        .yprint-checkbox-group {
            display: flex;
            align-items: center;
            margin: 10px 0;
        }
        
        .yprint-checkbox-group input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .yprint-checkbox-group label {
            line-height: 1.4;
            color: #1d1d1f;
        }
        
        .yprint-conditional-fields {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #f5f5f7;
        }
        
        .yprint-button {
            background-color: transparent;
            color: #2997FF;
            padding: 12px 24px;
            border: 1px solid #2997FF;
            border-radius: 6px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            text-align: center;
            display: inline-block;
        }
        
        .yprint-button:hover {
            background-color: #2997FF;
            color: #FFF;
        }
        
        .yprint-message {
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 0.95rem;
        }
        
        .yprint-message-success {
            background-color: #E8F5E9;
            border-left: 4px solid #4CAF50;
            color: #2E7D32;
        }
        
        .yprint-message-error {
            background-color: #FFEBEE;
            border-left: 4px solid #F44336;
            color: #C62828;
        }
        
        .yprint-message-info {
            background-color: #E3F2FD;
            border-left: 4px solid #2196F3;
            color: #1565C0;
        }
        
        /* Address Autocomplete Styling */
        .yprint-address-search {
            position: relative;
        }
        
        .yprint-address-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #D1D1D6;
            border-radius: 6px;
            z-index: 1000;
            max-height: 200px;
            overflow-y: auto;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        
        .yprint-address-suggestion {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f5f5f7;
        }
        
        .yprint-address-suggestion:last-child {
            border-bottom: none;
        }
        
        .yprint-address-suggestion:hover {
            background-color: #f8f9fa;
        }
        
        .yprint-suggestion-main {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .yprint-suggestion-secondary {
            font-size: 0.85rem;
            color: #6e6e73;
        }
        
        .yprint-loader {
            border: 2px solid #f3f3f3;
            border-radius: 50%;
            border-top: 2px solid #2997FF;
            width: 20px;
            height: 20px;
            animation: yprint-spin 1s linear infinite;
            margin: 10px auto;
            display: none;
        }
        
        @keyframes yprint-spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .yprint-form-input,
            .yprint-form-select,
            .yprint-mobile-select {
                border-color: #DFDFDF !important;
            }
            
            .yprint-form-row {
                flex-direction: column;
            }
            
            .yprint-settings-content {
                padding: 15px;
            }
            
            .yprint-settings-tabs {
                display: none;
            }
            
            .yprint-mobile-tabs {
                display: block;
            }
        }
        
        @media (max-width: 600px) {
            .yprint-form-row {
                flex-direction: column;
            }
            
            .yprint-settings-content {
                padding-top: 0;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Personal Settings Shortcode
 * 
 * Usage: [yprint_personal_settings]
 */
function yprint_personal_settings_shortcode() {
    ob_start();
    global $wpdb;
    $user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $message = '';
    $message_type = '';
    
    // Get data from database
    $user_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}personal_data WHERE user_id = %d",
            $user_id
        ),
        ARRAY_A
    );

    // Set default values
    $first_name = isset($user_data['first_name']) ? esc_attr($user_data['first_name']) : '';
    $last_name = isset($user_data['last_name']) ? esc_attr($user_data['last_name']) : '';
    $birthdate = isset($user_data['birthdate']) ? esc_attr($user_data['birthdate']) : '';
    $current_email = $current_user->user_email;

    // Form processing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['personal_settings_nonce']) && 
        wp_verify_nonce($_POST['personal_settings_nonce'], 'save_personal_settings')) {
        
        $email_changed = false;
        $needs_logout = false;
        
        // Process email change
        if (isset($_POST['email']) && !empty($_POST['email'])) {
            $new_email = sanitize_email($_POST['email']);
            
            if ($new_email !== $current_email) {
                // Check if email already exists
                if (email_exists($new_email)) {
                    $message = 'Diese E-Mail-Adresse wird bereits verwendet.';
                    $message_type = 'error';
                } else {
                    $email_update = wp_update_user([
                        'ID' => $user_id,
                        'user_email' => $new_email
                    ]);
                    
                    if (!is_wp_error($email_update)) {
                        // Reset email verification
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}email_verifications 
                            SET email_verified = 0, 
                                updated_at = NOW() 
                            WHERE user_id = %d",
                            $user_id
                        ));
                        
                        $email_changed = true;
                        $needs_logout = true;
                    } else {
                        $message = 'Fehler beim Ändern der E-Mail-Adresse.';
                        $message_type = 'error';
                    }
                }
            }
        }

        // Process personal data
        $fields_to_update = [];
        
        if (isset($_POST['first_name']) && !empty($_POST['first_name'])) {
            $fields_to_update['first_name'] = sanitize_text_field($_POST['first_name']);
            
            // Also update WooCommerce Billing/Shipping First Name
            update_user_meta($user_id, 'billing_first_name', $fields_to_update['first_name']);
            update_user_meta($user_id, 'shipping_first_name', $fields_to_update['first_name']);
        }
        
        if (isset($_POST['last_name']) && !empty($_POST['last_name'])) {
            $fields_to_update['last_name'] = sanitize_text_field($_POST['last_name']);
            
            // Also update WooCommerce Billing/Shipping Last Name
            update_user_meta($user_id, 'billing_last_name', $fields_to_update['last_name']);
            update_user_meta($user_id, 'shipping_last_name', $fields_to_update['last_name']);
        }
        
        if (isset($_POST['birthdate']) && !empty($_POST['birthdate'])) {
            $fields_to_update['birthdate'] = sanitize_text_field($_POST['birthdate']);
        }

        if (!empty($fields_to_update)) {
            if ($user_data) {
                $update_result = $wpdb->update(
                    $wpdb->prefix . 'personal_data',
                    $fields_to_update,
                    ['user_id' => $user_id]
                );
            } else {
                $fields_to_update['user_id'] = $user_id;
                $update_result = $wpdb->insert($wpdb->prefix . 'personal_data', $fields_to_update);
            }
            
            $message = 'Deine persönlichen Daten wurden erfolgreich gespeichert.';
            $message_type = 'success';
        }
        
        // If email changed, show logout overlay
        if ($needs_logout && $email_changed) {
            ?>
            <div id="emailChangeOverlay" class="yprint-overlay">
                <div class="yprint-overlay-content">
                    <h3>E-Mail-Adresse wird geändert</h3>
                    <div class="yprint-loader" style="display: block;"></div>
                    <p>Sie werden nun ausgeloggt und zum Login weitergeleitet.</p>
                    <p>Bitte loggen Sie sich mit Ihrer neuen E-Mail-Adresse ein.</p>
                </div>
            </div>
            
            <script>
            jQuery(document).ready(function($) {
                // Force logout and redirect
                function forceLogoutAndRedirect() {
                    // Show overlay
                    $('#emailChangeOverlay').css('display', 'flex');
                    
                    // Forced Logout via AJAX
                    $.ajax({
                        url: '<?php echo admin_url('admin-ajax.php'); ?>',
                        type: 'POST',
                        data: {
                            action: 'custom_force_logout',
                            security: '<?php echo wp_create_nonce('force_logout_nonce'); ?>'
                        },
                        success: function() {
                            // Redirect after short delay
                            setTimeout(function() {
                                window.location.href = '<?php echo esc_url(home_url('/login/')); ?>';
                            }, 2000);
                        },
                        error: function() {
                            // On AJAX error, redirect anyway
                            window.location.href = '<?php echo esc_url(home_url('/login/')); ?>';
                        }
                    });
                }
                
                // Call logout function
                forceLogoutAndRedirect();
            });
            </script>
            <?php
        }
    }
    
    // Output form
    ?>
    <div class="yprint-settings-page">
        
        <?php if ($message): ?>
        <div class="yprint-message yprint-message-<?php echo esc_attr($message_type); ?>">
            <?php echo esc_html($message); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="yprint-settings-form" id="personal-settings-form">
            <?php wp_nonce_field('save_personal_settings', 'personal_settings_nonce'); ?>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="first_name" class="yprint-form-label">Vorname</label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($first_name); ?>" 
                           placeholder="Vorname">
                </div>
                
                <div class="yprint-form-group">
                    <label for="last_name" class="yprint-form-label">Nachname</label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($last_name); ?>" 
                           placeholder="Nachname">
                </div>
            </div>
            
            <div class="yprint-form-group">
                <label for="birthdate" class="yprint-form-label">Geburtsdatum</label>
                <input type="date" 
                       id="birthdate" 
                       name="birthdate" 
                       class="yprint-form-input" 
                       value="<?php echo esc_attr($birthdate); ?>">
            </div>
            
            <div class="yprint-form-group">
                <label for="email" class="yprint-form-label">E-Mail-Adresse</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="yprint-form-input" 
                       value="<?php echo esc_attr($current_email); ?>" 
                       placeholder="E-Mail-Adresse">
                <div class="yprint-field-hint" id="email-change-warning" style="display: none; margin-top: 8px; color: #2997FF; font-size: 0.9rem;">
                    Wenn du deine E-Mail-Adresse änderst, wirst du ausgeloggt und musst dich mit der neuen Adresse wieder einloggen.
                </div>
            </div>
            
            <div>
                <button type="submit" class="yprint-button">Speichern</button>
            </div>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var originalEmail = '<?php echo esc_js($current_email); ?>';
        var emailInput = $('#email');
        var emailWarning = $('#email-change-warning');
        
        // Show warning when email is changed
        emailInput.on('input', function() {
            if ($(this).val() !== originalEmail) {
                emailWarning.slideDown();
            } else {
                emailWarning.slideUp();
            }
        });
    });
    </script>
    <?php
    
    return ob_get_clean();
}
add_shortcode('yprint_personal_settings', 'yprint_personal_settings_shortcode');

/**
 * Billing Settings Shortcode
 * 
 * Usage: [yprint_billing_settings]
 */
function yprint_billing_settings_shortcode() {
    ob_start();
    
    // Get current user data
    $user_id = get_current_user_id();
    $message = '';
    $message_type = '';
    
    // Retrieve WooCommerce billing fields
    $billing_first_name = get_user_meta($user_id, 'billing_first_name', true);
    $billing_last_name = get_user_meta($user_id, 'billing_last_name', true);
    $billing_company = get_user_meta($user_id, 'billing_company', true);
    $billing_vat = get_user_meta($user_id, 'billing_vat', true);
    $billing_address_1 = get_user_meta($user_id, 'billing_address_1', true);
    $billing_address_2 = get_user_meta($user_id, 'billing_address_2', true);
    $billing_postcode = get_user_meta($user_id, 'billing_postcode', true);
    $billing_city = get_user_meta($user_id, 'billing_city', true);
    $billing_country = get_user_meta($user_id, 'billing_country', true) ?: 'DE';
    $alt_billing_email = get_user_meta($user_id, 'alt_billing_email', true);
    $is_company = get_user_meta($user_id, 'is_company', true);

    // Form processing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['billing_settings_nonce']) && 
        wp_verify_nonce($_POST['billing_settings_nonce'], 'save_billing_settings')) {
        
        $updated = false;
        $email_changed = false;
        
        // Update standard fields
        $fields_to_update = [
            'billing_first_name' => isset($_POST['billing_first_name']) ? sanitize_text_field($_POST['billing_first_name']) : '',
            'billing_last_name' => isset($_POST['billing_last_name']) ? sanitize_text_field($_POST['billing_last_name']) : '',
            'billing_address_1' => isset($_POST['billing_address_1']) ? sanitize_text_field($_POST['billing_address_1']) : '',
            'billing_address_2' => isset($_POST['billing_address_2']) ? sanitize_text_field($_POST['billing_address_2']) : '',
            'billing_postcode' => isset($_POST['billing_postcode']) ? sanitize_text_field($_POST['billing_postcode']) : '',
            'billing_city' => isset($_POST['billing_city']) ? sanitize_text_field($_POST['billing_city']) : '',
            'billing_country' => isset($_POST['billing_country']) ? sanitize_text_field($_POST['billing_country']) : 'DE',
        ];

        // Update company data
        $is_company = isset($_POST['is_company']);
        update_user_meta($user_id, 'is_company', $is_company);
        
        if ($is_company) {
            $fields_to_update['billing_company'] = isset($_POST['billing_company']) ? sanitize_text_field($_POST['billing_company']) : '';
            $fields_to_update['billing_vat'] = isset($_POST['billing_vat']) ? sanitize_text_field($_POST['billing_vat']) : '';
        }

        // Alternative billing email
        if (isset($_POST['different_billing_email']) && $_POST['different_billing_email'] === 'on') {
            if (isset($_POST['alt_billing_email']) && !empty($_POST['alt_billing_email'])) {
                $new_billing_email = sanitize_email($_POST['alt_billing_email']);
                $old_billing_email = get_user_meta($user_id, 'alt_billing_email', true);
                
                if ($new_billing_email !== $old_billing_email) {
                    // Generate verification token
                    $verification_token = wp_generate_password(32, false);
                    update_user_meta($user_id, 'billing_email_verification_token', $verification_token);
                    
                    // Send email to new billing email
                    $verification_link = add_query_arg(
                        array(
                            'action' => 'reject_billing_email',
                            'token' => $verification_token,
                            'user_id' => $user_id
                        ),
                        home_url()
                    );

                    $user = get_userdata($user_id);
                    $message_content = sprintf(
                        'Die E-Mail-Adresse %s wurde als Empfänger für Rechnungen von %s bei YPrint eingetragen.<br><br>
                        Falls Sie diese Änderung nicht veranlasst haben oder nicht möchten, klicken Sie bitte hier:<br><br>
                        <a href="%s" style="display: inline-block; padding: 10px 20px; background-color: #2997FF; color: white; text-decoration: none; border-radius: 5px;">Diese Einstellung ablehnen</a>',
                        $new_billing_email,
                        $user->display_name,
                        esc_url($verification_link)
                    );
                    
                    // Use email template function if available
                    if (function_exists('yprint_get_email_template')) {
                        $message = yprint_get_email_template('Bestätigung: Rechnungsempfänger', 'Hallo', $message_content);
                    } else {
                        $message = $message_content;
                    }

                    $headers = array('Content-Type: text/html; charset=UTF-8');
                    wp_mail($new_billing_email, 'Bestätigung: Rechnungsempfänger bei YPrint', $message, $headers);
                    
                    update_user_meta($user_id, 'alt_billing_email', $new_billing_email);
                    update_user_meta($user_id, 'billing_email', $new_billing_email);
                    $email_changed = true;
                }
            }
        } else {
            // If checkbox is not selected, remove alternative email
            delete_user_meta($user_id, 'alt_billing_email');
            $user = get_userdata($user_id);
            update_user_meta($user_id, 'billing_email', $user->user_email);
        }

        // Update WooCommerce metadata
        foreach ($fields_to_update as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
        
        $message = 'Deine Rechnungsdaten wurden erfolgreich gespeichert.';
        $message_type = 'success';
        
        // Redirect to keep URL clean and prevent form resubmit
        if (!$email_changed) {
            wp_redirect(add_query_arg(
                array(
                    'tab' => 'billing',
                    'message' => urlencode($message),
                    'type' => $message_type
                ),
                remove_query_arg(array('message', 'type'))
            ));
            exit;
        }
    }
    
    // Output form
    ?>
    <div class="yprint-settings-page">
        
        <?php if ($message): ?>
        <div class="yprint-message yprint-message-<?php echo esc_attr($message_type); ?>">
            <?php echo esc_html($message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Email Change Overlay -->
        <div id="emailChangeOverlay" class="yprint-overlay" style="display: none;">
            <div class="yprint-overlay-content">
                <h3>Rechnungs-E-Mail wurde geändert</h3>
                <p>Eine Bestätigungs-E-Mail wurde an die neue Adresse gesendet.</p>
            </div>
        </div>
        
        <form method="POST" class="yprint-settings-form" id="billing-settings-form">
            <?php wp_nonce_field('save_billing_settings', 'billing_settings_nonce'); ?>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="billing_first_name" class="yprint-form-label">Vorname</label>
                    <input type="text" 
                           id="billing_first_name" 
                           name="billing_first_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($billing_first_name); ?>" 
                           placeholder="Vorname" 
                           required>
                </div>
                <div class="yprint-form-group">
                    <label for="billing_last_name" class="yprint-form-label">Nachname</label>
                    <input type="text" 
                           id="billing_last_name" 
                           name="billing_last_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($billing_last_name); ?>" 
                           placeholder="Nachname" 
                           required>
                </div>
            </div>
            
            <!-- Company data -->
            <div class="yprint-checkbox-group">
                <input type="checkbox" 
                       id="is_company" 
                       name="is_company" 
                       <?php checked($is_company, true); ?>>
                <label for="is_company">Ich bin Unternehmer</label>
            </div>
            
            <div id="company_fields" class="yprint-conditional-fields" <?php echo $is_company ? 'style="display: block;"' : 'style="display: none;"'; ?>>
                <div class="yprint-form-row">
                    <div class="yprint-form-group">
                        <label for="billing_company" class="yprint-form-label">Unternehmensname</label>
                        <input type="text" 
                               id="billing_company" 
                               name="billing_company" 
                               class="yprint-form-input" 
                               value="<?php echo esc_attr($billing_company); ?>" 
                               placeholder="Unternehmensname">
                    </div>
                    
                    <div class="yprint-form-group">
                        <label for="billing_vat" class="yprint-form-label">USt.-ID</label>
                        <input type="text" 
                               id="billing_vat" 
                               name="billing_vat" 
                               class="yprint-form-input" 
                               value="<?php echo esc_attr($billing_vat); ?>" 
                               placeholder="USt.-ID">
                    </div>
                </div>
            </div>
            
            <!-- Address data with search -->
            <div class="yprint-form-group yprint-address-search">
                <label for="address_search_billing" class="yprint-form-label">Adresse suchen</label>
                <input type="text" 
                       id="address_search_billing" 
                       class="yprint-form-input" 
                       placeholder="Adresse eingeben...">
                <div id="billing_address_loader" class="yprint-loader"></div>
                <div id="billing_address_suggestions" class="yprint-address-suggestions"></div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="billing_address_1" class="yprint-form-label">Straße</label>
                    <input type="text" 
                           id="billing_address_1" 
                           name="billing_address_1" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($billing_address_1); ?>" 
                           placeholder="Straße" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="billing_address_2" class="yprint-form-label">Hausnummer</label>
                    <input type="text" 
                           id="billing_address_2" 
                           name="billing_address_2" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($billing_address_2); ?>" 
                           placeholder="Hausnummer" 
                           required>
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="billing_postcode" class="yprint-form-label">PLZ</label>
                    <input type="text" 
                           id="billing_postcode" 
                           name="billing_postcode" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($billing_postcode); ?>" 
                           placeholder="PLZ" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="billing_city" class="yprint-form-label">Stadt</label>
                    <input type="text" 
                           id="billing_city" 
                           name="billing_city" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($billing_city); ?>" 
                           placeholder="Stadt" 
                           required>
                </div>
            </div>
            
            <div class="yprint-form-group">
                <label for="billing_country" class="yprint-form-label">Land</label>
                <select id="billing_country" 
                        name="billing_country" 
                        class="yprint-form-select" 
                        required>
                    <?php
                    if (class_exists('WC_Countries')) {
                        $countries_obj = new WC_Countries();
                        $countries = $countries_obj->get_countries();
                        foreach ($countries as $code => $name) {
                            $selected = ($billing_country === $code) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                    } else {
                        // Fallback if WooCommerce's countries class is not available
                        $countries = array(
                            'DE' => 'Deutschland',
                            'AT' => 'Österreich',
                            'CH' => 'Schweiz',
                        );
                        foreach ($countries as $code => $name) {
                            $selected = ($billing_country === $code) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <!-- Alternative billing email -->
            <div class="yprint-checkbox-group">
                <input type="checkbox" 
                       id="different_billing_email" 
                       name="different_billing_email" 
                       <?php checked(!empty($alt_billing_email), true); ?>>
                <label for="different_billing_email">Abweichende E-Mail für Rechnungen</label>
            </div>
            
            <div id="different_billing_email_field" class="yprint-conditional-fields" 
                 <?php echo !empty($alt_billing_email) ? 'style="display: block;"' : 'style="display: none;"'; ?>>
                <div class="yprint-form-group">
                    <label for="alt_billing_email" class="yprint-form-label">E-Mail für Rechnungen</label>
                    <input type="email" 
                           id="alt_billing_email" 
                           name="alt_billing_email" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($alt_billing_email); ?>" 
                           placeholder="E-Mail für Rechnungen">
                </div>
            </div>
            
            <div>
                <button type="submit" class="yprint-button">Speichern</button>
            </div>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // HERE API Initialization
        const API_KEY = 'xPlTGXIrjg1O6Oea3e2gvo5lrN-iO1gT47Sc-VojWdU';
        
        // Company field toggle
        $('#is_company').change(function() {
            if (this.checked) {
                $('#company_fields').slideDown(300);
            } else {
                $('#company_fields').slideUp(300);
            }
        });

        // Alternative billing email toggle
        $('#different_billing_email').change(function() {
            if (this.checked) {
                $('#different_billing_email_field').slideDown(300);
            } else {
                $('#different_billing_email_field').slideUp(300);
                $('#alt_billing_email').val('');
            }
        });

        <?php if (isset($email_changed) && $email_changed): ?>
        // Show overlay
        $('#emailChangeOverlay').css('display', 'flex');
        
        // Hide after 3 seconds
        setTimeout(function() {
            $('#emailChangeOverlay').fadeOut();
        }, 3000);
        <?php endif; ?>
        
        // Hide success message after 3 seconds
        setTimeout(function() {
            $('.yprint-message').fadeOut();
        }, 3000);
        
        // Address search for billing address
        setupAddressSearch('billing');
        
        // Address search function
        function setupAddressSearch(prefix) {
            let searchTimeout;
            $(`#address_search_${prefix}`).on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                
                $(`#${prefix}_address_loader`).hide();
                
                if (query.length < 3) {
                    $(`#${prefix}_address_suggestions`).hide();
                    return;
                }

                $(`#${prefix}_address_loader`).show();

                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: 'https://geocode.search.hereapi.com/v1/geocode',
                        data: {
                            q: query,
                            apiKey: API_KEY,
                            limit: 5,
                            lang: 'de',
                            in: 'countryCode:DEU,AUT'
                        },
                        type: 'GET',
                        success: function(data) {
                            $(`#${prefix}_address_loader`).hide();
                            const $suggestions = $(`#${prefix}_address_suggestions`);
                            $suggestions.empty();

                            if (data && data.items && data.items.length > 0) {
                                data.items.forEach(function(item) {
                                    const address = item.address;
                                    
                                    // Main address line
                                    const mainLine = [
                                        address.street,
                                        address.houseNumber,
                                        address.postalCode,
                                        address.city
                                    ].filter(Boolean).join(' ');

                                    // Additional information
                                    const secondaryLine = [
                                        address.district,
                                        address.state,
                                        address.countryName
                                    ].filter(Boolean).join(', ');

                                    const $suggestion = $('<div>').addClass('yprint-address-suggestion')
                                        .append($('<div>').addClass('yprint-suggestion-main').text(mainLine))
                                        .append($('<div>').addClass('yprint-suggestion-secondary').text(secondaryLine))
                                        .data('address', address);

                                    $suggestion.on('click', function() {
                                        const address = $(this).data('address');
                                        
                                        // Separate street and house number
                                        const street = address.street || '';
                                        const houseNumber = address.houseNumber || '';
                                        
                                        // Fill fields
                                        $(`#${prefix}_address_1`).val(street);
                                        $(`#${prefix}_address_2`).val(houseNumber);
                                        $(`#${prefix}_postcode`).val(address.postalCode || '');
                                        $(`#${prefix}_city`).val(address.city || '');
                                        
                                        // Set country
                                        if (address.countryCode) {
                                            const countryCode = address.countryCode.toUpperCase();
                                            $(`#${prefix}_country`).val(countryCode);
                                        }

                                        $suggestions.hide();
                                        $(`#address_search_${prefix}`).val('');
                                    });

                                    $suggestions.append($suggestion);
                                });

                                $suggestions.show();
                            }
                        },
                        error: function(xhr, status, error) {
                            $(`#${prefix}_address_loader`).hide();
                            console.error('Error in address search:', error);
                        }
                    });
                }, 500);
            });
            
            // Click outside closes suggestions
            $(document).on('click', function(e) {
                if (!$(e.target).closest(`#address_search_${prefix}, #${prefix}_address_suggestions`).length) {
                    $(`#${prefix}_address_suggestions`).hide();
                }
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
}
add_shortcode('yprint_billing_settings', 'yprint_billing_settings_shortcode');

/**
 * Shipping Settings Shortcode
 * 
 * Usage: [yprint_shipping_settings]
 */
function yprint_shipping_settings_shortcode() {
    ob_start();
    
    // Get current user data
    $user_id = get_current_user_id();
    $message = '';
    $message_type = '';
    
    // Retrieve WooCommerce shipping fields
    $shipping_first_name = get_user_meta($user_id, 'shipping_first_name', true);
    $shipping_last_name = get_user_meta($user_id, 'shipping_last_name', true);
    $shipping_company = get_user_meta($user_id, 'shipping_company', true);
    $shipping_address_1 = get_user_meta($user_id, 'shipping_address_1', true);
    $shipping_address_2 = get_user_meta($user_id, 'shipping_address_2', true);
    $shipping_postcode = get_user_meta($user_id, 'shipping_postcode', true);
    $shipping_city = get_user_meta($user_id, 'shipping_city', true);
    $shipping_country = get_user_meta($user_id, 'shipping_country', true) ?: 'DE';
    $is_company_shipping = get_user_meta($user_id, 'is_company_shipping', true);
    
    // If company is set in billing data, also suggest it here
    $billing_company = get_user_meta($user_id, 'billing_company', true);
    $is_company_billing = get_user_meta($user_id, 'is_company', true);

    // Form processing
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['shipping_settings_nonce']) && 
        wp_verify_nonce($_POST['shipping_settings_nonce'], 'save_shipping_settings')) {
        
        // Update standard fields
        $fields_to_update = [
            'shipping_first_name' => isset($_POST['shipping_first_name']) ? sanitize_text_field($_POST['shipping_first_name']) : '',
            'shipping_last_name' => isset($_POST['shipping_last_name']) ? sanitize_text_field($_POST['shipping_last_name']) : '',
            'shipping_address_1' => isset($_POST['shipping_address_1']) ? sanitize_text_field($_POST['shipping_address_1']) : '',
            'shipping_address_2' => isset($_POST['shipping_address_2']) ? sanitize_text_field($_POST['shipping_address_2']) : '',
            'shipping_postcode' => isset($_POST['shipping_postcode']) ? sanitize_text_field($_POST['shipping_postcode']) : '',
            'shipping_city' => isset($_POST['shipping_city']) ? sanitize_text_field($_POST['shipping_city']) : '',
            'shipping_country' => isset($_POST['shipping_country']) ? sanitize_text_field($_POST['shipping_country']) : 'DE',
        ];

        // Update company data
        $is_company_shipping = isset($_POST['is_company_shipping']);
        update_user_meta($user_id, 'is_company_shipping', $is_company_shipping);
        
        if ($is_company_shipping) {
            $fields_to_update['shipping_company'] = isset($_POST['shipping_company']) ? sanitize_text_field($_POST['shipping_company']) : '';
        }

        // Update WooCommerce metadata
        foreach ($fields_to_update as $key => $value) {
            update_user_meta($user_id, $key, $value);
        }
        
        $message = 'Deine Lieferadresse wurde erfolgreich gespeichert.';
        $message_type = 'success';
        
        // Redirect to keep URL clean and prevent form resubmit
        wp_redirect(add_query_arg(
            array(
                'tab' => 'shipping',
                'message' => urlencode($message),
                'type' => $message_type
            ),
            remove_query_arg(array('message', 'type'))
        ));
        exit;
    }
    
    // Output form
    ?>
    <div class="yprint-settings-page">
        
        <?php if ($message): ?>
        <div class="yprint-message yprint-message-<?php echo esc_attr($message_type); ?>">
            <?php echo esc_html($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="yprint-info-message" style="background-color: #E3F2FD; padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 0.9rem; border-left: 4px solid #2196F3; color: #0D47A1;">
            <p style="margin: 0;">Hier kannst du deine Standard-Lieferadresse hinterlegen. Im Bestellprozess hast du weiterhin die Möglichkeit, eine abweichende Lieferadresse anzugeben.</p>
        </div>
        
        <form method="POST" class="yprint-settings-form" id="shipping-settings-form">
            <?php wp_nonce_field('save_shipping_settings', 'shipping_settings_nonce'); ?>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="shipping_first_name" class="yprint-form-label">Vorname</label>
                    <input type="text" 
                           id="shipping_first_name" 
                           name="shipping_first_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_first_name); ?>" 
                           placeholder="Vorname" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="shipping_last_name" class="yprint-form-label">Nachname</label>
                    <input type="text" 
                           id="shipping_last_name" 
                           name="shipping_last_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_last_name); ?>" 
                           placeholder="Nachname" 
                           required>
                </div>
            </div>
            
            <!-- Company data -->
            <div class="yprint-checkbox-group">
                <input type="checkbox" 
                       id="is_company_shipping" 
                       name="is_company_shipping" 
                       <?php checked($is_company_shipping, true); ?>>
                <label for="is_company_shipping">Lieferung an Firma</label>
            </div>
            
            <div id="company_shipping_fields" class="yprint-conditional-fields" <?php echo $is_company_shipping ? 'style="display: block;"' : 'style="display: none;"'; ?>>
                <div class="yprint-form-group">
                    <label for="shipping_company" class="yprint-form-label">Firmenname</label>
                    <input type="text" 
                           id="shipping_company" 
                           name="shipping_company" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_company); ?>" 
                           placeholder="Firmenname">
                    <?php if ($is_company_billing && $billing_company): ?>
                    <div class="yprint-company-suggestion" style="margin-top: 8px; font-size: 0.9rem;">
                        <a href="#" id="use-billing-company" style="color: #2997FF; text-decoration: none;">
                            '<?php echo esc_html($billing_company); ?>' aus Rechnungsdaten übernehmen
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Address data with search -->
            <div class="yprint-form-group yprint-address-search">
                <label for="address_search_shipping" class="yprint-form-label">Adresse suchen</label>
                <input type="text" 
                       id="address_search_shipping" 
                       class="yprint-form-input" 
                       placeholder="Adresse eingeben...">
                <div id="shipping_address_loader" class="yprint-loader"></div>
                <div id="shipping_address_suggestions" class="yprint-address-suggestions"></div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="shipping_address_1" class="yprint-form-label">Straße</label>
                    <input type="text" 
                           id="shipping_address_1" 
                           name="shipping_address_1" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_address_1); ?>" 
                           placeholder="Straße" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="shipping_address_2" class="yprint-form-label">Hausnummer</label>
                    <input type="text" 
                           id="shipping_address_2" 
                           name="shipping_address_2" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_address_2); ?>" 
                           placeholder="Hausnummer" 
                           required>
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="shipping_postcode" class="yprint-form-label">PLZ</label>
                    <input type="text" 
                           id="shipping_postcode" 
                           name="shipping_postcode" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_postcode); ?>" 
                           placeholder="PLZ" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="shipping_city" class="yprint-form-label">Stadt</label>
                    <input type="text" 
                           id="shipping_city" 
                           name="shipping_city" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_city); ?>" 
                           placeholder="Stadt" 
                           required>
                </div>
            </div>
            
            <div class="yprint-form-group">
                <label for="shipping_country" class="yprint-form-label">Land</label>
                <select id="shipping_country" 
                        name="shipping_country" 
                        class="yprint-form-select" 
                        required>
                    <?php
                    if (class_exists('WC_Countries')) {
                        $countries_obj = new WC_Countries();
                        $countries = $countries_obj->get_shipping_countries();
                        foreach ($countries as $code => $name) {
                            $selected = ($shipping_country === $code) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                    } else {
                        // Fallback if WooCommerce's countries class is not available
                        $countries = array(
                            'DE' => 'Deutschland',
                            'AT' => 'Österreich',
                            'CH' => 'Schweiz',
                        );
                        foreach ($countries as $code => $name) {
                            $selected = ($shipping_country === $code) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <button type="submit" class="yprint-button">Speichern</button>
            </div>
        </form>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // HERE API Initialization
        const API_KEY = 'xPlTGXIrjg1O6Oea3e2gvo5lrN-iO1gT47Sc-VojWdU';
        
        // Company field toggle
        $('#is_company_shipping').change(function() {
            if (this.checked) {
                $('#company_shipping_fields').slideDown(300);
            } else {
                $('#company_shipping_fields').slideUp(300);
            }
        });
        
        // Use company name from billing data
        $('#use-billing-company').click(function(e) {
            e.preventDefault();
            $('#shipping_company').val('<?php echo esc_js($billing_company); ?>');
        });

        // Hide success message after 3 seconds
        setTimeout(function() {
            $('.yprint-message').fadeOut();
        }, 3000);
        
        // Address search for shipping address
        setupAddressSearch('shipping');
        
        // Address search function
        function setupAddressSearch(prefix) {
            let searchTimeout;
            $(`#address_search_${prefix}`).on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                
                $(`#${prefix}_address_loader`).hide();
                
                if (query.length < 3) {
                    $(`#${prefix}_address_suggestions`).hide();
                    return;
                }

                $(`#${prefix}_address_loader`).show();

                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: 'https://geocode.search.hereapi.com/v1/geocode',
                        data: {
                            q: query,
                            apiKey: API_KEY,
                            limit: 5,
                            lang: 'de',
                            in: 'countryCode:DEU,AUT'
                        },
                        type: 'GET',
                        success: function(data) {
                            $(`#${prefix}_address_loader`).hide();
                            const $suggestions = $(`#${prefix}_address_suggestions`);
                            $suggestions.empty();

                            if (data && data.items && data.items.length > 0) {
                                data.items.forEach(function(item) {
                                    const address = item.address;
                                    
                                    // Main address line
                                    const mainLine = [
                                        address.street,
                                        address.houseNumber,
                                        address.postalCode,
                                        address.city
                                    ].filter(Boolean).join(' ');

                                    // Additional information
                                    const secondaryLine = [
                                        address.district,
                                        address.state,
                                        address.countryName
                                    ].filter(Boolean).join(', ');

                                    const $suggestion = $('<div>').addClass('yprint-address-suggestion')
                                        .append($('<div>').addClass('yprint-suggestion-main').text(mainLine))
                                        .append($('<div>').addClass('yprint-suggestion-secondary').text(secondaryLine))
                                        .data('address', address);

                                    $suggestion.on('click', function() {
                                        const address = $(this).data('address');
                                        
                                        // Separate street and house number
                                        const street = address.street || '';
                                        const houseNumber = address.houseNumber || '';
                                        
                                        // Fill fields
                                        $(`#${prefix}_address_1`).val(street);
                                        $(`#${prefix}_address_2`).val(houseNumber);
                                        $(`#${prefix}_postcode`).val(address.postalCode || '');
                                        $(`#${prefix}_city`).val(address.city || '');
                                        
                                        // Set country
                                        if (address.countryCode) {
                                            const countryCode = address.countryCode.toUpperCase();
                                            $(`#${prefix}_country`).val(countryCode);
                                        }

                                        $suggestions.hide();
                                        $(`#address_search_${prefix}`).val('');
                                    });

                                    $suggestions.append($suggestion);
                                });

                                $suggestions.show();
                            }
                        },
                        error: function(xhr, status, error) {
                            $(`#${prefix}_address_loader`).hide();
                            console.error('Error in address search:', error);
                        }
                    });
                }, 500);
            });
            
            // Click outside closes suggestions
            $(document).on('click', function(e) {
                if (!$(e.target).closest(`#address_search_${prefix}, #${prefix}_address_suggestions`).length) {
                    $(`#${prefix}_address_suggestions`).hide();
                }
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
}
add_shortcode('yprint_shipping_settings', 'yprint_shipping_settings_shortcode');

/**
 * Optimizations for the checkout process
 * - Integration of user settings into checkout
 * - Automatic selection for Germany
 */

/**
 * Set default country for new users
 */
function yprint_set_default_country($user_id) {
    // Set default country to DE if not already set
    if (!get_user_meta($user_id, 'billing_country', true)) {
        update_user_meta($user_id, 'billing_country', 'DE');
    }
    
    if (!get_user_meta($user_id, 'shipping_country', true)) {
        update_user_meta($user_id, 'shipping_country', 'DE');
    }
}
add_action('user_register', 'yprint_set_default_country');

/**
 * Integrate settings with WooCommerce Checkout - Transfer different fields
 */
function yprint_checkout_load_user_data($checkout_fields) {
    if (!is_user_logged_in()) {
        // For non-logged-in users, set default country
        $checkout_fields['billing']['billing_country']['default'] = 'DE';
        $checkout_fields['shipping']['shipping_country']['default'] = 'DE';
        return $checkout_fields;
    }
    
    $user_id = get_current_user_id();
    
    // Get company settings
    $is_company = get_user_meta($user_id, 'is_company', true);
    $is_company_shipping = get_user_meta($user_id, 'is_company_shipping', true);
    
    // Add our custom fields to WooCommerce checkout
    if ($is_company) {
        $checkout_fields['billing']['billing_company']['required'] = true;
        
        // Add VAT ID field if not exists
        if (!isset($checkout_fields['billing']['billing_vat'])) {
            $checkout_fields['billing']['billing_vat'] = array(
                'label'     => 'USt.-ID',
                'required'  => false,
                'class'     => array('form-row-wide'),
                'clear'     => true
            );
        }
    }
    
    // Adjust company field for shipping address
    if ($is_company_shipping) {
        $checkout_fields['shipping']['shipping_company']['required'] = true;
    }
    
    return $checkout_fields;
}
add_filter('woocommerce_checkout_fields', 'yprint_checkout_load_user_data');

/**
 * Copy billing company to shipping if checked
 */
function yprint_copy_billing_company_to_shipping() {
    if (!is_user_logged_in()) return;
    
    $user_id = get_current_user_id();
    
    $is_company = get_user_meta($user_id, 'is_company', true);
    $is_company_shipping = get_user_meta($user_id, 'is_company_shipping', true);
    
    if ($is_company && $is_company_shipping) {
        $billing_company = get_user_meta($user_id, 'billing_company', true);
        $shipping_company = get_user_meta($user_id, 'shipping_company', true);
        
        // If shipping company is empty but billing company exists, copy it
        if (empty($shipping_company) && !empty($billing_company)) {
            update_user_meta($user_id, 'shipping_company', $billing_company);
        }
    }
}
add_action('woocommerce_before_checkout_form', 'yprint_copy_billing_company_to_shipping');

/**
 * Display VAT ID in checkout and save it
 */
function yprint_checkout_vat_field($checkout) {
    $user_id = get_current_user_id();
    $is_company = get_user_meta($user_id, 'is_company', true);
    $billing_vat = get_user_meta($user_id, 'billing_vat', true);
    
    // Only show if company status is set
    if ($is_company) {
        echo '<div id="vat_number_field">';
        
        woocommerce_form_field('billing_vat', array(
            'type'        => 'text',
            'class'       => array('form-row-wide'),
            'label'       => 'USt.-ID',
            'placeholder' => 'DE123456789',
            'required'    => false,
            'default'     => $billing_vat,
        ), $checkout->get_value('billing_vat'));
        
        echo '</div>';
    }
}
add_action('woocommerce_after_checkout_billing_form', 'yprint_checkout_vat_field');

/**
 * Save VAT ID with order
 */
function yprint_save_vat_with_order($order_id) {
    if (isset($_POST['billing_vat']) && !empty($_POST['billing_vat'])) {
        $vat_number = sanitize_text_field($_POST['billing_vat']);
        update_post_meta($order_id, '_billing_vat', $vat_number);
        
        // Also save in user profile if logged in
        if (is_user_logged_in()) {
            update_user_meta(get_current_user_id(), 'billing_vat', $vat_number);
        }
    }
}
add_action('woocommerce_checkout_update_order_meta', 'yprint_save_vat_with_order');

/**
 * Update checkout with user data from other settings
 */
function yprint_checkout_use_saved_user_data() {
    if (!is_user_logged_in()) return;
    
    $user_id = get_current_user_id();
    
    // Check if alternative billing email is set
    $alt_billing_email = get_user_meta($user_id, 'alt_billing_email', true);
    if (!empty($alt_billing_email)) {
        // Use in checkout
        add_filter('woocommerce_checkout_get_value', function($value, $input) use ($alt_billing_email) {
            if ($input === 'billing_email') {
                return $alt_billing_email;
            }
            return $value;
        }, 10, 2);
        
        // Set automatically for orders
        add_filter('woocommerce_email_recipient_customer_invoice', function($recipient, $order) use ($alt_billing_email) {
            if ($order) {
                return $alt_billing_email;
            }
            return $recipient;
        }, 10, 2);
    }
    
    // Additional adjustments as needed
}
add_action('woocommerce_before_checkout_form', 'yprint_checkout_use_saved_user_data');

/**
 * Force Logout AJAX Handler for email change
 */
function yprint_force_logout() {
    check_ajax_referer('force_logout_nonce', 'security');
    
    wp_logout();
    wp_clear_auth_cookie();
    wp_send_json_success();
    wp_die();
}
add_action('wp_ajax_custom_force_logout', 'yprint_force_logout');

/**
 * Function for billing email rejection processing
 */
function yprint_handle_billing_email_rejection() {
    if (isset($_GET['action']) && $_GET['action'] === 'reject_billing_email' && 
        isset($_GET['token']) && isset($_GET['user_id'])) {
        
        $user_id = intval($_GET['user_id']);
        $token = sanitize_text_field($_GET['token']);
        $stored_token = get_user_meta($user_id, 'billing_email_verification_token', true);
        
        if ($token === $stored_token) {
            // Delete token
            delete_user_meta($user_id, 'billing_email_verification_token');
            
            // User affected
            $user = get_userdata($user_id);
            $rejected_email = get_user_meta($user_id, 'alt_billing_email', true);
            
            // Restore original user email
            update_user_meta($user_id, 'billing_email', $user->user_email);
            delete_user_meta($user_id, 'alt_billing_email');
            
            // Send notification to administrators
            $admin_message_content = sprintf(
                'Eine Rechnungs-E-Mail-Änderung wurde abgelehnt:<br><br>
                Benutzer: %s (ID: %d)<br>
                Abgelehnte E-Mail: %s<br>
                Ursprüngliche E-Mail: %s<br><br>
                Bitte prüfen Sie den Fall.',
                $user->display_name,
                $user_id,
                $rejected_email,
                $user->user_email
            );
            
            // Use email template function if available
            if (function_exists('yprint_get_email_template')) {
                $admin_message = yprint_get_email_template('Rechnungs-E-Mail-Änderung abgelehnt', 'Admin', $admin_message_content);
            } else {
                $admin_message = $admin_message_content;
            }
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail('max@yprint.de', 'Rechnungs-E-Mail-Änderung abgelehnt', $admin_message, $headers);
            
            // Redirect user to confirmation page
            wp_safe_redirect(home_url('/email-ablehnung-bestaetigt/'));
            exit;
        }
    }
}
add_action('init', 'yprint_handle_billing_email_rejection');

/**
 * Integrate alternative billing emails with WooCommerce
 */
function yprint_update_order_billing_email($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    $user_id = $order->get_user_id();
    if (!$user_id) return;
    
    $alt_billing_email = get_user_meta($user_id, 'alt_billing_email', true);
    if (!empty($alt_billing_email)) {
        $order->set_billing_email($alt_billing_email);
        $order->save();
    }
}
add_action('woocommerce_checkout_update_order_meta', 'yprint_update_order_billing_email');

/**
 * Changes for different billing address in checkout
 */
function yprint_copy_billing_data_to_different_billing() {
    if (!is_user_logged_in()) return;
    
    $user_id = get_current_user_id();
    
    // If company status is set in profile, also enable in checkout
    $is_company = get_user_meta($user_id, 'is_company', true);
    if ($is_company) {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Enable company field in checkout if set in settings
            $('#different_billing').prop('checked', true).trigger('change');
            
            // Show company information in checkout
            if ($('#different_billing_company').length) {
                $('#different_billing_company').val('<?php echo esc_js(get_user_meta($user_id, 'billing_company', true)); ?>');
            }
            
            if ($('#billing_vat').length) {
                $('#billing_vat').val('<?php echo esc_js(get_user_meta($user_id, 'billing_vat', true)); ?>');
            }
        });
        </script>
        <?php
    }
}
add_action('woocommerce_before_checkout_form', 'yprint_copy_billing_data_to_different_billing');

/**
 * Integrate address search into checkout
 */
function yprint_add_address_search_to_checkout() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        const API_KEY = 'xPlTGXIrjg1O6Oea3e2gvo5lrN-iO1gT47Sc-VojWdU';
        
        // Adjust checkout form style
        $('select.select2-hidden-accessible').css({
            'background-color': '#FFF',
            'border': '1px solid #D1D1D6',
            'border-radius': '6px'
        });
        
        // Add address search to checkout
        addAddressSearch('billing');
        addAddressSearch('shipping');
        
        function addAddressSearch(addressType) {
            // Create input field
            const searchField = `
                <div class="form-row form-row-wide address-search">
                    <label>Adresse suchen</label>
                    <input type="text" id="address_search_${addressType}" placeholder="Adresse eingeben..." class="input-text" style="width: 100%; margin-bottom: 10px;">
                    <div id="${addressType}_suggestions" style="display: none; position: absolute; z-index: 1000; background: white; width: 100%; border: 1px solid #ddd; max-height: 200px; overflow-y: auto;"></div>
                </div>
            `;
            
            // Insert before first address field
            $(`#${addressType}_address_1_field`).before(searchField);
            
            // Event handler for address search
            $(`#address_search_${addressType}`).on('input', function() {
                const query = $(this).val();
                if (query.length < 3) {
                    $(`#${addressType}_suggestions`).hide();
                    return;
                }
                
                // HERE API query
                $.ajax({
                    url: 'https://geocode.search.hereapi.com/v1/geocode',
                    data: {
                        q: query,
                        apiKey: API_KEY,
                        limit: 5,
                        lang: 'de',
                        in: 'countryCode:DEU,AUT'
                    },
                    type: 'GET',
                    success: function(data) {
                        const $suggestions = $(`#${addressType}_suggestions`);
                        $suggestions.empty();
                        
                        if (data && data.items && data.items.length > 0) {
                            data.items.forEach(function(item) {
                                const address = item.address;
                                const mainLine = [
                                    address.street,
                                    address.houseNumber,
                                    address.postalCode,
                                    address.city
                                ].filter(Boolean).join(' ');
                                
                                const secondaryLine = [
                                    address.district,
                                    address.state,
                                    address.countryName
                                ].filter(Boolean).join(', ');
                                
                                const $suggestion = $('<div>')
                                    .css({
                                        'padding': '10px',
                                        'cursor': 'pointer',
                                        'border-bottom': '1px solid #f0f0f0'
                                    })
                                    .hover(
                                        function() { $(this).css('background-color', '#f5f5f5'); },
                                        function() { $(this).css('background-color', ''); }
                                    )
                                    .append($('<div>').css('font-weight', 'bold').text(mainLine))
                                    .append($('<div>').css('font-size', '0.9em', 'color', '#666').text(secondaryLine))
                                    .data('address', address);
                                
                                $suggestion.on('click', function() {
                                    const address = $(this).data('address');
                                    
                                    // Separate street and house number
                                    const street = address.street || '';
                                    const houseNumber = address.houseNumber || '';
                                    
                                    // Fill fields
                                    $(`#${addressType}_address_1`).val(street);
                                    $(`#${addressType}_address_2`).val(houseNumber);
                                    $(`#${addressType}_postcode`).val(address.postalCode || '');
                                    $(`#${addressType}_city`).val(address.city || '');
                                    
                                    // Set country
                                    if (address.countryCode) {
                                        const countryCode = address.countryCode.toUpperCase();
                                        $(`#${addressType}_country`).val(countryCode).trigger('change');
                                    }
                                    
                                    $suggestions.hide();
                                    $(`#address_search_${addressType}`).val('');
                                });
                                
                                $suggestions.append($suggestion);
                            });
                            
                            $suggestions.show();
                        }
                    }
                });
            });
            
            // Click outside closes suggestions
            $(document).on('click', function(e) {
                if (!$(e.target).closest(`#address_search_${addressType}, #${addressType}_suggestions`).length) {
                    $(`#${addressType}_suggestions`).hide();
                }
            });
        }
    });
    </script>
    <style>
    /* Checkout styles for address search */
    .form-row.address-search {
        position: relative;
        margin-bottom: 15px !important;
    }
    
    #billing_suggestions div, 
    #shipping_suggestions div {
        margin-bottom: 0 !important;
    }
    
    /* Adjust select fields */
    .select2-container--default .select2-selection--single {
        background-color: #FFF !important;
        border: 1px solid #D1D1D6 !important;
        border-radius: 6px !important;
        height: auto !important;
        padding: 8px !important;
    }
    
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 40px !important;
    }
    </style>
    <?php
}
add_action('woocommerce_before_checkout_form', 'yprint_add_address_search_to_checkout');

/**
 * Initialize the settings system
 */
function yprint_init_settings_system() {
    // Register all needed shortcodes
    add_shortcode('yprint_user_settings', 'yprint_user_settings_shortcode');
    add_shortcode('yprint_personal_settings', 'yprint_personal_settings_shortcode');
    add_shortcode('yprint_billing_settings', 'yprint_billing_settings_shortcode');
    add_shortcode('yprint_shipping_settings', 'yprint_shipping_settings_shortcode');
    
    // Overlay styles for notifications
    add_action('wp_head', 'yprint_add_overlay_styles');
}
add_action('init', 'yprint_init_settings_system');

/**
 * Output overlay styles in header
 */
function yprint_add_overlay_styles() {
    ?>
    <style>
    .yprint-overlay {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.8);
        z-index: 99999;
        justify-content: center;
        align-items: center;
    }
    
    .yprint-overlay-content {
        background-color: white;
        padding: 30px;
        border-radius: 10px;
        text-align: center;
        max-width: 400px;
        width: 90%;
    }
    </style>
    <?php
}

/**
 * User-friendly error messages in checkout
 */
function yprint_customize_validation_messages($fields) {
    // More user-friendly error messages
    $fields['billing']['billing_first_name']['required_message'] = 'Bitte gib deinen Vornamen ein';
    $fields['billing']['billing_last_name']['required_message'] = 'Bitte gib deinen Nachnamen ein';
    $fields['billing']['billing_address_1']['required_message'] = 'Bitte gib deine Straße ein';
    $fields['billing']['billing_postcode']['required_message'] = 'Bitte gib deine PLZ ein';
    $fields['billing']['billing_city']['required_message'] = 'Bitte gib deine Stadt ein';
    
    $fields['shipping']['shipping_first_name']['required_message'] = 'Bitte gib deinen Vornamen ein';
    $fields['shipping']['shipping_last_name']['required_message'] = 'Bitte gib deinen Nachnamen ein';
    $fields['shipping']['shipping_address_1']['required_message'] = 'Bitte gib deine Straße ein';
    $fields['shipping']['shipping_postcode']['required_message'] = 'Bitte gib deine PLZ ein';
    $fields['shipping']['shipping_city']['required_message'] = 'Bitte gib deine Stadt ein';
    
    return $fields;
}
add_filter('woocommerce_checkout_fields', 'yprint_customize_validation_messages', 20);

/**
 * Synchronize user settings with checkout
 * 
 * This function ensures that data saved in the user profile
 * is transferred to the checkout and vice versa.
 */
function yprint_sync_user_settings_with_checkout($order_id) {
    if (!is_user_logged_in()) return;
    
    $user_id = get_current_user_id();
    $order = wc_get_order($order_id);
    
    // Check company status and save
    $is_company_in_order = !empty($order->get_billing_company());
    update_user_meta($user_id, 'is_company', $is_company_in_order);
    
    if ($is_company_in_order) {
        // Save VAT ID
        $vat = get_post_meta($order_id, '_billing_vat', true);
        if (!empty($vat)) {
            update_user_meta($user_id, 'billing_vat', $vat);
        }
    }
    
    // Same for shipping company
    $is_company_shipping_in_order = !empty($order->get_shipping_company());
    update_user_meta($user_id, 'is_company_shipping', $is_company_shipping_in_order);
    
    // Synchronize alternative billing email
    if (isset($_POST['different_billing_email']) && isset($_POST['alt_billing_email']) && 
        !empty($_POST['alt_billing_email'])) {
        
        $alt_email = sanitize_email($_POST['alt_billing_email']);
        update_user_meta($user_id, 'alt_billing_email', $alt_email);
        update_user_meta($user_id, 'billing_email', $alt_email);
    }
}
add_action('woocommerce_checkout_update_order_meta', 'yprint_sync_user_settings_with_checkout');

/**
 * Handle conditions for different billing addresses in checkout
 */
function yprint_handle_different_billing_fields() {
    if (!is_user_logged_in()) return;
    
    $user_id = get_current_user_id();
    
    // Check if various elements are set
    $is_company = get_user_meta($user_id, 'is_company', true);
    $billing_company = get_user_meta($user_id, 'billing_company', true);
    $billing_vat = get_user_meta($user_id, 'billing_vat', true);
    
    ?>
    <script>
    jQuery(document).ready(function($) {
        const isCompany = <?php echo $is_company ? 'true' : 'false'; ?>;
        
        if (isCompany) {
            // If user is a company, activate different billing address checkbox
            if ($('#different_billing').length) {
                $('#different_billing').prop('checked', true).trigger('change');
            }
            
            // Pre-fill company data
            <?php if (!empty($billing_company)) : ?>
            if ($('#billing_company').length) {
                $('#billing_company').val('<?php echo esc_js($billing_company); ?>');
            }
            <?php endif; ?>
            
            <?php if (!empty($billing_vat)) : ?>
            if ($('#billing_vat').length) {
                $('#billing_vat').val('<?php echo esc_js($billing_vat); ?>');
            }
            <?php endif; ?>
        }
    });
    </script>
    <?php
}
add_action('woocommerce_before_checkout_form', 'yprint_handle_different_billing_fields');

/**
 * Create required database table during plugin activation
 * Called from main plugin file
 */
function yprint_create_settings_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Personal data table
    $personal_data_table = $wpdb->prefix . 'personal_data';
    $sql = "CREATE TABLE IF NOT EXISTS $personal_data_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        first_name varchar(255),
        last_name varchar(255),
        birthdate date,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}