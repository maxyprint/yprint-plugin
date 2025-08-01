<?php
/**
 * Consent Icon Template (permanent)
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}
?>

<div id="yprint-consent-icon" class="yprint-consent-icon">
    <button type="button" id="yprint-open-consent-settings" class="yprint-consent-icon-btn" title="Cookie-Einstellungen">
        🍪
    </button>
</div>

<!-- ✅ KRITISCHER FIX: Cookie-Button soll IMMER funktionieren -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const cookieButton = document.getElementById('yprint-open-consent-settings');
    if (cookieButton) {
        cookieButton.addEventListener('click', function(e) {
            e.preventDefault();
            console.log('🍪 Cookie-Button geklickt - FORCIERE Banner-Anzeige');
            
            // ✅ Warte auf yprintConsentManager oder zeige Banner direkt
            function showBannerForcibly() {
                const banner = document.getElementById('yprint-cookie-banner');
                if (banner) {
                    // Banner sofort anzeigen
                    banner.style.display = 'flex';
                    banner.classList.remove('yprint-hidden');
                    document.body.classList.add('yprint-consent-open');
                    
                    // Versuche Einstellungen zu laden mit Fallback
                    if (typeof window.yprintConsentManager !== 'undefined') {
                        window.yprintConsentManager.loadConsentForSettings();
                        console.log('🍪 Settings über yprintConsentManager geladen');
                    } else {
                        // ✅ VERBESSERTER FALLBACK: UI aus Browser-Cookies setzen
                        setTimeout(() => {
                            setUIFromBrowserCookies();
                        }, 100);
                        console.log('🍪 Fallback: UI aus Browser-Cookies gesetzt');
                    }
                } else {
                    console.error('🍪 Cookie-Banner nicht gefunden');
                }
            }
            
            // ✅ Warte kurz auf Initialisierung, dann zeige Banner
            if (typeof window.yprintConsentManager === 'undefined') {
                // Versuche 3 Sekunden zu warten
                let attempts = 0;
                const checkInterval = setInterval(() => {
                    attempts++;
                    if (typeof window.yprintConsentManager !== 'undefined' || attempts >= 30) {
                        clearInterval(checkInterval);
                        showBannerForcibly();
                    }
                }, 100);
            } else {
                showBannerForcibly();
            }
        });
    }
    
    // ✅ VERBESSERTE FALLBACK-FUNKTION: UI aus Browser-Cookies setzen
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
                } else {
                    console.log('🍪 Keine gültigen Cookie-Daten gefunden - verwende Defaults');
                }
            } else {
                console.log('🍪 Keine Cookie-Präferenzen gefunden - verwende Defaults');
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