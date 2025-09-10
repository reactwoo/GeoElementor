<?php
/**
 * Create Add-On Zip File
 * 
 * Script to create distributable zip files for add-ons
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

// Check if ZipArchive is available
if (!class_exists('ZipArchive')) {
    die("Error: ZipArchive class not available. Please install the zip extension.\n");
}

// Set the correct paths for the dev-tools directory
$plugin_root = dirname(dirname(__FILE__));
$addons_dir = $plugin_root . '/addons/';

/**
 * Create zip file for an add-on
 */
function create_addon_zip($addon_id, $source_dir, $output_dir = null) {
    if ($output_dir === null) {
        $output_dir = dirname(__FILE__) . '/dist/';
    }
    
    // Create output directory if it doesn't exist
    if (!file_exists($output_dir)) {
        mkdir($output_dir, 0755, true);
    }
    
    global $addons_dir;
    $source_path = $addons_dir . $addon_id;
    $zip_file = $output_dir . $addon_id . '.zip';
    
    if (!file_exists($source_path)) {
        die("Error: Source directory not found: $source_path\n");
    }
    
    $zip = new ZipArchive();
    $result = $zip->open($zip_file, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    
    if ($result !== TRUE) {
        die("Error: Cannot create zip file: $zip_file\n");
    }
    
    // Add all files from the add-on directory
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source_path),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $file_path = $file->getRealPath();
            $relative_path = substr($file_path, strlen($source_path) + 1);
            
            // Skip hidden files and directories
            if (strpos($relative_path, '.') === 0) {
                continue;
            }
            
            $zip->addFile($file_path, $relative_path);
        }
    }
    
    $zip->close();
    
    echo "Created zip file: $zip_file\n";
    echo "Size: " . format_bytes(filesize($zip_file)) . "\n";
    
    return $zip_file;
}

/**
 * Format bytes to human readable format
 */
function format_bytes($size, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
        $size /= 1024;
    }
    
    return round($size, $precision) . ' ' . $units[$i];
}

/**
 * Validate add-on structure
 */
function validate_addon($addon_id) {
    global $addons_dir;
    $addon_path = $addons_dir . $addon_id;
    
    if (!file_exists($addon_path)) {
        return "Add-on directory not found: $addon_path";
    }
    
    // Check for required files
    $required_files = array(
        'addon-info.json',
        $addon_id . '.php'
    );
    
    foreach ($required_files as $file) {
        if (!file_exists($addon_path . '/' . $file)) {
            return "Required file missing: $file";
        }
    }
    
    // Validate addon-info.json
    $info_file = $addon_path . '/addon-info.json';
    $info_content = file_get_contents($info_file);
    $info_data = json_decode($info_content, true);
    
    if (!$info_data) {
        return "Invalid JSON in addon-info.json";
    }
    
    $required_fields = array('id', 'name', 'version', 'file', 'class');
    foreach ($required_fields as $field) {
        if (empty($info_data[$field])) {
            return "Missing required field in addon-info.json: $field";
        }
    }
    
    // Check if main PHP file exists
    $main_file = $addon_path . '/' . $info_data['file'];
    if (!file_exists($main_file)) {
        return "Main add-on file not found: " . $info_data['file'];
    }
    
    return true;
}

// Command line interface
if (php_sapi_name() === 'cli') {
    echo "Geo Elementor Add-On Zip Creator\n";
    echo "================================\n\n";
    
    if ($argc < 2) {
        echo "Usage: php create-addon-zip.php <addon-id> [output-dir]\n";
        echo "Example: php create-addon-zip.php city-targeting\n";
        echo "Example: php create-addon-zip.php city-targeting /path/to/output/\n\n";
        
        echo "Available add-ons:\n";
        global $addons_dir;
        if (is_dir($addons_dir)) {
            $addons = scandir($addons_dir);
            foreach ($addons as $addon) {
                if ($addon !== '.' && $addon !== '..' && is_dir($addons_dir . $addon)) {
                    echo "- $addon\n";
                }
            }
        }
        exit(1);
    }
    
    $addon_id = $argv[1];
    $output_dir = isset($argv[2]) ? $argv[2] : null;
    
    echo "Validating add-on: $addon_id\n";
    $validation = validate_addon($addon_id);
    
    if ($validation !== true) {
        echo "Validation failed: $validation\n";
        exit(1);
    }
    
    echo "Validation passed!\n\n";
    
    echo "Creating zip file...\n";
    $zip_file = create_addon_zip($addon_id, null, $output_dir);
    
    echo "\nAdd-on zip file created successfully!\n";
    echo "You can now distribute this file: $zip_file\n";
    
} else {
    // Web interface
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <title>Create Add-On Zip</title>
        <style>
            body { font-family: Arial, sans-serif; margin: 40px; }
            .container { max-width: 800px; }
            .addon-list { background: #f9f9f9; padding: 20px; border-radius: 4px; margin: 20px 0; }
            .addon-item { margin: 10px 0; padding: 10px; background: white; border-radius: 4px; }
            .btn { background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; display: inline-block; }
            .btn:hover { background: #005177; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>Create Add-On Zip Files</h1>
            
            <div class="addon-list">
                <h2>Available Add-Ons</h2>
                <?php
                global $addons_dir;
                if (is_dir($addons_dir)) {
                    $addons = scandir($addons_dir);
                    foreach ($addons as $addon) {
                        if ($addon !== '.' && $addon !== '..' && is_dir($addons_dir . $addon)) {
                            $validation = validate_addon($addon);
                            $status = $validation === true ? 'Valid' : 'Invalid';
                            $status_class = $validation === true ? 'color: green;' : 'color: red;';
                            
                            echo '<div class="addon-item">';
                            echo '<strong>' . esc_html($addon) . '</strong> ';
                            echo '<span style="' . $status_class . '">(' . $status . ')</span>';
                            
                            if ($validation === true) {
                                echo ' <a href="?create=' . urlencode($addon) . '" class="btn">Create Zip</a>';
                            } else {
                                echo '<br><small style="color: red;">' . esc_html($validation) . '</small>';
                            }
                            echo '</div>';
                        }
                    }
                } else {
                    echo '<p>No add-ons found.</p>';
                }
                ?>
            </div>
            
            <?php
            if (isset($_GET['create'])) {
                $addon_id = sanitize_text_field($_GET['create']);
                $validation = validate_addon($addon_id);
                
                if ($validation === true) {
                    $zip_file = create_addon_zip($addon_id);
                    echo '<div style="background: #d4edda; padding: 15px; border-radius: 4px; margin: 20px 0;">';
                    echo '<h3>Zip File Created Successfully!</h3>';
                    echo '<p><strong>File:</strong> ' . esc_html(basename($zip_file)) . '</p>';
                    echo '<p><strong>Size:</strong> ' . esc_html(format_bytes(filesize($zip_file))) . '</p>';
                    echo '<p><a href="' . esc_url(basename($zip_file)) . '" class="btn">Download Zip File</a></p>';
                    echo '</div>';
                } else {
                    echo '<div style="background: #f8d7da; padding: 15px; border-radius: 4px; margin: 20px 0;">';
                    echo '<h3>Validation Failed</h3>';
                    echo '<p>' . esc_html($validation) . '</p>';
                    echo '</div>';
                }
            }
            ?>
        </div>
    </body>
    </html>
    <?php
}
?>