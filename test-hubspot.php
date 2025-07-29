<?php
/**
 * HubSpot Test
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

function yprint_test_hubspot_classes() {
    echo '<div style="background: #f0f0f0; padding: 20px; margin: 20px; border: 1px solid #ccc;">';
    echo '<h2>🍪 HubSpot Test</h2>';
    
    // Test HubSpot API
    echo '<h3>HubSpot API Test:</h3>';
    if (class_exists('YPrint_HubSpot_API')) {
        echo '<p style="color: green;">✅ YPrint_HubSpot_API class exists</p>';
        try {
            $api = YPrint_HubSpot_API::get_instance();
            echo '<p style="color: green;">✅ YPrint_HubSpot_API::get_instance() successful</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ YPrint_HubSpot_API::get_instance() failed: ' . $e->getMessage() . '</p>';
        }
    } else {
        echo '<p style="color: red;">❌ YPrint_HubSpot_API class not found</p>';
    }
    
    // Test HubSpot Admin
    echo '<h3>HubSpot Admin Test:</h3>';
    if (class_exists('YPrint_HubSpot_Admin')) {
        echo '<p style="color: green;">✅ YPrint_HubSpot_Admin class exists</p>';
        try {
            $admin = YPrint_HubSpot_Admin::get_instance();
            echo '<p style="color: green;">✅ YPrint_HubSpot_Admin::get_instance() successful</p>';
        } catch (Exception $e) {
            echo '<p style="color: red;">❌ YPrint_HubSpot_Admin::get_instance() failed: ' . $e->getMessage() . '</p>';
        }
    } else {
        echo '<p style="color: red;">❌ YPrint_HubSpot_Admin class not found</p>';
    }
    
    // Test Menü
    echo '<h3>Menu Test:</h3>';
    global $submenu;
    if (isset($submenu['yprint-plugin'])) {
        echo '<p style="color: green;">✅ YPrint submenu exists</p>';
        foreach ($submenu['yprint-plugin'] as $item) {
            if (strpos($item[2], 'hubspot') !== false) {
                echo '<p style="color: green;">✅ HubSpot menu item found: ' . $item[2] . '</p>';
            }
        }
    } else {
        echo '<p style="color: red;">❌ YPrint submenu not found</p>';
    }
    
    echo '</div>';
}

// Hook für Admin-Test
if (is_admin()) {
    add_action('admin_notices', 'yprint_test_hubspot_classes');
}