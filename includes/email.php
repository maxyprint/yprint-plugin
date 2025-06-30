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
                                    <td align="center" style="color: #1d1d1f; font-size: 15px; line-height: 1.5; padding-bottom: 10px;">
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
    error_log('=== YPRINT EMAIL DEBUG: Bestellbestätigungs-E-Mail Prozess gestartet ===');
    
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log('YPrint EMAIL DEBUG: FEHLER - Ungültiges Bestellobjekt für E-Mail-Versendung');
        error_log('YPrint EMAIL DEBUG: Order Type: ' . gettype($order));
        error_log('YPrint EMAIL DEBUG: Order Class: ' . (is_object($order) ? get_class($order) : 'Not an object'));
        return false;
    }

    error_log('YPrint EMAIL DEBUG: Gültiges WC_Order Objekt erhalten');
    error_log('YPrint EMAIL DEBUG: Bestellnummer: ' . $order->get_order_number());
    error_log('YPrint EMAIL DEBUG: Bestell-ID: ' . $order->get_id());
    error_log('YPrint EMAIL DEBUG: Bestellstatus: ' . $order->get_status());
    error_log('YPrint EMAIL DEBUG: Ist bezahlt: ' . ($order->is_paid() ? 'JA' : 'NEIN'));

    $customer_email = $order->get_billing_email();
    $customer_name = $order->get_billing_first_name();
    
    error_log('YPrint EMAIL DEBUG: Kunden-E-Mail: ' . ($customer_email ?: 'LEER'));
    error_log('YPrint EMAIL DEBUG: Kundenname: ' . ($customer_name ?: 'LEER'));
    
    if (empty($customer_email)) {
        error_log('YPrint EMAIL DEBUG: FEHLER - Keine E-Mail-Adresse für Bestellbestätigung gefunden');
        return false;
    }

    // Prüfe ob E-Mail bereits gesendet wurde
    $email_already_sent = $order->get_meta('_yprint_confirmation_email_sent');
    error_log('YPrint EMAIL DEBUG: E-Mail bereits gesendet? ' . ($email_already_sent === 'yes' ? 'JA' : 'NEIN'));
    
    if ($email_already_sent === 'yes') {
        error_log('YPrint EMAIL DEBUG: ÜBERSPRUNGEN - E-Mail bereits gesendet für Bestellung ' . $order->get_order_number());
        return true; // Return true da E-Mail bereits erfolgreich gesendet wurde
    }

    error_log('YPrint EMAIL DEBUG: Erstelle E-Mail-Inhalt...');
    
    // E-Mail-Inhalt erstellen
    $email_content = yprint_build_order_confirmation_content($order);
    error_log('YPrint EMAIL DEBUG: E-Mail-Inhalt erstellt, Länge: ' . strlen($email_content) . ' Zeichen');
    
    // E-Mail-Template verwenden
    $email_html = yprint_get_email_template(
        'Bestellbestätigung',
        $customer_name ?: 'Kunde',
        $email_content
    );
    error_log('YPrint EMAIL DEBUG: E-Mail-Template angewendet, finale Länge: ' . strlen($email_html) . ' Zeichen');

    // E-Mail-Header für HTML
    $headers = array(
        'Content-Type: text/html; charset=UTF-8',
        'From: YPrint <noreply@yprint.de>'
    );

    $subject = sprintf('Bestellbestätigung #%s - YPrint', $order->get_order_number());
    error_log('YPrint EMAIL DEBUG: E-Mail-Betreff: ' . $subject);
    error_log('YPrint EMAIL DEBUG: E-Mail-Header: ' . print_r($headers, true));

    error_log('YPrint EMAIL DEBUG: Sende E-Mail über wp_mail()...');
    
    // E-Mail senden
    $sent = wp_mail(
        $customer_email,
        $subject,
        $email_html,
        $headers
    );

    error_log('YPrint EMAIL DEBUG: wp_mail() Ergebnis: ' . ($sent ? 'ERFOLGREICH' : 'FEHLGESCHLAGEN'));

    if ($sent) {
        error_log(sprintf('YPrint EMAIL DEBUG: ERFOLG - Bestellbestätigung an %s gesendet (Bestellung: %s)', 
            $customer_email, 
            $order->get_order_number()
        ));
        
        // Meta-Flag setzen um doppelte E-Mails zu vermeiden
        $order->update_meta_data('_yprint_confirmation_email_sent', 'yes');
        $order->save();
        error_log('YPrint EMAIL DEBUG: Meta-Flag _yprint_confirmation_email_sent auf "yes" gesetzt');
    } else {
        error_log(sprintf('YPrint EMAIL DEBUG: FEHLER - Fehler beim Senden der Bestellbestätigung an %s', 
            $customer_email
        ));
        
        // Zusätzliche Debug-Informationen bei Fehlern
        global $phpmailer;
        if (isset($phpmailer) && is_object($phpmailer)) {
            error_log('YPrint EMAIL DEBUG: PHPMailer Fehler: ' . $phpmailer->ErrorInfo);
        }
    }

    error_log('=== YPRINT EMAIL DEBUG: Bestellbestätigungs-E-Mail Prozess beendet ===');
    return $sent;
}

/**
 * Erstellt den HTML-Inhalt für die Bestellbestätigungsmail
 *
 * @param WC_Order $order Das WooCommerce Bestellobjekt
 * @return string Der HTML-Inhalt
 */
/**
 * Erstellt den HTML-Inhalt für die Bestellbestätigungsmail
 *
 * @param WC_Order $order Das WooCommerce Bestellobjekt
 * @return string Der HTML-Inhalt
 */
function yprint_build_order_confirmation_content($order) {
    ob_start();
    ?>
    <!-- Hero Bereich -->
    <div style="background: linear-gradient(135deg, #0079FF 0%, #0066DD 100%); border-radius: 12px; padding: 30px; text-align: center; margin-bottom: 30px; color: white;">
        <h1 style="margin: 0 0 10px 0; font-size: 28px; font-weight: 700; color: white;">
            🎉 Bestellung bestätigt!
        </h1>
        <p style="margin: 0; font-size: 16px; opacity: 0.9; line-height: 1.5;">
            Vielen Dank für Ihre Bestellung bei YPrint! Wir werden Ihr Design jetzt für Sie drucken.
        </p>
    </div>

    <!-- Bestelldetails -->
    <div style="background: linear-gradient(135deg, #f8fffe 0%, #f0f9ff 100%); border-left: 4px solid #0079FF; border-radius: 8px; padding: 25px; margin: 25px 0; box-shadow: 0 2px 8px rgba(0,121,255,0.1);">
        <h2 style="margin: 0 0 20px 0; color: #1a1a1a; font-size: 20px; font-weight: 600; display: flex; align-items: center;">
            📋 Ihre Bestelldetails
        </h2>
        <div style="display: grid; gap: 12px;">
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(0,121,255,0.1);">
                <span style="color: #666; font-weight: 500;">Bestellnummer</span>
                <span style="color: #1a1a1a; font-weight: 700;">#<?php echo esc_html($order->get_order_number()); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(0,121,255,0.1);">
                <span style="color: #666; font-weight: 500;">Bestelldatum</span>
                <span style="color: #1a1a1a; font-weight: 600;"><?php echo esc_html($order->get_date_created()->format('d.m.Y H:i')); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid rgba(0,121,255,0.1);">
                <span style="color: #666; font-weight: 500;">Status</span>
                <span style="background: #22c55e; color: white; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600;"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 15px 0 5px 0; border-top: 2px solid #0079FF; margin-top: 10px;">
                <span style="color: #1a1a1a; font-weight: 700; font-size: 16px;">Gesamtbetrag</span>
                <span style="color: #0079FF; font-weight: 700; font-size: 20px;"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></span>
            </div>
        </div>
    </div>

    <!-- Bestellte Artikel -->
    <div style="background: white; border: 1px solid #e5e7eb; border-radius: 12px; padding: 25px; margin: 25px 0; box-shadow: 0 1px 6px rgba(0,0,0,0.05);">
        <h2 style="margin: 0 0 20px 0; color: #1a1a1a; font-size: 20px; font-weight: 600; display: flex; align-items: center;">
            🛍️ Ihre bestellten Artikel
        </h2>
        <?php
        foreach ($order->get_items() as $item_id => $item) {
            $product = $item->get_product();
            if (!$product) continue;
            
            // Bestimme den Artikelnamen basierend auf Design-Daten
            $design_name = $item->get_meta('_design_name');
            $product_name = $product->get_name();
            
            if (!empty($design_name)) {
                $display_name = esc_html($design_name) . ' - gedruckt auf ' . esc_html($product_name);
            } else {
                $display_name = esc_html($product_name);
            }
            
            // Design-Details sammeln
            $design_details = [];
            $design_color = $item->get_meta('_design_color');
            $design_size = $item->get_meta('_design_size');
            
            if (!empty($design_color)) {
                $design_details[] = $design_color;
            }
            if (!empty($design_size)) {
                $design_details[] = 'Größe ' . $design_size;
            }
            
            $individual_price = $item->get_subtotal() / $item->get_quantity();
            ?>
            <div style="background: linear-gradient(135deg, #fafafa 0%, #f5f5f5 100%); border-radius: 8px; padding: 20px; margin-bottom: 15px; border-left: 4px solid #0079FF;">
                <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                    <div style="flex: 1;">
                        <h3 style="margin: 0 0 8px 0; color: #1a1a1a; font-size: 16px; font-weight: 600; line-height: 1.4;">
                            <?php echo $display_name; ?>
                        </h3>
                        <?php if (!empty($design_details)): ?>
                        <div style="margin-bottom: 8px;">
                            <?php foreach ($design_details as $detail): ?>
                            <span style="background: #0079FF; color: white; padding: 4px 10px; border-radius: 15px; font-size: 11px; font-weight: 600; margin-right: 8px; display: inline-block;">
                                <?php echo esc_html($detail); ?>
                            </span>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                        <p style="margin: 0; color: #666; font-size: 13px; font-weight: 500;">
                            Menge: <?php echo esc_html($item->get_quantity()); ?> × <?php echo wp_kses_post(wc_price($individual_price)); ?>
                        </p>
                    </div>
                    <div style="text-align: right; margin-left: 20px;">
                        <div style="background: #0079FF; color: white; padding: 8px 16px; border-radius: 6px; font-weight: 700; font-size: 16px;">
                            <?php echo wp_kses_post(wc_price($item->get_total())); ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>
    </div>

    <!-- Adress-Container -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin: 25px 0;">
        <!-- Lieferadresse -->
        <?php if ($order->has_shipping_address()) : ?>
        <div style="background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%); border-left: 4px solid #22c55e; border-radius: 8px; padding: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 16px; font-weight: 600; display: flex; align-items: center;">
                🚚 Lieferadresse
            </h3>
            <div style="color: #374151; line-height: 1.6; font-size: 14px;">
                <?php echo wp_kses_post($order->get_formatted_shipping_address()); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Rechnungsadresse -->
        <div style="background: linear-gradient(135deg, #fef7ff 0%, #fdf4ff 100%); border-left: 4px solid #a855f7; border-radius: 8px; padding: 20px;">
            <h3 style="margin: 0 0 15px 0; color: #1a1a1a; font-size: 16px; font-weight: 600; display: flex; align-items: center;">
                🧾 Rechnungsadresse
            </h3>
            <div style="color: #374151; line-height: 1.6; font-size: 14px;">
                <?php echo wp_kses_post($order->get_formatted_billing_address()); ?>
            </div>
        </div>
    </div>

    <!-- Was passiert als nächstes -->
    <div style="background: linear-gradient(135deg, #fff7ed 0%, #fed7aa 100%); border-radius: 12px; padding: 25px; margin: 30px 0; text-align: center; border: 1px solid #fb923c;">
        <h3 style="margin: 0 0 15px 0; color: #ea580c; font-size: 18px; font-weight: 700;">
            ⏰ Was passiert als nächstes?
        </h3>
        <p style="margin: 0 0 15px 0; color: #9a3412; line-height: 1.6; font-size: 15px;">
            Wir werden Ihre Bestellung innerhalb der <strong>nächsten 24 Stunden</strong> bearbeiten und Ihnen eine Versandbestätigung mit Tracking-Informationen senden.
        </p>
        <div style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
            <p style="margin: 0; color: #374151; font-size: 14px; line-height: 1.5;">
                <strong>Fragen?</strong> Kontaktieren Sie uns jederzeit unter Angabe Ihrer Bestellnummer <span style="background: #0079FF; color: white; padding: 2px 8px; border-radius: 4px; font-weight: 600;">#<?php echo esc_html($order->get_order_number()); ?></span>
            </p>
        </div>
    </div>

    <!-- Dankesnachricht -->
    <div style="background: linear-gradient(135deg, #1a1a1a 0%, #374151 100%); border-radius: 12px; padding: 30px; text-align: center; color: white; margin: 30px 0;">
        <h3 style="margin: 0 0 10px 0; font-size: 20px; font-weight: 700; color: white;">
            Vielen Dank für Ihr Vertrauen! 🙏
        </h3>
        <p style="margin: 0; opacity: 0.9; font-size: 16px; line-height: 1.5;">
            Ihr <span style="background: #0079FF; padding: 4px 8px; border-radius: 4px; font-weight: 600;">YPrint</span> Team
        </p>
    </div>
    <?php
    return ob_get_clean();
}