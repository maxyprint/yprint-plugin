<?php
/**
 * YPrint Multistep Checkout Template
 *
 * @package YPrint
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure user is logged in
if (!is_user_logged_in()) {
    ?>
    <div class="yprint-checkout-login-required">
        <div class="yprint-checkout-message">
            <h2>Anmeldung erforderlich</h2>
            <p>Bitte melde dich an, um mit dem Checkout fortzufahren.</p>
            <a href="<?php echo esc_url(wc_get_page_permalink('myaccount')); ?>" class="yprint-button">Zum Login</a>
        </div>
    </div>
    <?php
    return;
}

// Check if cart is empty
if (WC()->cart->is_empty()) {
    ?>
    <div class="yprint-checkout-empty-cart">
        <div class="yprint-checkout-message">
            <h2>Dein Warenkorb ist leer</h2>
            <p>Bitte füge Produkte zu deinem Warenkorb hinzu, bevor du zur Kasse gehst.</p>
            <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" class="yprint-button">Zum Shop</a>
        </div>
    </div>
    <?php
    return;
}

// Get current step from URL or default to 'address'
$current_step = isset($_GET['step']) ? sanitize_text_field($_GET['step']) : 'address';

// Available steps and their numbers
$steps = array(
    'address' => 1,
    'payment' => 2,
    'confirmation' => 3
);

// Get current step number
$current_step_number = isset($steps[$current_step]) ? $steps[$current_step] : 1;

// Calculate progress percentage
$progress_percentage = (($current_step_number - 1) / (count($steps) - 1)) * 100;
?>
<div class="yprint-checkout-container" data-current-step="<?php echo esc_attr($current_step); ?>">
    
    <!-- Checkout Header & Progress -->
    <div class="yprint-checkout-header">
        <div class="yprint-progress-container">
            <div class="yprint-progress-bar">
                <div class="yprint-progress-track">
                    <div class="yprint-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%"></div>
                </div>
            </div>
            
            <div class="yprint-progress-steps">
                <?php foreach ($steps as $step_key => $step_number) : 
                    $step_active = ($current_step === $step_key) ? ' active' : '';
                    $step_completed = ($step_number < $current_step_number) ? ' completed' : '';
                    $step_class = 'yprint-progress-step' . $step_active . $step_completed;
                    $step_url = add_query_arg('step', $step_key);
                    
                    // Only make completed steps clickable
                    $step_link = ($step_completed) ? $step_url : '#';
                    $step_clickable = ($step_completed) ? '' : ' not-clickable';
                ?>
                <div class="<?php echo esc_attr($step_class . $step_clickable); ?>" data-step="<?php echo esc_attr($step_number); ?>">
                    <a href="<?php echo esc_url($step_link); ?>" class="yprint-step-link">
                        <div class="yprint-step-indicator"><?php echo esc_html($step_number); ?></div>
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
    </div>
    
    <!-- Checkout Content -->
    <div class="yprint-checkout-content">
        <div class="yprint-checkout-main">
            <?php
            // Load the template for the current step
            $step_template = 'partials/checkout-step-' . $current_step . '.php';
            if (file_exists(YPRINT_PLUGIN_DIR . 'templates/' . $step_template)) {
                include(YPRINT_PLUGIN_DIR . 'templates/' . $step_template);
            } else {
                echo '<p>Template nicht gefunden: ' . esc_html($step_template) . '</p>';
            }
            ?>
        </div>
        
        <div class="yprint-checkout-sidebar">
            <?php include(YPRINT_PLUGIN_DIR . 'templates/partials/checkout-cart-summary.php'); ?>
        </div>
    </div>
</div>