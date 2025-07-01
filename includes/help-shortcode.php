<?php
/*
Plugin Name: YPrint Hilfe-Seite
Description: Umfassende Hilfe-Seite mit FAQ, Support-Kontakt und technischer Hilfe im YPrint White Theme
Version: 4.0
Author: YPrint Team
*/

function yprint_help_shortcode() {
    // Formularverarbeitung für Kontaktformular
    $message_sent = false;
    $form_errors = array();
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['yprint_help_contact_nonce'])) {
        if (wp_verify_nonce($_POST['yprint_help_contact_nonce'], 'yprint_help_contact_action')) {
            $name = sanitize_text_field($_POST['contact_name'] ?? '');
            $email = sanitize_email($_POST['contact_email'] ?? '');
            $message = sanitize_textarea_field($_POST['contact_message'] ?? '');
            
            // Turnstile Bot-Schutz Validierung
            $turnstile = YPrint_Turnstile::get_instance();
            if ($turnstile->is_enabled() && in_array('contact_forms', $turnstile->get_protected_pages())) {
                $turnstile_token = sanitize_text_field($_POST['cf-turnstile-response'] ?? '');
                $verification = $turnstile->verify_token($turnstile_token);
                if (!$verification['success']) {
                    $form_errors[] = $verification['error'] ?? 'Bot-Verifikation fehlgeschlagen';
                }
            }
            
            // Validierung
            if (empty($name)) {
                $form_errors[] = 'Name ist erforderlich';
            }
            if (empty($email) || !is_email($email)) {
                $form_errors[] = 'Gültige E-Mail-Adresse ist erforderlich';
            }
            if (empty($message)) {
                $form_errors[] = 'Nachricht ist erforderlich';
            }
            
            // E-Mail senden wenn keine Fehler
            if (empty($form_errors)) {
                $subject = 'YPrint Hilfe - Kontaktanfrage von ' . $name;
                $body = "Name: {$name}\n";
                $body .= "E-Mail: {$email}\n\n";
                $body .= "Nachricht:\n{$message}\n\n";
                
                if (is_user_logged_in()) {
                    $current_user = wp_get_current_user();
                    $body .= "User ID: {$current_user->ID}\n";
                    $body .= "Username: {$current_user->user_login}\n";
                }
                
                $headers = array('Content-Type: text/plain; charset=UTF-8');
                
                if (wp_mail('info@yprint.de', $subject, $body, $headers)) {
                    $message_sent = true;
                }
            }
        }
    }
    
    ob_start();
    ?>

<div class="help-shortcode">
    <style>
        /* YPrint Help Shortcode - Mobile First, Encapsulated Design */
        .help-shortcode {
            font-family: 'Roboto', -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, sans-serif;
            display: block;
            width: 100%;
            background-color: #ffffff;
            color: #1d1d1f;
            line-height: 1.5;
            padding: 20px;
            box-sizing: border-box;
        }
        
        .help-shortcode * {
            box-sizing: border-box;
        }
        
        /* Container für Desktop-Zentrierung */
        @media (min-width: 768px) {
            .help-shortcode {
                max-width: 1024px;
                margin: 0 auto;
                padding: 40px;
            }
        }
        
        /* Header */
        .help-shortcode .help-header {
            display: flex;
            align-items: center;
            min-height: 48px;
            margin-bottom: 32px;
            padding: 0;
        }
        
        .help-shortcode .help-back-button {
            background: transparent;
            border: none;
            color: #0079FF;
            cursor: pointer;
            padding: 12px;
            margin-right: 16px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 48px;
            min-height: 48px;
        }
        
        .help-shortcode .help-back-button:hover {
            background-color: rgba(0, 121, 255, 0.1);
        }
        
        .help-shortcode .help-back-button svg {
            width: 20px;
            height: 20px;
        }
        
        .help-shortcode .help-title {
            font-size: 24px;
            font-weight: 600;
            margin: 0;
            color: #1d1d1f;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        @media (min-width: 768px) {
            .help-shortcode .help-title {
                font-size: 32px;
            }
        }
        
        /* Suchleiste */
        .help-shortcode .help-search {
            width: 100%;
            min-width: 280px;
            max-width: 600px;
            margin: 0 auto 32px auto;
            position: relative;
        }
        
        .help-shortcode .help-search-input {
            width: 100%;
            padding: 16px 16px 16px 48px;
            background-color: #f6f7fa;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            color: #1d1d1f;
            font-size: 16px;
            font-family: 'Roboto', sans-serif;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .help-shortcode .help-search-input::placeholder {
            color: #6e6e73;
        }
        
        .help-shortcode .help-search-input:focus {
            outline: none;
            border-color: #0079FF;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 121, 255, 0.1);
        }
        
        .help-shortcode .help-search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: #6e6e73;
            pointer-events: none;
        }
        
        /* Messages */
        .help-shortcode .help-message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
        }
        
        .help-shortcode .help-message--success {
            background-color: #f0f9ff;
            border: 1px solid #28a745;
            color: #28a745;
        }
        
        .help-shortcode .help-message--error {
            background-color: #fff5f5;
            border: 1px solid #dc3545;
            color: #dc3545;
        }
        
        /* Search Results */
        .help-shortcode .help-search-results {
            background-color: #ffffff;
            border: 1px solid #DFDFDF;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 32px;
            display: none;
        }
        
        .help-shortcode .help-search-results.show {
            display: block;
        }
        
        .help-shortcode .help-search-result {
            padding: 16px 0;
            border-bottom: 1px solid #e5e5e5;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .help-shortcode .help-search-result:last-child {
            border-bottom: none;
        }
        
        .help-shortcode .help-search-result:hover {
            background-color: #f6f7fa;
            margin: 0 -16px;
            padding: 16px;
            border-radius: 8px;
        }
        
        .help-shortcode .help-search-result-title {
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .help-shortcode .help-search-result-snippet {
            color: #6e6e73;
            font-size: 14px;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        .help-shortcode .help-no-results {
            text-align: center;
            color: #6e6e73;
            padding: 40px 20px;
            font-size: 16px;
        }
        
        /* Content Grid */
        .help-shortcode .help-content {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 32px;
        }
        
        @media (min-width: 768px) {
            .help-shortcode .help-content {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 24px;
            }
        }
        
        /* Contact Support Section */
        .help-shortcode .help-contact-support {
            background-color: #ffffff;
            border: 1px solid #DFDFDF;
            border-radius: 16px;
            padding: 24px;
            text-align: center;
            margin-bottom: 24px;
        }
        
        @media (min-width: 768px) {
            .help-shortcode .help-contact-support {
                grid-column: 1 / -1;
                max-width: 600px;
                margin: 0 auto 32px auto;
                padding: 32px;
            }
        }
        
        .help-shortcode .help-section-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0 0 20px 0;
            color: #1d1d1f;
        }
        
        @media (min-width: 768px) {
            .help-shortcode .help-section-title {
                font-size: 24px;
            }
        }
        
        .help-shortcode .help-contact-buttons {
            display: flex;
            justify-content: center;
        }
        
        .help-shortcode .help-contact-button {
            padding: 16px 32px;
            background-color: #0079FF;
            color: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
            min-height: 48px;
            max-width: 250px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .help-shortcode .help-contact-button:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(0, 121, 255, 0.3);
        }
        
        .help-shortcode .help-contact-button svg {
            width: 18px;
            height: 18px;
            flex-shrink: 0;
        }
        
        /* Help Cards */
        .help-shortcode .help-card {
            background-color: #ffffff;
            border: 1px solid #DFDFDF;
            border-radius: 16px;
            overflow: hidden;
            transition: all 0.2s ease;
        }
        
        .help-shortcode .help-card:hover {
            border-color: #0079FF;
        }
        
        .help-shortcode .help-card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            cursor: pointer;
            transition: background-color 0.2s ease;
            min-height: 48px;
        }
        
        .help-shortcode .help-card-header:hover {
            background-color: #f6f7fa;
        }
        
        .help-shortcode .help-card-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #1d1d1f;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (min-width: 768px) {
            .help-shortcode .help-card-title {
                font-size: 20px;
            }
        }
        
        .help-shortcode .help-card-chevron {
            width: 20px;
            height: 20px;
            color: #6e6e73;
            transition: transform 0.3s ease;
            flex-shrink: 0;
        }
        
        .help-shortcode .help-card-chevron.rotated {
            transform: rotate(180deg);
        }
        
        .help-shortcode .help-card-content {
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease;
        }
        
        .help-shortcode .help-card-content.open {
            max-height: 400px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        
        /* Responsives Scrolling */
        @media (min-width: 768px) {
            .help-shortcode .help-card-content.open {
                max-height: 500px;
            }
        }
        
        @media (max-width: 480px) {
            .help-shortcode .help-card-content.open {
                max-height: 300px;
            }
        }

        /* Scrollbar Styling für bessere UX */
        .help-shortcode .help-card-content::-webkit-scrollbar {
            width: 6px;
        }
        
        .help-shortcode .help-card-content::-webkit-scrollbar-track {
            background: #f6f7fa;
            border-radius: 3px;
        }
        
        .help-shortcode .help-card-content::-webkit-scrollbar-thumb {
            background: #c7c7cc;
            border-radius: 3px;
        }
        
        .help-shortcode .help-card-content::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
        
        /* Firefox Scrollbar */
        .help-shortcode .help-card-content {
            scrollbar-width: thin;
            scrollbar-color: #c7c7cc #f6f7fa;
        }
        
        /* Smooth scroll behavior */
        .help-shortcode .help-card-content {
            scroll-behavior: smooth;
        }
        
        /* Padding adjustment for scrollable content */
        .help-shortcode .help-card-content.open .help-card-inner {
            padding-right: 16px;
        }
        
        .help-shortcode .help-card-inner {
            padding: 0 24px 24px 24px;
        }
        
        /* FAQ List */
        .help-shortcode .help-faq-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .help-shortcode .help-faq-item {
            padding: 16px 0;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .help-shortcode .help-faq-item:last-child {
            border-bottom: none;
        }
        
        .help-shortcode .help-faq-question {
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 8px;
            font-size: 15px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .help-shortcode .help-faq-answer {
            color: #6e6e73;
            line-height: 1.6;
            font-size: 14px;
        }
        
        /* Help Items List */
        .help-shortcode .help-items-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .help-shortcode .help-item {
            padding: 16px 0;
            border-bottom: 1px solid #e5e5e5;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .help-shortcode .help-item:last-child {
            border-bottom: none;
        }
        
        .help-shortcode .help-item:hover {
            background-color: #f6f7fa;
            margin: 0 -16px;
            padding: 16px;
            border-radius: 8px;
        }
        
        .help-shortcode .help-item-title {
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 4px;
            font-size: 15px;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .help-shortcode .help-item-description {
            color: #6e6e73;
            font-size: 13px;
            line-height: 1.4;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }
        
        /* Detail Content */
        .help-shortcode .help-detail {
            background-color: #ffffff;
            border: 1px solid #DFDFDF;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 32px;
            display: none;
        }
        
        .help-shortcode .help-detail.show {
            display: block;
        }
        
        .help-shortcode .help-detail-header {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            min-height: 48px;
        }
        
        .help-shortcode .help-detail-back {
            background: transparent;
            border: none;
            color: #0079FF;
            cursor: pointer;
            padding: 12px;
            margin-right: 12px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
            min-width: 48px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .help-shortcode .help-detail-back:hover {
            background-color: rgba(0, 121, 255, 0.1);
        }
        
        .help-shortcode .help-detail-back svg {
            width: 18px;
            height: 18px;
        }
        
        .help-shortcode .help-detail-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #1d1d1f;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        @media (min-width: 768px) {
            .help-shortcode .help-detail-title {
                font-size: 24px;
            }
        }
        
        .help-shortcode .help-detail-content p {
            color: #1d1d1f;
            line-height: 1.6;
            margin-bottom: 16px;
            font-size: 15px;
        }
        
        .help-shortcode .help-detail-list {
            list-style: none;
            padding: 0;
            margin: 16px 0;
        }
        
        .help-shortcode .help-detail-list-item {
            padding: 10px 0;
            border-bottom: 1px solid #e5e5e5;
            color: #1d1d1f;
            line-height: 1.6;
            font-size: 14px;
        }
        
        .help-shortcode .help-detail-list-item:last-child {
            border-bottom: none;
        }
        
        .help-shortcode .help-detail-list-item strong {
            color: #0079FF;
            font-weight: 600;
        }
        
        /* Contact Modal */
        .help-shortcode .help-modal {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        
        .help-shortcode .help-modal.show {
            display: flex;
        }
        
        .help-shortcode .help-modal-content {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid #DFDFDF;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }
        
        .help-shortcode .help-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            min-height: 48px;
        }
        
        .help-shortcode .help-modal-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #1d1d1f;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .help-shortcode .help-modal-close {
            background: none;
            border: none;
            color: #6e6e73;
            cursor: pointer;
            padding: 8px;
            border-radius: 8px;
            transition: all 0.2s ease;
            min-width: 48px;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .help-shortcode .help-modal-close:hover {
            color: #1d1d1f;
            background-color: #f6f7fa;
        }
        
        .help-shortcode .help-modal-close svg {
            width: 20px;
            height: 20px;
        }
        
        /* Form Styles */
        .help-shortcode .help-form-group {
            margin-bottom: 16px;
        }
        
        .help-shortcode .help-form-label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #1d1d1f;
            font-size: 14px;
        }
        
        .help-shortcode .help-form-input,
        .help-shortcode .help-form-textarea {
            width: 100%;
            padding: 12px 16px;
            background-color: #f6f7fa;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            color: #1d1d1f;
            font-size: 16px;
            transition: all 0.2s ease;
            font-family: 'Roboto', sans-serif;
        }
        
        .help-shortcode .help-form-input::placeholder,
        .help-shortcode .help-form-textarea::placeholder {
            color: #6e6e73;
        }
        
        .help-shortcode .help-form-input:focus,
        .help-shortcode .help-form-textarea:focus {
            outline: none;
            border-color: #0079FF;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 121, 255, 0.1);
        }
        
        .help-shortcode .help-form-textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .help-shortcode .help-form-actions {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-top: 24px;
        }
        
        @media (min-width: 480px) {
            .help-shortcode .help-form-actions {
                flex-direction: row;
                justify-content: flex-end;
            }
        }
        
        .help-shortcode .help-btn {
            padding: 12px 24px;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 500;
            transition: all 0.2s ease;
            min-height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        
        .help-shortcode .help-btn--secondary {
            background-color: #f6f7fa;
            color: #1d1d1f;
            border: 1px solid #e5e5e5;
        }
        
        .help-shortcode .help-btn--secondary:hover {
            background-color: #e5e5ea;
        }
        
        .help-shortcode .help-btn--primary {
            background-color: #0079FF;
            color: #ffffff;
            border: none;
        }
        
        .help-shortcode .help-btn--primary:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 16px rgba(0, 121, 255, 0.3);
        }
        
        /* Footer */
        .help-shortcode .help-footer {
            text-align: center;
            padding: 32px 0;
            border-top: 1px solid #e5e5e5;
            margin-top: 40px;
            min-height: 48px;
        }
        
        .help-shortcode .help-footer-links {
            color: #6e6e73;
            font-size: 14px;
        }
        
        .help-shortcode .help-footer-links a {
            color: #6e6e73;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .help-shortcode .help-footer-links a:hover {
            color: #0079FF;
        }

        /* Turnstile Integration */
        .help-shortcode .turnstile-container {
            display: flex;
            justify-content: center;
            margin: 16px 0;
        }
        
        .help-shortcode .turnstile-error {
            color: #dc3545;
            font-size: 14px;
            margin-top: 8px;
            text-align: center;
        }
        
        @media (max-width: 480px) {
            .help-shortcode .cf-turnstile {
                transform: scale(0.85);
                transform-origin: center;
            }
        }
    </style>

    <!-- Header -->
    <header class="help-header">
        <button class="help-back-button" onclick="history.back()" aria-label="Zurück">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
        </button>
        <h1 class="help-title">Hilfe & Support</h1>
    </header>

    <!-- Suchleiste -->
    <div class="help-search">
        <svg class="help-search-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <path d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" class="help-search-input" placeholder="Wonach suchst du?" id="helpSearch">
    </div>

    <!-- Suchergebnisse -->
    <div class="help-search-results" id="searchResults">
        <h3 class="help-section-title">Suchergebnisse</h3>
        <div id="searchResultsList"></div>
    </div>

    <!-- Detail View -->
    <div class="help-detail" id="detailContent">
        <div class="help-detail-header">
            <button class="help-detail-back" onclick="hideDetailContent()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="help-detail-title" id="detailTitle"></h2>
        </div>
        <div class="help-detail-content" id="detailBody"></div>
    </div>

    <!-- Messages -->
    <?php if ($message_sent): ?>
    <div class="help-message help-message--success">
        ✅ Deine Nachricht wurde erfolgreich gesendet. Wir werden uns bald bei dir melden!
    </div>
    <?php endif; ?>

    <?php if (!empty($form_errors)): ?>
    <div class="help-message help-message--error">
        ❌ <?php foreach ($form_errors as $error): ?>
            <div><?php echo esc_html($error); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Contact Support -->
    <div class="help-contact-support">
        <h2 class="help-section-title">Support kontaktieren</h2>
        <p style="color: #6e6e73; margin-bottom: 20px; font-size: 15px;">Hast du Fragen oder benötigst Hilfe? Unser Support-Team ist für dich da!</p>
        <div class="help-contact-buttons">
            <button class="help-contact-button" onclick="openContactModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Nachricht schreiben
            </button>
        </div>
    </div>

    <!-- Content Grid -->
    <div class="help-content">
        <!-- FAQ Card -->
        <div class="help-card">
            <div class="help-card-header" onclick="toggleCard('faq')">
                <h3 class="help-card-title">Häufig gestellte Fragen</h3>
                <svg class="help-card-chevron" id="faqChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </div>
            <div class="help-card-content" id="faqContent">
                <div class="help-card-inner">
                    <ul class="help-faq-list">
                        <li class="help-faq-item">
                            <div class="help-faq-question">Wie kann ich meine Bestellung verfolgen?</div>
                            <div class="help-faq-answer">Nach Abschluss der Bearbeitung deiner Bestellung, in der Regel innerhalb eines Werktags, erhältst du eine E-Mail mit der entsprechenden Sendungsverfolgungsnummer. In deinem Konto unter „Aufträge“ siehst du auch immer den aktuellen Bearbeitungsstand deiner Bestellung.</div>
                        </li>
                        <li class="help-faq-item">
                            <div class="help-faq-question">Welche Zahlungsmethoden akzeptiert ihr?</div>
                            <div class="help-faq-answer">Wir akzeptieren alle gängigen Wallet-Zahlungsmethoden sowie Kreditkarten und SEPA-Lastschrift.</div>
                        </li>
                        <li class="help-faq-item">
                            <div class="help-faq-question">Wie lange dauert die Produktion?</div>
                            <div class="help-faq-answer">Die Produktion dauert in der Regel einen Werktag. Print-on-Demand Artikel werden erst nach der Bestellung für dich produziert.</div>
                        </li>
                        <li class="help-faq-item">
                            <div class="help-faq-question">Kann ich meine Bestellung stornieren?</div>
                            <div class="help-faq-answer">Du kannst deine Bestellung innerhalb von 2 Stunden nach der Bestellung im Reiter „Aufträge“ stornieren. Danach ist eine Stornierung leider nicht mehr möglich, da die Produktion bereits begonnen hat.</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Technical Help Card -->
        <div class="help-card">
            <div class="help-card-header" onclick="toggleCard('techHelp')">
                <h3 class="help-card-title">Technische Hilfe</h3>
                <svg class="help-card-chevron" id="techHelpChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </div>
            <div class="help-card-content" id="techHelpContent">
                <div class="help-card-inner">
                    <ul class="help-items-list">
                        <li class="help-item" onclick="showDetailContent('common-issues')">
                            <div class="help-item-title">Häufige Probleme</div>
                            <div class="help-item-description">Design wird nicht angezeigt & Upload-Probleme</div>
                        </li>
                        <li class="help-item" onclick="showDetailContent('design-upload')">
                            <div class="help-item-title">Design-Upload Probleme</div>
                            <div class="help-item-description">Unterstützte Formate, maximale Dateigröße, Auflösung</div>
                        </li>
                        <li class="help-item" onclick="showDetailContent('website-problems')">
                            <div class="help-item-title">Website-Probleme</div>
                            <div class="help-item-description">Browser-Kompatibilität, Cache-Probleme, Login-Schwierigkeiten</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Other Topics Card -->
        <div class="help-card">
            <div class="help-card-header" onclick="toggleCard('otherTopics')">
                <h3 class="help-card-title">Weitere Themen</h3>
                <svg class="help-card-chevron" id="otherTopicsChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </div>
            <div class="help-card-content" id="otherTopicsContent">
                <div class="help-card-inner">
                    <ul class="help-items-list">
                        <li class="help-item" onclick="showDetailContent('shipping')">
                            <div class="help-item-title">Versand</div>
                            <div class="help-item-description">Versandkosten, Lieferzeiten, Sendungsverfolgung</div>
                        </li>
                        <li class="help-item" onclick="showDetailContent('returns')">
                            <div class="help-item-title">Rücksendungen</div>
                            <div class="help-item-description">Rückgaberecht, Bedingungen, Erstattung</div>
                        </li>
                        <li class="help-item" onclick="showDetailContent('payments')">
                            <div class="help-item-title">Zahlungen</div>
                            <div class="help-item-description">Verfügbare Zahlungsmethoden, Sicherheit</div>
                        </li>
                        <li class="help-item" onclick="showDetailContent('account')">
                            <div class="help-item-title">Konto</div>
                            <div class="help-item-description">Registrierung, Bestellübersicht, Einstellungen</div>
                        </li>
                        <li class="help-item" onclick="showDetailContent('size-chart')">
                            <div class="help-item-title">Größentabelle</div>
                            <div class="help-item-description">Größenführer für alle Produktkategorien</div>
                        </li>
                        <li class="help-item" onclick="showDetailContent('materials')">
                            <div class="help-item-title">Materialien</div>
                            <div class="help-item-description">Stoffqualität, Druckverfahren, Pflegehinweise</div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="help-footer">
        <div class="help-footer-links">
            <a href="/datenschutz/">Rechtliches</a>
        </div>
    </footer>

    <!-- Contact Modal -->
    <div class="help-modal" id="contactModal">
        <div class="help-modal-content">
            <div class="help-modal-header">
                <h3 class="help-modal-title">Support kontaktieren</h3>
                <button class="help-modal-close" onclick="closeContactModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            
            <form method="POST" action="">
                <?php wp_nonce_field('yprint_help_contact_action', 'yprint_help_contact_nonce'); ?>
                
                <div class="help-form-group">
                    <label for="contact_name" class="help-form-label">Name</label>
                    <input type="text" id="contact_name" name="contact_name" class="help-form-input" placeholder="Dein Name" required value="<?php echo esc_attr($_POST['contact_name'] ?? ''); ?>">
                </div>
                
                <div class="help-form-group">
                    <label for="contact_email" class="help-form-label">E-Mail</label>
                    <input type="email" id="contact_email" name="contact_email" class="help-form-input" placeholder="deine@email.de" required value="<?php echo esc_attr($_POST['contact_email'] ?? ''); ?>">
                </div>
                
                <div class="help-form-group">
                    <label for="contact_message" class="help-form-label">Nachricht</label>
                    <textarea id="contact_message" name="contact_message" class="help-form-textarea" placeholder="Beschreibe dein Problem oder deine Frage..." required><?php echo esc_textarea($_POST['contact_message'] ?? ''); ?></textarea>
                </div>
                
                <?php
                // Turnstile Widget einbinden wenn aktiviert
                $turnstile = YPrint_Turnstile::get_instance();
                if ($turnstile->is_enabled() && in_array('contact_forms', $turnstile->get_protected_pages())) {
                    echo '<div class="help-form-group">';
                    echo $turnstile->render_widget('help-contact-form', 'light');
                    echo '</div>';
                }
                ?>
                
                <div class="help-form-actions">
                    <button type="button" class="help-btn help-btn--secondary" onclick="closeContactModal()">Abbrechen</button>
                    <button type="submit" class="help-btn help-btn--primary">Nachricht senden</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Detail content data
        const detailData = {
            'common-issues': {
                title: 'Häufige Probleme',
                content: `
                    <div class="help-detail-content">
                        <p>Hier sind die häufigsten technischen Probleme und ihre Lösungen:</p>
                        <ul class="help-detail-list">
                            <li class="help-detail-list-item"><strong>Design wird nicht korrekt angezeigt:</strong> Stelle sicher, dass dein Design im PNG oder JPEG Format vorliegt und die Auflösung mindestens 300 DPI beträgt.</li>
                            <li class="help-detail-list-item"><strong>Upload dauert zu lange:</strong> Überprüfe deine Internetverbindung und stelle sicher, dass die Dateigröße unter 5MB liegt.</li>
                            <li class="help-detail-list-item"><strong>E-Mail-Bestätigung nicht erhalten:</strong> Überprüfe deinen Spam-Ordner oder kontaktiere unseren Support. In Einzelfällen verschwindet die E-Mail, unser Support hilft dir damit sofort!</li>
                            <li class="help-detail-list-item"><strong>Hinweis:</strong> Bei den meisten Webseitenproblemen hilft ein Reload der Webseite.</li>
                        </ul>
                    </div>
                `
            },
            'design-upload': {
                title: 'Design-Upload Probleme',
                content: `
                    <div class="help-detail-content">
                        <p>Probleme beim Hochladen deiner Designs? Hier findest du Hilfe:</p>
                        <ul class="help-detail-list">
                            <li class="help-detail-list-item"><strong>Unterstützte Dateiformate:</strong> PNG und JPEG. Sollte deine Bilddatei in einem anderen Format vorliegen, kannst du gängige Online-Tools zur einfachen Konvertierung nutzen.</li>
                            <li class="help-detail-list-item"><strong>Maximale Dateigröße:</strong> 5MB pro Datei</li>
                            <li class="help-detail-list-item"><strong>Empfohlene Auflösung:</strong> Mindestens 300 DPI für beste Druckqualität</li>
                            <li class="help-detail-list-item"><strong>Farbmodus:</strong> CMYK für Druck</li>
                            <li class="help-detail-list-item"><strong>Transparenz:</strong> PNG-Dateien mit transparentem Hintergrund werden unterstützt</li>
                        </ul>
                        <p>Falls weiterhin Probleme auftreten, kontaktiere unseren Support mit der Fehlermeldung.</p>
                    </div>
                `
            },
            'website-problems': {
                title: 'Website-Probleme',
                content: `
                    <div class="help-detail-content">
                        <p>Technische Probleme mit der Website? Hier sind einige Lösungsansätze:</p>
                        <ul class="help-detail-list">
                            <li class="help-detail-list-item"><strong>Seite lädt nicht:</strong> Leere deinen Browser-Cache und lade die Seite neu (Strg+F5)</li>
                            <li class="help-detail-list-item"><strong>Login-Probleme:</strong> Überprüfe deine Anmeldedaten oder setze dein Passwort zurück</li>
                            <li class="help-detail-list-item"><strong>Browser-Kompatibilität:</strong> Verwende die neueste Version von Chrome, Firefox, Safari oder Edge</li>
                            <li class="help-detail-list-item"><strong>Mobile Ansicht:</strong> Bei Problemen auf dem Smartphone versuche die Desktop-Version</li>
                        </ul>
                    </div>
                `
            },
            'shipping': {
                title: 'Versand',
                content: `
                    <div class="help-detail-content">
                        <p>Alles was du über unseren Versand wissen musst:</p>
                        <ul class="help-detail-list">
                            <li class="help-detail-list-item"><strong>Versandkosten Deutschland:</strong> 4,90€ (kostenlos ab 25€)</li>
                            <li class="help-detail-list-item"><strong>Versandkosten EU:</strong> 9,90€ (kostenlos ab 50€)</li>
                            <li class="help-detail-list-item"><strong>Lieferzeit Deutschland:</strong> 3-5 Werktage</li>
                            <li class="help-detail-list-item"><strong>Lieferzeit EU:</strong> 5-10 Werktage</li>
                            <li class="help-detail-list-item"><strong>Sendungsverfolgung:</strong> Du erhältst eine Tracking-Nummer per E-Mail</li>
                        </ul>
                    </div>
                `
            },
            'returns': {
                title: 'Rücksendungen',
                content: `
                    <div class="help-detail-content">
                        <p>Informationen zu Rücksendungen und Umtausch:</p>
                        <ul class="help-detail-list">
                            <li class="help-detail-list-item"><strong>Rückgaberecht:</strong> 14 Tage ab Erhalt der Ware</li>
                            <li class="help-detail-list-item"><strong>Bedingung:</strong> Artikel müssen ungetragen und in Originalverpackung sein</li>
                            <li class="help-detail-list-item"><strong>Personalisierte Artikel:</strong> Können nur bei Produktionsfehlern zurückgegeben werden</li>
                            <li class="help-detail-list-item"><strong>Rücksendekosten:</strong> Trägt der Kunde (außer bei fehlerhaften Produkten)</li>
                            <li class="help-detail-list-item"><strong>Erstattung:</strong> Erfolgt innerhalb von 14 Tagen nach Erhalt der Rücksendung</li>
                        </ul>
                    </div>
                `
            },
            'payments': {
                title: 'Zahlungen',
                content: `
                    <div class="help-detail-content">
                        <p>Alle verfügbaren Zahlungsmethoden im Überblick:</p>
                        <ul class="help-detail-list">
                            <li class="help-detail-list-item"><strong>Kreditkarten:</strong> Wir akzeptieren alle gängigen Kreditkarten.</li>
                            <li class="help-detail-list-item"><strong>Debitkarten:</strong> Alle gängigen Debitkarten.</li>
                            <li class="help-detail-list-item"><strong>Digital Wallets:</strong> Apple Pay, Google Pay, Microsoft Pay</li>
                            <li class="help-detail-list-item"><strong>Bankeinzug:</strong> SEPA-Lastschrift</li>
                            <li class="help-detail-list-item"><strong>Sicherheit:</strong> Alle Zahlungen werden SSL-verschlüsselt übertragen</li>
                            <li class="help-detail-list-item"><strong>Währung:</strong> Alle Preise in Euro (EUR)</li>
                        </ul>
                    </div>
                `
            },
            'account': {
                title: 'Konto',
                content: `
                    <div class="help-detail-content">
                        <p>Verwalte dein YPrint-Konto:</p>
                        <ul class="help-detail-list">
                            <li class="help-detail-list-item"><strong>Registrierung:</strong> https://yprint.de/register/</li>
                            <li class="help-detail-list-item"><strong>Bestellübersicht:</strong> Findest du im Reiter Aufträge über dein Dashboard</li>
                            <li class="help-detail-list-item"><strong>Adressbuch:</strong> In den Benutzereinstellungen kannst mehrere Adressen für den Checkout speichern</li>
                            <li class="help-detail-list-item"><strong>Passwort ändern:</strong> Jederzeit in den Kontoeinstellungen möglich</li>
                            <li class="help-detail-list-item"><strong>Newsletter:</strong> Folge uns auf Instagram, dort kriegst du Informationen zu allen Neuerungen @yprint.de</li>
                            <li class="help-detail-list-item"><strong>Konto löschen:</strong> Funktioniert jederzeit über https://yprint.de/settings/ im Reiter „Datenschutz“</li>
                        </ul>
                    </div>
                `
            },
            
        };

        // Toggle card functionality
        function toggleCard(type) {
            const content = document.getElementById(type + 'Content');
            const chevron = document.getElementById(type + 'Chevron');
            
            content.classList.toggle('open');
            chevron.classList.toggle('rotated');
        }

        // Detail content functions
        function showDetailContent(contentKey) {
            const detailContent = document.getElementById('detailContent');
            const detailTitle = document.getElementById('detailTitle');
            const detailBody = document.getElementById('detailBody');
            
            if (detailData[contentKey]) {
                detailTitle.textContent = detailData[contentKey].title;
                detailBody.innerHTML = detailData[contentKey].content;
                detailContent.classList.add('show');
                
                // Hide other sections
                hideAllSections();
                
                // Scroll to top
                detailContent.scrollIntoView({ behavior: 'smooth' });
            }
        }

        function hideDetailContent() {
            const detailContent = document.getElementById('detailContent');
            detailContent.classList.remove('show');
            showAllSections();
        }

        function hideAllSections() {
            const sections = [
                'searchResults',
                '.help-contact-support',
                '.help-content',
                '.help-footer'
            ];
            
            sections.forEach(selector => {
                const element = typeof selector === 'string' ? document.querySelector(selector) : selector;
                if (element) {
                    element.style.display = 'none';
                }
            });
        }

        function showAllSections() {
            const sections = [
                '.help-contact-support',
                '.help-content', 
                '.help-footer'
            ];
            
            sections.forEach(selector => {
                const element = document.querySelector(selector);
                if (element) {
                    element.style.display = '';
                }
            });
        }

        // Contact modal functions
        function openContactModal() {
            document.getElementById('contactModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeContactModal() {
            document.getElementById('contactModal').classList.remove('show');
            document.body.style.overflow = '';
        }

        // Close modal when clicking outside
        document.getElementById('contactModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeContactModal();
            }
        });

        // Search functionality
        const searchData = [
            {
                title: "Bestellung verfolgen",
                content: "Du erhältst eine E-Mail mit der Sendungsverfolgungsnummer, sobald deine Bestellung versendet wurde.",
                category: "FAQ"
            },
            {
                title: "Zahlungsmethoden",
                content: "Wir akzeptieren alle gängigen Kreditkarten, PayPal, Apple Pay, Google Pay und SEPA-Lastschrift.",
                category: "FAQ",
                action: "showDetailContent('payments')"
            },
            {
                title: "Produktionszeit",
                content: "Die Produktion dauert in der Regel 2-5 Werktage. Print-on-Demand Artikel werden erst nach der Bestellung produziert.",
                category: "FAQ"
            },
            {
                title: "Bestellung stornieren", 
                content: "Du kannst deine Bestellung innerhalb von 2 Stunden nach der Bestellung stornieren.",
                category: "FAQ"
            },
            {
                title: "Häufige Probleme",
                content: "Design wird nicht korrekt angezeigt, Upload dauert zu lange, Zahlungsfehler beim Checkout",
                category: "Technisch",
                action: "showDetailContent('common-issues')"
            },
            {
                title: "Design-Upload Probleme", 
                content: "Probleme beim Hochladen deiner Designs und unterstützte Dateiformate.",
                category: "Technisch",
                action: "showDetailContent('design-upload')"
            },
            {
                title: "Website-Probleme",
                content: "Lösungen für häufige Website-Probleme und Browser-Kompatibilität.",
                category: "Technisch", 
                action: "showDetailContent('website-problems')"
            },
            {
                title: "Versand",
                content: "Informationen zu Versandkosten, Lieferzeiten und internationalen Sendungen.",
                category: "Versand",
                action: "showDetailContent('shipping')"
            },
            {
                title: "Rücksendungen",
                content: "Wie du Artikel zurücksenden kannst und unsere Rückgabebedingungen.",
                category: "Rücksendung",
                action: "showDetailContent('returns')"
            },
            {
                title: "Größentabelle", 
                content: "Finde die richtige Größe für deine Bestellung mit unserer detaillierten Größentabelle.",
                category: "Produkt",
                action: "showDetailContent('size-chart')"
            },
            {
                title: "Materialien",
                content: "Informationen über die verwendeten Materialien und Pflegehinweise.",
                category: "Produkt", 
                action: "showDetailContent('materials')"
            },
            {
                title: "Konto verwalten",
                content: "Registrierung, Bestellübersicht, Adressbuch und Kontoeinstellungen.",
                category: "Konto",
                action: "showDetailContent('account')"
            }
        ];

        const searchInput = document.getElementById('helpSearch');
        const searchResults = document.getElementById('searchResults');
        const searchResultsList = document.getElementById('searchResultsList');
        let searchTimeout;

        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim().toLowerCase();

            searchTimeout = setTimeout(() => {
                if (query.length === 0) {
                    searchResults.classList.remove('show');
                    return;
                }

                if (query.length < 2) {
                    return;
                }

                const results = searchData.filter(item => 
                    item.title.toLowerCase().includes(query) ||
                    item.content.toLowerCase().includes(query) ||
                    item.category.toLowerCase().includes(query)
                );

                displaySearchResults(results, query);
            }, 300);
        });

        function displaySearchResults(results, query) {
            searchResultsList.innerHTML = '';

            if (results.length === 0) {
                searchResultsList.innerHTML = '<div class="help-no-results">Keine Ergebnisse für "' + query + '" gefunden.</div>';
            } else {
                results.forEach(result => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'help-search-result';
                    
                    if (result.action) {
                        resultItem.onclick = function() {
                            eval(result.action);
                            searchResults.classList.remove('show');
                            searchInput.value = '';
                        };
                    }
                    
                    resultItem.innerHTML = `
                        <div class="help-search-result-title">${result.title}</div>
                        <div class="help-search-result-snippet">${result.content}</div>
                    `;
                    
                    searchResultsList.appendChild(resultItem);
                });
            }

            searchResults.classList.add('show');
        }

        // Clear search when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.help-search') && !e.target.closest('.help-search-results')) {
                searchResults.classList.remove('show');
            }
        });

        // Keyboard navigation for search
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                searchResults.classList.remove('show');
                this.blur();
            }
        });

        // Auto-open contact modal if there were form errors
        <?php if (!empty($form_errors)): ?>
        window.addEventListener('load', function() {
            openContactModal();
        });
        <?php endif; ?>

        // Back button enhancement
        const backButton = document.querySelector('.help-back-button');
        if (window.history.length <= 1) {
            backButton.style.display = 'none';
        }
    </script>
</div>

<?php
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('yprint_help', 'yprint_help_shortcode');

// Enqueue Font Awesome for icons (optional, if not already loaded)
function yprint_help_enqueue_scripts() {
    if (has_shortcode(get_post()->post_content, 'yprint_help')) {
        wp_enqueue_style('font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css');
    }
}
add_action('wp_enqueue_scripts', 'yprint_help_enqueue_scripts');
?>