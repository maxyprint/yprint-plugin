<?php
/**
 * Cookie Banner Template
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

$consent_manager = YPrint_Consent_Manager::get_instance();
$show_initially = $consent_manager->should_show_banner_initially();
// ✅ ANTI-FOUC: Banner ist standardmäßig versteckt, JavaScript zeigt es bei Bedarf
$css_class = $show_initially ? 'yprint-cookie-banner' : 'yprint-cookie-banner yprint-hidden';
?>
<div id="yprint-cookie-banner" class="<?php echo $css_class; ?>" role="dialog" aria-modal="true" aria-labelledby="cookie-banner-title" aria-describedby="cookie-banner-description" style="display: none;">
    <div class="yprint-cookie-banner-overlay"></div>
    <div class="yprint-cookie-banner-content">
        <div class="yprint-cookie-banner-header">
            <h3 id="cookie-banner-title">Diese Website verwendet Cookies</h3>
            <button type="button" class="yprint-cookie-banner-close" aria-label="Schließen">×</button>
        </div>
        
        <div class="yprint-cookie-banner-body">
            <p id="cookie-banner-description">
                Wir verwenden Cookies, um dir die bestmögliche Erfahrung auf unserer Website zu bieten. 
                Einige sind essenziell, andere helfen uns dabei, diese Website und deine Erfahrung zu verbessern.
            </p>
            
            <div class="yprint-cookie-categories">
                <div class="yprint-cookie-category" data-cookie-type="essential">
                    <div class="yprint-cookie-category-info">
                        <div class="yprint-cookie-category-label">
                            <span class="yprint-cookie-category-title">Essenzielle Cookies</span>
                        </div>
                        <p class="yprint-cookie-category-desc">Diese Cookies sind für die Grundfunktionen der Website erforderlich.</p>
                    </div>
                    <input type="checkbox" id="cookie-essential" checked disabled style="display: none;">
                </div>
                
                <div class="yprint-cookie-category" data-cookie-type="analytics">
                    <div class="yprint-cookie-category-info">
                        <div class="yprint-cookie-category-label">
                            <span class="yprint-cookie-category-title">Analyse Cookies</span>
                        </div>
                        <p class="yprint-cookie-category-desc">Helfen uns zu verstehen, wie Besucher mit der Website interagieren.</p>
                    </div>
                    <input type="checkbox" id="cookie-analytics" style="display: none;">
                </div>
                
                <div class="yprint-cookie-category" data-cookie-type="marketing">
                    <div class="yprint-cookie-category-info">
                        <div class="yprint-cookie-category-label">
                            <span class="yprint-cookie-category-title">Marketing Cookies</span>
                        </div>
                        <p class="yprint-cookie-category-desc">Werden verwendet, um dir relevante Anzeigen zu zeigen.</p>
                    </div>
                    <input type="checkbox" id="cookie-marketing" style="display: none;">
                </div>
                
                <div class="yprint-cookie-category" data-cookie-type="functional">
                    <div class="yprint-cookie-category-info">
                        <div class="yprint-cookie-category-label">
                            <span class="yprint-cookie-category-title">Funktionale Cookies</span>
                        </div>
                        <p class="yprint-cookie-category-desc">Ermöglichen erweiterte Funktionalitäten und Personalisierung.</p>
                    </div>
                    <input type="checkbox" id="cookie-functional" style="display: none;">
                </div>
            </div>
        </div>
        
        <div class="yprint-cookie-banner-footer">
            <div class="yprint-cookie-banner-actions">
                <button type="button" id="yprint-accept-all" class="yprint-btn yprint-btn-primary">
                    Alle akzeptieren
                </button>
                <button type="button" id="yprint-save-preferences" class="yprint-btn yprint-btn-primary">
                    Auswahl speichern
                </button>
            </div>
            
            <div class="yprint-cookie-banner-links">
                <a href="/datenschutz" target="_blank">Datenschutzerklärung</a>
                <a href="/impressum" target="_blank">Impressum</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ✅ KRITISCHER FIX: Cookie-Button Event Handler
    const cookieButton = document.getElementById('yprint-open-consent-settings');
    if (cookieButton) {
        cookieButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🍪 Cookie-Button geklickt - FORCIERE Banner-Anzeige');
            
            // ✅ IMMER Banner anzeigen - egal ob bereits entschieden
            const banner = document.getElementById('yprint-cookie-banner');
            if (banner) {
                // Alle versteckenden Klassen und Styles entfernen
                banner.classList.remove('yprint-hidden');
                banner.style.display = 'flex';
                document.body.classList.add('yprint-consent-open');
                
                // ✅ Aktuelle Einstellungen in UI laden
                if (typeof window.yprintConsentManager !== 'undefined') {
                    // Lade aktuelle Cookie-Einstellungen und setze UI-State
                    window.yprintConsentManager.loadConsentForSettings();
                    console.log('🍪 Aktuelle Einstellungen in UI geladen');
                } else {
                    console.warn('🍪 yprintConsentManager nicht verfügbar - verwende Fallback');
                    // ✅ FALLBACK: Setze UI basierend auf Browser-Cookies
                    setUIFromBrowserCookies();
                }
            } else {
                console.error('🍪 Cookie-Banner Element nicht gefunden!');
            }
        });
        console.log('🍪 Cookie-Button Event Handler registriert');
    } else {
        console.warn('🍪 Cookie-Button nicht gefunden - suche alternative Selektoren');
        // ✅ FALLBACK: Suche alternative Cookie-Button-Selektoren
        const altButtons = document.querySelectorAll('[id*="consent"], [class*="cookie"], [class*="consent"]');
        console.log('🍪 Alternative Buttons gefunden:', altButtons.length);
    }
    
    // ✅ FALLBACK-FUNKTION: UI aus Browser-Cookies setzen
    function setUIFromBrowserCookies() {
        try {
            const cookieValue = getCookieValue('yprint_consent_preferences');
            if (cookieValue) {
                const cookieData = JSON.parse(decodeURIComponent(cookieValue));
                if (cookieData && cookieData.consents) {
                    // Setze Checkboxes basierend auf Cookie-Daten
                    const essentialBox = document.getElementById('cookie-essential');
                    const analyticsBox = document.getElementById('cookie-analytics');
                    const marketingBox = document.getElementById('cookie-marketing');
                    const functionalBox = document.getElementById('cookie-functional');
                    
                    if (essentialBox) essentialBox.checked = true; // Immer true
                    if (analyticsBox) analyticsBox.checked = cookieData.consents.cookie_analytics?.granted || false;
                    if (marketingBox) marketingBox.checked = cookieData.consents.cookie_marketing?.granted || false;
                    if (functionalBox) functionalBox.checked = cookieData.consents.cookie_functional?.granted || false;
                    
                    console.log('🍪 UI-State aus Browser-Cookies gesetzt');
                }
            }
        } catch (e) {
            console.warn('🍪 Fehler beim Laden der Cookie-UI:', e);
        }
    }
    
    // ✅ HILFSFUNKTION: Cookie-Wert abrufen
    function getCookieValue(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }
});
</script>