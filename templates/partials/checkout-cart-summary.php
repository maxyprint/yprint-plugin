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
<h2 class="text-xl font-semibold mb-4"><?php esc_html_e('Warenkorb', 'yprint-checkout'); ?></h2>

<div id="checkout-cart-summary-items" class="space-y-3 mb-6 max-h-60 overflow-y-auto pr-2">
    <?php if ( ! empty( $cart_items_data ) ) : ?>
        <?php foreach ( $cart_items_data as $item ) : ?>
            <div class="product-summary-item flex justify-between items-center py-2 border-b border-yprint-medium-gray last:border-b-0">
                <div class="flex items-center">
                    <?php if ( ! empty( $item['image'] ) ) : ?>
                        <img src="<?php echo esc_url( $item['image'] ); ?>" alt="<?php echo esc_attr( $item['name'] ); ?>" class="w-12 h-12 object-cover rounded mr-3">
                    <?php endif; ?>
                    <div>
                        <p class="font-medium text-sm"><?php echo esc_html( $item['name'] ); ?></p>
                        <p class="text-xs text-yprint-text-secondary">
                            <?php esc_html_e('Menge:', 'yprint-checkout'); ?> <?php echo esc_html( $item['quantity'] ); ?>
                        </p>
                    </div>
                </div>
                <p class="font-medium text-sm">
                    €<?php echo esc_html( number_format_i18n( $item['price'] * $item['quantity'], 2 ) ); ?>
                </p>
            </div>
        <?php endforeach; ?>
    <?php else : ?>
        <p class="text-yprint-text-secondary"><?php esc_html_e('Ihr Warenkorb ist leer.', 'yprint-checkout'); ?></p>
    <?php endif; ?>
</div>

<div id="checkout-cart-summary-totals">
    <div class="flex justify-between text-sm mt-3">
        <span><?php esc_html_e('Zwischensumme:', 'yprint-checkout'); ?></span>
        <span>€<?php echo esc_html( number_format_i18n( $cart_totals_data['subtotal'], 2 ) ); ?></span>
    </div>
    <div class="flex justify-between text-sm">
        <span><?php esc_html_e('Versandkosten:', 'yprint-checkout'); ?></span>
        <span>€<?php echo esc_html( number_format_i18n( $cart_totals_data['shipping'], 2 ) ); ?></span>
    </div>

    <?php if ( $cart_totals_data['discount'] > 0 ) : ?>
    <div class="flex justify-between text-sm text-yprint-success">
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

    <hr class="my-2 border-yprint-medium-gray">

    <div class="flex justify-between text-base font-bold mt-2 text-yprint-blue">
        <span><?php esc_html_e('Gesamtbetrag:', 'yprint-checkout'); ?></span>
        <span>€<?php echo esc_html( number_format_i18n( $cart_totals_data['total'], 2 ) ); ?></span>
    </div>
</div>

<div class="mt-6">
    <label for="cart-voucher" class="form-label text-sm"><?php esc_html_e('Gutscheincode', 'yprint-checkout'); ?></label>
    <div class="flex">
        <input type="text" id="cart-voucher" name="cart_voucher" class="form-input rounded-r-none text-sm py-2" placeholder="<?php esc_attr_e('Code eingeben', 'yprint-checkout'); ?>">
        <button type="button" class="btn btn-secondary rounded-l-none whitespace-nowrap text-sm py-2 px-3">
            <?php esc_html_e('Einlösen', 'yprint-checkout'); ?>
        </button>
    </div>
    <p id="cart-voucher-feedback" class="text-xs mt-1"></p>
</div>
