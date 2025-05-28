<?php
/*
Plugin Name: YPrint Kontaktformular für eingeloggte Nutzer
Description: Generiert ein einfaches Textfeld, um eine E-Mail mit Nutzerdaten an info@yprint.de zu senden.
Version: 1.2
Author: Ihr Name oder Organisation
*/

function yprint_kontaktformular_shortcode() {
    if (is_user_logged_in()) {
        $current_user = wp_get_current_user();
        $user_id = $current_user->ID;
        $user_login = $current_user->user_login;
        $user_email = $current_user->user_email;
        $display_name = $current_user->display_name;

        $output = '<style>';
        $output .= '.yprint-button {';
        $output .= '    display: inline-block;';
        $output .= '    padding: 12px 20px;';
        $output .= '    background-color: #0079FF;';
        $output .= '    color: white;';
        $output .= '    border: none;';
        $output .= '    border-radius: 10px;';
        $output .= '    font-size: 16px;';
        $output .= '    font-weight: 500;';
        $output .= '    cursor: pointer;';
        $output .= '    text-decoration: none;';
        $output .= '    transition: all 0.2s ease;';
        $output .= '}';
        $output .= '.yprint-button:hover {';
        $output .= '    background-color: #0056b3;';
        $output .= '    transform: translateY(-1px);';
        $output .= '    box-shadow: 0 4px 12px rgba(0, 121, 255, 0.3);';
        $output .= '}';
        $output .= '</style>';
        $output .= '<form method="post" action="">';
        $output .= '<p><textarea name="nachricht" style="width: 100%; min-height: 150px; border: 1px solid #ccc; padding: 10px; box-sizing: border-box; background-color: #f9f9f9;"></textarea></p>';
        $output .= '<input type="hidden" name="user_id" value="' . esc_attr($user_id) . '">';
        $output .= '<input type="hidden" name="user_login" value="' . esc_attr($user_login) . '">';
        $output .= '<input type="hidden" name="user_email" value="' . esc_attr($user_email) . '">';
        $output .= '<input type="hidden" name="display_name" value="' . esc_attr($display_name) . '">';
        $output .= '<p style="text-align: right;"><input type="submit" value="Nachricht senden" class="yprint-button"></p>';
        $output .= '</form>';

        if (isset($_POST['nachricht'])) {
            $nachricht = sanitize_textarea_field($_POST['nachricht']);
            $betreff = 'Kontaktanfrage von eingeloggtem Nutzer';
            $body = "Nachricht:\n" . $nachricht . "\n\n";
            $body .= "Nutzer-ID: " . $user_id . "\n";
            $body .= "Benutzername: " . $user_login . "\n";
            $body .= "E-Mail: " . $user_email . "\n";
            $body .= "Anzeigename: " . $display_name . "\n";
            $headers = array('Content-Type: text/plain; charset=UTF-8');

            wp_mail('info@yprint.de', $betreff, $body, $headers);
            $output .= '<p style="color: green;">Ihre Nachricht wurde gesendet.</p>';
        }

        return $output;

    } else {
        return '<p>Bitte loggen Sie sich ein, um das Kontaktformular zu sehen.</p>';
    }
}
add_shortcode('yprint_kontakt', 'yprint_kontaktformular_shortcode');
?>