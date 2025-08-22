<?php
/**
 * Centralized License Manager
 * 
 * Prevents conflicts between multiple plugins making license calls to the same server
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Centralized License Manager to prevent conflicts between multiple plugins
 */
class EGP_Centralized_License_Manager {
    
    private static $instance = null;
    private $license_server = 'https://license.reactwoo.com';
    private $cache_group = 'egp_license_cache';
    private $request_locks = array();
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Initialize shared hooks
        add_action('init', array($this, 'init_shared_license_system'));
    }
    
    /**
     * Initialize shared license system
     */
    public function init_shared_license_system() {
        // Register shared license check cron (only once across all plugins)
        if (!wp_next_scheduled('shared_license_check')) {
            wp_schedule_event(time(), 'hourly', 'shared_license_check');
        }
        add_action('shared_license_check', array($this, 'perform_shared_license_check'));
        
        // Clear expired cache entries
        add_action('wp_scheduled_delete', array($this, 'cleanup_expired_cache'));
    }
    
    /**
     * Get license data with caching and deduplication
     */
    public function get_license_data($plugin_slug, $license_key = null, $force_refresh = false) {
        $cache_key = "license_data_{$plugin_slug}";
        
        // Check if we have a recent cache entry
        if (!$force_refresh) {
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached && $cached['expires'] > time()) {
                return $cached['data'];
            }
        }
        
        // Check if another request is already fetching this license
        $lock_key = "license_lock_{$plugin_slug}";
        if (isset($this->request_locks[$lock_key]) && $this->request_locks[$lock_key] > time() - 30) {
            // Wait for existing request to complete
            $wait_time = 0;
            while (isset($this->request_locks[$lock_key]) && $this->request_locks[$lock_key] > time() - 30 && $wait_time < 10) {
                sleep(1);
                $wait_time++;
            }
            
            // Check cache again after waiting
            $cached = wp_cache_get($cache_key, $this->cache_group);
            if ($cached && $cached['expires'] > time()) {
                return $cached['data'];
            }
        }
        
        // Set request lock
        $this->request_locks[$lock_key] = time();
        
        try {
            $license_data = $this->fetch_license_from_server($plugin_slug, $license_key);
            
            // Cache the result for 15 minutes
            wp_cache_set($cache_key, array(
                'data' => $license_data,
                'expires' => time() + 900 // 15 minutes
            ), $this->cache_group, 900);
            
            return $license_data;
        } finally {
            // Clear request lock
            unset($this->request_locks[$lock_key]);
        }
    }
    
    /**
     * Fetch license data from server
     */
    private function fetch_license_from_server($plugin_slug, $license_key = null) {
        $access_token = get_option("{$plugin_slug}_license_access_token", '');
        
        if (!$access_token) {
            return array('valid' => false, 'error' => 'No access token');
        }
        
        $response = wp_remote_get($this->license_server . '/verify', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token),
            'timeout' => 20
        ));
        
        if (is_wp_error($response)) {
            return array('valid' => false, 'error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            return array('valid' => false, 'error' => 'Invalid response');
        }
        
        return $data;
    }
    
    /**
     * Activate license with deduplication
     */
    public function activate_license($plugin_slug, $license_key, $domain, $plugin_version, $product_type) {
        $lock_key = "activation_lock_{$plugin_slug}_{$domain}";
        
        // Check if activation is already in progress
        if (isset($this->request_locks[$lock_key]) && $this->request_locks[$lock_key] > time() - 60) {
            return new WP_Error('activation_in_progress', 'License activation already in progress for this domain');
        }
        
        $this->request_locks[$lock_key] = time();
        
        try {
            $response = wp_remote_post($this->license_server . '/activate', array(
                'headers' => array('Content-Type' => 'application/json'),
                'body' => wp_json_encode(array(
                    'licenseKey' => $license_key,
                    'domain' => $domain,
                    'pluginVersion' => $plugin_version,
                    'productType' => $product_type
                )),
                'timeout' => 30
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            if (!$data) {
                return new WP_Error('invalid_response', 'Invalid response from license server');
            }
            
            // Clear any cached license data for this plugin
            wp_cache_delete("license_data_{$plugin_slug}", $this->cache_group);
            
            return $data;
        } finally {
            unset($this->request_locks[$lock_key]);
        }
    }
    
    /**
     * Perform shared license check (called by cron)
     */
    public function perform_shared_license_check() {
        // Get all plugins that might have licenses
        $plugins = array('geo-elementor', 'ali2woo', 'other-plugin');
        
        foreach ($plugins as $plugin_slug) {
            $access_token = get_option("{$plugin_slug}_license_access_token", '');
            if ($access_token) {
                // Force refresh of license data
                $this->get_license_data($plugin_slug, null, true);
            }
        }
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        // WordPress handles this automatically, but we can add custom cleanup if needed
    }
}

// Initialize the centralized license manager
EGP_Centralized_License_Manager::get_instance();

