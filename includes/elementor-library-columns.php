<?php
/**
 * Elementor Library Columns Integration
 * 
 * Adds geo-targeting columns and controls to Elementor's native template library
 * 
 * @package GeoElementor
 * @since 1.1.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class EGP_Elementor_Library_Columns {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        // Add custom columns to Elementor library
        add_filter('manage_elementor_library_posts_columns', array($this, 'add_columns'));
        add_action('manage_elementor_library_posts_custom_column', array($this, 'render_column'), 10, 2);
        
        // Make columns sortable
        add_filter('manage_edit-elementor_library_sortable_columns', array($this, 'sortable_columns'));
        
        // Add "Geo" view/tab
        add_filter('views_edit-elementor_library', array($this, 'add_geo_view'));
        
        // Filter by geo view
        add_filter('parse_query', array($this, 'filter_geo_view'));
        
        // Add quick edit for geo settings
        add_action('quick_edit_custom_box', array($this, 'quick_edit_box'), 10, 2);
        add_action('save_post_elementor_library', array($this, 'save_quick_edit'), 10, 1);
        
        // Add bulk actions
        add_filter('bulk_actions-edit-elementor_library', array($this, 'add_bulk_actions'));
        add_filter('handle_bulk_actions-edit-elementor_library', array($this, 'handle_bulk_actions'), 10, 3);
        
        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        
        error_log('[EGP] Elementor Library Columns initialized');
    }
    
    /**
     * Add custom columns
     */
    public function add_columns($columns) {
        // Insert geo column after title (countries column includes status)
        $new_columns = array();
        
        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;
            
            // Add single geo/countries column after title
            if ($key === 'title') {
                $new_columns['egp_geo_countries'] = '<span class="dashicons dashicons-admin-site" title="Geo Targeting"></span> ' . __('Geo Countries', 'elementor-geo-popup');
            }
        }
        
        return $new_columns;
    }
    
    /**
     * Render custom columns
     */
    public function render_column($column, $post_id) {
        if ($column !== 'egp_geo_countries') {
            return;
        }
        
        $page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
        $geo_enabled = isset($page_settings['egp_geo_enabled']) && $page_settings['egp_geo_enabled'] === 'yes';
        $countries = isset($page_settings['egp_countries']) ? $page_settings['egp_countries'] : array();
        
        if (!$geo_enabled || empty($countries)) {
            echo '<span class="egp-status-badge egp-disabled">—</span>';
            return;
        }
        
        // Show status badge + countries
        echo '<span class="egp-status-badge egp-enabled">🌍</span> ';
        
        if (is_array($countries) && !empty($countries)) {
            $display_countries = array_slice($countries, 0, 3);
            echo '<span class="egp-countries-list">';
            echo esc_html(implode(', ', $display_countries));
            if (count($countries) > 3) {
                echo ' <span class="egp-more-countries" title="' . esc_attr(implode(', ', $countries)) . '">+' . (count($countries) - 3) . '</span>';
            }
            echo '</span>';
        }
    }
    
    /**
     * Make columns sortable
     */
    public function sortable_columns($columns) {
        $columns['egp_geo_countries'] = 'egp_geo_countries';
        return $columns;
    }
    
    /**
     * Add "Geo" view tab
     */
    public function add_geo_view($views) {
        // Count templates with geo enabled
        $count = get_posts(array(
            'post_type' => 'elementor_library',
            'post_status' => 'publish',
            'meta_query' => array(
                array('key' => 'egp_geo_enabled', 'value' => 'yes')
            ),
            'fields' => 'ids',
            'posts_per_page' => -1
        ));
        
        $class = (isset($_GET['geo_view']) && $_GET['geo_view'] === 'enabled') ? 'current' : '';
        
        $views['geo'] = sprintf(
            '<a href="%s" class="%s">%s <span class="count">(%d)</span></a>',
            admin_url('edit.php?post_type=elementor_library&geo_view=enabled'),
            $class,
            '🌍 ' . __('Geo Enabled', 'elementor-geo-popup'),
            count($count)
        );
        
        return $views;
    }
    
    /**
     * Filter by geo view
     */
    public function filter_geo_view($query) {
        global $pagenow, $typenow;
        
        if ($pagenow === 'edit.php' && $typenow === 'elementor_library' && isset($_GET['geo_view']) && $_GET['geo_view'] === 'enabled') {
            $meta_query = array(
                array(
                    'key' => 'egp_geo_enabled',
                    'value' => 'yes',
                    'compare' => '='
                )
            );
            
            $query->set('meta_query', $meta_query);
        }
    }
    
    /**
     * Add quick edit box
     */
    public function quick_edit_box($column_name, $post_type) {
        if ($post_type !== 'elementor_library' || $column_name !== 'egp_geo_countries') {
            return;
        }
        
        ?>
        <fieldset class="inline-edit-col-right inline-edit-egp-geo">
            <div class="inline-edit-col">
                <label class="inline-edit-group">
                    <span class="title">🌍 <?php _e('Geo Targeting', 'elementor-geo-popup'); ?></span>
                    <select name="egp_geo_enabled" class="egp-geo-toggle">
                        <option value="">— <?php _e('No Change', 'elementor-geo-popup'); ?> —</option>
                        <option value="yes"><?php _e('Enable', 'elementor-geo-popup'); ?></option>
                        <option value="no"><?php _e('Disable', 'elementor-geo-popup'); ?></option>
                    </select>
                </label>
                <label class="inline-edit-group egp-countries-group">
                    <span class="title"><?php _e('Target Countries', 'elementor-geo-popup'); ?></span>
                    <span class="description" style="display: block; margin-bottom: 5px;">
                        <?php _e('Hold Ctrl (Cmd on Mac) to select multiple countries', 'elementor-geo-popup'); ?>
                    </span>
                    <select name="egp_countries[]" multiple="multiple" size="8" class="egp-countries-select" style="width: 100%; max-width: 300px;">
                        <?php foreach ($this->get_countries_list() as $code => $name): ?>
                            <option value="<?php echo esc_attr($code); ?>">
                                <?php echo esc_html($code . ' - ' . $name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="description"><?php _e('Leave unselected to keep existing countries', 'elementor-geo-popup'); ?></span>
                </label>
            </div>
        </fieldset>
        <?php
    }
    
    /**
     * Get countries list
     */
    private function get_countries_list() {
        // Try JSON file first
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
        
        // Fallback list (abbreviated for quick edit - full list in template settings)
        return array(
            'US' => 'United States', 'GB' => 'United Kingdom', 'CA' => 'Canada',
            'AU' => 'Australia', 'DE' => 'Germany', 'FR' => 'France', 'IT' => 'Italy',
            'ES' => 'Spain', 'NL' => 'Netherlands', 'BE' => 'Belgium', 'SE' => 'Sweden',
            'NO' => 'Norway', 'DK' => 'Denmark', 'FI' => 'Finland', 'CH' => 'Switzerland',
            'AT' => 'Austria', 'IE' => 'Ireland', 'NZ' => 'New Zealand', 'JP' => 'Japan',
            'KR' => 'South Korea', 'CN' => 'China', 'IN' => 'India', 'BR' => 'Brazil',
            'MX' => 'Mexico', 'AR' => 'Argentina', 'CL' => 'Chile', 'CO' => 'Colombia',
            'PE' => 'Peru', 'VE' => 'Venezuela', 'ZA' => 'South Africa', 'EG' => 'Egypt',
            'NG' => 'Nigeria', 'KE' => 'Kenya', 'MA' => 'Morocco', 'SA' => 'Saudi Arabia',
            'AE' => 'United Arab Emirates', 'IL' => 'Israel', 'TR' => 'Turkey',
            'RU' => 'Russia', 'PL' => 'Poland', 'CZ' => 'Czech Republic', 'HU' => 'Hungary',
            'RO' => 'Romania', 'BG' => 'Bulgaria', 'HR' => 'Croatia', 'SI' => 'Slovenia',
            'SK' => 'Slovakia', 'LT' => 'Lithuania', 'LV' => 'Latvia', 'EE' => 'Estonia',
            'MT' => 'Malta', 'CY' => 'Cyprus', 'GR' => 'Greece', 'PT' => 'Portugal',
            'SG' => 'Singapore', 'MY' => 'Malaysia', 'TH' => 'Thailand', 'ID' => 'Indonesia',
            'PH' => 'Philippines', 'VN' => 'Vietnam', 'PK' => 'Pakistan', 'BD' => 'Bangladesh',
        );
    }
    
    /**
     * Save quick edit
     */
    public function save_quick_edit($post_id) {
        // Security check
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Get current page settings
        $page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
        if (!is_array($page_settings)) {
            $page_settings = array();
        }
        
        // Update geo enabled
        if (isset($_POST['egp_geo_enabled']) && $_POST['egp_geo_enabled'] !== '') {
            $page_settings['egp_geo_enabled'] = sanitize_text_field($_POST['egp_geo_enabled']);
            update_post_meta($post_id, 'egp_geo_enabled', $_POST['egp_geo_enabled']);
        }
        
        // Update countries (from multi-select array)
        if (isset($_POST['egp_countries']) && is_array($_POST['egp_countries']) && !empty($_POST['egp_countries'])) {
            $countries = array_map('sanitize_text_field', $_POST['egp_countries']);
            $countries = array_map('strtoupper', $countries);
            $countries = array_filter($countries);
            
            if (!empty($countries)) {
                $page_settings['egp_countries'] = $countries;
                update_post_meta($post_id, 'egp_countries', $countries);
            }
        }
        
        // Save updated page settings
        update_post_meta($post_id, '_elementor_page_settings', $page_settings);
    }
    
    /**
     * Add bulk actions
     */
    public function add_bulk_actions($actions) {
        $actions['egp_enable_geo'] = __('Enable Geo Targeting', 'elementor-geo-popup');
        $actions['egp_disable_geo'] = __('Disable Geo Targeting', 'elementor-geo-popup');
        return $actions;
    }
    
    /**
     * Handle bulk actions
     */
    public function handle_bulk_actions($redirect_to, $action, $post_ids) {
        if ($action !== 'egp_enable_geo' && $action !== 'egp_disable_geo') {
            return $redirect_to;
        }
        
        $count = 0;
        foreach ($post_ids as $post_id) {
            $page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
            if (!is_array($page_settings)) {
                $page_settings = array();
            }
            
            if ($action === 'egp_enable_geo') {
                $page_settings['egp_geo_enabled'] = 'yes';
                update_post_meta($post_id, 'egp_geo_enabled', 'yes');
            } else {
                $page_settings['egp_geo_enabled'] = 'no';
                delete_post_meta($post_id, 'egp_geo_enabled');
            }
            
            update_post_meta($post_id, '_elementor_page_settings', $page_settings);
            $count++;
        }
        
        $redirect_to = add_query_arg('egp_bulk_action', $action, $redirect_to);
        $redirect_to = add_query_arg('egp_count', $count, $redirect_to);
        
        return $redirect_to;
    }
    
    /**
     * Enqueue admin scripts
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'edit.php' || get_post_type() !== 'elementor_library') {
            return;
        }
        
        wp_enqueue_style('egp-library-columns', 
            plugin_dir_url(dirname(__FILE__)) . 'assets/css/library-columns.css', 
            array(), '1.0.0');
        
        wp_enqueue_script('egp-library-columns',
            plugin_dir_url(dirname(__FILE__)) . 'assets/js/library-columns.js',
            array('jquery', 'inline-edit-post'), '1.0.0', true);
    }
}

// Initialize
EGP_Elementor_Library_Columns::get_instance();

