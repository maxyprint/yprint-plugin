/**
 * YPrint Checkout - Erweiterte Validierung und Formularverarbeitung
 * Schritt 6: Live-Validierung, Fehlermeldungen und UX-Optimierung
 */

// Zentrale Validierungsklasse
class YPrintCheckoutValidator {
    constructor() {
        this.validationRules = {};
        this.customMessages = {};
        this.validationState = {};
        this.debounceTimers = {};
        
        this.initializeValidation();
    }

    /**
     * Initialisiert das Validierungssystem
     */
    initializeValidation() {
        this.setupValidationRules();
        this.setupCustomMessages();
        this.bindEvents();
        this.createValidationUI();
    }

    /**
     * Definiert Validierungsregeln für alle Felder
     */
    setupValidationRules() {
        this.validationRules = {
            // Adressvalidierung
            first_name: {
                required: true,
                minLength: 2,
                pattern: /^[a-zA-ZäöüÄÖÜß\s-']+$/,
                sanitize: true
            },
            last_name: {
                required: true,
                minLength: 2,
                pattern: /^[a-zA-ZäöüÄÖÜß\s-']+$/,
                sanitize: true
            },
            street: {
                required: true,
                minLength: 3,
                maxLength: 100,
                sanitize: true
            },
            housenumber: {
                required: true,
                pattern: /^[0-9]+[a-zA-Z]?$/,
                sanitize: true
            },
            zip: {
                required: true,
                pattern: /^[0-9]{5}$/,
                custom: this.validateGermanPostalCode.bind(this)
            },
            city: {
                required: true,
                minLength: 2,
                maxLength: 100,
                pattern: /^[a-zA-ZäöüÄÖÜß\s-']+$/,
                sanitize: true
            },
            country: {
                required: true,
                options: ['DE', 'AT', 'CH', 'NL']
            },
            phone: {
                required: false,
                pattern: /^[\+]?[0-9\s\-\(\)]+$/,
                minLength: 10,
                custom: this.validatePhoneNumber.bind(this)
            },

            // E-Mail Validierung
            email: {
                required: true,
                pattern: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                custom: this.validateEmailDomain.bind(this),
                ajax: this.checkEmailAvailability.bind(this)
            },

            // Rechnungsadresse (falls abweichend)
            billing_first_name: {
                required: true,
                conditionalRequired: () => !document.getElementById('billing-same-as-shipping')?.checked,
                minLength: 2,
                pattern: /^[a-zA-ZäöüÄÖÜß\s-']+$/
            },
            billing_last_name: {
                required: true,
                conditionalRequired: () => !document.getElementById('billing-same-as-shipping')?.checked,
                minLength: 2,
                pattern: /^[a-zA-ZäöüÄÖÜß\s-']+$/
            },
            billing_street: {
                required: true,
                conditionalRequired: () => !document.getElementById('billing-same-as-shipping')?.checked,
                minLength: 3
            },
            billing_housenumber: {
                required: true,
                conditionalRequired: () => !document.getElementById('billing-same-as-shipping')?.checked,
                pattern: /^[0-9]+[a-zA-Z]?$/
            },
            billing_zip: {
                required: true,
                conditionalRequired: () => !document.getElementById('billing-same-as-shipping')?.checked,
                pattern: /^[0-9]{5}$/
            },
            billing_city: {
                required: true,
                conditionalRequired: () => !document.getElementById('billing-same-as-shipping')?.checked,
                minLength: 2,
                pattern: /^[a-zA-ZäöüÄÖÜß\s-']+$/
            },
            billing_country: {
                required: true,
                conditionalRequired: () => !document.getElementById('billing-same-as-shipping')?.checked,
                options: ['DE', 'AT', 'CH', 'NL']
            },

            // Gutscheincode
            voucher_code: {
                required: false,
                pattern: /^[A-Z0-9]{3,20}$/,
                transform: (value) => value.toUpperCase(),
                ajax: this.validateVoucherCode.bind(this)
            }
        };
    }

    /**
     * Definiert benutzerdefinierte Fehlermeldungen
     */
    setupCustomMessages() {
        this.customMessages = {
            required: 'Dieses Feld ist erforderlich.',
            minLength: 'Mindestens {min} Zeichen erforderlich.',
            maxLength: 'Maximal {max} Zeichen erlaubt.',
            pattern: 'Bitte geben Sie ein gültiges Format ein.',
            email: 'Bitte geben Sie eine gültige E-Mail-Adresse ein.',
            phone: 'Bitte geben Sie eine gültige Telefonnummer ein.',
            zip: 'Bitte geben Sie eine gültige deutsche Postleitzahl ein.',
            name: 'Nur Buchstaben, Bindestriche und Apostrophe sind erlaubt.',
            housenumber: 'Bitte geben Sie eine gültige Hausnummer ein (z.B. 15, 42a).',
            voucher: 'Ungültiger Gutscheincode.',
            server: 'Ein Serverfehler ist aufgetreten. Bitte versuchen Sie es erneut.'
        };
    }

    /**
     * Bindet Event-Listener für Live-Validierung
     */
    bindEvents() {
        // Input events für Live-Validierung
        document.addEventListener('input', (e) => {
            if (this.shouldValidateField(e.target)) {
                this.debounceValidation(e.target, 300);
            }
        });

        // Blur events für vollständige Validierung
        document.addEventListener('blur', (e) => {
            if (this.shouldValidateField(e.target)) {
                this.validateField(e.target, true);
            }
        }, true);

        // Focus events für Fehlermeldungen ausblenden
        document.addEventListener('focus', (e) => {
            if (this.shouldValidateField(e.target)) {
                this.clearFieldError(e.target);
            }
        }, true);

        // Submit events abfangen
        document.addEventListener('submit', (e) => {
            if (e.target.id === 'address-form' || e.target.id === 'payment-form') {
                if (!this.validateForm(e.target)) {
                    e.preventDefault();
                }
            }
        });

        // Checkbox für Rechnungsadresse
        const billingSameCheckbox = document.getElementById('billing-same-as-shipping');
        if (billingSameCheckbox) {
            billingSameCheckbox.addEventListener('change', () => {
                this.handleBillingAddressToggle();
            });
        }
    }

    /**
     * Prüft ob ein Feld validiert werden soll
     */
    shouldValidateField(field) {
        return field.name && this.validationRules[field.name];
    }

    /**
     * Debounced Validierung für bessere Performance
     */
    debounceValidation(field, delay = 300) {
        const fieldName = field.name;
        
        if (this.debounceTimers[fieldName]) {
            clearTimeout(this.debounceTimers[fieldName]);
        }

        this.debounceTimers[fieldName] = setTimeout(() => {
            this.validateField(field);
        }, delay);
    }

    /**
     * Validiert ein einzelnes Feld
     */
    async validateField(field, showSuccess = false) {
        const fieldName = field.name;
        const value = field.value.trim();
        const rules = this.validationRules[fieldName];

        if (!rules) return true;

        // Feld-Transformation anwenden
        if (rules.transform) {
            const transformedValue = rules.transform(value);
            if (transformedValue !== value) {
                field.value = transformedValue;
            }
        }

        const validationResult = await this.performFieldValidation(field, value, rules);

        // UI aktualisieren
        this.updateFieldUI(field, validationResult, showSuccess);

        // Validierungsstatus speichern
        this.validationState[fieldName] = validationResult;

        return validationResult.isValid;
    }

    /**
     * Führt die eigentliche Validierung durch
     */
    async performFieldValidation(field, value, rules) {
        const result = {
            isValid: true,
            errors: [],
            warnings: [],
            suggestions: []
        };

        // Required Validierung
        if (rules.required || (rules.conditionalRequired && rules.conditionalRequired())) {
            if (!value) {
                result.isValid = false;
                result.errors.push(this.customMessages.required);
                return result;
            }
        }

        // Wenn Feld leer und nicht erforderlich, ist es gültig
        if (!value && !rules.required) {
            return result;
        }

        // Längen-Validierung
        if (rules.minLength && value.length < rules.minLength) {
            result.isValid = false;
            result.errors.push(this.customMessages.minLength.replace('{min}', rules.minLength));
        }

        if (rules.maxLength && value.length > rules.maxLength) {
            result.isValid = false;
            result.errors.push(this.customMessages.maxLength.replace('{max}', rules.maxLength));
        }

        // Pattern-Validierung
        if (rules.pattern && !rules.pattern.test(value)) {
            result.isValid = false;
            result.errors.push(this.getPatternMessage(field.name));
        }

        // Options-Validierung
        if (rules.options && !rules.options.includes(value)) {
            result.isValid = false;
            result.errors.push('Bitte wählen Sie eine gültige Option.');
        }

        // Benutzerdefinierte Validierung
        if (rules.custom) {
            const customResult = await rules.custom(value, field);
            if (!customResult.isValid) {
                result.isValid = false;
                result.errors.push(...customResult.errors);
            }
            if (customResult.warnings) {
                result.warnings.push(...customResult.warnings);
            }
            if (customResult.suggestions) {
                result.suggestions.push(...customResult.suggestions);
            }
        }

        // AJAX-Validierung
        if (rules.ajax && result.isValid) {
            try {
                const ajaxResult = await rules.ajax(value, field);
                if (!ajaxResult.isValid) {
                    result.isValid = false;
                    result.errors.push(...ajaxResult.errors);
                }
                if (ajaxResult.warnings) {
                    result.warnings.push(...ajaxResult.warnings);
                }
            } catch (error) {
                console.error('AJAX validation error:', error);
                result.warnings.push('Validierung konnte nicht abgeschlossen werden.');
            }
        }

        return result;
    }

    /**
     * Aktualisiert die UI basierend auf Validierungsresultat
     */
    updateFieldUI(field, result, showSuccess = false) {
        const fieldContainer = this.getFieldContainer(field);
        const errorContainer = this.getOrCreateErrorContainer(fieldContainer);
        const successContainer = this.getOrCreateSuccessContainer(fieldContainer);

        // Bestehende Klassen entfernen
        field.classList.remove('border-red-500', 'border-green-500', 'border-yellow-500');
        fieldContainer.classList.remove('field-error', 'field-success', 'field-warning');

        if (!result.isValid) {
            // Fehler anzeigen
            field.classList.add('border-red-500');
            fieldContainer.classList.add('field-error');
            this.showErrors(errorContainer, result.errors);
            this.hideSuccess(successContainer);
        } else if (result.warnings.length > 0) {
            // Warnungen anzeigen
            field.classList.add('border-yellow-500');
            fieldContainer.classList.add('field-warning');
            this.showWarnings(errorContainer, result.warnings);
            this.hideSuccess(successContainer);
        } else if (showSuccess && field.value.trim()) {
            // Erfolg anzeigen
            field.classList.add('border-green-500');
            fieldContainer.classList.add('field-success');
            this.showSuccess(successContainer);
            this.hideErrors(errorContainer);
        } else {
            // Neutral
            this.hideErrors(errorContainer);
            this.hideSuccess(successContainer);
        }

        // Vorschläge anzeigen
        if (result.suggestions.length > 0) {
            this.showSuggestions(fieldContainer, result.suggestions);
        }
    }

    /**
     * Erstellt die Validierungs-UI-Elemente
     */
    createValidationUI() {
        // CSS für Validierung hinzufügen
        const style = document.createElement('style');
        style.textContent = `
            .field-container {
                position: relative;
                margin-bottom: 1rem;
            }

            .validation-message {
                font-size: 0.875rem;
                margin-top: 0.25rem;
                display: flex;
                align-items: center;
            }

            .validation-error {
                color: #dc2626;
            }

            .validation-success {
                color: #16a34a;
            }

            .validation-warning {
                color: #d97706;
            }

            .validation-icon {
                width: 1rem;
                height: 1rem;
                margin-right: 0.25rem;
            }

            .field-suggestion {
                background: #fef3c7;
                border: 1px solid #f59e0b;
                border-radius: 0.375rem;
                padding: 0.5rem;
                margin-top: 0.25rem;
                font-size: 0.875rem;
                cursor: pointer;
                transition: background-color 0.2s;
            }

            .field-suggestion:hover {
                background: #fde68a;
            }

            .validation-loading {
                display: inline-flex;
                align-items: center;
                color: #6b7280;
                font-size: 0.875rem;
                margin-top: 0.25rem;
            }

            .validation-spinner {
                width: 1rem;
                height: 1rem;
                border: 2px solid #e5e7eb;
                border-top: 2px solid #3b82f6;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin-right: 0.5rem;
            }

            @keyframes spin {
                to { transform: rotate(360deg); }
            }

            .form-input.border-red-500 {
                border-color: #dc2626 !important;
                box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
            }

            .form-input.border-green-500 {
                border-color: #16a34a !important;
                box-shadow: 0 0 0 3px rgba(22, 163, 74, 0.1);
            }

            .form-input.border-yellow-500 {
                border-color: #d97706 !important;
                box-shadow: 0 0 0 3px rgba(217, 119, 6, 0.1);
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Zeigt Fehlermeldungen an
     */
    showErrors(container, errors) {
        container.innerHTML = errors.map(error => `
            <div class="validation-message validation-error">
                <svg class="validation-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                ${error}
            </div>
        `).join('');
        container.style.display = 'block';
    }

    /**
     * Zeigt Warnungen an
     */
    showWarnings(container, warnings) {
        container.innerHTML = warnings.map(warning => `
            <div class="validation-message validation-warning">
                <svg class="validation-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
                </svg>
                ${warning}
            </div>
        `).join('');
        container.style.display = 'block';
    }

    /**
     * Zeigt Erfolgsmeldung an
     */
    showSuccess(container) {
        container.innerHTML = `
            <div class="validation-message validation-success">
                <svg class="validation-icon" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
                </svg>
                Eingabe gültig
            </div>
        `;
        container.style.display = 'block';
    }

    /**
     * Versteckt Fehlermeldungen
     */
    hideErrors(container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }

    /**
     * Versteckt Erfolgsmeldungen
     */
    hideSuccess(container) {
        container.style.display = 'none';
        container.innerHTML = '';
    }

    /**
     * Zeigt Lade-Indikator während AJAX-Validierung
     */
    showLoading(container) {
        container.innerHTML = `
            <div class="validation-loading">
                <div class="validation-spinner"></div>
                Überprüfung läuft...
            </div>
        `;
        container.style.display = 'block';
    }

    /**
     * Zeigt Vorschläge für Auto-Korrektur
     */
    showSuggestions(fieldContainer, suggestions) {
        // Bestehende Vorschläge entfernen
        const existingSuggestions = fieldContainer.querySelectorAll('.field-suggestion');
        existingSuggestions.forEach(s => s.remove());

        suggestions.forEach(suggestion => {
            const suggestionEl = document.createElement('div');
            suggestionEl.className = 'field-suggestion';
            suggestionEl.textContent = `Meinten Sie: ${suggestion}?`;
            suggestionEl.addEventListener('click', () => {
                const field = fieldContainer.querySelector('input, select, textarea');
                if (field) {
                    field.value = suggestion;
                    this.validateField(field, true);
                }
                suggestionEl.remove();
            });
            fieldContainer.appendChild(suggestionEl);
        });
    }

    /**
     * Hilfsfunktionen für Container-Management
     */
    getFieldContainer(field) {
        let container = field.closest('.field-container');
        if (!container) {
            container = field.parentElement;
            container.classList.add('field-container');
        }
        return container;
    }

    getOrCreateErrorContainer(fieldContainer) {
        let errorContainer = fieldContainer.querySelector('.validation-errors');
        if (!errorContainer) {
            errorContainer = document.createElement('div');
            errorContainer.className = 'validation-errors';
            errorContainer.style.display = 'none';
            fieldContainer.appendChild(errorContainer);
        }
        return errorContainer;
    }

    getOrCreateSuccessContainer(fieldContainer) {
        let successContainer = fieldContainer.querySelector('.validation-success-container');
        if (!successContainer) {
            successContainer = document.createElement('div');
            successContainer.className = 'validation-success-container';
            successContainer.style.display = 'none';
            fieldContainer.appendChild(successContainer);
        }
        return successContainer;
    }

    /**
     * Spezifische Validierungsmethoden
     */
    validateGermanPostalCode(value) {
        const validRanges = [
            { min: 1000, max: 99999 }
        ];
        
        const numValue = parseInt(value);
        const isValid = validRanges.some(range => numValue >= range.min && numValue <= range.max);
        
        return {
            isValid,
            errors: isValid ? [] : [this.customMessages.zip]
        };
    }

    validatePhoneNumber(value) {
        // Erweiterte Telefonnummer-Validierung
        const cleanNumber = value.replace(/[\s\-\(\)]/g, '');
        const isValid = cleanNumber.length >= 10 && cleanNumber.length <= 20;
        
        return {
            isValid,
            errors: isValid ? [] : [this.customMessages.phone],
            suggestions: !isValid && value.length > 0 ? ['Beispiel: +49 123 456789'] : []
        };
    }

    validateEmailDomain(value) {
        // Domain-Blacklist und Suggestions
        const commonDomains = ['gmail.com', 'yahoo.com', 'outlook.com', 'web.de', 'gmx.de'];
        const domain = value.split('@')[1];
        
        if (!domain) {
            return { isValid: false, errors: [this.customMessages.email] };
        }

        // Typo-Erkennung für häufige Domains
        const suggestions = [];
        commonDomains.forEach(commonDomain => {
            if (this.levenshteinDistance(domain, commonDomain) === 1) {
                suggestions.push(value.replace(domain, commonDomain));
            }
        });

        return {
            isValid: true,
            errors: [],
            suggestions: suggestions.slice(0, 1) // Nur den besten Vorschlag
        };
    }

    /**
     * AJAX-Validierungsmethoden
     */
    async checkEmailAvailability(value, field) {
        if (!window.yprint_checkout_params) {
            return { isValid: true, errors: [] };
        }

        const container = this.getFieldContainer(field);
        const errorContainer = this.getOrCreateErrorContainer(container);
        this.showLoading(errorContainer);

        try {
            const response = await fetch(yprint_checkout_params.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'yprint_check_email_availability',
                    email: value,
                    nonce: yprint_checkout_params.nonce
                })
            });

            const data = await response.json();
            
            if (data.success) {
                return { isValid: true, errors: [] };
            } else {
                return { 
                    isValid: false, 
                    errors: [data.data.message || 'E-Mail bereits vergeben.'] 
                };
            }
        } catch (error) {
            return { 
                isValid: true, 
                errors: [],
                warnings: ['E-Mail-Verfügbarkeit konnte nicht geprüft werden.']
            };
        }
    }

    async validateVoucherCode(value, field) {
        if (!window.yprint_checkout_params || !value) {
            return { isValid: true, errors: [] };
        }

        const container = this.getFieldContainer(field);
        const errorContainer = this.getOrCreateErrorContainer(container);
        this.showLoading(errorContainer);

        try {
            const response = await fetch(yprint_checkout_params.ajax_url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'yprint_validate_voucher',
                    voucher_code: value,
                    nonce: yprint_checkout_params.nonce
                })
            });

            const data = await response.json();
            
            if (data.success) {
                // Gutschein gültig - Preise aktualisieren
                if (typeof updateCartTotalsDisplay === 'function') {
                    updateCartTotalsDisplay(document.getElementById('checkout-cart-summary-totals'));
                }
                return { isValid: true, errors: [] };
            } else {
                return { 
                    isValid: false, 
                    errors: [data.data.message || this.customMessages.voucher]
                };
            }
        } catch (error) {
            return { 
                isValid: true, 
                errors: [],
                warnings: ['Gutschein konnte nicht überprüft werden.']
            };
        }
    }

    /**
     * Formular-weite Validierung
     */
    async validateForm(form) {
        const fields = form.querySelectorAll('input, select, textarea');
        const validationPromises = [];

        fields.forEach(field => {
            if (this.shouldValidateField(field)) {
                validationPromises.push(this.validateField(field, true));
            }
        });

        const results = await Promise.all(validationPromises);
        const isFormValid = results.every(result => result);

        // Zum ersten Fehlerfeld scrollen
        if (!isFormValid) {
            const firstErrorField = form.querySelector('.border-red-500');
            if (firstErrorField) {
                firstErrorField.scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
                firstErrorField.focus();
            }
        }

        return isFormValid;
    }

    /**
     * Behandelt Toggle der Rechnungsadresse
     */
    handleBillingAddressToggle() {
        const billingFields = document.querySelectorAll('[name^="billing_"]');
        
        billingFields.forEach(field => {
            // Validierungsstatus zurücksetzen
            this.clearFieldError(field);
            this.validationState[field.name] = null;
            
            // Re-validieren falls sichtbar
            if (!document.getElementById('billing-same-as-shipping')?.checked) {
                setTimeout(() => this.validateField(field), 100);
            }
        });
    }

    /**
     * Löscht Fehleranzeige für ein Feld
     */
    clearFieldError(field) {
        field.classList.remove('border-red-500', 'border-green-500', 'border-yellow-500');
        
        const container = this.getFieldContainer(field);
        container.classList.remove('field-error', 'field-success', 'field-warning');
        
        const errorContainer = container.querySelector('.validation-errors');
        if (errorContainer) {
            this.hideErrors(errorContainer);
        }
        
        const successContainer = container.querySelector('.validation-success-container');
        if (successContainer) {
            this.hideSuccess(successContainer);
        }

        // Vorschläge entfernen
        container.querySelectorAll('.field-suggestion').forEach(s => s.remove());
    }

    /**
     * Hilfsmethoden
     */
    getPatternMessage(fieldName) {
        const messageMap = {
            first_name: this.customMessages.name,
            last_name: this.customMessages.name,
            billing_first_name: this.customMessages.name,
            billing_last_name: this.customMessages.name,
            housenumber: this.customMessages.housenumber,
            billing_housenumber: this.customMessages.housenumber,
            zip: this.customMessages.zip,
            billing_zip: this.customMessages.zip,
            phone: this.customMessages.phone,
            email: this.customMessages.email,
            voucher_code: this.customMessages.voucher
        };
        
        return messageMap[fieldName] || this.customMessages.pattern;
    }

    levenshteinDistance(str1, str2) {
        const matrix = [];
        
        for (let i = 0; i <= str2.length; i++) {
            matrix[i] = [i];
        }
        
        for (let j = 0; j <= str1.length; j++) {
            matrix[0][j] = j;
        }
        
        for (let i = 1; i <= str2.length; i++) {
            for (let j = 1; j <= str1.length; j++) {
                if (str2.charAt(i - 1) === str1.charAt(j - 1)) {
                    matrix[i][j] = matrix[i - 1][j - 1];
                } else {
                    matrix[i][j] = Math.min(
                        matrix[i - 1][j - 1] + 1,
                        matrix[i][j - 1] + 1,
                        matrix[i - 1][j] + 1
                    );
                }
            }
        }
        
        return matrix[str2.length][str1.length];
    }

    /**
     * Öffentliche API für externe Nutzung
     */
    isFormValid(formId) {
        const form = document.getElementById(formId);
        if (!form) return false;
        
        const fields = form.querySelectorAll('input, select, textarea');
        return Array.from(fields)
            .filter(field => this.shouldValidateField(field))
            .every(field => this.validationState[field.name]?.isValid !== false);
    }

    getFormErrors(formId) {
        const form = document.getElementById(formId);
        if (!form) return [];
        
        const errors = [];
        const fields = form.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            if (this.shouldValidateField(field)) {
                const state = this.validationState[field.name];
                if (state && !state.isValid) {
                    errors.push({
                        field: field.name,
                        errors: state.errors
                    });
                }
            }
        });
        
        return errors;
    }

    resetForm(formId) {
        const form = document.getElementById(formId);
        if (!form) return;
        
        const fields = form.querySelectorAll('input, select, textarea');
        fields.forEach(field => {
            this.clearFieldError(field);
            this.validationState[field.name] = null;
        });
    }
}

/**
 * Erweiterte UX-Verbesserungen für den Checkout
 */
class YPrintCheckoutUXEnhancer {
    constructor(validator) {
        this.validator = validator;
        this.init();
    }

    init() {
        this.setupFormProgressTracking();
        this.setupSmartAutofill();
        this.setupKeyboardNavigation();
        this.setupMobileOptimizations();
        this.setupAccessibilityFeatures();
    }

    /**
     * Verfolgt Fortschritt beim Ausfüllen der Formulare
     */
    setupFormProgressTracking() {
        const forms = ['address-form', 'payment-form'];
        
        forms.forEach(formId => {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const progressBar = this.createProgressBar(form);
            this.updateProgress(form, progressBar);
            
            // Live-Update des Fortschritts
            form.addEventListener('input', () => {
                this.updateProgress(form, progressBar);
            });
        });
    }

    createProgressBar(form) {
        const progressContainer = document.createElement('div');
        progressContainer.className = 'form-progress-container';
        progressContainer.innerHTML = `
            <div class="form-progress-bar">
                <div class="form-progress-fill" style="width: 0%"></div>
            </div>
            <span class="form-progress-text">0% ausgefüllt</span>
        `;
        
        // CSS für Progress Bar
        const style = document.createElement('style');
        style.textContent = `
            .form-progress-container {
                margin-bottom: 1rem;
                padding: 0.75rem;
                background: #f8fafc;
                border-radius: 0.5rem;
                border: 1px solid #e2e8f0;
            }

            .form-progress-bar {
                width: 100%;
                height: 0.5rem;
                background: #e2e8f0;
                border-radius: 0.25rem;
                overflow: hidden;
                margin-bottom: 0.5rem;
            }

            .form-progress-fill {
                height: 100%;
                background: linear-gradient(90deg, #0079FF, #00a8ff);
                transition: width 0.3s ease;
                border-radius: 0.25rem;
            }

            .form-progress-text {
                font-size: 0.875rem;
                color: #64748b;
                font-weight: 500;
            }
        `;
        
        if (!document.getElementById('form-progress-styles')) {
            style.id = 'form-progress-styles';
            document.head.appendChild(style);
        }
        
        form.parentNode.insertBefore(progressContainer, form);
        return progressContainer;
    }

    updateProgress(form, progressContainer) {
        const fields = form.querySelectorAll('input[required], select[required], textarea[required]');
        const filledFields = Array.from(fields).filter(field => {
            if (field.type === 'checkbox' || field.type === 'radio') {
                return field.checked;
            }
            return field.value.trim() !== '';
        });

        const percentage = fields.length > 0 ? Math.round((filledFields.length / fields.length) * 100) : 0;
        
        const progressFill = progressContainer.querySelector('.form-progress-fill');
        const progressText = progressContainer.querySelector('.form-progress-text');
        
        progressFill.style.width = `${percentage}%`;
        progressText.textContent = `${percentage}% ausgefüllt`;
        
        // Farbe ändern basierend auf Fortschritt
        if (percentage === 100) {
            progressFill.style.background = 'linear-gradient(90deg, #16a34a, #22c55e)';
        } else if (percentage >= 75) {
            progressFill.style.background = 'linear-gradient(90deg, #0079FF, #00a8ff)';
        } else {
            progressFill.style.background = 'linear-gradient(90deg, #6b7280, #9ca3af)';
        }
    }

    /**
     * Intelligente Autovervollständigung
     */
    setupSmartAutofill() {
        this.setupAddressAutofill();
        this.setupNameFormatting();
        this.setupPostalCodeCityLookup();
    }

    setupAddressAutofill() {
        const streetField = document.getElementById('street');
        const housenumberField = document.getElementById('housenumber');
        
        if (streetField && housenumberField) {
            streetField.addEventListener('blur', () => {
                this.extractHousenumberFromStreet(streetField, housenumberField);
            });
        }
    }

    extractHousenumberFromStreet(streetField, housenumberField) {
        const value = streetField.value.trim();
        const match = value.match(/^(.+?)\s+(\d+[a-zA-Z]?)$/);
        
        if (match && !housenumberField.value) {
            const [, street, housenumber] = match;
            streetField.value = street.trim();
            housenumberField.value = housenumber;
            
            // Trigger validation
            this.validator.validateField(streetField);
            this.validator.validateField(housenumberField);
        }
    }

    setupNameFormatting() {
        const nameFields = ['first_name', 'last_name', 'billing_first_name', 'billing_last_name'];
        
        nameFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.addEventListener('blur', () => {
                    this.formatName(field);
                });
            }
        });
    }

    formatName(field) {
        const value = field.value.trim();
        if (value) {
            // Ersten Buchstaben jedes Wortes großschreiben
            const formatted = value.split(' ')
                .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                .join(' ');
            
            if (formatted !== value) {
                field.value = formatted;
                this.validator.validateField(field);
            }
        }
    }

    setupPostalCodeCityLookup() {
        const zipField = document.getElementById('zip');
        const cityField = document.getElementById('city');
        
        if (zipField && cityField) {
            zipField.addEventListener('blur', () => {
                this.lookupCityFromPostalCode(zipField, cityField);
            });
        }
        
        // Für Rechnungsadresse
        const billingZipField = document.getElementById('billing_zip');
        const billingCityField = document.getElementById('billing_city');
        
        if (billingZipField && billingCityField) {
            billingZipField.addEventListener('blur', () => {
                this.lookupCityFromPostalCode(billingZipField, billingCityField);
            });
        }
    }

    async lookupCityFromPostalCode(zipField, cityField) {
        const zip = zipField.value.trim();
        
        if (zip.length === 5 && /^\d{5}$/.test(zip) && !cityField.value) {
            // Einfache PLZ-zu-Stadt Zuordnung (kann erweitert werden)
            const cityMap = {
                '10115': 'Berlin',
                '20095': 'Hamburg',
                '80331': 'München',
                '50667': 'Köln',
                '60311': 'Frankfurt am Main',
                '70173': 'Stuttgart',
                '40213': 'Düsseldorf',
                '44135': 'Dortmund',
                '45127': 'Essen',
                '28195': 'Bremen'
            };
            
            if (cityMap[zip]) {
                cityField.value = cityMap[zip];
                this.validator.validateField(cityField);
                
                // Visuelles Feedback
                this.showAutofillNotification(cityField, `Stadt automatisch ergänzt: ${cityMap[zip]}`);
            }
        }
    }

    showAutofillNotification(field, message) {
        const notification = document.createElement('div');
        notification.className = 'autofill-notification';
        notification.textContent = message;
        notification.style.cssText = `
            position: absolute;
            top: -2rem;
            left: 0;
            background: #0079FF;
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            z-index: 1000;
            animation: fadeInOut 3s ease-in-out;
        `;
        
        const container = this.validator.getFieldContainer(field);
        container.style.position = 'relative';
        container.appendChild(notification);
        
        setTimeout(() => {
            notification.remove();
        }, 3000);
        
        // CSS Animation
        if (!document.getElementById('autofill-animations')) {
            const style = document.createElement('style');
            style.id = 'autofill-animations';
            style.textContent = `
                @keyframes fadeInOut {
                    0% { opacity: 0; transform: translateY(10px); }
                    20%, 80% { opacity: 1; transform: translateY(0); }
                    100% { opacity: 0; transform: translateY(-10px); }
                }
            `;
            document.head.appendChild(style);
        }
    }

    /**
     * Keyboard Navigation Verbesserungen
     */
    setupKeyboardNavigation() {
        document.addEventListener('keydown', (e) => {
            // Tab-Navigation optimieren
            if (e.key === 'Tab') {
                this.handleTabNavigation(e);
            }
            
            // Enter-Taste für Form-Progression
            if (e.key === 'Enter') {
                this.handleEnterKey(e);
            }
            
            // Escape für Modal/Dropdown schließen
            if (e.key === 'Escape') {
                this.handleEscapeKey(e);
            }
        });
    }

    handleTabNavigation(e) {
        const activeElement = document.activeElement;
        
        // Skip versteckte Felder
        if (activeElement.offsetParent === null) {
            e.preventDefault();
            this.focusNextVisibleField(activeElement, !e.shiftKey);
        }
    }

    handleEnterKey(e) {
        const activeElement = document.activeElement;
        
        // Bei letztem Feld im Formular: Weiter zum nächsten Schritt
        if (this.isLastFieldInForm(activeElement)) {
            e.preventDefault();
            this.triggerNextStep(activeElement);
        }
    }

    handleEscapeKey(e) {
        // Aktive Modals schließen
        const activeModals = document.querySelectorAll('.modal.active, .dropdown.open');
        if (activeModals.length > 0) {
            e.preventDefault();
            activeModals.forEach(modal => modal.classList.remove('active', 'open'));
        }
    }

    focusNextVisibleField(currentField, forward = true) {
        const form = currentField.closest('form');
        if (!form) return;
        
        const fields = Array.from(form.querySelectorAll('input, select, textarea, button'))
            .filter(field => field.offsetParent !== null && !field.disabled);
        
        const currentIndex = fields.indexOf(currentField);
        const nextIndex = forward ? currentIndex + 1 : currentIndex - 1;
        
        if (nextIndex >= 0 && nextIndex < fields.length) {
            fields[nextIndex].focus();
        }
    }

    isLastFieldInForm(field) {
        const form = field.closest('form');
        if (!form) return false;
        
        const visibleFields = Array.from(form.querySelectorAll('input, select, textarea'))
            .filter(f => f.offsetParent !== null && !f.disabled);
        
        return visibleFields.indexOf(field) === visibleFields.length - 1;
    }

    triggerNextStep(field) {
        const form = field.closest('form');
        if (!form) return;
        
        if (form.id === 'address-form') {
            const nextButton = document.getElementById('btn-to-payment');
            if (nextButton && !nextButton.disabled) {
                nextButton.click();
            }
        } else if (form.id === 'payment-form') {
            const nextButton = document.getElementById('btn-to-confirmation');
            if (nextButton && !nextButton.disabled) {
                nextButton.click();
            }
        }
    }

    /**
     * Mobile-spezifische Optimierungen
     */
    setupMobileOptimizations() {
        if (this.isMobileDevice()) {
            this.optimizeInputTypes();
            this.setupMobileValidationBehavior();
            this.optimizeTouchTargets();
        }
    }

    isMobileDevice() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent) ||
               window.innerWidth <= 768;
    }

    optimizeInputTypes() {
        // Bessere Eingabetypen für Mobile
        const zipFields = document.querySelectorAll('#zip, #billing_zip');
        zipFields.forEach(field => {
            field.setAttribute('inputmode', 'numeric');
            field.setAttribute('pattern', '[0-9]*');
        });
        
        const phoneField = document.getElementById('phone');
        if (phoneField) {
            phoneField.setAttribute('inputmode', 'tel');
        }
        
        const emailField = document.getElementById('email');
        if (emailField) {
            emailField.setAttribute('inputmode', 'email');
        }
    }

    setupMobileValidationBehavior() {
        // Auf Mobile: Validierung erst bei Blur, nicht bei Input
        const fields = document.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            if (this.validator.shouldValidateField(field)) {
                // Entferne Input-Listener auf Mobile
                field.removeEventListener('input', this.validator.debounceValidation);
                
                // Nur Blur-Validierung
                field.addEventListener('blur', () => {
                    this.validator.validateField(field, true);
                });
            }
        });
    }

    optimizeTouchTargets() {
        // Mindestgröße für Touch-Targets sicherstellen
        const style = document.createElement('style');
        style.textContent = `
            @media (max-width: 768px) {
                .form-input, .form-select, .btn {
                    min-height: 44px;
                    font-size: 16px; /* Verhindert Zoom auf iOS */
                }
                
                .checkbox-container, .radio-container {
                    min-height: 44px;
                    display: flex;
                    align-items: center;
                }
                
                .address-card {
                    min-height: 60px;
                    padding: 12px;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Accessibility Features
     */
    setupAccessibilityFeatures() {
        this.enhanceFormLabels();
        this.setupAriaLive();
        this.improveErrorAnnouncements();
        this.setupSkipLinks();
    }

    enhanceFormLabels() {
        const fields = document.querySelectorAll('input, select, textarea');
        
        fields.forEach(field => {
            if (!field.id) return;
            
            const label = document.querySelector(`label[for="${field.id}"]`);
            if (label) {
                // Pflichtfeld-Indikator
                if (field.required && !label.querySelector('.required-indicator')) {
                    const indicator = document.createElement('span');
                    indicator.className = 'required-indicator';
                    indicator.textContent = ' *';
                    indicator.setAttribute('aria-label', 'Pflichtfeld');
                    label.appendChild(indicator);
                }
                
                // Beschreibende Texte verknüpfen
                const description = field.nextElementSibling;
                if (description && description.classList.contains('field-description')) {
                    const descId = `${field.id}-description`;
                    description.id = descId;
                    field.setAttribute('aria-describedby', descId);
                }
            }
        });
    }

    setupAriaLive() {
        // Live-Region für Validierungsmeldungen
        const liveRegion = document.createElement('div');
        liveRegion.id = 'validation-live-region';
        liveRegion.setAttribute('aria-live', 'polite');
        liveRegion.setAttribute('aria-atomic', 'true');
        liveRegion.style.cssText = `
            position: absolute;
            left: -10000px;
            width: 1px;
            height: 1px;
            overflow: hidden;
        `;
        document.body.appendChild(liveRegion);
        
        this.liveRegion = liveRegion;
    }

    improveErrorAnnouncements() {
        // Überschreibe die Original-Fehlermeldung-Funktion
        const originalShowErrors = this.validator.showErrors.bind(this.validator);
        
        this.validator.showErrors = (container, errors) => {
            originalShowErrors(container, errors);
            
            // Screen Reader Ankündigung
            if (errors.length > 0 && this.liveRegion) {
                this.liveRegion.textContent = `Eingabefehler: ${errors[0]}`;
            }
        };
    }

    setupSkipLinks() {
        // Skip-Link für Tastaturnavigation
        const skipLink = document.createElement('a');
        skipLink.href = '#checkout-form';
        skipLink.textContent = 'Zum Hauptformular springen';
        skipLink.className = 'skip-link';
        skipLink.style.cssText = `
            position: absolute;
            left: -10000px;
            top: auto;
            width: 1px;
            height: 1px;
            overflow: hidden;
            background: #0079FF;
            color: white;
            padding: 8px 16px;
            text-decoration: none;
            border-radius: 4px;
            z-index: 9999;
        `;
        
        skipLink.addEventListener('focus', () => {
            skipLink.style.cssText = `
                position: fixed;
                top: 10px;
                left: 10px;
                width: auto;
                height: auto;
                overflow: visible;
                background: #0079FF;
                color: white;
                padding: 8px 16px;
                text-decoration: none;
                border-radius: 4px;
                z-index: 9999;
            `;
        });
        
        skipLink.addEventListener('blur', () => {
            skipLink.style.left = '-10000px';
        });
        
        document.body.insertBefore(skipLink, document.body.firstChild);
    }
}

/**
 * Initialisierung und Integration
 */
jQuery(document).ready(function($) {
    console.log('YPrint Checkout Validation System initializing...');
    
    // Warte auf die Verfügbarkeit der Grundfunktionen
    function initializeValidationSystem() {
        if (typeof window.yprint_checkout_params === 'undefined') {
            console.warn('yprint_checkout_params not available, retrying...');
            setTimeout(initializeValidationSystem, 100);
            return;
        }
        
        try {
            // Validation System initialisieren
            window.YPrintValidator = new YPrintCheckoutValidator();
            
            // UX Enhancer initialisieren
            window.YPrintUXEnhancer = new YPrintCheckoutUXEnhancer(window.YPrintValidator);
            
            // Integration mit bestehendem Checkout-System
            integateWithExistingCheckout();
            
            console.log('YPrint Checkout Validation System successfully initialized');
        } catch (error) {
            console.error('Error initializing validation system:', error);
        }
    }
    
    function integateWithExistingCheckout() {
        // Integration mit dem bestehenden validateAddressForm
        if (typeof window.validateAddressForm === 'function') {
            const originalValidateAddressForm = window.validateAddressForm;
            
            window.validateAddressForm = function() {
                // Neue Validierung verwenden
                return window.YPrintValidator.validateForm(document.getElementById('address-form'));
            };
        }
        
        // Integration mit Schritt-Navigation
        if (typeof window.showStep === 'function') {
            const originalShowStep = window.showStep;
            
            window.showStep = function(stepNumber) {
                originalShowStep(stepNumber);
                
                // Validierung für neuen Schritt aktivieren
                setTimeout(() => {
                    const activeForm = document.querySelector('.checkout-step.active form');
                    if (activeForm) {
                        // Initiale Validierung falls Felder bereits ausgefüllt
                        const fields = activeForm.querySelectorAll('input, select, textarea');
                        fields.forEach(field => {
                            if (field.value && window.YPrintValidator.shouldValidateField(field)) {
                                window.YPrintValidator.validateField(field, false);
                            }
                        });
                    }
                }, 100);
            };
        }
        
        // Button-Status Management verbessern
        enhanceButtonManagement();
    }
    
    function enhanceButtonManagement() {
        // Erweiterte Button-Aktivierung basierend auf Validierung
        function updateButtonStates() {
            const addressFormValid = window.YPrintValidator.isFormValid('address-form');
            const paymentFormValid = window.YPrintValidator.isFormValid('payment-form');
            
            const btnToPayment = document.getElementById('btn-to-payment');
            const btnToConfirmation = document.getElementById('btn-to-confirmation');
            const btnBuyNow = document.getElementById('btn-buy-now');
            
            if (btnToPayment) {
                btnToPayment.disabled = !addressFormValid;
                btnToPayment.classList.toggle('opacity-50', !addressFormValid);
            }
            
            if (btnToConfirmation) {
                btnToConfirmation.disabled = !paymentFormValid;
                btnToConfirmation.classList.toggle('opacity-50', !paymentFormValid);
            }
            
            if (btnBuyNow) {
                const allFormsValid = addressFormValid && paymentFormValid;
                btnBuyNow.disabled = !allFormsValid;
                btnBuyNow.classList.toggle('opacity-50', !allFormsValid);
            }
        }
        
        // Button-Status periodisch aktualisieren
        setInterval(updateButtonStates, 500);
        
        // Sofortige Aktualisierung bei Änderungen
        document.addEventListener('input', updateButtonStates);
        document.addEventListener('change', updateButtonStates);
    }
    
    // System initialisieren
    initializeValidationSystem();
});

/**
 * Export für externe Nutzung
 */
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        YPrintCheckoutValidator,
        YPrintCheckoutUXEnhancer
    };
}