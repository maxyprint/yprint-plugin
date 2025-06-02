<?php
/**
 * Template für den mehrstufigen YPrint Checkout.
 *
 * Dieses Template wird vom Shortcode [yprint_checkout] geladen.
 * Es enthält die Hauptstruktur des Checkouts, einschließlich Fortschrittsanzeige,
 * Schritte und Warenkorb-Zusammenfassung.
 *
 * Verfügbare Variablen (Beispiele, müssen von der Shortcode-Funktion oder global gesetzt werden):
 * $current_step_slug (string) - Slug des aktuellen Schritts (z.B. 'address', 'payment', 'confirmation', 'thankyou')
 * $cart_items (array) - Array mit Warenkorbartikeln
 * $cart_totals (array) - Array mit Warenkorbsummen
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Debug-Ausgabe am Anfang von checkout-multistep.php
error_log('Loading checkout-multistep.php');

// Fallback, falls Daten nicht korrekt übergeben wurden
if ( !isset($cart_items_data) || !is_array($cart_items_data) ) {
    $cart_items_data = array(); // Leerer Warenkorb
}
if ( !isset($cart_totals_data) || !is_array($cart_totals_data) ) {
    $cart_totals_data = array( // Standard-Summen
        'subtotal' => 0,
        'shipping' => 0,
        'discount' => 0,
        'vat'      => 0,
        'total'    => 0,
    );
}

// Aktuellen Schritt bestimmen
$possible_steps_slugs = array('address', 'payment', 'confirmation', 'thankyou');
$current_step_slug = isset($_GET['step']) && in_array($_GET['step'], $possible_steps_slugs) ? sanitize_text_field($_GET['step']) : 'address';

// Debug-Ausgabe
error_log('Requested step from GET: ' . (isset($_GET['step']) ? $_GET['step'] : 'not set'));
error_log('Current step after validation: ' . $current_step_slug);

// Zentrale Datenverwaltung nutzen (Single Source of Truth)
$cart_data_manager = YPrint_Cart_Data::get_instance();
$checkout_context = $cart_data_manager->get_checkout_context('full');

// Daten für Templates extrahieren
$cart_items_data = $checkout_context['cart_items'];
$cart_totals_data = $checkout_context['cart_totals'];

// Debug-Ausgabe für Entwicklung (kann später entfernt werden)
if (current_user_can('administrator') && isset($_GET['debug'])) {
    error_log('YPrint Debug - Cart Items Data: ' . print_r($cart_items_data, true));
    error_log('YPrint Debug - Cart Totals Data: ' . print_r($cart_totals_data, true));
}


// Pfad zum 'partials'-Ordner definieren
$partials_dir = YPRINT_PLUGIN_DIR . 'templates/partials/';

// Debug: Prüfe ob das Verzeichnis existiert und die Zahlung-Datei vorhanden ist
if (!file_exists($partials_dir)) {
    error_log('Partials directory not found at: ' . $partials_dir);
}
if (!file_exists($partials_dir . 'checkout-step-payment.php')) {
    error_log('Payment step template not found at: ' . $partials_dir . 'checkout-step-payment.php');
}

// Body-Klasse hinzufügen, um spezifische Styles für die Checkout-Seite zu ermöglichen
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'yprint-checkout-page';
    $classes[] = 'bg-gray-50'; // Add Tailwind background class
    return $classes;
} );

?>

<style>
    /* Globale Anpassungen für bessere Mobile-Erfahrung */
    body.yprint-checkout-page {
        font-size: 16px; /* Grundlegende Schriftgröße für Mobilgeräte */
        line-height: 1.6;
    }

    .card {
        border: 1px solid #DFDFDF !important;
        background-color: #ffffff !important;
        border-radius: 8px;
        padding: 15px; /* Weniger Padding für kleinere Bildschirme */
        margin-bottom: 1.5rem; /* Mehr Abstand zwischen den Karten */
    }

    /* Checkout Header Integration - innerhalb der Card */
    .checkout-step .yprint-checkout-header {
        margin: -15px -15px 20px -15px; /* Negatives Margin um Card-Padding zu kompensieren */
        border: none; /* Kein eigener Rahmen, da Teil der Card */
        border-bottom: 1px solid #DFDFDF; /* Nur unterer Rahmen als Trenner */
        border-radius: 8px 8px 0 0; /* Nur obere Ecken abgerundet */
        background-color: #f8f9fa; /* Leicht abgesetzter Hintergrund */
    }

    /* Responsive Anpassungen für integrierten Header */
    @media (max-width: 768px) {
        .checkout-step .yprint-checkout-header {
            margin: -15px -15px 15px -15px;
            border-radius: 8px 8px 0 0;
        }
    }

    /* Größere Bildschirme - mehr Padding */
    @media (min-width: 768px) {
        .checkout-step .yprint-checkout-header {
            margin: -20px -20px 20px -20px;
        }
    }

    /* Fortschrittsbalken (Anpassungen für Mobilgeräte sind oft im Partial selbst) */
    .progress-bar-wrapper {
        margin-bottom: 1.5rem;
    }

    /* Hauptinhaltsbereich und Sidebar */
    .yprint-checkout-layout {
        display: flex;
        flex-direction: column; /* Standardmäßig untereinander auf kleinen Bildschirmen */
        gap: 1.5rem;
        align-items: stretch; /* Elemente dehnen, um die volle Breite zu nutzen */
    }

    .yprint-checkout-main-content,
    .yprint-checkout-sidebar {
        flex: 1 1 auto; /* Gleichmäßige Verteilung, wenn nebeneinander möglich */
        min-width: 0; /* Verhindert Überlaufen */
    }

    /* Warenkorb-Zusammenfassung (Sidebar) */
    .yprint-checkout-sidebar {
        /* Reihenfolge kann mit order in Flexbox angepasst werden, falls nötig */
    }

    .order-summary-bold-final {
        border: 1px solid #ccc; /* Dünnere Border für Mobilgeräte */
        padding: 15px; /* Weniger Padding */
        font-family: sans-serif;
        background-color: #ffffff;
        border-radius: 15px; /* Etwas weniger Rundung */
        margin: 0 auto 1.5rem; /* Zentriert und mit Abstand nach unten */
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05); /* Dezenterer Schatten */
        max-width: 100%; /* Nimmt standardmäßig die volle Breite ein */
    }

    .bold-header-final {
        color: #333;
        font-size: 1.2em; /* Kleinere Überschrift */
        margin-bottom: 15px;
        text-align: center; /* Zentriert die Überschrift */
    }

    .items {
        margin-bottom: 15px;
        max-height: 150px; /* Weniger Höhe für den Scrollbereich */
        overflow-y: auto;
        padding-right: 5px;
    }

    .item {
        display: flex;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #eee;
    }

    .item-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 4px;
        margin-right: 10px;
        flex-shrink: 0;
    }

    .item-details {
        flex-grow: 1;
    }

    .item-name {
        font-weight: bold;
        color: #333;
        margin-bottom: 2px;
        font-size: 0.9em;
    }

    .item-quantity {
        font-size: 0.75em;
        color: #666;
    }

    .item-price {
        font-weight: bold;
        color: #333;
        white-space: nowrap;
        font-size: 0.9em;
    }

    .totals div {
        display: flex;
        justify-content: space-between;
        padding: 5px 0;
        font-size: 0.9em;
        color: #555;
    }

    .total-final {
        font-weight: bold;
        font-size: 1.1em;
        color: #333;
        display: flex;
        justify-content: space-between;
        padding-top: 8px;
    }

    .voucher {
        margin-top: 20px;
    }

    .voucher label {
        display: block;
        margin-bottom: 6px;
        font-weight: bold;
        color: #555;
        font-size: 0.9em;
    }

    .voucher-input-group-final {
        display: flex;
        width: 100%;
    }

    .voucher-input-group-final input {
        flex-grow: 1;
        padding: 8px;
        border: 1px solid #ccc;
        border-radius: 5px 0 0 5px;
        outline: none;
        font-size: 0.85em;
    }

    .voucher-button-final {
        padding: 8px 12px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 0 5px 5px 0;
        cursor: pointer;
        font-size: 0.85em;
        white-space: nowrap;
        transition: background-color 0.2s ease;
    }

    #cart-voucher-feedback {
        font-size: 0.75em;
        margin-top: 5px;
        color: #6c757d;
    }

    .text-yprint-text-secondary {
        color: #6c757d;
    }

    /* Layout spezifisches CSS */
    .yprint-checkout-layout {
        display: flex;
        flex-direction: column; /* Standardmäßig untereinander auf kleinen Bildschirmen */
        gap: 1.5rem;
        align-items: stretch;
    }

    .yprint-checkout-main-content {
        flex: 1;
        min-width: 0;
    }

    .yprint-checkout-sidebar {
        flex: 1;
        min-width: 0;
        display: flex;
        flex-direction: column;
    }

    /* Responsiveness für größere Bildschirme */
    @media (min-width: 768px) {
        .yprint-checkout-layout {
            flex-direction: row;
            gap: 2rem;
        }

        .yprint-checkout-main-content {
            flex: 2;
        }

        .yprint-checkout-sidebar {
            flex: 1;
        }

        .order-summary-bold-final {
            max-width: 350px;
            margin: 0 0 1.5rem 0;
        }

        .bold-header-final {
            text-align: left;
        }

        .card {
            padding: 20px;
        }
    }

    /* Styling für den Footer (aus Original-Code) */
    .yprint-checkout-footer {
        text-align: center;
        margin-top: 1.5rem;
        padding-top: 1rem;
        border-top: 1px solid #eee;
        font-size: 0.85em;
    }

    .yprint-checkout-footer a {
        color: #007bff;
        text-decoration: none;
    }

    .yprint-checkout-footer a:hover {
        text-decoration: underline;
    }

    /* Styling für das Lade-Overlay (aus Original-Code) */
    #loading-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        flex-direction: column;
        justify-content: center;
        align-items: center;
        z-index: 9999;
    }

    .spinner {
        border: 3px solid rgba(0, 0, 0, 0.1); /* Etwas kleiner */
        border-left-color: #007bff;
        border-radius: 50%;
        width: 30px; /* Kleinerer Spinner */
        height: 30px; /* Kleinerer Spinner */
        animation: spin 1s linear infinite;
        margin-bottom: 8px;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }
</style>
    <?php // Fortschrittsbalken nur anzeigen, wenn nicht auf der Danke-Seite ?>
    
<?php if ($current_step_slug !== 'thankyou') : ?>
    <div class="progress-bar-wrapper mb-8">
    <?php include( $partials_dir . 'checkout-progress.php' ); ?>
</div>
<?php endif; ?>

<div class="yprint-checkout-layout">
    <div class="yprint-checkout-main-content">
        <div class="card">
        <?php
        // Debug-Information ausgeben (kann in Produktion entfernt werden)
        error_log('Debug Checkout Steps:');
        error_log('Current Step: ' . $current_step_slug);
        error_log('Partials Dir: ' . $partials_dir);

        // Mapping von Schritt-Slug zu Step-ID für die 'active' Klasse
        $step_slug_to_id = [
            'address'      => 'step-1',
            'payment'      => 'step-2',
            'confirmation' => 'step-3',
            'thankyou'     => 'step-4',
        ];
        $current_step_id = $step_slug_to_id[$current_step_slug] ?? 'step-1'; // Standard auf step-1 setzen, falls unbekannt

        // Hier werden ALLE Schritte gerendert, aber nur der aktuelle ist "active"
        // Jeder Schritt sollte in einer eigenen Partial-Datei liegen
        ?>

<div id="step-1" class="checkout-step active">
    <?php 
    // Checkout Header für Adress-Schritt
    echo do_shortcode('[yprint_checkout_header step="information" show_total="yes" show_progress="yes"]');
    
            // Prüfe, ob die Partial-Datei existiert, bevor sie eingebunden wird
            if (file_exists($partials_dir . 'checkout-step-address.php')) {
                include($partials_dir . 'checkout-step-address.php');
            } else {
                echo '<p>Fehler: Adress-Schritt-Template nicht gefunden.</p>';
            }
            ?>
        </div>

        <div id="step-2" class="checkout-step <?php echo ($current_step_id === 'step-2') ? 'active' : ''; ?>">
        <?php 
    // Checkout Header für Adress-Schritt
    echo do_shortcode('[yprint_checkout_header step="information" show_total="yes" show_progress="yes"]');
            if (file_exists($partials_dir . 'checkout-step-payment.php')) {
                include($partials_dir . 'checkout-step-payment.php');
            } else {
                echo '<p>Fehler: Zahlungs-Schritt-Template nicht gefunden.</p>';
                // Fallback-Buttons für den Fall, dass die Datei nicht existiert
                echo '<div class="pt-6 flex flex-col md:flex-row justify-between items-center gap-4">';
                echo '<button type="button" id="btn-back-to-address" class="btn btn-secondary w-full md:w-auto order-2 md:order-1">';
                echo '<i class="fas fa-arrow-left mr-2"></i> ' . esc_html__('Zurück zur Adresse', 'yprint-checkout');
                echo '</button>';
                echo '<button type="button" id="btn-to-confirmation" class="btn btn-primary w-full md:w-auto order-1 md:order-2">';
                echo esc_html__('Weiter zur Bestätigung', 'yprint-checkout') . ' <i class="fas fa-arrow-right ml-2"></i>';
                echo '</button>';
                echo '</div>';
            }
            ?>
        </div>

        <div id="step-3" class="checkout-step <?php echo ($current_step_id === 'step-3') ? 'active' : ''; ?>">
        <?php 
    // Checkout Header für Bestätigungs-Schritt
    echo do_shortcode('[yprint_checkout_header step="payment" show_total="yes" show_progress="yes"]');
    ?>
            <?php
            if (file_exists($partials_dir . 'checkout-step-confirmation.php')) {
                include($partials_dir . 'checkout-step-confirmation.php');
            } else {
                echo '<p>Fehler: Bestätigungs-Schritt-Template nicht gefunden.</p>';
            }
            ?>
        </div>

        <div id="step-4" class="checkout-step <?php echo ($current_step_id === 'step-4') ? 'active' : ''; ?>">
            <?php
            if (file_exists($partials_dir . 'checkout-step-thank-you.php')) {
                include($partials_dir . 'checkout-step-thank-you.php');
            } else {
                echo '<p>Fehler: Danke-Seite-Template nicht gefunden.</p>';
            }
            ?>

</div>

</div>

        <div class="yprint-checkout-sidebar">
        </div>
    </div>

    <!-- Footer-Bereich für rechtliche Hinweise -->
    <div class="yprint-checkout-footer">
        <p><?php printf(
            wp_kses(
                __('Sichere Zahlung über SSL. Lesen Sie unsere <a href="%1$s">AGB</a> und <a href="%2$s">Datenschutzbestimmungen</a>.', 'yprint-checkout'),
                array( 'a' => array( 'href' => array() ) )
            ),
            esc_url( home_url('/agb') ),
            esc_url( home_url('/datenschutz') )
        ); ?></p>
    </div>

    <!-- Loading Overlay für AJAX-Requests -->
    <div id="loading-overlay" style="display: none;">
        <div class="spinner"></div>
        <p><?php esc_html_e('Verarbeitung läuft...', 'yprint-checkout'); ?></p>
    </div>

    <script>
    // Checkout Header Integration
    jQuery(document).ready(function($) {
        // Event für Schritt-Wechsel
        $(document).on('yprint_step_changed', function(event, stepData) {
            // Header Step mapping
            const headerStepMapping = {
                'address': 'information',
                'payment': 'payment',
                'confirmation': 'payment'
            };
            
            const headerStep = headerStepMapping[stepData.step] || 'information';
            
            // Update Header Step (falls Header bereits geladen)
            if (typeof updateCheckoutHeaderStep === 'function') {
                updateCheckoutHeaderStep(headerStep);
            }
        });
        
        // Event für Warenkorb-Updates
        $(document).on('yprint_cart_updated', function(event, cartData) {
            // Header Total aktualisieren
            if (cartData.totals && cartData.totals.total) {
                $('#yprint-header-total').text('€' + parseFloat(cartData.totals.total).toFixed(2).replace('.', ','));
            }
        });
    });
    </script>