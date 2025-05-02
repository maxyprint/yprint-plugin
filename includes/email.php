<?php
/**
 * Email functions for YPrint
 *
 * @package YPrint
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function yprint_get_email_template($title, $username, $content) {
    ob_start();
    ?>
    <!DOCTYPE html>
    <html lang="de">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo esc_html($title); ?></title>
        <style>
            body {
                font-family: 'Roboto', sans-serif;
                background-color: #F6F7FA;
                color: #343434;
                margin: 0;
                padding: 0;
                height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            .email-wrapper {
                max-width: 600px;
                width: 100%;
                padding: 0;
                margin: 0;
                border: 1px solid #d3d3d3;
                border-radius: 10px;
                background-color: transparent;
            }
            .email-container {
                background-color: #ffffff;
                padding: 20px;
                border-radius: 10px;
                text-align: center;
            }
            .email-header {
                padding-bottom: 20px;
            }
            .email-header img {
                width: 100px;
                height: 40px;
            }
            .email-body {
                font-size: 15px;
                color: #878787;
                line-height: 1.5;
                text-align: center;
            }
            .email-body h1 {
                font-size: 25px;
                color: #000000;
                margin-bottom: 15px;
            }
            .email-body p {
                margin-bottom: 15px;
            }
            .email-footer {
                text-align: center;
                font-size: 12px;
                color: #808080;
                padding-top: 20px;
            }
            .email-footer a {
                color: #0079FF;
                text-decoration: none;
                text-transform: lowercase;
            }
            .email-footer a:hover {
                text-decoration: underline;
            }
        </style>
    </head>
    <body>
        <div class="email-wrapper">
            <div class="email-container">
                <div class="email-header">
                    <img src="https://yprint.de/wp-content/uploads/2025/02/yprint-logo.png" alt="YPrint Logo">
                </div>
                <div class="email-body">
                    <h1><?php echo esc_html($title); ?></h1>
                    <p>Hi <?php echo esc_html($username); ?>,</p>
                    <?php echo wp_kses_post($content); ?>
                </div>
                <div class="email-footer">
                    <p>© <?php echo date('Y'); ?> <a href="https://yprint.de" target="_blank" rel="noopener noreferrer">yprint</a> – Alle Rechte vorbehalten.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    return ob_get_clean();
}