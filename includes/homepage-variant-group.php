<?php
/**
 * Homepage Variant Group Integration
 * 
 * Allows using variant groups for homepage and blog page in WordPress Settings → Reading
 * Shows different pages based on visitor's country
 * 
 * @package GeoElementor
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_Homepage_Variant_Group {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add settings fields to Reading settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Modify front page query based on variant group
        add_action('pre_get_posts', array($this, 'modify_front_page_query'), 1);
        
        // Filter page_on_front option
        add_filter('option_page_on_front', array($this, 'filter_homepage_option'));
        add_filter('option_page_for_posts', array($this, 'filter_blog_page_option'));
        
        error_log('[EGP] Homepage Variant Group initialized');
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        // Add fields to Reading settings page
        add_settings_section(
            'egp_homepage_section',
            '<span class="dashicons dashicons-location-alt"></span> ' . __('Geo-Targeted Homepage', 'elementor-geo-popup'),
            array($this, 'render_section_description'),
            'reading'
        );
        
        // Homepage variant group
        add_settings_field(
            'egp_homepage_variant_group',
            __('Use Variant Group for Homepage', 'elementor-geo-popup'),
            array($this, 'render_homepage_group_field'),
            'reading',
            'egp_homepage_section'
        );
        
        // Blog page variant group
        add_settings_field(
            'egp_blog_variant_group',
            __('Use Variant Group for Blog Page', 'elementor-geo-popup'),
            array($this, 'render_blog_group_field'),
            'reading',
            'egp_homepage_section'
        );
        
        register_setting('reading', 'egp_homepage_variant_group');
        register_setting('reading', 'egp_blog_variant_group');
    }
    
    /**
     * Section description
     */
    public function render_section_description() {
        echo '<p class="description">';
        _e('Use variant groups to show different homepages/blog pages based on visitor country. When enabled, the page selection above will be overridden by the group.', 'elementor-geo-popup');
        echo '</p>';
    }
    
    /**
     * Render homepage group field
     */
    public function render_homepage_group_field() {
        $selected = get_option('egp_homepage_variant_group', '');
        $groups = $this->get_variant_groups();
        
        echo '<select name="egp_homepage_variant_group" id="egp_homepage_variant_group">';
        echo '<option value="">— ' . __('Use Single Page (WordPress default)', 'elementor-geo-popup') . ' —</option>';
        
        foreach ($groups as $group) {
            echo '<option value="' . esc_attr($group['id']) . '" ' . selected($selected, $group['id'], false) . '>';
            echo esc_html($group['name']) . ' (' . count($group['mappings']) . ' variants)';
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">';
        _e('If selected, visitors will see different homepages based on their country (as configured in the variant group).', 'elementor-geo-popup');
        echo '</p>';
    }
    
    /**
     * Render blog page group field
     */
    public function render_blog_group_field() {
        $selected = get_option('egp_blog_variant_group', '');
        $groups = $this->get_variant_groups();
        
        echo '<select name="egp_blog_variant_group" id="egp_blog_variant_group">';
        echo '<option value="">— ' . __('Use Single Page (WordPress default)', 'elementor-geo-popup') . ' —</option>';
        
        foreach ($groups as $group) {
            echo '<option value="' . esc_attr($group['id']) . '" ' . selected($selected, $group['id'], false) . '>';
            echo esc_html($group['name']) . ' (' . count($group['mappings']) . ' variants)';
            echo '</option>';
        }
        
        echo '</select>';
        echo '<p class="description">';
        _e('If selected, visitors will see different blog pages based on their country (as configured in the variant group).', 'elementor-geo-popup');
        echo '</p>';
    }
    
    /**
     * Get all variant groups
     */
    private function get_variant_groups() {
        if (!class_exists('RW_Geo_Variant_CRUD')) {
            return array();
        }
        
        $variant_crud = new RW_Geo_Variant_CRUD();
        return $variant_crud->get_all();
    }
    
    /**
     * Modify front page query to use variant group
     */
    public function modify_front_page_query($query) {
        // Only on main query for front page
        if (!$query->is_main_query() || is_admin()) {
            return;
        }
        
        // Check if we're on the front page or blog page
        if ($query->is_home() || $query->is_front_page()) {
            // This is handled by option filters instead
            // We don't modify the query directly
        }
    }
    
    /**
     * Filter homepage option based on variant group
     */
    public function filter_homepage_option($page_id) {
        // Only filter on frontend
        if (is_admin()) {
            return $page_id;
        }
        
        $group_id = get_option('egp_homepage_variant_group', '');
        
        if (empty($group_id)) {
            return $page_id; // No group selected, use WordPress default
        }
        
        // Get page for user's country from variant group
        $country_page = $this->get_page_for_country($group_id);
        
        if ($country_page) {
            error_log('[EGP] Homepage override: Using page ' . $country_page . ' from variant group ' . $group_id);
            return $country_page;
        }
        
        // Fallback to original page if no match
        error_log('[EGP] Homepage: No variant found, using default page ' . $page_id);
        return $page_id;
    }
    
    /**
     * Filter blog page option based on variant group
     */
    public function filter_blog_page_option($page_id) {
        // Only filter on frontend
        if (is_admin()) {
            return $page_id;
        }
        
        $group_id = get_option('egp_blog_variant_group', '');
        
        if (empty($group_id)) {
            return $page_id; // No group selected, use WordPress default
        }
        
        // Get page for user's country from variant group
        $country_page = $this->get_page_for_country($group_id);
        
        if ($country_page) {
            error_log('[EGP] Blog page override: Using page ' . $country_page . ' from variant group ' . $group_id);
            return $country_page;
        }
        
        // Fallback to original page if no match
        error_log('[EGP] Blog page: No variant found, using default page ' . $page_id);
        return $page_id;
    }
    
    /**
     * Get page ID for user's country from variant group
     */
    private function get_page_for_country($group_id) {
        if (!class_exists('RW_Geo_Variant_CRUD')) {
            return null;
        }
        
        // Get user's country
        $user_country = 'US'; // Default
        if (class_exists('EGP_Geo_Detect')) {
            $geo_detect = EGP_Geo_Detect::get_instance();
            $user_country = $geo_detect->get_visitor_country();
        }
        
        $user_country = strtoupper($user_country);
        
        // Get variant group
        $variant_crud = new RW_Geo_Variant_CRUD();
        $group = $variant_crud->get_by_id($group_id);
        
        if (!$group) {
            return null;
        }
        
        // Find matching mapping for user's country
        $mappings = isset($group['mappings']) ? $group['mappings'] : array();
        
        foreach ($mappings as $mapping) {
            $countries = isset($mapping['countries']) ? $mapping['countries'] : array();
            $countries = array_map('strtoupper', $countries);
            
            if (in_array($user_country, $countries)) {
                $target_id = isset($mapping['target_id']) ? intval($mapping['target_id']) : 0;
                if ($target_id > 0) {
                    error_log('[EGP] Found variant for country ' . $user_country . ': page ' . $target_id);
                    return $target_id;
                }
            }
        }
        
        // No match found, use default target if available
        if (isset($group['default_target_id']) && $group['default_target_id'] > 0) {
            error_log('[EGP] Using default variant: page ' . $group['default_target_id']);
            return intval($group['default_target_id']);
        }
        
        return null;
    }
}

// Initialize
EGP_Homepage_Variant_Group::get_instance();

