<?php
/**
 * Abstrakte API-Klasse für Vectorize WP
 *
 * Diese Klasse dient als Basis für verschiedene API-Implementierungen.
 */

// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}

abstract class Vectorize_WP_API {
    /**
     * API-Schlüssel
     *
     * @var string
     */
    protected $api_key;
    
    /**
     * API Endpunkt URL
     *
     * @var string
     */
    protected $api_url;
    
    /**
     * Timeout für API-Anfragen in Sekunden
     *
     * @var int
     */
    protected $timeout = 30;
    
    /**
     * Konstruktor
     *
     * @param string $api_key API-Schlüssel
     */
    public function __construct($api_key = '') {
        $this->api_key = $api_key;
        
        if (empty($this->api_key)) {
            $options = get_option('vectorize_wp_options', array());
            if (!empty($options['api_key'])) {
                $this->api_key = $options['api_key'];
            }
        }
    }
    
    /**
     * Sendet eine Anfrage an die API
     *
     * @param string $endpoint API-Endpunkt
     * @param array $params Parameter für die Anfrage
     * @param string $method HTTP-Methode (GET, POST, etc.)
     * @return array|WP_Error Antwort der API oder Fehler
     */
    protected function send_request($endpoint, $params = array(), $method = 'POST') {
        // Vollständige API-URL erstellen
        $url = $this->api_url . $endpoint;
        
        // Anfrage-Argumente
        $args = array(
            'method'      => $method,
            'timeout'     => $this->timeout,
            'redirection' => 5,
            'httpversion' => '1.1',
            'headers'     => array(
                'Authorization' => 'Basic ' . base64_encode($this->api_key)
            ),
            'sslverify'   => true,
        );
        
        // Bei POST-Anfragen Parameter hinzufügen
        if ($method === 'POST') {
            if (isset($params['file'])) {
                // Multipart/form-data für Datei-Uploads
                $args['body'] = $params;
            } else {
                // JSON für normale POST-Anfragen
                $args['headers']['Content-Type'] = 'application/json';
                $args['body'] = json_encode($params);
            }
        } elseif ($method === 'GET' && !empty($params)) {
            // Bei GET-Anfragen Parameter an URL anhängen
            $url = add_query_arg($params, $url);
        }
        
        // Anfrage senden
        $response = wp_remote_request($url, $args);
        
        // Fehlerbehandlung
        if (is_wp_error($response)) {
            return $response;
        }
        
        // HTTP-Statuscode überprüfen
        $status_code = wp_remote_retrieve_response_code($response);
        if ($status_code >= 400) {
            $error_message = wp_remote_retrieve_response_message($response);
            $body = wp_remote_retrieve_body($response);
            $body_data = json_decode($body, true);
            
            if (isset($body_data['error'])) {
                $error_message = $body_data['error'];
            } elseif (isset($body_data['message'])) {
                $error_message = $body_data['message'];
            }
            
            return new WP_Error('api_error', $error_message, array(
                'status' => $status_code,
                'body' => $body_data
            ));
        }
        
        // Antwort verarbeiten
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Wenn die Antwort kein gültiges JSON ist, Rohtext zurückgeben
            return array(
                'body' => $body,
                'headers' => wp_remote_retrieve_headers($response),
                'status' => $status_code
            );
        }
        
        return $data;
    }
    
    /**
     * Überprüft, ob der API-Schlüssel gültig ist
     *
     * @return bool True, wenn der API-Schlüssel gültig ist, sonst False
     */
    abstract public function validate_api_key();
    
    /**
     * Vektorisiert ein Bild
     *
     * @param string $image_path Pfad zum Bild
     * @param array $options Optionen für die Vektorisierung
     * @return array|WP_Error Vektorisierungsergebnis oder Fehler
     */
    abstract public function vectorize_image($image_path, $options = array());
}