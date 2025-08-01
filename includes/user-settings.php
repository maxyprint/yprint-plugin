<?php
/**
 * Erweiterte User Settings System für YPrint
 *
 * @package YPrint
 * @since 1.2.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Initialisiere das User-Settings-System
 */
function yprint_init_settings_system() {
    // Registriere alle benötigten Shortcodes
    add_shortcode('yprint_user_settings', 'yprint_user_settings_shortcode');
    add_shortcode('yprint_personal_settings', 'yprint_personal_settings_shortcode');
    add_shortcode('yprint_billing_settings', 'yprint_billing_settings_shortcode');
    add_shortcode('yprint_shipping_settings', 'yprint_shipping_settings_shortcode');
    add_shortcode('yprint_payment_settings', 'yprint_payment_settings_shortcode');
    add_shortcode('yprint_privacy_settings', 'yprint_privacy_settings_shortcode');
    add_shortcode('yprint_notification_settings', 'yprint_notification_settings_shortcode');
    
    // Styles und Scripts für User Settings
    add_action('wp_enqueue_scripts', 'yprint_enqueue_settings_assets');
    
    // Overlay-Styles für Benachrichtigungen
    add_action('wp_head', 'yprint_add_overlay_styles');
    
    // AJAX-Handler für zusätzliche Funktionen
    add_action('wp_ajax_yprint_save_payment_method', 'yprint_save_payment_method_callback');
    add_action('wp_ajax_yprint_delete_payment_method', 'yprint_delete_payment_method_callback');
    add_action('wp_ajax_yprint_set_default_payment', 'yprint_set_default_payment_callback');
    add_action('wp_ajax_yprint_save_notification_settings', 'yprint_save_notification_settings_callback');
    add_action('wp_ajax_yprint_save_privacy_settings', 'yprint_save_privacy_settings_callback');
    
    // Beim Checkout die Benutzerdaten synchronisieren
    add_action('woocommerce_checkout_update_order_meta', 'yprint_sync_user_settings_with_checkout');
    
    // Datenbanktabellen erstellen, falls sie nicht existieren
    yprint_create_settings_tables();
}
add_action('init', 'yprint_init_settings_system');

/**
 * Enqueue Settings Assets
 */
function yprint_enqueue_settings_assets() {
    global $post;
    
    // Nur laden wenn User Settings Shortcode vorhanden
    if (is_a($post, 'WP_Post') && (
        has_shortcode($post->post_content, 'yprint_user_settings') ||
        has_shortcode($post->post_content, 'yprint_personal_settings') ||
        has_shortcode($post->post_content, 'yprint_billing_settings') ||
        has_shortcode($post->post_content, 'yprint_shipping_settings') ||
        has_shortcode($post->post_content, 'yprint_payment_settings') ||
        has_shortcode($post->post_content, 'yprint_notification_settings') ||
        has_shortcode($post->post_content, 'yprint_privacy_settings')
    )) {
        
        // Address Manager Assets
        wp_enqueue_style('yprint-address-manager', YPRINT_PLUGIN_URL . 'assets/css/yprint-address-manager.css', array(), YPRINT_PLUGIN_VERSION);
        wp_enqueue_script('yprint-address-manager', YPRINT_PLUGIN_URL . 'assets/js/yprint-address-manager.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
        
        // Settings-spezifische Styles inline hinzufügen
        wp_add_inline_style('yprint-address-manager', yprint_settings_styles());
        
        // Address Manager JavaScript-Variablen
        wp_localize_script('yprint-address-manager', 'yprint_address_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yprint_save_address_action'),
            'messages' => array(
                'delete_address' => __('Adresse wirklich löschen?', 'yprint-plugin'),
                'set_as_default' => __('Als Standard setzen', 'yprint-plugin'),
                'standard_address' => __('Standard-Adresse', 'yprint-plugin'),
            ),
        ));
        
        // Settings JavaScript für Events
        wp_enqueue_script('yprint-settings-js', YPRINT_PLUGIN_URL . 'assets/js/yprint-settings.js', array('jquery', 'yprint-address-manager'), YPRINT_PLUGIN_VERSION, true);
        
        wp_localize_script('yprint-settings-js', 'yprint_settings_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('yprint_settings_nonce'),
        ));
    }
}

/**
 * Erstelle die benötigten Datenbanktabellen
 */
function yprint_create_settings_tables() {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    
    // Bestehende Tabellen (unverändert)
    $personal_data_table = $wpdb->prefix . 'personal_data';
    $sql_personal = "CREATE TABLE IF NOT EXISTS $personal_data_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        first_name varchar(100) NOT NULL,
        last_name varchar(100) NOT NULL,
        birthdate date NULL,
        phone varchar(20) NULL,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    // Payment Methods Tabelle (unverändert)
    $payment_methods_table = $wpdb->prefix . 'payment_methods';
    $sql_payment = "CREATE TABLE IF NOT EXISTS $payment_methods_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        method_type varchar(50) NOT NULL,
        method_data text NOT NULL,
        is_default tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id)
    ) $charset_collate;";
    
    // Notification Settings (unverändert)
    $notification_settings_table = $wpdb->prefix . 'notification_settings';
    $sql_notification = "CREATE TABLE IF NOT EXISTS $notification_settings_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        email_orders tinyint(1) NOT NULL DEFAULT 1,
        email_marketing tinyint(1) NOT NULL DEFAULT 1,
        email_news tinyint(1) NOT NULL DEFAULT 1,
        sms_orders tinyint(1) NOT NULL DEFAULT 0,
        sms_marketing tinyint(1) NOT NULL DEFAULT 0,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    // Privacy Settings (erweitert um consent_version)
    $privacy_settings_table = $wpdb->prefix . 'privacy_settings';
    $sql_privacy = "CREATE TABLE IF NOT EXISTS $privacy_settings_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        data_sharing tinyint(1) NOT NULL DEFAULT 1,
        data_collection tinyint(1) NOT NULL DEFAULT 1,
        personalized_ads tinyint(1) NOT NULL DEFAULT 1,
        preferences_analysis tinyint(1) NOT NULL DEFAULT 1,
        consent_version varchar(20) NOT NULL DEFAULT '1.0',
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset_collate;";
    
    // NEUE TABELLE: Consent Management (DSGVO + TTDSG)
    $consents_table = $wpdb->prefix . 'yprint_consents';
    $sql_consents = "CREATE TABLE IF NOT EXISTS $consents_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NULL,
        session_id varchar(191) NULL,
        consent_type enum('PRIVACY_POLICY','COOKIE_ESSENTIAL','COOKIE_ANALYTICS','COOKIE_MARKETING','COOKIE_FUNCTIONAL') NOT NULL,
        granted tinyint(1) NOT NULL DEFAULT 0,
        version varchar(20) NOT NULL DEFAULT '1.0',
        ip_address varchar(45) NULL,
        user_agent text NULL,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        INDEX idx_user_id (user_id),
        INDEX idx_session_id (session_id),
        INDEX idx_consent_type (consent_type),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB $charset_collate;";
    
    // NEUE TABELLE: Legal Texts (Admin-editierbar)
    $legal_texts_table = $wpdb->prefix . 'yprint_legal_texts';
    $sql_legal_texts = "CREATE TABLE IF NOT EXISTS $legal_texts_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        text_key varchar(100) NOT NULL,
        content longtext NOT NULL,
        version varchar(20) NOT NULL DEFAULT '1.0',
        language varchar(5) NOT NULL DEFAULT 'de',
        is_active tinyint(1) NOT NULL DEFAULT 1,
        created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY unique_key_lang_version (text_key, language, version),
        INDEX idx_text_key (text_key),
        INDEX idx_is_active (is_active)
    ) ENGINE=InnoDB $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql_personal);
    dbDelta($sql_payment);
    dbDelta($sql_notification);
    dbDelta($sql_privacy);
    dbDelta($sql_consents);
    dbDelta($sql_legal_texts);
    
    // Standard Legal Texts einfügen
    yprint_insert_default_legal_texts();
}

function yprint_insert_default_legal_texts() {
    global $wpdb;
    
    $table = $wpdb->prefix . 'yprint_legal_texts';
    
    $default_texts = array(
        array(
            'text_key' => 'COOKIE_BANNER_TITLE',
            'content' => 'Diese Website verwendet Cookies',
            'version' => '1.0'
        ),
        array(
            'text_key' => 'COOKIE_BANNER_DESCRIPTION',
            'content' => 'Wir verwenden Cookies, um dir die bestmögliche Erfahrung auf unserer Website zu bieten. Einige sind essenziell, andere helfen uns dabei, diese Website und deine Erfahrung zu verbessern.',
            'version' => '1.0'
        ),
        array(
            'text_key' => 'REGISTRATION_CONSENT_TEXT',
            'content' => 'Ich habe die <a href="/datenschutz" target="_blank">Datenschutzerklärung</a> gelesen und stimme der darin beschriebenen Verarbeitung meiner Daten zur Erbringung des Dienstes zu.',
            'version' => '1.0'
        ),
        array(
            'text_key' => 'PRIVACY_POLICY_CONTENT',
            'content' => '<h1>Datenschutzerklärung</h1><p>Hier steht deine vollständige Datenschutzerklärung...</p>',
            'version' => '1.0'
        )
    );
    
    foreach ($default_texts as $text) {
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE text_key = %s AND version = %s",
            $text['text_key'],
            $text['version']
        ));
        
        if (!$exists) {
            $wpdb->insert($table, array_merge($text, array(
                'language' => 'de',
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql')
            )));
        }
    }
}

/**
 * Hauptshortcode für die gesamte Einstellungsseite
 * 
 * Usage: [yprint_user_settings]
 */
function yprint_user_settings_shortcode() {
    ob_start();
    
    // Google Fonts für Roboto
    echo '<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@700&display=swap" rel="stylesheet">';
    
    // Go Back Button - nur auf Desktop
    echo '<a href="https://yprint.de/dashboard" class="go-back-button" style="
        background: transparent;
        border: none;
        font-family: \'Roboto\', sans-serif;
        font-size: 15px;
        color: #2997FF;
        cursor: pointer;
        padding: 0;
        margin-bottom: 20px;
        display: none;
        align-items: center;
        font-weight: bold;
        text-decoration: none;
    ">
        ← go back
    </a>';
    
    // Benutzer muss angemeldet sein
    if (!is_user_logged_in()) {
        return '<div class="yprint-login-required">
            <p>Bitte melde dich an, um deine Einstellungen zu verwalten.</p>
            <a href="' . esc_url(wc_get_page_permalink('myaccount')) . '" class="yprint-button">Zum Login</a>
        </div>';
    }
    
    // Aktuellen Tab abrufen (Standard: 'personal')
    $current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'personal';
    
    // Meldung verarbeiten, falls vorhanden
    $message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
    $message_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'info';
    
    if ($message) {
        echo '<div class="yprint-message yprint-message-' . esc_attr($message_type) . '">';
        echo esc_html($message);
        echo '</div>';
    }
    
    // Tabs definieren
    $tabs = array(
        'personal' => array(
            'title' => 'Persönliche Daten',
            'icon' => 'user'
        ),
        'billing' => array(
            'title' => 'Rechnungsadresse',
            'icon' => 'file-invoice'
        ),
        'shipping' => array(
            'title' => 'Lieferadressen',
            'icon' => 'shipping-fast'
        ),
        'privacy' => array(
            'title' => 'Datenschutz',
            'icon' => 'shield-alt'
        ),
    );
    
    // Beginn der Einstellungsseite
    ?>
    <div class="yprint-settings-container">
        <!-- Kompakter Header -->
        <div class="yprint-settings-header">
            <h1>Mein Konto</h1>
            <p class="yprint-settings-intro">Verwalte deine Einstellungen</p>
        </div>

        <!-- Mobile-first Navigation Grid -->
        <div class="yprint-settings-tabs-container">
            <div class="yprint-settings-grid">
                <?php foreach ($tabs as $tab_id => $tab_info) : 
                    $active_class = ($current_tab === $tab_id) ? ' active' : '';
                    ?>
                    <a href="?tab=<?php echo esc_attr($tab_id); ?>" class="settings-item<?php echo esc_attr($active_class); ?>">
                        <div class="settings-item-left">
                            <div class="settings-icon">
                                <i class="fas fa-<?php echo esc_attr($tab_info['icon']); ?>"></i>
                            </div>
                            <div class="settings-title"><?php echo esc_html($tab_info['title']); ?></div>
                        </div>
                        <div class="settings-chevron">
                            <i class="fas fa-chevron-right"></i>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Desktop Content Area (nur auf Desktop sichtbar) -->
            <div class="yprint-settings-content">
                <?php
                // Nur auf Desktop den Inhalt direkt anzeigen
                if ($current_tab !== 'overview') {
                    switch ($current_tab) {
                        case 'personal':
                            echo do_shortcode('[yprint_personal_settings]');
                            break;
                        case 'billing':
                            echo do_shortcode('[yprint_billing_settings]');
                            break;
                        case 'shipping':
                            echo do_shortcode('[yprint_shipping_settings]');
                            break;
                        case 'privacy':
                            echo do_shortcode('[yprint_privacy_settings]');
                            break;
                    }
                }
                ?>
            </div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // Mobile Tab Selection Handler
        $("#yprint-mobile-tab-select").on("change", function() {
            var selectedTab = $(this).val();
            window.location.href = window.location.pathname + "?tab=" + selectedTab;
        });
        
        // Read URL parameters and set tab accordingly
        function getParameterByName(name, url) {
            if (!url) url = window.location.href;
            name = name.replace(/[\[\]]/g, "\\$&");
            var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
                results = regex.exec(url);
            if (!results) return null;
            if (!results[2]) return "";
            return decodeURIComponent(results[2].replace(/\+/g, " "));
        }
        
        var tabParam = getParameterByName("tab");
        if (tabParam) {
            // Desktop-Tabs
            $(".yprint-tab").removeClass("active");
            $(".yprint-tab[href=\"?tab=" + tabParam + "\"]").addClass("active");
            
            // Mobile-Dropdown
            $("#yprint-mobile-tab-select").val(tabParam);
        }
        
        // Hide success message after 3 seconds
        setTimeout(function() {
            $('.yprint-message-success').fadeOut(500);
        }, 3000);
    });
    </script>
    <?php
    
    // CSS-Styles für die Einstellungsseite
    echo yprint_settings_styles();
    
    return ob_get_clean();
}

/**
 * CSS-Styles für die Einstellungsseite
 */
function yprint_settings_styles() {
    ob_start();
    ?>
    <style>
        /* Mobile-first Hauptcontainer - Optimiert für Header/Footer Navigation */
        .yprint-settings-container {
            font-family: 'SF Pro Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
            padding: 12px 16px 16px 16px;
            margin: 0;
            background-color: #F8F9FB;
            color: #1A1A1A;
            min-height: calc(100vh - 120px);
            padding-bottom: 100px;
            position: relative;
        }

        /* Address Manager Integration Styles */
        .yprint-saved-addresses {
            margin-bottom: 20px;
        }

        .address-cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .address-card {
            background: #FFFFFF;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 16px;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .address-card:hover {
            border-color: #2997FF;
            box-shadow: 0 4px 12px rgba(41, 151, 255, 0.1);
        }

        .address-card.selected {
            border-color: #2997FF;
            background-color: #F0F8FF;
        }

        .address-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .address-card-title {
            font-weight: 600;
            color: #1A1A1A;
            display: flex;
            align-items: center;
        }

        .address-card-actions {
            display: flex;
            gap: 8px;
        }

        .btn-address-action {
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .btn-address-action:hover {
            color: #2997FF;
            background-color: #F0F8FF;
        }

        .address-card-content {
            margin-bottom: 12px;
            font-size: 14px;
            line-height: 1.4;
            color: #666;
        }

        .address-card-footer {
            margin-top: 12px;
        }

        .btn-select-address {
            width: 100%;
            background-color: #2997FF;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-select-address:hover {
            background-color: #0080FF;
        }

        .add-new-address-card {
            border: 2px dashed #e5e5e5;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
            text-align: center;
            transition: all 0.2s ease;
        }

        .add-new-address-card:hover {
            border-color: #2997FF;
            background-color: #F9FBFF;
        }

        /* Modal Styles für Address Manager */
        .yprint-address-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .address-modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }

        .address-modal-content {
            background: white;
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            z-index: 10001;
        }

        .address-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid #e5e5e5;
        }

        .btn-close-modal {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: #666;
            padding: 4px;
        }

        .address-form-actions {
            display: flex;
            gap: 12px;
            margin-top: 20px;
            justify-content: flex-end;
        }

        .change-address-link {
            margin-top: 16px;
            text-align: center;
        }

        .change-address-link button {
            background: none;
            border: none;
            color: #2997FF;
            cursor: pointer;
            text-decoration: underline;
        }

        /* Loading States */
        .loading-addresses {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .loading-addresses i {
            margin-right: 8px;
        }

        /* Address Selection Options */
        .address-selection-options {
            background: #f9f9f9;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 24px;
        }

        .address-option {
            margin-bottom: 12px;
        }

        .address-option-label {
            display: flex;
            align-items: flex-start;
            cursor: pointer;
            padding: 16px;
            border: 2px solid #e5e5e5;
            border-radius: 10px;
            transition: all 0.2s ease;
        }

        .address-option-label:hover {
            border-color: #2997FF;
            background-color: #F9FBFF;
        }

        .address-option input[type="radio"] {
            margin-right: 12px;
            margin-top: 2px;
        }

        .address-option input[type="radio"]:checked + .address-option-content {
            color: #2997FF;
        }

        .address-option-label:has(input:checked) {
            border-color: #2997FF;
            background-color: #F0F8FF;
        }

        .address-option-content {
            flex: 1;
        }

        .address-option-content strong {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
        }

        .address-option-content small {
            color: #666;
            font-size: 13px;
        }

        /* Mobile Anpassungen für Address Options */
        @media (max-width: 767px) {
            .address-selection-options {
                padding: 16px;
                margin-bottom: 20px;
            }
            
            .address-option-label {
                padding: 12px;
            }
            
            .address-cards-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

/* Navigation Area - Niedrigere z-index */
.yprint-settings-tabs-container {
    position: relative;
    z-index: 1;
}

.yprint-settings-grid {
    position: relative;
    z-index: 1;
}

/* Content Area - Höhere z-index für Überlagerung */
.yprint-settings-content {
    position: relative;
    z-index: 10;
    background-color: #FFFFFF;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    padding: 30px;
    border: 1px solid #e5e5e5;
    margin-top: -20px; /* Überlappt die Navigation leicht */
}

/* Go Back Button - Nur Desktop */
.go-back-button {
            display: none !important; /* Standard: Versteckt auf Mobile */
        }
        
        /* Privacy Action Buttons */
        .privacy-action-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            border-color: #2997FF;
        }
        
        .privacy-action-button.danger:hover {
            background: #fef2f2;
            border-color: #dc2626;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);
        }
        
        /* Mobile Privacy Buttons */
        @media (max-width: 768px) {
            .privacy-buttons-grid {
                gap: 12px !important;
            }
            
            .privacy-action-button {
                padding: 18px 20px !important;
                font-size: 16px !important;
            }
        }
        
        /* Mobile Content Sections */
        @media (max-width: 767px) {
            .settings-section {
                margin: 15px -16px 0 -16px;
                border-radius: 0;
                border-left: none;
                border-right: none;
                padding: 20px 16px;
            }
        }

/* Kompakter Header für Mobile */
.yprint-settings-header {
    margin-bottom: 16px;
    text-align: center;
}
        
        .yprint-settings-header h1 {
            font-size: 24px;
            font-weight: 600;
            color: #1A1A1A;
            margin-bottom: 8px;
        }
        
        .yprint-settings-intro {
            font-size: 14px;
            color: #7D7D7D;
            line-height: 1.4;
        }
        
        /* Mobile Settings Grid */
        .yprint-settings-grid {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        /* Settings Item - Mobile-first Design */
        .settings-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background-color: #FFFFFF;
            padding: 12px 16px;
            border-radius: 12px;
            box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.02);
            transition: background-color 0.2s ease;
            text-decoration: none;
            color: inherit;
            min-height: 48px;
        }
        
        .settings-item:hover,
        .settings-item:focus {
            background-color: #F0F2F5;
            text-decoration: none;
            color: inherit;
        }
        
        .settings-item.active {
            background-color: #EDF1F7;
            border-left: 4px solid #2997FF;
        }
        
        .settings-item-left {
            display: flex;
            align-items: center;
            flex: 1;
        }
        
        .settings-icon {
            background-color: #EDF1F7;
            padding: 8px;
            border-radius: 8px;
            margin-right: 12px;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .settings-icon i {
            font-size: 14px;
            color: #2997FF;
        }
        
        .settings-title {
            font-size: 16px;
            font-weight: 500;
            color: #1A1A1A;
        }
        
        .settings-chevron {
            color: #C4C4C4;
            font-size: 12px;
        }
        
        /* Desktop Anpassungen */
@media (min-width: 768px) {
    .yprint-settings-container {
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px 20px;
        background-color: transparent;
        min-height: auto;
        padding-bottom: 30px;
    }
    
    /* Go Back Button nur auf Desktop anzeigen */
    .go-back-button {
        display: flex !important;
    }
    
    /* Visuelle Trennung für Settings-Bereich auf Desktop */
    .settings-section {
        background-color: #FFFFFF;
        padding: 24px;
        border-radius: 16px;
        box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.04);
        margin-top: 32px;
    }
    
    .settings-section h3 {
        margin-top: 0;
        margin-bottom: 20px;
        font-size: 18px;
        font-weight: 600;
        color: #1A1A1A;
        border-bottom: 1px solid #e5e5e5;
        padding-bottom: 12px;
    }
}
            
            .yprint-settings-header {
                text-align: left;
                margin-bottom: 40px;
            }
            
            .yprint-settings-header h1 {
                font-size: 32px;
                margin-bottom: 10px;
            }
            
            .yprint-settings-intro {
                font-size: 16px;
                max-width: 600px;
            }
            
            
            
            .settings-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background-color: #FFFFFF;
    padding: 16px 20px; /* Größere Touch-Targets für Mobile */
    border-radius: 12px;
    box-shadow: 0px 2px 4px rgba(0, 0, 0, 0.02);
    transition: all 0.2s ease;
    text-decoration: none;
    color: inherit;
    min-height: 56px; /* Größere Mindesthöhe für bessere Touch-Ergonomie */
    margin-bottom: 8px;
    position: relative;
    border: 1px solid transparent;
}

.settings-item:hover,
.settings-item:focus {
    background-color: #F0F2F5;
    text-decoration: none;
    color: inherit;
    transform: translateY(-1px);
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.04);
}

.settings-item:active {
    transform: translateY(0);
    background-color: #E8F0FE;
}

.settings-item.active {
    background-color: #EDF1F7;
    border-color: #2997FF;
    border-left: 4px solid #2997FF;
}

/* Interaktive Chevron-Animation */
.settings-chevron {
    color: #C4C4C4;
    font-size: 12px;
    transition: transform 0.3s ease, color 0.2s ease;
}

.settings-item:hover .settings-chevron {
    color: #2997FF;
    transform: translateX(2px);
}

.settings-chevron.rotate {
    transform: rotate(180deg);
}
            
        /* Desktop Layout mit Sidebar */
.yprint-settings-tabs-container {
    display: flex;
    gap: 30px;
    position: relative;
}

.yprint-settings-grid {
    flex: 0 0 250px;
    gap: 5px;
    position: sticky;
    top: 30px;
    height: fit-content;
    z-index: 1;
}

.yprint-settings-content {
    flex: 1;
    min-width: 0; /* Ermöglicht Flex-Shrinking */
    background-color: #FFFFFF;
    border-radius: 16px;
    box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
    padding: 30px;
    border: 1px solid #e5e5e5;
    z-index: 10;
    position: relative;
    
    /* Erweitere Content-Bereich für bessere Überlagerung */
    margin-left: -15px; /* Überlappt leicht die Navigation */
    padding-left: 45px; /* Kompensiert die negative Margin */
    
    /* Zusätzliche Breite für vollständige Überlagerung */
    width: calc(100% + 15px);
}
        
        /* Verstecke Desktop-spezifische Elemente auf Mobile */
        .yprint-settings-tabs,
        .yprint-mobile-tabs {
            display: none;
        }
        
        /* Formularelemente */
        .yprint-settings-page h2 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: 25px;
            color: #1d1d1f;
        }
        
        .yprint-settings-page h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 30px 0 15px;
            color: #1d1d1f;
        }
        
        .yprint-form-group {
            margin-bottom: 20px;
        }
        
        .yprint-form-label {
            display: block;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
            color: #434C5E;
        }
        
        .yprint-form-input,
        .yprint-form-select {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #d1d1d6;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.2s ease;
        }
        
        .yprint-form-input:focus,
        .yprint-form-select:focus {
            border-color: #2997FF;
            box-shadow: 0 0 0 2px rgba(41, 151, 255, 0.1);
            outline: none;
        }
        
        .yprint-form-row {
            display: flex;
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .yprint-form-row > div {
            flex: 1;
        }
        
        .yprint-form-hint {
            font-size: 13px;
            color: #6e6e73;
            margin-top: 5px;
        }
        
        /* Knöpfe */
        .yprint-button {
            display: inline-block;
            padding: 12px 20px;
            background-color: #2997FF;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s ease;
        }
        
        .yprint-button:hover {
            background-color: #0080FF;
        }
        
        .yprint-button-secondary {
            background-color: #f5f5f7;
            color: #1d1d1f;
        }
        
        .yprint-button-secondary:hover {
            background-color: #e5e5ea;
        }
        
        .yprint-button-danger {
            background-color: #ff3b30;
            color: white;
        }
        
        .yprint-button-danger:hover {
            background-color: #d70015;
        }
        
        /* Adressen-Karten */
        .yprint-address-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .yprint-address-card {
            padding: 20px;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            position: relative;
            transition: box-shadow 0.2s ease;
        }
        
        .yprint-address-card:hover {
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .yprint-address-card.default {
            border-color: #2997FF;
            background-color: #F0F8FF;
        }
        
        .yprint-address-default-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: #2997FF;
            color: white;
            padding: 3px 8px;
            border-radius: 5px;
            font-size: 12px;
        }
        
        .yprint-address-actions {
            margin-top: 15px;
            display: flex;
            gap: 10px;
        }
        
        .yprint-address-actions .yprint-button {
            padding: 8px 15px;
            font-size: 14px;
        }
        
        .yprint-payment-card {
            padding: 20px;
            border: 1px solid #e5e5e5;
            border-radius: 10px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            position: relative;
        }
        
        .yprint-payment-card.default {
            border-color: #2997FF;
            background-color: #F0F8FF;
        }
        
        .yprint-payment-icon {
            flex: 0 0 60px;
            height: 40px;
            margin-right: 15px;
            background-repeat: no-repeat;
            background-position: center;
            background-size: contain;
        }
        
        .yprint-payment-card.visa .yprint-payment-icon {
            background-image: url('https://yprint.de/wp-content/uploads/2025/03/visa-icon.svg');
        }
        
        .yprint-payment-card.mastercard .yprint-payment-icon {
            background-image: url('https://yprint.de/wp-content/uploads/2025/03/mastercard-icon.svg');
        }
        
        .yprint-payment-card.paypal .yprint-payment-icon {
            background-image: url('https://yprint.de/wp-content/uploads/2025/03/paypal-icon.svg');
        }
        
        .yprint-payment-card.sepa .yprint-payment-icon {
            background-image: url('https://yprint.de/wp-content/uploads/2025/03/sepa-icon.svg');
        }
        
        .yprint-payment-details {
            flex-grow: 1;
        }
        
        .yprint-payment-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .yprint-payment-info {
            color: #6e6e73;
            font-size: 14px;
        }
        
        .yprint-payment-actions {
            display: flex;
            gap: 10px;
        }
        
        .yprint-payment-actions .yprint-button {
            padding: 8px 12px;
            font-size: 14px;
        }
        
        /* Schalter für Toggle-Einstellungen */
        .yprint-switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 24px;
        }
        
        .yprint-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .yprint-switch-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 24px;
        }
        
        .yprint-switch-slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        .yprint-switch input:checked + .yprint-switch-slider {
            background-color: #2997FF;
        }
        
        .yprint-switch input:checked + .yprint-switch-slider:before {
            transform: translateX(26px);
        }
        
        /* Einstellungszeile mit Schalter */
        .yprint-setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 0;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .yprint-setting-row:last-child {
            border-bottom: none;
        }
        
        .yprint-setting-info {
            flex-grow: 1;
        }
        
        .yprint-setting-title {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .yprint-setting-description {
            font-size: 14px;
            color: #6e6e73;
        }
        
        /* Meldungen */
        .yprint-message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 15px;
        }
        
        .yprint-message-success {
            background-color: #E8F5E9;
            border-left: 4px solid #4CAF50;
            color: #2E7D32;
        }
        
        .yprint-message-error {
            background-color: #FFEBEE;
            border-left: 4px solid #F44336;
            color: #C62828;
        }
        
        .yprint-message-info {
            background-color: #E3F2FD;
            border-left: 4px solid #2196F3;
            color: #1565C0;
        }
        
        .yprint-message-warning {
            background-color: #FFF8E1;
            border-left: 4px solid #FFC107;
            color: #FF8F00;
        }
        
        /* Checkboxen */
        .yprint-checkbox-row {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .yprint-checkbox-row input[type="checkbox"] {
            margin-right: 10px;
        }
        
        /* Adresssuche */
        .yprint-address-search {
            position: relative;
            margin-bottom: 15px;
        }
        
        .yprint-address-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #d1d1d6;
            border-radius: 10px;
            z-index: 1000;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            display: none;
        }
        
        .yprint-address-suggestion {
            padding: 12px 15px;
            cursor: pointer;
            border-bottom: 1px solid #f5f5f7;
        }
        
        .yprint-address-suggestion:last-child {
            border-bottom: none;
        }
        
        .yprint-address-suggestion:hover {
            background-color: #f5f5f7;
        }
        
        .yprint-suggestion-main {
            font-weight: 500;
            margin-bottom: 5px;
        }
        
        .yprint-suggestion-secondary {
            font-size: 14px;
            color: #6e6e73;
        }
        
        /* Loader */
        .yprint-loader {
            display: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #2997FF;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Overlay für Benachrichtigungen */
        .yprint-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .yprint-overlay-content {
            background-color: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            text-align: center;
        }
        
        .yprint-overlay-content h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        /* Login erforderlich */
        .yprint-login-required {
            text-align: center;
            padding: 40px 20px;
            border: 1px solid #e5e5e5;
            border-radius: 16px;
            background-color: #f5f5f7;
        }
        
        .yprint-login-required p {
            margin-bottom: 20px;
            font-size: 16px;
            color: #6e6e73;
        }
        
        /* Responsive Anpassungen */
        @media (max-width: 992px) {
            .yprint-settings-tabs-container {
                flex-direction: column;
            }
            
            .yprint-settings-tabs {
                flex: 0 0 auto;
                flex-direction: row;
                flex-wrap: wrap;
                gap: 10px;
                position: static;
            }
            
            .yprint-tab {
                flex: 0 0 auto;
                font-size: 14px;
                padding: 10px 15px;
            }
        }
        
        @media (max-width: 768px) {
            .yprint-settings-tabs {
                display: none;
            }
            
            .yprint-mobile-tabs {
                display: block;
            }
            
            .yprint-settings-header h1 {
                font-size: 24px;
            }
            
            .yprint-settings-intro {
                font-size: 14px;
            }
            
            .yprint-form-row {
                flex-direction: column;
                gap: 15px;
            }
            
            .yprint-settings-content {
                padding: 20px 15px;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Output Overlay-Styles im Header
 */
function yprint_add_overlay_styles() {
    ?>
    <style>
        .yprint-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 9999;
            justify-content: center;
            align-items: center;
        }
        
        .yprint-overlay-content {
            background-color: white;
            border-radius: 16px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            text-align: center;
        }
        
        .yprint-overlay-content h3 {
            margin-top: 0;
            margin-bottom: 20px;
        }
        
        .yprint-overlay-buttons {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-top: 25px;
        }
        
        .yprint-overlay-loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #2997FF;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
    <?php
}

/**
 * Shortcode für persönliche Einstellungen
 * 
 * Usage: [yprint_personal_settings]
 */
function yprint_personal_settings_shortcode() {
    ob_start();
    global $wpdb;
    $user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    $message = '';
    $message_type = '';
    
    // Daten aus der Datenbank abrufen
    $user_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}personal_data WHERE user_id = %d",
            $user_id
        ),
        ARRAY_A
    );

    // Standardwerte setzen
    $first_name = isset($user_data['first_name']) ? esc_attr($user_data['first_name']) : '';
    $last_name = isset($user_data['last_name']) ? esc_attr($user_data['last_name']) : '';
    $birthdate = isset($user_data['birthdate']) ? esc_attr($user_data['birthdate']) : '';
    $phone = isset($user_data['phone']) ? esc_attr($user_data['phone']) : '';
    $current_email = $current_user->user_email;

    // Formularverarbeitung
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['personal_settings_nonce']) && 
        wp_verify_nonce($_POST['personal_settings_nonce'], 'save_personal_settings')) {
        
        $email_changed = false;
        $needs_logout = false;
        
        // E-Mail-Änderung verarbeiten
        if (isset($_POST['email']) && !empty($_POST['email'])) {
            $new_email = sanitize_email($_POST['email']);
            
            if ($new_email !== $current_email) {
                // Prüfen, ob die E-Mail bereits verwendet wird
                if (email_exists($new_email)) {
                    $message = 'Diese E-Mail-Adresse wird bereits verwendet.';
                    $message_type = 'error';
                } else {
                    $email_update = wp_update_user([
                        'ID' => $user_id,
                        'user_email' => $new_email
                    ]);
                    
                    if (!is_wp_error($email_update)) {
                        // E-Mail-Verifizierung zurücksetzen
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$wpdb->prefix}email_verifications 
                            SET email_verified = 0, 
                                updated_at = NOW() 
                            WHERE user_id = %d",
                            $user_id
                        ));
                        
                        $email_changed = true;
                        $needs_logout = true;
                    } else {
                        $message = 'Fehler beim Ändern der E-Mail-Adresse.';
                        $message_type = 'error';
                    }
                }
            }
        }

        // Persönliche Daten verarbeiten
        $fields_to_update = [];
        
        if (isset($_POST['first_name']) && !empty($_POST['first_name'])) {
            $fields_to_update['first_name'] = sanitize_text_field($_POST['first_name']);
            
            // Auch WooCommerce Billing/Shipping First Name aktualisieren
            update_user_meta($user_id, 'billing_first_name', $fields_to_update['first_name']);
            update_user_meta($user_id, 'shipping_first_name', $fields_to_update['first_name']);
            
            // WordPress-Profil aktualisieren
            wp_update_user([
                'ID' => $user_id,
                'first_name' => $fields_to_update['first_name']
            ]);
        }
        
        if (isset($_POST['last_name']) && !empty($_POST['last_name'])) {
            $fields_to_update['last_name'] = sanitize_text_field($_POST['last_name']);
            
            // Auch WooCommerce Billing/Shipping Last Name aktualisieren
            update_user_meta($user_id, 'billing_last_name', $fields_to_update['last_name']);
            update_user_meta($user_id, 'shipping_last_name', $fields_to_update['last_name']);
            
            // WordPress-Profil aktualisieren
            wp_update_user([
                'ID' => $user_id,
                'last_name' => $fields_to_update['last_name']
            ]);
        }
        
        if (isset($_POST['birthdate']) && !empty($_POST['birthdate'])) {
            $fields_to_update['birthdate'] = sanitize_text_field($_POST['birthdate']);
        }
        
        if (isset($_POST['phone']) && !empty($_POST['phone'])) {
            $fields_to_update['phone'] = sanitize_text_field($_POST['phone']);
            
            // Auch WooCommerce Billing/Shipping Phone aktualisieren
            update_user_meta($user_id, 'billing_phone', $fields_to_update['phone']);
        }

        if (!empty($fields_to_update)) {
            if ($user_data) {
                $update_result = $wpdb->update(
                    $wpdb->prefix . 'personal_data',
                    $fields_to_update,
                    ['user_id' => $user_id]
                );
            } else {
                $fields_to_update['user_id'] = $user_id;
                $update_result = $wpdb->insert($wpdb->prefix . 'personal_data', $fields_to_update);
            }
            
            // Daten nach Update neu laden
            $user_data = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}personal_data WHERE user_id = %d",
                    $user_id
                ),
                ARRAY_A
            );
            
            // Aktualisierte Werte setzen
            $first_name = isset($user_data['first_name']) ? esc_attr($user_data['first_name']) : '';
            $last_name = isset($user_data['last_name']) ? esc_attr($user_data['last_name']) : '';
            $birthdate = isset($user_data['birthdate']) ? esc_attr($user_data['birthdate']) : '';
            $phone = isset($user_data['phone']) ? esc_attr($user_data['phone']) : '';
            
            $message = 'Deine persönlichen Daten wurden erfolgreich gespeichert.';
            $message_type = 'success';
        }
        
        // Bei E-Mail-Änderung Logout-Overlay anzeigen
        if ($needs_logout && $email_changed) {
            ?>
            <div id="emailChangeOverlay" class="yprint-overlay">
                <div class="yprint-overlay-content">
                    <h3>E-Mail-Adresse wird geändert</h3>
                    <div class="yprint-overlay-loader"></div>
                    <p>Deine E-Mail-Adresse wurde geändert. Aus Sicherheitsgründen wirst du automatisch ausgeloggt und zur Login-Seite weitergeleitet.</p>
                    <p>Bitte logge dich anschließend mit deiner neuen E-Mail-Adresse ein.</p>
                </div>
            </div>
            
            <script>
jQuery(document).ready(function($) {
    // Mobile Tab Selection Handler mit Content-Überlagerung
$(".settings-item").on("click", function(e) {
    var href = $(this).attr('href');
    
    // Auf Mobile: Content über Navigation legen
    if (window.innerWidth <= 768) {
        e.preventDefault();
        
        // Chevron-Animation
        $(this).find('.settings-chevron').addClass('rotate');
        
        // Content-Overlay erstellen falls nicht vorhanden
        if ($('.content-overlay').length === 0) {
            $('body').append('<div class="content-overlay"></div>');
        }
        
        // Content-Bereich anzeigen
        setTimeout(function() {
            $('.content-overlay').addClass('show');
            $('.yprint-settings-content').addClass('show');
            $('body').css('overflow', 'hidden');
        }, 100);
        
        // Close-Button hinzufügen falls nicht vorhanden
        if ($('.content-close-btn').length === 0) {
            $('.yprint-settings-content').prepend('<button class="content-close-btn" type="button">&times;</button>');
        }
        
        // Content laden (AJAX oder direkte Navigation nach Animation)
        setTimeout(function() {
            window.location.href = href;
        }, 500);
    }
});

// Close-Handler für Content-Overlay
$(document).on('click', '.content-close-btn, .content-overlay', function(e) {
    if (e.target === this) {
        $('.content-overlay').removeClass('show');
        $('.yprint-settings-content').removeClass('show');
        $('body').css('overflow', 'auto');
        $('.settings-chevron').removeClass('rotate');
    }
});

// Escape-Key zum Schließen
$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('.yprint-settings-content.show').length > 0) {
        $('.content-overlay').removeClass('show');
        $('.yprint-settings-content').removeClass('show');
        $('body').css('overflow', 'auto');
        $('.settings-chevron').removeClass('rotate');
    }
});

/* Close-Button für Mobile Content */
.content-close-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 32px;
    height: 32px;
    border: none;
    background-color: #f5f5f7;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: bold;
    color: #666;
    cursor: pointer;
    z-index: 101;
    transition: all 0.2s ease;
}

.content-close-btn:hover {
    background-color: #e5e5ea;
    color: #333;
    transform: scale(1.05);
}

.content-close-btn:active {
    transform: scale(0.95);
}

/* Desktop - Close-Button verstecken */
@media (min-width: 768px) {
    .content-close-btn {
        display: none;
    }
    
    .content-overlay {
        display: none;
    }
    
    .yprint-settings-content {
        position: relative !important;
        transform: none !important;
        height: auto !important;
        opacity: 1 !important;
        visibility: visible !important;
    }
}

// Close-Handler für Settings-Overlay
$(document).on('click', '.settings-close-btn, .settings-overlay', function(e) {
    if (e.target === this) {
        $('.settings-overlay').removeClass('show');
        $('.settings-section').removeClass('show');
        $('body').css('overflow', 'auto');
        $('.settings-chevron').removeClass('rotate');
    }
});

// Escape-Key zum Schließen
$(document).on('keydown', function(e) {
    if (e.key === 'Escape' && $('.settings-section.show').length > 0) {
        $('.settings-overlay').removeClass('show');
        $('.settings-section').removeClass('show');
        $('body').css('overflow', 'auto');
        $('.settings-chevron').removeClass('rotate');
    }
});

function loadSettingsContent(href) {
    // Hier kannst du AJAX-Content laden oder zur neuen Seite navigieren
    // Für jetzt navigieren wir einfach nach kurzer Verzögerung
    setTimeout(function() {
        window.location.href = href;
    }, 400);
}
    
    // Touch-Feedback für Mobile
    $(".settings-item").on("touchstart", function() {
        $(this).addClass('touching');
    }).on("touchend touchcancel", function() {
        $(this).removeClass('touching');
    });
    
    $("#yprint-mobile-tab-select").on("change", function() {
        var selectedTab = $(this).val();
        window.location.href = window.location.pathname + "?tab=" + selectedTab;
    });
                }
                
                // Call logout function
                forceLogoutAndRedirect();
            });
            </script>
            <?php
        }
    }
    
    // Formular ausgeben
    ?>
    <div class="yprint-settings-page">
        <h2>Persönliche Daten</h2>
        
        <?php if ($message): ?>
        <div class="yprint-message yprint-message-<?php echo esc_attr($message_type); ?>">
            <?php echo esc_html($message); ?>
        </div>
        <?php endif; ?>
        
        <form method="POST" class="yprint-settings-form" id="personal-settings-form">
            <?php wp_nonce_field('save_personal_settings', 'personal_settings_nonce'); ?>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="first_name" class="yprint-form-label">Vorname</label>
                    <input type="text" 
                           id="first_name" 
                           name="first_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($first_name); ?>" 
                           placeholder="Vorname">
                </div>
                
                <div class="yprint-form-group">
                    <label for="last_name" class="yprint-form-label">Nachname</label>
                    <input type="text" 
                           id="last_name" 
                           name="last_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($last_name); ?>" 
                           placeholder="Nachname">
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="birthdate" class="yprint-form-label">Geburtsdatum</label>
                    <input type="date" 
                           id="birthdate" 
                           name="birthdate" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($birthdate); ?>">
                    <div class="yprint-form-hint">Für personalisierte Angebote und altersgemäße Inhalte</div>
                </div>
                
                <div class="yprint-form-group">
                    <label for="phone" class="yprint-form-label">Telefonnummer</label>
                    <input type="tel" 
                           id="phone" 
                           name="phone" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($phone); ?>" 
                           placeholder="+49 123 4567890">
                    <div class="yprint-form-hint">Für schnellere Unterstützung bei Bestellungen</div>
                </div>
            </div>
            
            <div class="yprint-form-group">
                <label for="email" class="yprint-form-label">E-Mail-Adresse</label>
                <input type="email" 
                       id="email" 
                       name="email" 
                       class="yprint-form-input" 
                       value="<?php echo esc_attr($current_email); ?>" 
                       placeholder="E-Mail-Adresse">
                <div class="yprint-form-hint" id="email-change-warning" style="display: none; color: #ff3b30;">
                    Wenn du deine E-Mail-Adresse änderst, wirst du aus Sicherheitsgründen ausgeloggt und musst dich mit der neuen Adresse wieder einloggen.
                </div>
            </div>
            
            <div class="yprint-form-buttons">
                <button type="submit" class="yprint-button">Änderungen speichern</button>
            </div>
        </form>
        
        <!-- Passwort-Reset Sektion -->
        <div class="yprint-password-reset-section" style="margin-top: 40px; padding-top: 30px; border-top: 1px solid #e0e0e0;">
            <h3>Passwort-Sicherheit</h3>
            <p style="color: #666; margin-bottom: 20px;">Setze dein Passwort zurück, wenn du den Verdacht hast, dass dein Konto kompromittiert wurde.</p>
            
            <button type="button" id="reset-password-btn" class="yprint-button yprint-button-secondary">
                <i class="fas fa-key" style="margin-right: 8px;"></i>
                Passwort zurücksetzen
            </button>
            
            <div id="password-reset-loading" style="display: none; margin-top: 15px;">
                <div class="yprint-overlay-loader"></div>
                <p style="text-align: center; color: #666;">Reset-E-Mail wird versendet...</p>
            </div>
            
            <div id="password-reset-success" style="display: none; margin-top: 15px; padding: 15px; background-color: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; color: #155724;">
                <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
                <strong>E-Mail versendet!</strong> Du erhältst in Kürze eine E-Mail mit Anweisungen zum Zurücksetzen deines Passworts.
            </div>
            
            <div id="password-reset-error" style="display: none; margin-top: 15px; padding: 15px; background-color: #f8d7da; border: 1px solid #f5c6cb; border-radius: 8px; color: #721c24;">
                <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i>
                <strong>Fehler:</strong> <span id="error-message">Bitte versuche es später erneut.</span>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        var originalEmail = '<?php echo esc_js($current_email); ?>';
        var emailInput = $('#email');
        var emailWarning = $('#email-change-warning');
        
        // Show warning when email is changed
        emailInput.on('input', function() {
            if ($(this).val() !== originalEmail) {
                emailWarning.slideDown();
            } else {
                emailWarning.slideUp();
            }
        });
        
        // Rate Limiting für eingeloggte User
        var lastResetAttempt = 0;
        var resetAttempts = 0;
        var maxAttempts = 3;
        var cooldownTime = 3600000; // 1 Stunde in Millisekunden
        
        // Passwort-Reset Button Handler
        $('#reset-password-btn').on('click', function() {
            var currentTime = Date.now();
            
            // Rate Limiting prüfen
            if (currentTime - lastResetAttempt < cooldownTime && resetAttempts >= maxAttempts) {
                $('#password-reset-error #error-message').text('Zu viele Versuche. Bitte warte eine Stunde bis zum nächsten Versuch.');
                $('#password-reset-error').slideDown();
                return;
            }
            
            // Reset previous messages
            $('#password-reset-success, #password-reset-error').slideUp();
            
            // Show loading
            $('#password-reset-loading').slideDown();
            $(this).prop('disabled', true);
            
            // Logging für Sicherheit
            console.log('YPrint: Password reset initiated for logged-in user at ' + new Date().toISOString());
            
            // AJAX-Call mit bestehender Infrastruktur
            $.ajax({
                type: 'POST',
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                data: {
                    action: 'yprint_logged_in_reset',
                    nonce: '<?php echo wp_create_nonce('yprint_logged_in_reset'); ?>'
                },
                success: function(response) {
                    $('#password-reset-loading').slideUp();
                    $('#reset-password-btn').prop('disabled', false);
                    
                    if (response.success) {
                        $('#password-reset-success').slideDown();
                        
                        // Erfolgreiche Anfrage loggen
                        console.log('YPrint: Password reset email sent successfully');
                        
                        // Button temporär deaktivieren
                        $('#reset-password-btn').prop('disabled', true);
                        setTimeout(function() {
                            $('#reset-password-btn').prop('disabled', false);
                        }, 60000); // 1 Minute Wartezeit
                        
                    } else {
                        var errorMsg = response.data && response.data.message ? 
                            response.data.message : 'Ein unerwarteter Fehler ist aufgetreten.';
                        $('#password-reset-error #error-message').text(errorMsg);
                        $('#password-reset-error').slideDown();
                        
                        // Rate Limiting Update
                        resetAttempts++;
                        lastResetAttempt = currentTime;
                        
                        // Fehlschlag loggen
                        console.error('YPrint: Password reset failed - ' + errorMsg);
                    }
                },
                error: function(xhr, status, error) {
                    $('#password-reset-loading').slideUp();
                    $('#reset-password-btn').prop('disabled', false);
                    
                    $('#password-reset-error #error-message').text('Verbindungsfehler. Bitte versuche es erneut.');
                    $('#password-reset-error').slideDown();
                    
                    // Rate Limiting Update
                    resetAttempts++;
                    lastResetAttempt = currentTime;
                    
                    // Fehler loggen
                    console.error('YPrint: Password reset AJAX error - ', error);
                }
            });
        });
        
        // Auto-hide success message nach 10 Sekunden
        $(document).on('show', '#password-reset-success', function() {
            setTimeout(function() {
                $('#password-reset-success').slideUp();
            }, 10000);
        });
    });
    </script>
    <?php
    
    return ob_get_clean();
}

/**
 * Shortcode für Rechnungsadresse
 * 
 * Usage: [yprint_billing_settings]
 */
function yprint_billing_settings_shortcode() {
    ob_start();
    
    // Aktuelle Benutzerdaten abrufen
    $user_id = get_current_user_id();
    
    // Address Manager Integration - gespeicherte Adressen abrufen
    $additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
    if (!is_array($additional_addresses)) {
        $additional_addresses = array();
    }
    
    // Formular ausgeben
    ?>
    <div class="yprint-settings-page">
        <h2>Rechnungsadressen</h2>
        
        <div class="yprint-info-message" style="background-color: #E3F2FD; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 15px;">
            <p style="margin: 0;">Hier kannst du verschiedene Rechnungsadressen hinterlegen und verwalten. Im Bestellprozess kannst du dann auswählen, welche Adresse für die Rechnung verwendet werden soll.</p>
        </div>
        
        <!-- Address Manager Integration -->
        <div class="yprint-saved-addresses" style="margin-bottom: 30px;">
            <div class="loading-addresses" style="text-align: center; padding: 20px; display: none;">
                <i class="fas fa-spinner fa-spin"></i> Lade gespeicherte Adressen...
            </div>
            <div class="address-cards-grid" style="display: none;">
                <!-- Adressen werden hier durch JavaScript geladen -->
            </div>
        </div>
        
        <!-- Modal für neue/bearbeitete Adressen -->
        <div id="new-address-modal" class="yprint-address-modal" style="display: none;">
            <div class="address-modal-overlay"></div>
            <div class="address-modal-content">
                <div class="address-modal-header">
                    <h3>Neue Adresse hinzufügen</h3>
                    <button type="button" class="btn-close-modal">&times;</button>
                </div>
                <form id="new-address-form">
                    <!-- Hidden Field für ID bei Bearbeitung -->
                    <input type="hidden" id="new_address_edit_id" name="new_address_edit_id" value="">
                    
                    <div class="yprint-form-group">
                        <label for="new_address_name" class="yprint-form-label">Bezeichnung</label>
                        <input type="text" id="new_address_name" name="new_address_name" class="yprint-form-input" placeholder="z.B. Zuhause, Büro" required>
                    </div>
                    
                    <div class="yprint-form-row">
                        <div class="yprint-form-group">
                            <label for="new_address_first_name" class="yprint-form-label">Vorname</label>
                            <input type="text" id="new_address_first_name" name="new_address_first_name" class="yprint-form-input" required>
                        </div>
                        <div class="yprint-form-group">
                            <label for="new_last_name" class="yprint-form-label">Nachname</label>
                            <input type="text" id="new_last_name" name="new_last_name" class="yprint-form-input" required>
                        </div>
                    </div>
                    
                    <div class="yprint-checkbox-row">
                        <input type="checkbox" id="new_is_company" name="new_is_company">
                        <label for="new_is_company">Firmenadresse</label>
                    </div>
                    
                    <div id="new_company_field" class="yprint-company-fields" style="display: none;">
                        <div class="yprint-form-group">
                            <label for="new_company" class="yprint-form-label">Firmenname</label>
                            <input type="text" id="new_company" name="new_company" class="yprint-form-input">
                        </div>
                    </div>
                    
                    <div class="yprint-form-row">
                        <div class="yprint-form-group">
                            <label for="new_address_1" class="yprint-form-label">Straße</label>
                            <input type="text" id="new_address_1" name="new_address_1" class="yprint-form-input" required>
                        </div>
                        <div class="yprint-form-group">
                            <label for="new_address_2" class="yprint-form-label">Hausnummer</label>
                            <input type="text" id="new_address_2" name="new_address_2" class="yprint-form-input" required>
                        </div>
                    </div>
                    
                    <div class="yprint-form-row">
                        <div class="yprint-form-group">
                            <label for="new_postcode" class="yprint-form-label">PLZ</label>
                            <input type="text" id="new_postcode" name="new_postcode" class="yprint-form-input" required>
                        </div>
                        <div class="yprint-form-group">
                            <label for="new_city" class="yprint-form-label">Stadt</label>
                            <input type="text" id="new_city" name="new_city" class="yprint-form-input" required>
                        </div>
                    </div>
                    
                    <div class="yprint-form-group">
                        <label for="new_country" class="yprint-form-label">Land</label>
                        <select id="new_country" name="new_country" class="yprint-form-select" required>
                            <option value="DE">Deutschland</option>
                            <option value="AT">Österreich</option>
                            <option value="CH">Schweiz</option>
                            <!-- Weitere Länder hier -->
                        </select>
                    </div>
                    
                    <div class="address-form-actions">
                        <button type="button" class="yprint-button yprint-button-secondary btn-cancel-address">Abbrechen</button>
                        <button type="button" class="yprint-button btn-save-address">
                            <i class="fas fa-save mr-2"></i>Adresse speichern
                        </button>
                    </div>
                    
                    <div class="address-form-errors" style="display: none; margin-top: 15px;"></div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Unternehmensfeld umschalten
        $('#new_is_company').change(function() {
            if (this.checked) {
                $('#new_company_field').slideDown(300);
            } else {
                $('#new_company_field').slideUp(300);
            }
        });
    });
    </script>
    <?php
    
    return ob_get_clean();
}

/**
 * Shortcode für Lieferadressen
 * 
 * Usage: [yprint_shipping_settings]
 */
function yprint_shipping_settings_shortcode() {
    ob_start();
    
    // Aktuelle Benutzerdaten abrufen
    $user_id = get_current_user_id();
    $message = '';
    $message_type = '';
    
    // Address Manager Integration - Styles und Scripts einbinden
    wp_enqueue_style('yprint-address-manager', YPRINT_PLUGIN_URL . 'assets/css/yprint-address-manager.css', array(), YPRINT_PLUGIN_VERSION);
    wp_enqueue_script('yprint-address-manager', YPRINT_PLUGIN_URL . 'assets/js/yprint-address-manager.js', array('jquery'), YPRINT_PLUGIN_VERSION, true);
    
    // Zusätzliche Settings-Styles einbinden
    wp_add_inline_style('yprint-address-manager', yprint_settings_styles());
    
    // Address Manager JavaScript-Variablen
    wp_localize_script('yprint-address-manager', 'yprint_address_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('yprint_save_address_action'),
        'messages' => array(
            'delete_address' => __('Adresse wirklich löschen?', 'yprint-plugin'),
            'set_as_default' => __('Als Standard setzen', 'yprint-plugin'),
            'standard_address' => __('Standard-Adresse', 'yprint-plugin'),
        ),
    ));
    
    // Standard-Lieferadresse aus WooCommerce abrufen
    $shipping_first_name = get_user_meta($user_id, 'shipping_first_name', true);
    $shipping_last_name = get_user_meta($user_id, 'shipping_last_name', true);
    $shipping_company = get_user_meta($user_id, 'shipping_company', true);
    $shipping_address_1 = get_user_meta($user_id, 'shipping_address_1', true);
    $shipping_address_2 = get_user_meta($user_id, 'shipping_address_2', true);
    $shipping_postcode = get_user_meta($user_id, 'shipping_postcode', true);
    $shipping_city = get_user_meta($user_id, 'shipping_city', true);
    $shipping_country = get_user_meta($user_id, 'shipping_country', true) ?: 'DE';
    $is_company_shipping = get_user_meta($user_id, 'is_company_shipping', true);
    
    // Falls Unternehmen in Rechnungsdaten gesetzt ist, auch hier vorschlagen
    $billing_company = get_user_meta($user_id, 'billing_company', true);
    $is_company_billing = get_user_meta($user_id, 'is_company', true);
    
    // Prüfen, ob Zusatzadressen vorhanden sind
        $additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
        if (!is_array($additional_addresses)) {
            $additional_addresses = array();
        }
        
        // Defaultadresse abrufen
        $default_address_id = get_user_meta($user_id, 'default_shipping_address', true);
        
        // Falls Unternehmen in Rechnungsdaten gesetzt ist, auch hier vorschlagen
        $billing_company = get_user_meta($user_id, 'billing_company', true);
        $is_company_billing = get_user_meta($user_id, 'is_company', true);
    
    // Wenn POST-Anfrage zur Speicherung
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Hauptadresse aktualisieren
        if (isset($_POST['shipping_settings_nonce']) && wp_verify_nonce($_POST['shipping_settings_nonce'], 'save_shipping_settings')) {
            // Standardfelder aktualisieren
            $fields_to_update = [
                'shipping_first_name' => isset($_POST['shipping_first_name']) ? sanitize_text_field($_POST['shipping_first_name']) : '',
                'shipping_last_name' => isset($_POST['shipping_last_name']) ? sanitize_text_field($_POST['shipping_last_name']) : '',
                'shipping_address_1' => isset($_POST['shipping_address_1']) ? sanitize_text_field($_POST['shipping_address_1']) : '',
                'shipping_address_2' => isset($_POST['shipping_address_2']) ? sanitize_text_field($_POST['shipping_address_2']) : '',
                'shipping_postcode' => isset($_POST['shipping_postcode']) ? sanitize_text_field($_POST['shipping_postcode']) : '',
                'shipping_city' => isset($_POST['shipping_city']) ? sanitize_text_field($_POST['shipping_city']) : '',
                'shipping_country' => isset($_POST['shipping_country']) ? sanitize_text_field($_POST['shipping_country']) : 'DE',
            ];

            // Unternehmensdaten aktualisieren
            $is_company_shipping = isset($_POST['is_company_shipping']);
            update_user_meta($user_id, 'is_company_shipping', $is_company_shipping);
            
            if ($is_company_shipping) {
                $fields_to_update['shipping_company'] = isset($_POST['shipping_company']) ? sanitize_text_field($_POST['shipping_company']) : '';
            }

            // WooCommerce-Metadaten aktualisieren
            foreach ($fields_to_update as $key => $value) {
                update_user_meta($user_id, $key, $value);
            }
            
            // Event für Address Manager auslösen
            do_action('yprint_after_address_save', $user_id, $fields_to_update);
            
            $message = 'Deine Lieferadresse wurde erfolgreich gespeichert.';
            $message_type = 'success';
            
            // Umleitung, um URL sauber zu halten und Formular-Resubmit zu verhindern
            wp_redirect(add_query_arg(
                array(
                    'tab' => 'shipping',
                    'message' => urlencode($message),
                    'type' => $message_type
                ),
                remove_query_arg(array('message', 'type'))
            ));
            exit;
        }
        
        // Neue Adresse hinzufügen
        if (isset($_POST['add_address_nonce']) && wp_verify_nonce($_POST['add_address_nonce'], 'add_shipping_address')) {
            $new_address = [
                'id' => uniqid('addr_'),
                'name' => isset($_POST['address_name']) ? sanitize_text_field($_POST['address_name']) : 'Neue Adresse',
                'first_name' => isset($_POST['addr_first_name']) ? sanitize_text_field($_POST['addr_first_name']) : '',
                'last_name' => isset($_POST['addr_last_name']) ? sanitize_text_field($_POST['addr_last_name']) : '',
                'company' => isset($_POST['addr_company']) ? sanitize_text_field($_POST['addr_company']) : '',
                'address_1' => isset($_POST['addr_address_1']) ? sanitize_text_field($_POST['addr_address_1']) : '',
                'address_2' => isset($_POST['addr_address_2']) ? sanitize_text_field($_POST['addr_address_2']) : '',
                'postcode' => isset($_POST['addr_postcode']) ? sanitize_text_field($_POST['addr_postcode']) : '',
                'city' => isset($_POST['addr_city']) ? sanitize_text_field($_POST['addr_city']) : '',
                'country' => isset($_POST['addr_country']) ? sanitize_text_field($_POST['addr_country']) : 'DE',
                'is_company' => isset($_POST['addr_is_company']) ? true : false,
            ];
            
            $additional_addresses[] = $new_address;
            update_user_meta($user_id, 'additional_shipping_addresses', $additional_addresses);
            
            // Event für Address Manager auslösen
            do_action('yprint_after_address_save', $user_id, $new_address);
            
            $message = 'Neue Lieferadresse wurde erfolgreich hinzugefügt.';
            $message_type = 'success';
            
            wp_redirect(add_query_arg(
                array(
                    'tab' => 'shipping',
                    'message' => urlencode($message),
                    'type' => $message_type
                ),
                remove_query_arg(array('message', 'type', 'action', 'address_id'))
            ));
            exit;
        }
        
        // Adresse bearbeiten
        if (isset($_POST['edit_address_nonce']) && wp_verify_nonce($_POST['edit_address_nonce'], 'edit_shipping_address')) {
            $address_id = isset($_POST['address_id']) ? sanitize_text_field($_POST['address_id']) : '';
            
            if ($address_id) {
                foreach ($additional_addresses as $key => $address) {
                    if ($address['id'] === $address_id) {
                        $updated_address = [
                            'id' => $address_id,
                            'name' => isset($_POST['address_name']) ? sanitize_text_field($_POST['address_name']) : $address['name'],
                            'first_name' => isset($_POST['addr_first_name']) ? sanitize_text_field($_POST['addr_first_name']) : $address['first_name'],
                            'last_name' => isset($_POST['addr_last_name']) ? sanitize_text_field($_POST['addr_last_name']) : $address['last_name'],
                            'company' => isset($_POST['addr_company']) ? sanitize_text_field($_POST['addr_company']) : $address['company'],
                            'address_1' => isset($_POST['addr_address_1']) ? sanitize_text_field($_POST['addr_address_1']) : $address['address_1'],
                            'address_2' => isset($_POST['addr_address_2']) ? sanitize_text_field($_POST['addr_address_2']) : $address['address_2'],
                            'postcode' => isset($_POST['addr_postcode']) ? sanitize_text_field($_POST['addr_postcode']) : $address['postcode'],
                            'city' => isset($_POST['addr_city']) ? sanitize_text_field($_POST['addr_city']) : $address['city'],
                            'country' => isset($_POST['addr_country']) ? sanitize_text_field($_POST['addr_country']) : $address['country'],
                            'is_company' => isset($_POST['addr_is_company']) ? true : false,
                        ];
                        
                        $additional_addresses[$key] = $updated_address;
                        break;
                    }
                }
                
                update_user_meta($user_id, 'additional_shipping_addresses', $additional_addresses);
                
                // Event für Address Manager auslösen
                do_action('yprint_after_address_save', $user_id, $updated_address);
                
                $message = 'Lieferadresse wurde erfolgreich aktualisiert.';
                $message_type = 'success';
            }
            
            wp_redirect(add_query_arg(
                array(
                    'tab' => 'shipping',
                    'message' => urlencode($message),
                    'type' => $message_type
                ),
                remove_query_arg(array('message', 'type', 'action', 'address_id'))
            ));
            exit;
        }
        
        // Neue Adresse hinzufügen
        if (isset($_POST['add_address_nonce']) && wp_verify_nonce($_POST['add_address_nonce'], 'add_shipping_address')) {
            $new_address = [
                'id' => uniqid('addr_'),
                'name' => isset($_POST['address_name']) ? sanitize_text_field($_POST['address_name']) : 'Neue Adresse',
                'first_name' => isset($_POST['addr_first_name']) ? sanitize_text_field($_POST['addr_first_name']) : '',
                'last_name' => isset($_POST['addr_last_name']) ? sanitize_text_field($_POST['addr_last_name']) : '',
                'company' => isset($_POST['addr_company']) ? sanitize_text_field($_POST['addr_company']) : '',
                'address_1' => isset($_POST['addr_address_1']) ? sanitize_text_field($_POST['addr_address_1']) : '',
                'address_2' => isset($_POST['addr_address_2']) ? sanitize_text_field($_POST['addr_address_2']) : '',
                'postcode' => isset($_POST['addr_postcode']) ? sanitize_text_field($_POST['addr_postcode']) : '',
                'city' => isset($_POST['addr_city']) ? sanitize_text_field($_POST['addr_city']) : '',
                'country' => isset($_POST['addr_country']) ? sanitize_text_field($_POST['addr_country']) : 'DE',
                'is_company' => isset($_POST['addr_is_company']) ? true : false,
            ];
            
            $additional_addresses[] = $new_address;
            update_user_meta($user_id, 'additional_shipping_addresses', $additional_addresses);
            
            $message = 'Neue Lieferadresse wurde erfolgreich hinzugefügt.';
            $message_type = 'success';
            
            wp_redirect(add_query_arg(
                array(
                    'tab' => 'shipping',
                    'message' => urlencode($message),
                    'type' => $message_type
                ),
                remove_query_arg(array('message', 'type', 'action', 'address_id'))
            ));
            exit;
        }
        
        // Adresse bearbeiten
        if (isset($_POST['edit_address_nonce']) && wp_verify_nonce($_POST['edit_address_nonce'], 'edit_shipping_address')) {
            $address_id = isset($_POST['address_id']) ? sanitize_text_field($_POST['address_id']) : '';
            
            if ($address_id) {
                foreach ($additional_addresses as $key => $address) {
                    if ($address['id'] === $address_id) {
                        $additional_addresses[$key] = [
                            'id' => $address_id,
                            'name' => isset($_POST['address_name']) ? sanitize_text_field($_POST['address_name']) : $address['name'],
                            'first_name' => isset($_POST['addr_first_name']) ? sanitize_text_field($_POST['addr_first_name']) : $address['first_name'],
                            'last_name' => isset($_POST['addr_last_name']) ? sanitize_text_field($_POST['addr_last_name']) : $address['last_name'],
                            'company' => isset($_POST['addr_company']) ? sanitize_text_field($_POST['addr_company']) : $address['company'],
                            'address_1' => isset($_POST['addr_address_1']) ? sanitize_text_field($_POST['addr_address_1']) : $address['address_1'],
                            'address_2' => isset($_POST['addr_address_2']) ? sanitize_text_field($_POST['addr_address_2']) : $address['address_2'],
                            'postcode' => isset($_POST['addr_postcode']) ? sanitize_text_field($_POST['addr_postcode']) : $address['postcode'],
                            'city' => isset($_POST['addr_city']) ? sanitize_text_field($_POST['addr_city']) : $address['city'],
                            'country' => isset($_POST['addr_country']) ? sanitize_text_field($_POST['addr_country']) : $address['country'],
                            'is_company' => isset($_POST['addr_is_company']) ? true : false,
                        ];
                        break;
                    }
                }
                
                update_user_meta($user_id, 'additional_shipping_addresses', $additional_addresses);
                
                $message = 'Lieferadresse wurde erfolgreich aktualisiert.';
                $message_type = 'success';
            }
            
            wp_redirect(add_query_arg(
                array(
                    'tab' => 'shipping',
                    'message' => urlencode($message),
                    'type' => $message_type
                ),
                remove_query_arg(array('message', 'type', 'action', 'address_id'))
            ));
            exit;
        }
    }
    
    // AJAX-Handler für Adress-Aktionen
    if (isset($_GET['action']) && isset($_GET['address_id'])) {
        $action = sanitize_text_field($_GET['action']);
        $address_id = sanitize_text_field($_GET['address_id']);
        
        if ($action === 'delete' && $address_id) {
            $deleted_address = null;
            
            // Adresse löschen
            foreach ($additional_addresses as $key => $address) {
                if ($address['id'] === $address_id) {
                    $deleted_address = $address;
                    unset($additional_addresses[$key]);
                    break;
                }
            }
            
            // Array neu indizieren
            $additional_addresses = array_values($additional_addresses);
            update_user_meta($user_id, 'additional_shipping_addresses', $additional_addresses);
            
            // Wenn Default-Adresse gelöscht wurde, Default entfernen
            if ($default_address_id === $address_id) {
                delete_user_meta($user_id, 'default_shipping_address');
                $default_address_id = '';
            }
            
            // Event für Address Manager auslösen
            if ($deleted_address) {
                do_action('yprint_after_address_delete', $user_id, $deleted_address);
            }
            
            $message = 'Lieferadresse wurde erfolgreich gelöscht.';
            $message_type = 'success';
            
            wp_redirect(add_query_arg(
                array(
                    'tab' => 'shipping',
                    'message' => urlencode($message),
                    'type' => $message_type
                ),
                remove_query_arg(array('action', 'address_id'))
            ));
            exit;
        } elseif ($action === 'set_default' && $address_id) {
            // Als Standard setzen
            update_user_meta($user_id, 'default_shipping_address', $address_id);
            
            // Event für Address Manager auslösen
            do_action('yprint_after_default_address_change', $user_id, $address_id);
            
            $message = 'Standardadresse wurde erfolgreich festgelegt.';
            $message_type = 'success';
            
            wp_redirect(add_query_arg(
                array(
                    'tab' => 'shipping',
                    'message' => urlencode($message),
                    'type' => $message_type
                ),
                remove_query_arg(array('action', 'address_id'))
            ));
            exit;
        }
    }
    
    // Formular ausgeben - Standard oder Bearbeitungsmodus
    ?>
    <div class="yprint-settings-page">
        <h2>Lieferadressen</h2>
        
        <?php if ($message): ?>
        <div class="yprint-message yprint-message-<?php echo esc_attr($message_type); ?>">
            <?php echo esc_html($message); ?>
        </div>
        <?php endif; ?>
        
        <div class="yprint-info-message" style="background-color: #E3F2FD; padding: 15px; border-radius: 10px; margin-bottom: 20px; font-size: 15px;">
            <p style="margin: 0;">Hier kannst du verschiedene Lieferadressen hinterlegen und verwalten. Im Bestellprozess kannst du dann auswählen, an welche dieser Adressen geliefert werden soll.</p>
        </div>
        
        <!-- Address Manager Integration -->
        <div class="yprint-saved-addresses" style="margin-bottom: 30px;">
            <div class="loading-addresses" style="text-align: center; padding: 20px; display: none;">
                <i class="fas fa-spinner fa-spin"></i> Lade gespeicherte Adressen...
            </div>
            <div class="address-cards-grid" style="display: none;">
                <!-- Adressen werden hier durch JavaScript geladen -->
            </div>
        </div>
        
        <!-- Adressformular (wird versteckt wenn gespeicherte Adressen vorhanden) -->
        <div id="address-form" style="display: none;">
            <h3>Neue Adresse hinzufügen</h3>
            
            <!-- Hier wird das bestehende Formular eingefügt -->
        </div>
        
        <!-- Modal für neue/bearbeitete Adressen -->
        <div id="new-address-modal" class="yprint-address-modal" style="display: none;">
            <div class="address-modal-overlay"></div>
            <div class="address-modal-content">
                <div class="address-modal-header">
                    <h3>Neue Adresse hinzufügen</h3>
                    <button type="button" class="btn-close-modal">&times;</button>
                </div>
                <form id="new-address-form">
                    <!-- Hidden Field für ID bei Bearbeitung -->
                    <input type="hidden" id="new_address_edit_id" name="new_address_edit_id" value="">
                    
                    <div class="yprint-form-group">
                        <label for="new_address_name" class="yprint-form-label">Bezeichnung</label>
                        <input type="text" id="new_address_name" name="new_address_name" class="yprint-form-input" placeholder="z.B. Zuhause, Büro" required>
                    </div>
                    
                    <div class="yprint-form-row">
                        <div class="yprint-form-group">
                            <label for="new_address_first_name" class="yprint-form-label">Vorname</label>
                            <input type="text" id="new_address_first_name" name="new_address_first_name" class="yprint-form-input" required>
                        </div>
                        <div class="yprint-form-group">
                            <label for="new_last_name" class="yprint-form-label">Nachname</label>
                            <input type="text" id="new_last_name" name="new_last_name" class="yprint-form-input" required>
                        </div>
                    </div>
                    
                    <div class="yprint-checkbox-row">
                        <input type="checkbox" id="new_is_company" name="new_is_company">
                        <label for="new_is_company">Firmenadresse</label>
                    </div>
                    
                    <div id="new_company_field" class="yprint-company-fields" style="display: none;">
                        <div class="yprint-form-group">
                            <label for="new_company" class="yprint-form-label">Firmenname</label>
                            <input type="text" id="new_company" name="new_company" class="yprint-form-input">
                        </div>
                    </div>
                    
                    <div class="yprint-form-row">
                        <div class="yprint-form-group">
                            <label for="new_address_1" class="yprint-form-label">Straße</label>
                            <input type="text" id="new_address_1" name="new_address_1" class="yprint-form-input" required>
                        </div>
                        <div class="yprint-form-group">
                            <label for="new_address_2" class="yprint-form-label">Hausnummer</label>
                            <input type="text" id="new_address_2" name="new_address_2" class="yprint-form-input" required>
                        </div>
                    </div>
                    
                    <div class="yprint-form-row">
                        <div class="yprint-form-group">
                            <label for="new_postcode" class="yprint-form-label">PLZ</label>
                            <input type="text" id="new_postcode" name="new_postcode" class="yprint-form-input" required>
                        </div>
                        <div class="yprint-form-group">
                            <label for="new_city" class="yprint-form-label">Stadt</label>
                            <input type="text" id="new_city" name="new_city" class="yprint-form-input" required>
                        </div>
                    </div>
                    
                    <div class="yprint-form-group">
                        <label for="new_country" class="yprint-form-label">Land</label>
                        <select id="new_country" name="new_country" class="yprint-form-select" required>
                            <option value="DE">Deutschland</option>
                            <option value="AT">Österreich</option>
                            <option value="CH">Schweiz</option>
                            <!-- Weitere Länder hier -->
                        </select>
                    </div>
                    
                    <div class="address-form-actions">
                        <button type="button" class="yprint-button yprint-button-secondary btn-cancel-address">Abbrechen</button>
                        <button type="button" class="yprint-button btn-save-address">
                            <i class="fas fa-save mr-2"></i>Adresse speichern
                        </button>
                    </div>
                    
                    <div class="address-form-errors" style="display: none; margin-top: 15px;"></div>
                </form>
            </div>
        </div>
        
        <?php
        // Prüfen, ob wir im Bearbeitungsmodus sind
        $is_editing = false;
        $edit_address = null;
        
        if (isset($_GET['action']) && isset($_GET['address_id']) && $_GET['action'] === 'edit') {
            $edit_address_id = sanitize_text_field($_GET['address_id']);
            
            foreach ($additional_addresses as $address) {
                if ($address['id'] === $edit_address_id) {
                    $is_editing = true;
                    $edit_address = $address;
                    break;
                }
            }
        }
        
        // Wenn eine Adresse bearbeitet wird
        if ($is_editing && $edit_address):
        ?>
        
        <h3>Adresse bearbeiten</h3>
        
        <form method="POST" class="yprint-settings-form" id="edit-address-form">
            <?php wp_nonce_field('edit_shipping_address', 'edit_address_nonce'); ?>
            <input type="hidden" name="address_id" value="<?php echo esc_attr($edit_address['id']); ?>">
            
            <div class="yprint-form-group">
                <label for="address_name" class="yprint-form-label">Bezeichnung</label>
                <input type="text" 
                       id="address_name" 
                       name="address_name" 
                       class="yprint-form-input" 
                       value="<?php echo esc_attr($edit_address['name']); ?>" 
                       placeholder="z.B. Büro, Eltern, Ferienhaus" 
                       required>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="addr_first_name" class="yprint-form-label">Vorname</label>
                    <input type="text" 
                           id="addr_first_name" 
                           name="addr_first_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($edit_address['first_name']); ?>" 
                           placeholder="Vorname" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="addr_last_name" class="yprint-form-label">Nachname</label>
                    <input type="text" 
                           id="addr_last_name" 
                           name="addr_last_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($edit_address['last_name']); ?>" 
                           placeholder="Nachname" 
                           required>
                </div>
            </div>
            
            <!-- Unternehmen -->
            <div class="yprint-checkbox-row">
                <input type="checkbox" 
                       id="addr_is_company" 
                       name="addr_is_company" 
                       <?php checked(isset($edit_address['is_company']) && $edit_address['is_company'], true); ?>>
                <label for="addr_is_company">Lieferung an Unternehmen</label>
            </div>
            
            <div id="addr_company_field" class="yprint-company-fields" 
                 <?php echo (isset($edit_address['is_company']) && $edit_address['is_company']) ? 'style="display: block;"' : 'style="display: none;"'; ?>>
                <div class="yprint-form-group">
                    <label for="addr_company" class="yprint-form-label">Firmenname</label>
                    <input type="text" 
                           id="addr_company" 
                           name="addr_company" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($edit_address['company']); ?>" 
                           placeholder="Firmenname">
                </div>
            </div>
            
            <!-- Adresse mit Suche -->
            <div class="yprint-form-group yprint-address-search">
                <label for="address_search_edit" class="yprint-form-label">Adresse suchen</label>
                <input type="text" 
                       id="address_search_edit" 
                       class="yprint-form-input" 
                       placeholder="Adresse eingeben...">
                <div id="edit_address_loader" class="yprint-loader"></div>
                <div id="edit_address_suggestions" class="yprint-address-suggestions"></div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="addr_address_1" class="yprint-form-label">Straße</label>
                    <input type="text" 
                           id="addr_address_1" 
                           name="addr_address_1" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($edit_address['address_1']); ?>" 
                           placeholder="Straße" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="addr_address_2" class="yprint-form-label">Hausnummer</label>
                    <input type="text" 
                           id="addr_address_2" 
                           name="addr_address_2" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($edit_address['address_2']); ?>" 
                           placeholder="Hausnummer" 
                           required>
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="addr_postcode" class="yprint-form-label">PLZ</label>
                    <input type="text" 
                           id="addr_postcode" 
                           name="addr_postcode" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($edit_address['postcode']); ?>" 
                           placeholder="PLZ" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="addr_city" class="yprint-form-label">Stadt</label>
                    <input type="text" 
                           id="addr_city" 
                           name="addr_city" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($edit_address['city']); ?>" 
                           placeholder="Stadt" 
                           required>
                </div>
            </div>
            
            <div class="yprint-form-group">
                <label for="addr_country" class="yprint-form-label">Land</label>
                <select id="addr_country" 
                        name="addr_country" 
                        class="yprint-form-select" 
                        required>
                    <?php
                    if (class_exists('WC_Countries')) {
                        $countries_obj = new WC_Countries();
                        $countries = $countries_obj->get_shipping_countries();
                        foreach ($countries as $code => $name) {
                            $selected = ($edit_address['country'] === $code) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                    } else {
                        // Fallback, wenn WooCommerce's Länderklasse nicht verfügbar ist
                        $countries = array(
                            'DE' => 'Deutschland',
                            'AT' => 'Österreich',
                            'CH' => 'Schweiz',
                            'FR' => 'Frankreich',
                            'IT' => 'Italien',
                            'NL' => 'Niederlande',
                            'BE' => 'Belgien',
                            'LU' => 'Luxemburg',
                            'DK' => 'Dänemark',
                            'SE' => 'Schweden',
                            'FI' => 'Finnland',
                            'PL' => 'Polen',
                            'CZ' => 'Tschechien',
                            'GB' => 'Großbritannien',
                            'US' => 'Vereinigte Staaten'
                        );
                        foreach ($countries as $code => $name) {
                            $selected = ($edit_address['country'] === $code) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <button type="submit" class="yprint-button">Änderungen speichern</button>
                <a href="?tab=shipping" class="yprint-button yprint-button-secondary" style="margin-left: 10px;">Abbrechen</a>
            </div>
        </form>
        
        <?php
        // Wenn wir im Hinzufügen-Modus sind
        elseif (isset($_GET['action']) && $_GET['action'] === 'add'):
        ?>
        
        <h3>Neue Adresse hinzufügen</h3>
        
        <form method="POST" class="yprint-settings-form" id="add-address-form">
            <?php wp_nonce_field('add_shipping_address', 'add_address_nonce'); ?>
            
            <div class="yprint-form-group">
                <label for="address_name" class="yprint-form-label">Bezeichnung</label>
                <input type="text" 
                       id="address_name" 
                       name="address_name" 
                       class="yprint-form-input" 
                       value="" 
                       placeholder="z.B. Büro, Eltern, Ferienhaus" 
                       required>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="addr_first_name" class="yprint-form-label">Vorname</label>
                    <input type="text" 
                           id="addr_first_name" 
                           name="addr_first_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_first_name); ?>" 
                           placeholder="Vorname" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="addr_last_name" class="yprint-form-label">Nachname</label>
                    <input type="text" 
                           id="addr_last_name" 
                           name="addr_last_name" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_last_name); ?>" 
                           placeholder="Nachname" 
                           required>
                </div>
            </div>
            
            <!-- Unternehmen -->
            <div class="yprint-checkbox-row">
                <input type="checkbox" 
                       id="addr_is_company" 
                       name="addr_is_company" 
                       <?php checked($is_company_shipping, true); ?>>
                <label for="addr_is_company">Lieferung an Unternehmen</label>
            </div>
            
            <div id="addr_company_field" class="yprint-company-fields" 
                 <?php echo $is_company_shipping ? 'style="display: block;"' : 'style="display: none;"'; ?>>
                <div class="yprint-form-group">
                    <label for="addr_company" class="yprint-form-label">Firmenname</label>
                    <input type="text" 
                           id="addr_company" 
                           name="addr_company" 
                           class="yprint-form-input" 
                           value="<?php echo esc_attr($shipping_company); ?>" 
                           placeholder="Firmenname">
                </div>
            </div>
            
            <!-- Adresse mit Suche -->
            <div class="yprint-form-group yprint-address-search">
                <label for="address_search_new" class="yprint-form-label">Adresse suchen</label>
                <input type="text" 
                       id="address_search_new" 
                       class="yprint-form-input" 
                       placeholder="Adresse eingeben...">
                <div id="new_address_loader" class="yprint-loader"></div>
                <div id="new_address_suggestions" class="yprint-address-suggestions"></div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="addr_address_1" class="yprint-form-label">Straße</label>
                    <input type="text" 
                           id="addr_address_1" 
                           name="addr_address_1" 
                           class="yprint-form-input" 
                           value="" 
                           placeholder="Straße" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="addr_address_2" class="yprint-form-label">Hausnummer</label>
                    <input type="text" 
                           id="addr_address_2" 
                           name="addr_address_2" 
                           class="yprint-form-input" 
                           value="" 
                           placeholder="Hausnummer" 
                           required>
                </div>
            </div>
            
            <div class="yprint-form-row">
                <div class="yprint-form-group">
                    <label for="addr_postcode" class="yprint-form-label">PLZ</label>
                    <input type="text" 
                           id="addr_postcode" 
                           name="addr_postcode" 
                           class="yprint-form-input" 
                           value="" 
                           placeholder="PLZ" 
                           required>
                </div>
                
                <div class="yprint-form-group">
                    <label for="addr_city" class="yprint-form-label">Stadt</label>
                    <input type="text" 
                           id="addr_city" 
                           name="addr_city" 
                           class="yprint-form-input" 
                           value="" 
                           placeholder="Stadt" 
                           required>
                </div>
            </div>
            
            <div class="yprint-form-group">
                <label for="addr_country" class="yprint-form-label">Land</label>
                <select id="addr_country" 
                        name="addr_country" 
                        class="yprint-form-select" 
                        required>
                    <?php
                    if (class_exists('WC_Countries')) {
                        $countries_obj = new WC_Countries();
                        $countries = $countries_obj->get_shipping_countries();
                        foreach ($countries as $code => $name) {
                            $selected = ($shipping_country === $code) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                    } else {
                        // Fallback, wenn WooCommerce's Länderklasse nicht verfügbar ist
                        $countries = array(
                            'DE' => 'Deutschland',
                            'AT' => 'Österreich',
                            'CH' => 'Schweiz',
                            'FR' => 'Frankreich',
                            'IT' => 'Italien',
                            'NL' => 'Niederlande',
                            'BE' => 'Belgien',
                            'LU' => 'Luxemburg',
                            'DK' => 'Dänemark',
                            'SE' => 'Schweden',
                            'FI' => 'Finnland',
                            'PL' => 'Polen',
                            'CZ' => 'Tschechien',
                            'GB' => 'Großbritannien',
                            'US' => 'Vereinigte Staaten'
                        );
                        foreach ($countries as $code => $name) {
                            $selected = ($shipping_country === $code) ? 'selected' : '';
                            echo '<option value="' . esc_attr($code) . '" ' . $selected . '>' . esc_html($name) . '</option>';
                        }
                    }
                    ?>
                </select>
            </div>
            
            <div>
                <button type="submit" class="yprint-button">Adresse hinzufügen</button>
                <a href="?tab=shipping" class="yprint-button yprint-button-secondary" style="margin-left: 10px;">Abbrechen</a>
            </div>
        </form>
        
        <?php
        // Standardansicht - nur Address Manager anzeigen
        else:
        ?>
        
        <!-- Keine Standard-Formulare mehr - nur Address Manager Integration -->
        
        <?php endif; ?>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // HERE API Initialisierung
        const API_KEY = 'xPlTGXIrjg1O6Oea3e2gvo5lrN-iO1gT47Sc-VojWdU';
        
        // Unternehmensfeld umschalten
        $('#is_company, #is_company_shipping, #addr_is_company').change(function() {
            let fieldId;
            if (this.id === 'is_company') {
                fieldId = 'company_fields';
            } else if (this.id === 'is_company_shipping') {
                fieldId = 'company_shipping_fields';
            } else {
                fieldId = 'addr_company_field';
            }
            
            if (this.checked) {
                $('#' + fieldId).slideDown(300);
            } else {
                $('#' + fieldId).slideUp(300);
            }
        });
        
        // Firmenname von Rechnungsdaten übernehmen
        $('#use-billing-company').click(function(e) {
            e.preventDefault();
            $('#shipping_company').val('<?php echo esc_js($billing_company); ?>');
        });

        // Erfolgsmeldung nach 3 Sekunden ausblenden
        setTimeout(function() {
            $('.yprint-message-success').fadeOut(500);
        }, 3000);
        
        // Adresssuche für alle Adressformen einrichten
        setupAddressSearch('shipping', 'shipping_');
        setupAddressSearch('edit', 'addr_');
        setupAddressSearch('new', 'addr_');
        
        // Adresssuche-Funktion
        function setupAddressSearch(prefix, targetPrefix) {
            let searchTimeout;
            $(`#address_search_${prefix}`).on('input', function() {
                clearTimeout(searchTimeout);
                const query = $(this).val();
                
                $(`#${prefix}_address_loader`).hide();
                
                if (query.length < 3) {
                    $(`#${prefix}_address_suggestions`).hide();
                    return;
                }

                $(`#${prefix}_address_loader`).show();

                searchTimeout = setTimeout(function() {
                    $.ajax({
                        url: 'https://geocode.search.hereapi.com/v1/geocode',
                        data: {
                            q: query,
                            apiKey: API_KEY,
                            limit: 5,
                            lang: 'de',
                            in: 'countryCode:DEU,AUT,CHE,FRA,ITA'
                        },
                        type: 'GET',
                        success: function(data) {
                            $(`#${prefix}_address_loader`).hide();
                            const $suggestions = $(`#${prefix}_address_suggestions`);
                            $suggestions.empty();

                            if (data && data.items && data.items.length > 0) {
                                data.items.forEach(function(item) {
                                    const address = item.address;
                                    
                                    // Hauptadresszeile
                                    const mainLine = [
                                        address.street,
                                        address.houseNumber,
                                        address.postalCode,
                                        address.city
                                    ].filter(Boolean).join(' ');

                                    // Zusätzliche Informationen
                                    const secondaryLine = [
                                        address.district,
                                        address.state,
                                        address.countryName
                                    ].filter(Boolean).join(', ');

                                    const $suggestion = $('<div>').addClass('yprint-address-suggestion')
                                        .append($('<div>').addClass('yprint-suggestion-main').text(mainLine))
                                        .append($('<div>').addClass('yprint-suggestion-secondary').text(secondaryLine))
                                        .data('address', address);

                                    $suggestion.on('click', function() {
                                        const address = $(this).data('address');
                                        
                                        // Straße und Hausnummer trennen
                                        const street = address.street || '';
                                        const houseNumber = address.houseNumber || '';
                                        
                                        // Felder ausfüllen
                                        $(`#${targetPrefix}address_1`).val(street);
                                        $(`#${targetPrefix}address_2`).val(houseNumber);
                                        $(`#${targetPrefix}postcode`).val(address.postalCode || '');
                                        $(`#${targetPrefix}city`).val(address.city || '');
                                        
                                        // Land setzen
                                        if (address.countryCode) {
                                            const countryCode = address.countryCode.toUpperCase();
                                            $(`#${targetPrefix}country`).val(countryCode);
                                        }

                                        $suggestions.hide();
                                        $(`#address_search_${prefix}`).val('');
                                    });

                                    $suggestions.append($suggestion);
                                });

                                $suggestions.show();
                            }
                        },
                        error: function(xhr, status, error) {
                            $(`#${prefix}_address_loader`).hide();
                            console.error('Fehler bei der Adresssuche:', error);
                        }
                    });
                }, 500);
            });
            
            // Klick außerhalb schließt Vorschläge
            $(document).on('click', function(e) {
                if (!$(e.target).closest(`#address_search_${prefix}, #${prefix}_address_suggestions`).length) {
                    $(`#${prefix}_address_suggestions`).hide();
                }
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
}


/**
 * Shortcode für Datenschutzeinstellungen
 * 
 * Usage: [yprint_privacy_settings]
 */
function yprint_privacy_settings_shortcode() {
    ob_start();
    
    // Benutzer muss angemeldet sein
    if (!is_user_logged_in()) {
        return '<div class="yprint-login-required">
            <p>Bitte melde dich an, um deine Datenschutzeinstellungen zu verwalten.</p>
            <a href="' . esc_url(wc_get_page_permalink('myaccount')) . '" class="yprint-button">Zum Login</a>
        </div>';
    }
    
    $user_id = get_current_user_id();
    
    // Bestehende Privacy-Settings abrufen
    global $wpdb;
    $privacy_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}privacy_settings WHERE user_id = %d",
            $user_id
        ),
        ARRAY_A
    );
    
    // Cookie-Consents abrufen
    $cookie_consents = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT consent_type, granted, version, created_at 
             FROM {$wpdb->prefix}yprint_consents 
             WHERE user_id = %d AND consent_type LIKE 'COOKIE_%'
             ORDER BY consent_type",
            $user_id
        ),
        ARRAY_A
    );
    
    // Datenschutz-Consent abrufen
    $privacy_consent = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}yprint_consents 
             WHERE user_id = %d AND consent_type = 'PRIVACY_POLICY'
             ORDER BY created_at DESC LIMIT 1",
            $user_id
        ),
        ARRAY_A
    );
    
    ?>
    <div class="yprint-privacy-settings">
        <div class="yprint-settings-header">
            <h2>Datenschutz & Cookie-Einstellungen</h2>
            <p>Verwalte deine Datenschutz-Präferenzen und Cookie-Einstellungen.</p>
        </div>
        
        <!-- Datenschutzerklärung Status -->
        <div class="yprint-consent-status-card">
            <h3>📋 Datenschutzerklärung</h3>
            <?php if ($privacy_consent): ?>
                <div class="consent-status consent-granted">
                    <span class="status-icon">✅</span>
                    <div class="status-info">
                        <strong>Zugestimmt</strong>
                        <p>Du hast am <?php echo date_i18n('d.m.Y H:i', strtotime($privacy_consent['created_at'])); ?> der Datenschutzerklärung (Version <?php echo esc_html($privacy_consent['version']); ?>) zugestimmt.</p>
                    </div>
                </div>
                <p class="consent-note">
                    <strong>Hinweis:</strong> Die Zustimmung zur Datenschutzerklärung ist für die Nutzung unseres Dienstes erforderlich und kann nicht widerrufen werden, ohne das Konto zu löschen.
                </p>
            <?php else: ?>
                <div class="consent-status consent-missing">
                    <span class="status-icon">❌</span>
                    <div class="status-info">
                        <strong>Fehlend</strong>
                        <p>Keine Zustimmung zur Datenschutzerklärung gefunden.</p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Cookie-Einstellungen -->
        <div class="yprint-cookie-settings-card">
            <h3>🍪 Cookie-Einstellungen</h3>
            <p>Bestimme, welche Cookies wir verwenden dürfen.</p>
            
            <form id="yprint-cookie-preferences-form">
                <?php wp_nonce_field('yprint_cookie_preferences', 'cookie_preferences_nonce'); ?>
                
                <div class="cookie-categories">
                    <?php
                    $cookie_types = array(
                        'COOKIE_ESSENTIAL' => array(
                            'title' => 'Essenzielle Cookies',
                            'description' => 'Diese Cookies sind für die Grundfunktionen der Website erforderlich.',
                            'required' => true
                        ),
                        'COOKIE_ANALYTICS' => array(
                            'title' => 'Analyse Cookies',
                            'description' => 'Helfen uns zu verstehen, wie Besucher mit der Website interagieren.',
                            'required' => false
                        ),
                        'COOKIE_MARKETING' => array(
                            'title' => 'Marketing Cookies',
                            'description' => 'Werden verwendet, um dir relevante Anzeigen zu zeigen.',
                            'required' => false
                        ),
                        'COOKIE_FUNCTIONAL' => array(
                            'title' => 'Funktionale Cookies',
                            'description' => 'Ermöglichen erweiterte Funktionalitäten und Personalisierung.',
                            'required' => false
                        )
                    );
                    
                    // Aktuelle Werte ermitteln
                    $current_consents = array();
                    foreach ($cookie_consents as $consent) {
                        $current_consents[$consent['consent_type']] = (bool) $consent['granted'];
                    }
                    
                    foreach ($cookie_types as $type => $config):
                        $current_value = isset($current_consents[$type]) ? $current_consents[$type] : false;
                        $is_required = $config['required'];
                    ?>
                    <div class="cookie-category">
                        <label class="cookie-category-label">
                            <input 
                                type="checkbox" 
                                name="cookie_consent[<?php echo esc_attr($type); ?>]" 
                                value="1"
                                <?php checked($current_value || $is_required); ?>
                                <?php disabled($is_required); ?>
                                class="cookie-consent-checkbox"
                            >
                            <span class="cookie-category-title">
                                <?php echo esc_html($config['title']); ?>
                                <?php if ($is_required): ?>
                                    <span class="required-badge">Erforderlich</span>
                                <?php endif; ?>
                            </span>
                        </label>
                        <p class="cookie-category-description"><?php echo esc_html($config['description']); ?></p>
                        
                        <?php if (isset($current_consents[$type])): ?>
                            <?php
                            $consent_info = array_filter($cookie_consents, function($c) use ($type) {
                                return $c['consent_type'] === $type;
                            });
                            $consent_info = reset($consent_info);
                            ?>
                            <div class="consent-history">
                                <small>
                                    Letzte Änderung: <?php echo date_i18n('d.m.Y H:i', strtotime($consent_info['created_at'])); ?>
                                    (Version <?php echo esc_html($consent_info['version']); ?>)
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="yprint-btn yprint-btn-primary">
                        Cookie-Einstellungen speichern
                    </button>
                    <button type="button" id="open-cookie-banner" class="yprint-btn yprint-btn-secondary">
                        Cookie-Banner öffnen
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Bestehende Privacy-Settings -->
        <div class="yprint-privacy-preferences-card">
            <h3>🔒 Datenschutz-Präferenzen</h3>
            <p>Zusätzliche Einstellungen für den Umgang mit deinen Daten.</p>
            
            <form id="yprint-privacy-preferences-form">
                <?php wp_nonce_field('yprint_privacy_preferences', 'privacy_preferences_nonce'); ?>
                
                <div class="privacy-options">
                    <label class="privacy-option">
                        <input 
                            type="checkbox" 
                            name="data_sharing" 
                            value="1" 
                            <?php checked(isset($privacy_data['data_sharing']) ? $privacy_data['data_sharing'] : 1); ?>
                        >
                        <span class="privacy-option-title">Datenfreigabe für Produktverbesserungen</span>
                        <p class="privacy-option-desc">Anonymisierte Nutzungsdaten zur Verbesserung unserer Dienste verwenden.</p>
                    </label>
                    
                    <label class="privacy-option">
                        <input 
                            type="checkbox" 
                            name="data_collection" 
                            value="1" 
                            <?php checked(isset($privacy_data['data_collection']) ? $privacy_data['data_collection'] : 1); ?>
                        >
                        <span class="privacy-option-title">Erweiterte Datensammlung</span>
                        <p class="privacy-option-desc">Zusätzliche Informationen zur Personalisierung sammeln.</p>
                    </label>
                    
                    <label class="privacy-option">
                        <input 
                            type="checkbox" 
                            name="personalized_ads" 
                            value="1" 
                            <?php checked(isset($privacy_data['personalized_ads']) ? $privacy_data['personalized_ads'] : 1); ?>
                        >
                        <span class="privacy-option-title">Personalisierte Werbung</span>
                        <p class="privacy-option-desc">Anzeigen basierend auf deinen Interessen anzeigen.</p>
                    </label>
                    
                    <label class="privacy-option">
                        <input 
                            type="checkbox" 
                            name="preferences_analysis" 
                            value="1" 
                            <?php checked(isset($privacy_data['preferences_analysis']) ? $privacy_data['preferences_analysis'] : 1); ?>
                        >
                        <span class="privacy-option-title">Präferenz-Analyse</span>
                        <p class="privacy-option-desc">Deine Präferenzen analysieren, um dir bessere Empfehlungen zu geben.</p>
                    </label>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="yprint-btn yprint-btn-primary">
                        Datenschutz-Einstellungen speichern
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Daten-Management -->
        <div class="yprint-data-management-card">
            <h3>📊 Daten-Management</h3>
            <p>Exportiere oder lösche deine persönlichen Daten.</p>
            
            <div class="data-actions">
                <button type="button" id="export-user-data" class="yprint-btn yprint-btn-secondary">
                    📥 Meine Daten exportieren
                </button>
                <button type="button" id="delete-user-account" class="yprint-btn yprint-btn-danger">
                    🗑️ Konto löschen
                </button>
            </div>
            
            <div class="data-info">
                <h4>Was passiert beim Datenexport?</h4>
                <ul>
                    <li>Alle deine persönlichen Daten werden in einer ZIP-Datei zusammengefasst</li>
                    <li>Enthält: Profildaten, Bestellhistorie, gespeicherte Adressen, Consent-Historie</li>
                    <li>Download-Link wird per E-Mail gesendet</li>
                </ul>
                
                <h4>Was passiert beim Konto löschen?</h4>
                <ul>
                    <li>Alle persönlichen Daten werden unwiderruflich gelöscht</li>
                    <li>Bestellhistorie wird anonymisiert (für Buchhaltung erforderlich)</li>
                    <li>Alle Einwilligungen werden widerrufen</li>
                </ul>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Cookie-Präferenzen speichern
        $('#yprint-cookie-preferences-form').on('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const consents = {};
            
            // Checkbox-Werte sammeln
            $('.cookie-consent-checkbox').each(function() {
                const name = $(this).attr('name').replace('cookie_consent[', '').replace(']', '');
                consents[name] = $(this).is(':checked');
            });
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'yprint_save_consent',
                    nonce: '<?php echo wp_create_nonce('yprint_consent_nonce'); ?>',
                    consents: consents,
                    version: '1.0'
                },
                success: function(response) {
                    if (response.success) {
                        alert('Cookie-Einstellungen wurden gespeichert.');
                        location.reload();
                    } else {
                        alert('Fehler: ' + response.data.message);
                    }
                },
                error: function() {
                    alert('Netzwerkfehler beim Speichern.');
                }
            });
        });
        
        // Cookie-Banner öffnen
        $('#open-cookie-banner').on('click', function() {
            if (typeof window.yprintConsentManager !== 'undefined') {
                window.yprintConsentManager.showBanner();
            }
        });
    });
    </script>
    <?php
    
    return ob_get_clean();
}

// Login-optimierter gesammelter Settings-AJAX-Handler
function yprint_save_unified_settings_callback() {
    check_ajax_referer('yprint_settings_nonce', 'security');
    
    // Prüfen, ob Benutzer angemeldet ist
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du musst angemeldet sein, um deine Einstellungen zu ändern.'));
        return;
    }
    
    $user_id = get_current_user_id();
    $settings_type = isset($_POST['settings_type']) ? sanitize_text_field($_POST['settings_type']) : '';
    $settings_data = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
    
    $result = array('success' => false, 'message' => 'Unbekannter Einstellungstyp');
    
    switch ($settings_type) {
        case 'notifications':
            $result = $this->save_notification_settings($user_id, $settings_data);
            break;
        case 'privacy':
            $result = $this->save_privacy_settings($user_id, $settings_data);
            break;
        case 'personal':
            $result = $this->save_personal_settings($user_id, $settings_data);
            break;
        case 'checkout_preferences':
            $result = $this->save_checkout_preferences($user_id, $settings_data);
            break;
        default:
            $result = array('success' => false, 'message' => 'Unbekannter Einstellungstyp: ' . $settings_type);
    }
    
    if ($result['success']) {
        wp_send_json_success($result);
    } else {
        wp_send_json_error($result);
    }
}

// Hilfsfunktionen für die verschiedenen Settings-Typen
function save_notification_settings($user_id, $settings) {
    // Originaler Code aus yprint_save_notification_settings_callback() hier
    global $wpdb;
    
    $clean_settings = array(
        'email_orders' => isset($settings['email_orders']) ? intval($settings['email_orders']) : 0,
        'email_marketing' => isset($settings['email_marketing']) ? intval($settings['email_marketing']) : 0,
        'email_news' => isset($settings['email_news']) ? intval($settings['email_news']) : 0,
        'sms_orders' => isset($settings['sms_orders']) ? intval($settings['sms_orders']) : 0,
        'sms_marketing' => isset($settings['sms_marketing']) ? intval($settings['sms_marketing']) : 0
    );
    
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}notification_settings WHERE user_id = %d",
        $user_id
    ));
    
    if ($exists) {
        $result = $wpdb->update(
            $wpdb->prefix . 'notification_settings',
            $clean_settings,
            array('user_id' => $user_id)
        );
    } else {
        $clean_settings['user_id'] = $user_id;
        $clean_settings['created_at'] = current_time('mysql');
        $clean_settings['updated_at'] = current_time('mysql');
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'notification_settings',
            $clean_settings
        );
    }
    
    return array(
        'success' => $result !== false,
        'message' => $result !== false ? 'Benachrichtigungseinstellungen erfolgreich gespeichert.' : 'Fehler beim Speichern.'
    );
}

// Ersetze alte einzelne AJAX-Handler durch einheitlichen
add_action('wp_ajax_yprint_save_unified_settings', 'yprint_save_unified_settings_callback');

/**
 * AJAX-Handler für das Speichern von Datenschutzeinstellungen
 */
function yprint_save_privacy_settings_callback() {
    check_ajax_referer('privacy_settings_nonce', 'security');
    
    // Prüfen, ob Benutzer angemeldet ist
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Du musst angemeldet sein, um deine Datenschutzeinstellungen zu ändern.'));
        return;
    }
    
    $user_id = get_current_user_id();
    $settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
    
    if (empty($settings)) {
        wp_send_json_error(array('message' => 'Ungültige Daten übermittelt.'));
        return;
    }
    
    // Daten validieren und aufbereiten
    $clean_settings = array(
        'data_sharing' => isset($settings['data_sharing']) ? intval($settings['data_sharing']) : 0,
        'data_collection' => isset($settings['data_collection']) ? intval($settings['data_collection']) : 0,
        'personalized_ads' => isset($settings['personalized_ads']) ? intval($settings['personalized_ads']) : 0,
        'preferences_analysis' => isset($settings['preferences_analysis']) ? intval($settings['preferences_analysis']) : 0
    );
    
    // Prüfen, ob bereits Einstellungen vorhanden sind
    global $wpdb;
    $exists = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}privacy_settings WHERE user_id = %d",
        $user_id
    ));
    
    if ($exists) {
        // Bestehende Einstellungen aktualisieren
        $result = $wpdb->update(
            $wpdb->prefix . 'privacy_settings',
            $clean_settings,
            array('user_id' => $user_id)
        );
    } else {
        // Neue Einstellungen einfügen
        $clean_settings['user_id'] = $user_id;
        $clean_settings['created_at'] = current_time('mysql');
        $clean_settings['updated_at'] = current_time('mysql');
        
        $result = $wpdb->insert(
            $wpdb->prefix . 'privacy_settings',
            $clean_settings
        );
    }
    
    if ($result !== false) {
        wp_send_json_success(array('message' => 'Datenschutzeinstellungen wurden erfolgreich gespeichert.'));
    } else {
        wp_send_json_error(array('message' => 'Fehler beim Speichern der Einstellungen. Bitte versuche es später erneut.'));
    }
}
add_action('wp_ajax_yprint_save_privacy_settings', 'yprint_save_privacy_settings_callback');

/**
 * Vollständige Checkout-Account-Synchronisation
 * - Integration der Benutzereinstellungen in den Checkout
 * - Address Manager Integration
 * - Event-basierte Kommunikation
 */

/**
 * Standard-Land für neue Benutzer setzen
 */
function yprint_set_default_country($user_id) {
    // Standard-Land auf DE setzen, wenn nicht bereits gesetzt
    if (!get_user_meta($user_id, 'billing_country', true)) {
        update_user_meta($user_id, 'billing_country', 'DE');
    }
    
    if (!get_user_meta($user_id, 'shipping_country', true)) {
        update_user_meta($user_id, 'shipping_country', 'DE');
    }
}
add_action('user_register', 'yprint_set_default_country');

/**
 * AJAX Handler für Synchronisation Account → Checkout
 */
function yprint_sync_account_to_checkout_callback() {
    check_ajax_referer('yprint_sync_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht angemeldet'));
        return;
    }
    
    $user_id = get_current_user_id();
    $sync_type = isset($_POST['sync_type']) ? sanitize_text_field($_POST['sync_type']) : 'all';
    
    $response_data = array();
    
    // Adressdaten synchronisieren
    if ($sync_type === 'all' || $sync_type === 'addresses') {
        $addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
        if (!is_array($addresses)) {
            $addresses = array();
        }
        
        $response_data['addresses'] = $addresses;
        $response_data['default_address'] = get_user_meta($user_id, 'default_shipping_address', true);
    }
    
    
    // Benutzer-Präferenzen
    if ($sync_type === 'all' || $sync_type === 'preferences') {
        $response_data['preferences'] = array(
            'billing_same_as_shipping' => get_user_meta($user_id, 'billing_same_as_shipping', true),
            'save_payment_methods' => get_user_meta($user_id, 'save_payment_methods', true),
            'auto_fill_checkout' => get_user_meta($user_id, 'auto_fill_checkout', true),
        );
    }
    
    do_action('yprint_account_checkout_synced', $user_id, $sync_type, $response_data);
    
    wp_send_json_success($response_data);
}
add_action('wp_ajax_yprint_sync_account_to_checkout', 'yprint_sync_account_to_checkout_callback');

/**
 * AJAX Handler für Checkout → Account Sync
 */
function yprint_sync_checkout_to_account_callback() {
    check_ajax_referer('yprint_sync_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'Nicht angemeldet'));
        return;
    }
    
    $user_id = get_current_user_id();
    $checkout_data = isset($_POST['checkout_data']) ? wp_unslash($_POST['checkout_data']) : array();
    
    if (empty($checkout_data)) {
        wp_send_json_error(array('message' => 'Keine Daten übermittelt'));
        return;
    }
    
    // Adresse aus Checkout als neue gespeicherte Adresse hinzufügen
   if (isset($checkout_data['address']) && !empty($checkout_data['address'])) {
    $address_data = $checkout_data['address'];
    
    // Validierung der Adressdaten
    $required_fields = array('first_name', 'last_name', 'address_1', 'postcode', 'city', 'country');
    $is_valid = true;
    
    foreach ($required_fields as $field) {
        if (empty($address_data[$field])) {
            $is_valid = false;
            break;
        }
    }
    
    if ($is_valid) {
        // Neue Adresse zu gespeicherten Adressen hinzufügen
        $additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
        if (!is_array($additional_addresses)) {
            $additional_addresses = array();
        }
        
        $new_address = array(
            'id' => uniqid('checkout_addr_'),
            'name' => 'Aus Checkout (' . date('d.m.Y') . ')',
            'first_name' => sanitize_text_field($address_data['first_name']),
            'last_name' => sanitize_text_field($address_data['last_name']),
            'company' => isset($address_data['company']) ? sanitize_text_field($address_data['company']) : '',
            'address_1' => sanitize_text_field($address_data['address_1']),
            'address_2' => isset($address_data['address_2']) ? sanitize_text_field($address_data['address_2']) : '',
            'postcode' => sanitize_text_field($address_data['postcode']),
            'city' => sanitize_text_field($address_data['city']),
            'country' => sanitize_text_field($address_data['country']),
            'is_company' => !empty($address_data['company']),
            'created_from' => 'checkout'
        );
        
        $additional_addresses[] = $new_address;
        update_user_meta($user_id, 'additional_shipping_addresses', $additional_addresses);
        
        do_action('yprint_address_saved_from_checkout', $user_id, $new_address);
    }
}

do_action('yprint_checkout_account_synced', $user_id, $checkout_data);

wp_send_json_success(array('message' => 'Checkout-Daten erfolgreich in Account übernommen'));
}
add_action('wp_ajax_yprint_sync_checkout_to_account', 'yprint_sync_checkout_to_account_callback');

/**
* Event Handler für Address Manager Integration
*/
function yprint_trigger_address_events() {
// JavaScript Events für Account-Settings Integration
?>
<script>
// Event-System für Address Manager Integration
jQuery(document).ready(function($) {
    // Event: Adresse wurde ausgewählt
    $(document).on('address_selected', function(event, addressId, addressData) {
        console.log('Address selected:', addressId, addressData);
        
        // Trigger Account-Checkout Sync
        $.ajax({
            url: yprint_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_sync_account_to_checkout',
                nonce: yprint_checkout_params.nonce,
                sync_type: 'addresses',
                selected_address: addressId
            },
            success: function(response) {
                if (response.success) {
                    $(document).trigger('yprint_checkout_prefilled', [response.data]);
                }
            }
        });
    });
    
    // Event: Adresse wurde gespeichert
    $(document).on('address_saved', function(event, addressData) {
        console.log('Address saved:', addressData);
        
        // Benachrichtigung anzeigen
        if (typeof showMessage === 'function') {
            showMessage('Adresse wurde erfolgreich gespeichert', 'success');
        }
        
        $(document).trigger('yprint_address_updated', [addressData]);
    });
    
    // Event: Adresse wurde gelöscht
    $(document).on('address_deleted', function(event, addressId) {
        console.log('Address deleted:', addressId);
        
        // Prüfen ob das die aktuelle Checkout-Adresse war
        const currentAddressId = $('#selected_address_id').val();
        if (currentAddressId === addressId) {
            // Checkout-Felder leeren
            $('#street, #housenumber, #zip, #city').val('');
            $('#selected_address_id').val('');
        }
        
        $(document).trigger('yprint_address_updated');
    });
    
    // Event: Zahlungsmethode wurde gespeichert
    $(document).on('payment_method_saved', function(event, paymentData) {
        console.log('Payment method saved:', paymentData);
        
        // Checkout-Integration aktualisieren
        if (window.YPrintStripeCheckout && paymentData.is_default) {
            window.YPrintStripeCheckout.setDefaultPaymentMethod(paymentData.id);
        }
        
        $(document).trigger('yprint_payment_method_updated', [paymentData]);
    });
    
    // Event: Benutzer-Präferenzen wurden geändert
    $(document).on('preferences_changed', function(event, preferences) {
        console.log('Preferences changed:', preferences);
        
        // Auto-Fill Status aktualisieren
        if (preferences.auto_fill_checkout !== undefined) {
            $('body').toggleClass('auto-fill-enabled', preferences.auto_fill_checkout);
        }
        
        $(document).trigger('yprint_preferences_updated', [preferences]);
    });
});
</script>
<?php
}
add_action('wp_footer', 'yprint_trigger_address_events');


/**
* Erweiterte Formularverarbeitung mit Event-Integration
*/
function yprint_enhanced_form_processing() {
// Hook in bestehende Formularverarbeitung für Event-Triggering
add_action('yprint_after_address_save', 'yprint_trigger_address_saved_event', 10, 2);
add_action('yprint_after_payment_method_save', 'yprint_trigger_payment_saved_event', 10, 2);
}
add_action('init', 'yprint_enhanced_form_processing');

/**
* Event-Trigger für gespeicherte Adresse
*/
function yprint_trigger_address_saved_event($user_id, $address_data) {
// JavaScript Event über AJAX Response auslösen
if (defined('DOING_AJAX') && DOING_AJAX) {
    // Event wird über JavaScript in der AJAX Response ausgelöst
    add_filter('wp_send_json_success', function($response) use ($address_data) {
        if (!isset($response['events'])) {
            $response['events'] = array();
        }
        $response['events'][] = array(
            'type' => 'address_saved',
            'data' => $address_data
        );
        return $response;
    });
}
}

/**
* Event-Trigger für gespeicherte Zahlungsmethode
*/
function yprint_trigger_payment_saved_event($user_id, $payment_data) {
if (defined('DOING_AJAX') && DOING_AJAX) {
    add_filter('wp_send_json_success', function($response) use ($payment_data) {
        if (!isset($response['events'])) {
            $response['events'] = array();
        }
        $response['events'][] = array(
            'type' => 'payment_method_saved',
            'data' => $payment_data
        );
        return $response;
    });
}
}

/**
* Checkout-Präferenzen Management
*/
function yprint_checkout_preferences_shortcode() {
if (!is_user_logged_in()) {
    return '<p>Bitte melde dich an, um deine Checkout-Präferenzen zu verwalten.</p>';
}

$user_id = get_current_user_id();
$auto_fill = get_user_meta($user_id, 'auto_fill_checkout', true);
$save_addresses = get_user_meta($user_id, 'save_addresses_checkout', true);
$save_payment_methods = get_user_meta($user_id, 'save_payment_methods', true);

ob_start();
?>

<script>
jQuery(document).ready(function($) {
    $('#checkout-preferences-form').on('submit', function(e) {
        e.preventDefault();
        
        const preferences = {
            auto_fill_checkout: $('#auto_fill_checkout').is(':checked'),
            save_addresses_checkout: $('#save_addresses_checkout').is(':checked'),
            save_payment_methods: $('#save_payment_methods').is(':checked')
        };
        
        $.ajax({
            url: yprint_checkout_params.ajax_url,
            type: 'POST',
            data: {
                action: 'yprint_save_checkout_preferences',
                nonce: yprint_checkout_params.nonce,
                preferences: preferences
            },
            success: function(response) {
                if (response.success) {
                    $(document).trigger('preferences_changed', [preferences]);
                    
                    // Feedback anzeigen
                    const message = $('<div class="yprint-message yprint-message-success">Einstellungen gespeichert</div>');
                    $('.yprint-checkout-preferences').prepend(message);
                    setTimeout(() => message.fadeOut(), 3000);
                }
            }
        });
    });
});
</script>
<?php

return ob_get_clean();
}
add_shortcode('yprint_checkout_preferences', 'yprint_checkout_preferences_shortcode');

/**
* AJAX Handler für Checkout-Präferenzen
*/
function yprint_save_checkout_preferences_callback() {
check_ajax_referer('yprint_checkout_nonce', 'nonce');

if (!is_user_logged_in()) {
    wp_send_json_error(array('message' => 'Nicht angemeldet'));
    return;
}

$user_id = get_current_user_id();
$preferences = isset($_POST['preferences']) ? wp_unslash($_POST['preferences']) : array();

if (empty($preferences)) {
    wp_send_json_error(array('message' => 'Keine Präferenzen übermittelt'));
    return;
}

// Präferenzen speichern
update_user_meta($user_id, 'auto_fill_checkout', !empty($preferences['auto_fill_checkout']));
update_user_meta($user_id, 'save_addresses_checkout', !empty($preferences['save_addresses_checkout']));
update_user_meta($user_id, 'save_payment_methods', !empty($preferences['save_payment_methods']));

do_action('yprint_checkout_preferences_updated', $user_id, $preferences);

wp_send_json_success(array(
    'message' => 'Checkout-Präferenzen gespeichert',
    'preferences' => $preferences
));
}
add_action('wp_ajax_yprint_save_checkout_preferences', 'yprint_save_checkout_preferences_callback');


/**
* Erweiterte User Settings mit Checkout-Tab
*/
function yprint_user_settings_with_checkout_shortcode($atts) {
$atts = shortcode_atts(array(
    'test_mode' => 'no',
    'debug' => 'no',
), $atts, 'yprint_user_settings');

// Benutzer muss angemeldet sein
if (!is_user_logged_in()) {
    return '<div class="yprint-login-required">
        <p>Bitte melde dich an, um deine Einstellungen zu verwalten.</p>
        <a href="' . esc_url(wc_get_page_permalink('myaccount')) . '" class="yprint-button">Zum Login</a>
    </div>';
}

// Aktuellen Tab abrufen
$current_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'personal';

// Erweiterte Tabs mit Checkout-Integration
$tabs = array(
    'personal' => array('title' => 'Persönliche Daten', 'icon' => 'user'),
    'billing' => array('title' => 'Rechnungsadresse', 'icon' => 'file-invoice'),
    'shipping' => array('title' => 'Lieferadressen', 'icon' => 'shipping-fast'),
    'privacy' => array('title' => 'Datenschutz', 'icon' => 'shield-alt'),
);

ob_start();

// Meldungen verarbeiten
$message = isset($_GET['message']) ? sanitize_text_field($_GET['message']) : '';
$message_type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'info';

if ($message) {
    echo '<div class="yprint-message yprint-message-' . esc_attr($message_type) . '">';
    echo esc_html($message);
    echo '</div>';
}

?>
<div class="yprint-settings-container">
    <div class="yprint-settings-header">
        <h1>Mein Konto</h1>
        <p class="yprint-settings-intro">Verwalte deine Einstellungen und Checkout-Präferenzen</p>
    </div>

    <div class="yprint-settings-tabs-container">
        <div class="yprint-settings-grid">
            <?php foreach ($tabs as $tab_id => $tab_info) : 
                $active_class = ($current_tab === $tab_id) ? ' active' : '';
                ?>
                <a href="?tab=<?php echo esc_attr($tab_id); ?>" class="settings-item<?php echo esc_attr($active_class); ?>">
                    <div class="settings-item-left">
                        <div class="settings-icon">
                            <i class="fas fa-<?php echo esc_attr($tab_info['icon']); ?>"></i>
                        </div>
                        <div class="settings-title"><?php echo esc_html($tab_info['title']); ?></div>
                    </div>
                    <div class="settings-chevron">
                        <i class="fas fa-chevron-right"></i>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <div class="yprint-settings-content">
            <?php
            switch ($current_tab) {
                case 'personal':
                    echo do_shortcode('[yprint_personal_settings]');
                    break;
                case 'billing':
                    echo do_shortcode('[yprint_billing_settings]');
                    break;
                case 'shipping':
                    echo do_shortcode('[yprint_shipping_settings]');
                    break;
                case 'payment':
                    echo do_shortcode('[yprint_payment_settings]');
                    break;
                case 'checkout':
                    echo do_shortcode('[yprint_checkout_preferences]');
                    break;
                case 'privacy':
                    echo do_shortcode('[yprint_privacy_settings]');
                    break;
            }
            ?>
        </div>
    </div>
</div>
<?php

return ob_get_clean();
}

/**
* Registrierung der erweiterten Shortcodes
*/
function yprint_register_enhanced_shortcodes() {
// Bestehende Shortcodes überschreiben für Integration
remove_shortcode('yprint_user_settings');
add_shortcode('yprint_user_settings', 'yprint_user_settings_with_checkout_shortcode');
}
add_action('init', 'yprint_register_enhanced_shortcodes', 20);

/**
 * Einstellungen mit WooCommerce Checkout integrieren - Verschiedene Felder übertragen
 */
function yprint_checkout_load_user_data($checkout_fields) {
    if (!is_user_logged_in()) {
        // Für nicht angemeldete Benutzer, Standard-Land setzen
        $checkout_fields['billing']['billing_country']['default'] = 'DE';
        $checkout_fields['shipping']['shipping_country']['default'] = 'DE';
        return $checkout_fields;
    }
    
    $user_id = get_current_user_id();
    
    // Unternehmenseinstellungen abrufen
    $is_company = get_user_meta($user_id, 'is_company', true);
    $is_company_shipping = get_user_meta($user_id, 'is_company_shipping', true);
    
    // Benutzerdefinierte Felder zum WooCommerce-Checkout hinzufügen
    if ($is_company) {
        $checkout_fields['billing']['billing_company']['required'] = true;
        
        // USt-ID-Feld hinzufügen, wenn nicht vorhanden
        if (!isset($checkout_fields['billing']['billing_vat'])) {
            $checkout_fields['billing']['billing_vat'] = array(
                'label'     => 'USt.-ID',
                'required'  => false,
                'class'     => array('form-row-wide'),
                'clear'     => true
            );
        }
    }
    
    // Unternehmensfeld für Lieferadresse anpassen
    if ($is_company_shipping) {
        $checkout_fields['shipping']['shipping_company']['required'] = true;
    }
    
    return $checkout_fields;
}
add_filter('woocommerce_checkout_fields', 'yprint_checkout_load_user_data');

/**
 * Logout AJAX-Handler für E-Mail-Änderung
 */
function yprint_force_logout() {
    check_ajax_referer('force_logout_nonce', 'security');
    
    wp_logout();
    wp_clear_auth_cookie();
    wp_send_json_success();
    wp_die();
}
add_action('wp_ajax_custom_force_logout', 'yprint_force_logout');

/**
 * Handler für Ablehnung der Rechnungs-E-Mail
 */
function yprint_handle_billing_email_rejection() {
    if (isset($_GET['action']) && $_GET['action'] === 'reject_billing_email' && 
        isset($_GET['token']) && isset($_GET['user_id'])) {
        
        $user_id = intval($_GET['user_id']);
        $token = sanitize_text_field($_GET['token']);
        $stored_token = get_user_meta($user_id, 'billing_email_verification_token', true);
        
        if ($token === $stored_token) {
            // Token löschen
            delete_user_meta($user_id, 'billing_email_verification_token');
            
            // Betroffener Benutzer
            $user = get_userdata($user_id);
            $rejected_email = get_user_meta($user_id, 'alt_billing_email', true);
            
            // Ursprüngliche Benutzer-E-Mail wiederherstellen
            update_user_meta($user_id, 'billing_email', $user->user_email);
            delete_user_meta($user_id, 'alt_billing_email');
            
            // Benachrichtigung an Administratoren
            $admin_message_content = sprintf(
                'Eine Rechnungs-E-Mail-Änderung wurde abgelehnt:<br><br>
                Benutzer: %s (ID: %d)<br>
                Abgelehnte E-Mail: %s<br>
                Ursprüngliche E-Mail: %s<br><br>
                Bitte prüfen Sie den Fall.',
                $user->display_name,
                $user_id,
                $rejected_email,
                $user->user_email
            );
            
            // E-Mail-Template-Funktion verwenden, wenn verfügbar
            if (function_exists('yprint_get_email_template')) {
                $admin_message = yprint_get_email_template('Rechnungs-E-Mail-Änderung abgelehnt', 'Admin', $admin_message_content);
            } else {
                $admin_message = $admin_message_content;
            }
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            wp_mail(get_option('admin_email'), 'Rechnungs-E-Mail-Änderung abgelehnt', $admin_message, $headers);
            
            // Benutzer zur Bestätigungsseite weiterleiten
            wp_safe_redirect(home_url('/email-ablehnung-bestaetigt/'));
            exit;
        }
    }
}
add_action('init', 'yprint_handle_billing_email_rejection');

/**
 * AJAX-Handler für das Löschen des Benutzerkontos
 */
function yprint_delete_user_account_callback() {
    check_ajax_referer('delete_account_nonce', 'security');
    
// Prüfen, ob Benutzer angemeldet ist
if (!is_user_logged_in()) {
    wp_send_json_error(array('message' => 'Du musst angemeldet sein, um dein Konto zu löschen.'));
    return;
}

$user_id = get_current_user_id();
$user = get_userdata($user_id);
$password = isset($_POST['password']) ? $_POST['password'] : '';

if (empty($password)) {
    wp_send_json_error(array('message' => 'Bitte gib dein Passwort ein.'));
    return;
}

// Passwort überprüfen
if (!wp_check_password($password, $user->user_pass, $user_id)) {
    wp_send_json_error(array('message' => 'Das eingegebene Passwort ist nicht korrekt.'));
    return;
}

// Prüfen, ob offene Bestellungen vorhanden sind
if (function_exists('wc_get_orders')) {
    $args = array(
        'customer_id' => $user_id,
        'status' => array('processing', 'pending', 'on-hold'),
        'limit' => 1,
    );
    
    $orders = wc_get_orders($args);
    
    if (!empty($orders)) {
        wp_send_json_error(array('message' => 'Du hast noch offene Bestellungen. Bitte warte, bis diese abgeschlossen sind, bevor du dein Konto löschst.'));
        return;
    }
}

// Benutzer löschen
$deleted = wp_delete_user($user_id);

if ($deleted) {
    // Datenbank aufräumen - benutzerbezogene Daten löschen
    global $wpdb;
    
    // Eigene Tabellen bereinigen
    $tables = array(
        $wpdb->prefix . 'personal_data',
        $wpdb->prefix . 'payment_methods',
        $wpdb->prefix . 'notification_settings',
        $wpdb->prefix . 'privacy_settings',
        $wpdb->prefix . 'email_verifications',
        $wpdb->prefix . 'password_reset_tokens'
    );
    
    foreach ($tables as $table) {
        $wpdb->delete($table, array('user_id' => $user_id));
    }
    
    // Benutzer ausloggen
    wp_logout();
    
    wp_send_json_success(array('message' => 'Dein Konto wurde erfolgreich gelöscht.'));
} else {
    wp_send_json_error(array('message' => 'Fehler beim Löschen des Kontos. Bitte versuche es später erneut oder kontaktiere den Support.'));
}
}
add_action('wp_ajax_yprint_delete_user_account', 'yprint_delete_user_account_callback');

/**
* Handler für Datenexport
*/
function yprint_export_user_data() {
if (!is_user_logged_in()) {
    wp_die('Du musst angemeldet sein, um deine Daten herunterzuladen.');
}

$user_id = get_current_user_id();
$user = get_userdata($user_id);

// Benutzerdaten sammeln
$user_data = array(
    'account_info' => array(
        'username' => $user->user_login,
        'email' => $user->user_email,
        'registered_date' => $user->user_registered,
        'display_name' => $user->display_name,
        'first_name' => $user->first_name,
        'last_name' => $user->last_name,
    )
);

// Persönliche Daten hinzufügen
global $wpdb;
$personal_data = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}personal_data WHERE user_id = %d",
        $user_id
    ),
    ARRAY_A
);

if ($personal_data) {
    $user_data['personal_data'] = $personal_data;
}

// Adressen hinzufügen
$user_data['addresses'] = array(
    'billing' => array(
        'first_name' => get_user_meta($user_id, 'billing_first_name', true),
        'last_name' => get_user_meta($user_id, 'billing_last_name', true),
        'company' => get_user_meta($user_id, 'billing_company', true),
        'address_1' => get_user_meta($user_id, 'billing_address_1', true),
        'address_2' => get_user_meta($user_id, 'billing_address_2', true),
        'city' => get_user_meta($user_id, 'billing_city', true),
        'postcode' => get_user_meta($user_id, 'billing_postcode', true),
        'country' => get_user_meta($user_id, 'billing_country', true),
        'phone' => get_user_meta($user_id, 'billing_phone', true),
        'email' => get_user_meta($user_id, 'billing_email', true),
    ),
    'shipping' => array(
        'first_name' => get_user_meta($user_id, 'shipping_first_name', true),
        'last_name' => get_user_meta($user_id, 'shipping_last_name', true),
        'company' => get_user_meta($user_id, 'shipping_company', true),
        'address_1' => get_user_meta($user_id, 'shipping_address_1', true),
        'address_2' => get_user_meta($user_id, 'shipping_address_2', true),
        'city' => get_user_meta($user_id, 'shipping_city', true),
        'postcode' => get_user_meta($user_id, 'shipping_postcode', true),
        'country' => get_user_meta($user_id, 'shipping_country', true),
    )
);

// Zusätzliche Adressen
$additional_addresses = get_user_meta($user_id, 'additional_shipping_addresses', true);
if (is_array($additional_addresses) && !empty($additional_addresses)) {
    $user_data['addresses']['additional'] = $additional_addresses;
}

// Benachrichtigungseinstellungen
$notification_settings = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}notification_settings WHERE user_id = %d",
        $user_id
    ),
    ARRAY_A
);

if ($notification_settings) {
    $user_data['notification_settings'] = $notification_settings;
}

// Datenschutzeinstellungen
$privacy_settings = $wpdb->get_row(
    $wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}privacy_settings WHERE user_id = %d",
        $user_id
    ),
    ARRAY_A
);

if ($privacy_settings) {
    $user_data['privacy_settings'] = $privacy_settings;
}

/* Mobile-spezifische Anpassungen */
echo '<style>
@media (max-width: 767px) {
    .yprint-settings-content {
        display: none; /* Auf Mobile verstecken, da Navigation zur Unterseite führt */
    }
    
    .yprint-settings-grid {
        margin-bottom: 20px;
    }
    
    /* Mobile Formular-Anpassungen */
    .yprint-settings-page {
        padding: 0;
        background: transparent;
        box-shadow: none;
        border: none;
    }
    
    .yprint-form-row {
        flex-direction: column;
        gap: 15px;
    }
    
    .yprint-form-input,
    .yprint-form-select {
        font-size: 16px; /* Verhindert Zoom auf iOS */
    }
    
    /* Mobile Button-Anpassungen */
    .yprint-button {
        width: 100%;
        padding: 14px 20px;
        font-size: 16px;
        margin-bottom: 10px;
    }
    
    /* Mobile Address Cards */
    .yprint-address-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }
    
    .yprint-address-card {
        padding: 16px;
    }
    
    /* Mobile Payment Cards */
    .yprint-payment-card {
        padding: 16px;
        flex-direction: column;
        align-items: flex-start;
        gap: 12px;
    }
    
    .yprint-payment-actions {
        width: 100%;
        justify-content: space-between;
    }
}

/* Tablet Anpassungen */
@media (min-width: 768px) and (max-width: 992px) {
    .yprint-settings-tabs-container {
        flex-direction: column;
    }
    
    .yprint-settings-grid {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 10px;
    }
    
    .settings-item {
        flex: 0 0 calc(50% - 5px);
    }
}
</style>
<?php
return ob_get_clean();
}

/* Touch-Feedback für Mobile */
.settings-item.touching {
    background-color: #E8F0FE;
    transform: scale(0.98);
}

/* Settings Section - Visuell getrennt */
.settings-section {
    background-color: #FFFFFF;
    padding: 20px 16px;
    border-radius: 16px;
    box-shadow: 0px 4px 8px rgba(0, 0, 0, 0.04);
    margin-top: 24px;
    border: 1px solid #e5e5e5;
}

.settings-section h3 {
    margin-top: 0;
    margin-bottom: 16px;
    font-size: 16px;
    font-weight: 600;
    color: #1A1A1A;
    border-bottom: 1px solid #e5e5e5;
    padding-bottom: 8px;
}

/* Mobile Content Area - versteckt auf Mobile, sichtbar auf Desktop */
@media (max-width: 767px) {
    .yprint-settings-content {
        display: none;
    }
    
    /* Kompaktere Abstände auf Mobile */
    .yprint-settings-container {
        padding-bottom: 120px;
        overflow-x: hidden; /* Verhindert horizontales Scrollen */
    }
    
    /* Navigation Area - Fixierte Position */
    .yprint-settings-tabs-container {
        position: relative;
        z-index: 1;
        background-color: #F8F9FB;
        padding-bottom: 16px;
    }
    
    .yprint-settings-grid {
        position: relative;
        z-index: 2;
        background-color: #F8F9FB;
        padding-bottom: 8px;
    }
    
    .yprint-settings-content {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 100; /* Sehr hoher z-index */
        background-color: #FFFFFF;
        margin: 0;
        padding: 20px;
        border-radius: 0;
        border: none;
        width: 100vw;
        height: 100vh;
        overflow-y: auto;
        box-shadow: none;
        
        /* Initial versteckt */
        transform: translateY(100%);
        transition: transform 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
        opacity: 0;
        visibility: hidden;
    }
    
    /* Content sichtbar wenn aktiv */
    .yprint-settings-content.show {
        transform: translateY(0);
        opacity: 1;
        visibility: visible;
    }
    
    /* Navigation bleibt sichtbar aber darunter */
    .yprint-settings-grid {
        z-index: 1;
        position: relative;
    }
    
    /* Overlay-Hintergrund für Navigation */
    .content-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.4);
        z-index: 99;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.4s ease, visibility 0.4s ease;
    }
    
    .content-overlay.show {
        opacity: 1;
        visibility: visible;
    }
    
    /* Settings Section - Sichtbar wenn aktiv */
    .settings-section.show {
        transform: translateY(0);
    }
    
    /* Überlagerung für Navigation dahinter */
    .settings-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.3);
        z-index: 49;
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.4s ease, visibility 0.4s ease;
    }
    
    .settings-overlay.show {
        opacity: 1;
        visibility: visible;
    }
}
    
    .yprint-settings-grid {
        gap: 6px; /* Kompaktere Abstände zwischen Items */
    }
    
    .settings-item {
        padding: 14px 16px;
        min-height: 52px;
    }
    
    .settings-title {
        font-size: 15px;
    }
    
    .settings-icon {
        width: 28px;
        height: 28px;
        padding: 6px;
    }
    
    .settings-icon i {
        font-size: 12px;
    }
}

/* Close-Button für Mobile Settings */
.settings-close-btn {
    position: absolute;
    top: 16px;
    right: 16px;
    width: 32px;
    height: 32px;
    border: none;
    background-color: #f5f5f7;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    font-weight: bold;
    color: #666;
    cursor: pointer;
    z-index: 51;
    transition: all 0.2s ease;
}

.settings-close-btn:hover {
    background-color: #e5e5ea;
    color: #333;
    transform: scale(1.05);
}

.settings-close-btn:active {
    transform: scale(0.95);
}

/* Desktop - Close-Button verstecken */
@media (min-width: 768px) {
    .settings-close-btn {
        display: none;
    }
    
    .settings-overlay {
        display: none;
    }
    
    .settings-section {
        position: relative !important;
        transform: none !important;
        height: auto !important;
    }
}

/* Größere Touch-Targets und bessere Lesbarkeit */
@media (max-width: 480px) {
    .yprint-settings-header h1 {
        font-size: 22px;
    }
    
    .yprint-settings-intro {
        font-size: 13px;
    }
    
    .settings-item {
        padding: 16px 18px;
        min-height: 58px;
    }
    
    .settings-title {
        font-size: 16px;
    }
}
</style>';

// WooCommerce-Bestellungen hinzufügen, wenn verfügbar
if (function_exists('wc_get_orders')) {
    $orders = wc_get_orders(array(
        'customer_id' => $user_id,
        'limit' => -1,
    ));
    
    if (!empty($orders)) {
        $user_data['orders'] = array();
        
        foreach ($orders as $order) {
            $order_data = array(
                'order_id' => $order->get_id(),
                'order_number' => $order->get_order_number(),
                'date_created' => $order->get_date_created()->date('Y-m-d H:i:s'),
                'status' => $order->get_status(),
                'total' => $order->get_total(),
                'payment_method' => $order->get_payment_method_title(),
                'items' => array(),
            );
            
            // Bestellpositionen
            foreach ($order->get_items() as $item_id => $item) {
                $product = $item->get_product();
                $product_id = $item->get_product_id();
                $product_name = $item->get_name();
                $quantity = $item->get_quantity();
                $subtotal = $item->get_subtotal();
                
                $order_data['items'][] = array(
                    'product_id' => $product_id,
                    'name' => $product_name,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                );
            }
            
            $user_data['orders'][] = $order_data;
        }
    }
}

// Datei generieren und zum Download anbieten
$json_data = json_encode($user_data, JSON_PRETTY_PRINT);
$filename = 'yprint-user-data-' . $user_id . '-' . date('Ymd') . '.json';

// Header für den Download setzen
header('Content-Type: application/json');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($json_data));

// Keine Caching
header('Pragma: no-cache');
header('Expires: 0');

// Ausgabe und Beenden
echo $json_data;
exit;
}
add_action('admin_post_yprint_export_user_data', 'yprint_export_user_data');