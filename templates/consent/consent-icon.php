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
            
            // ✅ IMMER Banner anzeigen - egal ob bereits entschieden
            const banner = document.getElementById('yprint-cookie-banner');
            if (banner) {
                // Banner sofort anzeigen
                banner.style.display = 'flex';
                banner.classList.remove('yprint-hidden');
                document.body.classList.add('yprint-consent-open');
                
                // Aktuelle Einstellungen laden und UI aktualisieren
                if (typeof window.yprintConsentManager !== 'undefined') {
                    window.yprintConsentManager.loadConsentForSettings();
                } else {
                    console.warn('🍪 yprintConsentManager nicht verfügbar');
                }
            } else {
                console.error('🍪 Cookie-Banner nicht gefunden');
            }
        });
    }
});
</script>