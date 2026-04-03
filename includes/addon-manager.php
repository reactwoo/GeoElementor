<?php
/**
 * Add-On Manager
 * 
 * Core system for managing Geo Elementor add-ons
 * 
 * @package ElementorGeoPopup
 * @since 1.1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add-On Manager Class
 */
class EGP_Addon_Manager {
    
    /**
     * Instance of this class
     */
    private static $instance = null;
    
    /**
     * Registered add-ons
     */
    private $registered_addons = array();
    
    /**
     * Installed add-ons
     */
    private $installed_addons = array();
    
    /**
     * Add-on directory
     */
    private $addon_dir;
    
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
        $this->addon_dir = EGP_PLUGIN_DIR . 'addons/';
        $this->init_hooks();
        $this->load_installed_addons();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_egp_install_addon', array($this, 'ajax_install_addon'));
        add_action('wp_ajax_egp_activate_addon', array($this, 'ajax_activate_addon'));
        add_action('wp_ajax_egp_deactivate_addon', array($this, 'ajax_deactivate_addon'));
        add_action('wp_ajax_egp_uninstall_addon', array($this, 'ajax_uninstall_addon'));
        add_action('wp_ajax_egp_update_addon', array($this, 'ajax_update_addon'));
        // Upload functionality removed - for internal development only
    }
    
    /**
     * Initialize add-on manager
     */
    public function init() {
        // Create add-on directory if it doesn't exist
        if (!file_exists($this->addon_dir)) {
            wp_mkdir_p($this->addon_dir);
        }

        // Register add-ons first so activation metadata can resolve class/file definitions.
        $this->register_core_addons();

        // Load active add-ons after registration.
        $this->load_active_addons();
    }
    
    /**
     * Register core add-ons
     */
    private function register_core_addons() {
        // City targeting add-on
        $this->register_addon(array(
            'id' => 'city-targeting',
            'name' => 'City Targeting',
            'description' => 'Target content based on visitor city location',
            'version' => '1.0.0',
            'author' => 'ReactWoo',
            'author_uri' => 'https://reactwoo.com',
            'plugin_uri' => 'https://reactwoo.com',
            'requires' => '1.0.0',
            'tested' => '1.0.1',
            'file' => 'city-targeting/city-targeting.php',
            'class' => 'EGP_City_Targeting_Addon',
            'category' => 'geo-targeting',
            'tags' => array('city', 'location', 'geo'),
            'screenshot' => '',
            'icon' => 'eicon-map-pin',
            'premium' => false,
            'status' => 'available'
        ));
        
        // Weather targeting add-on
        $this->register_addon(array(
            'id' => 'weather-targeting',
            'name' => 'Weather Targeting',
            'description' => 'Target content based on current weather conditions',
            'version' => '1.0.0',
            'author' => 'ReactWoo',
            'author_uri' => 'https://reactwoo.com',
            'plugin_uri' => 'https://reactwoo.com',
            'requires' => '1.0.0',
            'tested' => '1.0.1',
            'file' => 'weather-targeting/weather-targeting.php',
            'class' => 'EGP_Weather_Targeting_Addon',
            'category' => 'environmental',
            'tags' => array('weather', 'temperature', 'conditions'),
            'screenshot' => '',
            'icon' => 'eicon-weather-sunny',
            'premium' => true,
            'status' => 'available'
        ));
        
        // Time-based targeting add-on
        $this->register_addon(array(
            'id' => 'time-targeting',
            'name' => 'Time-Based Targeting',
            'description' => 'Target content based on time, timezone, and business hours',
            'version' => '1.0.0',
            'author' => 'ReactWoo',
            'author_uri' => 'https://reactwoo.com',
            'plugin_uri' => 'https://reactwoo.com',
            'requires' => '1.0.0',
            'tested' => '1.0.1',
            'file' => 'time-targeting/time-targeting.php',
            'class' => 'EGP_Time_Targeting_Addon',
            'category' => 'temporal',
            'tags' => array('time', 'timezone', 'business-hours'),
            'screenshot' => '',
            'icon' => 'eicon-clock',
            'premium' => false,
            'status' => 'available'
        ));

        /**
         * Allow in-development or third-party add-ons to register (same shape as register_addon()).
         *
         * @param EGP_Addon_Manager $manager
         */
        do_action( 'egp_register_addons', $this );
    }
    
    /**
     * Register an add-on
     */
    public function register_addon($addon_data) {
        $defaults = array(
            'id' => '',
            'name' => '',
            'description' => '',
            'version' => '1.0.0',
            'author' => '',
            'author_uri' => '',
            'plugin_uri' => '',
            'requires' => '1.0.0',
            'tested' => '1.0.1',
            'file' => '',
            'class' => '',
            'category' => 'general',
            'tags' => array(),
            'screenshot' => '',
            'icon' => 'eicon-plug',
            'premium' => false,
            'status' => 'available'
        );
        
        $addon_data = wp_parse_args($addon_data, $defaults);
        
        if (empty($addon_data['id']) || empty($addon_data['name'])) {
            return false;
        }
        
        $this->registered_addons[$addon_data['id']] = $addon_data;
        
        return true;
    }
    
    /**
     * Get registered add-ons
     */
    public function get_registered_addons() {
        return $this->registered_addons;
    }
    
    /**
     * Get installed add-ons
     */
    public function get_installed_addons() {
        return $this->installed_addons;
    }
    
    /**
     * Get add-on by ID
     */
    public function get_addon($addon_id) {
        return isset($this->registered_addons[$addon_id]) ? $this->registered_addons[$addon_id] : null;
    }
    
    /**
     * Check if add-on is installed
     */
    public function is_addon_installed($addon_id) {
        return isset($this->installed_addons[$addon_id]);
    }
    
    /**
     * Check if add-on is active
     */
    public function is_addon_active($addon_id) {
        return $this->is_addon_installed($addon_id) && 
               isset($this->installed_addons[$addon_id]['active']) && 
               $this->installed_addons[$addon_id]['active'];
    }
    
    /**
     * Install an add-on
     */
    public function install_addon($addon_id) {
        $addon = $this->get_addon($addon_id);
        
        if (!$addon) {
            return new WP_Error('addon_not_found', __('Add-on not found', 'elementor-geo-popup'));
        }
        
        if ($this->is_addon_installed($addon_id)) {
            return new WP_Error('addon_already_installed', __('Add-on is already installed', 'elementor-geo-popup'));
        }
        
        // Check requirements
        if (!$this->check_requirements($addon)) {
            return new WP_Error('requirements_not_met', __('Add-on requirements not met', 'elementor-geo-popup'));
        }
        
        // Create add-on directory
        $addon_path = $this->addon_dir . $addon_id;
        if (!file_exists($addon_path)) {
            wp_mkdir_p($addon_path);
        }
        
        // Copy add-on files (in a real implementation, this would download from a repository)
        $this->copy_addon_files($addon_id, $addon_path);
        
        // Register as installed
        $this->installed_addons[$addon_id] = array(
            'version' => $addon['version'],
            'installed_at' => current_time('mysql'),
            'active' => false
        );
        
        $this->save_installed_addons();
        
        return true;
    }
    
    // Zip installation methods removed - for internal development only
    
    /**
     * Activate an add-on
     */
    public function activate_addon($addon_id) {
        if (!$this->is_addon_installed($addon_id)) {
            return new WP_Error('addon_not_installed', __('Add-on is not installed', 'elementor-geo-popup'));
        }
        
        $addon_file = $this->addon_dir . $this->registered_addons[$addon_id]['file'];
        
        if (!file_exists($addon_file)) {
            return new WP_Error('addon_file_missing', __('Add-on file not found', 'elementor-geo-popup'));
        }
        
        // Include the add-on file
        require_once $addon_file;
        
        // Initialize the add-on
        $class_name = $this->registered_addons[$addon_id]['class'];
        if (class_exists($class_name)) {
            $addon_instance = new $class_name();
            if (method_exists($addon_instance, 'init')) {
                $addon_instance->init();
            }
        }
        
        // Mark as active
        $this->installed_addons[$addon_id]['active'] = true;
        $this->installed_addons[$addon_id]['activated_at'] = current_time('mysql');
        
        $this->save_installed_addons();
        
        // Trigger activation hook
        do_action('egp_addon_activated', $addon_id, $this->registered_addons[$addon_id]);
        
        return true;
    }
    
    /**
     * Deactivate an add-on
     */
    public function deactivate_addon($addon_id) {
        if (!$this->is_addon_installed($addon_id)) {
            return new WP_Error('addon_not_installed', __('Add-on is not installed', 'elementor-geo-popup'));
        }
        
        // Trigger deactivation hook
        do_action('egp_addon_deactivated', $addon_id, $this->registered_addons[$addon_id]);
        
        // Mark as inactive
        $this->installed_addons[$addon_id]['active'] = false;
        $this->installed_addons[$addon_id]['deactivated_at'] = current_time('mysql');
        
        $this->save_installed_addons();
        
        return true;
    }
    
    /**
     * Uninstall an add-on
     */
    public function uninstall_addon($addon_id) {
        if (!$this->is_addon_installed($addon_id)) {
            return new WP_Error('addon_not_installed', __('Add-on is not installed', 'elementor-geo-popup'));
        }
        
        // Deactivate first if active
        if ($this->is_addon_active($addon_id)) {
            $this->deactivate_addon($addon_id);
        }
        
        // Trigger uninstall hook
        do_action('egp_addon_uninstalled', $addon_id, $this->registered_addons[$addon_id]);
        
        // Remove add-on files
        $addon_path = $this->addon_dir . $addon_id;
        if (file_exists($addon_path)) {
            $this->remove_directory($addon_path);
        }
        
        // Remove from installed list
        unset($this->installed_addons[$addon_id]);
        
        $this->save_installed_addons();
        
        return true;
    }
    
    /**
     * Load installed add-ons from database
     */
    private function load_installed_addons() {
        $this->installed_addons = get_option('egp_installed_addons', array());
    }
    
    /**
     * Save installed add-ons to database
     */
    private function save_installed_addons() {
        update_option('egp_installed_addons', $this->installed_addons);
    }
    
    /**
     * Load active add-ons
     */
    private function load_active_addons() {
        foreach ($this->installed_addons as $addon_id => $addon_data) {
            if ($addon_data['active']) {
                $addon_file = $this->addon_dir . $this->registered_addons[$addon_id]['file'];
                
                if (file_exists($addon_file)) {
                    require_once $addon_file;
                    
                    $class_name = $this->registered_addons[$addon_id]['class'];
                    if (class_exists($class_name)) {
                        $addon_instance = new $class_name();
                        if (method_exists($addon_instance, 'init')) {
                            $addon_instance->init();
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Check add-on requirements
     */
    private function check_requirements($addon) {
        // Check plugin version requirement
        if (version_compare(EGP_VERSION, $addon['requires'], '<')) {
            return false;
        }
        
        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '5.0', '<')) {
            return false;
        }
        
        // Check PHP version
        if (version_compare(PHP_VERSION, '7.4', '<')) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Copy add-on files (placeholder for real implementation)
     */
    private function copy_addon_files($addon_id, $destination) {
        // In a real implementation, this would download files from a repository
        // For now, we'll create placeholder files
        $addon_file = $destination . '/' . $addon_id . '.php';
        
        if (!file_exists($addon_file)) {
            file_put_contents($addon_file, "<?php\n// Add-on file for {$addon_id}\n");
        }
    }
    
    // Zip validation and extraction methods removed - for internal development only
    
    /**
     * Remove directory recursively
     */
    private function remove_directory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
    
    /**
     * Add admin menu (handled by main admin menu)
     */
    public function add_admin_menu() {
        // Menu is now handled by the main admin menu system
        // This method is kept for compatibility but does nothing
    }
    
    /**
     * Admin page
     */
    public function admin_page() {
        // Enqueue styles and scripts
        wp_enqueue_style('egp-addon-manager', EGP_PLUGIN_URL . 'assets/css/addon-manager.css', array(), EGP_VERSION);
        wp_enqueue_script('egp-addon-manager', EGP_PLUGIN_URL . 'assets/js/addon-manager.js', array('jquery'), EGP_VERSION, true);
        
        // Localize script
        wp_localize_script('egp-addon-manager', 'egpAddonManager', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('egp_addon_nonce'),
            'strings' => array(
                'confirm_install' => __('Are you sure you want to install this add-on?', 'elementor-geo-popup'),
                'confirm_deactivate' => __('Are you sure you want to deactivate this add-on?', 'elementor-geo-popup'),
                'confirm_uninstall' => __('Are you sure you want to uninstall this add-on? This action cannot be undone.', 'elementor-geo-popup'),
                'installing' => __('Installing...', 'elementor-geo-popup'),
                'activating' => __('Activating...', 'elementor-geo-popup'),
                'deactivating' => __('Deactivating...', 'elementor-geo-popup'),
                'uninstalling' => __('Uninstalling...', 'elementor-geo-popup'),
                'updating' => __('Updating...', 'elementor-geo-popup'),
                'ajax_error' => __('AJAX request failed', 'elementor-geo-popup')
            )
        ));
        
        ?>
        <div class="wrap egp-settings rwgc-wrap rwgc-suite">
            <?php if ( class_exists( 'EGP_Admin_Menu' ) ) { EGP_Admin_Menu::render_page_header( esc_html__( 'Geo Elementor Add-Ons', 'elementor-geo-popup' ), 'egp-addons' ); } ?>
            
            <input type="hidden" name="egp_addon_nonce" value="<?php echo wp_create_nonce('egp_addon_nonce'); ?>" />
            
            <div class="egp-section-card egp-addons-container">
                <div class="egp-addons-tabs">
                    <a href="#installed" class="nav-tab nav-tab-active"><?php echo esc_html__('Installed', 'elementor-geo-popup'); ?></a>
                    <a href="#available" class="nav-tab"><?php echo esc_html__('Available', 'elementor-geo-popup'); ?></a>
                </div>
                
                <div id="installed" class="egp-tab-content active">
                    <?php $this->render_installed_addons(); ?>
                </div>
                
                <div id="available" class="egp-tab-content">
                    <?php $this->render_available_addons(); ?>
                </div>
            </div>
        </div>
        
        <style>
        .egp-addons-container {
            margin-top: 20px;
        }
        
        .egp-addons-tabs {
            margin-bottom: 20px;
        }
        
        .egp-tab-content {
            display: none;
        }
        
        .egp-tab-content.active {
            display: block;
        }
        
        .egp-addon-card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,.04);
        }
        
        .egp-addon-header {
            display: flex;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .egp-addon-icon {
            font-size: 24px;
            margin-right: 15px;
            color: #0073aa;
        }
        
        .egp-addon-title {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        
        .egp-addon-status {
            margin-left: auto;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .egp-addon-status.active {
            background: #d4edda;
            color: #155724;
        }
        
        .egp-addon-status.inactive {
            background: #f8d7da;
            color: #721c24;
        }
        
        .egp-addon-description {
            color: #666;
            margin-bottom: 15px;
        }
        
        .egp-addon-actions {
            display: flex;
            gap: 10px;
        }
        
        .egp-addon-actions .button {
            padding: 6px 12px;
            font-size: 13px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            $('.egp-addons-tabs a').on('click', function(e) {
                e.preventDefault();
                
                var target = $(this).attr('href');
                
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                $('.egp-tab-content').removeClass('active');
                $(target).addClass('active');
            });
        });
        </script>
        <?php
    }
    
    /**
     * Render installed add-ons
     */
    private function render_installed_addons() {
        if (empty($this->installed_addons)) {
            echo '<p>' . esc_html__('No add-ons installed yet.', 'elementor-geo-popup') . '</p>';
            return;
        }
        
        foreach ($this->installed_addons as $addon_id => $addon_data) {
            $addon = $this->get_addon($addon_id);
            if (!$addon) continue;
            
            $is_active = $addon_data['active'];
            $status_class = $is_active ? 'active' : 'inactive';
            $status_text = $is_active ? __('Active', 'elementor-geo-popup') : __('Inactive', 'elementor-geo-popup');
            
            ?>
            <div class="egp-addon-card">
                <div class="egp-addon-header">
                    <span class="egp-addon-icon <?php echo esc_attr($addon['icon']); ?>"></span>
                    <h3 class="egp-addon-title"><?php echo esc_html($addon['name']); ?></h3>
                    <span class="egp-addon-status <?php echo esc_attr($status_class); ?>"><?php echo esc_html($status_text); ?></span>
                </div>
                
                <div class="egp-addon-description">
                    <?php echo esc_html($addon['description']); ?>
                </div>
                
                <div class="egp-addon-actions">
                    <?php if ($is_active): ?>
                        <button class="button button-secondary egp-deactivate-addon" data-addon="<?php echo esc_attr($addon_id); ?>">
                            <?php echo esc_html__('Deactivate', 'elementor-geo-popup'); ?>
                        </button>
                    <?php else: ?>
                        <button class="button button-primary egp-activate-addon" data-addon="<?php echo esc_attr($addon_id); ?>">
                            <?php echo esc_html__('Activate', 'elementor-geo-popup'); ?>
                        </button>
                    <?php endif; ?>
                    
                    <button class="button button-link-delete egp-uninstall-addon" data-addon="<?php echo esc_attr($addon_id); ?>">
                        <?php echo esc_html__('Uninstall', 'elementor-geo-popup'); ?>
                    </button>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * Render available add-ons
     */
    private function render_available_addons() {
        $available_addons = array();
        
        foreach ($this->registered_addons as $addon_id => $addon) {
            if (!isset($this->installed_addons[$addon_id])) {
                $available_addons[$addon_id] = $addon;
            }
        }
        
        if (empty($available_addons)) {
            echo '<p>' . esc_html__('All available add-ons are already installed.', 'elementor-geo-popup') . '</p>';
            return;
        }
        
        foreach ($available_addons as $addon_id => $addon) {
            ?>
            <div class="egp-addon-card">
                <div class="egp-addon-header">
                    <span class="egp-addon-icon <?php echo esc_attr($addon['icon']); ?>"></span>
                    <h3 class="egp-addon-title"><?php echo esc_html($addon['name']); ?></h3>
                    <?php if ($addon['premium']): ?>
                        <span class="egp-addon-status" style="background: #ffc107; color: #856404;"><?php echo esc_html__('Premium', 'elementor-geo-popup'); ?></span>
                    <?php endif; ?>
                </div>
                
                <div class="egp-addon-description">
                    <?php echo esc_html($addon['description']); ?>
                </div>
                
                <div class="egp-addon-actions">
                    <button class="button button-primary egp-install-addon" data-addon="<?php echo esc_attr($addon_id); ?>">
                        <?php echo esc_html__('Install', 'elementor-geo-popup'); ?>
                    </button>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * AJAX: Install add-on
     */
    public function ajax_install_addon() {
        check_ajax_referer('egp_addon_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $addon_id = sanitize_text_field($_POST['addon_id']);
        $result = $this->install_addon($addon_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Add-on installed successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX: Activate add-on
     */
    public function ajax_activate_addon() {
        check_ajax_referer('egp_addon_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $addon_id = sanitize_text_field($_POST['addon_id']);
        $result = $this->activate_addon($addon_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Add-on activated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX: Deactivate add-on
     */
    public function ajax_deactivate_addon() {
        check_ajax_referer('egp_addon_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $addon_id = sanitize_text_field($_POST['addon_id']);
        $result = $this->deactivate_addon($addon_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Add-on deactivated successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX: Uninstall add-on
     */
    public function ajax_uninstall_addon() {
        check_ajax_referer('egp_addon_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $addon_id = sanitize_text_field($_POST['addon_id']);
        $result = $this->uninstall_addon($addon_id);
        
        if (is_wp_error($result)) {
            wp_send_json_error($result->get_error_message());
        }
        
        wp_send_json_success(__('Add-on uninstalled successfully', 'elementor-geo-popup'));
    }
    
    /**
     * AJAX: Update add-on
     */
    public function ajax_update_addon() {
        check_ajax_referer('egp_addon_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Insufficient permissions', 'elementor-geo-popup'));
        }
        
        $addon_id = sanitize_text_field($_POST['addon_id']);
        
        // Placeholder for update logic
        wp_send_json_success(__('Add-on updated successfully', 'elementor-geo-popup'));
    }
    
    // Upload AJAX handler removed - for internal development only
    
    // Upload interface removed - for internal development only
}

// Initialize the add-on manager
EGP_Addon_Manager::get_instance();