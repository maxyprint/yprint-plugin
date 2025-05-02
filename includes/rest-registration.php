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
    
    // HMAC-basierte Validierung ist besser als Nonce für REST API
    // Keine Änderung, da WP REST API eigene Sicherheitsmaßnahmen hat

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
 * Shortcode for verification page
 */
function verify_email_shortcode() {
    ob_start();

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
                echo '<div class="yprint-message success">
                    <h2>E-Mail-Adresse bereits bestätigt</h2>
                    <p>Die E-Mail-Adresse wurde bereits bestätigt. Du kannst dich nun einloggen.</p>
                    <p><a href="' . wp_login_url() . '" class="button">Zum Login</a></p>
                </div>';
            } else {
                $verification_request_time = strtotime($user->created_at);
                $expiry_time = 24 * 60 * 60; // 24 Stunden
                $current_time = time();
                $remaining_time = $expiry_time - ($current_time - $verification_request_time);

                // Überprüfen, ob der Verifizierungscode noch gültig ist
                if ($remaining_time > 0) {
                    $verified = verify_user_email($user_id, $verification_code);

                    if ($verified) {
                        echo '<div class="yprint-message success">
                            <h2>E-Mail-Adresse bestätigt!</h2>
                            <p>Die E-Mail-Adresse wurde erfolgreich bestätigt! Du wirst nun zum Login weitergeleitet.</p>
                        </div>';
                        echo '<script>
                            setTimeout(function() {
                                window.location.href = "' . home_url('/login/') . '";
                            }, 3000);
                        </script>';
                    } else {
                        echo '<div class="yprint-message error">
                            <h2>Verifizierung fehlgeschlagen</h2>
                            <p>Es gab ein Problem bei der Verifizierung des Codes.</p>
                            <p><a href="' . home_url('/login/') . '" class="button">Zum Login</a></p>
                        </div>';
                    }
                } else {
                    echo '<div class="yprint-message error">
                        <h2>Verifizierungscode abgelaufen</h2>
                        <p>Der Verifizierungscode ist abgelaufen. Bitte fordere einen neuen an.</p>
                        <p><a href="' . home_url('/login/') . '" class="button">Zum Login</a></p>
                    </div>';
                }
            }
        } else {
            echo '<div class="yprint-message error">
                <h2>Ungültiger Verifizierungscode</h2>
                <p>Der Verifizierungscode ist ungültig oder abgelaufen.</p>
                <p><a href="' . home_url('/login/') . '" class="button">Zum Login</a></p>
            </div>';
        }
    } else {
        echo '<div class="yprint-message info">
            <h2>Verifizierung nicht möglich</h2>
            <p>Kein Verifizierungscode gefunden. Bitte überprüfe deinen E-Mail-Link.</p>
            <p><a href="' . home_url('/login/') . '" class="button">Zum Login</a></p>
        </div>';
    }

    return ob_get_clean();
}
add_shortcode('verify_email', 'verify_email_shortcode');

/**
 * Mobile registration form shortcode
 */
function yprint_registration_form_mobile() {
    return '<form id="register-form-mobile" method="post" style="display: flex; flex-direction: column; align-items: center; background-color: #fff; border-radius: 25px; padding: 20px; width: 300px; box-sizing: border-box; border: 1px solid #000; position: relative;">
        <div style="display: flex; flex-direction: column; align-items: center; font-family: \'Roboto\', sans-serif; font-weight: bold; margin-bottom: 16px;">
            <!-- Username Feld -->
            <label style="font-size: 14px; margin-bottom: 4px; color: #000; text-align: center;">Username</label>
            <div style="width: 240px; height: 36px; display: flex; align-items: center; border: 1px solid #000; border-radius: 18px; padding: 0 12px; background-color: #f9f9f9; box-sizing: border-box;">
                <input type="text" name="username" id="user_login_mobile" placeholder="Enter your username" style="flex-grow: 1; text-align: center; border: none; outline: none; font-family: inherit; font-size: 14px; background: none; height: 100%; margin: 0;" required>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; font-family: \'Roboto\', sans-serif; font-weight: bold; margin-bottom: 16px;">
            <!-- Email Feld -->
            <label style="font-size: 14px; margin-bottom: 4px; color: #000; text-align: center;">Email</label>
            <div style="width: 240px; height: 36px; display: flex; align-items: center; border: 1px solid #000; border-radius: 18px; padding: 0 12px; background-color: #f9f9f9; box-sizing: border-box;">
                <input type="email" name="email" id="user_email_mobile" placeholder="Enter your email" style="flex-grow: 1; text-align: center; border: none; outline: none; font-family: inherit; font-size: 14px; background: none; height: 100%; margin: 0;" required>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; font-family: \'Roboto\', sans-serif; font-weight: bold; margin-bottom: 16px;">
            <!-- Password Feld -->
            <label style="font-size: 14px; margin-bottom: 4px; color: #000; text-align: center;">Password</label>
            <div style="width: 240px; height: 36px; display: flex; align-items: center; border: 1px solid #000; border-radius: 18px; padding: 0 12px; background-color: #f9f9f9; box-sizing: border-box;">
                <input type="password" name="password" id="user_password_mobile" placeholder="Enter your password" style="flex-grow: 1; text-align: center; border: none; outline: none; font-family: inherit; font-size: 14px; background: none; height: 100%; margin: 0;" required>
                <span id="password-toggle-mobile" style="cursor: pointer; padding-left: 5px;">
                    <i class="eicon-eye" style="font-size: 16px; color: #666;"></i>
                </span>
            </div>
            <!-- Passwortanforderungen -->
            <div id="password-requirements" style="margin-top: 8px; font-size: 12px; color: red;">
                <ul>
                    <li id="length" style="color: red;">Mindestens 8 Zeichen</li>
                    <li id="uppercase" style="color: red;">Mindestens ein Großbuchstabe</li>
                    <li id="number" style="color: red;">Mindestens eine Zahl</li>
                    <li id="special" style="color: red;">Mindestens ein Sonderzeichen</li>
                </ul>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; align-items: center; font-family: \'Roboto\', sans-serif; font-weight: bold; margin-bottom: 16px;">
            <!-- Repeat Password Feld -->
            <label style="font-size: 14px; margin-bottom: 4px; color: #000; text-align: center;">Repeat Password</label>
            <div style="width: 240px; height: 36px; display: flex; align-items: center; border: 1px solid #000; border-radius: 18px; padding: 0 12px; background-color: #f9f9f9; box-sizing: border-box;">
                <input type="password" name="password_confirm" id="user_password_confirm_mobile" placeholder="Repeat your password" style="flex-grow: 1; text-align: center; border: none; outline: none; font-family: inherit; font-size: 14px; background: none; height: 100%; margin: 0;" required>
                <span id="confirm-password-toggle-mobile" style="cursor: pointer; padding-left: 5px;">
                    <i class="eicon-eye" style="font-size: 16px; color: #666;"></i>
                </span>
            </div>
        </div>

        <!-- Datenschutz-Checkbox -->
        <div style="display: flex; margin-bottom: 25px; width: 240px; font-family: \'Roboto\', sans-serif; font-size: 13px; align-items: flex-start;">
            <input type="checkbox" id="datenschutz_akzeptiert" name="datenschutz_akzeptiert" style="margin-right: 10px; margin-top: 4px;" required>
            <label for="datenschutz_akzeptiert" style="font-weight: normal;">
                Ich habe die <a href="https://yprint.de/datenschutz/" target="_blank" style="color: #0079FF; font-weight: bold; text-decoration: none;">Datenschutzerklärung</a> gelesen und akzeptiere diese.
            </label>
        </div>

        <!-- Fehlermeldung für Datenschutz -->
        <div id="datenschutz-error" style="color: red; font-size: 12px; margin-bottom: 10px; display: none; font-family: \'Roboto\', sans-serif;">
            Bitte akzeptiere die Datenschutzerklärung, um fortzufahren.
        </div>

        <!-- Der Submit-Button wird innerhalb des Formulars, aber visuell außerhalb des Containers positioniert -->
        <div style="position: absolute; bottom: -75px; width: 100%; display: flex; justify-content: center;">
            <button type="submit" id="register-button-mobile" style="width: 155px; height: 35px; font-family: \'Roboto\', sans-serif; font-size: 20px; font-weight: bold; color: #FFFFFF; background-color: #0079FF; border: 1px solid #707070; border-radius: 0; cursor: pointer; text-transform: lowercase; line-height: 1px;">
                Register
            </button>
        </div>
    </form>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Passwort-Validierung
            document.getElementById("user_password_mobile").addEventListener("input", function() {
                var password = this.value;
                var requirements = {
                    length: password.length >= 8,
                    uppercase: /[A-Z]/.test(password),
                    number: /\d/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };

                // Überprüfe jede Anforderung
                if (requirements.length) {
                    document.getElementById("length").style.display = "none";
                } else {
                    document.getElementById("length").style.display = "block";
                    document.getElementById("length").style.color = "red";
                }

                if (requirements.uppercase) {
                    document.getElementById("uppercase").style.display = "none";
                } else {
                    document.getElementById("uppercase").style.display = "block";
                    document.getElementById("uppercase").style.color = "red";
                }

                if (requirements.number) {
                    document.getElementById("number").style.display = "none";
                } else {
                    document.getElementById("number").style.display = "block";
                    document.getElementById("number").style.color = "red";
                }

                if (requirements.special) {
                    document.getElementById("special").style.display = "none";
                } else {
                    document.getElementById("special").style.display = "block";
                    document.getElementById("special").style.color = "red";
                }
            });

            // Formular-Validierung inklusive Datenschutz-Checkbox
            document.getElementById("register-form-mobile").addEventListener("submit", function(event) {
                var datenschutzCheckbox = document.getElementById("datenschutz_akzeptiert");
                var datenschutzError = document.getElementById("datenschutz-error");
                
                if (!datenschutzCheckbox.checked) {
                    event.preventDefault();
                    datenschutzError.style.display = "block";
                    return false;
                } else {
                    datenschutzError.style.display = "none";
                }
                
                // Hier kannst du weitere Validierungen hinzufügen wenn nötig
            });
            
            // Vereinfachte und robustere Password Toggle Funktionalität
            document.getElementById("password-toggle-mobile").onclick = function() {
                var field = document.getElementById("user_password_mobile");
                
                // Toggle zwischen Passwort und Text
                field.type = (field.type === "password") ? "text" : "password";
                
                // Verhindern, dass andere Event-Handler ausgelöst werden
                return false;
            };
            
            document.getElementById("confirm-password-toggle-mobile").onclick = function() {
                var field = document.getElementById("user_password_confirm_mobile");
                
                // Toggle zwischen Passwort und Text
                field.type = (field.type === "password") ? "text" : "password";
                
                // Verhindern, dass andere Event-Handler ausgelöst werden
                return false;
            };
        });
    </script>';
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