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

/**
 * Sendet eine Bestellbestätigungsmail
 *
 * @param WC_Order $order Das WooCommerce Bestellobjekt
 * @return bool Erfolg der E-Mail-Versendung
 */
function yprint_send_order_confirmation_email($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log('YPrint: Ungültiges Bestellobjekt für E-Mail-Versendung');
        return false;
    }

    $customer_email = $order->get_billing_email();
    $customer_name = $order->get_billing_first_name();
    
    if (empty($customer_email)) {
        error_log('YPrint: Keine E-Mail-Adresse für Bestellbestätigung gefunden');
        return false;
    }

    // E-Mail-Inhalt erstellen
    $email_content = yprint_build_order_confirmation_content($order);
    
    // E-Mail-Template verwenden
    $email_html = yprint_get_email_template(
        'Bestellbestätigung',
        $customer_name ?: 'Kunde',
        $email_content
    );

    // E-Mail-Header für HTML
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: YPrint <noreply@yprint.de>'
    );

    $subject = sprintf('Bestellbestätigung #%s - YPrint', $order->get_order_number());

    // E-Mail senden
    $sent = wp_mail(
        $customer_email,
        $subject,
        $email_html,
        $headers
    );

    if ($sent) {
        error_log(sprintf('YPrint: Bestellbestätigung an %s gesendet (Bestellung: %s)', 
            $customer_email, 
            $order->get_order_number()
        ));
        
        // Meta-Flag setzen um doppelte E-Mails zu vermeiden
        $order->update_meta_data('_yprint_confirmation_email_sent', 'yes');
        $order->save();
    } else {
        error_log(sprintf('YPrint: Fehler beim Senden der Bestellbestätigung an %s', 
            $customer_email
        ));
    }

    return $sent;
}

/**
 * Erstellt den HTML-Inhalt für die Bestellbestätigungsmail
 *
 * @param WC_Order $order Das WooCommerce Bestellobjekt
 * @return string Der HTML-Inhalt
 */
function yprint_build_order_confirmation_content($order) {
    ob_start();
    ?>
    <p style="margin-bottom: 20px; color: #343434; line-height: 1.5;">
        vielen Dank für Ihre Bestellung bei YPrint! Wir haben Ihre Bestellung erhalten und werden sie schnellstmöglich bearbeiten.
    </p>

    <!-- Bestelldetails -->
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f8f9fa; border-radius: 8px; overflow: hidden;">
        <tr style="background: #0079FF; color: white;">
            <td colspan="2" style="padding: 15px; font-weight: bold; font-size: 16px;">
                Bestelldetails
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; font-weight: bold;">Bestellnummer:</td>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5;">#<?php echo esc_html($order->get_order_number()); ?></td>
        </tr>
        <tr>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; font-weight: bold;">Bestelldatum:</td>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html($order->get_date_created()->format('d.m.Y H:i')); ?></td>
        </tr>
        <tr>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; font-weight: bold;">Status:</td>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5;"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></td>
        </tr>
        <tr>
            <td style="padding: 10px 15px; font-weight: bold;">Gesamtbetrag:</td>
            <td style="padding: 10px 15px; font-weight: bold; color: #0079FF;"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
        </tr>
    </table>

    <!-- Bestellte Artikel -->
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
        <tr style="background: #0079FF; color: white;">
            <td colspan="4" style="padding: 15px; font-weight: bold; font-size: 16px;">
                Bestellte Artikel
            </td>
        </tr>
        <tr style="background: #f8f9fa; font-weight: bold;">
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5;">Artikel</td>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; text-align: center;">Menge</td>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; text-align: right;">Einzelpreis</td>
            <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; text-align: right;">Gesamt</td>
        </tr>
        <?php
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            ?>
            <tr>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5;">
                    <?php echo esc_html($item->get_name()); ?>
                    <?php
                    // Design-Details falls vorhanden
                    $design_details = $item->get_meta('_design_details');
                    if (!empty($design_details)) {
                        echo '<br><small style="color: #666;">' . esc_html($design_details) . '</small>';
                    }
                    ?>
                </td>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; text-align: center;"><?php echo esc_html($item->get_quantity()); ?></td>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; text-align: right;"><?php echo wp_kses_post(wc_price($item->get_subtotal() / $item->get_quantity())); ?></td>
                <td style="padding: 10px 15px; border-bottom: 1px solid #e5e5e5; text-align: right;"><?php echo wp_kses_post(wc_price($item->get_total())); ?></td>
            </tr>
            <?php
        }
        ?>
    </table>

    <!-- Lieferadresse -->
    <?php if ($order->has_shipping_address()) : ?>
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f8f9fa; border-radius: 8px; overflow: hidden;">
        <tr style="background: #0079FF; color: white;">
            <td style="padding: 15px; font-weight: bold; font-size: 16px;">
                Lieferadresse
            </td>
        </tr>
        <tr>
            <td style="padding: 15px; line-height: 1.5;">
                <?php echo wp_kses_post($order->get_formatted_shipping_address()); ?>
            </td>
        </tr>
    </table>
    <?php endif; ?>

    <!-- Rechnungsadresse -->
    <table style="width: 100%; border-collapse: collapse; margin: 20px 0; background: #f8f9fa; border-radius: 8px; overflow: hidden;">
        <tr style="background: #0079FF; color: white;">
            <td style="padding: 15px; font-weight: bold; font-size: 16px;">
                Rechnungsadresse
            </td>
        </tr>
        <tr>
            <td style="padding: 15px; line-height: 1.5;">
                <?php echo wp_kses_post($order->get_formatted_billing_address()); ?>
            </td>
        </tr>
    </table>

    <p style="margin-top: 30px; color: #343434; line-height: 1.5;">
        <strong>Was passiert als nächstes?</strong><br>
        Wir werden Ihre Bestellung innerhalb der nächsten 24 Stunden bearbeiten und Ihnen eine Versandbestätigung mit Tracking-Informationen senden.
    </p>

    <p style="color: #343434; line-height: 1.5;">
        Bei Fragen zu Ihrer Bestellung können Sie uns jederzeit kontaktieren. Geben Sie dabei bitte Ihre Bestellnummer <strong>#<?php echo esc_html($order->get_order_number()); ?></strong> an.
    </p>

    <p style="margin-top: 20px; color: #343434; line-height: 1.5;">
        Vielen Dank für Ihr Vertrauen!<br>
        Ihr YPrint Team
    </p>
    <?php
    return ob_get_clean();
}