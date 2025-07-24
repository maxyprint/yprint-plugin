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

    // AGGRESSIVE Duplikat-Bereinigung vor Rendering
    const allContainers = document.querySelectorAll('.cf-turnstile');
    console.log('🛡️ Frontend JavaScript: Gefundene .cf-turnstile Container:', allContainers.length);

    // Log jedes Containers für Debugging
    allContainers.forEach((container, index) => {
        const form = container.closest('form');
        const formId = form?.id || 'unbekannt';
        const hasManualAttribute = !!container.closest('[data-manual-turnstile]');
        console.log(`🛡️ Frontend JavaScript: Container ${index}:`, {
            'hasAttribute data-rendered': container.hasAttribute('data-rendered'),
            'has iframe': !!container.querySelector('iframe'), 
            'has data-callback': !!container.getAttribute('data-callback'),
            'has data-error-callback': !!container.getAttribute('data-error-callback'),
            'innerHTML': container.innerHTML,
            'formId': formId,
            'isManual': hasManualAttribute
        });
    });

    // Bereits gerenderte Widgets sofort entfernen (Error 300030 Schutz)
    allContainers.forEach((container, index) => {
        if (container.hasAttribute('data-rendered') || container.querySelector('iframe')) {
            console.log(`🛡️ Turnstile: Entferne bereits gerendertes Widget ${index}`);
            container.closest('.turnstile-widget-container, .yprint-input-group')?.remove();
            return;
        }
    });

    // VERSCHÄRFTE Formular-basierte Duplikat-Entfernung
    const formGroups = new Map();
    const globalWidgetRegistry = new Set(); // Globaler Widget-Tracker

    document.querySelectorAll('.cf-turnstile').forEach(container => {
        const form = container.closest('form');
        const formId = form?.id || 'no-form';
        const hasManualAttribute = !!container.closest('[data-manual-turnstile]');
        const containerPosition = Array.from(document.querySelectorAll('.cf-turnstile')).indexOf(container);
        
        console.log(`🛡️ Turnstile DETAILED: Container ${containerPosition}:`, {
            formId: formId,
            isManual: hasManualAttribute,
            hasIframe: !!container.querySelector('iframe'),
            isRendered: container.hasAttribute('data-rendered'),
            innerHTML: container.innerHTML.substring(0, 50)
        });
        
        if (!formGroups.has(formId)) {
            formGroups.set(formId, []);
        }
        formGroups.get(formId).push({
            container: container,
            isManual: hasManualAttribute,
            position: containerPosition
        });
    });

    // AGGRESSIV: Bei mehr als einem Container pro Form - nur EINEN behalten
    formGroups.forEach((containerInfos, formId) => {
        if (containerInfos.length > 1) {
            console.log(`🛡️ Turnstile: CRITICAL - Form ${formId} hat ${containerInfos.length} Container:`, 
                containerInfos.map(info => `${info.position}(${info.isManual ? 'manual' : 'auto'})`));
            
            // Priorität: Manuell > Erstes gefundenes
            const sortedContainers = containerInfos.sort((a, b) => {
                if (a.isManual && !b.isManual) return -1;
                if (!a.isManual && b.isManual) return 1;
                return a.position - b.position;
            });
            
            const keepContainer = sortedContainers[0];
            console.log(`🛡️ Turnstile: Behalte Container ${keepContainer.position} (${keepContainer.isManual ? 'manual' : 'auto'})`);
            
            // Alle anderen entfernen
            sortedContainers.slice(1).forEach((containerInfo, index) => {
                console.log(`🛡️ Turnstile: ENTFERNE Duplikat ${containerInfo.position} aus Form ${formId}`);
                const wrapper = containerInfo.container.closest('.turnstile-widget-container, .yprint-input-group');
                if (wrapper) {
                    wrapper.remove();
                } else {
                    containerInfo.container.remove();
                }
            });
            
            // Registriere das behaltene Widget global
            globalWidgetRegistry.add(keepContainer.container);
        } else {
            // Einzelne Container auch registrieren
            globalWidgetRegistry.add(containerInfos[0].container);
        }
    });

    console.log(`🛡️ Turnstile: Finale Anzahl registrierter Widgets: ${globalWidgetRegistry.size}`);

    // Widgets rendern - jetzt garantiert nur ein Container pro Form
    document.querySelectorAll('.cf-turnstile').forEach((container, index) => {
        const form = container.closest('form');
        const formId = form?.id || 'no-form';
        
        // Überspringe bereits verarbeitete Formulare
        if (processedForms.has(formId)) {
            console.log(`🛡️ Turnstile: Überspringe bereits verarbeitetes Formular ${formId}`);
            return;
        }
        
        // Überspringe bereits gerenderte Container
        if (container.hasAttribute('data-rendered') || container.querySelector('iframe')) {
            console.log(`🛡️ Turnstile: Überspringe bereits gerendertes Widget in Form ${formId}`);
            return;
        }
        
        console.log(`🛡️ Turnstile: Rendere Widget für Form ${formId} (Container ${index})`);
        
        // Widget rendern
        try {
            turnstile.render(container, {
                sitekey: yprintTurnstileConfig.sitekey,
                callback: function(token) {
                    console.log(`🛡️ Turnstile: Token erhalten für Form ${formId}`);
                    if (form) {
                        const tokenInput = form.querySelector('input[name="cf-turnstile-response"]');
                        if (tokenInput) {
                            tokenInput.value = token;
                        } else {
                            // Token-Input erstellen falls nicht vorhanden
                            const newTokenInput = document.createElement('input');
                            newTokenInput.type = 'hidden';
                            newTokenInput.name = 'cf-turnstile-response';
                            newTokenInput.value = token;
                            form.appendChild(newTokenInput);
                        }
                    }
                },
                'error-callback': function() {
                    console.error(`🛡️ Turnstile: Fehler beim Rendern für Form ${formId}`);
                },
                'expired-callback': function() {
                    console.log(`🛡️ Turnstile: Token abgelaufen für Form ${formId}`);
                    if (form) {
                        const tokenInput = form.querySelector('input[name="cf-turnstile-response"]');
                        if (tokenInput) {
                            tokenInput.value = '';
                        }
                    }
                }
            });
            
            // Container als gerendert markieren
            container.setAttribute('data-rendered', 'true');
            processedForms.add(formId);
            widgets.set(formId, container);
            
            console.log(`🛡️ Turnstile: Widget erfolgreich gerendert für Form ${formId}`);
            
        } catch (error) {
            console.error(`🛡️ Turnstile: Fehler beim Rendern des Widgets für Form ${formId}:`, error);
        }
    });
    
    console.log(`🛡️ Turnstile: Integration abgeschlossen. Verarbeitete Formulare: ${processedForms.size}`);
    return widgets;
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