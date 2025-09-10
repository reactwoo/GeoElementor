<?php
/**
 * Development Environment Setup
 * 
 * Sets up the development environment for creating Geo Elementor add-ons
 * 
 * @package ElementorGeoPopup
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    // If not in WordPress context, define basic constants
    if (!defined('ABSPATH')) {
        define('ABSPATH', dirname(__FILE__) . '/');
    }
}

$plugin_root = dirname(__FILE__);
$addons_dir = $plugin_root . '/../addons/';

echo "Geo Elementor Development Environment Setup\n";
echo "==========================================\n\n";

// Check if we're in the right directory
if (!file_exists($addons_dir)) {
    echo "Error: Add-ons directory not found. Please run this from the dev-tools directory.\n";
    exit(1);
}

echo "✓ Add-ons directory found: $addons_dir\n";

// Check for required tools
$required_tools = array(
    'ZipArchive' => class_exists('ZipArchive'),
    'JSON' => function_exists('json_encode'),
    'File Operations' => function_exists('file_get_contents')
);

echo "\nChecking required tools:\n";
foreach ($required_tools as $tool => $available) {
    echo ($available ? "✓" : "✗") . " $tool\n";
}

if (!class_exists('ZipArchive')) {
    echo "\nError: ZipArchive is required but not available.\n";
    echo "Please install the zip extension for PHP.\n";
    exit(1);
}

// List available add-ons
echo "\nAvailable add-ons:\n";
if (is_dir($addons_dir)) {
    $addons = scandir($addons_dir);
    $valid_addons = 0;
    
    foreach ($addons as $addon) {
        if ($addon !== '.' && $addon !== '..' && is_dir($addons_dir . $addon)) {
            $info_file = $addons_dir . $addon . '/addon-info.json';
            $main_file = $addons_dir . $addon . '/' . $addon . '.php';
            
            $has_info = file_exists($info_file);
            $has_main = file_exists($main_file);
            
            $status = ($has_info && $has_main) ? '✓' : '✗';
            echo "$status $addon";
            
            if ($has_info && $has_main) {
                $valid_addons++;
            } else {
                echo " (missing: " . (!$has_info ? "info.json" : "main file") . ")";
            }
            echo "\n";
        }
    }
    
    echo "\nValid add-ons: $valid_addons\n";
} else {
    echo "No add-ons found.\n";
}

// Create output directory
$output_dir = $plugin_root . '/dist/';
if (!file_exists($output_dir)) {
    if (mkdir($output_dir, 0755, true)) {
        echo "\n✓ Created output directory: $output_dir\n";
    } else {
        echo "\n✗ Failed to create output directory: $output_dir\n";
    }
} else {
    echo "\n✓ Output directory exists: $output_dir\n";
}

// Test zip creation
echo "\nTesting zip creation...\n";
if (is_dir($addons_dir)) {
    $addons = scandir($addons_dir);
    foreach ($addons as $addon) {
        if ($addon !== '.' && $addon !== '..' && is_dir($addons_dir . $addon)) {
            $info_file = $addons_dir . $addon . '/addon-info.json';
            $main_file = $addons_dir . $addon . '/' . $addon . '.php';
            
            if (file_exists($info_file) && file_exists($main_file)) {
                echo "Creating zip for: $addon\n";
                
                // Include the create-addon-zip.php functions
                require_once $plugin_root . '/create-addon-zip.php';
                
                $zip_file = create_addon_zip($addon, null, $output_dir);
                if ($zip_file && file_exists($zip_file)) {
                    echo "✓ Created: " . basename($zip_file) . "\n";
                } else {
                    echo "✗ Failed to create zip for $addon\n";
                }
                break; // Only test one add-on
            }
        }
    }
}

echo "\nDevelopment environment setup complete!\n";
echo "\nNext steps:\n";
echo "1. Develop add-ons in the /addons/ directory\n";
echo "2. Use create-addon-zip.php to create distributable zips\n";
echo "3. Distribute zip files to customers\n";
echo "\nTools available:\n";
echo "- CLI: php create-addon-zip.php <addon-name>\n";
echo "- Web: http://yoursite.com/wp-content/plugins/geo-elementor/dev-tools/create-addon-zip.php\n";
?>