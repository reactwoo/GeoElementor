<?php
/**
 * Ali2Woo Integration Example with Centralized License Manager
 * 
 * This file shows how Ali2Woo plugin can integrate with the centralized license manager
 * to prevent conflicts and reduce network requests.
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Example Ali2Woo Licensing Integration
 * 
 * This class demonstrates how Ali2Woo plugin can use the centralized license manager
 * instead of making direct calls to the license server.
 */
class Ali2Woo_License_Integration_Example {
    
    private $license_manager;
    private $plugin_slug = 'ali2woo';
    
    public function __construct() {
        // Check if centralized license manager is available
        if (class_exists('EGP_Centralized_License_Manager')) {
            $this->license_manager = EGP_Centralized_License_Manager::get_instance();
            $this->init_integration();
        } else {
            // Fallback to direct license calls if centralized manager not available
            $this->init_fallback();
        }
    }
    
    /**
     * Initialize integration with centralized manager
     */
    private function init_integration() {
        // Replace Ali2Woo's existing license check with centralized version
        add_action('admin_init', array($this, 'check_license_centralized'));
        
        // Replace Ali2Woo's license activation with centralized version
        add_action('wp_ajax_a2w_activate_license', array($this, 'activate_license_centralized'));
        
        // Use centralized cron instead of Ali2Woo's own
        if (wp_next_scheduled('a2w_license_check')) {
            wp_clear_scheduled_hook('a2w_license_check');
        }
    }
    
    /**
     * Check license using centralized manager
     */
    public function check_license_centralized() {
        // This will use caching and deduplication automatically
        $license_data = $this->license_manager->get_license_data($this->plugin_slug);
        
        if (isset($license_data['valid']) && $license_data['valid']) {
            update_option('a2w_license_status', 'valid');
            update_option('a2w_license_data', $license_data);
        } else {
            update_option('a2w_license_status', 'invalid');
            update_option('a2w_license_data', array());
        }
    }
    
    /**
     * Activate license using centralized manager
     */
    public function activate_license_centralized() {
        check_ajax_referer('a2w_license_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Insufficient permissions');
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error('License key is required');
        }
        
        $domain = wp_parse_url(get_site_url(), PHP_URL_HOST);
        $plugin_version = defined('A2W_VERSION') ? A2W_VERSION : '1.0.0';
        
        $result = $this->license_manager->activate_license(
            $this->plugin_slug,
            $license_key,
            $domain,
            $plugin_version,
            'aliexpress'
        );
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Store tokens
        update_option('a2w_license_key', $license_key);
        update_option('a2w_license_access_token', $result['accessToken']);
        update_option('a2w_license_refresh_token', $result['refreshToken']);
        update_option('a2w_license_expires_at', $result['expires_at']);
        update_option('a2w_license_status', 'valid');
        
        wp_send_json_success('License activated successfully');
    }
    
    /**
     * Fallback to direct license calls if centralized manager not available
     */
    private function init_fallback() {
        // Ali2Woo's original license logic would go here
        add_action('admin_init', array($this, 'check_license_direct'));
        add_action('wp_ajax_a2w_activate_license', array($this, 'activate_license_direct'));
    }
    
    /**
     * Direct license check (fallback)
     */
    public function check_license_direct() {
        // Original Ali2Woo license check logic
        // This would make direct calls to license.reactwoo.com
    }
    
    /**
     * Direct license activation (fallback)
     */
    public function activate_license_direct() {
        // Original Ali2Woo license activation logic
        // This would make direct calls to license.reactwoo.com
    }
}

/**
 * Integration Hook for Ali2Woo Plugin
 * 
 * Ali2Woo plugin can use this hook to integrate with the centralized license manager
 */
function ali2woo_integrate_with_centralized_license_manager() {
    // Only initialize if Ali2Woo is active
    if (class_exists('A2W_Plugin')) {
        new Ali2Woo_License_Integration_Example();
    }
}

// Hook into WordPress init to ensure Ali2Woo is loaded
add_action('init', 'ali2woo_integrate_with_centralized_license_manager', 20);

/**
 * Alternative: Direct Integration Method
 * 
 * Ali2Woo plugin can also directly call the centralized manager methods
 * without using this integration class.
 */
function ali2woo_direct_integration_example() {
    if (!class_exists('EGP_Centralized_License_Manager')) {
        return;
    }
    
    $license_manager = EGP_Centralized_License_Manager::get_instance();
    
    // Example: Check Ali2Woo license status
    $ali2woo_license = $license_manager->get_license_data('ali2woo');
    
    // Example: Activate Ali2Woo license
    if (isset($_POST['a2w_license_key'])) {
        $result = $license_manager->activate_license(
            'ali2woo',
            $_POST['a2w_license_key'],
            wp_parse_url(get_site_url(), PHP_URL_HOST),
            '3.5.6', // Ali2Woo version
            'aliexpress'
        );
    }
}

// This function can be called from Ali2Woo's admin pages
add_action('admin_init', 'ali2woo_direct_integration_example');

