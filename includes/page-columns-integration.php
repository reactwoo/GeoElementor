<?php
/**
 * WordPress Pages Columns Integration
 * 
 * Adds geo and group columns to WordPress pages admin
 * 
 * @package GeoElementor
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_Page_Columns_Integration {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add custom columns to pages
        add_filter('manage_page_posts_columns', array($this, 'add_columns'));
        add_action('manage_page_posts_custom_column', array($this, 'render_column'), 10, 2);
        
        // Make columns sortable
        add_filter('manage_edit-page_sortable_columns', array($this, 'sortable_columns'));
        
        // Add "Geo Pages" view/tab
        add_filter('views_edit-page', array($this, 'add_geo_view'));
        
        // Filter by geo view
        add_filter('parse_query', array($this, 'filter_geo_view'));
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        error_log('[EGP] Page Columns Integration initialized');
    }
    
    /**
     * Add custom columns
     */
    public function add_columns($columns) {
        // Add geo columns after title
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add after title column
            if ($key === 'title') {
                $new_columns['egp_geo'] = '<span class="dashicons dashicons-location-alt" title="Geo Targeting"></span> ' . __('Geo', 'elementor-geo-popup');
                $new_columns['egp_group'] = __('Variant Group', 'elementor-geo-popup');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public function render_column($column, $post_id) {
        switch ($column) {
            case 'egp_geo':
                // Check if page has geo rule
                $has_geo_rule = $this->check_page_has_geo_rule($post_id);
                
                // Check if page has Elementor geo settings
                $page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
                $elementor_geo = isset($page_settings['egp_geo_enabled']) && $page_settings['egp_geo_enabled'] === 'yes';
                
                if ($has_geo_rule || $elementor_geo) {
                    echo '<span class="egp-status-badge egp-enabled">✓ Enabled</span>';
                } else {
                    echo '<span class="egp-status-badge egp-disabled">Disabled</span>';
                }
                break;
                
            case 'egp_group':
                // Check if page is in a variant group
                $group_info = $this->get_page_variant_group($post_id);
                
                if ($group_info) {
                    echo '<a href="' . admin_url('admin.php?page=geo-variant-groups&action=edit&id=' . $group_info['id']) . '" class="egp-group-link">';
                    echo esc_html($group_info['name']);
                    echo '</a>';
                    if (!empty($group_info['countries'])) {
                        echo '<br><span class="egp-group-countries">' . esc_html(implode(', ', array_slice($group_info['countries'], 0, 3)));
                        if (count($group_info['countries']) > 3) {
                            echo ' +' . (count($group_info['countries']) - 3);
                        }
                        echo '</span>';
                    }
                } else {
                    echo '<span class="egp-no-group">—</span>';
                }
                break;
        }
    }
    
    /**
     * Check if page has geo rule
     */
    private function check_page_has_geo_rule($page_id) {
        $rules = get_posts(array(
            'post_type' => 'geo_rule',
            'meta_query' => array(
                array('key' => 'egp_target_type', 'value' => 'page'),
                array('key' => 'egp_target_id', 'value' => (string)$page_id),
                array('key' => 'egp_active', 'value' => '1')
            ),
            'posts_per_page' => 1,
            'fields' => 'ids'
        ));
        
        return !empty($rules);
    }
    
    /**
     * Get variant group for page
     */
    private function get_page_variant_group($page_id) {
        // Check if variant groups system exists
        if (!class_exists('RW_Geo_Variant_CRUD')) {
            return null;
        }
        
        try {
            $variant_crud = new RW_Geo_Variant_CRUD();
            $all_groups = $variant_crud->get_all();
        } catch (Exception $e) {
            error_log('[EGP] Error getting variant groups: ' . $e->getMessage());
            return null;
        }
        
        if (!is_array($all_groups)) {
            return null;
        }
        
        foreach ($all_groups as $group) {
            $mappings = isset($group['mappings']) ? $group['mappings'] : array();
            
            // Check if this page is in any mapping
            foreach ($mappings as $mapping) {
                if (isset($mapping['target_id']) && intval($mapping['target_id']) === intval($page_id)) {
                    return array(
                        'id' => $group['id'],
                        'name' => $group['name'],
                        'countries' => isset($mapping['countries']) ? $mapping['countries'] : array(),
                    );
                }
            }
            
            // Check default target
            if (isset($group['default_target_id']) && intval($group['default_target_id']) === intval($page_id)) {
                return array(
                    'id' => $group['id'],
                    'name' => $group['name'] . ' (Default)',
                    'countries' => array(),
                );
            }
        }
        
        return null;
    }
    
    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['egp_geo'] = 'egp_geo';
        return $columns;
    }
    
    /**
     * Add "Geo Pages" view tab
     */
    public function add_geo_view($views) {
        // Count pages with geo enabled
        $count = 0;
        
        // Count pages with geo rules
        $rules = get_posts(array(
            'post_type' => 'geo_rule',
            'meta_query' => array(
                array('key' => 'egp_target_type', 'value' => 'page'),
                array('key' => 'egp_active', 'value' => '1')
            ),
            'fields' => 'ids',
            'posts_per_page' => -1
        ));
        
        $count = count($rules);
        
        $class = (isset($_GET['geo_view']) && $_GET['geo_view'] === 'enabled') ? 'current' : '';
        
        $views['geo'] = sprintf(
            '<a href="%s" class="%s"><span class="dashicons dashicons-location-alt" style="vertical-align: middle;"></span> %s <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=page&geo_view=enabled'),
            $class,
            __('Geo Pages', 'elementor-geo-popup'),
            $count
        );
        
        return $views;
    }
    
    /**
     * Filter by geo view
     */
    public function filter_geo_view($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'page' && isset($_GET['geo_view']) && $_GET['geo_view'] === 'enabled') {
            // Get page IDs with geo rules
            $rules = get_posts(array(
                'post_type' => 'geo_rule',
                'meta_query' => array(
                    array('key' => 'egp_target_type', 'value' => 'page'),
                    array('key' => 'egp_active', 'value' => '1')
                ),
                'posts_per_page' => -1
            ));
            
            $page_ids = array();
            foreach ($rules as $rule) {
                $page_id = get_post_meta($rule->ID, 'egp_target_id', true);
                if ($page_id) {
                    $page_ids[] = intval($page_id);
                }
            }
            
            if (!empty($page_ids)) {
                $query->set('post__in', $page_ids);
            } else {
                // No geo pages, show none
                $query->set('post__in', array(0));
            }
        }
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'edit.php' || get_post_type() !== 'page') {
            return;
        }
        
        wp_enqueue_style('egp-page-columns', 
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/page-columns.css', 
            array(), '1.0.0');
    }
}

// Initialize
EGP_Page_Columns_Integration::get_instance();

