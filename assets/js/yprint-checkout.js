// YPrint Checkout JavaScript - Allgemeine Funktionen
// Stellt sicher, dass das DOM vollständig geladen ist, bevor das Skript ausgeführt wird.
document.addEventListener('DOMContentLoaded', function () {
    // Globale Variablen und Zustand
    let currentStep = 1; // Aktueller Schritt im Checkout-Prozess
    const formData = { // Objekt zum Speichern der Formulardaten
        shipping: {},
        billing: {},
        payment: { method: 'paypal' }, // Standard-Zahlungsmethode
        voucher: null,
        isBillingSameAsShipping: true,
    };

    // Dummy Produktdaten für den Warenkorb (sollten serverseitig geladen werden)
    const cartItems = [
        { id: 1, name: "Individuelles Fotobuch Premium", price: 49.99, quantity: 1, image: "https://placehold.co/100x100/0079FF/FFFFFF?text=Buch" },
        { id: 2, name: "Visitenkarten (250 Stk.)", price: 19.50, quantity: 2, image: "https://placehold.co/100x100/E3F2FD/1d1d1f?text=Karten" },
        { id: 3, name: "Großformat Poster A2", price: 25.00, quantity: 1, image: "https://placehold.co/100x100/CCCCCC/FFFFFF?text=Poster" },
    ];

    // DOM-Elemente auswählen
    const steps = document.querySelectorAll(".checkout-step");
    const progressSteps = document.querySelectorAll(".progress-step");
    const btnToPayment = document.getElementById('btn-to-payment');
    const btnToConfirmation = document.getElementById('btn-to-confirmation');
    const btnBackToAddress = document.getElementById('btn-back-to-address'); // In Step 2
    const btnBackToPaymentFromConfirm = document.getElementById('btn-back-to-payment-from-confirm'); // In Step 3
    const btnBuyNow = document.getElementById('btn-buy-now');
    const btnContinueShopping = document.getElementById('btn-continue-shopping');
    const loadingOverlay = document.getElementById('loading-overlay');
    const billingSameAsShippingCheckbox = document.getElementById('billing-same-as-shipping');
    const billingAddressFieldsContainer = document.getElementById('billing-address-fields'); // Container der abweichenden Rechnungsadresse
    const addressForm = document.getElementById('address-form');

    // Erforderliche Felder für die Adressvalidierung
    const requiredAddressFields = ['street', 'housenumber', 'zip', 'city', 'country'];
    const requiredBillingFields = ['billing_street', 'billing_housenumber', 'billing_zip', 'billing_city', 'billing_country'];


    /**
     * Validiert das Adressformular.
     * @returns {boolean} - True, wenn das Formular gültig ist, sonst false.
     */
    function validateAddressForm() {
        if (!addressForm) return true; // Wenn kein Adressformular auf der Seite ist, überspringen.
        let isValid = true;

        requiredAddressFields.forEach(fieldId => {
            const field = document.getElementById(fieldId);
            if (field && !field.value.trim()) {
                isValid = false;
                field.classList.add('border-yprint-error');
            } else if (field) {
                field.classList.remove('border-yprint-error');
            }
        });

        if (!formData.isBillingSameAsShipping && billingAddressFieldsContainer) {
            requiredBillingFields.forEach(fieldId => {
                const field = document.getElementById(fieldId);
                if (field && !field.value.trim()) {
                    isValid = false;
                    field.classList.add('border-yprint-error');
                } else if (field) {
                    field.classList.remove('border-yprint-error');
                }
            });
        }
        if (btnToPayment) {
            btnToPayment.disabled = !isValid;
        }
        return isValid;
    }

    // Event Listener für das Adressformular zur Live-Validierung
    if (addressForm) {
        addressForm.addEventListener('input', validateAddressForm);
    }

    // Event Listener für die Checkbox "Rechnungsadresse ist identisch"
    if (billingSameAsShippingCheckbox && billingAddressFieldsContainer) {
        billingSameAsShippingCheckbox.addEventListener('change', () => {
            formData.isBillingSameAsShipping = billingSameAsShippingCheckbox.checked;
            billingAddressFieldsContainer.classList.toggle('hidden', formData.isBillingSameAsShipping);
            const billingInputs = billingAddressFieldsContainer.querySelectorAll('input, select');
            billingInputs.forEach(input => input.required = !formData.isBillingSameAsShipping);
            validateAddressForm(); // Nach Änderung neu validieren
        });
        // Initialer Zustand der Rechnungsadressfelder
        billingAddressFieldsContainer.classList.toggle('hidden', billingSameAsShippingCheckbox.checked);
        const initialBillingInputs = billingAddressFieldsContainer.querySelectorAll('input, select');
        initialBillingInputs.forEach(input => input.required = !billingSameAsShippingCheckbox.checked);

    }


    /**
     * Zeigt den angegebenen Checkout-Schritt an und aktualisiert die Fortschrittsanzeige.
     * @param {number} stepNumber - Die Nummer des anzuzeigenden Schritts.
     */
    function showStep(stepNumber) {
        steps.forEach((stepEl, index) => {
            stepEl.classList.toggle('active', index + 1 === stepNumber);
        });
        progressSteps.forEach((pStep, index) => {
            pStep.classList.remove('active', 'completed');
            if (index < stepNumber - 1) {
                pStep.classList.add('completed');
            } else if (index === stepNumber - 1) {
                pStep.classList.add('active');
            }
        });
        currentStep = stepNumber;
        window.scrollTo({ top: 0, behavior: 'smooth' }); // Sanft nach oben scrollen
    }

    /**
     * Sammelt die Adressdaten aus dem Formular.
     */
    function collectAddressData() {
        if (!addressForm) return; // Überspringen, wenn kein Adressformular vorhanden

        formData.shipping.street = document.getElementById('street')?.value || '';
        formData.shipping.housenumber = document.getElementById('housenumber')?.value || '';
        formData.shipping.zip = document.getElementById('zip')?.value || '';
        formData.shipping.city = document.getElementById('city')?.value || '';
        formData.shipping.country = document.getElementById('country')?.value || '';
        formData.shipping.phone = document.getElementById('phone')?.value || '';

        if (formData.isBillingSameAsShipping) {
            formData.billing = { ...formData.shipping }; // Kopiert die Lieferadresse
        } else {
            formData.billing.street = document.getElementById('billing_street')?.value || '';
            formData.billing.housenumber = document.getElementById('billing_housenumber')?.value || '';
            formData.billing.zip = document.getElementById('billing_zip')?.value || '';
            formData.billing.city = document.getElementById('billing_city')?.value || '';
            formData.billing.country = document.getElementById('billing_country')?.value || '';
        }
        // Hier könnte ein AJAX Call an 'wp_ajax_yprint_save_address' erfolgen
        console.log("Adressdaten gesammelt:", formData);
    }

    /**
     * Sammelt die Zahlungsdaten.
     */
    function collectPaymentData() {
        const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked');
        if (selectedPaymentMethod) {
            formData.payment.method = selectedPaymentMethod.value;
        }
        const voucherInput = document.getElementById('voucher');
        if (voucherInput) {
            formData.voucher = voucherInput.value;
        }
        // Hier könnte ein AJAX Call an 'wp_ajax_yprint_set_payment_method' erfolgen
        console.log("Zahlungsdaten gesammelt:", formData);
    }

    /**
     * Berechnet die Preise (Zwischensumme, Versand, Rabatt, Gesamt).
     * @returns {object} - Ein Objekt mit den berechneten Preisen.
     */
    function calculatePrices() {
        let subtotal = cartItems.reduce((sum, item) => sum + item.price * item.quantity, 0);
        let shipping = 4.99; // Feste Versandkosten (Beispiel)
        let discount = 0;
        const voucherFeedbackEl = document.getElementById('voucher-feedback');

        if (formData.voucher && formData.voucher.toUpperCase() === "YPRINT10") { // Dummy Gutschein Logik
            discount = subtotal * 0.10; // 10% Rabatt
            if (voucherFeedbackEl) {
                voucherFeedbackEl.textContent = `"YPRINT10" angewendet: -€${discount.toFixed(2)}`;
                voucherFeedbackEl.className = 'text-sm mt-1 text-yprint-success';
            }
        } else if (formData.voucher && voucherFeedbackEl) {
            voucherFeedbackEl.textContent = 'Ungültiger Gutscheincode.';
            voucherFeedbackEl.className = 'text-sm mt-1 text-yprint-error';
        } else if (voucherFeedbackEl) {
            voucherFeedbackEl.textContent = '';
        }

        let total = subtotal + shipping - discount;
        let vat = total * 0.19; // Annahme 19% MwSt auf den Gesamtbetrag nach Rabatt

        return { subtotal, shipping, discount, total, vat };
    }

    /**
     * Aktualisiert die Preisanzeige im Zahlungsschritt.
     */
    function updatePaymentStepSummary() {
        const prices = calculatePrices();
        const subtotalPriceEl = document.getElementById('subtotal-price');
        const shippingPriceEl = document.getElementById('shipping-price');
        const totalPricePaymentEl = document.getElementById('total-price-payment');

        if (subtotalPriceEl) subtotalPriceEl.textContent = `€${prices.subtotal.toFixed(2)}`;
        if (shippingPriceEl) shippingPriceEl.textContent = `€${prices.shipping.toFixed(2)}`;
        if (totalPricePaymentEl) totalPricePaymentEl.textContent = `€${prices.total.toFixed(2)}`;
    }

    // Event Listener für Gutscheinfeld und Button, um Preise live zu aktualisieren
    const voucherInput = document.getElementById('voucher');
    const voucherButton = document.querySelector('#voucher + button'); // Annahme: Button ist direkt daneben

    if (voucherInput) {
        voucherInput.addEventListener('input', () => {
            formData.voucher = voucherInput.value;
            updatePaymentStepSummary();
            // Optional: updateConfirmationSummary(), falls direkt auf Bestätigungsseite sichtbar
        });
    }
    if (voucherButton) {
        voucherButton.addEventListener('click', () => {
            if(voucherInput) formData.voucher = voucherInput.value;
            updatePaymentStepSummary();
            // Optional: updateConfirmationSummary()
        });
    }


    /**
     * Füllt die Bestätigungsseite mit den gesammelten Daten.
     */
    function populateConfirmation() {
        // Adressen
        const confirmShippingAddressEl = document.getElementById('confirm-shipping-address');
        if (confirmShippingAddressEl) {
            confirmShippingAddressEl.innerHTML = `
                ${formData.shipping.street || ''} ${formData.shipping.housenumber || ''}<br>
                ${formData.shipping.zip || ''} ${formData.shipping.city || ''}<br>
                ${formData.shipping.country || ''}
                ${formData.shipping.phone ? '<br>Tel: ' + formData.shipping.phone : ''}
            `;
        }

        const confirmBillingContainer = document.getElementById('confirm-billing-address-container');
        const confirmBillingAddressEl = document.getElementById('confirm-billing-address');
        if (confirmBillingContainer && confirmBillingAddressEl) {
            if (!formData.isBillingSameAsShipping && Object.keys(formData.billing).length > 0) {
                confirmBillingAddressEl.innerHTML = `
                    ${formData.billing.street || ''} ${formData.billing.housenumber || ''}<br>
                    ${formData.billing.zip || ''} ${formData.billing.city || ''}<br>
                    ${formData.billing.country || ''}
                `;
                confirmBillingContainer.classList.remove('hidden');
            } else {
                confirmBillingContainer.classList.add('hidden');
            }
        }

        // Zahlungsart
        const confirmPaymentMethodEl = document.getElementById('confirm-payment-method');
        if (confirmPaymentMethodEl) {
            let paymentMethodText = 'Nicht gewählt';
            switch (formData.payment.method) {
                case 'paypal': paymentMethodText = '<i class="fab fa-paypal mr-2"></i> PayPal'; break;
                case 'applepay': paymentMethodText = '<i class="fab fa-apple-pay mr-2"></i> Apple Pay'; break;
                case 'creditcard': paymentMethodText = '<i class="fas fa-credit-card mr-2"></i> Kreditkarte'; break;
                case 'klarna': paymentMethodText = '<svg viewBox="0 0 496 156" class="klarna-logo-svg inline" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M248.291 31.0084C265.803 31.0084 280.21 37.1458 291.513 49.4206C302.888 61.6954 308.575 77.0417 308.575 95.4594C308.575 113.877 302.888 129.223 291.513 141.498C280.21 153.773 265.803 159.91 248.291 159.91H180.854V31.0084H248.291ZM213.956 132.621H248.291C258.57 132.621 267.076 129.68 273.808 123.798C280.612 117.844 284.014 109.177 284.014 97.7965C284.014 86.4158 280.612 77.7491 273.808 71.7947C267.076 65.8403 258.57 62.8992 248.291 62.8992H213.956V132.621ZM143.061 31.0084H109.959V159.91H143.061V31.0084ZM495.99 31.0084L445.609 159.91H408.009L378.571 79.1557L349.132 159.91H311.532L361.914 31.0084H399.514L428.952 112.661L458.39 31.0084H495.99ZM0 31.0084H33.1017V159.91H0V31.0084Z" fill="#FFB3C7"></path></svg> Klarna'; break;
            }
            confirmPaymentMethodEl.innerHTML = paymentMethodText;
        }

        // Produkte
        const productListEl = document.getElementById('confirm-product-list');
        if (productListEl) {
            productListEl.innerHTML = ''; // Vorherige Einträge löschen
            cartItems.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'flex justify-between items-center py-2'; // Tailwind Klassen
                itemEl.innerHTML = `
                    <div class="flex items-center">
                        <img src="${item.image}" alt="${item.name}" class="product-item"> <div>
                            <p class="font-medium">${item.name}</p>
                            <p class="text-sm text-yprint-text-secondary">Menge: ${item.quantity}</p>
                        </div>
                    </div>
                    <p class="font-medium">€${(item.price * item.quantity).toFixed(2)}</p>
                `;
                productListEl.appendChild(itemEl);
            });
        }

        // Preise
        const prices = calculatePrices();
        const confirmSubtotalEl = document.getElementById('confirm-subtotal');
        const confirmShippingEl = document.getElementById('confirm-shipping');
        const confirmDiscountRowEl = document.getElementById('confirm-discount-row');
        const confirmDiscountEl = document.getElementById('confirm-discount');
        const confirmVatEl = document.getElementById('confirm-vat');
        const confirmTotalEl = document.getElementById('confirm-total');

        if (confirmSubtotalEl) confirmSubtotalEl.textContent = `€${prices.subtotal.toFixed(2)}`;
        if (confirmShippingEl) confirmShippingEl.textContent = `€${prices.shipping.toFixed(2)}`;
        if (confirmDiscountRowEl && confirmDiscountEl) {
            if (prices.discount > 0) {
                confirmDiscountEl.textContent = `-€${prices.discount.toFixed(2)}`;
                confirmDiscountRowEl.classList.remove('hidden');
            } else {
                confirmDiscountRowEl.classList.add('hidden');
            }
        }
        if (confirmVatEl) confirmVatEl.textContent = `€${prices.vat.toFixed(2)}`;
        if (confirmTotalEl) confirmTotalEl.textContent = `€${prices.total.toFixed(2)}`;
    }

    /**
     * Füllt die Danke-Seite mit Bestelldetails.
     */
    function populateThankYouPage() {
        const orderNumberEl = document.getElementById('order-number');
        if (orderNumberEl) {
            orderNumberEl.textContent = `YP-${Math.floor(Math.random() * 900000000) + 100000000}`; // Zufällige Bestellnummer
        }

        const summaryEl = document.getElementById('thankyou-product-summary');
        if (summaryEl) {
            summaryEl.innerHTML = '<h3 class="font-semibold mb-2 text-lg">Ihre Bestellung:</h3>'; // Tailwind: text-lg
            cartItems.forEach(item => {
                const itemEl = document.createElement('div');
                itemEl.className = 'flex justify-between items-center py-1 text-sm'; // Tailwind Klassen
                itemEl.innerHTML = `
                    <span>${item.name} (x${item.quantity})</span>
                    <span>€${(item.price * item.quantity).toFixed(2)}</span>
                `;
                summaryEl.appendChild(itemEl);
            });
            const prices = calculatePrices();
            const totalEl = document.createElement('div');
            totalEl.className = 'flex justify-between items-center py-1 text-sm font-bold mt-2 border-t border-yprint-medium-gray pt-2'; // Tailwind Klassen
            totalEl.innerHTML = `
                <span>Gesamt:</span>
                <span>€${prices.total.toFixed(2)}</span>
            `;
            summaryEl.appendChild(totalEl);
        }
    }

    /**
     * Zeigt oder versteckt das Lade-Overlay.
     * @param {boolean} show - True zum Anzeigen, false zum Verstecken.
     */
    function toggleLoadingOverlay(show) {
        if (loadingOverlay) {
            loadingOverlay.classList.toggle('visible', show);
        }
    }

    // Event Listeners für Navigationsbuttons
    if (btnToPayment) {
        btnToPayment.addEventListener('click', (e) => {
            e.preventDefault(); // Verhindert Standard-Formular-Submit
            if (validateAddressForm()) {
                collectAddressData();
                updatePaymentStepSummary();
                showStep(2);
                 // In einer echten Anwendung würde hier ein AJAX Call erfolgen.
                 // z.B. YPrintAJAX.saveAddress(formData.shipping, formData.billing);
            } else {
                // Optional: Fokussiere das erste invalide Feld oder zeige eine generelle Nachricht
                const firstError = addressForm.querySelector('.border-yprint-error');
                if (firstError) firstError.focus();
            }
        });
    }

    if (btnBackToAddress) {
        btnBackToAddress.addEventListener('click', (e) => {
            e.preventDefault();
            showStep(1);
        });
    }

    if (btnToConfirmation) {
        btnToConfirmation.addEventListener('click', (e) => {
            e.preventDefault();
            collectPaymentData();
            populateConfirmation();
            showStep(3);
            // z.B. YPrintAJAX.setPaymentMethod(formData.payment);
        });
    }

    if (btnBackToPaymentFromConfirm) {
        btnBackToPaymentFromConfirm.addEventListener('click', (e) => {
            e.preventDefault();
            updatePaymentStepSummary();
            showStep(2);
        });
    }

    if (btnBuyNow) {
        btnBuyNow.addEventListener('click', (e) => {
            e.preventDefault();
            toggleLoadingOverlay(true);
            // Simuliere Bestellverarbeitung (AJAX Call an 'wp_ajax_yprint_process_checkout')
            // YPrintAJAX.processCheckout(formData).then(response => { ... });
            setTimeout(() => {
                toggleLoadingOverlay(false);
                populateThankYouPage();
                showStep(4);
                // Fortschrittsanzeige für Danke-Seite anpassen
                progressSteps.forEach(pStep => pStep.classList.add('completed'));
                const lastProgressStep = document.getElementById('progress-step-3');
                if (lastProgressStep) {
                    lastProgressStep.classList.remove('active'); // Letzten Schritt nicht mehr aktiv
                    lastProgressStep.classList.add('completed'); // Auch als completed markieren
                }
            }, 2500); // 2.5 Sekunden Ladezeit-Simulation
        });
    }

    if (btnContinueShopping) {
        btnContinueShopping.addEventListener('click', () => {
            // Hier Weiterleitung zur Startseite oder Kategorieseite
            // window.location.href = "/"; // Beispiel für Weiterleitung
            alert("Weiterleitung zum Shop...");
        });
    }
    
    // Klarna Logo SVG Path Korrektur (falls im HTML gekürzt)
    // Stellt sicher, dass das Klarna-Logo korrekt angezeigt wird.
    document.querySelectorAll('svg path[d^="M照明省略"]').forEach(path => {
        path.setAttribute('d', "M248.291 31.0084C265.803 31.0084 280.21 37.1458 291.513 49.4206C302.888 61.6954 308.575 77.0417 308.575 95.4594C308.575 113.877 302.888 129.223 291.513 141.498C280.21 153.773 265.803 159.91 248.291 159.91H180.854V31.0084H248.291ZM213.956 132.621H248.291C258.57 132.621 267.076 129.68 273.808 123.798C280.612 117.844 284.014 109.177 284.014 97.7965C284.014 86.4158 280.612 77.7491 273.808 71.7947C267.076 65.8403 258.57 62.8992 248.291 62.8992H213.956V132.621ZM143.061 31.0084H109.959V159.91H143.061V31.0084ZM495.99 31.0084L445.609 159.91H408.009L378.571 79.1557L349.132 159.91H311.532L361.914 31.0084H399.514L428.952 112.661L458.39 31.0084H495.99ZM0 31.0084H33.1017V159.91H0V31.0084Z");
        // Die Fill-Farbe sollte idealerweise direkt im SVG oder per CSS Klasse gesetzt werden.
        // path.setAttribute('fill', "#FFB3C7"); // Klarna Pink
    });


    // Initialisierung des ersten Schritts und der Validierung
    // Bestimme den initialen Schritt basierend auf URL-Parametern (serverseitig in PHP besser)
    const urlParams = new URLSearchParams(window.location.search);
    const stepParam = urlParams.get('step');
    let initialStep = 1;
    if (stepParam === 'payment') initialStep = 2;
    if (stepParam === 'confirmation') initialStep = 3;
    // if (stepParam === 'thankyou') initialStep = 4; // Danke-Seite wird meist nach Aktion geladen

    showStep(initialStep);
    if (initialStep === 1) {
        validateAddressForm(); // Initial validieren, wenn auf Adress-Schritt gestartet wird
    } else if (initialStep === 2) {
        updatePaymentStepSummary(); // Preise laden, wenn auf Zahlungs-Schritt gestartet
    } else if (initialStep === 3) {
        // Daten müssten hier aus der Session/Backend geladen werden, um die Bestätigungsseite korrekt zu füllen
        // Für Demo-Zwecke:
        collectAddressData(); // Simuliert, dass Adressdaten vorhanden sind
        collectPaymentData(); // Simuliert, dass Zahlungsdaten vorhanden sind
        populateConfirmation();
    }

    // Warenkorb-Zusammenfassung initial laden (falls vorhanden)
    const cartSummaryContainer = document.getElementById('checkout-cart-summary-items');
    if (cartSummaryContainer) {
        updateCartSummaryDisplay(cartSummaryContainer);
    }
    const cartTotalsContainer = document.getElementById('checkout-cart-summary-totals');
     if (cartTotalsContainer) {
        updateCartTotalsDisplay(cartTotalsContainer);
    }

}); // Ende DOMContentLoaded

/**
 * Aktualisiert die Anzeige der Produkte im Warenkorb-Widget.
 * @param {HTMLElement} container - Das HTML-Element, in das die Produktliste gerendert wird.
 */
function updateCartSummaryDisplay(container) {
    // Diese Funktion würde normalerweise von WooCommerce Hooks oder AJAX aktualisiert werden.
    // Hier eine einfache Demo basierend auf den `cartItems`.
    // const cartItems = []; // Sollte global verfügbar sein oder als Parameter übergeben werden.
    // Annahme: cartItems ist global oder wird anderswoher bezogen.
    // Für die Demo nehme ich die globale Variable aus dem oberen Scope.
    // In einer echten Anwendung würden die cartItems dynamisch geladen.

    if (!container || typeof cartItems === 'undefined') return;

    container.innerHTML = ''; // Bestehende Elemente leeren

    if (cartItems.length === 0) {
        container.innerHTML = '<p class="text-yprint-text-secondary">Ihr Warenkorb ist leer.</p>';
        return;
    }

    cartItems.forEach(item => {
        const itemEl = document.createElement('div');
        itemEl.className = 'product-summary-item flex justify-between items-center py-2 border-b border-yprint-medium-gray';
        itemEl.innerHTML = `
            <div class="flex items-center">
                <img src="${item.image}" alt="${item.name}" class="w-12 h-12 object-cover rounded mr-3">
                <div>
                    <p class="font-medium text-sm">${item.name}</p>
                    <p class="text-xs text-yprint-text-secondary">Menge: ${item.quantity}</p>
                </div>
            </div>
            <p class="font-medium text-sm">€${(item.price * item.quantity).toFixed(2)}</p>
        `;
        container.appendChild(itemEl);
    });
}


/**
 * Aktualisiert die Preisanzeige in der Warenkorb-Zusammenfassung.
 * @param {HTMLElement} container - Das HTML-Element, in das die Gesamtpreise gerendert werden.
 */
function updateCartTotalsDisplay(container) {
    // Auch diese Funktion würde von WC oder AJAX aktualisiert.
    // Nutzt calculatePrices() für Konsistenz, obwohl calculatePrices() auch Gutscheine aus dem Hauptformular berücksichtigt.
    // In einer echten Implementierung wären Gutscheine im Warenkorb-Widget separat.
    // Annahme: calculatePrices ist global verfügbar.

    if (!container || typeof calculatePrices === 'undefined') return;

    const prices = calculatePrices(); // Verwendet formData.voucher, was hier ggf. nicht ideal ist.
                                     // Besser wäre eine separate Berechnung für den reinen Warenkorb.
                                     // Für die Demo ist es okay.

    container.innerHTML = `
        <div class="flex justify-between text-sm mt-3">
            <span>Zwischensumme:</span>
            <span>€${prices.subtotal.toFixed(2)}</span>
        </div>
        <div class="flex justify-between text-sm">
            <span>Versand:</span>
            <span>€${prices.shipping.toFixed(2)}</span>
        </div>
        ${prices.discount > 0 ? `
        <div class="flex justify-between text-sm text-yprint-success">
            <span>Rabatt:</span>
            <span>-€${prices.discount.toFixed(2)}</span>
        </div>` : ''}
        <div class="flex justify-between text-base font-bold mt-2 pt-2 border-t border-yprint-medium-gray">
            <span>Gesamt:</span>
            <span class="text-yprint-blue">€${prices.total.toFixed(2)}</span>
        </div>
    `;
}

// Beispiel für ein globales Objekt für AJAX-Aufrufe (Platzhalter)
// const YPrintAJAX = {
//     saveAddress: function(shippingData, billingData) {
//         console.log("AJAX: Speichere Adresse", shippingData, billingData);
//         // Hier fetch oder jQuery.ajax an wp_ajax_yprint_save_address
//         return Promise.resolve({success: true}); // Simulierter Erfolg
//     },
//     setPaymentMethod: function(paymentData) {
//         console.log("AJAX: Setze Zahlungsmethode", paymentData);
//         // Hier fetch oder jQuery.ajax an wp_ajax_yprint_set_payment_method
//         return Promise.resolve({success: true});
//     },
//     processCheckout: function(allFormData) {
//         console.log("AJAX: Verarbeite Checkout", allFormData);
//         // Hier fetch oder jQuery.ajax an wp_ajax_yprint_process_checkout
//         return new Promise((resolve) => {
//             setTimeout(() => resolve({success: true, orderId: 'YP-SIM123'}), 2000);
//         });
//     }
// };

