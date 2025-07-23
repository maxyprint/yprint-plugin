<?php
/**
 * YPrint Cloudflare Turnstile Integration
 * Zentrale Klasse für Bot-Schutz mit Admin-Panel Integration
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

class YPrint_Turnstile {
    
    private static $instance = null;
    private $options = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_options();
        
        if ($this->is_enabled()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_turnstile_script'));
            add_action('wp_ajax_verify_turnstile', array($this, 'ajax_verify_turnstile'));
            add_action('wp_ajax_nopriv_verify_turnstile', array($this, 'ajax_verify_turnstile'));
        }
        
        // Admin AJAX-Handler
        add_action('wp_ajax_yprint_test_turnstile_connection', array($this, 'test_connection'));
    }
    
    /**
     * Optionen aus WordPress Database laden
     */
    private function load_options() {
        $this->options = get_option('yprint_turnstile_options', array(
            'enabled' => false,
            'site_key' => '',
            'secret_key' => '',
            'protected_pages' => array()
        ));
    }
    
    /**
     * Prüft ob Turnstile aktiviert ist
     */
    public function is_enabled() {
        return !empty($this->options['enabled']) && 
               !empty($this->options['site_key']) && 
               !empty($this->options['secret_key']);
    }
    
    /**
     * Site Key abrufen
     */
    public function get_site_key() {
        return $this->options['site_key'] ?? '';
    }
    
    /**
     * Secret Key abrufen
     */
    private function get_secret_key() {
        return $this->options['secret_key'] ?? '';
    }
    
    /**
     * Geschützte Bereiche abrufen
     */
    public function get_protected_pages() {
        return $this->options['protected_pages'] ?? array();
    }
    
    /**
     * Cloudflare Turnstile Script laden
     */
    public function enqueue_turnstile_script() {
        if ($this->should_load_turnstile()) {
            wp_enqueue_script(
                'cloudflare-turnstile',
                'https://challenges.cloudflare.com/turnstile/v0/api.js',
                array(),
                null,
                true
            );
            
            wp_localize_script('cloudflare-turnstile', 'yprint_turnstile', array(
                'site_key' => $this->get_site_key(),
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('yprint_turnstile_nonce')
            ));
        }
    }
    
    /**
     * Prüft ob Turnstile auf aktueller Seite geladen werden soll
     */
    private function should_load_turnstile() {
        global $post;
        $protected_pages = $this->get_protected_pages();
        
        // Prüfe Seiten-basierte Schutzregeln
        if (in_array('registration', $protected_pages) && (is_page('register') || is_page('registration'))) {
            return true;
        }
        
        if (in_array('login', $protected_pages) && is_page('login')) {
            return true;
        }
        
        if (in_array('password_recovery', $protected_pages) && (is_page('password-recovery') || is_page('reset-password'))) {
            return true;
        }
        
        if (in_array('contact_forms', $protected_pages) && (is_page('help') || is_page('support') || is_page('contact'))) {
            return true;
        }
        
        if (in_array('checkout', $protected_pages) && is_page('checkout')) {
            return true;
        }
        
        // Shortcode-basierte Prüfung
        if (is_a($post, 'WP_Post')) {
            $content = $post->post_content;
            $critical_shortcodes = array(
                'yprint_login_form' => 'login',
                'yprint_registration_form' => 'registration',
                'yprint_password_recovery' => 'password_recovery',
                'yprint_help' => 'contact_forms'
            );
            
            foreach ($critical_shortcodes as $shortcode => $protection_type) {
                if (has_shortcode($content, $shortcode) && in_array($protection_type, $protected_pages)) {
                    return true;
                }
            }
        }
        
        return false;
    }
    
    /**
     * Turnstile Widget HTML generieren
     */
    public function render_widget($form_id = '', $theme = 'auto') {
        if (!$this->is_enabled()) {
            return '';
        }
        
        $widget_id = 'turnstile-' . $form_id . '-' . uniqid();
        
        $html = '<div class="turnstile-container" style="margin: 15px 0;">';
        $html .= sprintf(
            '<div class="cf-turnstile" id="%s" data-sitekey="%s" data-theme="%s" data-callback="onTurnstileSuccess" data-error-callback="onTurnstileError"></div>',
            esc_attr($widget_id),
            esc_attr($this->get_site_key()),
            esc_attr($theme)
        );
        $html .= '<input type="hidden" name="cf-turnstile-response" value="" />';
        $html .= '<div class="turnstile-error" style="color: #dc3232; margin-top: 10px; display: none;"></div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Server-side Turnstile Token Verifikation
     */
    public function verify_token($token, $user_ip = null) {
        if (empty($token)) {
            return array('success' => false, 'error' => 'Kein Turnstile Token bereitgestellt');
        }
        
        $user_ip = $user_ip ?: $this->get_client_ip();
        
        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => array(
                'secret' => $this->get_secret_key(),
                'response' => $token,
                'remoteip' => $user_ip
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            error_log('YPrint Turnstile: API Error - ' . $response->get_error_message());
            return array('success' => false, 'error' => 'Turnstile Verifikation fehlgeschlagen');
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['success'])) {
            error_log('YPrint Turnstile: Invalid API Response - ' . $body);
            return array('success' => false, 'error' => 'Ungültige Turnstile Antwort');
        }
        
        if (!$data['success']) {
            $error_codes = isset($data['error-codes']) ? implode(', ', $data['error-codes']) : 'Unbekannter Fehler';
            error_log('YPrint Turnstile: Verification Failed - ' . $error_codes);
            return array('success' => false, 'error' => 'Bot-Verifikation fehlgeschlagen');
        }
        
        return array('success' => true, 'data' => $data);
    }
    
    /**
     * AJAX Handler für Frontend Turnstile Verifikation
     */
    public function ajax_verify_turnstile() {
        check_ajax_referer('yprint_turnstile_nonce', 'nonce');
        
        $token = sanitize_text_field($_POST['token'] ?? '');
        $result = $this->verify_token($token);
        
        if ($result['success']) {
            wp_send_json_success(array('message' => 'Turnstile erfolgreich verifiziert'));
        } else {
            wp_send_json_error(array('message' => $result['error']));
        }
    }
    
    /**
     * Test-Connection für Admin-Panel
     */
    public function test_connection() {
        check_ajax_referer('yprint_turnstile_test_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Keine Berechtigung'));
        }
        
        $site_key = sanitize_text_field($_POST['site_key'] ?? '');
        $secret_key = sanitize_text_field($_POST['secret_key'] ?? '');
        
        if (empty($site_key) || empty($secret_key)) {
            wp_send_json_error(array('message' => 'Site Key und Secret Key sind erforderlich'));
        }
        
        // Testtoken für Verifikation erstellen (Dummy-Test)
        $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
            'body' => array(
                'secret' => $secret_key,
                'response' => 'test', // Dummy-Response
                'remoteip' => $this->get_client_ip()
            ),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => 'API-Verbindung fehlgeschlagen: ' . $response->get_error_message()));
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            wp_send_json_error(array('message' => 'Ungültige API-Antwort'));
        }
        
        // Bei Dummy-Test erwarten wir einen Fehler, aber eine gültige Antwort
        if (isset($data['error-codes']) && in_array('invalid-input-response', $data['error-codes'])) {
            wp_send_json_success(array('message' => 'Verbindung erfolgreich - Keys sind gültig'));
        } else {
            wp_send_json_error(array('message' => 'Keys scheinen ungültig zu sein'));
        }
    }
    
    /**
     * Client IP ermitteln
     */
    private function get_client_ip() {
        $headers = array('HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR');
        
        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                return trim($ips[0]);
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Turnstile JavaScript Helper Functions
     */
    public function get_turnstile_js() {
        if (!$this->is_enabled()) {
            return '';
        }
        
        return "
        <script>
        console.log('[Turnstile] Initialisierung: Turnstile wird gestartet...');
        window.onTurnstileSuccess = onTurnstileSuccess = function(token) {
            console.log('[Turnstile] Erfolg: Token empfangen:', token);
            console.log('[Turnstile] Token-Länge:', token.length);
            const loginForm = document.getElementById('yprint-loginform');
            let hiddenField = null;
            if (loginForm) {
                hiddenField = loginForm.querySelector('input[name=\"cf-turnstile-response\"]');
                console.log('[Turnstile] Hidden Field im Form gefunden:', !!hiddenField);
            }
            if (!hiddenField) {
                hiddenField = document.querySelector('input[name=\"cf-turnstile-response\"]');
                console.log('[Turnstile] Hidden Field global gefunden:', !!hiddenField);
            }
            if (!hiddenField) {
                // Warte bis Turnstile das Hidden Field erstellt hat
                setTimeout(() => {
                    const autoField = loginForm ? loginForm.querySelector('input[name=\"cf-turnstile-response\"]') : null;
                    if (autoField) {
                        autoField.value = token;
                        console.log('[Turnstile] Token in automatisches Feld gesetzt:', autoField.value.substring(0, 20) + '...');
                    } else {
                        // Fallback: Erstelle Hidden Field falls nicht vorhanden
                        hiddenField = document.createElement('input');
                        hiddenField.type = 'hidden';
                        hiddenField.name = 'cf-turnstile-response';
                        hiddenField.value = token;
                        if (loginForm) {
                            loginForm.appendChild(hiddenField);
                            console.log('[Turnstile] Hidden Field automatisch erstellt und befüllt');
                        }
                    }
                }, 100);
                return;
            }
            hiddenField.value = token;
            console.log('[Turnstile] Token gesetzt:', hiddenField.value.substring(0, 20) + '...');
            // Entferne eventuelle Error-Nachrichten
            const errorDiv = document.querySelector('.turnstile-error');
            if (errorDiv) {
                errorDiv.style.display = 'none';
            }
            document.dispatchEvent(new CustomEvent('turnstileSuccess', { detail: { token: token } }));
        };
        window.onTurnstileError = onTurnstileError = function(error) {
            console.error('[Turnstile] Fehler:', error);
            const errorDiv = document.querySelector('.turnstile-error');
            if (errorDiv) {
                errorDiv.textContent = 'Bot-Verifikung fehlgeschlagen. Bitte versuchen Sie es erneut.';
                errorDiv.style.display = 'block';
            }
            document.dispatchEvent(new CustomEvent('turnstileError', { detail: { error: error } }));
        };
        </script>";
    }
    
    /**
     * Hilfsfunktion: Prüft ob Request gültigen Turnstile Token enthält
     */
    public function has_valid_turnstile_token() {
        if (!$this->is_enabled()) {
            return true; // Wenn deaktiviert, immer durchlassen
        }
        
        $token = $_POST['cf-turnstile-response'] ?? '';
        if (empty($token)) {
            return false;
        }
        
        $result = $this->verify_token($token);
        return $result['success'];
    }
    
    /**
     * Automatische Widget-Einbindung für geschützte Formulare
     */
    public function auto_inject_widgets() {
        if (!$this->is_enabled()) {
            return;
        }
        
        // Generische Widget-Einbindung per JavaScript (Fallback)
        add_action('wp_footer', array($this, 'inject_generic_widgets'));
    }
    
    /**
     * Generische Widget-Einbindung per JavaScript (Fallback)
     */
    public function inject_generic_widgets() {
        if (!$this->should_load_turnstile()) {
            return;
        }
        // Skip Auto-Injection auf Login-Seite da bereits manuell eingefügt
        if (is_page('login')) {
            return;
        }
        $protected_pages = $this->get_protected_pages();
        $site_key = $this->get_site_key();
        
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
    console.log('🛡️ Auto-Injection: DOMContentLoaded Event gefeuert');
    console.log('🛡️ Auto-Injection: Body has data-turnstile-injection-done:', document.body.hasAttribute('data-turnstile-injection-done'));
    console.log('🛡️ Auto-Injection: Existing widgets:', document.querySelectorAll('.cf-turnstile, .cf-turnstile-rendered').length);
    
    // Mehrfach-Ausführung verhindern
    if (document.body.hasAttribute('data-turnstile-injection-done')) {
        console.log('🛡️ Turnstile: Auto-Injection bereits ausgeführt, überspringe');
        return;
    }
    document.body.setAttribute('data-turnstile-injection-done', 'true');
    console.log('🛡️ Auto-Injection: Markierung gesetzt, führe Injection aus');
            
            <?php if (in_array('registration', $protected_pages)): ?>
            // Registration-Formular Turnstile einfügen
const regForm = document.getElementById('register-form');
if (regForm && !regForm.querySelector('.cf-turnstile') && !regForm.hasAttribute('data-turnstile-injected')) {
    regForm.setAttribute('data-turnstile-injected', 'true');
                const submitGroup = regForm.querySelector('input[type="submit"]').closest('.yprint-input-group');
                if (submitGroup) {
                    const turnstileContainer = document.createElement('div');
                    turnstileContainer.className = 'yprint-input-group turnstile-widget-container';
                    turnstileContainer.style.cssText = 'text-align: center; margin: 20px 0;';
                    turnstileContainer.innerHTML = `
                        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($site_key); ?>" data-theme="light"></div>
                        <input type="hidden" name="cf-turnstile-response" value="" />
                    `;
                    submitGroup.parentNode.insertBefore(turnstileContainer, submitGroup);
                    console.log('🛡️ Turnstile: Widget automatisch in Registration-Formular eingefügt');
                }
            }
            <?php endif; ?>
        });
        </script>
        <?php
    }
}

// Singleton initialisieren
$turnstile_instance = YPrint_Turnstile::get_instance();

// Auto-Injection komplett deaktiviert für Debugging
// add_action('wp_loaded', array($turnstile_instance, 'auto_inject_widgets'));
