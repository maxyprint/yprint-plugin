<?php
/*
Plugin Name: YPrint Hilfe-Seite
Description: Umfassende Hilfe-Seite mit FAQ, Support-Kontakt und technischer Hilfe im YPrint White Theme
Version: 2.0
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

<div class="yprint-help-container">
    <style>
        /* YPrint Help Page Styles - Responsive White Theme */
        .yprint-help-container {
            font-family: 'SF Pro Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
            background-color: #ffffff;
            color: #1d1d1f;
            min-height: 100vh;
            padding: 16px;
            line-height: 1.5;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .help-header {
            display: flex;
            align-items: center;
            margin-bottom: 32px;
            padding: 12px 0;
        }
        
        .back-button {
            background: transparent;
            border: none;
            color: #0079FF;
            cursor: pointer;
            padding: 8px;
            margin-right: 16px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        
        .back-button:hover {
            background-color: rgba(0, 121, 255, 0.1);
        }
        
        .back-button svg {
            width: 20px;
            height: 20px;
        }
        
        .help-title {
            font-size: 32px;
            font-weight: 600;
            margin: 0;
            color: #1d1d1f;
        }
        
        .search-bar {
  position: relative;
  display: flex;
  align-items: center;
  background-color: #f5f5f7;
  border: 1px solid #ccc;
  border-radius: 6px;
  padding: 8px;
}

.search-bar svg {
  position: absolute;
  left: 16px;
  width: 20px;
  height: 20px;
  color: #6e6e73;
  pointer-events: none;
}

.search-input {
  width: 100%;
  padding: 10px 10px 10px 44px; /* Platz für Icon */
  border: none;
  background: transparent;
  font-size: 16px;
  color: #1d1d1f;
  font-family: 'Roboto', sans-serif;
}

        
        .search-input::placeholder {
            color: #6e6e73;
            font-weight: 400;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #0079FF;
            background-color: #ffffff;
            box-shadow: 0 0 0 3px rgba(0, 121, 255, 0.1);
            transform: translateY(-1px);
        }
        
        .help-section {
            margin-bottom: 32px;
        }
        
        .help-content-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 24px;
        }
        
        .faq-dropdown, .tech-help-dropdown, .other-topics-dropdown {
            background-color: #ffffff;
            border: 1px solid #e5e5e5;
            border-radius: 16px;
            overflow: hidden;
            margin-bottom: 24px;
            box-shadow: 0 2px 16px rgba(0, 0, 0, 0.08);
            transition: all 0.2s ease;
        }
        
        .faq-dropdown:hover, .tech-help-dropdown:hover, .other-topics-dropdown:hover {
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .dropdown-title-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .dropdown-title-container:hover {
            background-color: #f6f7fa;
        }
        
        .dropdown-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #1d1d1f;
        }
        
        .chevron-icon {
            width: 20px;
            height: 20px;
            color: #6e6e73;
            transition: transform 0.3s ease;
        }
        
        .chevron-icon.rotated {
            transform: rotate(180deg);
        }
        
        .dropdown-content {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .dropdown-content.open {
            max-height: 400px;
            padding: 0 20px 20px 20px;
            overflow-y: auto;
        }
        
        .faq-list, .help-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .faq-item {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e5e5;
        }
        
        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .faq-question {
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 8px;
        }
        
        .faq-answer {
            color: #6e6e73;
            line-height: 1.6;
        }
        
        .contact-support {
            background-color: #ffffff;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 16px 0;
            color: #1d1d1f;
        }
        
        .contact-buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }
        
        .contact-button {
            flex: 1;
            min-width: 120px;
            padding: 14px 20px;
            background-color: #0079FF;
            color: #ffffff;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        
        .contact-button:hover {
            background-color: #0056b3;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 121, 255, 0.3);
        }
        
        .contact-button svg {
            width: 18px;
            height: 18px;
        }
        
        .help-list-item {
            display: block;
            padding: 12px 0;
            border-bottom: 1px solid #e5e5e5;
            transition: background-color 0.2s ease;
        }
        
        .help-list-item:last-child {
            border-bottom: none;
        }
        
        .help-list-item:hover {
            background-color: #f6f7fa;
            margin: 0 -12px;
            padding: 12px;
            border-radius: 8px;
        }
        
        .help-footer {
            text-align: center;
            padding: 20px 0;
            border-top: 1px solid #e5e5e5;
            margin-top: 32px;
        }
        
        .footer-links {
            color: #6e6e73;
            font-size: 14px;
        }
        
        .footer-links a {
            color: #6e6e73;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .footer-links a:hover {
            color: #0079FF;
        }
        
        /* Contact Form Modal */
        .contact-modal {
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
            padding: 16px;
        }
        
        .contact-modal.show {
            display: flex;
        }
        
        .contact-modal-content {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid #e5e5e5;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #1d1d1f;
        }
        
        .close-button {
            background: none;
            border: none;
            color: #6e6e73;
            cursor: pointer;
            padding: 4px;
            border-radius: 4px;
            transition: color 0.2s ease;
        }
        
        .close-button:hover {
            color: #1d1d1f;
        }
        
        .close-button svg {
            width: 24px;
            height: 24px;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #1d1d1f;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            background-color: #f6f7fa;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            color: #1d1d1f;
            font-size: 16px;
            transition: border-color 0.2s ease, background-color 0.2s ease;
            box-sizing: border-box;
        }
        
        .form-input::placeholder, .form-textarea::placeholder {
            color: #6e6e73;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #0079FF;
            background-color: #ffffff;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .btn-secondary {
            padding: 12px 20px;
            background-color: #f6f7fa;
            color: #1d1d1f;
            border: 1px solid #e5e5e5;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s ease;
        }
        
        .btn-secondary:hover {
            background-color: #e5e5ea;
        }
        
        .btn-primary {
            padding: 12px 20px;
            background-color: #0079FF;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: #0056b3;
        }
        
        .success-message {
            background-color: #f6f7fa;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            color: #28a745;
        }
        
        .error-message {
            background-color: #f6f7fa;
            border: 1px solid #dc3545;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            color: #dc3545;
        }
        
        /* Desktop Optimizations */
        @media (min-width: 769px) {
            .yprint-help-container {
                padding: 40px 32px;
            }
            
            .help-content-grid {
                grid-template-columns: 1fr 1fr;
                gap: 32px;
            }
            
            .contact-support {
                grid-column: 1 / -1;
                max-width: 600px;
                margin: 0 auto 32px auto;
            }
            
            .contact-buttons {
                max-width: 400px;
                margin: 0 auto;
            }
            
            .dropdown-content.open {
                max-height: 500px;
            }
            
            .search-results {
                max-width: 600px;
                margin: 0 auto 32px auto;
            }
        }
        
        @media (min-width: 1024px) {
            .yprint-help-container {
                padding: 60px 40px;
            }
            
            .help-content-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }
            
            .help-title {
                font-size: 36px;
            }
            
            .search-bar {
                max-width: 800px;
            }
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .yprint-help-container {
                padding: 12px;
            }
            
            .help-title {
                font-size: 24px;
            }
            
            .search-bar {
                margin-bottom: 24px;
            }
            
            .search-input {
                padding: 16px 16px 16px 52px;
                border-radius: 12px;
            }
            
            .search-bar svg {
                left: 16px;
            }
            
            .contact-buttons {
                flex-direction: column;
            }
            
            .contact-button {
                min-width: auto;
            }
            
            .contact-modal-content {
                margin: 16px;
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn-secondary, .btn-primary {
                width: 100%;
            }
        }
        
        /* Search functionality styles */
        .search-results {
            background-color: #ffffff;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: none;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
        }
        
        .search-results.show {
            display: block;
        }
        
        .search-result-item {
            padding: 12px 0;
            border-bottom: 1px solid #e5e5e5;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-item:hover {
            background-color: #f6f7fa;
            margin: 0 -12px;
            padding: 12px;
            border-radius: 8px;
        }
        
        .search-result-title {
            font-weight: 600;
            color: #1d1d1f;
            margin-bottom: 4px;
        }
        
        .search-result-snippet {
            color: #6e6e73;
            font-size: 14px;
        }
        
        .no-results {
            text-align: center;
            color: #6e6e73;
            padding: 20px;
        }
        
        /* Detail Content Styles */
        .detail-content {
            background-color: #ffffff;
            border: 1px solid #e5e5e5;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.05);
            display: none;
        }
        
        .detail-content.show {
            display: block;
        }
        
        .detail-header {
            display: flex;
            align-items: center;
            margin-bottom: 16px;
        }
        
        .detail-back-button {
            background: transparent;
            border: none;
            color: #0079FF;
            cursor: pointer;
            padding: 8px;
            margin-right: 12px;
            border-radius: 8px;
            transition: background-color 0.2s ease;
        }
        
        .detail-back-button:hover {
            background-color: rgba(0, 121, 255, 0.1);
        }
        
        .detail-title {
            font-size: 20px;
            font-weight: 600;
            margin: 0;
            color: #1d1d1f;
        }
        
        .detail-text {
            color: #6e6e73;
            line-height: 1.6;
        }
        
        .detail-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .detail-list-item {
            padding: 8px 0;
            border-bottom: 1px solid #e5e5e5;
            color: #6e6e73;
        }
        
        .detail-list-item:last-child {
            border-bottom: none;
        }
    </style>

    <header class="help-header">
        <button class="back-button" onclick="history.back()" aria-label="Zurück">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5M12 19l-7-7 7-7"/>
            </svg>
        </button>
        <h1 class="help-title">Hilfe</h1>
    </header>

    <div class="search-bar">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <path d="M21 21l-4.35-4.35"/>
        </svg>
        <input type="text" class="search-input" placeholder="Wonach suchst du?" id="helpSearch">
    </div>

    <div class="search-results" id="searchResults">
        <h3 class="section-title">Suchergebnisse</h3>
        <div id="searchResultsList"></div>
    </div>

    <!-- Detail Content Container -->
    <div class="detail-content" id="detailContent">
        <div class="detail-header">
            <button class="detail-back-button" onclick="hideDetailContent()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M19 12H5M12 19l-7-7 7-7"/>
                </svg>
            </button>
            <h2 class="detail-title" id="detailTitle"></h2>
        </div>
        <div id="detailBody"></div>
    </div>

    <?php if ($message_sent): ?>
    <div class="success-message">
        Deine Nachricht wurde erfolgreich gesendet. Wir werden uns bald bei dir melden!
    </div>
    <?php endif; ?>

    <?php if (!empty($form_errors)): ?>
    <div class="error-message">
        <?php foreach ($form_errors as $error): ?>
            <div><?php echo esc_html($error); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="help-content-grid">
        <div class="faq-dropdown">
            <div class="dropdown-title-container" onclick="toggleDropdown('faq')">
                <h2 class="dropdown-title">Häufig gestellte Fragen</h2>
                <svg class="chevron-icon" id="faqChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M6 9l6 6 6-6"/>
                </svg>
            </div>
            <div class="dropdown-content" id="faqContent">
                <ul class="faq-list">
                <li class="faq-item">
                    <div class="faq-question">Wie kann ich meine Bestellung verfolgen?</div>
                    <div class="faq-answer">Du erhältst eine E-Mail mit der Sendungsverfolgungsnummer, sobald deine Bestellung versendet wurde. Du kannst den Status auch in deinem Konto unter "Meine Bestellungen" einsehen.</div>
                </li>
                <li class="faq-item">
                    <div class="faq-question">Welche Zahlungsmethoden akzeptiert ihr?</div>
                    <div class="faq-answer">Wir akzeptieren alle gängigen Kreditkarten, PayPal, Apple Pay, Google Pay und SEPA-Lastschrift.</div>
                </li>
                <li class="faq-item">
                    <div class="faq-question">Wie lange dauert die Produktion?</div>
                    <div class="faq-answer">Die Produktion dauert in der Regel 2-5 Werktage. Print-on-Demand Artikel werden erst nach der Bestellung für dich produziert.</div>
                </li>
                <li class="faq-item">
                    <div class="faq-question">Kann ich meine Bestellung stornieren?</div>
                    <div class="faq-answer">Du kannst deine Bestellung innerhalb von 2 Stunden nach der Bestellung stornieren. Danach ist eine Stornierung leider nicht mehr möglich, da die Produktion bereits begonnen hat.</div>
                </li>
                <li class="faq-item">
                    <div class="faq-question">Welche Größen sind verfügbar?</div>
                    <div class="faq-answer">Wir bieten Größen von XS bis 3XL an. Die genauen Maße findest du in unserer Größentabelle bei jedem Produkt.</div>
                </li>
            </ul>
        </div>
    </div>

    <div class="contact-support">
        <h3 class="section-title">Support kontaktieren</h3>
        <div class="contact-buttons">
            <button class="contact-button" onclick="openContactModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/>
                    <polyline points="22,6 12,13 2,6"/>
                </svg>
                Nachricht
            </button>
            <a href="tel:+4915123456789" class="contact-button">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"/>
                </svg>
                Anrufen
            </a>
        </div>
    </div>

    <div class="tech-help-dropdown">
        <div class="dropdown-title-container" onclick="toggleDropdown('techHelp')">
            <h3 class="dropdown-title">Technische Hilfe</h3>
            <svg class="chevron-icon" id="techHelpChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
        <div class="dropdown-content" id="techHelpContent">
            <ul class="help-list">
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Häufige Probleme</div>
                        <div style="color: #6e6e73; font-size: 14px;">Design wird nicht angezeigt, Upload-Probleme, Zahlungsfehler</div>
                    </div>
                </li>
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Design-Upload Probleme</div>
                        <div style="color: #6e6e73; font-size: 14px;">Unterstützte Formate, maximale Dateigröße, Auflösung</div>
                    </div>
                </li>
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Website-Probleme</div>
                        <div style="color: #6e6e73; font-size: 14px;">Browser-Kompatibilität, Cache-Probleme, Login-Schwierigkeiten</div>
                    </div>
                </li>
            </ul>
        </div>
    </div>

    <div class="other-topics-dropdown">
        <div class="dropdown-title-container" onclick="toggleDropdown('otherTopics')">
            <h3 class="dropdown-title">Weitere Themen</h3>
            <svg class="chevron-icon" id="otherTopicsChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
        <div class="dropdown-content" id="otherTopicsContent">
            <ul class="help-list">
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Versand</div>
                        <div style="color: #6e6e73; font-size: 14px;">Versandkosten, Lieferzeiten, Sendungsverfolgung</div>
                    </div>
                </li>
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Rücksendungen</div>
                        <div style="color: #6e6e73; font-size: 14px;">Rückgaberecht, Bedingungen, Erstattung</div>
                    </div>
                </li>
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Zahlungen</div>
                        <div style="color: #6e6e73; font-size: 14px;">Verfügbare Zahlungsmethoden, Sicherheit</div>
                    </div>
                </li>
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Konto</div>
                        <div style="color: #6e6e73; font-size: 14px;">Registrierung, Bestellübersicht, Einstellungen</div>
                    </div>
                </li>
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Größentabelle</div>
                        <div style="color: #6e6e73; font-size: 14px;">Größenführer für alle Produktkategorien</div>
                    </div>
                </li>
                <li class="help-list-item">
                    <div>
                        <div style="font-weight: 600; margin-bottom: 4px;">Materialien</div>
                        <div style="color: #6e6e73; font-size: 14px;">Stoffqualität, Druckverfahren, Pflegehinweise</div>
                    </div>
                </li>
            </ul>
        </div>
    </div>
    
    </div> <!-- Ende help-content-grid -->

    <footer class="help-footer">
        <div class="footer-links">
            <a href="/datenschutz/">Rechtliches</a> | <a href="/about/">App Info</a>
        </div>
    </footer>

    <!-- Contact Modal -->
    <div class="contact-modal" id="contactModal">
        <div class="contact-modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Support kontaktieren</h3>
                <button class="close-button" onclick="closeContactModal()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"/>
                        <line x1="6" y1="6" x2="18" y2="18"/>
                    </svg>
                </button>
            </div>
            
            <form method="POST" action="">
                <?php wp_nonce_field('yprint_help_contact_action', 'yprint_help_contact_nonce'); ?>
                
                <div class="form-group">
                    <label for="contact_name" class="form-label">Name</label>
                    <input type="text" id="contact_name" name="contact_name" class="form-input" placeholder="Dein Name" required value="<?php echo esc_attr($_POST['contact_name'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="contact_email" class="form-label">E-Mail</label>
                    <input type="email" id="contact_email" name="contact_email" class="form-input" placeholder="deine@email.de" required value="<?php echo esc_attr($_POST['contact_email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <label for="contact_message" class="form-label">Nachricht</label>
                    <textarea id="contact_message" name="contact_message" class="form-textarea" placeholder="Beschreibe dein Problem oder deine Frage..." required><?php echo esc_textarea($_POST['contact_message'] ?? ''); ?></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn-secondary" onclick="closeContactModal()">Abbrechen</button>
                    <button type="submit" class="btn-primary">Nachricht senden</button>
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
                    <div class="detail-text">
                        <p>Hier sind die häufigsten technischen Probleme und ihre Lösungen:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>Design wird nicht korrekt angezeigt:</strong> Stelle sicher, dass dein Design im PNG oder JPEG Format vorliegt und die Auflösung mindestens 300 DPI beträgt.</li>
                            <li class="detail-list-item"><strong>Upload dauert zu lange:</strong> Überprüfe deine Internetverbindung und stelle sicher, dass die Dateigröße unter 50MB liegt.</li>
                            <li class="detail-list-item"><strong>Zahlungsfehler beim Checkout:</strong> Überprüfe deine Kartendaten und stelle sicher, dass genügend Guthaben vorhanden ist.</li>
                            <li class="detail-list-item"><strong>E-Mail-Bestätigung nicht erhalten:</strong> Überprüfe deinen Spam-Ordner oder kontaktiere unseren Support.</li>
                            <li class="detail-list-item"><strong>Probleme mit der Größenauswahl:</strong> Verwende unsere Größentabelle als Referenz und beachte, dass Größen je nach Produkttyp variieren können.</li>
                        </ul>
                    </div>
                `
            },
            'design-upload': {
                title: 'Design-Upload Probleme',
                content: `
                    <div class="detail-text">
                        <p>Probleme beim Hochladen deiner Designs? Hier findest du Hilfe:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>Unterstützte Dateiformate:</strong> PNG, JPEG, PDF (Vektordateien bevorzugt)</li>
                            <li class="detail-list-item"><strong>Maximale Dateigröße:</strong> 50MB pro Datei</li>
                            <li class="detail-list-item"><strong>Empfohlene Auflösung:</strong> Mindestens 300 DPI für beste Druckqualität</li>
                            <li class="detail-list-item"><strong>Farbmodus:</strong> CMYK für Druck, RGB für Bildschirmdarstellung</li>
                            <li class="detail-list-item"><strong>Transparenz:</strong> PNG-Dateien mit transparentem Hintergrund werden unterstützt</li>
                        </ul>
                        <p>Falls weiterhin Probleme auftreten, kontaktiere unseren Support mit der Fehlermeldung.</p>
                    </div>
                `
            },
            'website-problems': {
                title: 'Website-Probleme',
                content: `
                    <div class="detail-text">
                        <p>Technische Probleme mit der Website? Hier sind einige Lösungsansätze:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>Seite lädt nicht:</strong> Leere deinen Browser-Cache und lade die Seite neu (Strg+F5)</li>
                            <li class="detail-list-item"><strong>Login-Probleme:</strong> Überprüfe deine Anmeldedaten oder setze dein Passwort zurück</li>
                            <li class="detail-list-item"><strong>Browser-Kompatibilität:</strong> Verwende die neueste Version von Chrome, Firefox, Safari oder Edge</li>
                            <li class="detail-list-item"><strong>JavaScript-Fehler:</strong> Stelle sicher, dass JavaScript in deinem Browser aktiviert ist</li>
                            <li class="detail-list-item"><strong>Mobile Ansicht:</strong> Bei Problemen auf dem Smartphone versuche die Desktop-Version</li>
                        </ul>
                    </div>
                `
            },
            'shipping': {
                title: 'Versand',
                content: `
                    <div class="detail-text">
                        <p>Alles was du über unseren Versand wissen musst:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>Versandkosten Deutschland:</strong> 4,90€ (kostenlos ab 50€)</li>
                            <li class="detail-list-item"><strong>Versandkosten EU:</strong> 9,90€ (kostenlos ab 75€)</li>
                            <li class="detail-list-item"><strong>Lieferzeit Deutschland:</strong> 3-7 Werktage</li>
                            <li class="detail-list-item"><strong>Lieferzeit EU:</strong> 5-10 Werktage</li>
                            <li class="detail-list-item"><strong>Sendungsverfolgung:</strong> Du erhältst eine Tracking-Nummer per E-Mail</li>
                            <li class="detail-list-item"><strong>Express-Versand:</strong> Verfügbar für 9,90€ Aufpreis (1-3 Werktage)</li>
                        </ul>
                    </div>
                `
            },
            'returns': {
                title: 'Rücksendungen',
                content: `
                    <div class="detail-text">
                        <p>Informationen zu Rücksendungen und Umtausch:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>Rückgaberecht:</strong> 14 Tage ab Erhalt der Ware</li>
                            <li class="detail-list-item"><strong>Bedingung:</strong> Artikel müssen ungetragen und in Originalverpackung sein</li>
                            <li class="detail-list-item"><strong>Personalisierte Artikel:</strong> Können nur bei Produktionsfehlern zurückgegeben werden</li>
                            <li class="detail-list-item"><strong>Rücksendekosten:</strong> Trägt der Kunde (außer bei fehlerhaften Produkten)</li>
                            <li class="detail-list-item"><strong>Erstattung:</strong> Erfolgt innerhalb von 14 Tagen nach Erhalt der Rücksendung</li>
                        </ul>
                    </div>
                `
            },
            'payments': {
                title: 'Zahlungen',
                content: `
                    <div class="detail-text">
                        <p>Alle verfügbaren Zahlungsmethoden im Überblick:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>Kreditkarten:</strong> Visa, Mastercard, American Express</li>
                            <li class="detail-list-item"><strong>Debitkarten:</strong> Maestro, V-Pay</li>
                            <li class="detail-list-item"><strong>Digital Wallets:</strong> Apple Pay, Google Pay, PayPal</li>
                            <li class="detail-list-item"><strong>Bankeinzug:</strong> SEPA-Lastschrift</li>
                            <li class="detail-list-item"><strong>Sicherheit:</strong> Alle Zahlungen werden SSL-verschlüsselt übertragen</li>
                            <li class="detail-list-item"><strong>Währung:</strong> Alle Preise in Euro (EUR)</li>
                        </ul>
                    </div>
                `
            },
            'account': {
                title: 'Konto',
                content: `
                    <div class="detail-text">
                        <p>Verwalte dein YPrint-Konto:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>Registrierung:</strong> Kostenlos mit E-Mail-Adresse</li>
                            <li class="detail-list-item"><strong>Bestellübersicht:</strong> Alle deine Bestellungen auf einen Blick</li>
                            <li class="detail-list-item"><strong>Adressbuch:</strong> Speichere mehrere Lieferadressen</li>
                            <li class="detail-list-item"><strong>Passwort ändern:</strong> Jederzeit in den Kontoeinstellungen möglich</li>
                            <li class="detail-list-item"><strong>Newsletter:</strong> Abonniere unseren Newsletter für Angebote</li>
                            <li class="detail-list-item"><strong>Konto löschen:</strong> Kontaktiere unseren Support für die Löschung</li>
                        </ul>
                    </div>
                `
            },
            'size-chart': {
                title: 'Größentabelle',
                content: `
                    <div class="detail-text">
                        <p>Finde die richtige Größe für deine Bestellung:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>XS:</strong> Brustumfang 80-84 cm</li>
                            <li class="detail-list-item"><strong>S:</strong> Brustumfang 84-88 cm</li>
                            <li class="detail-list-item"><strong>M:</strong> Brustumfang 88-92 cm</li>
                            <li class="detail-list-item"><strong>L:</strong> Brustumfang 92-96 cm</li>
                            <li class="detail-list-item"><strong>XL:</strong> Brustumfang 96-100 cm</li>
                            <li class="detail-list-item"><strong>XXL:</strong> Brustumfang 100-104 cm</li>
                            <li class="detail-list-item"><strong>3XL:</strong> Brustumfang 104-108 cm</li>
                        </ul>
                        <p><strong>Tipp:</strong> Miss deinen Brustumfang an der breitesten Stelle und wähle die entsprechende Größe.</p>
                    </div>
                `
            },
            'materials': {
                title: 'Materialien',
                content: `
                    <div class="detail-text">
                        <p>Informationen über unsere verwendeten Materialien:</p>
                        <ul class="detail-list">
                            <li class="detail-list-item"><strong>T-Shirts:</strong> 100% Baumwolle, 180g/m²</li>
                            <li class="detail-list-item"><strong>Hoodies:</strong> 80% Baumwolle, 20% Polyester, 350g/m²</li>
                            <li class="detail-list-item"><strong>Sweatshirts:</strong> 85% Baumwolle, 15% Polyester, 280g/m²</li>
                            <li class="detail-list-item"><strong>Druckverfahren:</strong> Direct-to-Garment (DTG) für beste Qualität</li>
                            <li class="detail-list-item"><strong>Nachhaltigkeit:</strong> Wir verwenden OEKO-TEX zertifizierte Materialien</li>
                            <li class="detail-list-item"><strong>Pflege:</strong> Waschbar bei 30°C, nicht bleichen, mäßig heiß bügeln</li>
                        </ul>
                    </div>
                `
            }
        };

        // Dropdown Toggle Functionality
        function toggleDropdown(type) {
            const content = document.getElementById(type + 'Content');
            const chevron = document.getElementById(type + 'Chevron');
            
            content.classList.toggle('open');
            chevron.classList.toggle('rotated');
        }

        // Detail Content Functions
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
                document.querySelector('.faq-dropdown'),
                document.querySelector('.contact-support'),
                document.querySelector('.tech-help-dropdown'),
                document.querySelector('.other-topics-dropdown'),
                document.querySelector('.help-footer')
            ];
            
            sections.forEach(section => {
                if (section) {
                    section.style.display = 'none';
                }
            });
        }

        function showAllSections() {
            const sections = [
                document.querySelector('.faq-dropdown'),
                document.querySelector('.contact-support'),
                document.querySelector('.tech-help-dropdown'),
                document.querySelector('.other-topics-dropdown'),
                document.querySelector('.help-footer')
            ];
            
            sections.forEach(section => {
                if (section) {
                    section.style.display = 'block';
                }
            });
        }

        // Contact Modal Functions
        function openContactModal() {
            document.getElementById('contactModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }

        function closeContactModal() {
            document.getElementById('contactModal').classList.remove('show');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside
        document.getElementById('contactModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeContactModal();
            }
        });

        // Search Functionality
        const searchInput = document.getElementById('helpSearch');
        const searchResults = document.getElementById('searchResults');
        const searchResultsList = document.getElementById('searchResultsList');

        // Enhanced search data including detail content
        const searchData = [
            {
                title: "Bestellung verfolgen",
                content: "Du erhältst eine E-Mail mit der Sendungsverfolgungsnummer, sobald deine Bestellung versendet wurde.",
                category: "FAQ",
                action: "faq"
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
                category: "FAQ",
                action: "faq"
            },
            {
                title: "Bestellung stornieren",
                content: "Du kannst deine Bestellung innerhalb von 2 Stunden nach der Bestellung stornieren.",
                category: "FAQ",
                action: "faq"
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
                searchResultsList.innerHTML = '<div class="no-results">Keine Ergebnisse für "' + query + '" gefunden.</div>';
            } else {
                results.forEach(result => {
                    const resultItem = document.createElement('div');
                    resultItem.className = 'search-result-item';
                    
                    if (result.action && result.action !== 'faq') {
                        resultItem.onclick = function() {
                            eval(result.action);
                            searchResults.classList.remove('show');
                            searchInput.value = '';
                        };
                    }
                    
                    resultItem.innerHTML = `
                        <div class="search-result-title">${result.title}</div>
                        <div class="search-result-snippet">${result.content}</div>
                    `;
                    
                    searchResultsList.appendChild(resultItem);
                });
            }

            searchResults.classList.add('show');
        }

        // Clear search when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-bar') && !e.target.closest('.search-results')) {
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

        // Smooth scroll for better UX
        document.querySelectorAll('.help-list-item').forEach(item => {
            item.addEventListener('click', function() {
                // Add a subtle animation when clicking list items
                this.style.transform = 'scale(0.98)';
                setTimeout(() => {
                    this.style.transform = 'scale(1)';
                }, 150);
            });
        });

        // Back button enhancement
        const backButton = document.querySelector('.back-button');
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