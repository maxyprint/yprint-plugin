<?php
/**
 * Email functions for YPrint
 * VOLLSTÄNDIG ÜBERARBEITET mit AddressOrchestrator-Integration
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
 * @param bool $show_greeting Optional: Zeigt die Begrüßung an (Standard: true)
 * @return string Die formatierte E-Mail-Nachricht
 */
function yprint_get_email_template($title, $username, $content, $show_greeting = true) {
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
                            <img src="https://yprint.de/wp-content/uploads/2025/02/120225-logo.svg" alt="YPrint Logo" style="height: 40px; width: auto;" />
                        </td>
                    </tr>
                    
                    <!-- Begrüßung -->
                    <?php if ($show_greeting): ?>
                    <tr>
                        <td style="padding: 0 30px;">
                            <p style="margin: 0 0 20px 0; color: #6e6e73; font-size: 16px;">
                                Hallo <?php echo esc_html($username); ?>,<br>
                                danke für deine Bestellung!
                            </p>
                        </td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Hauptinhalt -->
                    <tr>
                        <td style="padding: 0 30px 30px 30px;">
                            <?php echo $content; ?>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 20px 30px; border-top: 1px solid #e5e5e5; background-color: #F8F9FA;">
                            <p style="margin: 0; color: #6e6e73; font-size: 12px; text-align: center;">
                                © <?php echo date('Y'); ?> yprint – Alle Rechte vorbehalten.<br>
                                <a href="<?php echo esc_url(home_url('/agb')); ?>" style="color: #0079FF;">AGB</a> · 
                                <a href="<?php echo esc_url(home_url('/widerruf')); ?>" style="color: #0079FF;">Widerruf</a> · 
                                <a href="<?php echo esc_url(home_url('/datenschutz')); ?>" style="color: #0079FF;">Datenschutz</a> · 
                                <a href="<?php echo esc_url(home_url('/impressum')); ?>" style="color: #0079FF;">Impressum</a>
                            </p>
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
 * Erstellt den Inhalt für die Bestellbestätigungsmail (OHNE HTML-Wrapper)
 * Wird in yprint_get_email_template() als Content eingebettet
 * ROBUSTE AddressOrchestrator-Integration mit Fallbacks
 *
 * @param WC_Order $order Das WooCommerce Bestellobjekt
 * @return string Der Content-Inhalt für die E-Mail (ohne äußere HTML-Struktur)
 */
function yprint_build_order_confirmation_content($order) {
    if (!$order || !is_a($order, 'WC_Order')) {
        error_log('🔴 YPrint Email: Ungültiges Order-Objekt für Content-Build');
        return yprint_build_error_email_content();
    }
    
    $order_id = $order->get_id();
    error_log('🎯 === E-MAIL CONTENT BUILD START - Order #' . $order_id . ' ===');
    
    // 📊 SCHRITT 1: Warten auf AddressOrchestrator Meta-Daten mit Retry-Mechanismus
    $email_data = yprint_collect_email_data_with_retry($order);
    
    if (empty($email_data)) {
        error_log('🔴 Kritischer Fehler: Keine E-Mail-Daten verfügbar für Order #' . $order_id);
        return yprint_build_simple_fallback_content($order);
    }
    
    error_log('✅ E-Mail-Daten gesammelt: Shipping=' . ($email_data['shipping']['address_1'] ?? 'FEHLT') . 
              ', Items=' . count($email_data['items']));

    // === ALLE BENÖTIGTEN VARIABLEN VORBEREITEN ===
    $order_number = esc_html($email_data['order_number']);
    $order_date = esc_html($email_data['order_date']);
    $status_text = esc_html($email_data['status_text']);
    $payment_method = esc_html($email_data['payment_method']);
    $is_paid = $email_data['is_paid'];
    $subtotal_formatted = $email_data['subtotal_formatted'];
    $tax_total = $email_data['tax_total'];
    $tax_formatted = $email_data['tax_formatted'];
    $shipping_total = $email_data['shipping_total'];
    $shipping_formatted = $email_data['shipping_formatted'];
    $total_formatted = $email_data['total_formatted'];
    $shipping = $email_data['shipping'];
    $billing = $email_data['billing'];
    $items = $email_data['items'];

    // === TEMPLATE ===
    ob_start();
    ?>
    <!-- Bestellstatus Header -->
    <div style="background: linear-gradient(135deg, #0079FF 0%, #0056b3 100%); padding: 20px; text-align: center; border-radius: 8px; margin-bottom: 20px;">
        <h2 style="color: #FFFFFF; margin: 0; font-size: 24px; font-weight: 600;">
            ✅ Bestellung erfolgreich aufgegeben!
        </h2>
        <p style="color: #E6F3FF; margin: 10px 0 0 0; font-size: 14px;">
            Vielen Dank für Ihre Bestellung bei YPrint
        </p>
    </div>
    
    <!-- Bestellinformationen -->
    <div style="background-color: #F8F9FA; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #1d1d1f; font-size: 18px; border-bottom: 1px solid #e5e5e5; padding-bottom: 8px;">
            📋 Ihre Bestelldetails
        </h3>
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 5px 0; color: #6e6e73; font-weight: 500;">Bestellnummer:</td>
                <td style="padding: 5px 0; text-align: right; font-weight: 600; color: #1d1d1f;">
                    <?= $order_number ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 5px 0; color: #6e6e73; font-weight: 500;">Bestelldatum:</td>
                <td style="padding: 5px 0; text-align: right; color: #1d1d1f;">
                    <?= $order_date ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 5px 0; color: #6e6e73; font-weight: 500;">Status:</td>
                <td style="padding: 5px 0; text-align: right; color: #28a745; font-weight: 600;">
                    <?= $status_text ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 5px 0; color: #6e6e73; font-weight: 500;">Zahlungsart:</td>
                <td style="padding: 5px 0; text-align: right; color: #1d1d1f;">
                    <?= $payment_method ?>
                    <?php if ($is_paid): ?>
                        <span style="color: #28a745; font-weight: 600;"> ✓ Bezahlt</span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Bestellte Artikel -->
    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #1d1d1f; font-size: 18px; border-bottom: 1px solid #e5e5e5; padding-bottom: 8px;">
            🛍️ Ihre bestellten Artikel
        </h3>
        
        <?php foreach ($items as $item): ?>
        <div style="background-color: #FFFFFF; border: 1px solid #e5e5e5; border-radius: 6px; padding: 15px; margin-bottom: 10px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap;">
                <div style="flex: 1; min-width: 200px;">
                    <h4 style="margin: 0 0 8px 0; color: #1d1d1f; font-size: 16px; font-weight: 600;">
                        <?= esc_html($item['display_name']) ?>
                    </h4>
                    
                    <?php if (!empty($item['is_design_product']) && !empty($item['design_details'])): ?>
                    <div style="background-color: #E6F3FF; padding: 8px; border-radius: 4px; margin: 8px 0;">
                        <p style="margin: 0; color: #0079FF; font-weight: 600; font-size: 12px;">🎨 Design-Details:</p>
                        <?php if (!empty($item['design_details']['color'])): ?>
                        <p style="margin: 3px 0 0 0; color: #1d1d1f; font-size: 12px;">
                            Farbe: <?= esc_html($item['design_details']['color']) ?>
                        </p>
                        <?php endif; ?>
                        <?php if (!empty($item['design_details']['size'])): ?>
                        <p style="margin: 3px 0 0 0; color: #1d1d1f; font-size: 12px;">
                            Größe: <?= esc_html($item['design_details']['size']) ?>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <p style="margin: 3px 0 0 0; color: #6e6e73; font-size: 12px;">
                        Menge: <?= esc_html($item['quantity']) ?> Stück
                    </p>
                </div>
                
                <div style="text-align: right; margin-left: 15px;">
                    <p style="margin: 0; color: #6e6e73; font-size: 12px;">Einzelpreis</p>
                    <p style="margin: 3px 0; color: #1d1d1f; font-size: 14px;">
                        <?= $item['unit_price_formatted'] ?>
                    </p>
                    <p style="margin: 8px 0 0 0; color: #0079FF; font-size: 16px; font-weight: 600;">
                        Gesamt: <?= $item['total_formatted'] ?>
                    </p>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    
    <!-- Gesamtsumme -->
    <div style="background-color: #F8F9FA; padding: 15px; border-radius: 8px; border: 2px solid #0079FF; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 3px 0; color: #6e6e73;">Zwischensumme:</td>
                <td style="padding: 3px 0; text-align: right; color: #1d1d1f;">
                    <?= $subtotal_formatted ?>
                </td>
            </tr>
            <?php if ($tax_total > 0): ?>
            <tr>
                <td style="padding: 3px 0; color: #6e6e73;">MwSt. (19%):</td>
                <td style="padding: 3px 0; text-align: right; color: #1d1d1f;">
                    <?= $tax_formatted ?>
                </td>
            </tr>
            <?php endif; ?>
            <?php if ($shipping_total > 0): ?>
            <tr>
                <td style="padding: 3px 0; color: #6e6e73;">Versand:</td>
                <td style="padding: 3px 0; text-align: right; color: #1d1d1f;">
                    <?= $shipping_formatted ?>
                </td>
            </tr>
            <?php else: ?>
            <tr>
                <td style="padding: 3px 0; color: #6e6e73;">Versand:</td>
                <td style="padding: 3px 0; text-align: right; color: #28a745; font-weight: 600;">Kostenlos</td>
            </tr>
            <?php endif; ?>
            <tr style="border-top: 2px solid #e5e5e5;">
                <td style="padding: 12px 0 5px 0; color: #1d1d1f; font-size: 18px; font-weight: 700;">Gesamtbetrag:</td>
                <td style="padding: 12px 0 5px 0; text-align: right; color: #0079FF; font-size: 20px; font-weight: 700;">
                    <?= $total_formatted ?>
                </td>
            </tr>
        </table>
    </div>
    
    <!-- Adressen (aus AddressOrchestrator) -->
    <div style="margin-bottom: 20px;">
        <h3 style="margin: 0 0 15px 0; color: #1d1d1f; font-size: 18px; border-bottom: 1px solid #e5e5e5; padding-bottom: 8px;">
            📍 Adressinformationen
        </h3>
        
        <div style="display: flex; flex-wrap: wrap; gap: 15px;">
            <!-- Lieferadresse -->
            <div style="flex: 1; min-width: 250px; background-color: #f8f9fa; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #1d1d1f; font-size: 14px; font-weight: 600;">
                    🚚 Lieferadresse
                </h4>
                <div style="color: #1d1d1f; line-height: 1.5; font-size: 13px;">
                    <?= yprint_format_address_html($shipping) ?>
                </div>
            </div>
            
            <!-- Rechnungsadresse -->
            <div style="flex: 1; min-width: 250px; background-color: #f8f9fa; padding: 15px; border-radius: 6px;">
                <h4 style="margin: 0 0 10px 0; color: #1d1d1f; font-size: 14px; font-weight: 600;">
                    🧾 Rechnungsadresse
                </h4>
                <div style="color: #1d1d1f; line-height: 1.5; font-size: 13px;">
                    <?= yprint_format_address_html($billing) ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Call-to-Action -->
    <div style="text-align: center; margin-bottom: 20px;">
        <a href="<?= esc_url(wc_get_account_endpoint_url('orders')) ?>" 
           style="background: linear-gradient(135deg, #0079FF 0%, #0056b3 100%); color: #FFFFFF; 
                  padding: 12px 25px; text-decoration: none; border-radius: 6px; font-weight: 600; 
                  display: inline-block; font-size: 14px;">
            📋 Bestellung anzeigen
        </a>
    </div>
    
    <!-- Rechtliche Hinweise -->
    <div style="background-color: #F1F1F1; padding: 15px; border-radius: 6px; margin-top: 20px;">
        <h4 style="margin: 0 0 10px 0; color: #1d1d1f; font-size: 14px;">⚖️ Rechtliche Hinweise</h4>
        <p style="margin: 0 0 10px 0; color: #6e6e73; font-size: 12px; line-height: 1.4;">
            <strong>Widerrufsrecht:</strong> Sie haben das Recht, binnen 14 Tagen ohne Angabe von Gründen 
            diesen Vertrag zu widerrufen. Die vollständige 
            <a href="<?= esc_url(home_url('/widerruf')) ?>" style="color: #0079FF;">Widerrufsbelehrung</a> 
            finden Sie auf unserer Website.
        </p>
        <p style="margin: 0; color: #6e6e73; font-size: 12px; line-height: 1.4;">
            Mit der Bestellbestätigung kommt ein rechtsverbindlicher Kaufvertrag zustande. 
            Es gelten unsere <a href="<?= esc_url(home_url('/agb')) ?>" style="color: #0079FF;">AGB</a>.
        </p>
    </div>
    <?php
    error_log('✅ E-Mail CONTENT erfolgreich generiert für Order #' . $order_id);
    return ob_get_clean();
}

/**
 * ROBUSTE Datensammlung mit Retry-Mechanismus für AddressOrchestrator Meta-Daten
 * 
 * @param WC_Order $order
 * @return array|null
 */
function yprint_collect_email_data_with_retry($order) {
    $order_id = $order->get_id();
    $max_retries = 5;
    $retry_delay = 2; // Sekunden
    
    for ($attempt = 1; $attempt <= $max_retries; $attempt++) {
        error_log("🔄 Email Data Collection - Versuch {$attempt}/{$max_retries} für Order #{$order_id}");
        
        // Versuche Datensammlung
        $email_data = yprint_collect_email_data($order);
        
        // Prüfe ob kritische Daten vorhanden sind
        if (!empty($email_data) && 
            !empty($email_data['shipping']['address_1']) && 
            !empty($email_data['billing']['address_1'])) {
            
            error_log("✅ Email Daten erfolgreich gesammelt bei Versuch {$attempt}");
            return $email_data;
        }
        
        // Prüfe ob AddressOrchestrator Meta-Daten noch nicht bereit sind
        $addresses_ready = $order->get_meta('_email_template_addresses_ready');
        if (!$addresses_ready && $attempt < $max_retries) {
            error_log("⏳ AddressOrchestrator Meta-Daten nicht bereit - warte {$retry_delay}s...");
            sleep($retry_delay);
            
            // Order-Objekt neu laden für frische Meta-Daten
            $order = wc_get_order($order_id);
            continue;
        }
        
        error_log("🔴 Email Daten unvollständig bei Versuch {$attempt}");
    }
    
    error_log("🔴 KRITISCH: Email Daten nach {$max_retries} Versuchen immer noch unvollständig");
    return null;
}

/**
 * Sammelt alle benötigten Daten für die E-Mail mit robusten Fallbacks
 * VOLLSTÄNDIGE AddressOrchestrator-Integration
 *
 * @param WC_Order $order
 * @return array|null
 */
function yprint_collect_email_data($order) {
    $order_id = $order->get_id();
    
    // 1. ADRESSEN mit hierarchischen Fallbacks (AddressOrchestrator → WooCommerce)
    $addresses = yprint_get_email_addresses_with_orchestrator_fallbacks($order);
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
    
    // 3. PAYMENT-INFORMATIONEN mit vollständiger Stripe-Integration
    $payment_data = yprint_get_payment_display_info($order);
    
    // 4. ARTIKEL-DATEN mit Design-Integration
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
 * Robuste Adressauflösung mit AddressOrchestrator-Priorität und hierarchischen Fallbacks
 * 
 * @param WC_Order $order
 * @return array|null
 */
function yprint_get_email_addresses_with_orchestrator_fallbacks($order) {
    $order_id = $order->get_id();
    error_log('📍 AddressOrchestrator Adressauflösung für Order #' . $order_id);
    
    // PRIORITÄT 1: AddressOrchestrator E-Mail-Template Meta-Daten (AUTORITATIVE QUELLE)
    $email_shipping = $order->get_meta('_email_template_shipping_address');
    $email_billing = $order->get_meta('_email_template_billing_address');
    $addresses_ready = $order->get_meta('_email_template_addresses_ready');
    
    if ($addresses_ready && !empty($email_shipping['address_1']) && !empty($email_billing['address_1'])) {
        error_log('✅ Verwendet: AddressOrchestrator E-Mail-Template Meta-Daten (beste Quelle)');
        return [
            'shipping' => $email_shipping,
            'billing' => $email_billing,
            'source' => 'orchestrator_email_template'
        ];
    }
    
    // PRIORITÄT 2: AddressOrchestrator Stripe Meta-Daten
    $stripe_shipping = $order->get_meta('_stripe_display_shipping_address');
    $stripe_billing = $order->get_meta('_stripe_display_billing_address');
    
    if (!empty($stripe_shipping['address_1']) && !empty($stripe_billing['address_1'])) {
        error_log('✅ Verwendet: AddressOrchestrator Stripe Meta-Daten');
        return [
            'shipping' => $stripe_shipping,
            'billing' => $stripe_billing,
            'source' => 'orchestrator_stripe'
        ];
    }
    
    // PRIORITÄT 3: Session-Daten (während/kurz nach Checkout)
    if (WC()->session) {
        $session_shipping = WC()->session->get('yprint_selected_address');
        $session_billing = WC()->session->get('yprint_billing_address');
        $billing_different = WC()->session->get('yprint_billing_address_different', false);
        
        if (!empty($session_shipping['address_1'])) {
            $final_billing = ($billing_different && !empty($session_billing['address_1'])) 
                ? $session_billing 
                : $session_shipping;
                
            error_log('✅ Verwendet: YPrint Session-Adressen');
            return [
                'shipping' => $session_shipping,
                'billing' => $final_billing,
                'source' => 'yprint_session'
            ];
        }
    }
    
    // PRIORITÄT 4: Standard WooCommerce Order Felder (Fallback)
    $wc_shipping = [
        'first_name' => $order->get_shipping_first_name(),
        'last_name' => $order->get_shipping_last_name(),
        'company' => $order->get_shipping_company(),
        'address_1' => $order->get_shipping_address_1(),
        'address_2' => $order->get_shipping_address_2(),
        'city' => $order->get_shipping_city(),
        'postcode' => $order->get_shipping_postcode(),
        'country' => $order->get_shipping_country(),
        'phone' => $order->get_shipping_phone()
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
        'phone' => $order->get_billing_phone(),
        'email' => $order->get_billing_email()
    ];
    
    if (!empty($wc_shipping['address_1']) && !empty($wc_billing['address_1'])) {
        error_log('⚠️ Verwendet: WooCommerce Standard-Felder (Fallback)');
        return [
            'shipping' => $wc_shipping,
            'billing' => $wc_billing,
            'source' => 'woocommerce_fallback'
        ];
    }
    
    error_log('🔴 FEHLER: Keine gültigen Adressen gefunden für Order #' . $order_id);
    return null;
}

/**
 * Payment-Informationen mit vollständiger Stripe-Integration
 * Nutzt die bewährte Logik aus yprint_get_payment_method_details_callback()
 * 
 * @param WC_Order $order
 * @return array
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
 * 
 * @param WC_Order $order
 * @return string|null
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
            // Payment Intent → Payment Method ID extrahieren
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
 * 
 * @param string $payment_intent_id
 * @return string|null
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
 * 
 * @param string $payment_method_id
 * @return string|null
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
 * Sammelt Artikel-Daten mit vollständiger Design-Integration
 * 
 * @param WC_Order $order
 * @return array
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
 * Validiert und bereinigt E-Mail-Daten vor der Template-Generierung
 * 
 * @param array $email_data
 * @return bool
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
 * Helper: Adresse als HTML formatieren (E-Mail-Client-kompatibel)
 * 
 * @param array $address
 * @return string
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
 * 
 * @param WC_DateTime $date
 * @return string
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
 * 
 * @param string $status
 * @return string
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
 * Einfacher Fallback-Content (OHNE äußere HTML-Struktur)
 * 
 * @param WC_Order $order
 * @return string
 */
function yprint_build_simple_fallback_content($order) {
    error_log('🆘 Generiere einfachen Fallback-Content für Order #' . $order->get_id());
    
    ob_start();
    ?>
    <div style="padding: 20px; background-color: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px;">
        <h2 style="color: #856404; margin: 0 0 15px 0;">Bestellbestätigung</h2>
        <p style="margin: 0 0 10px 0;">Ihre Bestellung wurde erfolgreich aufgegeben.</p>
        <p style="margin: 0 0 10px 0;"><strong>Bestellnummer:</strong> <?php echo esc_html($order->get_order_number()); ?></p>
        <p style="margin: 0 0 10px 0;"><strong>Gesamtbetrag:</strong> <?php echo $order->get_formatted_order_total(); ?></p>
        <p style="margin: 0;">Weitere Details finden Sie in Ihrem Kundenkonto.</p>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Error-Content für kritische Systemfehler (OHNE äußere HTML-Struktur)
 * 
 * @return string
 */
function yprint_build_error_email_content() {
    error_log('🆘 Generiere Error-Content - kritischer Systemfehler');
    return '<div style="padding: 20px; color: red; background-color: #fff; border: 1px solid #ff0000; border-radius: 8px;">Es ist ein Fehler bei der E-Mail-Generierung aufgetreten. Bitte kontaktieren Sie den Support.</div>';
}