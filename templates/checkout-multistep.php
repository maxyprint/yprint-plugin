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

// Standardwerte setzen, falls Variablen nicht übergeben wurden
// In einer echten Anwendung würden diese Daten dynamisch von WooCommerce oder einer anderen Warenkorb-Logik kommen.
global $wp; // Zugriff auf globale WordPress-Variablen

// Aktuellen Schritt bestimmen (Beispielhafte Logik)
// In einer echten WP-Umgebung würde man dies vielleicht über Query Vars oder eine eigene Routing-Logik machen.
$possible_steps = array('address', 'payment', 'confirmation', 'thankyou');
$current_step_slug = isset($_GET['step']) && in_array($_GET['step'], $possible_steps) ? sanitize_text_field($_GET['step']) : 'address';

// Wenn der Prozess abgeschlossen ist und eine Bestell-ID in der Session/GET ist, direkt zur Danke-Seite
// if (isset($_GET['order_id']) && isset($_GET['thank_you'])) {
// $current_step_slug = 'thankyou';
// }


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

// Hier könnten wp_head() und wp_footer() Aufrufe sein, wenn dies als eigenständige Seite gedacht ist.
// Da es ein Shortcode ist, wird angenommen, dass dies innerhalb einer bestehenden WP-Seite läuft.
?>


    <?php // Fortschrittsbalken nur anzeigen, wenn nicht auf der Danke-Seite ?>
<?php if ($current_step_slug !== 'thankyou') : ?>
    <div class="progress-bar-wrapper card mb-8">
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
                    // und wird direkt im Hauptinhaltsbereich geladen.
                    // Der Inhalt der Danke-Seite ist in checkout-step-thankyou.php (neu erstellt)
                    // oder kann hier direkt eingebettet werden, wenn es einfacher ist.
                    // Für Konsistenz:
                    if (file_exists($partials_dir . 'checkout-step-thankyou.php')) {
                        include( $partials_dir . 'checkout-step-thankyou.php' );
                    } else {
                        // Fallback für Danke-Seite, falls die Datei nicht existiert
                        echo '<div id="step-4-thankyou" class="checkout-step active text-center">';
                        echo '<i class="fas fa-check-circle text-6xl text-yprint-success mb-6"></i>';
                        echo '<h2 class="text-3xl font-bold">' . esc_html__('Vielen Dank für Ihre Bestellung!', 'yprint-checkout') . '</h2>';
                        // Weitere Details hier...
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
            <div class="checkout-cart-summary">
                <?php include( $partials_dir . 'checkout-cart-summary.php' ); ?>
            </div>
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

</div> <?php // Ende .yprint-checkout-container ?>

<?php // Ladeanimation Overlay (global für alle Schritte) ?>
<div id="loading-overlay" class="hidden">
    <div class="spinner"></div>
    <p class="text-lg text-yprint-black"><?php esc_html_e('Ihre Bestellung wird verarbeitet...', 'yprint-checkout'); ?></p>
</div>
