<?php
/**
 * Login functions for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Customized login form shortcode
 */
function yprint_login_form_shortcode() {
    add_filter('wpseo_canonical', function($canonical) {
        if (is_page('login')) {
            return home_url('/login/');
        }
        return $canonical;
    });
    
    // Sofortige Weiterleitung, wenn der Benutzer eingeloggt ist und kein Admin ist
    if (is_user_logged_in() && !current_user_can('administrator')) {
        wp_redirect(home_url('/dashboard/'));
        exit;
    }
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    ob_start();
    
    // Feedback-System automatisch einbinden
    echo yprint_login_feedback_shortcode();
    ?>
    <style>
        /* [Alle bisherigen CSS-Styles bleiben unverändert] */
        .yprint-login-container {
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            padding: 40px 20px;
            box-sizing: border-box;
        }

        .yprint-login-card {
            background: #ffffff !important;
            border-radius: 20px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid #e5e7eb !important;
            padding: 40px !important;
            width: 100% !important;
            max-width: 420px !important;
            position: relative !important;
        }

        .yprint-login-header {
            text-align: center;
            margin-bottom: 32px;
        }

        .elementor .yprint-login-card,
        div.yprint-login-card,
        .yprint-login-container .yprint-login-card {
            background: #ffffff !important;
            border-radius: 20px !important;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
            border: 1px solid #e5e7eb !important;
            padding: 40px !important;
            width: 100% !important;
            max-width: 420px !important;
            position: relative !important;
        }

        .yprint-logo {
            display: flex;
            justify-content: center;
            margin-bottom: 24px;
        }

        .yprint-logo img {
            width: 48px;
            height: 48px;
            object-fit: contain;
        }

        .yprint-login-title {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 8px 0;
        }

        .yprint-login-subtitle {
            font-size: 15px;
            color: #6b7280;
            margin: 0;
        }

        .yprint-register-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .yprint-register-text {
            font-size: 14px;
            color: #6b7280;
            margin: 0 0 16px 0;
        }

        .yprint-register-button {
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

        .yprint-register-button:hover {
            background-color: #f1f5f9;
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            color: #2563eb !important;
            text-decoration: none;
        }

        .yprint-input-group {
            margin-bottom: 24px;
            position: relative;
        }

        #yprint-loginform {
            margin: 0 !important;
            padding: 0 !important;
            background: none !important;
            border: none !important;
            box-shadow: none !important;
        }

        #yprint-loginform input[type="text"],
        #yprint-loginform input[type="password"],
        #yprint-loginform input[type="email"] {
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

        #yprint-loginform input[type="text"]:focus,
        #yprint-loginform input[type="password"]:focus,
        #yprint-loginform input[type="email"]:focus {
            background-color: #ffffff !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }

        #yprint-loginform input::placeholder {
            color: #9ca3af !important;
            font-weight: 400 !important;
        }

        #yprint-loginform input[type="submit"] {
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

        #yprint-loginform input[type="submit"]:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
        }

        #yprint-loginform #rememberme,
        #yprint-loginform label {
            display: none !important;
        }

        .password-container {
            position: relative;
            width: 100%;
        }

        #eye-toggle {
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

        #eye-toggle:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        #eye-toggle i {
            font-size: 18px;
            color: #6b7280;
            transition: color 0.2s ease;
        }

        #eye-toggle:hover i {
            color: #3b82f6;
        }

        #email-hint {
            position: absolute;
            top: -45px;
            left: 50%;
            transform: translateX(-50%);
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 12px 16px;
            font-size: 13px;
            font-weight: 500;
            color: #92400e;
            display: none;
            z-index: 20;
            text-align: center;
            box-shadow: 0 4px 12px rgba(245, 158, 11, 0.15);
            max-width: 90%;
            white-space: nowrap;
        }

        #email-hint:after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid #fde68a;
        }

        @media screen and (min-width: 1025px) {
            .yprint-login-container {
                min-height: auto;
            }
            
            .yprint-login-card {
                max-width: 420px;
            }
        }
        
        @media screen and (min-width: 769px) {
            .yprint-login-container {
                min-height: auto !important;
            }
            
            .yprint-login-card,
            .elementor .yprint-login-card,
            div.yprint-login-card {
                max-width: 420px !important;
                border-radius: 20px !important;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1) !important;
                border: 1px solid #e5e7eb !important;
                background: #ffffff !important;
            }

            .yprint-login-form,
            .yprint-register-section {
                max-width: 500px;
                margin: 0 auto;
                width: 100%;
            }

            #yprint-loginform input[type="text"],
            #yprint-loginform input[type="password"],
            #yprint-loginform input[type="email"],
            #yprint-loginform input[type="submit"] {
                max-width: 500px;
                height: 52px !important;
                font-size: 16px !important;
            }
            
            .yprint-register-button {
                max-width: 500px;
                height: 48px;
                font-size: 15px;
            }
            
            #email-hint {
                max-width: 500px;
                margin: 12px auto 0 auto;
            }
        }

        @media screen and (max-width: 480px) {
            .yprint-login-card {
                padding: 20px !important;
            }
            
            .yprint-logo img {
                width: 40px;
                height: 40px;
            }

            .yprint-login-title {
                font-size: 22px;
            }

            .yprint-login-subtitle {
                font-size: 14px;
            }

            .yprint-register-button {
                height: 44px;
                font-size: 14px;
            }

            #yprint-loginform input[type="text"],
            #yprint-loginform input[type="password"],
            #yprint-loginform input[type="email"],
            #yprint-loginform input[type="submit"] {
                height: 48px !important;
                font-size: 16px !important;
            }

            #email-hint {
                font-size: 12px;
                padding: 10px 14px;
                white-space: normal;
                max-width: 95%;
            }
        }

        @media screen and (max-width: 320px) {
            .yprint-login-card {
                padding: 20px;
            }

            .yprint-register-button {
                height: 40px;
                font-size: 13px;
                padding: 10px 20px;
            }

            #yprint-loginform input[type="text"],
            #yprint-loginform input[type="password"],
            #yprint-loginform input[type="email"],
            #yprint-loginform input[type="submit"] {
                height: 44px !important;
                padding: 12px 16px !important;
            }
        }
    </style>
    
    <div class="yprint-login-container">
        <div class="yprint-login-card">
            <div class="yprint-login-header">
                <div class="yprint-logo">
                    <img src="https://yprint.de/wp-content/uploads/2024/10/y-icon.svg" alt="YPrint Logo" />
                </div>
                <h1 class="yprint-login-title">Willkommen zurück!</h1>
                <p class="yprint-login-subtitle">Bitte melde dich an, um fortzufahren</p>
            </div>
            
            <div class="yprint-login-form">
                <form name="yprint-loginform" id="yprint-loginform" action="<?php echo esc_url(home_url('/login/')); ?>" method="post">
                    <div class="yprint-input-group">
                        <input type="text" name="log" id="user_login" placeholder="Benutzername" value="" size="20" autocapitalize="off" required />
                        <div id="email-hint">Bitte beachte: Hier wird dein Benutzername benötigt, nicht deine E-Mail-Adresse.</div>
                    </div>
                    
                    <div class="yprint-input-group password-container">
                        <input type="password" name="pwd" id="user_pass" placeholder="Passwort" value="" size="20" autocomplete="current-password" required />
                        <button type="button" id="eye-toggle" aria-label="Passwort anzeigen/verstecken">
                            <i class="eicon-eye"></i>
                        </button>
                    </div>
                    
                    <div class="yprint-input-group">
                        <input type="submit" name="wp-submit" id="wp-submit" value="Anmelden" />
                        <input type="hidden" name="redirect_to" value="<?php echo esc_attr(home_url('/dashboard/')); ?>" />
                        <input type="hidden" name="yprint_login" value="1" />
                    </div>
                </form>
            </div>
            
            <div class="yprint-register-section">
                <p class="yprint-register-text">Noch kein Konto?</p>
                <a href="https://yprint.de/register/" class="yprint-register-button">
                    Jetzt registrieren
                </a>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var usernameField = document.getElementById('user_login');
        var passwordField = document.getElementById('user_pass');
        var emailHint = document.getElementById('email-hint');
        var eyeToggle = document.getElementById('eye-toggle');
        
        // Username Email-Hinweis
        if (usernameField && emailHint) {
            usernameField.addEventListener('input', function() {
                if (this.value.includes('@')) {
                    emailHint.style.display = 'block';
                } else {
                    emailHint.style.display = 'none';
                }
            });
        }
        
        // Password Toggle
        if (passwordField && eyeToggle) {
            function togglePassword(e) {
                e.preventDefault();
                e.stopPropagation();
                
                if (passwordField.type === 'password') {
                    passwordField.type = 'text';
                    eyeToggle.querySelector('i').style.color = '#3b82f6';
                } else {
                    passwordField.type = 'password';
                    eyeToggle.querySelector('i').style.color = '#6b7280';
                }
            }
            
            eyeToggle.addEventListener('click', togglePassword);
            eyeToggle.addEventListener('touchstart', togglePassword);
        }
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('yprint_login_form', 'yprint_login_form_shortcode');

/**
 * Umfassende Login-Fehlerbehandlung
 */
function yprint_handle_login_errors() {
    // Nur bei POST-Requests von unserem Login-Formular
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['yprint_login']) || !isset($_POST['log']) || !isset($_POST['pwd'])) {
        return;
    }
    
    $username = sanitize_text_field($_POST['log']);
    $password = $_POST['pwd'];
    
    // Prüfe leere Felder
    if (empty($username) || empty($password)) {
        wp_redirect(home_url('/login/') . '?login=empty');
        exit;
    }
    
    // Versuche Authentifizierung
    $user = wp_authenticate($username, $password);
    
    if (is_wp_error($user)) {
        // Login fehlgeschlagen
        wp_redirect(home_url('/login/') . '?login=failed');
        exit;
    }
    
    // E-Mail-Verifikation prüfen
    global $wpdb;
    $table_name = 'wp_email_verifications';
    $user_id = $user->ID;
    
    $email_verified = $wpdb->get_var(
        $wpdb->prepare("SELECT email_verified FROM $table_name WHERE user_id = %d", $user_id)
    );
    
    if ($email_verified !== null && $email_verified != 1) {
        wp_redirect(home_url('/login/?login=email_not_verified&user_id=' . $user_id));
        exit;
    }
    
    // Login erfolgreich - einloggen und weiterleiten
    wp_set_current_user($user_id, $username);
    wp_set_auth_cookie($user_id);
    do_action('wp_login', $username, $user);
    
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : home_url('/dashboard/');
    wp_redirect($redirect_to);
    exit;
}
add_action('init', 'yprint_handle_login_errors', 1);

/**
 * Backup: wp_login_failed Hook
 */
function yprint_login_failed_redirect($username) {
    // Nur weiterleiten wenn wir von unserem Login kommen
    if (isset($_POST['log'])) {
        wp_redirect(home_url('/login/') . '?login=failed');
        exit;
    }
}
add_action('wp_login_failed', 'yprint_login_failed_redirect');

/**
 * E-Mail-Verifikation beim Login überprüfen
 */
function yprint_authenticate_user($user, $username, $password) {
    if (is_wp_error($user)) {
        return $user;
    }
    
    // Wenn die Login-Daten noch nicht überprüft wurden, nicht weiterfahren
    if (!$username || !$password) {
        return $user;
    }
    
    global $wpdb;
    $table_name = 'wp_email_verifications';
    $user_id = $user->ID;
    
    $email_verified = $wpdb->get_var(
        $wpdb->prepare("SELECT email_verified FROM $table_name WHERE user_id = %d", $user_id)
    );
    
    // Prüfen ob ein Eintrag für den Benutzer existiert
    if ($email_verified === null) {
        // Wenn kein Eintrag existiert, Benutzer als verifiziert betrachten
        return $user;
    }
    
    if ($email_verified != 1) {
        wp_redirect(home_url('/login/?login=email_not_verified&user_id=' . $user_id));
        exit;
    }
    
    return $user;
}
add_filter('authenticate', 'yprint_authenticate_user', 30, 3);

/**
 * Handler für das Resenden der Verifikations-E-Mail
 */
function yprint_handle_resend_verification() {
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['resend_verification'])) {
        check_admin_referer('resend_verification_nonce', 'security');
        
        global $wpdb;
        
        $table_name = 'wp_email_verifications';
        $user_id = intval($_POST['resend_verification']);
        $user = get_userdata($user_id);
        
        if ($user) {
            $email = $user->user_email;
            $username = $user->user_login;
            $verification_code = bin2hex(random_bytes(16));
            $current_time = current_time('mysql');
            
            // Zuerst den alten Eintrag definitiv löschen
            $wpdb->delete(
                $table_name,
                array('user_id' => $user_id),
                array('%d')
            );
            
            // Dann einen neuen Eintrag erstellen
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
            
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            
            wp_redirect(home_url('/login/?verification_sent=1'));
            exit;
        }
    }
}
add_action('init', 'yprint_handle_resend_verification');

/**
 * Login feedback and error messages shortcode
 */
function yprint_login_feedback_shortcode() {
    ob_start();

    $notifications = array();
    $show_recover_option = false;
    $show_resend_verification = false;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    // Verschiedene Notification-Typen sammeln
    if (isset($_GET['login'])) {
        switch ($_GET['login']) {
            case 'failed':
                $notifications[] = array(
                    'type' => 'error',
                    'title' => 'Login fehlgeschlagen',
                    'message' => 'Falscher Benutzername oder Passwort!',
                    'duration' => 0 // 0 = bleibt bis manuell geschlossen
                );
                $show_recover_option = true;
                break;
            case 'email_not_verified':
                $notifications[] = array(
                    'type' => 'warning',
                    'title' => 'E-Mail nicht bestätigt',
                    'message' => 'Deine E-Mail-Adresse wurde noch nicht bestätigt. Bitte überprüfe dein Postfach.',
                    'duration' => 0
                );
                $show_resend_verification = true;
                break;
            case 'empty':
                $notifications[] = array(
                    'type' => 'error',
                    'title' => 'Felder ausfüllen',
                    'message' => 'Bitte fülle alle Felder aus!',
                    'duration' => 5000
                );
                break;
        }
    }

    if (isset($_GET['verification_sent']) && $_GET['verification_sent'] == '1') {
        $notifications[] = array(
            'type' => 'success',
            'title' => 'Registrierung erfolgreich!',
            'message' => 'Eine Bestätigungs-E-Mail wurde an deine E-Mail-Adresse gesendet. Bitte überprüfe dein Postfach.',
            'duration' => 0
        );
        $show_resend_verification = true;
    }
    
    if (isset($_GET['verification_issue']) && $_GET['verification_issue'] == '1') {
        $notifications[] = array(
            'type' => 'warning',
            'title' => 'E-Mail-Problem',
            'message' => 'Dein Account wurde erstellt, aber es gab ein Problem beim Senden der Bestätigungs-E-Mail.',
            'duration' => 0
        );
        $show_resend_verification = true;
    }

    // CSS für Toast-Notifications
    echo '<style>
        /* Toast Notification System */
        .yprint-toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 99999;
            display: flex;
            flex-direction: column;
            gap: 12px;
            max-width: 400px;
            width: 100%;
            pointer-events: none;
        }

        .yprint-toast {
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.12);
            border: 1px solid #e5e7eb;
            padding: 16px 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.4s cubic-bezier(0.23, 1, 0.32, 1);
            pointer-events: auto;
            font-family: "Inter", "Roboto", "Helvetica Neue", Arial, sans-serif;
            position: relative;
            overflow: hidden;
        }

        .yprint-toast.show {
            opacity: 1;
            transform: translateX(0);
        }

        .yprint-toast-icon {
            font-size: 20px;
            line-height: 1;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .yprint-toast-content {
            flex: 1;
            min-width: 0;
        }

        .yprint-toast-title {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 4px 0;
            color: #111827;
            line-height: 1.3;
        }

        .yprint-toast-message {
            font-size: 13px;
            color: #6b7280;
            margin: 0;
            line-height: 1.4;
            word-wrap: break-word;
        }

        .yprint-toast-close {
            position: absolute;
            top: 8px;
            right: 8px;
            background: transparent;
            border: none;
            color: #9ca3af;
            cursor: pointer;
            font-size: 16px;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
            line-height: 1;
        }

        .yprint-toast-close:hover {
            color: #374151;
            background: rgba(0, 0, 0, 0.05);
        }

        /* Toast-Typen */
        .yprint-toast.error {
            border-left: 4px solid #ef4444;
        }
        .yprint-toast.error .yprint-toast-icon {
            color: #ef4444;
        }

        .yprint-toast.success {
            border-left: 4px solid #10b981;
        }
        .yprint-toast.success .yprint-toast-icon {
            color: #10b981;
        }

        .yprint-toast.warning {
            border-left: 4px solid #f59e0b;
        }
        .yprint-toast.warning .yprint-toast-icon {
            color: #f59e0b;
        }

        .yprint-toast.info {
            border-left: 4px solid #3b82f6;
        }
        .yprint-toast.info .yprint-toast-icon {
            color: #3b82f6;
        }

        /* Progress Bar für Auto-Close */
        .yprint-toast-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: linear-gradient(90deg, #3b82f6, #2563eb);
            border-radius: 0 0 12px 12px;
            transition: width linear;
        }

        /* Action Buttons */
        .yprint-toast-actions {
            margin-top: 12px;
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .yprint-toast-action {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            color: #374151;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .yprint-toast-action:hover {
            background: #e5e7eb;
            color: #111827;
            text-decoration: none;
        }

        .yprint-toast-action.primary {
            background: #3b82f6;
            border-color: #3b82f6;
            color: #ffffff;
        }

        .yprint-toast-action.primary:hover {
            background: #2563eb;
            color: #ffffff;
        }

        /* Mobile Responsive */
        @media screen and (max-width: 480px) {
            .yprint-toast-container {
                top: 10px;
                right: 10px;
                left: 10px;
                max-width: none;
            }

            .yprint-toast {
                margin: 0;
            }

            .yprint-toast-title {
                font-size: 13px;
            }

            .yprint-toast-message {
                font-size: 12px;
            }
        }
    </style>';

    // Toast Container
    echo '<div id="yprint-toast-container" class="yprint-toast-container"></div>';

    // JavaScript für Toast-System
    echo '<script>
        window.YPrintToast = {
            container: null,
            toastCounter: 0,

            init: function() {
                this.container = document.getElementById("yprint-toast-container");
                if (!this.container) return;
                
                // Zeige alle gesammelten Notifications
                this.showQueuedNotifications();
            },

            show: function(type, title, message, duration = 5000, actions = []) {
                if (!this.container) return;

                const toastId = "toast-" + (++this.toastCounter);
                const iconMap = {
                    error: "⚠️",
                    success: "✅", 
                    warning: "⚠️",
                    info: "ℹ️"
                };

                let actionsHtml = "";
                if (actions.length > 0) {
                    actionsHtml = "<div class=\"yprint-toast-actions\">";
                    actions.forEach(action => {
                        if (action.type === "link") {
                            actionsHtml += `<a href="${action.url}" class="yprint-toast-action ${action.primary ? "primary" : ""}">${action.label}</a>`;
                        } else if (action.type === "form") {
                            actionsHtml += `<form method="post" action="${action.action}" style="display: inline;">${action.fields}<button type="submit" class="yprint-toast-action ${action.primary ? "primary" : ""}">${action.label}</button></form>`;
                        }
                    });
                    actionsHtml += "</div>";
                }

                const toastHtml = `
    <div id="${toastId}" class="yprint-toast ${type}">
        <div class="yprint-toast-icon">${iconMap[type] || "ℹ️"}</div>
        <div class="yprint-toast-content">
            <div class="yprint-toast-title">${title}</div>
            <div class="yprint-toast-message">${message}</div>
            ${actionsHtml}
        </div>
        <button class="yprint-toast-close" onclick="YPrintToast.close(\'${toastId}\')">&times;</button>
        ${duration > 0 ? `<div class="yprint-toast-progress" style="width: 100%"></div>` : ""}
    </div>
`;

                this.container.insertAdjacentHTML("beforeend", toastHtml);
                const toast = document.getElementById(toastId);
                
                // Animation starten
                setTimeout(() => {
                    toast.classList.add("show");
                }, 100);

                // Auto-close mit Progress Bar
                if (duration > 0) {
                    const progressBar = toast.querySelector(".yprint-toast-progress");
                    if (progressBar) {
                        progressBar.style.width = "0%";
                        progressBar.style.transitionDuration = duration + "ms";
                    }
                    
                    setTimeout(() => {
                        this.close(toastId);
                    }, duration);
                }
            },

            close: function(toastId) {
                const toast = document.getElementById(toastId);
                if (toast) {
                    toast.style.transform = "translateX(100%)";
                    toast.style.opacity = "0";
                    setTimeout(() => {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 400);
                }
            },

            showQueuedNotifications: function() {';

    // JavaScript für die gesammelten Notifications
    foreach ($notifications as $index => $notification) {
        $actions = array();
        
        // Actions basierend auf dem Notification-Typ hinzufügen
        if ($show_recover_option && $notification['type'] === 'error' && strpos($notification['message'], 'Falscher') !== false) {
            $actions[] = array(
                'type' => 'link',
                'url' => esc_url(home_url('/recover-account/')),
                'label' => '🔑 Konto wiederherstellen',
                'primary' => true
            );
        }

        if ($show_resend_verification && $user_id && ($notification['type'] === 'warning' || $notification['type'] === 'success')) {
            $nonce_field = wp_nonce_field('resend_verification_nonce', 'security', true, false);
            $actions[] = array(
                'type' => 'form',
                'action' => esc_url(home_url('/login')),
                'fields' => $nonce_field . '<input type="hidden" name="resend_verification" value="' . esc_attr($user_id) . '">',
                'label' => '✉️ E-Mail erneut senden',
                'primary' => false
            );
        }

        $actions_json = json_encode($actions);
        $delay = $index * 200; // Stagger die Notifications

        echo "
                setTimeout(() => {
                    this.show(
                        '" . esc_js($notification['type']) . "',
                        '" . esc_js($notification['title']) . "',
                        '" . esc_js($notification['message']) . "',
                        " . intval($notification['duration']) . ",
                        " . $actions_json . "
                    );
                }, $delay);";
    }

// Debug: Zeige alle URL-Parameter
echo '<script>
console.log("YPrint Login Debug:");
console.log("URL Parameters:", new URLSearchParams(window.location.search));
console.log("Current URL:", window.location.href);

// Test-Toast für Debugging
setTimeout(() => {
    if (window.YPrintToast && new URLSearchParams(window.location.search).get("debug") === "1") {
        window.YPrintToast.show("info", "Debug Toast", "Toast-System funktioniert!", 3000);
    }
}, 1000);
</script>';

    echo '
            }
        };

        // Toast-System initialisieren
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", () => YPrintToast.init());
        } else {
            YPrintToast.init();
        }
            
    </script>';

    return ob_get_clean();
}
add_shortcode('yprint_login_feedback', 'yprint_login_feedback_shortcode');