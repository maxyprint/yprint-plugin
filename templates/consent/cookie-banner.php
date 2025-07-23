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
?>

<div id="yprint-cookie-banner" class="yprint-cookie-banner" style="display: none;">
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
        </div>
        
        <div class="yprint-cookie-banner-footer">
            <div class="yprint-cookie-banner-actions">
                <button type="button" id="yprint-accept-all" class="yprint-btn yprint-btn-equal">
                    Alle akzeptieren
                </button>
                <button type="button" id="yprint-reject-all" class="yprint-btn yprint-btn-equal">
                    Alle ablehnen
                </button>
                <button type="button" id="yprint-show-settings" class="yprint-btn yprint-btn-equal">
                    Einstellungen
                </button>
            </div>
            
            <!-- Erweiterte Einstellungen (initial versteckt) -->
            <div id="yprint-detailed-settings" class="yprint-detailed-settings" style="display: none;">
                <div class="yprint-cookie-categories">
                    <div class="yprint-cookie-category">
                        <label class="yprint-cookie-category-label">
                            <input type="checkbox" id="cookie-essential" checked disabled data-required="true">
                            <span class="yprint-cookie-category-title">Technisch notwendige Cookies</span>
                        </label>
                        <p class="yprint-cookie-category-desc">Diese Cookies sind für die Grundfunktionen der Website erforderlich und können nicht deaktiviert werden.</p>
                        <details class="cookie-details">
                            <summary>Weitere Informationen</summary>
                            <p>Verwendete Services: Session-Management, CSRF-Schutz</p>
                            <p>Zweck: Sicherheit, Grundfunktionalität</p>
                            <p>Speicherdauer: Session</p>
                        </details>
                    </div>
                    
                    <div class="yprint-cookie-category">
                        <label class="yprint-cookie-category-label">
                            <input type="checkbox" id="cookie-analytics" data-required="false">
                            <span class="yprint-cookie-category-title">Statistik-Cookies</span>
                        </label>
                        <p class="yprint-cookie-category-desc">Ermöglichen anonyme Analyse der Website-Nutzung zur Verbesserung der Benutzererfahrung.</p>
                        <details class="cookie-details">
                            <summary>Weitere Informationen</summary>
                            <p>Verwendete Services: Google Analytics, Hotjar</p>
                            <p>Zweck: Verbesserung der Website-Performance</p>
                            <p>Speicherdauer: 24 Monate</p>
                        </details>
                    </div>
                    
                    <div class="yprint-cookie-category">
                        <label class="yprint-cookie-category-label">
                            <input type="checkbox" id="cookie-marketing" data-required="false">
                            <span class="yprint-cookie-category-title">Marketing-/Tracking-Cookies</span>
                        </label>
                        <p class="yprint-cookie-category-desc">Ermöglichen personalisierte Werbung und Erfolgsmessung von Werbekampagnen.</p>
                        <details class="cookie-details">
                            <summary>Weitere Informationen</summary>
                            <p>Verwendete Services: Facebook Pixel, Google Ads</p>
                            <p>Zweck: Personalisierte Werbung, Retargeting</p>
                            <p>Speicherdauer: 12 Monate</p>
                        </details>
                    </div>
                    
                    <div class="yprint-cookie-category">
                        <label class="yprint-cookie-category-label">
                            <input type="checkbox" id="cookie-functional" data-required="false">
                            <span class="yprint-cookie-category-title">Personalisierungsdaten</span>
                        </label>
                        <p class="yprint-cookie-category-desc">Speichern Ihre Präferenzen für ein verbessertes Nutzungserlebnis.</p>
                        <details class="cookie-details">
                            <summary>Weitere Informationen</summary>
                            <p>Verwendete Services: YPrint User Preferences</p>
                            <p>Zweck: Gespeicherte Einstellungen, Sprachauswahl</p>
                            <p>Speicherdauer: 6 Monate</p>
                        </details>
                    </div>
                </div>
                <div class="yprint-detailed-actions">
                    <button type="button" id="yprint-save-preferences" class="yprint-btn yprint-btn-primary">
                        Auswahl speichern
                    </button>
                    <button type="button" id="yprint-back-to-simple" class="yprint-btn yprint-btn-secondary">
                        Zurück
                    </button>
                </div>
            </div>
            
            <div class="yprint-cookie-banner-links">
                <a href="/datenschutz" target="_blank">Datenschutzerklärung</a>
                <a href="/impressum" target="_blank">Impressum</a>
            </div>
        </div>
    </div>
</div>