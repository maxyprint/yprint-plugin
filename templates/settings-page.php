<?php
// Sicherheitscheck: Direkten Zugriff auf diese Datei verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Aktuelle Einstellungen abrufen
$options = get_option('vectorize_wp_options', array(
    'api_key' => '',
    'max_upload_size' => 5,
    'default_output_format' => 'svg',
    'test_mode' => 'off',
    'vectorization_engine' => 'inkscape',
));

// API-Status
$api_status = '';
$api_status_class = '';

// Einstellungen speichern
if (isset($_POST['vectorize_wp_save_settings']) && check_admin_referer('vectorize_wp_settings', 'vectorize_wp_settings_nonce')) {
    // Alte API-Schlüssel speichern zur Vergleich
    $old_api_key = $options['api_key'];
    
    // Neue Einstellungen speichern
$options['api_key'] = sanitize_text_field($_POST['api_key']);
$options['max_upload_size'] = absint($_POST['max_upload_size']);
$options['default_output_format'] = sanitize_text_field($_POST['default_output_format']);
$options['test_mode'] = sanitize_text_field($_POST['test_mode']);
$options['vectorization_engine'] = sanitize_text_field($_POST['vectorization_engine']);

    update_option('vectorize_wp_options', $options);
    
    echo '<div class="notice notice-success is-dismissible"><p>' . __('Einstellungen gespeichert.', 'vectorize-wp') . '</p></div>';
    
    // API-Schlüssel testen, wenn er sich geändert hat
    if ($old_api_key !== $options['api_key'] && !empty($options['api_key'])) {
        // API-Instanz erstellen
        $api = new Vectorize_WP_Vectorize_API($options['api_key']);
        $api_result = $api->validate_api_key();
        
        if ($api_result === true) {
            $api_status = __('API-Schlüssel ist gültig.', 'vectorize-wp');
            $api_status_class = 'notice-success';
        } else {
            $error_message = is_wp_error($api_result) ? $api_result->get_error_message() : __('API-Schlüssel konnte nicht validiert werden.', 'vectorize-wp');
            $api_status = __('API-Schlüssel ist ungültig: ', 'vectorize-wp') . $error_message;
            $api_status_class = 'notice-error';
        }
        
        if (!empty($api_status)) {
            echo '<div class="notice ' . $api_status_class . ' is-dismissible"><p>' . $api_status . '</p></div>';
        }
    }
}

// API-Schlüssel testen Button
if (isset($_POST['vectorize_wp_test_api_key']) && check_admin_referer('vectorize_wp_settings', 'vectorize_wp_settings_nonce')) {
    if (empty($options['api_key']) && $options['vectorization_engine'] === 'api') {
        $api_status = __('Bitte gib einen API-Schlüssel ein, um die API zu testen.', 'vectorize-wp');
        $api_status_class = 'notice-warning';
    } else {
        // Je nach ausgewählter Engine testen
        if ($options['vectorization_engine'] === 'api') {
            // API-Instanz erstellen
            $api = new Vectorize_WP_Vectorize_API($options['api_key']);
            $api_result = $api->validate_api_key();
            
            if ($api_result === true) {
                $api_status = __('API-Schlüssel ist gültig! Die Verbindung zur Vectorize.ai API funktioniert.', 'vectorize-wp');
                $api_status_class = 'notice-success';
            } else {
                $error_message = is_wp_error($api_result) ? $api_result->get_error_message() : __('API-Schlüssel konnte nicht validiert werden.', 'vectorize-wp');
                $api_status = __('API-Test fehlgeschlagen: ', 'vectorize-wp') . '<pre style="max-height: 300px; overflow: auto; background: #f7f7f7; padding: 10px; border: 1px solid #ddd;">' . $error_message . '</pre>';
                $api_status_class = 'notice-error';
            }
        } elseif ($options['vectorization_engine'] === 'inkscape') {
            // Inkscape CLI testen
            $inkscape_cli = new Vectorize_WP_Inkscape_CLI();
            if ($inkscape_cli->is_available()) {
                $api_status = __('Inkscape CLI ist verfügbar und betriebsbereit!', 'vectorize-wp');
                $api_status_class = 'notice-success';
            } else {
                $api_status = __('Inkscape CLI ist nicht verfügbar. Bitte stelle sicher, dass Inkscape installiert ist und im Systempfad liegt.', 'vectorize-wp');
                $api_status_class = 'notice-error';
            }
        } else {
            // YPrint Vectorizer testen
            if (class_exists('YPrint_Vectorizer')) {
                $yprint_vectorizer = YPrint_Vectorizer::get_instance();
                $potrace_available = $yprint_vectorizer->check_potrace_exists();
                if ($potrace_available) {
                    $api_status = __('YPrint Vectorizer ist verfügbar und potrace wurde gefunden. Bereit für die Vektorisierung!', 'vectorize-wp');
                    $api_status_class = 'notice-success';
                } else {
                    $api_status = __('YPrint Vectorizer ist verfügbar, aber potrace wurde nicht gefunden. Die Funktionalität könnte eingeschränkt sein.', 'vectorize-wp');
                    $api_status_class = 'notice-warning';
                }
            } else {
                $api_status = __('YPrint Vectorizer ist nicht verfügbar. Bitte stelle sicher, dass die Klasse korrekt installiert ist.', 'vectorize-wp');
                $api_status_class = 'notice-error';
            }
        }
    }
    
    if (!empty($api_status)) {
        echo '<div class="notice ' . $api_status_class . ' is-dismissible"><p>' . $api_status . '</p></div>';
    }
}
?>

<div class="wrap vectorize-wp-settings">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="">
        <?php wp_nonce_field('vectorize_wp_settings', 'vectorize_wp_settings_nonce'); ?>
        
        <table class="form-table">
            <tbody>
                <tr>
                    <th scope="row">
                        <label for="api_key"><?php _e('Vectorize.ai API-Schlüssel', 'vectorize-wp'); ?></label>
                    </th>
                    <td>
                        <input type="text" id="api_key" name="api_key" class="regular-text" 
                               value="<?php echo esc_attr($options['api_key']); ?>" />
                        <p class="description">
                            <?php _e('Dein API-Schlüssel von vectorize.ai im Format "API-ID:API-Secret". Erhältlich auf <a href="https://vectorize.ai" target="_blank">vectorize.ai</a>.', 'vectorize-wp'); ?>
                        </p>
                        <input type="submit" name="vectorize_wp_test_api_key" class="button button-secondary" 
                           value="<?php _e('API-Schlüssel testen', 'vectorize-wp'); ?>" />
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="max_upload_size"><?php _e('Maximale Upload-Größe (MB)', 'vectorize-wp'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="max_upload_size" name="max_upload_size" class="small-text" 
                               value="<?php echo esc_attr($options['max_upload_size']); ?>" min="1" max="20" />
                        <p class="description">
                            <?php _e('Maximale Dateigröße für Bildupload in Megabyte (1-20).', 'vectorize-wp'); ?>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="default_output_format"><?php _e('Standard-Ausgabeformat', 'vectorize-wp'); ?></label>
                    </th>
                    <td>
                        <select id="default_output_format" name="default_output_format">
                            <option value="svg" <?php selected($options['default_output_format'], 'svg'); ?>>
                                <?php _e('SVG', 'vectorize-wp'); ?>
                            </option>
                            <option value="ai" <?php selected($options['default_output_format'], 'ai'); ?>>
                                <?php _e('AI (Adobe Illustrator)', 'vectorize-wp'); ?>
                            </option>
                            <option value="eps" <?php selected($options['default_output_format'], 'eps'); ?>>
                                <?php _e('EPS', 'vectorize-wp'); ?>
                            </option>
                        </select>
                        <p class="description">
                            <?php _e('Standard-Format für heruntergeladene Vektordateien.', 'vectorize-wp'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
    <th scope="row">
        <label for="test_mode"><?php _e('API-Testmodus', 'vectorize-wp'); ?></label>
    </th>
    <td>
        <select id="test_mode" name="test_mode">
            <option value="off" <?php selected($options['test_mode'], 'off'); ?>>
                <?php _e('Aus (Produktionsmodus)', 'vectorize-wp'); ?>
            </option>
            <option value="test" <?php selected($options['test_mode'], 'test'); ?>>
                <?php _e('Test (kostenloser Testmodus)', 'vectorize-wp'); ?>
            </option>
            <option value="test_preview" <?php selected($options['test_mode'], 'test_preview'); ?>>
                <?php _e('Test mit Vorschau', 'vectorize-wp'); ?>
            </option>
        </select>
        <p class="description">
            <?php _e('Wähle "Test" für kostenloses Testen während der Entwicklung.', 'vectorize-wp'); ?>
            <br>
            <?php _e('"Test mit Vorschau" bietet zusätzlich eine Vorschau des Ergebnisses.', 'vectorize-wp'); ?>
        </p>
    </td>
</tr>

<tr>
    <th scope="row">
        <label for="vectorization_engine"><?php _e('Vektorisierungs-Engine', 'vectorize-wp'); ?></label>
    </th>
    <td>
    <select id="vectorization_engine" name="vectorization_engine">
    <option value="api" <?php selected($options['vectorization_engine'], 'api'); ?>>
        <?php _e('Vectorize.ai API (online, kostenpflichtig)', 'vectorize-wp'); ?>
    </option>
    <option value="inkscape" <?php selected($options['vectorization_engine'], 'inkscape'); ?>>
        <?php _e('Inkscape CLI (lokal, kostenlos)', 'vectorize-wp'); ?>
    </option>
    <option value="yprint" <?php selected($options['vectorization_engine'], 'yprint'); ?>>
        <?php _e('YPrint Vectorizer (lokal, optimiert)', 'vectorize-wp'); ?>
    </option>
    </select>
    <p class="description">
    <?php _e('Wähle zwischen der Online-API, der lokalen Inkscape-Installation oder dem YPrint Vectorizer.', 'vectorize-wp'); ?>
    <br>
    <?php 
    $inkscape_cli = new Vectorize_WP_Inkscape_CLI();
    if ($inkscape_cli->is_available()) {
        echo '<span style="color:green;">' . __('Inkscape wurde gefunden und ist einsatzbereit.', 'vectorize-wp') . '</span>';
    } else {
        echo '<span style="color:red;">' . __('Inkscape wurde nicht gefunden. Bitte installiere Inkscape, falls du es verwenden möchtest.', 'vectorize-wp') . '</span>';
    }
    ?>
    <br>
    <?php
    if (class_exists('YPrint_Vectorizer')) {
        echo '<span style="color:green;">' . __('YPrint Vectorizer ist aktiv und einsatzbereit.', 'vectorize-wp') . '</span>';
        
        // Prüfen, ob Potrace verfügbar ist
        $potrace_available = YPrint_Vectorizer::get_instance()->check_potrace_exists();
        if (!$potrace_available) {
            echo '<br><span style="color:orange;">' . __('Hinweis: Potrace wurde nicht gefunden, was die Funktionalität des YPrint Vectorizers einschränken könnte.', 'vectorize-wp') . '</span>';
        }
    } else {
        echo '<span style="color:red;">' . __('YPrint Vectorizer ist nicht verfügbar.', 'vectorize-wp') . '</span>';
    }
    ?>
    </p>
    <p>
        <input type="submit" name="vectorize_wp_test_api_key" class="button button-secondary" 
               value="<?php _e('Ausgewählte Engine testen', 'vectorize-wp'); ?>" />
    </p>
    </td>
</tr>
                </tr>
            </tbody>
        </table>
        
        <p class="submit">
            <input type="submit" name="vectorize_wp_save_settings" class="button button-primary" 
                   value="<?php _e('Einstellungen speichern', 'vectorize-wp'); ?>" />
        </p>
    </form>
    
    <div class="vectorize-wp-info-section">
        <h2><?php _e('Hilfe & Informationen', 'vectorize-wp'); ?></h2>
        
        <div class="vectorize-wp-info-box">
            <h3><?php _e('Wie funktioniert Vectorize WP?', 'vectorize-wp'); ?></h3>
            <p>
                <?php _e('Vectorize WP verwendet die vectorize.ai API, um hochgeladene Bilder in SVG-Dateien umzuwandeln. ' .
                         'Nach der Umwandlung kannst du das SVG mit dem eingebauten SVG-Editor bearbeiten, ' .
                         'in der WordPress-Mediathek speichern oder herunterladen.', 'vectorize-wp'); ?>
            </p>
        </div>
        
        <div class="vectorize-wp-info-box">
        <h3><?php _e('Wie bekomme ich einen API-Schlüssel?', 'vectorize-wp'); ?></h3>
            <p>
                <?php _e('Um einen API-Schlüssel zu erhalten, besuche <a href="https://vectorize.ai" target="_blank">vectorize.ai</a>, ' .
                         'erstelle ein Konto und abonniere einen Plan. Du findest deinen API-Schlüssel dann in deinem Account-Bereich.', 'vectorize-wp'); ?>
            </p>
        </div>
        
        <div class="vectorize-wp-info-box">
            <h3><?php _e('Was ist SVG?', 'vectorize-wp'); ?></h3>
            <p>
                <?php _e('SVG (Scalable Vector Graphics) ist ein Vektorgrafikformat, das beliebig skaliert werden kann, ohne an Qualität zu verlieren. ' .
                         'SVG-Dateien sind ideal für Logos, Icons und Illustrationen, die in verschiedenen Größen verwendet werden.', 'vectorize-wp'); ?>
            </p>
        </div>
        
        <div class="vectorize-wp-info-box">
            <h3><?php _e('Unterstützte Bildformate', 'vectorize-wp'); ?></h3>
            <p>
                <?php _e('Vectorize WP unterstützt folgende Bildformate für die Vektorisierung:', 'vectorize-wp'); ?>
            </p>
            <ul>
                <li><?php _e('JPEG (.jpg, .jpeg)', 'vectorize-wp'); ?></li>
                <li><?php _e('PNG (.png)', 'vectorize-wp'); ?></li>
                <li><?php _e('GIF (.gif)', 'vectorize-wp'); ?></li>
                <li><?php _e('BMP (.bmp)', 'vectorize-wp'); ?></li>
                <li><?php _e('WebP (.webp)', 'vectorize-wp'); ?></li>
            </ul>
        </div>
    </div>
</div>

<style>
.vectorize-wp-info-section {
    margin-top: 30px;
    border-top: 1px solid #ddd;
    padding-top: 20px;
}

.vectorize-wp-info-box {
    background: #fff;
    border: 1px solid #ddd;
    border-left: 4px solid #0073aa;
    box-shadow: 0 1px 1px rgba(0, 0, 0, 0.04);
    margin-bottom: 20px;
    padding: 12px 15px;
}

.vectorize-wp-info-box h3 {
    margin-top: 0;
    margin-bottom: 10px;
}

.vectorize-wp-info-box ul {
    margin-left: 20px;
    list-style-type: disc;
}
</style>