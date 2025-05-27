// YPrint Checkout JavaScript - Stripe-spezifische Funktionen
// Dieses Skript sollte geladen werden, NACHDEM Stripe.js v3 geladen wurde.
// z.B. <script src="https://js.stripe.com/v3/"></script>

document.addEventListener('DOMContentLoaded', function () {
    // Überprüfen, ob Stripe-Objekt vorhanden ist
    if (typeof Stripe === 'undefined') {
        console.error('Stripe.js wurde nicht geladen. Stripe-Funktionen sind nicht verfügbar.');
        // Optional: Dem Nutzer eine Meldung anzeigen oder bestimmte UI-Elemente deaktivieren.
        const stripePaymentElements = document.querySelectorAll('.stripe-payment-element-container'); // Beispielklasse
        stripePaymentElements.forEach(el => {
            el.innerHTML = '<p class="text-yprint-error">Fehler beim Laden der Zahlungsoption. Bitte versuchen Sie es später erneut.</p>';
        });
        return;
    }

    // Prüfe ob yprint_stripe_vars verfügbar ist
if (typeof yprint_stripe_vars === 'undefined' || !yprint_stripe_vars.publishable_key) {
    console.error('Stripe Publishable Key nicht verfügbar. Stripe-Funktionen werden nicht initialisiert.');
    return; // Beende die Initialisierung
}

const stripe = Stripe(yprint_stripe_vars.publishable_key);

    // Beispiel: Initialisierung von Stripe Elements für ein Kreditkartenformular
    // Dies ist nur ein Grundgerüst. Die tatsächliche Implementierung hängt stark
    // von der gewählten Stripe-Integration ab (Payment Intents, Setup Intents, etc.)

    const elements = stripe.elements();
    const cardElementContainer = document.getElementById('stripe-card-element'); // Container im HTML für das Card Element

    if (cardElementContainer) {
        // Style für Stripe Elements (kann an YPrint CD angepasst werden)
        const elementsStyle = {
            base: {
                color: '#1d1d1f', // --yprint-black
                fontFamily: '"Roboto", sans-serif',
                fontSmoothing: 'antialiased',
                fontSize: '16px',
                '::placeholder': {
                    color: '#6e6e73' // --yprint-text-secondary
                }
            },
            invalid: {
                color: '#dc3545', // --yprint-error
                iconColor: '#dc3545'
            }
        };

        const card = elements.create('card', { style: elementsStyle });
        card.mount('#stripe-card-element'); // Mounten in den Container

        // Fehlerbehandlung für das Card Element
        card.on('change', function(event) {
            const displayError = document.getElementById('stripe-card-errors'); // Ein Div zur Anzeige von Kartenfehlern
            if (displayError) {
                if (event.error) {
                    displayError.textContent = event.error.message;
                    displayError.classList.add('text-yprint-error', 'mt-2'); // Sichtbar machen
                } else {
                    displayError.textContent = '';
                    displayError.classList.remove('text-yprint-error', 'mt-2'); // Verstecken
                }
            }
        });

        // Event Listener für den "Jetzt kaufen" Button, um die Zahlung mit Stripe zu verarbeiten
        // Dieser müsste mit der Logik in yprint-checkout.js koordiniert werden,
        // z.B. erst wenn der Schritt "Bestätigung" erreicht ist und die Zahlungsart "Kreditkarte (Stripe)" gewählt wurde.
        const form = document.getElementById('checkout-form'); // Das Haupt-Checkout-Formular
        const buyNowButton = document.getElementById('btn-buy-now'); // Der "Jetzt Kaufen" Button

        if (form && buyNowButton) {
            // Dieser Event Listener ist ein Beispiel und muss ggf. angepasst werden,
            // um nicht mit dem globalen 'btn-buy-now' Listener in yprint-checkout.js zu kollidieren.
            // Eine Möglichkeit wäre, den globalen Listener zu modifizieren, um Stripe-spezifische
            // Logik aufzurufen, wenn Stripe als Zahlungsart gewählt ist.

            // buyNowButton.addEventListener('click', async function(event) {
            //     event.preventDefault();
            //     const selectedPaymentMethod = document.querySelector('input[name="payment_method"]:checked')?.value;
            //
            //     if (selectedPaymentMethod === 'stripe_credit_card') { // Beispielwert für Stripe
            //         // Deaktiviere den Button, um doppelte Klicks zu verhindern
            //         buyNowButton.disabled = true;
            //         document.getElementById('loading-overlay')?.classList.add('visible');
            //
            //         // Hier würde die Erstellung eines PaymentIntent auf dem Server erfolgen (via AJAX)
            //         // const { clientSecret } = await fetch('/.netlify/functions/create-payment-intent', { // Beispiel-Endpunkt
            //         //     method: 'POST',
            //         //     headers: { 'Content-Type': 'application/json' },
            //         //     body: JSON.stringify({ amount: 1000 }) // Betrag in Cent
            //         // }).then(r => r.json());
            //
            //         // Dummy clientSecret für Demo
            //         const clientSecret = "pi_123_secret_456"; // ERSETZEN DURCH ECHTEN CLIENT SECRET VOM SERVER
            //
            //         const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
            //             payment_method: {
            //                 card: card,
            //                 // billing_details: { // Optional, kann serverseitig gesetzt werden
            //                 //    name: document.getElementById('name_on_card').value,
            //                 // },
            //             }
            //         });
            //
            //         if (error) {
            //             console.error('Stripe confirmCardPayment Fehler:', error);
            //             const displayError = document.getElementById('stripe-card-errors');
            //             if (displayError) displayError.textContent = error.message;
            //             buyNowButton.disabled = false; // Button wieder aktivieren
            //             document.getElementById('loading-overlay')?.classList.remove('visible');
            //         } else {
            //             if (paymentIntent.status === 'succeeded') {
            //                 console.log('Stripe Zahlung erfolgreich!', paymentIntent);
            //                 // Hier die Logik nach erfolgreicher Zahlung:
            //                 // - Bestellung in WordPress abschließen (via AJAX an ajax_process_checkout)
            //                 // - Weiterleitung zur Danke-Seite
            //                 // Die Logik aus yprint-checkout.js für showStep(4) etc. könnte hier getriggert werden.
            //             } else {
            //                 console.warn('Stripe Zahlung nicht abgeschlossen:', paymentIntent.status);
            //                  const displayError = document.getElementById('stripe-card-errors');
            //                 if (displayError) displayError.textContent = "Zahlung konnte nicht abgeschlossen werden: " + paymentIntent.status;
            //                 buyNowButton.disabled = false;
            //                 document.getElementById('loading-overlay')?.classList.remove('visible');
            //             }
            //         }
            //     }
            // });
        }

    } else {
        console.log('Stripe Card Element Container (stripe-card-element) nicht gefunden.');
    }


    // TODO: Implementierung für Express Checkout (Apple Pay, Google Pay)
    // Dies erfordert die Stripe Payment Request Button API.
    // 1. Prüfen, ob Payment Request API verfügbar ist.
    // 2. Payment Request Objekt erstellen.
    // 3. Payment Request Button erstellen und mounten.
    // 4. Event Listener für 'paymentmethod' am Payment Request Objekt.
    // 5. Zahlung mit dem erhaltenen PaymentMethod-Objekt bestätigen (serverseitig PaymentIntent erstellen).

    // Beispielhafte Struktur (sehr vereinfacht):
    // const paymentRequest = stripe.paymentRequest({
    //    country: 'DE',
    //    currency: 'eur',
    //    total: {
    //        label: 'YPrint Bestellung',
    //        amount: 1000, // In Cent, dynamisch vom Warenkorb
    //    },
    //    requestPayerName: true,
    //    requestPayerEmail: true,
    // });
    //
    // const prButton = elements.create('paymentRequestButton', {
    //    paymentRequest: paymentRequest,
    // });
    //
    // // Prüfen, ob der Payment Request Button verwendet werden kann.
    // paymentRequest.canMakePayment().then(function(result) {
    //    if (result) {
    //        const prButtonContainer = document.getElementById('payment-request-button'); // Container im HTML
    //        if (prButtonContainer) {
    //             prButton.mount('#payment-request-button');
    //        } else {
    //             console.log('Payment Request Button Container nicht gefunden.')
    //        }
    //    } else {
    //        const prButtonContainer = document.getElementById('payment-request-button');
    //        if (prButtonContainer) prButtonContainer.style.display = 'none'; // Button ausblenden, wenn nicht verfügbar
    //        console.log('Apple Pay / Google Pay nicht verfügbar.');
    //    }
    // });
    //
    // paymentRequest.on('paymentmethod', async (ev) => {
    //    // Bestätige den PaymentIntent auf dem Server mit ev.paymentMethod.id
    //    // const { clientSecret } = await fetch('/create-payment-intent', { ... }).then(r => r.json());
    //    // const { error, paymentIntent } = await stripe.confirmCardPayment(clientSecret, {
    //    //    payment_method: ev.paymentMethod.id,
    //    // }, { handleActions: false });
    //    //
    //    // if (error) {
    //    //    ev.complete('fail'); return;
    //    // }
    //    // ev.complete('success');
    //    // if (paymentIntent.status === 'requires_action') {
    //    //    stripe.confirmCardPayment(clientSecret).then(function(result) { ... });
    //    // } else {
    //    //    // Zahlung erfolgreich -> Weiterleiten etc.
    //    // }
    // });

    console.log('YPrint Stripe Checkout JS initialisiert.');
});

