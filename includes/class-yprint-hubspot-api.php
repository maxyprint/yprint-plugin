<?php
/**
 * YPrint HubSpot API Integration
 * 
 * @package YPrint
 * @since 1.3.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class YPrint_HubSpot_API {
    
    private static $instance = null;
    private $api_key = null;
    private $base_url = 'https://api.hubapi.com';
    private $enabled = false;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->api_key = get_option('yprint_hubspot_api_key', '');
        $this->enabled = get_option('yprint_hubspot_enabled', false) && !empty($this->api_key);
    }
    
    /**
     * Prüft ob HubSpot Integration aktiviert ist
     */
    public function is_enabled() {
        return $this->enabled;
    }
    
    /**
     * Testet die HubSpot API Verbindung
     */
    public function test_connection() {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'HubSpot Integration ist nicht aktiviert oder API Key fehlt'
            );
        }
        
        // Teste mit einem einfacheren Endpoint
        $response = wp_remote_get($this->base_url . '/crm/v3/objects/contacts?limit=1', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
                'User-Agent' => 'YPrint-Plugin/1.0',
                'Accept' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if ($status_code === 200) {
            return array(
                'success' => true,
                'message' => 'HubSpot API Verbindung erfolgreich'
            );
        } else {
            $error_data = json_decode($body, true);
            $error_message = isset($error_data['message']) ? $error_data['message'] : 'Unbekannter Fehler';
            
            // Debug-Informationen
            error_log('YPrint HubSpot Debug - Status: ' . $status_code);
            error_log('YPrint HubSpot Debug - Response: ' . $body);
            error_log('YPrint HubSpot Debug - API Key (first 10 chars): ' . substr($this->api_key, 0, 10) . '...');
            
            // Versuche alternative Authentifizierung
            if ($status_code === 401) {
                error_log('YPrint HubSpot Debug - Versuche alternative Authentifizierung...');
                
                // Alternative: API Key als Query Parameter
                $alt_response = wp_remote_get($this->base_url . '/crm/v3/objects/contacts?limit=1&hapikey=' . $this->api_key, array(
                    'headers' => array(
                        'Content-Type' => 'application/json',
                        'User-Agent' => 'YPrint-Plugin/1.0',
                        'Accept' => 'application/json'
                    ),
                    'timeout' => 30
                ));
                
                if (!is_wp_error($alt_response)) {
                    $alt_status = wp_remote_retrieve_response_code($alt_response);
                    if ($alt_status === 200) {
                        return array(
                            'success' => true,
                            'message' => 'HubSpot API Verbindung erfolgreich (alternative Methode)'
                        );
                    }
                }
            }
            
            // Spezifische Fehlermeldungen
            if (strpos($error_message, 'Authentication credentials not found') !== false) {
                $error_message .= ' - Bitte prüfe, ob du den korrekten Access Token aus der Private App verwendest.';
            } elseif (strpos($error_message, 'Invalid API key') !== false) {
                $error_message .= ' - Der API Key scheint ungültig zu sein. Erstelle eine neue Private App.';
            } elseif ($status_code === 403) {
                $error_message .= ' - Fehlende Berechtigungen. Prüfe die Scopes in deiner Private App.';
            }
            
            return array(
                'success' => false,
                'message' => 'API Fehler: ' . $error_message . ' (Status: ' . $status_code . ')'
            );
        }
    }
    
    /**
     * Erstellt einen neuen HubSpot Kontakt
     */
    public function create_contact($contact_data) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'HubSpot Integration ist nicht aktiviert'
            );
        }
        
        // Validiere erforderliche Felder
        if (empty($contact_data['email'])) {
            return array(
                'success' => false,
                'message' => 'E-Mail-Adresse ist erforderlich'
            );
        }
        
        // Bereite HubSpot Kontakt-Daten vor
        $hubspot_data = array(
            'properties' => array(
                'email' => $contact_data['email']
            )
        );
        
        // Optionale Felder hinzufügen
        if (!empty($contact_data['firstname'])) {
            $hubspot_data['properties']['firstname'] = $contact_data['firstname'];
        }
        
        if (!empty($contact_data['lastname'])) {
            $hubspot_data['properties']['lastname'] = $contact_data['lastname'];
        }
        
        if (!empty($contact_data['username'])) {
            $hubspot_data['properties']['username'] = $contact_data['username'];
        }
        
        if (!empty($contact_data['registration_date'])) {
            $hubspot_data['properties']['createdate'] = $contact_data['registration_date'];
        }
        
        // Cookie-Präferenzen als Custom Property
        if (!empty($contact_data['cookie_preferences'])) {
            $hubspot_data['properties']['cookie_preferences'] = json_encode($contact_data['cookie_preferences']);
        }
        
        // Sende Request an HubSpot
        $response = wp_remote_post($this->base_url . '/crm/v3/objects/contacts', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($hubspot_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('YPrint HubSpot: Request error - ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        
        if ($status_code === 201) {
            // Erfolgreich erstellt
            error_log('YPrint HubSpot: Contact created successfully - ID: ' . $response_data['id']);
            return array(
                'success' => true,
                'contact_id' => $response_data['id'],
                'message' => 'Kontakt erfolgreich erstellt'
            );
        } else {
            // Fehler beim Erstellen
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unbekannter Fehler';
            error_log('YPrint HubSpot: Failed to create contact - Status: ' . $status_code . ', Message: ' . $error_message);
            
            return array(
                'success' => false,
                'message' => 'Fehler beim Erstellen des Kontakts: ' . $error_message
            );
        }
    }
    
    /**
     * Aktualisiert einen bestehenden HubSpot Kontakt
     */
    public function update_contact($contact_id, $contact_data) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'HubSpot Integration ist nicht aktiviert'
            );
        }
        
        // Bereite Update-Daten vor
        $hubspot_data = array(
            'properties' => array()
        );
        
        // Füge zu aktualisierende Felder hinzu
        foreach ($contact_data as $key => $value) {
            if ($key === 'cookie_preferences') {
                $hubspot_data['properties'][$key] = json_encode($value);
            } else {
                $hubspot_data['properties'][$key] = $value;
            }
        }
        
        // Sende Update Request
        $response = wp_remote_patch($this->base_url . '/crm/v3/objects/contacts/' . $contact_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($hubspot_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('YPrint HubSpot: Update request error - ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        
        if ($status_code === 200) {
            error_log('YPrint HubSpot: Contact updated successfully - ID: ' . $contact_id);
            return array(
                'success' => true,
                'message' => 'Kontakt erfolgreich aktualisiert'
            );
        } else {
            $body = wp_remote_retrieve_body($response);
            $response_data = json_decode($body, true);
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unbekannter Fehler';
            
            error_log('YPrint HubSpot: Failed to update contact - Status: ' . $status_code . ', Message: ' . $error_message);
            
            return array(
                'success' => false,
                'message' => 'Fehler beim Aktualisieren des Kontakts: ' . $error_message
            );
        }
    }
    
    /**
     * Sucht einen Kontakt nach E-Mail
     */
    public function find_contact_by_email($email) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'HubSpot Integration ist nicht aktiviert'
            );
        }
        
        $response = wp_remote_get($this->base_url . '/crm/v3/objects/contacts?filter=email&filterOperator=EQ&filterValue=' . urlencode($email), array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        
        if ($status_code === 200 && !empty($response_data['results'])) {
            return array(
                'success' => true,
                'contact' => $response_data['results'][0],
                'found' => true
            );
        } else {
            return array(
                'success' => true,
                'found' => false,
                'message' => 'Kontakt nicht gefunden'
            );
        }
    }
    
    /**
     * Lädt aktuelle Cookie-Präferenzen für einen Kontakt
     */
    public function get_contact_cookie_preferences($contact_id) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'HubSpot Integration ist nicht aktiviert'
            );
        }
        
        $response = wp_remote_get($this->base_url . '/crm/v3/objects/contacts/' . $contact_id, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return array(
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        
        if ($status_code === 200 && isset($response_data['properties']['cookie_preferences'])) {
            $preferences = json_decode($response_data['properties']['cookie_preferences'], true);
            return array(
                'success' => true,
                'preferences' => $preferences
            );
        } else {
            return array(
                'success' => false,
                'message' => 'Keine Cookie-Präferenzen gefunden'
            );
        }
    }
}