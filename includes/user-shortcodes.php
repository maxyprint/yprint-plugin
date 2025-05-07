<?php
/**
 * User-related shortcodes for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Shortcode to display username if logged in, or a guest message if not
 * 
 * Usage: [hello_user_loggedin]
 * 
 * @return string The formatted HTML output
 */
function hello_user_loggedin_shortcode() {
    // Überprüfen ob ein Benutzer eingeloggt ist
    if (is_user_logged_in()) {
        // Holen des aktuellen Benutzerobjekts
        $current_user = wp_get_current_user();
        
        // Stildefinitionen
        $spacer_style = 'height: 20px;'; // Abstandshöhe
        $username_style = 'font-family: Roboto, sans-serif; font-size: 20px; color: rgba(0, 0, 0, 0.6); margin-bottom: 10px;';
        $button_style = 'font-family: Roboto, sans-serif; font-size: 16px; color: #007aff; text-decoration: none;';
        
        // HTML-Ausgabe
        $output = '<div style="text-align: center;">';
        $output .= '<div style="' . $spacer_style . '"></div>'; // Abstand über der Anzeige
        $output .= '<span style="' . $username_style . '">' . esc_html($current_user->user_login) . '</span>';
        $output .= '<br>';
        $output .= '<a href="' . wp_logout_url(home_url()) . '" style="' . $button_style . '">Logout</a>';
        $output .= '<div style="' . $spacer_style . '"></div>'; // Abstand unterhalb der Anzeige
        $output .= '</div>';
        
        return $output;
    } else {
        // Wenn kein Benutzer eingeloggt ist, wird eine allgemeine Nachricht angezeigt
        return '<span style="font-family: Roboto, sans-serif; font-size: 20px; font-weight: normal;">Hello Guest!</span>';
    }
}
add_shortcode('hello_user_loggedin', 'hello_user_loggedin_shortcode');

/**
 * Shortcode to display user account information
 * 
 * Usage: [yprint_user_account]
 *
 * @return string The formatted HTML output or empty if not logged in
 */
function yprint_user_account_shortcode() {
    if (!is_user_logged_in()) {
        return '';
    }
    
    $current_user = wp_get_current_user();
    
    $output = '<div class="yprint-user-account">';
    $output .= '<h3>Account Information</h3>';
    $output .= '<p><strong>Username:</strong> ' . esc_html($current_user->user_login) . '</p>';
    $output .= '<p><strong>Email:</strong> ' . esc_html($current_user->user_email) . '</p>';
    
    // Check if email is verified
    global $wpdb;
    $table_name = 'wp_email_verifications';
    $user_id = $current_user->ID;
    
    $email_verified = $wpdb->get_var(
        $wpdb->prepare("SELECT email_verified FROM $table_name WHERE user_id = %d", $user_id)
    );
    
    $verification_status = '';
    if ($email_verified === null) {
        $verification_status = '<span style="color: green;">✓ Verified</span>';
    } elseif ($email_verified == 1) {
        $verification_status = '<span style="color: green;">✓ Verified</span>';
    } else {
        $verification_status = '<span style="color: red;">✗ Not verified</span>';
        
        // Add resend verification option
        $output .= '<form method="post" action="' . esc_url(home_url('/login')) . '">';
        $output .= wp_nonce_field('resend_verification_nonce', 'security', true, false);
        $output .= '<input type="hidden" name="resend_verification" value="' . esc_attr($user_id) . '">';
        $output .= '<button type="submit" style="background-color: #0079FF; color: white; padding: 8px 15px; border: none; cursor: pointer; font-size: 14px; border-radius: 4px;">
                    ✉️ Resend Verification Email
                    </button>';
        $output .= '</form>';
    }
    
    $output .= '<p><strong>Email Status:</strong> ' . $verification_status . '</p>';
    $output .= '<p><a href="' . esc_url(wp_logout_url(home_url())) . '" style="color: #0079FF; text-decoration: none;">Logout</a></p>';
    $output .= '</div>';
    
    return $output;
}
add_shortcode('yprint_user_account', 'yprint_user_account_shortcode');