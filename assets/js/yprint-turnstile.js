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

    // Widgets rendern - doppelt abgesichert
console.log('🛡️ Frontend JavaScript: Gefundene .cf-turnstile Container:', document.querySelectorAll('.cf-turnstile').length);
document.querySelectorAll('.cf-turnstile').forEach((container, index) => {
    console.log(`🛡️ Frontend JavaScript: Container ${index}:`, {
        'hasAttribute data-rendered': container.hasAttribute('data-rendered'),
        'has iframe': !!container.querySelector('iframe'),
        'has data-callback': container.hasAttribute('data-callback'),
        'has data-error-callback': container.hasAttribute('data-error-callback'),
        'innerHTML': container.innerHTML.substring(0, 100)
    });
    
    if (container.hasAttribute('data-rendered') || container.querySelector('iframe')) {
        console.log('🛡️ Turnstile: Widget bereits gerendert, überspringe...');
        return;
    }

        const siteKey = container.getAttribute('data-sitekey');
        const theme = container.getAttribute('data-theme') || 'auto';
        const formId = container.closest('form')?.id || 'unknown';

        try {
            const widgetId = window.turnstile.render(container, {
                sitekey: siteKey,
                theme: theme,
                callback: (token) => {
                    console.log(`🛡️ Turnstile verified for form: ${formId}`);
                    widgets.set(formId, { isValid: true, token: token });
                    removeErrorMessage(formId);
                },
                'error-callback': (error) => {
                    console.error(`🛡️ Turnstile error for form ${formId}:`, error);
                    widgets.set(formId, { isValid: false, token: null });
                    showErrorMessage(formId, 'Bot-Verifikation fehlgeschlagen. Bitte versuche es erneut.');
                },
                'expired-callback': () => {
                    console.log(`🛡️ Turnstile expired for form: ${formId}`);
                    widgets.set(formId, { isValid: false, token: null });
                    showErrorMessage(formId, 'Verifikation abgelaufen. Bitte bestätige erneut, dass du kein Bot bist.');
                }
            });

            widgets.set(formId, { widgetId: widgetId, isValid: false, token: null });
            container.setAttribute('data-rendered', 'true');
            console.log(`🛡️ Turnstile widget rendered for form: ${formId}`);

        } catch (error) {
            console.error('🛡️ Turnstile render error:', error);
            showFallbackMessage(container);
        }
    });

    // Form-Validierung einrichten
    document.addEventListener('submit', (event) => {
        const form = event.target;
        const formId = form.id;
        
        // Prüfen ob Form ein Turnstile-Widget hat
        if (!widgets.has(formId)) {
            return; // Kein Turnstile für diese Form
        }

        const widget = widgets.get(formId);
        
        // Nur validieren wenn Form noch nicht validiert ist
        if (!form.hasAttribute('data-turnstile-validated')) {
            event.preventDefault();
            
            if (!widget.isValid) {
                showErrorMessage(formId, 'Bitte bestätige, dass du kein Bot bist.');
                return false;
            }
            
            // Form als validiert markieren und erneut absenden
            form.setAttribute('data-turnstile-validated', 'true');
            form.submit();
        }
    });

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
}