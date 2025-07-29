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
            });
        }
        
        setupElements() {
            this.banner = $('#yprint-cookie-banner');
            this.icon = $('#yprint-consent-icon');
            
            console.log('🍪 Banner gefunden:', this.banner.length > 0);
            console.log('🍪 Icon gefunden:', this.icon.length > 0);
            
            // KEINE automatische Initialisierung mehr hier
            console.log('🍪 Elemente gefunden, warte auf Consent-Status-Prüfung');
        }
        
        initializeConsentStatus() {
            // Kleine Verzögerung für bessere DOM-Erkennung
            setTimeout(() => {
                console.log('🍪 === CONSENT STATUS INITIALISIERUNG START ===');
                
                // ✅ NEU: Vollständige Cookie-Detection mit Debug
                this.debugCookieDetection();
                
                // Prüfe PHP-Attribut für korrekte Initial-Erkennung
                const bannerElement = document.getElementById('yprint-cookie-banner');
                const phpStyle = bannerElement ? bannerElement.getAttribute('style') : '';
                const isInitialBanner = phpStyle && phpStyle.includes('display: flex');
                
                // Zusätzliche Prüfungen
                const bannerVisible = this.banner.is(':visible');
                const hasHiddenClass = this.banner.hasClass('yprint-hidden');
                
                console.log('🍪 PHP Style:', phpStyle);
                console.log('🍪 Banner sichtbar:', bannerVisible);
                console.log('🍪 Hat hidden class:', hasHiddenClass);
                console.log('🍪 Initial Banner erkannt:', isInitialBanner);
                
                // ✅ NEU: Direkte Cookie-Prüfung für bessere Entscheidung
                const hasYPrintCookies = this.hasExistingYPrintCookies();
                const cookieDecision = this.evaluateCookieDecision();
                
                console.log('🍪 Hat YPrint Cookies:', hasYPrintCookies);
                console.log('🍪 Cookie-Entscheidung:', cookieDecision);
                
                // Debug: Alle Cookie-Kategorien-Status prüfen
                $('.yprint-cookie-category').each(function() {
                    const cookieType = $(this).data('cookie-type');
                    const isSelected = $(this).hasClass('selected');
                    const checkbox = $(this).find('input[type="checkbox"]');
                    const isChecked = checkbox.length > 0 ? checkbox.prop('checked') : false;
                    console.log(`🍪 Cookie ${cookieType}: selected=${isSelected}, checked=${isChecked}`);
                });
                
                // ✅ NEU: Vereinfachte Entscheidungslogik
                if (!hasYPrintCookies || cookieDecision.showBanner) {
                    // Banner wird initial angezeigt - KEINE Einstellungen laden
                    console.log('🍪 ENTSCHEIDUNG: Banner anzeigen - Grund:', cookieDecision.reason);
                    
                    // Stelle sicher, dass nur essenzielle Cookies vorausgewählt sind
                    this.resetToEssentialOnly();
                    this.showBanner();
                } else {
                    // Banner ist ausgeblendet - lade Einstellungen für Icon
                    console.log('🍪 ENTSCHEIDUNG: Banner ausblenden - Grund:', cookieDecision.reason);
                    this.hideBanner();
                    this.checkConsentStatus();
                }
                
                console.log('🍪 === CONSENT STATUS INITIALISIERUNG ENDE ===');
            }, 250); // 250ms Verzögerung
        }
        
        // ✅ NEU: Detaillierte Cookie-Detection mit Debug-Logs
        debugCookieDetection() {
            console.log('🍪 === COOKIE DETECTION DEBUG START ===');
            
            // Alle Cookies anzeigen
            console.log('🍪 Alle Browser-Cookies:', document.cookie);
            
            // YPrint-spezifische Cookies extrahieren
            const allCookies = document.cookie.split(';');
            const yprintCookies = {};
            
            allCookies.forEach(cookie => {
                const [name, value] = cookie.trim().split('=');
                if (name && name.includes('yprint_consent')) {
                    yprintCookies[name] = value ? decodeURIComponent(value) : '';
                }
            });
            
            console.log('🍪 YPrint-Cookies gefunden:', Object.keys(yprintCookies));
            console.log('🍪 YPrint-Cookie-Details:', yprintCookies);
            
            // Spezifische Cookie-Prüfungen
            const hasDecisionCookie = 'yprint_consent_decision' in yprintCookies;
            const hasPreferencesCookie = 'yprint_consent_preferences' in yprintCookies;
            const hasTimestampCookie = 'yprint_consent_timestamp' in yprintCookies;
            
            console.log('🍪 Cookie-Prüfungen:');
            console.log('- Decision Cookie:', hasDecisionCookie);
            console.log('- Preferences Cookie:', hasPreferencesCookie);
            console.log('- Timestamp Cookie:', hasTimestampCookie);
            
            // Preferences-Cookie Details
            if (hasPreferencesCookie) {
                try {
                    const preferencesData = JSON.parse(yprintCookies['yprint_consent_preferences']);
                    console.log('🍪 Preferences-Daten:', preferencesData);
                    
                    if (preferencesData.timestamp) {
                        const timestamp = preferencesData.timestamp;
                        const now = Math.floor(Date.now() / 1000);
                        const oneYearAgo = now - (365 * 24 * 60 * 60);
                        const isValid = timestamp >= oneYearAgo;
                        
                        console.log('🍪 Timestamp-Validierung:');
                        console.log('- Timestamp:', timestamp, '(' + new Date(timestamp * 1000).toLocaleString() + ')');
                        console.log('- Jetzt:', now, '(' + new Date(now * 1000).toLocaleString() + ')');
                        console.log('- Ein Jahr her:', oneYearAgo, '(' + new Date(oneYearAgo * 1000).toLocaleString() + ')');
                        console.log('- Gültig:', isValid);
                    }
                } catch (e) {
                    console.error('🍪 FEHLER beim Parsen der Preferences:', e);
                }
            }
            
            // Timestamp-Cookie Details
            if (hasTimestampCookie) {
                const timestamp = parseInt(yprintCookies['yprint_consent_timestamp']);
                const now = Math.floor(Date.now() / 1000);
                const oneYearAgo = now - (365 * 24 * 60 * 60);
                const isValid = timestamp >= oneYearAgo;
                
                console.log('🍪 Separates Timestamp-Cookie:');
                console.log('- Timestamp:', timestamp, '(' + new Date(timestamp * 1000).toLocaleString() + ')');
                console.log('- Gültig:', isValid);
            }
            
            console.log('🍪 === COOKIE DETECTION DEBUG ENDE ===');
        }
        
        // ✅ NEU: Prüft ob YPrint-Cookies existieren
        hasExistingYPrintCookies() {
            const cookies = document.cookie.split(';');
            const yprintCookiesFound = cookies.some(cookie => {
                const name = cookie.trim().split('=')[0];
                return name && name.includes('yprint_consent');
            });
            
            console.log('🍪 hasExistingYPrintCookies:', yprintCookiesFound);
            return yprintCookiesFound;
        }
        
        // ✅ NEU: Evaluiert Cookie-Entscheidung mit Grund
        evaluateCookieDecision() {
            const decision = {
                showBanner: true,
                reason: 'keine_cookies'
            };
            
            const cookies = document.cookie.split(';');
            const yprintCookies = {};
            
            cookies.forEach(cookie => {
                const [name, value] = cookie.trim().split('=');
                if (name && name.includes('yprint_consent')) {
                    yprintCookies[name] = value ? decodeURIComponent(value) : '';
                }
            });
            
            if (Object.keys(yprintCookies).length === 0) {
                decision.reason = 'keine_yprint_cookies_gefunden';
                return decision;
            }
            
            // Prüfe Decision-Cookie
            if ('yprint_consent_decision' in yprintCookies) {
                decision.showBanner = false;
                decision.reason = 'decision_cookie_vorhanden';
                
                // Zusätzlich Timestamp prüfen wenn vorhanden
                if ('yprint_consent_timestamp' in yprintCookies) {
                    const timestamp = parseInt(yprintCookies['yprint_consent_timestamp']);
                    const now = Math.floor(Date.now() / 1000);
                    const oneYearAgo = now - (365 * 24 * 60 * 60);
                    
                    if (timestamp < oneYearAgo) {
                        decision.showBanner = true;
                        decision.reason = 'timestamp_zu_alt';
                    } else {
                        decision.reason = 'decision_cookie_und_timestamp_gueltig';
                    }
                }
            }
            
            // Prüfe Preferences-Cookie als Fallback
            if ('yprint_consent_preferences' in yprintCookies && decision.showBanner) {
                try {
                    const preferencesData = JSON.parse(yprintCookies['yprint_consent_preferences']);
                    if (preferencesData && preferencesData.consents) {
                        decision.showBanner = false;
                        decision.reason = 'preferences_cookie_vorhanden';
                        
                        // Timestamp in Preferences prüfen
                        if (preferencesData.timestamp) {
                            const timestamp = preferencesData.timestamp;
                            const now = Math.floor(Date.now() / 1000);
                            const oneYearAgo = now - (365 * 24 * 60 * 60);
                            
                            if (timestamp < oneYearAgo) {
                                decision.showBanner = true;
                                decision.reason = 'preferences_timestamp_zu_alt';
                            } else {
                                decision.reason = 'preferences_cookie_und_timestamp_gueltig';
                            }
                        }
                    }
                } catch (e) {
                    decision.showBanner = true;
                    decision.reason = 'preferences_cookie_invalid_json';
                }
            }
            
            console.log('🍪 Cookie-Entscheidung evaluiert:', decision);
            return decision;
        }
        
        resetToEssentialOnly() {
            console.log('🍪 RESET: Setze nur essenzielle Cookies als ausgewählt');
            
            // Debug: Status vor Reset
            $('.yprint-cookie-category').each(function() {
                const cookieType = $(this).data('cookie-type');
                const isSelected = $(this).hasClass('selected');
                console.log(`🍪 VOR Reset ${cookieType}: selected=${isSelected}`);
            });
            
            // ALLE Kategorien zurücksetzen - auch visuell
            $('.yprint-cookie-category').removeClass('selected');
            $('.yprint-cookie-category input[type="checkbox"]').prop('checked', false);
            
            console.log('🍪 RESET: Alle Kategorien zurückgesetzt');
            
            // Nur essenzielle Cookies aktivieren
            const essentialCategory = $('.yprint-cookie-category[data-cookie-type="essential"]');
            const essentialCheckbox = $('#cookie-essential');
            
            if (essentialCategory.length && essentialCheckbox.length) {
                essentialCheckbox.prop('checked', true);
                essentialCategory.addClass('selected');
                console.log('🍪 RESET: Nur essenzielle Cookies als ausgewählt markiert');
            }
            
            // Debug: Status nach Reset
            $('.yprint-cookie-category').each(function() {
                const cookieType = $(this).data('cookie-type');
                const isSelected = $(this).hasClass('selected');
                const checkbox = $(this).find('input[type="checkbox"]');
                const isChecked = checkbox.length > 0 ? checkbox.prop('checked') : false;
                console.log(`🍪 NACH Reset ${cookieType}: selected=${isSelected}, checked=${isChecked}`);
            });
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
                
                // ✅ SICHERHEIT: Essenzielle Cookies können nicht deaktiviert werden
                if (cookieType === 'essential') {
                    console.log('🍪 SICHERHEIT: Essenzielle Cookies können nicht deaktiviert werden');
                    this.showNotification('Essenzielle Cookies sind für die Grundfunktionalität der Website erforderlich und können nicht deaktiviert werden.', 'info');
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
            
            // ✅ NEU: Direkte Checkbox-Klicks abfangen
            $(document).on('change', '.yprint-cookie-category input[type="checkbox"]', (e) => {
                const checkbox = $(e.currentTarget);
                const category = checkbox.closest('.yprint-cookie-category');
                const cookieType = category.data('cookie-type');
                
                // ✅ SICHERHEIT: Essenzielle Cookies können nicht deaktiviert werden
                if (cookieType === 'essential' && !checkbox.prop('checked')) {
                    console.log('🍪 SICHERHEIT: Versuch essenzielle Cookies zu deaktivieren - wird verhindert');
                    checkbox.prop('checked', true);
                    category.addClass('selected');
                    this.showNotification('Essenzielle Cookies können nicht deaktiviert werden.', 'warning');
                    return;
                }
                
                // Visuellen State entsprechend setzen
                if (checkbox.prop('checked')) {
                    category.addClass('selected');
                } else {
                    category.removeClass('selected');
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
            
            // Resize-Event für Mobile-Fullscreen
            $(window).on('resize', () => {
                if (this.banner.is(':visible')) {
                    this.handleMobileResize();
                }
            });
            
            console.log('🍪 Event-Handler registriert');
        }
        
        handleMobileResize() {
            const isMobile = window.innerWidth <= 768;
            const isBannerOpen = this.banner.is(':visible');
            
            if (isMobile && isBannerOpen) {
                // Mobile: Body-Scroll verhindern
                $('body').css({
                    'overflow': 'hidden',
                    'position': 'fixed',
                    'width': '100%',
                    'height': '100%'
                });
            } else if (!isMobile && isBannerOpen) {
                // Desktop: Body-Scroll wiederherstellen
                $('body').css({
                    'overflow': '',
                    'position': '',
                    'width': '',
                    'height': ''
                });
            }
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
            
            // Schutz vor undefined/null
            if (!consents || typeof consents !== 'object') {
                console.log('🍪 Consent-Status ist undefined/null - zeige Banner');
                this.resetToEssentialOnly();
                this.showBanner();
                return;
            }
            
            console.log('🍪 Anzahl gespeicherte Consents:', Object.keys(consents).length);
            
            // Wenn keine Einwilligungen vorhanden, Banner zeigen
            if (Object.keys(consents).length === 0) {
                console.log('🍪 Keine Consents gefunden - zeige Initial-Banner');
                this.resetToEssentialOnly();
                this.showBanner();
                return;
            }
            
            // Cookies entsprechend setzen/blockieren (für Icon-Funktionalität)
            this.applyCookieSettings(consents);
            
            // WICHTIG: Checkboxen werden hier NICHT gesetzt!
            console.log('🍪 Consent-Status für Icon-Funktionalität geladen - KEINE UI-Updates');
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
            console.log('🍪 Wende Consent auf Formular an (Settings-Modus):', consents);
            
            // Erst alle zurücksetzen
            $('.yprint-cookie-category').removeClass('selected');
            $('.yprint-cookie-category input[type="checkbox"]').prop('checked', false);
            
            // Dann gespeicherte Einstellungen anwenden
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
            
            console.log('🍪 Settings-Banner vollständig konfiguriert');
        }
        
        showBanner() {
            console.log('🍪 Banner wird angezeigt');
            console.log('🍪 Banner vor Show - Hidden-Klasse:', this.banner.hasClass('yprint-hidden'));
            
            this.banner.removeClass('yprint-hidden').css('display', 'flex');
            $('body').addClass('yprint-consent-open');
            
            // Mobile: Body-Scroll verhindern
            if (window.innerWidth <= 768) {
                $('body').css({
                    'overflow': 'hidden',
                    'position': 'fixed',
                    'width': '100%',
                    'height': '100%'
                });
            }
            
            console.log('🍪 Banner nach Show - sichtbar:', this.banner.is(':visible'));
        }
        
        hideBanner() {
            console.log('🍪 Banner wird ausgeblendet');
            
            // Einfache Lösung: Klasse hinzufügen
            this.banner.addClass('yprint-hidden');
            
            $('body').removeClass('yprint-consent-open');
            
            // Mobile: Body-Scroll wiederherstellen
            if (window.innerWidth <= 768) {
                $('body').css({
                    'overflow': '',
                    'position': '',
                    'width': '',
                    'height': ''
                });
            }
            
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
                'cookie_essential': true, // ✅ IMMER true - niemals änderbar
                'cookie_analytics': $('#cookie-analytics').is(':checked'),
                'cookie_marketing': $('#cookie-marketing').is(':checked'),
                'cookie_functional': $('#cookie-functional').is(':checked')
            };
            
            // ✅ Sicherheitscheck: Essenzielle Cookies können nicht abgelehnt werden
            if (!consents.cookie_essential) {
                console.log('🍪 SICHERHEIT: Essenzielle Cookies zwangsweise aktiviert');
                consents.cookie_essential = true;
            }
            
            // ✅ VALIDIERUNG: Prüfe auf verdächtige automatische Klicks
            this.validateConsentData(consents);
            
            console.log('🍪 Gesammelte Consent-States:', consents);
            console.log('🍪 Rufe saveConsents auf...');
            
            // Speichere die Auswahl
            this.saveConsents(consents);
        }
        
        // ✅ NEU: Validierung der Consent-Daten
        validateConsentData(consents) {
            // Prüfe auf "Alle akzeptieren" Pattern
            const nonEssentialCookies = ['cookie_analytics', 'cookie_marketing', 'cookie_functional'];
            const allAccepted = nonEssentialCookies.every(type => consents[type] === true);
            const allDenied = nonEssentialCookies.every(type => consents[type] === false);
            
            if (allAccepted) {
                console.log('🍪 WARNUNG: Möglicher automatischer "Alle akzeptieren" Klick erkannt');
            }
            
            if (allDenied) {
                console.log('🍪 WARNUNG: Möglicher automatischer "Alle ablehnen" Klick erkannt');
            }
            
            // Prüfe logische Konsistenz
            if (consents.cookie_essential !== true) {
                console.error('🍪 KRITISCHER FEHLER: Essenzielle Cookies sind nicht aktiviert!');
                consents.cookie_essential = true; // Erzwingen
            }
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
            
            // Erst Banner anzeigen
            this.banner.removeClass('yprint-hidden').css('display', 'flex');
            $('body').addClass('yprint-consent-open');
            
            // Dann Einstellungen laden und UI aktualisieren
            setTimeout(() => {
                this.loadConsentForSettings();
            }, 100);
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
            
            // ✅ NEU: Browser-Cookies für Gäste setzen (optimiert)
            this.setGuestCookies(consents);
            
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
        
        // ✅ NEU: Browser-Cookies für Gäste setzen (optimiert)
        setGuestCookies(consents) {
            console.log('🍪 Setze Browser-Cookies für Gast:', consents);
            
            const timestamp = Math.floor(Date.now() / 1000);
            const cookieData = {
                consents: consents,
                timestamp: timestamp,
                version: '1.0'
            };
            
            // Cookie-Domain automatisch ermitteln
            const domain = window.location.hostname.replace(/^www\./, '');
            const cookieOptions = `path=/; max-age=31536000; SameSite=Lax; domain=${domain}`;
            
            // Haupt-Cookie setzen
            document.cookie = `yprint_consent_preferences=${encodeURIComponent(JSON.stringify(cookieData))}; ${cookieOptions}`;
            
            // Timestamp-Cookie setzen (für PHP-Kompatibilität)
            document.cookie = `yprint_consent_timestamp=${timestamp}; ${cookieOptions}`;
            
            // Entscheidungs-Cookie setzen
            document.cookie = `yprint_consent_decision=1; ${cookieOptions}`;
            
            console.log('🍪 Browser-Cookies gesetzt für Gast mit Domain:', domain);
            console.log('🍪 Cookie-Daten:', cookieData);
            console.log('🍪 Aktueller Cookie-String:', document.cookie);
            
            // ✅ NEU: Sofortige Validierung
            setTimeout(() => {
                this.validateCookiesSet();
            }, 100);
        }
        
        // ✅ NEU: Cookie-Validierung
        validateCookiesSet() {
            const hasPreferences = document.cookie.includes('yprint_consent_preferences=');
            const hasTimestamp = document.cookie.includes('yprint_consent_timestamp=');
            const hasDecision = document.cookie.includes('yprint_consent_decision=');
            
            console.log('🍪 Cookie-Validierung:');
            console.log('- Preferences:', hasPreferences);
            console.log('- Timestamp:', hasTimestamp);
            console.log('- Decision:', hasDecision);
            
            if (!hasPreferences || !hasTimestamp || !hasDecision) {
                console.error('🍪 FEHLER: Nicht alle Cookies wurden gesetzt!');
                console.error('🍪 Document.cookie:', document.cookie);
            } else {
                console.log('🍪 ✅ Alle Cookies erfolgreich gesetzt');
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
            
            console.log('🧪 Debug-Informationen angezeigt - KEINE automatischen Aktionen');
        }
    }
    
    // Initialisierung - nur einmal
    $(document).ready(() => {
        if (!window.yprintConsentManager) {
            window.yprintConsentManager = new YPrintConsentManager();
            console.log('🍪 Consent Manager erstmalig initialisiert');
        } else {
            console.log('🍪 Consent Manager bereits vorhanden - überspringe Initialisierung');
        }
        
        // Debug-Code nur auf Anfrage - KEIN automatisches Ausführen
        console.log('🍪 Consent Manager bereit - Debug-Funktionen verfügbar über window.yprintConsentManager.debugCookieManager()');
    });
    
    // Debug: Alle Cookies löschen (nur manuell aufrufbar)
    function clearAllCookies() {
        document.cookie.split(";").forEach(function(c) { 
            document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/"); 
        });
        console.log('🧹 Alle Cookies gelöscht');
        localStorage.clear();
        sessionStorage.clear();
        location.reload();
    }
    
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