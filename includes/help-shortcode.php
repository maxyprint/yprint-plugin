<?php
/*
Plugin Name: YPrint Hilfe-Seite
Description: Umfassende Hilfe-Seite mit FAQ, Support-Kontakt und technischer Hilfe im YPrint Dark Theme
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
        /* YPrint Help Page Styles - Dark Theme */
        .yprint-help-container {
            font-family: 'SF Pro Text', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, sans-serif;
            background-color: #1d1d1f;
            color: #ffffff;
            min-height: 100vh;
            padding: 16px;
            line-height: 1.5;
        }
        
        .help-header {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            padding: 8px 0;
        }
        
        .back-button {
            background: transparent;
            border: none;
            color: #0079FF;
            cursor: pointer;
            padding: 8px;
            margin-right: 12px;
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
            font-size: 28px;
            font-weight: 600;
            margin: 0;
            color: #ffffff;
        }
        
        .search-bar {
            position: relative;
            margin-bottom: 32px;
        }
        
        .search-bar svg {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            color: #6e6e73;
        }
        
        .search-input {
            width: 100%;
            padding: 16px 16px 16px 52px;
            background-color: #444;
            border: none;
            border-radius: 12px;
            color: #ffffff;
            font-size: 16px;
            transition: background-color 0.2s ease;
        }
        
        .search-input::placeholder {
            color: #6e6e73;
        }
        
        .search-input:focus {
            outline: none;
            background-color: #555;
        }
        
        .help-section {
            margin-bottom: 24px;
        }
        
        .faq-dropdown {
            background-color: #444;
            border-radius: 12px;
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .faq-title-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .faq-title-container:hover {
            background-color: #555;
        }
        
        .faq-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
            color: #ffffff;
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
        
        .faq-content {
            padding: 0 20px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease, padding 0.3s ease;
        }
        
        .faq-content.open {
            max-height: 500px;
            padding: 0 20px 20px 20px;
        }
        
        .faq-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .faq-item {
            margin-bottom: 16px;
            padding-bottom: 16px;
            border-bottom: 1px solid #555;
        }
        
        .faq-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
            padding-bottom: 0;
        }
        
        .faq-question {
            font-weight: 600;
            color: #ffffff;
            margin-bottom: 8px;
        }
        
        .faq-answer {
            color: #6e6e73;
            line-height: 1.6;
        }
        
        .contact-support {
            background-color: #444;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .section-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0 0 16px 0;
            color: #ffffff;
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
            background-color: #f5f5f7;
            color: #1d1d1f;
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
            background-color: #e5e5ea;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .contact-button svg {
            width: 18px;
            height: 18px;
        }
        
        .technical-help, .other-topics {
            background-color: #444;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        
        .help-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .help-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #555;
            cursor: pointer;
            transition: color 0.2s ease;
        }
        
        .help-list-item:last-child {
            border-bottom: none;
        }
        
        .help-list-item:hover {
            color: #0079FF;
        }
        
        .help-list-item span {
            color: #6e6e73;
            font-size: 18px;
        }
        
        .help-footer {
            text-align: center;
            padding: 20px 0;
            border-top: 1px solid #444;
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
            background-color: rgba(0, 0, 0, 0.8);
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
            background-color: #1d1d1f;
            border-radius: 16px;
            padding: 24px;
            max-width: 500px;
            width: 100%;
            max-height: 90vh;
            overflow-y: auto;
            border: 1px solid #444;
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
            color: #ffffff;
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
            color: #ffffff;
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
            color: #ffffff;
        }
        
        .form-input, .form-textarea {
            width: 100%;
            padding: 12px 16px;
            background-color: #444;
            border: 1px solid #555;
            border-radius: 8px;
            color: #ffffff;
            font-size: 16px;
            transition: border-color 0.2s ease;
        }
        
        .form-input::placeholder, .form-textarea::placeholder {
            color: #6e6e73;
        }
        
        .form-input:focus, .form-textarea:focus {
            outline: none;
            border-color: #0079FF;
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
            background-color: #444;
            color: #ffffff;
            border: 1px solid #555;
            border-radius: 8px;
            cursor: pointer;
            font-size: 16px;
            transition: background-color 0.2s ease;
        }
        
        .btn-secondary:hover {
            background-color: #555;
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
            background-color: #444;
            border: 1px solid #28a745;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            color: #28a745;
        }
        
        .error-message {
            background-color: #444;
            border: 1px solid #dc3545;
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 20px;
            color: #dc3545;
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            .yprint-help-container {
                padding: 12px;
            }
            
            .help-title {
                font-size: 24px;
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
            background-color: #444;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
            display: none;
        }
        
        .search-results.show {
            display: block;
        }
        
        .search-result-item {
            padding: 12px 0;
            border-bottom: 1px solid #555;
        }
        
        .search-result-item:last-child {
            border-bottom: none;
        }
        
        .search-result-title {
            font-weight: 600;
            color: #ffffff;
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

    <div class="faq-dropdown">
        <div class="faq-title-container" onclick="toggleFAQ()">
            <h2 class="faq-title">Häufig gestellte Fragen</h2>
            <svg class="chevron-icon" id="faqChevron" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M6 9l6 6 6-6"/>
            </svg>
        </div>
        <div class="faq-content" id="faqContent">
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

    <div class="technical-help">
        <h3 class="section-title">Technische Hilfe</h3>
        <ul class="help-list">
            <li class="help-list-item" onclick="showCommonIssues()">
                Häufige Probleme
                <span>→</span>
            </li>
            <li class="help-list-item">
                Design-Upload Probleme
                <span>→</span>
            </li>
            <li class="help-list-item">
                Website-Probleme
                <span>→</span>
            </li>
        </ul>
    </div>

    <div class="other-topics">
        <h3 class="section-title">Weitere Themen</h3>
        <ul class="help-list">
            <li class="help-list-item">Versand</li>
            <li class="help-list-item">Rücksendungen</li>
            <li class="help-list-item">Zahlungen</li>
            <li class="help-list-item">Konto</li>
            <li class="help-list-item">Größentabelle</li>
            <li class="help-list-item">Materialien</li>
        </ul>
    </div>

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
        // FAQ Toggle Functionality
        function toggleFAQ() {
            const content = document.getElementById('faqContent');
            const chevron = document.getElementById('faqChevron');
            
            content.classList.toggle('open');
            chevron.classList.toggle('rotated');
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

        // Search data
        const searchData = [
            {
                title: "Bestellung verfolgen",
                content: "Du erhältst eine E-Mail mit der Sendungsverfolgungsnummer, sobald deine Bestellung versendet wurde.",
                category: "FAQ"
            },
            {
                title: "Zahlungsmethoden",
                content: "Wir akzeptieren alle gängigen Kreditkarten, PayPal, Apple Pay, Google Pay und SEPA-Lastschrift.",
                category: "FAQ"
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
                title: "Versand",
                content: "Informationen zu Versandkosten, Lieferzeiten und internationalen Sendungen.",
                category: "Versand"
            },
            {
                title: "Rücksendungen",
                content: "Wie du Artikel zurücksenden kannst und unsere Rückgabebedingungen.",
                category: "Rücksendung"
            },
            {
                title: "Größentabelle",
                content: "Finde die richtige Größe für deine Bestellung mit unserer detaillierten Größentabelle.",
                category: "Produkt"
            },
            {
                title: "Materialien",
                content: "Informationen über die verwendeten Materialien und Pflegehinweise.",
                category: "Produkt"
            },
            {
                title: "Design-Upload",
                content: "Probleme beim Hochladen deiner Designs und unterstützte Dateiformate.",
                category: "Technisch"
            },
            {
                title: "Website-Probleme",
                content: "Lösungen für häufige Website-Probleme und Browser-Kompatibilität.",
                category: "Technisch"
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
                    
                    resultItem.innerHTML = `
                        <div class="search-result-title">${result.title}</div>
                        <div class="search-result-snippet">${result.content}</div>
                    `;
                    
                    searchResultsList.appendChild(resultItem);
                });
            }

            searchResults.classList.add('show');
        }

        // Common Issues Function
        function showCommonIssues() {
            const commonIssues = [
                "Design wird nicht korrekt angezeigt",
                "Upload dauert zu lange",
                "Zahlungsfehler beim Checkout",
                "E-Mail-Bestätigung nicht erhalten",
                "Probleme mit der Größenauswahl"
            ];

            searchResultsList.innerHTML = '';
            
            commonIssues.forEach(issue => {
                const resultItem = document.createElement('div');
                resultItem.className = 'search-result-item';
                
                resultItem.innerHTML = `
                    <div class="search-result-title">${issue}</div>
                    <div class="search-result-snippet">Klicke hier für weitere Informationen zu diesem Problem.</div>
                `;
                
                searchResultsList.appendChild(resultItem);
            });

            searchResults.classList.add('show');
            searchInput.value = 'Häufige Probleme';
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