<?php
/**
 * Partial Template: Schritt 3 - Bestellung überprüfen und abschließen.
 *
 * Benötigt:
 * JavaScript füllt die dynamischen Daten (Adressen, Produkte, Preise)
 * basierend auf dem `formData` Objekt und den `cartItems`.
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div id="step-3" class="checkout-step"> <?php // 'active' Klasse wird serverseitig oder per JS initial gesetzt ?>
    <h2 class="flex items-center"><i class="fas fa-check-circle mr-2 text-yprint-blue"></i><?php esc_html_e('Bestellung überprüfen und abschließen', 'yprint-checkout'); ?></h2>
    <div class="space-y-6 mt-6">
        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Lieferadresse', 'yprint-checkout'); ?></h3>
            <div id="confirm-shipping-address" class="text-yprint-text-secondary text-sm leading-relaxed">
                <?php // Wird von JS befüllt, z.B. populateConfirmation() ?>
                <?php esc_html_e('Lädt Adressdaten...', 'yprint-checkout'); ?>
            </div>
        </div>

        <?php // Container für abweichende Rechnungsadresse, nur sichtbar wenn relevant ?>
        <div id="confirm-billing-address-container" class="hidden">
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Rechnungsadresse', 'yprint-checkout'); ?></h3>
            <div id="confirm-billing-address" class="text-yprint-text-secondary text-sm leading-relaxed">
                <?php // Wird von JS befüllt ?>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Gewählte Zahlungsart', 'yprint-checkout'); ?></h3>
            <div id="confirm-payment-method" class="text-yprint-text-secondary text-sm">
                <?php // Wird von JS befüllt ?>
                <?php esc_html_e('Lädt Zahlungsart...', 'yprint-checkout'); ?>
            </div>
        </div>

        <div>
            <h3 class="text-lg font-semibold border-b border-yprint-medium-gray pb-2 mb-3"><?php esc_html_e('Produkte', 'yprint-checkout'); ?></h3>
            <div id="confirm-product-list" class="space-y-3">
                <?php // Produkte werden hier per JS eingefügt (aus cartItems) ?>
                <p class="text-yprint-text-secondary text-sm"><?php esc_html_e('Lädt Produktliste...', 'yprint-checkout'); ?></p>
            </div>
        </div>

        <div class="mt-6 border-t border-yprint-medium-gray pt-6">
            <h3 class="text-lg font-semibold mb-2"><?php esc_html_e('Gesamtkosten', 'yprint-checkout'); ?></h3>
            <div class="space-y-1 text-sm">
                <div class="flex justify-between"><span><?php esc_html_e('Zwischensumme:', 'yprint-checkout'); ?></span> <span id="confirm-subtotal">€0,00</span></div>
                <div class="flex justify-between"><span><?php esc_html_e('Versand:', 'yprint-checkout'); ?></span> <span id="confirm-shipping">€0,00</span></div>
                <div id="confirm-discount-row" class="flex justify-between text-yprint-success hidden">
                    <span><?php esc_html_e('Rabatt (Gutschein):', 'yprint-checkout'); ?></span> <span id="confirm-discount">€0,00</span>
                </div>
                <div class="flex justify-between"><span><?php esc_html_e('inkl. MwSt.:', 'yprint-checkout'); ?></span> <span id="confirm-vat">€0,00</span></div>
                <hr class="my-2 border-yprint-medium-gray">
                <div class="flex justify-between text-xl font-bold text-yprint-blue">
                    <span><?php esc_html_e('Gesamtbetrag:', 'yprint-checkout'); ?></span> <span id="confirm-total">€0,00</span>
                </div>
            </div>
        </div>

        <div class="mt-4 p-3 bg-yprint-info rounded-lg text-sm text-yprint-text-secondary space-y-1">
            <p><i class="fas fa-truck fa-fw mr-2"></i> <?php esc_html_e('Geschätzte Lieferzeit: 2-3 Werktage.', 'yprint-checkout'); ?></p>
            <p><i class="fas fa-undo fa-fw mr-2"></i> <?php esc_html_e('30 Tage Rückgaberecht.', 'yprint-checkout'); ?></p>
            <p><i class="fas fa-headset fa-fw mr-2"></i> <?php esc_html_e('Fragen?', 'yprint-checkout'); ?> <a href="#" class="text-yprint-blue hover:underline"><?php esc_html_e('Support kontaktieren', 'yprint-checkout'); ?></a></p>
        </div>

         <div class="mt-4 text-xs text-yprint-text-secondary">
            <?php printf(
                wp_kses(
                    /* translators: %1$s: AGB link, %2$s: Datenschutz link */
                    __('Mit Klick auf "Jetzt kaufen" geben Sie eine verbindliche Bestellung ab. Sie erhalten eine Bestellbestätigung per E-Mail. Es gelten unsere <a href="%1$s" target="_blank" class="text-yprint-blue hover:underline">AGB</a> und <a href="%2$s" target="_blank" class="text-yprint-blue hover:underline">Datenschutzbestimmungen</a>.', 'yprint-checkout'),
                    array( 'a' => array( 'href' => array(), 'class' => array(), 'target' => array() ) )
                ),
                esc_url( home_url('/agb') ), // Beispiel-URL, anpassen!
                esc_url( home_url('/datenschutz') ) // Beispiel-URL, anpassen!
            ); ?>
        </div>
        <!-- Buttons entfernt - Zahlung bereits abgeschlossen -->
    </div>
</div>
