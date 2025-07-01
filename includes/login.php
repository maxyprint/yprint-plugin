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
    ?>
    <style>
        /* Moderner Login Container - flexibel für Integration */
.yprint-login-container {
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Inter', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
    padding: 40px 20px;
    box-sizing: border-box;
}

.yprint-login-card {
    background: #ffffff;
    border-radius: 20px;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
    border: 1px solid #e5e7eb;
    padding: 40px;
    width: 100%;
    max-width: 420px;
    position: relative;
}

/* Mobile Override für vollständige Breite */
@media screen and (max-width: 768px) {
    .yprint-login-card {
        max-width: none !important;
        width: 100% !important;
    }
}

.yprint-login-header {
    text-align: center;
    margin-bottom: 32px;
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

/* Registrieren Button */
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

.yprint-register-button:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
}
        
        /* Reset WordPress Form Styles */
        #loginform {
            margin: 0 !important;
            padding: 0 !important;
            background: none !important;
            border: none !important;
            box-shadow: none !important;
        }
        
        /* Input Field Container */
        .yprint-input-group {
            margin-bottom: 24px;
            position: relative;
        }
        
        /* Eingabefelder */
        #loginform input[type="text"],
        #loginform input[type="password"],
        #loginform input[type="email"] {
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
        
        #loginform input[type="text"]:focus,
        #loginform input[type="password"]:focus,
        #loginform input[type="email"]:focus {
            background-color: #ffffff !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }
        
        /* Platzhaltertext */
        #loginform input::placeholder {
            color: #9ca3af !important;
            font-weight: 400 !important;
        }
        
        /* Submit-Button */
        #loginform input[type="submit"] {
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
        
        #loginform input[type="submit"]:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
        }
        
        #loginform input[type="submit"]:active {
            transform: translateY(0) !important;
        }
        
        /* Verstecken unnötiger Elemente */
        #loginform #rememberme,
        #loginform label {
            display: none !important;
        }
        
        /* E-Mail-Hinweis - moderner Stil */
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
        
        /* Passwort Container */
        .password-container {
            position: relative;
            width: 100%;
        }
        
        /* Augen-Icon für Passwort-Toggle - moderner Stil */
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
        

        /* Desktop Standard */
        @media screen and (min-width: 769px) {
            .yprint-login-container {
                min-height: auto;
            }
            
            .yprint-login-card {
                max-width: 420px;
            }
        }

        /* Desktop Standard */
        @media screen and (min-width: 769px) {
            .yprint-login-container {
                min-height: auto;
            }
            
            .yprint-login-card {
                max-width: 420px;
            }
        }
        
        /* Mobile Responsive - Fullscreen */
        @media screen and (max-width: 768px) {
            .yprint-login-container {
                padding: 0 !important;
                min-height: 100vh;
                background: #ffffff;
                width: 100% !important;
                box-sizing: border-box;
            }
            
            .yprint-login-card {
                padding: 40px 24px !important;
                border-radius: 0 !important;
                box-shadow: none !important;
                border: none !important;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
                justify-content: center;
                max-width: none !important;
                width: 100% !important;
                margin: 0 !important;
                box-sizing: border-box;
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
            
            #loginform input[type="text"],
            #loginform input[type="password"],
            #loginform input[type="email"],
            #loginform input[type="submit"] {
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
            
            #loginform input[type="text"],
            #loginform input[type="password"],
            #loginform input[type="email"],
            #loginform input[type="submit"] {
                height: 44px !important;
                padding: 12px 16px !important;
            }
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
    
    #loginform input[type="text"],
    #loginform input[type="password"],
    #loginform input[type="email"],
    #loginform input[type="submit"] {
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
    
    #loginform input[type="text"],
    #loginform input[type="password"],
    #loginform input[type="email"],
    #loginform input[type="submit"] {
        height: 44px !important;
        padding: 12px 16px !important;
    }
}
        }
        
        @media screen and (max-width: 320px) {
            .yprint-login-card {
                padding: 20px;
            }
            
            #loginform input[type="text"],
            #loginform input[type="password"],
            #loginform input[type="email"],
            #loginform input[type="submit"] {
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
            <?php
            // Standard WordPress Login-Formular
            $args = array(
                'redirect' => home_url('/dashboard'),
                'label_username' => '',
                'label_password' => '',
                'label_remember' => '',
                'value_username' => '',
                'value_remember' => false,
            );
            
            wp_login_form($args);
            ?>
        </div>
        
        <div class="yprint-register-section">
            <p class="yprint-register-text">Noch kein Konto?</p>
            <a href="https://yprint.de/register/" class="yprint-register-button">
                Jetzt registrieren
            </a>
        </div>
    </div>
</div>
    
    <!-- Dieser komplette Block wird entfernt -->
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var usernameField = document.querySelector('input[name="log"]');
        var passwordField = document.querySelector('input[name="pwd"]');
        
        // Username-Feld anpassen
        if (usernameField) {
            usernameField.setAttribute('placeholder', 'Benutzername');
            
            // Username-Wrapper erstellen mit neuer Klasse
            var usernameWrapper = document.createElement('div');
            usernameWrapper.className = 'yprint-input-group';
            
            // Das original Benutzernamenfeld in den Wrapper verschieben
            usernameField.parentNode.insertBefore(usernameWrapper, usernameField);
            usernameWrapper.appendChild(usernameField);
            
            // Hinweis-Element erstellen
            var emailHint = document.createElement('div');
            emailHint.id = 'email-hint';
            emailHint.innerHTML = 'Bitte beachte: Hier wird dein Benutzername benötigt, nicht deine E-Mail-Adresse.';
            usernameWrapper.appendChild(emailHint);
            
            // Input-Event für den Benutzernamen
            usernameField.addEventListener('input', function() {
                if (this.value.includes('@')) {
                    emailHint.style.display = 'block';
                } else {
                    emailHint.style.display = 'none';
                }
            });
        }
        
        // Passwort-Feld anpassen
        if (passwordField) {
            passwordField.setAttribute('placeholder', 'Passwort');
            
            // Container für Passwortfeld erstellen mit neuer Klasse
            var passwordContainer = document.createElement('div');
            passwordContainer.className = 'yprint-input-group password-container';
            
            // Passwortfeld in Container verschieben
            passwordField.parentNode.insertBefore(passwordContainer, passwordField);
            passwordContainer.appendChild(passwordField);
            
            // Augen-Icon erstellen mit modernem Design
            var eyeToggle = document.createElement('button');
            eyeToggle.type = 'button';
            eyeToggle.id = 'eye-toggle';
            eyeToggle.innerHTML = '<i class="eicon-eye"></i>';
            eyeToggle.setAttribute('aria-label', 'Passwort anzeigen/verstecken');
            passwordContainer.appendChild(eyeToggle);
            
            // Icon-Funktionalität mit neuen Farben
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
            
            // Event Listeners für alle Geräte
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
 * Login feedback and error messages shortcode
 */
function yprint_login_feedback_shortcode() {
    ob_start();

    $error_message = '';
    $success_message = '';
    $info_message = '';
    $show_recover_option = false;
    $show_resend_verification = false;
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

    if (isset($_GET['login'])) {
        switch ($_GET['login']) {
            case 'failed':
                $error_message = '⚠️ Falscher Benutzername oder Passwort!';
                $show_recover_option = true;
                break;
            case 'email_not_verified':
                $error_message = '⚠️ Deine E-Mail-Adresse wurde noch nicht bestätigt. Bitte überprüfe dein Postfach.';
                $show_resend_verification = true;
                break;
            case 'empty':
                $error_message = '⚠️ Bitte fülle alle Felder aus!';
                break;
        }
    }

    if (isset($_GET['verification_sent']) && $_GET['verification_sent'] == '1') {
        $success_message = '✅ Registrierung erfolgreich! Eine Bestätigungs-E-Mail wurde an deine E-Mail-Adresse gesendet. Bitte überprüfe dein Postfach und bestätige deine Adresse, um dich einloggen zu können.';
        $show_resend_verification = true;
    }
    
    if (isset($_GET['verification_issue']) && $_GET['verification_issue'] == '1') {
        $error_message = '⚠️ Dein Account wurde erstellt, aber es gab ein Problem beim Senden der Bestätigungs-E-Mail.';
        $info_message = 'Du kannst dich anmelden, sobald deine E-Mail-Adresse bestätigt ist. Bitte verwende die Option zum erneuten Senden der Bestätigungs-E-Mail.';
        $show_resend_verification = true;
    }

    if ($error_message) {
        echo '<div style="color: red; text-align: center; margin-bottom: 10px;">' . esc_html($error_message) . '</div>';
    }

    if ($info_message) {
        echo '<div style="color: #0079FF; text-align: center; margin-bottom: 10px;">' . esc_html($info_message) . '</div>';
    }

    if ($success_message) {
        echo '<div style="color: green; text-align: center; margin-bottom: 10px;">' . esc_html($success_message) . '</div>';
    }

    if ($show_recover_option) {
        echo '<div style="text-align: center; margin-top: 10px;">
                <a href="' . esc_url(home_url('/recover-account/')) . '" style="color: #0079FF; font-weight: bold; text-decoration: none;">
                    ❓ Passwort vergessen oder Nutzername falsch? Konto wiederherstellen
                </a>
              </div>';
    }

    if ($show_resend_verification && $user_id) {
        echo '<div style="text-align: center; margin-top: 10px;">
                <form method="post" action="' . esc_url(home_url('/login')) . '">';
        // PHP-Tags außerhalb des Strings platzieren
        wp_nonce_field('resend_verification_nonce', 'security');
        echo '<input type="hidden" name="resend_verification" value="' . esc_attr($user_id) . '">
                <button type="submit" style="background-color: #0079FF; color: white; padding: 10px; border: none; cursor: pointer;">
                ✉️ Bestätigungs-E-Mail erneut senden
                </button>
                </form>
              </div>';
    } else if ($show_resend_verification) {
        // Wenn kein User-ID Parameter vorhanden ist, aber trotzdem der Button gezeigt werden soll
        echo '<div style="text-align: center; margin-top: 10px; color: #0079FF;">
                Wenn du keine E-Mail erhalten hast, versuche dich mit deinen Daten anzumelden, um die Bestätigungs-E-Mail erneut zu senden.
              </div>';
    }

    return ob_get_clean();
}
add_shortcode('yprint_login_feedback', 'yprint_login_feedback_shortcode');

/**
 * Verhindert die Weiterleitung von wp-login.php
 */
function yprint_login_failed_redirect($username) {
    if (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/wp-login.php') !== false) {
        if (!isset($_POST['log'])) { 
            return;
        }
        wp_redirect(home_url('/login/') . '?login=failed');
        exit;
    }
}
add_action('wp_login_failed', 'yprint_login_failed_redirect');

/**
 * Fehlerbehandlung für leere Felder
 */
function yprint_empty_fields_redirect($user, $username, $password) {
    if (empty($username) || empty($password)) {
        if (isset($_POST['log']) && isset($_POST['pwd'])) {
            wp_redirect(home_url('/login/') . '?login=empty');
            exit;
        }
    }
    return $user;
}
add_filter('authenticate', 'yprint_empty_fields_redirect', 30, 3);

/**
 * Weiterleitung nach fehlgeschlagenem Login
 */
function yprint_redirect_after_failed_login($redirect_to, $request, $user) {
    if (is_wp_error($user)) {
        if (isset($_POST['log'])) {
            $redirect_to = home_url('/login/') . '?login=failed';
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'yprint_redirect_after_failed_login', 10, 3);

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