<?php
/**
 * Test Add-On System
 * 
 * Simple test script to verify the add-on manager works
 * 
 * @package ElementorGeoPopup
 * @since 1.1.0
 */

// Include WordPress
require_once('../../../wp-config.php');

// Include our plugin files
require_once('elementor-geo-popup.php');
require_once('includes/addon-manager.php');
require_once('includes/addon-base.php');

echo "<h1>Geo Elementor Add-On System Test</h1>\n";

// Test 1: Add-on Manager Initialization
echo "<h2>Test 1: Add-on Manager Initialization</h2>\n";
$addon_manager = EGP_Addon_Manager::get_instance();
echo "✓ Add-on manager initialized successfully<br>\n";

// Test 2: Registered Add-ons
echo "<h2>Test 2: Registered Add-ons</h2>\n";
$registered_addons = $addon_manager->get_registered_addons();
echo "Found " . count($registered_addons) . " registered add-ons:<br>\n";
foreach ($registered_addons as $addon_id => $addon_data) {
    echo "- {$addon_data['name']} (ID: {$addon_id})<br>\n";
}

// Test 3: Installed Add-ons
echo "<h2>Test 3: Installed Add-ons</h2>\n";
$installed_addons = $addon_manager->get_installed_addons();
echo "Found " . count($installed_addons) . " installed add-ons:<br>\n";
foreach ($installed_addons as $addon_id => $addon_data) {
    $status = $addon_data['active'] ? 'Active' : 'Inactive';
    echo "- {$addon_id} ({$status})<br>\n";
}

// Test 4: City Add-on Installation
echo "<h2>Test 4: City Add-on Installation</h2>\n";
if (!$addon_manager->is_addon_installed('city-targeting')) {
    echo "Installing city-targeting add-on...<br>\n";
    $result = $addon_manager->install_addon('city-targeting');
    if (is_wp_error($result)) {
        echo "✗ Installation failed: " . $result->get_error_message() . "<br>\n";
    } else {
        echo "✓ City add-on installed successfully<br>\n";
    }
} else {
    echo "✓ City add-on already installed<br>\n";
}

// Test 5: City Add-on Activation
echo "<h2>Test 5: City Add-on Activation</h2>\n";
if (!$addon_manager->is_addon_active('city-targeting')) {
    echo "Activating city-targeting add-on...<br>\n";
    $result = $addon_manager->activate_addon('city-targeting');
    if (is_wp_error($result)) {
        echo "✗ Activation failed: " . $result->get_error_message() . "<br>\n";
    } else {
        echo "✓ City add-on activated successfully<br>\n";
    }
} else {
    echo "✓ City add-on already active<br>\n";
}

// Test 6: City Add-on Instance
echo "<h2>Test 6: City Add-on Instance</h2>\n";
if (class_exists('EGP_City_Targeting_Addon')) {
    $city_addon = new EGP_City_Targeting_Addon();
    echo "✓ City add-on class instantiated successfully<br>\n";
    echo "Add-on ID: " . $city_addon->get_addon_id() . "<br>\n";
    echo "Add-on Version: " . $city_addon->get_version() . "<br>\n";
    echo "Add-on Active: " . ($city_addon->is_active() ? 'Yes' : 'No') . "<br>\n";
} else {
    echo "✗ City add-on class not found<br>\n";
}

// Test 7: Add-on Settings
echo "<h2>Test 7: Add-on Settings</h2>\n";
if (isset($city_addon)) {
    $settings = $city_addon->get_settings();
    echo "Current settings:<br>\n";
    echo "<pre>" . print_r($settings, true) . "</pre>\n";
    
    // Test setting a value
    $city_addon->set_setting('test_setting', 'test_value');
    $test_value = $city_addon->get_setting('test_setting');
    echo "Test setting value: " . $test_value . "<br>\n";
}

// Test 8: Visitor Data
echo "<h2>Test 8: Visitor Data</h2>\n";
if (isset($city_addon)) {
    $visitor_data = $city_addon->get_visitor_data();
    echo "Visitor data:<br>\n";
    echo "<pre>" . print_r($visitor_data, true) . "</pre>\n";
}

echo "<h2>Test Complete!</h2>\n";
echo "<p>All tests completed. Check the results above for any issues.</p>\n";
?>