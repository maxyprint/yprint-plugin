<?php
/**
 * Vectorize.ai API-Implementierung für Vectorize WP
 *
 * Diese Klasse implementiert die Vectorize.ai API-Schnittstelle.
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Abstrakte API-Klasse einbinden
require_once VECTORIZE_WP_PATH . 'includes/class-api.php';

class Vectorize_WP_Vectorize_API extends Vectorize_WP_API {
    /**
     * Konstruktor
     *
     * @param string $api_key API-Schlüssel
     */
    public function __construct($api_key = '') {
        parent::__construct($api_key);
        
        // API-Endpunkt für Vectorize.ai setzen
        $this->api_url = 'https://vectorizer.ai/api/v1/';
        
        // Log welche URL wir verwenden
        error_log("Vectorize_WP_Vectorize_API: Using API URL: " . $this->api_url);
    }
    
    /**
 * Überprüft, ob der API-Schlüssel gültig ist, indem ein kleines Testbild vektorisiert wird
 *
 * @return bool|WP_Error True bei Erfolg, WP_Error bei Fehler
 */
public function validate_api_key() {
    if (empty($this->api_key)) {
        return new WP_Error('missing_api_key', 'API-Schlüssel ist nicht konfiguriert.');
    }
    
    // Debug-Informationen sammeln
    $debug_info = "=== Vectorize.ai API Test ===\n";
    $debug_info .= "API URL: https://vectorizer.ai/api/v1/vectorize\n";
    $debug_info .= "API Key: " . substr($this->api_key, 0, 5) . "..." . "\n\n";
    
    // Ein Test-PNG erstellen (statt SVG)
    $test_png_path = VECTORIZE_WP_PATH . 'assets/images/test-image.png';
    
    // Falls die Test-Datei nicht existiert, erstellen wir ein simples PNG
    if (!file_exists($test_png_path)) {
        $test_png_path = wp_tempnam('test_image.png');
        $im = imagecreatetruecolor(100, 100);
        $red = imagecolorallocate($im, 255, 0, 0);
        imagefilledrectangle($im, 0, 0, 99, 99, $red);
        imagepng($im, $test_png_path);
        imagedestroy($im);
    }
    
    // cURL für den API-Aufruf verwenden (direkter Ansatz)
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => 'https://vectorizer.ai/api/v1/vectorize',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_USERPWD => $this->api_key,
        CURLOPT_POST => true,
        CURLOPT_VERBOSE => true,
        CURLOPT_TIMEOUT => 30,
    ]);
    
    // Erstelle ein cURL-File-Objekt mit korrektem Pfad und MIME-Typ
    $cfile = new CURLFile($test_png_path, 'image/png', 'test-image.png');
    
    // Erstelle die POST-Daten
    $post_data = [
        'image' => $cfile,  // Verwende 'image' statt 'file' als Parameter
        'mode' => 'test',
        'detail' => 'low',
    ];
    
    // Setze die POST-Daten
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    
    // Verbose-Ausgabe für Debugging
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    // Führe den Request aus
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    
    // Verbose-Log abrufen
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    $debug_info .= "CURL Info: " . print_r($info, true) . "\n";
    $debug_info .= "CURL Error: " . $error . "\n";
    $debug_info .= "HTTP Status: " . $info['http_code'] . "\n";
    $debug_info .= "Antwort: " . $response . "\n";
    $debug_info .= "Verbose Log: " . $verboseLog . "\n";
    
    // Temporäre Datei aufräumen, wenn wir eine erstellt haben
    if (!file_exists(VECTORIZE_WP_PATH . 'assets/images/test-image.png') && file_exists($test_png_path)) {
        @unlink($test_png_path);
    }
    
    curl_close($ch);
    
    // Auswerten der Antwort
    if ($info['http_code'] == 200 && strpos($info['content_type'], 'image/svg+xml') !== false) {
        // HTTP Status 200 und SVG-Content-Type bedeuten Erfolg
        return true;
    }
    
    // Wenn der erste Versuch nicht erfolgreich war, versuchen wir es mit einem anderen Ansatz
    if ($info['http_code'] != 200 || empty($response)) {
        $debug_info .= "\n=== Zweiter Versuch mit anderem Parameter-Namen ===\n";
        
        // Neuer cURL-Request
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://vectorizer.ai/api/v1/vectorize',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_USERPWD => $this->api_key,
            CURLOPT_POST => true,
            CURLOPT_VERBOSE => true,
            CURLOPT_TIMEOUT => 30,
        ]);
        
        // Erstelle die Test-Datei erneut
        $test_png_path = wp_tempnam('test_image2.png');
        $im = imagecreatetruecolor(100, 100);
        $blue = imagecolorallocate($im, 0, 0, 255);
        imagefilledrectangle($im, 0, 0, 99, 99, $blue);
        imagepng($im, $test_png_path);
        imagedestroy($im);
        
        // Erstelle ein cURL-File-Objekt
        $cfile = new CURLFile($test_png_path, 'image/png', 'test-image2.png');
        
        // Versuche alle möglichen Parameter-Namen
        $post_data = [
            'file' => $cfile,
            'mode' => 'test',
            'detail' => 'low',
        ];
        
        // Setze die POST-Daten
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        
        // Verbose-Ausgabe für Debugging
        $verbose = fopen('php://temp', 'w+');
        curl_setopt($ch, CURLOPT_STDERR, $verbose);
        
        // Führe den Request aus
        $response2 = curl_exec($ch);
        $info2 = curl_getinfo($ch);
        $error2 = curl_error($ch);
        
        // Verbose-Log abrufen
        rewind($verbose);
        $verboseLog2 = stream_get_contents($verbose);
        
        $debug_info .= "CURL Info: " . print_r($info2, true) . "\n";
        $debug_info .= "CURL Error: " . $error2 . "\n";
        $debug_info .= "HTTP Status: " . $info2['http_code'] . "\n";
        $debug_info .= "Antwort: " . $response2 . "\n";
        $debug_info .= "Verbose Log: " . $verboseLog2 . "\n";
        
        // Temporäre Datei aufräumen
        @unlink($test_png_path);
        
        curl_close($ch);
        
        // Auswerten der Antwort
        if ($info2['http_code'] == 200 && !empty($response2)) {
            $data = json_decode($response2, true);
            
            // Prüfen, ob die API einen Erfolg meldet
            if (isset($data['result_url']) || isset($data['id'])) {
                return true;
            }
        }
    }
    
    // Wenn wir bis hierher kommen, war die Validierung nicht erfolgreich
    return new WP_Error('api_validation_failed', $debug_info);
}
 /**
 * Vektorisiert ein Bild mit der Vectorize.ai API
 *
 * @param string $image_path Pfad zum Bild
 * @param array $options Optionen für die Vektorisierung
 * @return array|WP_Error Vektorisierungsergebnis oder Fehler
 */
public function vectorize_image($image_path, $options = array()) {
    // Debug-Protokoll initialisieren
    $debug_log = "=== Vectorize Image Debug Log ===\n";
    $debug_log .= "Time: " . date('Y-m-d H:i:s') . "\n";
    $debug_log .= "Image Path: " . $image_path . "\n";
    $debug_log .= "Options: " . print_r($options, true) . "\n\n";
    
    if (empty($this->api_key)) {
        $debug_log .= "Error: API-Schlüssel ist nicht konfiguriert.\n";
        error_log($debug_log);
        return new WP_Error('missing_api_key', __('API-Schlüssel ist nicht konfiguriert.', 'vectorize-wp'));
    }
    
    if (!file_exists($image_path)) {
        $debug_log .= "Error: Das angegebene Bild existiert nicht.\n";
        error_log($debug_log);
        return new WP_Error('image_not_found', __('Das angegebene Bild existiert nicht.', 'vectorize-wp'));
    }
    
    // Globale Plugin-Optionen abrufen
    $global_options = get_option('vectorize_wp_options', array());
    $debug_log .= "Global Options: " . print_r($global_options, true) . "\n";
    
    // Testmodus aus den globalen Optionen bekommen
    $api_mode = isset($global_options['test_mode']) ? $global_options['test_mode'] : 'off';
    $api_mode = ($api_mode === 'off') ? '' : $api_mode; // Leerer String für Produktionsmodus
    $debug_log .= "API Mode: " . ($api_mode ?: 'production') . "\n";
    
    // Standard-Optionen
    $default_options = array(
        'detail' => 'medium', // low, medium, high
        'format' => 'svg',    // svg, ai, eps
    );
    
    // Optionen zusammenführen
    $options = wp_parse_args($options, $default_options);
    $debug_log .= "Merged Options: " . print_r($options, true) . "\n";
    
    // Temporären Dateinamen erstellen
    $temp_file = wp_tempnam(basename($image_path));
    $debug_log .= "Temporary File: " . $temp_file . "\n";
    
    // Bild in temporäre Datei kopieren
    $copy_result = copy($image_path, $temp_file);
    $debug_log .= "Copy Result: " . ($copy_result ? 'Success' : 'Failed') . "\n";
    
    if (!$copy_result) {
        $debug_log .= "Error: Konnte Datei nicht in temporäre Datei kopieren.\n";
        error_log($debug_log);
        return new WP_Error('copy_failed', __('Konnte Datei nicht in temporäre Datei kopieren.', 'vectorize-wp'));
    }
    
    $mime_type = $this->get_mime_type($image_path);
    $debug_log .= "MIME Type: " . $mime_type . "\n";
    
    // API-Endpunkt überprüfen
    $api_endpoint = 'vectorize';
    $debug_log .= "API Endpoint: " . $api_endpoint . "\n";
    $debug_log .= "Full API URL: " . $this->api_url . $api_endpoint . "\n";
    
    // Direkte cURL-Implementierung für bessere Kontrolle und Fehlerbehebung
$ch = curl_init();
$url = $this->api_url . $api_endpoint;
$debug_log .= "Connecting to API URL: " . $url . "\n";
$debug_log .= "API Key (first 5 chars): " . substr($this->api_key, 0, 5) . "...\n";

// Überprüfen, ob curl installiert und aktiviert ist
if (!function_exists('curl_version')) {
    $debug_log .= "CRITICAL ERROR: cURL is not installed or enabled on this server!\n";
    error_log($debug_log);
    return new WP_Error('curl_not_available', 'cURL ist auf diesem Server nicht verfügbar.');
}

curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
curl_setopt($ch, CURLOPT_USERPWD, $this->api_key);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 60); // längeres Timeout für große Bilder
// SSL Verifizierung deaktivieren falls Probleme (nur für Tests!)
//curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
//curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);

// Debug-Informationen für cURL aktivieren
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

// Zusätzliche HTTP-Header für bessere Kompatibilität
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Accept: application/json',
    'User-Agent: WordPress/VectorizeWP-Plugin'
));
    
    // Formular-Daten vorbereiten - Nur 'image' verwenden, wie in der API-Dokumentation angegeben
$post_data = array(
    'image' => new CURLFile($temp_file, $mime_type, basename($image_path)),
    'mode' => 'test' // Für den ersten Test immer den Test-Modus verwenden
);

// Diese Parameter heißen laut API-Dokumentation anders
if (isset($options['detail'])) {
    $post_data['processing.detail'] = $options['detail'];
}

if (isset($options['format'])) {
    $post_data['output.file_format'] = $options['format'];
}
    
    // Testmodus hinzufügen, wenn aktiviert
    if (!empty($api_mode)) {
        $post_data['mode'] = $api_mode;
    }
    
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
    
    $debug_log .= "POST Data: " . print_r($post_data, true) . "\n";
    
    // Anfrage senden
    $debug_log .= "Sending request...\n";
    $response_body = curl_exec($ch);
    $info = curl_getinfo($ch);
    $error = curl_error($ch);
    
    // Verbose-Log abrufen
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    
    $debug_log .= "HTTP Status: " . $info['http_code'] . "\n";
    $debug_log .= "Content Type: " . $info['content_type'] . "\n";
    $debug_log .= "CURL Error: " . $error . "\n";
    $debug_log .= "Response Size: " . strlen($response_body) . " bytes\n";
    $debug_log .= "CURL Info: " . print_r($info, true) . "\n";
    $debug_log .= "Verbose Log: " . $verboseLog . "\n";
    
    // Temporäre Datei löschen
    @unlink($temp_file);
    $debug_log .= "Temporary file deleted\n";
    
    if ($error) {
        $debug_log .= "Error: cURL-Fehler: " . $error . "\n";
        error_log($debug_log);
        return new WP_Error('curl_error', __('cURL-Fehler: ', 'vectorize-wp') . $error);
    }
    
    $svg_content = '';
    
    // Content-Type überprüfen - direkter SVG-Download oder JSON-Antwort
    if (strpos($info['content_type'], 'image/svg+xml') !== false) {
        // Direkte SVG-Antwort
        $debug_log .= "Direct SVG response received\n";
        $svg_content = $response_body;
    } else {
        // JSON-Antwort oder anderer Typ
$debug_log .= "JSON or other response type received\n";
$debug_log .= "Raw Response Body (first 1000 chars): " . substr($response_body, 0, 1000) . "\n";

// JSON-Decodierung versuchen
$response_data = json_decode($response_body, true);
$json_error = json_last_error();
$debug_log .= "JSON Decode Status: " . ($json_error === JSON_ERROR_NONE ? "Success" : "Error: " . json_last_error_msg()) . "\n";

if ($json_error !== JSON_ERROR_NONE) {
    // Speichere die vollständige Antwort für Debugging-Zwecke
    $debug_file = wp_upload_dir()['basedir'] . '/vectorize-wp/debug-' . time() . '.txt';
    file_put_contents($debug_file, $response_body);
    $debug_log .= "Full response saved to: " . $debug_file . "\n";
    
    error_log($debug_log);
    return new WP_Error('json_decode_error', __('Fehler beim Dekodieren der API-Antwort.', 'vectorize-wp'));
}

$debug_log .= "Decoded Response: " . print_r($response_data, true) . "\n";
        
        // Verarbeiten des Ergebnisses
        if (isset($response_data['result_url'])) {
            // URL zum Herunterladen des Ergebnisses
            $download_url = $response_data['result_url'];
            $debug_log .= "Result URL found: " . $download_url . "\n";
            
            // Ergebnis herunterladen
            $debug_log .= "Downloading result...\n";
            $svg_content = $this->download_result($download_url);
            
            if (is_wp_error($svg_content)) {
                $debug_log .= "Download Error: " . $svg_content->get_error_message() . "\n";
                error_log($debug_log);
                return $svg_content;
            }
        } else {
            $debug_log .= "Error: Keine Ergebnis-URL in der API-Antwort gefunden.\n";
            error_log($debug_log);
            return new WP_Error('missing_result', __('Keine Ergebnis-URL in der API-Antwort gefunden.', 'vectorize-wp'));
        }
    }
    
    // Speicherort für das Ergebnis
    $upload_dir = wp_upload_dir();
    $result_dir = $upload_dir['basedir'] . '/vectorize-wp';
    
    if (!file_exists($result_dir)) {
        wp_mkdir_p($result_dir);
    }
    
    // Dateiname für das Ergebnis erstellen
    $file_info = pathinfo($image_path);
    $result_file = $result_dir . '/' . $file_info['filename'] . '.' . $options['format'];
    
    // Ergebnis speichern
    $save_result = file_put_contents($result_file, $svg_content);
    $debug_log .= "Save Result: " . ($save_result ? 'Success' : 'Failed') . " (Bytes: $save_result)\n";
    
    if (!$save_result) {
        $debug_log .= "Error: Konnte SVG nicht speichern.\n";
        error_log($debug_log);
        return new WP_Error('save_failed', __('Konnte SVG nicht speichern.', 'vectorize-wp'));
    }
    
    $debug_log .= "Vectorization successful!\n";
    // Debug-Log in die WordPress-Debug-Datei schreiben
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log($debug_log);
    }
    
    return array(
        'file_path' => $result_file,
        'file_url' => $upload_dir['baseurl'] . '/vectorize-wp/' . $file_info['filename'] . '.' . $options['format'],
        'format' => $options['format'],
        'content' => $svg_content,
        'is_test_mode' => !empty($api_mode),
        'test_mode' => $api_mode,
    );
}
    
    /**
     * Lädt das Ergebnis von der angegebenen URL herunter
     *
     * @param string $url URL zum Herunterladen
     * @return string|WP_Error Inhalt oder Fehler
     */
    private function download_result($url) {
        $response = wp_remote_get($url, array(
            'timeout' => 60,
            'sslverify' => true,
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code !== 200) {
            return new WP_Error('download_failed', __('Herunterladen des Ergebnisses fehlgeschlagen.', 'vectorize-wp'));
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    /**
     * Ermittelt den MIME-Typ einer Datei
     *
     * @param string $file Dateipfad
     * @return string MIME-Typ
     */
    private function get_mime_type($file) {
        $mime_types = array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'bmp' => 'image/bmp',
            'webp' => 'image/webp',
        );
        
        $file_ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        
        foreach ($mime_types as $ext_pattern => $mime_type) {
            $ext_pattern = explode('|', $ext_pattern);
            if (in_array($file_ext, $ext_pattern)) {
                return $mime_type;
            }
        }
        
        return 'application/octet-stream';
    }
}