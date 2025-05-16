<?php
/**
 * Partial Template: Fortschrittsbalken für den Checkout.
 *
 * Benötigt:
 * $current_step_slug (string) - Slug des aktuellen Schritts (z.B. 'address', 'payment', 'confirmation')
 */

// Direktaufruf verhindern
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definition der Schritte und ihrer Reihenfolge
$checkout_steps_config = array(
    'address' => array(
        'number' => 1,
        'label' => __('Adresse', 'yprint-checkout'),
        'icon' => 'fa-map-marker-alt' // Font Awesome Icon Klasse
    ),
    'payment' => array(
        'number' => 2,
        'label' => __('Zahlung', 'yprint-checkout'),
        'icon' => 'fa-credit-card'
    ),
    'confirmation' => array(
        'number' => 3,
        'label' => __('Bestätigung', 'yprint-checkout'),
        'icon' => 'fa-check-circle'
    ),
    // 'thankyou' wird hier nicht als sichtbarer Schritt in der Leiste dargestellt
);

// Sicherstellen, dass $current_step_slug gesetzt ist, Standard auf 'address'
$current_step_slug = isset($current_step_slug) ? $current_step_slug : 'address';
$current_step_number = isset($checkout_steps_config[$current_step_slug]['number']) ? $checkout_steps_config[$current_step_slug]['number'] : 1;

?>
<div class="progress-bar-container">
    <?php foreach ($checkout_steps_config as $step_slug => $step_data) : ?>
        <?php
        $is_active = ($step_slug === $current_step_slug);
        $is_completed = ($step_data['number'] < $current_step_number);
        $step_class = 'progress-step';
        if ($is_active) {
            $step_class .= ' active';
        } elseif ($is_completed) {
            $step_class .= ' completed';
        }

        // URL zum Schritt (vereinfacht, für komplexere URLs anpassen)
        // In einer echten WP-Anwendung würde man hier vielleicht get_permalink() oder add_query_arg() verwenden.
        $step_url = add_query_arg('step', $step_slug, get_permalink()); // Annahme: Checkout ist auf einer Seite
        ?>
        <div class="<?php echo esc_attr($step_class); ?>" id="progress-step-<?php echo esc_attr($step_data['number']); ?>">
            <?php // Klickbar machen, wenn abgeschlossen, aber nicht aktiv ?>
            <?php if ($is_completed && !$is_active) : ?>
                <a href="<?php echo esc_url($step_url); ?>" class="progress-step-link">
                    <div class="progress-circle">
                        <?php if ($is_completed): ?>
                            <i class="fas fa-check"></i>
                        <?php else: ?>
                            <?php echo esc_html($step_data['number']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="progress-label"><?php echo esc_html($step_data['label']); ?></div>
                </a>
            <?php else: ?>
                <div class="progress-circle">
                     <?php if ($is_completed && !$is_active): // Sollte eigentlich nicht passieren wegen oberer if-Bedingung, aber zur Sicherheit ?>
                        <i class="fas fa-check"></i>
                    <?php else: ?>
                        <?php echo esc_html($step_data['number']); ?>
                    <?php endif; ?>
                </div>
                <div class="progress-label"><?php echo esc_html($step_data['label']); ?></div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>
