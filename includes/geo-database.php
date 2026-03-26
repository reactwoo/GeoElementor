<?php
/**
 * Enhanced Database Layer for Geo Targeting System
 * Implements Variant Groups with fallback support as per UPDATED-SPEC.md
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants for geo types
define('RW_GEO_TYPE_PAGE', 1);
define('RW_GEO_TYPE_POPUP', 2);
define('RW_GEO_TYPE_SECTION', 4);
define('RW_GEO_TYPE_WIDGET', 8);

/**
 * Enhanced Geo Database Manager
 */
class RW_Geo_Database {
    
    private static $instance = null;
    private $db;
    private $charset_collate;
    
    // Table names
    private $variants_table;
    private $mappings_table;
    
    /**
     * Get singleton instance
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
        global $wpdb;
        $this->db = $wpdb;
        $this->charset_collate = $this->db->get_charset_collate();
        
        $this->variants_table = $this->db->prefix . 'rw_geo_variant';
        $this->mappings_table = $this->db->prefix . 'rw_geo_variant_mapping';
        
        add_action('init', array($this, 'maybe_create_tables'));
        add_action('init', array($this, 'maybe_set_default_options'));
    }
    
    /**
     * Create database tables if they don't exist
     */
    public function maybe_create_tables() {
        if (get_option('rw_geo_db_version') !== EGP_VERSION) {
            $this->create_tables();
            update_option('rw_geo_db_version', EGP_VERSION);
        }
    }
    
    /**
     * Set default options if not exists
     */
    public function maybe_set_default_options() {
        if (get_option('rw_geo_settings') === false) {
            $this->set_default_options();
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Variants table
        $sql = "CREATE TABLE {$this->variants_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(190) NOT NULL,
            slug VARCHAR(190) NOT NULL,
            type_mask TINYINT UNSIGNED NOT NULL DEFAULT 3,
            master_page_id BIGINT UNSIGNED NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            priority SMALLINT UNSIGNED NOT NULL DEFAULT 50,
            default_page_id BIGINT UNSIGNED NULL,
            default_popup_id BIGINT UNSIGNED NULL,
            default_section_ref VARCHAR(190) NULL,
            default_widget_ref VARCHAR(190) NULL,
            options JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY slug_unique (slug),
            KEY type_mask_idx (type_mask),
            KEY master_page_idx (master_page_id),
            KEY active_priority_idx (is_active, priority)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        // Mappings table
        $sql = "CREATE TABLE {$this->mappings_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            variant_id BIGINT UNSIGNED NOT NULL,
            country_iso2 CHAR(2) NOT NULL,
            page_id BIGINT UNSIGNED NULL,
            popup_id BIGINT UNSIGNED NULL,
            section_ref VARCHAR(190) NULL,
            widget_ref VARCHAR(190) NULL,
            options JSON NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_variant_country (variant_id, country_iso2),
            KEY variant_id_idx (variant_id),
            CONSTRAINT fk_variant
                FOREIGN KEY (variant_id) REFERENCES {$this->variants_table}(id)
                ON DELETE CASCADE
        ) {$this->charset_collate};";
        
        dbDelta($sql);

        // Experiment analytics table (A/B add-on contract).
        $events_table = $this->db->prefix . 'rw_geo_experiment_event';
        $sql = "CREATE TABLE {$events_table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            variant_id BIGINT UNSIGNED NOT NULL,
            experiment_key VARCHAR(120) NOT NULL,
            bucket_key VARCHAR(120) NOT NULL,
            visitor_hash VARCHAR(64) NOT NULL,
            event_type VARCHAR(40) NOT NULL,
            target_type VARCHAR(20) NULL,
            target_id BIGINT UNSIGNED NULL,
            country_iso2 CHAR(2) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY variant_exp_idx (variant_id, experiment_key),
            KEY visitor_idx (visitor_hash),
            KEY event_type_idx (event_type),
            KEY created_idx (created_at)
        ) {$this->charset_collate};";

        dbDelta($sql);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[RW Geo] Database tables created/updated');
        }
    }
    
    /**
     * Set default options
     */
    private function set_default_options() {
        $defaults = array(
            'maxmind' => array(
                'license_key' => '',
                'last_updated' => '',
                'db_path' => 'wp-content/uploads/geo-popup-db/GeoLite2-Country.mmdb',
                'auto_update' => false,
                'update_freq' => 'weekly'
            ),
            'qa' => array(
                'enable_force_param' => true,
                'param_name' => 'force_country'
            ),
            'selector' => array(
                'enabled' => true,
                'cookie_name' => 'rw_geo_region',
                'ttl_days' => 60
            ),
            'bots' => array(
                'skip_redirect' => true
            ),
            'defaults' => array(
                'variant_home_slug' => 'homepage'
            ),
            'routing' => array(
                'pro_group_precedence' => true,
                'auto_migrate_from_free' => true
            )
        );
        
        update_option('rw_geo_settings', $defaults);
    }
    
    /**
     * Get variants table name
     */
    public function get_variants_table() {
        return $this->variants_table;
    }
    
    /**
     * Get mappings table name
     */
    public function get_mappings_table() {
        return $this->mappings_table;
    }
    
    /**
     * Drop all tables (for deactivation)
     */
    public function drop_tables() {
        $this->db->query("DROP TABLE IF EXISTS {$this->mappings_table}");
        $this->db->query("DROP TABLE IF EXISTS {$this->variants_table}");
        delete_option('rw_geo_db_version');
        delete_option('rw_geo_settings');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[RW Geo] Database tables dropped');
        }
    }
}

// Initialize the enhanced database system
RW_Geo_Database::get_instance();

/**
 * Enhanced Variant CRUD Class
 */
class RW_Geo_Variant_CRUD {
    
    private $db;
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = RW_Geo_Database::get_instance()->get_variants_table();
    }
    
    /**
     * Create a new variant group
     */
    public function create($data) {
        $defaults = array(
            'name' => '',
            'slug' => '',
            'type_mask' => 3, // Page + Popup by default
            'master_page_id' => null,
            'is_active' => 1,
            'priority' => 50,
            'default_page_id' => null,
            'default_popup_id' => null,
            'default_section_ref' => null,
            'default_widget_ref' => null,
            'options' => array(
                'soft_redirect' => true,
                'show_selector' => true,
                'respect_cookie' => true,
                'skip_bots' => true,
                'cookie_ttl' => 60
            )
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['name'])) {
            return new WP_Error('missing_name', 'Variant group name is required');
        }
        
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['name']);
        }
        
        // Check if slug already exists
        if ($this->get_by_slug($data['slug'])) {
            return new WP_Error('duplicate_slug', 'A variant group with this slug already exists');
        }
        
        // Sanitize data
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'slug' => sanitize_key($data['slug']),
            'type_mask' => intval($data['type_mask']),
            'master_page_id' => $data['master_page_id'] ? intval($data['master_page_id']) : null,
            'is_active' => !empty($data['is_active']) ? 1 : 0,
            'priority' => isset($data['priority']) ? max(1, min(1000, intval($data['priority']))) : 50,
            'default_page_id' => $data['default_page_id'] ? intval($data['default_page_id']) : null,
            'default_popup_id' => $data['default_popup_id'] ? intval($data['default_popup_id']) : null,
            'default_section_ref' => $data['default_section_ref'] ? sanitize_text_field($data['default_section_ref']) : null,
            'default_widget_ref' => $data['default_widget_ref'] ? sanitize_text_field($data['default_widget_ref']) : null,
            'options' => wp_json_encode($data['options'])
        );
        
        $result = $this->db->insert($this->table, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create variant group');
        }
        
        return $this->db->insert_id;
    }
    
    /**
     * Get variant by ID
     */
    public function get($id) {
        $id = intval($id);
        $result = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );
        
        if ($result) {
            $result->options = json_decode($result->options, true);
        }
        
        return $result;
    }
    
    /**
     * Get variant by slug
     */
    public function get_by_slug($slug) {
        $slug = sanitize_key($slug);
        $result = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE slug = %s", $slug)
        );
        
        if ($result) {
            $result->options = json_decode($result->options, true);
        }
        
        return $result;
    }
    
    /**
     * Get all variants
     */
    public function get_all($args = array()) {
        $defaults = array(
            'type_mask' => null,
            'orderby' => 'name',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_values = array();
        
        if ($args['type_mask'] !== null) {
            $where[] = 'type_mask & %d';
            $where_values[] = intval($args['type_mask']);
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        
        $sql = "SELECT * FROM {$this->table} {$where_clause} {$order_clause}";
        
        if (!empty($where_values)) {
            $sql = $this->db->prepare($sql, $where_values);
        }
        
        $results = $this->db->get_results($sql);
        
        // Decode options for each result
        foreach ($results as $result) {
            $result->options = json_decode($result->options, true);
        }
        
        return $results;
    }
    
    /**
     * Update variant
     */
    public function update($id, $data) {
        $id = intval($id);
        
        // Check if variant exists
        if (!$this->get($id)) {
            return new WP_Error('not_found', 'Variant group not found');
        }
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['slug'])) {
            $new_slug = sanitize_key($data['slug']);
            // Check if new slug conflicts with existing
            $existing = $this->get_by_slug($new_slug);
            if ($existing && $existing->id != $id) {
                return new WP_Error('duplicate_slug', 'A variant group with this slug already exists');
            }
            $update_data['slug'] = $new_slug;
        }
        
        if (isset($data['type_mask'])) {
            $update_data['type_mask'] = intval($data['type_mask']);
        }

        if (isset($data['master_page_id'])) {
            $update_data['master_page_id'] = $data['master_page_id'] ? intval($data['master_page_id']) : null;
        }

        if (isset($data['is_active'])) {
            $update_data['is_active'] = !empty($data['is_active']) ? 1 : 0;
        }

        if (isset($data['priority'])) {
            $update_data['priority'] = max(1, min(1000, intval($data['priority'])));
        }
        
        if (isset($data['default_page_id'])) {
            $update_data['default_page_id'] = $data['default_page_id'] ? intval($data['default_page_id']) : null;
        }
        
        if (isset($data['default_popup_id'])) {
            $update_data['default_popup_id'] = $data['default_popup_id'] ? intval($data['default_popup_id']) : null;
        }
        
        if (isset($data['default_section_ref'])) {
            $update_data['default_section_ref'] = $data['default_section_ref'] ? sanitize_text_field($data['default_section_ref']) : null;
        }
        
        if (isset($data['default_widget_ref'])) {
            $update_data['default_widget_ref'] = $data['default_widget_ref'] ? sanitize_text_field($data['default_widget_ref']) : null;
        }
        
        if (isset($data['options'])) {
            $update_data['options'] = wp_json_encode($data['options']);
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'No data to update');
        }
        
        $result = $this->db->update(
            $this->table,
            $update_data,
            array('id' => $id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update variant group');
        }
        
        return true;
    }
    
    /**
     * Delete variant
     */
    public function delete($id) {
        $id = intval($id);
        
        $result = $this->db->delete(
            $this->table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete variant group');
        }
        
        return true;
    }

    /**
     * Get active variants mapped to a specific master page.
     *
     * @param int $master_page_id Master page ID.
     * @return array
     */
    public function get_active_by_master($master_page_id) {
        $master_page_id = intval($master_page_id);
        if ($master_page_id <= 0) {
            return array();
        }

        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE master_page_id = %d AND is_active = 1 ORDER BY priority ASC, id ASC",
                $master_page_id
            )
        );

        foreach ($results as $result) {
            $result->options = json_decode($result->options, true);
        }

        return is_array($results) ? $results : array();
    }
}

/**
 * Geo Routing & Context Manager
 */
class RW_Geo_Router {
    
    private static $instance = null;
    private $current_country = null;
    private $current_variant = null;
    private $resolved_mapping = null;
    
    /**
     * Get singleton instance
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
        add_action('template_redirect', array($this, 'route_current_request'), 0);
        add_action('wp_footer', array($this, 'inject_frontend_content'));
    }
    
    /**
     * Route current request based on geo context
     */
    public function route_current_request() {
        // Skip admin, AJAX, REST, or preview builders
        if (is_admin() || wp_doing_ajax() || defined('REST_REQUEST') || $this->is_elementor_editor()) {
            return;
        }
        
        $country = $this->detect_country();
        $master_page_id = $this->get_master_context_page_id();
        $variant = $this->get_active_variant_group_for_route($master_page_id);
        
        if (!$variant) {
            return;
        }
        
        // Bot skip
        $settings = get_option('rw_geo_settings', array());
        if (!empty($settings['bots']['skip_redirect']) && $this->is_bot()) {
            $this->set_context_country('GLOBAL');
            return;
        }
        
        $mapping = $this->resolve_mapping($variant, $country);
        $mapping = apply_filters('egp_ab_assignment_target', $mapping, $variant, $country, $master_page_id);
        $this->set_context_country($country ?: 'GLOBAL');
        $this->resolved_mapping = $mapping;
        
        // Page redirect handling
        if ($variant->type_mask & RW_GEO_TYPE_PAGE) {
            $current_id = get_queried_object_id();
            $target_id = $mapping->page_id ?: $variant->default_page_id;
            
            if ($target_id && $target_id != $current_id && !empty($variant->options['soft_redirect'])) {
                wp_safe_redirect(get_permalink($target_id), 302);
                exit;
            }
        }
    }
    
    /**
     * Detect visitor's country
     */
    public function detect_country() {
        // 1) Admin QA override
        $settings = get_option('rw_geo_settings', array());
        $param_name = !empty($settings['qa']['param_name']) ? $settings['qa']['param_name'] : 'force_country';
        
        if (current_user_can('manage_options') && isset($_GET[$param_name])) {
            return strtoupper(sanitize_text_field($_GET[$param_name]));
        }
        
        // 2) Region cookie override
        $cookie_name = !empty($settings['selector']['cookie_name']) ? $settings['selector']['cookie_name'] : 'rw_geo_region';
        if (!empty($_COOKIE[$cookie_name])) {
            return strtoupper($_COOKIE[$cookie_name]);
        }
        
        // 3) MaxMind lookup - use existing geo detection system
        try {
            if (class_exists('RW_Geo_Detect')) {
                $geo_detect = RW_Geo_Detect::get_instance();
                $country = $geo_detect->get_visitor_country();
                return $country ? strtoupper($country) : null;
            }
        } catch (Exception $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[RW Geo] Geo detection failed: ' . $e->getMessage());
            }
        }
        
        return null;
    }
    
    /**
     * Get active variant group for current route
     */
    public function get_active_variant_group_for_route($master_page_id = 0) {
        $master_page_id = intval($master_page_id);

        if ($master_page_id > 0) {
            $variant_crud = new RW_Geo_Variant_CRUD();
            $variants = $variant_crud->get_active_by_master($master_page_id);
            if (!empty($variants)) {
                return $variants[0];
            }
        }

        // Back-compat fallback: old default slug behavior.
        $settings = get_option('rw_geo_settings', array());
        $default_slug = !empty($settings['defaults']['variant_home_slug']) ? $settings['defaults']['variant_home_slug'] : 'homepage';
        
        $variant_crud = new RW_Geo_Variant_CRUD();
        return $variant_crud->get_by_slug($default_slug);
    }
    
    /**
     * Resolve mapping for variant and country
     */
    public function resolve_mapping($variant, $country) {
        if (!$variant) {
            return null;
        }
        
        $mapping_crud = new RW_Geo_Mapping_CRUD();
        
        if ($country) {
            $mapping = $mapping_crud->get_by_variant_country($variant->id, $country);
            if ($mapping) {
                return $mapping;
            }
        }
        
        // Return default mapping
        return (object) array(
            'page_id' => $variant->default_page_id,
            'popup_id' => $variant->default_popup_id,
            'section_ref' => $variant->default_section_ref,
            'widget_ref' => $variant->default_widget_ref
        );
    }

    /**
     * Resolve master context page ID for current request.
     *
     * @return int
     */
    private function get_master_context_page_id() {
        $page_id = get_queried_object_id();
        if (!$page_id) {
            return 0;
        }

        if (class_exists('RWGC_Routing')) {
            $cfg = RWGC_Routing::get_page_route_config((int) $page_id);
            if (!empty($cfg['enabled']) && isset($cfg['role']) && $cfg['role'] === 'variant' && !empty($cfg['master_page_id'])) {
                return intval($cfg['master_page_id']);
            }
        }

        return intval($page_id);
    }
    
    /**
     * Set context country
     */
    public function set_context_country($country) {
        $this->current_country = $country;
    }
    
    /**
     * Get current country context
     */
    public function get_current_country() {
        return $this->current_country;
    }
    
    /**
     * Get resolved mapping
     */
    public function get_resolved_mapping() {
        return $this->resolved_mapping;
    }
    
    /**
     * Check if current request is from Elementor editor
     */
    private function is_elementor_editor() {
        return isset($_GET['elementor-preview']) || 
               (isset($_GET['action']) && $_GET['action'] === 'elementor') ||
               (isset($_GET['post']) && get_post_type($_GET['post']) === 'elementor_library');
    }
    
    /**
     * Check if visitor is a bot
     */
    private function is_bot() {
        $bot_patterns = array(
            'bot', 'crawler', 'spider', 'scraper', 'curl', 'wget', 'python', 'java', 'perl'
        );
        
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $user_agent = strtolower($user_agent);
        
        foreach ($bot_patterns as $pattern) {
            if (strpos($user_agent, $pattern) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Inject frontend content (popups, etc.)
     */
    public function inject_frontend_content() {
        if (!$this->resolved_mapping) {
            return;
        }
        
        // Inject popup if available
        if (!empty($this->resolved_mapping->popup_id) && !$this->popup_seen($this->resolved_mapping->popup_id)) {
            echo "<script>
                document.addEventListener('DOMContentLoaded', function(){
                    if (window.elementorProFrontend && elementorProFrontend.modules && elementorProFrontend.modules.popup) {
                        elementorProFrontend.modules.popup.showPopup({ id: " . intval($this->resolved_mapping->popup_id) . " });
                    }
                });
            </script>";
        }
    }
    
    /**
     * Check if popup has been seen
     */
    private function popup_seen($popup_id) {
        $cookie_name = 'rw_popup_' . $popup_id . '_seen';
        return !empty($_COOKIE[$cookie_name]);
    }
}

// Initialize the router
RW_Geo_Router::get_instance();

/**
 * Enhanced Mapping CRUD Class
 */
class RW_Geo_Mapping_CRUD {
    
    private $db;
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = RW_Geo_Database::get_instance()->get_mappings_table();
    }
    
    /**
     * Create a new mapping
     */
    public function create($data) {
        $defaults = array(
            'variant_id' => 0,
            'country_iso2' => '',
            'page_id' => null,
            'popup_id' => null,
            'section_ref' => null,
            'widget_ref' => null,
            'options' => array()
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['variant_id'])) {
            return new WP_Error('missing_variant', 'Variant ID is required');
        }
        
        if (empty($data['country_iso2'])) {
            return new WP_Error('missing_country', 'Country ISO2 code is required');
        }
        
        // Validate country format
        if (!preg_match('/^[A-Z]{2}$/', $data['country_iso2'])) {
            return new WP_Error('invalid_country', 'Country must be a valid ISO2 code (e.g., US, GB)');
        }
        
        // Check if variant exists
        $variant_crud = new RW_Geo_Variant_CRUD();
        if (!$variant_crud->get($data['variant_id'])) {
            return new WP_Error('invalid_variant', 'Variant group not found');
        }
        
        // Check if mapping already exists
        $existing = $this->get_by_variant_country($data['variant_id'], $data['country_iso2']);
        if ($existing) {
            return new WP_Error('duplicate_mapping', 'Mapping already exists for this variant and country');
        }
        
        // Sanitize data
        $insert_data = array(
            'variant_id' => intval($data['variant_id']),
            'country_iso2' => strtoupper($data['country_iso2']),
            'page_id' => $data['page_id'] ? intval($data['page_id']) : null,
            'popup_id' => $data['popup_id'] ? intval($data['popup_id']) : null,
            'section_ref' => $data['section_ref'] ? sanitize_text_field($data['section_ref']) : null,
            'widget_ref' => $data['widget_ref'] ? sanitize_text_field($data['widget_ref']) : null,
            'options' => wp_json_encode($data['options'])
        );
        
        $result = $this->db->insert($this->table, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create mapping');
        }
        
        return $this->db->insert_id;
    }
    
    /**
     * Get mapping by ID
     */
    public function get($id) {
        $id = intval($id);
        $result = $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );
        
        if ($result) {
            $result->options = json_decode($result->options, true);
        }
        
        return $result;
    }
    
    /**
     * Get mapping by variant and country
     */
    public function get_by_variant_country($variant_id, $country_iso2) {
        $variant_id = intval($variant_id);
        $country_iso2 = strtoupper($country_iso2);
        
        $result = $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE variant_id = %d AND country_iso2 = %s",
                $variant_id,
                $country_iso2
            )
        );
        
        if ($result) {
            $result->options = json_decode($result->options, true);
        }
        
        return $result;
    }
    
    /**
     * Get all mappings for a variant
     */
    public function get_by_variant($variant_id) {
        $variant_id = intval($variant_id);
        
        $results = $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE variant_id = %d ORDER BY country_iso2 ASC",
                $variant_id
            )
        );
        
        // Decode options for each result
        foreach ($results as $result) {
            $result->options = json_decode($result->options, true);
        }
        
        return $results;
    }
    
    /**
     * Get all mappings
     */
    public function get_all($args = array()) {
        $defaults = array(
            'variant_id' => null,
            'country_iso2' => null,
            'orderby' => 'country_iso2',
            'order' => 'ASC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_values = array();
        
        if ($args['variant_id'] !== null) {
            $where[] = 'variant_id = %d';
            $where_values[] = intval($args['variant_id']);
        }
        
        if ($args['country_iso2'] !== null) {
            $where[] = 'country_iso2 = %s';
            $where_values[] = strtoupper($args['country_iso2']);
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        
        $sql = "SELECT * FROM {$this->table} {$where_clause} {$order_clause}";
        
        if (!empty($where_values)) {
            $sql = $this->db->prepare($sql, $where_values);
        }
        
        $results = $this->db->get_results($sql);
        
        // Decode options for each result
        foreach ($results as $result) {
            $result->options = json_decode($result->options, true);
        }
        
        return $results;
    }
    
    /**
     * Update mapping
     */
    public function update($id, $data) {
        $id = intval($id);
        
        // Check if mapping exists
        if (!$this->get($id)) {
            return new WP_Error('not_found', 'Mapping not found');
        }
        
        $update_data = array();
        
        if (isset($data['page_id'])) {
            $update_data['page_id'] = $data['page_id'] ? intval($data['page_id']) : null;
        }
        
        if (isset($data['popup_id'])) {
            $update_data['popup_id'] = $data['popup_id'] ? intval($data['popup_id']) : null;
        }
        
        if (isset($data['section_ref'])) {
            $update_data['section_ref'] = $data['section_ref'] ? sanitize_text_field($data['section_ref']) : null;
        }
        
        if (isset($data['widget_ref'])) {
            $update_data['widget_ref'] = $data['widget_ref'] ? sanitize_text_field($data['widget_ref']) : null;
        }
        
        if (isset($data['options'])) {
            $update_data['options'] = wp_json_encode($data['options']);
        }
        
        if (empty($update_data)) {
            return new WP_Error('no_data', 'No data to update');
        }
        
        $result = $this->db->update(
            $this->table,
            $update_data,
            array('id' => $id)
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to update mapping');
        }
        
        return true;
    }
    
    /**
     * Delete mapping
     */
    public function delete($id) {
        $id = intval($id);
        
        $result = $this->db->delete(
            $this->table,
            array('id' => $id),
            array('%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete mapping');
        }
        
        return true;
    }
    
    /**
     * Delete mapping by variant and country
     */
    public function delete_by_variant_country($variant_id, $country_iso2) {
        $variant_id = intval($variant_id);
        $country_iso2 = strtoupper($country_iso2);
        
        $result = $this->db->delete(
            $this->table,
            array(
                'variant_id' => $variant_id,
                'country_iso2' => $country_iso2
            ),
            array('%d', '%s')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete mapping');
        }
        
        return true;
    }
}

// AJAX: preview mapping for a given country (admin)
add_action('wp_ajax_rw_geo_preview_mapping', function(){
    check_ajax_referer('rw_geo_variants_nonce', 'nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error(__('Insufficient permissions', 'elementor-geo-popup')); }
    $country = isset($_POST['country']) ? sanitize_text_field($_POST['country']) : '';
    if (!$country) { wp_send_json_error(__('Missing country', 'elementor-geo-popup')); }
    $router = RW_Geo_Router::get_instance();
    $variant = $router->get_active_variant_group_for_route();
    if (!$variant) { wp_send_json_error(__('No group for this route', 'elementor-geo-popup')); }
    $mapping = $router->resolve_mapping($variant, strtoupper($country));
    $out = array(
        'variant' => array('id'=>$variant->id, 'name'=>$variant->name, 'slug'=>$variant->slug),
        'mapping' => $mapping ? array('id'=>$mapping->id, 'popup_id'=>$mapping->popup_id, 'page_id'=>$mapping->page_id) : null,
    );
    wp_send_json_success($out);
});
