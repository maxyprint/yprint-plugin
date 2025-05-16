<?php
/**
 * Partial Template: Fortschrittsbalken für den Checkout.
 *
 * Benötigt:
 * $current_step_slug (string) - Slug des aktuellen Schritts (z.B. 'address', 'payment', 'confirmation')
 *
 * Beachte: get_permalink() und add_query_arg() sind WordPress-Funktionen.
 * Für eine eigenständige Verwendung außerhalb von WordPress müssten diese angepasst oder entfernt werden.
 */

// Direktaufruf verhindern (typisch für WordPress-Templates)
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definition der Schritte und ihrer Reihenfolge, exakt wie in deinem ursprünglichen PHP-Code
$checkout_steps_config = array(
    'address' => array(
        'number' => 1,
        'label' => __('Adresse', 'yprint-checkout'), // __('Adresse', 'yprint-checkout') wird in WordPress übersetzt
        'icon' => 'fa-map-marker-alt' // Dieses Icon wird im Kreis nicht verwendet, da Checkmark oder Nummer angezeigt wird
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
);

// Sicherstellen, dass $current_step_slug gesetzt ist, Standard auf 'address'
// Dies simuliert die Übergabe des aktuellen Schritts.
$current_step_slug = isset($current_step_slug) ? $current_step_slug : 'address';
$current_step_number = isset($checkout_steps_config[$current_step_slug]['number']) ? $checkout_steps_config[$current_step_slug]['number'] : 1;

?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Design 3: Icon-Fokus</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        /* Das komplette CSS aus deinem Design-Beispiel, unverändert übernommen */
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #eef1f5;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            box-sizing: border-box;
            margin: 0;
        }

        .progress-bar-container-v3 {
    display: flex;
    justify-content: space-around;
    align-items: center;
    width: 100%;
    max-width: 500px;
    margin: 40px auto;
    padding: 20px 0;
    background-color: #fff;
    border-radius: 12px; /* Angepasst an andere Komponenten */
    border: 1px solid #DFDFDF; /* Einheitlicher Rahmen */
    --primary-color: #0079FF; /* Angepasst an YPrint-Blau */
    --completed-color: #28a745; /* Angepasst an YPrint-Success */
    --pending-color: #e5e5e5; /* Angepasst an YPrint-Medium-Gray */
    --icon-bg-size: 60px;
    --icon-font-size: 24px;
}

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .progress-step-link, .progress-step > div:not(.progress-label) {
            text-decoration: none;
            color: inherit;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .progress-step:not(.completed) .progress-step-link {
            cursor: default;
        }
        .progress-step.completed .progress-step-link {
            cursor: pointer;
        }

        .progress-circle {
            width: var(--icon-bg-size);
            height: var(--icon-bg-size);
            border-radius: 50%;
            background-color: var(--pending-color);
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: var(--icon-font-size);
            font-weight: bold;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.27, 1.55);
            position: relative;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .progress-circle i {
            transition: transform 0.3s ease;
        }

        .progress-label {
            font-size: 0.75rem;
            color: #333;
            margin-top: 12px;
            font-weight: 600;
            opacity: 0.7;
            transition: opacity 0.3s ease, color 0.3s ease;
        }

        /* Zustände */
        .progress-step.completed .progress-circle {
            background-color: var(--completed-color);
            transform: scale(1.1);
        }
        .progress-step.completed .progress-circle i.fa-check {
            transform: scale(1.2);
        }
        .progress-step.completed .progress-label {
            opacity: 1;
            color: var(--completed-color);
        }

        .progress-step.active .progress-circle {
            background-color: var(--primary-color);
            transform: scale(1.2);
            box-shadow: 0 0 15px rgba(var(--primary-color), 0.6);
        }
        .progress-step.active .progress-circle i {
            color: #fff;
        }
        .progress-step.active .progress-label {
            opacity: 1;
            color: var(--primary-color);
            font-weight: 700;
        }

        /* Hover-Effekte */
        .progress-step:not(.active) .progress-step-link:hover .progress-circle,
        .progress-step:not(.active) > div:not(.progress-label):hover .progress-circle {
            transform: translateY(-5px) scale(1.05);
            box-shadow: 0 8px 15px rgba(0,0,0,0.15);
        }
        .progress-step:not(.active):hover .progress-label {
            opacity: 1;
        }
    </style>
</head>
<body>

<div class="progress-bar-container-v3">
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

        // URL zum Schritt (behält die WordPress-Funktionen bei, da sie in deinem Quellcode waren)
        // In einem Nicht-WordPress-Kontext müssten get_permalink() und add_query_arg() durch einfache Links ersetzt werden.
        $step_url = add_query_arg('step', $step_slug, get_permalink());
        ?>
        <div class="<?php echo esc_attr($step_class); ?>" id="progress-step-<?php echo esc_attr($step_data['number']); ?>-v3">
            <?php
            // Die Logik für den Inhalt des Kreises und die Klickbarkeit wird hier vereinheitlicht,
            // um dem Design-Beispiel perfekt zu entsprechen:
            // - Abgeschlossene, nicht-aktive Schritte sind Links mit Checkmark.
            // - Aktive oder ausstehende Schritte sind keine Links und zeigen die Nummer.
            if ($is_completed && !$is_active) : ?>
                <a href="<?php echo esc_url($step_url); ?>" class="progress-step-link">
                    <div class="progress-circle">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="progress-label"><?php echo esc_html($step_data['label']); ?></div>
                </a>
            <?php else: ?>
                <div>
                    <div class="progress-circle">
                        <?php echo esc_html($step_data['number']); ?>
                    </div>
                    <div class="progress-label"><?php echo esc_html($step_data['label']); ?></div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

</body>
</html>