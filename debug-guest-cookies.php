<?php
/**
 * Debug-Script für Gast-Cookie-Problem
 */

// WordPress laden
require_once('../../../wp-load.php');

echo "<h2>🍪 Gast-Cookie Debug</h2>";

// Test 1: Aktuelle Cookies anzeigen
echo "<h3>1. Aktuelle Cookies:</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Test 2: Cookie-Pfad und Domain prüfen
echo "<h3>2. Cookie-Einstellungen:</h3>";
echo "Domain: " . $_SERVER['HTTP_HOST'] . "<br>";
echo "Pfad: " . $_SERVER['REQUEST_URI'] . "<br>";
echo "SSL: " . (is_ssl() ? 'JA' : 'NEIN') . "<br>";

// Test 3: Cookie manuell setzen
echo "<h3>3. Cookie manuell setzen:</h3>";

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

// Cookie mit korrekten Parametern setzen
$cookie_result = setcookie(
    'yprint_consent_preferences',
    json_encode($test_data),
    time() + (365 * 24 * 60 * 60), // 1 Jahr
    '/', // Pfad
    '', // Domain (leer = aktuelle Domain)
    is_ssl(), // Secure
    true // HttpOnly
);

echo "Cookie setzen erfolgreich: " . ($cookie_result ? 'JA' : 'NEIN') . "<br>";
echo "Cookie-Daten: " . json_encode($test_data) . "<br>";

// Test 4: YPrint Consent Manager testen
if (class_exists('YPrint_Consent_Manager')) {
    $consent_manager = YPrint_Consent_Manager::get_instance();
    
    echo "<h3>4. YPrint Consent Manager Test:</h3>";
    
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
    
    // Cookie-Daten analysieren
    if (isset($_COOKIE['yprint_consent_preferences'])) {
        $preferences = json_decode(stripslashes($_COOKIE['yprint_consent_preferences']), true);
        echo "<h4>Cookie-Daten Analyse:</h4>";
        echo "<pre>";
        print_r($preferences);
        echo "</pre>";
        
        // Prüfe essenzielle Cookies
        if (isset($preferences['consents']['cookie_essential'])) {
            echo "Essenzielle Cookies: " . ($preferences['consents']['cookie_essential'] ? 'AKZEPTIERT' : 'ABGELEHNT') . "<br>";
        } else {
            echo "Essenzielle Cookies: NICHT GEFUNDEN<br>";
        }
    }
}

// Test 5: JavaScript-Cookie-Test
echo "<h3>5. JavaScript-Cookie-Test:</h3>";
echo "<script>
console.log('🍪 JavaScript Cookie Test:');
console.log('document.cookie:', document.cookie);

// Cookie manuell setzen
document.cookie = 'yprint_consent_preferences=' + encodeURIComponent('" . json_encode($test_data) . "') + '; path=/; max-age=31536000; SameSite=Lax';
console.log('Cookie gesetzt');

// Nach 1 Sekunde prüfen
setTimeout(() => {
    console.log('Nach 1 Sekunde - document.cookie:', document.cookie);
}, 1000);
</script>";

echo "<hr>";
echo "<p><strong>Hinweis:</strong> Dieses Script ist nur für Debugging-Zwecke.</p>";
?>