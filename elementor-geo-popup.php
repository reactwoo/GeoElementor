<?php
/**
 * Plugin Name: Elementor Geo Popup
 * Plugin URI: https://reactwoo.com
 * Description: Trigger Elementor Pro Popups based on visitor IP/country using MaxMind GeoLite2. Deep integration with Elementor for geo-targeting popups.
 * Version: 1.0.0
 * Author: ReactWoo
 * Author URI: https://reactwoo.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: elementor-geo-popup
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Elementor requires at least: 3.0.0
 * Elementor tested up to: 3.18.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('EGP_VERSION', '1.0.0');
define('EGP_PLUGIN_FILE', __FILE__);
define('EGP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('EGP_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Autoloader for Composer dependencies
if (file_exists(EGP_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once EGP_PLUGIN_DIR . 'vendor/autoload.php';
}

/**
 * Main Plugin Class
 */
class ElementorGeoPopup {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Get single instance of this class
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('plugins_loaded', array($this, 'init'));
        register_activation_hook(EGP_PLUGIN_FILE, array($this, 'activate'));
        register_deactivation_hook(EGP_PLUGIN_FILE, array($this, 'deactivate'));
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Always load admin settings and licensing so settings are visible even if Elementor isn't active
        if (is_admin()) {
            require_once EGP_PLUGIN_DIR . 'admin/settings-page.php';
            require_once EGP_PLUGIN_DIR . 'includes/licensing.php';
            // Initialize admin-only components early
            new EGP_Admin_Settings();
            new EGP_Licensing();
        }

        // Check if Elementor is active
        if (!did_action('elementor/loaded')) {
            add_action('admin_notices', array($this, 'elementor_missing_notice'));
            return;
        }
        
        // Check if Elementor Pro is active
        if (!class_exists('ElementorPro\Plugin')) {
            add_action('admin_notices', array($this, 'elementor_pro_missing_notice'));
            return;
        }
        
        // Load plugin components
        $this->load_dependencies();
        $this->init_components();
    }
    
    /**
     * Load plugin dependencies
     */
    private function load_dependencies() {
        // Admin functionality
        if (is_admin()) {
            require_once EGP_PLUGIN_DIR . 'admin/settings-page.php';
            require_once EGP_PLUGIN_DIR . 'admin/popup-editor.php';
        }
        
        // Core functionality
        require_once EGP_PLUGIN_DIR . 'includes/geo-detect.php';
        require_once EGP_PLUGIN_DIR . 'includes/popup-hooks.php';
        require_once EGP_PLUGIN_DIR . 'includes/licensing.php';
        require_once EGP_PLUGIN_DIR . 'includes/widget-registration.php';
        require_once EGP_PLUGIN_DIR . 'includes/global-settings.php';
    }
    
    /**
     * Initialize plugin components
     */
    private function init_components() {
        // Initialize admin components (Editor integration only; settings and licensing were initialized earlier)
        if (is_admin()) {
            new EGP_Popup_Editor();
        }
        
        // Initialize core components
        new EGP_Geo_Detect();
        new EGP_Popup_Hooks();
        new EGP_Widget_Registration();
        new EGP_Global_Settings();
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        // Create necessary directories
        $this->create_directories();
        
        // Create database tables if needed
        $this->create_tables();
        
        // Set default options
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clear scheduled events
        wp_clear_scheduled_hook('egp_update_geo_database');
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create necessary directories
     */
    private function create_directories() {
        $upload_dir = wp_upload_dir();
        $geo_dir = $upload_dir['basedir'] . '/geo-popup-db';
        
        if (!file_exists($geo_dir)) {
            wp_mkdir_p($geo_dir);
        }
        
        // Create .htaccess to protect database files
        $htaccess_file = $geo_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        
        $sql = "CREATE TABLE $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            popup_id bigint(20) NOT NULL,
            countries longtext NOT NULL,
            enabled tinyint(1) DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY popup_id (popup_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'maxmind_license_key' => '',
            'database_path' => '',
            'last_update' => '',
            'auto_update' => false,
            'debug_mode' => false,
            'default_popup_id' => '',
            'fallback_behavior' => 'show_to_all'
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option('egp_' . $key) === false) {
                add_option('egp_' . $key, $value);
            }
        }
    }
    
    /**
     * Elementor missing notice
     */
    public function elementor_missing_notice() {
        $message = sprintf(
            __('Elementor Geo Popup requires %1$sElementor%2$s plugin to be installed and activated.', 'elementor-geo-popup'),
            '<a href="' . esc_url(admin_url('plugin-install.php?s=Elementor&tab=search&type=term')) . '">',
            '</a>'
        );
        
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }
    
    /**
     * Elementor Pro missing notice
     */
    public function elementor_pro_missing_notice() {
        $message = sprintf(
            __('Elementor Geo Popup requires %1$sElementor Pro%2$s plugin to be installed and activated.', 'elementor-geo-popup'),
            '<a href="' . esc_url(admin_url('plugin-install.php?s=Elementor+Pro&tab=search&type=term')) . '">',
            '</a>'
        );
        
        printf('<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', $message);
    }
}

// Initialize the plugin
ElementorGeoPopup::get_instance();

