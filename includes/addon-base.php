<?php
/**
 * Base Add-On Class
 * 
 * Base class for all Geo Elementor add-ons
 * 
 * @package ElementorGeoPopup
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Base Add-On Class
 */
abstract class EGP_Base_Addon {
    
    /**
     * Add-on ID
     */
    protected $addon_id;
    
    /**
     * Add-on data
     */
    protected $addon_data;
    
    /**
     * Add-on settings
     */
    protected $settings;
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }
    
    /**
     * Initialize the add-on
     */
    public function init() {
        $this->addon_id = $this->get_addon_id();
        $this->addon_data = $this->get_addon_data();
        $this->settings = $this->get_settings();
        
        $this->init_hooks();
        $this->init_elementor_integration();
        $this->init_frontend();
    }
    
    /**
     * Get add-on ID (must be implemented by child class)
     */
    abstract protected function get_addon_id();
    
    /**
     * Get add-on data (must be implemented by child class)
     */
    abstract protected function get_addon_data();
    
    /**
     * Initialize hooks
     */
    protected function init_hooks() {
        // Override in child class
    }
    
    /**
     * Initialize Elementor integration
     */
    protected function init_elementor_integration() {
        // Override in child class
    }
    
    /**
     * Initialize frontend functionality
     */
    protected function init_frontend() {
        // Override in child class
    }
    
    /**
     * Get add-on settings
     */
    protected function get_settings() {
        return get_option('egp_addon_' . $this->addon_id . '_settings', array());
    }
    
    /**
     * Save add-on settings
     */
    protected function save_settings($settings) {
        $this->settings = $settings;
        update_option('egp_addon_' . $this->addon_id . '_settings', $settings);
    }
    
    /**
     * Get setting value
     */
    protected function get_setting($key, $default = null) {
        return isset($this->settings[$key]) ? $this->settings[$key] : $default;
    }
    
    /**
     * Set setting value
     */
    protected function set_setting($key, $value) {
        $this->settings[$key] = $value;
        $this->save_settings($this->settings);
    }
    
    /**
     * Add Elementor controls to widgets
     */
    protected function add_elementor_controls($element, $controls) {
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }
        
        // Add controls section
        $element->start_controls_section(
            'egp_' . $this->addon_id . '_section',
            array(
                'label' => $this->addon_data['name'],
                'tab' => \Elementor\Controls_Manager::TAB_ADVANCED,
            )
        );
        
        // Add individual controls
        foreach ($controls as $control) {
            $element->add_control(
                'egp_' . $this->addon_id . '_' . $control['name'],
                $control['args']
            );
        }
        
        $element->end_controls_section();
    }
    
    /**
     * Check if targeting condition is met
     */
    protected function is_targeting_condition_met($settings, $context = array()) {
        // Override in child class
        return true;
    }
    
    /**
     * Get targeting data for frontend
     */
    protected function get_targeting_data($context = array()) {
        // Override in child class
        return array();
    }
    
    /**
     * Render admin settings page
     */
    public function render_admin_settings() {
        // Override in child class
        echo '<p>' . sprintf(__('Settings for %s add-on', 'elementor-geo-popup'), $this->addon_data['name']) . '</p>';
    }
    
    /**
     * Handle AJAX requests
     */
    public function handle_ajax_request($action, $data) {
        // Override in child class
        return false;
    }
    
    /**
     * Get add-on version
     */
    public function get_version() {
        return isset($this->addon_data['version']) ? $this->addon_data['version'] : '1.0.0';
    }
    
    /**
     * Check if add-on is active
     */
    public function is_active() {
        $addon_manager = EGP_Addon_Manager::get_instance();
        return $addon_manager->is_addon_active($this->addon_id);
    }
    
    /**
     * Log debug message
     */
    protected function log_debug($message) {
        if (get_option('egp_debug_mode')) {
            error_log('[EGP Add-on ' . $this->addon_id . '] ' . $message);
        }
    }
    
    /**
     * Get visitor data
     */
    protected function get_visitor_data() {
        $geo_detect = EGP_Geo_Detect::get_instance();
        
        return array(
            'country' => $geo_detect->get_visitor_country(),
            'ip' => $this->get_visitor_ip(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'referrer' => $_SERVER['HTTP_REFERER'] ?? '',
            'timestamp' => current_time('timestamp')
        );
    }
    
    /**
     * Get visitor IP
     */
    protected function get_visitor_ip() {
        $ip_keys = array('HTTP_CF_CONNECTING_IP', 'HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER) === true) {
                foreach (explode(',', $_SERVER[$key]) as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false) {
                        return $ip;
                    }
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    /**
     * Make API request
     */
    protected function make_api_request($url, $args = array()) {
        $defaults = array(
            'method' => 'GET',
            'timeout' => 30,
            'headers' => array(),
            'body' => null
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $response = wp_remote_request($url, $args);
        
        if (is_wp_error($response)) {
            $this->log_debug('API request failed: ' . $response->get_error_message());
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->log_debug('API response JSON decode error: ' . json_last_error_msg());
            return false;
        }
        
        return $data;
    }
    
    /**
     * Cache data
     */
    protected function cache_set($key, $data, $expiration = 3600) {
        $cache_key = 'egp_addon_' . $this->addon_id . '_' . $key;
        wp_cache_set($cache_key, $data, 'egp_addons', $expiration);
    }
    
    /**
     * Get cached data
     */
    protected function cache_get($key) {
        $cache_key = 'egp_addon_' . $this->addon_id . '_' . $key;
        return wp_cache_get($cache_key, 'egp_addons');
    }
    
    /**
     * Delete cached data
     */
    protected function cache_delete($key) {
        $cache_key = 'egp_addon_' . $this->addon_id . '_' . $key;
        wp_cache_delete($cache_key, 'egp_addons');
    }
    
    /**
     * Add admin notice
     */
    protected function add_admin_notice($message, $type = 'info') {
        add_action('admin_notices', function() use ($message, $type) {
            $class = 'notice notice-' . $type;
            printf('<div class="%1$s"><p>%2$s</p></div>', esc_attr($class), esc_html($message));
        });
    }
    
    /**
     * Get add-on URL
     */
    protected function get_addon_url($path = '') {
        return EGP_PLUGIN_URL . 'addons/' . $this->addon_id . '/' . $path;
    }
    
    /**
     * Get add-on path
     */
    protected function get_addon_path($path = '') {
        return EGP_PLUGIN_DIR . 'addons/' . $this->addon_id . '/' . $path;
    }
    
    /**
     * Enqueue script
     */
    protected function enqueue_script($handle, $src, $deps = array(), $ver = null, $in_footer = true) {
        if ($ver === null) {
            $ver = $this->get_version();
        }
        
        wp_enqueue_script($handle, $src, $deps, $ver, $in_footer);
    }
    
    /**
     * Enqueue style
     */
    protected function enqueue_style($handle, $src, $deps = array(), $ver = null) {
        if ($ver === null) {
            $ver = $this->get_version();
        }
        
        wp_enqueue_style($handle, $src, $deps, $ver);
    }
    
    /**
     * Register AJAX handler
     */
    protected function register_ajax_handler($action, $callback) {
        add_action('wp_ajax_egp_' . $this->addon_id . '_' . $action, $callback);
        add_action('wp_ajax_nopriv_egp_' . $this->addon_id . '_' . $action, $callback);
    }
    
    /**
     * Create nonce
     */
    protected function create_nonce($action = '') {
        return wp_create_nonce('egp_' . $this->addon_id . '_' . $action);
    }
    
    /**
     * Verify nonce
     */
    protected function verify_nonce($nonce, $action = '') {
        return wp_verify_nonce($nonce, 'egp_' . $this->addon_id . '_' . $action);
    }
}