<?php
/**
 * Email functions for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get email template with YPrint branding
 *
 * @param string $title Email title
 * @param string $username Username
 * @param string $content Email content
 * @return string Formatted email template
 */
function yprint_get_email_template($title, $username, $content) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($title); ?></title>
        <style>
            body {
                font-family: Arial, sans-serif;
                line-height: 1.6;
                margin: 0;
                padding: 0;
                background-color: #f6f7fa;
            }
            .email-container {
                max-width: 600px;
                margin: 0 auto;
                background-color: #ffffff;
            }
            .email-header {
                background-color: #ffffff;
                padding: 20px;
                text-align: center;
            }
            .email-content {
                padding: 30px;
                color: #333333;
            }
            .email-footer {
                background-color: #f6f7fa;
                padding: 20px;
                text-align: center;
                font-size: 12px;
                color: #777777;
            }
            .button {
                display: inline-block;
                background-color: #007aff;
                padding: 15px 30px;
                color: #ffffff;
                text-decoration: none;
                font-size: 16px;
                border-radius: 5px;
            }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="email-header">
                <img src="https://yprint.de/wp-content/uploads/2024/08/yprint-icon.png" alt="YPrint Logo" style="max-width: 200px;">
            </div>
            <div class="email-content">
                <h2><?php echo esc_html($title); ?></h2>
                <p>Hello <?php echo esc_html($username); ?>,</p>
                
                <?php echo wp_kses_post($content); ?>
                
                <p>Best regards,<br>The YPrint Team</p>
            </div>
            <div class="email-footer">
                <p>&copy; <?php echo date('Y'); ?> YPrint. All rights reserved.</p>
                <p>
                    <a href="https://yprint.de/privacy-policy">Privacy Policy</a> | 
                    <a href="https://yprint.de/terms-of-service">Terms of Service</a>
                </p>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}

/**
 * Handle email verification page content
 */
function yprint_email_verification_page_content($content) {
    global $post;
    
    // Check if we're on the verification page
    if (is_page('verify-email')) {
        ob_start();
        
        if (isset($_GET['verified']) && $_GET['verified'] == 'true') {
            ?>
            <div class="yprint-message success">
                <h2>Email Verified!</h2>
                <p>Your email has been successfully verified. You can now log in to your account.</p>
                <p><a href="<?php echo wp_login_url(); ?>" class="button">Log In</a></p>
            </div>
            <?php
        } else if (isset($_GET['verified']) && $_GET['verified'] == 'false') {
            ?>
            <div class="yprint-message error">
                <h2>Verification Failed</h2>
                <p>We couldn't verify your email address. The verification link may be expired or invalid.</p>
                <p><a href="<?php echo wp_registration_url(); ?>" class="button">Register Again</a></p>
            </div>
            <?php
        } else {
            ?>
            <div class="yprint-message info">
                <h2>Verifying Email...</h2>
                <p>Please wait while we verify your email address.</p>
            </div>
            <?php
        }
        
        return ob_get_clean();
    }
    
    return $content;
}
add_filter('the_content', 'yprint_email_verification_page_content');