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
        wp_redirect(home_url('/my-products/'));
        exit;
    }
    
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    
    ob_start();
    ?>
    <style>
        /* Grundstil für Loginform */
        #loginform {
            margin: 0 !important;
            padding: 0 !important;
        }
        
        /* Eingabefelder */
        #loginform input[type="text"],
        #loginform input[type="password"],
        #loginform input[type="email"] {
            font-family: 'Roboto' !important;
            background-color: #f1f1f1 !important;
            border-radius: 10px !important;
            border: 1px solid #000000 !important;
            text-align: center !important;
            padding: 10px !important;
            height: 40px !important;
            width: 400px !important;
            margin: 0px !important;
            color: #333 !important;
        }
        
        /* Platzhaltertext */
        #loginform input::placeholder {
            color: #333 !important;
            font-weight: normal !important;
        }
        
        /* Submit-Button */
        #loginform input[type="submit"] {
            width: 155px !important;
            height: 35px !important;
            font-family: 'Roboto' !important;
            font-size: 20px !important;
            font-weight: bold !important;
            color: white !important;
            background-color: #0079FF !important;
            border: 1px solid #707070 !important;
            text-transform: lowercase !important;
            cursor: pointer !important;
            line-height: 1px !important;
            margin-top: 20px !important;
            margin-bottom: -15px !important;
            border-radius: 0px !important;
        }
        
        /* Verstecken unnötiger Elemente */
        #loginform #rememberme,
        #loginform label {
            display: none !important;
        }
        
        /* E-Mail-Hinweis */
        #email-hint {
            position: absolute;
            top: -40px;
            left: 50%;
            transform: translateX(-50%);
            background-color: #fff9c4;
            border: 1px solid #ffc107;
            border-radius: 5px;
            padding: 8px 12px;
            font-size: 14px;
            color: #333;
            display: none;
            z-index: 10;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
            max-width: 90%;
        }
        
        #email-hint:after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 8px solid transparent;
            border-right: 8px solid transparent;
            border-top: 8px solid #fff9c4;
        }
        
        #email-hint:before {
            content: '';
            position: absolute;
            bottom: -9px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 9px solid transparent;
            border-right: 9px solid transparent;
            border-top: 9px solid #ffc107;
        }
        
        /* Neue Passwortfeld-Struktur */
        .password-container {
            position: relative;
            margin-bottom: 20px;
            width: 100%;
        }
        
        /* Augen-Icon für Passwort-Toggle */
        #eye-toggle {
            position: absolute;
            right: -45px; /* Position außerhalb des Felds */
            top: 50%;
            transform: translateY(-50%);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: transparent;
            border: none;
            cursor: pointer;
            z-index: 10;
        }
        
        #eye-toggle i {
            font-size: 24px;
            color: #666;
        }
        
        /* Mobile Wrapper */
        .yprint-login-mobile-wrapper {
            width: 100%;
            display: block;
            position: relative;
        }
        
        @media screen and (max-width: 767px) {
            .yprint-login-mobile-wrapper {
                padding: 0 10%;
            }
            
            #loginform input[type="text"],
            #loginform input[type="password"],
            #loginform input[type="email"] {
                width: 100% !important;
                max-width: 100% !important;
            }
            
            /* Mobile-spezifische Position für das Augen-Icon */
            #eye-toggle {
                right: -35px; /* Anpassung für Mobile */
            }
        }
    </style>
    
    <div class="yprint-login-mobile-wrapper">
    <?php
    // Standard WordPress Login-Formular
    $args = array(
        'redirect' => home_url('/my-products'),
        'label_username' => '',
        'label_password' => '',
        'label_remember' => '',
        'value_username' => '',
        'value_remember' => false,
    );
    
    wp_login_form($args);
    ?>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            var usernameField = document.querySelector('input[name="log"]');
            var passwordField = document.querySelector('input[name="pwd"]');
            
            // Username-Feld anpassen
            if (usernameField) {
                usernameField.setAttribute('placeholder', 'Username');
                
                // Username-Wrapper erstellen
                var usernameWrapper = document.createElement('div');
                usernameWrapper.style.position = 'relative';
                usernameWrapper.style.marginBottom = '20px';
                
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
                passwordField.setAttribute('placeholder', 'Password');
                
                // Container für Passwortfeld erstellen
                var passwordContainer = document.createElement('div');
                passwordContainer.className = 'password-container';
                
                // Passwortfeld in Container verschieben
                passwordField.parentNode.insertBefore(passwordContainer, passwordField);
                passwordContainer.appendChild(passwordField);
                
                // Augen-Icon erstellen - jetzt standardmäßig sichtbar
                var eyeToggle = document.createElement('div');
                eyeToggle.id = 'eye-toggle';
                eyeToggle.innerHTML = '<i class="eicon-eye"></i>';
                passwordContainer.appendChild(eyeToggle);
                
                // Icon-Funktionalität
                eyeToggle.addEventListener('click', function(e) {
                    e.preventDefault(); // Wichtig für Mobile
                    e.stopPropagation(); // Verhindert Bubblen
                    
                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        this.querySelector('i').style.color = '#0079FF';
                    } else {
                        passwordField.type = 'password';
                        this.querySelector('i').style.color = '#666';
                    }
                });
                
                // Spezifisch für Touch-Geräte
                eyeToggle.addEventListener('touchstart', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        this.querySelector('i').style.color = '#0079FF';
                    } else {
                        passwordField.type = 'password';
                        this.querySelector('i').style.color = '#666';
                    }
                });
                
                // Auch ein normales "touch" Event hinzufügen für ältere Mobilgeräte
                eyeToggle.addEventListener('touch', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (passwordField.type === 'password') {
                        passwordField.type = 'text';
                        this.querySelector('i').style.color = '#0079FF';
                    } else {
                        passwordField.type = 'password';
                        this.querySelector('i').style.color = '#666';
                    }
                });
                
                // Füge einen Fokus-Event hinzu, der sicherstellt, dass das Icon auch auf Mobile funktioniert
                passwordField.addEventListener('click', function() {
                    // Nichts tun, aber das Event abfangen
                });
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
                    ❓ Passwort vergessen? Konto wiederherstellen
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