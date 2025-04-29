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

    <div class="yprint-register-container">
        <div class="yprint-logo">
            <div class="yprint-logo-image"></div>
        </div>
        <form method="post" id="register-form">
            <div class="yprint-form-group">
                <input type="text" name="user_login" id="user_login" class="input" placeholder="Username" required>
            </div>
            <div class="yprint-form-group">
                <input type="email" name="user_email" id="user_email" class="input" placeholder="Email" required>
                <div id="email-validity">
                    Email must be from a leading provider (e.g., gmail.com, yahoo.com).
                </div>
            </div>
            <div class="yprint-form-group">
                <input type="password" name="user_password" id="user_password" class="input" placeholder="Password" required>
                <div id="password-hint">
                    Password must be at least 8 characters long, include a capital letter, and a special character.
                </div>
            </div>
            <div class="yprint-form-group">
                <input type="password" name="user_password_confirm" id="user_password_confirm" class="input" placeholder="Repeat Password" required>
                <div id="confirm-password-hint">
                    Passwords must match.
                </div>
            </div>
            <div class="yprint-form-group">
                <input type="submit" name="wp-submit" value="Register" class="button button-primary">
            </div>
        </form>
        <div id="registration-message"></div>
    </div>

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