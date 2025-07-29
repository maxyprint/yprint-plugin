<?php
/**
 * Test-Datei für HubSpot Cookie-Aktivitäten
 * 
 * Diese Datei dient zum Testen der erweiterten HubSpot-Integration
 * mit Cookie-Aktivitäten.
 * 
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test-Funktion für HubSpot Cookie-Aktivitäten
 */
function test_hubspot_cookie_activities() {
    if (!current_user_can('manage_options')) {
        wp_die('Nicht autorisiert');
    }
    
    echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
    echo '<h2>🍪 HubSpot Cookie-Aktivitäten Test</h2>';
    
    // Test HubSpot API
    if (class_exists('YPrint_HubSpot_API')) {
        echo '<h3>HubSpot API Test:</h3>';
        $api = YPrint_HubSpot_API::get_instance();
        
        if ($api->is_enabled()) {
            echo '<p style="color: green;">✅ HubSpot API ist aktiviert</p>';
            
            // Teste Verbindung
            $connection_test = $api->test_connection();
            if ($connection_test['success']) {
                echo '<p style="color: green;">✅ HubSpot Verbindung erfolgreich</p>';
            } else {
                echo '<p style="color: red;">❌ HubSpot Verbindung fehlgeschlagen: ' . $connection_test['message'] . '</p>';
            }
            
            // Teste Cookie-Aktivitäten
            echo '<h3>Cookie-Aktivitäten Test:</h3>';
            
            // Test-Daten für Cookie-Aktivitäten
            $test_email = 'test@example.com';
            $test_cookie_data = array(
                'cookie_essential' => true,
                'cookie_analytics' => false,
                'cookie_marketing' => true,
                'cookie_functional' => false
            );
            
            // Teste erstmalige Cookie-Aktivität
            echo '<h4>Test: Erstmalige Cookie-Aktivität</h4>';
            $initial_result = $api->handle_cookie_activity($test_email, $test_cookie_data, 'initial');
            
            if ($initial_result['success']) {
                echo '<p style="color: green;">✅ Erstmalige Cookie-Aktivität erfolgreich erstellt</p>';
                echo '<p>Activity ID: ' . $initial_result['activity_id'] . '</p>';
            } else {
                echo '<p style="color: red;">❌ Erstmalige Cookie-Aktivität fehlgeschlagen: ' . $initial_result['message'] . '</p>';
            }
            
            // Teste Cookie-Update-Aktivität
            echo '<h4>Test: Cookie-Update-Aktivität</h4>';
            $update_cookie_data = array(
                'cookie_essential' => true,
                'cookie_analytics' => true, // Geändert
                'cookie_marketing' => true,
                'cookie_functional' => true  // Geändert
            );
            
            $update_result = $api->handle_cookie_activity($test_email, $update_cookie_data, 'update');
            
            if ($update_result['success']) {
                echo '<p style="color: green;">✅ Cookie-Update-Aktivität erfolgreich erstellt</p>';
                echo '<p>Activity ID: ' . $update_result['activity_id'] . '</p>';
            } else {
                echo '<p style="color: red;">❌ Cookie-Update-Aktivität fehlgeschlagen: ' . $update_result['message'] . '</p>';
            }
            
        } else {
            echo '<p style="color: red;">❌ HubSpot API ist nicht aktiviert</p>';
        }
    } else {
        echo '<p style="color: red;">❌ YPrint_HubSpot_API Klasse nicht gefunden</p>';
    }
    
    // Test Consent Manager
    echo '<h3>Consent Manager Test:</h3>';
    if (class_exists('YPrint_Consent_Manager')) {
        echo '<p style="color: green;">✅ YPrint_Consent_Manager Klasse gefunden</p>';
        
        $consent_manager = YPrint_Consent_Manager::get_instance();
        
        // Teste erstmalige Cookie-Erkennung
        $test_consent_data = array(
            'cookie_essential' => true,
            'cookie_analytics' => false,
            'cookie_marketing' => true,
            'cookie_functional' => false
        );
        
        // Verwende Reflection um private Methode zu testen
        $reflection = new ReflectionClass($consent_manager);
        $method = $reflection->getMethod('is_initial_cookie_consent');
        $method->setAccessible(true);
        
        $is_initial = $method->invoke($consent_manager, $test_consent_data);
        echo '<p>Ist erstmalige Cookie-Auswahl: ' . ($is_initial ? 'JA' : 'NEIN') . '</p>';
        
    } else {
        echo '<p style="color: red;">❌ YPrint_Consent_Manager Klasse nicht gefunden</p>';
    }
    
    // Test Cookie-Präferenzen Extraktion
    echo '<h3>Cookie-Präferenzen Extraktion Test:</h3>';
    if (function_exists('get_cookie_preferences_from_registration')) {
        echo '<p style="color: green;">✅ get_cookie_preferences_from_registration Funktion gefunden</p>';
        
        // Simuliere POST-Daten
        $_POST['final_cookie_essential'] = 'true';
        $_POST['final_cookie_analytics'] = 'false';
        $_POST['final_cookie_marketing'] = 'true';
        $_POST['final_cookie_functional'] = 'false';
        
        $cookie_prefs = get_cookie_preferences_from_registration();
        echo '<p>Extrahiert Cookie-Präferenzen: ' . json_encode($cookie_prefs) . '</p>';
        
    } else {
        echo '<p style="color: red;">❌ get_cookie_preferences_from_registration Funktion nicht gefunden</p>';
    }
    
    // Debug-Informationen
    echo '<h3>Debug-Informationen:</h3>';
    echo '<p>WordPress Version: ' . get_bloginfo('version') . '</p>';
    echo '<p>PHP Version: ' . phpversion() . '</p>';
    echo '<p>HubSpot API Key vorhanden: ' . (get_option('yprint_hubspot_api_key') ? 'JA' : 'NEIN') . '</p>';
    echo '<p>HubSpot Integration aktiviert: ' . (get_option('yprint_hubspot_enabled') ? 'JA' : 'NEIN') . '</p>';
    
    // Aktuelle Cookies anzeigen
    echo '<h3>Aktuelle Cookies:</h3>';
    echo '<pre>';
    print_r($_COOKIE);
    echo '</pre>';
    
    echo '</div>';
}

/**
 * Admin-Menü für Tests hinzufügen
 */
function add_hubspot_test_menu() {
    add_submenu_page(
        'tools.php',
        'HubSpot Cookie Test',
        'HubSpot Cookie Test',
        'manage_options',
        'hubspot-cookie-test',
        'test_hubspot_cookie_activities'
    );
}
add_action('admin_menu', 'add_hubspot_test_menu');

/**
 * AJAX-Handler für Cookie-Aktivitäten Test
 */
function test_cookie_activity_ajax() {
    check_ajax_referer('yprint_test_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Nicht autorisiert');
    }
    
    $test_type = sanitize_text_field($_POST['test_type']);
    $email = sanitize_email($_POST['email']);
    $cookie_data = isset($_POST['cookie_data']) ? $_POST['cookie_data'] : array();
    
    if (class_exists('YPrint_HubSpot_API')) {
        $api = YPrint_HubSpot_API::get_instance();
        
        if ($api->is_enabled()) {
            $result = $api->handle_cookie_activity($email, $cookie_data, $test_type);
            
            if ($result['success']) {
                wp_send_json_success(array(
                    'message' => 'Cookie-Aktivität erfolgreich erstellt',
                    'activity_id' => $result['activity_id']
                ));
            } else {
                wp_send_json_error(array(
                    'message' => 'Fehler beim Erstellen der Cookie-Aktivität: ' . $result['message']
                ));
            }
        } else {
            wp_send_json_error('HubSpot API ist nicht aktiviert');
        }
    } else {
        wp_send_json_error('HubSpot API Klasse nicht gefunden');
    }
}
add_action('wp_ajax_test_cookie_activity', 'test_cookie_activity_ajax');

/**
 * JavaScript für interaktive Tests
 */
function enqueue_hubspot_test_script() {
    if (isset($_GET['page']) && $_GET['page'] === 'hubspot-cookie-test') {
        wp_enqueue_script('jquery');
        wp_localize_script('jquery', 'yprintTest', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yprint_test_nonce')
        ));
        
        echo '<script>
        jQuery(document).ready(function($) {
            $("#test-cookie-activity").on("click", function() {
                var testData = {
                    action: "test_cookie_activity",
                    nonce: yprintTest.nonce,
                    test_type: "initial",
                    email: "test@example.com",
                    cookie_data: {
                        cookie_essential: true,
                        cookie_analytics: false,
                        cookie_marketing: true,
                        cookie_functional: false
                    }
                };
                
                $.post(yprintTest.ajaxUrl, testData, function(response) {
                    if (response.success) {
                        alert("Cookie-Aktivität erfolgreich erstellt! ID: " + response.data.activity_id);
                    } else {
                        alert("Fehler: " + response.data.message);
                    }
                });
            });
        });
        </script>';
        
        echo '<button id="test-cookie-activity" style="margin: 10px; padding: 10px; background: #0073aa; color: white; border: none; cursor: pointer;">Test Cookie-Aktivität erstellen</button>';
    }
}
add_action('admin_footer', 'enqueue_hubspot_test_script');