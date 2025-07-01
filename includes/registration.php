<?php
/**
 * Registration functions for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the custom registration form shortcode
 */
function yprint_custom_registration_form() {
    ob_start();
    ?>
    <style>
/* Cloudflare Turnstile Responsive Styling - Universal für Login & Registration */
.turnstile-widget-container {
    text-align: center !important;
    margin: 20px 0 !important;
    width: 100% !important;
    max-width: 100% !important;
    overflow: hidden !important;
    box-sizing: border-box !important;
}

.yprint-input-group.turnstile-widget-container {
    display: flex !important;
    justify-content: center !important;
    align-items: center !important;
    padding: 0 !important;
    margin: 24px 0 !important;
}

.cf-turnstile,
.cf-turnstile-rendered {
    margin: 0 auto !important;
    max-width: 100% !important;
    width: auto !important;
    transform-origin: center !important;
}

/* Container für Login und Registration Formulare */
#yprint-loginform .turnstile-widget-container,
#register-form .turnstile-widget-container,
#register-form-mobile .turnstile-widget-container {
    max-width: 500px !important;
    margin: 20px auto !important;
}

/* Turnstile Widgets in Login und Registration */
#yprint-loginform .cf-turnstile,
#yprint-loginform .cf-turnstile-rendered,
#register-form .cf-turnstile,
#register-form .cf-turnstile-rendered,
#register-form-mobile .cf-turnstile,
#register-form-mobile .cf-turnstile-rendered {
    transform: scale(1) !important;
    max-width: 100% !important;
}

.cf-turnstile iframe,
.cf-turnstile-rendered iframe {
    max-width: 100% !important;
    height: auto !important;
}

/* Container Overflow für alle Form-Cards */
.yprint-login-card,
.yprint-register-card,
.yprint-mobile-register-card {
    overflow: visible !important;
}

@media screen and (max-width: 500px) {
    .turnstile-widget-container {
        margin: 16px 0 !important;
        padding: 0 10px !important;
    }
    
    .cf-turnstile,
    .cf-turnstile-rendered {
        transform: scale(0.85) !important;
        transform-origin: center center !important;
        max-width: 90% !important;
    }
    
    /* Spezifische Skalierung für alle Formulare */
    #yprint-loginform .cf-turnstile,
    #yprint-loginform .cf-turnstile-rendered,
    #register-form .cf-turnstile,
    #register-form .cf-turnstile-rendered,
    #register-form-mobile .cf-turnstile,
    #register-form-mobile .cf-turnstile-rendered {
        transform: scale(0.8) !important;
    }
}

@media screen and (max-width: 400px) {
    .cf-turnstile,
    .cf-turnstile-rendered {
        transform: scale(0.75) !important;
    }
    
    /* Kleinere Skalierung für alle Formulare */
    #yprint-loginform .cf-turnstile,
    #yprint-loginform .cf-turnstile-rendered,
    #register-form .cf-turnstile,
    #register-form .cf-turnstile-rendered,
    #register-form-mobile .cf-turnstile,
    #register-form-mobile .cf-turnstile-rendered {
        transform: scale(0.7) !important;
    }
    
    .turnstile-widget-container {
        padding: 0 5px !important;
    }
}

@media screen and (max-width: 320px) {
    .cf-turnstile,
    .cf-turnstile-rendered {
        transform: scale(0.65) !important;
    }
    
    /* Kleinste Skalierung für alle Formulare */
    #yprint-loginform .cf-turnstile,
    #yprint-loginform .cf-turnstile-rendered,
    #register-form .cf-turnstile,
    #register-form .cf-turnstile-rendered,
    #register-form-mobile .cf-turnstile,
    #register-form-mobile .cf-turnstile-rendered {
        transform: scale(0.6) !important;
    }
}
        
        /* Moderner Register Container - identisch mit Login */
        .yprint-register-container {
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', 'Roboto', 'Helvetica Neue', Arial, sans-serif;
            padding: 40px 20px;
            box-sizing: border-box;
        }

        .yprint-register-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            border: 1px solid #e5e7eb;
            padding: 40px;
            width: 100%;
            max-width: 420px;
            position: relative;
        }

        .yprint-register-header {
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

        .yprint-register-title {
            font-size: 26px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 8px 0;
        }

        .yprint-register-subtitle {
            font-size: 15px;
            color: #6b7280;
            margin: 0;
        }

        /* Login Button */
        .yprint-login-section {
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
        }

        .yprint-login-text {
            font-size: 14px;
            color: #6b7280;
            margin: 0 0 16px 0;
        }

        .yprint-login-button {
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

        .yprint-login-button:hover {
            background-color: #f1f5f9;
            border-color: #3b82f6;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.15);
            color: #2563eb !important;
            text-decoration: none;
        }

        .yprint-login-button:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        /* Input Field Container */
        .yprint-input-group {
            margin-bottom: 24px;
            position: relative;
        }

        /* Eingabefelder - identisch mit Login */
        #register-form input[type="text"],
        #register-form input[type="email"],
        #register-form input[type="password"] {
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

        #register-form input[type="text"]:focus,
        #register-form input[type="email"]:focus,
        #register-form input[type="password"]:focus {
            background-color: #ffffff !important;
            border-color: #3b82f6 !important;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1) !important;
        }

        /* Platzhaltertext */
        #register-form input::placeholder {
            color: #9ca3af !important;
            font-weight: 400 !important;
        }

        /* Submit-Button - identisch mit Login */
        #register-form input[type="submit"] {
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

        #register-form input[type="submit"]:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%) !important;
            transform: translateY(-1px) !important;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4) !important;
        }

        #register-form input[type="submit"]:active {
            transform: translateY(0) !important;
        }

        /* Hint-Styling - moderner Stil */
        .yprint-input-hint {
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

        .yprint-input-hint:after {
            content: '';
            position: absolute;
            bottom: -6px;
            left: 50%;
            transform: translateX(-50%);
            border-left: 6px solid transparent;
            border-right: 6px solid transparent;
            border-top: 6px solid #fde68a;
        }

        .yprint-input-hint.success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border-color: #10b981;
            color: #065f46;
        }

        .yprint-input-hint.success:after {
            border-top-color: #a7f3d0;
        }

        .yprint-input-hint.error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border-color: #ef4444;
            color: #991b1b;
        }

        .yprint-input-hint.error:after {
            border-top-color: #fecaca;
        }

        /* Success/Error Messages */
        #registration-message {
            margin-top: 20px;
            padding: 16px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            text-align: center;
            display: none;
        }

        #registration-message.success {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            border: 1px solid #10b981;
            color: #065f46;
        }

        #registration-message.error {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            border: 1px solid #ef4444;
            color: #991b1b;
        }

        /* Password Container für Eye-Toggle */
        .password-container {
            position: relative;
            width: 100%;
        }

        /* Augen-Icon für Passwort-Toggle */
        .eye-toggle {
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

        .eye-toggle:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .eye-toggle i {
            font-size: 18px;
            color: #6b7280;
            transition: color 0.2s ease;
        }

        .eye-toggle:hover i {
            color: #3b82f6;
        }

        /* Mobile Responsive */
        @media screen and (max-width: 480px) {
            .yprint-register-container {
                padding: 20px 16px;
            }

            .yprint-register-card {
                padding: 24px;
                border-radius: 16px;
                box-shadow: 0 4px 16px rgba(0, 0, 0, 0.1);
            }

            .yprint-logo img {
                width: 40px;
                height: 40px;
            }

            .yprint-register-title {
                font-size: 22px;
            }

            .yprint-register-subtitle {
                font-size: 14px;
            }

            .yprint-login-button {
                height: 44px;
                font-size: 14px;
            }

            #register-form input[type="text"],
            #register-form input[type="email"],
            #register-form input[type="password"],
            #register-form input[type="submit"] {
                height: 48px !important;
                font-size: 16px !important;
            }

            .yprint-input-hint {
                font-size: 12px;
                padding: 10px 14px;
                white-space: normal;
                max-width: 95%;
            }
        }

        @media screen and (max-width: 320px) {
            .yprint-register-card {
                padding: 20px;
            }

            .yprint-login-button {
                height: 40px;
                font-size: 13px;
                padding: 10px 20px;
            }

            #register-form input[type="text"],
            #register-form input[type="email"],
            #register-form input[type="password"],
            #register-form input[type="submit"] {
                height: 44px !important;
                padding: 12px 16px !important;
            }
        }
    </style>

    <div class="yprint-register-container">
        <div class="yprint-register-card">
            <div class="yprint-register-header">
                <div class="yprint-logo">
                    <img src="https://yprint.de/wp-content/uploads/2024/10/y-icon.svg" alt="YPrint Logo" />
                </div>
                <h1 class="yprint-register-title">Konto erstellen</h1>
                <p class="yprint-register-subtitle">Registriere dich bei YPrint</p>
            </div>

            <div class="yprint-register-form">
                <form method="post" id="register-form">
                    <div class="yprint-input-group">
                        <input type="text" name="user_login" id="user_login" placeholder="Benutzername" required>
                        <div id="username-hint" class="yprint-input-hint">
                            Benutzername muss mindestens 3 Zeichen lang sein.
                        </div>
                    </div>

                    <div class="yprint-input-group">
                        <input type="email" name="user_email" id="user_email" placeholder="E-Mail-Adresse" required>
                        <div id="email-hint" class="yprint-input-hint">
                            E-Mail muss von einem bekannten Anbieter sein (z.B. gmail.com, yahoo.com).
                        </div>
                    </div>

                    <div class="yprint-input-group password-container">
                        <input type="password" name="user_password" id="user_password" placeholder="Passwort" required>
                        <button type="button" class="eye-toggle" id="eye-toggle-1" aria-label="Passwort anzeigen/verstecken">
                            <i class="eicon-eye"></i>
                        </button>
                        <div id="password-hint" class="yprint-input-hint">
                            Passwort muss mindestens 8 Zeichen lang sein, einen Großbuchstaben und ein Sonderzeichen enthalten.
                        </div>
                    </div>

                    <div class="yprint-input-group password-container">
                        <input type="password" name="user_password_confirm" id="user_password_confirm" placeholder="Passwort wiederholen" required>
                        <button type="button" class="eye-toggle" id="eye-toggle-2" aria-label="Passwort anzeigen/verstecken">
                            <i class="eicon-eye"></i>
                        </button>
                        <div id="confirm-password-hint" class="yprint-input-hint">
                            Passwörter müssen übereinstimmen.
                        </div>
                    </div>

                    <div class="yprint-input-group">
                        <input type="submit" name="wp-submit" value="Registrieren">
                    </div>
                </form>

                <div id="registration-message"></div>
            </div>

            <div class="yprint-login-section">
                <p class="yprint-login-text">Bereits ein Konto?</p>
                <a href="https://yprint.de/login/" class="yprint-login-button">
                    Jetzt anmelden
                </a>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password Toggle Funktionalität
        function setupPasswordToggle(toggleId, inputId) {
            const toggle = document.getElementById(toggleId);
            const input = document.getElementById(inputId);
            
            if (toggle && input) {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        toggle.querySelector('i').style.color = '#3b82f6';
                    } else {
                        input.type = 'password';
                        toggle.querySelector('i').style.color = '#6b7280';
                    }
                });
            }
        }

        // Setup für beide Passwort-Felder
        setupPasswordToggle('eye-toggle-1', 'user_password');
        setupPasswordToggle('eye-toggle-2', 'user_password_confirm');

        // Validation Hints
        const usernameField = document.getElementById('user_login');
        const emailField = document.getElementById('user_email');
        const passwordField = document.getElementById('user_password');
        const confirmPasswordField = document.getElementById('user_password_confirm');

        const usernameHint = document.getElementById('username-hint');
        const emailHint = document.getElementById('email-hint');
        const passwordHint = document.getElementById('password-hint');
        const confirmPasswordHint = document.getElementById('confirm-password-hint');

        // Username Validation
        if (usernameField && usernameHint) {
            usernameField.addEventListener('input', function() {
                if (this.value.length > 0 && this.value.length < 3) {
                    usernameHint.className = 'yprint-input-hint error';
                    usernameHint.style.display = 'block';
                } else if (this.value.length >= 3) {
                    usernameHint.className = 'yprint-input-hint success';
                    usernameHint.innerHTML = 'Benutzername ist gültig.';
                    usernameHint.style.display = 'block';
                    setTimeout(() => { usernameHint.style.display = 'none'; }, 2000);
                } else {
                    usernameHint.style.display = 'none';
                }
            });
        }

        // Email Validation
        if (emailField && emailHint) {
            const validProviders = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'gmx.de', 'web.de', 't-online.de'];
            
            emailField.addEventListener('input', function() {
                const email = this.value;
                if (email.includes('@')) {
                    const domain = email.split('@')[1];
                    if (validProviders.includes(domain)) {
                        emailHint.className = 'yprint-input-hint success';
                        emailHint.innerHTML = 'E-Mail-Anbieter ist gültig.';
                        emailHint.style.display = 'block';
                        setTimeout(() => { emailHint.style.display = 'none'; }, 2000);
                    } else if (domain) {
                        emailHint.className = 'yprint-input-hint error';
                        emailHint.style.display = 'block';
                    }
                } else if (email.length > 0) {
                    emailHint.className = 'yprint-input-hint error';
                    emailHint.innerHTML = 'Bitte geben Sie eine gültige E-Mail-Adresse ein.';
                    emailHint.style.display = 'block';
                } else {
                    emailHint.style.display = 'none';
                }
            });
        }

        // Password Validation
        if (passwordField && passwordHint) {
            passwordField.addEventListener('input', function() {
                const password = this.value;
                const hasLength = password.length >= 8;
                const hasUpper = /[A-Z]/.test(password);
                const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);

                if (password.length > 0) {
                    if (hasLength && hasUpper && hasSpecial) {
                        passwordHint.className = 'yprint-input-hint success';
                        passwordHint.innerHTML = 'Passwort ist sicher.';
                        passwordHint.style.display = 'block';
                        setTimeout(() => { passwordHint.style.display = 'none'; }, 2000);
                    } else {
                        passwordHint.className = 'yprint-input-hint error';
                        passwordHint.style.display = 'block';
                    }
                } else {
                    passwordHint.style.display = 'none';
                }
            });
        }

        // Confirm Password Validation
        if (confirmPasswordField && confirmPasswordHint) {
            confirmPasswordField.addEventListener('input', function() {
                const password = passwordField.value;
                const confirmPassword = this.value;

                if (confirmPassword.length > 0) {
                    if (password === confirmPassword) {
                        confirmPasswordHint.className = 'yprint-input-hint success';
                        confirmPasswordHint.innerHTML = 'Passwörter stimmen überein.';
                        confirmPasswordHint.style.display = 'block';
                        setTimeout(() => { confirmPasswordHint.style.display = 'none'; }, 2000);
                    } else {
                        confirmPasswordHint.className = 'yprint-input-hint error';
                        confirmPasswordHint.style.display = 'block';
                    }
                } else {
                    confirmPasswordHint.style.display = 'none';
                }
            });
        }

        // AJAX Form Submission (bestehende Funktionalität beibehalten)
        const form = document.getElementById('register-form');
        const messageDiv = document.getElementById('registration-message');

        if (form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();

                const formData = new FormData();
                formData.append('action', 'yprint_register_user');
                formData.append('username', usernameField.value);
                formData.append('email', emailField.value);
                formData.append('password', passwordField.value);
                formData.append('password_confirm', confirmPasswordField.value);
                
                // WordPress nonce für Sicherheit
                if (typeof ajax_object !== 'undefined') {
                    formData.append('nonce', ajax_object.nonce);
                }

                // Submit Button deaktivieren
                const submitButton = form.querySelector('input[type="submit"]');
                const originalValue = submitButton.value;
                submitButton.value = 'Registriere...';
                submitButton.disabled = true;

                fetch(ajax_object.ajax_url || '/wp-admin/admin-ajax.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        messageDiv.className = 'success';
                        messageDiv.innerHTML = data.data.message;
                        messageDiv.style.display = 'block';
                        form.reset();
                    } else {
                        messageDiv.className = 'error';
                        messageDiv.innerHTML = data.data.message;
                        messageDiv.style.display = 'block';
                    }
                })
                .catch(error => {
                    messageDiv.className = 'error';
                    messageDiv.innerHTML = 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.';
                    messageDiv.style.display = 'block';
                })
                .finally(() => {
                    submitButton.value = originalValue;
                    submitButton.disabled = false;
                });
            });
        }
    });
    </script>

    <?php
    return ob_get_clean();
}
add_shortcode('yprint_registration_form', 'yprint_custom_registration_form');

/**
 * Handle AJAX registration
 */
function yprint_register_user_callback() {
    // Check nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'yprint-ajax-nonce')) {
        wp_send_json_error(array('message' => 'Security check failed.'));
    }

    // Turnstile Bot-Schutz Validierung
    $turnstile = YPrint_Turnstile::get_instance();
    if ($turnstile->is_enabled() && in_array('registration', $turnstile->get_protected_pages())) {
        if (!$turnstile->has_valid_turnstile_token()) {
            wp_send_json_error(array('message' => 'Bot-Verifikation fehlgeschlagen. Bitte versuchen Sie es erneut.'));
        }
    }

    $username = sanitize_text_field($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];

    // Email validation
    if (!is_email($email)) {
        wp_send_json_error(array('message' => 'Invalid email address.'));
    }

    // Check if username or email already exists
    if (username_exists($username) || email_exists($email)) {
        wp_send_json_error(array('message' => 'Username or email already registered.'));
    }

    // Create user
    $user_id = wp_create_user($username, $password, $email);

    // Error handling
    if (is_wp_error($user_id)) {
        wp_send_json_error(array('message' => $user_id->get_error_message()));
    } else {
        // User created successfully, send confirmation email

        // Generate verification code
        $verification_code = md5(time() . $email);
        
        // Set user metadata
        update_user_meta($user_id, 'email_verification_code', $verification_code);
        update_user_meta($user_id, 'email_verified', false); // Email not verified by default

        // Verification link
        $verification_link = site_url("/verify-email?code=$verification_code");

        $subject = 'Please verify your email address';
        
        // Get email content from email functions
        $message_content = "Thank you for registering with YPrint. To complete your registration, please verify your email address by clicking the button below.<br><br>";
        $message_content .= "<a href='" . esc_url($verification_link) . "' style='display: inline-block; background-color: #007aff; padding: 15px 30px; color: #ffffff; text-decoration: none; font-size: 16px; border-radius: 5px;'>Verify Email</a><br><br>";
        $message_content .= "If you did not create this account, please ignore this email.";

        // Get email template - this function should be in email.php
        $message = yprint_get_email_template('Please verify your email address', esc_html($username), $message_content);

        // Email headers
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: YPrint <no-reply@yprint.de>',
            'Reply-To: no-reply@yprint.de'
        );

        // Send email
        if (wp_mail($email, $subject, $message, $headers)) {
            wp_send_json_success(array('message' => 'Registration successful! Please check your email to verify your account.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to send the verification email.'));
        }
    }
}
add_action('wp_ajax_nopriv_yprint_register_user', 'yprint_register_user_callback');
add_action('wp_ajax_yprint_register_user', 'yprint_register_user_callback');

/**
 * Handle email verification
 */
function yprint_verify_email() {
    if (isset($_GET['code'])) {
        $verification_code = sanitize_text_field($_GET['code']);
        
        // Find user with this verification code
        $users = get_users(array(
            'meta_key' => 'email_verification_code',
            'meta_value' => $verification_code,
            'number' => 1,
        ));
        
        if (!empty($users)) {
            $user = $users[0];
            
            // Mark email as verified
            update_user_meta($user->ID, 'email_verified', true);
            
            // Optional: Remove the verification code
            delete_user_meta($user->ID, 'email_verification_code');
            
            // Redirect to login page with success message
            wp_redirect(wp_login_url() . '?verified=true');
            exit;
        }
    }
    
    // If verification fails, redirect to homepage with error
    wp_redirect(home_url('?verified=false'));
    exit;
}
add_action('template_redirect', 'yprint_verify_email_redirect');

function yprint_verify_email_redirect() {
    if (is_page('verify-email') && isset($_GET['code'])) {
        yprint_verify_email();
    }
}