<?php
/**
 * REST API Registration functions for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register REST API endpoint for user registration
 */
add_action('rest_api_init', 'wp_rest_user_endpoints');

function wp_rest_user_endpoints() {
    register_rest_route('wp/v2', 'users/register', array(
        'methods' => 'POST',
        'callback' => 'wc_rest_user_endpoint_handler',
    ));
}

/**
 * Handle REST API registration requests
 *
 * @param WP_REST_Request $request The request object
 * @return WP_REST_Response|WP_Error The response or error object
 */
function wc_rest_user_endpoint_handler($request) {
    global $wpdb;
    $response = array();
    $parameters = $request->get_json_params();
    
    // Turnstile Bot-Schutz Validierung für REST API
    $turnstile = YPrint_Turnstile::get_instance();
    if ($turnstile->is_enabled() && in_array('registration', $turnstile->get_protected_pages())) {
        $token = $parameters['cf-turnstile-response'] ?? '';
        $verification = $turnstile->verify_token($token);
        if (!$verification['success']) {
            return new WP_Error(403, 'Bot-Verifikation fehlgeschlagen. Bitte versuchen Sie es erneut.', array('status' => 403));
        }
    }

    // Eingabedaten validieren
    $username = sanitize_text_field($parameters['username']);
    $email = sanitize_text_field($parameters['email']);
    $password = sanitize_text_field($parameters['password']);

    $error = new WP_Error();

    if (empty($username)) {
        $error->add(400, __("Username field 'username' is required.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }
    if (empty($email)) {
        $error->add(401, __("Email field 'email' is required.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }
    if (empty($password)) {
        $error->add(404, __("Password field 'password' is required.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }

    // Prüfen, ob Benutzername oder E-Mail bereits existiert
    if (username_exists($username) || email_exists($email)) {
        $error->add(406, __("Username or email already registered.", 'wp-rest-user'), array('status' => 400));
        return $error;
    }

    // Registrierungs-E-Mail von WordPress verhindern
    add_filter('wp_new_user_notification_email', '__return_false');

    // Benutzer erstellen
    $user_id = wp_create_user($username, $password, $email);

    if (is_wp_error($user_id)) {
        return $user_id;
    }

    // Cookie-Preferences aus Hidden Fields verarbeiten
    $cookie_preferences = isset($parameters['cookie_preferences']) ? $parameters['cookie_preferences'] : array();

    // Standard-Werte falls nicht gesetzt
    $cookie_preferences = array_merge(array(
        'essential' => 'true',
        'analytics' => 'false',
        'marketing' => 'false',
        'functional' => 'false'
    ), $cookie_preferences);

    // Basis-Datenschutz-Consent automatisch (durch Nutzung impliziert)
    $wpdb->insert(
        $wpdb->prefix . 'yprint_consents',
        array(
            'user_id' => $user_id,
            'consent_type' => 'PRIVACY_POLICY',
            'granted' => 1,
            'version' => '1.0',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        )
    );

    // Cookie-Consents in DB speichern
    $cookie_mapping = array(
        'essential' => 'COOKIE_ESSENTIAL',
        'analytics' => 'COOKIE_ANALYTICS', 
        'marketing' => 'COOKIE_MARKETING',
        'functional' => 'COOKIE_FUNCTIONAL'
    );

    foreach ($cookie_mapping as $form_key => $db_key) {
        $granted = ($cookie_preferences[$form_key] === 'true');
        
        $wpdb->insert(
            $wpdb->prefix . 'yprint_consents',
            array(
                'user_id' => $user_id,
                'consent_type' => $db_key,
                'granted' => $granted ? 1 : 0,
                'version' => '1.0',
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '',
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )
        );
    }

    // Guest-Cookie löschen nach Übertragung
    if (isset($_COOKIE['yprint_consent_preferences'])) {
        setcookie('yprint_consent_preferences', '', time() - 3600, '/');
        setcookie('yprint_consent_decision', '', time() - 3600, '/');
    }

    // Benutzerrolle setzen
    $user = get_user_by('id', $user_id);
    $user->set_role('subscriber');
    if (class_exists('WooCommerce')) {
        $user->set_role('customer');
    }

    // Verifizierungscode generieren
    $verification_code = bin2hex(random_bytes(16));

    // Tabellenname
    $table_name = 'wp_email_verifications';

    // Prüfen, ob der Benutzer bereits in der Verifizierungstabelle existiert
    $existing_id = $wpdb->get_var(
        $wpdb->prepare("SELECT id FROM $table_name WHERE user_id = %d", $user_id)
    );

    if ($existing_id) {
        // Falls Eintrag existiert: UPDATE
        $wpdb->update(
            $table_name,
            array(
                'verification_code' => $verification_code,
                'email_verified' => 0,
                'updated_at' => current_time('mysql'),
            ),
            array('user_id' => $user_id),
            array('%s', '%d', '%s'),
            array('%d')
        );
    } else {
        // Falls kein Eintrag existiert: INSERT
        $wpdb->insert(
            $table_name,
            array(
                'user_id' => $user_id,
                'verification_code' => $verification_code,
                'email_verified' => 0,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ),
            array('%d', '%s', '%d', '%s', '%s')
        );
    }

    // DEBUG: Fehler ausgeben
    if ($wpdb->last_error) {
        error_log("Datenbankfehler: " . $wpdb->last_error);
    }

    // Verifizierungslink erstellen
    $verification_link = add_query_arg(array(
        'user_id' => $user_id,
        'verification_code' => $verification_code,
    ), home_url('/verify-email/'));

    // Hier wird die E-Mail für die Verifizierung gesendet
    $email_sent = send_verification_email($email, $username, $verification_link);
    
    // Prüfen, ob die E-Mail gesendet wurde
    if (!$email_sent) {
        error_log("Email sending failed during registration for user: {$username} ({$email})");
        // Trotzdem fortfahren, da der Benutzer erstellt wurde
    }

    // HubSpot Kontakt erstellen
    if (class_exists('YPrint_HubSpot_API')) {
        $hubspot_api = YPrint_HubSpot_API::get_instance();
        if ($hubspot_api->is_enabled()) {
            $hubspot_contact_data = array(
                'email' => $email,
                'username' => $username,
                'firstname' => $username, // Fallback, da wir keinen separaten Vor-/Nachnamen haben
                'registration_date' => current_time('Y-m-d H:i:s'),
                'cookie_preferences' => $cookie_preferences
            );
            
            $hubspot_result = $hubspot_api->create_contact($hubspot_contact_data);
            
            if ($hubspot_result['success']) {
                error_log('YPrint Registration: HubSpot contact created for user ' . $username . ' with ID: ' . $hubspot_result['contact_id']);
                // Optional: Contact ID in WordPress User Meta speichern
                update_user_meta($user_id, 'hubspot_contact_id', $hubspot_result['contact_id']);
                
                // ✅ NEU: Cookie-Aktivität bei Registrierung aus Session erstellen
                $consent_manager = YPrint_Consent_Manager::get_instance();
                $cookie_preferences_from_session = $consent_manager->get_cookie_preferences_from_session();

                if (!empty($cookie_preferences_from_session)) {
                    error_log('YPrint REST Registration: Cookie-Präferenzen für HubSpot gefunden: ' . json_encode($cookie_preferences_from_session));
                    $cookie_activity_result = $hubspot_api->handle_cookie_activity($email, $cookie_preferences_from_session, 'initial');
                    
                    if ($cookie_activity_result['success']) {
                        error_log('YPrint Registration: Initial cookie activity created for user ' . $username . ' during REST registration');
                    } else {
                        error_log('YPrint Registration: Failed to create initial cookie activity for user ' . $username . ' during REST registration: ' . $cookie_activity_result['message']);
                    }
                } else {
                    error_log('YPrint REST Registration: Keine Cookie-Präferenzen für HubSpot gefunden');
                }
            } else {
                error_log('YPrint Registration: Failed to create HubSpot contact for user ' . $username . ': ' . $hubspot_result['message']);
                // Registration trotzdem erfolgreich, auch wenn HubSpot fehlschlägt
            }
        }
    }

    // Antwort zurückgeben
    $response['code'] = 200;
    $response['message'] = __("User '" . $username . "' Registration was Successful", "wp-rest-user");
    $response['email_sent'] = $email_sent; // Status der E-Mail-Versendung mitgeben
    
    return new WP_REST_Response($response, 200);
}

/**
 * Send verification email to the user
 *
 * @param string $email User's email address
 * @param string $username User's username
 * @param string $verification_link Verification link
 * @return bool True if email was sent, false otherwise
 */
function send_verification_email($email, $username, $verification_link) {
    // Debug-Information in das Log schreiben
    error_log("Sending verification email to: {$email} with link: {$verification_link}");
    
    // Nutze unsere eigene E-Mail-Template-Funktion
    $headline = 'Verifiziere deine E-Mail-Adresse';
    $first_name = $username;
    $message_content = "Bitte klicke auf den folgenden Link, um deine E-Mail-Adresse zu verifizieren:<br><br>";
    $message_content .= "<a href='" . esc_url($verification_link) . "' style='display: inline-block; background-color: #007aff; padding: 15px 30px; color: #ffffff; text-decoration: none; font-size: 16px; border-radius: 5px;'>E-Mail verifizieren</a><br><br>";
    $message_content .= "Wenn du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.";
    
    // Nutze die yprint_get_email_template-Funktion
    $message = yprint_get_email_template($headline, $first_name, $message_content);

    // Betreff und Header für die E-Mail
    $subject = 'Bitte verifiziere deine E-Mail-Adresse';
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: YPrint <do-not-reply@yprint.de>',
    );

    // E-Mail senden und Ergebnis prüfen
    $mail_sent = wp_mail($email, $subject, $message, $headers);
    
    // Wenn die E-Mail nicht gesendet werden konnte, dies in das Fehlerlog schreiben
    if (!$mail_sent) {
        error_log("Failed to send verification email to: {$email}");
        
        // Überprüfen der wp_mail Fehler
        global $phpmailer;
        if (isset($GLOBALS['phpmailer'])) {
            $phpmailer = $GLOBALS['phpmailer'];
            if ($phpmailer->ErrorInfo != '') {
                error_log('PHPMailer error: ' . $phpmailer->ErrorInfo);
            }
        }
    } else {
        error_log("Verification email sent successfully to: {$email}");
    }

    // Nur den Sende-Status zurückgeben
    return $mail_sent;
}

/**
 * Verify user email
 *
 * @param int $user_id User ID
 * @param string $verification_code Verification code
 * @return bool True if verified, false otherwise
 */
function verify_user_email($user_id, $verification_code) {
    global $wpdb;
    // Sicherstellen, dass der Code und die Benutzer-ID übereinstimmen
    $updated = $wpdb->update(
        'wp_email_verifications',
        ['email_verified' => 1], // Markiere als verifiziert
        ['user_id' => $user_id, 'verification_code' => $verification_code],
        ['%d'], // Format der Werte
        ['%d', '%s'] // Format der Bedingungen
    );

    return ($updated !== false); // Wenn das Update erfolgreich war
}

/**
 * Handler für das Resenden der Verifikations-E-Mail von der Verify-Seite
 */
function yprint_handle_resend_verification_from_verify_page() {
    // Nur auf Verify-E-Mail-Seite ausführen
    if (!is_page('verify-email') && strpos($_SERVER['REQUEST_URI'], '/verify-email') === false) {
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_verification_email'])) {
        check_admin_referer('resend_verification_from_verify_nonce', 'security');
        
        global $wpdb;
        
        $table_name = 'wp_email_verifications';
        $email = sanitize_email($_POST['resend_verification_email']);
        
        if (empty($email) || !is_email($email)) {
            wp_redirect(add_query_arg('resend_error', 'invalid_email', $_SERVER['REQUEST_URI']));
            exit;
        }
        
        // Benutzer anhand der E-Mail-Adresse finden
        $user = get_user_by('email', $email);
        
        if (!$user) {
            wp_redirect(add_query_arg('resend_error', 'user_not_found', $_SERVER['REQUEST_URI']));
            exit;
        }
        
        $user_id = $user->ID;
        $username = $user->user_login;
        $verification_code = bin2hex(random_bytes(16));
        $current_time = current_time('mysql');
        
        // Prüfen ob E-Mail bereits verifiziert ist
        $existing_verification = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d", 
            $user_id
        ));
        
        if ($existing_verification && $existing_verification->email_verified == 1) {
            wp_redirect(add_query_arg('resend_error', 'already_verified', $_SERVER['REQUEST_URI']));
            exit;
        }
        
        // Prüfen, ob bereits ein Eintrag existiert
        $existing_entry = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id)
        );
        
        if ($existing_entry) {
            // Update mit NEUEM created_at Timestamp für frische 24h
            $wpdb->update(
                $table_name,
                array(
                    'verification_code' => $verification_code,
                    'email_verified' => 0,
                    'created_at' => $current_time,  // WICHTIG: Neuer Timestamp für 24h Gültigkeit
                    'updated_at' => $current_time
                ),
                array('user_id' => $user_id),
                array('%s', '%d', '%s', '%s'),
                array('%d')
            );
        } else {
            // Neuen Eintrag erstellen
            $wpdb->insert(
                $table_name,
                array(
                    'user_id' => $user_id,
                    'verification_code' => $verification_code,
                    'email_verified' => 0,
                    'created_at' => $current_time,
                    'updated_at' => $current_time
                ),
                array('%d', '%s', '%d', '%s', '%s')
            );
        }

        // Verification Link erstellen
        $verification_link = add_query_arg(
            array(
                'user_id' => $user_id,
                'verification_code' => $verification_code,
            ),
            home_url('/verify-email/')
        );

        // E-Mail senden mit der vorhandenen Funktion
        if (function_exists('yprint_get_email_template')) {
            $subject = 'Bitte verifiziere deine E-Mail-Adresse';
            $message_content = "Bitte klicke auf den folgenden Link, um deine E-Mail-Adresse zu verifizieren:<br><br>";
            $message_content .= "<a href='" . esc_url($verification_link) . "' style='display: inline-block; background-color: #007aff; padding: 15px 30px; color: #ffffff; text-decoration: none; font-size: 16px; border-radius: 5px;'>Verifizieren</a><br><br>";
            $message_content .= "Wenn du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.";
            
            $message = yprint_get_email_template('Bitte verifiziere deine E-Mail-Adresse', esc_html($username), $message_content);
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: YPrint <do-not-reply@yprint.de>'
            );
            
            wp_mail($email, $subject, $message, $headers);
        }
        
        wp_redirect(add_query_arg('resend_success', '1', $_SERVER['REQUEST_URI']));
        exit;
    }
}
add_action('template_redirect', 'yprint_handle_resend_verification_from_verify_page', 2);

function verify_email_shortcode() {
    ob_start();
    
    // Modern styling for verification page
    echo '<style>
        .verify-container {
            max-width: 500px;
            margin: 40px auto;
            padding: 30px;
            background-color: #ffffff;
            border-radius: 10px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            text-align: center;
            font-family: "Roboto", sans-serif;
        }
        .verify-icon {
            margin-bottom: 20px;
            font-size: 48px;
        }
        .verify-title {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 15px;
            color: #333333;
        }
        .verify-message {
            font-size: 16px;
            color: #666666;
            line-height: 1.5;
            margin-bottom: 25px;
        }
        .verify-button {
            display: inline-block;
            background-color: #0079FF;
            color: white;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
            border: none;
            font-size: 16px;
            cursor: pointer;
        }
        .verify-button:hover {
            background-color: #0056b3;
        }
        .verify-container.success .verify-icon {
            color: #28a745;
        }
        .verify-container.error .verify-icon {
            color: #dc3545;
        }
        .verify-container.info .verify-icon {
            color: #17a2b8;
        }
        .verify-resend {
            margin-top: 15px;
            font-size: 14px;
            color: #666666;
        }
        .verify-resend a {
            color: #0079FF;
            text-decoration: none;
        }
        .verify-resend a:hover {
            text-decoration: underline;
        }
        .loading-spinner {
            border: 4px solid rgba(0, 0, 0, 0.1);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border-left-color: #0079FF;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Neue Styles für E-Mail-Eingabefeld */
        .verify-email-form {
            margin-top: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
        }
        .verify-email-input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 6px;
            font-size: 16px;
            margin-bottom: 15px;
            box-sizing: border-box;
            transition: border-color 0.3s;
        }
        .verify-email-input:focus {
            border-color: #0079FF;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 121, 255, 0.1);
        }
        .verify-submit-button {
            display: inline-block;
            background-color: #28a745;
            color: white;
            font-weight: bold;
            padding: 12px 30px;
            border-radius: 5px;
            text-decoration: none;
            transition: background-color 0.3s;
            border: none;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
        }
        .verify-submit-button:hover {
            background-color: #218838;
        }
        .verify-error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #f5c6cb;
            margin-bottom: 15px;
            font-size: 14px;
        }
        .verify-success-message {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 6px;
            border: 1px solid #c3e6cb;
            margin-bottom: 15px;
            font-size: 14px;
        }
    </style>';

    // Zeige Erfolgs- oder Fehlermeldungen für Resend-Anfragen
    if (isset($_GET['resend_success']) && $_GET['resend_success'] == '1') {
        echo '<div class="verify-container success">
            <div class="verify-icon">✉️</div>
            <div class="verify-title">E-Mail versendet!</div>
            <div class="verify-message">Eine neue Verifikations-E-Mail wurde an deine E-Mail-Adresse gesendet. Bitte überprüfe dein Postfach.</div>
            <a href="' . home_url('/login/') . '" class="verify-button">Zum Login</a>
        </div>';
        return ob_get_clean();
    }
    
    if (isset($_GET['resend_error'])) {
        $error_message = '';
        switch ($_GET['resend_error']) {
            case 'invalid_email':
                $error_message = 'Bitte gib eine gültige E-Mail-Adresse ein.';
                break;
            case 'user_not_found':
                $error_message = 'Es wurde kein Benutzer mit dieser E-Mail-Adresse gefunden.';
                break;
            case 'already_verified':
                $error_message = 'Diese E-Mail-Adresse ist bereits verifiziert.';
                break;
            default:
                $error_message = 'Ein unbekannter Fehler ist aufgetreten.';
        }
    }

    if (isset($_GET['verification_code']) && isset($_GET['user_id'])) {
        global $wpdb;
        $verification_code = sanitize_text_field($_GET['verification_code']);
        $user_id = intval($_GET['user_id']);

        // Korrekte Tabelle für Verifizierungsdaten
        $table_name = 'wp_email_verifications';

        // Verifizierungscode in der Datenbank suchen
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND verification_code = %s", 
            $user_id, $verification_code
        ));

        if ($user) {
            // Überprüfen, ob die E-Mail bereits verifiziert wurde
            if ($user->email_verified == 1) {
                echo '<div class="verify-container success">
                    <div class="verify-icon">✓</div>
                    <div class="verify-title">E-Mail-Adresse bereits bestätigt</div>
                    <div class="verify-message">Deine E-Mail-Adresse wurde bereits erfolgreich bestätigt. Du kannst dich jetzt mit deinen Zugangsdaten einloggen.</div>
                    <a href="' . home_url('/login/') . '" class="verify-button">Zum Login</a>
                </div>';
            } else {
                $verification_request_time = strtotime($user->created_at);
                $expiry_time = 24 * 60 * 60; // 24 Stunden
                $current_time = time();
                $remaining_time = $expiry_time - ($current_time - $verification_request_time);

                // Debug-Logging für Troubleshooting
                error_log('YPrint Verify Debug: User ID ' . $user_id);
                error_log('YPrint Verify Debug: Created at ' . $user->created_at);
                error_log('YPrint Verify Debug: Verification request time ' . $verification_request_time);
                error_log('YPrint Verify Debug: Current time ' . $current_time);
                error_log('YPrint Verify Debug: Time difference ' . ($current_time - $verification_request_time) . ' seconds');
                error_log('YPrint Verify Debug: Remaining time ' . $remaining_time . ' seconds');

                // Überprüfen, ob der Verifizierungscode noch gültig ist
                if ($remaining_time > 0) {
                    $verified = verify_user_email($user_id, $verification_code);

                    if ($verified) {
                        echo '<div class="verify-container success">
                            <div class="verify-icon">✓</div>
                            <div class="verify-title">E-Mail-Adresse bestätigt!</div>
                            <div class="verify-message">Deine E-Mail-Adresse wurde erfolgreich bestätigt! Du wirst in Kürze zum Login weitergeleitet...</div>
                            <div class="loading-spinner"></div>
                        </div>';
                        echo '<script>
                            setTimeout(function() {
                                window.location.href = "' . home_url('/login/') . '";
                            }, 3000);
                        </script>';
                    } else {
                        echo '<div class="verify-container error">
                            <div class="verify-icon">✗</div>
                            <div class="verify-title">Verifizierung fehlgeschlagen</div>
                            <div class="verify-message">Es gab ein technisches Problem bei der Verifizierung deines Codes. Bitte versuche es später erneut oder wende dich an unseren Support.</div>
                            <a href="' . home_url('/login/') . '" class="verify-button">Zum Login</a>
                            <div class="verify-resend">
                                <p>Probleme mit der Verifizierung? <a href="mailto:support@yprint.de">Kontaktiere unseren Support</a></p>
                            </div>
                        </div>';
                    }
                } else {
                    // Verifizierungscode ist abgelaufen
                    $user_data = get_userdata($user_id);
                    $username = $user_data ? $user_data->user_login : '';
                    $user_email = $user_data ? $user_data->user_email : '';
                    
                    echo '<div class="verify-container error">
                        <div class="verify-icon">⏱</div>
                        <div class="verify-title">Verifikationslink abgelaufen</div>
                        <div class="verify-message">Der Link, dem du gefolgt bist, ist abgelaufen. Du kannst direkt hier eine neue Verifikations-E-Mail anfordern:</div>';
                        
                    // Zeige Fehlermeldung falls vorhanden
                    if (isset($error_message)) {
                        echo '<div class="verify-error-message">' . esc_html($error_message) . '</div>';
                    }
                    
                    echo '<form method="post" class="verify-email-form">
                            <input type="email" name="resend_verification_email" class="verify-email-input" 
                                   placeholder="Deine E-Mail-Adresse" value="' . esc_attr($user_email) . '" required>
                            <button type="submit" class="verify-submit-button">Neue Verifikations-E-Mail senden</button>';
                    
                    wp_nonce_field('resend_verification_from_verify_nonce', 'security');
                    
                    echo '</form>
                        <div class="verify-resend">
                            <p>Oder gehe <a href="' . home_url('/login/') . '">zum Login</a></p>
                        </div>
                    </div>';
                }
            }
        } else {
            echo '<div class="verify-container error">
                <div class="verify-icon">⚠</div>
                <div class="verify-title">Ungültiger Verifizierungscode</div>
                <div class="verify-message">Der angegebene Verifizierungscode konnte nicht gefunden werden. Du kannst direkt hier eine neue Verifikations-E-Mail anfordern:</div>';
                
            // Zeige Fehlermeldung falls vorhanden
            if (isset($error_message)) {
                echo '<div class="verify-error-message">' . esc_html($error_message) . '</div>';
            }
            
            echo '<form method="post" class="verify-email-form">
                    <input type="email" name="resend_verification_email" class="verify-email-input" 
                           placeholder="Deine E-Mail-Adresse" required>
                    <button type="submit" class="verify-submit-button">Neue Verifikations-E-Mail senden</button>';
            
            wp_nonce_field('resend_verification_from_verify_nonce', 'security');
            
            echo '</form>
                <div class="verify-resend">
                    <p>Oder gehe <a href="' . home_url('/login/') . '">zum Login</a></p>
                </div>
            </div>';
        }
    } else {
        echo '<div class="verify-container info">
            <div class="verify-icon">ℹ</div>
            <div class="verify-title">E-Mail-Verifikation</div>
            <div class="verify-message">Gib deine E-Mail-Adresse ein, um eine neue Verifikations-E-Mail zu erhalten:</div>';
            
        // Zeige Fehlermeldung falls vorhanden
        if (isset($error_message)) {
            echo '<div class="verify-error-message">' . esc_html($error_message) . '</div>';
        }
        
        echo '<form method="post" class="verify-email-form">
                <input type="email" name="resend_verification_email" class="verify-email-input" 
                       placeholder="Deine E-Mail-Adresse" required>
                <button type="submit" class="verify-submit-button">Verifikations-E-Mail senden</button>';
        
        wp_nonce_field('resend_verification_from_verify_nonce', 'security');
        
        echo '</form>
            <div class="verify-resend">
                <p>Oder gehe <a href="' . home_url('/login/') . '">zum Login</a></p>
            </div>
        </div>';
    }

    return ob_get_clean();
}

add_shortcode('verify_email', 'verify_email_shortcode');

/**
 * Mobile registration form shortcode
 */
function yprint_registration_form_mobile() {
    ob_start();
    ?>
    <style>

        
        /* Moderner Mobile Register Container - identisch mit Login Design */
        .yprint-mobile-register-container {
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            padding: 40px 20px;
            box-sizing: border-box;
        }

        .yprint-mobile-register-wrapper {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            position: relative;
        }

        .yprint-mobile-register-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .yprint-mobile-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
        }

        .yprint-mobile-logo img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .yprint-mobile-register-title {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 8px 0;
        }

        .yprint-mobile-register-subtitle {
            font-size: 15px;
            color: #6b7280;
            margin: 0;
        }

        /* Login Button Section */
        .yprint-mobile-login-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .yprint-mobile-login-text {
            font-size: 14px;
            color: #6b7280;
            margin: 0 0 16px 0;
        }

        .yprint-mobile-login-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            height: 48px;
            padding: 12px 24px;
            font-family: inherit;
            font-size: 15px;
            font-weight: 500;
            color: #3b82f6 !important;
            background-color: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            box-sizing: border-box;
        }

        .yprint-mobile-login-button:hover {
            background-color: #f1f5f9;
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            color: #2563eb !important;
            text-decoration: none;
        }

        /* Input Field Container */
        .yprint-mobile-input-group {
            margin-bottom: 24px;
            position: relative;
        }

        /* Eingabefelder - moderne Styles */
        #register-form-mobile input[type="text"],
        #register-form-mobile input[type="email"],
        #register-form-mobile input[type="password"] {
            width: 100% !important;
            height: 52px !important;
            padding: 16px 20px !important;
            font-family: inherit !important;
            font-size: 16px !important;
            font-weight: 400 !important;
            line-height: 1.5 !important;
            color: #111827 !important;
            background-color: #f3f4f6 !important;
            border: 2px solid #e5e7eb !important;
            border-radius: 12px !important;
            outline: none !important;
            transition: all 0.3s ease !important;
            text-align: left !important;
            margin: 0 !important;
            box-sizing: border-box !important;
        }

        #register-form-mobile input[type="text"]:focus,
        #register-form-mobile input[type="email"]:focus,
        #register-form-mobile input[type="password"]:focus {
            background-color: #ffffff !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }

        /* Platzhaltertext */
        #register-form-mobile input::placeholder {
            color: #9ca3af !important;
            font-weight: 400 !important;
        }

        /* Submit-Button */
        #register-form-mobile button[type="submit"] {
            width: 100% !important;
            height: 52px !important;
            padding: 16px 24px !important;
            font-family: inherit !important;
            font-size: 16px !important;
            font-weight: 600 !important;
            color: #ffffff !important;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%) !important;
            border: none !important;
            border-radius: 12px !important;
            cursor: pointer !important;
            transition: all 0.3s ease !important;
            margin-top: 8px !important;
            margin-bottom: 0 !important;
            text-transform: none !important;
            line-height: 1.5 !important;
        }

        #register-form-mobile button[type="submit"]:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
        }

        /* Password Container für Eye-Toggle */
        .yprint-mobile-password-container {
            position: relative;
            width: 100%;
        }

        /* Augen-Icon für Passwort-Toggle */
        .yprint-mobile-eye-toggle {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            cursor: pointer;
            z-index: 10;
            border-radius: 4px;
            transition: background-color 0.2s ease;
        }

        .yprint-mobile-eye-toggle:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .yprint-mobile-eye-toggle i {
            font-size: 18px;
            color: #6b7280;
            transition: color 0.2s ease;
        }

        .yprint-mobile-eye-toggle:hover i {
            color: #3b82f6;
        }

        /* Password Requirements */
        #password-requirements {
            margin-top: 12px;
            padding: 12px;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 8px;
            font-size: 12px;
            color: #92400e;
        }

        #password-requirements ul {
            margin: 0;
            padding-left: 20px;
        }

        #password-requirements li {
            margin: 4px 0;
            transition: all 0.2s ease;
        }

        #password-requirements li.valid {
            color: #065f46 !important;
            text-decoration: line-through;
        }

        /* Datenschutz Checkbox */
        .yprint-mobile-checkbox-group {
            display: flex;
            align-items: flex-start;
            margin-bottom: 20px;
            font-size: 14px;
            color: #6b7280;
        }

        .yprint-mobile-checkbox-group input[type="checkbox"] {
            margin-right: 12px;
            margin-top: 2px;
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
        }

        .yprint-mobile-checkbox-group a {
            color: #3b82f6;
            font-weight: 600;
            text-decoration: none;
        }

        .yprint-mobile-checkbox-group a:hover {
            text-decoration: underline;
        }

        /* Cookie-Transfer-Info Styling */
        .cookie-transfer-info {
            margin-top: 10px;
            padding: 10px;
            background: #e8f4fd;
            border: 1px solid #2997FF;
            border-radius: 6px;
            font-size: 12px;
            color: #1a365d;
            line-height: 1.4;
        }

        .cookie-modify-link {
            color: #2997FF;
            text-decoration: underline;
            font-weight: 600;
            cursor: pointer;
            transition: color 0.2s;
        }

        .cookie-modify-link:hover {
            color: #1e7fff;
            text-decoration: none;
        }

        .cookie-status {
            font-weight: 600;
            color: #2997FF;
        }

        /* Rechtlicher Hinweis */
        .yprint-legal-notice {
            margin-top: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid #2997FF;
        }

        .yprint-legal-notice p {
            margin: 8px 0;
            font-size: 12px;
            color: #666;
            line-height: 1.4;
        }

        .yprint-legal-notice a {
            color: #2997FF;
            text-decoration: none;
            font-weight: 500;
        }

        .yprint-legal-notice a:hover {
            text-decoration: underline;
        }

        /* Error Messages */
        .yprint-mobile-error {
            color: #ef4444;
            font-size: 12px;
            margin-top: 8px;
            display: none;
            padding: 8px 12px;
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #ef4444;
            border-radius: 6px;
        }

       

/* Desktop Standard */
@media screen and (min-width: 1025px) {
            .yprint-mobile-register-container {
                min-height: auto;
            }
            
            .yprint-mobile-register-wrapper {
                max-width: 420px;
            }
        }
        
        /* Mobile + Tablet Responsive - Fullscreen */
        @media screen and (max-width: 1024px) {
            .yprint-mobile-register-container {
                padding: 0 !important;
                min-height: 100vh !important;
                background: #ffffff !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .yprint-mobile-register-wrapper {
                padding: 40px 24px !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                border: none !important;
                min-height: 100vh !important;
                display: flex !important;
                flex-direction: column !important;
                justify-content: center !important;
                max-width: none !important;
                width: 100% !important;
                margin: 0 !important;
                box-sizing: border-box !important;
            }

            /* Elemente begrenzen damit sie nicht zu groß werden */
            #register-form-mobile {
                max-width: 500px;
                margin: 0 auto;
                width: 100%;
            }
            
            .yprint-mobile-login-section {
                max-width: 500px;
                margin: 0 auto;
                width: 100%;
            }

            #register-form-mobile input[type="text"],
            #register-form-mobile input[type="email"],
            #register-form-mobile input[type="password"],
            #register-form-mobile button[type="submit"] {
                max-width: 500px;
                height: 52px !important;
                font-size: 16px !important;
            }
            
            .yprint-mobile-login-button {
                max-width: 500px;
                height: 48px;
                font-size: 15px;
            }
            
            #password-requirements {
                max-width: 500px;
                margin: 12px auto 0 auto;
            }
        }

        /* Kleine Mobile Geräte */
        @media screen and (max-width: 480px) {
            .yprint-mobile-register-wrapper {
                padding: 20px !important;
            }
            
            .yprint-mobile-logo img {
                width: 40px;
                height: 40px;
            }

            .yprint-mobile-register-title {
                font-size: 22px;
            }

            #register-form-mobile input[type="text"],
            #register-form-mobile input[type="email"],
            #register-form-mobile input[type="password"],
            #register-form-mobile button[type="submit"] {
                height: 48px !important;
                font-size: 16px !important;
            }
            
            .yprint-mobile-login-button {
                height: 44px;
                font-size: 14px;
            }

            .cookie-transfer-info {
                font-size: 11px;
                padding: 8px;
            }
        }
    </style>

    <div class="yprint-mobile-register-container">
        <div class="yprint-mobile-register-wrapper">
            <div class="yprint-mobile-register-header">
                <div class="yprint-mobile-logo">
                    <img src="https://yprint.de/wp-content/uploads/2024/10/y-icon.svg" alt="YPrint Logo" />
                </div>
                <h1 class="yprint-mobile-register-title">Konto erstellen</h1>
                <p class="yprint-mobile-register-subtitle">Registriere dich bei YPrint</p>
            </div>

            <form id="register-form-mobile" method="post">
                <div class="yprint-mobile-input-group">
                    <input type="text" name="username" id="user_login_mobile" placeholder="Benutzername" required>
                </div>

                <div class="yprint-mobile-input-group">
                    <input type="email" name="email" id="user_email_mobile" placeholder="E-Mail-Adresse" required>
                </div>

                <div class="yprint-mobile-input-group yprint-mobile-password-container">
                    <input type="password" name="password" id="user_password_mobile" placeholder="Passwort" required>
                    <span id="password-toggle-mobile" class="yprint-mobile-eye-toggle">
                        <i class="eicon-eye"></i>
                    </span>
                </div>
                
                <!-- Passwortanforderungen außerhalb des Containers -->
                <div id="password-requirements" style="display: none;">
                    <ul>
                        <li id="length">Mindestens 8 Zeichen</li>
                        <li id="uppercase">Mindestens ein Großbuchstabe</li>
                        <li id="number">Mindestens eine Zahl</li>
                        <li id="special">Mindestens ein Sonderzeichen</li>
                    </ul>
                </div>

                <div class="yprint-mobile-input-group yprint-mobile-password-container">
                    <input type="password" name="password_confirm" id="user_password_confirm_mobile" placeholder="Passwort wiederholen" required>
                    <span id="confirm-password-toggle-mobile" class="yprint-mobile-eye-toggle">
                        <i class="eicon-eye"></i>
                    </span>
                </div>

                <!-- Hidden Cookie-Preference Fields -->
                <input type="hidden" id="final_cookie_essential" name="cookie_preferences[essential]" value="true">
                <input type="hidden" id="final_cookie_analytics" name="cookie_preferences[analytics]" value="false">
                <input type="hidden" id="final_cookie_marketing" name="cookie_preferences[marketing]" value="false">
                <input type="hidden" id="final_cookie_functional" name="cookie_preferences[functional]" value="false">

                <!-- Minimaler Cookie-Button über Registrieren -->
                <div class="yprint-cookie-section">
                    <button type="button" id="modify-cookie-settings" class="yprint-cookie-btn">
                        Cookies
                    </button>
                </div>

                <?php
                // Turnstile Widget wird automatisch per Auto-Injection eingefügt
                // Keine manuelle Einbindung mehr nötig
                ?>

                <button type="submit" class="yprint-mobile-register-btn">Registrieren</button>

                <!-- Sauberer rechtlicher Hinweis (nur das Wesentliche) -->
                <div class="yprint-legal-notice">
                    <p>Durch Klicken auf 'Registrieren' akzeptieren Sie unsere 
                    <a href="/nutzungsbedingungen" target="_blank">Nutzungsbedingungen</a> 
                    und bestätigen, die 
                    <a href="/datenschutz" target="_blank">Datenschutzerklärung</a> 
                    gelesen zu haben.</p>
                </div>
            </form>

            <div class="yprint-mobile-login-section">
                <p class="yprint-mobile-login-text">Du hast bereits ein Konto?</p>
                <a href="https://yprint.de/login/" class="yprint-mobile-login-button">
                    Jetzt anmelden
                </a>
            </div>
        </div>
    </div>

    <style>
.yprint-cookie-section {
    margin: 15px 0 10px 0;
    text-align: center;
}

.yprint-cookie-btn {
    width: 100% !important;
    box-sizing: border-box !important;
    justify-content: center !important;
    background: #ffffff !important;
    border: 1px solid #e1e5e9 !important;
    border-radius: 6px !important;
    padding: 8px 16px !important;
    font-size: 13px !important;
    color: #666 !important;
    cursor: pointer !important;
    transition: all 0.2s ease !important;
    font-family: inherit !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 8px !important;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1) !important;
}

.yprint-cookie-btn:hover {
    background: #f8f9fa !important;
    border-color: #2997FF !important;
    color: #2997FF !important;
    transform: translateY(-1px) !important;
    box-shadow: 0 2px 8px rgba(41, 151, 255, 0.15) !important;
}

#cookie-status-display {
    font-size: 11px !important;
    color: #888 !important;
    font-weight: normal !important;
}

.yprint-mobile-register-btn {
    margin-top: 5px;
}

@media (max-width: 480px) {
    .yprint-cookie-btn {
        font-size: 12px !important;
        padding: 6px 12px !important;
    }
    #cookie-status-display {
        font-size: 10px !important;
    }
}
</style>

<script>
// === FINALE COOKIE-INTEGRATION (Minimal) ===
function openCookieSettings() {
    const cookieIcon = document.getElementById('yprint-open-consent-settings');
    if (cookieIcon) {
        cookieIcon.click();
        setupBannerCloseListener();
    } else {
        const banner = document.getElementById('yprint-cookie-banner');
        if (banner) {
            banner.style.display = 'block';
            document.body.classList.add('yprint-consent-open');
            setupBannerCloseListener();
        } else if (typeof $ !== 'undefined') {
            $('#yprint-cookie-banner').fadeIn(300);
            $('body').addClass('yprint-consent-open');
            setupBannerCloseListener();
        }
    }
}

function setupBannerCloseListener() {
    let bannerCheckCount = 0;
    const checkInterval = setInterval(() => {
        bannerCheckCount++;
        const banner = document.getElementById('yprint-cookie-banner');
        if (banner) {
            const isVisible = banner.style.display !== 'none' && 
                            window.getComputedStyle(banner).display !== 'none';
            if (!isVisible) {
                clearInterval(checkInterval);
                setTimeout(updateCookieStatus, 1000);
                return;
            }
        }
        if (bannerCheckCount >= 60) clearInterval(checkInterval);
    }, 1000);
}

function updateCookieStatus() {
    let cookiePrefs = {
        essential: true,
        analytics: false,
        marketing: false,
        functional: false
    };
    
    // ✅ VERBESSERUNG: Bessere Cookie-Parsing-Logik
    if (document.cookie.includes('yprint_consent_preferences')) {
        try {
            const cookieValue = getCookieValue('yprint_consent_preferences');
            console.log('🍪 REGISTRATION: Cookie-Wert gefunden:', cookieValue);
            
            if (cookieValue) {
                const decoded = JSON.parse(decodeURIComponent(cookieValue));
                console.log('🍪 REGISTRATION: Dekodierte Cookie-Daten:', decoded);
                
                if (decoded && decoded.consents) {
                    cookiePrefs.essential = decoded.consents.cookie_essential?.granted !== false;
                    cookiePrefs.analytics = decoded.consents.cookie_analytics?.granted === true;
                    cookiePrefs.marketing = decoded.consents.cookie_marketing?.granted === true;
                    cookiePrefs.functional = decoded.consents.cookie_functional?.granted === true;
                    
                    console.log('🍪 REGISTRATION: Extrahierte Cookie-Preferences:', cookiePrefs);
                } else {
                    console.warn('🍪 REGISTRATION: Cookie-Struktur ungültig, verwende Defaults');
                }
            }
        } catch (e) {
            console.error('🍪 REGISTRATION: Fehler beim Cookie-Parsing:', e);
            // Fallback auf Standard-Werte
        }
    } else {
        console.log('🍪 REGISTRATION: Keine Cookie-Präferenzen gefunden, verwende Defaults');
    }
    
    // ✅ VERBESSERUNG: Hidden Fields setzen mit Validation
    const fields = [
        { id: 'final_cookie_essential', value: cookiePrefs.essential },
        { id: 'final_cookie_analytics', value: cookiePrefs.analytics },
        { id: 'final_cookie_marketing', value: cookiePrefs.marketing },
        { id: 'final_cookie_functional', value: cookiePrefs.functional }
    ];
    
    fields.forEach(field => {
        const element = document.getElementById(field.id);
        if (element) {
            element.value = field.value ? 'true' : 'false';
            console.log(`🍪 REGISTRATION: ${field.id} = ${element.value}`);
        } else {
            console.error(`🍪 REGISTRATION: Element ${field.id} nicht gefunden!`);
        }
    });
    
    // Status-Text aktualisieren (minimal)
    const activeCount = Object.values(cookiePrefs).filter(val => val === true).length;
    let statusText;
    if (activeCount === 1) {
        statusText = '(Nur notwendige)';
    } else if (activeCount === 4) {
        statusText = '(Alle aktiv)';
    } else {
        statusText = `(${activeCount} aktiv)`;
    }
    const statusDisplay = document.getElementById('cookie-status-display');
    if (statusDisplay) {
        statusDisplay.textContent = statusText;
    }
}

function getCookieValue(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    return parts.length === 2 ? parts.pop().split(';').shift() : null;
}

document.addEventListener('DOMContentLoaded', function() {
    // Passwort-Toggle
    document.getElementById("password-toggle-mobile").onclick = function() {
        const field = document.getElementById("user_password_mobile");
        const icon = this.querySelector('i');
        if (field.type === "password") {
            field.type = "text";
            icon.style.color = '#3b82f6';
        } else {
            field.type = "password";
            icon.style.color = '#6b7280';
        }
        return false;
    };
    document.getElementById("confirm-password-toggle-mobile").onclick = function() {
        const field = document.getElementById("user_password_confirm_mobile");
        const icon = this.querySelector('i');
        if (field.type === "password") {
            field.type = "text";
            icon.style.color = '#3b82f6';
        } else {
            field.type = "password";
            icon.style.color = '#6b7280';
        }
        return false;
    };
    // Passwort-Validierung
    document.getElementById("user_password_mobile").addEventListener("input", function() {
        const password = this.value;
        const requirements = document.getElementById("password-requirements");
        if (password.length > 0) {
            requirements.style.display = "block";
            const checks = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
            Object.keys(checks).forEach(check => {
                const element = document.getElementById(check);
                if (element) {
                    element.classList.toggle("valid", checks[check]);
                }
            });
        } else {
            requirements.style.display = "none";
        }
    });
    // Cookie-Settings Button
    document.getElementById('modify-cookie-settings').addEventListener('click', function(e) {
        e.preventDefault();
        openCookieSettings();
    });
    // Form-Submit
    document.getElementById('register-form-mobile').addEventListener('submit', function() {
        updateCookieStatus();
    });
    
    // ✅ NEU: Cookie-Status bei Seitenladung initialisieren
    console.log('🍪 REGISTRATION: Initialisiere Cookie-Status bei Seitenladung');
    updateCookieStatus();
    
    // ✅ NEU: Cookie-Button für Registration
    const modifyCookieBtn = document.getElementById('modify-cookie-settings');
    if (modifyCookieBtn) {
        modifyCookieBtn.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🍪 REGISTRATION: Cookie-Modify-Button geklickt');
            openCookieSettings();
        });
    }
    
    // ✅ NEU: Periodische Überprüfung für Live-Updates
    setInterval(function() {
        // Nur aktualisieren wenn Banner nicht sichtbar ist
        const banner = document.getElementById('yprint-cookie-banner');
        if (!banner || banner.style.display === 'none' || window.getComputedStyle(banner).display === 'none') {
            updateCookieStatus();
        }
    }, 2000); // Alle 2 Sekunden prüfen
});
</script>
    <?php
    return ob_get_clean();
}
add_shortcode('yprint_registration_form_mobile', 'yprint_registration_form_mobile');

/**
 * Enqueue registration script for AJAX functionality
 */
function yprint_enqueue_registration_script() {
    wp_add_inline_script('jquery', '
        document.addEventListener("DOMContentLoaded", function () {
            if (document.getElementById("register-form-mobile")) {
                document.getElementById("register-form-mobile").addEventListener("submit", function (event) {
                    event.preventDefault();

                    const username = document.getElementById("user_login_mobile").value;
                    const email = document.getElementById("user_email_mobile").value;
                    const password = document.getElementById("user_password_mobile").value;
                    const confirmPassword = document.getElementById("user_password_confirm_mobile").value;

                    if (password !== confirmPassword) {
                        alert("Passwords do not match.");
                        return;
                    }

                    const data = {
                        username: username,
                        email: email,
                        password: password
                    };

                    // Cookie-Präferenzen aus versteckten Input-Feldern lesen
                    const cookiePreferences = {
                        essential: document.getElementById(\'final_cookie_essential\').value === \'true\',
                        analytics: document.getElementById(\'final_cookie_analytics\').value === \'true\',
                        marketing: document.getElementById(\'final_cookie_marketing\').value === \'true\',
                        functional: document.getElementById(\'final_cookie_functional\').value === \'true\'
                    };
                    
                    // Cookie-Präferenzen zur Daten hinzufügen
                    data.cookie_preferences = cookiePreferences;

                    // Turnstile Token hinzufügen falls vorhanden
                    const turnstileResponse = document.querySelector(\'input[name="cf-turnstile-response"]\');
                    if (turnstileResponse && turnstileResponse.value) {
                        data[\'cf-turnstile-response\'] = turnstileResponse.value;
                    }

                    fetch("' . esc_url(rest_url('wp/v2/users/register')) . '", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                        },
                        body: JSON.stringify(data)
                    })
                    .then(response => response.json())
                    .then(result => {
                        if (result.code === 200) {
                            // Überprüfen, ob die E-Mail erfolgreich gesendet wurde
                            if (result.email_sent === false) {
                                // Wenn die E-Mail nicht gesendet wurde, zur Login-Seite mit einem Parameter für Email-Probleme weiterleiten
                                window.location.href = "/login/?verification_issue=1&user_id=" + result.user_id;
                            } else {
                                // Standardweiterleitung bei erfolgreicher Registrierung und E-Mail-Versand
                                window.location.href = "/login/?verification_sent=1";
                            }
                        } else {
                            alert(result.message || "An error occurred.");
                        }
                    })
                    .catch(error => {
                        alert("An unexpected error occurred.");
                        console.error(error);
                    });
                });
            }
        });
    ');
}
add_action('wp_enqueue_scripts', 'yprint_enqueue_registration_script');

add_action('wp_ajax_yprint_check_confirmation_ready', 'yprint_check_confirmation_ready');
add_action('wp_ajax_nopriv_yprint_check_confirmation_ready', 'yprint_check_confirmation_ready');
function yprint_check_confirmation_ready() {
    $order_id = intval($_POST['order_id'] ?? 0);
    if (!$order_id) {
        wp_send_json_error('Missing order_id');
    }
    $order = wc_get_order($order_id);
    if (!$order) {
        wp_send_json_error('Order not found');
    }
    $shipping = $order->get_meta('_yprint_orchestrator_final_shipping', true);
    $billing  = $order->get_meta('_yprint_orchestrator_final_billing', true);
    $payment  = $order->get_meta('_yprint_stripe_payment_details', true);

    $ready = !empty($shipping) && !empty($billing) && !empty($payment);

    wp_send_json_success([
        'ready' => $ready,
        'shipping' => $shipping,
        'billing' => $billing,
        'payment' => $payment,
    ]);
}