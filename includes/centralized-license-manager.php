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
        
        // Check if token is expired or about to expire (within 5 minutes)
        $token_expired = $expires_at && $expires_at < (time() + 300);
        
        if ($token_expired) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[EGP License] Token expired or expiring soon, attempting refresh. Expires: " . date('Y-m-d H:i:s', $expires_at) . ", Current: " . date('Y-m-d H:i:s'));
            }
            
            // Try to refresh the token
            $refresh_token = get_option("{$prefix}_license_refresh_token", '');
            if ($refresh_token) {
                $refreshed_data = $this->refresh_access_token($plugin_slug, $refresh_token);
                if (!is_wp_error($refreshed_data)) {
                    $access_token = $refreshed_data['accessToken'] ?? $access_token;
                    $expires_at = $refreshed_data['expires_at'] ?? $expires_at;
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("[EGP License] Token refreshed successfully. New expires: " . date('Y-m-d H:i:s', $expires_at));
                    }
                } else {
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log("[EGP License] Token refresh failed: " . $refreshed_data->get_error_message());
                    }
                    
                    // If refresh fails, clear the expired tokens
                    delete_option("{$prefix}_license_access_token");
                    delete_option("{$prefix}_license_refresh_token");
                    delete_option("{$prefix}_license_expires_at");
                    
                    return array('valid' => false, 'error' => 'Token expired and refresh failed. Please reactivate your license.');
                }
            } else {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log("[EGP License] No refresh token available, clearing expired access token");
                }
                
                // No refresh token, clear the expired access token
                delete_option("{$prefix}_license_access_token");
                delete_option("{$prefix}_license_expires_at");
                
                return array('valid' => false, 'error' => 'Token expired and no refresh token available. Please reactivate your license.');
            }
        }
        
        // If we have a valid token, try to verify with server
        if ($access_token && (!$expires_at || $expires_at > time())) {
            // First try to get the stored license data
            $stored_data = get_option("{$prefix}_license_data", array());
            
            if (!empty($stored_data) && isset($stored_data['valid']) && $stored_data['valid']) {
                // Return the stored data if it's valid
                wp_cache_set($cache_key, $stored_data, $this->cache_group, 3600);
                return $stored_data;
            }
            
            // If no stored data or it's invalid, try server verification
            $verification_result = $this->verify_license_with_server($access_token, $expires_at);
            
            // Format the verification result for display
            if (isset($verification_result['valid']) && $verification_result['valid']) {
                $formatted_result = $this->format_license_data_for_display($verification_result);
                
                // Store the formatted data for future use
                update_option("{$prefix}_license_data", $formatted_result);
                
                // Cache the formatted result
                wp_cache_set($cache_key, $formatted_result, $this->cache_group, 3600);
                
                return $formatted_result;
            }
            
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
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[EGP License] Attempting to refresh access token for plugin: {$plugin_slug}");
        }
        
        $response = wp_remote_post($this->license_server . '/refresh', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array(
                'refreshToken' => $refresh_token,
                'pluginSlug' => $plugin_slug
            )),
            'timeout' => 20
        ));
        
        if (is_wp_error($response)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[EGP License] Refresh request failed: " . $response->get_error_message());
            }
            return $response;
        }
        
        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[EGP License] Refresh response status: {$status_code}");
            error_log("[EGP License] Refresh response body: " . substr($body, 0, 500));
        }
        
        if ($status_code !== 200) {
            return new WP_Error('refresh_failed', "Server returned status {$status_code}: {$body}");
        }
        
        $data = json_decode($body, true);
        
        if (!$data) {
            return new WP_Error('refresh_failed', 'Invalid JSON response from server');
        }
        
        if (!isset($data['success']) || !$data['success']) {
            $error_message = isset($data['error']) ? $data['error'] : 'Unknown refresh error';
            return new WP_Error('refresh_failed', $error_message);
        }
        
        // Store the new token data
        if (isset($data['accessToken'])) {
            $this->store_license_data($plugin_slug, $data);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[EGP License] Access token refreshed and stored successfully");
            }
        } else {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[EGP License] Warning: No accessToken in refresh response");
            }
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
        // Use plugin-specific option prefix to avoid clashes with other plugins
        $prefix = $this->get_plugin_prefix($plugin_slug);
        $access_token = get_option("{$prefix}_license_access_token", '');
        $expires_at = get_option("{$prefix}_license_expires_at", 0);
        
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
            $prefix = $this->get_plugin_prefix($current_plugin);
            $access_token = get_option("{$prefix}_license_access_token", '');
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
     * Store license data with plugin-specific prefixes to prevent conflicts
     */
    public function store_license_data($plugin_slug, $license_data) {
        // Store with plugin-specific prefixes to prevent conflicts
        $prefix = $this->get_plugin_prefix($plugin_slug);
        
        // Format the license data for display
        $formatted_data = $this->format_license_data_for_display($license_data);
        
        if (isset($license_data['accessToken'])) {
            update_option("{$prefix}_license_access_token", $license_data['accessToken']);
        }
        
        if (isset($license_data['refreshToken'])) {
            update_option("{$prefix}_license_refresh_token", $license_data['refreshToken']);
        }
        
        if (isset($license_data['expires_at'])) {
            update_option("{$prefix}_license_expires_at", $license_data['expires_at']);
        }
        
        // Store complete license data (formatted)
        update_option("{$prefix}_license_data", $formatted_data);
        
        // Clear any cached data
        wp_cache_delete("license_data_{$plugin_slug}", $this->cache_group);
    }
    
    /**
     * Clear expired tokens for a plugin
     */
    public function clear_expired_tokens($plugin_slug) {
        if (!$this->is_current_plugin($plugin_slug)) {
            return false;
        }
        
        $prefix = $this->get_plugin_prefix($plugin_slug);
        
        // Clear all token-related options
        delete_option("{$prefix}_license_access_token");
        delete_option("{$prefix}_license_refresh_token");
        delete_option("{$prefix}_license_expires_at");
        delete_option("{$prefix}_license_data");
        
        // Also clear the old format options for backward compatibility
        delete_option("{$plugin_slug}_license_access_token");
        delete_option("{$plugin_slug}_license_refresh_token");
        delete_option("{$plugin_slug}_license_expires_at");
        
        // Clear cache
        wp_cache_delete("license_data_{$plugin_slug}", $this->cache_group);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[EGP License] Cleared expired tokens for plugin: {$plugin_slug}");
        }
        
        return true;
    }
    
    /**
     * Check if tokens need to be refreshed (called before operations)
     */
    public function check_and_refresh_tokens($plugin_slug) {
        if (!$this->is_current_plugin($plugin_slug)) {
            return false;
        }
        
        $prefix = $this->get_plugin_prefix($plugin_slug);
        $expires_at = get_option("{$prefix}_license_expires_at", 0);
        
        // If token expires within 10 minutes, refresh it
        if ($expires_at && $expires_at < (time() + 600)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log("[EGP License] Token expiring soon, proactively refreshing for plugin: {$plugin_slug}");
            }
            
            $refresh_token = get_option("{$prefix}_license_refresh_token", '');
            if ($refresh_token) {
                $this->refresh_access_token($plugin_slug, $refresh_token);
            }
        }
        
        return true;
    }
    
    /**
     * Format license data for display
     */
    private function format_license_data_for_display($license_data) {
        $formatted = array(
            'valid' => isset($license_data['valid']) ? $license_data['valid'] : false,
            'success' => isset($license_data['success']) ? $license_data['success'] : false
        );
        
        // Extract product name from package data
        if (isset($license_data['package']['name'])) {
            $formatted['product_name'] = $license_data['package']['name'];
        } elseif (isset($license_data['packageType'])) {
            $formatted['product_name'] = 'Geo Elementor ' . ucfirst($license_data['packageType']);
        } else {
            $formatted['product_name'] = 'Geo Elementor';
        }
        
        // Determine if it's a free plan based on package slug
        $package_slug = $license_data['package']['slug'] ?? $license_data['packageType'] ?? '';
        $formatted['is_free_plan'] = ($package_slug === 'geo-free' || $package_slug === 'free');
        
        // Handle expiration
        if ($formatted['is_free_plan']) {
            $formatted['expires_at'] = 'never';
            $formatted['expires_text'] = 'Never Expires';
        } else {
            if (isset($license_data['expires_at'])) {
                $formatted['expires_at'] = $license_data['expires_at'];
            } elseif (isset($license_data['expires'])) {
                $formatted['expires_at'] = $license_data['expires'];
            } else {
                $formatted['expires_at'] = '';
            }
        }
        
        // Handle sites information
        if ($formatted['is_free_plan']) {
            $formatted['sites_text'] = '1 / Unlimited';
        } else {
            if (isset($license_data['sitesCount']) && isset($license_data['sitesLimit'])) {
                $formatted['sites_text'] = $license_data['sitesCount'] . ' / ' . $license_data['sitesLimit'];
            } else {
                $formatted['sites_text'] = '1 / Unlimited';
            }
        }
        
        // Extract version from license data or use default
        $formatted['version'] = $license_data['version'] ?? '1.0.0';
        
        // Add package type for reference
        $formatted['package_type'] = $package_slug;
        
        // Add license ID for reference
        if (isset($license_data['license']['id'])) {
            $formatted['license_id'] = $license_data['license']['id'];
        }
        
        return $formatted;
    }
    
    /**
     * Get plugin-specific prefix for options
     */
    private function get_plugin_prefix($plugin_slug) {
        switch ($plugin_slug) {
            case 'geo-elementor':
                return 'egp';
            case 'ali2woo':
                return 'ali2woo';
            default:
                return $plugin_slug;
        }
    }
}

// Initialize the centralized license manager
EGP_Centralized_License_Manager::get_instance();

