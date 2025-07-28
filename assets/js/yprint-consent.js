/**
 * YPrint Consent Manager JavaScript
 * 
 * @package YPrint
 * @since 1.3.0
 */

(function($) {
    'use strict';
    
    class YPrintConsentManager {
        constructor() {
            this.config = window.yprintConsent || {};
            this.banner = null;
            this.icon = null;
            
            this.init();
        }
        
        init() {
            console.log('🍪 YPrint Consent Manager: Initialisierung gestartet');
            
            // DOM Ready warten
            $(document).ready(() => {
                this.setupElements();
                this.bindEvents();
                this.loadTexts();
                
                // Nur bestehende Einstellungen laden wenn Banner nicht initial gezeigt wird
                this.initializeConsentStatus();
                
                // Debug: Forciere Icon-Anzeige für Tests
                setTimeout(() => {
                    if ($('#yprint-consent-icon').length === 0) {
                        console.log('🚨 Cookie Icon nicht gefunden - füge es hinzu');
                        this.forceCreateIcon();
                    } else {
                        console.log('✅ Cookie Icon gefunden');
                        $('#yprint-consent-icon').show();
                    }
                }, 1000);
            });
        }
        
        setupElements() {
            this.banner = $('#yprint-cookie-banner');
            this.icon = $('#yprint-consent-icon');
            
            console.log('🍪 Banner gefunden:', this.banner.length > 0);
            console.log('🍪 Icon gefunden:', this.icon.length > 0);
            
            // Essenzielle Cookies immer als ausgewählt markieren
            this.initializeEssentialCookies();
        }
        
        initializeEssentialCookies() {
            // Essenzielle Cookies immer ausgewählt
            const essentialCategory = $('.yprint-cookie-category[data-cookie-type="essential"]');
            const essentialCheckbox = $('#cookie-essential');
            
            if (essentialCategory.length && essentialCheckbox.length) {
                essentialCheckbox.prop('checked', true);
                essentialCategory.addClass('selected');
                console.log('🍪 Essenzielle Cookies initialisiert');
            }
        }
        
        initializeConsentStatus() {
            // Prüfen ob Banner initial angezeigt wird (noch keine Entscheidung getroffen)
            const bannerVisible = this.banner.is(':visible');
            const bannerDisplayFlex = this.banner.css('display') === 'flex';
            
            console.log('🍪 Banner sichtbar:', bannerVisible, 'Display:', this.banner.css('display'));
            
            if (bannerVisible || bannerDisplayFlex) {
                // Banner wird initial angezeigt - keine bestehenden Einstellungen laden
                console.log('🍪 Initial-Banner erkannt - keine Vorauswahl der Cookie-Kategorien');
                
                // Nur essenzielle Cookies vorausgewählt lassen (bereits in setupElements gemacht)
                return;
            } else {
                // Banner ist ausgeblendet - bestehende Einstellungen für Icon-Funktionalität laden
                console.log('🍪 Banner ausgeblendet - lade bestehende Einstellungen für Icon');
                this.checkConsentStatus();
            }
        }
        
        bindEvents() {
            // Banner schließen
            $(document).on('click', '.yprint-cookie-banner-close, .yprint-cookie-banner-overlay', (e) => {
                console.log('🍪 Schließen-Button geklickt');
                e.preventDefault();
                e.stopPropagation();
                this.hideBanner();
            });
            
            // Cookie-Kategorien klickbar machen
            $(document).on('click', '.yprint-cookie-category', (e) => {
                const category = $(e.currentTarget);
                const checkbox = category.find('input[type="checkbox"]');
                const cookieType = category.data('cookie-type');
                
                console.log('🍪 Cookie-Kategorie geklickt:', cookieType);
                
                // Essenzielle Cookies können nicht deaktiviert werden
                if (cookieType === 'essential') {
                    console.log('🍪 Essenzielle Cookies können nicht deaktiviert werden');
                    return;
                }
                
                // Toggle checkbox state
                checkbox.prop('checked', !checkbox.prop('checked'));
                
                // Toggle visual state
                if (checkbox.prop('checked')) {
                    category.addClass('selected');
                    console.log('🍪 Kategorie ausgewählt:', cookieType);
                } else {
                    category.removeClass('selected');
                    console.log('🍪 Kategorie abgewählt:', cookieType);
                }
            });
            
            // Alle akzeptieren
            $(document).on('click', '#yprint-accept-all', (e) => {
                console.log('🍪 Alle akzeptieren geklickt');
                e.preventDefault();
                this.acceptAll();
            });
            
            // Auswahl speichern
            $(document).on('click', '#yprint-save-preferences', (e) => {
                console.log('🍪 Auswahl speichern geklickt');
                e.preventDefault();
                this.savePreferences();
            });
            
            // Einstellungen anzeigen
            $(document).on('click', '#yprint-show-settings', (e) => {
                console.log('🍪 Einstellungen anzeigen geklickt');
                e.preventDefault();
                this.showDetailedSettings();
            });
            
            // Zurück zu einfacher Ansicht
            $(document).on('click', '#yprint-back-to-simple', (e) => {
                console.log('🍪 Zurück zu einfacher Ansicht geklickt');
                e.preventDefault();
                this.hideDetailedSettings();
            });
            
            // Consent Icon
            $(document).on('click', '#yprint-open-consent-settings', (e) => {
                console.log('🍪 Consent Icon geklickt - zeige Settings');
                e.preventDefault();
                this.showBannerForSettings();
            });
            
            // ESC-Taste zum Schließen
            $(document).on('keydown', (e) => {
                if (e.key === 'Escape' && this.banner.is(':visible')) {
                    console.log('🍪 ESC-Taste gedrückt - Banner schließen');
                    this.hideBanner();
                }
            });
            
            console.log('🍪 Event-Handler registriert');
        }
        
        loadTexts() {
            if (this.config.texts) {
                // Dynamische Texte einsetzen
                $('#cookie-banner-title').text(this.config.texts.COOKIE_BANNER_TITLE || 'Diese Website verwendet Cookies');
                $('#cookie-banner-description').text(this.config.texts.COOKIE_BANNER_DESCRIPTION || 'Wir verwenden Cookies...');
                
                console.log('🍪 Texte geladen:', Object.keys(this.config.texts).length);
            }
        }
        
        checkConsentStatus() {
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_get_consent_status',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.handleConsentStatus(response.data);
                    }
                },
                error: () => {
                    console.log('🍪 Fehler beim Laden des Consent-Status');
                    this.showBanner(); // Fallback: Banner anzeigen
                }
            });
        }
        
        handleConsentStatus(consents) {
            console.log('🍪 Aktueller Consent-Status:', consents);
            
            // Wenn keine Einwilligungen vorhanden, Banner zeigen
            if (Object.keys(consents).length === 0) {
                this.showBanner();
                return;
            }
            
            // Cookies entsprechend setzen/blockieren (für Icon-Funktionalität)
            this.applyCookieSettings(consents);
            
            // Checkboxen NUR bei expliziter Settings-Anzeige setzen
            console.log('🍪 Consent-Status geladen, aber Checkboxen nicht automatisch gesetzt');
        }
        
        loadConsentForSettings() {
            console.log('🍪 Lade Einstellungen für Settings-Banner');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_get_consent_status',
                    nonce: this.config.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.applyConsentToForm(response.data);
                    }
                },
                error: () => {
                    console.log('🍪 Fehler beim Laden der Settings');
                }
            });
        }

        applyConsentToForm(consents) {
            console.log('🍪 Wende Consent auf Formular an:', consents);
            
            // Checkboxen mit aktuellen Werten setzen und visuelle States aktualisieren
            Object.keys(consents).forEach(type => {
                const checkbox = $(`#cookie-${type.toLowerCase().replace('cookie_', '')}`);
                const category = checkbox.closest('.yprint-cookie-category');
                
                if (checkbox.length && category.length) {
                    checkbox.prop('checked', consents[type].granted);
                    
                    // Visuellen State setzen
                    if (consents[type].granted) {
                        category.addClass('selected');
                        console.log(`🍪 ${type} als ausgewählt markiert (Settings-Modus)`);
                    } else {
                        category.removeClass('selected');
                        console.log(`🍪 ${type} als nicht ausgewählt markiert (Settings-Modus)`);
                    }
                }
            });
        }
        
        showBanner() {
            console.log('🍪 Banner wird angezeigt');
            console.log('🍪 Banner vor Show - Hidden-Klasse:', this.banner.hasClass('yprint-hidden'));
            
            this.banner.removeClass('yprint-hidden').css('display', 'flex');
            $('body').addClass('yprint-consent-open');
            
            console.log('🍪 Banner nach Show - sichtbar:', this.banner.is(':visible'));
        }
        
        hideBanner() {
            console.log('🍪 Banner wird ausgeblendet');
            
            // Einfache Lösung: Klasse hinzufügen
            this.banner.addClass('yprint-hidden');
            
            $('body').removeClass('yprint-consent-open');
            
            console.log('🍪 Banner ausgeblendet - Display:', this.banner.css('display'));
            console.log('🍪 Banner sichtbar:', this.banner.is(':visible'));
        }
        
        acceptAll() {
            console.log('🍪 Alle Cookies akzeptiert');
            
            const consents = {
                'cookie_essential': true,
                'cookie_analytics': true,
                'cookie_marketing': true,
                'cookie_functional': true
            };
            
            // Visuelle States setzen - alle Kategorien als ausgewählt markieren
            $('.yprint-cookie-category').addClass('selected');
            $('.yprint-cookie-category input[type="checkbox"]').prop('checked', true);
            
            console.log('🍪 Alle Cookie-Kategorien als ausgewählt markiert');
            
            this.saveConsents(consents);
        }
        
        showDetailedSettings() {
            $('#yprint-detailed-settings').slideDown(300);
            $('.yprint-cookie-banner-actions').hide();
        }
        
        hideDetailedSettings() {
            $('#yprint-detailed-settings').slideUp(300);
            $('.yprint-cookie-banner-actions').show();
        }
        
        savePreferences() {
            console.log('🍪 Benutzerdefinierte Auswahl wird gespeichert');
            
            // Sammle alle aktuellen Checkbox-States
            const consents = {
                'cookie_essential': true, // Immer true
                'cookie_analytics': $('#cookie-analytics').is(':checked'),
                'cookie_marketing': $('#cookie-marketing').is(':checked'),
                'cookie_functional': $('#cookie-functional').is(':checked')
            };
            
            console.log('🍪 Gesammelte Consent-States:', consents);
            console.log('🍪 Rufe saveConsents auf...');
            
            // Speichere die Auswahl
            this.saveConsents(consents);
        }
        
        // Banner für Registrierung öffnen (verwendet bestehende showBanner Methode)
        showBannerForRegistration() {
            console.log('🍪 Öffne Cookie-Einstellungen für Registrierung');
            this.showBanner();
            
            // Event für Registrierungs-Callback
            this.registrationCallback = true;
        }
        
        showBannerForSettings() {
            console.log('🍪 Banner für Settings wird angezeigt');
            
            // Banner anzeigen
            this.banner.css('display', 'flex');
            this.banner.removeClass('yprint-hidden');
            $('body').addClass('yprint-consent-open');
            
            // Bestehende Einstellungen laden
            this.loadConsentForSettings();
        }

        
        saveConsents(consents) {
            const self = this;
            
            console.log('🍪 saveConsents aufgerufen mit:', consents);
            console.log('🍪 AJAX URL:', this.config.ajaxUrl);
            console.log('🍪 Nonce:', this.config.nonce);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_save_consent',
                    nonce: this.config.nonce,
                    consents: consents,
                    version: '1.0'
                },
                beforeSend: function() {
                    console.log('🍪 AJAX-Anfrage wird gesendet...');
                },
                success: (response) => {
                    console.log('🍪 AJAX-Response erhalten:', response);
                    if (response.success) {
                        console.log('🍪 Consent erfolgreich gespeichert');
                        
                        // Cookies entsprechend setzen
                        self.applyCookieSettings(consents);
                        
                        // Banner schließen
                        self.hideBanner();
                        
                        // Wenn aus Registrierung aufgerufen: Event triggern
                        if (self.registrationCallback) {
                            self.registrationCallback = false;
                            
                            // Custom Event für Registrierung
                            const event = new CustomEvent('yprintCookieUpdated', {
                                detail: { consents: consents }
                            });
                            document.dispatchEvent(event);
                        } else {
                            // Normale Notification für nicht-Registrierung
                            self.showNotification('Deine Cookie-Einstellungen wurden gespeichert.', 'success');
                        }
                    } else {
                        console.error('🍪 Fehler beim Speichern:', response.data?.message);
                        self.showNotification('Fehler beim Speichern der Einstellungen.', 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🍪 Netzwerkfehler beim Speichern:', error);
                    console.error('🍪 Status:', status);
                    console.error('🍪 XHR:', xhr);
                    self.showNotification('Netzwerkfehler beim Speichern.', 'error');
                }
            });
        }
        
        applyCookieSettings(consents) {
            console.log('🍪 Wende Cookie-Einstellungen an:', consents);
            
            // Google Analytics
            if (consents.cookie_analytics === true) {
                this.loadGoogleAnalytics();
            } else {
                this.blockGoogleAnalytics();
            }
            
            // Marketing Cookies
            if (consents.cookie_marketing === true) {
                this.loadMarketingScripts();
            } else {
                this.blockMarketingScripts();
            }
            
            // Funktionale Cookies
            if (consents.cookie_functional === true) {
                this.loadFunctionalScripts();
            } else {
                this.blockFunctionalScripts();
            }
        }
        
        loadGoogleAnalytics() {
            // Beispiel: Google Analytics laden
            console.log('🍪 Google Analytics wird geladen');
            // Hier würdest du GA4 Code einfügen
        }
        
        blockGoogleAnalytics() {
            console.log('🍪 Google Analytics wird blockiert');
            // GA blockieren/deaktivieren
        }
        
        loadMarketingScripts() {
            console.log('🍪 Marketing-Scripts werden geladen');
            // Facebook Pixel, etc.
        }
        
        blockMarketingScripts() {
            console.log('🍪 Marketing-Scripts werden blockiert');
        }
        
        loadFunctionalScripts() {
            console.log('🍪 Funktionale Scripts werden geladen');
            // Chat-Widgets, etc.
        }
        
        blockFunctionalScripts() {
            console.log('🍪 Funktionale Scripts werden blockiert');
        }
        
        showNotification(message, type = 'info') {
            // Einfache Notification (kann später durch dein bestehendes System ersetzt werden)
            const notification = $(`
                <div class="yprint-notification yprint-notification-${type}" style="
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#007bff'};
                    color: white;
                    padding: 15px 20px;
                    border-radius: 6px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 100000;
                    font-family: 'Roboto', Arial, sans-serif;
                    font-size: 14px;
                    max-width: 300px;
                    word-wrap: break-word;
                ">
                    ${message}
                </div>
            `);
            
            $('body').append(notification);
            
            // Automatisch ausblenden nach 3 Sekunden
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 3000);
            
            console.log(`🍪 Notification angezeigt: ${message} (${type})`);
        }

        forceCreateIcon() {
            const iconHtml = `
                <div id="yprint-consent-icon" class="yprint-consent-icon">
                    <button type="button" id="yprint-open-consent-settings" class="yprint-consent-icon-btn" title="Cookie-Einstellungen">
                        🍪
                    </button>
                </div>
            `;
            $('body').append(iconHtml);
            console.log('🍪 Cookie Icon manuell hinzugefügt');
        }
        
        debugCookieManager() {
            console.log('🧪 Cookie Manager Debug:');
            console.log('- Banner gefunden:', this.banner.length > 0);
            console.log('- Icon gefunden:', this.icon.length > 0);
            console.log('- Banner sichtbar:', this.banner.is(':visible'));
            console.log('- Banner Display:', this.banner.css('display'));
            console.log('- Banner Style:', this.banner.attr('style'));
            console.log('- Banner Classes:', this.banner.attr('class'));
            console.log('- Config geladen:', !!this.config);
            console.log('- AJAX URL:', this.config.ajaxUrl);
            
            // Direkte Banner-Tests
            console.log('🧪 Direkte Banner-Tests:');
            if (this.banner.length > 0) {
                console.log('- Banner Element:', this.banner[0]);
                console.log('- Banner computedStyle:', window.getComputedStyle(this.banner[0]).display);
                console.log('- Banner visibility:', window.getComputedStyle(this.banner[0]).visibility);
                console.log('- Banner z-index:', window.getComputedStyle(this.banner[0]).zIndex);
            }
            
            // Teste Event-Handler
            console.log('🧪 Teste Event-Handler...');
            $('#yprint-accept-all').trigger('click');
        }
        
        // Direkte Test-Funktion für Banner-Ausblendung
        forceHideBanner() {
            console.log('🧪 Force Hide Banner Test');
            
            // Methode 1: Direkte DOM-Manipulation
            const bannerElement = document.getElementById('yprint-cookie-banner');
            if (bannerElement) {
                bannerElement.style.display = 'none';
                bannerElement.style.visibility = 'hidden';
                bannerElement.style.opacity = '0';
                bannerElement.style.pointerEvents = 'none';
                console.log('🧪 Banner direkt ausgeblendet');
            }
            
            // Methode 2: jQuery
            if (this.banner && this.banner.length > 0) {
                this.banner.css({
                    'display': 'none !important',
                    'visibility': 'hidden !important',
                    'opacity': '0 !important',
                    'pointer-events': 'none !important'
                });
                console.log('🧪 Banner über jQuery ausgeblendet');
            }
            
            // Methode 3: Banner entfernen
            if (bannerElement) {
                bannerElement.remove();
                console.log('🧪 Banner komplett entfernt');
            }
            
            $('body').removeClass('yprint-consent-open');
        }
    }
    
    // Initialisierung
    $(document).ready(() => {
        window.yprintConsentManager = new YPrintConsentManager();
        
        // Teste Cookie-Manager beim Laden
        setTimeout(() => {
            console.log('🧪 Testing Cookie Manager...');
            if (window.yprintConsentManager) {
                window.yprintConsentManager.debugCookieManager();
            }
            
            // Test Button (temporär für Debugging)
            const testButton = document.createElement('button');
            testButton.textContent = 'Test Cookie Manager';
            testButton.style.position = 'fixed';
            testButton.style.top = '10px';
            testButton.style.right = '10px';
            testButton.style.zIndex = '9999';
            testButton.style.background = '#007cba';
            testButton.style.color = 'white';
            testButton.style.border = 'none';
            testButton.style.padding = '8px 12px';
            testButton.style.borderRadius = '4px';
            testButton.style.cursor = 'pointer';
            testButton.onclick = () => {
                if (window.yprintConsentManager) {
                    window.yprintConsentManager.debugCookieManager();
                    window.yprintConsentManager.showBannerForRegistration();
                }
            };
            
            // Nur in Development/Test hinzufügen
            if (window.location.hostname === 'localhost' || window.location.hostname.includes('dev')) {
                document.body.appendChild(testButton);
            }
        }, 2000);
    });
    
    // Event-Listener für Cookie-Updates aus Registrierung
    document.addEventListener('yprintCookieUpdated', function(e) {
        console.log('🍪 Cookie-Einstellungen für Registrierung aktualisiert:', e.detail.consents);
        
        // Registrierungsformular aktualisieren falls vorhanden
        if (typeof loadCurrentCookieSettings === 'function') {
            setTimeout(() => {
                loadCurrentCookieSettings();
            }, 100);
        }
    });
    
    // Event-Listener für geladene Cookie-Einstellungen
    document.addEventListener('yprintCookieSettingsLoaded', function(e) {
        console.log('🍪 Cookie-Einstellungen geladen:', e.detail.cookiePrefs);
        
        // Registrierungsformular aktualisieren falls vorhanden
        if (typeof updateCookieStatusText === 'function') {
            updateCookieStatusText(e.detail.cookiePrefs);
        }
    });
    
})(jQuery);