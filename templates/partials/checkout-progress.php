<?php
/**
 * YPrint Checkout Progress Template
 *
 * @package YPrint
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="yprint-progress-container">
    <div class="yprint-progress-steps">
        <?php foreach ($steps as $step_key => $step_number) : 
            $step_active = ($current_step === $step_key) ? ' active' : '';
            $step_completed = ($step_number < $current_step_number) ? ' completed' : '';
            $step_class = 'yprint-progress-step' . $step_active . $step_completed;
            $step_url = add_query_arg('step', $step_key);
            
            // Only make completed steps clickable
            $step_link = ($step_completed) ? $step_url : '#';
            $step_clickable = ($step_completed) ? '' : ' not-clickable';
            
            // Icon für jeden Schritt
            $step_icon = '';
            switch ($step_key) {
                case 'address':
                    $step_icon = '<i class="fas fa-map-marker-alt"></i>';
                    break;
                case 'payment':
                    $step_icon = '<i class="fas fa-credit-card"></i>';
                    break;
                case 'confirmation':
                    $step_icon = '<i class="fas fa-check-circle"></i>';
                    break;
            }
        ?>
        <div class="<?php echo esc_attr($step_class . $step_clickable); ?>" data-step="<?php echo esc_attr($step_number); ?>">
            <a href="<?php echo esc_url($step_link); ?>" class="yprint-step-link">
                <div class="yprint-step-circle">
                    <?php if ($step_completed): ?>
                        <i class="fas fa-check"></i>
                    <?php else: ?>
                        <?php echo $step_number; ?>
                    <?php endif; ?>
                </div>
                <div class="yprint-step-label">
                    <?php 
                    switch ($step_key) {
                        case 'address':
                            echo 'Adresse';
                            break;
                        case 'payment':
                            echo 'Zahlung';
                            break;
                        case 'confirmation':
                            echo 'Bestätigung';
                            break;
                        default:
                            echo esc_html(ucfirst($step_key));
                    }
                    ?>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
</div>