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
 * Erstellt eine E-Mail-Vorlage mit verbesserten Email-Client-Kompatibilität
 *
 * @param string $title Die Überschrift der E-Mail
 * @param string $username Der Benutzername des Empfängers
 * @param string $content Der Nachrichteninhalt
 * @return string Die formatierte E-Mail-Nachricht
 */
function yprint_get_email_template($title, $username, $content) {
    // Vollständig integrierte Vorlage ohne externe Abhängigkeiten
    ob_start();
    ?><!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo esc_html($title); ?></title>
</head>
<body style="margin: 0; padding: 0; background-color: #F6F7FA; font-family: Arial, Helvetica, sans-serif; color: #343434;">
    <!-- Wrapper für die gesamte E-Mail -->
    <table border="0" cellpadding="0" cellspacing="0" width="100%" style="background-color: #F6F7FA;">
        <tr>
            <td align="center" style="padding: 20px 0;">
                <!-- Container mit maximaler Breite -->
                <table border="0" cellpadding="0" cellspacing="0" width="600" style="background-color: #FFFFFF; border: 1px solid #d3d3d3; border-radius: 10px;">
                    <!-- Header mit Logo -->
                    <tr>
                        <td align="center" style="padding: 20px 0;">
                            <img src="https://yprint.de/wp-content/uploads/2025/02/yprint-logo.png" alt="YPrint Logo" width="100" height="40" style="display: block;" />
                        </td>
                    </tr>
                    
                    <!-- Inhalt -->
                    <tr>
                        <td align="center" style="padding: 0 30px 30px 30px;">
                            <table border="0" cellpadding="0" cellspacing="0" width="100%">
                                <tr>
                                    <td align="center" style="font-size: 25px; color: #000000; padding-bottom: 15px;">
                                        <strong><?php echo esc_html($title); ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td align="center" style="color: #878787; font-size: 15px; line-height: 1.5; padding-bottom: 10px;">
                                        <p style="margin-top: 0;">Hi <?php echo esc_html($username); ?>,</p>
                                        
                                        <!-- Inhalt wird hier eingefügt -->
                                        <?php 
                                        // Den Content in tabellenkompatibles Format umwandeln
                                        $processed_content = str_replace('<a href=', '<a style="color: #0079FF; text-decoration: none;" href=', $content);
                                        echo $processed_content; 
                                        ?>
                                    </td>
                                </tr>
                            </table>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td align="center" style="padding: 20px 30px; border-top: 1px solid #eeeeee; color: #808080; font-size: 12px;">
                            <p style="margin: 0;">© <?php echo date('Y'); ?> <a href="https://yprint.de" target="_blank" rel="noopener noreferrer" style="color: #0079FF; text-decoration: none; text-transform: lowercase;">yprint</a> – Alle Rechte vorbehalten.</p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
    <?php
    return ob_get_clean();
}