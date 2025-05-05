<?php
/**
 * Password Recovery functions for YPrint
 *
 * @package YPrint
 * @since 1.1.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * YPrint Password Recovery Class
 * 
 * Handles all password recovery functionality including:
 * - Token generation and validation
 * - Request processing
 * - Password reset
 * - Security features (rate limiting, sanitization)
 */
class YPrint_Password_Recovery {
    
    /**
 * Initialize hooks and actions
 */
public function __construct() {
    // Override default lost password URL
    add_filter('lostpassword_url', array($this, 'custom_lostpassword_url'), 10, 2);
    
    // Handle password recovery request step
    add_action('init', array($this, 'handle_recovery_request'));
    
    // Handle password reset step
    add_action('init', array($this, 'handle_password_reset'));
    
    // Add AJAX actions
    add_action('wp_ajax_nopriv_yprint_recover_account', array($this, 'ajax_process_recovery_request'));
    add_action('wp_ajax_yprint_recover_account', array($this, 'ajax_process_recovery_request'));
    add_action('wp_ajax_nopriv_yprint_reset_password', array($this, 'ajax_process_password_reset'));
    add_action('wp_ajax_yprint_reset_password', array($this, 'ajax_process_password_reset'));
    
    // Register dynamic pages
    add_action('init', array($this, 'register_recovery_pages'));
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    
    // Hinzufügen eines Logs zur vereinfachten Sicherheit
    error_log("YPrint: Initialized Password Recovery with simplified security model");
}

    private function send_username_hint_email($user) {
        $to = $user->user_email;
        $subject = 'Account Recovery Information';
        
        if (function_exists('yprint_get_email_template')) {
            $headline = 'Information zu deiner Kontowiederherstellung';
            $username = $user->user_login;
            
            $message_content = "Wir haben bemerkt, dass du möglicherweise deine E-Mail-Adresse und deinen Benutzernamen verwechselt hast.<br><br>";
            $message_content .= "Dein Benutzername lautet: <strong>" . esc_html($username) . "</strong><br><br>";
            $message_content .= "Falls du dein Passwort zurücksetzen möchtest, klicke bitte auf den Button unten:<br><br>";
            
            // Generate token for password reset
            $token = $this->generate_token();
            $reset_url = home_url("/recover-account/reset/{$username}/{$token}/");
            
            // Keine Tokenspeicherung in Datenbank mehr
            
            $message_content .= "<a href='" . esc_url($reset_url) . "' style='display: inline-block; background-color: #007aff; padding: 15px 30px; color: #ffffff; text-decoration: none; font-size: 16px; border-radius: 5px;'>Passwort zurücksetzen</a><br><br>";
            $message_content .= "Falls du dein Passwort kennst, kannst du einfach <a href='" . esc_url(home_url('/login/')) . "'>hier</a> mit deinem Benutzernamen und deinem Passwort anmelden.<br><br>";
            $message_content .= "Falls du diese Anfrage nicht gestellt hast, kannst du diese E-Mail ignorieren.";
            
            $message = yprint_get_email_template($headline, $username, $message_content);
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: YPrint <do-not-reply@yprint.de>'
            );
            
            wp_mail($to, $subject, $message, $headers);
        }
    }
    
    /**
     * Redirect WordPress's default lost password to our custom page
     */
    public function custom_lostpassword_url($lostpassword_url, $redirect) {
        return home_url('/recover-account/');
    }
    
    /**
     * Register rewrite rules for dynamic password recovery pages
     */
    public function register_recovery_pages() {
        // Main recovery page (already exists in most cases)
        if (!get_page_by_path('recover-account')) {
            wp_insert_post(array(
                'post_title' => 'Account Recovery',
                'post_name' => 'recover-account',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed'
            ));
        }
        
        // Success page
        if (!get_page_by_path('password-reset-success')) {
            wp_insert_post(array(
                'post_title' => 'Password Reset Successful',
                'post_name' => 'password-reset-success',
                'post_status' => 'publish',
                'post_type' => 'page',
                'comment_status' => 'closed'
            ));
        }
        
        // Add rewrite rule for reset password page with token
add_rewrite_rule(
    '^recover-account/reset/([^/]+)/([^/]+)/?$',
    'index.php?pagename=recover-account&action=reset&login=$matches[1]&key=$matches[2]',
    'top'
);

// Force flush rewrite rules to ensure our custom rules are added
flush_rewrite_rules();
        
        // Add query vars
        add_filter('query_vars', function($query_vars) {
            $query_vars[] = 'action';
            $query_vars[] = 'login';
            $query_vars[] = 'key';
            return $query_vars;
        });
        
        // Register template filter for custom pages
        add_filter('template_include', array($this, 'recovery_page_template'));
        
        // Flush rewrite rules only when necessary
        if (get_option('yprint_recovery_flush_rules', false)) {
            flush_rewrite_rules();
            update_option('yprint_recovery_flush_rules', false);
        }
    }
    
    public function recovery_page_template($template) {
        global $wp_query;
        
        // Check if we're on the recover-account page
        if (is_page('recover-account')) {
            $action = get_query_var('action', '');
            
            // Debug information
            error_log("YPrint: On recover-account page. Action: " . $action);
            error_log("YPrint: Query vars: " . print_r($wp_query->query_vars, true));
            
            // Check if we're processing a reset
            if ($action === 'reset') {
                $login = get_query_var('login', '');
                $key = get_query_var('key', '');
                
                error_log("YPrint: Reset action with login: $login and key: $key");
                
                // Display password reset form
                add_filter('the_content', array($this, 'display_reset_form'));
            } else {
                // Check URL parameters for direct access (compatibility)
                $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
                if (strpos($path, '/recover-account/reset/') === 0) {
                    // Extract login and key from URL
                    $parts = explode('/', trim($path, '/'));
                    // Format should be: recover-account/reset/username/token/
                    if (count($parts) >= 4) {
                        $login = $parts[2];
                        $key = $parts[3];
                        
                        error_log("YPrint: Direct URL access with login: $login and key: $key");
                        
                        // Setup query vars manually
                        $wp_query->set('action', 'reset');
                        $wp_query->set('login', $login);
                        $wp_query->set('key', $key);
                        
                        // Display password reset form
                        add_filter('the_content', array($this, 'display_reset_form'));
                        return $template;
                    }
                }
                
                // Display request form (default)
                add_filter('the_content', array($this, 'display_request_form'));
            }
        }
        
        // Check if we're on the success page
        if (is_page('password-reset-success')) {
            add_filter('the_content', array($this, 'display_success_page'));
        }
        
        return $template;
    }
    
/**
 * Generate the request form HTML
 */
public function display_request_form($content) {
    ob_start();
    ?>
    <style>
        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh;
            padding: 20px;
        }
        
        .yprint-recover-container {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            background-color: #ffffff;
            border: 1px solid #d3d3d3;
            border-radius: 10px;
            box-shadow: none;
        }
        
        .yprint-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .yprint-logo div {
            width: 200px;
            height: 100px;
            background-image: url("https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
        
        .yprint-form-group {
            position: relative;
            margin-bottom: 20px;
        }
        
        .yprint-form-group input[type="email"] {
            width: 100%;
            padding: 10px 10px 10px 35px;
            border: 1px solid #ddd;
            border-radius: 30px;
            background-color: #F6F7FA;
            text-align: center;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        .yprint-form-group input[type="submit"] {
            width: 100%;
            padding: 10px;
            background-color: #007aff;
            border: none;
            color: #fff;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            box-sizing: border-box;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        #loading .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #007aff;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @media (max-width: 480px) {
            .yprint-recover-container {
                padding: 20px;
                width: 90%;
            }
        }
    </style>
    
    <div class="page-wrapper">
        <div class="yprint-recover-container">
            <div class="yprint-logo">
                <div></div>
            </div>
            
            <form method="post" id="recover-form" style="text-align: center;">
            <div class="yprint-form-group">
    <span class="dashicons dashicons-email" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #999;"></span>
    <input type="email" name="user_email" id="user_email" class="input" placeholder="Email oder Benutzername" required>
</div>
<div id="email-hint" style="font-size: 12px; margin-top: 5px; text-align: center; color: #999;">
    Gib deine E-Mail-Adresse ein. Falls du dich mit deinem Benutzernamen anmeldest, kannst du auch diesen eingeben.
</div>
                <div class="yprint-form-group">
                    <input type="submit" name="wp-submit" value="Recover Account" class="button button-primary">
                </div>
                <div class="yprint-links" style="text-align: center;">
                    <a href="<?php echo esc_url(home_url('/login/')); ?>" style="color: #007aff;">Back to Login</a>
                </div>
            </form>

            <div id="loading" style="display: none; text-align: center;">
                <div class="spinner"></div>
                <p style="color: #007aff;">Processing...</p>
            </div>

            <div id="success-message" style="display: none; text-align: center; color: #007aff; margin-top: 20px;">
                <p>If an account exists with that email, you will receive recovery instructions.</p>
                <button id="back-to-login" class="button button-primary" style="background-color: #007aff; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Back to Login</button>
            </div>
        </div>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            var recoverForm = document.getElementById('recover-form');
            if (!recoverForm) return;
            
            recoverForm.addEventListener('submit', function(event) {
    event.preventDefault();
    recoverForm.style.display = 'none';
    document.getElementById('loading').style.display = 'block';

    var email = document.getElementById('user_email').value;
    
    console.log("Sending recovery request without security token");
    
    jQuery.ajax({
        type: 'POST',
        url: '<?php echo admin_url('admin-ajax.php'); ?>',
        data: {
            action: 'yprint_recover_account',
            user_email: email
        },
                    success: function(response) {
                        document.getElementById('loading').style.display = 'none';
                        
                        if (response.success) {
                            document.getElementById('success-message').style.display = 'block';
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'An error occurred. Please try again.');
                            recoverForm.style.display = 'block';
                        }
                    },
                    error: function() {
                        document.getElementById('loading').style.display = 'none';
                        recoverForm.style.display = 'block';
                        alert('An error occurred. Please try again later.');
                    }
                });
            });

            var backToLoginBtn = document.getElementById('back-to-login');
            if (backToLoginBtn) {
                backToLoginBtn.addEventListener('click', function() {
                    window.location.href = '<?php echo esc_url(home_url('/login/')); ?>';
                });
            }
        });
    </script>
    <?php
    
    return ob_get_clean();
}

public function display_reset_form($content) {
    $login = get_query_var('login', '');
    $key = get_query_var('key', '');
    
    // Verify token before showing form
    $token_valid = $this->verify_token($login, $key);
    
    ob_start();
    ?>
    <style>
        .page-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh;
            padding: 20px;
        }
        
        .yprint-recover-container {
            width: 100%;
            max-width: 400px;
            padding: 30px;
            background-color: #ffffff;
            border: 1px solid #d3d3d3;
            border-radius: 10px;
            box-shadow: none;
        }
        
        .yprint-logo {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .yprint-logo div {
            width: 200px;
            height: 100px;
            background-image: url("https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg");
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }
        
        @media (max-width: 480px) {
            .yprint-recover-container {
                padding: 20px;
                width: 90%;
            }
        }
    </style>
    <div class="page-wrapper">
        <div class="yprint-recover-container">
            <div class="yprint-logo">
                <div></div>
            </div>
            
            <?php if ($token_valid): ?>
                <form method="post" id="reset-form" style="text-align: center;">
                    <input type="hidden" name="login" value="<?php echo esc_attr($login); ?>">
                    <input type="hidden" name="key" value="<?php echo esc_attr($key); ?>">
                    
                    <h3 style="margin-bottom: 20px;">Reset Your Password</h3>
                    
                    <div class="yprint-form-group" style="position: relative; margin-bottom: 20px;">
                        <span class="dashicons dashicons-lock" style="position: absolute; left: 10px; top: 40%; transform: translateY(-50%); color: #999;"></span>
                        <input type="password" name="password" id="password" class="input" placeholder="New Password" required style="width: 100%; padding: 10px 10px 10px 35px; border: 1px solid #ddd; border-radius: 30px; background-color: #F6F7FA; text-align: center;">
                        <div id="password-strength" style="font-size: 12px; margin-top: 5px; text-align: left; color: #999;">
                            Password must be at least 8 characters long and include a capital letter and a special character.
                        </div>
                    </div>
                    
                    <div class="yprint-form-group" style="position: relative; margin-bottom: 20px;">
                        <span class="dashicons dashicons-lock" style="position: absolute; left: 10px; top: 40%; transform: translateY(-50%); color: #999;"></span>
                        <input type="password" name="confirm_password" id="confirm_password" class="input" placeholder="Confirm Password" required style="width: 100%; padding: 10px 10px 10px 35px; border: 1px solid #ddd; border-radius: 30px; background-color: #F6F7FA; text-align: center;">
                        <div id="password-match" style="font-size: 12px; margin-top: 5px; text-align: left; color: #999;">
                            Passwords must match.
                        </div>
                    </div>
                    
                    <div class="yprint-form-group" style="text-align: center; margin-bottom: 20px;">
                        <input type="submit" name="wp-submit" value="Reset Password" class="button button-primary" style="width: 100%; padding: 10px; background-color: #007aff; border: none; color: #fff; border-radius: 5px;">
                    </div>
                </form>
                
                <!-- Loading animation -->
                <div id="loading" style="display: none; text-align: center;">
                    <div class="spinner" style="border: 4px solid #f3f3f3; border-top: 4px solid #007aff; border-radius: 50%; width: 30px; height: 30px; animation: spin 1s linear infinite; margin: 20px auto;"></div>
                    <p style="color: #007aff;">Processing...</p>
                </div>
                
                <!-- Success message -->
                <div id="success-message" style="display: none; text-align: center; color: #007aff; margin-top: 20px;">
                    <p>Your password has been reset successfully!</p>
                    <p>You'll be redirected to the login page shortly...</p>
                </div>
                
                <script type="text/javascript">
    document.addEventListener('DOMContentLoaded', function() {
        // Password validation
        var passwordField = document.getElementById('password');
        if (passwordField) {
            passwordField.addEventListener('input', function() {
                var password = this.value;
                var strengthDiv = document.getElementById('password-strength');
                
                if (password.length >= 8 && /[A-Z]/.test(password) && /[\W_]/.test(password)) {
                    strengthDiv.style.color = '#28a745';
                    strengthDiv.textContent = 'Password meets requirements.';
                } else {
                    strengthDiv.style.color = '#dc3545';
                    strengthDiv.textContent = 'Password must be at least 8 characters long and include a capital letter and a special character.';
                }
            });
        }
        
        // Password matching
        var confirmField = document.getElementById('confirm_password');
        if (confirmField) {
            confirmField.addEventListener('input', function() {
                var confirmPassword = this.value;
                var password = document.getElementById('password').value;
                var matchDiv = document.getElementById('password-match');
                
                if (confirmPassword === password) {
                    matchDiv.style.color = '#28a745';
                    matchDiv.textContent = 'Passwords match.';
                } else {
                    matchDiv.style.color = '#dc3545';
                    matchDiv.textContent = 'Passwords do not match.';
                }
            });
        }
        

// Form submission
var resetForm = document.getElementById('reset-form');
if (resetForm) {
    resetForm.addEventListener('submit', function(event) {
        event.preventDefault();
        
        var password = document.getElementById('password').value;
        var confirmPassword = document.getElementById('confirm_password').value;
        var login = document.querySelector('input[name="login"]').value;
        var key = document.querySelector('input[name="key"]').value;
        
        // Validate password
        if (password.length < 8 || !/[A-Z]/.test(password) || !/[\W_]/.test(password)) {
            alert('Your password does not meet the required criteria.');
            return false;
        }
        
        // Validate password match
        if (password !== confirmPassword) {
            alert('Passwords do not match.');
            return false;
        }
        
        // Show loading animation
        document.getElementById('reset-form').style.display = 'none';
        document.getElementById('loading').style.display = 'block';
        
        console.log("Sending password reset request without security token");
        
        // Send AJAX request
        jQuery.ajax({
            type: 'POST',
            url: '<?php echo admin_url('admin-ajax.php'); ?>',
            data: {
                action: 'yprint_reset_password',
                login: login,
                key: key,
                password: password
            },
                    success: function(response) {
                        document.getElementById('loading').style.display = 'none';
                        if (response.success) {
                            document.getElementById('success-message').style.display = 'block';
                            // Log the successful response for debugging
                            console.log("Password reset success:", response);
                            setTimeout(function() {
                                window.location.href = '<?php echo esc_url(home_url('/password-reset-success/')); ?>';
                            }, 3000);
                        } else {
                            var errorMsg = response.data && response.data.message ? response.data.message : 'An unknown error occurred.';
                            alert(errorMsg);
                            console.error("Password reset error:", response);
                            document.getElementById('reset-form').style.display = 'block';
                        }
                    },
                    error: function(xhr, status, error) {
                        document.getElementById('loading').style.display = 'none';
                        console.error("AJAX error:", status, error);
                        console.error("Response text:", xhr.responseText);
                        alert('A connection error occurred. Please try again later.');
                        document.getElementById('reset-form').style.display = 'block';
                    }
                });
            });
        }
    });
</script>
            <?php else: ?>
                <div style="text-align: center; color: #dc3545;">
                    <p>The password reset link has expired or is invalid.</p>
                    <p>Please request a new password reset link.</p>
                    <a href="<?php echo esc_url(home_url('/recover-account/')); ?>" class="button button-primary" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #007aff; color: #fff; border: none; border-radius: 5px; text-decoration: none;">Request New Link</a>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    public function display_success_page($content) {
        ob_start();
        ?>
        <style>
            .page-wrapper {
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 70vh;
                padding: 20px;
            }
            
            .yprint-recover-container {
                width: 100%;
                max-width: 400px;
                padding: 30px;
                background-color: #ffffff;
                border: 1px solid #d3d3d3;
                border-radius: 10px;
                box-shadow: none;
            }
            
            .yprint-logo {
                display: flex;
                justify-content: center;
                align-items: center;
                margin-bottom: 30px;
            }
            
            .yprint-logo div {
                width: 200px;
                height: 100px;
                background-image: url("https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg");
                background-size: contain;
                background-repeat: no-repeat;
                background-position: center;
            }
            
            @media (max-width: 480px) {
                .yprint-recover-container {
                    padding: 20px;
                    width: 90%;
                }
            }
        </style>
        <div class="page-wrapper">
            <div class="yprint-recover-container">
                <div class="yprint-logo">
                    <div></div>
                </div>
                
                <div style="text-align: center; color: #28a745;">
                    <h2>Password Reset Successful!</h2>
                    <p>Your password has been reset successfully.</p>
                    <p>You can now log in with your new password.</p>
                    <a href="<?php echo esc_url(home_url('/login/')); ?>" class="button button-primary" style="display: inline-block; margin-top: 20px; padding: 10px 20px; background-color: #007aff; color: #fff; border: none; border-radius: 5px; text-decoration: none;">Go to Login</a>
                </div>
            </div>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    public function enqueue_scripts() {
        // Only load on recovery pages
        if (is_page('recover-account') || is_page('password-reset-success')) {
            // Add spin animation for loading spinner
            wp_add_inline_style('yprint-styles', '
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .page-wrapper {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    min-height: 70vh;
                    padding: 20px;
                }
                
                .yprint-recover-container {
                    width: 100%;
                    max-width: 400px;
                    padding: 30px;
                    background-color: #ffffff;
                    border: 1px solid #d3d3d3;
                    border-radius: 10px;
                    box-shadow: none;
                }
                
                .yprint-logo {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    margin-bottom: 30px;
                }
                
                .yprint-logo div {
                    width: 200px;
                    height: 100px;
                    background-image: url("https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg");
                    background-size: contain;
                    background-repeat: no-repeat;
                    background-position: center;
                }
                
                @media (max-width: 480px) {
                    .yprint-recover-container {
                        padding: 20px;
                        width: 90%;
                    }
                }
            ');
            
            // Enqueue Dashicons
            wp_enqueue_style('dashicons');
        }
    }
    
    public function handle_recovery_request() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'yprint_recover_account') {
            // KEINE SICHERHEITSÜBERPRÜFUNG MEHR
            error_log("YPrint: POST recovery request received - security check completely bypassed");
            $this->process_recovery_request();
        }
    }
    
    public function ajax_process_recovery_request() {    
        // KEINE SICHERHEITSÜBERPRÜFUNG MEHR
        error_log("YPrint: Recovery request received - security check completely bypassed");
        
        try {
            $this->process_recovery_request();
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'An unexpected error occurred. Please try again later.'
            ));
        }
    }
    
    /**
     * Core recovery request processing logic
     */
    public function process_recovery_request() {
        global $wpdb;
        
        // Check rate limiting
        $ip_address = $this->get_client_ip();
        $rate_key = 'yprint_recovery_rate_' . md5($ip_address);
        $rate_count = get_transient($rate_key);
        
        if ($rate_count !== false && $rate_count >= 5) {
            wp_send_json_error(array('message' => 'Too many requests. Please try again later.'));
        }
        
        // Parse input - could be email or username
$user_input = isset($_POST['user_email']) ? trim($_POST['user_email']) : '';
if (empty($user_input)) {
    wp_send_json_error(array('message' => 'Bitte gib deine E-Mail-Adresse oder deinen Benutzernamen ein.'));
}

// Determine if input is email or username
$is_email = is_email($user_input);
$user_email = $is_email ? sanitize_email($user_input) : '';
$username = !$is_email ? sanitize_user($user_input) : '';

// Find user by email or username
$user = $is_email ? get_user_by('email', $user_email) : get_user_by('login', $username);
        
        // Check if email exists but was entered as username
$potential_user = get_user_by('login', $user_email);
if (!$user && !$potential_user) {
    // Always return success to prevent user enumeration
    wp_send_json_success(array('message' => 'If an account exists with that email, you will receive recovery instructions.'));
} elseif (!$user && $potential_user) {
    // User entered their username instead of email - send a special email
    $this->send_username_hint_email($potential_user);
    
    // Return success to prevent user enumeration
    wp_send_json_success(array('message' => 'If an account exists with that email, you will receive recovery instructions.'));
}
        
// Generate token
$token = $this->generate_token();
$user_login = $user->user_login;
$user_id = $user->ID;

// Hash token for secure storage
$token_hash = wp_hash_password($token);

// Store token in database
global $wpdb;
$token_table = $wpdb->prefix . 'password_reset_tokens';

// First, clean up any existing tokens for this user
$wpdb->delete(
    $token_table,
    array('user_id' => $user_id),
    array('%d')
);

// Then insert the new token
$current_time = current_time('mysql');
$expires_at = date('Y-m-d H:i:s', strtotime('+1 hour', strtotime($current_time)));

$wpdb->insert(
    $token_table,
    array(
        'user_id' => $user_id,
        'token_hash' => $token_hash,
        'created_at' => $current_time,
        'expires_at' => $expires_at
    ),
    array('%d', '%s', '%s', '%s')
);

// Log token creation
error_log("YPrint DEBUG: Generated secure token for user: {$user_login} (ID: {$user_id})");
        
// Build reset URL
$reset_url = home_url("/recover-account/reset/{$user_login}/{$token}/");
        
        // Send email
        $this->send_recovery_email($user, $reset_url);
        
        // Update rate limiting
        if ($rate_count === false) {
            set_transient($rate_key, 1, HOUR_IN_SECONDS);
        } else {
            set_transient($rate_key, $rate_count + 1, HOUR_IN_SECONDS);
        }
        
        wp_send_json_success(array('message' => 'If an account exists with that email, you will receive recovery instructions.'));
    }
    
    public function handle_password_reset() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'yprint_reset_password') {
            // KEINE SICHERHEITSÜBERPRÜFUNG MEHR
            error_log("YPrint: POST password reset request received - security check completely bypassed");
            $this->process_password_reset();
        }
    }
    
    public function ajax_process_password_reset() {
        // KEINE SICHERHEITSÜBERPRÜFUNG MEHR
        error_log("YPrint: Password reset request received - security check completely bypassed");
        
        try {
            $this->process_password_reset();
        } catch (Exception $e) {
            error_log("YPrint: Exception in password reset: " . $e->getMessage());
            wp_send_json_error(array(
                'message' => 'An unexpected error occurred. Please try again later.'
            ));
        }
    }
    
    public function process_password_reset() {
        global $wpdb;
        
        // Parse and validate input
        $login = isset($_POST['login']) ? sanitize_user($_POST['login']) : '';
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        error_log("YPrint: Processing password reset for login: " . $login);
        
        if (empty($login) || empty($password) || empty($key)) {
            error_log("YPrint: Missing required fields in password reset");
            wp_send_json_error(array('message' => 'Missing required fields.'));
            return;
        }
        
        // Validate password
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[\W_]/', $password)) {
            error_log("YPrint: Password does not meet requirements");
            wp_send_json_error(array('message' => 'Password does not meet the requirements.'));
            return;
        }
        
        // Verify token
        $token_valid = $this->verify_token($login, $key);
        if (!$token_valid) {
            error_log("YPrint: Token verification failed for login: " . $login);
            wp_send_json_error(array('message' => 'Invalid or expired token. Please request a new password reset link.'));
            return;
        }
        
        // Get user
        $user = get_user_by('login', $login);
        if (!$user) {
            error_log("YPrint: User not found for login: " . $login);
            wp_send_json_error(array('message' => 'User not found.'));
            return;
        }
        
        error_log("YPrint: Resetting password for user ID: " . $user->ID);
        
        // Reset password - use wp_set_password directly for reliability
        wp_set_password($password, $user->ID);
        error_log("YPrint: Password updated for user ID: " . $user->ID);
    
        // Delete used token from database
        $token_table = $wpdb->prefix . 'password_reset_tokens';
        $delete_result = $wpdb->delete(
            $token_table,
            array('user_id' => $user->ID),
            array('%d')
        );
        error_log("YPrint: Password reset token deleted for user ID: " . $user->ID);
    
        // Send confirmation email
        $this->send_password_changed_email($user);
    
        error_log("YPrint: Password reset successful for user ID: " . $user->ID);
    
        // Detaillierte Erfolgsmeldung
        wp_send_json_success(array(
            'message' => 'Password reset successfully.',
            'user_id' => $user->ID,
            'token_deleted' => ($delete_result !== false)
        ));
    }
    
    /**
 * Generate a simple token (vereinfacht)
 */
private function generate_token($length = 32) {
    return wp_generate_password($length, false);
}

private function verify_token($login, $token) {
    if (empty($login) || empty($token)) {
        error_log("YPrint: Empty login or token in verify_token");
        return false;
    }
    
    // Get user
    $user = get_user_by('login', $login);
    if (!$user) {
        error_log("YPrint: User not found for login: " . $login);
        return false;
    }
    
    global $wpdb;
    $token_table = $wpdb->prefix . 'password_reset_tokens';
    $user_id = $user->ID;
    
    // Fetch token from database
    $stored_token = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM $token_table WHERE user_id = %d AND expires_at > %s ORDER BY created_at DESC LIMIT 1",
            $user_id,
            current_time('mysql')
        )
    );
    
    if (!$stored_token) {
        error_log("YPrint: No valid token found for user ID: {$user_id}");
        return false;
    }
    
    // We can't directly compare hashed tokens, so we'll need to use a timing-safe comparison
    // or a special WordPress function if available
    if (function_exists('wp_check_password')) {
        $is_valid = wp_check_password($token, $stored_token->token_hash);
    } else {
        // Fallback to a simple comparison (not recommended for production)
        $is_valid = ($token === $stored_token->token_hash);
    }
    
    if ($is_valid) {
        error_log("YPrint: Token verified successfully for user ID: {$user_id}");
        return true;
    } else {
        error_log("YPrint: Token verification failed for user ID: {$user_id}");
        return false;
    }
}
    
    /**
     * Send password recovery email
     */
    private function send_recovery_email($user, $reset_url) {
        $to = $user->user_email;
        $subject = 'Password Reset Request';
        
        if (function_exists('yprint_get_email_template')) {
            $headline = 'Password Reset Request';
            $username = $user->user_login;
            
            $message_content = "We received a request to reset your password. To reset your password, click the button below:<br><br>";
            $message_content .= "<a href='" . esc_url($reset_url) . "' style='display: inline-block; background-color: #007aff; padding: 15px 30px; color: #ffffff; text-decoration: none; font-size: 16px; border-radius: 5px;'>Reset Password</a><br><br>";
            $message_content .= "If you did not request a password reset, please ignore this email or contact us if you have questions.<br><br>";
            $message_content .= "This password reset link is only valid for 1 hour.";
            
            $message = yprint_get_email_template($headline, $username, $message_content);
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: YPrint <do-not-reply@yprint.de>'
            );
            
            wp_mail($to, $subject, $message, $headers);
        }
    }
    
    /**
     * Send password changed confirmation email
     */
    private function send_password_changed_email($user) {
        $to = $user->user_email;
        $subject = 'Password Changed Successfully';
        
        if (function_exists('yprint_get_email_template')) {
            $headline = 'Password Changed Successfully';
            $username = $user->user_login;
            
            $message_content = "Your password has been changed successfully.<br><br>";
            $message_content .= "If you did not make this change, please contact us immediately.";
            
            $message = yprint_get_email_template($headline, $username, $message_content);
            
            $headers = array(
                'Content-Type: text/html; charset=UTF-8',
                'From: YPrint <do-not-reply@yprint.de>'
            );
            
            $mail_sent = wp_mail($to, $subject, $message, $headers);
if (!$mail_sent) {
    error_log("Failed to send recovery email to: {$to}");
    // Überprüfen der wp_mail Fehler
    global $phpmailer;
    if (isset($GLOBALS['phpmailer'])) {
        $phpmailer = $GLOBALS['phpmailer'];
        if ($phpmailer->ErrorInfo != '') {
            error_log('PHPMailer error: ' . $phpmailer->ErrorInfo);
        }
    }
}
        }
    }
    
    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        
        return $ip;
    }
    
/**
 * Clean up expired tokens
 */
public static function cleanup_expired_tokens() {
    global $wpdb;
    $token_table = $wpdb->prefix . 'password_reset_tokens';
    
    // Delete all expired tokens
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM $token_table WHERE expires_at < %s",
            current_time('mysql')
        )
    );
    
    error_log("YPrint: Expired password reset tokens cleanup completed");
}
}

/**
 * Initialize the Password Recovery system
 */
function yprint_init_password_recovery() {
    // Create a new instance
    $password_recovery = new YPrint_Password_Recovery();
    
// Korrekter Plugin-Hauptdatei-Verweis für Activation/Deactivation Hooks
register_activation_hook(YPRINT_PLUGIN_DIR . 'yprint-plugin.php', 'yprint_recovery_activation');
register_deactivation_hook(YPRINT_PLUGIN_DIR . 'yprint-plugin.php', 'yprint_recovery_deactivation');
    
    // Register cron job for cleanup
    if (!wp_next_scheduled('yprint_cleanup_recovery_tokens')) {
        wp_schedule_event(time(), 'daily', 'yprint_cleanup_recovery_tokens');
    }
    
    // Add action for cron job
    add_action('yprint_cleanup_recovery_tokens', array('YPrint_Password_Recovery', 'cleanup_expired_tokens'));
}
add_action('init', 'yprint_init_password_recovery', 5);

/**
 * Vereinfachte Aktivierungsfunktion ohne Tabellenerstellung
 */
function yprint_recovery_activation() {
    // Nur Rewrite-Rules setzen, keine Tabelle mehr erstellen
    update_option('yprint_recovery_flush_rules', true);
}

/**
 * Deaktivierungs-Hook (unverändert)
 */
function yprint_recovery_deactivation() {
    // Clear scheduled event
    wp_clear_scheduled_hook('yprint_cleanup_recovery_tokens');
}
register_deactivation_hook(__FILE__, 'yprint_recovery_deactivation');