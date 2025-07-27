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
                this.checkConsentStatus();
                
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
        }
        
        bindEvents() {
            // Banner schließen
            $(document).on('click', '.yprint-cookie-banner-close, .yprint-cookie-banner-overlay', () => {
                this.hideBanner();
            });
            
            // Cookie-Kategorien klickbar machen
            $(document).on('click', '.yprint-cookie-category', (e) => {
                const category = $(e.currentTarget);
                const checkbox = category.find('input[type="checkbox"]');
                const cookieType = category.data('cookie-type');
                
                // Essenzielle Cookies können nicht deaktiviert werden
                if (cookieType === 'essential') {
                    return;
                }
                
                // Toggle checkbox state
                checkbox.prop('checked', !checkbox.prop('checked'));
                
                // Toggle visual state
                if (checkbox.prop('checked')) {
                    category.addClass('selected');
                } else {
                    category.removeClass('selected');
                }
            });
            
            // Alle akzeptieren
            $(document).on('click', '#yprint-accept-all', () => {
                this.acceptAll();
            });
            
            // Alle ablehnen
            $(document).on('click', '#yprint-reject-all', () => {
                this.rejectAll();
            });
            
            // Einstellungen anzeigen
            $(document).on('click', '#yprint-show-settings', () => {
                this.showDetailedSettings();
            });
            
            // Zurück zu einfacher Ansicht
            $(document).on('click', '#yprint-back-to-simple', () => {
                this.hideDetailedSettings();
            });
            
            // Auswahl speichern
            $(document).on('click', '#yprint-save-preferences', () => {
                this.savePreferences();
            });
            
            // Consent Icon
            $(document).on('click', '#yprint-open-consent-settings', () => {
                this.showBanner();
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
            
            // Checkboxen mit aktuellen Werten setzen und visuelle States aktualisieren
            Object.keys(consents).forEach(type => {
                const checkbox = $(`#cookie-${type.toLowerCase().replace('cookie_', '')}`);
                const category = checkbox.closest('.yprint-cookie-category');
                
                if (checkbox.length) {
                    checkbox.prop('checked', consents[type].granted);
                    
                    // Visuellen State setzen
                    if (consents[type].granted) {
                        category.addClass('selected');
                    } else {
                        category.removeClass('selected');
                    }
                }
            });
            
            // Cookies entsprechend setzen/blockieren
            this.applyCookieSettings(consents);
        }
        
        showBanner() {
            console.log('🍪 Banner wird angezeigt');
            this.banner.fadeIn(300);
            $('body').addClass('yprint-consent-open');
        }
        
        hideBanner() {
            console.log('🍪 Banner wird ausgeblendet');
            this.banner.fadeOut(300);
            $('body').removeClass('yprint-consent-open');
        }
        
        acceptAll() {
            console.log('🍪 Alle Cookies akzeptiert');
            
            const consents = {
                'cookie_essential': true,
                'cookie_analytics': true,
                'cookie_marketing': true,
                'cookie_functional': true
            };
            
            // Visuelle States setzen
            $('.yprint-cookie-category').addClass('selected');
            $('.yprint-cookie-category input[type="checkbox"]').prop('checked', true);
            
            this.saveConsents(consents);
        }
        
        rejectAll() {
            console.log('🍪 Alle nicht-notwendigen Cookies abgelehnt');
            
            const consents = {
                'cookie_essential': true,    // Technisch notwendig, immer true
                'cookie_analytics': false,
                'cookie_marketing': false,
                'cookie_functional': false
            };
            
            // Visuelle States setzen
            $('.yprint-cookie-category').removeClass('selected');
            $('.yprint-cookie-category input[type="checkbox"]').prop('checked', false);
            
            // Essenzielle Cookies immer ausgewählt
            $('#cookie-essential').prop('checked', true);
            $('.yprint-cookie-category[data-cookie-type="essential"]').addClass('selected');
            
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
            
            const consents = {
                'cookie_essential': true, // Immer true
                'cookie_analytics': $('#cookie-analytics').is(':checked'),
                'cookie_marketing': $('#cookie-marketing').is(':checked'),
                'cookie_functional': $('#cookie-functional').is(':checked')
            };
            
            this.saveConsents(consents);
        }
        
        // Banner für Registrierung öffnen (verwendet bestehende showBanner Methode)
        showBannerForRegistration() {
            console.log('🍪 Öffne Cookie-Einstellungen für Registrierung');
            this.showBanner();
            
            // Event für Registrierungs-Callback
            this.registrationCallback = true;
        }
        

        
        saveConsents(consents) {
            const self = this;
            
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
                success: (response) => {
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
                    self.showNotification('Netzwerkfehler beim Speichern.', 'error');
                }
            });
        }
        
        applyCookieSettings(consents) {
            console.log('🍪 Wende Cookie-Einstellungen an:', consents);
            
            // Google Analytics
            if (consents.cookie_analytics && consents.cookie_analytics.granted) {
                this.loadGoogleAnalytics();
            } else {
                this.blockGoogleAnalytics();
            }
            
            // Marketing Cookies
            if (consents.cookie_marketing && consents.cookie_marketing.granted) {
                this.loadMarketingScripts();
            } else {
                this.blockMarketingScripts();
            }
            
            // Funktionale Cookies
            if (consents.cookie_functional && consents.cookie_functional.granted) {
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
                <div class="yprint-notification yprint-notification-${type}">
                    ${message}
                </div>
            `);
            
            $('body').append(notification);
            
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 3000);
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