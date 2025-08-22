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
     * Check if operation is for current plugin
     */
    public function is_current_plugin($plugin_slug) {
        $current_plugin = $this->get_current_plugin_slug();
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[EGP License] Checking plugin isolation: requested={$plugin_slug}, current={$current_plugin}");
        }
        
        return $plugin_slug === $current_plugin;
    }
    
    /**
     * Get license data for a specific plugin (with isolation check)
     */
    public function get_license_data($plugin_slug, $access_token = null, $force_refresh = false) {
        // Only allow operations for the current plugin to prevent conflicts
        if (!$this->is_current_plugin($plugin_slug)) {
            return array('valid' => false, 'error' => 'Cross-plugin license access not allowed');
        }
        
        $cache_key = "license_data_{$plugin_slug}";
        
        // Try to get from cache first (unless force refresh)
        if (!$force_refresh) {
            $cached_data = wp_cache_get($cache_key, $this->cache_group);
            if ($cached_data !== false) {
                return $cached_data;
            }
        }
        
        // Get from WordPress options using plugin-specific prefixes
        $prefix = $this->get_plugin_prefix($plugin_slug);
        
        if (!$access_token) {
            $access_token = get_option("{$prefix}_license_access_token", '');
        }
        
        if (!$access_token) {
            return array('valid' => false, 'error' => 'No access token found');
        }
        
        $expires_at = get_option("{$prefix}_license_expires_at", 0);
        
        // Check if token is expired
        if ($expires_at && $expires_at < time()) {
            // Try to refresh the token
            $refresh_token = get_option("{$prefix}_license_refresh_token", '');
            if ($refresh_token) {
                $refreshed_data = $this->refresh_access_token($plugin_slug, $refresh_token);
                if (!is_wp_error($refreshed_data)) {
                    $access_token = $refreshed_data['access_token'] ?? $access_token;
                    $expires_at = $refreshed_data['expires_at'] ?? $expires_at;
                }
            }
        }
        
        // If we have a valid token, try to verify with server
        if ($access_token && (!$expires_at || $expires_at > time())) {
            $verification_result = $this->verify_license_with_server($access_token, $expires_at);
            
            // Cache the result
            wp_cache_set($cache_key, $verification_result, $this->cache_group, 3600);
            
            return $verification_result;
        }
        
        return array('valid' => false, 'error' => 'Access token expired or invalid');
    }
    
    /**
     * Refresh access token using refresh token
     */
    private function refresh_access_token($plugin_slug, $refresh_token) {
        $response = wp_remote_post($this->license_server . '/refresh', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array(
                'refreshToken' => $refresh_token,
                'pluginSlug' => $plugin_slug
            )),
            'timeout' => 20
        ));
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data || !isset($data['success']) || !$data['success']) {
            return new WP_Error('refresh_failed', 'Failed to refresh access token');
        }
        
        // Store the new token data
        if (isset($data['accessToken'])) {
            $this->store_license_data($plugin_slug, $data);
        }
        
        return $data;
    }
    
    /**
     * Verify license with server
     */
    private function verify_license_with_server($access_token, $expires_at) {
        // First try to verify with server
        $response = wp_remote_get($this->license_server . '/verify', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token),
            'timeout' => 20
        ));
        
        if (is_wp_error($response)) {
            // If verification fails but we have a valid token, return valid
            if ($access_token && (!$expires_at || $expires_at > time())) {
                return array('valid' => true, 'success' => true, 'message' => 'License is valid (local verification)');
            }
            return array('valid' => false, 'error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            // If server response is invalid but we have a valid token, return valid
            if ($access_token && (!$expires_at || $expires_at > time())) {
                return array('valid' => true, 'success' => true, 'message' => 'License is valid (local verification)');
            }
            return array('valid' => false, 'error' => 'Invalid response');
        }
        
        return $data;
    }
    
    /**
     * Fetch license data from server
     */
    private function fetch_license_from_server($plugin_slug, $license_key = null) {
        $access_token = get_option("{$plugin_slug}_license_access_token", '');
        $expires_at = get_option("{$plugin_slug}_license_expires_at", 0);
        
        if (!$access_token) {
            return array('valid' => false, 'error' => 'No access token');
        }
        
        // Check if token is expired
        if ($expires_at && $expires_at < time()) {
            return array('valid' => false, 'error' => 'Access token expired');
        }
        
        // If we have a valid access token and it's not expired, consider the license valid
        if ($access_token && (!$expires_at || $expires_at > time())) {
            return array('valid' => true, 'success' => true, 'message' => 'License is valid');
        }
        
        // Try to verify with server if endpoint exists (fallback)
        $response = wp_remote_get($this->license_server . '/verify', array(
            'headers' => array('Authorization' => 'Bearer ' . $access_token),
            'timeout' => 20
        ));
        
        if (is_wp_error($response)) {
            // If verification fails but we have a valid token, return valid
            if ($access_token && (!$expires_at || $expires_at > time())) {
                return array('valid' => true, 'success' => true, 'message' => 'License is valid (local verification)');
            }
            return array('valid' => false, 'error' => $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!$data) {
            // If server response is invalid but we have a valid token, return valid
            if ($access_token && (!$expires_at || $expires_at > time())) {
                return array('valid' => true, 'success' => true, 'message' => 'License is valid (local verification)');
            }
            return array('valid' => false, 'error' => 'Invalid response');
        }
        
        return $data;
    }
    
    /**
     * Activate license with deduplication
     */
    public function activate_license($plugin_slug, $license_key, $domain, $plugin_version, $product_type) {
        // Only allow activation for the current plugin to prevent conflicts
        if (!$this->is_current_plugin($plugin_slug)) {
            return new WP_Error('cross_plugin_activation', 'Cross-plugin license activation not allowed');
        }
        
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
            
            // Store license data with plugin-specific isolation
            if (isset($data['success']) && $data['success']) {
                $this->store_license_data($plugin_slug, $data);
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
        // Only check licenses for the current plugin to avoid conflicts
        // Each plugin should manage its own license independently
        $current_plugin = $this->get_current_plugin_slug();
        
        if ($current_plugin) {
            $access_token = get_option("{$current_plugin}_license_access_token", '');
            if ($access_token) {
                // Only refresh license data for current plugin
                $this->get_license_data($current_plugin, null, true);
            }
        }
    }
    
    /**
     * Get the current plugin slug based on context
     */
    private function get_current_plugin_slug() {
        // Try to determine which plugin is calling this
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);
        
        foreach ($backtrace as $trace) {
            if (isset($trace['file'])) {
                if (strpos($trace['file'], 'geo-elementor') !== false) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[EGP License] Detected geo-elementor plugin from: ' . $trace['file']);
                    }
                    return 'geo-elementor';
                } elseif (strpos($trace['file'], 'ali2woo') !== false) {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[EGP License] Detected ali2woo plugin from: ' . $trace['file']);
                    }
                    return 'ali2woo';
                }
            }
        }
        
        // Default to geo-elementor if we can't determine
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EGP License] Could not determine plugin, defaulting to geo-elementor');
        }
        return 'geo-elementor';
    }
    
    /**
     * Cleanup expired cache entries
     */
    public function cleanup_expired_cache() {
        // WordPress handles this automatically, but we can add custom cleanup if needed
    }

    /**
     * Store license data with plugin-specific isolation
     */
    public function store_license_data($plugin_slug, $license_data) {
        // Store with plugin-specific prefixes to prevent conflicts
        $prefix = $this->get_plugin_prefix($plugin_slug);
        
        if (isset($license_data['accessToken'])) {
            update_option("{$prefix}_license_access_token", $license_data['accessToken']);
        }
        
        if (isset($license_data['refreshToken'])) {
            update_option("{$prefix}_license_refresh_token", $license_data['refreshToken']);
        }
        
        if (isset($license_data['expires_at'])) {
            update_option("{$prefix}_license_expires_at", $license_data['expires_at']);
        }
        
        // Store complete license data
        update_option("{$prefix}_license_data", $license_data);
        
        // Clear any cached data
        wp_cache_delete("license_data_{$plugin_slug}", $this->cache_group);
    }
    
    /**
     * Get plugin-specific prefix for options
     */
    private function get_plugin_prefix($plugin_slug) {
        switch ($plugin_slug) {
            case 'geo-elementor':
                return 'egp_license';
            case 'ali2woo':
                return 'ali2woo_license';
            default:
                return $plugin_slug . '_license';
        }
    }
}

// Initialize the centralized license manager
EGP_Centralized_License_Manager::get_instance();

