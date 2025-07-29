<?php
/**
 * Einfacher Cookie-Flow Test
 */

// WordPress laden
require_once('../../../wp-load.php');

echo "<h2>🍪 Cookie Flow Test</h2>";

// Test 1: Aktuelle Cookies
echo "<h3>1. Aktuelle Cookies:</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Test 2: Cookie setzen (simuliert)
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

// Cookie setzen
setcookie(
    'yprint_consent_preferences',
    json_encode($test_data),
    time() + (365 * 24 * 60 * 60),
    '/',
    '',
    is_ssl(),
    true
);

setcookie(
    'yprint_consent_timestamp',
    time(),
    time() + (365 * 24 * 60 * 60),
    '/',
    '',
    is_ssl(),
    true
);

setcookie(
    'yprint_consent_decision',
    '1',
    time() + (365 * 24 * 60 * 60),
    '/',
    '',
    is_ssl(),
    true
);

echo "Cookies wurden gesetzt!<br>";
echo "Cookie-Daten: " . json_encode($test_data) . "<br>";

// Test 3: Nach dem Setzen prüfen
echo "<h3>3. Nach dem Setzen - Cookies prüfen:</h3>";
echo "<pre>";
print_r($_COOKIE);
echo "</pre>";

// Test 4: Banner-Anzeige prüfen
if (class_exists('YPrint_Consent_Manager')) {
    $consent_manager = YPrint_Consent_Manager::get_instance();
    
    echo "<h3>4. Banner-Anzeige Test:</h3>";
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

echo "<hr>";
echo "<p><strong>Hinweis:</strong> Dieses Script setzt Test-Cookies. Lösche es nach dem Test.</p>";
?>