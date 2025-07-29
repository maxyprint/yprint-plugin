/**
 * YPrint Consent Admin JavaScript
 * Admin-Funktionen für Cookie-Consent-Management
 * 
 * @package YPrint
 * @since 1.3.0
 */

(function($) {
    'use strict';
    
    class YPrintConsentAdmin {
        constructor() {
            this.config = window.yprintConsentAdmin || {};
            this.init();
        }
        
        init() {
            console.log('🍪 YPrint Consent Admin: Initialisierung gestartet');
            
            $(document).ready(() => {
                this.bindEvents();
            });
        }
        
        bindEvents() {
            // Essenzielle Cookies korrigieren
            $(document).on('click', '#fix-essential-cookies', (e) => {
                e.preventDefault();
                this.fixEssentialCookies();
            });
            
            // Rechtstext speichern
            $(document).on('click', '.save-text-btn', (e) => {
                e.preventDefault();
                this.saveLegalText($(e.currentTarget));
            });
            
            // Export-Funktionen
            $(document).on('click', '#export-full-consents', (e) => {
                e.preventDefault();
                this.exportConsents('full');
            });
            
            $(document).on('click', '#export-anonymous-stats', (e) => {
                e.preventDefault();
                this.exportConsents('anonymous');
            });
            
            $(document).on('click', '#export-legal-texts', (e) => {
                e.preventDefault();
                this.exportLegalTexts();
            });
            
            // ✅ NEU: Tab-Navigation
            $(document).on('click', '.yprint-tab-btn', (e) => {
                e.preventDefault();
                this.switchTab($(e.currentTarget));
            });
            
            // ✅ NEU: Synchronisations-Funktionen
            $(document).on('click', '#sync-files-to-db', (e) => {
                e.preventDefault();
                this.syncFilesToDb();
            });
            
            $(document).on('click', '#sync-db-to-files', (e) => {
                e.preventDefault();
                this.syncDbToFiles();
            });
            
            $(document).on('click', '#sync-privacy-policy', (e) => {
                e.preventDefault();
                this.syncPrivacyPolicy();
            });
            
            $(document).on('click', '.sync-to-db-btn', (e) => {
                e.preventDefault();
                this.importFileToDb($(e.currentTarget));
            });
            
            console.log('🍪 Admin Event-Handler registriert');
        }
        
        /**
         * Essenzielle Cookies korrigieren
         */
        fixEssentialCookies() {
            if (!confirm('⚠️ WARNUNG: Dies wird alle abgelehnten essenziellen Cookies auf "akzeptiert" setzen. Fortfahren?')) {
                return;
            }
            
            console.log('🍪 Korrigiere essenzielle Cookies...');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_fix_essential_cookies',
                    nonce: this.config.nonce
                },
                beforeSend: function() {
                    $('#fix-essential-cookies').prop('disabled', true).text('Korrigiere...');
                },
                success: (response) => {
                    console.log('🍪 Korrektur-Response:', response);
                    if (response.success) {
                        this.showAdminNotification('✅ Essenzielle Cookies erfolgreich korrigiert!', 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showAdminNotification('❌ Fehler beim Korrigieren: ' + (response.data?.message || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🍪 Netzwerkfehler:', error);
                    this.showAdminNotification('❌ Netzwerkfehler beim Korrigieren', 'error');
                },
                complete: function() {
                    $('#fix-essential-cookies').prop('disabled', false).text('🔧 Essenzielle Cookies korrigieren');
                }
            });
        }
        
        /**
         * Rechtstext speichern
         */
        saveLegalText(button) {
            const card = button.closest('.yprint-text-editor-card');
            const textId = card.data('text-id');
            const editor = card.find('textarea, .wp-editor-area');
            const content = editor.val();
            
            console.log('🍪 Speichere Rechtstext:', textId);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_save_legal_text',
                    nonce: this.config.nonce,
                    text_id: textId,
                    content: content
                },
                beforeSend: function() {
                    button.prop('disabled', true).text('Speichere...');
                },
                success: (response) => {
                    console.log('🍪 Speichern-Response:', response);
                    if (response.success) {
                        this.showAdminNotification('✅ Text erfolgreich gespeichert!', 'success');
                        // Version aktualisieren
                        card.find('.text-version').text('Version: ' + response.data.new_version);
                    } else {
                        this.showAdminNotification('❌ Fehler beim Speichern: ' + (response.data?.message || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🍪 Netzwerkfehler:', error);
                    this.showAdminNotification('❌ Netzwerkfehler beim Speichern', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).text('Speichern');
                }
            });
        }
        
        /**
         * Consent-Daten exportieren
         */
        exportConsents(type) {
            const filters = this.getExportFilters();
            
            console.log('🍪 Exportiere Consents:', type, filters);
            
            // Formular erstellen und submit
            const form = $('<form>', {
                method: 'POST',
                action: this.config.ajaxUrl,
                target: '_blank'
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'yprint_export_consents'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: this.config.nonce
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'export_type',
                value: type
            }));
            
            // Filter hinzufügen
            Object.keys(filters).forEach(key => {
                if (filters[key]) {
                    form.append($('<input>', {
                        type: 'hidden',
                        name: key,
                        value: filters[key]
                    }));
                }
            });
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            this.showAdminNotification('📊 Export gestartet - Download beginnt automatisch', 'info');
        }
        
        /**
         * Export-Filter sammeln
         */
        getExportFilters() {
            const filters = {};
            
            // Datum-Filter
            const dateFrom = $('#date_from').val();
            const dateTo = $('#date_to').val();
            if (dateFrom) filters.date_from = dateFrom;
            if (dateTo) filters.date_to = dateTo;
            
            // Consent-Typen
            const consentTypes = [];
            $('#export-filter-form input[name="consent_types[]"]:checked').each(function() {
                consentTypes.push($(this).val());
            });
            if (consentTypes.length > 0) {
                filters.consent_types = consentTypes;
            }
            
            return filters;
        }
        
        /**
         * Rechtstexte exportieren
         */
        exportLegalTexts() {
            console.log('🍪 Exportiere Rechtstexte...');
            
            const form = $('<form>', {
                method: 'POST',
                action: this.config.ajaxUrl,
                target: '_blank'
            });
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'action',
                value: 'yprint_export_legal_texts'
            }));
            
            form.append($('<input>', {
                type: 'hidden',
                name: 'nonce',
                value: this.config.nonce
            }));
            
            $('body').append(form);
            form.submit();
            form.remove();
            
            this.showAdminNotification('📄 Rechtstexte-Export gestartet', 'info');
        }
        
        /**
         * ✅ NEU: Tab wechseln
         */
        switchTab(button) {
            const tabName = button.data('tab');
            
            // Alle Tabs deaktivieren
            $('.yprint-tab-btn').removeClass('active');
            $('.yprint-tab-content').removeClass('active');
            
            // Gewählten Tab aktivieren
            button.addClass('active');
            $('#' + tabName + '-tab').addClass('active');
            
            console.log('🍪 Tab gewechselt:', tabName);
        }
        
        /**
         * ✅ NEU: Dateien in Datenbank synchronisieren
         */
        syncFilesToDb() {
            if (!confirm('⚠️ WARNUNG: Dies wird alle Rechtstexte aus dem Rechtstexte/ Ordner in die Datenbank importieren. Fortfahren?')) {
                return;
            }
            
            console.log('🍪 Synchronisiere Dateien → Datenbank...');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_sync_files_to_db',
                    nonce: this.config.nonce
                },
                beforeSend: function() {
                    $('#sync-files-to-db').prop('disabled', true).text('Synchronisiere...');
                },
                success: (response) => {
                    console.log('🍪 Sync-Response:', response);
                    if (response.success) {
                        this.showAdminNotification('✅ ' + response.data.message, 'success');
                        setTimeout(() => {
                            location.reload();
                        }, 2000);
                    } else {
                        this.showAdminNotification('❌ ' + (response.data?.message || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🍪 Netzwerkfehler:', error);
                    this.showAdminNotification('❌ Netzwerkfehler bei Synchronisation', 'error');
                },
                complete: function() {
                    $('#sync-files-to-db').prop('disabled', false).html('<i class="fas fa-download"></i> Alle importieren');
                }
            });
        }
        
        /**
         * ✅ NEU: Datenbank in Dateien synchronisieren
         */
        syncDbToFiles() {
            if (!confirm('⚠️ WARNUNG: Dies wird alle Datenbank-Texte als HTML-Dateien in den Rechtstexte/ Ordner exportieren. Fortfahren?')) {
                return;
            }
            
            console.log('🍪 Synchronisiere Datenbank → Dateien...');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_sync_db_to_files',
                    nonce: this.config.nonce
                },
                beforeSend: function() {
                    $('#sync-db-to-files').prop('disabled', true).text('Synchronisiere...');
                },
                success: (response) => {
                    console.log('🍪 Sync-Response:', response);
                    if (response.success) {
                        this.showAdminNotification('✅ ' + response.data.message, 'success');
                    } else {
                        this.showAdminNotification('❌ ' + (response.data?.message || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🍪 Netzwerkfehler:', error);
                    this.showAdminNotification('❌ Netzwerkfehler bei Synchronisation', 'error');
                },
                complete: function() {
                    $('#sync-db-to-files').prop('disabled', false).html('<i class="fas fa-upload"></i> Alle exportieren');
                }
            });
        }
        
        /**
         * ✅ NEU: Datenschutzerklärung synchronisieren
         */
        syncPrivacyPolicy() {
            if (!confirm('⚠️ WARNUNG: Dies wird die Datenschutzerklärung zwischen Datei und Cookie-Consent-System synchronisieren. Fortfahren?')) {
                return;
            }
            
            console.log('🍪 Synchronisiere Datenschutzerklärung...');
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_sync_privacy_policy',
                    nonce: this.config.nonce
                },
                beforeSend: function() {
                    $('#sync-privacy-policy').prop('disabled', true).text('Synchronisiere...');
                },
                success: (response) => {
                    console.log('🍪 Privacy-Sync-Response:', response);
                    if (response.success) {
                        let message = '✅ ' + response.data.message;
                        if (response.data.cookie_section_found) {
                            message += ' (Cookie-Sektion gefunden)';
                        }
                        this.showAdminNotification(message, 'success');
                    } else {
                        this.showAdminNotification('❌ ' + (response.data?.message || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🍪 Netzwerkfehler:', error);
                    this.showAdminNotification('❌ Netzwerkfehler bei Datenschutz-Synchronisation', 'error');
                },
                complete: function() {
                    $('#sync-privacy-policy').prop('disabled', false).html('<i class="fas fa-sync"></i> Datenschutz synchronisieren');
                }
            });
        }
        
        /**
         * ✅ NEU: Einzelne Datei in DB importieren
         */
        importFileToDb(button) {
            const slug = button.data('slug');
            
            if (!confirm('⚠️ WARNUNG: Dies wird die Datei "' + slug + '" in die Datenbank importieren. Fortfahren?')) {
                return;
            }
            
            console.log('🍪 Importiere Datei:', slug);
            
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'yprint_import_file_to_db',
                    nonce: this.config.nonce,
                    slug: slug
                },
                beforeSend: function() {
                    button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Importiere...');
                },
                success: (response) => {
                    console.log('🍪 Import-Response:', response);
                    if (response.success) {
                        this.showAdminNotification('✅ ' + response.data.message, 'success');
                    } else {
                        this.showAdminNotification('❌ ' + (response.data?.message || 'Unbekannter Fehler'), 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('🍪 Netzwerkfehler:', error);
                    this.showAdminNotification('❌ Netzwerkfehler beim Import', 'error');
                },
                complete: function() {
                    button.prop('disabled', false).html('<i class="fas fa-sync"></i> In DB importieren');
                }
            });
        }
        
        /**
         * Admin-Notification anzeigen
         */
        showAdminNotification(message, type = 'info') {
            const notification = $(`
                <div class="yprint-admin-notification yprint-admin-notification-${type}" style="
                    position: fixed;
                    top: 32px;
                    right: 20px;
                    background: ${type === 'success' ? '#46b450' : type === 'error' ? '#dc3232' : '#0073aa'};
                    color: white;
                    padding: 15px 20px;
                    border-radius: 4px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
                    z-index: 100000;
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen-Sans, Ubuntu, Cantarell, 'Helvetica Neue', sans-serif;
                    font-size: 14px;
                    max-width: 400px;
                    word-wrap: break-word;
                ">
                    ${message}
                </div>
            `);
            
            $('body').append(notification);
            
            // Automatisch ausblenden nach 4 Sekunden
            setTimeout(() => {
                notification.fadeOut(() => notification.remove());
            }, 4000);
            
            console.log(`🍪 Admin Notification: ${message} (${type})`);
        }
    }
    
    // Initialisierung
    $(document).ready(() => {
        if (!window.yprintConsentAdmin) {
            window.yprintConsentAdmin = new YPrintConsentAdmin();
            console.log('🍪 Consent Admin initialisiert');
        }
    });
    
})(jQuery);