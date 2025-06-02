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
    /* Anpassungen für die 'card'-Klasse */
    .card {
        border: none !important;
        background-color: #ffffff !important;
        border-width: 1px
        border-color: #6D6D6D;
        border-radius: 8px;
        padding: 20px;
    }

    /* Das Styling für die Warenkorb-Zusammenfassung */
    .order-summary-bold-final {
        border: 2px solid #ccc;
        padding: 25px;
        font-family: sans-serif;
        background-color: #ffffff;
        border-radius: 20px;
        max-width: 350px;
        margin: 0 auto;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
    }

    .bold-header-final {
        color: #333;
        font-size: 1.5em;
        margin-bottom: 20px;
    }

    .items {
        margin-bottom: 20px;
        max-height: 200px;
        overflow-y: auto;
        padding-right: 5px;
    }

    .item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #eee;
    }

    .item:last-child {
        border-bottom: none;
    }

    .item-image {
        width: 60px;
        height: 60px;
        object-fit: cover;
        border-radius: 4px;
        margin-right: 15px;
        flex-shrink: 0;
    }

    .item-details {
        flex-grow: 1;
    }

    .item-name {
        font-weight: bold;
        color: #333;
        margin-bottom: 3px;
        font-size: 0.95em;
    }

    .item-quantity {
        font-size: 0.8em;
        color: #666;
    }

    .item-price {
        font-weight: bold;
        color: #333;
        white-space: nowrap;
        font-size: 0.95em;
    }

    .totals div {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.95em;
        color: #555;
    }

    .discount {
        color: #28a745;
    }

    .total-divider-final {
        border: none;
        border-top: 1px solid #ddd;
        margin: 10px 0;
    }

    .total-final {
        font-weight: bold;
        font-size: 1.3em;
        color: #333;
        display: flex;
        justify-content: space-between;
        padding-top: 10px;
    }

    .voucher {
        margin-top: 25px;
    }

    .voucher label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #555;
        font-size: 0.95em;
    }

    .voucher-input-group-final {
        display: flex;
        width: 100%;
    }

    .voucher-input-group-final input {
        flex-grow: 1;
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px 0 0 5px;
        outline: none;
        font-size: 0.9em;
    }

    .voucher-button-final {
        padding: 10px 15px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 0 5px 5px 0;
        cursor: pointer;
        font-size: 0.9em;
        white-space: nowrap;
        transition: background-color 0.2s ease;
    }

    .voucher-button-final:hover {
        background-color: #0056b3;
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
        gap: 2rem;
        flex-wrap: wrap;
        align-items: stretch;
    }

    .yprint-checkout-main-content {
        flex: 2;
        min-width: 300px;
    }

    .yprint-checkout-sidebar {
        flex: 1;
        min-width: 280px;
        display: flex;
        flex-direction: column;
    }

    /* Responsiveness für kleinere Bildschirme */
    @media (max-width: 768px) {
        .yprint-checkout-layout {
            flex-direction: column;
            gap: 1.5rem;
        }

        .yprint-checkout-main-content,
        .yprint-checkout-sidebar {
            flex: none;
            width: 100%;
            min-width: unset;
        }
    }

    /* Styling für den Footer (aus Original-Code) */
    .yprint-checkout-footer {
        text-align: center;
        margin-top: 2rem;
        padding-top: 1rem;
        border-top: 1px solid #eee;
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
        border: 4px solid rgba(0, 0, 0, 0.1);
        border-left-color: #007bff;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 1s linear infinite;
        margin-bottom: 10px;
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

        <div id="step-1" class="checkout-step <?php echo ($current_step_id === 'step-1') ? 'active' : ''; ?>">
            <?php
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

        </div> <?php // Ende .card ?>
    </div> <?php // Ende .yprint-checkout-main-content ?>

    <?php // Warenkorb-Zusammenfassung (Sidebar) nur anzeigen, wenn nicht auf der Danke-Seite ?>
    <?php if ($current_step_slug !== 'thankyou') : ?>
    <aside class="yprint-checkout-sidebar">
        <?php
        // Hier wird das Warenkorb-Zusammenfassungs-Partial geladen
        // und die benötigten Daten übergeben.
        $cart_items_data_for_summary = $cart_items_data; // Dummy-Daten
        $cart_totals_data_for_summary = $cart_totals_data; // Dummy-Daten

        include( $partials_dir . 'checkout-cart-summary.php' ); ?>
    </aside>
<?php endif; ?>
</div> <?php // Ende .yprint-checkout-layout ?>

    <?php // Footer-Bereich mit Links ?>
    <footer class="yprint-checkout-footer">
        <p class="mt-1">
            <a href="#" class="hover:text-yprint-blue"><?php esc_html_e('FAQ', 'yprint-checkout'); ?></a> |
            <a href="#" class="hover:text-yprint-blue"><?php esc_html_e('Rückgabe', 'yprint-checkout'); ?></a> |
            <a href="#" class="hover:text-yprint-blue"><?php esc_html_e('Datenschutz', 'yprint-checkout'); ?></a>
        </p>
    </footer>

<?php // Ladeanimation Overlay (global für alle Schritte) ?>
<div id="loading-overlay" class="hidden">
    <div class="spinner"></div>
    <p class="text-lg text-yprint-black"><?php esc_html_e('Ihre Bestellung wird verarbeitet...', 'yprint-checkout'); ?></p>

    <?php if (current_user_can('administrator') && isset($_GET['debug'])) : ?>
<div style="margin-top: 30px; padding: 10px; background: #f8f8f8; border: 1px solid #ddd; font-family: monospace;">
    <h3>Debug Info:</h3>
    <p>Current Step: <?php echo esc_html($current_step_slug); ?></p>
    <p>Partials Dir: <?php echo esc_html($partials_dir); ?></p>
    <p>Payment Step File: <?php echo esc_html($partials_dir . 'checkout-step-payment.php'); ?></p>
    <p>File Exists: <?php echo file_exists($partials_dir . 'checkout-step-payment.php') ? 'Yes' : 'No'; ?></p>
    <h4>Active Steps:</h4>
    <ul id="debug-steps"></ul>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const activeSteps = document.querySelectorAll('.checkout-step.active');
            const debugList = document.getElementById('debug-steps');
            if (activeSteps.length === 0) {
                debugList.innerHTML = '<li>No active steps found!</li>';
            } else {
                activeSteps.forEach(step => {
                    const li = document.createElement('li');
                    li.textContent = `#${step.id} - Display: ${getComputedStyle(step).display}`;
                    debugList.appendChild(li);
                });
            }
        });
    </script>
</div>
<?php endif; ?>
</div>