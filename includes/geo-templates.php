<?php
/**
 * Geo Templates System
 * 
 * Manages reusable geo-targeted content templates that can be inserted via widgets
 * 
 * @package GeoElementor
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class EGP_Geo_Templates {
    
    private static $instance = null;
    private $post_type = 'geo_template';
    private $meta_prefix = 'egp_';
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        add_action('init', array($this, 'register_post_type'));
        add_action('admin_menu', array($this, 'add_admin_menu'), 20);
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        
        // AJAX handlers
        add_action('wp_ajax_egp_save_geo_template', array($this, 'ajax_save_template'));
        add_action('wp_ajax_egp_delete_geo_template', array($this, 'ajax_delete_template'));
        add_action('wp_ajax_egp_get_geo_template', array($this, 'ajax_get_template'));
    }
    
    /**
     * Register custom post type for geo templates
     */
    public function register_post_type() {
        $args = array(
            'label' => __('Geo Templates', 'elementor-geo-popup'),
            'public' => true, // MUST be true for Elementor editor
            'publicly_queryable' => true, // Allow Elementor to query
            'show_ui' => true, // Show in admin (but we hide from menu)
            'show_in_menu' => false, // Hide default menu (we have custom page)
            'show_in_rest' => true, // Required for Elementor editor
            'capability_type' => 'post',
            'hierarchical' => false,
            'supports' => array('title', 'editor', 'elementor', 'custom-fields'),
            'has_archive' => false,
            'rewrite' => false,
            'query_var' => true, // Allow queries
            'can_export' => true,
            'exclude_from_search' => true, // Don't show in site search
        );
        
        register_post_type($this->post_type, $args);
        
        // Tell Elementor this post type is supported
        add_filter('elementor/documents/register', array($this, 'register_elementor_document_type'));
    }
    
    /**
     * Register Elementor document type for templates
     */
    public function register_elementor_document_type($documents_manager) {
        // Make sure Elementor can edit geo_template posts
        add_post_type_support('geo_template', 'elementor');
        return $documents_manager;
    }
    
    /**
     * Add admin menu page
     */
    public function add_admin_menu() {
        add_submenu_page(
            'geo-elementor',
            __('Geo Templates', 'elementor-geo-popup'),
            __('Geo Templates', 'elementor-geo-popup'),
            'edit_posts',
            'geo-templates',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_admin_scripts($hook) {
        if ($hook !== 'geo-elementor_page_geo-templates') {
            return;
        }
        
        wp_enqueue_style(
            'egp-templates-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/templates-admin.css',
            array(),
            '1.0.0'
        );
        
        wp_enqueue_script(
            'egp-templates-admin',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/templates-admin.js',
            array('jquery'),
            '1.0.0',
            true
        );
        
        wp_localize_script('egp-templates-admin', 'egpTemplates', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('egp_templates_nonce'),
            'countries' => $this->get_countries_list(),
        ));
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        $templates = $this->get_all_templates();
        ?>
        <div class="wrap egp-templates-wrap">
            <h1 class="wp-heading-inline">
                <?php _e('Geo Templates', 'elementor-geo-popup'); ?>
            </h1>
            <a href="#" class="page-title-action egp-new-template">
                <?php _e('Add New', 'elementor-geo-popup'); ?>
            </a>
            
            <p class="description">
                <?php _e('Create reusable geo-targeted content that can be inserted anywhere using Elementor widgets or shortcodes.', 'elementor-geo-popup'); ?>
            </p>
            
            <div class="egp-templates-grid">
                
                <!-- Templates List -->
                <div class="egp-templates-list">
                    <?php if (empty($templates)) : ?>
                        <div class="egp-empty-state">
                            <div class="egp-empty-icon">📄</div>
                            <h3><?php _e('No templates yet', 'elementor-geo-popup'); ?></h3>
                            <p><?php _e('Create your first geo-targeted template to get started.', 'elementor-geo-popup'); ?></p>
                            <a href="#" class="button button-primary egp-new-template">
                                <?php _e('Create Template', 'elementor-geo-popup'); ?>
                            </a>
                        </div>
                    <?php else : ?>
                        <table class="wp-list-table widefat fixed striped">
                            <thead>
                                <tr>
                                    <th><?php _e('Name', 'elementor-geo-popup'); ?></th>
                                    <th><?php _e('Type', 'elementor-geo-popup'); ?></th>
                                    <th><?php _e('Countries', 'elementor-geo-popup'); ?></th>
                                    <th><?php _e('Usage', 'elementor-geo-popup'); ?></th>
                                    <th><?php _e('Actions', 'elementor-geo-popup'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($templates as $template) : 
                                    $template_id = $template->ID;
                                    $type = get_post_meta($template_id, $this->meta_prefix . 'template_type', true);
                                    $countries = get_post_meta($template_id, $this->meta_prefix . 'countries', true);
                                    $usage = get_post_meta($template_id, $this->meta_prefix . 'usage_count', true);
                                ?>
                                    <tr data-template-id="<?php echo esc_attr($template_id); ?>">
                                        <td>
                                            <strong><?php echo esc_html($template->post_title); ?></strong>
                                        </td>
                                        <td>
                                            <span class="egp-template-type-badge egp-type-<?php echo esc_attr($type); ?>">
                                                <?php echo esc_html(ucfirst($type ?: 'section')); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php 
                                            if (is_array($countries) && !empty($countries)) {
                                                echo esc_html(implode(', ', array_slice($countries, 0, 3)));
                                                if (count($countries) > 3) {
                                                    echo ' <span class="egp-more-countries">+' . (count($countries) - 3) . '</span>';
                                                }
                                            } else {
                                                echo '<span class="egp-no-countries">' . __('None', 'elementor-geo-popup') . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <?php echo intval($usage); ?> <?php _e('pages', 'elementor-geo-popup'); ?>
                                        </td>
                                        <td>
                                            <a href="<?php echo admin_url('post.php?post=' . $template_id . '&action=elementor'); ?>" 
                                               class="button button-small" target="_blank">
                                                <?php _e('Edit with Elementor', 'elementor-geo-popup'); ?>
                                            </a>
                                            <a href="#" class="button button-small egp-edit-template" 
                                               data-template-id="<?php echo esc_attr($template_id); ?>">
                                                <?php _e('Settings', 'elementor-geo-popup'); ?>
                                            </a>
                                            <a href="#" class="button button-small egp-delete-template" 
                                               data-template-id="<?php echo esc_attr($template_id); ?>">
                                                <?php _e('Delete', 'elementor-geo-popup'); ?>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
                
            </div>
        </div>
        
        <!-- Template Editor Modal -->
        <div id="egp-template-modal" class="egp-modal" style="display: none;">
            <div class="egp-modal-content">
                <span class="egp-modal-close">&times;</span>
                <h2 id="egp-modal-title"><?php _e('New Geo Template', 'elementor-geo-popup'); ?></h2>
                
                <form id="egp-template-form">
                    <input type="hidden" id="egp-template-id" name="template_id" value="">
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="egp-template-name">
                                    <?php _e('Template Name', 'elementor-geo-popup'); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <input type="text" id="egp-template-name" name="template_name" 
                                       class="regular-text" required>
                                <p class="description">
                                    <?php _e('A descriptive name for this template (e.g., "Japan Promo Header")', 'elementor-geo-popup'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="egp-template-type">
                                    <?php _e('Template Type', 'elementor-geo-popup'); ?>
                                </label>
                            </th>
                            <td>
                                <select id="egp-template-type" name="template_type">
                                    <option value="section"><?php _e('Section', 'elementor-geo-popup'); ?></option>
                                    <option value="container"><?php _e('Container', 'elementor-geo-popup'); ?></option>
                                    <option value="form"><?php _e('Form', 'elementor-geo-popup'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('The type of content this template contains', 'elementor-geo-popup'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="egp-template-countries">
                                    <?php _e('Target Countries', 'elementor-geo-popup'); ?>
                                    <span class="required">*</span>
                                </label>
                            </th>
                            <td>
                                <select id="egp-template-countries" name="template_countries[]" 
                                        multiple="multiple" style="width: 100%; max-width: 500px;" required>
                                    <?php foreach ($this->get_countries_list() as $code => $name) : ?>
                                        <option value="<?php echo esc_attr($code); ?>">
                                            <?php echo esc_html($name); ?> (<?php echo esc_html($code); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">
                                    <?php _e('This template will only show to visitors from these countries', 'elementor-geo-popup'); ?>
                                </p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">
                                <label for="egp-template-fallback">
                                    <?php _e('Fallback Behavior', 'elementor-geo-popup'); ?>
                                </label>
                            </th>
                            <td>
                                <select id="egp-template-fallback" name="template_fallback">
                                    <option value="hide"><?php _e('Hide (show nothing)', 'elementor-geo-popup'); ?></option>
                                    <option value="show_default"><?php _e('Show default content', 'elementor-geo-popup'); ?></option>
                                </select>
                                <p class="description">
                                    <?php _e('What to show visitors from non-targeted countries', 'elementor-geo-popup'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary">
                            <?php _e('Save Template', 'elementor-geo-popup'); ?>
                        </button>
                        <button type="button" class="button egp-modal-close">
                            <?php _e('Cancel', 'elementor-geo-popup'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
        <?php
    }
    
    /**
     * AJAX: Save template
     */
    public function ajax_save_template() {
        check_ajax_referer('egp_templates_nonce', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $template_id = intval($_POST['template_id'] ?? 0);
        $name = sanitize_text_field($_POST['template_name'] ?? '');
        $type = sanitize_text_field($_POST['template_type'] ?? 'section');
        $countries = array_map('sanitize_text_field', $_POST['template_countries'] ?? array());
        $fallback = sanitize_text_field($_POST['template_fallback'] ?? 'hide');
        
        if (empty($name) || empty($countries)) {
            wp_send_json_error('Template name and countries are required');
        }
        
        // Create or update post
        $post_data = array(
            'post_title' => $name,
            'post_type' => $this->post_type,
            'post_status' => 'publish',
        );
        
        if ($template_id > 0) {
            $post_data['ID'] = $template_id;
            $result = wp_update_post($post_data);
        } else {
            $result = wp_insert_post($post_data);
            $template_id = $result;
        }
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        // Save meta
        update_post_meta($template_id, $this->meta_prefix . 'template_type', $type);
        update_post_meta($template_id, $this->meta_prefix . 'countries', $countries);
        update_post_meta($template_id, $this->meta_prefix . 'fallback_mode', $fallback);
        update_post_meta($template_id, $this->meta_prefix . 'content_type', 'elementor');
        
        // Initialize Elementor data for new templates
        if (!get_post_meta($template_id, '_elementor_edit_mode', true)) {
            update_post_meta($template_id, '_elementor_edit_mode', 'builder');
            update_post_meta($template_id, '_elementor_template_type', 'page');
            
            // Set Elementor version (with fallback)
            $elementor_version = defined('ELEMENTOR_VERSION') ? ELEMENTOR_VERSION : '3.0.0';
            update_post_meta($template_id, '_elementor_version', $elementor_version);
            
            // Add empty Elementor data structure
            $initial_data = json_encode(array());
            update_post_meta($template_id, '_elementor_data', $initial_data);
            
            // Add basic page settings
            $page_settings = array(
                'post_status' => 'publish',
                'template' => 'elementor_canvas', // Use canvas template
            );
            update_post_meta($template_id, '_elementor_page_settings', $page_settings);
            
            // Trigger Elementor to recognize this as editable
            if (class_exists('\Elementor\Plugin')) {
                \Elementor\Plugin::$instance->db->set_is_elementor_page($template_id, true);
            }
        }
        
        wp_send_json_success(array(
            'template_id' => $template_id,
            'message' => 'Template saved successfully',
            'edit_url' => admin_url('post.php?post=' . $template_id . '&action=elementor'),
        ));
    }
    
    /**
     * AJAX: Delete template
     */
    public function ajax_delete_template() {
        check_ajax_referer('egp_templates_nonce', 'nonce');
        
        if (!current_user_can('delete_posts')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        $template_id = intval($_POST['template_id'] ?? 0);
        
        if ($template_id <= 0) {
            wp_send_json_error('Invalid template ID');
        }
        
        $result = wp_delete_post($template_id, true);
        
        if ($result) {
            wp_send_json_success('Template deleted successfully');
        } else {
            wp_send_json_error('Failed to delete template');
        }
    }
    
    /**
     * AJAX: Get template data
     */
    public function ajax_get_template() {
        check_ajax_referer('egp_templates_nonce', 'nonce');
        
        $template_id = intval($_POST['template_id'] ?? 0);
        
        if ($template_id <= 0) {
            wp_send_json_error('Invalid template ID');
        }
        
        $template = get_post($template_id);
        
        if (!$template) {
            wp_send_json_error('Template not found');
        }
        
        $data = array(
            'id' => $template_id,
            'name' => $template->post_title,
            'type' => get_post_meta($template_id, $this->meta_prefix . 'template_type', true),
            'countries' => get_post_meta($template_id, $this->meta_prefix . 'countries', true),
            'fallback' => get_post_meta($template_id, $this->meta_prefix . 'fallback_mode', true),
        );
        
        wp_send_json_success($data);
    }
    
    /**
     * Get all templates
     */
    public function get_all_templates() {
        return get_posts(array(
            'post_type' => $this->post_type,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ));
    }
    
    /**
     * Get template for widget dropdown
     */
    public function get_templates_for_select() {
        $templates = $this->get_all_templates();
        $options = array('' => __('Select a template', 'elementor-geo-popup'));
        
        foreach ($templates as $template) {
            $type = get_post_meta($template->ID, $this->meta_prefix . 'template_type', true);
            $options[$template->ID] = $template->post_title . ' (' . ucfirst($type) . ')';
        }
        
        return $options;
    }
    
    /**
     * Get countries list - Complete ISO-3166 list
     */
    private function get_countries_list() {
        // Try to load from JSON file first
        $json_path = plugin_dir_path(__FILE__) . '../assets/data/countries.json';
        $json_path = realpath($json_path);
        if ($json_path && file_exists($json_path)) {
            $contents = file_get_contents($json_path);
            $decoded = json_decode($contents, true);
            if (is_array($decoded) && !empty($decoded)) {
                $countries = array();
                foreach ($decoded as $country) {
                    if (isset($country['code']) && isset($country['name'])) {
                        $countries[$country['code']] = $country['name'];
                    }
                }
                if (!empty($countries)) {
                    return $countries;
                }
            }
        }
        
        // Fallback comprehensive list (same as geo-rules.php)
        return array(
            'US' => 'United States',
            'GB' => 'United Kingdom',
            'CA' => 'Canada',
            'AU' => 'Australia',
            'DE' => 'Germany',
            'FR' => 'France',
            'IT' => 'Italy',
            'ES' => 'Spain',
            'NL' => 'Netherlands',
            'BE' => 'Belgium',
            'SE' => 'Sweden',
            'NO' => 'Norway',
            'DK' => 'Denmark',
            'FI' => 'Finland',
            'CH' => 'Switzerland',
            'AT' => 'Austria',
            'IE' => 'Ireland',
            'NZ' => 'New Zealand',
            'JP' => 'Japan',
            'KR' => 'South Korea',
            'CN' => 'China',
            'IN' => 'India',
            'BR' => 'Brazil',
            'MX' => 'Mexico',
            'AR' => 'Argentina',
            'CL' => 'Chile',
            'CO' => 'Colombia',
            'PE' => 'Peru',
            'VE' => 'Venezuela',
            'ZA' => 'South Africa',
            'EG' => 'Egypt',
            'NG' => 'Nigeria',
            'KE' => 'Kenya',
            'MA' => 'Morocco',
            'SA' => 'Saudi Arabia',
            'AE' => 'United Arab Emirates',
            'IL' => 'Israel',
            'TR' => 'Turkey',
            'RU' => 'Russia',
            'PL' => 'Poland',
            'CZ' => 'Czech Republic',
            'HU' => 'Hungary',
            'RO' => 'Romania',
            'BG' => 'Bulgaria',
            'HR' => 'Croatia',
            'SI' => 'Slovenia',
            'SK' => 'Slovakia',
            'LT' => 'Lithuania',
            'LV' => 'Latvia',
            'EE' => 'Estonia',
            'MT' => 'Malta',
            'CY' => 'Cyprus',
            'GR' => 'Greece',
            'PT' => 'Portugal',
            'SG' => 'Singapore',
            'MY' => 'Malaysia',
            'TH' => 'Thailand',
            'ID' => 'Indonesia',
            'PH' => 'Philippines',
            'VN' => 'Vietnam',
            'PK' => 'Pakistan',
            'BD' => 'Bangladesh',
        );
    }
}

// Initialize
EGP_Geo_Templates::get_instance();

