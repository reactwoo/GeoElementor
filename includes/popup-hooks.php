<?php
/**
 * Popup Hooks Integration
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Popup Hooks Integration Class
 */
class EGP_Popup_Hooks {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action('elementor/init', array($this, 'init_popup_hooks'));
        add_filter('elementor_pro/popup/display_conditions', array($this, 'add_geo_display_condition'));
        add_action('elementor_pro/popup/before_render', array($this, 'check_geo_display_condition'));
        add_action('wp_ajax_egp_get_popup_stats', array($this, 'ajax_get_popup_stats'));
        add_action('wp_ajax_nopriv_egp_get_popup_stats', array($this, 'ajax_get_popup_stats'));
    }
    
    /**
     * Initialize popup hooks
     */
    public function init_popup_hooks() {
        // Add custom popup display conditions
        add_action('elementor_pro/popup/display_conditions/register', array($this, 'register_geo_conditions'));
        
        // Hook into popup display logic
        add_filter('elementor_pro/popup/should_show', array($this, 'filter_popup_display'), 10, 2);
        
        // Add analytics tracking
        add_action('elementor_pro/popup/after_render', array($this, 'track_popup_view'));
        add_action('elementor_pro/popup/after_close', array($this, 'track_popup_close'));
    }
    
    /**
     * Register geo display conditions
     */
    public function register_geo_conditions($conditions_registry) {
        // This would integrate with Elementor Pro's display conditions system
        // For now, we'll handle it through our custom logic
    }
    
    /**
     * Filter popup display based on geo conditions
     */
    public function filter_popup_display($should_show, $popup_id) {
        // If popup shouldn't show for other reasons, don't override
        if (!$should_show) {
            return false;
        }
        
        // Check if this popup has geo targeting enabled
        $geo_settings = $this->get_popup_geo_settings($popup_id);
        
        if (!$geo_settings || empty($geo_settings['enabled'])) {
            return $should_show; // No geo targeting, show normally
        }
        
        // Get visitor's country
        $visitor_country = $this->get_visitor_country();
        
        if (!$visitor_country) {
            // Couldn't determine country, check fallback behavior
            return $this->handle_fallback_behavior($geo_settings['fallback_behavior']);
        }
        
        // Normalize codes and check if visitor's country (ISO-2) is in target countries
        $targets = array_map('strtoupper', (array) $geo_settings['countries']);
        if (in_array(strtoupper($visitor_country), $targets, true)) {
            return true; // Country matches, show popup
        }
        
        // Country doesn't match: do not show this popup
        return false;
    }
    
    /**
     * Get popup geo settings
     */
    private function get_popup_geo_settings($popup_id) {
        // Prefer Elementor document settings stored on the popup
        $page_settings = get_post_meta($popup_id, '_elementor_page_settings', true);
        if (is_array($page_settings) && !empty($page_settings['egp_enable_geo_targeting']) && $page_settings['egp_enable_geo_targeting'] === 'yes') {
            $countries = array();
            if (!empty($page_settings['egp_countries']) && is_array($page_settings['egp_countries'])) {
                $countries = array_map('sanitize_text_field', $page_settings['egp_countries']);
            }
            $fallback = !empty($page_settings['egp_fallback_behavior']) ? sanitize_text_field($page_settings['egp_fallback_behavior']) : 'inherit';
            return array(
                'enabled' => true,
                'countries' => $countries,
                'fallback_behavior' => $fallback,
            );
        }

        // Fallback to legacy DB mapping if present
        global $wpdb;
        $table_name = $wpdb->prefix . 'egp_popup_countries';
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE popup_id = %d",
            $popup_id
        ));
        if ($row) {
            return array(
                'enabled' => (bool) $row->enabled,
                'countries' => json_decode($row->countries, true) ?: array(),
                'fallback_behavior' => $row->fallback_behavior ?: 'inherit'
            );
        }
        
        return false;
    }
    
    /**
     * Get visitor country (cached)
     */
    private function get_visitor_country() {
        $ip = $this->get_visitor_ip();
        
        if (!$ip) {
            return false;
        }
        
        // Check cache first
        $cached_country = wp_cache_get('egp_visitor_country_' . $ip, 'egp_geo');
        
        if ($cached_country !== false) {
            return $cached_country;
        }
        
        // Look up country
        $country = $this->lookup_country($ip);
        
        if ($country) {
            wp_cache_set('egp_visitor_country_' . $ip, $country, 'egp_geo', HOUR_IN_SECONDS);
        }
        
        return $country;
    }
    
    /**
     * Get visitor IP
     */
    private function get_visitor_ip() {
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
     * Look up country
     */
    private function lookup_country($ip) {
        $database_path = get_option('egp_database_path');
        
        if (!$database_path || !file_exists($database_path)) {
            return false;
        }
        
        try {
            if (!class_exists('GeoIp2\Database\Reader')) {
                return false;
            }
            
            $reader = new GeoIp2\Database\Reader($database_path);
            $record = $reader->country($ip);
            $country_code = $record->country->isoCode;
            $reader->close();
            
            return $country_code;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Handle fallback behavior
     */
    private function handle_fallback_behavior($fallback_behavior) {
        switch ($fallback_behavior) {
            case 'show_to_all':
                return true;
            case 'show_to_none':
                return false;
            case 'show_default':
                $default_popup_id = get_option('egp_default_popup_id', 0);
                return $default_popup_id > 0;
            case 'inherit':
            default:
                // Inherit from global settings
                $global_fallback = get_option('egp_fallback_behavior', 'show_to_all');
                return $this->handle_fallback_behavior($global_fallback);
        }
    }
    
    /**
     * Track popup view for analytics
     */
    public function track_popup_view($popup_id) {
        if (!$popup_id) {
            return;
        }
        
        $visitor_country = $this->get_visitor_country();
        $ip = $this->get_visitor_ip();
        
        // Store popup view data
        $this->store_popup_event($popup_id, 'view', $visitor_country, $ip);
        
        // Update popup statistics
        $this->update_popup_stats($popup_id, 'view');
    }
    
    /**
     * Track popup close for analytics
     */
    public function track_popup_close($popup_id) {
        if (!$popup_id) {
            return;
        }
        
        $visitor_country = $this->get_visitor_country();
        $ip = $this->get_visitor_ip();
        
        // Store popup close data
        $this->store_popup_event($popup_id, 'close', $visitor_country, $ip);
        
        // Update popup statistics
        $this->update_popup_stats($popup_id, 'close');
    }
    
    /**
     * Store popup event data
     */
    private function store_popup_event($popup_id, $event_type, $country, $ip) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'egp_popup_events';
        
        // Create table if it doesn't exist
        $this->create_events_table();
        
        $wpdb->insert(
            $table_name,
            array(
                'popup_id' => $popup_id,
                'event_type' => $event_type,
                'country' => $country,
                'ip_address' => $ip,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s', '%s')
        );
    }
    
    /**
     * Create events table
     */
    private function create_events_table() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'egp_popup_events';
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            $charset_collate = $wpdb->get_charset_collate();
            
            $sql = "CREATE TABLE $table_name (
                id bigint(20) NOT NULL AUTO_INCREMENT,
                popup_id bigint(20) NOT NULL,
                event_type varchar(20) NOT NULL,
                country varchar(2) DEFAULT NULL,
                ip_address varchar(45) DEFAULT NULL,
                user_agent text DEFAULT NULL,
                created_at datetime DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY popup_id (popup_id),
                KEY event_type (event_type),
                KEY country (country),
                KEY created_at (created_at)
            ) $charset_collate;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }
    
    /**
     * Update popup statistics
     */
    private function update_popup_stats($popup_id, $event_type) {
        $stats_key = 'egp_popup_stats_' . $popup_id;
        $stats = get_option($stats_key, array());
        
        if (!isset($stats[$event_type])) {
            $stats[$event_type] = 0;
        }
        
        $stats[$event_type]++;
        
        // Update total views
        if ($event_type === 'view') {
            if (!isset($stats['total_views'])) {
                $stats['total_views'] = 0;
            }
            $stats['total_views']++;
        }
        
        // Update total closes
        if ($event_type === 'close') {
            if (!isset($stats['total_closes'])) {
                $stats['total_closes'] = 0;
            }
            $stats['total_closes']++;
        }
        
        // Calculate conversion rate
        if (isset($stats['total_views']) && isset($stats['total_closes']) && $stats['total_views'] > 0) {
            $stats['conversion_rate'] = round(($stats['total_closes'] / $stats['total_views']) * 100, 2);
        }
        
        update_option($stats_key, $stats);
    }
    
    /**
     * AJAX get popup statistics
     */
    public function ajax_get_popup_stats() {
        check_ajax_referer('egp_stats_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_die(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $popup_id = intval($_GET['popup_id']);
        
        if (!$popup_id) {
            wp_send_json_error(__('Invalid popup ID', 'elementor-geo-popup'));
        }
        
        $stats = $this->get_popup_stats($popup_id);
        wp_send_json_success($stats);
    }
    
    /**
     * Get popup statistics
     */
    private function get_popup_stats($popup_id) {
        $stats_key = 'egp_popup_stats_' . $popup_id;
        $stats = get_option($stats_key, array());
        
        // Get country breakdown
        global $wpdb;
        $table_name = $wpdb->prefix . 'egp_popup_events';
        
        $country_stats = $wpdb->get_results($wpdb->prepare(
            "SELECT country, COUNT(*) as count, event_type 
             FROM $table_name 
             WHERE popup_id = %d 
             GROUP BY country, event_type",
            $popup_id
        ));
        
        $country_breakdown = array();
        foreach ($country_stats as $row) {
            if (!isset($country_breakdown[$row->country])) {
                $country_breakdown[$row->country] = array(
                    'views' => 0,
                    'closes' => 0
                );
            }
            $country_breakdown[$row->country][$row->event_type . 's'] = $row->count;
        }
        
        return array(
            'stats' => $stats,
            'country_breakdown' => $country_breakdown
        );
    }
    
    /**
     * Add geo display condition to Elementor
     */
    public function add_geo_display_condition($conditions) {
        // This would integrate with Elementor Pro's display conditions
        // For now, we handle it through our custom logic
        return $conditions;
    }
    
    /**
     * Check geo display condition before rendering
     */
    public function check_geo_display_condition($popup_id) {
        // This is called before popup rendering
        // We can use this to set up any necessary data
    }
}



