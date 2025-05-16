<?php
/**
 * YPrint Stripe Checkout Shortcode Klasse
 *
 * Diese Klasse handhabt die Registrierung des Shortcodes, das Laden von Assets
 * und die AJAX-Handler für den Checkout-Prozess.
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Sicherstellen, dass die Konstante für das Plugin-Verzeichnis definiert ist (normalerweise in der Haupt-Plugin-Datei)
if ( ! defined( 'YPRINT_PLUGIN_DIR' ) ) {
    define( 'YPRINT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) . '../../' ); // Annahme: Klasse ist in includes/stripe/
}
if ( ! defined( 'YPRINT_PLUGIN_URL' ) ) {
    define( 'YPRINT_PLUGIN_URL', plugin_dir_url( __FILE__ ) . '../../' ); // Annahme: Klasse ist in includes/stripe/
}

class YPrint_Stripe_Checkout_Shortcode {

    /**
     * Singleton-Instanz der Klasse.
     * @var YPrint_Stripe_Checkout_Shortcode|null
     */
    protected static $instance = null;

    /**
     * Stellt sicher, dass nur eine Instanz der Klasse existiert.
     * @return YPrint_Stripe_Checkout_Shortcode
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Privater Konstruktor, um direkte Instanziierung zu verhindern.
     */
    private function __construct() {
        // Initialisierungsaktionen hier, falls notwendig beim Erstellen der Instanz
    }

    /**
     * Initialisiert die Hooks für den Checkout.
     * Diese Methode sollte von der Haupt-Plugin-Datei aufgerufen werden.
     */
    public static function init() {
        $instance = self::get_instance();

        // Shortcode registrieren: [yprint_checkout]
        add_shortcode( 'yprint_checkout', array( $instance, 'render_checkout_shortcode' ) );

        // Assets (JS & CSS) für den Checkout laden
        add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_checkout_assets' ) );

        // AJAX-Handler registrieren (Beispiele)
        // Nonce-Prüfung sollte in jeder AJAX-Funktion implementiert werden!
        add_action( 'wp_ajax_yprint_save_address', array( $instance, 'ajax_save_address' ) );
        add_action( 'wp_ajax_nopriv_yprint_save_address', array( $instance, 'ajax_save_address' ) ); // Für nicht eingeloggte Benutzer

        add_action( 'wp_ajax_yprint_set_payment_method', array( $instance, 'ajax_set_payment_method' ) );
        add_action( 'wp_ajax_nopriv_yprint_set_payment_method', array( $instance, 'ajax_set_payment_method' ) );

        add_action( 'wp_ajax_yprint_process_checkout', array( $instance, 'ajax_process_checkout' ) );
        add_action( 'wp_ajax_nopriv_yprint_process_checkout', array( $instance, 'ajax_process_checkout' ) );
        
        // Endpoints für den Checkout (optional, falls benötigt für spezielle Routen)
        // add_action('init', array($instance, 'add_checkout_endpoints'));
    }

    /**
     * Rendert den Checkout-Shortcode.
     *
     * @param array $atts Shortcode-Attribute.
     * @return string HTML-Ausgabe für den Checkout.
     */
    public function render_checkout_shortcode( $atts ) {
        // Standardattribute (falls welche benötigt werden)
        // $atts = shortcode_atts( array(
        // 'some_attribute' => 'default_value',
        // ), $atts, 'yprint_checkout' );

        // Hier könnten Logikprüfungen stattfinden, z.B. ob der Warenkorb leer ist.
        // if ( class_exists('WooCommerce') && WC()->cart && WC()->cart->is_empty() ) {
        // return '<p>Ihr Warenkorb ist leer. Bitte fügen Sie Produkte hinzu, um fortzufahren.</p>';
        // }

        // Template für den mehrstufigen Checkout rendern
        ob_start();
        // Der Pfad muss korrekt sein und auf die Datei im 'templates'-Ordner zeigen.
        $template_path = YPRINT_PLUGIN_DIR . 'templates/checkout-multistep.php';
        
        if ( file_exists( $template_path ) ) {
            include( $template_path );
        } else {
            // Fallback oder Fehlermeldung, wenn Template nicht gefunden wird.
            echo '<p>Checkout-Template nicht gefunden unter: ' . esc_html( $template_path ) . '</p>';
        }
        
        return ob_get_clean();
    }

    /**
     * Lädt die benötigten CSS- und JavaScript-Dateien für den Checkout.
     * Wird nur geladen, wenn der Shortcode auf der Seite vorhanden ist.
     */
    public function enqueue_checkout_assets() {
        // Prüfen, ob der Shortcode auf der aktuellen Seite verwendet wird.
        // Dies ist eine einfache Prüfung. Für komplexere Szenarien (z.B. in Widgets oder Page Buildern)
        // könnte eine robustere Methode erforderlich sein oder die Assets globaler geladen werden,
        // wenn der Checkout häufig genutzt wird.
        global $post;
        if ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'yprint_checkout' ) ) {

            // YPrint Checkout CSS
            wp_enqueue_style(
                'yprint-checkout-style',
                YPRINT_PLUGIN_URL . 'assets/css/yprint-checkout.css',
                array(), // Abhängigkeiten, z.B. Tailwind, falls es separat geladen wird
                filemtime( YPRINT_PLUGIN_DIR . 'assets/css/yprint-checkout.css' ) // Version für Cache Busting
            );

            // Font Awesome (optional, falls nicht schon vom Theme geladen)
            wp_enqueue_style(
                'font-awesome',
                'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
                array(),
                '6.5.2'
            );

            // Google Fonts: Roboto (optional, falls nicht schon vom Theme geladen)
            wp_enqueue_style(
                'google-fonts-roboto',
                'https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;600;700&display=swap',
                array(),
                null
            );
            
            // Tailwind CSS (vom CDN, wie im ursprünglichen HTML)
            // Es wird empfohlen, Tailwind lokal zu kompilieren und einzubinden.
            // Für dieses Beispiel wird das CDN beibehalten.
            wp_enqueue_script(
                'tailwindcss-cdn',
                'https://cdn.tailwindcss.com',
                array(),
                null, // Version nicht nötig für CDN-Hauptskript
                false // Nicht im Footer, da es für das Styling benötigt wird (kann aber zu FOUC führen)
                      // Besser: Tailwind kompilieren und als CSS-Datei einbinden.
            );


            // YPrint Checkout Haupt-JavaScript
            wp_enqueue_script(
                'yprint-checkout-js',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-checkout.js',
                array( 'jquery' ), // Abhängigkeiten, z.B. jQuery, falls verwendet
                filemtime( YPRINT_PLUGIN_DIR . 'assets/js/yprint-checkout.js' ),
                true // Im Footer laden
            );

            // YPrint Stripe Checkout JavaScript (falls Stripe verwendet wird)
            // Stripe.js v3 sollte vorher geladen werden, wenn dies davon abhängt.
            // wp_enqueue_script(
            // 'stripe-v3',
            // 'https://js.stripe.com/v3/',
            // array(),
            // null,
            // true // Im Footer laden
            // );

            wp_enqueue_script(
                'yprint-stripe-checkout-js',
                YPRINT_PLUGIN_URL . 'assets/js/yprint-stripe-checkout.js',
                array( 'jquery' /*, 'stripe-v3' */ ), // Abhängigkeit zu Stripe.js hinzufügen
                filemtime( YPRINT_PLUGIN_DIR . 'assets/js/yprint-stripe-checkout.js' ),
                true // Im Footer laden
            );

            // Daten an JavaScript übergeben (z.B. AJAX URL, Nonces, Stripe Keys)
            wp_localize_script(
                'yprint-checkout-js', // Handle des Haupt-JS-Skripts
                'yprint_checkout_params',
                array(
                    'ajax_url' => admin_url( 'admin-ajax.php' ),
                    'nonce'    => wp_create_nonce( 'yprint_checkout_nonce' ), // Beispiel-Nonce
                    // Weitere Parameter hier hinzufügen
                    // 'current_step' => isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'address', // Aktuellen Schritt übergeben
                )
            );
            
            // Parameter für Stripe JS (z.B. Publishable Key)
            // $stripe_options = get_option('yprint_stripe_settings'); // Stripe Einstellungen aus DB laden
            // $publishable_key = isset($stripe_options['publishable_key']) ? $stripe_options['publishable_key'] : '';
            // wp_localize_script(
            // 'yprint-stripe-checkout-js',
            // 'yprint_stripe_vars',
            //     array(
            // 'publishable_key' => $publishable_key,
            //     )
            // );
        }
    }

    /**
     * AJAX-Handler zum Speichern der Adressdaten.
     * (Implementierung erforderlich)
     */
    public function ajax_save_address() {
        // Nonce prüfen für Sicherheit
        // check_ajax_referer( 'yprint_checkout_nonce', 'nonce' );

        // Eingabedaten validieren und bereinigen
        // $shipping_address = isset($_POST['shipping']) ? map_deep($_POST['shipping'], 'sanitize_text_field') : array();
        // $billing_address = isset($_POST['billing']) ? map_deep($_POST['billing'], 'sanitize_text_field') : array();
        // $is_billing_same = isset($_POST['is_billing_same']) ? rest_sanitize_boolean($_POST['is_billing_same']) : true;

        // Adressdaten in der Session oder Benutzer-Metadaten speichern
        // if ( class_exists('WooCommerce') && WC()->session ) {
        // WC()->session->set('customer', array_merge(WC()->session->get('customer', array()), array(
        // 'shipping_address_1' => $shipping_address['street'],
        // // ... weitere Felder
        // )));
        // }

        // Antwort senden
        // wp_send_json_success( array( 'message' => 'Adresse erfolgreich gespeichert.' ) );
        wp_send_json_error( array( 'message' => 'Funktion ajax_save_address noch nicht implementiert.' ) ); // Platzhalter
        wp_die(); // Wichtig bei AJAX-Handlern in WordPress
    }

    /**
     * AJAX-Handler zum Speichern der gewählten Zahlungsmethode.
     * (Implementierung erforderlich)
     */
    public function ajax_set_payment_method() {
        // check_ajax_referer( 'yprint_checkout_nonce', 'nonce' );
        // $payment_method = isset($_POST['payment_method']) ? sanitize_text_field($_POST['payment_method']) : '';
        // if ( class_exists('WooCommerce') && WC()->session ) {
        // WC()->session->set('chosen_payment_method', $payment_method);
        // }
        // wp_send_json_success( array( 'message' => 'Zahlungsmethode gespeichert.' ) );
        wp_send_json_error( array( 'message' => 'Funktion ajax_set_payment_method noch nicht implementiert.' ) ); // Platzhalter
        wp_die();
    }

    /**
     * AJAX-Handler zur Verarbeitung des Checkouts und Erstellung der Bestellung.
     * (Implementierung erforderlich)
     */
    public function ajax_process_checkout() {
        // check_ajax_referer( 'yprint_checkout_nonce', 'nonce' );
        // Alle Checkout-Daten sammeln (Adresse, Zahlung, Warenkorb)
        // Validieren
        // Bei Stripe: PaymentIntent erstellen/bestätigen
        // WooCommerce-Bestellung erstellen: wc_create_order()
        // Warenkorb leeren: WC()->cart->empty_cart()
        // wp_send_json_success( array( 'message' => 'Bestellung erfolgreich verarbeitet.', 'order_id' => 123, 'redirect_url' => wc_get_checkout_url() . 'order-received/123/?key=wc_order_key_...' ) );
        wp_send_json_error( array( 'message' => 'Funktion ajax_process_checkout noch nicht implementiert.' ) ); // Platzhalter
        wp_die();
    }
    
    /**
     * Fügt benutzerdefinierte Endpoints für den Checkout hinzu (optional).
     * Beispiel: /checkout/payment oder /checkout/confirmation
     * Dies ist nützlich, wenn man keine Query-Parameter verwenden möchte.
     */
    // public function add_checkout_endpoints() {
    // add_rewrite_endpoint( 'yprint-checkout-step', EP_PAGES );
    // }
    // Man müsste dann auch die Logik anpassen, um den aktuellen Schritt
    // basierend auf dem Endpoint statt auf $_GET['step'] zu bestimmen.
    // Und nach dem Hinzufügen von Rewrite Rules müssen die Permalinks neu gespeichert werden.

}

// Die Klasse sollte erst initialisiert werden, wenn WordPress vollständig geladen ist.
// Normalerweise in der Haupt-Plugin-Datei oder über einen Hook wie 'plugins_loaded'.
// Beispiel für Aufruf in der Haupt-Plugin-Datei:
// YPrint_Stripe_Checkout_Shortcode::init();

