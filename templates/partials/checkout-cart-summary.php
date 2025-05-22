<?php
/**
 * Partial Template: Warenkorb-Zusammenfassung (Sidebar).
 *
 * Benötigt:
 * $cart_items_data (array) - Array mit Warenkorbartikeln
 * $cart_totals_data (array) - Array mit Warenkorbsummen
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// $cart_items_data und $cart_totals_data sollten von checkout-multistep.php übergeben werden
// oder hier direkt von WooCommerce geladen werden.
// Für dieses Beispiel nehmen wir an, sie sind bereits verfügbar.

// Fallback, falls Daten nicht korrekt übergeben wurden
if ( !isset($cart_items_data) || !is_array($cart_items_data) ) {
    $cart_items_data = array(); // Leerer Warenkorb
}
if ( !isset($cart_totals_data) || !is_array($cart_totals_data) ) {
    $cart_totals_data = array( // Standard-Summen
        'subtotal' => 0,
        'shipping' => 0,
        'discount' => 0,
        'vat'      => 0,
        'total'    => 0,
    );
}
?>

<style>
.order-summary-bold-final {
    border: 1px solid #DFDFDF; /* Gleicher Rahmen wie andere Karten */
    padding: 20px; /* Verkleinert von 25px */
    font-family: 'Roboto', sans-serif; /* Gleiche Schriftart wie der Rest des Checkouts */
    background-color: #ffffff; /* Hintergrund ist Weiß */
    border-radius: 12px; /* Gleicher Radius wie andere Karten */
    max-width: 350px; /* Optional: für bessere Lesbarkeit in einer Sidebar */
    margin: 0 auto; /* Optional: zum Zentrieren */
    display: flex;
    flex-direction: column;
    height: calc(100% - 20px); /* Volle Höhe minus Margin */
}

    /* Titel "Warenkorb" */
.bold-header-final {
    color: var(--yprint-black, #1d1d1f); /* Gleiche Farbe wie die Hauptüberschriften */
    font-size: 1.25rem; /* 20px / 1.25rem = h3 im Rest des Designs */
    font-weight: 600;
    margin-bottom: 20px;
}

    /* Container für Artikel */
    .items {
        margin-bottom: 20px;
        max-height: 260px; /* Erhöht für bessere Anpassung an den Adressbereich */
        overflow-y: auto; /* Scrollbalken für viele Artikel */
        padding-right: 5px; /* Abstand für Scrollbalken, damit er nicht den Inhalt überlappt */
    }

    /* Einzelner Artikel */
    .item {
        display: flex;
        align-items: center;
        padding: 12px 0;
        border-bottom: 1px solid #eee; /* Hellere Trennlinie zwischen Artikeln */
    }

    .item:last-child {
        border-bottom: none; /* Keine Linie nach dem letzten Artikel */
    }

    /* Artikelbild */
    .item-image {
        width: 60px;
        height: 60px;
        object-fit: cover; /* Bildausschnitt anpassen */
        border-radius: 4px; /* Leichte Rundung für Bilder */
        margin-right: 15px;
        flex-shrink: 0; /* Verhindert das Schrumpfen des Bildes */
    }

    /* Details (Name, Menge) */
    .item-details {
        flex-grow: 1; /* Nimmt den verbleibenden Platz ein */
    }

    .item-name {
        font-weight: bold;
        color: #333;
        margin-bottom: 3px;
        font-size: 0.95em;
    }

    .item-quantity {
        font-size: 0.8em;
        color: #666;
    }

    /* Artikelpreis */
    .item-price {
        font-weight: bold;
        color: #333;
        white-space: nowrap; /* Preis nicht umbrechen */
        font-size: 0.95em;
    }

    /* Summen-Zeilen (Zwischensumme, Versandkosten, Rabatt) */
    .totals div {
        display: flex;
        justify-content: space-between;
        padding: 6px 0;
        font-size: 0.95em;
        color: #555;
    }

    /* Rabatt-Zeile */
    .discount {
        color: #28a745; /* Grüne Farbe für Rabatt */
    }

    /* Trennlinie vor dem Gesamtbetrag */
    .total-divider-final {
        border: none;
        border-top: 1px solid #ddd;
        margin: 10px 0;
    }

    /* Gesamtbetrag */
    .total-final {
        font-weight: bold;
        font-size: 1.3em;
        color: #333; /* Schwarze Gesamtbetragsfarbe */
        display: flex;
        justify-content: space-between;
        padding-top: 10px;
    }

    /* Gutscheincode-Bereich */
    .voucher {
        margin-top: 25px;
    }

    .voucher label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
        color: #555;
        font-size: 0.95em;
    }

    /* Input-Gruppe für Gutschein (Input + Button) */
    .voucher-input-group-final {
        display: flex;
        width: 100%;
    }

    .voucher-input-group-final input {
        flex-grow: 1; /* Input nimmt den meisten Platz ein */
        padding: 10px;
        border: 1px solid #ccc;
        border-radius: 5px 0 0 5px; /* Links abgerundet */
        outline: none;
        font-size: 0.9em;
    }

    /* Einlösen-Button */
    .voucher-button-final {
        padding: 10px 15px;
        background-color: #007bff; /* Ihr gewünschtes Blau */
        color: white;
        border: none;
        border-radius: 0 5px 5px 0; /* Rechts abgerundet */
        cursor: pointer;
        font-size: 0.9em;
        white-space: nowrap;
        transition: background-color 0.2s ease; /* Sanfter Übergang beim Hover */
    }

    .voucher-button-final:hover {
        background-color: #0056b3; /* Dunkleres Blau beim Hover */
    }

    /* Feedback-Text für Gutschein (optional, aus Original-Code) */
    #cart-voucher-feedback {
        font-size: 0.75em; /* text-xs */
        margin-top: 5px; /* mt-1 */
        color: #6c757d; /* text-yprint-text-secondary, angelehnt an Bootstrap text-muted */
    }

    /* Styling für leeren Warenkorb (optional, aus Original-Code) */
    .text-yprint-text-secondary {
        color: #6c757d; /* Dunkelgrau für Text */
    }
</style>

<div class="order-summary-bold-final">
    <h2 class="bold-header-final"><?php esc_html_e('Warenkorb', 'yprint-checkout'); ?></h2>

    <div id="checkout-cart-summary-items" class="items">
        <?php if ( ! empty( $cart_items_data ) ) : ?>
            <?php foreach ( $cart_items_data as $item ) : ?>
                <div class="item <?php echo isset($item['is_design_product']) && $item['is_design_product'] ? 'design-product-item' : ''; ?>">
                    <div style="display: flex; align-items: center;">
                        <div class="item-image-container">
                            <?php if ( ! empty( $item['image'] ) ) : ?>
                                <img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" class="item-image">
                                <?php if ( isset($item['is_design_product']) && $item['is_design_product'] ) : ?>
                                    <div class="design-badge" title="<?php esc_attr_e('Design-Produkt', 'yprint-checkout'); ?>">
                                        <i class="fas fa-palette"></i>
                                    </div>
                                <?php endif; ?>
                            <?php else : ?>
                                <div class="item-image" style="background: var(--yprint-light-gray); display: flex; align-items: center; justify-content: center; color: var(--yprint-text-secondary);">
                                    <i class="fas fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="item-details">
                            <p class="item-name"><?php echo esc_html( $item['name'] ); ?></p>
                            <p class="item-quantity">
                                <?php esc_html_e('Menge:', 'yprint-checkout'); ?> <?php echo esc_html( $item['quantity'] ); ?>
                            </p>
                            <?php if ( isset($item['design_details']) && !empty($item['design_details']) ) : ?>
                                <div class="design-details">
                                    <?php foreach ($item['design_details'] as $detail) : ?>
                                        <span class="design-detail"><?php echo esc_html($detail); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="item-price-container">
                        <p class="item-price">
                            €<?php echo esc_html( number_format_i18n( $item['price'] * $item['quantity'], 2 ) ); ?>
                        </p>
                        <?php if ( $item['quantity'] > 1 ) : ?>
                            <span class="unit-price">
                                (€<?php echo esc_html( number_format_i18n( $item['price'], 2 ) ); ?> / Stk.)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <p class="text-yprint-text-secondary"><?php esc_html_e('Ihr Warenkorb ist leer.', 'yprint-checkout'); ?></p>
        <?php endif; ?>
    </div>

    <div id="checkout-cart-summary-totals">
        <div class="subtotal">
            <span><?php esc_html_e('Zwischensumme:', 'yprint-checkout'); ?></span>
            <span>€<?php echo esc_html( number_format_i18n( $cart_totals_data['subtotal'], 2 ) ); ?></span>
        </div>
        <div class="shipping">
            <span><?php esc_html_e('Versandkosten:', 'yprint-checkout'); ?></span>
            <span>€<?php echo esc_html( number_format_i18n( $cart_totals_data['shipping'], 2 ) ); ?></span>
        </div>

        <?php if ( $cart_totals_data['discount'] > 0 ) : ?>
        <div class="discount">
            <span><?php esc_html_e('Rabatt:', 'yprint-checkout'); ?></span>
            <span>-€<?php echo esc_html( number_format_i18n( $cart_totals_data['discount'], 2 ) ); ?></span>
        </div>
        <?php endif; ?>

        <?php /* Optional: Anzeige der Mehrwertsteuer
        <div class="flex justify-between text-sm">
            <span><?php esc_html_e('MwSt. (19%):', 'yprint-checkout'); ?></span>
            <span>€<?php echo esc_html( number_format_i18n( $cart_totals_data['vat'], 2 ) ); ?></span>
        </div>
        */ ?>

        <hr class="total-divider-final">

        <div class="total-final">
            <span><?php esc_html_e('Gesamtbetrag:', 'yprint-checkout'); ?></span>
            <span>€<?php echo esc_html( number_format_i18n( $cart_totals_data['total'], 2 ) ); ?></span>
        </div>
    </div>

    <div class="voucher">
        <label for="cart-voucher"><?php esc_html_e('Gutscheincode', 'yprint-checkout'); ?></label>
        <div class="voucher-input-group-final">
            <input type="text" id="cart-voucher" name="cart_voucher" placeholder="<?php esc_attr_e('Code eingeben', 'yprint-checkout'); ?>">
            <button type="button" class="voucher-button-final">
                <?php esc_html_e('Einlösen', 'yprint-checkout'); ?>
            </button>
        </div>
        <p id="cart-voucher-feedback" class="text-xs mt-1"></p>
    </div>
</div>