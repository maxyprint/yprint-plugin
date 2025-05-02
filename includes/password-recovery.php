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
            'recover-account/reset/([^/]+)/([^/]+)/?$',
            'index.php?pagename=recover-account&action=reset&login=$matches[1]&key=$matches[2]',
            'top'
        );
        
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
    
    /**
     * Display custom template based on URL
     */
    public function recovery_page_template($template) {
        global $wp_query;
        
        // Check if we're on the recover-account page
        if (is_page('recover-account')) {
            $action = get_query_var('action', '');
            
            // Check if we're processing a reset
            if ($action === 'reset') {
                // Display password reset form
                add_filter('the_content', array($this, 'display_reset_form'));
            } else {
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
        /* Zentrale Positionierung des Containers */
        .page-content {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 70vh; /* Anpassbare Höhe */
        }
        
        /* Container-Styling */
        .yprint-recover-container {
            width: 100%;
            max-width: 400px;
            margin: 0 auto;
            padding: 30px;
            background-color: #ffffff;
            border: 1px solid #d3d3d3;
            border-radius: 10px;
            box-shadow: none;
        }
        
        /* Logo-Styling */
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
        
        /* Formular-Styling */
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
        
        /* Ladeanimation */
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
        
        /* Responsive Anpassungen */
        @media (max-width: 480px) {
            .yprint-recover-container {
                padding: 20px;
                width: 90%;
            }
            
            .yprint-logo div {
                width: 150px;
                height: 75px;
            }
        }
    </style>
    
    <div class="yprint-recover-container">
        <div class="yprint-logo">
            <div></div>
        </div>
        
        <form method="post" id="recover-form" style="text-align: center;">
            <div class="yprint-form-group">
                <span class="dashicons dashicons-email" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%); color: #999;"></span>
                <input type="email" name="user_email" id="user_email" class="input" placeholder="Email" required>
            </div>
            <div class="yprint-form-group">
                <input type="submit" name="wp-submit" value="Recover Account" class="button button-primary">
            </div>
            <div class="yprint-links" style="text-align: center;">
                <a href="<?php echo esc_url(home_url('/login/')); ?>" style="color: #007aff;">Back to Login</a>
            </div>
        </form>

        <!-- Loading animation -->
        <div id="loading" style="display: none; text-align: center;">
            <div class="spinner"></div>
            <p style="color: #007aff;">Processing...</p>
        </div>

        <!-- Success message -->
        <div id="success-message" style="display: none; text-align: center; color: #007aff; margin-top: 20px;">
            <p>If an account exists with that email, you will receive recovery instructions.</p>
            <button id="back-to-login" class="button button-primary" style="background-color: #007aff; color: #fff; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">Back to Login</button>
        </div>
    </div>

    <script type="text/javascript">
        document.addEventListener('DOMContentLoaded', function() {
            // Prüfen, ob wir auf der richtigen Seite sind
            var recoverForm = document.getElementById('recover-form');
            if (!recoverForm) {
                return; // Nicht weiter ausführen, wenn das Formular nicht existiert
            }
            
            // Form-Submission
            recoverForm.addEventListener('submit', function(event) {
                event.preventDefault();

                // Show loading animation
                recoverForm.style.display = 'none';
                var loadingElem = document.getElementById('loading');
                if (loadingElem) loadingElem.style.display = 'block';

                var email = document.getElementById('user_email').value;
                
                // Send AJAX request
                jQuery.ajax({
                    type: 'POST',
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    data: {
                        action: 'yprint_recover_account',
                        user_email: email,
                        security: '<?php echo wp_create_nonce('yprint_recovery_nonce'); ?>'
                    },
                    success: function(response) {
                        if (loadingElem) loadingElem.style.display = 'none';
                        
                        if (response.success) {
                            var successElem = document.getElementById('success-message');
                            if (successElem) successElem.style.display = 'block';
                        } else {
                            alert(response.data && response.data.message ? response.data.message : 'An error occurred. Please try again.');
                            recoverForm.style.display = 'block';
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        if (loadingElem) loadingElem.style.display = 'none';
                        recoverForm.style.display = 'block';
                        alert('An error occurred. Please try again later.');
                    }
                });
            });

            // Back to login button
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

    /**
     * Generate the reset form HTML
     */
    public function display_reset_form($content) {
        $login = get_query_var('login', '');
        $key = get_query_var('key', '');
        
        // Verify token before showing form
        $token_valid = $this->verify_token($login, $key);
        
        ob_start();
        ?>
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
                    // Password validation
                    document.getElementById('password').addEventListener('input', function() {
                        var password = this.value;
                        var strength = document.getElementById('password-strength');
                        
                        if (password.length >= 8 && /[A-Z]/.test(password) && /[\W_]/.test(password)) {
                            strength.style.color = '#28a745';
                            strength.textContent = 'Password meets requirements.';
                        } else {
                            strength.style.color = '#dc3545';
                            strength.textContent = 'Password must be at least 8 characters long and include a capital letter and a special character.';
                        }
                    });
                    
                    // Password matching
                    document.getElementById('confirm_password').addEventListener('input', function() {
                        var password = document.getElementById('password').value;
                        var confirmPassword = this.value;
                        var match = document.getElementById('password-match');
                        
                        if (password === confirmPassword) {
                            match.style.color = '#28a745';
                            match.textContent = 'Passwords match.';
                        } else {
                            match.style.color = '#dc3545';
                            match.textContent = 'Passwords do not match.';
                        }
                    });
                    
                    // Form submission
                    document.getElementById('reset-form').addEventListener('submit', function(event) {
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
                        
                        // Send AJAX request
                        jQuery.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                            action: 'yprint_reset_password',
                            login: login,
                            key: key,
                            password: password,
                            security: '<?php echo wp_create_nonce('yprint_reset_nonce'); ?>'
                        }, function(response) {
                            document.getElementById('loading').style.display = 'none';
                            if (response.success) {
                                document.getElementById('success-message').style.display = 'block';
                                setTimeout(function() {
                                    window.location.href = '<?php echo esc_url(home_url('/login/')); ?>';
                                }, 3000);
                            } else {
                                alert(response.data.message);
                                document.getElementById('reset-form').style.display = 'block';
                            }
                        });
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
    
    /**
     * Generate the success page HTML
     */
    public function display_success_page($content) {
        ob_start();
        ?>
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
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Enqueue scripts and styles
     */
    public function enqueue_scripts() {
        // Only load on recovery pages
        if (is_page('recover-account') || is_page('password-reset-success')) {
            // Add spin animation for loading spinner
            wp_add_inline_style('yprint-styles', '
                @keyframes spin {
                    0% { transform: rotate(0deg); }
                    100% { transform: rotate(360deg); }
                }
                
                .yprint-recover-container {
                    width: 100%;
                    max-width: 420px;
                    margin: 0 auto;
                    padding: 40px;
                    background-color: #ffffff;
                    box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
                    border-radius: 10px;
                }
                
                .yprint-logo {
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    margin-bottom: 40px;
                }
                
                .yprint-logo div {
                    width: 100%;
                    max-width: 400px;
                    height: 200px;
                    background-image: url("https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg");
                    background-size: contain;
                    background-repeat: no-repeat;
                    background-position: center;
                }
                
                @media (max-width: 600px) {
                    .yprint-recover-container {
                        width: 90%;
                        padding: 20px;
                    }
                
                    .yprint-logo div {
                        height: 150px;
                    }
                }
            ');
            
            // Enqueue Dashicons
            wp_enqueue_style('dashicons');
        }
    }
    
    /**
     * Handle password recovery request processing
     */
    public function handle_recovery_request() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'yprint_recover_account') {
            // Verify nonce
            if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'yprint_recovery_nonce')) {
                wp_send_json_error(array('message' => 'Security check failed.'));
            }
            
            $this->process_recovery_request();
        }
    }
    
    public function ajax_process_recovery_request() {
        // Debug-Information
        error_log("ajax_process_recovery_request aufgerufen");
        error_log("POST-Daten: " . print_r($_POST, true));
        
        // Verify nonce
        if (!isset($_POST['security'])) {
            error_log("Fehler: Kein Security-Parameter gefunden");
            wp_send_json_error(array('message' => 'Security parameter fehlt.'));
            return;
        }
        
        if (!wp_verify_nonce($_POST['security'], 'yprint_recovery_nonce')) {
            error_log("Fehler: Nonce-Überprüfung fehlgeschlagen. Erhaltener Wert: " . $_POST['security']);
            wp_send_json_error(array('message' => 'Security check failed. Ungültiger Nonce.'));
            return;
        }
        
        try {
            error_log("Versuche process_recovery_request auszuführen");
            $this->process_recovery_request();
        } catch (Exception $e) {
            error_log("Exception in recovery process: " . $e->getMessage());
            error_log("Stacktrace: " . $e->getTraceAsString());
            wp_send_json_error(array(
                'message' => 'Ein unerwarteter Fehler ist aufgetreten. Bitte versuche es später erneut.',
                'details' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
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
        
        // Parse and validate email
        $user_email = isset($_POST['user_email']) ? sanitize_email($_POST['user_email']) : '';
        if (empty($user_email)) {
            wp_send_json_error(array('message' => 'Please enter a valid email address.'));
        }
        
        // Find user by email
        $user = get_user_by('email', $user_email);
        
        // Always return success to prevent user enumeration
        if (!$user) {
            wp_send_json_success(array('message' => 'If an account exists with that email, you will receive recovery instructions.'));
        }
        
        // Generate token
        $token = $this->generate_token();
        $user_login = $user->user_login;
        
        // Store token in database
$table_name = $wpdb->prefix . 'password_reset_tokens';

// Debug: Log table name and user ID
error_log('Using table: ' . $table_name . ' for user ID: ' . $user->ID);

// Check if table exists
$table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
if (!$table_exists) {
    error_log('ERROR: Table ' . $table_name . ' does not exist!');
    
    // Try to create the table on the fly
    $charset_collate = $wpdb->get_charset_collate();
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        token_hash varchar(255) NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    error_log('Attempted to create the table. Checking again...');
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'");
    if (!$table_exists) {
        error_log('ERROR: Failed to create table ' . $table_name);
        wp_send_json_error(array('message' => 'Database setup error. Please contact support.'));
        return;
    }
}

// Delete any existing tokens for this user
$delete_result = $wpdb->delete(
    $table_name,
    array('user_id' => $user->ID),
    array('%d')
);
error_log('Delete result: ' . ($delete_result !== false ? 'Success' : 'Failed') . 
          ($wpdb->last_error ? ' - Error: ' . $wpdb->last_error : ''));

// Set expiry time (1 hour from now)
$expires = date('Y-m-d H:i:s', time() + 3600);

// Insert new token
$insert_result = $wpdb->insert(
    $table_name,
    array(
        'user_id' => $user->ID,
        'token_hash' => wp_hash_password($token),
        'created_at' => current_time('mysql'),
        'expires_at' => $expires
    ),
    array('%d', '%s', '%s', '%s')
);

if ($insert_result === false) {
    error_log('DB Insert Error: ' . $wpdb->last_error);
    // Dump the data we tried to insert (sanitize sensitive info)
    error_log('Insert data: ' . json_encode(array(
        'user_id' => $user->ID,
        'token_hash' => '[REDACTED]',
        'created_at' => current_time('mysql'),
        'expires_at' => $expires
    )));
    wp_send_json_error(array('message' => 'Database error. Please try again later.'));
    return;
}
        
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
    
    /**
     * Handle password reset processing
     */
    public function handle_password_reset() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'yprint_reset_password') {
            // Verify nonce
            if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'yprint_reset_nonce')) {
                wp_send_json_error(array('message' => 'Security check failed.'));
            }
            
            $this->process_password_reset();
        }
    }
    
    /**
     * Handle AJAX password reset
     */
    public function ajax_process_password_reset() {
        // Verify nonce
        if (!isset($_POST['security']) || !wp_verify_nonce($_POST['security'], 'yprint_reset_nonce')) {
            wp_send_json_error(array('message' => 'Security check failed.'));
        }
        
        $this->process_password_reset();
    }
    
    /**
     * Core password reset processing logic
     */
    public function process_password_reset() {
        global $wpdb;
        
        // Parse and validate input
        $login = isset($_POST['login']) ? sanitize_user($_POST['login']) : '';
        $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $password = isset($_POST['password']) ? $_POST['password'] : '';
        
        if (empty($login) || empty($key) || empty($password)) {
            wp_send_json_error(array('message' => 'Missing required fields.'));
        }
        
        // Validate password
        if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[\W_]/', $password)) {
            wp_send_json_error(array('message' => 'Password does not meet the requirements.'));
        }
        
        // Verify token
        if (!$this->verify_token($login, $key)) {
            wp_send_json_error(array('message' => 'Invalid or expired reset link.'));
        }
        
        // Get user
        $user = get_user_by('login', $login);
        if (!$user) {
            wp_send_json_error(array('message' => 'User not found.'));
        }
        
        // Reset password
        reset_password($user, $password);
        
        // Delete token
        $table_name = $wpdb->prefix . 'password_reset_tokens';
        $wpdb->delete(
            $table_name,
            array('user_id' => $user->ID),
            array('%d')
        );
        
        // Send confirmation email
        $this->send_password_changed_email($user);
        
        wp_send_json_success(array('message' => 'Password reset successfully.'));
    }
    
    /**
     * Generate a secure token
     */
    private function generate_token($length = 32) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Verify token validity
     */
    private function verify_token($login, $token) {
        global $wpdb;
        
        if (empty($login) || empty($token)) {
            return false;
        }
        
        // Get user
        $user = get_user_by('login', $login);
        if (!$user) {
            return false;
        }
        
        // Get token from database
        $table_name = $wpdb->prefix . 'password_reset_tokens';
        $stored_token = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_id = %d AND expires_at > %s",
            $user->ID,
            current_time('mysql')
        ));
        
        // Check if token exists and has not expired
        if (!$stored_token) {
            return false;
        }
        
        // Since we can't reverse the hash, we'll need to check the token
        // This is a simplified check - WordPress's password_verify function would be more secure
        // but it's not directly available in this context
        return wp_check_password($token, $stored_token->token_hash);
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
            
            $mail_sent = $mail_sent = wp_mail($to, $subject, $message, $headers);
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
     * Clean up expired tokens (called via cron)
     */
    public static function cleanup_expired_tokens() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'password_reset_tokens';
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name WHERE expires_at < %s",
            current_time('mysql')
        ));
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
 * Create the password recovery tokens table
 */
function yprint_recovery_activation() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'password_reset_tokens';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        token_hash varchar(255) NOT NULL,
        created_at datetime NOT NULL,
        expires_at datetime NOT NULL,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Set flag to flush rewrite rules
    update_option('yprint_recovery_flush_rules', true);
}

/**
 * Deactivation hook to clean up
 */
function yprint_recovery_deactivation() {
    // Clear scheduled event
    wp_clear_scheduled_hook('yprint_cleanup_recovery_tokens');
}
register_deactivation_hook(__FILE__, 'yprint_recovery_deactivation');