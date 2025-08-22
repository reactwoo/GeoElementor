<?php
/**
 * Licensing System Integration
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
    public $license_server = 'https://license.reactwoo.com';
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
            
            // If server returned error, encapsulate it as WP_Error so UI can show it
            if (isset($data['error']) && $data['error']) {
                $reason = isset($data['reason']) ? trim($data['reason']) : '';
                $message = $data['error'] . ($reason !== '' ? (': ' . $reason) : '');
                return new WP_Error('activation_failed', $message);
            }
            
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

/**
 * Licensing System Class for Geo Elementor Plugin
 */
class EGP_Licensing {
    
    private $license_manager;
    private $plugin_slug = 'geo-elementor';
    
    /**
     * Constructor
     */
    public function __construct() {
        // Try to initialize centralized license manager, fallback to basic if it fails
        try {
            $this->license_manager = EGP_Centralized_License_Manager::get_instance();
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: Centralized manager failed, using fallback: ' . $e->getMessage());
            }
            $this->license_manager = null;
        }
        
        add_action('admin_init', array($this, 'init_licensing'));
        add_action('admin_notices', array($this, 'license_notices'));
        add_action('wp_ajax_egp_activate_license', array($this, 'ajax_activate_license'));
        add_action('wp_ajax_egp_deactivate_license', array($this, 'ajax_deactivate_license'));
        add_action('wp_ajax_egp_check_license', array($this, 'ajax_check_license'));
        
        // Schedule license checks (now handled centrally)
        if (!wp_next_scheduled('egp_daily_license_check')) {
            wp_schedule_event(time(), 'daily', 'egp_daily_license_check');
        }
        add_action('egp_daily_license_check', array($this, 'check_license_status'));
        
        // Allow rendering the license page directly without redirects
        add_action('egp_render_license_page', array($this, 'render_license_page'));
    }
    
    /**
     * Initialize licensing
     */
    public function init_licensing() {
        // Check license status on admin pages
        if (is_admin()) {
            $this->check_license_status();
        }
    }
    
    /**
     * Render license page
     */
    public function render_license_page() {
        // Check if user has access to either the Settings page or our custom menu
        $allowed_caps = array('manage_options', 'manage_woocommerce');
        $allowed_caps = apply_filters('egp_allowed_capabilities', $allowed_caps);
        
        $user_has_access = false;
        foreach ($allowed_caps as $cap) {
            if (current_user_can($cap)) {
                $user_has_access = true;
                break;
            }
        }
        
        if (!$user_has_access) {
            // Allow custom capability override as last resort
            $custom_cap = apply_filters('egp_license_capability', 'manage_options');
            if (!current_user_can($custom_cap)) {
                wp_die(__('You do not have sufficient permissions to access this page.', 'elementor-geo-popup'));
            }
        }

        // Determine which capability to use for AJAX actions
        $ajax_cap = current_user_can('manage_options') ? 'manage_options' : 'manage_woocommerce';
        if (has_filter('egp_license_capability')) {
            $ajax_cap = apply_filters('egp_license_capability', 'manage_options');
        }

        // Store the capability for AJAX checks
        if (!defined('EGP_LICENSE_CAPABILITY')) {
            define('EGP_LICENSE_CAPABILITY', $ajax_cap);
        }
        
        // Debug: Log the capability being used
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License Page: User capability check - Required: ' . $ajax_cap . ', User can: ' . (current_user_can($ajax_cap) ? 'YES' : 'NO'));
        }
        
        $license_key = get_option('egp_license_key', '');
        $license_status = get_option('egp_license_status', '');
        $license_data = get_option('egp_license_data', array());
        
        ?>
        <div class="wrap">
            <h1><?php _e('Elementor Geo Popup License', 'elementor-geo-popup'); ?></h1>
            
            <?php if (isset($_GET['license_updated'])) : ?>
                <div class="notice notice-success is-dismissible">
                    <p><?php _e('License settings updated successfully!', 'elementor-geo-popup'); ?></p>
                </div>
            <?php endif; ?>
            
            <div class="egp-license-container">
                <div class="egp-license-info">
                    <h2><?php _e('License Information', 'elementor-geo-popup'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('License Key', 'elementor-geo-popup'); ?></th>
                            <td>
                                <input type="text" id="egp-license-key" class="regular-text" 
                                       value="<?php echo esc_attr($license_key); ?>" 
                                       placeholder="<?php _e('Enter your license key', 'elementor-geo-popup'); ?>" />
                                <p class="description">
                                    <?php _e('Enter your license key from reactwoo.com', 'elementor-geo-popup'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('License Status', 'elementor-geo-popup'); ?></th>
                            <td>
                                <span class="egp-license-status egp-status-<?php echo esc_attr($license_status); ?>">
                                    <?php echo $this->get_license_status_text($license_status); ?>
                                </span>
                            </td>
                        </tr>
                        <?php if (!empty($license_data)) : ?>
                        <tr>
                            <th scope="row"><?php _e('Product', 'elementor-geo-popup'); ?></th>
                            <td>
                                <?php 
                                $product_name = $license_data['product_name'] ?? '';
                                $is_free_plan = $license_data['is_free_plan'] ?? false;
                                
                                if ($product_name) {
                                    if ($is_free_plan) {
                                        echo esc_html($product_name) . ' <span style="color: #0073aa;">(Free)</span>';
                                        echo '<br><small><a href="https://reactwoo.com/geo-elementor" target="_blank" style="color: #0073aa; text-decoration: none;">';
                                        echo __('Upgrade to Pro for advanced features', 'elementor-geo-popup');
                                        echo '</a></small>';
                                    } else {
                                        echo esc_html($product_name);
                                    }
                                } else {
                                    echo esc_html(__('Not specified', 'elementor-geo-popup'));
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Expires', 'elementor-geo-popup'); ?></th>
                            <td>
                                <?php 
                                $expires_text = $license_data['expires_text'] ?? '';
                                if ($expires_text) {
                                    echo esc_html($expires_text);
                                } else {
                                    echo esc_html(__('Not specified', 'elementor-geo-popup'));
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Sites', 'elementor-geo-popup'); ?></th>
                            <td>
                                <?php 
                                $sites_text = $license_data['sites_text'] ?? '';
                                if ($sites_text) {
                                    echo esc_html($sites_text);
                                } else {
                                    echo esc_html(__('Not specified', 'elementor-geo-popup'));
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Version', 'elementor-geo-popup'); ?></th>
                            <td><?php echo esc_html($license_data['version'] ?? '1.0.0'); ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
                
                <div class="egp-license-actions">
                    <h2><?php _e('License Actions', 'elementor-geo-popup'); ?></h2>
                    
                    <div class="egp-action-buttons">
                        <button type="button" id="egp-activate-license" class="button button-primary">
                            <?php _e('Activate License', 'elementor-geo-popup'); ?>
                        </button>
                        
                        <button type="button" id="egp-deactivate-license" class="button button-secondary">
                            <?php _e('Deactivate License', 'elementor-geo-popup'); ?>
                        </button>
                        
                        <button type="button" id="egp-check-license" class="button button-secondary">
                            <?php _e('Check License', 'elementor-geo-popup'); ?>
                        </button>
                    </div>
                    
                    <div id="egp-license-message"></div>
                </div>
                
                <div class="egp-license-help">
                    <h2><?php _e('Need Help?', 'elementor-geo-popup'); ?></h2>
                    <p>
                        <?php _e('If you need help with your license or have questions, please contact us at', 'elementor-geo-popup'); ?>
                        <a href="mailto:support@reactwoo.com">support@reactwoo.com</a>
                    </p>
                    <p>
                        <?php _e('You can also visit our website at', 'elementor-geo-popup'); ?>
                        <a href="https://reactwoo.com" target="_blank">reactwoo.com</a>
                    </p>
                </div>
            </div>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Activate license
            $('#egp-activate-license').on('click', function() {
                var licenseKey = $('#egp-license-key').val();
                if (!licenseKey) {
                    alert('<?php _e('Please enter a license key', 'elementor-geo-popup'); ?>');
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Activating...', 'elementor-geo-popup'); ?>');
                
                $.post(ajaxurl, {
                    action: 'egp_activate_license',
                    license_key: licenseKey,
                    nonce: '<?php echo wp_create_nonce('egp_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $('#egp-license-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    $('#egp-activate-license').prop('disabled', false).text('<?php _e('Activate License', 'elementor-geo-popup'); ?>');
                });
            });
            
            // Deactivate license
            $('#egp-deactivate-license').on('click', function() {
                if (!confirm('<?php _e('Are you sure you want to deactivate your license?', 'elementor-geo-popup'); ?>')) {
                    return;
                }
                
                $(this).prop('disabled', true).text('<?php _e('Deactivating...', 'elementor-geo-popup'); ?>');
                
                $.post(ajaxurl, {
                    action: 'egp_deactivate_license',
                    nonce: '<?php echo wp_create_nonce('egp_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $('#egp-license-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    $('#egp-deactivate-license').prop('disabled', false).text('<?php _e('Deactivate License', 'elementor-geo-popup'); ?>');
                });
            });
            
            // Check license
            $('#egp-check-license').on('click', function() {
                $(this).prop('disabled', true).text('<?php _e('Checking...', 'elementor-geo-popup'); ?>');
                
                $.post(ajaxurl, {
                    action: 'egp_check_license',
                    nonce: '<?php echo wp_create_nonce('egp_license_nonce'); ?>'
                }, function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        $('#egp-license-message').html('<div class="notice notice-error"><p>' + response.data + '</p></div>');
                    }
                    $('#egp-check-license').prop('disabled', false).text('<?php _e('Check License', 'elementor-geo-popup'); ?>');
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * Get license status text
     */
    private function get_license_status_text($status) {
        switch ($status) {
            case 'valid':
                return '<span style="color: green;">✓ ' . __('Valid', 'elementor-geo-popup') . '</span>';
            case 'invalid':
                return '<span style="color: red;">✗ ' . __('Invalid', 'elementor-geo-popup') . '</span>';
            case 'expired':
                return '<span style="color: orange;">⚠ ' . __('Expired', 'elementor-geo-popup') . '</span>';
            case 'inactive':
                return '<span style="color: gray;">○ ' . __('Inactive', 'elementor-geo-popup') . '</span>';
            default:
                return '<span style="color: gray;">○ ' . __('Not Set', 'elementor-geo-popup') . '</span>';
        }
    }
    
    /**
     * AJAX activate license
     */
    public function ajax_activate_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        $required_cap = defined('EGP_LICENSE_CAPABILITY') ? EGP_LICENSE_CAPABILITY : 'manage_options';
        if (!current_user_can($required_cap)) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $license_key = sanitize_text_field($_POST['license_key']);
        
        if (empty($license_key)) {
            wp_send_json_error(__('License key is required', 'elementor-geo-popup'));
        }
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Attempting to activate license key: ' . substr($license_key, 0, 8) . '...');
        }
        
        $result = $this->activate_license($license_key);
        
        if (is_wp_error($result)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: Activation failed with error: ' . $result->get_error_message());
            }
            wp_send_json_error($result->get_error_message());
        }
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Activation successful');
        }
        
        wp_send_json_success(__('License activated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX deactivate license
     */
    public function ajax_deactivate_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        $required_cap = defined('EGP_LICENSE_CAPABILITY') ? EGP_LICENSE_CAPABILITY : 'manage_options';
        if (!current_user_can($required_cap)) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $result = $this->deactivate_license();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('License deactivated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX check license
     */
    public function ajax_check_license() {
        check_ajax_referer('egp_license_nonce', 'nonce');
        
        $required_cap = defined('EGP_LICENSE_CAPABILITY') ? EGP_LICENSE_CAPABILITY : 'manage_options';
        if (!current_user_can($required_cap)) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $result = $this->check_license_status();
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('License status updated', 'elementor-geo-popup'));
    }
    
    /**
     * Activate license using centralized manager
     */
    private function activate_license($license_key) {
        $site_url = get_site_url();
        $domain = wp_parse_url($site_url, PHP_URL_HOST);
        $plugin_version = defined('EGP_VERSION') ? EGP_VERSION : '1.0.0';
        
        // Debug logging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Starting activation process for domain: ' . $domain);
            error_log('EGP License: Plugin version: ' . $plugin_version);
            error_log('EGP License: License manager instance: ' . (is_object($this->license_manager) ? 'Valid' : 'Invalid'));
        }
        
        // Check if license manager is available
        if (!$this->license_manager || !method_exists($this->license_manager, 'activate_license')) {
            // Fallback: Basic license activation (for testing/development)
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: License manager not available, using fallback activation');
            }
            
            // Simple validation - just store the key and mark as valid
            if (strlen($license_key) >= 8) {
                update_option('egp_license_key', $license_key);
                update_option('egp_license_status', 'valid');
                update_option('egp_license_data', array(
                    'valid' => true,
                    'product_name' => 'Geo Elementor (Development)',
                    'expires_at' => time() + (365 * 24 * 60 * 60), // 1 year from now
                    'features' => array('basic_geo_targeting', 'page_targeting', 'popup_targeting')
                ));
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('EGP License: Fallback activation successful');
                }
                
                return true;
            }
            
            return new WP_Error('invalid_key', __('License key must be at least 8 characters long', 'elementor-geo-popup'));
        }
        
        $result = $this->license_manager->activate_license(
            $this->plugin_slug,
            $license_key,
            $domain,
            $plugin_version,
            'geo'
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: License manager response: ' . print_r($result, true));
        }
        
        if (is_wp_error($result)) {
            return $result;
        }
        
        $access_token = $result['accessToken'] ?? $result['access_token'] ?? '';
        $refresh_token = $result['refreshToken'] ?? $result['refresh_token'] ?? '';
        $expires_at = intval($result['expires_at'] ?? 0);
        
        if (empty($access_token) || empty($refresh_token)) {
            return new WP_Error('activation_failed', __('License activation failed', 'elementor-geo-popup'));
        }
        
        // Store license data and tokens (both generic and slugged, for verifier compatibility)
        update_option('egp_license_key', $license_key);
        update_option('egp_license_access_token', $access_token);
        update_option('egp_license_refresh_token', $refresh_token);
        update_option('egp_license_expires_at', $expires_at);
        update_option("{$this->plugin_slug}_license_access_token", $access_token);
        update_option("{$this->plugin_slug}_license_refresh_token", $refresh_token);
        update_option('egp_license_status', 'valid');
        
        // Store complete license data from server response
        $license_data_to_store = array(
            'valid' => true,
            'success' => true,
            'packageType' => $result['packageType'] ?? 'geo-free',
            'version' => $result['version'] ?? '1.0.0',
            'expires_at' => $expires_at,
            'accessToken' => $access_token,
            'refreshToken' => $refresh_token,
            'product_name' => 'Geo Elementor ' . ucfirst(($result['packageType'] ?? 'free')),
            'sites_count' => $result['sitesCount'] ?? 1,
            'sites_limit' => $result['sitesLimit'] ?? 'unlimited',
            'features' => array('basic_geo_targeting', 'page_targeting', 'popup_targeting')
        );
        
        // Merge with any additional data from server
        if (is_array($result)) {
            $license_data_to_store = array_merge($license_data_to_store, $result);
        }
        
        update_option('egp_license_data', $license_data_to_store);
        
        // Fetch features/limits via centralized manager (if available)
        if ($this->license_manager && method_exists($this->license_manager, 'get_license_data')) {
            $license_data = $this->license_manager->get_license_data($this->plugin_slug, $license_key, true);
            if (isset($license_data['valid']) && $license_data['valid']) {
                // Merge server data with our stored data
                $merged_data = array_merge($license_data_to_store, $license_data);
                update_option('egp_license_data', $merged_data);
            }
        }
        
        return true;
    }
    
    /**
     * Deactivate license
     */
    private function deactivate_license() {
        $license_key = get_option('egp_license_key');
        
        if (!$license_key) {
            return true; // Already deactivated
        }
        
        $site_url = get_site_url();
        
        $response = wp_remote_post($this->license_manager->license_server . '/deactivate', array(
            'headers' => array('Content-Type' => 'application/json'),
            'body' => wp_json_encode(array(
                'licenseKey' => $license_key,
                'domain' => wp_parse_url($site_url, PHP_URL_HOST)
            )),
            'timeout' => 30
        ));
        
        // Even if the server request fails, we should deactivate locally
        delete_option('egp_license_key');
        delete_option('egp_license_status');
        delete_option('egp_license_data');
        delete_option('egp_license_access_token');
        delete_option('egp_license_refresh_token');
        delete_option('egp_license_expires_at');
        delete_option("{$this->plugin_slug}_license_access_token");
        delete_option("{$this->plugin_slug}_license_refresh_token");
        
        // Clear cached license data
        wp_cache_delete("license_data_{$this->plugin_slug}", 'egp_license_cache');
        
        return true;
    }
    
    /**
     * Check license status using centralized manager
     */
    public function check_license_status() {
        $license_key = get_option('egp_license_key');
        
        if (!$license_key) {
            return true; // No license to check
        }
        
        // Debug: Log what we're checking
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Checking license status for key: ' . substr($license_key, 0, 8) . '...');
            error_log('EGP License: Current stored status: ' . get_option('egp_license_status', 'none'));
        }
        
        // Force refresh to avoid stale cache after activation
        $license_data = $this->license_manager->get_license_data($this->plugin_slug, $license_key, true);
        
        // Debug: Log what we got back
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Centralized manager returned: ' . print_r($license_data, true));
        }
        
        $is_valid = false;
        if (is_array($license_data)) {
            if (isset($license_data['valid'])) {
                $is_valid = (bool)$license_data['valid'];
            } elseif (isset($license_data['success'])) {
                $is_valid = (bool)$license_data['success'];
            }
        }
        
        // Debug: Log the validation result
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Validation result - is_valid: ' . ($is_valid ? 'true' : 'false'));
        }
        
        if ($is_valid) {
            // Extract and format the license data for display
            $formatted_license_data = $this->format_license_data_for_display($license_data);
            
            update_option('egp_license_status', 'valid');
            update_option('egp_license_data', $formatted_license_data);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: Status updated to valid');
                error_log('EGP License: Formatted data: ' . print_r($formatted_license_data, true));
            }
        } else {
            $error_msg = 'License is invalid';
            if (is_array($license_data) && isset($license_data['error'])) {
                $reason = isset($license_data['reason']) ? trim($license_data['reason']) : '';
                $error_msg = $license_data['error'] . ($reason !== '' ? (': ' . $reason) : '');
            }
            update_option('egp_license_status', 'invalid');
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('EGP License: Status updated to invalid - ' . $error_msg);
            }
            return new WP_Error('license_invalid', __($error_msg, 'elementor-geo-popup'));
        }
        
        return true;
    }
    
    /**
     * Check if license is valid
     */
    public function is_license_valid() {
        $status = get_option('egp_license_status');
        return $status === 'valid';
    }
    
    /**
     * Display license notices
     */
    public function license_notices() {
        $required_cap = defined('EGP_LICENSE_CAPABILITY') ? EGP_LICENSE_CAPABILITY : 'manage_options';
        if (!current_user_can($required_cap)) {
            return;
        }
        
        $license_status = get_option('egp_license_status');
        
        if ($license_status === 'invalid' || $license_status === 'expired') {
            $default_url = admin_url('admin.php?page=geo-elementor-license');
            ?>
            <div class="notice notice-error">
                <p>
                    <?php _e('Elementor Geo Popup license is invalid or expired. Please', 'elementor-geo-popup'); ?>
                    <a href="<?php echo esc_url($default_url); ?>">
                        <?php _e('check your license', 'elementor-geo-popup'); ?>
                    </a>
                    <?php _e('to continue using all features.', 'elementor-geo-popup'); ?>
                </p>
            </div>
            <?php
        } elseif ($license_status === 'inactive') {
            $default_url = admin_url('admin.php?page=geo-elementor-license');
            ?>
            <div class="notice notice-warning">
                <p>
                    <?php _e('Elementor Geo Popup license is not activated. Please', 'elementor-geo-popup'); ?>
                    <a href="<?php echo esc_url($default_url); ?>">
                        <?php _e('activate your license', 'elementor-geo-popup'); ?>
                    </a>
                    <?php _e('to access all features.', 'elementor-geo-popup'); ?>
                </p>
            </div>
            <?php
        }
    }
    
    /**
     * Get license data
     */
    public function get_license_data() {
        return get_option('egp_license_data', array());
    }

    /**
     * Format license data for display, extracting relevant fields from nested arrays.
     */
    private function format_license_data_for_display($license_data) {
        $formatted_data = array(
            'valid' => true,
            'success' => true
        );

        // Extract product name from package data
        if (isset($license_data['package']['name'])) {
            $formatted_data['product_name'] = $license_data['package']['name'];
        } elseif (isset($license_data['packageType'])) {
            $formatted_data['product_name'] = 'Geo Elementor ' . ucfirst($license_data['packageType']);
        } else {
            $formatted_data['product_name'] = 'Geo Elementor';
        }

        // Extract version
        if (isset($license_data['version'])) {
            $formatted_data['version'] = $license_data['version'];
        } else {
            $formatted_data['version'] = '1.0.0';
        }

        // Handle expiration based on package type
        $package_slug = $license_data['package']['slug'] ?? $license_data['packageType'] ?? '';
        if ($package_slug === 'geo-free' || $package_slug === 'free') {
            // Free plans never expire
            $formatted_data['expires_at'] = 'never';
            $formatted_data['expires_text'] = 'Never Expires';
            $formatted_data['is_free_plan'] = true;
        } else {
            // Paid plans have expiration
            if (isset($license_data['expires_at'])) {
                $formatted_data['expires_at'] = $license_data['expires_at'];
                $formatted_data['expires_text'] = date('F j, Y', $license_data['expires_at']);
            } elseif (isset($license_data['expires'])) {
                $formatted_data['expires_at'] = $license_data['expires'];
                $formatted_data['expires_text'] = date('F j, Y', $license_data['expires']);
            } else {
                $formatted_data['expires_at'] = '';
                $formatted_data['expires_text'] = 'Not specified';
            }
            $formatted_data['is_free_plan'] = false;
        }

        // Extract sites information based on package type
        if ($package_slug === 'geo-free' || $package_slug === 'free') {
            $formatted_data['sites_count'] = 1;
            $formatted_data['sites_limit'] = 'unlimited';
            $formatted_data['sites_text'] = '1 / Unlimited';
        } else {
            // Paid plans may have site limits
            if (isset($license_data['sitesCount'])) {
                $formatted_data['sites_count'] = $license_data['sitesCount'];
            } elseif (isset($license_data['sites_count'])) {
                $formatted_data['sites_count'] = $license_data['sites_count'];
            } else {
                $formatted_data['sites_count'] = 1;
            }

            if (isset($license_data['sitesLimit'])) {
                $formatted_data['sites_limit'] = $license_data['sitesLimit'];
            } elseif (isset($license_data['sites_limit'])) {
                $formatted_data['sites_limit'] = $license_data['sites_limit'];
            } else {
                $formatted_data['sites_limit'] = 'unlimited';
            }
            
            $formatted_data['sites_text'] = $formatted_data['sites_count'] . ' / ' . $formatted_data['sites_limit'];
        }

        // Extract features from package
        if (isset($license_data['package']['features'])) {
            $formatted_data['features'] = $license_data['package']['features'];
        } else {
            $formatted_data['features'] = array('basic_geo_targeting', 'page_targeting', 'popup_targeting');
        }

        // Add package type
        if (isset($license_data['package']['slug'])) {
            $formatted_data['packageType'] = $license_data['package']['slug'];
        }

        // Add license status
        if (isset($license_data['license']['status'])) {
            $formatted_data['license_status'] = $license_data['license']['status'];
        }

        // Add user tracking information (for future marketing)
        if (isset($license_data['license']['id'])) {
            $formatted_data['license_id'] = $license_data['license']['id'];
        }
        
        if (isset($license_data['license']['domain'])) {
            $formatted_data['installed_domain'] = $license_data['license']['domain'];
        }

        // Track user information for marketing (non-intrusive)
        $this->track_user_usage($formatted_data);

        return $formatted_data;
    }

    /**
     * Track user usage for marketing purposes (non-intrusive)
     */
    private function track_user_usage($license_data) {
        // Only track basic usage, no personal data
        $tracking_data = array(
            'license_id' => $license_data['license_id'] ?? '',
            'domain' => $license_data['installed_domain'] ?? get_site_url(),
            'package_type' => $license_data['packageType'] ?? 'unknown',
            'is_free_plan' => $license_data['is_free_plan'] ?? false,
            'plugin_version' => defined('EGP_VERSION') ? EGP_VERSION : '1.0.0',
            'wordpress_version' => get_bloginfo('version'),
            'tracking_timestamp' => time()
        );

        // Store locally for potential future use
        update_option('egp_usage_tracking', $tracking_data);

        // Send to license server if it supports usage tracking
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('EGP License: Usage tracking data: ' . print_r($tracking_data, true));
        }

        // In the future, you can implement server-side tracking here
        // wp_remote_post($this->license_server . '/track-usage', array(
        //     'body' => wp_json_encode($tracking_data),
        //     'timeout' => 10
        // ));
    }
}

// Initialize the centralized license manager
EGP_Centralized_License_Manager::get_instance();



