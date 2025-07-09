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
                                        <p style="margin-top: 0;">danke für deine Bestellung!</p>
                                        
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

                </table>
            
            <!-- 🔥 NEU: Adressdaten-Sektion -->
            <h2 style="margin: 30px 0 25px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 22px; font-weight: 600;">
                Adressdaten
            </h2>
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <!-- Lieferadresse -->
                <div style="flex: 1; min-width: 250px; background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                    <h3 style="margin: 0 0 15px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 16px; font-weight: 600;">
                        📦 Lieferadresse
                    </h3>
                    <div style="color: <?php echo esc_attr($text_color_dark); ?>; line-height: 1.5;">
                        <?php 
                        $shipping_lines = [];
                        if (!empty($shipping_address['first_name']) || !empty($shipping_address['last_name'])) {
                            $shipping_lines[] = trim(($shipping_address['first_name'] ?? '') . ' ' . ($shipping_address['last_name'] ?? ''));
                        }
                        if (!empty($shipping_address['address_1'])) {
                            $shipping_lines[] = esc_html($shipping_address['address_1']);
                        }
                        if (!empty($shipping_address['address_2'])) {
                            $shipping_lines[] = esc_html($shipping_address['address_2']);
                        }
                        if (!empty($shipping_address['postcode']) || !empty($shipping_address['city'])) {
                            $shipping_lines[] = trim(($shipping_address['postcode'] ?? '') . ' ' . ($shipping_address['city'] ?? ''));
                        }
                        if (!empty($shipping_address['country'])) {
                            $shipping_lines[] = esc_html($shipping_address['country']);
                        }
                        echo implode('<br>', $shipping_lines);
                        ?>
                    </div>
                </div>
                
                <!-- Rechnungsadresse -->
                <div style="flex: 1; min-width: 250px; background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 15px;">
                    <h3 style="margin: 0 0 15px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 16px; font-weight: 600;">
                        🧾 Rechnungsadresse
                    </h3>
                    <div style="color: <?php echo esc_attr($text_color_dark); ?>; line-height: 1.5;">
                        <?php 
                        $billing_lines = [];
                        if (!empty($billing_address['first_name']) || !empty($billing_address['last_name'])) {
                            $billing_lines[] = trim(($billing_address['first_name'] ?? '') . ' ' . ($billing_address['last_name'] ?? ''));
                        }
                        if (!empty($billing_address['address_1'])) {
                            $billing_lines[] = esc_html($billing_address['address_1']);
                        }
                        if (!empty($billing_address['address_2'])) {
                            $billing_lines[] = esc_html($billing_address['address_2']);
                        }
                        if (!empty($billing_address['postcode']) || !empty($billing_address['city'])) {
                            $billing_lines[] = trim(($billing_address['postcode'] ?? '') . ' ' . ($billing_address['city'] ?? ''));
                        }
                        if (!empty($billing_address['country'])) {
                            $billing_lines[] = esc_html($billing_address['country']);
                        }
                        echo implode('<br>', $billing_lines);
                        ?>
                    </div>
                </div>
            </div>
        </div>
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
 * VOLLSTÄNDIG ÜBERARBEITETE VERSION mit robusten Fallbacks
 *
 * @param WC_Order $order Das WooCommerce Bestellobjekt
 * @return string Der HTML-Inhalt für die E-Mail
 */
function yprint_build_order_confirmation_content($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log('🔴 YPrint Email: Ungültiges Order-Objekt für Content-Build');
        return yprint_build_error_email_content();
    }
    
    $order_id = $order->get_id();
    error_log('🎯 === E-MAIL CONTENT BUILD START - Order #' . $order_id . ' ===');
    
    // 📊 SCHRITT 1: Robuste Datensammlung
    $email_data = yprint_collect_email_data($order);
    
    if (empty($email_data)) {
        error_log('🔴 Kritischer Fehler: Keine E-Mail-Daten verfügbar für Order #' . $order_id);
        return yprint_build_fallback_email_content($order);
    }
    
    error_log('✅ E-Mail-Daten gesammelt: Shipping=' . ($email_data['shipping']['address_1'] ?? 'FEHLT') . 
              ', Items=' . count($email_data['items']));
    
    // 🎨 SCHRITT 2: HTML-Template generieren
    ob_start();
    ?>
    
    <!-- Bestellbestätigung Header -->
    <div style="background: linear-gradient(135deg, #0079FF 0%, #0056b3 100%); padding: 30px; text-align: center; border-radius: 12px 12px 0 0;">
        <h1 style="color: #FFFFFF; margin: 0; font-size: 28px; font-weight: 600;">
            ✅ Bestellung bestätigt!
        </h1>
        <p style="color: #E6F3FF; margin: 10px 0 0 0; font-size: 16px;">
            Vielen Dank für Ihre Bestellung bei YPrint
        </p>
    </div>
    
    <!-- Bestellinformationen -->
    <div style="background-color: #F8F9FA; padding: 25px; border-left: 4px solid #0079FF;">
        <h2 style="margin: 0 0 15px 0; color: #1d1d1f; font-size: 20px;">📋 Ihre Bestelldetails</h2>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #6e6e73; font-weight: 500;">Bestellnummer:</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600; color: #1d1d1f;">
                    <?php echo esc_html($email_data['order_number']); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6e6e73; font-weight: 500;">Bestelldatum:</td>
                <td style="padding: 8px 0; text-align: right; color: #1d1d1f;">
                    <?php echo esc_html($email_data['order_date']); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6e6e73; font-weight: 500;">Status:</td>
                <td style="padding: 8px 0; text-align: right; color: #28a745; font-weight: 600;">
                    <?php echo esc_html($email_data['status_text']); ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #6e6e73; font-weight: 500;">Zahlungsart:</td>
                <td style="padding: 8px 0; text-align: right; color: #1d1d1f;">
                    <?php echo esc_html($email_data['payment_method']); ?>
                    <?php if ($email_data['is_paid']): ?>
                        <span style="color: #28a745; font-weight: 600;"> ✓ Bezahlt</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Bestellte Artikel -->
    <div style="margin: 25px 0;">
        <h2 style="margin: 0 0 20px 0; color: #1d1d1f; font-size: 20px; border-bottom: 2px solid #e5e5e5; padding-bottom: 10px;">
            🛍️ Ihre bestellten Artikel
        </h2>
        
        <?php foreach ($email_data['items'] as $item): ?>
        <div style="background-color: #FFFFFF; border: 1px solid #e5e5e5; border-radius: 8px; padding: 20px; margin-bottom: 15px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <h3 style="margin: 0 0 10px 0; color: #1d1d1f; font-size: 18px; font-weight: 600;">
                        <?php echo esc_html($item['display_name']); ?>
                    </h3>
                    
                    <?php if ($item['is_design_product'] && !empty($item['design_details'])): ?>
                    <div style="background-color: #E6F3FF; padding: 12px; border-radius: 6px; margin: 10px 0;">
                        <p style="margin: 0; color: #0079FF; font-weight: 600; font-size: 14px;">🎨 Design-Details:</p>
                        <?php if (!empty($item['design_details']['color'])): ?>
                        <p style="margin: 5px 0 0 0; color: #1d1d1f; font-size: 14px;">
                            Farbe: <?php echo esc_html($item['design_details']['color']); ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($item['design_details']['size'])): ?>
                        <p style="margin: 5px 0 0 0; color: #1d1d1f; font-size: 14px;">
                            Größe: <?php echo esc_html($item['design_details']['size']); ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p style="margin: 5px 0 0 0; color: #6e6e73; font-size: 14px;">
                        Menge: <?php echo esc_html($item['quantity']); ?> Stück
                    </p>
                </div>
                
                <div style="text-align: right; margin-left: 20px;">
                    <p style="margin: 0; color: #6e6e73; font-size: 14px;">Einzelpreis</p>
                    <p style="margin: 5px 0; color: #1d1d1f; font-size: 16px;">
                        <?php echo $item['unit_price_formatted']; ?>
                    </p>
                    <p style="margin: 10px 0 0 0; color: #0079FF; font-size: 18px; font-weight: 600;">
                        Gesamt: <?php echo $item['total_formatted']; ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Gesamtsumme -->
    <div style="background-color: #F8F9FA; padding: 20px; border-radius: 8px; border: 2px solid #0079FF;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 5px 0; color: #6e6e73;">Zwischensumme:</td>
                <td style="padding: 5px 0; text-align: right; color: #1d1d1f;">
                    <?php echo esc_html($email_data['subtotal_formatted']); ?>
                </td>
            </tr>
            <?php if ($email_data['tax_total'] > 0): ?>
            <tr>
                <td style="padding: 5px 0; color: #6e6e73;">MwSt. (19%):</td>
                <td style="padding: 5px 0; text-align: right; color: #1d1d1f;">
                    <?php echo esc_html($email_data['tax_formatted']); ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($email_data['shipping_total'] > 0): ?>
            <tr>
                <td style="padding: 5px 0; color: #6e6e73;">Versand:</td>
                <td style="padding: 5px 0; text-align: right; color: #1d1d1f;">
                    <?php echo esc_html($email_data['shipping_formatted']); ?>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <td style="padding: 5px 0; color: #6e6e73;">Versand:</td>
                <td style="padding: 5px 0; text-align: right; color: #28a745; font-weight: 600;">Kostenlos</td>
            </tr>
            <?php endif; ?>
            <tr style="border-top: 2px solid #e5e5e5;">
                <td style="padding: 15px 0 5px 0; color: #1d1d1f; font-size: 20px; font-weight: 700;">Gesamtbetrag:</td>
                <td style="padding: 15px 0 5px 0; text-align: right; color: #0079FF; font-size: 24px; font-weight: 700;">
                    <?php echo esc_html($email_data['total_formatted']); ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Adressen -->
    <div style="margin: 30px 0;">
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            <!-- Lieferadresse -->
            <div style="flex: 1; min-width: 250px; background-color: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0; color: #1d1d1f; font-size: 16px; font-weight: 600;">
                    🚚 Lieferadresse
                </h3>
                <div style="color: #1d1d1f; line-height: 1.6;">
                    <?php echo yprint_format_address_html($email_data['shipping']); ?>
                </div>
            </div>
            
            <!-- Rechnungsadresse -->
            <div style="flex: 1; min-width: 250px; background-color: #f8f9fa; padding: 20px; border-radius: 8px;">
                <h3 style="margin: 0 0 15px 0; color: #1d1d1f; font-size: 16px; font-weight: 600;">
                    🧾 Rechnungsadresse
                </h3>
                <div style="color: #1d1d1f; line-height: 1.6;">
                    <?php echo yprint_format_address_html($email_data['billing']); ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Call-to-Action -->
    <div style="text-align: center; margin: 30px 0;">
        <a href="<?php echo esc_url(wc_get_account_endpoint_url('orders')); ?>" 
           style="background: linear-gradient(135deg, #0079FF 0%, #0056b3 100%); color: #FFFFFF; 
                  padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: 600; 
                  display: inline-block; font-size: 16px;">
            📋 Bestellung anzeigen
        </a>
    </div>
    
    <!-- Rechtliche Hinweise -->
    <div style="background-color: #F1F1F1; padding: 20px; border-radius: 8px; margin-top: 30px;">
        <h3 style="margin: 0 0 15px 0; color: #1d1d1f; font-size: 16px;">⚖️ Rechtliche Hinweise</h3>
        <p style="margin: 0 0 15px 0; color: #6e6e73; font-size: 13px; line-height: 1.5;">
            <strong>Widerrufsrecht:</strong> Sie haben das Recht, binnen 14 Tagen ohne Angabe von Gründen 
            diesen Vertrag zu widerrufen. Die vollständige 
            <a href="<?php echo esc_url(home_url('/widerruf')); ?>" style="color: #0079FF;">Widerrufsbelehrung</a> 
            finden Sie auf unserer Website.
        </p>
        <p style="margin: 0; color: #6e6e73; font-size: 13px; line-height: 1.5;">
            Mit der Bestellbestätigung kommt ein rechtsverbindlicher Kaufvertrag zustande. 
            Es gelten unsere <a href="<?php echo esc_url(home_url('/agb')); ?>" style="color: #0079FF;">AGB</a>.
        </p>
    </div>
    
    <?php
    error_log('✅ E-Mail HTML erfolgreich generiert für Order #' . $order_id);
    return ob_get_clean();
}

/**
 * Sammelt alle benötigten Daten für die E-Mail mit robusten Fallbacks
 *
 * @param WC_Order $order
 * @return array|null
 */
function yprint_collect_email_data($order) {
    $order_id = $order->get_id();
    
    // 1. ADRESSEN mit hierarchischen Fallbacks
    $addresses = yprint_get_email_addresses_with_fallbacks($order);
    if (empty($addresses)) {
        error_log('🔴 Keine gültigen Adressen für Order #' . $order_id);
        return null;
    }
    
    // 2. BESTELLINFORMATIONEN
    $order_data = [
        'order_number' => '#YP-' . date('Ymd', $order->get_date_created()->getTimestamp()) . '-' . $order->get_id(),
        'order_date' => yprint_format_german_date($order->get_date_created()),
        'status_text' => yprint_get_german_order_status($order->get_status()),
        'total_formatted' => $order->get_formatted_order_total(),
        'subtotal_formatted' => wc_price($order->get_subtotal()),
        'tax_total' => $order->get_total_tax(),
        'tax_formatted' => wc_price($order->get_total_tax()),
        'shipping_total' => $order->get_shipping_total(),
        'shipping_formatted' => wc_price($order->get_shipping_total()),
        'is_paid' => $order->is_paid()
    ];
    
    // 3. PAYMENT-INFORMATIONEN
$payment_data = yprint_get_payment_display_info($order);

// 4. ARTIKEL-DATEN
$items_data = yprint_get_email_items_data($order);

// 5. DATEN ZUSAMMENFÜHREN UND VALIDIEREN
$email_data = array_merge($addresses, $order_data, $payment_data, ['items' => $items_data]);

// 6. KRITISCHE VALIDIERUNG
if (!yprint_validate_email_data($email_data)) {
    error_log('🔴 E-Mail-Daten-Validierung fehlgeschlagen für Order #' . $order_id);
    return null;
}

error_log('✅ Vollständige E-Mail-Daten erfolgreich gesammelt und validiert für Order #' . $order_id);
return $email_data;
}

/**
 * Robuste Adressauflösung mit Prioritäten-System
 */
function yprint_get_email_addresses_with_fallbacks($order) {
    $order_id = $order->get_id();
    error_log('📍 Adressauflösung für Order #' . $order_id);
    
    // PRIORITÄT 1: Orchestrierte Adressen (korrekte Quelle)
    $email_shipping = $order->get_meta('_email_template_shipping_address');
    $email_billing = $order->get_meta('_email_template_billing_address');
    $addresses_ready = $order->get_meta('_email_template_addresses_ready');
    
    if ($addresses_ready && !empty($email_shipping['address_1']) && !empty($email_billing['address_1'])) {
        error_log('✅ Verwendet: Orchestrierte E-Mail-Adressen');
        return [
            'shipping' => $email_shipping,
            'billing' => $email_billing
        ];
    }
    
    // PRIORITÄT 2: Standard WooCommerce Fields (Fallback)
    $wc_shipping = [
        'first_name' => $order->get_shipping_first_name(),
        'last_name' => $order->get_shipping_last_name(),
        'company' => $order->get_shipping_company(),
        'address_1' => $order->get_shipping_address_1(),
        'address_2' => $order->get_shipping_address_2(),
        'city' => $order->get_shipping_city(),
        'postcode' => $order->get_shipping_postcode(),
        'country' => $order->get_shipping_country()
    ];
    
    $wc_billing = [
        'first_name' => $order->get_billing_first_name(),
        'last_name' => $order->get_billing_last_name(),
        'company' => $order->get_billing_company(),
        'address_1' => $order->get_billing_address_1(),
        'address_2' => $order->get_billing_address_2(),
        'city' => $order->get_billing_city(),
        'postcode' => $order->get_billing_postcode(),
        'country' => $order->get_billing_country(),
        'email' => $order->get_billing_email()
    ];
    
    if (!empty($wc_shipping['address_1']) && !empty($wc_billing['address_1'])) {
        error_log('⚠️ Verwendet: WooCommerce Standard-Felder (Fallback)');
        return [
            'shipping' => $wc_shipping,
            'billing' => $wc_billing
        ];
    }
    
    error_log('🔴 Keine gültigen Adressen gefunden');
    return null;
}

/**
 * Sammelt Artikel-Daten mit Design-Integration
 */
function yprint_get_email_items_data($order) {
    $items = [];
    
    foreach ($order->get_items() as $item_id => $item) {
        $design_id = $item->get_meta('_design_id');
        $has_design = $item->get_meta('_has_print_design') === 'yes';
        
        $item_data = [
            'name' => $item->get_name(),
            'quantity' => $item->get_quantity(),
            'total_formatted' => wc_price($item->get_total()),
            'unit_price_formatted' => wc_price($item->get_total() / $item->get_quantity()),
            'is_design_product' => $has_design
        ];
        
        if ($has_design && $design_id) {
            // Design-Produkt: Erweiterte Informationen
            $design_name = $item->get_meta('_design_name');
            $product_name = $item->get_meta('_db_design_product_name') ?: $item->get_name();
            
            $item_data['display_name'] = $design_name . ' - gedruckt auf ' . $product_name;
            $item_data['design_details'] = [
                'color' => $item->get_meta('_design_color'),
                'size' => $item->get_meta('_design_size')
            ];
        } else {
            // Standard-Produkt
            $item_data['display_name'] = $item->get_name();
        }
        
        $items[] = $item_data;
    }
    
    return $items;
}

/**
 * Payment-Informationen mit vollständiger Stripe-Integration
 */
function yprint_get_payment_display_info($order) {
    $base_payment_method = $order->get_payment_method_title();
    $payment_method = $base_payment_method; // Fallback
    
    // Stripe-spezifische Verbesserungen mit bewährter Logik
    if ($order->get_payment_method() === 'yprint_stripe') {
        $enhanced_method = yprint_get_stripe_payment_display_for_email($order);
        if ($enhanced_method) {
            $payment_method = $enhanced_method;
        }
    }
    
    error_log('🎯 Payment Display: Base="' . $base_payment_method . '", Enhanced="' . $payment_method . '"');
    
    return ['payment_method' => $payment_method];
}

/**
 * Ermittelt benutzerfreundliche Stripe Payment Method Darstellung für E-Mails
 * Nutzt die bewährte Logik aus yprint_get_payment_method_details_callback()
 */
function yprint_get_stripe_payment_display_for_email($order) {
    if (!$order || $order->get_payment_method() !== 'yprint_stripe') {
        return null;
    }
    
    $order_id = $order->get_id();
    error_log('🎯 Stripe Payment Display für E-Mail - Order #' . $order_id);
    
    // 1. PAYMENT METHOD ID ermitteln (gleiche Logik wie AJAX-Handler)
    $payment_method_id = $order->get_meta('_yprint_stripe_payment_method_id');
    
    // Fallback: Transaction ID könnte Payment Method ID sein
    if (empty($payment_method_id)) {
        $transaction_id = $order->get_transaction_id();
        if (strpos($transaction_id, 'pm_') === 0) {
            $payment_method_id = $transaction_id;
        } elseif (strpos($transaction_id, 'pi_') === 0) {
            // Payment Intent -> Payment Method ID extrahieren
            $payment_method_id = yprint_extract_payment_method_from_intent($transaction_id);
        }
    }
    
    if (empty($payment_method_id) || strpos($payment_method_id, 'pm_') !== 0) {
        error_log('🔴 Keine gültige Payment Method ID gefunden für Order #' . $order_id);
        return 'Stripe Payment'; // Basis-Fallback
    }
    
    error_log('✅ Payment Method ID gefunden: ' . $payment_method_id);
    
    // 2. STRIPE API CALL (gleiche Logik wie AJAX-Handler)
    $display_info = yprint_fetch_stripe_payment_method_display($payment_method_id);
    
    if ($display_info) {
        error_log('✅ Stripe Display ermittelt: ' . $display_info);
        return $display_info;
    }
    
    error_log('⚠️ Fallback auf Standard-Darstellung für Order #' . $order_id);
    return 'Kreditkarte (Stripe)'; // Robuster Fallback
}

/**
 * Extrahiert Payment Method ID aus Payment Intent (bewährte Logik)
 */
function yprint_extract_payment_method_from_intent($payment_intent_id) {
    if (!class_exists('YPrint_Stripe_API')) {
        error_log('🔴 YPrint_Stripe_API Klasse nicht verfügbar');
        return null;
    }
    
    try {
        $intent = YPrint_Stripe_API::request(array(), 'payment_intents/' . $payment_intent_id);
        if (!empty($intent->payment_method)) {
            error_log('✅ Payment Method aus Intent extrahiert: ' . $intent->payment_method);
            return $intent->payment_method;
        }
    } catch (Exception $e) {
        error_log('🔴 Fehler beim Abrufen der Payment Intent: ' . $e->getMessage());
    }
    
    return null;
}

/**
 * Ruft Stripe Payment Method Details ab und formatiert sie benutzerfreundlich
 * Basiert auf der bewährten Logik aus yprint_get_payment_method_details_callback()
 */
function yprint_fetch_stripe_payment_method_display($payment_method_id) {
    if (!class_exists('YPrint_Stripe_API')) {
        error_log('🔴 YPrint_Stripe_API Klasse nicht verfügbar');
        return null;
    }
    
    try {
        // API-Call (gleiche Logik wie AJAX-Handler)
        $payment_method = YPrint_Stripe_API::request(array(), 'payment_methods/' . $payment_method_id);
        
        if (empty($payment_method) || isset($payment_method->error)) {
            error_log('🔴 Stripe Payment Method nicht gefunden oder Fehler: ' . $payment_method_id);
            return null;
        }
        
        // Formatierung (gleiche Logik wie AJAX-Handler)
        $display_method = 'Stripe Payment';
        
        if ($payment_method->type === 'card' && !empty($payment_method->card)) {
            $card = $payment_method->card;
            $brand = ucfirst($card->brand);
            $last4 = $card->last4;
            $display_method = $brand . ' ****' . $last4;
            
        } elseif ($payment_method->type === 'sepa_debit' && !empty($payment_method->sepa_debit)) {
            $sepa = $payment_method->sepa_debit;
            $last4 = $sepa->last4;
            $display_method = 'SEPA-Lastschrift ****' . $last4;
        }
        
        error_log('✅ Stripe Payment Method formatiert: ' . $display_method);
        return $display_method;
        
    } catch (Exception $e) {
        error_log('🔴 Stripe API Fehler: ' . $e->getMessage());
        return null;
    }
}

/**
 * Validiert und bereinigt E-Mail-Daten vor der Template-Generierung
 */
function yprint_validate_email_data($email_data) {
    $order_id = $email_data['order_number'] ?? 'UNKNOWN';
    error_log('🔍 Validiere E-Mail-Daten für Order: ' . $order_id);
    
    // Kritische Felder prüfen
    $required_fields = ['shipping', 'billing', 'items', 'payment_method', 'total_formatted'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($email_data[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (!empty($missing_fields)) {
        error_log('🔴 Fehlende E-Mail-Daten: ' . implode(', ', $missing_fields));
        return false;
    }
    
    // Adressen-Validierung
    if (empty($email_data['shipping']['address_1']) || empty($email_data['billing']['address_1'])) {
        error_log('🔴 Unvollständige Adressen in E-Mail-Daten');
        return false;
    }
    
    error_log('✅ E-Mail-Daten-Validierung erfolgreich');
    return true;
}

/**
 * Helper: Adresse als HTML formatieren
 */
function yprint_format_address_html($address) {
    $lines = [];
    
    if (!empty($address['first_name']) || !empty($address['last_name'])) {
        $lines[] = esc_html(trim(($address['first_name'] ?? '') . ' ' . ($address['last_name'] ?? '')));
    }
    if (!empty($address['company'])) {
        $lines[] = esc_html($address['company']);
    }
    if (!empty($address['address_1'])) {
        $lines[] = esc_html($address['address_1']);
    }
    if (!empty($address['address_2'])) {
        $lines[] = esc_html($address['address_2']);
    }
    if (!empty($address['postcode']) || !empty($address['city'])) {
        $lines[] = esc_html(trim(($address['postcode'] ?? '') . ' ' . ($address['city'] ?? '')));
    }
    if (!empty($address['country'])) {
        $lines[] = esc_html($address['country']);
    }
    
    return !empty($lines) ? implode('<br>', $lines) : 'Keine Adresse verfügbar';
}

/**
 * Helper: Deutsche Datumsformatierung
 */
function yprint_format_german_date($date) {
    $german_months = [
        1 => 'Januar', 2 => 'Februar', 3 => 'März', 4 => 'April',
        5 => 'Mai', 6 => 'Juni', 7 => 'Juli', 8 => 'August',
        9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Dezember'
    ];
    
    return $date->format('d') . '. ' . $german_months[(int)$date->format('m')] . ' ' . $date->format('Y');
}

/**
 * Helper: Deutsche Status-Übersetzung
 */
function yprint_get_german_order_status($status) {
    $translations = [
        'pending' => 'Ausstehend',
        'processing' => 'In Bearbeitung', 
        'completed' => 'Abgeschlossen',
        'on-hold' => 'In Warteschleife',
        'cancelled' => 'Storniert',
        'refunded' => 'Erstattet',
        'failed' => 'Fehlgeschlagen'
    ];
    
    return $translations[$status] ?? 'Unbekannt';
}

/**
 * Fallback für kritische Fehler
 */
function yprint_build_fallback_email_content($order) {
    error_log('🆘 Generiere Fallback-E-Mail für Order #' . $order->get_id());
    
    ob_start();
    ?>
    <div style="padding: 20px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
        <h2 style="color: #856404;">Bestellbestätigung</h2>
        <p>Ihre Bestellung wurde erfolgreich aufgegeben.</p>
        <p><strong>Bestellnummer:</strong> <?php echo esc_html($order->get_order_number()); ?></p>
        <p><strong>Gesamtbetrag:</strong> <?php echo $order->get_formatted_order_total(); ?></p>
        <p>Weitere Details finden Sie in Ihrem Kundenkonto.</p>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Error-E-Mail für kritische Systemfehler
 */
function yprint_build_error_email_content() {
    error_log('🆘 Generiere Error-E-Mail - kritischer Systemfehler');
    return '<div style="padding: 20px; color: red;">Es ist ein Fehler bei der E-Mail-Generierung aufgetreten.</div>';
}