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
        
        // Optionale Felder hinzufügen (nur gültige Properties)
        if (!empty($contact_data['firstname'])) {
            $hubspot_data['properties']['firstname'] = $contact_data['firstname'];
        }
        
        if (!empty($contact_data['lastname'])) {
            $hubspot_data['properties']['lastname'] = $contact_data['lastname'];
        }
        
        // Custom Properties für zusätzliche Daten
        if (!empty($contact_data['username'])) {
            $hubspot_data['properties']['yprint_username'] = $contact_data['username'];
        }
        
        if (!empty($contact_data['registration_date'])) {
            // HubSpot erwartet Unix-Timestamp für Datumsfelder
            $timestamp = strtotime($contact_data['registration_date']);
            if ($timestamp !== false) {
                $hubspot_data['properties']['yprint_registration_date'] = $timestamp;
            }
        }
        
        // Cookie-Präferenzen als Custom Property
        if (!empty($contact_data['cookie_preferences'])) {
            $hubspot_data['properties']['yprint_cookie_preferences'] = json_encode($contact_data['cookie_preferences']);
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
            
            // Spezifische Fehlerbehandlung
            if (isset($response_data['errors'])) {
                foreach ($response_data['errors'] as $error) {
                    if (isset($error['code']) && $error['code'] === 'PROPERTY_DOESNT_EXIST') {
                        error_log('YPrint HubSpot: Custom property missing - ' . $error['message']);
                    }
                }
            }
            
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
    
    /**
     * ✅ NEU: Erstellt eine HubSpot-Aktivität bei erstmaliger Cookie-Auswahl
     */
    public function create_initial_cookie_activity($contact_id, $cookie_data) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'HubSpot Integration ist nicht aktiviert'
            );
        }
        
        // Bereite Aktivitätsdaten vor
        $activity_data = array(
            'properties' => array(
                'hs_timestamp' => time() * 1000, // HubSpot erwartet Millisekunden
                'hs_note_body' => $this->format_initial_cookie_note($cookie_data),
                'hs_attachment_ids' => '',
                'hs_note_body_pre_processing' => $this->format_initial_cookie_note($cookie_data)
            )
        );
        
        // Sende Request an HubSpot Engagements API
        $response = wp_remote_post($this->base_url . '/crm/v3/objects/notes', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($activity_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('YPrint HubSpot: Initial cookie activity request error - ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        
        if ($status_code === 201) {
            // Erfolgreich erstellt - verknüpfe mit Kontakt
            $note_id = $response_data['id'];
            $this->associate_note_with_contact($note_id, $contact_id);
            
            error_log('YPrint HubSpot: Initial cookie activity created successfully - Note ID: ' . $note_id . ' for Contact ID: ' . $contact_id);
            return array(
                'success' => true,
                'activity_id' => $note_id,
                'message' => 'Erstmalige Cookie-Aktivität erfolgreich erstellt'
            );
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unbekannter Fehler';
            error_log('YPrint HubSpot: Failed to create initial cookie activity - Status: ' . $status_code . ', Message: ' . $error_message);
            
            return array(
                'success' => false,
                'message' => 'Fehler beim Erstellen der erstmaligen Cookie-Aktivität: ' . $error_message
            );
        }
    }
    
    /**
     * ✅ NEU: Erstellt eine HubSpot-Aktivität bei Cookie-Aktualisierung
     */
    public function create_cookie_update_activity($contact_id, $cookie_data, $previous_data = null) {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'HubSpot Integration ist nicht aktiviert'
            );
        }
        
        // Bereite Aktivitätsdaten vor
        $activity_data = array(
            'properties' => array(
                'hs_timestamp' => time() * 1000, // HubSpot erwartet Millisekunden
                'hs_note_body' => $this->format_cookie_update_note($cookie_data, $previous_data),
                'hs_attachment_ids' => '',
                'hs_note_body_pre_processing' => $this->format_cookie_update_note($cookie_data, $previous_data)
            )
        );
        
        // Sende Request an HubSpot Engagements API
        $response = wp_remote_post($this->base_url . '/crm/v3/objects/notes', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($activity_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('YPrint HubSpot: Cookie update activity request error - ' . $response->get_error_message());
            return array(
                'success' => false,
                'message' => 'Verbindungsfehler: ' . $response->get_error_message()
            );
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $response_data = json_decode($body, true);
        
        if ($status_code === 201) {
            // Erfolgreich erstellt - verknüpfe mit Kontakt
            $note_id = $response_data['id'];
            $this->associate_note_with_contact($note_id, $contact_id);
            
            error_log('YPrint HubSpot: Cookie update activity created successfully - Note ID: ' . $note_id . ' for Contact ID: ' . $contact_id);
            return array(
                'success' => true,
                'activity_id' => $note_id,
                'message' => 'Cookie-Aktualisierungsaktivität erfolgreich erstellt'
            );
        } else {
            $error_message = isset($response_data['message']) ? $response_data['message'] : 'Unbekannter Fehler';
            error_log('YPrint HubSpot: Failed to create cookie update activity - Status: ' . $status_code . ', Message: ' . $error_message);
            
            return array(
                'success' => false,
                'message' => 'Fehler beim Erstellen der Cookie-Aktualisierungsaktivität: ' . $error_message
            );
        }
    }
    
    /**
     * ✅ NEU: Verknüpft eine Notiz mit einem Kontakt
     */
    private function associate_note_with_contact($note_id, $contact_id) {
        $association_data = array(
            'inputs' => array(
                array(
                    'from' => array(
                        'id' => $note_id
                    ),
                    'to' => array(
                        'id' => $contact_id
                    ),
                    'types' => array(
                        array(
                            'associationCategory' => 'HUBSPOT_DEFINED',
                            'associationTypeId' => 1 // Note to Contact association
                        )
                    )
                )
            )
        );
        
        $response = wp_remote_post($this->base_url . '/crm/v4/objects/notes/associations/batch/upsert', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($association_data),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('YPrint HubSpot: Failed to associate note with contact - ' . $response->get_error_message());
        } else {
            $status_code = wp_remote_retrieve_response_code($response);
            if ($status_code === 200) {
                error_log('YPrint HubSpot: Note successfully associated with contact');
            } else {
                error_log('YPrint HubSpot: Failed to associate note with contact - Status: ' . $status_code);
            }
        }
    }
    
    /**
     * ✅ NEU: Formatiert Notiz für erstmalige Cookie-Auswahl
     */
    private function format_initial_cookie_note($cookie_data) {
        $note = "🍪 **Erstmalige Cookie-Auswahl**\n\n";
        $note .= "**Zeitpunkt:** " . date('d.m.Y H:i:s') . "\n\n";
        $note .= "**Cookie-Präferenzen:**\n";
        
        $cookie_labels = array(
            'cookie_essential' => 'Essenzielle Cookies',
            'cookie_analytics' => 'Analytics Cookies',
            'cookie_marketing' => 'Marketing Cookies',
            'cookie_functional' => 'Funktionale Cookies'
        );
        
        foreach ($cookie_labels as $key => $label) {
            $status = isset($cookie_data[$key]) && $cookie_data[$key] ? '✅ Akzeptiert' : '❌ Abgelehnt';
            $note .= "- {$label}: {$status}\n";
        }
        
        $note .= "\n**Prozess:** Erstmalige Cookie-Auswahl durch Benutzer\n";
        $note .= "**Quelle:** YPrint Cookie-Consent-System";
        
        return $note;
    }
    
    /**
     * ✅ NEU: Formatiert Notiz für Cookie-Aktualisierung
     */
    private function format_cookie_update_note($cookie_data, $previous_data = null) {
        $note = "🍪 **Cookie-Präferenzen aktualisiert**\n\n";
        $note .= "**Zeitpunkt:** " . date('d.m.Y H:i:s') . "\n\n";
        
        if ($previous_data) {
            $note .= "**Änderungen:**\n";
            $cookie_labels = array(
                'cookie_essential' => 'Essenzielle Cookies',
                'cookie_analytics' => 'Analytics Cookies',
                'cookie_marketing' => 'Marketing Cookies',
                'cookie_functional' => 'Funktionale Cookies'
            );
            
            foreach ($cookie_labels as $key => $label) {
                $old_status = isset($previous_data[$key]) && $previous_data[$key] ? 'Akzeptiert' : 'Abgelehnt';
                $new_status = isset($cookie_data[$key]) && $cookie_data[$key] ? 'Akzeptiert' : 'Abgelehnt';
                
                if ($old_status !== $new_status) {
                    $note .= "- {$label}: {$old_status} → {$new_status}\n";
                }
            }
            $note .= "\n";
        }
        
        $note .= "**Aktuelle Cookie-Präferenzen:**\n";
        $cookie_labels = array(
            'cookie_essential' => 'Essenzielle Cookies',
            'cookie_analytics' => 'Analytics Cookies',
            'cookie_marketing' => 'Marketing Cookies',
            'cookie_functional' => 'Funktionale Cookies'
        );
        
        foreach ($cookie_labels as $key => $label) {
            $status = isset($cookie_data[$key]) && $cookie_data[$key] ? '✅ Akzeptiert' : '❌ Abgelehnt';
            $note .= "- {$label}: {$status}\n";
        }
        
        $note .= "\n**Prozess:** Cookie-Präferenzen aktualisiert\n";
        $note .= "**Quelle:** YPrint Cookie-Consent-System";
        
        return $note;
    }
    
    /**
     * ✅ NEU: Zentrale Methode für Cookie-Aktivitäten
     */
    public function handle_cookie_activity($email, $cookie_data, $activity_type = 'update') {
        if (!$this->is_enabled()) {
            return array(
                'success' => false,
                'message' => 'HubSpot Integration ist nicht aktiviert'
            );
        }
        
        // Suche Kontakt nach E-Mail
        $contact_result = $this->find_contact_by_email($email);
        
        if (!$contact_result['success']) {
            error_log('YPrint HubSpot: Failed to find contact for cookie activity - ' . $contact_result['message']);
            return $contact_result;
        }
        
        if (!$contact_result['found']) {
            error_log('YPrint HubSpot: Contact not found for cookie activity - Email: ' . $email);
            return array(
                'success' => false,
                'message' => 'Kontakt nicht gefunden für Cookie-Aktivität'
            );
        }
        
        $contact_id = $contact_result['contact']['id'];
        
        // Erstelle entsprechende Aktivität
        if ($activity_type === 'initial') {
            return $this->create_initial_cookie_activity($contact_id, $cookie_data);
        } else {
            // Für Updates: Lade vorherige Daten für Vergleich
            $previous_data = null;
            $preferences_result = $this->get_contact_cookie_preferences($contact_id);
            if ($preferences_result['success']) {
                $previous_data = $preferences_result['preferences'];
            }
            
            return $this->create_cookie_update_activity($contact_id, $cookie_data, $previous_data);
        }
    }
}