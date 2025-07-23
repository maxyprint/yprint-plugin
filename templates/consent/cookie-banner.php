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
            
            <div class="yprint-cookie-categories">
                <div class="yprint-cookie-category">
                    <label class="yprint-cookie-category-label">
                        <input type="checkbox" id="cookie-essential" checked disabled>
                        <span class="yprint-cookie-category-title">Essenzielle Cookies</span>
                    </label>
                    <p class="yprint-cookie-category-desc">Diese Cookies sind für die Grundfunktionen der Website erforderlich.</p>
                </div>
                
                <div class="yprint-cookie-category">
                    <label class="yprint-cookie-category-label">
                        <input type="checkbox" id="cookie-analytics">
                        <span class="yprint-cookie-category-title">Analyse Cookies</span>
                    </label>
                    <p class="yprint-cookie-category-desc">Helfen uns zu verstehen, wie Besucher mit der Website interagieren.</p>
                </div>
                
                <div class="yprint-cookie-category">
                    <label class="yprint-cookie-category-label">
                        <input type="checkbox" id="cookie-marketing">
                        <span class="yprint-cookie-category-title">Marketing Cookies</span>
                    </label>
                    <p class="yprint-cookie-category-desc">Werden verwendet, um Besuchern relevante Anzeigen zu zeigen.</p>
                </div>
                
                <div class="yprint-cookie-category">
                    <label class="yprint-cookie-category-label">
                        <input type="checkbox" id="cookie-functional">
                        <span class="yprint-cookie-category-title">Funktionale Cookies</span>
                    </label>
                    <p class="yprint-cookie-category-desc">Ermöglichen erweiterte Funktionalitäten und Personalisierung.</p>
                </div>
            </div>
        </div>
        
        <div class="yprint-cookie-banner-footer">
            <div class="yprint-cookie-banner-actions">
                <button type="button" id="yprint-accept-essential" class="yprint-btn yprint-btn-secondary">
                    Nur Essenzielle
                </button>
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