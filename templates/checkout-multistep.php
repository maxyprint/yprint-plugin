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

// $cart_items_data und $cart_totals_data sollten von checkout-multistep.php übergeben werden
// oder hier direkt von WooCommerce geladen werden.
// Für dieses Beispiel nehmen wir an, sie sind bereits verfügbar.

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

// Aktuellen Schritt bestimmen (Beispielhafte Logik)
$possible_steps = array('address', 'payment', 'confirmation', 'thankyou');
$current_step_slug = isset($_GET['step']) && in_array($_GET['step'], $possible_steps) ? sanitize_text_field($_GET['step']) : 'address';

// Dummy-Warenkorbdaten für die Darstellung (sollten von WC()->cart kommen)
$placeholder_cart_items = array(
    array( 'id' => 1, 'name' => 'Individuelles Fotobuch Premium', 'price' => 49.99, 'quantity' => 1, 'image' => 'https://placehold.co/100x100/0079FF/FFFFFF?text=Buch' ),
    array( 'id' => 2, 'name' => 'Visitenkarten (250 Stk.)', 'price' => 19.50, 'quantity' => 2, 'image' => 'https://placehold.co/100x100/E3F2FD/1d1d1f?text=Karten' ),
    array( 'id' => 3, 'name' => 'Großformat Poster A2', 'price' => 25.00, 'quantity' => 1, 'image' => 'https://placehold.co/100x100/CCCCCC/FFFFFF?text=Poster' ),
);
$cart_items_data = $placeholder_cart_items; // In WC: WC()->cart->get_cart();

$subtotal_example = 0;
foreach($cart_items_data as $item) {
    $subtotal_example += $item['price'] * $item['quantity'];
}
$shipping_example = 4.99;
$discount_example = 0; // Hier Gutscheinlogik einbauen
$total_example = $subtotal_example + $shipping_example - $discount_example;
$vat_example = $total_example * 0.19;

$cart_totals_data = array(
    'subtotal' => $subtotal_example,
    'shipping' => $shipping_example,
    'discount' => $discount_example,
    'vat'      => $vat_example,
    'total'    => $total_example,
);


// Pfad zum 'partials'-Ordner definieren
$partials_dir = YPRINT_PLUGIN_DIR . 'templates/partials/';

// Body-Klasse hinzufügen, um spezifische Styles für die Checkout-Seite zu ermöglichen
add_filter( 'body_class', function( $classes ) {
    $classes[] = 'yprint-checkout-page';
    $classes[] = 'bg-gray-50'; // Add Tailwind background class
    return $classes;
} );

?>

<style>
    /* Anpassungen für die 'card'-Klasse */
    /* Dies entfernt Rahmen und Füllung (setzt Hintergrund auf Weiß), behält aber den Schatten */
    .card {
        border: none !important; /* Rahmen entfernen */
        background-color: #ffffff !important; /* Füllung (Hintergrund) auf Weiß setzen */
        /* padding und box-shadow bleiben, falls vom Framework gesetzt,
           oder können hier explizit hinzugefügt werden, wenn nicht vorhanden */
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1); /* Leichter Schatten, um die "Card"-Form zu erhalten */
        border-radius: 8px; /* Leichte Abrundung der Ecken */
        padding: 20px; /* Standard-Polsterung, falls nicht vom Framework gesetzt */
    }

    /* Das Styling für die Warenkorb-Zusammenfassung */
    .order-summary-bold-final {
        border: 2px solid #ccc; /* Hellgrauer Rahmen */
        padding: 25px;
        font-family: sans-serif;
        background-color: #ffffff; /* Hintergrund ist Weiß */
        border-radius: 20px; /* Abgerundete Ecken */
        max-width: 350px; /* Optional: für bessere Lesbarkeit in einer Sidebar */
        margin: 0 auto; /* Optional: zum Zentrieren */
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05); /* Leichter Schatten für Tiefe */
    }

    .bold-header-final {
        color: #333; /* Schwarzer Titel */
        font-size: 1.5em;
        margin-bottom: 20px;
    }

    .items {
        margin-bottom: 20px;
        max-height: 200px; /* Begrenzte Höhe für Scroll, falls viele Artikel */
        overflow-y: auto; /* Scrollbalken für viele Artikel */
        padding-right: 5px; /* Abstand für Scrollbalken, damit er nicht den Inhalt überlappt */
    }

    .item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #eee; /* Hellere Trennlinie zwischen Artikeln */
    }

    .item:last-child {
        border-bottom: none; /* Keine Linie nach dem letzten Artikel */
    }

    .item-image {
        width: 60px;
        height: 60px;
        object-fit: cover; /* Bildausschnitt anpassen */
        border-radius: 4px; /* Leichte Rundung für Bilder */
        margin-right: 15px;
        flex-shrink: 0; /* Verhindert das Schrumpfen des Bildes */
    }

    .item-details {
        flex-grow: 1; /* Nimmt den verbleibenden Platz ein */
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
        white-space: nowrap; /* Preis nicht umbrechen */
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
        color: #28a745; /* Grüne Farbe für Rabatt */
    }

    .total-divider-final {
        border: none;
        border-top: 1px solid #ddd;
        margin: 10px 0;
    }

    .total-final {
        font-weight: bold;
        font-size: 1.3em;
        color: #333; /* Schwarze Gesamtbetragsfarbe */
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
        border-radius: 5px 0 0 5px; /* Links abgerundet */
        outline: none;
        font-size: 0.9em;
    }

    .voucher-button-final {
        padding: 10px 15px;
        background-color: #007bff; /* Ihr gewünschtes Blau */
        color: white;
        border: none;
        border-radius: 0 5px 5px 0; /* Rechts abgerundet */
        cursor: pointer;
        font-size: 0.9em;
        white-space: nowrap;
        transition: background-color 0.2s ease;
    }

    .voucher-button-final:hover {
        background-color: #0056b3; /* Dunkleres Blau beim Hover */
    }

    #cart-voucher-feedback {
        font-size: 0.75em; /* text-xs */
        margin-top: 5px; /* mt-1 */
        color: #6c757d; /* text-yprint-text-secondary, angelehnt an Bootstrap text-muted */
    }

    .text-yprint-text-secondary {
        color: #6c757d; /* Dunkelgrau für Text */
    }

    /* Layout spezifisches CSS */
.yprint-checkout-layout {
    display: flex;
    gap: 2rem; /* Abstand zwischen Hauptinhalt und Sidebar */
    flex-wrap: wrap; /* Umbruch auf kleineren Bildschirmen */
    align-items: stretch; /* Gleiche Höhe für Kinder */
}

.yprint-checkout-main-content {
    flex: 2; /* Nimmt mehr Platz ein */
    min-width: 300px; /* Mindestbreite, bevor der Umbruch erfolgt */
}

.yprint-checkout-sidebar {
    flex: 1; /* Nimmt weniger Platz ein */
    min-width: 280px; /* Mindestbreite der Sidebar */
    display: flex;
    flex-direction: column;
}

    /* Responsiveness für kleinere Bildschirme */
    @media (max-width: 768px) {
        .yprint-checkout-layout {
            flex-direction: column; /* Stapelt Spalten übereinander */
            gap: 1.5rem;
        }

        .yprint-checkout-main-content,
        .yprint-checkout-sidebar {
            flex: none; /* Setzt Flex-Werte zurück */
            width: 100%; /* Volle Breite */
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
        color: #007bff; /* Linkfarbe */
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
            <?php // Lade den entsprechenden Schritt basierend auf $current_step_slug ?>
            <?php
            switch ($current_step_slug) {
                case 'address':
                    include( $partials_dir . 'checkout-step-address.php' );
                    break;
                case 'payment':
                    include( $partials_dir . 'checkout-step-payment.php' );
                    break;
                case 'confirmation':
                    include( $partials_dir . 'checkout-step-confirmation.php' );
                    break;
                case 'thankyou':
                    // Die "Danke"-Seite ist jetzt Teil der Haupt-Switch-Case Logik
                    if (file_exists($partials_dir . 'checkout-step-thankyou.php')) {
                        include( $partials_dir . 'checkout-step-thankyou.php' );
                    } else {
                        // Fallback für Danke-Seite, falls die Datei nicht existiert
                        echo '<div id="step-4-thankyou" class="checkout-step active text-center">';
                        echo '<i class="fas fa-check-circle text-6xl text-yprint-success mb-6"></i>';
                        echo '<h2 class="text-3xl font-bold">' . esc_html__('Vielen Dank für Ihre Bestellung!', 'yprint-checkout') . '</h2>';
                        echo '</div>';
                    }
                    break;
                default:
                    // Fallback, falls ein ungültiger Schritt angegeben wurde
                    include( $partials_dir . 'checkout-step-address.php' );
                    break;
            }
            ?>
        </div>
    </div>

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
</div>

    <?php // Footer-Bereich mit Links ?>
    <footer class="yprint-checkout-footer">
        <p class="mt-1">
            <a href="#" class="hover:text-yprint-blue"><?php esc_html_e('FAQ', 'yprint-checkout'); ?></a> |
            <a href="#" class="hover:text-yprint-blue"><?php esc_html_e('Rückgabe', 'yprint-checkout'); ?></a> |
            <a href="#" class="hover:text-yprint-blue"><?php esc_html_e('Datenschutz', 'yprint-checkout'); ?></a>
        </p>
    </footer>

</div> <?php // Ende .yprint-checkout-container - Dies ist nicht Teil des übergebenen Codes. Entfernen oder anpassen. ?>

<?php // Ladeanimation Overlay (global für alle Schritte) ?>
<div id="loading-overlay" class="hidden">
    <div class="spinner"></div>
    <p class="text-lg text-yprint-black"><?php esc_html_e('Ihre Bestellung wird verarbeitet...', 'yprint-checkout'); ?></p>
</div>