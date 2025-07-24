/**
 * YPrint Turnstile Frontend Integration
 * Handles Turnstile widget management and form validation
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mehrfach-Initialisierung verhindern
    if (window.yprintTurnstileInitialized) {
        console.log('🛡️ YPrint Turnstile: Already initialized, skipping...');
        return;
    }
    
    // Nur initialisieren wenn Turnstile-Widgets vorhanden sind
    if (document.querySelector('.cf-turnstile')) {
        console.log('🛡️ YPrint Turnstile: Widgets detected, initializing...');
        window.yprintTurnstileInitialized = true;
        
        // Warten auf Cloudflare Turnstile Script
        waitForTurnstile().then(() => {
            console.log('🛡️ YPrint Turnstile: Script loaded, setting up widgets...');
            setupTurnstileIntegration();
        });
    }
});

/**
 * Warten bis Turnstile Script geladen ist
 */
function waitForTurnstile() {
    return new Promise((resolve) => {
        const checkTurnstile = () => {
            if (window.turnstile && typeof window.turnstile.render === 'function') {
                resolve();
            } else {
                setTimeout(checkTurnstile, 100);
            }
        };
        checkTurnstile();
    });
}

/**
 * Turnstile Integration einrichten
 */
function setupTurnstileIntegration() {
    const widgets = new Map();
    const processedForms = new Set();

    // Duplikate sofort bereinigen bevor Rendering startet
    const allContainers = document.querySelectorAll('.cf-turnstile');
    console.log('🛡️ Turnstile: Gefundene Container vor Bereinigung:', allContainers.length);
    
    // Gruppiere Container nach Formularen
    const containersByForm = new Map();
    allContainers.forEach(container => {
        const form = container.closest('form');
        const formId = form?.id || 'no-form';
        
        if (!containersByForm.has(formId)) {
            containersByForm.set(formId, []);
        }
        containersByForm.get(formId).push(container);
    });

    // Pro Form nur den ersten Container behalten, Rest entfernen
    containersByForm.forEach((containers, formId) => {
        if (containers.length > 1) {
            console.log(`🛡️ Turnstile: Form ${formId} hat ${containers.length} Container - bereinige Duplikate`);
            // Ersten Container behalten, Rest entfernen
            containers.slice(1).forEach((duplicateContainer, index) => {
                console.log(`🛡️ Turnstile: Entferne Duplikat ${index + 1} aus Form ${formId}`);
                duplicateContainer.closest('.turnstile-widget-container, .yprint-input-group')?.remove();
            });
        }
    });

    // Widgets rendern - jetzt garantiert nur ein Container pro Form
    document.querySelectorAll('.cf-turnstile').forEach((container, index) => {
        const form = container.closest('form');
        const formId = form?.id || 'no-form';
        
        // Bereits verarbeitet?
        if (processedForms.has(formId)) {
            console.log(`🛡️ Turnstile: Form ${formId} bereits verarbeitet, überspringe`);
            return;
        }
        
        // Container bereits gerendert?
        if (container.hasAttribute('data-rendered') || container.querySelector('iframe')) {
            console.log(`🛡️ Turnstile: Container bereits gerendert, überspringe`);
            processedForms.add(formId);
            return;
        }

        const siteKey = container.getAttribute('data-sitekey');
        const theme = container.getAttribute('data-theme') || 'auto';

        if (!siteKey) {
            console.error(`🛡️ Turnstile: Kein Site Key für Form ${formId}`);
            return;
        }

        try {
            // Form als verarbeitet markieren BEVOR Rendering
            processedForms.add(formId);
            
            const widgetId = window.turnstile.render(container, {
                sitekey: siteKey,
                theme: theme,
                callback: (token) => {
                    console.log(`🛡️ Turnstile verified for form: ${formId}`);
                    widgets.set(formId, { isValid: true, token: token, widgetId: widgetId });
                    removeErrorMessage(formId);
                },
                'error-callback': (error) => {
                    console.error(`🛡️ Turnstile error for form ${formId}:`, error);
                    widgets.set(formId, { isValid: false, token: null, widgetId: widgetId });
                    showErrorMessage(formId, 'Bot-Verifikation fehlgeschlagen. Bitte versuche es erneut.');
                },
                'expired-callback': () => {
                    console.log(`🛡️ Turnstile expired for form: ${formId}`);
                    widgets.set(formId, { isValid: false, token: null, widgetId: widgetId });
                    showErrorMessage(formId, 'Verifikation abgelaufen. Bitte bestätige erneut, dass du kein Bot bist.');
                }
            });

            widgets.set(formId, { widgetId: widgetId, isValid: false, token: null });
            container.setAttribute('data-rendered', 'true');
            console.log(`🛡️ Turnstile: Widget erfolgreich gerendert für Form ${formId}`);

        } catch (error) {
            console.error(`🛡️ Turnstile render error für Form ${formId}:`, error);
            processedForms.delete(formId); // Retry erlauben bei Fehler
            showFallbackMessage(container);
        }
    });

    console.log(`🛡️ Turnstile: Initialisierung abgeschlossen. Verarbeitete Forms:`, Array.from(processedForms));
}

/**
 * Error message für Form anzeigen
 */
function showErrorMessage(formId, message) {
    const form = document.getElementById(formId);
    if (!form) return;

    // Bestehende Error Messages entfernen
    removeErrorMessage(formId);

    // Neue Error Message erstellen
    const errorDiv = document.createElement('div');
    errorDiv.className = 'yprint-turnstile-error';
    errorDiv.setAttribute('data-form', formId);
    errorDiv.innerHTML = `
        <div style="background: #fee2e2; border: 1px solid #fecaca; color: #dc2626; padding: 12px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
            ${message}
        </div>
    `;

    // Error message am Anfang des Forms einfügen
    form.insertBefore(errorDiv, form.firstChild);
}

/**
 * Error message für Form entfernen
 */
function removeErrorMessage(formId) {
    const errorMessages = document.querySelectorAll(`[data-form="${formId}"].yprint-turnstile-error`);
    errorMessages.forEach(msg => msg.remove());
}

/**
 * Fallback message wenn Turnstile nicht lädt
 */
function showFallbackMessage(container) {
    container.innerHTML = `
        <div style="background: #fef3c7; border: 1px solid #fbbf24; color: #92400e; padding: 12px; border-radius: 8px; text-align: center; font-size: 14px;">
            Bot-Schutz konnte nicht geladen werden. Bitte lade die Seite neu.
        </div>
    `;
}