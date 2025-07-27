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
$display_style = $show_initially ? 'display: block;' : 'display: none;';
?>
<div id="yprint-cookie-banner" class="yprint-cookie-banner" role="dialog" aria-modal="true" aria-labelledby="cookie-banner-title" aria-describedby="cookie-banner-description" style="<?php echo $display_style; ?>">
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
                    <div class="yprint-cookie-category-info">
                        <div class="yprint-cookie-category-label">
                            <span class="yprint-cookie-category-title">Essenzielle Cookies</span>
                        </div>
                        <p class="yprint-cookie-category-desc">Diese Cookies sind für die Grundfunktionen der Website erforderlich.</p>
                    </div>
                    <label class="yprint-toggle-switch">
                        <input type="checkbox" id="cookie-essential" checked disabled>
                        <span class="yprint-slider"></span>
                    </label>
                </div>
                
                <div class="yprint-cookie-category">
                    <div class="yprint-cookie-category-info">
                        <div class="yprint-cookie-category-label">
                            <span class="yprint-cookie-category-title">Analyse Cookies</span>
                        </div>
                        <p class="yprint-cookie-category-desc">Helfen uns zu verstehen, wie Besucher mit der Website interagieren.</p>
                    </div>
                    <label class="yprint-toggle-switch">
                        <input type="checkbox" id="cookie-analytics">
                        <span class="yprint-slider"></span>
                    </label>
                </div>
                
                <div class="yprint-cookie-category">
                    <div class="yprint-cookie-category-info">
                        <div class="yprint-cookie-category-label">
                            <span class="yprint-cookie-category-title">Marketing Cookies</span>
                        </div>
                        <p class="yprint-cookie-category-desc">Werden verwendet, um dir relevante Anzeigen zu zeigen.</p>
                    </div>
                    <label class="yprint-toggle-switch">
                        <input type="checkbox" id="cookie-marketing">
                        <span class="yprint-slider"></span>
                    </label>
                </div>
                
                <div class="yprint-cookie-category">
                    <div class="yprint-cookie-category-info">
                        <div class="yprint-cookie-category-label">
                            <span class="yprint-cookie-category-title">Funktionale Cookies</span>
                        </div>
                        <p class="yprint-cookie-category-desc">Ermöglichen erweiterte Funktionalitäten und Personalisierung.</p>
                    </div>
                    <label class="yprint-toggle-switch">
                        <input type="checkbox" id="cookie-functional">
                        <span class="yprint-slider"></span>
                    </label>
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
                <button type="button" id="yprint-reject-all" class="yprint-btn yprint-btn-secondary">
                    Nur essenzielle Cookies
                </button>
            </div>
            
            <div class="yprint-cookie-banner-links">
                <a href="/datenschutz" target="_blank">Datenschutzerklärung</a>
                <a href="/impressum" target="_blank">Impressum</a>
            </div>
        </div>
    </div>
</div>