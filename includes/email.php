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
 *
 * @param WC_Order $order Das WooCommerce Bestellobjekt
 * @return string Der HTML-Inhalt
 */
function yprint_build_order_confirmation_content($order) {
    $order_id = $order->get_id();
    error_log('🎯 === E-MAIL CONTENT BUILD START - Order #' . $order_id . ' ===');
    
    // 🔥 KRITISCH: Nutze orchestrierte Adressen für E-Mail-Templates
    $shipping_address = $order->get_meta('_email_template_shipping_address');
    $billing_address = $order->get_meta('_email_template_billing_address');
    $addresses_ready = $order->get_meta('_email_template_addresses_ready');
    $template_timestamp = $order->get_meta('_email_template_timestamp');
    
    error_log('🔍 E-Mail Meta Check - addresses_ready: ' . ($addresses_ready ? 'TRUE' : 'FALSE'));
    error_log('🔍 E-Mail Meta Check - shipping_address empty: ' . (empty($shipping_address) ? 'TRUE' : 'FALSE'));
    error_log('🔍 E-Mail Meta Check - billing_address empty: ' . (empty($billing_address) ? 'TRUE' : 'FALSE'));
    error_log('🔍 E-Mail Meta Check - template_timestamp: ' . ($template_timestamp ?: 'NICHT GESETZT'));
    
    // ROBUSTE RETRY-LOGIK für Meta-Daten-Abruf
$max_retries = 3;
$retry_count = 0;

while ($retry_count < $max_retries && (!$addresses_ready || empty($shipping_address) || empty($billing_address))) {
    if ($retry_count > 0) {
        error_log('🔄 YPrint Email: RETRY #' . $retry_count . ' - Warte auf AddressOrchestrator Meta-Daten...');
        usleep(500000); // 0.5 Sekunden warten
    }
    
    // Meta-Daten erneut abrufen
    $shipping_address = $order->get_meta('_email_template_shipping_address');
    $billing_address = $order->get_meta('_email_template_billing_address');
    $addresses_ready = $order->get_meta('_email_template_addresses_ready');
    
    $retry_count++;
}

// Prüfung nach Retry-Loop
if ($addresses_ready && !empty($shipping_address) && !empty($billing_address)) {
    error_log('✅ YPrint Email: SUCCESS - Nutze orchestrierte Adressen (nach ' . $retry_count . ' Versuchen)');
    error_log('✅ Orchestrierte Shipping: ' . ($shipping_address['first_name'] ?? '') . ' ' . ($shipping_address['last_name'] ?? '') . ', ' . ($shipping_address['address_1'] ?? ''));
    error_log('✅ Orchestrierte Billing: ' . ($billing_address['first_name'] ?? '') . ' ' . ($billing_address['last_name'] ?? '') . ', ' . ($billing_address['address_1'] ?? ''));
} else {
    error_log('🔴 YPrint Email: KRITISCHER FALLBACK nach ' . $max_retries . ' Versuchen - Nutze Standard Order-Adressen');
    error_log('🔴 URSACHE: AddressOrchestrator Meta-Daten nach ' . ($retry_count - 1) . ' Retries nicht verfügbar für Order #' . $order_id);
    
    // Fallback auf Standard-Order-Felder mit Warnung
    $shipping_address = [
        'first_name' => $order->get_shipping_first_name(),
        'last_name' => $order->get_shipping_last_name(),
        'address_1' => $order->get_shipping_address_1(),
        'address_2' => $order->get_shipping_address_2(),
        'city' => $order->get_shipping_city(),
        'postcode' => $order->get_shipping_postcode(),
        'country' => $order->get_shipping_country()
    ];
    $billing_address = [
        'first_name' => $order->get_billing_first_name(),
        'last_name' => $order->get_billing_last_name(),
        'address_1' => $order->get_billing_address_1(),
        'address_2' => $order->get_billing_address_2(),
        'city' => $order->get_billing_city(),
        'postcode' => $order->get_billing_postcode(),
        'country' => $order->get_billing_country()
    ];
    
    // Admin-Benachrichtigung bei kritischem Fallback
    $order->add_order_note('⚠️ E-Mail mit Fallback-Adressen versendet - AddressOrchestrator Meta-Daten nicht verfügbar', 0, false);
    
    error_log('🔍 Fallback Shipping: ' . ($shipping_address['first_name'] ?? '') . ' ' . ($shipping_address['last_name'] ?? '') . ', ' . ($shipping_address['address_1'] ?? ''));
    error_log('🔍 Fallback Billing: ' . ($billing_address['first_name'] ?? '') . ' ' . ($billing_address['last_name'] ?? '') . ', ' . ($billing_address['address_1'] ?? ''));
}
    
    error_log('🎯 === E-MAIL CONTENT BUILD END - Order #' . $order_id . ' ===');
    
    ob_start();
    $accent_color = '#007BFF'; // A clean, modern blue
    $text_color_dark = '#333333';
    $text_color_light = '#666666';
    $border_color = '#EEEEEE';
    ?>
    <div style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol'; line-height: 1.6; color: <?php echo esc_attr($text_color_dark); ?>; max-width: 600px; margin: 20px auto; padding: 20px;">


        <div style="background-color: #FFFFFF; padding: 30px; margin-top: 20px; border: 1px solid <?php echo esc_attr($border_color); ?>; border-radius: 8px;">
            <h2 style="margin: 0 0 25px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 22px; font-weight: 600;">
                Ihre Bestelldetails
            </h2>
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="font-size: 16px;">
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid <?php echo esc_attr($border_color); ?>; color: <?php echo esc_attr($text_color_light); ?>;">Bestellnummer:</td>
                    <td style="padding: 10px 0; border-bottom: 1px solid <?php echo esc_attr($border_color); ?>; text-align: right; color: <?php echo esc_attr($text_color_dark); ?>; font-weight: 600;">#<?php echo esc_html($order->get_order_number()); ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid <?php echo esc_attr($border_color); ?>; color: <?php echo esc_attr($text_color_light); ?>;">Bestelldatum:</td>
                    <td style="padding: 10px 0; border-bottom: 1px solid <?php echo esc_attr($border_color); ?>; text-align: right; color: <?php echo esc_attr($text_color_dark); ?>;"><?php echo esc_html($order->get_date_created()->format('d.m.Y H:i')); ?></td>
                </tr>
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid <?php echo esc_attr($border_color); ?>; color: <?php echo esc_attr($text_color_light); ?>;">Status:</td>
                    <td style="padding: 10px 0; border-bottom: 1px solid <?php echo esc_attr($border_color); ?>; text-align: right;">
                        <span style="background-color: #D4EDDA; color: #155724; padding: 5px 12px; border-radius: 20px; font-size: 13px; font-weight: 600;"><?php echo esc_html(wc_get_order_status_name($order->get_status())); ?></span>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 15px 0 5px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-weight: 700; font-size: 18px;">Gesamtbetrag:</td>
                    <td style="padding: 15px 0 5px 0; text-align: right; color: <?php echo esc_attr($accent_color); ?>; font-weight: 700; font-size: 24px;"><?php echo wp_kses_post($order->get_formatted_order_total()); ?></td>
                </tr>
            </table>
        </div>

        <div style="background-color: #FFFFFF; padding: 30px; margin-top: 20px; border: 1px solid <?php echo esc_attr($border_color); ?>; border-radius: 8px;">
            <h2 style="margin: 0 0 25px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 22px; font-weight: 600;">
                Ihre bestellten Artikel
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
                <div style="border-bottom: 1px solid <?php echo esc_attr($border_color); ?>; padding-bottom: 20px; margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <h3 style="margin: 0 0 5px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 17px; font-weight: 600; line-height: 1.4;">
                                <?php echo $display_name; ?>
                            </h3>
                            <?php if (!empty($design_details)): ?>
                            <div style="margin-bottom: 8px;">
                                <?php foreach ($design_details as $detail): ?>
                                <span style="background-color: <?php echo esc_attr($accent_color); ?>; color: white; padding: 4px 10px; border-radius: 15px; font-size: 12px; font-weight: 500; margin-right: 8px; display: inline-block;">
                                    <?php echo esc_html($detail); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <p style="margin: 0; color: <?php echo esc_attr($text_color_light); ?>; font-size: 14px;">
                                Menge: <?php echo esc_html($item->get_quantity()); ?> × <?php echo wp_kses_post(wc_price($individual_price)); ?>
                            </p>
                        </div>
                        <div style="text-align: right; margin-left: 20px;">
                            <div style="color: <?php echo esc_attr($text_color_dark); ?>; font-weight: 700; font-size: 18px;">
                                <?php echo wp_kses_post(wc_price($item->get_total())); ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>

        <div style="display: flex; flex-wrap: wrap; justify-content: space-between; margin-top: 20px;">
            <?php if ($order->has_shipping_address()) : ?>
            <div style="background-color: #FFFFFF; padding: 25px; border: 1px solid <?php echo esc_attr($border_color); ?>; border-radius: 8px; width: 48%; box-sizing: border-box; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 18px; font-weight: 600;">
                    Lieferadresse
                </h3>
                <div style="color: <?php echo esc_attr($text_color_light); ?>; line-height: 1.6; font-size: 15px;">
                    <?php echo wp_kses_post($order->get_formatted_shipping_address()); ?>
                </div>
            </div>
            <?php endif; ?>

            <div style="background-color: #FFFFFF; padding: 25px; border: 1px solid <?php echo esc_attr($border_color); ?>; border-radius: 8px; width: 48%; box-sizing: border-box; margin-bottom: 20px;">
                <h3 style="margin: 0 0 15px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 18px; font-weight: 600;">
                    Rechnungsadresse
                </h3>
                <div style="color: <?php echo esc_attr($text_color_light); ?>; line-height: 1.6; font-size: 15px;">
                    <?php echo wp_kses_post($order->get_formatted_billing_address()); ?>
                </div>
            </div>
        </div>

        <div style="background-color: #FFFFFF; padding: 30px; margin-top: 20px; border: 1px solid <?php echo esc_attr($border_color); ?>; border-radius: 8px; text-align: center;">
            <h3 style="margin: 0 0 15px 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 20px; font-weight: 700;">
                Was passiert als Nächstes?
            </h3>
            <p style="margin: 0 0 20px 0; color: <?php echo esc_attr($text_color_light); ?>; line-height: 1.6; font-size: 16px;">
                Wir bearbeiten Ihre Bestellung innerhalb der <strong>nächsten 24 Stunden</strong> und senden Ihnen eine Versandbestätigung mit Tracking-Informationen.
            </p>
            <p style="margin: 0; color: <?php echo esc_attr($text_color_dark); ?>; font-size: 15px; line-height: 1.5;">
                Haben Sie Fragen? Kontaktieren Sie uns jederzeit unter Angabe Ihrer Bestellnummer <strong style="color: <?php echo esc_attr($accent_color); ?>;">#<?php echo esc_html($order->get_order_number()); ?></strong>.
            </p>
        </div>

        <div style="text-align: center; padding: 30px 20px; margin-top: 30px; border-top: 1px solid <?php echo esc_attr($border_color); ?>;">
            <p style="margin: 0; font-size: 16px; color: <?php echo esc_attr($text_color_light); ?>;">
                Vielen Dank für Ihr Vertrauen!
            </p>
            <p style="margin: 10px 0 0 0; font-size: 18px; font-weight: 600; color: <?php echo esc_attr($accent_color); ?>;">
                Ihr YPrint Team
            </p>
        </div>
    </div>
    <?php
    return ob_get_clean();
}