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

// TEMPORÄRES DEBUGGING
add_action('init', function() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yprint_login'])) {
        error_log('=== YPRINT LOGIN DEBUG ===');
        error_log('POST data: ' . print_r($_POST, true));
        error_log('REQUEST_URI: ' . $_SERVER['REQUEST_URI']);
        error_log('Current page: ' . (function_exists('is_page') && is_page('login') ? 'LOGIN PAGE' : 'OTHER PAGE'));
    }
});

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
    
    // TEMPORÄRES DEBUGGING: POST-Daten als console.log im Browser ausgeben
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yprint_login'])) {
        echo '<script>console.log("=== YPRINT LOGIN DEBUG ===");';
        echo 'console.log("POST data:", ' . json_encode($_POST) . ');';
        echo 'console.log("REQUEST_URI:", ' . json_encode($_SERVER['REQUEST_URI']) . ');';
        echo 'console.log("Current page:", ' . (function_exists('is_page') && is_page('login') ? '\'LOGIN PAGE\'' : '\'OTHER PAGE\'') . ');';
        echo '</script>';
    }
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

        /* Cloudflare Turnstile Responsive Styling */
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

        #yprint-loginform .turnstile-widget-container {
            max-width: 500px !important;
            margin: 20px auto !important;
        }

        #yprint-loginform .cf-turnstile,
        #yprint-loginform .cf-turnstile-rendered {
            transform: scale(1) !important;
            max-width: 100% !important;
        }

        .cf-turnstile iframe,
        .cf-turnstile-rendered iframe {
            max-width: 100% !important;
            height: auto !important;
        }

        .yprint-login-card {
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
            
            #yprint-loginform .cf-turnstile,
            #yprint-loginform .cf-turnstile-rendered {
                transform: scale(0.8) !important;
            }
        }

        @media screen and (max-width: 400px) {
            .cf-turnstile,
            .cf-turnstile-rendered {
                transform: scale(0.75) !important;
            }
            
            #yprint-loginform .cf-turnstile,
            #yprint-loginform .cf-turnstile-rendered {
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
            
            #yprint-loginform .cf-turnstile,
            #yprint-loginform .cf-turnstile-rendered {
                transform: scale(0.6) !important;
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
                    
                    <?php
                    // Turnstile Widget direkt einbinden - mit Duplikats-Schutz
                    if (class_exists('YPrint_Turnstile')) {
                        $turnstile = YPrint_Turnstile::get_instance();
                        if ($turnstile->is_enabled() && in_array('login', $turnstile->get_protected_pages())) {
                            echo '<div class="yprint-input-group turnstile-widget-container" style="text-align: center !important; margin: 20px 0 !important;">';
                            echo '<div class="cf-turnstile" data-sitekey="' . esc_attr($turnstile->get_site_key()) . '" data-theme="light" data-callback="onTurnstileSuccess" data-error-callback="onTurnstileError"></div>';
                            // KEIN manuelles Hidden Field mehr!
                            echo '</div>';
                            echo $turnstile->get_turnstile_js();
                        }
                    }
                    ?>
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
        console.log('🔍 LOGIN DEBUG: DOMContentLoaded fired');
        
        var usernameField = document.getElementById('user_login');
        var passwordField = document.getElementById('user_pass');
        var emailHint = document.getElementById('email-hint');
        var eyeToggle = document.getElementById('eye-toggle');
        var loginForm = document.getElementById('yprint-loginform');
        
        console.log('🔍 LOGIN DEBUG: Form found:', !!loginForm);
        console.log('🔍 LOGIN DEBUG: Turnstile containers:', document.querySelectorAll('.cf-turnstile').length);
        console.log('🔍 LOGIN DEBUG: Hidden token fields:', document.querySelectorAll('input[name="cf-turnstile-response"]').length);
        
        // WIDGET-URSPRUNG ANALYSE
        var containers = document.querySelectorAll('.cf-turnstile');
        containers.forEach(function(container, index) {
            console.log('🔍 LOGIN DEBUG: Widget', index + 1 + ':', {
                'parent': container.parentElement ? container.parentElement.className : 'no parent',
                'closest form': container.closest('form') ? container.closest('form').id : 'no form',
                'innerHTML': container.innerHTML.substring(0, 50),
                'data-attributes': Array.from(container.attributes).map(attr => attr.name + '=' + attr.value)
            });
        });
        
        // TOKEN-FELDER-URSPRUNG ANALYSE
        var tokenFields = document.querySelectorAll('input[name="cf-turnstile-response"]');
        tokenFields.forEach(function(field, index) {
            console.log('🔍 LOGIN DEBUG: Token Field', index + 1 + ':', {
                'value': field.value,
                'parent': field.parentElement ? field.parentElement.className : 'no parent',
                'closest form': field.closest('form') ? field.closest('form').id : 'no form',
                'outerHTML': field.outerHTML
            });
        });
        
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

        // Form-Submit abfangen für Turnstile-Prüfung
        if (loginForm) {
            console.log('🔍 LOGIN DEBUG: Registriere Form-Submit Event-Listener');
            
            loginForm.addEventListener('submit', function(e) {
                console.log('🔍 LOGIN DEBUG: Form-Submit Event gefeuert!');
                
                var turnstileToken = document.querySelector('input[name="cf-turnstile-response"]');
                // FIXED: Suche nach beiden Klassen - original und gerendert
                var turnstileContainers = document.querySelectorAll('.cf-turnstile, .cf-turnstile-rendered');
                
                console.log('Form Submit - Turnstile Container gefunden:', turnstileContainers.length);
                console.log('Form Submit - Turnstile Token Feld gefunden:', !!turnstileToken);
                
                if (turnstileToken) {
                    console.log('Form Submit - Turnstile Token Wert:', turnstileToken.value);
                    console.log('Form Submit - Token Länge:', turnstileToken.value.length);
                }
                
                // Prüfe ob Turnstile aktiv ist und Token fehlt
                if (turnstileContainers.length > 0 && turnstileToken && (!turnstileToken.value || turnstileToken.value.length < 10)) {
                    e.preventDefault();
                    console.error('Form Submit blockiert - Turnstile Token fehlt oder ungültig');
                    
                    // Zeige Benutzer-Feedback
                    var errorDiv = document.querySelector('.turnstile-error') || document.createElement('div');
                    errorDiv.className = 'turnstile-error';
                    errorDiv.style.cssText = 'color: #dc3232; margin: 10px 0; font-size: 14px; text-align: center;';
                    errorDiv.textContent = 'Bitte warte einen Moment, bis die Bot-Verifikation abgeschlossen ist.';
                    
                    if (!document.querySelector('.turnstile-error')) {
                        turnstileContainers[0].parentNode.insertBefore(errorDiv, turnstileContainers[0].nextSibling);
                    }
                    
                    return false;
                }
                
                console.log('🔍 LOGIN DEBUG: Form Submit - alle Prüfungen bestanden, sende Formular');
            });
            
            console.log('🔍 LOGIN DEBUG: Event-Listener erfolgreich registriert');
        } else {
            console.error('🔍 LOGIN DEBUG: FEHLER - Login-Formular nicht gefunden!');
        }

        // TEST-LOGIC: Login-Button überschreibt Submit und loggt alle Felder
        // (diesen Block entfernen, damit der Login wieder funktioniert)
    });
    </script>
    <?php
    return ob_get_clean();
}
add_shortcode('yprint_login_form', 'yprint_login_form_shortcode');

/**
 * Login-Verarbeitung auf Login-Seite
 */
function yprint_process_custom_login() {
    // Nur auf Login-Seite ausführen
    if (!is_page('login') && strpos($_SERVER['REQUEST_URI'], '/login') === false) {
        return;
    }
    // Nur bei POST-Requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        return;
    }
    // Nur bei unserem Login-Formular
    if (!isset($_POST['yprint_login']) || $_POST['yprint_login'] !== '1') {
        return;
    }
    // CONSOLE-DEBUGGING für tatsächlichen POST-Request
    ?>
    <script>
    console.log('🔍 SERVER DEBUG: POST-Request verarbeitet!');
    console.log('🔍 SERVER DEBUG: POST Keys:', <?php echo json_encode(array_keys($_POST)); ?>);
    console.log('🔍 SERVER DEBUG: Token present:', <?php echo (isset($_POST['cf-turnstile-response']) ? 'true' : 'false'); ?>);
    <?php if (isset($_POST['cf-turnstile-response'])): ?>
    console.log('🔍 SERVER DEBUG: Token length:', <?php echo strlen($_POST['cf-turnstile-response']); ?>);
    <?php endif; ?>
    </script>
    <?php
    // Turnstile-Verifikation wenn aktiviert
    if (class_exists('YPrint_Turnstile')) {
        $turnstile = YPrint_Turnstile::get_instance();
        if ($turnstile->is_enabled() && in_array('login', $turnstile->get_protected_pages())) {
            $token = sanitize_text_field($_POST['cf-turnstile-response'] ?? '');
            echo '<script>console.log("🔍 SERVER DEBUG: Turnstile Verifikation startet");</script>';
            if (empty($token)) {
                echo '<script>console.log("🔍 SERVER DEBUG: TOKEN FEHLT - Redirect!");</script>';
                wp_redirect(home_url('/login/?login=turnstile_missing&timestamp=' . time()));
                exit;
            }
            $verification = $turnstile->verify_token($token);
            echo '<script>console.log("🔍 SERVER DEBUG: Nach verify_token, Verification-Array: ' . json_encode($verification) . '");</script>';
            echo '<script>console.log("🔍 SERVER DEBUG: Vor User-Authentifizierung");</script>';
            if (!$verification['success']) {
                echo '<script>console.log("🔍 SERVER DEBUG: Verifikation fehlgeschlagen! Redirect.");</script>';
                wp_redirect(home_url('/login/?login=turnstile_failed&timestamp=' . time()));
                exit;
            }
        }
    }
    echo '<script>console.log("🔍 SERVER DEBUG: Turnstile erfolgreich, fahre mit User-Authentifizierung fort");</script>';
    $username = isset($_POST['log']) ? sanitize_text_field($_POST['log']) : '';
    $password = isset($_POST['pwd']) ? $_POST['pwd'] : '';
    $redirect_to = isset($_POST['redirect_to']) ? $_POST['redirect_to'] : home_url('/dashboard/');
    echo '<script>console.log("🔍 SERVER DEBUG: Username: ' . $username . ', Password: ' . (empty($password) ? 'leer' : 'gesetzt') . '");</script>';
    // Leere Felder prüfen
    if (empty($username) || empty($password)) {
        echo '<script>console.log("🔍 SERVER DEBUG: Leere Felder erkannt, Redirect.");</script>';
        error_log('YPrint Custom Login: Empty fields detected');
        wp_redirect(home_url('/login/?login=empty&timestamp=' . time()));
        exit;
    }
    echo '<script>console.log("🔍 SERVER DEBUG: Authentifiziere User: ' . $username . '");</script>';
    $user = wp_authenticate($username, $password);
    echo '<script>console.log("🔍 SERVER DEBUG: Nach wp_authenticate, User-Objekt: ' . (is_wp_error($user) ? 'WP_Error' : (is_object($user) ? 'User-ID: ' . $user->ID : 'NULL')) . '");</script>';
    if (is_wp_error($user)) {
        echo '<script>console.log("🔍 SERVER DEBUG: Authentifizierung fehlgeschlagen, Redirect.");</script>';
        error_log('YPrint Custom Login: Authentication failed for user: ' . $username);
        wp_redirect(home_url('/login/?login=failed&timestamp=' . time()));
        exit;
    }
    echo '<script>console.log("🔍 SERVER DEBUG: User-Objekt erhalten, ID: ' . ($user ? $user->ID : 'NULL') . '");</script>';
    // E-Mail-Verifikation prüfen
    global $wpdb;
    $table_name = 'wp_email_verifications';
    $user_id = $user->ID;
    $email_verified = $wpdb->get_var(
        $wpdb->prepare("SELECT email_verified FROM $table_name WHERE user_id = %d", $user_id)
    );
    echo '<script>console.log("🔍 SERVER DEBUG: E-Mail-Verifikation Status: ' . ($email_verified === null ? 'kein Eintrag' : $email_verified) . '");</script>';
    if ($email_verified !== null && $email_verified != 1) {
        echo '<script>console.log("🔍 SERVER DEBUG: E-Mail nicht verifiziert, Redirect.");</script>';
        error_log('YPrint Custom Login: Email not verified for user: ' . $username);
        wp_redirect(home_url('/login/?login=email_not_verified&user_id=' . $user_id . '&timestamp=' . time()));
        exit;
    }
    echo '<script>console.log("🔍 SERVER DEBUG: Login erfolgreich, setze Auth-Cookie und leite weiter");</script>';
    error_log('YPrint Custom Login: Login successful for user: ' . $username);
    wp_set_current_user($user_id, $username);
    wp_set_auth_cookie($user_id);
    do_action('wp_login', $username, $user);
    echo '<script>console.log("🔍 SERVER DEBUG: Redirect zu: ' . $redirect_to . '");</script>';
    wp_redirect($redirect_to);
    exit;
}
add_action('template_redirect', 'yprint_process_custom_login', 1);

/**
 * WordPress Standard-Login abfangen und umleiten
 */
function yprint_intercept_wp_login() {
    // Nur bei POST-Requests auf wp-login.php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log']) && isset($_POST['pwd']) && !isset($_POST['yprint_login'])) {
        // Wenn es nicht unser Custom-Login ist, zur Custom-Login-Seite umleiten
        if (strpos($_SERVER['REQUEST_URI'], 'wp-login.php') !== false || strpos($_SERVER['REQUEST_URI'], 'wp-admin') !== false) {
            $username = sanitize_text_field($_POST['log']);
            $password = $_POST['pwd'];
            
            // Prüfe Credentials
            $user = wp_authenticate($username, $password);
            
            if (is_wp_error($user)) {
                wp_redirect(home_url('/login/?login=failed&timestamp=' . time()));
                exit;
            }
        }
    }
}
add_action('init', 'yprint_intercept_wp_login', 1);

/**
 * Backup: wp_login_failed Hook für alle anderen Login-Versuche
 */
function yprint_login_failed_backup($username) {
    if (!isset($_POST['yprint_login'])) {
        wp_redirect(home_url('/login/?login=failed&timestamp=' . time()));
        exit;
    }
}
add_action('wp_login_failed', 'yprint_login_failed_backup');

/**
 * Handler für das Resenden der Verifikations-E-Mail
 */
function yprint_handle_resend_verification() {
    // Nur auf Login-Seite ausführen
    if (!is_page('login') && strpos($_SERVER['REQUEST_URI'], '/login') === false) {
        return;
    }
    
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
            
            wp_redirect(home_url('/login/?verification_sent=1&timestamp=' . time()));
            exit;
        }
    }
}
add_action('template_redirect', 'yprint_handle_resend_verification', 2);

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
    ?>
    <script type="text/javascript">
        window.YPrintToast = {
            container: null,
            toastCounter: 0,

            init: function() {
                this.container = document.getElementById("yprint-toast-container");
                if (!this.container) return;
                
                // Zeige alle gesammelten Notifications
                this.showQueuedNotifications();
            },

            show: function(type, title, message, duration, actions) {
                if (!this.container) return;
                duration = duration || 5000;
                actions = actions || [];

                var toastId = "toast-" + (++this.toastCounter);
                var iconMap = {
                    error: "⚠️",
                    success: "✅", 
                    warning: "⚠️",
                    info: "ℹ️"
                };

                var actionsHtml = "";
                if (actions.length > 0) {
                    actionsHtml = '<div class="yprint-toast-actions">';
                    for (var i = 0; i < actions.length; i++) {
                        var action = actions[i];
                        if (action.type === "link") {
                            actionsHtml += '<a href="' + action.url + '" class="yprint-toast-action ' + (action.primary ? "primary" : "") + '">' + action.label + '</a>';
                        } else if (action.type === "form") {
                            actionsHtml += '<form method="post" action="' + action.action + '" style="display: inline;">' + action.fields + '<button type="submit" class="yprint-toast-action ' + (action.primary ? "primary" : "") + '">' + action.label + '</button></form>';
                        }
                    }
                    actionsHtml += '</div>';
                }

                var progressHtml = duration > 0 ? '<div class="yprint-toast-progress" style="width: 100%"></div>' : "";
                
                var toastHtml = '<div id="' + toastId + '" class="yprint-toast ' + type + '">' +
                    '<div class="yprint-toast-icon">' + (iconMap[type] || "ℹ️") + '</div>' +
                    '<div class="yprint-toast-content">' +
                        '<div class="yprint-toast-title">' + title + '</div>' +
                        '<div class="yprint-toast-message">' + message + '</div>' +
                        actionsHtml +
                    '</div>' +
                    '<button class="yprint-toast-close" onclick="YPrintToast.close(\'' + toastId + '\')">&times;</button>' +
                    progressHtml +
                '</div>';

                this.container.insertAdjacentHTML("beforeend", toastHtml);
                var toast = document.getElementById(toastId);
                
                // Animation starten
                setTimeout(function() {
                    toast.classList.add("show");
                }, 100);

                // Auto-close mit Progress Bar
                if (duration > 0) {
                    var progressBar = toast.querySelector(".yprint-toast-progress");
                    if (progressBar) {
                        progressBar.style.width = "0%";
                        progressBar.style.transitionDuration = duration + "ms";
                    }
                    
                    setTimeout(function() {
                        YPrintToast.close(toastId);
                    }, duration);
                }
            },

            close: function(toastId) {
                var toast = document.getElementById(toastId);
                if (toast) {
                    toast.style.transform = "translateX(100%)";
                    toast.style.opacity = "0";
                    setTimeout(function() {
                        if (toast.parentNode) {
                            toast.parentNode.removeChild(toast);
                        }
                    }, 400);
                }
            },

            showQueuedNotifications: function() {
                <?php
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
                            'fields' => addslashes($nonce_field) . '<input type="hidden" name="resend_verification" value="' . esc_attr($user_id) . '">',
                            'label' => '✉️ E-Mail erneut senden',
                            'primary' => false
                        );
                    }

                    $actions_json = json_encode($actions, JSON_HEX_QUOT | JSON_HEX_APOS);
                    $delay = $index * 200; // Stagger die Notifications

                    echo "
                setTimeout(function() {
                    YPrintToast.show(
                        '" . esc_js($notification['type']) . "',
                        '" . esc_js($notification['title']) . "',
                        '" . esc_js($notification['message']) . "',
                        " . intval($notification['duration']) . ",
                        " . $actions_json . "
                    );
                }, $delay);";
                }
                ?>
            }
        };

        // Toast-System initialisieren
        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", function() {
                YPrintToast.init();
            });
        } else {
            YPrintToast.init();
        }
        
        // Debug: Zeige alle URL-Parameter
        console.log("YPrint Login Debug:");
        console.log("URL Parameters:", new URLSearchParams(window.location.search));
        console.log("Current URL:", window.location.href);
        
        // Test-Toast für Debugging
        setTimeout(function() {
            if (window.YPrintToast && new URLSearchParams(window.location.search).get("debug") === "1") {
                window.YPrintToast.show("info", "Debug Toast", "Toast-System funktioniert!", 3000);
            }
        }, 1000);
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('yprint_login_feedback', 'yprint_login_feedback_shortcode');