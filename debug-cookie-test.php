<?php
/**
 * Debug-Script für Cookie-Consent-Test
 * 
 * Dieses Script testet die Cookie-Speicherung und -Validierung
 * für Gäste ohne Account.
 */

// WordPress laden
require_once('../../../wp-load.php');

// YPrint Consent Manager laden
if (class_exists('YPrint_Consent_Manager')) {
    $consent_manager = YPrint_Consent_Manager::get_instance();
    
    echo "<h2>🍪 Cookie Consent Debug Test</h2>";
    
    // Test 1: Aktuelle Cookies anzeigen
    echo "<h3>1. Aktuelle Cookies:</h3>";
    echo "<pre>";
    print_r($_COOKIE);
    echo "</pre>";
    
    // Test 2: Banner-Anzeige prüfen
    echo "<h3>2. Banner-Anzeige Test:</h3>";
    $should_show = $consent_manager->should_show_banner_initially();
    echo "Banner sollte angezeigt werden: " . ($should_show ? 'JA' : 'NEIN') . "<br>";
    
    // Test 3: Gast-Consent-Entscheidung prüfen
    echo "<h3>3. Gast-Consent-Entscheidung:</h3>";
    $reflection = new ReflectionClass($consent_manager);
    $method = $reflection->getMethod('has_guest_consent_decision');
    $method->setAccessible(true);
    $has_decision = $method->invoke($consent_manager);
    echo "Hat Gast-Entscheidung: " . ($has_decision ? 'JA' : 'NEIN') . "<br>";
    
    // Test 4: Gültige Gast-Consent prüfen
    echo "<h3>4. Gültige Gast-Consent:</h3>";
    $method = $reflection->getMethod('has_valid_guest_consent');
    $method->setAccessible(true);
    $has_valid = $method->invoke($consent_manager);
    echo "Hat gültige Gast-Consent: " . ($has_valid ? 'JA' : 'NEIN') . "<br>";
    
    // Test 5: Cookie-Speicherung simulieren
    echo "<h3>5. Cookie-Speicherung simulieren:</h3>";
    $test_consent_data = array(
        'cookie_essential' => true,
        'cookie_analytics' => false,
        'cookie_marketing' => false,
        'cookie_functional' => false
    );
    
    $method = $reflection->getMethod('save_guest_consent');
    $method->setAccessible(true);
    $result = $method->invoke($consent_manager, $test_consent_data);
    echo "Cookie-Speicherung erfolgreich: " . ($result ? 'JA' : 'NEIN') . "<br>";
    
    // Test 6: Nach Speicherung erneut prüfen
    echo "<h3>6. Nach Speicherung - Banner-Anzeige:</h3>";
    $should_show_after = $consent_manager->should_show_banner_initially();
    echo "Banner sollte angezeigt werden: " . ($should_show_after ? 'JA' : 'NEIN') . "<br>";
    
    // Test 7: Cookie-Daten analysieren
    echo "<h3>7. Cookie-Daten Analyse:</h3>";
    if (isset($_COOKIE['yprint_consent_preferences'])) {
        $preferences = json_decode(stripslashes($_COOKIE['yprint_consent_preferences']), true);
        echo "Cookie-Daten:<br><pre>";
        print_r($preferences);
        echo "</pre>";
    } else {
        echo "Keine Cookie-Präferenzen vorhanden<br>";
    }
    
} else {
    echo "<h2>❌ YPrint Consent Manager nicht gefunden!</h2>";
}

echo "<hr>";
echo "<p><strong>Hinweis:</strong> Dieses Script ist nur für Debugging-Zwecke. Lösche es nach dem Test.</p>";
?>