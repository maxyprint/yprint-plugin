<?php
/**
 * Test-Script für Cookie-Persistenz
 */

// WordPress laden
require_once('../../../wp-load.php');

echo "<h2>🍪 Cookie-Persistenz Test</h2>";

// Test 1: Aktuelle Cookies
echo "<h3>1. Aktuelle Cookies:</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Test 2: Cookie setzen (wenn nicht vorhanden)
if (!isset($_COOKIE['yprint_consent_preferences'])) {
    echo "<h3>2. Cookie setzen (Simulation):</h3>";
    
    $test_data = array(
        'consents' => array(
            'cookie_essential' => true,
            'cookie_analytics' => false,
            'cookie_marketing' => false,
            'cookie_functional' => false
        ),
        'timestamp' => time(),
        'version' => '1.0'
    );
    
    // Cookies setzen
    setcookie('yprint_consent_preferences', json_encode($test_data), time() + (365 * 24 * 60 * 60), '/', '', is_ssl(), true);
    setcookie('yprint_consent_timestamp', time(), time() + (365 * 24 * 60 * 60), '/', '', is_ssl(), true);
    setcookie('yprint_consent_decision', '1', time() + (365 * 24 * 60 * 60), '/', '', is_ssl(), true);
    
    echo "Cookies wurden gesetzt!<br>";
    echo "Cookie-Daten: " . json_encode($test_data) . "<br>";
} else {
    echo "<h3>2. Cookies bereits vorhanden:</h3>";
    $preferences = json_decode(stripslashes($_COOKIE['yprint_consent_preferences']), true);
    echo "<pre>";
    print_r($preferences);
    echo "</pre>";
}

// Test 3: YPrint Consent Manager Test
if (class_exists('YPrint_Consent_Manager')) {
    $consent_manager = YPrint_Consent_Manager::get_instance();
    
    echo "<h3>3. YPrint Consent Manager Test:</h3>";
    
    // Banner-Anzeige prüfen
    $should_show = $consent_manager->should_show_banner_initially();
    echo "Banner sollte angezeigt werden: " . ($should_show ? 'JA' : 'NEIN') . "<br>";
    
    // Reflection für private Methoden
    $reflection = new ReflectionClass($consent_manager);
    
    // Gast-Entscheidung prüfen
    $method = $reflection->getMethod('has_guest_consent_decision');
    $method->setAccessible(true);
    $has_decision = $method->invoke($consent_manager);
    echo "Hat Gast-Entscheidung: " . ($has_decision ? 'JA' : 'NEIN') . "<br>";
    
    // Gültige Gast-Consent prüfen
    $method = $reflection->getMethod('has_valid_guest_consent');
    $method->setAccessible(true);
    $has_valid = $method->invoke($consent_manager);
    echo "Hat gültige Gast-Consent: " . ($has_valid ? 'JA' : 'NEIN') . "<br>";
}

// Test 4: JavaScript-Cookie-Test
echo "<h3>4. JavaScript-Cookie-Test:</h3>";
echo "<script>
console.log('🍪 JavaScript Cookie Test:');
console.log('document.cookie:', document.cookie);

// Cookie-Status prüfen
const hasConsentPreferences = document.cookie.includes('yprint_consent_preferences');
const hasConsentTimestamp = document.cookie.includes('yprint_consent_timestamp');
const hasConsentDecision = document.cookie.includes('yprint_consent_decision');

console.log('Cookie-Status:');
console.log('- yprint_consent_preferences:', hasConsentPreferences);
console.log('- yprint_consent_timestamp:', hasConsentTimestamp);
console.log('- yprint_consent_decision:', hasConsentDecision);

// Cookie-Daten extrahieren
const consentMatch = document.cookie.match(/yprint_consent_preferences=([^;]+)/);
if (consentMatch) {
    try {
        const cookieData = JSON.parse(decodeURIComponent(consentMatch[1]));
        console.log('Cookie-Daten:', cookieData);
    } catch (e) {
        console.error('Fehler beim Parsen der Cookie-Daten:', e);
    }
}
</script>";

echo "<hr>";
echo "<p><strong>Test:</strong> Lade diese Seite neu und schaue, ob die Cookies erhalten bleiben!</p>";
echo "<p><a href='?test=1'>Seite neu laden</a></p>";
?>