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
    /* YPrint Checkout - Globale Styles */
    body.yprint-checkout-page {
        font-size: 16px;
        line-height: 1.6;
        font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
    }

    /* Single Column Layout Container */
    .yprint-checkout-container {
        max-width: 800px;
        margin: 0 auto;
        padding: 20px;
    }

    /* Card Styling */
    .yprint-checkout-card {
        border: 1px solid #DFDFDF;
        background-color: #ffffff;
        border-radius: 12px;
        padding: 30px;
        margin-bottom: 20px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    }

    /* Progress Bar CSS entfernt - jetzt im Header integriert */

    /* Checkout Header Integration */
    .checkout-step .yprint-checkout-header {
        margin: -30px -30px 30px -30px;
        border: none;
        border-bottom: 1px solid #DFDFDF;
        border-radius: 12px 12px 0 0;
        background-color: #F6F7FA;
    }

    /* Font Awesome Icons sicherstellen - Erweiterte Definition */
    .fas, .far, .fab, .fa {
        font-family: "Font Awesome 6 Free", "Font Awesome 6 Pro", "Font Awesome 5 Free", "FontAwesome" !important;
        font-weight: 900;
        font-style: normal;
        font-variant: normal;
        text-rendering: auto;
        line-height: 1;
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
        display: inline-block;
        text-decoration: inherit;
    }
    
    .far {
        font-weight: 400 !important;
    }
    
    .fab {
        font-weight: 400 !important;
    }
    
    /* Fallback für Icon-Display */
    i[class*="fa-"]::before {
        font-family: "Font Awesome 6 Free" !important;
        font-weight: 900;
        font-style: normal;
    }
    
    /* Spezifische Icon-Fixes */
    .fa-map-marker-alt::before { content: "\f3c5"; }
    .fa-credit-card::before { content: "\f09d"; }
    .fa-check-circle::before { content: "\f058"; }
    .fa-shopping-bag::before { content: "\f290"; }
    .fa-chevron-down::before { content: "\f078"; }
    .fa-arrow-left::before { content: "\f060"; }
    .fa-arrow-right::before { content: "\f061"; }
    .fa-check::before { content: "\f00c"; }

    /* Mobile Responsive */
    @media (max-width: 768px) {
        .yprint-checkout-container {
            padding: 15px;
        }
        
        .yprint-checkout-card {
            padding: 20px;
        }
        
        .checkout-step .yprint-checkout-header {
            margin: -20px -20px 20px -20px;
        }
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

    /* Entfernt - nicht mehr benötigt für Single Column Layout */

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
    <?php 
// Font Awesome sicherstellen - sowohl für Frontend als auch Backend
wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');

// Zusätzliche Font Awesome Styles direkt einbinden für sofortige Verfügbarkeit
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

<div class="yprint-checkout-container">
    <div class="yprint-checkout-card">
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
    echo do_shortcode('[yprint_checkout_header step="address" show_total="yes" show_progress="yes"]');
    
    // Font Awesome Test für Debug
    if (current_user_can('administrator') && isset($_GET['debug'])) {
        echo '<div style="background: #f0f0f0; padding: 10px; margin: 10px 0; font-size: 12px;">';
        echo '<strong>Icon Test:</strong> ';
        echo '<i class="fas fa-check"></i> Check ';
        echo '<i class="fas fa-map-marker-alt"></i> Location ';
        echo '<i class="fas fa-credit-card"></i> Payment ';
        echo '</div>';
    }
    
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
    // Checkout Header für Zahlungs-Schritt
    echo do_shortcode('[yprint_checkout_header step="payment" show_total="yes" show_progress="yes"]');
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
    echo do_shortcode('[yprint_checkout_header step="confirmation" show_total="yes" show_progress="yes"]');
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
    // Font Awesome Check und Fallback
    function ensureFontAwesome() {
        // Prüfe ob Font Awesome geladen ist
        const testElement = document.createElement('i');
        testElement.className = 'fas fa-check';
        testElement.style.position = 'absolute';
        testElement.style.left = '-9999px';
        document.body.appendChild(testElement);
        
        const computedStyle = window.getComputedStyle(testElement, '::before');
        const isLoaded = computedStyle.getPropertyValue('font-family').includes('Font Awesome');
        
        document.body.removeChild(testElement);
        
        if (!isLoaded) {
            console.warn('Font Awesome nicht geladen, lade Fallback...');
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
            link.crossOrigin = 'anonymous';
            document.head.appendChild(link);
        }
    }
    
    // Checkout Header Integration
    jQuery(document).ready(function($) {
        // Font Awesome sicherstellen
        ensureFontAwesome();
        // Event für Schritt-Wechsel
        $(document).on('yprint_step_changed', function(event, stepData) {
            // Header Step mapping - direkte Zuordnung
            const headerStepMapping = {
                'address': 'address',
                'payment': 'payment',
                'confirmation': 'confirmation',
                'thankyou': 'confirmation'
            };
            
            const headerStep = headerStepMapping[stepData.step] || 'address';
            
            // Update Header Step Progress
            updateCheckoutHeaderProgress(headerStep);
        });
        
        // Funktion zum Aktualisieren der Fortschrittsanzeige im Header
        function updateCheckoutHeaderProgress(currentStep) {
            const steps = ['cart', 'address', 'payment', 'confirmation'];
            const currentIndex = steps.indexOf(currentStep);
            
            $('.yprint-progress-step').each(function(index) {
                const $step = $(this);
                $step.removeClass('active completed');
                
                if (index < currentIndex) {
                    $step.addClass('completed');
                } else if (index === currentIndex) {
                    $step.addClass('active');
                }
            });
        }
        
        // Event für Warenkorb-Updates
        $(document).on('yprint_cart_updated', function(event, cartData) {
            // Header Total aktualisieren
            if (cartData.totals && cartData.totals.total) {
                $('#yprint-header-total').text('€' + parseFloat(cartData.totals.total).toFixed(2).replace('.', ','));
            }
        });
    });
    </script>