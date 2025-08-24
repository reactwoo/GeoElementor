<?php
/**
 * Database Layer for Geo Targeting System
 * 
 * @package ElementorGeoPopup
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Geo Database Manager
 */
class EGP_Geo_Database {
    
    private static $instance = null;
    private $db;
    private $charset_collate;
    
    // Table names
    private $variants_table;
    private $mappings_table;
    
    // Constants for geo types
    const GEO_TYPE_PAGE = 1;
    const GEO_TYPE_POPUP = 2;
    const GEO_TYPE_SECTION = 4;
    const GEO_TYPE_WIDGET = 8;
    
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
        
        $this->variants_table = $this->db->prefix . 'egp_geo_variants';
        $this->mappings_table = $this->db->prefix . 'egp_geo_mappings';
        
        add_action('init', array($this, 'maybe_create_tables'));
    }
    
    /**
     * Create database tables if they don't exist
     */
    public function maybe_create_tables() {
        if (get_option('egp_db_version') !== EGP_VERSION) {
            $this->create_tables();
            update_option('egp_db_version', EGP_VERSION);
        }
    }
    
    /**
     * Create database tables
     */
    private function create_tables() {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        // Variants table
        $sql = "CREATE TABLE {$this->variants_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            description text,
            countries longtext NOT NULL,
            priority int(11) NOT NULL DEFAULT 50,
            active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY priority (priority),
            KEY active (active)
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        // Mappings table
        $sql = "CREATE TABLE {$this->mappings_table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            variant_id bigint(20) unsigned NOT NULL,
            target_type tinyint(4) NOT NULL,
            target_id bigint(20) unsigned NOT NULL,
            target_title varchar(255) NOT NULL,
            source varchar(50) NOT NULL DEFAULT 'manual',
            tracking_id varchar(255),
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY variant_id (variant_id),
            KEY target_type (target_type),
            KEY target_id (target_id),
            KEY source (source),
            FOREIGN KEY (variant_id) REFERENCES {$this->variants_table}(id) ON DELETE CASCADE
        ) {$this->charset_collate};";
        
        dbDelta($sql);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EGP] Database tables created/updated');
        }
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
        delete_option('egp_db_version');
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[EGP] Database tables dropped');
        }
    }
}

/**
 * Variant CRUD Class
 */
class EGP_Geo_Variant_CRUD {
    
    private $db;
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = EGP_Geo_Database::get_instance()->get_variants_table();
    }
    
    /**
     * Create a new variant
     */
    public function create($data) {
        $defaults = array(
            'name' => '',
            'description' => '',
            'countries' => array(),
            'priority' => 50,
            'active' => 1
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['name'])) {
            return new WP_Error('missing_name', 'Variant name is required');
        }
        
        if (empty($data['countries'])) {
            return new WP_Error('missing_countries', 'At least one country must be selected');
        }
        
        // Sanitize data
        $insert_data = array(
            'name' => sanitize_text_field($data['name']),
            'description' => sanitize_textarea_field($data['description']),
            'countries' => wp_json_encode(array_map('sanitize_text_field', $data['countries'])),
            'priority' => intval($data['priority']),
            'active' => intval($data['active'])
        );
        
        $result = $this->db->insert($this->table, $insert_data);
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to create variant');
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
            $result->countries = json_decode($result->countries, true);
        }
        
        return $result;
    }
    
    /**
     * Get all variants
     */
    public function get_all($args = array()) {
        $defaults = array(
            'active' => null,
            'orderby' => 'priority',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_values = array();
        
        if ($args['active'] !== null) {
            $where[] = 'active = %d';
            $where_values[] = intval($args['active']);
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        
        $sql = "SELECT * FROM {$this->table} {$where_clause} {$order_clause}";
        
        if (!empty($where_values)) {
            $sql = $this->db->prepare($sql, $where_values);
        }
        
        $results = $this->db->get_results($sql);
        
        // Decode countries for each result
        foreach ($results as $result) {
            $result->countries = json_decode($result->countries, true);
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
            return new WP_Error('not_found', 'Variant not found');
        }
        
        $update_data = array();
        
        if (isset($data['name'])) {
            $update_data['name'] = sanitize_text_field($data['name']);
        }
        
        if (isset($data['description'])) {
            $update_data['description'] = sanitize_textarea_field($data['description']);
        }
        
        if (isset($data['countries'])) {
            $update_data['countries'] = wp_json_encode(array_map('sanitize_text_field', $data['countries']));
        }
        
        if (isset($data['priority'])) {
            $update_data['priority'] = intval($data['priority']);
        }
        
        if (isset($data['active'])) {
            $update_data['active'] = intval($data['active']);
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
            return new WP_Error('db_error', 'Failed to update variant');
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
            return new WP_Error('db_error', 'Failed to delete variant');
        }
        
        return true;
    }
}

/**
 * Mapping CRUD Class
 */
class EGP_Geo_Mapping_CRUD {
    
    private $db;
    private $table;
    
    public function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->table = EGP_Geo_Database::get_instance()->get_mappings_table();
    }
    
    /**
     * Create a new mapping
     */
    public function create($data) {
        $defaults = array(
            'variant_id' => 0,
            'target_type' => 0,
            'target_id' => 0,
            'target_title' => '',
            'source' => 'manual',
            'tracking_id' => ''
        );
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate required fields
        if (empty($data['variant_id'])) {
            return new WP_Error('missing_variant', 'Variant ID is required');
        }
        
        if (empty($data['target_type']) || empty($data['target_id'])) {
            return new WP_Error('missing_target', 'Target type and ID are required');
        }
        
        // Check if variant exists
        $variant_crud = new EGP_Geo_Variant_CRUD();
        if (!$variant_crud->get($data['variant_id'])) {
            return new WP_Error('invalid_variant', 'Variant not found');
        }
        
        // Check if mapping already exists
        $existing = $this->get_by_target($data['target_type'], $data['target_id']);
        if ($existing) {
            return new WP_Error('duplicate_mapping', 'Mapping already exists for this target');
        }
        
        // Sanitize data
        $insert_data = array(
            'variant_id' => intval($data['variant_id']),
            'target_type' => intval($data['target_type']),
            'target_id' => intval($data['target_id']),
            'target_title' => sanitize_text_field($data['target_title']),
            'source' => sanitize_text_field($data['source']),
            'tracking_id' => sanitize_text_field($data['tracking_id'])
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
        return $this->db->get_row(
            $this->db->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id)
        );
    }
    
    /**
     * Get mapping by target
     */
    public function get_by_target($target_type, $target_id) {
        $target_type = intval($target_type);
        $target_id = intval($target_id);
        
        return $this->db->get_row(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE target_type = %d AND target_id = %d",
                $target_type,
                $target_id
            )
        );
    }
    
    /**
     * Get all mappings for a variant
     */
    public function get_by_variant($variant_id) {
        $variant_id = intval($variant_id);
        
        return $this->db->get_results(
            $this->db->prepare(
                "SELECT * FROM {$this->table} WHERE variant_id = %d ORDER BY created_at DESC",
                $variant_id
            )
        );
    }
    
    /**
     * Get all mappings
     */
    public function get_all($args = array()) {
        $defaults = array(
            'variant_id' => null,
            'target_type' => null,
            'source' => null,
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where = array();
        $where_values = array();
        
        if ($args['variant_id'] !== null) {
            $where[] = 'variant_id = %d';
            $where_values[] = intval($args['variant_id']);
        }
        
        if ($args['target_type'] !== null) {
            $where[] = 'target_type = %d';
            $where_values[] = intval($args['target_type']);
        }
        
        if ($args['source'] !== null) {
            $where[] = 'source = %s';
            $where_values[] = sanitize_text_field($args['source']);
        }
        
        $where_clause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $order_clause = "ORDER BY {$args['orderby']} {$args['order']}";
        
        $sql = "SELECT * FROM {$this->table} {$where_clause} {$order_clause}";
        
        if (!empty($where_values)) {
            $sql = $this->db->prepare($sql, $where_values);
        }
        
        return $this->db->get_results($sql);
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
        
        if (isset($data['variant_id'])) {
            $update_data['variant_id'] = intval($data['variant_id']);
        }
        
        if (isset($data['target_title'])) {
            $update_data['target_title'] = sanitize_text_field($data['target_title']);
        }
        
        if (isset($data['tracking_id'])) {
            $update_data['tracking_id'] = sanitize_text_field($data['tracking_id']);
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
     * Delete mapping by target
     */
    public function delete_by_target($target_type, $target_id) {
        $target_type = intval($target_type);
        $target_id = intval($target_id);
        
        $result = $this->db->delete(
            $this->table,
            array(
                'target_type' => $target_type,
                'target_id' => $target_id
            ),
            array('%d', '%d')
        );
        
        if ($result === false) {
            return new WP_Error('db_error', 'Failed to delete mapping');
        }
        
        return true;
    }
}

// Initialize the database system
EGP_Geo_Database::get_instance();
